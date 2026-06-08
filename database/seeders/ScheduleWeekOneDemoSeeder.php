<?php

namespace Database\Seeders;

use App\Models\ActivityType;
use App\Models\CourseOffering;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\StudentGroup;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder สาธิตฟีเจอร์ "คัดลอกสัปดาห์ (copy week)" + การตรวจชน
 *
 * จัดตารางสัปดาห์แรกแบบ clean ให้ 1 รายวิชา (จ./พ./ศ.) แล้ววาง "ตัวชน" ข้ามวิชา
 * ไว้ในสัปดาห์ที่ 2 เพื่อให้ทดสอบได้ว่า:
 *   - copy สัปดาห์ 1 → สัปดาห์ 2 → 2 รายการคัดลอกสำเร็จ, 1 รายการถูกข้าม (ห้องชนข้ามวิชา)
 *
 * รัน: php artisan db:seed --class=ScheduleWeekOneDemoSeeder
 * (ต้องมี course offerings + student groups อยู่แล้ว — รัน DatabaseSeeder ก่อน)
 */
class ScheduleWeekOneDemoSeeder extends Seeder
{
    public function run(): void
    {
        $primary = $this->pickOffering();

        if (! $primary) {
            $this->command?->warn('ScheduleWeekOneDemoSeeder: ไม่พบ course offering ที่มีกลุ่ม + ผู้สอน + ปีการศึกษา (scheduling). ข้าม.');

            return;
        }

        $year = $primary->academicYear;
        $room = Room::query()
            ->where('status', 'active')
            ->whereHas('locationType', fn ($q) => $q->where('is_shared', false))
            ->orderBy('id')
            ->first()
            ?? Room::where('status', 'active')->orderBy('id')->first();
        $activity = ActivityType::query()->orderBy('id')->first();

        if (! $room || ! $activity) {
            $this->command?->warn('ScheduleWeekOneDemoSeeder: ต้องมีห้องและประเภทกิจกรรมอย่างน้อยอย่างละ 1 รายการ. ข้าม.');

            return;
        }

        // สัปดาห์ที่ 1 = วันจันทร์แรกที่ >= วันเริ่มปีการศึกษา (กันไม่ให้ slot หลุดออกนอกช่วง)
        $weekOneMonday = CarbonImmutable::parse($year->start_date)->startOfWeek(CarbonImmutable::MONDAY);
        if ($weekOneMonday->lt(CarbonImmutable::parse($year->start_date))) {
            $weekOneMonday = $weekOneMonday->addWeek();
        }
        $weekTwoMonday = $weekOneMonday->addWeek();

        $instructor = $primary->instructorPool->first();
        $group = $this->ensureGroup($primary);

        // ล้างของเดิมในสัปดาห์ 1-2 ของ offering หลัก เพื่อให้เริ่มจากสถานะ clean
        $this->clearWeekRange($primary->id, $weekOneMonday, $weekTwoMonday->addDays(6));

        DB::transaction(function () use ($primary, $weekOneMonday, $room, $activity, $instructor, $group) {
            // จ. 09:00–12:00, พ. 13:00–16:00, ศ. 09:00–12:00 — clean, ไม่ชนกันเอง
            $slots = [
                ['offset' => 0, 'start' => '09:00', 'end' => '12:00', 'topic' => 'บรรยายหลัก (สัปดาห์ 1)'],
                ['offset' => 2, 'start' => '13:00', 'end' => '16:00', 'topic' => 'ปฏิบัติการกลุ่ม (สัปดาห์ 1)'],
                ['offset' => 4, 'start' => '09:00', 'end' => '12:00', 'topic' => 'สัมมนา (สัปดาห์ 1)'],
            ];

            foreach ($slots as $slot) {
                $date = $weekOneMonday->addDays($slot['offset'])->toDateString();
                $this->makeSchedule($primary, $activity, $room, $instructor, $group, [
                    'start_date' => $date,
                    'end_date' => $date,
                    'start_time' => $slot['start'],
                    'end_time' => $slot['end'],
                    'topic' => $slot['topic'],
                ]);
            }
        });

        // วาง "ตัวชน" ข้ามวิชา: อีกวิชาจองห้องเดียวกัน วัน/เวลาเดียวกับ slot จันทร์ แต่อยู่สัปดาห์ที่ 2
        $blocker = $this->pickOffering(exceptId: $primary->id);
        $blockerNote = 'ไม่ได้วางตัวชน (มีรายวิชาเดียว)';

        if ($blocker) {
            $blockerGroup = $this->ensureGroup($blocker);
            $blockerInstructor = $blocker->instructorPool->first();
            $blockerDate = $weekTwoMonday->toDateString();

            $this->clearWeekRange($blocker->id, $weekTwoMonday, $weekTwoMonday->addDays(6));
            $this->makeSchedule($blocker, $activity, $room, $blockerInstructor, $blockerGroup, [
                'start_date' => $blockerDate,
                'end_date' => $blockerDate,
                'start_time' => '09:00',
                'end_time' => '12:00',
                'topic' => 'กิจกรรมวิชาอื่น (ตัวชนห้องสัปดาห์ 2)',
            ]);
            $blockerNote = "วางตัวชนห้อง {$room->room_code} จ. {$blockerDate} 09:00–12:00 ในวิชา {$blocker->course?->course_code}";
        }

        $this->command?->info('ScheduleWeekOneDemoSeeder เสร็จแล้ว:');
        $this->command?->info("  • วิชาหลัก: {$primary->course?->course_code} — สัปดาห์ 1 มี 3 รายการ (จ./พ./ศ.)");
        $this->command?->info("  • {$blockerNote}");
        $this->command?->info("  • ทดสอบ: เปิดตารางวิชาหลัก → สัปดาห์ของ {$weekOneMonday->toDateString()} → กดคัดลอกไปสัปดาห์ {$weekTwoMonday->toDateString()}");
        $this->command?->info('    คาดผล: คัดลอกสำเร็จ 2 รายการ · ข้าม 1 รายการ (จันทร์ — ห้องชนข้ามวิชา)');
    }

    private function pickOffering(?int $exceptId = null): ?CourseOffering
    {
        return CourseOffering::query()
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->whereHas('academicYear', fn ($q) => $q->where('phase', 'scheduling'))
            ->whereHas('instructorPool')
            ->with(['academicYear', 'course', 'studentGroups', 'instructorPool'])
            ->join('courses', 'courses.id', '=', 'course_offerings.course_id')
            ->orderBy('courses.course_code')
            ->select('course_offerings.*')
            ->first();
    }

    /**
     * กลุ่มแรกของ offering — สร้าง A1 ให้ถ้ายังไม่มี (กลุ่มปกติหัวหน้าวิชาสร้างใน UI)
     */
    private function ensureGroup(CourseOffering $offering): ?StudentGroup
    {
        $existing = $offering->studentGroups()->orderBy('group_code')->first();
        if ($existing) {
            return $existing;
        }

        $count = 30;

        return StudentGroup::create([
            'course_offering_id' => $offering->id,
            'group_code' => 'A1',
            'student_count' => max(1, min($count, 30)),
            'color_code' => '#2563eb',
        ]);
    }

    private function clearWeekRange(int $offeringId, CarbonImmutable $from, CarbonImmutable $to): void
    {
        Schedule::query()
            ->where('course_offering_id', $offeringId)
            ->whereBetween('start_date', [$from->toDateString(), $to->toDateString()])
            ->each(function (Schedule $schedule) {
                $schedule->instructors()->detach();
                $schedule->studentGroups()->detach();
                $schedule->delete();
            });
    }

    private function makeSchedule(
        CourseOffering $offering,
        ActivityType $activity,
        Room $room,
        $instructor,
        $group,
        array $overrides
    ): Schedule {
        $schedule = Schedule::create(array_merge([
            'course_offering_id' => $offering->id,
            'activity_type_id' => $activity->id,
            'room_id' => $room->id,
            'practicum_series_id' => null,
            'capacity_required' => $group?->student_count,
            'sub_group_label' => null,
            'status' => 'draft',
            'remark' => null,
        ], $overrides));

        if ($instructor) {
            $schedule->instructors()->sync([$instructor->id => ['is_lead' => true]]);
        }
        if ($group) {
            $schedule->studentGroups()->sync([$group->id]);
        }

        return $schedule;
    }
}
