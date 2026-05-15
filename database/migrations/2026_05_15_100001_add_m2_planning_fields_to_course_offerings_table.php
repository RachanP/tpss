<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('course_offerings')) {
            return;
        }

        Schema::table('course_offerings', function (Blueprint $table) {
            if (! Schema::hasColumn('course_offerings', 'status')) {
                $table->enum('status', ['active', 'archived'])->default('active');
            }

            if (! Schema::hasColumn('course_offerings', 'total_student_count')) {
                $table->unsignedInteger('total_student_count')->nullable();
            }

            if (! Schema::hasColumn('course_offerings', 'planned_lecture_hours')) {
                $table->unsignedInteger('planned_lecture_hours')->nullable();
            }

            if (! Schema::hasColumn('course_offerings', 'planned_lab_hours')) {
                $table->unsignedInteger('planned_lab_hours')->nullable();
            }

            if (! Schema::hasColumn('course_offerings', 'planned_practicum_hours')) {
                $table->unsignedInteger('planned_practicum_hours')->nullable();
            }

            if (! Schema::hasColumn('course_offerings', 'teaching_weeks')) {
                $table->unsignedTinyInteger('teaching_weeks')->nullable();
            }

            if (! Schema::hasColumn('course_offerings', 'requires_practicum_rotation')) {
                $table->boolean('requires_practicum_rotation')->default(false);
            }

            if (! Schema::hasColumn('course_offerings', 'practicum_note')) {
                $table->text('practicum_note')->nullable();
            }

            if (! Schema::hasColumn('course_offerings', 'archived_at')) {
                $table->timestamp('archived_at')->nullable();
            }

            if (! Schema::hasColumn('course_offerings', 'archived_by')) {
                $table->unsignedBigInteger('archived_by')->nullable();
                $table->foreign('archived_by')->references('id')->on('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('course_offerings', 'archive_reason')) {
                $table->text('archive_reason')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Forward-only hardening migration. Keep existing data and schema intact.
    }
};
