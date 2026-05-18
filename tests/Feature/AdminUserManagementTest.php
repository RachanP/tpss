<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\InstructorProfile;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'username' => 'admin_test',
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
        ]);

        UserRole::create([
            'user_id' => $this->admin->id,
            'role' => 'admin',
            'is_primary' => true,
        ]);

        session(['active_role' => 'admin']);
    }

    public function test_admin_can_view_user_index(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get('/admin/users');

        $response->assertStatus(200);
        $response->assertViewIs('admin.users.index');
        $response->assertSee('Admin User');
    }

    public function test_admin_can_create_user(): void
    {
        $department = Department::create([
            'name' => 'Test Nursing Department',
        ]);

        $this->actingAs($this->admin);

        $response = $this->post('/admin/users', [
            'username' => 'newuser',
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
            'employee_id' => 'EMP-NEW-1',
            'roles' => ['staff', 'instructor'],
            'primary_role' => 'staff',
            'is_active' => true,
            'instructor_title' => 'Instructor',
            'instructor_department_id' => $department->id,
            'instructor_employment_type' => 'Full-time',
            'instructor_hired_at' => '2026-05-14',
            'instructor_academic_degree' => 'Master',
            'instructor_teaching_pct' => 50,
            'instructor_research_pct' => 20,
            'instructor_service_pct' => 10,
            'instructor_culture_pct' => 10,
            'instructor_other_pct' => 10,
        ]);

        $response->assertRedirect('/admin/users');
        $this->assertDatabaseHas('users', ['username' => 'newuser']);
        $this->assertDatabaseHas('user_roles', ['role' => 'staff', 'is_primary' => true]);
        $this->assertDatabaseHas('user_roles', ['role' => 'instructor', 'is_primary' => false]);
    }

    public function test_admin_can_update_user(): void
    {
        $this->actingAs($this->admin);
        $user = User::create([
            'username' => 'update-me',
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'password' => 'password',
        ]);

        $response = $this->put("/admin/users/{$user->id}", [
            'username' => 'update-me',
            'name' => 'New Name',
            'email' => 'new-email@example.com',
            'roles' => ['admin'],
            'primary_role' => 'admin',
            'is_active' => true,
        ]);

        $response->assertRedirect('/admin/users');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'new-email@example.com',
        ]);
    }

    public function test_admin_can_update_username(): void
    {
        $this->actingAs($this->admin);
        $user = User::create([
            'username' => 'old-username',
            'name' => 'Some Name',
            'email' => 'some@example.com',
            'password' => 'password',
        ]);

        $response = $this->put("/admin/users/{$user->id}", [
            'username' => 'new-username',
            'name' => 'Some Name',
            'email' => 'some@example.com',
            'roles' => ['staff'],
            'primary_role' => 'staff',
            'is_active' => true,
        ]);

        $response->assertRedirect('/admin/users');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'username' => 'new-username',
        ]);
    }

    public function test_admin_can_toggle_user_status(): void
    {
        $this->actingAs($this->admin);
        $user = User::create([
            'username' => 'toggle-me',
            'name' => 'Toggle User',
            'email' => 'toggle@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        $response = $this->patch("/admin/users/{$user->id}/toggle");

        $response->assertStatus(200);
        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_admin_can_delete_user(): void
    {
        $this->actingAs($this->admin);
        $user = User::create([
            'username' => 'delete-me',
            'name' => 'Delete User',
            'email' => 'delete@example.com',
            'password' => 'password',
        ]);

        $response = $this->delete("/admin/users/{$user->id}");

        $response->assertRedirect('/admin/users');
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    // ===== Authorization =====

    public function test_guest_cannot_access_user_index(): void
    {
        $this->get('/admin/users')->assertRedirect('/login');
    }

    public function test_non_admin_cannot_access_user_index(): void
    {
        $staff = $this->makeUser('staff_user', 'staff');
        session(['active_role' => 'staff']);

        $this->actingAs($staff)
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_non_admin_cannot_create_user(): void
    {
        $courseHead = $this->makeUser('ch_user', 'course_head');
        session(['active_role' => 'course_head']);

        $this->actingAs($courseHead)
            ->post('/admin/users', [
                'username' => 'sneaky',
                'name' => 'Sneaky',
                'email' => 'sneaky@example.com',
                'password' => 'password123',
                'roles' => ['staff'],
                'primary_role' => 'staff',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['username' => 'sneaky']);
    }

    // ===== Validation =====

    public function test_instructor_percentages_must_total_100(): void
    {
        $dept = Department::create(['name' => 'Dept A']);
        $this->actingAs($this->admin);

        $response = $this->post('/admin/users', $this->validInstructorPayload($dept, [
            'instructor_teaching_pct' => 50,
            'instructor_research_pct' => 20,
            'instructor_service_pct' => 10,
            'instructor_culture_pct' => 10,
            'instructor_other_pct' => 5, // total 95
        ]));

        $response->assertSessionHasErrors('instructor_teaching_pct');
        $this->assertDatabaseMissing('users', ['username' => 'inst1']);
    }

    public function test_primary_role_must_be_in_roles_list(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post('/admin/users', [
            'username' => 'badprimary',
            'name' => 'Bad',
            'email' => 'bad@example.com',
            'password' => 'password123',
            'roles' => ['staff'],
            'primary_role' => 'admin', // not in roles
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('primary_role');
    }

    public function test_duplicate_username_is_rejected(): void
    {
        $this->actingAs($this->admin);
        User::create([
            'username' => 'taken',
            'name' => 'X',
            'email' => 'x@example.com',
            'password' => 'password',
        ]);

        $response = $this->post('/admin/users', [
            'username' => 'taken',
            'name' => 'Y',
            'email' => 'y@example.com',
            'password' => 'password123',
            'roles' => ['staff'],
            'primary_role' => 'staff',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('username');
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $this->actingAs($this->admin);
        User::create([
            'username' => 'aaa',
            'name' => 'X',
            'email' => 'dup@example.com',
            'password' => 'password',
        ]);

        $response = $this->post('/admin/users', [
            'username' => 'bbb',
            'name' => 'Y',
            'email' => 'dup@example.com',
            'password' => 'password123',
            'roles' => ['staff'],
            'primary_role' => 'staff',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('email');
    }

    // ===== Security =====

    public function test_password_is_hashed_on_create(): void
    {
        $this->actingAs($this->admin);

        $this->post('/admin/users', [
            'username' => 'hashme',
            'name' => 'Hash Me',
            'email' => 'hash@example.com',
            'password' => 'secret-pw-123',
            'roles' => ['staff'],
            'primary_role' => 'staff',
            'is_active' => true,
        ]);

        $user = User::where('username', 'hashme')->first();
        $this->assertNotNull($user);
        $this->assertNotSame('secret-pw-123', $user->password);
        $this->assertTrue(Hash::check('secret-pw-123', $user->password));
    }

    // ===== Update behavior =====

    public function test_update_with_blank_password_keeps_existing_password(): void
    {
        $this->actingAs($this->admin);
        $user = User::create([
            'username' => 'keeppw',
            'name' => 'Keep PW',
            'email' => 'keeppw@example.com',
            'password' => 'original-pw',
        ]);
        $originalHash = $user->fresh()->password;

        $this->put("/admin/users/{$user->id}", [
            'username' => 'keeppw',
            'name' => 'Keep PW',
            'email' => 'keeppw@example.com',
            'password' => '',
            'roles' => ['staff'],
            'primary_role' => 'staff',
            'is_active' => true,
        ]);

        $this->assertSame($originalHash, $user->fresh()->password);
    }

    public function test_update_with_new_password_hashes_it(): void
    {
        $this->actingAs($this->admin);
        $user = User::create([
            'username' => 'changepw',
            'name' => 'Change PW',
            'email' => 'changepw@example.com',
            'password' => 'old-pw',
        ]);

        $this->put("/admin/users/{$user->id}", [
            'username' => 'changepw',
            'name' => 'Change PW',
            'email' => 'changepw@example.com',
            'password' => 'brand-new-pw',
            'roles' => ['staff'],
            'primary_role' => 'staff',
            'is_active' => true,
        ]);

        $this->assertTrue(Hash::check('brand-new-pw', $user->fresh()->password));
    }

    public function test_demoting_instructor_to_staff_removes_profile(): void
    {
        $dept = Department::create(['name' => 'Dept B']);
        $this->actingAs($this->admin);
        $user = User::create([
            'username' => 'demote',
            'name' => 'Demote',
            'email' => 'demote@example.com',
            'password' => 'password',
        ]);
        UserRole::create(['user_id' => $user->id, 'role' => 'instructor', 'is_primary' => true]);
        InstructorProfile::create([
            'user_id' => $user->id,
            'title' => 'อาจารย์',
            'department_id' => $dept->id,
            'academic_degree' => 'Master',
            'employment_type' => 'Full-time',
            'hired_at' => '2026-01-01',
            'teaching_pct' => 60, 'research_pct' => 20, 'service_pct' => 10, 'culture_pct' => 5, 'other_pct' => 5,
        ]);

        $this->put("/admin/users/{$user->id}", [
            'username' => 'demote',
            'name' => 'Demote',
            'email' => 'demote@example.com',
            'roles' => ['staff'],
            'primary_role' => 'staff',
            'is_active' => true,
        ]);

        $this->assertDatabaseMissing('instructor_profiles', ['user_id' => $user->id]);
        $this->assertDatabaseHas('user_roles', ['user_id' => $user->id, 'role' => 'staff']);
        $this->assertDatabaseMissing('user_roles', ['user_id' => $user->id, 'role' => 'instructor']);
    }

    // ===== Department position handoff =====

    public function test_assigning_new_head_clears_previous_holder(): void
    {
        $dept = Department::create(['name' => 'Handoff Dept']);
        $oldHead = $this->makeUser('oldhead', 'instructor');
        $dept->update(['head_user_id' => $oldHead->id]);
        $this->actingAs($this->admin);

        $response = $this->post('/admin/users', $this->validInstructorPayload($dept, [
            'username' => 'newhead',
            'email' => 'newhead@example.com',
            'instructor_department_position' => 'head',
        ]));

        $response->assertRedirect('/admin/users');
        $newHead = User::where('username', 'newhead')->first();
        $this->assertSame($newHead->id, $dept->fresh()->head_user_id);
    }

    // ===== Toggle status =====

    public function test_toggle_status_returns_json(): void
    {
        $this->actingAs($this->admin);
        $user = User::create([
            'username' => 'togglejson',
            'name' => 'X', 'email' => 'tj@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        $response = $this->patch("/admin/users/{$user->id}/toggle");

        $response->assertJson(['success' => true, 'is_active' => false]);
    }

    // ===== Delete with FK / position handling =====

    public function test_deleting_user_releases_department_position(): void
    {
        $dept = Department::create(['name' => 'Will Lose Head']);
        $user = $this->makeUser('willdie', 'instructor');
        $dept->update(['head_user_id' => $user->id]);

        $this->actingAs($this->admin)
            ->delete("/admin/users/{$user->id}")
            ->assertRedirect('/admin/users');

        $this->assertNull($dept->fresh()->head_user_id);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    // ===== Helpers =====

    private function makeUser(string $username, string $role): User
    {
        $user = User::create([
            'username' => $username,
            'name' => ucfirst($username),
            'email' => $username . '@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);
        UserRole::create(['user_id' => $user->id, 'role' => $role, 'is_primary' => true]);
        return $user;
    }

    private function validInstructorPayload(Department $dept, array $overrides = []): array
    {
        return array_merge([
            'username' => 'inst1',
            'name' => 'Instructor One',
            'email' => 'inst1@example.com',
            'password' => 'password123',
            'employee_id' => 'EMP' . random_int(1000, 9999),
            'roles' => ['instructor'],
            'primary_role' => 'instructor',
            'is_active' => true,
            'instructor_title' => 'อาจารย์',
            'instructor_department_id' => $dept->id,
            'instructor_academic_degree' => 'ปริญญาโท',
            'instructor_employment_type' => 'Full-time',
            'instructor_hired_at' => '2026-01-01',
            'instructor_teaching_pct' => 50,
            'instructor_research_pct' => 25,
            'instructor_service_pct' => 10,
            'instructor_culture_pct' => 10,
            'instructor_other_pct' => 5,
        ], $overrides);
    }
}
