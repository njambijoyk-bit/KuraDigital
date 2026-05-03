<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type'); // voter_summary, field_ops, finance, election_day, strategy, campaign_overview
            $table->json('parameters')->nullable();
            $table->json('data')->nullable();
            $table->string('format')->default('json'); // json, csv
            $table->string('status')->default('completed'); // pending, completed, failed
            $table->timestamps();
            $table->index(['campaign_id', 'type']);
            $table->index(['campaign_id', 'created_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
