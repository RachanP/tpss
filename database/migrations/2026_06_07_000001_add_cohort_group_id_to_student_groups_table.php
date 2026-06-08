<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_groups', function (Blueprint $table) {
            if (! Schema::hasColumn('student_groups', 'cohort_group_id')) {
                $table->foreignId('cohort_group_id')
                    ->nullable()
                    ->after('course_offering_id')
                    ->constrained('student_cohorts')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        // Forward-only compatibility migration. Do not drop a column that may
        // have been provided by the rebased master-data branch.
    }
};
