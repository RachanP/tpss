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
        Schema::disableForeignKeyConstraints();

        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->comment('ปีการศึกษา เช่น 2569 (1 แถว = 1 ปี — เทอมอยู่ในตาราง terms)');
            $table->date('start_date')->nullable()->comment('วันเริ่มปีการศึกษา — V4: derive จากเทอมในปฏิทิน (null ถ้ายังไม่ตั้งเทอม)');
            $table->date('end_date')->nullable()->comment('วันสิ้นสุดปีการศึกษา — V4: derive จากเทอมในปฏิทิน');
            $table->boolean('is_active');
            $table->enum('phase', ['preparation', 'scheduling', 'published'])
                ->default('preparation')
                ->comment('สถานะระดับระบบ — Admin คุม: preparation → scheduling → published (ต่อ "ปี")');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique('name');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};
