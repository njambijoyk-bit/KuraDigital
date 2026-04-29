<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opponent_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('party')->nullable();
            $table->string('position')->nullable();
            $table->string('county')->nullable();
            $table->string('constituency')->nullable();
            $table->string('ward')->nullable();
            $table->string('photo_url')->nullable();
            $table->text('bio')->nullable();
            $table->text('strengths_summary')->nullable();
            $table->text('weaknesses_summary')->nullable();
            $table->enum('threat_level', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['campaign_id', 'threat_level']);
            $table->index(['campaign_id', 'party']);
            $table->index(['campaign_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opponent_profiles');
    }
};
