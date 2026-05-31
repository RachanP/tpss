<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ActivityType;
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
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffPhaseOneTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 1;

    protected function setUp(): void
    {
        parent::setUp();

        config(['conflicts.async_reads' => false]);
    }

    public function test_staff_dashboard_loads_phase_one_summary_and_live_links(): void
    {
        [$staff, , $offering, $instructor, $group, $activityType, $room] = $this->makeReadyStaffOffering();
        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group]);

        $this->actingAsStaff($staff);

        $this->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('ภาพรวมระบบสำหรับเจ้าหน้าที่')
            ->assertSee('สรุปรายงานสำหรับ Phase 1')
            ->assertSee('Readiness ก่อนจัดตาราง')
            ->assertSee(route('staff.schedules.index'), false)
            ->assertSee(route('staff.dashboard') . '#staff-report-summary', false)
            ->assertDontSee('href="#"', false);
    }

    public function test_staff_dashboard_reads_scoped_conflict_summary_when_async_reads_are_enabled(): void
    {
        [$staff, , $offering, $instructor, $group, $activityType, $room] = $this->makeReadyStaffOffering();
        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group]);
        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
            'topic' => 'Overlapping staff schedule',
        ]);

        $this->artisan('conflicts:recompute', [
            '--academic-year' => $offering->academic_year_id,
            '--sync' => true,
        ])->assertExitCode(0);

        config(['conflicts.async_reads' => true]);
        $this->actingAsStaff($staff);

        $this->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('Conflict / Warning')
            ->assertSee(route('staff.dashboard') . '#staff-report-summary', false);
    }

    public function test_staff_course_update_preserves_instructor_pool_even_with_malicious_fields(): void
    {
        $staff = $this->makeUser('staff');
        $oldHead = $this->makeUser('course_head');
        $newHead = $this->makeUser('course_head');
        $oldInstructor = $this->makeUser('instructor');
        $maliciousInstructor = $this->makeUser('instructor');
        $course = $this->makeCourse($oldHead);
        $course->assignedStaff()->attach($staff->id);
        $course->instructors()->attach($oldInstructor->id);

        $this->actingAsStaff($staff);

        $this->put(route('staff.courses.update', $course), $this->coursePayload($course, [
            'name_th' => 'Staff updated course',
            'head_instructor_id' => $newHead->id,
            'staff_ids' => [$staff->id],
            'instructor_ids' => [$maliciousInstructor->id],
            'instructor_role_ids' => [
                $maliciousInstructor->id => null,
            ],
        ]))
            ->assertRedirect(route('staff.master_data', ['tab' => 'courses']))
            ->assertSessionHas('success');

        $course->refresh();
        $this->assertSame('Staff updated course', $course->name_th);
        $this->assertSame($newHead->id, $course->head_instructor_id);
        $this->assertDatabaseHas('course_instructors', [
            'course_id' => $course->id,
            'user_id' => $oldInstructor->id,
        ]);
        $this->assertDatabaseMissing('course_instructors', [
            'course_id' => $course->id,
            'user_id' => $maliciousInstructor->id,
        ]);
    }

    public function test_staff_schedule_scope_uses_course_staff_assignment(): void
    {
        [$staff, , $assignedOffering] = $this->makeReadyStaffOffering();
        [, , $unassignedOffering] = $this->makeReadyStaffOffering(null, 'scheduling', false);

        $this->actingAsStaff($staff);

        $this->get(route('staff.schedules.index'))
            ->assertRedirect(route('staff.course_offerings.schedules.index', [
                'courseOffering' => $assignedOffering->id,
            ]));

        $this->get(route('staff.course_offerings.schedules.index', $assignedOffering))
            ->assertOk()
            ->assertSee($assignedOffering->course->course_code)
            ->assertDontSee($unassignedOffering->course->course_code);

        $this->get(route('staff.course_offerings.schedules.index', $unassignedOffering))
            ->assertForbidden();
    }

    public function test_staff_can_write_schedule_only_during_scheduling_phase(): void
    {
        [$staff, , $offering, $instructor, $group, $activityType, $room] = $this->makeReadyStaffOffering();

        $this->actingAsStaff($staff);

        $this->post(route('staff.course_offerings.schedules.store', $offering), $this->schedulePayload(
            $instructor,
            $group,
            $activityType,
            $room,
            ['topic' => 'Staff scheduling item']
        ))
            ->assertRedirect(route('staff.course_offerings.schedules.index', $offering))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('schedules', [
            'course_offering_id' => $offering->id,
            'topic' => 'Staff scheduling item',
        ]);

        [, , $closedOffering, $closedInstructor, $closedGroup, $closedActivity, $closedRoom] = $this->makeReadyStaffOffering($staff, 'preparation');

        $this->post(route('staff.course_offerings.schedules.store', $closedOffering), $this->schedulePayload(
            $closedInstructor,
            $closedGroup,
            $closedActivity,
            $closedRoom,
            ['topic' => 'Blocked staff item']
        ))
            ->assertRedirect(route('staff.course_offerings.schedules.index', $closedOffering))
            ->assertSessionHasErrors('schedule');

        $this->assertDatabaseMissing('schedules', [
            'course_offering_id' => $closedOffering->id,
            'topic' => 'Blocked staff item',
        ]);
    }

    private function actingAsStaff(User $staff): void
    {
        $this->actingAs($staff);
        $this->withSession(['active_role' => 'staff']);
    }

    /**
     * @return array{User, User, CourseOffering, User, StudentGroup, ActivityType, Room}
     */
    private function makeReadyStaffOffering(?User $staff = null, string $phase = 'scheduling', bool $assignStaff = true): array
    {
        $staff ??= $this->makeUser('staff');
        $head = $this->makeUser('course_head');
        $instructor = $this->makeUser('instructor');
        $year = $this->makeYear($phase);
        $course = $this->makeCourse($head);

        if ($assignStaff) {
            $course->assignedStaff()->attach($staff->id);
        }

        $offering = CourseOffering::create([
            'course_id' => $course->id,
            'academic_year_id' => $year->id,
            'coordinator_id' => $head->id,
            'approval_status' => 'draft',
            'total_student_count' => 30,
        ]);

        $offering->instructorPool()->attach($instructor->id, ['role_in_course' => 'instructor']);

        $group = StudentGroup::create([
            'course_offering_id' => $offering->id,
            'group_code' => 'A' . $this->sequence++,
            'student_count' => 15,
        ]);

        return [$staff, $head, $offering, $instructor, $group, $this->makeActivityType(), $this->makeRoom()];
    }

    private function makeUser(string $role): User
    {
        $number = $this->sequence++;
        $user = User::create([
            'username' => "staff_phase_user_{$number}",
            'name' => "Staff Phase User {$number}",
            'email' => "staff_phase_user_{$number}@example.com",
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        UserRole::create(['user_id' => $user->id, 'role' => $role, 'is_primary' => true]);

        if (in_array($role, ['course_head', 'instructor'], true)) {
            InstructorProfile::create([
                'user_id' => $user->id,
                'title' => 'อาจารย์',
                'department_id' => $this->department()->id,
            ]);
        }

        return $user;
    }

    private function makeYear(string $phase): AcademicYear
    {
        $number = $this->sequence++;

        return AcademicYear::create([
            'name' => "2570-{$number}",
            'semester' => 1,
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
            'phase' => $phase,
        ]);
    }

    private function makeCourse(User $head): Course
    {
        $number = $this->sequence++;

        return Course::create([
            'course_code' => "STF{$number}",
            'curriculum_id' => $this->curriculum()->id,
            'department_id' => $this->department()->id,
            'head_instructor_id' => $head->id,
            'name_th' => "Staff Course {$number}",
            'name_en' => "Staff Course {$number}",
            'course_type' => 'theory_practicum',
            'default_year_level' => 2,
            'default_semester' => 1,
            'requires_practicum_rotation' => false,
            'is_required' => true,
            'credits' => 3,
            'lecture_hours' => 2,
            'lab_hours' => 1,
            'self_study_hours' => 3,
            'capacity' => 60,
            'color_code' => '#3b82f6',
            'status' => 'active',
        ]);
    }

    private function coursePayload(Course $course, array $overrides = []): array
    {
        return array_merge([
            '_form' => 'course',
            '_course_form_mode' => 'edit',
            '_course_route_key' => $course->course_code,
            '_course_id' => $course->id,
            'course_code' => $course->course_code,
            'name_th' => $course->name_th,
            'name_en' => $course->name_en,
            'curriculum_id' => $course->curriculum_id,
            'department_id' => $course->department_id,
            'head_instructor_id' => $course->head_instructor_id,
            'course_type' => $course->course_type,
            'default_year_level' => $course->default_year_level,
            'default_semester' => $course->default_semester,
            'credits' => $course->credits,
            'lecture_hours' => $course->lecture_hours,
            'lab_hours' => $course->lab_hours,
            'self_study_hours' => $course->self_study_hours,
            'capacity' => $course->capacity,
            'color_code' => $course->color_code ?? '#3b82f6',
            'status' => $course->status,
            'requires_practicum_rotation' => $course->requires_practicum_rotation ? '1' : '0',
            'is_required' => $course->is_required ? '1' : '0',
            'prerequisite_ids' => [],
            'staff_ids' => $course->assignedStaff()->pluck('users.id')->all(),
            'instructor_ids' => [],
            'instructor_role_ids' => [],
        ], $overrides);
    }

    private function schedulePayload(
        User $instructor,
        StudentGroup $group,
        ActivityType $activityType,
        Room $room,
        array $overrides = []
    ): array {
        return array_merge([
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'activity_type_id' => $activityType->id,
            'room_id' => $room->id,
            'topic' => 'Staff schedule topic',
            'capacity_required' => 15,
            'sub_group_label' => null,
            'remark' => null,
            'instructor_ids' => [$instructor->id],
            'lead_instructor_id' => $instructor->id,
            'student_group_ids' => [$group->id],
        ], $overrides);
    }

    /**
     * @param  array<int, User>  $instructors
     * @param  array<int, StudentGroup>  $groups
     */
    private function makeSchedule(
        CourseOffering $offering,
        ActivityType $activityType,
        Room $room,
        array $instructors,
        array $groups,
        array $overrides = []
    ): Schedule {
        $schedule = Schedule::create(array_merge([
            'course_offering_id' => $offering->id,
            'activity_type_id' => $activityType->id,
            'room_id' => $room->id,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'topic' => 'Staff existing schedule',
            'status' => 'draft',
        ], $overrides));

        $schedule->instructors()->sync(collect($instructors)->mapWithKeys(fn (User $user) => [
            $user->id => ['is_lead' => false],
        ])->all());
        $schedule->studentGroups()->sync(collect($groups)->pluck('id')->all());

        return $schedule;
    }

    private function makeActivityType(): ActivityType
    {
        $number = $this->sequence++;

        return ActivityType::create([
            'name' => "Staff Lecture {$number}",
            'color_code' => '#2563eb',
            'category' => 'lecture',
        ]);
    }

    private function makeRoom(): Room
    {
        $number = $this->sequence++;

        return Room::create([
            'room_code' => "SR{$number}",
            'room_name' => "Staff Room {$number}",
            'location_type_id' => LocationType::firstOrCreate(['name' => 'ห้องเรียน'])->id,
            'status' => 'active',
            'capacity' => 40,
        ]);
    }

    private function department(): Department
    {
        return Department::firstOrCreate(['name' => 'Staff Phase Department']);
    }

    private function curriculum(): Curriculum
    {
        return Curriculum::firstOrCreate(['name' => 'Staff Phase Curriculum'], [
            'effective_year' => 2569,
            'is_active' => true,
        ]);
    }
}
