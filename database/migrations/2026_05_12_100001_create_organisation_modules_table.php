<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisation_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('module');
            $table->string('plan')->nullable(); // plan the grant came from, if any
            $table->string('status')->default('active'); // active, revoked
            $table->json('limits')->nullable(); // e.g. {"agents": 50}
            $table->timestamp('starts_at');
            $table->timestamp('expires_at');
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['organisation_id', 'module']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisation_modules');
    }
};
