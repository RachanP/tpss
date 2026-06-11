<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\InstructorProfile;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * V2 delegation: หัวหน้าวิชามอบหมายให้อาจารย์ช่วยจัดตาราง offering (schedule_permission='schedule')
 * - delegated instructor เข้าหน้าจัดตาราง slot ได้
 * - อาจารย์ที่ไม่ได้รับมอบหมาย / ไม่อยู่ในชุดผู้สอน เข้าไม่ได้ (403)
 * - อาจารย์แตะหน้า "จัดการ offering" (ชุดผู้สอน/อนุมัติ) ไม่ได้ — เป็นของหัวหน้าวิชา
 */
class ScheduleDelegationTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 1;

    protected function setUp(): void
    {
        parent::setUp();
        config(['conflicts.async_reads' => false]);
    }

    public function test_delegated_instructor_can_open_offering_schedules(): void
    {
        [$head, $offering, $instructor] = $this->makeOffering();
        $offering->instructorPool()->updateExistingPivot($instructor->id, ['schedule_permission' => 'schedule']);

        $this->actingAsRole($instructor, 'instructor');

        $this->get(route('maker.course_offerings.schedules.index', $offering))
            ->assertOk()
            ->assertSee($offering->course->course_code);
    }

    public function test_instructor_without_permission_cannot_open_offering_schedules(): void
    {
        [$head, $offering, $instructor] = $this->makeOffering(); // default schedule_permission = view

        $this->actingAsRole($instructor, 'instructor');

        $this->get(route('maker.course_offerings.schedules.index', $offering))
            ->assertForbidden();
    }

    public function test_instructor_not_in_pool_cannot_open_offering_schedules(): void
    {
        [$head, $offering] = $this->makeOffering();
        $outsider = $this->makeUser('instructor');

        $this->actingAsRole($outsider, 'instructor');

        $this->get(route('maker.course_offerings.schedules.index', $offering))
            ->assertForbidden();
    }

    public function test_instructor_cannot_access_offering_management_pages(): void
    {
        [$head, $offering, $instructor] = $this->makeOffering();
        $offering->instructorPool()->updateExistingPivot($instructor->id, ['schedule_permission' => 'schedule']);

        $this->actingAsRole($instructor, 'instructor');

        // จัดการ offering = course_head เท่านั้น → CheckRole ปฏิเสธ instructor แม้ถูกมอบหมายให้จัดตาราง
        $this->get(route('maker.course_offerings.show', $offering))->assertForbidden();
        $this->get(route('maker.course_offerings.index'))->assertForbidden();
    }

    public function test_delegated_offering_appears_in_instructor_workspace(): void
    {
        [$head, $offering, $instructor] = $this->makeOffering();
        $offering->instructorPool()->updateExistingPivot($instructor->id, ['schedule_permission' => 'schedule']);

        $this->actingAsRole($instructor, 'instructor');

        // workspace redirect ไปหน้าวิชาแรกที่จัดได้ (เหมือนหัวหน้าวิชา) แล้วเห็นรหัสวิชา
        $this->followingRedirects()
            ->get(route('maker.schedules.index'))
            ->assertOk()
            ->assertSee($offering->course->course_code);
    }

    public function test_workspace_does_not_show_offering_to_non_delegated_instructor(): void
    {
        [$head, $offering, $instructor] = $this->makeOffering(); // permission = view

        $this->actingAsRole($instructor, 'instructor');

        $this->get(route('maker.schedules.index'))
            ->assertOk()
            ->assertDontSee($offering->course->course_code);
    }

    public function test_course_head_can_grant_and_revoke_schedule_permission(): void
    {
        [$head, $offering, $instructor] = $this->makeOffering();

        $this->actingAsRole($head, 'course_head');

        $this->patch(route('maker.course_offerings.instructors.permission', [$offering, $instructor]), [
            'schedule_permission' => 'schedule',
        ])->assertSessionHasNoErrors();

        $this->assertSame('schedule', $offering->fresh()->instructorPool()
            ->where('users.id', $instructor->id)->first()->pivot->schedule_permission);

        $this->patch(route('maker.course_offerings.instructors.permission', [$offering, $instructor]), [
            'schedule_permission' => 'view',
        ])->assertSessionHasNoErrors();

        $this->assertSame('view', $offering->fresh()->instructorPool()
            ->where('users.id', $instructor->id)->first()->pivot->schedule_permission);
    }

    public function test_cannot_delegate_schedule_permission_to_coordinator(): void
    {
        [$head, $offering] = $this->makeOffering();
        // coordinator อยู่ใน pool อยู่แล้ว (attachCoordinator) — มอบหมายซ้ำไม่ได้
        $offering->attachCoordinator();

        $this->actingAsRole($head, 'course_head');

        $this->patch(route('maker.course_offerings.instructors.permission', [$offering, $head]), [
            'schedule_permission' => 'schedule',
        ])->assertSessionHasErrors('instructor_pool');
    }

    public function test_instructor_cannot_toggle_permission(): void
    {
        [$head, $offering, $instructor] = $this->makeOffering();
        $other = $this->makeUser('instructor');
        $offering->instructorPool()->attach($other->id, ['role_in_course' => 'instructor']);

        $this->actingAsRole($instructor, 'instructor');

        // permission route อยู่ในกลุ่ม course_head เท่านั้น
        $this->patch(route('maker.course_offerings.instructors.permission', [$offering, $other]), [
            'schedule_permission' => 'schedule',
        ])->assertForbidden();
    }

    public function test_assigned_staff_can_open_offering_and_appears_in_workspace(): void
    {
        [$head, $offering] = $this->makeOffering();
        $staff = $this->makeUser('staff');
        $offering->course->assignedStaff()->attach($staff->id);

        $this->actingAsRole($staff, 'staff');

        $this->get(route('maker.course_offerings.schedules.index', $offering))
            ->assertOk()
            ->assertSee($offering->course->course_code);

        // workspace ของเจ้าหน้าที่ redirect ไปหน้าวิชาเหมือนหัวหน้าวิชา (ไม่ค้างหน้า overview)
        $this->followingRedirects()
            ->get(route('maker.schedules.index'))
            ->assertOk()
            ->assertSee($offering->course->course_code);
    }

    public function test_assigned_staff_schedule_page_uses_shared_schedule_modal_styles(): void
    {
        [$head, $offering] = $this->makeOffering();
        $staff = $this->makeUser('staff');
        $offering->course->assignedStaff()->attach($staff->id);

        $this->actingAsRole($staff, 'staff');

        $this->get(route('maker.course_offerings.schedules.index', $offering))
            ->assertOk()
            ->assertSee('class="schedule-shell "', false)
            ->assertSee('.schedule-shell .schedule-detail-actions', false)
            ->assertSee('.schedule-shell .schedule-modal > .schedule-detail-actions', false)
            ->assertSee('.schedule-shell .schedule-copy-week-modal', false);
    }

    public function test_unassigned_staff_cannot_open_offering_schedules(): void
    {
        [$head, $offering] = $this->makeOffering();
        $staff = $this->makeUser('staff');

        $this->actingAsRole($staff, 'staff');

        $this->get(route('maker.course_offerings.schedules.index', $offering))
            ->assertForbidden();
        $this->get(route('maker.schedules.index'))
            ->assertOk()
            ->assertDontSee($offering->course->course_code);
    }

    public function test_assigned_staff_cannot_access_offering_management(): void
    {
        [$head, $offering] = $this->makeOffering();
        $staff = $this->makeUser('staff');
        $offering->course->assignedStaff()->attach($staff->id);

        $this->actingAsRole($staff, 'staff');

        // จัดการ offering (ชุดผู้สอน/อนุมัติ) = course_head เท่านั้น
        $this->get(route('maker.course_offerings.show', $offering))->assertForbidden();
    }

    public function test_delegated_instructor_cannot_create_slot_when_not_scheduling_phase(): void
    {
        [$head, $offering, $instructor] = $this->makeOffering('preparation');
        $offering->instructorPool()->updateExistingPivot($instructor->id, ['schedule_permission' => 'schedule']);

        $this->actingAsRole($instructor, 'instructor');

        // requireSchedulingPhase บล็อกก่อน validate → redirect พร้อม error 'schedule'
        $this->post(route('maker.course_offerings.schedules.store', $offering), [])
            ->assertSessionHasErrors('schedule');

        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_assigned_staff_cannot_create_slot_when_not_scheduling_phase(): void
    {
        [$head, $offering] = $this->makeOffering('preparation');
        $staff = $this->makeUser('staff');
        $offering->course->assignedStaff()->attach($staff->id);

        $this->actingAsRole($staff, 'staff');

        $this->post(route('maker.course_offerings.schedules.store', $offering), [])
            ->assertSessionHasErrors('schedule');

        $this->assertDatabaseCount('schedules', 0);
    }

    // ── helpers ─────────────────────────────────────────────

    private function actingAsRole(User $user, string $role): void
    {
        $this->actingAs($user);
        $this->withSession(['active_role' => $role]);
    }

    /**
     * @return array{User, CourseOffering, User}
     */
    private function makeOffering(string $phase = 'scheduling'): array
    {
        $head = $this->makeUser('course_head');
        $instructor = $this->makeUser('instructor');
        $year = AcademicYear::create([
            'name' => '2570-' . ($this->seq++),
            'start_date' => '2026-08-01',
            'end_date' => '2027-05-31',
            'is_active' => true,
            'phase' => $phase,
        ]);
        $course = Course::create([
            'course_code' => 'DLG' . ($this->seq++),
            'curriculum_id' => $this->curriculum()->id,
            'department_id' => $this->department()->id,
            'head_instructor_id' => $head->id,
            'name_th' => 'Delegation Course',
            'name_en' => 'Delegation Course',
            'course_type' => 'theory_practicum',
            'default_year_level' => 2,
            'requires_practicum_rotation' => false,
            'credits' => 3,
            'lecture_hours' => 2,
            'lab_hours' => 1,
            'self_study_hours' => 3,
            'status' => 'active',
        ]);
        $offering = CourseOffering::create([
            'course_id' => $course->id,
            'academic_year_id' => $year->id,
            'coordinator_id' => $head->id,
            'approval_status' => 'draft',
            'total_student_count' => 30,
        ]);
        $offering->instructorPool()->attach($instructor->id, ['role_in_course' => 'instructor']);

        return [$head, $offering, $instructor];
    }

    private function makeUser(string $role): User
    {
        $n = $this->seq++;
        $user = User::create([
            'username' => "dlg_user_{$n}",
            'name' => "Delegation User {$n}",
            'email' => "dlg_user_{$n}@example.com",
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

    private function department(): Department
    {
        return Department::firstOrCreate(['name' => 'ภาควิชาทดสอบ delegation']);
    }

    private function curriculum(): Curriculum
    {
        return Curriculum::firstOrCreate(
            ['name' => 'หลักสูตรทดสอบ delegation'],
            [
                'education_level' => 'bachelor',
                'duration_years' => 4,
                'uses_year_level' => true,
                'effective_year' => 2569,
                'status' => 'active',
            ]
        );
    }
}
