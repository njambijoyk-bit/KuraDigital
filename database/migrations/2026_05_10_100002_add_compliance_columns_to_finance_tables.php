<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->string('source_type', 30)->default('individual')->after('notes');
            $table->text('donor_id_number')->nullable()->after('donor_email');
            $table->text('donor_occupation')->nullable()->after('donor_id_number');
            $table->text('donor_employer')->nullable()->after('donor_occupation');
            $table->boolean('requires_disclosure')->default(false)->after('donor_employer');
            $table->foreignId('disclosure_verified_by')->nullable()->constrained('users')->nullOnDelete()->after('requires_disclosure');
            $table->timestamp('disclosure_verified_at')->nullable()->after('disclosure_verified_by');
            $table->json('compliance_flags')->nullable()->after('disclosure_verified_at');

            // Blind index columns for encrypted field search
            $table->string('donor_phone_index', 64)->nullable()->after('compliance_flags');
            $table->string('donor_email_index', 64)->nullable()->after('donor_phone_index');
            $table->string('donor_name_index', 64)->nullable()->after('donor_email_index');
            $table->string('donor_id_index', 64)->nullable()->after('donor_name_index');

            $table->index('donor_phone_index');
            $table->index('donor_email_index');
            $table->index('donor_id_index');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('receipt_media_id')->nullable()->constrained('media')->nullOnDelete()->after('county');
            $table->json('compliance_flags')->nullable()->after('receipt_media_id');
            $table->foreignId('abac_override_by')->nullable()->constrained('users')->nullOnDelete()->after('compliance_flags');
            $table->text('abac_override_reason')->nullable()->after('abac_override_by');

            // Blind index for encrypted vendor
            $table->string('vendor_index', 64)->nullable()->after('abac_override_reason');
            $table->index('vendor_index');
        });

        Schema::table('mpesa_transactions', function (Blueprint $table) {
            $table->string('phone_index', 64)->nullable()->after('raw_callback');
            $table->index('phone_index');
        });

        // Widen columns to hold encrypted ciphertext
        Schema::table('donations', function (Blueprint $table) {
            $table->text('donor_name')->nullable()->change();
            $table->text('donor_phone')->nullable()->change();
            $table->text('donor_email')->nullable()->change();
        });

        Schema::table('mpesa_transactions', function (Blueprint $table) {
            $table->text('phone_number')->nullable()->change();
            $table->text('receipt_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropIndex(['donor_phone_index']);
            $table->dropIndex(['donor_email_index']);
            $table->dropIndex(['donor_id_index']);
            $table->dropColumn([
                'source_type', 'donor_id_number', 'donor_occupation', 'donor_employer',
                'requires_disclosure', 'disclosure_verified_by', 'disclosure_verified_at',
                'compliance_flags', 'donor_phone_index', 'donor_email_index',
                'donor_name_index', 'donor_id_index',
            ]);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['vendor_index']);
            $table->dropColumn([
                'receipt_media_id', 'compliance_flags', 'abac_override_by',
                'abac_override_reason', 'vendor_index',
            ]);
        });

        Schema::table('mpesa_transactions', function (Blueprint $table) {
            $table->dropIndex(['phone_index']);
            $table->dropColumn('phone_index');
        });

        Schema::table('donations', function (Blueprint $table) {
            $table->string('donor_name')->nullable()->change();
            $table->string('donor_phone')->nullable()->change();
            $table->string('donor_email')->nullable()->change();
        });

        Schema::table('mpesa_transactions', function (Blueprint $table) {
            $table->string('phone_number')->nullable()->change();
            $table->string('receipt_number')->nullable()->change();
        });
    }
};
