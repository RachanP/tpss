<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('course_offerings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->foreign('course_id')->references('id')->on('courses');
            $table->unsignedBigInteger('academic_year_id');
            $table->foreign('academic_year_id')->references('id')->on('academic_years');
            $table->unsignedBigInteger('coordinator_id')->comment('หัวหน้าวิชา');
            $table->foreign('coordinator_id')->references('id')->on('users');
            $table->enum('approval_status', ['draft', 'pending', 'published', 'rejected']);
            $table->text('rejection_reason')->nullable();

            // M2 planning fields — snapshot ของจำนวน นศ. + ชั่วโมง + รอบฝึกตอน open scheduling
            $table->unsignedInteger('total_student_count')->nullable();
            $table->unsignedInteger('planned_lecture_hours')->nullable();
            $table->unsignedInteger('planned_lab_hours')->nullable();
            $table->unsignedInteger('planned_practicum_hours')->nullable();
            $table->unsignedTinyInteger('teaching_weeks')->nullable();
            $table->boolean('requires_practicum_rotation')->default(false);
            $table->text('practicum_note')->nullable()->comment('override note เมื่อ rotation ต่างจาก Master Data');

            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('course_offerings');
    }
};
