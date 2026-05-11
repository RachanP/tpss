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
        Schema::table('instructor_profiles', function (Blueprint $table) {
            $table->integer('teaching_pct')->default(50)->after('department_id')->comment('ภาระงานสอนที่มอบหมาย (%)');
            $table->integer('teaching_quota')->nullable()->change()->comment('ชม.สอนต่อปี (คำนวณจาก % x ชม.ทำงานรวม)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instructor_profiles', function (Blueprint $table) {
            $table->dropColumn('teaching_pct');
        });
    }
};
