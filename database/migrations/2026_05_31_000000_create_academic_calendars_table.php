<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ปฏิทินการศึกษา (V4 ข้อ 8) — ปฏิทินย่อยภายใต้ "ปีการศึกษา"
     * 1 ปี มีได้หลายปฏิทิน เพื่อให้เปิด/ปิดเทอม + ช่วงสอบต่างกันตามหลักสูตร/ชั้นปี
     *  - curriculum_id = null → ใช้ได้ทุกหลักสูตร
     *  - year_level_min/max = null → ไม่จำกัดชั้นปี
     *  - is_default = ปฏิทินหลัก (fallback) ของปีนั้น
     * terms สังกัดปฏิทิน (ไม่ใช่สังกัดปีตรง ๆ อีกต่อไป)
     */
    public function up(): void
    {
        Schema::create('academic_calendars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->string('name', 100)->comment('ชื่อปฏิทิน เช่น "ป.ตรี ปี 1-2", "ป.โท"');
            $table->foreignId('curriculum_id')->nullable()->constrained('curriculums')->nullOnDelete()
                ->comment('ขอบเขต: หลักสูตร (null = ทุกหลักสูตร)');
            $table->unsignedTinyInteger('year_level_min')->nullable()->comment('ขอบเขตชั้นปีต่ำสุด (null = ไม่จำกัด)');
            $table->unsignedTinyInteger('year_level_max')->nullable()->comment('ขอบเขตชั้นปีสูงสุด (null = ไม่จำกัด)');
            $table->boolean('is_default')->default(false)->comment('ปฏิทินหลักของปี (ใช้เมื่อไม่มีปฏิทินเฉพาะกลุ่มที่ match)');
            $table->timestamps();

            $table->index(['academic_year_id', 'curriculum_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_calendars');
    }
};
