<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ActivityType;
use App\Models\CourseOffering;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\LocationType;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\StudentGroup;
use App\Models\Course;
use App\Models\User;
use App\Models\UserRole;
use App\Http\Controllers\Admin\AlertController;
use App\Services\ScheduleConflictChecker;
use App\Services\ScheduleConflictIndex;
use App\Services\ScheduleConflictPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Bug #0/#1 — Location Type is_shared
 *
 * ครอบคลุม:
 *  - room_overlap ไม่ถูก emit เมื่อ locationType.is_shared = true (roomConflicts)
 *  - room_overlap ยังถูก emit เมื่อ is_shared = false (roomConflicts)
 *  - bulkConflictMap ใช้ is_shared เช่นเดียวกัน
 *  - capacity alert ไม่ถูก emit สำหรับ is_shared room
 */
class LocationTypeSharedTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ──────────────────────────────────────────────────────

    private function makeUser(string $role): User
    {
        static $seq = 0;
        $seq++;
        $user = User::create([
            'username' => "{$role}_{$seq}",
            'name'     => ucfirst($role) . " User {$seq}",
            'email'    => "{$role}_{$seq}@example.com",
            'password' => Hash::make('password'),
        ]);
        UserRole::create(['user_id' => $user->id, 'role' => $role, 'is_primary' => true]);
        return $user;
    }

    private function makeMinimalOffering(?string $courseCode = null): CourseOffering
    {
        static $seq = 0;
        $seq++;

        $dept = Department::firstOrCreate(['name' => 'ภาควิชาทดสอบ']);
        $curr = Curriculum::firstOrCreate(['name' => 'หลักสูตรทดสอบ', 'effective_year' => 2568, 'is_active' => true]);
        $year = AcademicYear::firstOrCreate([
            'name'       => '2568',
            'start_date' => '2025-08-01',
            'end_date'   => '2026-01-31',
            'is_active'  => true,
            'phase'      => 'scheduling',
        ]);

        $coordinator = $this->makeUser('course_head');

        $course = Course::create([
            'course_code'                 => $courseCode ?? "TST{$seq}",
            'curriculum_id'               => $curr->id,
            'department_id'               => $dept->id,
            'name_th'                     => "รายวิชาทดสอบ {$seq}",
            'name_en'                     => "Test Course {$seq}",
            'course_type'                 => 'theory',
            'default_year_level'          => 1,
            'default_semester'            => 1,
            'credits'                     => 3,
            'lecture_hours'               => 3,
            'lab_hours'                   => 0,
            'self_study_hours'            => 6,
            'status'                      => 'active',
            'requires_practicum_rotation' => false,
        ]);

        return CourseOffering::create([
            'course_id'      => $course->id,
            'academic_year_id' => $year->id,
            'coordinator_id' => $coordinator->id,
            'status'         => 'approved',
        ]);
    }

    private function makeSchedule(CourseOffering $offering, Room $room, string $start = '08:00', string $end = '10:00'): Schedule
    {
        $actType = ActivityType::firstOrCreate([
            'name'       => 'บรรยาย',
            'color_code' => '#000000',
            'category'   => 'lecture',
        ]);

        return Schedule::create([
            'course_offering_id' => $offering->id,
            'activity_type_id'   => $actType->id,
            'room_id'            => $room->id,
            'start_date'         => '2025-09-01',
            'end_date'           => '2025-09-01',
            'start_time'         => $start . ':00',
            'end_time'           => $end . ':00',
        ]);
    }

    private function checker(): ScheduleConflictChecker
    {
        return new ScheduleConflictChecker(new ScheduleConflictPolicy());
    }

    // ══ roomConflicts (check()) ═══════════════════════════════════════

    public function test_room_overlap_not_emitted_when_location_type_is_shared(): void
    {
        // Arrange: ห้องประเภท is_shared
        $sharedType = LocationType::create(['name' => 'หอผู้ป่วย', 'is_shared' => true]);
        $room = Room::create([
            'room_code'        => 'WARD-01',
            'room_name'        => 'หอผู้ป่วย 1',
            'location_type_id' => $sharedType->id,
            'status'           => 'active',
        ]);

        $offering1 = $this->makeMinimalOffering('TST-SH-1');
        $offering2 = $this->makeMinimalOffering('TST-SH-2');

        // Schedule ที่มีอยู่แล้วในห้องเดียวกัน เวลาเดียวกัน
        $this->makeSchedule($offering2, $room, '08:00', '10:00');

        // จัดตาราง 2 วิชา ซ้อนกัน ในห้องเดียวกัน
        $data = [
            'room_id'            => $room->id,
            'course_offering_id' => $offering1->id,
            'activity_type_id'   => ActivityType::first()->id,
            'start_date'         => '2025-09-01',
            'end_date'           => '2025-09-01',
            'start_time'         => '08:00:00',
            'end_time'           => '10:00:00',
        ];

        // Act
        $conflicts = $this->checker()->check($data, [], []);

        // Assert: ไม่ควรมี room_overlap เพราะ is_shared
        $roomConflicts = collect($conflicts)->where('type', 'room_overlap');
        $this->assertCount(0, $roomConflicts, 'is_shared room ไม่ควรแจ้ง room_overlap');
    }

    public function test_room_overlap_emitted_when_location_type_is_not_shared(): void
    {
        // Arrange: ห้องประเภทปกติ (is_shared = false)
        $normalType = LocationType::create(['name' => 'ห้องบรรยาย', 'is_shared' => false]);
        $room = Room::create([
            'room_code'        => 'LEC-01',
            'room_name'        => 'ห้องบรรยาย 1',
            'location_type_id' => $normalType->id,
            'capacity'         => 40,
            'status'           => 'active',
        ]);

        $offering1 = $this->makeMinimalOffering('TST-NS-1');
        $offering2 = $this->makeMinimalOffering('TST-NS-2');

        // Schedule ที่มีอยู่แล้ว
        $this->makeSchedule($offering2, $room, '08:00', '10:00');

        $data = [
            'room_id'            => $room->id,
            'course_offering_id' => $offering1->id,
            'activity_type_id'   => ActivityType::first()->id,
            'start_date'         => '2025-09-01',
            'end_date'           => '2025-09-01',
            'start_time'         => '08:00:00',
            'end_time'           => '10:00:00',
        ];

        // Act
        $conflicts = $this->checker()->check($data, [], []);

        // Assert: ควรมี room_overlap เพราะ is_shared = false
        $roomConflicts = collect($conflicts)->where('type', 'room_overlap');
        $this->assertGreaterThan(0, $roomConflicts->count(), 'ห้องปกติควรแจ้ง room_overlap');
    }

    // ══ bulkConflictMap ══════════════════════════════════════════════

    public function test_bulk_conflict_map_skips_room_overlap_for_shared_type(): void
    {
        $sharedType = LocationType::create(['name' => 'ชุมชน', 'is_shared' => true]);
        $room = Room::create([
            'room_code'        => 'COM-01',
            'room_name'        => 'ชุมชน 1',
            'location_type_id' => $sharedType->id,
            'status'           => 'active',
        ]);

        $offering1 = $this->makeMinimalOffering('TST-BLK-1');
        $offering2 = $this->makeMinimalOffering('TST-BLK-2');
        $actType   = ActivityType::first();

        $s1 = $this->makeSchedule($offering1, $room, '08:00', '10:00');
        $s2 = $this->makeSchedule($offering2, $room, '08:00', '10:00');

        // Eager load relations ที่ bulkConflictMap ต้องการ
        $schedules = Schedule::with([
            'courseOffering.course',
            'instructors',
            'studentGroups',
            'room.locationType',
        ])->whereIn('id', [$s1->id, $s2->id])->get();

        $conflictMap = $this->checker()->bulkConflictMap($schedules);

        $s1Conflicts = collect($conflictMap->get($s1->id, []));
        $s2Conflicts = collect($conflictMap->get($s2->id, []));

        $this->assertCount(0, $s1Conflicts->where('type', 'room_overlap'), 'is_shared ไม่ควรมี room_overlap ใน bulkConflictMap');
        $this->assertCount(0, $s2Conflicts->where('type', 'room_overlap'), 'is_shared ไม่ควรมี room_overlap ใน bulkConflictMap');
    }

    public function test_bulk_conflict_map_emits_room_overlap_for_normal_type(): void
    {
        $normalType = LocationType::create(['name' => 'ห้องปฏิบัติการ', 'is_shared' => false]);
        $room = Room::create([
            'room_code'        => 'LAB-01',
            'room_name'        => 'ห้อง Lab 1',
            'location_type_id' => $normalType->id,
            'capacity'         => 30,
            'status'           => 'active',
        ]);

        $offering1 = $this->makeMinimalOffering('TST-LAB-1');
        $offering2 = $this->makeMinimalOffering('TST-LAB-2');

        $s1 = $this->makeSchedule($offering1, $room, '08:00', '10:00');
        $s2 = $this->makeSchedule($offering2, $room, '08:00', '10:00');

        $schedules = Schedule::with([
            'courseOffering.course',
            'instructors',
            'studentGroups',
            'room.locationType',
        ])->whereIn('id', [$s1->id, $s2->id])->get();

        $conflictMap = $this->checker()->bulkConflictMap($schedules);

        $s1Conflicts = collect($conflictMap->get($s1->id, []));
        $this->assertGreaterThan(0, $s1Conflicts->where('type', 'room_overlap')->count(), 'ห้องปกติควรมี room_overlap ใน bulkConflictMap');
    }

    // ══ ScheduleConflictIndex (หน้าแจ้งเตือน / sidebar badge) ═════════
    // Regression: path นี้เคยไม่เช็ค is_shared → นับห้องใช้ร่วมเป็นการชน (เลขไม่ตรงกับ bulkConflictMap)

    public function test_conflict_index_skips_room_overlap_for_shared_type(): void
    {
        $sharedType = LocationType::create(['name' => 'โรงพยาบาล', 'is_shared' => true]);
        $room = Room::create([
            'room_code'        => 'HOSP-IDX',
            'room_name'        => 'โรงพยาบาลทดสอบ',
            'location_type_id' => $sharedType->id,
            'status'           => 'active',
        ]);

        $s1 = $this->makeSchedule($this->makeMinimalOffering('TST-IDX-1'), $room, '08:00', '10:00');
        $s2 = $this->makeSchedule($this->makeMinimalOffering('TST-IDX-2'), $room, '08:00', '10:00');

        $schedules = Schedule::with(['courseOffering.course', 'instructors', 'studentGroups', 'room.locationType'])
            ->whereIn('id', [$s1->id, $s2->id])->get();

        $map = app(ScheduleConflictIndex::class)->conflictsFor($schedules);

        $this->assertCount(0, collect($map->get($s1->id, collect()))->where('type', 'room_overlap'), 'is_shared ไม่ควรมี room_overlap ใน conflictsFor');
        $this->assertCount(0, collect($map->get($s2->id, collect()))->where('type', 'room_overlap'), 'is_shared ไม่ควรมี room_overlap ใน conflictsFor');
    }

    public function test_conflict_index_emits_room_overlap_for_normal_type(): void
    {
        $normalType = LocationType::create(['name' => 'ห้องบรรยายปกติ', 'is_shared' => false]);
        $room = Room::create([
            'room_code'        => 'LEC-IDX',
            'room_name'        => 'ห้องบรรยายทดสอบ',
            'location_type_id' => $normalType->id,
            'capacity'         => 40,
            'status'           => 'active',
        ]);

        $s1 = $this->makeSchedule($this->makeMinimalOffering('TST-IDX-N1'), $room, '08:00', '10:00');
        $s2 = $this->makeSchedule($this->makeMinimalOffering('TST-IDX-N2'), $room, '08:00', '10:00');

        $schedules = Schedule::with(['courseOffering.course', 'instructors', 'studentGroups', 'room.locationType'])
            ->whereIn('id', [$s1->id, $s2->id])->get();

        $map = app(ScheduleConflictIndex::class)->conflictsFor($schedules);

        $this->assertGreaterThan(0, collect($map->get($s1->id, collect()))->where('type', 'room_overlap')->count(), 'ห้องปกติควรมี room_overlap ใน conflictsFor');
    }

    // ══ capacity alert for is_shared ═════════════════════════════════

    public function test_capacity_alert_skipped_for_is_shared_room(): void
    {
        // Arrange: ห้อง is_shared ไม่มี capacity
        $sharedType = LocationType::create([
            'name'      => 'หอผู้ป่วย',
            'is_shared' => true,  // สถานที่เปิด — ควร skip alert
        ]);
        Room::create([
            'room_code'        => 'WARD-99',
            'room_name'        => 'หอผู้ป่วยทดสอบ',
            'location_type_id' => $sharedType->id,
            'status'           => 'active',
            'capacity'         => 0, // ไม่มี capacity
        ]);

        AlertController::flushCache();
        $summary = AlertController::getSummary();

        $this->assertEquals(0, $summary['rooms'], 'is_shared room ไม่ควรถูกนับเป็น room warning แม้ไม่มี capacity');
    }

    public function test_capacity_alert_still_fires_for_non_shared_room_without_capacity(): void
    {
        $normalType = LocationType::create([
            'name'      => 'ห้องบรรยายปกติ',
            'is_shared' => false,
        ]);
        Room::create([
            'room_code'        => 'LEC-99',
            'room_name'        => 'ห้องทดสอบ',
            'location_type_id' => $normalType->id,
            'status'           => 'active',
            'capacity'         => 0,
        ]);

        AlertController::flushCache();
        $summary = AlertController::getSummary();

        $this->assertGreaterThan(0, $summary['rooms'], 'ห้องปกติไม่มี capacity ควรถูกนับเป็น warning');
    }
}
