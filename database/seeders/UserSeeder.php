<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserRole;
use App\Models\InstructorProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = Hash::make('password');

        $users = [
            [
                'prefix' => 'นาย',
                'username' => 'admin_01',
                'name' => 'ราชันย์ พิพัฒน์',
                'email' => 'rachan@mahidol.edu',
                'is_active' => true,
                'roles' => [
                    ['role' => 'admin', 'is_primary' => true],
                    ['role' => 'instructor', 'is_primary' => false],
                ],
                'profile' => [
                    'employee_id' => '52123',
                    'title' => 'ผู้ช่วยอาจารย์',
                    'department_id' => 3, // ภาควิชาสุขภาพจิตฯ
                    'employment_type' => 'พนักงานมหาวิทยาลัย',
                    'academic_degree' => 'ปริญญาโท',
                    'teaching_pct' => 50,
                    'research_pct' => 20,
                    'service_pct' => 15,
                    'culture_pct' => 15,
                    'other_pct' => 0,
                    'teaching_quota' => 683,
                ]
            ],
            [
                'prefix' => 'นางสาว',
                'username' => 'pronpimon',
                'name' => 'พรภิมร ประเสริฐสุข',
                'email' => 'pronpimon@mahidol.edu',
                'is_active' => true,
                'roles' => [
                    ['role' => 'instructor', 'is_primary' => true],
                ],
                'profile' => [
                    'employee_id' => '14235',
                    'title' => 'ศาสตราจารย์',
                    'department_id' => 3, // ภาควิชาสุขภาพจิตฯ
                    'employment_type' => 'ข้าราชการ',
                    'academic_degree' => 'ปริญญาเอก',
                    'teaching_pct' => 20,
                    'research_pct' => 50,
                    'service_pct' => 20,
                    'culture_pct' => 10,
                    'other_pct' => 0,
                    'teaching_quota' => 273,
                ]
            ]
        ];

        foreach ($users as $userData) {
            $user = User::firstOrCreate(
                ['username' => $userData['username']],
                [
                    'prefix' => $userData['prefix'],
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'password' => $password,
                    'is_active' => $userData['is_active'],
                ]
            );

            // Roles
            foreach ($userData['roles'] as $roleData) {
                UserRole::firstOrCreate(
                    ['user_id' => $user->id, 'role' => $roleData['role']],
                    ['is_primary' => $roleData['is_primary']]
                );
            }

            // Profile
            if (isset($userData['profile'])) {
                InstructorProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    $userData['profile']
                );
            }
        }
    }
}
