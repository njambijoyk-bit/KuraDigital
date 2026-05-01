<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('national_id')->nullable();
            $table->string('email')->nullable();
            $table->enum('supporter_status', [
                'supporter', 'leaning', 'undecided', 'opposition', 'unknown',
            ])->default('unknown');
            $table->enum('source', [
                'walk_in', 'field_agent', 'import', 'event', 'referral', 'online', 'other',
            ])->default('other');
            $table->string('county')->nullable();
            $table->string('constituency')->nullable();
            $table->string('ward')->nullable();
            $table->string('polling_station')->nullable();
            $table->json('tags')->nullable();
            $table->text('notes')->nullable();
            $table->string('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamps();

            $table->index('campaign_id');
            $table->index('supporter_status');
            $table->index('county');
            $table->index('constituency');
            $table->index('ward');
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voters');
    }
};
