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
            SystemSettingSeeder::class,
            DepartmentSeeder::class, // รอบแรกสร้างชื่อภาควิชา
            UserSeeder::class,       // สร้างผู้ใช้งานและผูกกับภาควิชา
            DepartmentSeeder::class, // รอบสองผูกหัวหน้า/เลขาภาควิชา
        ]);
    }
}
