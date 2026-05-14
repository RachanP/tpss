<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\User;
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

        $coordinator = User::whereHas('roles', fn($q) => $q->where('role', 'course_head'))->first()
            ?? User::whereHas('roles', fn($q) => $q->where('role', 'admin'))->first()
            ?? User::first();

        if (!$coordinator) {
            $this->command->warn('ไม่พบผู้ใช้งาน — ข้ามการ seed course offerings');
            return;
        }

        $courseCodes = ['NSBS 111', 'NSBS 212', 'NSBS 213', 'NSBS 221'];

        $count = 0;
        foreach ($courseCodes as $code) {
            $course = Course::where('course_code', $code)->first();
            if (!$course) continue;

            CourseOffering::firstOrCreate(
                ['course_id' => $course->id, 'academic_year_id' => $year->id],
                [
                    'coordinator_id'  => $coordinator->id,
                    'approval_status' => 'draft',
                ]
            );

            $count++;
        }

        $this->command->info("CourseOfferingSeeder: สร้าง {$count} course offerings (ยังไม่มีกลุ่มนักศึกษา — สร้างใน M2)");
    }
}
