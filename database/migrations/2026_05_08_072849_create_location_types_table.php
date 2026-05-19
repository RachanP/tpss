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

        Schema::create('location_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique()->comment('เช่น Lecture, Lab, Ward, Online, External');
            $table->boolean('requires_capacity')->default(true)->comment('ห้องในประเภทนี้ต้องระบุความจุไหม (false = ชุมชน/หอผู้ป่วย ไม่ต้องการ)');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('location_types');
    }
};
