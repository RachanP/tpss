<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('course_code', 255);
            $table->unsignedBigInteger('curriculum_id');
            $table->foreign('curriculum_id')->references('id')->on('curriculums');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->foreign('department_id')->references('id')->on('departments');
            $table->unsignedBigInteger('head_instructor_id')->nullable();
            $table->foreign('head_instructor_id')->references('id')->on('users');
            $table->string('name_th', 255);
            $table->string('name_en', 255)->nullable();
            // course_type ทำเป็น nullable — UI infer จาก lecture_hours + lab_hours + requires_practicum_rotation
            $table->enum('course_type', ['theory', 'practicum', 'theory_practicum'])->nullable();
            // ระดับการศึกษาย้ายไปอยู่ที่ curriculums.education_level (เพราะเป็น property ของหลักสูตร ไม่ใช่ของรายวิชา)
            $table->integer('default_year_level')->nullable()->comment('ชั้นปีที่ต้องเรียนตามแผน — null ถ้าหลักสูตรไม่ใช้ระบบชั้นปี');
            // V2 cleanup: ตัด default_semester — วิชาเปิดทั้งปี ไม่ผูกภาค (เทอมเป็นป้ายของแต่ละ slot)
            $table->boolean('requires_practicum_rotation')->default(false);
            $table->boolean('is_required')->default(true)->comment('true=วิชาบังคับของหลักสูตร, false=วิชาเลือก');
            $table->integer('credits');
            $table->integer('lecture_hours')->default(0);
            $table->integer('lab_hours')->default(0);
            $table->integer('self_study_hours')->default(0);
            $table->unsignedInteger('capacity')->nullable()->comment('จำนวนนักศึกษาสูงสุดที่รับได้ในวิชานี้');
            $table->string('color_code', 10)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unique(['course_code', 'curriculum_id']);
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
