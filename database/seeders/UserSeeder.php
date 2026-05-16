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
            // ─── Admin ────────────────────────────────────────────────────────
            [
                'prefix'      => 'นาย',
                'username'    => 'admin_01',
                'employee_id' => '52123',
                'name'        => 'ราชันย์ พิพัฒน์',
                'email'       => 'rachan@mahidol.edu',
                'is_active'   => true,
                'roles' => [
                    ['role' => 'admin',        'is_primary' => true],
                    ['role' => 'instructor',   'is_primary' => false],
                    ['role' => 'course_head',  'is_primary' => false],
                ],
                // ผู้ช่วยอาจารย์ + ป.โท: สอน ≤70%, วิจัย 15-20%, บริการ 5-20%, ศิลปะ 0-15%, อื่นๆ 0-20%
                'profile' => [
                    'title'           => 'ผู้ช่วยอาจารย์',
                    'department_id'   => 3,
                    'employment_type' => 'พนักงานมหาวิทยาลัย',
                    'academic_degree' => 'ปริญญาโท',
                    'hired_at'        => '2020-01-15',
                    'teaching_pct'    => 50,
                    'research_pct'    => 18,
                    'service_pct'     => 17,
                    'culture_pct'     => 10,
                    'other_pct'       => 5,
                ],
            ],

            // ─── Instructor + Course Head (ภาควิชาสุขภาพจิต dept 3) ───────────
            [
                'prefix'      => 'นางสาว',
                'username'    => 'pronpimon',
                'employee_id' => '14235',
                'name'        => 'พรภิมล ประเสริฐสุข',
                'email'       => 'pronpimon@mahidol.edu',
                'is_active'   => true,
                'roles' => [
                    ['role' => 'instructor',   'is_primary' => true],
                    ['role' => 'course_head',  'is_primary' => false],
                ],
                // ศาสตราจารย์ + ป.เอก: สอน 20-70%, วิจัย 20-70%, บริการ 5-20%, ศิลปะ 5-15%, อื่นๆ 0-20%
                'profile' => [
                    'title'           => 'ศาสตราจารย์',
                    'department_id'   => 3,
                    'employment_type' => 'ข้าราชการ',
                    'academic_degree' => 'ปริญญาเอก',
                    'hired_at'        => '2010-05-20',
                    'teaching_pct'    => 25,
                    'research_pct'    => 45,
                    'service_pct'     => 15,
                    'culture_pct'     => 10,
                    'other_pct'       => 5,
                ],
            ],

            // ─── Instructor + Staff + Course Head (ภาควิชาการพยาบาลรากฐาน dept 1) ──
            [
                'prefix'      => 'ดร.',
                'username'    => 'somsak_t',
                'employee_id' => '60101',
                'name'        => 'สมศักดิ์ ตันติเวช',
                'email'       => 'somsak.tan@mahidol.edu',
                'is_active'   => true,
                'roles' => [
                    ['role' => 'instructor',  'is_primary' => true],
                    ['role' => 'staff',       'is_primary' => false],
                    ['role' => 'course_head', 'is_primary' => false],
                ],
                // รองศาสตราจารย์ + ป.เอก: สอน 20-70%, วิจัย 20-70%, บริการ 5-20%, ศิลปะ 5-15%, อื่นๆ 0-20%
                'profile' => [
                    'title'           => 'รองศาสตราจารย์',
                    'department_id'   => 1,
                    'employment_type' => 'พนักงานมหาวิทยาลัย',
                    'academic_degree' => 'ปริญญาเอก',
                    'hired_at'        => '2005-10-10',
                    'teaching_pct'    => 40,
                    'research_pct'    => 35,
                    'service_pct'     => 12,
                    'culture_pct'     => 8,
                    'other_pct'       => 5,
                ],
            ],

            // ─── Staff ────────────────────────────────────────────────────────
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

            // ─── Executive ────────────────────────────────────────────────────
            [
                'prefix'      => 'นาง',
                'username'    => 'exec_01',
                'employee_id' => '11001',
                'name'        => 'วิไล สุขสมบูรณ์',
                'email'       => 'wilai.suk@mahidol.edu',
                'is_active'   => true,
                'roles' => [
                    ['role' => 'executive', 'is_primary' => true],
                ],
                // executive: title + degree เพื่อแสดงชื่อถูกต้อง (รศ.ดร.วิไล)
                'profile' => [
                    'title'           => 'รองศาสตราจารย์',
                    'academic_degree' => 'ปริญญาเอก',
                ],
            ],

            // ─── Executive (ผศ.ดร.) ───────────────────────────────────────────
            [
                'prefix'      => 'นาย',
                'username'    => 'phuwadol_t',
                'employee_id' => '11002',
                'name'        => 'ภูวดล ทองรอง',
                'email'       => 'phuwadol.tho@mahidol.edu',
                'is_active'   => true,
                'roles' => [
                    ['role' => 'executive', 'is_primary' => true],
                ],
                // executive: เก็บแค่ title + degree (ไม่มี PA ratios)
                'profile' => [
                    'title'           => 'ผู้ช่วยศาสตราจารย์',
                    'academic_degree' => 'ปริญญาเอก',
                ],
            ],
        ];

        foreach ($users as $userData) {
            $user = User::firstOrCreate(
                ['username' => $userData['username']],
                [
                    'prefix'      => $userData['prefix'],
                    'employee_id' => $userData['employee_id'] ?? null,
                    'name'        => $userData['name'],
                    'email'       => $userData['email'],
                    'password'    => $password,
                    'is_active'   => $userData['is_active'],
                ]
            );

            $user->update(['employee_id' => $userData['employee_id'] ?? null]);

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
