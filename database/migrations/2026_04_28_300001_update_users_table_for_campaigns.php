<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->enum('clearance_level', ['public', 'internal', 'confidential', 'top_secret'])
                ->default('public')->after('phone');
            $table->enum('account_status', ['active', 'suspended', 'locked', 'invited'])
                ->default('active')->after('clearance_level');
            $table->boolean('mfa_enabled')->default(false)->after('account_status');
            $table->string('mfa_secret')->nullable()->after('mfa_enabled');
            $table->timestamp('mfa_verified_at')->nullable()->after('mfa_secret');
            $table->timestamp('last_login_at')->nullable()->after('mfa_verified_at');
            $table->string('last_login_ip')->nullable()->after('last_login_at');
            $table->string('invite_token')->nullable()->after('last_login_ip');
            $table->timestamp('invite_expires_at')->nullable()->after('invite_token');
            $table->softDeletes();

            $table->index('phone');
            $table->index('account_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone', 'clearance_level', 'account_status',
                'mfa_enabled', 'mfa_secret', 'mfa_verified_at',
                'last_login_at', 'last_login_ip',
                'invite_token', 'invite_expires_at',
            ]);
            $table->dropSoftDeletes();
        });
    }
};
