<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Strategy tables ---

        Schema::create('strategy_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('content')->nullable();
            $table->string('category')->default('general'); // general, swot, talking-point, risk, opportunity
            $table->string('clearance_level')->default('internal'); // public, internal, confidential, top_secret
            $table->string('ward')->nullable();
            $table->string('constituency')->nullable();
            $table->string('county')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'category']);
            $table->index(['campaign_id', 'clearance_level']);
        });

        Schema::create('ward_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('ward');
            $table->string('constituency')->nullable();
            $table->string('county')->nullable();
            $table->unsignedInteger('registered_voters')->default(0);
            $table->unsignedInteger('target_votes')->default(0);
            $table->unsignedInteger('projected_turnout')->default(0);
            $table->string('priority')->default('medium'); // critical, high, medium, low
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'ward']);
            $table->index(['campaign_id', 'priority']);
        });

        Schema::create('polls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('pollster')->nullable();
            $table->date('poll_date');
            $table->unsignedInteger('sample_size')->nullable();
            $table->decimal('margin_of_error', 5, 2)->nullable();
            $table->json('results'); // [{candidate, percentage, party?}]
            $table->string('ward')->nullable();
            $table->string('constituency')->nullable();
            $table->string('county')->nullable();
            $table->string('clearance_level')->default('internal');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'poll_date']);
        });

        // --- Messaging tables ---

        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('channel'); // sms, whatsapp, email
            $table->string('subject')->nullable(); // email only
            $table->text('body');
            $table->json('variables')->nullable(); // [{name, description}]
            $table->string('status')->default('draft'); // draft, approved, archived
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'channel']);
            $table->index(['campaign_id', 'status']);
        });

        Schema::create('message_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('message_templates')->nullOnDelete();
            $table->string('name');
            $table->string('channel'); // sms, whatsapp, email
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('status')->default('draft'); // draft, approved, sending, sent, failed
            $table->json('audience_filters')->nullable(); // {ward, tags, segment}
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'channel']);
            $table->index(['campaign_id', 'status']);
        });

        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voter_id')->nullable()->constrained('voters')->nullOnDelete();
            $table->string('recipient'); // phone or email
            $table->string('channel'); // sms, whatsapp, email
            $table->string('status')->default('pending'); // pending, sent, delivered, failed
            $table->string('external_id')->nullable(); // AT/provider message ID
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['message_campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_logs');
        Schema::dropIfExists('message_campaigns');
        Schema::dropIfExists('message_templates');
        Schema::dropIfExists('polls');
        Schema::dropIfExists('ward_targets');
        Schema::dropIfExists('strategy_notes');
    }
};
