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

    public function test_open_sets_phase_and_creates_offerings_for_active_courses(): void
    {
        $admin = $this->makeAdmin();
        $head  = $this->makeInstructor();
        $year  = $this->makeYear(['semester' => 1, 'phase' => 'preparation']);
        $course = $this->makeCourse(['default_semester' => 1, 'head_instructor_id' => $head->id]);

        $this->seedCriticalsBaseline();
        $this->actingAsAdmin($admin);

        $this->patch(route('admin.settings.scheduling.open', $year))
            ->assertRedirect(route('admin.settings', ['tab' => 'academic']))
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

    public function test_open_closes_other_scheduling_windows(): void
    {
        $admin = $this->makeAdmin();
        $head  = $this->makeInstructor();
        $targetYear = $this->makeYear(['name' => '2569', 'semester' => 2, 'is_active' => true, 'phase' => 'preparation']);
        $otherSchedulingYear = $this->makeYear(['name' => '2569', 'semester' => 1, 'is_active' => false, 'phase' => 'scheduling']);
        $publishedYear = $this->makeYear(['name' => '2568', 'semester' => 2, 'is_active' => false, 'phase' => 'published']);
        $this->makeCourse(['head_instructor_id' => $head->id]);

        $this->seedCriticalsBaseline();
        $this->actingAsAdmin($admin);

        $this->patch(route('admin.settings.scheduling.open', $targetYear))
            ->assertRedirect(route('admin.settings', ['tab' => 'academic']))
            ->assertSessionHas('success');

        $this->assertSame('scheduling', $targetYear->fresh()->phase);
        $this->assertSame('preparation', $otherSchedulingYear->fresh()->phase);
        $this->assertSame('published', $publishedYear->fresh()->phase);
    }

    public function test_open_creates_offerings_for_all_active_courses_regardless_of_semester(): void
    {
        // After M2 hardening, the openSchedulingWindow no longer filters by
        // default_semester — course.status is the source of truth.
        $admin = $this->makeAdmin();
        $head  = $this->makeInstructor();
        $year  = $this->makeYear(['semester' => 1, 'phase' => 'preparation']);
        $sem1Course = $this->makeCourse(['default_semester' => 1, 'head_instructor_id' => $head->id]);
        $sem2Course = $this->makeCourse(['default_semester' => 2, 'head_instructor_id' => $head->id]);
        // Inactive course → skipped entirely (status filter).
        $this->makeCourse(['default_semester' => 1, 'head_instructor_id' => $head->id, 'status' => 'inactive']);

        $this->seedCriticalsBaseline();
        $this->actingAsAdmin($admin);
        $this->patch(route('admin.settings.scheduling.open', $year));

        $this->assertDatabaseCount('course_offerings', 2);
        $this->assertDatabaseHas('course_offerings', ['course_id' => $sem1Course->id, 'academic_year_id' => $year->id]);
        $this->assertDatabaseHas('course_offerings', ['course_id' => $sem2Course->id, 'academic_year_id' => $year->id]);
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

        $this->seedCriticalsBaseline();
        $this->actingAsAdmin($admin);
        $this->patch(route('admin.settings.scheduling.open', $year));

        $this->assertDatabaseCount('course_offerings', 1);
    }

    public function test_open_blocked_when_criticals_exist(): void
    {
        // No ActivityType / LocationType → critical gate should block opening.
        $admin = $this->makeAdmin();
        $head  = $this->makeInstructor();
        $year  = $this->makeYear(['phase' => 'preparation']);
        $this->makeCourse(['head_instructor_id' => $head->id]);

        $this->actingAsAdmin($admin);

        $this->patch(route('admin.settings.scheduling.open', $year))
            ->assertRedirect(route('admin.settings', ['tab' => 'academic']))
            ->assertSessionHas('error');

        $this->assertSame('preparation', $year->fresh()->phase);
        $this->assertDatabaseCount('course_offerings', 0);
    }

    public function test_open_blocked_when_active_course_missing_head_instructor(): void
    {
        $admin = $this->makeAdmin();
        $year  = $this->makeYear(['phase' => 'preparation']);
        $this->makeCourse(['head_instructor_id' => null]); // active but headless

        $this->seedCriticalsBaseline();
        $this->actingAsAdmin($admin);

        $this->patch(route('admin.settings.scheduling.open', $year))
            ->assertSessionHas('error');

        $this->assertSame('preparation', $year->fresh()->phase);
    }

    public function test_open_blocked_for_inactive_year(): void
    {
        $admin = $this->makeAdmin();
        $year  = $this->makeYear(['is_active' => false, 'phase' => 'preparation']);

        $this->actingAsAdmin($admin);

        $this->patch(route('admin.settings.scheduling.open', $year))
            ->assertRedirect(route('admin.settings', ['tab' => 'academic']))
            ->assertSessionHas('error');

        $this->assertSame('preparation', $year->fresh()->phase);
    }

    public function test_open_blocked_when_year_is_already_published(): void
    {
        $admin = $this->makeAdmin();
        $year  = $this->makeYear(['phase' => 'published']);

        $this->actingAsAdmin($admin);

        $this->patch(route('admin.settings.scheduling.open', $year))
            ->assertRedirect(route('admin.settings', ['tab' => 'academic']))
            ->assertSessionHas('error');

        $this->assertSame('published', $year->fresh()->phase);
    }

    public function test_settings_page_shows_scheduling_action_only_for_current_year(): void
    {
        $admin = $this->makeAdmin();
        $currentYear = $this->makeYear(['name' => '2570', 'semester' => 1, 'is_active' => true]);
        $otherYear = $this->makeYear(['name' => '2569', 'semester' => 2, 'is_active' => false]);

        $this->seedCriticalsBaseline();
        $this->actingAsAdmin($admin);

        $response = $this->get(route('admin.settings', ['tab' => 'academic']));

        $response
            ->assertOk()
            ->assertSee('open-scheduling-' . $currentYear->id, false)
            ->assertDontSee('open-scheduling-' . $otherYear->id, false)
            ->assertSee('ตั้งเป็นปีปัจจุบันก่อน');
    }

    public function test_setting_another_current_year_is_blocked_while_scheduling_window_is_open(): void
    {
        $admin = $this->makeAdmin();
        $openYear = $this->makeYear([
            'name' => '2569',
            'semester' => 1,
            'is_active' => true,
            'phase' => 'scheduling',
        ]);
        $targetYear = $this->makeYear([
            'name' => '2569',
            'semester' => 2,
            'is_active' => false,
            'phase' => 'preparation',
            'start_date' => '2026-11-01',
            'end_date' => '2027-03-15',
        ]);

        $this->actingAsAdmin($admin);

        $this->put(route('admin.settings.years.update', $targetYear), [
            'year_id' => $targetYear->id,
            'name' => $targetYear->name,
            'semester' => $targetYear->semester,
            'start_date' => '01/11/2569',
            'end_date' => '15/03/2570',
            'is_active' => '1',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('is_active')
            ->assertSessionHas('error');

        $this->assertTrue((bool) $openYear->fresh()->is_active);
        $this->assertSame('scheduling', $openYear->fresh()->phase);
        $this->assertFalse((bool) $targetYear->fresh()->is_active);
    }

    // ── Admin: Close Scheduling Window ───────────────────────────────

    public function test_setting_current_year_closes_previous_scheduling_window(): void
    {
        $admin = $this->makeAdmin();
        $currentYear = $this->makeYear(['name' => '2569', 'semester' => 1, 'is_active' => true, 'phase' => 'scheduling']);
        $nextYear = $this->makeYear([
            'name' => '2569',
            'semester' => 2,
            'start_date' => '2026-11-01',
            'end_date' => '2027-03-15',
            'is_active' => false,
            'phase' => 'preparation',
        ]);

        $this->actingAsAdmin($admin);

        $this->put(route('admin.settings.years.update', $nextYear), [
            'name' => '2569',
            'semester' => 2,
            'start_date' => '01/11/2569',
            'end_date' => '15/03/2570',
            'is_active' => '1',
        ])->assertRedirect(route('admin.settings', ['tab' => 'academic']))
            ->assertSessionHas('success');

        $this->assertFalse((bool) $currentYear->fresh()->is_active);
        $this->assertSame('preparation', $currentYear->fresh()->phase);
        $this->assertTrue((bool) $nextYear->fresh()->is_active);
        $this->assertSame('preparation', $nextYear->fresh()->phase);
    }

    public function test_close_reverts_phase_to_preparation(): void
    {
        $admin = $this->makeAdmin();
        $year  = $this->makeYear(['phase' => 'scheduling']);

        $this->actingAsAdmin($admin);

        $this->patch(route('admin.settings.scheduling.close', $year))
            ->assertRedirect(route('admin.settings', ['tab' => 'academic']))
            ->assertSessionHas('success');

        $this->assertSame('preparation', $year->fresh()->phase);
    }

    public function test_settings_page_uses_modal_confirmation_for_closing_scheduling_window(): void
    {
        $admin = $this->makeAdmin();
        $year = $this->makeYear([
            'name' => '2568',
            'semester' => 2,
            'is_active' => true,
            'phase' => 'scheduling',
        ]);

        $this->actingAsAdmin($admin);

        $response = $this->get(route('admin.settings', ['tab' => 'academic']));
        $html = $response->getContent();

        $response
            ->assertOk()
            ->assertSee('close-scheduling-' . $year->id, false)
            ->assertSee("startCloseScheduleConfirm('close-scheduling-{$year->id}', 'ปีการศึกษา 2568 ภาค 2')", false)
            ->assertSee('ยืนยันปิดช่วงจัดตาราง')
            ->assertSee('ข้อมูลตารางที่จัดไว้แล้วจะยังอยู่ ระบบจะปิดเฉพาะสิทธิ์การจัด/แก้ไขตารางชั่วคราว')
            ->assertSee('หัวหน้าวิชาจะไม่สามารถจัดหรือแก้ไขตารางในรอบนี้ต่อได้')
            ->assertSee('closeScheduleCountdown')
            ->assertSee('รอ ', false)
            ->assertSee('พร้อมยืนยันปิดช่วงจัดตาราง')
            ->assertSee(':disabled="closeScheduleCountdown > 0"', false);

        $this->assertStringNotContainsString('onclick="return confirm', $html);
        $this->assertMatchesRegularExpression(
            '/<button[^>]*type="button"[^>]*@click="startCloseScheduleConfirm/s',
            $html,
        );
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
                'requires_practicum_rotation' => 1,
                'practicum_note'              => 'override note',
            ])
            ->assertRedirect(route('maker.course_offerings.show', $offering) . '#course-info')
            ->assertSessionHas('error');

        $fresh = $offering->fresh();
        $this->assertFalse((bool) $fresh->requires_practicum_rotation);
        $this->assertNull($fresh->practicum_note);
    }

    public function test_offering_info_update_allowed_during_scheduling(): void
    {
        // After M2 hardening, the update endpoint only writes requires_practicum_rotation
        // (+ a required practicum_note when overriding the course default).
        $head    = $this->makeCourseHead();
        $year    = $this->makeYear(['phase' => 'scheduling']);
        $offering = $this->makeOffering($head, $year);

        $this->actingAsCourseHead($head);

        $this->from(route('maker.course_offerings.show', $offering))
            ->put(route('maker.course_offerings.update', $offering), [
                'requires_practicum_rotation' => 1,
                'practicum_note'              => 'ใช้ simulation lab แทนการหมุนเวียน',
            ])
            ->assertRedirect(route('maker.course_offerings.show', $offering) . '#course-info')
            ->assertSessionHasNoErrors();

        $fresh = $offering->fresh();
        $this->assertTrue((bool) $fresh->requires_practicum_rotation);
        $this->assertSame('ใช้ simulation lab แทนการหมุนเวียน', $fresh->practicum_note);
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
            ->assertRedirect(route('maker.course_offerings.show', $offering) . '#instructors')
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
            ->assertRedirect(route('maker.course_offerings.show', $offering) . '#instructors')
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

        $this->from(route('maker.course_offerings.show', $offering))
            ->post(route('maker.course_offerings.student_groups.store', $offering), [
                'group_code'    => 'B1',
                'student_count' => 10,
            ])
            ->assertRedirect(route('maker.course_offerings.show', $offering) . '#student-groups')
            ->assertSessionHas('error');

        // update
        $this->from(route('maker.course_offerings.show', $offering))
            ->put(route('maker.course_offerings.student_groups.update', [$offering, $group]), [
                'group_code'    => 'A1',
                'student_count' => 30,
            ])
            ->assertRedirect(route('maker.course_offerings.show', $offering) . '#student-groups')
            ->assertSessionHas('error');

        $this->from(route('maker.course_offerings.show', $offering))
            ->delete(route('maker.course_offerings.student_groups.destroy', [$offering, $group]))
            ->assertRedirect(route('maker.course_offerings.show', $offering) . '#student-groups')
            ->assertSessionHas('error');

        $this->assertDatabaseCount('student_groups', 1);
        $this->assertDatabaseHas('student_groups', ['id' => $group->id, 'student_count' => 20]);
    }

    // Prerequisite + schedule guard tests removed: prerequisites moved to Master Data
    // (per-course, not per-offering), and schedule routes were removed in this branch
    // (M3 not yet implemented). See CoursePoolManagementTest for prerequisite coverage.

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

    /**
     * Ensure all baseline criticals are cleared so openSchedulingWindow is not blocked.
     * (no_activity_type, no_location_type — others are auto-created by other helpers.)
     */
    private function seedCriticalsBaseline(): void
    {
        ActivityType::firstOrCreate(['name' => 'Lecture'], [
            'color_code' => '#2563eb',
            'category'   => 'lecture',
        ]);
        LocationType::firstOrCreate(['name' => 'ห้องเรียน']);
        \App\Http\Controllers\Admin\AlertController::flushCache();
    }

    private function curriculum(): Curriculum
    {
        return Curriculum::firstOrCreate(['name' => 'Phase Test Curriculum'], [
            'effective_year' => 2569,
            'is_active'      => true,
        ]);
    }
}
