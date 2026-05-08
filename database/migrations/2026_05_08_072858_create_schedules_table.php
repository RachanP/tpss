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

        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_offering_id');
            $table->foreign('course_offering_id')->references('id')->on('course_offerings');
            $table->unsignedBigInteger('activity_type_id')->comment('\\u0e42\\u0e22\\u0e07\\u0e44\\u0e1b Master Data Table');
            $table->foreign('activity_type_id')->references('id')->on('activity_types');
            $table->unsignedBigInteger('room_id')->nullable();
            $table->foreign('room_id')->references('id')->on('rooms');
            $table->unsignedBigInteger('practicum_series_id')->nullable();
            $table->foreign('practicum_series_id')->references('id')->on('practicum_series');
            $table->date('teaching_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('topic', 255)->nullable();
            $table->unsignedInteger('capacity_required')->nullable()->comment('\\u0e08\\u0e33\\u0e19\\u0e27\\u0e19\\u0e19\\u0e31\\u0e01\\u0e28\\u0e36\\u0e01\\u0e29\\u0e32\\u0e17\\u0e35\\u0e48\\u0e23\\u0e2d\\u0e07\\u0e23\\u0e31\\u0e1a\\u0e2a\\u0e33\\u0e2b\\u0e23\\u0e31\\u0e1a activity \\u0e19\\u0e35\\u0e49 \\u2014 \\u0e43\\u0e0a\\u0e49\\u0e15\\u0e23\\u0e27\\u0e08 warning_capacity \\u0e40\\u0e17\\u0e35\\u0e22\\u0e1a\\u0e01\\u0e31\\u0e1a student_count \\u0e02\\u0e2d\\u0e07\\u0e01\\u0e25\\u0e38\\u0e48\\u0e21');
            $table->string('sub_group_label', 20)->nullable()->comment('\\u0e1b\\u0e49\\u0e32\\u0e22\\u0e01\\u0e25\\u0e38\\u0e48\\u0e21\\u0e22\\u0e48\\u0e2d\\u0e22\\u0e2a\\u0e33\\u0e2b\\u0e23\\u0e31\\u0e1a display \\u0e40\\u0e0a\\u0e48\\u0e19 a, b, 1, 2 \\u2014 \\u0e15\\u0e48\\u0e2d\\u0e17\\u0e49\\u0e32\\u0e22 group_code \\u0e40\\u0e1b\\u0e47\\u0e19 A1a, A1b');
            $table->enum('status', ["draft","pending_approval","approved","revised"]);
            $table->text('remark')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['teaching_date', 'course_offering_id']);
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
