<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('instructor_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->foreign('user_id')->references('id')->on('users');
            $table->string('title', 100)->nullable()->comment('คำนำหน้า/ตำแหน่งทางวิชาการ');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('employment_type')->nullable()->comment('พนักงานมหาวิทยาลัย, ข้าราชการ');
            $table->date('hired_at')->nullable()->comment('วันบรรจุ');
            $table->string('academic_degree')->nullable()->comment('วุฒิการศึกษา (ป.ตรี, ป.โท, ป.เอก)');
            $table->boolean('is_english_passed')->default(false)->comment('สอบผ่านเกณฑ์ภาษาอังกฤษหรือไม่');
            $table->integer('teaching_pct')->default(50)->comment('ภาระงานสอนที่มอบหมาย (%)');
            $table->integer('research_pct')->default(20)->comment('ภาระงานวิจัย (%)');
            $table->integer('service_pct')->default(10)->comment('ภาระงานบริการวิชาการ (%)');
            $table->integer('culture_pct')->default(10)->comment('ภาระงานศิลปวัฒนธรรม/พัฒนาองค์กร (%)');
            $table->integer('other_pct')->default(10)->comment('ภาระงานอื่นๆ ที่ได้รับมอบหมาย (%)');
            $table->foreign('department_id')->references('id')->on('departments');
            $table->integer('teaching_quota')->nullable()->comment('ชม.สอนต่อปี (คำนวณจาก % x ชม.ทำงานรวม)');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_profiles');
    }
};
