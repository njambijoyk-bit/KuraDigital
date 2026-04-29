<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opponent_research', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opponent_profile_id')->constrained('opponent_profiles')->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('content');
            $table->string('source_url')->nullable();
            $table->string('source_name')->nullable();
            $table->enum('category', [
                'policy_position', 'public_statement', 'scandal', 'voting_record',
                'financial', 'media_coverage', 'legal', 'other',
            ])->default('other');
            $table->enum('clearance_level', ['public', 'internal', 'confidential', 'restricted'])->default('internal');
            $table->date('date_published')->nullable();
            $table->string('attached_media_url')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['opponent_profile_id', 'category']);
            $table->index(['campaign_id', 'clearance_level']);
            $table->index(['campaign_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opponent_research');
    }
};
