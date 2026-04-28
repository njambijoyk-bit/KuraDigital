<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('level', ['national', 'county', 'constituency', 'ward']);
            $table->enum('election_type', [
                'presidential', 'gubernatorial', 'senatorial',
                'woman_rep', 'parliamentary', 'mca', 'other',
            ])->default('parliamentary');
            $table->string('county')->nullable();
            $table->string('constituency')->nullable();
            $table->string('ward')->nullable();
            $table->string('party')->nullable();
            $table->string('coalition')->nullable();
            $table->enum('election_phase', [
                'pre_campaign', 'campaign', 'e_day', 'post_election',
            ])->default('pre_campaign');
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('level');
            $table->index('election_phase');
            $table->index('county');
            $table->index('constituency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
