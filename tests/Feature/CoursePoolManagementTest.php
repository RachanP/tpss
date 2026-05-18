<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseRole;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\InstructorProfile;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CoursePoolManagementTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCourseRoles();
    }

    // ── Index / Show ──────────────────────────────────────────────────

    public function test_admin_can_list_course_pool(): void
    {
        $admin  = $this->makeUser('admin');
        $course = $this->makeCourse(['course_code' => 'NSBS 111']);

        $this->actingAsRole($admin, 'admin');

        $this->get(route('admin.course_pool.index'))
            ->assertOk()
            ->assertSee('NSBS 111');
    }

    public function test_admin_can_view_course_pool_detail(): void
    {
        $admin  = $this->makeUser('admin');
        $course = $this->makeCourse(['course_code' => 'NSBS 212', 'name_th' => 'การพยาบาลเด็ก 1']);

        $this->actingAsRole($admin, 'admin');

        $this->get(route('admin.course_pool.show', $course))
            ->assertOk()
            ->assertSee('NSBS 212')
            ->assertSee('การพยาบาลเด็ก 1');
    }

    public function test_admin_course_pool_detail_resolves_unique_course_code(): void
    {
        $admin  = $this->makeUser('admin');
        $course = $this->makeCourse(['course_code' => 'NSBS 111', 'name_th' => 'Unique Route Course']);

        $this->actingAsRole($admin, 'admin');

        $this->get('/admin/course-pool/NSBS%20111')
            ->assertOk()
            ->assertSee($course->course_code)
            ->assertSee('Unique Route Course');
    }

    public function test_admin_course_pool_detail_returns_404_for_missing_course_code(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAsRole($admin, 'admin');

        $this->get('/admin/course-pool/MISSING101')->assertNotFound();
    }

    public function test_admin_course_pool_detail_returns_404_for_duplicate_course_code(): void
    {
        $admin = $this->makeUser('admin');
        $code = 'DUP 101';

        $this->makeCourse(['course_code' => $code]);
        $otherCurriculum = Curriculum::create([
            'name' => 'Duplicate Code Curriculum',
            'effective_year' => 2570,
            'is_active' => true,
        ]);
        $this->makeCourse([
            'course_code' => $code,
            'curriculum_id' => $otherCurriculum->id,
        ]);

        $this->actingAsRole($admin, 'admin');

        $this->get('/admin/course-pool/DUP%20101')->assertNotFound();
    }

    public function test_course_pool_index_links_use_course_code_not_numeric_id(): void
    {
        $admin = $this->makeUser('admin');
        $course = $this->makeCourse(['course_code' => 'NSBS 111']);

        $this->actingAsRole($admin, 'admin');

        $this->get(route('admin.course_pool.index'))
            ->assertOk()
            ->assertSee('/admin/course-pool/NSBS%20111', false)
            ->assertDontSee('/admin/course-pool/NSBS111', false)
            ->assertDontSee('/admin/course-pool/' . $course->id, false);
    }

    public function test_course_pool_index_marks_courses_without_head_instructor(): void
    {
        $admin = $this->makeUser('admin');
        $head = $this->makeUser('instructor');

        $this->makeCourse(['course_code' => 'HEAD 101', 'head_instructor_id' => $head->id]);
        $this->makeCourse(['course_code' => 'HEAD 102', 'head_instructor_id' => null]);

        $this->actingAsRole($admin, 'admin');

        $response = $this->get(route('admin.course_pool.index'))
            ->assertOk()
            ->assertSee('HEAD 101')
            ->assertSee('HEAD 102')
            ->assertSee('ยังไม่มีหัวหน้าวิชา')
            ->assertSee('data-head-state="assigned"', false)
            ->assertSee('data-head-state="missing"', false);

        $this->assertSame(1, substr_count($response->getContent(), 'data-testid="course-pool-missing-head-badge"'));
    }

    public function test_course_pool_index_shows_current_term_open_and_closed_state(): void
    {
        $admin = $this->makeUser('admin');
        $head = $this->makeUser('instructor');
        $activeYear = $this->makeYear([
            'name' => '2569',
            'semester' => 1,
            'is_active' => true,
        ]);
        $openCourse = $this->makeCourse(['course_code' => 'OPEN 101', 'head_instructor_id' => $head->id]);
        $closedCourse = $this->makeCourse(['course_code' => 'CLOSED 101', 'head_instructor_id' => $head->id]);

        CourseOffering::create([
            'course_id' => $openCourse->id,
            'academic_year_id' => $activeYear->id,
            'coordinator_id' => $head->id,
            'approval_status' => 'draft',
        ]);

        $this->actingAsRole($admin, 'admin');

        $this->get(route('admin.course_pool.index'))
            ->assertOk()
            ->assertSee('รอบปัจจุบัน: ปีการศึกษา 2569 / เทอม 1')
            ->assertSee('OPEN 101')
            ->assertSee('CLOSED 101')
            ->assertSee('เปิดสอน')
            ->assertSee('ปิดสอน')
            ->assertSee('data-term-state="open"', false)
            ->assertSee('data-term-state="closed"', false);
    }

    public function test_course_pool_index_renders_head_and_term_filters(): void
    {
        $admin = $this->makeUser('admin');
        $this->makeCourse(['course_code' => 'FILTER 101']);

        $this->actingAsRole($admin, 'admin');

        $this->get(route('admin.course_pool.index'))
            ->assertOk()
            ->assertSee('data-testid="course-pool-head-filter"', false)
            ->assertSee('data-testid="course-pool-term-filter"', false)
            ->assertSee('ทั้งหมด')
            ->assertSee('ยังไม่มีหัวหน้าวิชา')
            ->assertSee('มีหัวหน้าวิชาแล้ว')
            ->assertSee('เปิดสอน')
            ->assertSee('ปิดสอน');
    }

    public function test_course_pool_index_handles_missing_active_academic_year(): void
    {
        $admin = $this->makeUser('admin');
        $this->makeCourse(['course_code' => 'NOYEAR 101']);

        $this->actingAsRole($admin, 'admin');

        $this->get(route('admin.course_pool.index'))
            ->assertOk()
            ->assertSee('ยังไม่ได้ตั้งค่าปีการศึกษาปัจจุบัน')
            ->assertSee('ไม่ทราบ')
            ->assertSee('data-term-state="unknown"', false);
    }

    public function test_staff_can_access_course_pool_via_inherited_controller(): void
    {
        $staff  = $this->makeUser('staff');
        $course = $this->makeCourse();

        $this->actingAsRole($staff, 'staff');

        $this->get(route('staff.course_pool.index'))->assertOk();
        $this->get(route('staff.course_pool.show', $course))->assertOk();
    }

    public function test_course_head_cannot_access_admin_course_pool(): void
    {
        $head = $this->makeUser('course_head');
        $this->actingAsRole($head, 'course_head');

        $this->get(route('admin.course_pool.index'))->assertForbidden();
    }

    // ── Update head_instructor ────────────────────────────────────────

    public function test_admin_can_set_course_head_instructor(): void
    {
        $admin      = $this->makeUser('admin');
        $instructor = $this->makeUser('instructor');
        $course     = $this->makeCourse(['head_instructor_id' => null]);

        $this->actingAsRole($admin, 'admin');

        $this->from(route('admin.course_pool.show', $course))
            ->put(route('admin.course_pool.head.update', $course), [
                'head_instructor_id' => $instructor->id,
            ])
            ->assertRedirect(route('admin.course_pool.show', $course))
            ->assertSessionHas('success');

        $this->assertSame($instructor->id, $course->fresh()->head_instructor_id);
    }

    public function test_admin_can_clear_course_head_instructor(): void
    {
        $admin      = $this->makeUser('admin');
        $instructor = $this->makeUser('instructor');
        $course     = $this->makeCourse(['head_instructor_id' => $instructor->id]);

        $this->actingAsRole($admin, 'admin');

        $this->from(route('admin.course_pool.show', $course))
            ->put(route('admin.course_pool.head.update', $course), [
                'head_instructor_id' => null,
            ]);

        $this->assertNull($course->fresh()->head_instructor_id);
    }

    public function test_cannot_set_assigned_staff_as_head_instructor(): void
    {
        $admin = $this->makeUser('admin');
        $staff = $this->makeUser('staff');
        $course = $this->makeCourse(['head_instructor_id' => null]);
        $course->assignedStaff()->attach($staff->id);

        $this->actingAsRole($admin, 'admin');

        $this->from(route('admin.course_pool.show', $course))
            ->put(route('admin.course_pool.head.update', $course), [
                'head_instructor_id' => $staff->id,
            ])
            ->assertSessionHasErrors('head_instructor_id');

        $this->assertNull($course->fresh()->head_instructor_id);
    }

    // ── Instructor pool (course template) ─────────────────────────────

    public function test_admin_can_add_instructor_with_default_role(): void
    {
        $admin      = $this->makeUser('admin');
        $instructor = $this->makeUser('instructor');
        $course     = $this->makeCourse();
        $defaultRole = CourseRole::where('name_th', 'อาจารย์ผู้สอน')->first();

        $this->actingAsRole($admin, 'admin');

        $this->from(route('admin.course_pool.show', $course))
            ->post(route('admin.course_pool.instructors.store', $course), [
                'user_id' => $instructor->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('course_instructors', [
            'course_id'      => $course->id,
            'user_id'        => $instructor->id,
            'course_role_id' => $defaultRole->id,
        ]);
    }

    public function test_admin_can_add_instructor_with_specific_role_via_json(): void
    {
        $admin      = $this->makeUser('admin');
        $instructor = $this->makeUser('instructor');
        $course     = $this->makeCourse();
        $preceptor  = CourseRole::where('name_th', 'อาจารย์พี่เลี้ยง')->first();

        $this->actingAsRole($admin, 'admin');

        $response = $this->postJson(route('admin.course_pool.instructors.store', $course), [
            'user_id'        => $instructor->id,
            'course_role_id' => $preceptor->id,
        ]);

        $response->assertOk()
            ->assertJsonFragment([
                'id'             => $instructor->id,
                'course_role_id' => $preceptor->id,
                'role_name'      => 'อาจารย์พี่เลี้ยง',
            ]);

        $this->assertDatabaseHas('course_instructors', [
            'course_id'      => $course->id,
            'user_id'        => $instructor->id,
            'course_role_id' => $preceptor->id,
        ]);
    }

    public function test_cannot_add_instructor_without_instructor_profile(): void
    {
        $admin   = $this->makeUser('admin');
        $teacher = $this->makeUser('instructor', active: true, withProfile: false);
        $course  = $this->makeCourse();

        $this->actingAsRole($admin, 'admin');

        $this->postJson(route('admin.course_pool.instructors.store', $course), [
            'user_id' => $teacher->id,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('course_instructors', [
            'course_id' => $course->id,
            'user_id'   => $teacher->id,
        ]);
    }

    public function test_cannot_add_duplicate_instructor(): void
    {
        $admin      = $this->makeUser('admin');
        $instructor = $this->makeUser('instructor');
        $course     = $this->makeCourse();
        $course->instructors()->attach($instructor->id);

        $this->actingAsRole($admin, 'admin');

        $this->postJson(route('admin.course_pool.instructors.store', $course), [
            'user_id' => $instructor->id,
        ])->assertStatus(422);
    }

    public function test_admin_can_update_instructor_role(): void
    {
        $admin       = $this->makeUser('admin');
        $instructor  = $this->makeUser('instructor');
        $course      = $this->makeCourse();
        $oldRole     = CourseRole::where('name_th', 'อาจารย์ผู้สอน')->first();
        $newRole     = CourseRole::where('name_th', 'อาจารย์ประจำกลุ่ม')->first();
        $course->instructors()->attach($instructor->id, ['course_role_id' => $oldRole->id]);

        $this->actingAsRole($admin, 'admin');

        $this->patchJson(
            route('admin.course_pool.instructors.role', [$course, $instructor]),
            ['course_role_id' => $newRole->id]
        )->assertOk()
            ->assertJsonFragment(['course_role_id' => $newRole->id, 'role_name' => 'อาจารย์ประจำกลุ่ม']);

        $this->assertDatabaseHas('course_instructors', [
            'course_id'      => $course->id,
            'user_id'        => $instructor->id,
            'course_role_id' => $newRole->id,
        ]);
    }

    public function test_admin_can_remove_instructor(): void
    {
        $admin      = $this->makeUser('admin');
        $instructor = $this->makeUser('instructor');
        $course     = $this->makeCourse();
        $course->instructors()->attach($instructor->id);

        $this->actingAsRole($admin, 'admin');

        $this->deleteJson(route('admin.course_pool.instructors.destroy', [$course, $instructor]))
            ->assertOk();

        $this->assertDatabaseMissing('course_instructors', [
            'course_id' => $course->id,
            'user_id'   => $instructor->id,
        ]);
    }

    // ── Staff pool ────────────────────────────────────────────────────

    public function test_admin_can_add_staff_to_course(): void
    {
        $admin  = $this->makeUser('admin');
        $staff  = $this->makeUser('staff');
        $course = $this->makeCourse();

        $this->actingAsRole($admin, 'admin');

        $this->from(route('admin.course_pool.show', $course))
            ->post(route('admin.course_pool.staff.store', $course), ['user_id' => $staff->id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('course_staff', [
            'course_id' => $course->id,
            'user_id'   => $staff->id,
        ]);
    }

    public function test_cannot_add_head_as_staff(): void
    {
        $admin  = $this->makeUser('admin');
        $head   = $this->makeUser('instructor');
        $course = $this->makeCourse(['head_instructor_id' => $head->id]);

        $this->actingAsRole($admin, 'admin');

        $this->postJson(route('admin.course_pool.staff.store', $course), [
            'user_id' => $head->id,
        ])->assertStatus(422);
    }

    public function test_admin_can_remove_staff_from_course(): void
    {
        $admin  = $this->makeUser('admin');
        $staff  = $this->makeUser('staff');
        $course = $this->makeCourse();
        $course->assignedStaff()->attach($staff->id);

        $this->actingAsRole($admin, 'admin');

        $this->from(route('admin.course_pool.show', $course))
            ->delete(route('admin.course_pool.staff.destroy', [$course, $staff]))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('course_staff', [
            'course_id' => $course->id,
            'user_id'   => $staff->id,
        ]);
    }

    // ── Lock semantics ────────────────────────────────────────────────

    public function test_course_pool_is_locked_once_offering_enters_scheduling(): void
    {
        $admin      = $this->makeUser('admin');
        $instructor = $this->makeUser('instructor');
        $head       = $this->makeUser('instructor');
        $course     = $this->makeCourse(['head_instructor_id' => $head->id]);
        $year       = $this->makeYear(['phase' => 'scheduling']);
        CourseOffering::create([
            'course_id'        => $course->id,
            'academic_year_id' => $year->id,
            'coordinator_id'   => $head->id,
            'approval_status'  => 'draft',
        ]);

        $this->actingAsRole($admin, 'admin');

        // PUT head — blocked
        $this->from(route('admin.course_pool.show', $course))
            ->put(route('admin.course_pool.head.update', $course), [
                'head_instructor_id' => $instructor->id,
            ])
            ->assertSessionHas('error');

        // POST instructor JSON — 423 Locked
        $this->postJson(route('admin.course_pool.instructors.store', $course), [
            'user_id' => $instructor->id,
        ])->assertStatus(423);

        $this->assertSame($head->id, $course->fresh()->head_instructor_id);
        $this->assertDatabaseMissing('course_instructors', [
            'course_id' => $course->id,
            'user_id'   => $instructor->id,
        ]);
    }

    public function test_course_pool_not_locked_while_offering_is_in_preparation(): void
    {
        $admin      = $this->makeUser('admin');
        $instructor = $this->makeUser('instructor');
        $head       = $this->makeUser('instructor');
        $course     = $this->makeCourse(['head_instructor_id' => $head->id]);
        $year       = $this->makeYear(['phase' => 'preparation']);
        CourseOffering::create([
            'course_id'        => $course->id,
            'academic_year_id' => $year->id,
            'coordinator_id'   => $head->id,
            'approval_status'  => 'draft',
        ]);

        $this->actingAsRole($admin, 'admin');

        $this->postJson(route('admin.course_pool.instructors.store', $course), [
            'user_id' => $instructor->id,
        ])->assertOk();

        $this->assertDatabaseHas('course_instructors', [
            'course_id' => $course->id,
            'user_id'   => $instructor->id,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function seedCourseRoles(): void
    {
        foreach ([
            ['name_th' => 'หัวหน้าวิชา',         'sort_order' => 1],
            ['name_th' => 'เลขานุการวิชา',        'sort_order' => 2],
            ['name_th' => 'อาจารย์ผู้สอน',        'sort_order' => 3],
            ['name_th' => 'อาจารย์ประจำกลุ่ม',  'sort_order' => 4],
            ['name_th' => 'อาจารย์พี่เลี้ยง',    'sort_order' => 5],
        ] as $role) {
            CourseRole::firstOrCreate(['name_th' => $role['name_th']], $role);
        }
    }

    private function actingAsRole(User $user, string $role): void
    {
        $this->actingAs($user);
        $this->withSession(['active_role' => $role]);
    }

    private function makeUser(string $role, bool $active = true, bool $withProfile = true): User
    {
        $n    = $this->sequence++;
        $user = User::create([
            'username'  => "u_{$role}_{$n}",
            'name'      => "{$role} {$n}",
            'email'     => "u{$n}@test.example",
            'password'  => Hash::make('password'),
            'is_active' => $active,
        ]);
        UserRole::create(['user_id' => $user->id, 'role' => $role, 'is_primary' => true]);

        if ($withProfile && in_array($role, ['instructor', 'course_head'], true)) {
            InstructorProfile::create([
                'user_id'       => $user->id,
                'title'         => 'อาจารย์',
                'department_id' => $this->department()->id,
            ]);
        }

        return $user;
    }

    private function makeCourse(array $overrides = []): Course
    {
        $n = $this->sequence++;
        return Course::create(array_merge([
            'course_code'              => "POOL{$n}",
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

    private function makeYear(array $overrides = []): AcademicYear
    {
        $n = $this->sequence++;
        return AcademicYear::create(array_merge([
            'name'       => "256{$n}",
            'semester'   => 1,
            'start_date' => '2026-08-01',
            'end_date'   => '2026-12-31',
            'is_active'  => true,
            'phase'      => 'preparation',
        ], $overrides));
    }

    private function department(): Department
    {
        return Department::firstOrCreate(['name' => 'Pool Test Dept']);
    }

    private function curriculum(): Curriculum
    {
        return Curriculum::firstOrCreate(['name' => 'Pool Test Curriculum'], [
            'effective_year' => 2569,
            'is_active'      => true,
        ]);
    }
}
