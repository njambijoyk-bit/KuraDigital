<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opponents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('party')->nullable();
            $table->string('position')->nullable();
            $table->string('county')->nullable();
            $table->string('constituency')->nullable();
            $table->string('ward')->nullable();
            $table->enum('threat_level', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->text('bio')->nullable();
            $table->text('strengths')->nullable();
            $table->text('weaknesses')->nullable();
            $table->string('photo_url')->nullable();
            $table->string('social_facebook')->nullable();
            $table->string('social_twitter')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['campaign_id', 'threat_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opponents');
    }
};
