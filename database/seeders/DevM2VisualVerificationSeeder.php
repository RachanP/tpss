<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DevM2VisualVerificationSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DevM2VisualVerificationSeeder may only run in local or testing environments.');
        }

        $courseHead = User::where('username', 'admin_01')->first();

        if (! $courseHead) {
            throw new RuntimeException('Cannot seed M2 visual data: user admin_01 is missing. Run base seeders first.');
        }

        UserRole::firstOrCreate(
            ['user_id' => $courseHead->id, 'role' => 'course_head'],
            ['is_primary' => false]
        );

        $academicYear = AcademicYear::where('is_active', true)->first();

        if (! $academicYear) {
            throw new RuntimeException('Cannot seed M2 visual data: no active academic year exists.');
        }

        $prerequisiteCourse = $this->requiredCourse('NSBS 111');
        $activeCourse = $this->requiredCourse('NSBS 212');
        $archivedCourse = $this->requiredCourse('NSBS 213');

        $activeOffering = CourseOffering::updateOrCreate(
            [
                'course_id' => $activeCourse->id,
                'academic_year_id' => $academicYear->id,
                'coordinator_id' => $courseHead->id,
            ],
            [
                'approval_status' => 'draft',
                'status' => 'active',
                'total_student_count' => 60,
                'planned_lecture_hours' => 2,
                'planned_lab_hours' => 1,
                'planned_practicum_hours' => 0,
                'teaching_weeks' => 15,
                'requires_practicum_rotation' => false,
                'practicum_note' => 'Dev visual verification data for Sprint 3 M2.',
                'archived_at' => null,
                'archived_by' => null,
                'archive_reason' => null,
            ]
        );

        $archivedOffering = CourseOffering::updateOrCreate(
            [
                'course_id' => $archivedCourse->id,
                'academic_year_id' => $academicYear->id,
                'coordinator_id' => $courseHead->id,
            ],
            [
                'approval_status' => 'draft',
                'status' => 'archived',
                'total_student_count' => 45,
                'planned_lecture_hours' => 2,
                'planned_lab_hours' => 0,
                'planned_practicum_hours' => 0,
                'teaching_weeks' => 15,
                'requires_practicum_rotation' => false,
                'practicum_note' => 'Archived dev visual verification data for Sprint 3 M2.',
                'archived_at' => now(),
                'archived_by' => $courseHead->id,
                'archive_reason' => 'Dev visual verification archive sample.',
            ]
        );

        $activeOffering->studentGroups()->updateOrCreate(
            ['group_code' => 'A1'],
            ['student_count' => 30, 'color_code' => '#2563eb']
        );

        $activeOffering->studentGroups()->updateOrCreate(
            ['group_code' => 'A2'],
            ['student_count' => 30, 'color_code' => '#16a34a']
        );

        $instructors = User::query()
            ->where('is_active', true)
            ->whereHas('instructorProfile')
            ->orderByRaw('CASE WHEN id = ? THEN 1 ELSE 0 END', [$courseHead->id])
            ->orderBy('id')
            ->limit(2)
            ->get();

        if ($instructors->isEmpty()) {
            throw new RuntimeException('Cannot seed M2 visual data: no active instructor profile users exist.');
        }

        foreach ($instructors as $instructor) {
            DB::table('course_offering_instructors')->updateOrInsert(
                [
                    'course_offering_id' => $activeOffering->id,
                    'user_id' => $instructor->id,
                ],
                ['role_in_course' => 'instructor']
            );
        }

        $activeCourse->prerequisites()->syncWithoutDetaching([$prerequisiteCourse->id]);

        $this->command?->info(
            "DevM2VisualVerificationSeeder: active offering {$activeOffering->id}, archived offering {$archivedOffering->id} for admin_01."
        );
    }

    private function requiredCourse(string $courseCode): Course
    {
        $course = Course::where('course_code', $courseCode)->first();

        if (! $course) {
            throw new RuntimeException("Cannot seed M2 visual data: course {$courseCode} is missing. Run base seeders first.");
        }

        return $course;
    }
}
