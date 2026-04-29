<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            $table->enum('status', ['draft', 'published', 'scheduled'])->default('draft')->after('is_published');
            $table->timestamp('scheduled_at')->nullable()->after('status');
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete()->after('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            $table->dropForeign(['author_id']);
            $table->dropColumn(['status', 'scheduled_at', 'author_id']);
        });
    }
};
