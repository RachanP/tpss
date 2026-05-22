<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ActivityType;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Room;
use App\Models\Schedule;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
        $monthStart = Carbon::parse($year->start_date)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $teachingWeeks = collect(CarbonPeriod::create($monthStart, $monthEnd))
            ->map(fn ($date) => Carbon::parse($date))
            ->filter(fn (Carbon $date) => $date->isWeekday())
            ->groupBy(fn (Carbon $date) => $date->copy()->startOfWeek(Carbon::MONDAY)->toDateString())
            ->map(fn ($dates) => collect($dates)->values())
            ->values();

        if ($teachingWeeks->isEmpty()) {
            $this->command->warn('ScheduleFlowSeeder: ไม่มีวันทำการในเดือนเริ่มต้นของปีการศึกษา — ข้ามการสร้างรายการสอน');

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
            ->with(['course', 'studentGroups', 'instructorPool'])
            ->orderBy('id')
            ->get();

        $slotsPerDay = count(self::TIME_SLOTS);
        $globalSlot = 0;
        $schedulesCreated = 0;
        $offeringsWithSchedules = 0;

        foreach ($offerings as $offering) {
            $groupIds = $offering->studentGroups->pluck('id')->all();
            $instructorIds = $offering->instructorPool->pluck('id')->all();
            if (empty($groupIds) || empty($instructorIds)) {
                continue;
            }

            $leadId = $offering->coordinator_id ?: $instructorIds[0];
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
                    $dayIndex = intdiv($slot, $slotsPerDay) % max(1, $weekDates->count());
                    $timeIndex = $slot % $slotsPerDay;
                    $date = $weekDates[$dayIndex]->toDateString();
                    $startTime = self::TIME_SLOTS[$timeIndex];
                    $endTime = Carbon::parse($startTime)->addHours(2)->format('H:i');
                    $room = $rooms[$slot % $rooms->count()];
                    $topic = $activity['topic'] . ' (สัปดาห์ที่ ' . ((int) $weekIndex + 1) . ')';

                    $alreadyExists = $offering->schedules()
                        ->whereDate('start_date', $date)
                        ->where('start_time', $startTime . ':00')
                        ->exists();

                    if ($alreadyExists) {
                        continue;
                    }

                    DB::transaction(function () use (
                        $offering,
                        $activity,
                        $room,
                        $date,
                        $startTime,
                        $endTime,
                        $topic,
                        $instructorIds,
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
                        foreach (array_slice($instructorIds, 0, 2) as $id) {
                            $payload[$id] = ['is_lead' => (int) $id === (int) $leadId];
                        }

                        $schedule->instructors()->sync($payload);
                        $schedule->studentGroups()->sync($groupIds);

                        $schedulesCreated++;
                    });

                    $createdForOffering = true;
                }
            }

            if ($createdForOffering) {
                $offeringsWithSchedules++;
            }
        }

        $this->command->info("ScheduleFlowSeeder: สร้างรายการสอน {$schedulesCreated} รายการ ใน {$offeringsWithSchedules} รายวิชา (ทั้งเดือน {$monthStart->format('Y-m')})");
    }
}
