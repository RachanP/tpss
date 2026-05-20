<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseRole;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class CourseOfferingSeeder extends Seeder
{
    public function run(): void
    {
        $year = AcademicYear::where('is_active', true)->first();
        if (!$year) {
            $this->command->info('CourseOfferingSeeder: ข้ามแล้ว — ยังไม่ได้เลือกปีการศึกษาปัจจุบัน');
            return;
        }

        // จำลองสถานะหลัง Admin กด "เปิดช่วงจัดตาราง":
        // สร้าง offering สำหรับทุกวิชาที่ active และมีหัวหน้าวิชา
        $courses = Course::where('status', 'active')
            ->whereNotNull('head_instructor_id')
            ->get();

        $created = 0;
        $synced = 0;
        $teachingWeeks = (int) SystemSetting::get('teaching_load_weeks', 39);
        $coordinatorRoleId = CourseRole::where('name_th', 'หัวหน้าวิชา')->value('id');

        foreach ($courses as $course) {
            $offering = CourseOffering::firstOrNew([
                'course_id' => $course->id,
                'academic_year_id' => $year->id,
            ]);

            if (! $offering->exists) {
                $offering->approval_status = 'draft';
                $created++;
            }

            $offering->fill([
                'coordinator_id' => $course->head_instructor_id,
                'total_student_count' => $course->capacity,
                'planned_lecture_hours' => $course->lecture_hours,
                'planned_lab_hours' => $course->requires_practicum_rotation ? 0 : $course->lab_hours,
                'planned_practicum_hours' => $course->requires_practicum_rotation ? $course->lab_hours : 0,
                'teaching_weeks' => $teachingWeeks,
                'requires_practicum_rotation' => $course->requires_practicum_rotation,
                'practicum_note' => null,
            ]);
            $offering->save();
            $offering->syncInstructorPoolFromCourseTemplate($coordinatorRoleId);
            $synced++;
        }

        // ตั้ง phase เป็น scheduling เพื่อให้หัวหน้าวิชาจัดการได้ทันที
        $year->update(['phase' => 'scheduling']);

        $this->command->info("CourseOfferingSeeder: สร้างใหม่ {$created} รายวิชา, sync {$synced} course offerings, ตั้ง phase = scheduling สำหรับ {$year->name} ภาค {$year->semester}");
    }
}
