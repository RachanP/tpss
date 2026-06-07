<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_cohorts', function (Blueprint $table) {
            if (! Schema::hasColumn('student_cohorts', 'academic_year_id')) {
                $table->foreignId('academic_year_id')
                    ->nullable()
                    ->after('curriculum_id')
                    ->constrained('academic_years')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        // Forward-only compatibility migration. Keep this column if it came
        // from the rebased master-data branch or existing local data.
    }
};
