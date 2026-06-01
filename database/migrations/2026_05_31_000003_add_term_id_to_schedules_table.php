<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V2 schedule phase: ติดป้ายว่าแต่ละกิจกรรม (slot) อยู่เทอมไหน
 * term_id derive จาก start_date ตอน store/update (เทอมที่ช่วงวันคลุมวันเริ่ม)
 * nullable เพราะวันที่อาจตกช่วงปิดภาคเรียน (ไม่มีเทอมคลุม) — แต่ validation จะบล็อกการสร้างกรณีนั้น
 * วางหลัง create_terms_table (2026_05_31_000001) เพราะอ้าง FK terms
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            if (! Schema::hasColumn('schedules', 'term_id')) {
                $table->unsignedBigInteger('term_id')->nullable()->after('course_offering_id');
                $table->foreign('term_id')->references('id')->on('terms')->nullOnDelete();
                $table->index(['term_id', 'course_offering_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['term_id']);
            $table->dropIndex(['term_id', 'course_offering_id']);
            $table->dropColumn('term_id');
        });
    }
};
