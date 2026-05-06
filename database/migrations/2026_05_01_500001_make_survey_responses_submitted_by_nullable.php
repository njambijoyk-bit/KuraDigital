<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // SQLite: rebuild table with nullable submitted_by
            // Drop old indexes first to avoid name collision
            Schema::rename('survey_responses', 'survey_responses_old');

            // Drop indexes from old table
            try {
                DB::statement('DROP INDEX IF EXISTS survey_responses_survey_id_index');
                DB::statement('DROP INDEX IF EXISTS survey_responses_submitted_by_index');
                DB::statement('DROP INDEX IF EXISTS survey_responses_ward_index');
            } catch (\Exception $e) {
                // Ignore
            }

            Schema::create('survey_responses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('survey_id')->constrained('surveys')->cascadeOnDelete();
                $table->unsignedBigInteger('submitted_by')->nullable();
                $table->json('answers');
                $table->string('ward')->nullable();
                $table->string('constituency')->nullable();
                $table->string('county')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->timestamps();

                $table->index('survey_id');
                $table->index('submitted_by');
                $table->index('ward');
            });

            DB::statement('INSERT INTO survey_responses SELECT * FROM survey_responses_old');
            Schema::drop('survey_responses_old');
        } else {
            DB::statement('ALTER TABLE survey_responses MODIFY submitted_by BIGINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver !== 'sqlite') {
            DB::statement('ALTER TABLE survey_responses MODIFY submitted_by BIGINT UNSIGNED NOT NULL');
        }
    }
};
