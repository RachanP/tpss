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

        // ปี 1-2 = กลุ่มใหญ่รวมรุ่น (รหัสตัวอักษร) · ปี 3-4 = 4 กลุ่มใหญ่ A–D (V4)
        // V4: กลุ่มใหญ่ = ตัวอักษรล้วน · กลุ่มย่อย (parent_id) = ตัวอักษร+เลข เช่น A1, A2
        // ตัวอย่างซอยกลุ่มย่อยที่กลุ่ม A ของปี 3-4 ให้เห็นโครง parent_id
        $majors = [
            ['year_level' => 1, 'code' => 'A', 'student_count' => 300, 'subgroups' => []],
            ['year_level' => 2, 'code' => 'A', 'student_count' => 285, 'subgroups' => []],
            ['year_level' => 3, 'code' => 'A', 'student_count' => 80, 'subgroups' => [['A1', 40], ['A2', 40]]],
            ['year_level' => 3, 'code' => 'B', 'student_count' => 80, 'subgroups' => []],
            ['year_level' => 3, 'code' => 'C', 'student_count' => 80, 'subgroups' => []],
            ['year_level' => 3, 'code' => 'D', 'student_count' => 80, 'subgroups' => []],
            ['year_level' => 4, 'code' => 'A', 'student_count' => 78, 'subgroups' => [['A1', 39], ['A2', 39]]],
            ['year_level' => 4, 'code' => 'B', 'student_count' => 78, 'subgroups' => []],
            ['year_level' => 4, 'code' => 'C', 'student_count' => 78, 'subgroups' => []],
            ['year_level' => 4, 'code' => 'D', 'student_count' => 78, 'subgroups' => []],
        ];

        foreach ($majors as $data) {
            $major = StudentCohort::updateOrCreate(
                [
                    'curriculum_id' => $curriculum->id,
                    'year_level'    => $data['year_level'],
                    'code'          => $data['code'],
                ],
                ['student_count' => $data['student_count'], 'parent_id' => null]
            );

            foreach ($data['subgroups'] as [$subCode, $subCount]) {
                StudentCohort::updateOrCreate(
                    [
                        'curriculum_id' => $curriculum->id,
                        'year_level'    => $data['year_level'],
                        'code'          => $subCode,
                    ],
                    ['student_count' => $subCount, 'parent_id' => $major->id]
                );
            }
        }
    }
}
