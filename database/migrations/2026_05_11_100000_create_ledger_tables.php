<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->string('type'); // asset, liability, equity, revenue, expense
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['campaign_id', 'code']);
            $table->index(['campaign_id', 'type']);
        });

        Schema::create('funds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 20);
            $table->text('description')->nullable();
            $table->boolean('is_restricted')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['campaign_id', 'code']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('entry_number', 30);
            $table->date('date');
            $table->string('description');
            $table->string('reference_type')->nullable(); // expense, donation, mpesa_transaction, manual
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_posted')->default(false);
            $table->boolean('is_reversed')->default(false);
            $table->foreignId('reversed_by_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'entry_number']);
            $table->index(['campaign_id', 'date']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('fund_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description')->nullable();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->timestamps();

            $table->index('account_id');
            $table->index('fund_id');
        });

        Schema::create('account_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fund_id')->nullable()->constrained()->nullOnDelete();
            $table->string('period', 7); // YYYY-MM
            $table->decimal('debit_total', 14, 2)->default(0);
            $table->decimal('credit_total', 14, 2)->default(0);
            $table->decimal('balance', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['account_id', 'campaign_id', 'fund_id', 'period'], 'acct_bal_unique');
            $table->index(['campaign_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_balances');
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('funds');
        Schema::dropIfExists('accounts');
    }
};
