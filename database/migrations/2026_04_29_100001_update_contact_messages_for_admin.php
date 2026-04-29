<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->boolean('is_archived')->default(false)->after('is_read');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete()->after('is_archived');
            $table->text('response')->nullable()->after('assigned_to');
            $table->timestamp('responded_at')->nullable()->after('response');
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropColumn(['is_archived', 'assigned_to', 'response', 'responded_at']);
        });
    }
};
