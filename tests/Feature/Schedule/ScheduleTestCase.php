<?php

namespace Tests\Feature\Schedule;

use App\Models\AcademicYear;
use App\Models\ActivityType;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\InstructorProfile;
use App\Models\LocationType;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ScheduleTemplate;
use App\Models\StudentCohort;
use App\Models\StudentGroup;
use App\Models\User;
use App\Models\UserRole;
use App\Jobs\ConflictRecomputeJob;
use App\Services\ScheduleConflictIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * Shared base สำหรับ Schedule feature suite — รวม setUp + helper สร้างข้อมูล
 * (เดิมอยู่ใน ScheduleManagementTest เดียว 99 tests → ซอยเป็น Views/Store/Conflict)
 */
abstract class ScheduleTestCase extends TestCase
{
    use RefreshDatabase;

    protected int $sequence = 1;

    protected function setUp(): void
    {
        parent::setUp();

        config(['conflicts.async_reads' => false]);
        Cache::flush();
    }

    protected function actingAsCourseHead(User $user): void
    {
        $this->actingAs($user);
        $this->withSession(['active_role' => 'course_head']);
    }

    /**
     * @return array{User, CourseOffering, User, StudentGroup, ActivityType, Room}
     */
    protected function makeReadyOffering(string $phase = 'scheduling'): array
    {
        $head = $this->makeUser('course_head');
        $instructor = $this->makeUser('instructor');
        $year = $this->makeYear($phase);
        $course = $this->makeCourse($head);
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
            'group_code' => 'A1',
            'student_count' => 15,
        ]);

        return [$head, $offering, $instructor, $group, $this->makeActivityType(), $this->makeRoom()];
    }

    protected function makeUser(string $role): User
    {
        $number = $this->sequence++;
        $user = User::create([
            'username' => "schedule_user_{$number}",
            'name' => "Schedule User {$number}",
            'email' => "schedule_user_{$number}@example.com",
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        UserRole::create(['user_id' => $user->id, 'role' => $role, 'is_primary' => true]);
        InstructorProfile::create([
            'user_id' => $user->id,
            'title' => 'อาจารย์',
            'department_id' => $this->department()->id,
        ]);

        return $user;
    }

    protected function makeYear(string $phase): AcademicYear
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

    protected function makeCourse(User $head): Course
    {
        $number = $this->sequence++;

        return Course::create([
            'course_code' => "SCH{$number}",
            'curriculum_id' => $this->curriculum()->id,
            'department_id' => $this->department()->id,
            'head_instructor_id' => $head->id,
            'name_th' => "Schedule Course {$number}",
            'name_en' => "Schedule Course {$number}",
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

    protected function makeActivityType(): ActivityType
    {
        $number = $this->sequence++;

        return ActivityType::create([
            'name' => "Lecture {$number}",
            'color_code' => '#2563eb',
            'category' => 'lecture',
        ]);
    }

    protected function makePracticumActivityType(): ActivityType
    {
        $number = $this->sequence++;

        return ActivityType::create([
            'name' => "Practicum {$number}",
            'color_code' => '#16a34a',
            'category' => 'practicum',
        ]);
    }

    protected function makeRoom(): Room
    {
        $number = $this->sequence++;

        return Room::create([
            'room_code' => "R{$number}",
            'room_name' => "Room {$number}",
            'location_type_id' => LocationType::firstOrCreate(['name' => 'ห้องเรียน'])->id,
            'status' => 'active',
        ]);
    }

    /**
     * @param  array<int, User>  $instructors
     * @param  array<int, StudentGroup>  $groups
     */
    protected function makeSchedule(
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
            'end_date' => '2026-08-07',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'topic' => 'Existing schedule',
            'status' => 'draft',
        ], $overrides));

        $schedule->instructors()->sync(collect($instructors)->mapWithKeys(fn (User $user) => [
            $user->id => ['is_lead' => false],
        ])->all());
        $schedule->studentGroups()->sync(collect($groups)->pluck('id')->all());

        return $schedule;
    }

    protected function schedulePayload(
        User $instructor,
        StudentGroup $group,
        ActivityType $activityType,
        Room $room,
        array $overrides = []
    ): array {
        return array_merge([
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-07',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'activity_type_id' => $activityType->id,
            'room_id' => $room->id,
            'topic' => 'Schedule topic',
            'capacity_required' => 15,
            'sub_group_label' => null,
            'remark' => null,
            'instructor_ids' => [$instructor->id],
            'lead_instructor_id' => $instructor->id,
            'student_group_ids' => [$group->id],
        ], $overrides);
    }

    protected function makeCohort(string $code): StudentCohort
    {
        return StudentCohort::create([
            'curriculum_id' => $this->curriculum()->id,
            'year_level' => 2,
            'code' => $code,
            'student_count' => 80,
        ]);
    }

    protected function department(): Department
    {
        return Department::firstOrCreate(['name' => 'Schedule Department']);
    }

    protected function curriculum(): Curriculum
    {
        return Curriculum::firstOrCreate(['name' => 'Schedule Curriculum'], [
            'effective_year' => 2569,
            'is_active' => true,
        ]);
    }
}
