<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_finance_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();

            // Spending caps (Kenya Election Campaign Financing Act)
            $table->decimal('spending_cap', 14, 2)->nullable();
            $table->string('spending_cap_currency', 3)->default('KES');

            // Donation caps
            $table->decimal('individual_donation_cap', 14, 2)->nullable();
            $table->decimal('corporate_donation_cap', 14, 2)->nullable();
            $table->decimal('disclosure_threshold', 14, 2)->nullable();

            // Approval limits (JSON: role → max amount)
            $table->json('approval_limits')->nullable();

            // Compliance settings
            $table->date('election_date')->nullable();
            $table->string('reporting_period', 20)->default('quarterly');
            $table->decimal('require_receipts_above', 14, 2)->default(5000);
            $table->boolean('require_segregation_of_duties')->default(true);

            // Alert thresholds (percentage of spending cap)
            $table->unsignedTinyInteger('alert_at_percent')->default(75);
            $table->unsignedTinyInteger('critical_at_percent')->default(90);

            $table->timestamps();

            $table->unique('campaign_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_finance_settings');
    }
};
