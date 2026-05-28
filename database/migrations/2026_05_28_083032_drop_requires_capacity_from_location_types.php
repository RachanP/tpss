<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * รวม requires_capacity + is_shared เป็น is_shared เดียว
     *
     * Semantic ใหม่:
     *   is_shared = false → ห้องเรียนทั่วไป (ตรวจ room_overlap + ตรวจ capacity)
     *   is_shared = true  → สถานที่ประเภทเปิด (ข้าม room_overlap + ข้าม capacity)
     *
     * Data migration:
     *   requires_capacity = false → is_shared = true (ไม่ตรวจ capacity = สถานที่เปิด)
     *   requires_capacity = true  → is_shared ยังเป็นค่าเดิม (false = ทั่วไป, true = เปิดอยู่แล้ว)
     */
    public function up(): void
    {
        // 1. Data migration: ถ้าห้องไม่ต้องระบุ capacity แสดงว่าเป็นสถานที่เปิด
        DB::table('location_types')
            ->where('requires_capacity', false)
            ->update(['is_shared' => true]);

        // 2. Drop column ที่ไม่ใช้แล้ว
        Schema::table('location_types', function (Blueprint $table) {
            $table->dropColumn('requires_capacity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('location_types', function (Blueprint $table) {
            //
        });
    }
};
