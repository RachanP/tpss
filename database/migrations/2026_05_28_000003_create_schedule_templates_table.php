<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('schedule_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_offering_id');
            $table->foreign('course_offering_id')->references('id')->on('course_offerings');
            $table->unsignedBigInteger('activity_type_id');
            $table->foreign('activity_type_id')->references('id')->on('activity_types');
            $table->unsignedTinyInteger('weekday')->comment('1=Monday, 7=Sunday');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedTinyInteger('start_week');
            $table->unsignedTinyInteger('end_week');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('topic', 255)->nullable();
            $table->unsignedInteger('capacity_required')->nullable();
            $table->string('sub_group_label', 20)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->foreign('updated_by')->references('id')->on('users');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['course_offering_id', 'start_week', 'end_week'], 'schedule_templates_offering_weeks_index');
            $table->index(['course_offering_id', 'weekday', 'start_time'], 'schedule_templates_offering_weekday_time_index');
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_templates');
    }
};
