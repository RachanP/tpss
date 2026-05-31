<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * เทอม (ภาคการศึกษา) — ลูกของปีการศึกษา (V2 Master Data Cleanup)
     * ปีปกติ = เทอม 1 + เทอม 2 · ปีที่มีภาคฤดูร้อน = เพิ่มรายการที่ 3 (optional)
     * ช่วงปิดภาคเรียน = derive จากช่องว่างระหว่างเทอม (ไม่เก็บแยก)
     * วันสอบกลางภาค/ปลายภาค เก็บเป็นช่วง "สัปดาห์สอบ" (nullable)
     */
    public function up(): void
    {
        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence')->comment('ลำดับเทอม 1, 2, 3(ฤดูร้อน)');
            $table->string('name', 100)->comment('เช่น "ภาคเรียนที่ 1", "ภาคฤดูร้อน"');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('midterm_start')->nullable();
            $table->date('midterm_end')->nullable();
            $table->date('final_start')->nullable();
            $table->date('final_end')->nullable();
            $table->timestamps();

            $table->unique(['academic_year_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terms');
    }
};
