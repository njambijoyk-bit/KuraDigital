<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('volunteers', function (Blueprint $table) {
            $table->json('tags')->nullable()->after('skills');
            $table->text('notes')->nullable()->after('tags');
            $table->enum('engagement_status', ['new', 'active', 'inactive'])->default('new')->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('volunteers', function (Blueprint $table) {
            $table->dropColumn(['tags', 'notes', 'engagement_status']);
        });
    }
};
