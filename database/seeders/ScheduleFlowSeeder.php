<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ActivityType;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\InstructorProfile;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\User;
use App\Models\UserRole;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Optional seeder for preparing a realistic scheduling flow:
 *
 *   php artisan db:seed --class=ScheduleFlowSeeder
 */
class ScheduleFlowSeeder extends Seeder
{
    private const STUDENTS_PER_GROUP = 30;

    private const GROUP_COLORS = [
        '#2563eb', '#16a34a', '#ca8a04', '#dc2626', '#7c3aed',
        '#0891b2', '#db2777', '#4f46e5', '#65a30d', '#ea580c',
    ];

    private const TIME_SLOTS = ['08:00', '10:00', '13:00', '15:00'];

    private const MIN_INSTRUCTORS_PER_OFFERING = 3;

    public function run(): void
    {
        $year = AcademicYear::whereDate('end_date', '>=', Carbon::today())
            ->orderBy('start_date')
            ->first()
            ?? AcademicYear::orderByDesc('start_date')->first();

        if (! $year) {
            $this->command->warn('ScheduleFlowSeeder: ไม่พบปีการศึกษาในระบบ — รัน AcademicYearSeeder ก่อน');

            return;
        }

        AcademicYear::query()->update(['is_active' => false]);
        $year->update(['is_active' => true]);
        $this->command->info("ScheduleFlowSeeder: ตั้งปีการศึกษาปัจจุบัน = {$year->name} ภาค {$year->semester}");

        $activated = Course::whereNotNull('head_instructor_id')
            ->where('status', '!=', 'active')
            ->update(['status' => 'active']);
        $this->command->info("ScheduleFlowSeeder: activate รายวิชา {$activated} วิชา");

        $this->call(CourseOfferingSeeder::class);

        $offerings = CourseOffering::where('academic_year_id', $year->id)
            ->with('studentGroups')
            ->get();

        $offeringsSeeded = 0;
        $groupsCreated = 0;

        foreach ($offerings as $offering) {
            if ($offering->studentGroups->isNotEmpty()) {
                continue;
            }

            $total = (int) ($offering->total_student_count ?: 0);
            if ($total < 1) {
                continue;
            }

            $groupCount = max(1, (int) ceil($total / self::STUDENTS_PER_GROUP));
            $baseCount = intdiv($total, $groupCount);
            $remainder = $total % $groupCount;

            for ($index = 0; $index < $groupCount; $index++) {
                $offering->studentGroups()->create([
                    'group_code' => 'A' . ($index + 1),
                    'student_count' => $baseCount + ($index < $remainder ? 1 : 0),
                    'color_code' => self::GROUP_COLORS[$index % count(self::GROUP_COLORS)],
                ]);
            }

            $offeringsSeeded++;
            $groupsCreated += $groupCount;
        }

        $this->command->info("ScheduleFlowSeeder: สร้างกลุ่มนักศึกษา {$groupsCreated} กลุ่ม ใน {$offeringsSeeded} รายวิชา");

        $this->seedSchedules($year);

        $this->command->info('ScheduleFlowSeeder: เสร็จสิ้น — ระบบพร้อมใช้งาน มีรายวิชาในตารางแล้ว');
    }

    private function seedSchedules(AcademicYear $year): void
    {
        $termStart = Carbon::parse($year->start_date)->startOfDay();
        $termEnd = Carbon::parse($year->end_date)->endOfDay();
        $teachingWeeks = collect(CarbonPeriod::create($termStart, $termEnd))
            ->map(fn ($date) => Carbon::parse($date))
            ->filter(fn (Carbon $date) => $date->isWeekday())
            ->groupBy(fn (Carbon $date) => $date->copy()->startOfWeek(Carbon::MONDAY)->toDateString())
            ->map(fn ($dates) => collect($dates)->values())
            ->values();

        if ($teachingWeeks->isEmpty()) {
            $this->command->warn('ScheduleFlowSeeder: ไม่มีวันทำการในช่วงปีการศึกษา — ข้ามการสร้างรายการสอน');

            return;
        }

        $rooms = Room::where('status', 'active')->orderBy('room_code')->get();

        if ($rooms->isEmpty()) {
            $this->command->warn('ScheduleFlowSeeder: ไม่มีห้องที่ใช้งานได้ — ข้ามการสร้างรายการสอน');

            return;
        }

        $lectureType = ActivityType::where('name', 'บรรยาย')->first()
            ?? ActivityType::where('category', 'lecture')->first();
        $practicumType = ActivityType::where('name', 'ฝึกปฏิบัติในแหล่งจริง')->first()
            ?? ActivityType::where('category', 'practicum')->first();
        $labType = ActivityType::where('name', 'Lab / ห้องปฏิบัติการ')->first() ?? $practicumType;
        $seminarType = ActivityType::where('name', 'สัมมนา')->first() ?? $lectureType;

        if (! $lectureType || ! $practicumType) {
            $this->command->warn('ScheduleFlowSeeder: ไม่พบประเภทกิจกรรม — รัน ActivityTypeSeeder ก่อน');

            return;
        }

        $offerings = CourseOffering::where('academic_year_id', $year->id)
            ->with(['course', 'studentGroups', 'instructorPool.instructorProfile'])
            ->orderBy('id')
            ->get();

        $offeringIds = $offerings->pluck('id')->all();
        $this->resetSeededSchedules($offeringIds);

        $slotsPerDay = count(self::TIME_SLOTS);
        $globalSlot = 0;
        $schedulesCreated = 0;
        $offeringsWithSchedules = 0;
        $skippedActivities = 0;
        [$occupiedRooms, $occupiedInstructors] = $this->existingScheduleOccupancy($termStart, $termEnd);

        foreach ($offerings as $offering) {
            $this->ensureOfferingInstructors($offering);
            $offering->load(['course', 'studentGroups', 'instructorPool.instructorProfile']);

            $groupIds = $offering->studentGroups->pluck('id')->all();
            $instructorIds = $this->eligibleInstructorIds($offering);
            if (empty($groupIds) || empty($instructorIds)) {
                continue;
            }

            $leadId = in_array((int) $offering->coordinator_id, $instructorIds, true)
                ? (int) $offering->coordinator_id
                : $instructorIds[0];
            $hasLecture = (int) $offering->planned_lecture_hours > 0;
            $hasPracticum = (int) $offering->planned_practicum_hours > 0
                || (int) $offering->planned_lab_hours > 0;

            $activities = [
                [
                    'type' => $hasLecture ? $lectureType : $practicumType,
                    'topic' => $hasLecture ? 'บรรยาย — แนะนำรายวิชาและปฐมนิเทศ' : 'ฝึกปฏิบัติ — ปฐมนิเทศแหล่งฝึก',
                ],
                [
                    'type' => $hasPracticum
                        ? ($offering->requires_practicum_rotation ? $practicumType : $labType)
                        : $seminarType,
                    'topic' => $hasPracticum ? 'ฝึกปฏิบัติ — กิจกรรมตามแผนการเรียน' : 'สัมมนากลุ่มย่อย',
                ],
            ];

            $createdForOffering = false;

            foreach ($teachingWeeks as $weekIndex => $weekDates) {
                foreach ($activities as $activity) {
                    $slot = $globalSlot++;
                    $placement = $this->findAvailablePlacement(
                        $weekDates,
                        $rooms,
                        $instructorIds,
                        $slot,
                        $occupiedRooms,
                        $occupiedInstructors
                    );

                    if (! $placement) {
                        $skippedActivities++;
                        continue;
                    }

                    [$date, $startTime, $endTime, $room, $selectedInstructorIds] = $placement;
                    $topic = $activity['topic'] . ' (สัปดาห์ที่ ' . ((int) $weekIndex + 1) . ')';

                    DB::transaction(function () use (
                        $offering,
                        $activity,
                        $room,
                        $date,
                        $startTime,
                        $endTime,
                        $topic,
                        $selectedInstructorIds,
                        $leadId,
                        $groupIds,
                        &$schedulesCreated
                    ): void {
                        $schedule = Schedule::create([
                            'course_offering_id' => $offering->id,
                            'activity_type_id' => $activity['type']->id,
                            'room_id' => $room->id,
                            'practicum_series_id' => null,
                            'start_date' => $date,
                            'end_date' => $date,
                            'teaching_date' => $date,
                            'start_time' => $startTime . ':00',
                            'end_time' => $endTime . ':00',
                            'topic' => $topic,
                            'capacity_required' => (int) $offering->total_student_count ?: null,
                            'status' => 'draft',
                        ]);

                        $payload = [];
                        foreach ($selectedInstructorIds as $id) {
                            $payload[$id] = ['is_lead' => (int) $id === (int) $leadId];
                        }

                        $schedule->instructors()->sync($payload);
                        $schedule->studentGroups()->sync($groupIds);

                        $schedulesCreated++;
                    });

                    $this->reservePlacement($occupiedRooms, $occupiedInstructors, $date, $startTime, $endTime, $room->id, $selectedInstructorIds);

                    $createdForOffering = true;
                }
            }

            if ($createdForOffering) {
                $offeringsWithSchedules++;
            }
        }

        $this->command->info("ScheduleFlowSeeder: สร้างรายการสอน {$schedulesCreated} รายการ ใน {$offeringsWithSchedules} รายวิชา (ทั้งเทอม {$termStart->format('Y-m-d')} - {$termEnd->format('Y-m-d')})");

        $demoConflictsCreated = $this->seedDemoConflicts($offerings, $lectureType);

        if ($demoConflictsCreated > 0) {
            $this->command->info("ScheduleFlowSeeder: สร้างรายการชนตัวอย่าง {$demoConflictsCreated} รายการ สำหรับทดสอบหน้าการแจ้งเตือนการชน");
        }

        $demoStackCardsCreated = $this->seedDemoStackCards($offerings, $rooms, $lectureType, $practicumType, $termStart, $termEnd);

        if ($demoStackCardsCreated > 0) {
            $this->command->info("ScheduleFlowSeeder: สร้างรายการซ้อนกัน {$demoStackCardsCreated} รายการ สำหรับทดสอบ stack card ในหน้าตารางสอน");
        }

        if ($skippedActivities > 0) {
            $this->command->warn("ScheduleFlowSeeder: ข้าม {$skippedActivities} กิจกรรม เพราะหาห้องหรือผู้สอนที่ว่างไม่พอ");
        }

        $this->reportSeededScheduleIntegrity($offeringIds, $termStart, $termEnd);
    }

    private function seedDemoConflicts($offerings, ActivityType $activityType): int
    {
        $sourceSchedule = Schedule::query()
            ->with(['courseOffering.course', 'instructors', 'studentGroups'])
            ->whereIn('course_offering_id', $offerings->pluck('id')->all())
            ->whereNotNull('room_id')
            ->whereHas('instructors')
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->first();

        if (! $sourceSchedule) {
            return 0;
        }

        $targetOffering = $offerings
            ->filter(fn (CourseOffering $offering) => (int) $offering->id !== (int) $sourceSchedule->course_offering_id
                && $offering->studentGroups->isNotEmpty())
            ->sortBy('id')
            ->first();

        if (! $targetOffering) {
            return 0;
        }

        $targetOffering->load(['course', 'studentGroups', 'instructorPool.instructorProfile']);
        $targetInstructorIds = $this->eligibleInstructorIds($targetOffering);

        if (empty($targetInstructorIds)) {
            $this->ensureOfferingInstructors($targetOffering);
            $targetOffering->load(['course', 'studentGroups', 'instructorPool.instructorProfile']);
            $targetInstructorIds = $this->eligibleInstructorIds($targetOffering);
        }

        $targetGroupIds = $targetOffering->studentGroups->pluck('id')->all();

        if (empty($targetInstructorIds) || empty($targetGroupIds)) {
            return 0;
        }

        $selectedInstructorIds = $this->instructorWindow($targetInstructorIds, (int) $targetOffering->id);
        $leadId = $selectedInstructorIds[0] ?? null;

        DB::transaction(function () use (
            $targetOffering,
            $sourceSchedule,
            $activityType,
            $selectedInstructorIds,
            $leadId,
            $targetGroupIds
        ): void {
            $schedule = Schedule::create([
                'course_offering_id' => $targetOffering->id,
                'activity_type_id' => $activityType->id,
                'room_id' => $sourceSchedule->room_id,
                'practicum_series_id' => null,
                'start_date' => $sourceSchedule->start_date,
                'end_date' => $sourceSchedule->end_date,
                'teaching_date' => $sourceSchedule->teaching_date ?? $sourceSchedule->start_date,
                'start_time' => $sourceSchedule->start_time,
                'end_time' => $sourceSchedule->end_time,
                'topic' => 'รายการสอนทดสอบการชน - ห้องซ้อนกับรายวิชาอื่น',
                'capacity_required' => (int) $targetOffering->total_student_count ?: null,
                'status' => 'draft',
            ]);

            $payload = [];
            foreach ($selectedInstructorIds as $id) {
                $payload[$id] = ['is_lead' => (int) $id === (int) $leadId];
            }

            $schedule->instructors()->sync($payload);
            $schedule->studentGroups()->sync($targetGroupIds);
        });

        return 1;
    }

    private function seedDemoStackCards($offerings, $rooms, ActivityType $lectureType, ActivityType $practicumType, Carbon $termStart, Carbon $termEnd): int
    {
        $targetOffering = $offerings
            ->filter(fn (CourseOffering $offering) => $offering->studentGroups->isNotEmpty())
            ->sortBy('id')
            ->first();

        if (! $targetOffering || $rooms->isEmpty()) {
            return 0;
        }

        $targetOffering->load(['course', 'studentGroups', 'instructorPool.instructorProfile']);
        $this->ensureOfferingInstructors($targetOffering);
        $targetOffering->load(['course', 'studentGroups', 'instructorPool.instructorProfile']);

        $instructorIds = $this->eligibleInstructorIds($targetOffering);
        $groupIds = $targetOffering->studentGroups->pluck('id')->values()->all();

        if (empty($instructorIds) || empty($groupIds)) {
            return 0;
        }

        $demoDate = $this->firstWeekdayBetween($termStart, $termEnd);
        if (! $demoDate) {
            return 0;
        }

        $templates = [
            ['08:00', '10:00', $lectureType, 'Stack demo - บรรยายภาพรวม'],
            ['08:15', '09:15', $practicumType, 'Stack demo - ฝึกทักษะสั้น'],
            ['08:30', '10:15', $lectureType, 'Stack demo - อภิปรายกรณีศึกษา'],
            ['09:00', '11:00', $practicumType, 'Stack demo - ฝึกปฏิบัติกลุ่ม'],
            ['09:15', '10:15', $lectureType, 'Stack demo - ทบทวนหัวข้อหลัก'],
            ['10:00', '12:00', $practicumType, 'Stack demo - สรุปสถานการณ์'],
        ];

        $created = 0;
        DB::transaction(function () use ($targetOffering, $rooms, $demoDate, $templates, $instructorIds, $groupIds, &$created): void {
            foreach ($templates as $index => [$startTime, $endTime, $activityType, $topic]) {
                $instructorId = (int) $instructorIds[$index % count($instructorIds)];
                $room = $rooms[$index % $rooms->count()];
                $groupId = (int) $groupIds[$index % count($groupIds)];

                $schedule = Schedule::create([
                    'course_offering_id' => $targetOffering->id,
                    'activity_type_id' => $activityType->id,
                    'room_id' => $room->id,
                    'practicum_series_id' => null,
                    'start_date' => $demoDate->toDateString(),
                    'end_date' => $demoDate->toDateString(),
                    'teaching_date' => $demoDate->toDateString(),
                    'start_time' => $startTime . ':00',
                    'end_time' => $endTime . ':00',
                    'topic' => $topic,
                    'capacity_required' => (int) $targetOffering->total_student_count ?: null,
                    'status' => 'draft',
                ]);

                $schedule->instructors()->sync([$instructorId => ['is_lead' => true]]);
                $schedule->studentGroups()->sync([$groupId]);

                $created++;
            }
        });

        return $created;
    }

    private function firstWeekdayBetween(Carbon $termStart, Carbon $termEnd): ?Carbon
    {
        foreach (CarbonPeriod::create($termStart->copy()->startOfDay(), $termEnd->copy()->endOfDay()) as $date) {
            $candidate = Carbon::parse($date);
            if ($candidate->isWeekday()) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array<int, int>
     */
    private function eligibleInstructorIds(CourseOffering $offering): array
    {
        $departmentId = $offering->course?->department_id;

        return $offering->instructorPool
            ->filter(fn ($instructor) => ! $departmentId
                || (int) $instructor->instructorProfile?->department_id === (int) $departmentId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $offeringIds
     */
    private function resetSeededSchedules(array $offeringIds): void
    {
        if (empty($offeringIds)) {
            return;
        }

        Schedule::whereIn('course_offering_id', $offeringIds)->delete();
    }

    private function ensureOfferingInstructors(CourseOffering $offering): void
    {
        $departmentId = $offering->course?->department_id;

        if (! $departmentId) {
            return;
        }

        $offering->loadMissing(['course', 'instructorPool.instructorProfile']);
        $this->detachStaleGeneratedInstructors($offering);
        $offering->load('instructorPool.instructorProfile');

        $eligibleIds = $this->eligibleInstructorIds($offering);

        while (count($eligibleIds) < self::MIN_INSTRUCTORS_PER_OFFERING) {
            $instructor = $this->createGeneratedInstructor($offering, $departmentId, count($eligibleIds) + 1);

            $offering->instructorPool()->syncWithoutDetaching([
                $instructor->id => ['role_in_course' => 'instructor'],
            ]);

            $offering->course?->instructors()->syncWithoutDetaching([
                $instructor->id => ['course_role_id' => null],
            ]);

            $offering->load('instructorPool.instructorProfile');
            $eligibleIds = $this->eligibleInstructorIds($offering);
        }
    }

    private function detachStaleGeneratedInstructors(CourseOffering $offering): void
    {
        $currentPrefix = $this->generatedInstructorPrefix($offering);

        $staleIds = $offering->instructorPool
            ->filter(fn (User $user) => Str::startsWith($user->username, 'schedule_dept_')
                || (Str::startsWith($user->username, 'schedule_offering_')
                    && ! Str::startsWith($user->username, $currentPrefix)))
            ->pluck('id')
            ->all();

        if (empty($staleIds)) {
            return;
        }

        $offering->instructorPool()->detach($staleIds);
        $offering->course?->instructors()->detach($staleIds);
    }

    private function createGeneratedInstructor(CourseOffering $offering, int $departmentId, int $sequence): User
    {
        $username = $this->generatedInstructorPrefix($offering) . sprintf('%02d', $sequence);
        $identity = $this->generatedInstructorIdentity($offering, $sequence);

        $user = User::firstOrCreate(
            ['username' => $username],
            [
                'prefix' => $identity['prefix'],
                'employee_id' => sprintf('8%04d%02d', $offering->id, $sequence),
                'name' => $identity['name'],
                'email' => $username . '@mahidol.edu',
                'password' => 'password',
                'is_active' => true,
            ]
        );

        $user->forceFill([
            'prefix' => $identity['prefix'],
            'employee_id' => sprintf('8%04d%02d', $offering->id, $sequence),
            'name' => $identity['name'],
            'email' => $username . '@mahidol.edu',
            'is_active' => true,
        ])->save();

        UserRole::firstOrCreate(
            ['user_id' => $user->id, 'role' => 'instructor'],
            ['is_primary' => true]
        );

        InstructorProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'title' => $sequence % 3 === 0 ? 'รองศาสตราจารย์' : 'อาจารย์',
                'department_id' => $departmentId,
                'employment_type' => $sequence % 2 === 0 ? 'ข้าราชการ' : 'พนักงานมหาวิทยาลัย',
                'academic_degree' => $sequence % 2 === 0 ? 'ปริญญาเอก' : 'ปริญญาโท',
                'hired_at' => '2018-06-01',
                'teaching_pct' => 50,
                'research_pct' => 25,
                'service_pct' => 15,
                'culture_pct' => 5,
                'other_pct' => 5,
            ]
        );

        return $user->fresh(['instructorProfile']);
    }

    private function generatedInstructorPrefix(CourseOffering $offering): string
    {
        return sprintf('schedule_offering_%d_', $offering->id);
    }

    /**
     * @return array{prefix: string, name: string}
     */
    private function generatedInstructorIdentity(CourseOffering $offering, int $sequence): array
    {
        $givenNames = [
            ['prefix' => 'นางสาว', 'name' => 'นลินี'],
            ['prefix' => 'นางสาว', 'name' => 'กานต์ธิดา'],
            ['prefix' => 'นางสาว', 'name' => 'ปรียาภรณ์'],
            ['prefix' => 'นาย', 'name' => 'ธนวัฒน์'],
            ['prefix' => 'นางสาว', 'name' => 'อรพรรณ'],
            ['prefix' => 'นาย', 'name' => 'พีรพล'],
            ['prefix' => 'นางสาว', 'name' => 'พิมพ์ชนก'],
            ['prefix' => 'นาย', 'name' => 'ศรัณย์'],
            ['prefix' => 'นางสาว', 'name' => 'วราภรณ์'],
            ['prefix' => 'นาย', 'name' => 'ณัฐภัทร'],
            ['prefix' => 'นางสาว', 'name' => 'สุชาดา'],
            ['prefix' => 'นาย', 'name' => 'กิตติพงศ์'],
            ['prefix' => 'นางสาว', 'name' => 'มนัสนันท์'],
            ['prefix' => 'นาย', 'name' => 'ปกรณ์'],
            ['prefix' => 'นางสาว', 'name' => 'ชุติมา'],
            ['prefix' => 'นาย', 'name' => 'ธีรภัทร'],
            ['prefix' => 'นางสาว', 'name' => 'ภาวิณี'],
            ['prefix' => 'นาย', 'name' => 'ภาสกร'],
            ['prefix' => 'นางสาว', 'name' => 'อัจฉรา'],
            ['prefix' => 'นาย', 'name' => 'วรุตม์'],
            ['prefix' => 'นางสาว', 'name' => 'เมธาวี'],
            ['prefix' => 'นาย', 'name' => 'ชยพล'],
            ['prefix' => 'นางสาว', 'name' => 'รัตนา'],
        ];

        $familyNames = [
            'วิชาการ',
            'สุขใจ',
            'วัฒนกุล',
            'พิพัฒน์สุข',
            'ตั้งมั่น',
            'เกื้อกูล',
            'ศรีสวัสดิ์',
            'จิตต์มั่น',
            'อรุณรักษ์',
            'ธำรงเวช',
            'อินทรสุข',
            'วรสิทธิ์',
            'สุนทรกิจ',
            'ศิริวงศ์',
            'ปัญญาพูล',
            'กุลประเสริฐ',
            'รัตนานนท์',
            'บูรณศิลป์',
            'ธรรมวัฒน์',
            'เพชรประภา',
            'เลิศวิทยา',
            'ภักดีสุข',
            'จันทร์ฉาย',
            'สร้อยสน',
            'ทวีวัฒน์',
            'คงสมบัติ',
            'แสงอรุณ',
            'เวชภิบาล',
            'มิ่งขวัญ',
        ];

        $index = max(0, ((int) $offering->id - 1) * self::MIN_INSTRUCTORS_PER_OFFERING + ($sequence - 1));
        $given = $givenNames[$index % count($givenNames)];
        $familyName = $familyNames[$index % count($familyNames)];

        return [
            'prefix' => $given['prefix'],
            'name' => "{$given['name']} {$familyName}",
        ];
    }

    /**
     * @return array{0: array<string, list<array{start: int, end: int}>>, 1: array<int, array<string, list<array{start: int, end: int}>>>}
     */
    private function existingScheduleOccupancy(Carbon $termStart, Carbon $termEnd): array
    {
        $occupiedRooms = [];
        $occupiedInstructors = [];

        Schedule::query()
            ->with('instructors')
            ->whereDate('start_date', '<=', $termEnd->toDateString())
            ->whereDate('end_date', '>=', $termStart->toDateString())
            ->get()
            ->each(function (Schedule $schedule) use (&$occupiedRooms, &$occupiedInstructors): void {
                $startDate = Carbon::parse($schedule->start_date);
                $endDate = Carbon::parse($schedule->end_date);
                $start = $this->minutes((string) $schedule->start_time);
                $end = $this->minutes((string) $schedule->end_time);

                foreach (CarbonPeriod::create($startDate, $endDate) as $date) {
                    $dateKey = Carbon::parse($date)->toDateString();

                    if ($schedule->room_id) {
                        $occupiedRooms[$dateKey . '|' . $schedule->room_id][] = ['start' => $start, 'end' => $end];
                    }

                    foreach ($schedule->instructors as $instructor) {
                        $occupiedInstructors[(int) $instructor->id][$dateKey][] = ['start' => $start, 'end' => $end];
                    }
                }
            });

        return [$occupiedRooms, $occupiedInstructors];
    }

    private function findAvailablePlacement(
        $weekDates,
        $rooms,
        array $instructorIds,
        int $seedSlot,
        array $occupiedRooms,
        array $occupiedInstructors
    ): ?array {
        $weekDateCount = max(1, $weekDates->count());
        $roomCount = max(1, $rooms->count());
        $timeSlotCount = count(self::TIME_SLOTS);
        $attempts = $weekDateCount * $timeSlotCount * $roomCount * max(1, count($instructorIds));

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            $cursor = $seedSlot + $attempt;
            $date = $weekDates[intdiv($cursor, $timeSlotCount) % $weekDateCount]->toDateString();
            $startTime = self::TIME_SLOTS[$cursor % $timeSlotCount];
            $endTime = Carbon::parse($startTime)->addHours(2)->format('H:i');
            $room = $rooms[intdiv($cursor, $timeSlotCount * $weekDateCount) % $roomCount];
            $selectedInstructorIds = $this->instructorWindow($instructorIds, $cursor);

            if ($this->placementAvailable($occupiedRooms, $occupiedInstructors, $date, $startTime, $endTime, $room->id, $selectedInstructorIds)) {
                return [$date, $startTime, $endTime, $room, $selectedInstructorIds];
            }
        }

        return null;
    }

    /**
     * @param  array<int, int>  $instructorIds
     * @return array<int, int>
     */
    private function instructorWindow(array $instructorIds, int $cursor): array
    {
        $count = count($instructorIds);
        $take = min(2, $count);
        $start = $count > 0 ? $cursor % $count : 0;
        $selected = [];

        for ($i = 0; $i < $take; $i++) {
            $selected[] = (int) $instructorIds[($start + $i) % $count];
        }

        return array_values(array_unique($selected));
    }

    private function placementAvailable(
        array $occupiedRooms,
        array $occupiedInstructors,
        string $date,
        string $startTime,
        string $endTime,
        int $roomId,
        array $instructorIds
    ): bool {
        $start = $this->minutes($startTime);
        $end = $this->minutes($endTime);

        if ($this->hasOverlap($occupiedRooms[$date . '|' . $roomId] ?? [], $start, $end)) {
            return false;
        }

        foreach ($instructorIds as $instructorId) {
            if ($this->hasOverlap($occupiedInstructors[(int) $instructorId][$date] ?? [], $start, $end)) {
                return false;
            }
        }

        return true;
    }

    private function reservePlacement(
        array &$occupiedRooms,
        array &$occupiedInstructors,
        string $date,
        string $startTime,
        string $endTime,
        int $roomId,
        array $instructorIds
    ): void {
        $start = $this->minutes($startTime);
        $end = $this->minutes($endTime);
        $occupiedRooms[$date . '|' . $roomId][] = ['start' => $start, 'end' => $end];

        foreach ($instructorIds as $instructorId) {
            $occupiedInstructors[(int) $instructorId][$date][] = ['start' => $start, 'end' => $end];
        }
    }

    private function hasOverlap(array $ranges, int $start, int $end): bool
    {
        foreach ($ranges as $range) {
            if ($start < (int) $range['end'] && $end > (int) $range['start']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, int>  $offeringIds
     */
    private function reportSeededScheduleIntegrity(array $offeringIds, Carbon $termStart, Carbon $termEnd): void
    {
        if (empty($offeringIds)) {
            return;
        }

        $schedules = Schedule::query()
            ->with(['instructors', 'studentGroups'])
            ->whereIn('course_offering_id', $offeringIds)
            ->whereDate('start_date', '<=', $termEnd->toDateString())
            ->whereDate('end_date', '>=', $termStart->toDateString())
            ->get();

        $withoutInstructors = $schedules->filter(fn (Schedule $schedule) => $schedule->instructors->isEmpty())->count();
        $withoutGroups = $schedules->filter(fn (Schedule $schedule) => $schedule->studentGroups->isEmpty())->count();
        $roomOverlaps = 0;
        $instructorOverlaps = 0;
        $roomRanges = [];
        $instructorRanges = [];

        foreach ($schedules as $schedule) {
            $startDate = Carbon::parse($schedule->start_date);
            $endDate = Carbon::parse($schedule->end_date);
            $start = $this->minutes((string) $schedule->start_time);
            $end = $this->minutes((string) $schedule->end_time);

            foreach (CarbonPeriod::create($startDate, $endDate) as $date) {
                $dateKey = Carbon::parse($date)->toDateString();

                if ($schedule->room_id) {
                    $roomKey = $dateKey . '|' . $schedule->room_id;
                    if ($this->hasOverlap($roomRanges[$roomKey] ?? [], $start, $end)) {
                        $roomOverlaps++;
                    }
                    $roomRanges[$roomKey][] = ['start' => $start, 'end' => $end];
                }

                foreach ($schedule->instructors as $instructor) {
                    $instructorKey = $dateKey . '|' . $instructor->id;
                    if ($this->hasOverlap($instructorRanges[$instructorKey] ?? [], $start, $end)) {
                        $instructorOverlaps++;
                    }
                    $instructorRanges[$instructorKey][] = ['start' => $start, 'end' => $end];
                }
            }
        }

        if ($withoutInstructors > 0 || $withoutGroups > 0) {
            $this->command->warn(
                "ScheduleFlowSeeder: ตรวจพบข้อมูลที่ควรทบทวน — ไม่มีผู้สอน {$withoutInstructors} รายการ, "
                . "ไม่มีกลุ่ม {$withoutGroups} รายการ, ห้องชน {$roomOverlaps} จุด, ผู้สอนชน {$instructorOverlaps} จุด"
            );

            return;
        }

        if ($roomOverlaps > 0 || $instructorOverlaps > 0) {
            $this->command->info(
                "ScheduleFlowSeeder: ตรวจสอบแล้ว - รายการสอนมีผู้สอน/กลุ่มครบ และมีรายการชนตัวอย่าง ห้องชน {$roomOverlaps} จุด, ผู้สอนชน {$instructorOverlaps} จุด"
            );

            return;
        }

        $this->command->info('ScheduleFlowSeeder: ตรวจสอบแล้ว — รายการสอนมีผู้สอน/กลุ่มครบ และไม่มีห้องหรือผู้สอนชนกัน');
    }

    private function minutes(string $time): int
    {
        return ((int) substr($time, 0, 2) * 60) + (int) substr($time, 3, 2);
    }

}
