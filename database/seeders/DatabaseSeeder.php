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
            DepartmentSeeder::class,
            AcademicYearSeeder::class,
            UserSeeder::class,
            RoomSeeder::class,
            CourseSeeder::class,
            ActivityTypeSeeder::class,
            CourseOfferingSeeder::class,
        ]);
    }
}
