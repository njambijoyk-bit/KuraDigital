<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('election_day_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('severity', 20)->default('info');
            $table->text('message');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['campaign_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('election_day_activities');
    }
};
