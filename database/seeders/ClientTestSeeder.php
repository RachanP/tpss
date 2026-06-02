<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\Holiday;
use Illuminate\Database\Seeder;

/**
 * Seeder เตรียม "สนามให้ลูกค้าลองจัดตารางเอง"
 *
 * ต่างจาก ClientDemoSeeder (ที่ pre-fill ตาราง + เปิด scheduling มาให้แล้ว):
 * ตัวนี้จัด master data ให้พร้อม แต่จงใจ "ยังไม่เลือกปีการศึกษา + ยังไม่เปิดช่วงจัดตาราง"
 * เพื่อให้ลูกค้าเดินครบ flow ด้วยตัวเอง:
 *   1. admin   → ตั้งค่าระบบ → เลือกปีการศึกษาปัจจุบัน (ตั้ง 2569 เป็นปีปัจจุบัน)
 *   2. admin   → เปิดช่วงจัดตาราง (critical gate เหลือแค่ no_active_year ที่เพิ่งเคลียร์)
 *   3. head_*  → เข้าหน้าจัดตาราง สร้างกิจกรรมเอง
 *
 * สถานะหลังรัน (migrate:fresh --seed):
 *   - ปีการศึกษา 2568/2569 มีในระบบ แต่ทั้งคู่ is_active=false, phase=preparation
 *   - รายวิชา demo 4 วิชา status=active + มีหัวหน้าวิชาครบ (จาก CourseSeeder)
 *     → ตอนลูกค้าเลือกปี + เปิด scheduling, critical gate (no_active_course /
 *       active_courses_missing_head) ผ่าน เหลือแค่เคลียร์ no_active_year ด้วยการเลือกปี
 *   - ยังไม่มี course_offering / schedule ใด ๆ — ลูกค้าสร้างเองหลังเปิด scheduling
 *
 * บัญชีทดสอบ (รหัสผ่าน password):
 *   admin_01 / head_med / head_psy / exec_01 / instructor_01 / staff_01 / staff_02
 *
 * ไม่แตะ DatabaseSeeder baseline ในโหมด testing — feature/E2E ยังใช้ preparation + วิชา inactive
 */
class ClientTestSeeder extends Seeder
{
    /** วิชาที่เปิดสอน (status=active) ในรอบทดสอบ — เล็ก กระชับ ครอบทั้ง 2 ภาควิชา */
    private const ACTIVE_COURSES = [
        'NSBS 111', // กระบวนการพยาบาล 1        — head_med (ภาควิชาการพยาบาลรากฐาน)
        'NSBS 212', // การพยาบาลเด็ก 1            — head_med
        'NSBS 213', // สุขภาพจิตและการพยาบาลจิตเวช 1 — head_psy (ภาควิชาสุขภาพจิตฯ)
        'NSBS 314', // สุขภาพจิตและการพยาบาลจิตเวช 2 — head_psy
    ];

    public function run(): void
    {
        // โหมดทดสอบ: คง baseline (preparation + วิชา inactive) ตามที่ feature/E2E test คาดหวัง
        if (app()->environment('testing')) {
            return;
        }

        // ลบ schedules/offerings ที่หลงเหลือจากรอบเดิม เพื่อเตรียมสนามใหม่ให้ลูกค้า
        // (ลำดับสำคัญ: schedule ก่อน → อื่น ๆ ที่อ้าง schedule จาก cascade)
        \App\Models\Schedule::query()->delete();
        \App\Models\CourseOffering::query()->delete();

        $this->ensureNoActiveYear();
        $this->activateDemoCourses();
        $this->seedHolidays();

        $this->printSummary();
    }

    /** ทุกปีกลับสู่ "ยังไม่เลือก" — is_active=false + phase=preparation ให้ลูกค้าเลือกเอง */
    private function ensureNoActiveYear(): void
    {
        AcademicYear::query()->update([
            'is_active' => false,
            'phase'     => 'preparation',
        ]);
    }

    /** เปิดสอนเฉพาะวิชา demo (status=active) — วิชาเหล่านี้มีหัวหน้าวิชาครบจาก CourseSeeder */
    private function activateDemoCourses(): void
    {
        Course::query()
            ->whereIn('course_code', self::ACTIVE_COURSES)
            ->get()
            ->each(fn (Course $course) => $course->forceFill(['status' => 'active'])->save());
    }

    /**
     * วันหยุดราชการไทยในช่วงปีการศึกษา 2569 (source=manual)
     * — ปกติระบบดึงจาก Google ICS ตอน admin สร้าง/แก้ปีผ่าน UI; seed มือไว้ก่อน
     *   ให้ปฏิทินลงสีวันหยุดทันทีที่ลูกค้าเปิดหน้าจัดตาราง (refresh google ไม่ลบ source=manual)
     */
    private function seedHolidays(): void
    {
        $holidays = [
            ['date' => '2026-06-03', 'name' => 'วันเฉลิมพระชนมพรรษาสมเด็จพระนางเจ้าฯ พระบรมราชินี'],
            ['date' => '2026-07-28', 'name' => 'วันเฉลิมพระชนมพรรษาพระบาทสมเด็จพระเจ้าอยู่หัว'],
            ['date' => '2026-08-12', 'name' => 'วันเฉลิมพระชนมพรรษาสมเด็จพระบรมราชชนนีพันปีหลวง (วันแม่แห่งชาติ)'],
            ['date' => '2026-10-13', 'name' => 'วันคล้ายวันสวรรคต ร.9'],
            ['date' => '2026-10-23', 'name' => 'วันปิยมหาราช'],
            ['date' => '2026-12-05', 'name' => 'วันคล้ายวันพระบรมราชสมภพ ร.9 (วันพ่อแห่งชาติ)'],
            ['date' => '2026-12-10', 'name' => 'วันรัฐธรรมนูญ'],
            ['date' => '2026-12-31', 'name' => 'วันสิ้นปี'],
            ['date' => '2027-01-01', 'name' => 'วันขึ้นปีใหม่'],
        ];

        foreach ($holidays as $h) {
            Holiday::updateOrCreate(
                ['date' => $h['date']],
                ['name' => $h['name'], 'remark' => 'งดการเรียนการสอน', 'source' => 'manual']
            );
        }
    }

    private function printSummary(): void
    {
        $this->command?->info('ClientTestSeeder เสร็จแล้ว — สนามให้ลูกค้าลองจัดตารางเอง:');
        $this->command?->info('  • ปีการศึกษา 2568/2569 อยู่ในระบบ แต่ "ยังไม่เลือกปีปัจจุบัน" + phase=preparation');
        $this->command?->info('  • วิชาเปิดสอน (active + มีหัวหน้าวิชา): ' . implode(', ', self::ACTIVE_COURSES));
        $this->command?->info('  • ยังไม่มี course offering / ตาราง — ลูกค้าสร้างเองหลังเปิดช่วงจัดตาราง');
        $this->command?->info('  ขั้นตอนทดสอบ:');
        $this->command?->info('    1) admin_01  → ตั้งค่าระบบ → เลือกปีการศึกษา 2569 เป็นปีปัจจุบัน');
        $this->command?->info('    2) admin_01  → เปิดช่วงจัดตาราง (auto-create offering ของวิชา active)');
        $this->command?->info('    3) head_med / head_psy → เข้าหน้าตารางสอน สร้างกิจกรรมเอง');
        $this->command?->info('  บัญชี (รหัสผ่าน password): admin_01 / head_med / head_psy / exec_01 / instructor_01 / staff_01 / staff_02');
    }
}
