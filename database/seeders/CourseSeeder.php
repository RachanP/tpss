<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseRole;
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
        $deptAdult = Department::where('name', 'LIKE', '%ผู้ใหญ่%')->first();

        // หัวหน้าวิชา (demo): head_med ดูแลวิชาภาควิชาการพยาบาลรากฐาน, head_psy ดูแลวิชาสุขภาพจิตฯ
        $headFoundation = User::where('username', 'head_med')->first();   // ภาควิชาการพยาบาลรากฐาน
        $headMental     = User::where('username', 'head_psy')->first();   // ภาควิชาสุขภาพจิตฯ

        $courses = [
            [
                'course_code' => 'NSBS 111',
                'name_th' => 'กระบวนการพยาบาล 1',
                'name_en' => 'Nursing Process 1',
                'course_type' => 'theory',
                'default_year_level' => 1,
                'credits' => 2,
                'lecture_hours' => 2,
                'lab_hours' => 0,
                'self_study_hours' => 4,
                'color_code' => '#3b82f6',
                'department_id' => $deptFoundation->id ?? 1,
                'head_instructor_id' => $headFoundation?->id,
                'status' => 'inactive',
                'staff_usernames' => ['staff_01'],
                'instructors' => [
                    'instructor_01' => 'อาจารย์ผู้สอน',
                ],
            ],
            [
                'course_code' => 'NSBS 212',
                'name_th' => 'การพยาบาลเด็ก 1',
                'name_en' => 'Pediatric Nursing 1',
                'course_type' => 'theory_practicum',
                'default_year_level' => 2,
                'credits' => 3,
                'lecture_hours' => 2,
                'lab_hours' => 1,
                'self_study_hours' => 6,
                'color_code' => '#10b981',
                'department_id' => $deptFoundation->id ?? 1,
                'head_instructor_id' => $headFoundation?->id,
                'status' => 'inactive',
                'prerequisite_codes' => ['NSBS 111'],
                'staff_usernames' => ['staff_01'],
                'instructors' => [
                    'instructor_01' => 'อาจารย์ผู้สอน',
                ],
            ],
            [
                'course_code' => 'NSBS 213',
                'name_th' => 'สุขภาพจิตและการพยาบาลจิตเวช 1',
                'name_en' => 'Mental Health and Psychiatric Nursing 1',
                'course_type' => 'theory',
                'default_year_level' => 2,
                'credits' => 2,
                'lecture_hours' => 2,
                'lab_hours' => 0,
                'self_study_hours' => 4,
                'color_code' => '#8b5cf6',
                'department_id' => $deptMental->id ?? 3,
                'head_instructor_id' => $headMental?->id,
                'status' => 'inactive',
                'prerequisite_codes' => ['NSBS 111'],
                'staff_usernames' => ['staff_01'],
                'instructors' => [],
            ],
            [
                'course_code' => 'NSBS 221',
                'name_th' => 'การพยาบาลเด็ก 2',
                'name_en' => 'Pediatric Nursing 2',
                'course_type' => 'practicum',
                'default_year_level' => 2,
                'credits' => 2,
                'lecture_hours' => 0,
                'lab_hours' => 2,
                'self_study_hours' => 4,
                'color_code' => '#f59e0b',
                'department_id' => $deptFoundation->id ?? 1,
                'head_instructor_id' => $headFoundation?->id,
                'status' => 'inactive',
                'prerequisite_codes' => ['NSBS 212'],
                'staff_usernames' => ['staff_01'],
                'instructors' => [
                    'instructor_01' => 'อาจารย์ผู้สอน',
                ],
            ],
            [
                'course_code' => 'NSBS 222',
                'name_th' => 'การพยาบาลผู้ใหญ่ 1',
                'name_en' => 'Adult Nursing 1',
                'course_type' => 'theory_practicum',
                'default_year_level' => 2,
                'credits' => 3,
                'lecture_hours' => 2,
                'lab_hours' => 1,
                'self_study_hours' => 6,
                'color_code' => '#0891b2',
                'department_id' => $deptAdult->id ?? $deptFoundation->id ?? 1,
                'head_instructor_id' => $headFoundation?->id,
                'status' => 'inactive',
                'prerequisite_codes' => ['NSBS 111'],
                'staff_usernames' => ['staff_01'],
                'instructors' => [
                    'instructor_01' => 'อาจารย์ผู้สอน',
                ],
            ],
            [
                'course_code' => 'NSBS 231',
                'name_th' => 'การพยาบาลมารดา ทารก และการผดุงครรภ์ 1',
                'name_en' => 'Maternal-Newborn Nursing and Midwifery 1',
                'course_type' => 'theory_practicum',
                'default_year_level' => 2,
                'credits' => 3,
                'lecture_hours' => 2,
                'lab_hours' => 1,
                'self_study_hours' => 6,
                'color_code' => '#db2777',
                'department_id' => $deptFoundation->id ?? 1,
                'head_instructor_id' => $headFoundation?->id,
                'status' => 'inactive',
                'prerequisite_codes' => ['NSBS 111'],
                'staff_usernames' => ['staff_01'],
                'instructors' => [
                    'instructor_01' => 'อาจารย์ผู้สอน',
                ],
            ],
            [
                'course_code' => 'NSBS 314',
                'name_th' => 'สุขภาพจิตและการพยาบาลจิตเวช 2',
                'name_en' => 'Mental Health and Psychiatric Nursing 2',
                'course_type' => 'practicum',
                'default_year_level' => 3,
                'credits' => 2,
                'lecture_hours' => 0,
                'lab_hours' => 2,
                'self_study_hours' => 4,
                'color_code' => '#7c3aed',
                'department_id' => $deptMental->id ?? 3,
                'head_instructor_id' => $headMental?->id,
                'status' => 'inactive',
                'prerequisite_codes' => ['NSBS 213'],
                'staff_usernames' => ['staff_01'],
                'instructors' => [],
            ],
        ];

        foreach ($courses as $courseData) {
            $staffUsernames = $courseData['staff_usernames'] ?? [];
            $instructorRoles = $courseData['instructors'] ?? [];
            $prerequisiteCodes = $courseData['prerequisite_codes'] ?? [];
            unset($courseData['staff_usernames'], $courseData['instructors'], $courseData['prerequisite_codes']);

            $courseData['curriculum_id'] = $curriculum->id;
            $course = Course::updateOrCreate(
                ['course_code' => $courseData['course_code'], 'curriculum_id' => $curriculum->id],
                $courseData
            );

            $staffIds = User::whereIn('username', $staffUsernames)->pluck('id')->all();
            $course->assignedStaff()->sync($staffIds);

            $instructorPayload = [];
            foreach ($instructorRoles as $username => $roleName) {
                $instructorId = User::where('username', $username)->value('id');
                if (!$instructorId) continue;

                $roleId = CourseRole::where('name_th', $roleName)->value('id');
                $instructorPayload[$instructorId] = ['course_role_id' => $roleId];
            }
            $course->instructors()->sync($instructorPayload);

            $prerequisiteIds = Course::where('curriculum_id', $curriculum->id)
                ->whereIn('course_code', $prerequisiteCodes)
                ->pluck('id')
                ->all();
            $course->prerequisites()->sync($prerequisiteIds);
        }
    }
}
