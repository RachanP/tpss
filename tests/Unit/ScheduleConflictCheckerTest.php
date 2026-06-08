<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\LocationType;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\StudentCohort;
use App\Models\StudentGroup;
use App\Models\User;
use App\Services\ScheduleConflictChecker;
use App\Services\ScheduleConflictPolicy;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Unit test สำหรับ ScheduleConflictChecker::bulkConflictMap()
 *
 * เป็น pure in-memory logic — สร้าง Eloquent model + setRelation เองทั้งหมด ไม่แตะ DB
 * (relations ถูก preload ครบ → ไม่มี query ยิงออกไป) ทำให้ไล่ permutation ของการชนได้ครบและเร็ว:
 * instructor / room (รวม is_shared) / group ภายในวิชา / group ข้ามวิชาผ่าน root cohort
 */
class ScheduleConflictCheckerTest extends TestCase
{
    private int $autoId = 1;

    private function checker(): ScheduleConflictChecker
    {
        // policy ไม่ถูกใช้ใน bulkConflictMap แต่ constructor บังคับ
        return new ScheduleConflictChecker(new ScheduleConflictPolicy());
    }

    /**
     * @param  array<int, string>  $instructors  [id => name]
     * @param  array<int, StudentGroup>  $groups
     */
    private function makeSchedule(
        int $offeringId,
        string $date,
        string $start,
        string $end,
        array $instructors = [],
        array $groups = [],
        ?int $roomId = null,
        bool $roomShared = false,
        string $roomName = 'ห้อง A',
        string $courseCode = 'NSBS 111',
    ): Schedule {
        $schedule = new Schedule();
        $schedule->forceFill([
            'id' => $this->autoId++,
            'course_offering_id' => $offeringId,
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'room_id' => $roomId,
        ]);

        // courseOffering.course — ใช้สร้าง schedule label
        $course = new Course();
        $course->forceFill(['id' => $offeringId, 'course_code' => $courseCode, 'name_th' => 'วิชาทดสอบ']);
        $offering = new CourseOffering();
        $offering->forceFill(['id' => $offeringId, 'course_id' => $offeringId]);
        $offering->setRelation('course', $course);
        $schedule->setRelation('courseOffering', $offering);

        // instructors
        $instructorModels = collect($instructors)->map(function (string $name, int $id) {
            $user = new User();
            $user->forceFill(['id' => $id, 'name' => $name, 'prefix' => null]);
            $user->setRelation('instructorProfile', null); // formatted_name → คืน name ตรง ๆ
            return $user;
        })->values();
        $schedule->setRelation('instructors', $instructorModels);

        // student groups
        $schedule->setRelation('studentGroups', collect($groups));

        // room + locationType
        if ($roomId !== null) {
            $locationType = new LocationType();
            $locationType->forceFill(['id' => 1, 'is_shared' => $roomShared]);
            $room = new Room();
            $room->forceFill(['id' => $roomId, 'room_name' => $roomName]);
            $room->setRelation('locationType', $locationType);
            $schedule->setRelation('room', $room);
        } else {
            $schedule->setRelation('room', null);
        }

        return $schedule;
    }

    private function makeGroup(int $id, string $code, int $offeringId, int $rootCohortId, string $rootCode): StudentGroup
    {
        $cohort = new StudentCohort();
        $cohort->forceFill(['id' => 900000 + $id, 'parent_id' => $rootCohortId, 'code' => $rootCode]);
        $cohort->setRelation('parent', null);

        $group = new StudentGroup();
        $group->forceFill([
            'id' => $id,
            'group_code' => $code,
            'course_offering_id' => $offeringId,
            'cohort_group_id' => $cohort->id,
        ]);
        $group->setRelation('cohortGroup', $cohort);

        return $group;
    }

    /**
     * @param  Collection<int, array{type:string}>  $entries
     * @return array<int, string>
     */
    private function types(Collection $map, int $scheduleId): array
    {
        return collect($map->get($scheduleId, collect()))
            ->pluck('type')
            ->all();
    }

    public function test_overlapping_instructor_produces_conflict_both_directions(): void
    {
        $a = $this->makeSchedule(1, '2026-08-01', '09:00', '12:00', [10 => 'อ.ราชันย์']);
        $b = $this->makeSchedule(2, '2026-08-01', '10:00', '11:00', [10 => 'อ.ราชันย์']);

        $map = $this->checker()->bulkConflictMap(collect([$a, $b]));

        $this->assertContains('instructor_overlap', $this->types($map, $a->id));
        $this->assertContains('instructor_overlap', $this->types($map, $b->id));
    }

    public function test_non_overlapping_time_produces_no_conflict(): void
    {
        $a = $this->makeSchedule(1, '2026-08-01', '09:00', '10:00', [10 => 'อ.ราชันย์']);
        $b = $this->makeSchedule(2, '2026-08-01', '10:00', '11:00', [10 => 'อ.ราชันย์']);

        $map = $this->checker()->bulkConflictMap(collect([$a, $b]));

        $this->assertSame([], $this->types($map, $a->id));
        $this->assertSame([], $this->types($map, $b->id));
    }

    public function test_different_instructors_do_not_conflict(): void
    {
        $a = $this->makeSchedule(1, '2026-08-01', '09:00', '12:00', [10 => 'อ.ราชันย์']);
        $b = $this->makeSchedule(2, '2026-08-01', '10:00', '11:00', [11 => 'อ.พรภิมล']);

        $map = $this->checker()->bulkConflictMap(collect([$a, $b]));

        $this->assertNotContains('instructor_overlap', $this->types($map, $a->id));
        $this->assertNotContains('instructor_overlap', $this->types($map, $b->id));
    }

    public function test_same_room_overlap_produces_room_conflict(): void
    {
        $a = $this->makeSchedule(1, '2026-08-01', '09:00', '12:00', roomId: 5);
        $b = $this->makeSchedule(2, '2026-08-01', '10:00', '11:00', roomId: 5);

        $map = $this->checker()->bulkConflictMap(collect([$a, $b]));

        $this->assertContains('room_overlap', $this->types($map, $a->id));
        $this->assertContains('room_overlap', $this->types($map, $b->id));
    }

    public function test_shared_room_is_exempt_from_room_conflict(): void
    {
        // ห้องประเภท is_shared (เช่น โรงพยาบาล/ชุมชน) ใช้ร่วมข้ามตารางได้ ไม่ถือว่าชน
        $a = $this->makeSchedule(1, '2026-08-01', '09:00', '12:00', roomId: 5, roomShared: true);
        $b = $this->makeSchedule(2, '2026-08-01', '10:00', '11:00', roomId: 5, roomShared: true);

        $map = $this->checker()->bulkConflictMap(collect([$a, $b]));

        $this->assertNotContains('room_overlap', $this->types($map, $a->id));
        $this->assertNotContains('room_overlap', $this->types($map, $b->id));
    }

    public function test_same_offering_exact_group_overlap_conflicts(): void
    {
        $group = $this->makeGroup(7, 'A1', offeringId: 1, rootCohortId: 100, rootCode: 'A');
        $a = $this->makeSchedule(1, '2026-08-01', '09:00', '12:00', groups: [$group]);
        $b = $this->makeSchedule(1, '2026-08-01', '10:00', '11:00', groups: [$group]);

        $map = $this->checker()->bulkConflictMap(collect([$a, $b]));

        $this->assertContains('group_overlap', $this->types($map, $a->id));
        $this->assertContains('group_overlap', $this->types($map, $b->id));
    }

    public function test_cross_course_shared_root_cohort_conflicts(): void
    {
        // สองวิชาต่างกัน แต่กลุ่มย่อยอยู่ใต้ root cohort เดียวกัน (A) → กลุ่มเดียวห้ามอยู่ 2 วิชาพร้อมกัน
        $groupX = $this->makeGroup(7, 'A1', offeringId: 1, rootCohortId: 100, rootCode: 'A');
        $groupY = $this->makeGroup(8, 'A2', offeringId: 2, rootCohortId: 100, rootCode: 'A');
        $a = $this->makeSchedule(1, '2026-08-01', '09:00', '12:00', groups: [$groupX], courseCode: 'NSBS 301');
        $b = $this->makeSchedule(2, '2026-08-01', '10:00', '11:00', groups: [$groupY], courseCode: 'NSBS 302');

        $map = $this->checker()->bulkConflictMap(collect([$a, $b]));

        $this->assertContains('group_overlap', $this->types($map, $a->id));
        $this->assertContains('group_overlap', $this->types($map, $b->id));
    }

    public function test_cross_course_different_root_cohort_does_not_conflict(): void
    {
        $groupX = $this->makeGroup(7, 'A1', offeringId: 1, rootCohortId: 100, rootCode: 'A');
        $groupY = $this->makeGroup(8, 'B1', offeringId: 2, rootCohortId: 200, rootCode: 'B');
        $a = $this->makeSchedule(1, '2026-08-01', '09:00', '12:00', groups: [$groupX]);
        $b = $this->makeSchedule(2, '2026-08-01', '10:00', '11:00', groups: [$groupY]);

        $map = $this->checker()->bulkConflictMap(collect([$a, $b]));

        $this->assertNotContains('group_overlap', $this->types($map, $a->id));
        $this->assertNotContains('group_overlap', $this->types($map, $b->id));
    }

    public function test_no_room_means_no_room_conflict(): void
    {
        $a = $this->makeSchedule(1, '2026-08-01', '09:00', '12:00', [10 => 'อ.ราชันย์'], roomId: null);
        $b = $this->makeSchedule(2, '2026-08-01', '10:00', '11:00', [10 => 'อ.ราชันย์'], roomId: null);

        $map = $this->checker()->bulkConflictMap(collect([$a, $b]));

        $this->assertNotContains('room_overlap', $this->types($map, $a->id));
    }

    public function test_disjoint_date_windows_do_not_conflict(): void
    {
        $a = $this->makeSchedule(1, '2026-08-01', '09:00', '12:00', [10 => 'อ.ราชันย์']);
        $b = $this->makeSchedule(2, '2026-08-08', '09:00', '12:00', [10 => 'อ.ราชันย์']);

        $map = $this->checker()->bulkConflictMap(collect([$a, $b]));

        $this->assertSame([], $this->types($map, $a->id));
        $this->assertSame([], $this->types($map, $b->id));
    }
}
