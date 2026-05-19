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

    public function test_course_pool_pages_are_removed(): void
    {
        $admin = $this->makeUser('admin');
        $this->makeCourse(['course_code' => 'NSBS 111']);

        $this->actingAsRole($admin, 'admin');

        $this->get('/admin/course-pool')->assertNotFound();
        $this->get('/admin/course-pool/NSBS%20111')->assertNotFound();
    }

    public function test_master_data_course_modal_contains_assignment_controls(): void
    {
        $admin = $this->makeUser('admin');
        $this->makeCourse(['course_code' => 'NSBS 111']);

        $this->actingAsRole($admin, 'admin');

        $this->get(route('admin.master_data', ['tab' => 'courses']))
            ->assertOk()
            ->assertSee('ผู้รับผิดชอบรายวิชา')
            ->assertSee('หัวหน้าวิชา')
            ->assertSee('เจ้าหน้าที่ดูแลรายวิชา')
            ->assertSee('อาจารย์ผู้สอน')
            ->assertDontSee('/admin/course-pool/NSBS%20111', false);
    }

    public function test_admin_can_update_course_assignments_from_course_modal_form(): void
    {
        $admin = $this->makeUser('admin');
        $head = $this->makeUser('course_head');
        $staff = $this->makeUser('staff');
        $instructor = $this->makeUser('instructor');
        $role = CourseRole::where('name_th', 'อาจารย์พี่เลี้ยง')->first();
        $course = $this->makeCourse(['head_instructor_id' => null]);

        $this->actingAsRole($admin, 'admin');

        $this->put(route('admin.courses.update', $course), $this->coursePayload($course, [
            'head_instructor_id' => $head->id,
            'staff_ids' => [$staff->id],
            'instructor_ids' => [$instructor->id],
            'instructor_role_ids' => [
                $instructor->id => $role->id,
            ],
        ]))
            ->assertRedirect(route('admin.master_data', ['tab' => 'courses']))
            ->assertSessionHas('success');

        $this->assertSame($head->id, $course->fresh()->head_instructor_id);
        $this->assertDatabaseHas('course_staff', [
            'course_id' => $course->id,
            'user_id' => $staff->id,
        ]);
        $this->assertDatabaseHas('course_instructors', [
            'course_id' => $course->id,
            'user_id' => $instructor->id,
            'course_role_id' => $role->id,
        ]);
    }

    public function test_active_course_requires_head_instructor_when_assignment_is_editable(): void
    {
        $admin = $this->makeUser('admin');
        $course = $this->makeCourse(['head_instructor_id' => null, 'status' => 'active']);

        $this->actingAsRole($admin, 'admin');

        $this->put(route('admin.courses.update', $course), $this->coursePayload($course, [
            'head_instructor_id' => null,
            'status' => 'active',
        ]))
            ->assertSessionHasErrors('head_instructor_id');

        $this->assertNull($course->fresh()->head_instructor_id);
    }

    public function test_inactive_course_can_be_saved_without_head_instructor(): void
    {
        $admin = $this->makeUser('admin');
        $course = $this->makeCourse(['head_instructor_id' => null, 'status' => 'inactive']);

        $this->actingAsRole($admin, 'admin');

        $this->put(route('admin.courses.update', $course), $this->coursePayload($course, [
            'head_instructor_id' => null,
            'status' => 'inactive',
        ]))
            ->assertRedirect(route('admin.master_data', ['tab' => 'courses']))
            ->assertSessionHas('success');

        $this->assertNull($course->fresh()->head_instructor_id);
    }

    public function test_locked_course_keeps_assignment_template_when_course_form_submits(): void
    {
        $admin = $this->makeUser('admin');
        $oldHead = $this->makeUser('course_head');
        $newHead = $this->makeUser('course_head');
        $staff = $this->makeUser('staff');
        $instructor = $this->makeUser('instructor');
        $course = $this->makeCourse(['head_instructor_id' => $oldHead->id]);
        $year = $this->makeYear(['phase' => 'scheduling']);

        CourseOffering::create([
            'course_id' => $course->id,
            'academic_year_id' => $year->id,
            'coordinator_id' => $oldHead->id,
            'approval_status' => 'draft',
        ]);

        $this->actingAsRole($admin, 'admin');

        $this->put(route('admin.courses.update', $course), $this->coursePayload($course, [
            'name_th' => 'แก้ไขชื่อรายวิชา',
            'head_instructor_id' => $newHead->id,
            'staff_ids' => [$staff->id],
            'instructor_ids' => [$instructor->id],
        ]))
            ->assertRedirect(route('admin.master_data', ['tab' => 'courses']))
            ->assertSessionHas('success');

        $course->refresh();
        $this->assertSame('แก้ไขชื่อรายวิชา', $course->name_th);
        $this->assertSame($oldHead->id, $course->head_instructor_id);
        $this->assertDatabaseMissing('course_staff', [
            'course_id' => $course->id,
            'user_id' => $staff->id,
        ]);
        $this->assertDatabaseMissing('course_instructors', [
            'course_id' => $course->id,
            'user_id' => $instructor->id,
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
            'academic_level' => $course->academic_level,
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
            'prerequisite_ids' => [],
            'staff_ids' => [],
            'instructor_ids' => [],
            'instructor_role_ids' => [],
        ], $overrides);
    }

    private function seedCourseRoles(): void
    {
        foreach ([
            ['name_th' => 'หัวหน้าวิชา', 'sort_order' => 1],
            ['name_th' => 'เลขานุการวิชา', 'sort_order' => 2],
            ['name_th' => 'อาจารย์ผู้สอน', 'sort_order' => 3],
            ['name_th' => 'อาจารย์ประจำกลุ่ม', 'sort_order' => 4],
            ['name_th' => 'อาจารย์พี่เลี้ยง', 'sort_order' => 5],
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
        $n = $this->sequence++;
        $user = User::create([
            'username' => "u_{$role}_{$n}",
            'name' => "{$role} {$n}",
            'email' => "u{$n}@test.example",
            'password' => Hash::make('password'),
            'is_active' => $active,
        ]);
        UserRole::create(['user_id' => $user->id, 'role' => $role, 'is_primary' => true]);

        if ($withProfile && in_array($role, ['instructor', 'course_head'], true)) {
            InstructorProfile::create([
                'user_id' => $user->id,
                'title' => 'อาจารย์',
                'department_id' => $this->department()->id,
            ]);
        }

        return $user;
    }

    private function makeCourse(array $overrides = []): Course
    {
        $n = $this->sequence++;

        return Course::create(array_merge([
            'course_code' => "POOL{$n}",
            'curriculum_id' => $this->curriculum()->id,
            'department_id' => $this->department()->id,
            'name_th' => "วิชา {$n}",
            'name_en' => "Course {$n}",
            'course_type' => 'theory',
            'academic_level' => 'undergraduate',
            'default_year_level' => 1,
            'default_semester' => 1,
            'credits' => 3,
            'lecture_hours' => 3,
            'lab_hours' => 0,
            'self_study_hours' => 6,
            'capacity' => 120,
            'color_code' => '#3b82f6',
            'status' => 'active',
            'requires_practicum_rotation' => false,
        ], $overrides));
    }

    private function makeYear(array $overrides = []): AcademicYear
    {
        $n = $this->sequence++;

        return AcademicYear::create(array_merge([
            'name' => "256{$n}",
            'semester' => 1,
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
            'phase' => 'preparation',
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
            'is_active' => true,
        ]);
    }
}
