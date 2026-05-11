<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            ['name' => 'ภาควิชาการพยาบาลรากฐาน'],
            ['name' => 'ภาควิชาการพยาบาลกุมารเวชศาสตร์'],
            [
                'name' => 'ภาควิชาสุขภาพจิต และการพยาบาลจิตเวชศาสตร์',
                'head_username' => 'admin_01',
                'secretary_username' => 'pronpimon'
            ],
            ['name' => 'ภาควิชาการพยาบาลอายุรศาสตร์'],
            ['name' => 'ภาควิชาการพยาบาลสูติศาสตร์ - นรีเวชวิทยา'],
            ['name' => 'ภาควิชาการพยาบาลสาธารณสุขศาสตร์'],
            ['name' => 'ภาควิชาการพยาบาลศัลยศาสตร์'],
        ];

        foreach ($departments as $data) {
            $dept = Department::firstOrCreate(['name' => $data['name']]);
            
            // Assign head if specified (will only work if users are already seeded or run via DatabaseSeeder after UserSeeder)
            if (isset($data['head_username'])) {
                $user = User::where('username', $data['head_username'])->first();
                if ($user) {
                    $dept->update(['head_user_id' => $user->id]);
                }
            }
            
            if (isset($data['secretary_username'])) {
                $user = User::where('username', $data['secretary_username'])->first();
                if ($user) {
                    $dept->update(['secretary_user_id' => $user->id]);
                }
            }
        }
    }
}
