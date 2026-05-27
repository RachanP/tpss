<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_conflict_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('schedule_conflict_runs')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->unsignedBigInteger('schedule_id');
            $table->unsignedBigInteger('conflicting_schedule_id');
            $table->enum('conflict_type', ['instructor_overlap', 'room_overlap', 'group_overlap']);
            $table->string('resource_type', 50)->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->string('message', 255);
            $table->string('pair_key', 191);
            $table->timestamps();

            $table->unique(['run_id', 'pair_key', 'schedule_id'], 'conflict_results_run_pair_schedule_unique');
            $table->index(['run_id', 'schedule_id']);
            $table->index(['academic_year_id', 'schedule_id']);
            $table->index(['academic_year_id', 'conflict_type']);
            $table->index(['resource_type', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_conflict_results');
    }
};
