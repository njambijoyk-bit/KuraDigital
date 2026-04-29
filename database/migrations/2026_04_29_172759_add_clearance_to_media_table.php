<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->enum('clearance', ['public', 'internal', 'confidential', 'top_secret'])
                ->default('public')
                ->after('collection');
            $table->index('clearance');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropIndex(['clearance']);
            $table->dropColumn('clearance');
        });
    }
};
