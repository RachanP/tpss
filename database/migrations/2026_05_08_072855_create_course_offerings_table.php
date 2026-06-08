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

            // M2 planning fields — snapshot ของชั่วโมงตอน open scheduling
            $table->unsignedInteger('planned_lecture_hours')->nullable();
            $table->unsignedInteger('planned_lab_hours')->nullable();
            $table->unsignedTinyInteger('teaching_weeks')->nullable();
            // เหตุผลที่หัวหน้าวิชาแก้ชุดผู้สอนต่างจากแม่แบบรายวิชา (เพิ่ม/เปลี่ยนบทบาท/ลบ) — แสดงในหน้าแจ้งเตือน admin
            $table->text('instructor_pool_note')->nullable();

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
