<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_conflict_result_scopes', function (Blueprint $table) {
            $table->index(
                ['scope_type', 'user_id', 'academic_year_id', 'run_id', 'result_id'],
                'conflict_scopes_user_year_run_result_index'
            );
        });

        Schema::table('schedule_conflict_results', function (Blueprint $table) {
            $table->index(
                ['run_id', 'academic_year_id', 'schedule_id', 'conflict_type'],
                'conflict_results_run_year_schedule_type_index'
            );
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->index(
                ['course_offering_id', 'start_date', 'end_date', 'start_time'],
                'schedules_offering_date_time_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex('schedules_offering_date_time_index');
        });

        Schema::table('schedule_conflict_results', function (Blueprint $table) {
            $table->dropIndex('conflict_results_run_year_schedule_type_index');
        });

        Schema::table('schedule_conflict_result_scopes', function (Blueprint $table) {
            $table->dropIndex('conflict_scopes_user_year_run_result_index');
        });
    }
};
