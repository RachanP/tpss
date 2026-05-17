<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseOffering;
use Illuminate\Database\Seeder;

class CourseOfferingSeeder extends Seeder
{
    public function run(): void
    {
        $year = AcademicYear::where('is_active', true)->first() ?? AcademicYear::first();
        if (!$year) {
            $this->command->warn('ไม่พบปีการศึกษา — ข้ามการ seed course offerings');
            return;
        }

        // จำลองสถานะหลัง Admin กด "เปิดช่วงจัดตาราง":
        // สร้าง offering สำหรับทุกวิชาที่ active และมีหัวหน้าวิชา
        $courses = Course::where('status', 'active')
            ->whereNotNull('head_instructor_id')
            ->get();

        $created = 0;
        foreach ($courses as $course) {
            $offering = CourseOffering::firstOrCreate(
                ['course_id' => $course->id, 'academic_year_id' => $year->id],
                [
                    'coordinator_id'  => $course->head_instructor_id,
                    'approval_status' => 'draft',
                ]
            );
            $offering->attachCoordinator();
            $offering->copyInstructorPoolFromCourse();
            $created++;
        }

        // ตั้ง phase เป็น scheduling เพื่อให้หัวหน้าวิชาจัดการได้ทันที
        $year->update(['phase' => 'scheduling']);

        $this->command->info("CourseOfferingSeeder: สร้าง {$created} course offerings, ตั้ง phase = scheduling สำหรับ {$year->name} ภาค {$year->semester}");
    }
}
