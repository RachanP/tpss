<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserRole;
use App\Models\InstructorProfile;
use Illuminate\Database\Seeder;
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = 'password';

        $users = [
            [
                'prefix'      => 'นาย',
                'username'    => 'admin_01',
                'employee_id' => '52123',
                'name'        => 'ราชันย์ พิพัฒน์',
                'email'       => 'rachan@mahidol.edu',
                'is_active'   => true,
                'roles' => [
                    ['role' => 'admin',       'is_primary' => true],
                    ['role' => 'instructor',  'is_primary' => false],
                ],
                'profile' => [
                    'title'          => 'ผู้ช่วยอาจารย์',
                    'department_id'  => 3,
                    'employment_type'=> 'พนักงานมหาวิทยาลัย',
                    'academic_degree'=> 'ปริญญาโท',
                    'teaching_pct'   => 50,
                    'research_pct'   => 20,
                    'service_pct'    => 15,
                    'culture_pct'    => 15,
                    'other_pct'      => 0,
                    'hired_at'       => '2020-01-15',
                    'teaching_quota' => 683,
                ],
            ],
            [
                'prefix'      => 'นางสาว',
                'username'    => 'pronpimon',
                'employee_id' => '14235',
                'name'        => 'พรภิมล ประเสริฐสุข',
                'email'       => 'pronpimon@mahidol.edu',
                'is_active'   => true,
                'roles' => [
                    ['role' => 'instructor', 'is_primary' => true],
                ],
                'profile' => [
                    'title'          => 'ศาสตราจารย์',
                    'department_id'  => 3,
                    'employment_type'=> 'ข้าราชการ',
                    'academic_degree'=> 'ปริญญาเอก',
                    'teaching_pct'   => 20,
                    'research_pct'   => 50,
                    'service_pct'    => 20,
                    'culture_pct'    => 10,
                    'other_pct'      => 0,
                    'hired_at'       => '2010-05-20',
                    'teaching_quota' => 273,
                ],
            ],
            [
                'prefix'      => 'ดร.',
                'username'    => 'somsak_t',
                'employee_id' => '60101',
                'name'        => 'สมศักดิ์ ตันติเวช',
                'email'       => 'somsak.tan@mahidol.edu',
                'is_active'   => true,
                'roles' => [
                    ['role' => 'instructor', 'is_primary' => true],
                    ['role' => 'staff',      'is_primary' => false],
                ],
                'profile' => [
                    'title'          => 'รองศาสตราจารย์',
                    'department_id'  => 1,
                    'employment_type'=> 'พนักงานมหาวิทยาลัย',
                    'academic_degree'=> 'ปริญญาเอก',
                    'teaching_pct'   => 40,
                    'research_pct'   => 40,
                    'service_pct'    => 10,
                    'culture_pct'    => 5,
                    'other_pct'      => 5,
                    'hired_at'       => '2005-10-10',
                    'teaching_quota' => 546,
                ],
            ],
            [
                'prefix'      => 'นาง',
                'username'    => 'staff_01',
                'employee_id' => '70001',
                'name'        => 'สมใจ รักดี',
                'email'       => 'somjai.rak@mahidol.edu',
                'is_active'   => true,
                'roles' => [
                    ['role' => 'staff', 'is_primary' => true],
                ],
            ],
        ];

        foreach ($users as $userData) {
            $user = User::firstOrCreate(
                ['username' => $userData['username']],
                [
                    'prefix'      => $userData['prefix'],
                    'employee_id' => $userData['employee_id'],
                    'name'        => $userData['name'],
                    'email'       => $userData['email'],
                    'password'    => $password,
                    'is_active'   => $userData['is_active'],
                ]
            );

            // Ensure employee_id is synced even if user already existed
            $user->update(['employee_id' => $userData['employee_id']]);

            foreach ($userData['roles'] as $roleData) {
                UserRole::firstOrCreate(
                    ['user_id' => $user->id, 'role' => $roleData['role']],
                    ['is_primary' => $roleData['is_primary']]
                );
            }

            if (isset($userData['profile'])) {
                InstructorProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    $userData['profile']
                );
            }
        }
    }
}
