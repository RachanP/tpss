<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserRole;
use App\Models\InstructorProfile;
use App\Models\Department;
use Illuminate\Database\Seeder;

/**
 * บัญชีผู้ใช้สำหรับ demo ลูกค้า — ครบทุกบทบาท จำลองการทำงานได้เสมือนจริง
 *
 * ชุดบัญชี (รหัสผ่านทุกบัญชี = "password"):
 *   admin_01   ผู้ดูแลระบบ
 *   head_med   หัวหน้าวิชา (ภาควิชาการพยาบาลรากฐาน) + อาจารย์   ← มีหลายบทบาท โชว์ปุ่มสลับบทบาท
 *   head_psy   หัวหน้าวิชา (ภาควิชาสุขภาพจิตฯ) + อาจารย์
 *   exec_01    ผู้บริหาร (อนุมัติ/ตรวจภาระงาน — read-only)
 *   instructor_01 อาจารย์ผู้สอน (เห็นตารางของตัวเอง)
 *   staff_01   เจ้าหน้าที่ (เห็นตารางทั้งคณะ)
 *   staff_02   เจ้าหน้าที่ (บัญชีที่ 2 — ให้ลูกค้าทดสอบงานเจ้าหน้าที่คู่ขนาน)
 *
 * หมายเหตุ: คง username `admin_01` + `staff_01` ไว้ (Playwright E2E login ด้วยสองตัวนี้)
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = 'password';
        $mentalHealthDepartmentId = Department::where('name', 'ภาควิชาสุขภาพจิต และการพยาบาลจิตเวชศาสตร์')->value('id');
        $foundationDepartmentId = Department::where('name', 'ภาควิชาการพยาบาลรากฐาน')->value('id');

        $users = [
            // ─── ผู้ดูแลระบบ ───────────────────────────────────────────────────
            [
                'prefix'      => 'นางสาว',
                'username'    => 'admin_01',
                'employee_id' => '52001',
                'name'        => 'สุดารัตน์ จัดการดี',
                'email'       => 'admin@tpss.demo',
                'is_active'   => true,
                'roles' => [
                    ['role' => 'admin', 'is_primary' => true],
                ],
            ],

            // ─── หัวหน้าวิชา (ภาควิชาการพยาบาลรากฐาน) + อาจารย์ ─────────────────
            [
                'prefix'      => 'นาง',
                'username'    => 'head_med',
                'employee_id' => '40101',
                'name'        => 'กาญจนา วิสุทธิ์',
                'email'       => 'head.med@tpss.demo',
                'is_active'   => true,
                'roles' => [
                    ['role' => 'course_head', 'is_primary' => true],
                    ['role' => 'instructor',  'is_primary' => false],
                ],
                // รองศาสตราจารย์ + ป.เอก: สอน 20-70, วิจัย 20-70, บริการ 5-20, ศิลปะ 5-15, อื่นๆ 0-20
                'profile' => [
                    'title'           => 'รองศาสตราจารย์',
                    'department_id'   => $foundationDepartmentId,
                    'employment_type' => 'พนักงานมหาวิทยาลัย',
                    'academic_degree' => 'ปริญญาเอก',
                    'hired_at'        => '2008-06-01',
                    'teaching_pct'    => 45,
                    'research_pct'    => 30,
                    'service_pct'     => 12,
                    'culture_pct'     => 8,
                    'other_pct'       => 5,
                ],
            ],

            // ─── หัวหน้าวิชา (ภาควิชาสุขภาพจิตฯ) + อาจารย์ ──────────────────────
            [
                'prefix'      => 'นาย',
                'username'    => 'head_psy',
                'employee_id' => '40201',
                'name'        => 'ธีรพงษ์ มั่นคง',
                'email'       => 'head.psy@tpss.demo',
                'is_active'   => true,
                'roles' => [
                    ['role' => 'course_head', 'is_primary' => true],
                    ['role' => 'instructor',  'is_primary' => false],
                ],
                // ผู้ช่วยศาสตราจารย์ + ป.เอก
                'profile' => [
                    'title'           => 'ผู้ช่วยศาสตราจารย์',
                    'department_id'   => $mentalHealthDepartmentId,
                    'employment_type' => 'พนักงานมหาวิทยาลัย',
                    'academic_degree' => 'ปริญญาเอก',
                    'hired_at'        => '2014-06-01',
                    'teaching_pct'    => 50,
                    'research_pct'    => 25,
                    'service_pct'     => 13,
                    'culture_pct'     => 7,
                    'other_pct'       => 5,
                ],
            ],

            // ─── อาจารย์ผู้สอน (ภาควิชาการพยาบาลรากฐาน) ────────────────────────
            [
                'prefix'      => 'นางสาว',
                'username'    => 'instructor_01',
                'employee_id' => '40102',
                'name'        => 'นภัส ใจดี',
                'email'       => 'teacher@tpss.demo',
                'is_active'   => true,
                'roles' => [
                    ['role' => 'instructor', 'is_primary' => true],
                ],
                // อาจารย์ + ป.โท
                'profile' => [
                    'title'           => 'อาจารย์',
                    'department_id'   => $foundationDepartmentId,
                    'employment_type' => 'พนักงานมหาวิทยาลัย',
                    'academic_degree' => 'ปริญญาโท',
                    'hired_at'        => '2019-06-01',
                    'teaching_pct'    => 55,
                    'research_pct'    => 22,
                    'service_pct'     => 13,
                    'culture_pct'     => 5,
                    'other_pct'       => 5,
                ],
            ],

            // ─── ผู้บริหาร (อนุมัติ/ตรวจ — read-only) ──────────────────────────
            [
                'prefix'      => 'นาง',
                'username'    => 'exec_01',
                'employee_id' => '10001',
                'name'        => 'สมหญิง อำนวยพร',
                'email'       => 'exec@tpss.demo',
                'is_active'   => true,
                'roles' => [
                    ['role' => 'executive', 'is_primary' => true],
                ],
                // executive: title + degree เพื่อแสดงชื่อถูกต้อง (รศ.ดร.สมหญิง)
                'profile' => [
                    'title'           => 'รองศาสตราจารย์',
                    'academic_degree' => 'ปริญญาเอก',
                ],
            ],

            // ─── เจ้าหน้าที่ (เห็นตารางทั้งคณะ) ─────────────────────────────────
            [
                'prefix'      => 'นาง',
                'username'    => 'staff_01',
                'employee_id' => '70001',
                'name'        => 'มาลี ประสานงาน',
                'email'       => 'staff@tpss.demo',
                'is_active'   => true,
                'roles' => [
                    ['role' => 'staff', 'is_primary' => true],
                ],
            ],

            // ─── เจ้าหน้าที่ คนที่ 2 (เห็นตารางทั้งคณะ) ──────────────────────────
            [
                'prefix'      => 'นาย',
                'username'    => 'staff_02',
                'employee_id' => '70002',
                'name'        => 'ปิยะ บริการเลิศ',
                'email'       => 'staff2@tpss.demo',
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
                    'employee_id' => $userData['employee_id'] ?? null,
                    'name'        => $userData['name'],
                    'email'       => $userData['email'],
                    'password'    => $password,
                    'is_active'   => $userData['is_active'],
                ]
            );

            $user->update([
                'prefix'      => $userData['prefix'],
                'name'        => $userData['name'],
                'email'       => $userData['email'],
                'employee_id' => $userData['employee_id'] ?? null,
            ]);

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
