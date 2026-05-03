<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Budgets ---

        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('category'); // operations, media, events, field, personnel, logistics, other
            $table->decimal('allocated_amount', 14, 2)->default(0);
            $table->decimal('spent_amount', 14, 2)->default(0);
            $table->string('period')->nullable(); // monthly, quarterly, total
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'category']);
        });

        // --- Expenses ---

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('KES');
            $table->string('category'); // operations, media, events, field, personnel, logistics, other
            $table->string('payment_method')->default('cash'); // cash, mpesa, bank_transfer, cheque
            $table->string('reference')->nullable(); // receipt/transaction number
            $table->string('vendor')->nullable();
            $table->date('expense_date');
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('ward')->nullable();
            $table->string('constituency')->nullable();
            $table->string('county')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index(['campaign_id', 'category']);
            $table->index(['campaign_id', 'expense_date']);
        });

        // --- Donations ---

        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('donor_name')->nullable();
            $table->string('donor_phone')->nullable();
            $table->string('donor_email')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('KES');
            $table->string('channel')->default('mpesa'); // mpesa, bank_transfer, cash, online
            $table->string('mpesa_receipt')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('status')->default('completed'); // pending, completed, failed, reversed
            $table->text('notes')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->timestamp('donated_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'channel']);
            $table->index(['campaign_id', 'status']);
            $table->index('mpesa_receipt');
            $table->index('transaction_id');
        });

        // --- M-Pesa Transactions (raw Daraja callback data) ---

        Schema::create('mpesa_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('donation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('transaction_type'); // c2b, stk_push
            $table->string('merchant_request_id')->nullable();
            $table->string('checkout_request_id')->nullable();
            $table->string('receipt_number')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('phone_number')->nullable();
            $table->string('result_code')->nullable();
            $table->string('result_desc')->nullable();
            $table->json('raw_callback')->nullable();
            $table->string('status')->default('pending'); // pending, completed, failed, cancelled
            $table->timestamps();

            $table->index('merchant_request_id');
            $table->index('checkout_request_id');
            $table->index('receipt_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mpesa_transactions');
        Schema::dropIfExists('donations');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('budgets');
    }
};
