<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * กลุ่มนักศึกษาระดับ "ชั้นปี" (cohort) — ตั้งใน Master Data โดย Admin
     * V2: ป.ตรี ปี1 = กลุ่มใหญ่, ปี3-4 = 4 กลุ่มใหญ่ (~80 คน)
     * เป็นโครงสร้างต่อหลักสูตร (ยังไม่ผูก academic_year) — ใช้เป็น template ให้ rotation/publish phase ภายหลัง
     */
    public function up(): void
    {
        Schema::create('student_cohorts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_id')->constrained('curriculums')->cascadeOnDelete();
            $table->unsignedTinyInteger('year_level')->nullable()
                ->comment('ชั้นปี เช่น 1, 3, 4 — null สำหรับหลักสูตรที่ไม่ใช้ระบบชั้นปี (ป.โท/ป.เอก)');
            $table->string('code', 50)->comment('รหัสกลุ่ม เช่น "กลุ่ม 1", "A"');
            $table->unsignedInteger('student_count')->default(0)->comment('จำนวนนักศึกษาในกลุ่ม');
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['curriculum_id', 'year_level', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_cohorts');
    }
};
