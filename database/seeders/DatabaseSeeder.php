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
            LocationTypeSeeder::class,
            SystemSettingSeeder::class,
            DepartmentSeeder::class,
            UserSeeder::class,
            DepartmentSeeder::class,
            RoomSeeder::class,
        ]);
    }
}
