<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $curriculum = Curriculum::where('name', 'LIKE', '%2565%')->first();
        if (!$curriculum) return;

        $deptMental = Department::where('name', 'LIKE', '%สุขภาพจิต%')->first();
        $deptFoundation = Department::where('name', 'LIKE', '%รากฐาน%')->first();

        $instructor = User::where('username', 'rachan_p')->first() ?? User::first();
        $staff = User::whereHas('roles', fn($q) => $q->where('role', 'staff'))->first();

        $courses = [
            [
                'course_code' => 'NSBS 111',
                'name_th' => 'กระบวนการพยาบาล 1',
                'name_en' => 'Nursing Process 1',
                'course_type' => 'theory',
                'academic_level' => 'undergraduate',
                'default_year_level' => 1,
                'default_semester' => 1,
                'credits' => 2,
                'lecture_hours' => 2,
                'lab_hours' => 0,
                'self_study_hours' => 4,
                'capacity' => 252,
                'color_code' => '#3b82f6',
                'department_id' => $deptFoundation->id ?? 1,
                'head_instructor_id' => $instructor->id,
                'assigned_staff_id' => $staff?->id,
                'status' => 'active',
            ],
            [
                'course_code' => 'NSBS 212',
                'name_th' => 'การพยาบาลเด็ก 1',
                'name_en' => 'Pediatric Nursing 1',
                'course_type' => 'theory_practicum',
                'academic_level' => 'undergraduate',
                'default_year_level' => 2,
                'default_semester' => 1,
                'credits' => 3,
                'lecture_hours' => 2,
                'lab_hours' => 1,
                'self_study_hours' => 6,
                'capacity' => 240,
                'color_code' => '#10b981',
                'department_id' => $deptFoundation->id ?? 1,
                'head_instructor_id' => $instructor->id,
                'assigned_staff_id' => $staff?->id,
                'status' => 'active',
            ],
            [
                'course_code' => 'NSBS 213',
                'name_th' => 'สุขภาพจิตและการพยาบาลจิตเวช 1',
                'name_en' => 'Mental Health and Psychiatric Nursing 1',
                'course_type' => 'theory',
                'academic_level' => 'undergraduate',
                'default_year_level' => 2,
                'default_semester' => 1,
                'credits' => 2,
                'lecture_hours' => 2,
                'lab_hours' => 0,
                'self_study_hours' => 4,
                'capacity' => 240,
                'color_code' => '#8b5cf6',
                'department_id' => $deptMental->id ?? 3,
                'head_instructor_id' => $instructor->id,
                'assigned_staff_id' => $staff?->id,
                'status' => 'active',
            ],
            [
                'course_code' => 'NSBS 221',
                'name_th' => 'การพยาบาลเด็ก 2',
                'name_en' => 'Pediatric Nursing 2',
                'course_type' => 'practicum',
                'academic_level' => 'undergraduate',
                'default_year_level' => 2,
                'default_semester' => 2,
                'credits' => 2,
                'lecture_hours' => 0,
                'lab_hours' => 2,
                'self_study_hours' => 4,
                'capacity' => 240,
                'color_code' => '#f59e0b',
                'department_id' => $deptFoundation->id ?? 1,
                'head_instructor_id' => $instructor->id,
                'assigned_staff_id' => $staff?->id,
                'status' => 'active',
            ],
        ];

        foreach ($courses as $courseData) {
            $courseData['curriculum_id'] = $curriculum->id;
            Course::updateOrCreate(
                ['course_code' => $courseData['course_code'], 'curriculum_id' => $curriculum->id],
                $courseData
            );
        }
    }
}
