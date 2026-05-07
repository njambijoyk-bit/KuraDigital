<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\PollingStation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Import IEBC polling station data from a CSV file into a campaign.
 *
 * Expected CSV columns: code, name, ward, constituency, county, registered_voters, latitude, longitude
 *
 * Usage:
 *   php artisan iebc:import {campaign_id} {csv_path} [--dry-run]
 *
 * Example CSV:
 *   code,name,ward,constituency,county,registered_voters,latitude,longitude
 *   001/001/001,"KILELESHWA PRIMARY SCHOOL",Kileleshwa,Dagoretti North,Nairobi,754,-1.2921,36.7746
 */
class ImportIebcStations extends Command
{
    protected $signature = 'iebc:import
                            {campaign : Campaign ID to import stations into}
                            {file : Path to IEBC CSV file}
                            {--dry-run : Preview without saving}
                            {--skip-duplicates : Skip stations with existing codes instead of updating}';

    protected $description = 'Import IEBC polling station register into a campaign';

    public function handle(): int
    {
        $campaignId = $this->argument('campaign');
        $filePath = $this->argument('file');
        $dryRun = $this->option('dry-run');
        $skipDuplicates = $this->option('skip-duplicates');

        $campaign = Campaign::find($campaignId);
        if (!$campaign) {
            $this->error("Campaign #{$campaignId} not found.");
            return self::FAILURE;
        }

        if (!file_exists($filePath) || !is_readable($filePath)) {
            $this->error("File not found or not readable: {$filePath}");
            return self::FAILURE;
        }

        $handle = fopen($filePath, 'r');
        $header = fgetcsv($handle);

        if (!$header) {
            $this->error('Empty CSV file.');
            fclose($handle);
            return self::FAILURE;
        }

        $header = array_map(fn ($h) => strtolower(trim($h)), $header);
        $requiredColumns = ['name'];
        foreach ($requiredColumns as $col) {
            if (!in_array($col, $header)) {
                $this->error("Required column '{$col}' not found. Available: " . implode(', ', $header));
                fclose($handle);
                return self::FAILURE;
            }
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;
        $row = 1;

        $this->info("Importing IEBC stations into Campaign #{$campaignId}: {$campaign->name}");
        if ($dryRun) {
            $this->warn('DRY RUN — no data will be saved.');
        }

        $bar = $this->output->createProgressBar();
        $bar->start();

        DB::beginTransaction();

        try {
            while (($data = fgetcsv($handle)) !== false) {
                $row++;
                $bar->advance();

                if (count($data) !== count($header)) {
                    $this->newLine();
                    $this->warn("Row {$row}: column count mismatch, skipping.");
                    $errors++;
                    continue;
                }

                $record = array_combine($header, $data);
                $name = trim($record['name'] ?? '');
                $code = trim($record['code'] ?? '');

                if (empty($name)) {
                    $errors++;
                    continue;
                }

                $stationData = [
                    'campaign_id' => $campaignId,
                    'created_by' => $campaign->owner_id ?? 1,
                    'name' => $name,
                    'code' => $code ?: null,
                    'ward' => trim($record['ward'] ?? '') ?: null,
                    'constituency' => trim($record['constituency'] ?? '') ?: null,
                    'county' => trim($record['county'] ?? '') ?: null,
                    'registered_voters' => (int) ($record['registered_voters'] ?? 0),
                    'latitude' => !empty($record['latitude']) ? (float) $record['latitude'] : null,
                    'longitude' => !empty($record['longitude']) ? (float) $record['longitude'] : null,
                    'status' => 'pending',
                ];

                if (!$dryRun) {
                    $existing = $code
                        ? PollingStation::where('campaign_id', $campaignId)->where('code', $code)->first()
                        : null;

                    if ($existing) {
                        if ($skipDuplicates) {
                            $skipped++;
                            continue;
                        }
                        $existing->update($stationData);
                        $updated++;
                    } else {
                        PollingStation::create($stationData);
                        $created++;
                    }
                } else {
                    $created++;
                }
            }

            if (!$dryRun) {
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->newLine();
            $this->error("Import failed at row {$row}: {$e->getMessage()}");
            fclose($handle);
            return self::FAILURE;
        }

        fclose($handle);
        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Created', $created],
                ['Updated', $updated],
                ['Skipped (duplicates)', $skipped],
                ['Errors', $errors],
                ['Total rows processed', $row - 1],
            ]
        );

        if ($dryRun) {
            $this->warn('This was a dry run. Run without --dry-run to save.');
        } else {
            $this->info('Import complete!');
        }

        return self::SUCCESS;
    }
}
