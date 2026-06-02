<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CurriculumSeeder::class,
            LocationTypeSeeder::class,
            SystemSettingSeeder::class,
            AcademicYearSeeder::class,
            DepartmentSeeder::class,   // pass 1: create departments (head/secretary skipped, users don't exist yet)
            UserSeeder::class,         // create users + instructor_profiles (needs departments FK)
            DepartmentSeeder::class,   // pass 2: assign head/secretary now that users exist
            RoomSeeder::class,
            CourseRoleSeeder::class,
            CourseSeeder::class,
            ActivityTypeSeeder::class,
            CourseOfferingSeeder::class,
            StudentCohortSeeder::class,
            // สนามให้ลูกค้าลองจัดตารางเอง: เปิดสอนวิชา demo + วันหยุด แต่ยัง "ไม่เลือกปี + ไม่เปิด scheduling"
            // (ลูกค้าเดิน flow เอง: เลือกปี → เปิดช่วงจัดตาราง → สร้างกิจกรรม)
            // ต้องการสถานะ demo แบบ pre-fill ตาราง+การชน ให้รันเพิ่ม: php artisan db:seed --class=ClientDemoSeeder
            ClientTestSeeder::class,
        ]);
    }
}
