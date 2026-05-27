<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_conflict_result_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('schedule_conflict_runs')->cascadeOnDelete();
            $table->foreignId('result_id')->constrained('schedule_conflict_results')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->enum('scope_type', ['course_head_user', 'admin_global', 'executive_academic_year']);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('role', 50)->nullable();
            $table->unsignedBigInteger('course_offering_id')->nullable();
            $table->timestamps();

            $table->index(['scope_type', 'user_id', 'academic_year_id'], 'conflict_scopes_user_academic_index');
            $table->index(['scope_type', 'role', 'academic_year_id'], 'conflict_scopes_role_academic_index');
            $table->index(['course_offering_id', 'academic_year_id'], 'conflict_scopes_offering_academic_index');
            $table->index(['run_id', 'scope_type']);
            $table->index('result_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_conflict_result_scopes');
    }
};
