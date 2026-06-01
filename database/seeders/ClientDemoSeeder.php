<?php

namespace Database\Seeders;

use App\Jobs\ConflictRecomputeJob;
use App\Models\AcademicYear;
use App\Models\ActivityType;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Holiday;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ScheduleConflictRun;
use App\Models\User;
use App\Services\NavigationBadgeService;
use App\Services\ScheduleConflictIndex;
use App\Services\ScheduleConflictInvalidationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder สาธิตสำหรับลูกค้า — จัดสถานะข้อมูลแบบ "ผสม" ให้ทุกบทบาทมีของให้ดูและให้ลองทำ
 *
 * รัน (หลัง migrate:fresh --seed):
 *   php artisan db:seed --class=ClientDemoSeeder
 *
 * หรือทีเดียวจบ:
 *   php artisan migrate:fresh --seed && php artisan db:seed --class=ClientDemoSeeder
 *
 * สถานะที่ seed (ปีการศึกษา 2569, phase = scheduling):
 *   ทุกรายวิชา = draft (ยังจัดตารางอยู่) — ระบบยังทำแค่ส่วน "จัดตาราง" ยังไม่มี workflow อนุมัติ (M11 = Phase 2)
 *   จึงไม่มี offering ใดไปถึง pending/published ได้จริง
 *   NSBS 111 (head_med)  : slot ครบ — เป็น slot ต้นทางให้ 212 ไปชนข้ามวิชา
 *   NSBS 212 (head_med)  : มีการชนข้ามวิชา + ห้องใช้ร่วม + ข้อมูลไม่ครบ → โชว์ smart check
 *   NSBS 213 (head_psy)  : slot ครบ ไม่มีการชน
 *   NSBS 314 (head_psy)  : มีกิจกรรมตรงวันหยุด (holiday warning) + เหลือพื้นที่ให้ลองจัดเอง
 *
 * ไม่แตะ DatabaseSeeder (E2E/test ยังใช้ baseline เดิม = preparation)
 */
class ClientDemoSeeder extends Seeder
{
    /**
     * รายวิชาที่เปิดใน demo + สถานะอนุมัติ
     * ยังไม่มี workflow อนุมัติ → ทุกวิชาเป็น draft (สถานะตั้งต้นเดียวที่ระบบไปถึงได้จริง)
     */
    private const DEMO_COURSES = [
        'NSBS 111' => 'draft',
        'NSBS 212' => 'draft',
        'NSBS 213' => 'draft',
        'NSBS 314' => 'draft',
    ];

    public function run(): void
    {
        $year = $this->activateSchedulingYear();
        $this->seedHolidays($year);
        $this->activateDemoCourses();

        // สร้าง course offerings (วิชา active ในหลักสูตร active) + ตั้ง phase = scheduling
        $this->call(CourseOfferingSeeder::class);

        $offerings = $this->demoOfferings($year);
        if ($offerings->isEmpty()) {
            $this->command?->warn('ClientDemoSeeder: ไม่พบ course offering ของวิชา demo — ข้ามการ seed ตาราง');

            return;
        }

        $this->seedSchedules($year, $offerings);
        $this->applyApprovalStatuses($offerings);
        $this->warmConflicts($year);

        $this->printSummary();
    }

    /** ปี 2569 (ล่าสุด) = active + scheduling, ปิดปีอื่น */
    private function activateSchedulingYear(): AcademicYear
    {
        $year = AcademicYear::query()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->firstOrFail();

        AcademicYear::query()->whereKeyNot($year->id)->update(['is_active' => false]);

        $year->forceFill(['is_active' => true, 'phase' => 'scheduling'])->save();

        return $year->refresh();
    }

    /**
     * วันหยุดราชการไทยในช่วงปีการศึกษา (source=manual)
     * — ปกติระบบดึงจาก Google ICS ตอน admin สร้าง/แก้ปีผ่าน UI แต่ seeder ไม่ผ่าน flow นั้น
     *   จึง seed ชุดมือไว้ให้ปฏิทินลงสีวันหยุด + เปิด holiday warning ใน demo
     */
    private function seedHolidays(AcademicYear $year): void
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

    /** เปิดสอน (status=active) เฉพาะวิชา demo */
    private function activateDemoCourses(): void
    {
        Course::query()
            ->whereIn('course_code', array_keys(self::DEMO_COURSES))
            ->get()
            ->each(fn (Course $course) => $course->forceFill(['status' => 'active'])->save());
    }

    /** @return \Illuminate\Support\Collection<string, CourseOffering> keyed by course_code */
    private function demoOfferings(AcademicYear $year)
    {
        return CourseOffering::query()
            ->with(['course', 'instructorPool'])
            ->where('academic_year_id', $year->id)
            ->whereHas('course', fn ($q) => $q->whereIn('course_code', array_keys(self::DEMO_COURSES)))
            ->get()
            ->keyBy(fn (CourseOffering $offering) => $offering->course?->course_code);
    }

    private function seedSchedules(AcademicYear $year, $offerings): void
    {
        $term = $year->terms()->orderBy('sequence')->first();
        $lecture = ActivityType::query()->where('category', 'lecture')->orderBy('id')->first()
            ?? ActivityType::query()->orderBy('id')->firstOrFail();
        $practicum = ActivityType::query()->where('category', 'practicum')->orderBy('id')->first()
            ?? $lecture;

        // ห้องไม่ใช้ร่วม 3 ห้อง — แยกชัดเพื่อแยกเคสชนห้อง/ชนผู้สอน
        $rooms = Room::query()
            ->where('status', 'active')
            ->whereHas('locationType', fn ($q) => $q->where('is_shared', false))
            ->orderBy('id')
            ->take(3)
            ->get();
        $roomA = $rooms->get(0) ?? Room::query()->where('status', 'active')->orderBy('id')->firstOrFail();
        $roomB = $rooms->get(1) ?? $roomA;
        $roomC = $rooms->get(2) ?? $roomB;

        // ห้องใช้ร่วม (is_shared) สำหรับเคส "หลายวิชาใช้สถานที่เดียวกันพร้อมกันได้ = ไม่ชน"
        $roomShared = Room::query()
            ->where('status', 'active')
            ->whereHas('locationType', fn ($q) => $q->where('is_shared', true))
            ->orderBy('id')
            ->first();

        // ผู้สอนของฝั่ง NSBS 111/212 (ภาควิชาการพยาบาลรากฐาน)
        $headMed = User::where('username', 'head_med')->first();
        $teacher = User::where('username', 'instructor_01')->first();
        // ผู้สอนจากภาควิชาอื่น (ใช้ทดสอบผู้สอนต่างภาควิชา)
        $headPsy = User::where('username', 'head_psy')->first();

        // วันแรกที่เป็นวันทำการ (จันทร์) ในเทอม 1
        $termStart = CarbonImmutable::parse($term?->start_date ?? $year->start_date);
        $monday = $termStart->startOfWeek(CarbonImmutable::MONDAY);
        if ($monday->lt($termStart)) {
            $monday = $monday->addWeek();
        }
        $mon = $monday->toDateString();
        $tue = $monday->addDays(1)->toDateString();
        $wed = $monday->addDays(2)->toDateString();
        $thu = $monday->addDays(3)->toDateString();
        $fri = $monday->addDays(4)->toDateString();

        // หมายเหตุ: พ. 06-03 = วันหยุด (วันเฉลิมพระชนมพรรษาฯ) → เลี่ยงไม่วาง slot ชน/pending วันพุธ
        //          ใช้วันพุธสำหรับโชว์ holiday warning โดยเฉพาะ (NSBS 314)
        DB::transaction(function () use ($offerings, $lecture, $practicum, $roomA, $roomB, $roomC, $roomShared, $headMed, $teacher, $headPsy, $mon, $wed, $tue, $thu, $fri) {
            // ── NSBS 111 (published) — slot "ต้นทาง" ที่ NSBS 212 จะไปชนข้ามวิชา ──
            if ($o = $offerings->get('NSBS 111')) {
                $this->clearDemo($o->id);
                // จ. 09–12 ห้อง B (ต้นทางเคส "ชนห้องข้ามวิชา")
                $this->makeSlot($o, $lecture, $roomB, $teacher, $mon, '09:00', '12:00', 'บรรยาย: บทนำกระบวนการพยาบาล', 'approved');
                // อ. 13–16 ห้อง B สอนโดย instructor_01 (ต้นทางเคส "ชนผู้สอนข้ามวิชา")
                $this->makeSlot($o, $lecture, $roomB, $teacher, $tue, '13:00', '16:00', 'บรรยาย: การประเมินภาวะสุขภาพ', 'approved');
                // ศ. 13–16 ห้องใช้ร่วม (ต้นทางเคส "ห้องใช้ร่วม = ไม่ชน" กับ NSBS 212)
                if ($roomShared) {
                    $this->makeSlot($o, $practicum, $roomShared, $teacher, $fri, '13:00', '16:00', 'ปฏิบัติ: ฝึกหอผู้ป่วย (สถานที่ใช้ร่วม)', 'approved');
                }
            }

            // ── NSBS 212 (draft) — โชว์การชน "ข้ามวิชา" + ห้องใช้ร่วม + incomplete ──
            // หมายเหตุ: ไม่ seed "การชนในวิชา" เพราะ realtime check บล็อกปุ่มบันทึก → สร้างไม่ได้จริง
            //          การ์ด "การชนข้ามวิชา" มีไว้แสดงเฉพาะการชนข้ามรายวิชา (เกิดได้จริงตอนวิชาอื่นจองทับ)
            if ($o = $offerings->get('NSBS 212')) {
                $this->clearDemo($o->id);

                // [1] ชนห้อง ข้าม-วิชา: จ. 09–12 ห้อง B เดียวกับ NSBS 111 (คนละผู้สอน → ชนเฉพาะห้อง)
                $this->makeSlot($o, $lecture, $roomB, $headMed, $mon, '09:00', '12:00', '[ชนห้องข้ามวิชา] บรรยาย: ภูมิคุ้มกันเด็ก (ห้องเดียวกับ NSBS 111)', 'draft');

                // [2] ชนผู้สอน ข้าม-วิชา: อ. 13–16 ผู้สอน instructor_01 เดียวกับ NSBS 111 (คนละห้อง → ชนเฉพาะผู้สอน)
                $this->makeSlot($o, $lecture, $roomA, $teacher, $tue, '13:00', '16:00', '[ชนผู้สอนข้ามวิชา] บรรยาย: พัฒนาการเด็ก (ผู้สอนเดียวกับ NSBS 111)', 'draft');

                // [3] ห้องใช้ร่วม = ไม่ชน: ศ. 13–16 ห้องใช้ร่วมเดียวกับ NSBS 111 คนละผู้สอน → ไม่ถือว่าชน
                if ($roomShared) {
                    $this->makeSlot($o, $practicum, $roomShared, $headMed, $fri, '13:00', '16:00', '[ห้องใช้ร่วม=ไม่ชน] ปฏิบัติ: ฝึกหอผู้ป่วย (พร้อม NSBS 111 ได้)', 'draft');
                }

                // [W1] ข้อมูลไม่ครบ (incomplete): ศ. 09–12 มีหัวข้อ+ห้อง แต่ "ยังไม่ระบุผู้สอน"
                $this->makeSlot($o, $lecture, $roomC, null, $fri, '09:00', '12:00', '[ข้อมูลไม่ครบ] บรรยาย: ทบทวนบทเรียน (ยังไม่ระบุผู้สอน)', 'draft');

                // [H1] กาญจนา วิสุทธิ์ (head_med) - สาธิตกิจกรรมตรงวันหยุด (holiday warning)
                $this->makeSlot($o, $lecture, $roomA, $headMed, $wed, '10:00', '12:00', '[ตรงวันหยุด] บรรยายพิเศษ: การประเมินความเสี่ยง (holiday warning)', 'draft');

                // [C1] ความจุเกิน: สร้างกลุ่มนักศึกษา 2 กลุ่ม รวมเกินความจุของห้อง
                $capSlot = $this->makeSlot($o, $practicum, $roomA, $headMed, $fri, '14:00', '16:00', '[ความจุเกิน] ปฏิบัติ: ฝึกห้องเล็ก (ทดสอบ capacity)', 'draft');
                // สร้าง student groups สำหรับ offering นี้แล้วผูกกับ slot
                $g1 = \App\Models\StudentGroup::updateOrCreate([
                    'course_offering_id' => $o->id,
                    'group_code' => 'A1',
                ],[
                    'student_count' => 25,
                    'color_code' => '#f3c2a0',
                ]);
                $g2 = \App\Models\StudentGroup::updateOrCreate([
                    'course_offering_id' => $o->id,
                    'group_code' => 'A2',
                ],[
                    'student_count' => 20,
                    'color_code' => '#a0c2f3',
                ]);
                $capSlot->studentGroups()->sync([$g1->id, $g2->id]);
                // ตั้ง capacity_required ให้เล็กกว่าจำนวนจริง (30) เพื่อให้เกิด alert
                $capSlot->update(['capacity_required' => 30]);

                // [D1] ผู้สอนต่างภาควิชา: ให้หัวหน้าภาควิชาสุขภาพจิตมาเป็นผู้สอนในวิชา NSBS 212 (ต่างภาค)
                $this->makeSlot($o, $lecture, $roomC, $headPsy, $thu, '09:00', '12:00', '[ผู้สอนต่างภาควิชา] บรรยาย: ข้ามภาคทดลอง', 'draft');

                // [R1] ไม่มีผู้สอนนำ (no lead): สร้าง slot ที่มีผู้สอนแต่ไม่มี is_lead flag (attach later)
                $noLead = $this->makeSlot($o, $lecture, $roomB, null, $thu, '13:00', '15:00', '[ไม่มีผู้สอนนำ] สัมมนา: ยังไม่กำหนด lead', 'draft');
                if ($teacher) {
                    $noLead->instructors()->sync([$teacher->id => ['is_lead' => false]]);
                }
            }

            // ── NSBS 213 (pending) — ส่งขออนุมัติแล้ว, ไม่มีการชน (เลี่ยงพุธวันหยุด + เวลา/ห้องของ 212) ──
            if ($o = $offerings->get('NSBS 213')) {
                $this->clearDemo($o->id);
                $lead = $this->leadFor($o);
                $this->makeSlot($o, $lecture, $roomA, $lead, $thu, '13:00', '16:00', 'บรรยาย: สุขภาพจิตและการพยาบาลจิตเวช', 'pending_approval');
                $this->makeSlot($o, $lecture, $roomA, $lead, $fri, '09:00', '12:00', 'สัมมนา: กรณีศึกษาผู้ป่วยจิตเวช', 'pending_approval');
            }

            // ── NSBS 314 (draft) — โชว์ holiday warning (พ. 06-03 วันหยุด) + เหลือพื้นที่ให้ลองจัดเอง ──
            if ($o = $offerings->get('NSBS 314')) {
                $this->clearDemo($o->id);
                $lead = $this->leadFor($o);
                // [W2] ตรงวันหยุดราชการ (holiday): พ. = วันเฉลิมพระชนมพรรษาฯ → เตือน (บันทึกได้ ไม่บล็อก)
                $this->makeSlot($o, $lecture, $roomB, $lead, $wed, '09:00', '12:00', '[ตรงวันหยุด] ปฐมนิเทศรายวิชา (ตรงวันหยุดราชการ)', 'draft');
            }
        });
    }

    /** ผู้สอนหลักของ slot = หัวหน้าวิชา (coordinator) ถ้ามี ไม่งั้นคนแรกใน pool */
    private function leadFor(CourseOffering $offering): ?User
    {
        $offering->loadMissing('instructorPool');

        return $offering->instructorPool->firstWhere('id', $offering->coordinator_id)
            ?? $offering->instructorPool->first();
    }

    private function makeSlot(
        CourseOffering $offering,
        ActivityType $activity,
        Room $room,
        ?User $lead,
        string $date,
        string $start,
        string $end,
        string $topic,
        string $status
    ) {
        $schedule = Schedule::create([
            'course_offering_id' => $offering->id,
            'activity_type_id' => $activity->id,
            'room_id' => $room->id,
            'practicum_series_id' => null,
            'start_date' => $date,
            'end_date' => $date,
            'teaching_date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'topic' => $topic,
            'capacity_required' => null,
            'sub_group_label' => null,
            'status' => $status,
            'remark' => 'client_demo',
        ]);

        if ($lead) {
            $schedule->instructors()->sync([$lead->id => ['is_lead' => true]]);
        }

        return $schedule;
    }

    private function clearDemo(int $offeringId): void
    {
        Schedule::query()
            ->where('course_offering_id', $offeringId)
            ->where('remark', 'client_demo')
            ->each(function (Schedule $schedule): void {
                $schedule->instructors()->detach();
                $schedule->studentGroups()->detach();
                $schedule->delete();
            });
    }

    private function applyApprovalStatuses($offerings): void
    {
        foreach (self::DEMO_COURSES as $code => $status) {
            if ($o = $offerings->get($code)) {
                $o->forceFill(['approval_status' => $status])->save();
            }
        }
    }

    private function warmConflicts(AcademicYear $year): void
    {
        if (config('conflicts.async_reads')) {
            $generation = (int) ScheduleConflictRun::query()
                ->where('academic_year_id', $year->id)
                ->max('generation');

            $run = ScheduleConflictRun::query()->create([
                'academic_year_id' => $year->id,
                'status' => 'pending',
                'generation' => $generation + 1,
                'source' => 'manual',
                'requested_at' => now(),
                'result_count' => 0,
                'metadata' => ['seeded_by' => self::class],
            ]);

            (new ConflictRecomputeJob((int) $year->id, (int) $run->id, (int) $run->generation, 'manual'))
                ->handle(app(ScheduleConflictIndex::class), app(ScheduleConflictInvalidationService::class));

            return;
        }

        $badgeService = app(NavigationBadgeService::class);
        CourseOffering::query()
            ->where('academic_year_id', $year->id)
            ->pluck('coordinator_id')
            ->filter()
            ->unique()
            ->each(fn ($id) => $badgeService->refreshCourseHeadConflictCount((int) $id, $year->id));
    }

    private function printSummary(): void
    {
        $this->command?->info('ClientDemoSeeder เสร็จแล้ว — ปีการศึกษา 2569 (scheduling):');
        $this->command?->info('  • NSBS 111 = published (อนุมัติแล้ว — เป็น slot ต้นทางให้ 212 ไปชนข้ามวิชา)');
        $this->command?->info('  • NSBS 212 = draft + โชว์การชนข้ามวิชา + ห้องใช้ร่วม + ข้อมูลไม่ครบ:');
        $this->command?->info('        [1] ชนห้องข้ามวิชา  [2] ชนผู้สอนข้ามวิชา  [3] ห้องใช้ร่วม=ไม่ชน  [W] ข้อมูลไม่ครบ');
        $this->command?->info('        (ไม่ seed การชนในวิชา — ระบบบล็อกตั้งแต่กรอก สร้างไม่ได้จริง)');
        $this->command?->info('  • NSBS 213 = pending (รอผู้บริหารอนุมัติ — ไม่มีการชน)');
        $this->command?->info('  • NSBS 314 = draft ว่าง (ลองจัดเอง)');
        $this->command?->info('  บัญชี demo (รหัสผ่าน password): admin_01 / head_med / head_psy / exec_01 / instructor_01 / staff_01');
    }
}
