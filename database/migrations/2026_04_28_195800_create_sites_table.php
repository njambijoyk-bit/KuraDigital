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
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('candidate_name');
            $table->string('position')->default('Member of Parliament');
            $table->string('constituency')->nullable();
            $table->string('county')->nullable();
            $table->string('party')->nullable();
            $table->string('slogan')->nullable();
            $table->string('slogan_sw')->nullable();

            $table->string('primary_color')->default('#16a34a');
            $table->string('secondary_color')->default('#0f172a');
            $table->string('logo_url')->nullable();
            $table->string('portrait_url')->nullable();
            $table->string('hero_image_url')->nullable();
            $table->string('about_image_url')->nullable();

            $table->text('bio_summary')->nullable();
            $table->text('bio_summary_sw')->nullable();
            $table->longText('bio_full')->nullable();
            $table->longText('education')->nullable();
            $table->longText('experience')->nullable();
            $table->json('pillars')->nullable();
            $table->json('milestones')->nullable();

            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('office_address')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('twitter_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('tiktok_url')->nullable();
            $table->string('youtube_url')->nullable();

            $table->text('donation_info')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
