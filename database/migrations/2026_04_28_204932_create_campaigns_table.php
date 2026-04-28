<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('candidate_name');
            $table->string('position')->default('Member of Parliament');
            $table->string('constituency')->nullable();
            $table->string('county')->nullable();
            $table->string('party')->nullable();
            $table->string('election_year')->default('2027');
            $table->string('slogan')->nullable();
            $table->string('primary_color')->default('#16a34a');
            $table->string('secondary_color')->default('#0f172a');
            $table->string('logo_url')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'active', 'paused', 'completed'])->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
