<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opponent_research', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opponent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('content');
            $table->enum('clearance', ['public', 'internal', 'confidential', 'restricted'])->default('internal');
            $table->string('source')->nullable();
            $table->date('date_observed')->nullable();
            $table->timestamps();

            $table->index(['opponent_id', 'clearance']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opponent_research');
    }
};
