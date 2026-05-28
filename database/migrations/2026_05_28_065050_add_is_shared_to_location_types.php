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
        Schema::table('location_types', function (Blueprint $table) {
            $table->boolean('is_shared')
                  ->default(false)
                  ->after('requires_capacity')
                  ->comment('true = ห้องประเภทนี้ใช้ร่วมกันได้ข้ามตาราง — ไม่นับเป็น room_overlap');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('location_types', function (Blueprint $table) {
            $table->dropColumn('is_shared');
        });
    }
};
