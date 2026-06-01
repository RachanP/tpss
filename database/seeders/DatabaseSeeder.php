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
            // Demo helper: seeds holidays, demo course offerings, demo schedules and approval statuses
            ClientDemoSeeder::class,
        ]);
    }
}
