<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Events - geographic (events happen at specific locations)
        Schema::table('events', function (Blueprint $table) {
            $table->string('ward')->nullable()->after('location');
            $table->string('constituency')->nullable()->after('ward');
            $table->string('county')->nullable()->after('constituency');
        });

        // Projects - geographic (development projects are location-bound)
        Schema::table('projects', function (Blueprint $table) {
            $table->string('ward')->nullable()->after('impact');
            $table->string('constituency')->nullable()->after('ward');
            $table->string('county')->nullable()->after('constituency');
        });

        // Contact messages - geographic (track where contacts come from)
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->string('ward')->nullable()->after('phone');
            $table->string('constituency')->nullable()->after('ward');
            $table->string('county')->nullable()->after('constituency');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['ward', 'constituency', 'county']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['ward', 'constituency', 'county']);
        });

        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropColumn(['ward', 'constituency', 'county']);
        });
    }
};
