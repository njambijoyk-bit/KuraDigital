<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreignId('organisation_id')->nullable()->after('id')
                ->constrained('organisations')->nullOnDelete();
        });

        $this->grandfatherExistingCampaigns();
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organisation_id');
        });
    }

    /**
     * Installs that predate licensing keep working: every existing campaign
     * hierarchy gets its own organisation with every module for one term.
     * Fresh installs have no campaigns yet, so this does nothing for them.
     */
    private function grandfatherExistingCampaigns(): void
    {
        $campaigns = DB::table('campaigns')->orderBy('id')->get(['id', 'parent_id', 'name']);
        if ($campaigns->isEmpty()) {
            return;
        }

        $byId = $campaigns->keyBy('id');
        $modules = array_keys(config('licensing.modules', []));
        $months = (int) config('licensing.term_months', 12);
        $now = now();
        $orgByRoot = [];

        foreach ($campaigns as $campaign) {
            $root = $campaign;
            $guard = 0;
            while ($root->parent_id && $byId->has($root->parent_id) && $guard++ < 50) {
                $root = $byId[$root->parent_id];
            }

            if (!isset($orgByRoot[$root->id])) {
                $orgId = DB::table('organisations')->insertGetId([
                    'name' => $root->name,
                    'slug' => Str::slug($root->name) . '-' . Str::lower(Str::random(6)),
                    'status' => 'active',
                    'notes' => 'Created automatically when licensing was introduced.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $orgByRoot[$root->id] = $orgId;

                foreach ($modules as $module) {
                    DB::table('organisation_modules')->insert([
                        'organisation_id' => $orgId,
                        'module' => $module,
                        'status' => 'active',
                        'starts_at' => $now,
                        'expires_at' => $now->copy()->addMonths($months),
                        'notes' => 'Grandfathered',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            DB::table('campaigns')->where('id', $campaign->id)
                ->update(['organisation_id' => $orgByRoot[$root->id]]);
        }
    }
};
