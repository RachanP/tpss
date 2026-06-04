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
        Schema::create('curriculums', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->comment('\\u0e40\\u0e0a\\u0e48\\u0e19 \\u0e1e\\u0e22\\u0e32\\u0e1a\\u0e32\\u0e25\\u0e28\\u0e32\\u0e2a\\u0e15\\u0e23\\u0e1a\\u0e31\\u0e13\\u0e11\\u0e34\\u0e15 (\\u0e1b\\u0e23\\u0e31\\u0e1a\\u0e1b\\u0e23\\u0e38\\u0e07 2565)');
            $table->integer('effective_year')->comment('\\u0e1b\\u0e35\\u0e17\\u0e35\\u0e48\\u0e40\\u0e23\\u0e34\\u0e48\\u0e21\\u0e43\\u0e0a\\u0e49');
            $table->enum('education_level', ['bachelor', 'master', 'doctorate'])
                ->default('bachelor')
                ->comment('ระดับการศึกษาของหลักสูตร (ป.ตรี/ป.โท/ป.เอก)');
            $table->unsignedTinyInteger('duration_years')
                ->default(4)
                ->comment('จำนวนปีของหลักสูตร (ป.ตรี=4, ป.โท=2, ป.เอก=3)');
            $table->boolean('uses_year_level')
                ->default(true)
                ->comment('ใช้ระบบชั้นปี (cohort) หรือไม่ — false=ใช้ prerequisite + หน่วยกิตสะสมแทน');
            $table->unsignedSmallInteger('total_credits_required')
                ->nullable()
                ->comment('หน่วยกิตขั้นต่ำของหลักสูตร');
            $table->boolean('counts_service_only')
                ->default(false)
                ->comment('หลักสูตรนับงานบริการวิชาการอย่างเดียว (ไม่นับชั่วโมงทำการสอนปกติ) — V4 ข้อ 4');
            $table->boolean('is_active')->nullable()->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('curriculums');
    }
};
