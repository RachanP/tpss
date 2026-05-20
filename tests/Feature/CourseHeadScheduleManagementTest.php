<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\InstructorProfile;
use App\Models\LocationType;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\StudentGroup;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CourseHeadScheduleManagementTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 1;

    public function test_schedule_store_blocks_overlapping_instructor(): void
    {
        $head = $this->makeUser('course_head');
        $instructor = $this->makeUser('instructor');
        $offering = $this->makeOffering($head, 'NUR101');
        $offering->instructorPool()->attach($instructor->id, ['role_in_course' => 'instructor']);
        $group = $this->makeGroup($offering, 'A1');
        $otherGroup = $this->makeGroup($offering, 'A2');
        $activityTypeId = $this->createActivityType();
        $room = $this->makeRoom('R-101');

        $existing = $this->makeSchedule($offering, $activityTypeId, $room->id, [
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-01',
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]);
        $existing->instructors()->attach($instructor->id, ['is_lead' => false]);
        $existing->studentGroups()->attach($group->id);

        $this->actingAsCourseHead($head);

        $this->post(route('maker.course_offerings.schedules.store', $offering), [
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-01',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'activity_type_id' => $activityTypeId,
            'room_id' => null,
            'topic' => 'ทดสอบผู้สอนชนเวลา',
            'capacity_required' => 20,
            'instructor_ids' => [$instructor->id],
            'student_group_ids' => [$otherGroup->id],
        ])->assertSessionHasErrors('instructor_ids');
    }

    public function test_schedule_store_blocks_overlapping_room_across_offerings(): void
    {
        $head = $this->makeUser('course_head');
        $otherHead = $this->makeUser('course_head');
        $instructor = $this->makeUser('instructor');
        $otherInstructor = $this->makeUser('instructor');
        $offering = $this->makeOffering($head, 'NUR201');
        $otherOffering = $this->makeOffering($otherHead, 'NUR202');
        $offering->instructorPool()->attach($instructor->id, ['role_in_course' => 'instructor']);
        $otherOffering->instructorPool()->attach($otherInstructor->id, ['role_in_course' => 'instructor']);
        $group = $this->makeGroup($offering, 'A1');
        $otherGroup = $this->makeGroup($otherOffering, 'B1');
        $activityTypeId = $this->createActivityType();
        $room = $this->makeRoom('R-201');

        $existing = $this->makeSchedule($otherOffering, $activityTypeId, $room->id, [
            'start_date' => '2026-08-02',
            'end_date' => '2026-08-02',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);
        $existing->instructors()->attach($otherInstructor->id, ['is_lead' => false]);
        $existing->studentGroups()->attach($otherGroup->id);

        $this->actingAsCourseHead($head);

        $this->post(route('maker.course_offerings.schedules.store', $offering), [
            'start_date' => '2026-08-02',
            'end_date' => '2026-08-02',
            'start_time' => '09:30',
            'end_time' => '11:00',
            'activity_type_id' => $activityTypeId,
            'room_id' => $room->id,
            'topic' => 'ทดสอบห้องชนเวลา',
            'capacity_required' => 20,
            'instructor_ids' => [$instructor->id],
            'student_group_ids' => [$group->id],
        ])->assertSessionHasErrors('room_id');
    }

    public function test_conflict_check_endpoint_returns_realtime_conflicts(): void
    {
        $head = $this->makeUser('course_head');
        $instructor = $this->makeUser('instructor');
        $offering = $this->makeOffering($head, 'NUR301');
        $offering->instructorPool()->attach($instructor->id, ['role_in_course' => 'instructor']);
        $group = $this->makeGroup($offering, 'A1');
        $activityTypeId = $this->createActivityType();
        $room = $this->makeRoom('R-301');

        $existing = $this->makeSchedule($offering, $activityTypeId, $room->id, [
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);
        $existing->instructors()->attach($instructor->id, ['is_lead' => false]);
        $existing->studentGroups()->attach($group->id);

        $this->actingAsCourseHead($head);

        $this->postJson(route('maker.course_offerings.schedules.check_conflicts', $offering), [
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'room_id' => $room->id,
            'capacity_required' => 10,
            'instructor_ids' => [$instructor->id],
            'student_group_ids' => [$group->id],
        ])
            ->assertOk()
            ->assertJsonPath('conflicts.groups.0', 'A1')
            ->assertJsonPath('conflicts.instructors.0', $instructor->formatted_name)
            ->assertJsonPath('conflicts.room', $room->room_code.' '.$room->room_name)
            ->assertJsonPath('conflicts.capacity.selected', 20)
            ->assertJsonPath('conflicts.capacity.limit', 10);
    }

    public function test_schedule_index_shows_warning_badges(): void
    {
        $head = $this->makeUser('course_head');
        $instructor = $this->makeUser('instructor');
        $offering = $this->makeOffering($head, 'NUR401');
        $offering->instructorPool()->attach($instructor->id, ['role_in_course' => 'instructor']);
        $firstGroup = $this->makeGroup($offering, 'A1');
        $secondGroup = $this->makeGroup($offering, 'A2');
        $activityTypeId = $this->createActivityType();
        $room = $this->makeRoom('R-401');

        $first = $this->makeSchedule($offering, $activityTypeId, $room->id, [
            'start_date' => '2026-08-04',
            'end_date' => '2026-08-04',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);
        $first->instructors()->attach($instructor->id, ['is_lead' => false]);
        $first->studentGroups()->attach($firstGroup->id);

        $second = $this->makeSchedule($offering, $activityTypeId, $room->id, [
            'start_date' => '2026-08-04',
            'end_date' => '2026-08-04',
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]);
        $second->instructors()->attach($instructor->id, ['is_lead' => false]);
        $second->studentGroups()->attach([$firstGroup->id, $secondGroup->id]);
        $second->update(['capacity_required' => 10]);

        $this->actingAsCourseHead($head);

        $this->get(route('maker.course_offerings.schedules.index', $offering))
            ->assertOk()
            ->assertSee('มีคำเตือน')
            ->assertSee('กลุ่มชนเวลา')
            ->assertSee('ผู้สอนชนเวลา')
            ->assertSee('ห้องชนเวลา')
            ->assertSee('จำนวนเกิน');
    }

    private function actingAsCourseHead(User $user): void
    {
        $this->actingAs($user);
        $this->withSession(['active_role' => 'course_head']);
    }

    private function makeUser(string $role): User
    {
        $number = $this->sequence++;

        $user = User::create([
            'username' => "schedule_user_{$number}",
            'name' => "Schedule User {$number}",
            'email' => "schedule_user_{$number}@example.com",
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        UserRole::create([
            'user_id' => $user->id,
            'role' => $role,
            'is_primary' => true,
        ]);

        InstructorProfile::create([
            'user_id' => $user->id,
            'title' => 'Instructor',
            'department_id' => $this->department()->id,
        ]);

        return $user;
    }

    private function makeOffering(User $coordinator, string $courseCode): CourseOffering
    {
        return CourseOffering::create([
            'course_id' => $this->makeCourse($courseCode)->id,
            'academic_year_id' => AcademicYear::create([
                'name' => '2569-'.$this->sequence++,
                'semester' => 1,
                'start_date' => '2026-08-01',
                'end_date' => '2026-12-31',
                'is_active' => true,
                'phase' => 'scheduling',
            ])->id,
            'coordinator_id' => $coordinator->id,
            'approval_status' => 'draft',
            'total_student_count' => 60,
            'teaching_weeks' => 15,
            'requires_practicum_rotation' => false,
        ]);
    }

    private function makeCourse(string $courseCode): Course
    {
        return Course::create([
            'course_code' => $courseCode,
            'curriculum_id' => Curriculum::firstOrCreate(['name' => 'Schedule Curriculum'], [
                'effective_year' => 2569,
                'is_active' => true,
            ])->id,
            'department_id' => $this->department()->id,
            'name_th' => $courseCode,
            'name_en' => $courseCode,
            'course_type' => 'theory_practicum',
            'default_year_level' => 2,
            'default_semester' => 1,
            'requires_practicum_rotation' => false,
            'credits' => 3,
            'lecture_hours' => 2,
            'lab_hours' => 1,
            'self_study_hours' => 3,
            'status' => 'active',
        ]);
    }

    private function makeGroup(CourseOffering $offering, string $code): StudentGroup
    {
        return StudentGroup::create([
            'course_offering_id' => $offering->id,
            'group_code' => $code,
            'student_count' => 20,
        ]);
    }

    private function makeRoom(string $code): Room
    {
        return Room::create([
            'room_code' => $code,
            'room_name' => "ห้อง {$code}",
            'location_type_id' => LocationType::firstOrCreate(['name' => 'Lecture'], ['requires_capacity' => true])->id,
            'capacity' => 60,
            'status' => 'active',
        ]);
    }

    private function makeSchedule(CourseOffering $offering, int $activityTypeId, ?int $roomId, array $overrides): Schedule
    {
        return Schedule::create([
            'course_offering_id' => $offering->id,
            'activity_type_id' => $activityTypeId,
            'room_id' => $roomId,
            'start_date' => $overrides['start_date'],
            'end_date' => $overrides['end_date'],
            'start_time' => $overrides['start_time'],
            'end_time' => $overrides['end_time'],
            'topic' => 'Existing schedule',
            'capacity_required' => 20,
            'status' => 'draft',
        ]);
    }

    private function createActivityType(): int
    {
        return DB::table('activity_types')->insertGetId([
            'name' => 'Lecture',
            'color_code' => '#2563eb',
            'category' => 'lecture',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function department(): Department
    {
        return Department::firstOrCreate(['name' => 'Nursing Department']);
    }
}
