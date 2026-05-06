<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->decimal('donation_goal', 14, 2)->nullable()->after('donation_info');
            $table->boolean('donations_enabled')->default(false)->after('donation_goal');
            $table->string('mpesa_paybill')->nullable()->after('donations_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['donation_goal', 'donations_enabled', 'mpesa_paybill']);
        });
    }
};
