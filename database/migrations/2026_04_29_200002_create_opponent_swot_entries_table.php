<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opponent_swot_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opponent_profile_id')->constrained('opponent_profiles')->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['strength', 'weakness', 'opportunity', 'threat']);
            $table->text('description');
            $table->enum('impact_level', ['low', 'medium', 'high'])->default('medium');
            $table->string('source')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['opponent_profile_id', 'type']);
            $table->index(['campaign_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opponent_swot_entries');
    }
};
