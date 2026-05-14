<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\LocationType;
use App\Models\Room;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MasterDataRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_shared_master_data_action_redirects_to_admin_route(): void
    {
        $admin = $this->createUserWithRole('admin', 'admin_redirect');

        $this->actingAsRole($admin, 'admin');

        $response = $this->post('/admin/master-data/location-types', $this->csrfPayload([
            'name' => 'Admin Redirect Location Type',
        ]));

        $response->assertRedirect('/admin/master-data?tab=location_types');
        $this->assertDatabaseHas('location_types', [
            'name' => 'Admin Redirect Location Type',
        ]);
    }

    public function test_staff_store_course_redirects_to_staff_route(): void
    {
        $staff = $this->createUserWithRole('staff', 'staff_store_course');
        $curriculum = $this->createCurriculum('Staff Store Curriculum', 2565);
        $department = $this->createDepartment('Staff Store Department');

        $this->actingAsRole($staff, 'staff');

        $response = $this->post('/staff/master-data/courses', $this->coursePayload($curriculum, $department, [
            'course_code' => 'ST101',
        ]));

        $response->assertRedirect('/staff/master-data?tab=courses');
        $this->assertDatabaseHas('courses', [
            'course_code' => 'ST101',
            'curriculum_id' => $curriculum->id,
        ]);
    }

    public function test_staff_update_room_redirects_to_staff_route(): void
    {
        $staff = $this->createUserWithRole('staff', 'staff_update_room');
        $locationType = LocationType::create(['name' => 'Ward']);
        $room = Room::create([
            'room_code' => 'RM-101',
            'room_name' => 'Room 101',
            'location_type_id' => $locationType->id,
            'status' => 'active',
        ]);

        $this->actingAsRole($staff, 'staff');

        $response = $this->put("/staff/master-data/rooms/{$room->id}", $this->csrfPayload([
            'room_code' => 'RM-101',
            'room_name' => 'Updated Room 101',
            'location_type_id' => $locationType->id,
            'status' => 'active',
        ]));

        $response->assertRedirect('/staff/master-data?tab=location_types');
        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'room_name' => 'Updated Room 101',
        ]);
    }

    public function test_staff_delete_course_redirects_to_staff_route(): void
    {
        $staff = $this->createUserWithRole('staff', 'staff_delete_course');
        $curriculum = $this->createCurriculum('Staff Delete Curriculum', 2566);
        $department = $this->createDepartment('Staff Delete Department');
        $course = $this->createCourse($curriculum, $department, [
            'course_code' => 'ST102',
        ]);

        $this->actingAsRole($staff, 'staff');

        $response = $this->delete("/staff/master-data/courses/{$course->id}", $this->csrfPayload());

        $response->assertRedirect('/staff/master-data?tab=courses');
        $this->assertDatabaseMissing('courses', [
            'id' => $course->id,
        ]);
    }

    public function test_staff_cannot_access_admin_only_master_data_routes(): void
    {
        $staff = $this->createUserWithRole('staff', 'admin_only_denied');

        $this->actingAsRole($staff, 'staff');

        $this->get('/admin/master-data')->assertStatus(403);
        $this->post('/admin/master-data/departments', $this->csrfPayload([
            'name' => 'Denied Department',
        ]))->assertStatus(403);
    }

    private function createUserWithRole(string $role, string $suffix): User
    {
        $user = User::create([
            'username' => "{$role}_{$suffix}",
            'name' => "{$role} {$suffix}",
            'email' => "{$role}_{$suffix}@example.com",
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        UserRole::create([
            'user_id' => $user->id,
            'role' => $role,
            'is_primary' => true,
        ]);

        return $user;
    }

    private function actingAsRole(User $user, string $activeRole): void
    {
        $this
            ->withSession([
                'active_role' => $activeRole,
                '_token' => 'valid-test-csrf-token',
            ])
            ->actingAs($user);
    }

    private function csrfPayload(array $overrides = []): array
    {
        return array_merge([
            '_token' => 'valid-test-csrf-token',
        ], $overrides);
    }

    private function coursePayload(Curriculum $curriculum, Department $department, array $overrides = []): array
    {
        return $this->csrfPayload(array_merge([
            'course_code' => 'ST100',
            'name_th' => 'Staff Nursing Course',
            'name_en' => 'Staff Nursing Course',
            'curriculum_id' => $curriculum->id,
            'department_id' => $department->id,
            'head_instructor_id' => null,
            'staff_ids' => [],
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
        ], $overrides));
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
            'course_code' => 'ST100',
            'curriculum_id' => $curriculum->id,
            'department_id' => $department->id,
            'name_th' => 'Existing Staff Course',
            'name_en' => 'Existing Staff Course',
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
