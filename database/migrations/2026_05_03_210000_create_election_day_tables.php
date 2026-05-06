<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polling_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('ward')->nullable();
            $table->string('constituency')->nullable();
            $table->string('county')->nullable();
            $table->integer('registered_voters')->default(0);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status')->default('pending'); // pending, open, closed, disputed
            $table->foreignId('assigned_agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['campaign_id', 'ward']);
            $table->index(['campaign_id', 'constituency']);
            $table->index(['campaign_id', 'county']);
            $table->index(['campaign_id', 'status']);
            $table->unique(['campaign_id', 'code']);
        });

        Schema::create('tally_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('polling_station_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->string('candidate_name');
            $table->string('party')->nullable();
            $table->integer('votes')->default(0);
            $table->integer('rejected_votes')->default(0);
            $table->integer('total_votes_cast')->default(0);
            $table->string('status')->default('provisional'); // provisional, verified, disputed
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('photo_proof')->nullable();
            $table->timestamps();
            $table->index(['campaign_id', 'polling_station_id']);
            $table->index(['campaign_id', 'status']);
            $table->index(['campaign_id', 'candidate_name']);
        });

        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('polling_station_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('category'); // violence, irregularity, voter_intimidation, equipment_failure, procedural, other
            $table->string('severity')->default('medium'); // low, medium, high, critical
            $table->string('status')->default('reported'); // reported, acknowledged, investigating, resolved, escalated
            $table->string('ward')->nullable();
            $table->string('constituency')->nullable();
            $table->string('county')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('photos')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
            $table->index(['campaign_id', 'category']);
            $table->index(['campaign_id', 'severity']);
            $table->index(['campaign_id', 'status']);
            $table->index(['campaign_id', 'polling_station_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('tally_results');
        Schema::dropIfExists('polling_stations');
    }
};
