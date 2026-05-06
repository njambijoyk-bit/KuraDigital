<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('field_agent_id')->constrained('field_agents')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('shift_type')->default('full_day'); // morning, afternoon, evening, night, full_day, custom
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('ward')->nullable();
            $table->string('constituency')->nullable();
            $table->string('county')->nullable();
            $table->string('polling_station')->nullable();
            $table->string('status')->default('scheduled'); // scheduled, in_progress, completed, cancelled, no_show
            $table->text('notes')->nullable();
            $table->foreignId('assigned_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();

            $table->index('campaign_id');
            $table->index('field_agent_id');
            $table->index('date');
            $table->index('status');
            $table->index('ward');
            $table->index('shift_type');
            $table->index(['campaign_id', 'date']);
            $table->index(['campaign_id', 'field_agent_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_schedules');
    }
};
