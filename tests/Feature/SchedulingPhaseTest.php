<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\InstructorProfile;
use App\Models\StudentGroup;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SchedulingPhaseTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 1;

    // ── Admin: Open Scheduling Window ────────────────────────────────

    public function test_open_sets_phase_and_creates_offerings_for_matching_active_courses(): void
    {
        $admin = $this->makeAdmin();
        $head  = $this->makeInstructor();
        $year  = $this->makeYear(['semester' => 1, 'phase' => 'preparation']);
        $course = $this->makeCourse(['default_semester' => 1, 'head_instructor_id' => $head->id]);

        $this->actingAsAdmin($admin);

        $this->patch(route('admin.settings.scheduling.open', $year))
            ->assertRedirect(route('admin.settings', ['tab' => 'scheduling']))
            ->assertSessionHas('success');

        $year->refresh();
        $this->assertSame('scheduling', $year->phase);
        $this->assertDatabaseHas('course_offerings', [
            'course_id'       => $course->id,
            'academic_year_id'=> $year->id,
            'coordinator_id'  => $head->id,
            'approval_status' => 'draft',
        ]);
    }

    public function test_open_skips_courses_with_wrong_semester_or_no_head(): void
    {
        $admin = $this->makeAdmin();
        $head  = $this->makeInstructor();
        $year  = $this->makeYear(['semester' => 1, 'phase' => 'preparation']);

        $this->makeCourse(['default_semester' => 1, 'head_instructor_id' => null]);
        $this->makeCourse(['default_semester' => 2, 'head_instructor_id' => $head->id]);

        $this->actingAsAdmin($admin);
        $this->patch(route('admin.settings.scheduling.open', $year));

        $this->assertDatabaseCount('course_offerings', 0);
    }

    public function test_open_is_idempotent_when_offering_already_exists(): void
    {
        $admin  = $this->makeAdmin();
        $head   = $this->makeInstructor();
        $year   = $this->makeYear(['semester' => 1, 'phase' => 'scheduling']);
        $course = $this->makeCourse(['default_semester' => 1, 'head_instructor_id' => $head->id]);

        CourseOffering::create([
            'course_id'        => $course->id,
            'academic_year_id' => $year->id,
            'coordinator_id'   => $head->id,
            'approval_status'  => 'draft',
        ]);

        $this->actingAsAdmin($admin);
        $this->patch(route('admin.settings.scheduling.open', $year));

        $this->assertDatabaseCount('course_offerings', 1);
    }

    public function test_open_blocked_for_inactive_year(): void
    {
        $admin = $this->makeAdmin();
        $year  = $this->makeYear(['is_active' => false, 'phase' => 'preparation']);

        $this->actingAsAdmin($admin);

        $this->patch(route('admin.settings.scheduling.open', $year))
            ->assertRedirect(route('admin.settings', ['tab' => 'scheduling']))
            ->assertSessionHas('error');

        $this->assertSame('preparation', $year->fresh()->phase);
    }

    public function test_open_blocked_when_year_is_already_published(): void
    {
        $admin = $this->makeAdmin();
        $year  = $this->makeYear(['phase' => 'published']);

        $this->actingAsAdmin($admin);

        $this->patch(route('admin.settings.scheduling.open', $year))
            ->assertRedirect(route('admin.settings', ['tab' => 'scheduling']))
            ->assertSessionHas('error');

        $this->assertSame('published', $year->fresh()->phase);
    }

    // ── Admin: Close Scheduling Window ───────────────────────────────

    public function test_close_reverts_phase_to_preparation(): void
    {
        $admin = $this->makeAdmin();
        $year  = $this->makeYear(['phase' => 'scheduling']);

        $this->actingAsAdmin($admin);

        $this->patch(route('admin.settings.scheduling.close', $year))
            ->assertRedirect(route('admin.settings', ['tab' => 'scheduling']))
            ->assertSessionHas('success');

        $this->assertSame('preparation', $year->fresh()->phase);
    }

    public function test_close_blocked_for_inactive_year(): void
    {
        $admin = $this->makeAdmin();
        $year  = $this->makeYear(['is_active' => false, 'phase' => 'scheduling']);

        $this->actingAsAdmin($admin);

        $this->patch(route('admin.settings.scheduling.close', $year))
            ->assertSessionHas('error');

        $this->assertSame('scheduling', $year->fresh()->phase);
    }

    public function test_close_blocked_when_not_in_scheduling_phase(): void
    {
        $admin = $this->makeAdmin();
        $year  = $this->makeYear(['phase' => 'preparation']);

        $this->actingAsAdmin($admin);

        $this->patch(route('admin.settings.scheduling.close', $year))
            ->assertSessionHas('error');

        $this->assertSame('preparation', $year->fresh()->phase);
    }

    // ── RBAC: non-admin cannot touch scheduling window ───────────────

    public function test_course_head_cannot_access_open_or_close_endpoints(): void
    {
        $head = $this->makeCourseHead();
        $year = $this->makeYear(['phase' => 'preparation']);

        $this->actingAs($head);
        $this->withSession(['active_role' => 'course_head']);

        $this->patch(route('admin.settings.scheduling.open',  $year))->assertForbidden();
        $this->patch(route('admin.settings.scheduling.close', $year))->assertForbidden();
    }

    // ── Phase Guard: CourseOffering info update ───────────────────────

    public function test_offering_info_update_blocked_during_preparation(): void
    {
        $head    = $this->makeCourseHead();
        $year    = $this->makeYear(['phase' => 'preparation']);
        $offering = $this->makeOffering($head, $year);

        $this->actingAsCourseHead($head);

        $this->from(route('maker.course_offerings.show', $offering))
            ->put(route('maker.course_offerings.update', $offering), [
                'total_student_count' => 100,
                'teaching_weeks'      => 15,
            ])
            ->assertRedirect(route('maker.course_offerings.show', $offering))
            ->assertSessionHas('error');

        $this->assertNull($offering->fresh()->total_student_count);
    }

    public function test_offering_info_update_allowed_during_scheduling(): void
    {
        $head    = $this->makeCourseHead();
        $year    = $this->makeYear(['phase' => 'scheduling']);
        $offering = $this->makeOffering($head, $year);

        $this->actingAsCourseHead($head);

        $this->from(route('maker.course_offerings.show', $offering))
            ->put(route('maker.course_offerings.update', $offering), [
                'total_student_count' => 80,
                'teaching_weeks'      => 15,
            ])
            ->assertRedirect(route('maker.course_offerings.show', $offering))
            ->assertSessionHasNoErrors();

        $this->assertSame(80, $offering->fresh()->total_student_count);
    }

    // ── Phase Guard: Instructor pool mutations ────────────────────────

    public function test_instructor_pool_mutations_blocked_during_preparation(): void
    {
        $head       = $this->makeCourseHead();
        $instructor = $this->makeInstructor();
        $year       = $this->makeYear(['phase' => 'preparation']);
        $offering   = $this->makeOffering($head, $year);

        $this->actingAsCourseHead($head);

        // add instructor
        $this->from(route('maker.course_offerings.show', $offering))
            ->post(route('maker.course_offerings.instructors.store', $offering), [
                'user_id' => $instructor->id,
            ])
            ->assertRedirect(route('maker.course_offerings.show', $offering))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('course_offering_instructors', [
            'course_offering_id' => $offering->id,
            'user_id'            => $instructor->id,
        ]);

        // attach manually, then try removing — still blocked
        DB::table('course_offering_instructors')->insert([
            'course_offering_id' => $offering->id,
            'user_id'            => $instructor->id,
            'role_in_course'     => 'instructor',
        ]);

        $this->from(route('maker.course_offerings.show', $offering))
            ->delete(route('maker.course_offerings.instructors.destroy', [$offering, $instructor]))
            ->assertRedirect(route('maker.course_offerings.show', $offering))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('course_offering_instructors', [
            'course_offering_id' => $offering->id,
            'user_id'            => $instructor->id,
        ]);
    }

    // ── Phase Guard: Student group mutations ──────────────────────────

    public function test_student_group_mutations_blocked_during_preparation(): void
    {
        $head    = $this->makeCourseHead();
        $year    = $this->makeYear(['phase' => 'preparation']);
        $offering = $this->makeOffering($head, $year);
        $group   = StudentGroup::create([
            'course_offering_id' => $offering->id,
            'group_code'         => 'A1',
            'student_count'      => 20,
        ]);

        $this->actingAsCourseHead($head);

        // store
        $this->from(route('maker.course_offerings.show', $offering))
            ->post(route('maker.course_offerings.student_groups.store', $offering), [
                'group_code'    => 'B1',
                'student_count' => 10,
            ])
            ->assertRedirect(route('maker.course_offerings.show', $offering))
            ->assertSessionHas('error');

        // update
        $this->from(route('maker.course_offerings.show', $offering))
            ->put(route('maker.course_offerings.student_groups.update', [$offering, $group]), [
                'group_code'    => 'A1',
                'student_count' => 30,
            ])
            ->assertRedirect(route('maker.course_offerings.show', $offering))
            ->assertSessionHas('error');

        // destroy
        $this->from(route('maker.course_offerings.show', $offering))
            ->delete(route('maker.course_offerings.student_groups.destroy', [$offering, $group]))
            ->assertRedirect(route('maker.course_offerings.show', $offering))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('student_groups', 1);
        $this->assertDatabaseHas('student_groups', ['id' => $group->id, 'student_count' => 20]);
    }

    // ── Phase Guard: Prerequisite mutations ───────────────────────────

    public function test_prerequisite_mutations_blocked_during_preparation(): void
    {
        $head        = $this->makeCourseHead();
        $year        = $this->makeYear(['phase' => 'preparation']);
        $offering    = $this->makeOffering($head, $year);
        $prereqCourse = $this->makeCourse();

        $this->actingAsCourseHead($head);

        // store
        $this->from(route('maker.course_offerings.show', $offering))
            ->post(route('maker.course_offerings.prerequisites.store', $offering), [
                'prerequisite_course_id' => $prereqCourse->id,
            ])
            ->assertRedirect(route('maker.course_offerings.show', $offering))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('course_prerequisites', [
            'course_id'             => $offering->course_id,
            'prerequisite_course_id'=> $prereqCourse->id,
        ]);

        // attach manually, then try removing — still blocked
        DB::table('course_prerequisites')->insert([
            'course_id'             => $offering->course_id,
            'prerequisite_course_id'=> $prereqCourse->id,
        ]);

        $this->from(route('maker.course_offerings.show', $offering))
            ->delete(route('maker.course_offerings.prerequisites.destroy', [$offering, $prereqCourse]))
            ->assertRedirect(route('maker.course_offerings.show', $offering))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('course_prerequisites', [
            'course_id'              => $offering->course_id,
            'prerequisite_course_id' => $prereqCourse->id,
        ]);
    }

    // ── Phase Guard: Schedule create / store ─────────────────────────

    public function test_schedule_create_and_store_blocked_during_preparation(): void
    {
        $head    = $this->makeCourseHead();
        $year    = $this->makeYear(['phase' => 'preparation']);
        $offering = $this->makeOffering($head, $year);

        $this->actingAsCourseHead($head);

        $this->get(route('maker.course_offerings.schedules.create', $offering))
            ->assertRedirect(route('maker.course_offerings.schedules.index', $offering))
            ->assertSessionHasErrors('schedule');

        $this->post(route('maker.course_offerings.schedules.store', $offering), [
            'teaching_date'    => '2026-08-01',
            'start_time'       => '09:00',
            'end_time'         => '11:00',
            'activity_type_id' => 999,
            'instructor_ids'   => [],
        ])
            ->assertRedirect(route('maker.course_offerings.schedules.index', $offering))
            ->assertSessionHasErrors('schedule');

        $this->assertDatabaseCount('schedules', 0);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function actingAsAdmin(User $user): void
    {
        $this->actingAs($user);
        $this->withSession(['active_role' => 'admin']);
    }

    private function actingAsCourseHead(User $user): void
    {
        $this->actingAs($user);
        $this->withSession(['active_role' => 'course_head']);
    }

    private function makeAdmin(): User
    {
        $n    = $this->sequence++;
        $user = User::create([
            'username'  => "admin_{$n}",
            'name'      => "Admin {$n}",
            'email'     => "admin_{$n}@test.example",
            'password'  => Hash::make('password'),
            'is_active' => true,
        ]);
        UserRole::create(['user_id' => $user->id, 'role' => 'admin', 'is_primary' => true]);
        return $user;
    }

    private function makeCourseHead(): User
    {
        $n    = $this->sequence++;
        $user = User::create([
            'username'  => "head_{$n}",
            'name'      => "Head {$n}",
            'email'     => "head_{$n}@test.example",
            'password'  => Hash::make('password'),
            'is_active' => true,
        ]);
        UserRole::create(['user_id' => $user->id, 'role' => 'course_head', 'is_primary' => true]);
        InstructorProfile::create([
            'user_id'       => $user->id,
            'title'         => 'อาจารย์',
            'department_id' => $this->department()->id,
        ]);
        return $user;
    }

    private function makeInstructor(): User
    {
        $n    = $this->sequence++;
        $user = User::create([
            'username'  => "instr_{$n}",
            'name'      => "Instructor {$n}",
            'email'     => "instr_{$n}@test.example",
            'password'  => Hash::make('password'),
            'is_active' => true,
        ]);
        UserRole::create(['user_id' => $user->id, 'role' => 'instructor', 'is_primary' => true]);
        InstructorProfile::create([
            'user_id'       => $user->id,
            'title'         => 'อาจารย์',
            'department_id' => $this->department()->id,
        ]);
        return $user;
    }

    private function makeYear(array $overrides = []): AcademicYear
    {
        return AcademicYear::create(array_merge([
            'name'       => '2569',
            'semester'   => 1,
            'start_date' => '2026-08-01',
            'end_date'   => '2026-12-31',
            'is_active'  => true,
            'phase'      => 'preparation',
        ], $overrides));
    }

    private function makeCourse(array $overrides = []): Course
    {
        $n = $this->sequence++;
        return Course::create(array_merge([
            'course_code'              => "NUR{$n}",
            'curriculum_id'            => $this->curriculum()->id,
            'department_id'            => $this->department()->id,
            'name_th'                  => "วิชา {$n}",
            'name_en'                  => "Course {$n}",
            'course_type'              => 'theory',
            'academic_level'           => 'undergraduate',
            'default_year_level'       => 1,
            'default_semester'         => 1,
            'credits'                  => 3,
            'lecture_hours'            => 3,
            'lab_hours'                => 0,
            'self_study_hours'         => 6,
            'status'                   => 'active',
            'requires_practicum_rotation' => false,
        ], $overrides));
    }

    private function makeOffering(User $coordinator, AcademicYear $year): CourseOffering
    {
        $course = $this->makeCourse();
        return CourseOffering::create([
            'course_id'        => $course->id,
            'academic_year_id' => $year->id,
            'coordinator_id'   => $coordinator->id,
            'approval_status'  => 'draft',
        ]);
    }

    private function department(): Department
    {
        return Department::firstOrCreate(['name' => 'Phase Test Dept']);
    }

    private function curriculum(): Curriculum
    {
        return Curriculum::firstOrCreate(['name' => 'Phase Test Curriculum'], [
            'effective_year' => 2569,
            'is_active'      => true,
        ]);
    }
}
