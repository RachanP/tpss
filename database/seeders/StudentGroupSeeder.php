<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class StudentGroupSeeder extends Seeder
{
    public function run(): void
    {
        // กลุ่มนักศึกษาย้ายไปสร้างใน CourseOfferingSeeder แล้ว (per course_offering)
        $this->command->info('StudentGroupSeeder: ข้ามแล้ว — ใช้ CourseOfferingSeeder แทน');
    }
}
