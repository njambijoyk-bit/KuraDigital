<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->enum('visibility_scope', ['own_campaign', 'county', 'national'])
                ->default('own_campaign');
            $table->json('assigned_wards')->nullable();
            $table->json('assigned_constituencies')->nullable();
            $table->json('assigned_counties')->nullable();
            $table->json('assigned_polling_stations')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'campaign_id']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_members');
    }
};
