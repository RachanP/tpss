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
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\CourseOfferingSeeder;
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
        $targetYear = $this->makeYear(['name' => '2569', 'is_active' => true, 'phase' => 'preparation']);
        $otherSchedulingYear = $this->makeYear(['name' => '2570', 'is_active' => false, 'phase' => 'scheduling']);
        $publishedYear = $this->makeYear(['name' => '2568', 'is_active' => false, 'phase' => 'published']);
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

    public function test_open_creates_offerings_for_active_courses_in_active_curriculum(): void
    {
        // V2: วิชาเปิดทั้งปี → สร้าง offering ให้ทุกวิชา active ใน active curriculum (เลิกกรองเทอม)
        $admin = $this->makeAdmin();
        $head  = $this->makeInstructor();
        $year  = $this->makeYear(['phase' => 'preparation']);
        $courseA = $this->makeCourse(['head_instructor_id' => $head->id]);
        $courseB = $this->makeCourse(['head_instructor_id' => $head->id]);
        $inactiveCourse = $this->makeCourse(['head_instructor_id' => $head->id, 'status' => 'inactive']);
        $inactiveCurriculum = Curriculum::create([
            'name' => 'Inactive Phase Curriculum',
            'effective_year' => 2569,
            'is_active' => false,
        ]);
        $inactiveCurriculumCourse = $this->makeCourse([
            'head_instructor_id' => $head->id,
            'curriculum_id' => $inactiveCurriculum->id,
        ]);

        $this->seedCriticalsBaseline();
        $this->actingAsAdmin($admin);
        $this->patch(route('admin.settings.scheduling.open', $year))
            ->assertRedirect(route('admin.settings', ['tab' => 'academic']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('course_offerings', ['course_id' => $courseA->id, 'academic_year_id' => $year->id]);
        $this->assertDatabaseHas('course_offerings', ['course_id' => $courseB->id, 'academic_year_id' => $year->id]);
        $this->assertDatabaseMissing('course_offerings', ['course_id' => $inactiveCourse->id, 'academic_year_id' => $year->id]);
        $this->assertDatabaseMissing('course_offerings', ['course_id' => $inactiveCurriculumCourse->id, 'academic_year_id' => $year->id]);
        $this->assertSame(2, CourseOffering::query()
            ->where('academic_year_id', $year->id)
            ->whereIn('course_id', [$courseA->id, $courseB->id, $inactiveCourse->id, $inactiveCurriculumCourse->id])
            ->count());
    }

    public function test_course_offering_seeder_creates_for_active_courses_in_active_curriculum(): void
    {
        $head = $this->makeInstructor();
        $activeYear = $this->makeYear(['name' => '2570', 'is_active' => true]);
        $courseA = $this->makeCourse(['head_instructor_id' => $head->id]);
        $courseB = $this->makeCourse(['head_instructor_id' => $head->id]);
        $headlessCourse = $this->makeCourse(['head_instructor_id' => null]);
        $inactiveCourse = $this->makeCourse(['head_instructor_id' => $head->id, 'status' => 'inactive']);
        $inactiveCurriculum = Curriculum::create([
            'name' => 'Inactive Seeder Curriculum',
            'effective_year' => 2570,
            'is_active' => false,
        ]);
        $inactiveCurriculumCourse = $this->makeCourse([
            'head_instructor_id' => $head->id,
            'curriculum_id' => $inactiveCurriculum->id,
        ]);

        $this->seed(CourseOfferingSeeder::class);

        $this->assertDatabaseHas('course_offerings', ['course_id' => $courseA->id, 'academic_year_id' => $activeYear->id]);
        $this->assertDatabaseHas('course_offerings', ['course_id' => $courseB->id, 'academic_year_id' => $activeYear->id]);
        $this->assertDatabaseMissing('course_offerings', ['course_id' => $headlessCourse->id]);
        $this->assertDatabaseMissing('course_offerings', ['course_id' => $inactiveCourse->id]);
        $this->assertDatabaseMissing('course_offerings', ['course_id' => $inactiveCurriculumCourse->id]);
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

    public function test_open_syncs_existing_offerings_for_active_courses(): void
    {
        // V2: เปิดช่วงจัดตาราง → sync offering ของวิชา active ทุกตัว (coordinator → หัวหน้าวิชาปัจจุบัน)
        $admin = $this->makeAdmin();
        $oldHead = $this->makeInstructor();
        $newHead = $this->makeInstructor();
        $year = $this->makeYear(['phase' => 'preparation']);
        $course = $this->makeCourse(['head_instructor_id' => $newHead->id]);
        $existingOffering = CourseOffering::create([
            'course_id' => $course->id,
            'academic_year_id' => $year->id,
            'coordinator_id' => $oldHead->id,
            'approval_status' => 'draft',
            'total_student_count' => 12,
        ]);

        $this->seedCriticalsBaseline();
        $this->actingAsAdmin($admin);
        $this->patch(route('admin.settings.scheduling.open', $year));

        // coordinator ถูก sync ไปเป็นหัวหน้าวิชาปัจจุบัน
        $this->assertSame($newHead->id, $existingOffering->fresh()->coordinator_id);
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
            'is_active' => true,
            'phase' => 'scheduling',
        ]);
        $targetYear = $this->makeYear([
            'name' => '2570',
            'is_active' => false,
            'phase' => 'preparation',
            'start_date' => '2026-11-02',
            'end_date' => '2027-03-15',
        ]);

        $this->actingAsAdmin($admin);

        $this->put(route('admin.settings.years.update', $targetYear), [
            'year_id' => $targetYear->id,
            'name' => $targetYear->name,
            'is_active' => '1',
            'terms' => [['sequence' => 1, 'name' => 'ภาคเรียนที่ 1', 'start_date' => '02/11/2569', 'end_date' => '15/03/2570']],
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('is_active')
            ->assertSessionHas('error');

        $this->assertTrue((bool) $openYear->fresh()->is_active);
        $this->assertSame('scheduling', $openYear->fresh()->phase);
        $this->assertFalse((bool) $targetYear->fresh()->is_active);
    }

    // ── Admin: Close Scheduling Window ───────────────────────────────

    public function test_setting_current_year_deactivates_previous_active_year(): void
    {
        // หลัง activation lock (22 พ.ค.) admin ต้องปิด scheduling window เก่าก่อนสลับปี active
        // เคสนี้คือสลับปี active ตอนปีเก่าอยู่ใน phase=preparation (ไม่มี scheduling lock)
        $admin = $this->makeAdmin();
        $currentYear = $this->makeYear(['name' => '2569', 'is_active' => true, 'phase' => 'preparation']);
        $nextYear = $this->makeYear([
            'name' => '2570',
            'start_date' => '2026-11-02',
            'end_date' => '2027-03-15',
            'is_active' => false,
            'phase' => 'preparation',
        ]);

        $this->actingAsAdmin($admin);

        $this->put(route('admin.settings.years.update', $nextYear), [
            'name' => '2570',
            'is_active' => '1',
            'terms' => [['sequence' => 1, 'name' => 'ภาคเรียนที่ 1', 'start_date' => '02/11/2569', 'end_date' => '15/03/2570']],
        ])->assertRedirect(route('admin.settings', ['tab' => 'academic']))
            ->assertSessionHas('success');

        $this->assertFalse((bool) $currentYear->fresh()->is_active);
        $this->assertSame('preparation', $currentYear->fresh()->phase);
        $this->assertTrue((bool) $nextYear->fresh()->is_active);
        $this->assertSame('preparation', $nextYear->fresh()->phase);
    }

    public function test_setting_another_current_year_is_blocked_even_when_year_id_not_in_request(): void
    {
        // Regression guard: activation lock ต้องบังคับใช้ผ่านทุก request shape
        // ไม่พึ่ง $request->filled('year_id') ที่อาจหายไปใน path อื่น
        $admin = $this->makeAdmin();
        $openYear = $this->makeYear([
            'name' => '2569', 'is_active' => true, 'phase' => 'scheduling',
        ]);
        $targetYear = $this->makeYear([
            'name' => '2570', 'is_active' => false, 'phase' => 'preparation',
            'start_date' => '2026-11-02', 'end_date' => '2027-03-15',
        ]);

        $this->actingAsAdmin($admin);

        // ส่ง PUT โดยไม่ใส่ year_id (จำลอง path อื่นที่ไม่ใช่ modal edit)
        $this->put(route('admin.settings.years.update', $targetYear), [
            'name' => $targetYear->name,
            'is_active' => '1',
            'terms' => [['sequence' => 1, 'name' => 'ภาคเรียนที่ 1', 'start_date' => '02/11/2569', 'end_date' => '15/03/2570']],
        ])
            ->assertSessionHasErrors('is_active');

        $this->assertTrue((bool) $openYear->fresh()->is_active);
        $this->assertSame('scheduling', $openYear->fresh()->phase);
        $this->assertFalse((bool) $targetYear->fresh()->is_active);
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
            'is_active' => true,
            'phase' => 'scheduling',
        ]);

        $this->actingAsAdmin($admin);

        $response = $this->get(route('admin.settings', ['tab' => 'academic']));
        $html = $response->getContent();

        $response
            ->assertOk()
            ->assertSee('close-scheduling-' . $year->id, false)
            ->assertSee("startCloseScheduleConfirm('close-scheduling-{$year->id}', 'ปีการศึกษา 2568')", false)
            ->assertSee('ยืนยันปิดช่วงจัดตาราง')
            ->assertSee('ข้อมูลตารางที่จัดไว้แล้วจะยังอยู่')
            ->assertSee('ระบบจะปิดเฉพาะสิทธิ์การจัด/แก้ไขตารางชั่วคราว')
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

    // หมายเหตุ: เดิมมี test_student_group_mutations_blocked_during_preparation
    // ถูกลบเพราะหัวหน้าวิชาไม่จัดกลุ่มย่อยแล้ว (V2 — กลุ่มเกิดหลังอนุมัติ โดยอาจารย์) routes ถูกถอด

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
        $year = AcademicYear::create(array_merge([
            'name'       => '2569',
            'semester'   => 1,
            'start_date' => '2026-08-03',
            'end_date'   => '2026-12-31',
            'is_active'  => true,
            'phase'      => 'preparation',
        ], $overrides));

        // V4 ข้อ 8: ปีต้องมีเทอมในปฏิทินค่าเริ่มต้น ไม่งั้นเปิดช่วงจัดตารางไม่ได้ (critical-gate)
        $year->fallbackCalendar()->terms()->create([
            'sequence' => 1, 'name' => 'ภาคเรียนที่ 1',
            'start_date' => $year->start_date ?: '2026-08-03',
            'end_date' => $year->end_date ?: '2026-12-31',
        ]);

        return $year;
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
