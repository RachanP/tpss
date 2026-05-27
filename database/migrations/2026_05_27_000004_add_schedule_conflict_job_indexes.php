<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_instructors', function (Blueprint $table) {
            $table->index(['user_id', 'schedule_id'], 'schedule_instructors_user_schedule_index');
        });

        Schema::table('schedule_student_groups', function (Blueprint $table) {
            $table->index(['student_group_id', 'schedule_id'], 'schedule_groups_group_schedule_index');
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->index('course_offering_id', 'schedules_course_offering_index');
            $table->index(['room_id', 'start_date', 'end_date'], 'schedules_room_date_range_index');
        });

        Schema::table('course_offerings', function (Blueprint $table) {
            $table->index(['academic_year_id', 'coordinator_id'], 'offerings_academic_coordinator_index');
        });
    }

    public function down(): void
    {
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->dropIndex('offerings_academic_coordinator_index');
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex('schedules_room_date_range_index');
            $table->dropIndex('schedules_course_offering_index');
        });

        Schema::table('schedule_student_groups', function (Blueprint $table) {
            $table->dropIndex('schedule_groups_group_schedule_index');
        });

        Schema::table('schedule_instructors', function (Blueprint $table) {
            $table->dropIndex('schedule_instructors_user_schedule_index');
        });
    }
};
