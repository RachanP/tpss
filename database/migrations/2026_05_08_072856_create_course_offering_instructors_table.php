<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('course_offering_instructors', function (Blueprint $table) {
            $table->unsignedBigInteger('course_offering_id');
            $table->unsignedBigInteger('user_id');
            // role_in_course เก็บเฉพาะ 'coordinator' marker — role จริงใช้ course_role_id FK
            $table->string('role_in_course', 100)->default('instructor');
            $table->foreignId('course_role_id')->nullable()->constrained('course_roles')->nullOnDelete();
            // V2 delegation: 'view' = เห็นอย่างเดียว · 'schedule' = อาจารย์ช่วยจัดตาราง offering นี้ได้ (หัวหน้าวิชามอบหมาย)
            $table->string('schedule_permission', 20)->default('view');
            $table->primary(['course_offering_id', 'user_id']);
            $table->foreign('course_offering_id')->references('id')->on('course_offerings')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('course_offering_instructors');
    }
};
