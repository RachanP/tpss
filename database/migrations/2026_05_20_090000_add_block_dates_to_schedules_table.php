<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('schedules') || ! Schema::hasColumn('schedules', 'teaching_date')) {
            return;
        }

        DB::statement('ALTER TABLE schedules CHANGE teaching_date start_date DATE NOT NULL');
        DB::statement('ALTER TABLE schedules ADD end_date DATE NULL AFTER start_date');
        DB::table('schedules')->update(['end_date' => DB::raw('start_date')]);
        DB::statement('ALTER TABLE schedules MODIFY end_date DATE NOT NULL');
        DB::statement('DROP INDEX schedules_teaching_date_course_offering_id_index ON schedules');
        DB::statement('CREATE INDEX schedules_start_date_end_date_course_offering_id_index ON schedules (start_date, end_date, course_offering_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('schedules') || ! Schema::hasColumn('schedules', 'start_date')) {
            return;
        }

        DB::statement('DROP INDEX schedules_start_date_end_date_course_offering_id_index ON schedules');
        DB::statement('ALTER TABLE schedules DROP COLUMN end_date');
        DB::statement('ALTER TABLE schedules CHANGE start_date teaching_date DATE NOT NULL');
        DB::statement('CREATE INDEX schedules_teaching_date_course_offering_id_index ON schedules (teaching_date, course_offering_id)');
    }
};
