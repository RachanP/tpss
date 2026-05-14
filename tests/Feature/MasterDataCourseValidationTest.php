<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MasterDataCourseValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'username' => 'course_admin',
            'name' => 'Course Admin',
            'email' => 'course-admin@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        UserRole::create([
            'user_id' => $this->admin->id,
            'role' => 'admin',
            'is_primary' => true,
        ]);
    }

    public function test_admin_cannot_create_duplicate_course_code_in_same_curriculum(): void
    {
        $curriculum = $this->createCurriculum('Curriculum 2565', 2565);
        $department = $this->createDepartment('Course Validation Department');
        $this->createCourse($curriculum, $department, ['course_code' => 'NS101']);

        $this->actingAsAdmin();

        $response = $this->from('/admin/master-data?tab=courses')
            ->post('/admin/master-data/courses', $this->coursePayload($curriculum, $department, [
                'course_code' => 'NS101',
            ]));

        $response->assertRedirect('/admin/master-data?tab=courses');
        $response->assertSessionHasErrors('course_code');
        $this->assertSame(1, Course::where('course_code', 'NS101')
            ->where('curriculum_id', $curriculum->id)
            ->count());
    }

    public function test_admin_can_create_same_course_code_in_different_curriculums(): void
    {
        $curriculumA = $this->createCurriculum('Curriculum 2565', 2565);
        $curriculumB = $this->createCurriculum('Curriculum 2566', 2566);
        $department = $this->createDepartment('Shared Code Department');
        $this->createCourse($curriculumA, $department, ['course_code' => 'NS102']);

        $this->actingAsAdmin();

        $response = $this->post('/admin/master-data/courses', $this->coursePayload($curriculumB, $department, [
            'course_code' => 'NS102',
        ]));

        $response->assertRedirect('/admin/master-data?tab=courses');
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('courses', [
            'course_code' => 'NS102',
            'curriculum_id' => $curriculumB->id,
        ]);
        $this->assertSame(2, Course::where('course_code', 'NS102')->count());
    }

    public function test_admin_can_update_course_without_failing_its_own_composite_unique_rule(): void
    {
        $curriculum = $this->createCurriculum('Curriculum 2567', 2567);
        $department = $this->createDepartment('Self Update Department');
        $course = $this->createCourse($curriculum, $department, ['course_code' => 'NS103']);

        $this->actingAsAdmin();

        $response = $this->put("/admin/master-data/courses/{$course->id}", $this->coursePayload($curriculum, $department, [
            'course_code' => 'NS103',
            'name_th' => 'Updated Course Name',
            'course_type' => 'theory',
        ]));

        $response->assertRedirect('/admin/master-data?tab=courses');
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'course_code' => 'NS103',
            'curriculum_id' => $curriculum->id,
            'name_th' => 'Updated Course Name',
        ]);
    }

    public function test_admin_cannot_update_course_to_duplicate_code_in_same_curriculum(): void
    {
        $curriculum = $this->createCurriculum('Curriculum 2568', 2568);
        $department = $this->createDepartment('Duplicate Update Department');
        $this->createCourse($curriculum, $department, ['course_code' => 'NS104']);
        $course = $this->createCourse($curriculum, $department, ['course_code' => 'NS105']);

        $this->actingAsAdmin();

        $response = $this->from('/admin/master-data?tab=courses')
            ->put("/admin/master-data/courses/{$course->id}", $this->coursePayload($curriculum, $department, [
                'course_code' => 'NS104',
                'course_type' => 'theory',
            ]));

        $response->assertRedirect('/admin/master-data?tab=courses');
        $response->assertSessionHasErrors('course_code');
        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'course_code' => 'NS105',
        ]);
    }

    private function actingAsAdmin(): void
    {
        $csrfToken = 'valid-test-csrf-token';

        $this
            ->withSession([
                'active_role' => 'admin',
                '_token' => $csrfToken,
            ])
            ->actingAs($this->admin);
    }

    private function coursePayload(Curriculum $curriculum, Department $department, array $overrides = []): array
    {
        return array_merge([
            '_token' => 'valid-test-csrf-token',
            'course_code' => 'NS100',
            'name_th' => 'Nursing Course',
            'name_en' => 'Nursing Course',
            'curriculum_id' => $curriculum->id,
            'department_id' => $department->id,
            'head_instructor_id' => null,
            'staff_ids' => [],
            'course_type' => 'theory',
            'academic_level' => 'undergraduate',
            'default_year_level' => 1,
            'default_semester' => 1,
            'credits' => 3,
            'lecture_hours' => 3,
            'lab_hours' => 0,
            'self_study_hours' => 6,
            'capacity' => 60,
            'color_code' => '#1f6feb',
            'status' => 'active',
        ], $overrides);
    }

    private function createCurriculum(string $name, int $effectiveYear): Curriculum
    {
        return Curriculum::create([
            'name' => $name,
            'effective_year' => $effectiveYear,
            'is_active' => true,
        ]);
    }

    private function createDepartment(string $name): Department
    {
        return Department::create([
            'name' => $name,
        ]);
    }

    private function createCourse(Curriculum $curriculum, Department $department, array $overrides = []): Course
    {
        return Course::create(array_merge([
            'course_code' => 'NS100',
            'curriculum_id' => $curriculum->id,
            'department_id' => $department->id,
            'name_th' => 'Existing Nursing Course',
            'name_en' => 'Existing Nursing Course',
            'course_type' => 'theory',
            'academic_level' => 'undergraduate',
            'default_year_level' => 1,
            'default_semester' => 1,
            'requires_practicum_rotation' => false,
            'credits' => 3,
            'lecture_hours' => 3,
            'lab_hours' => 0,
            'self_study_hours' => 6,
            'capacity' => 60,
            'color_code' => '#1f6feb',
            'status' => 'active',
        ], $overrides));
    }
}
