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

        Schema::create('schedule_student_groups', function (Blueprint $table) {
            $table->id();
            $table->foreign('schedule_id')->references('id')->on('schedules');
            $table->id();
            $table->foreign('student_group_id')->references('id')->on('student_groups');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_student_groups');
    }
};
