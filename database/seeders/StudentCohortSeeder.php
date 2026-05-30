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

        $cohorts = [
            ['year_level' => 1, 'code' => 'รุ่นใหญ่', 'student_count' => 300],
            ['year_level' => 3, 'code' => 'กลุ่ม 1', 'student_count' => 80],
            ['year_level' => 3, 'code' => 'กลุ่ม 2', 'student_count' => 80],
            ['year_level' => 3, 'code' => 'กลุ่ม 3', 'student_count' => 80],
            ['year_level' => 3, 'code' => 'กลุ่ม 4', 'student_count' => 80],
            ['year_level' => 4, 'code' => 'กลุ่ม 1', 'student_count' => 78],
            ['year_level' => 4, 'code' => 'กลุ่ม 2', 'student_count' => 78],
            ['year_level' => 4, 'code' => 'กลุ่ม 3', 'student_count' => 78],
            ['year_level' => 4, 'code' => 'กลุ่ม 4', 'student_count' => 78],
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
