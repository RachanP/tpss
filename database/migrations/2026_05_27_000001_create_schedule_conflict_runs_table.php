<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_conflict_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->enum('status', ['pending', 'processing', 'ready', 'failed', 'missing'])->default('pending');
            $table->unsignedInteger('generation');
            $table->enum('source', ['observer', 'pivot', 'manual', 'scheduled', 'bulk_import'])->default('manual');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('result_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['academic_year_id', 'generation']);
            $table->index(['academic_year_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_conflict_runs');
    }
};
