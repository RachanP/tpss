<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V4 ข้อ 1 — หัวข้อกิจกรรมสำเร็จรูป (Activity Topic Templates)
 * Admin/เจ้าหน้าที่กรอกหัวข้อกิจกรรม/บทเรียนของวิชาไว้ล่วงหน้า
 * → หัวหน้าวิชากดเลือกในฟอร์ม slot ได้เลย (ยังพิมพ์เองได้ = free text)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('course_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_topics');
    }
};
