<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('canvassing_assignments')->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('location_name')->nullable();
            $table->string('ward')->nullable();
            $table->string('constituency')->nullable();
            $table->string('county')->nullable();
            $table->enum('check_in_type', [
                'start_shift', 'end_shift', 'break', 'location_update', 'incident',
            ])->default('location_update');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('campaign_id');
            $table->index('user_id');
            $table->index('ward');
            $table->index('check_in_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_check_ins');
    }
};
