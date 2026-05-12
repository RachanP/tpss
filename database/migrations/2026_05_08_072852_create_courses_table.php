<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
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
            $table->enum('course_type', ["theory","practicum","theory_practicum"]);
            $table->enum('academic_level', ["undergraduate", "graduate"])->default('undergraduate');
            $table->integer('default_year_level')->nullable()->comment('ชั้นปีที่ต้องเรียนตามแผน (1-4)');
            $table->integer('default_semester')->nullable()->comment('ภาคเรียนที่ต้องเรียนตามแผน (1, 2, 3)');
            $table->boolean('requires_practicum_rotation')->default(false);
            $table->integer('credits');
            $table->integer('lecture_hours')->default(0);
            $table->integer('lab_hours')->default(0);
            $table->integer('self_study_hours')->default(0);
            $table->string('color_code', 10)->nullable();
            $table->enum('status', ["active","inactive"])->default('active');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unique(['course_code', 'curriculum_id']);
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
