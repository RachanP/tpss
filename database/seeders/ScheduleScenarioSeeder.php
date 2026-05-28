<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ActivityType;
use App\Models\CourseOffering;
use App\Models\Room;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * สร้างสถานการณ์ทดสอบให้ครบ — รันบน DB จริง (tpss) เพื่อทดสอบบน browser
 *
 *   php artisan db:seed --class=ScheduleScenarioSeeder
 *
 * เพิ่ม slot ที่ topic ขึ้นต้นด้วย [SCN-N] เพื่อแยกออกจากข้อมูลจริง
 * รันซ้ำได้ — จะลบ [SCN-*] เดิมก่อน
 */
class ScheduleScenarioSeeder extends Seeder
{
    public function run(): void
    {
        $year = AcademicYear::where('is_active', true)->first();
        if (! $year || $year->phase !== 'scheduling') {
            $this->command->error('ต้องมีปีการศึกษา active + phase=scheduling ก่อน');
            return;
        }

        $offerings = CourseOffering::where('academic_year_id', $year->id)
            ->with(['course', 'studentGroups', 'instructorPool.instructorProfile', 'coordinator'])
            ->orderBy('id')
            ->get();

        if ($offerings->count() < 2) {
            $this->command->error('ต้องมีอย่างน้อย 2 offerings');
            return;
        }

        $this->cleanup($offerings->pluck('id')->all());
        $this->ensureGroups($offerings);
        $offerings->load('studentGroups');

        $lecture = ActivityType::where('category', 'lecture')->first() ?? ActivityType::first();
        $rooms = Room::where('status', 'active')->orderBy('id')->get();
        if ($rooms->count() < 2) {
            $this->command->error('ต้องมีห้องอย่างน้อย 2 ห้อง');
            return;
        }

        $base = CarbonImmutable::parse($year->start_date)->startOfWeek(CarbonImmutable::MONDAY);

        // เลือก offering ที่มี dept-matched instructor >= 1 + groups >= 2 สำหรับ in-course scenarios
        $offIn = $offerings->first(fn ($o) => $this->deptMatchedPool($o)->count() >= 1 && $o->studentGroups->count() >= 2)
            ?? $offerings[0];

        // เลือก 2 offerings ที่มี dept-matched instructor ร่วมกัน สำหรับ cross-course scenarios
        [$offA, $offB] = $this->findCrossCoursePair($offerings) ?? [$offerings[0], $offerings[1]];

        // offering ที่ user ปัจจุบันไม่ได้เป็นหัวหน้า สำหรับ beyond-responsibility
        $offBeyond = $offerings->first(fn ($o) => (int) $o->coordinator_id !== 1);

        $this->command->info('ใช้ offering สำหรับ in-course: ' . $offIn->course->course_code);
        $this->command->info('ใช้ offering สำหรับ cross-course: ' . $offA->course->course_code . ' + ' . $offB->course->course_code);

        $scenarios = [];
        $scenarios[] = $this->scenario1FullTerm($offIn, $lecture, $rooms[0], $year, $base);
        $scenarios[] = $this->scenario2InCourseInstructor($offIn, $lecture, $rooms[0], $rooms[1], $base);
        $scenarios[] = $this->scenario3InCourseRoom($offIn, $lecture, $rooms[0], $base);
        $scenarios[] = $this->scenario4InCourseGroup($offIn, $lecture, $rooms[0], $rooms[1], $base);
        $scenarios[] = $this->scenario5CrossCourseInstructor($offA, $offB, $lecture, $rooms[0], $rooms[1], $base);
        $scenarios[] = $this->scenario6CrossCourseRoom($offA, $offB, $lecture, $rooms[0], $base);

        if ($offBeyond) {
            $scenarios[] = $this->scenario7BeyondResponsibility($offBeyond, $lecture, $rooms[0], $base);
        }

        foreach ($scenarios as $i => $count) {
            $this->command->info('  ✓ Scenario #' . ($i + 1) . ': ' . $count . ' slot');
        }

        $total = array_sum($scenarios);
        $this->command->info("เสร็จสิ้น — สร้าง {$total} slot ทดสอบ (topic ขึ้นต้นด้วย [SCN-N])");
    }

    private function cleanup(array $offeringIds): void
    {
        $deleted = Schedule::whereIn('course_offering_id', $offeringIds)
            ->where('topic', 'like', '[SCN-%]%')
            ->get();

        foreach ($deleted as $s) {
            $s->instructors()->detach();
            $s->studentGroups()->detach();
            $s->delete();
        }

        $this->command->info("ลบ scenario เดิม {$deleted->count()} รายการ");
    }

    /** Scenario 1: Series ทั้งเทอม — Monday 13:00-15:00 ทุกสัปดาห์ */
    private function scenario1FullTerm($offering, $activity, $room, $year, CarbonImmutable $base): int
    {
        $instructorIds = $this->instructorsFor($offering, 1);
        $groupIds = $offering->studentGroups->pluck('id')->take(2)->all();
        if (empty($instructorIds) || empty($groupIds)) return 0;

        $end = CarbonImmutable::parse($year->end_date);
        $count = 0;
        $date = $base->copy(); // Monday week 1

        while ($date->lte($end)) {
            if ($date->isWeekday()) {
                $this->createSchedule($offering, $activity, $room, $date, '13:00', '15:00',
                    "[SCN-1] Series ทั้งเทอม สัปดาห์ {$count}+1",
                    $instructorIds, $groupIds);
                $count++;
            }
            $date = $date->addWeek();
        }

        return $count;
    }

    /** Scenario 2: In-course instructor overlap */
    private function scenario2InCourseInstructor($offering, $activity, $room1, $room2, CarbonImmutable $base): int
    {
        $instructorIds = $this->instructorsFor($offering, 1);
        $groups = $offering->studentGroups;
        if (empty($instructorIds) || $groups->count() < 2) return 0;

        $date = $base->addWeek(); // สัปดาห์ 2 Monday
        $this->createSchedule($offering, $activity, $room1, $date, '08:00', '10:00',
            '[SCN-2A] อาจารย์ชน — slot ที่ 1', $instructorIds, [$groups[0]->id]);
        $this->createSchedule($offering, $activity, $room2, $date, '09:00', '11:00',
            '[SCN-2B] อาจารย์ชน — slot ที่ 2 (ชนกับ 2A)', $instructorIds, [$groups[1]->id]);

        return 2;
    }

    /** Scenario 3: In-course room overlap (ใช้ instructor 2 คนถ้ามี ไม่งั้นใช้คนเดียว) */
    private function scenario3InCourseRoom($offering, $activity, $room, CarbonImmutable $base): int
    {
        $pool = $this->deptMatchedPool($offering);
        if ($pool->isEmpty() || $offering->studentGroups->count() < 2) return 0;

        $inst1 = [(int) $pool[0]->id];
        $inst2 = [(int) ($pool->get(1)?->id ?? $pool[0]->id)];
        $groups = $offering->studentGroups;

        $date = $base->addWeek()->addDay(); // สัปดาห์ 2 Tuesday
        $this->createSchedule($offering, $activity, $room, $date, '13:00', '15:00',
            '[SCN-3A] ห้องชน — slot ที่ 1', $inst1, [$groups[0]->id]);
        $this->createSchedule($offering, $activity, $room, $date, '14:00', '16:00',
            '[SCN-3B] ห้องชน — slot ที่ 2 (ชนห้องกับ 3A)', $inst2, [$groups[1]->id]);

        return 2;
    }

    /** Scenario 4: In-course group overlap */
    private function scenario4InCourseGroup($offering, $activity, $room1, $room2, CarbonImmutable $base): int
    {
        $pool = $this->deptMatchedPool($offering);
        if ($pool->isEmpty() || $offering->studentGroups->isEmpty()) return 0;

        $inst1 = [(int) $pool[0]->id];
        $inst2 = [(int) ($pool->get(1)?->id ?? $pool[0]->id)];
        $groups = $offering->studentGroups;

        $date = $base->addWeek()->addDays(2); // สัปดาห์ 2 Wednesday
        $this->createSchedule($offering, $activity, $room1, $date, '08:00', '10:00',
            '[SCN-4A] กลุ่มชน — slot ที่ 1', $inst1, [$groups[0]->id]);
        $this->createSchedule($offering, $activity, $room2, $date, '09:00', '11:00',
            '[SCN-4B] กลุ่มชน — slot ที่ 2 (กลุ่มเดียวกับ 4A)', $inst2, [$groups[0]->id]);

        return 2;
    }

    /** Scenario 5: Cross-course instructor overlap (อาจารย์คนเดียวกันสอน 2 วิชาเวลาเดียวกัน) */
    private function scenario5CrossCourseInstructor($offA, $offB, $activity, $room1, $room2, CarbonImmutable $base): int
    {
        // ต้องเป็น instructor ที่ผ่าน dept gate ของทั้ง 2 offerings
        $deptIdA = $offA->course->department_id;
        $deptIdB = $offB->course->department_id;

        $poolA = $this->deptMatchedPool($offA)->pluck('id')->all();
        $poolB = $this->deptMatchedPool($offB)->pluck('id')->all();
        $shared = array_values(array_intersect($poolA, $poolB));

        if (empty($shared)) {
            $this->command->warn('  Scenario 5: ไม่มีอาจารย์ใน pool ที่ผ่าน dept gate ทั้ง ' . $offA->course->course_code . ' และ ' . $offB->course->course_code);
            return 0;
        }

        $groupA = $offA->studentGroups->first();
        $groupB = $offB->studentGroups->first();
        if (!$groupA || !$groupB) return 0;

        $date = $base->addWeek()->addDays(3); // สัปดาห์ 2 Thursday
        $this->createSchedule($offA, $activity, $room1, $date, '08:00', '10:00',
            '[SCN-5A] Cross-course instructor — ' . $offA->course->course_code,
            [$shared[0]], [$groupA->id]);
        $this->createSchedule($offB, $activity, $room2, $date, '09:00', '11:00',
            '[SCN-5B] Cross-course instructor — ' . $offB->course->course_code . ' (ชน อ. กับ 5A)',
            [$shared[0]], [$groupB->id]);

        return 2;
    }

    /** Scenario 6: Cross-course room overlap */
    private function scenario6CrossCourseRoom($offA, $offB, $activity, $room, CarbonImmutable $base): int
    {
        $instA = $this->instructorsFor($offA, 1);
        $instB = $this->instructorsFor($offB, 1);
        $groupA = $offA->studentGroups->first();
        $groupB = $offB->studentGroups->first();
        if (empty($instA) || empty($instB) || !$groupA || !$groupB) return 0;

        $date = $base->addWeek()->addDays(4); // สัปดาห์ 2 Friday
        $this->createSchedule($offA, $activity, $room, $date, '13:00', '15:00',
            '[SCN-6A] Cross-course room — ' . $offA->course->course_code,
            $instA, [$groupA->id]);
        $this->createSchedule($offB, $activity, $room, $date, '14:00', '16:00',
            '[SCN-6B] Cross-course room — ' . $offB->course->course_code . ' (ชนห้องกับ 6A)',
            $instB, [$groupB->id]);

        return 2;
    }

    /** Scenario 7: Slot ของ offering ที่ user อื่นเป็นหัวหน้า (test beyond-responsibility) */
    private function scenario7BeyondResponsibility($offering, $activity, $room, CarbonImmutable $base): int
    {
        $instructorIds = $this->instructorsFor($offering, 1);
        $groupIds = $offering->studentGroups->pluck('id')->take(1)->all();
        if (empty($instructorIds) || empty($groupIds)) return 0;

        $date = $base->addWeeks(2); // สัปดาห์ 3 Monday
        $coord = $offering->coordinator?->name ?? '?';
        $this->createSchedule($offering, $activity, $room, $date, '10:00', '12:00',
            "[SCN-7] Beyond-responsibility — {$offering->course->course_code} (coord: {$coord})",
            $instructorIds, $groupIds);

        return 1;
    }

    private function ensureGroups($offerings): void
    {
        foreach ($offerings as $o) {
            if ($o->studentGroups->isNotEmpty()) continue;
            for ($i = 1; $i <= 3; $i++) {
                $o->studentGroups()->create([
                    'group_code' => 'A' . $i,
                    'student_count' => 30,
                    'color_code' => ['#2563eb', '#16a34a', '#ca8a04'][$i - 1],
                ]);
            }
            $this->command->info("  + สร้าง 3 กลุ่มให้ {$o->course->course_code}");
        }
    }

    private function instructorsFor(CourseOffering $offering, int $take = 1, int $skip = 0): array
    {
        return $this->deptMatchedPool($offering)
            ->slice($skip, $take)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function deptMatchedPool(CourseOffering $offering)
    {
        $deptId = $offering->course?->department_id;

        return $offering->instructorPool
            ->filter(fn ($u) => (int) ($u->instructorProfile?->department_id) === (int) $deptId)
            ->values();
    }

    private function findCrossCoursePair($offerings): ?array
    {
        foreach ($offerings as $a) {
            $poolA = $this->deptMatchedPool($a)->pluck('id')->all();
            foreach ($offerings as $b) {
                if ($a->id === $b->id) continue;
                $poolB = $this->deptMatchedPool($b)->pluck('id')->all();
                if (!empty(array_intersect($poolA, $poolB))) {
                    return [$a, $b];
                }
            }
        }
        return null;
    }

    private function createSchedule($offering, $activity, $room, CarbonImmutable $date, string $start, string $end, string $topic, array $instructorIds, array $groupIds): void
    {
        DB::transaction(function () use ($offering, $activity, $room, $date, $start, $end, $topic, $instructorIds, $groupIds) {
            $schedule = Schedule::create([
                'course_offering_id' => $offering->id,
                'activity_type_id' => $activity->id,
                'room_id' => $room->id,
                'start_date' => $date->toDateString(),
                'end_date' => $date->toDateString(),
                'teaching_date' => $date->toDateString(),
                'start_time' => $start . ':00',
                'end_time' => $end . ':00',
                'topic' => $topic,
                'status' => 'draft',
            ]);

            $payload = [];
            foreach ($instructorIds as $i => $id) {
                $payload[$id] = ['is_lead' => $i === 0];
            }
            $schedule->instructors()->sync($payload);
            $schedule->studentGroups()->sync($groupIds);
        });
    }
}
