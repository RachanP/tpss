<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * วันหยุดราชการ (V3 ข้อ 2.4) — ปฏิทินขึ้น "งดการเรียนการสอน" + ไม่นับ workload
     * เก็บ local เป็น source of truth · เติมอัตโนมัติจาก API ตอนสร้างปีการศึกษา (Nager.Date)
     * Admin เพิ่ม/แก้/ลบเองได้ (เผื่อวันหยุดเฉพาะคณะ)
     */
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name', 255);
            $table->string('remark', 255)->nullable();
            $table->string('source', 30)->nullable()->comment('nager = ดึงอัตโนมัติ / manual = Admin เพิ่มเอง');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
