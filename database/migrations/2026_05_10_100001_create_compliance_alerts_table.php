<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('severity', 20)->default('warning');
            $table->string('title');
            $table->text('message');
            $table->json('metadata')->nullable();
            $table->nullableMorphs('alertable');
            $table->boolean('is_resolved')->default(false);
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['campaign_id', 'is_resolved']);
            $table->index(['campaign_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_alerts');
    }
};
