<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('title_sw')->nullable()->after('title');
            $table->text('description_sw')->nullable()->after('description');
        });

        Schema::table('news_articles', function (Blueprint $table) {
            $table->string('title_sw')->nullable()->after('title');
            $table->text('excerpt_sw')->nullable()->after('excerpt');
            $table->longText('body_sw')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['title_sw', 'description_sw']);
        });

        Schema::table('news_articles', function (Blueprint $table) {
            $table->dropColumn(['title_sw', 'excerpt_sw', 'body_sw']);
        });
    }
};
