<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opponent_research', function (Blueprint $table) {
            $table->decimal('sentiment_score', 4, 2)->nullable()->after('date_observed');
            $table->enum('sentiment_label', ['positive', 'neutral', 'negative'])->nullable()->after('sentiment_score');
        });
    }

    public function down(): void
    {
        Schema::table('opponent_research', function (Blueprint $table) {
            $table->dropColumn(['sentiment_score', 'sentiment_label']);
        });
    }
};
