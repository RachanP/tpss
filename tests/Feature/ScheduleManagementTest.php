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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ScheduleManagementTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 1;

    public function test_course_head_can_view_schedule_list_for_own_active_offering(): void
    {
        $head = $this->makeUser('course_head');
        $offering = $this->makeOffering($head);

        $this->actingAsCourseHead($head);

        $this->get(route('maker.course_offerings.schedules.index', $offering))
            ->assertOk()
            ->assertSee('ตารางสอน')
            ->assertSee('ยังไม่มีรายการตารางสอน');
    }

    public function test_course_head_cannot_access_unrelated_offering_schedule_list(): void
    {
        $head = $this->makeUser('course_head');
        $otherHead = $this->makeUser('course_head');
        $offering = $this->makeOffering($otherHead);

        $this->actingAsCourseHead($head);

        $this->get(route('maker.course_offerings.schedules.index', $offering))
            ->assertForbidden();
    }

    public function test_course_head_can_open_create_schedule_page_for_active_offering(): void
    {
        $head = $this->makeUser('course_head');
        $offering = $this->makeOffering($head);
        $instructor = $this->makeUser('instructor');
        $group = $this->makeStudentGroup($offering, 'A1', 30);

        $this->attachInstructor($offering, $instructor);
        $this->activityType();
        $this->room();

        $this->actingAsCourseHead($head);

        $this->get(route('maker.course_offerings.schedules.create', $offering))
            ->assertOk()
            ->assertSee('เพิ่มรายการสอน')
            ->assertSee($instructor->formatted_name)
            ->assertSee($group->group_code);
    }

    public function test_archived_offering_cannot_open_create_or_store_schedule(): void
    {
        $head = $this->makeUser('course_head');
        $offering = $this->makeOffering($head, ['status' => 'archived']);
        $instructor = $this->makeUser('instructor');
        $group = $this->makeStudentGroup($offering, 'A1', 20);
        $activityType = $this->activityType();

        $this->attachInstructor($offering, $instructor);

        $this->actingAsCourseHead($head);

        $this->get(route('maker.course_offerings.schedules.create', $offering))
            ->assertRedirect(route('maker.course_offerings.schedules.index', $offering))
            ->assertSessionHasErrors('schedule');

        $this->post(route('maker.course_offerings.schedules.store', $offering), [
            'teaching_date' => '2026-08-01',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'activity_type_id' => $activityType->id,
            'instructor_ids' => [$instructor->id],
            'student_group_ids' => [$group->id],
        ])
            ->assertRedirect(route('maker.course_offerings.schedules.index', $offering))
            ->assertSessionHasErrors('schedule');

        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_course_head_can_create_manual_schedule_with_valid_pool_and_groups(): void
    {
        $head = $this->makeUser('course_head');
        $offering = $this->makeOffering($head);
        $instructor = $this->makeUser('instructor');
        $group = $this->makeStudentGroup($offering, 'A1', 30);
        $activityType = $this->activityType();
        $room = $this->room();

        $this->attachInstructor($offering, $instructor);
        $this->actingAsCourseHead($head);

        $response = $this->post(route('maker.course_offerings.schedules.store', $offering), [
            'teaching_date' => '2026-08-01',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'activity_type_id' => $activityType->id,
            'room_id' => $room->id,
            'topic' => 'บทนำการพยาบาล',
            'remark' => 'เตรียมเอกสารประกอบ',
            'capacity_required' => 30,
            'sub_group_label' => 'A',
            'instructor_ids' => [$instructor->id],
            'student_group_ids' => [$group->id],
        ]);

        $response->assertRedirect(route('maker.course_offerings.schedules.index', $offering));

        $schedule = Schedule::firstOrFail();

        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'course_offering_id' => $offering->id,
            'activity_type_id' => $activityType->id,
            'room_id' => $room->id,
            'status' => 'draft',
            'topic' => 'บทนำการพยาบาล',
            'practicum_series_id' => null,
        ]);

        $this->assertDatabaseHas('schedule_instructors', [
            'schedule_id' => $schedule->id,
            'user_id' => $instructor->id,
            'is_lead' => false,
        ]);

        $this->assertDatabaseHas('schedule_student_groups', [
            'schedule_id' => $schedule->id,
            'student_group_id' => $group->id,
        ]);

        $this->assertSame(0, DB::table('schedule_conflicts')->count());
    }

    public function test_selected_instructor_must_belong_to_offering_instructor_pool(): void
    {
        $head = $this->makeUser('course_head');
        $offering = $this->makeOffering($head);
        $outsideInstructor = $this->makeUser('instructor');
        $group = $this->makeStudentGroup($offering, 'A1', 30);
        $activityType = $this->activityType();

        $this->actingAsCourseHead($head);

        $this->from(route('maker.course_offerings.schedules.create', $offering))
            ->post(route('maker.course_offerings.schedules.store', $offering), [
                'teaching_date' => '2026-08-01',
                'start_time' => '09:00',
                'end_time' => '11:00',
                'activity_type_id' => $activityType->id,
                'instructor_ids' => [$outsideInstructor->id],
                'student_group_ids' => [$group->id],
            ])
            ->assertSessionHasErrors('instructor_ids.0');
    }

    public function test_selected_student_group_must_belong_to_offering(): void
    {
        $head = $this->makeUser('course_head');
        $offering = $this->makeOffering($head);
        $otherOffering = $this->makeOffering($head);
        $instructor = $this->makeUser('instructor');
        $outsideGroup = $this->makeStudentGroup($otherOffering, 'B1', 25);
        $activityType = $this->activityType();

        $this->attachInstructor($offering, $instructor);
        $this->actingAsCourseHead($head);

        $this->from(route('maker.course_offerings.schedules.create', $offering))
            ->post(route('maker.course_offerings.schedules.store', $offering), [
                'teaching_date' => '2026-08-01',
                'start_time' => '09:00',
                'end_time' => '11:00',
                'activity_type_id' => $activityType->id,
                'instructor_ids' => [$instructor->id],
                'student_group_ids' => [$outsideGroup->id],
            ])
            ->assertSessionHasErrors('student_group_ids.0');
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        $head = $this->makeUser('course_head');
        $offering = $this->makeOffering($head);
        $instructor = $this->makeUser('instructor');
        $group = $this->makeStudentGroup($offering, 'A1', 30);
        $activityType = $this->activityType();

        $this->attachInstructor($offering, $instructor);
        $this->actingAsCourseHead($head);

        $this->from(route('maker.course_offerings.schedules.create', $offering))
            ->post(route('maker.course_offerings.schedules.store', $offering), [
                'teaching_date' => '2026-08-01',
                'start_time' => '11:00',
                'end_time' => '09:00',
                'activity_type_id' => $activityType->id,
                'instructor_ids' => [$instructor->id],
                'student_group_ids' => [$group->id],
            ])
            ->assertSessionHasErrors('end_time');
    }

    private function actingAsCourseHead(User $user): void
    {
        $this->actingAs($user);
        $this->withSession(['active_role' => 'course_head']);
    }

    private function makeUser(string $role, bool $active = true): User
    {
        $number = $this->sequence++;

        $user = User::create([
            'username' => "schedule_user{$number}",
            'name' => "Schedule User {$number}",
            'email' => "schedule_user{$number}@example.com",
            'password' => Hash::make('password'),
            'is_active' => $active,
        ]);

        UserRole::create([
            'user_id' => $user->id,
            'role' => $role,
            'is_primary' => true,
        ]);

        InstructorProfile::create([
            'user_id' => $user->id,
            'title' => 'อาจารย์',
            'department_id' => $this->department()->id,
        ]);

        return $user;
    }

    private function makeOffering(User $coordinator, array $overrides = []): CourseOffering
    {
        $number = $this->sequence++;
        $course = $this->makeCourse($number);

        return CourseOffering::create([
            'course_id' => $course->id,
            'academic_year_id' => $this->academicYear($number)->id,
            'coordinator_id' => $coordinator->id,
            'approval_status' => 'draft',
            'status' => $overrides['status'] ?? 'active',
            'total_student_count' => $overrides['total_student_count'] ?? 60,
            'planned_lecture_hours' => 2,
            'planned_lab_hours' => 1,
            'planned_practicum_hours' => 0,
            'teaching_weeks' => 15,
            'requires_practicum_rotation' => false,
        ]);
    }

    private function makeCourse(int $number): Course
    {
        return Course::create([
            'course_code' => "SCH{$number}",
            'curriculum_id' => $this->curriculum()->id,
            'department_id' => $this->department()->id,
            'name_th' => "Schedule Course {$number}",
            'name_en' => "Schedule Course {$number}",
            'course_type' => 'theory_practicum',
            'academic_level' => 'undergraduate',
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

    private function makeStudentGroup(CourseOffering $offering, string $code, int $count): StudentGroup
    {
        return StudentGroup::create([
            'course_offering_id' => $offering->id,
            'group_code' => $code,
            'student_count' => $count,
            'color_code' => '#2563eb',
        ]);
    }

    private function attachInstructor(CourseOffering $offering, User $instructor): void
    {
        DB::table('course_offering_instructors')->insert([
            'course_offering_id' => $offering->id,
            'user_id' => $instructor->id,
            'role_in_course' => 'instructor',
        ]);
    }

    private function activityType(): ActivityType
    {
        return ActivityType::firstOrCreate([
            'name' => 'บรรยาย',
        ], [
            'color_code' => '#2563eb',
            'category' => 'lecture',
        ]);
    }

    private function room(): Room
    {
        return Room::firstOrCreate([
            'room_code' => 'R-SCH-101',
        ], [
            'room_name' => 'ห้องเรียนทดสอบ',
            'building' => 'อาคารทดสอบ',
            'capacity' => 80,
            'location_type_id' => $this->locationType()->id,
            'status' => 'active',
        ]);
    }

    private function locationType(): LocationType
    {
        return LocationType::firstOrCreate(['name' => 'ห้องเรียนทั่วไป']);
    }

    private function department(): Department
    {
        return Department::firstOrCreate(['name' => 'Schedule Department']);
    }

    private function curriculum(): Curriculum
    {
        return Curriculum::firstOrCreate([
            'name' => 'Schedule Curriculum',
        ], [
            'effective_year' => 2569,
            'is_active' => true,
        ]);
    }

    private function academicYear(int $number): AcademicYear
    {
        return AcademicYear::create([
            'name' => "2569-SCH-{$number}",
            'semester' => 1,
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);
    }
}
