<?php

namespace Database\Seeders;

use App\Models\Curriculum;
use App\Models\StudentCohort;
use Illuminate\Database\Seeder;

/**
 * กลุ่มนักศึกษาตามชั้นปี (cohort — V2)
 * ป.ตรี: ปี 1 = กลุ่มใหญ่รวมรุ่น / ปี 3-4 = 4 กลุ่มใหญ่ (~80 คน)
 */
class StudentCohortSeeder extends Seeder
{
    public function run(): void
    {
        $curriculum = Curriculum::where('education_level', 'bachelor')
            ->where('uses_year_level', true)
            ->orderBy('effective_year', 'desc')
            ->first();

        if (! $curriculum) {
            return;
        }

        // ปี 1-2 = กลุ่มใหญ่รวมรุ่น · ปี 3-4 = 4 กลุ่ม (A1, B1, A2, B2)
        $cohorts = [
            ['year_level' => 1, 'code' => 'กลุ่มใหญ่', 'student_count' => 300],
            ['year_level' => 2, 'code' => 'กลุ่มใหญ่', 'student_count' => 285],
            ['year_level' => 3, 'code' => 'A1', 'student_count' => 80],
            ['year_level' => 3, 'code' => 'B1', 'student_count' => 80],
            ['year_level' => 3, 'code' => 'A2', 'student_count' => 80],
            ['year_level' => 3, 'code' => 'B2', 'student_count' => 80],
            ['year_level' => 4, 'code' => 'A1', 'student_count' => 78],
            ['year_level' => 4, 'code' => 'B1', 'student_count' => 78],
            ['year_level' => 4, 'code' => 'A2', 'student_count' => 78],
            ['year_level' => 4, 'code' => 'B2', 'student_count' => 78],
        ];

        foreach ($cohorts as $data) {
            StudentCohort::updateOrCreate(
                [
                    'curriculum_id' => $curriculum->id,
                    'year_level'    => $data['year_level'],
                    'code'          => $data['code'],
                ],
                ['student_count' => $data['student_count']]
            );
        }
    }
}
