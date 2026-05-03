<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('field_agent_id')->nullable()->constrained('field_agents')->nullOnDelete();
            $table->string('type'); // photo, video, audio, text
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('ward')->nullable();
            $table->string('constituency')->nullable();
            $table->string('county')->nullable();
            $table->string('status')->default('submitted'); // draft, submitted, processing, processed, flagged
            $table->timestamp('captured_at')->nullable();
            $table->string('client_id', 100)->nullable();
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'client_id']);
            $table->index('campaign_id');
            $table->index('user_id');
            $table->index('type');
            $table->index('status');
            $table->index('ward');
            $table->index('constituency');
            $table->index('county');
            $table->index('captured_at');
        });

        Schema::create('field_report_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_report_id')->constrained('field_reports')->cascadeOnDelete();
            $table->string('filename');
            $table->string('original_filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('url')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->unsignedInteger('duration')->nullable(); // seconds for audio/video
            $table->string('processing_status')->default('pending'); // pending, processing, completed, failed
            $table->json('processing_result')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('field_report_id');
            $table->index('processing_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_report_media');
        Schema::dropIfExists('field_reports');
    }
};
