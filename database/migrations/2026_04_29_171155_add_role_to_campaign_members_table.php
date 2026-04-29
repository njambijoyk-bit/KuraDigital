<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_members', function (Blueprint $table) {
            $table->string('role')->nullable()->after('campaign_id');
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_members', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};
