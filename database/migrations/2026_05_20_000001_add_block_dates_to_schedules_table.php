<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            if (! Schema::hasColumn('schedules', 'start_date')) {
                $table->date('start_date')->nullable()->after('practicum_series_id');
            }

            if (! Schema::hasColumn('schedules', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }
        });

        DB::table('schedules')
            ->whereNull('start_date')
            ->whereNotNull('teaching_date')
            ->update([
                'start_date' => DB::raw('teaching_date'),
                'end_date' => DB::raw('teaching_date'),
            ]);

        $this->makeTeachingDateNullable();

        Schema::table('schedules', function (Blueprint $table) {
            $table->index(
                ['course_offering_id', 'start_date', 'end_date'],
                'schedules_offering_block_dates_index'
            );
        });
    }

    public function down(): void
    {
        DB::table('schedules')
            ->whereNull('teaching_date')
            ->whereNotNull('start_date')
            ->update([
                'teaching_date' => DB::raw('start_date'),
            ]);

        $this->makeTeachingDateRequired();

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex('schedules_offering_block_dates_index');
            $table->dropColumn(['start_date', 'end_date']);
        });
    }

    private function makeTeachingDateNullable(): void
    {
        match (DB::getDriverName()) {
            'mysql', 'mariadb' => DB::statement('ALTER TABLE schedules MODIFY teaching_date DATE NULL'),
            'sqlite' => null,
            default => null,
        };
    }

    private function makeTeachingDateRequired(): void
    {
        match (DB::getDriverName()) {
            'mysql', 'mariadb' => DB::statement('ALTER TABLE schedules MODIFY teaching_date DATE NOT NULL'),
            'sqlite' => null,
            default => null,
        };
    }
};
