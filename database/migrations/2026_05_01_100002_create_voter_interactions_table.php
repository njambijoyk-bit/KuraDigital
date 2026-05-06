<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voter_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('voter_id')->constrained('voters')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('canvassing_assignments')->nullOnDelete();
            $table->enum('interaction_type', [
                'door_knock', 'phone_call', 'rally', 'community_meeting',
                'market_visit', 'church_visit', 'other',
            ]);
            $table->enum('outcome', [
                'contacted', 'not_home', 'refused', 'supportive',
                'undecided', 'hostile', 'moved', 'other',
            ]);
            $table->text('notes')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('ward')->nullable();
            $table->string('constituency')->nullable();
            $table->string('county')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->timestamps();

            $table->index('campaign_id');
            $table->index('voter_id');
            $table->index('agent_id');
            $table->index('assignment_id');
            $table->index('outcome');
            $table->index('ward');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voter_interactions');
    }
};
