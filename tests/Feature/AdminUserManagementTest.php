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

    public function test_admin_user_delete_confirm_does_not_apply_scrollbar_layout_compensation(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get('/admin/users');

        $response->assertStatus(200);
        $response->assertSee('function tpssDelete(btn)', false);
        $response->assertSee('heightAuto: false', false);
        $response->assertSee('scrollbarPadding: false', false);
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

    public function test_admin_user_instructor_hired_date_accepts_thai_buddhist_input(): void
    {
        $department = Department::create(['name' => 'Thai Date Department']);

        $this->actingAs($this->admin);

        $response = $this->post('/admin/users', $this->validInstructorPayload($department, [
            'username' => 'thai-date-inst',
            'email' => 'thai-date-inst@example.com',
            'instructor_hired_at' => '23/06/2569',
        ]));

        $response->assertRedirect('/admin/users');

        $user = User::where('username', 'thai-date-inst')->firstOrFail();
        $this->assertDatabaseHas('instructor_profiles', [
            'user_id' => $user->id,
            'hired_at' => '2026-06-23',
        ]);
    }

    public function test_admin_user_update_rejects_short_hired_date_year_and_reopens_edit_modal(): void
    {
        $department = Department::create(['name' => 'Invalid Thai Date Department']);
        $user = $this->makeInstructorWithProfile($department, [
            'hired_at' => '2026-01-01',
        ]);

        $this->actingAs($this->admin);

        $response = $this->from('/admin/users')->put("/admin/users/{$user->id}", $this->validInstructorUpdatePayload($user, [
            'instructor_hired_at' => '10/10/1',
            'editing_user_id' => (string) $user->id,
        ]));

        $response
            ->assertRedirect('/admin/users')
            ->assertSessionHasErrors('instructor_hired_at')
            ->assertSessionHasInput('editing_user_id', (string) $user->id);

        $this->assertSame('2026-01-01', $user->fresh('instructorProfile')->instructorProfile->hired_at);
    }

    public function test_admin_user_update_rejects_four_digit_out_of_range_hired_date_year(): void
    {
        $department = Department::create(['name' => 'Out Of Range Thai Date Department']);
        $user = $this->makeInstructorWithProfile($department, [
            'hired_at' => '2026-01-01',
        ]);

        $this->actingAs($this->admin);

        $response = $this->from('/admin/users')->put("/admin/users/{$user->id}", $this->validInstructorUpdatePayload($user, [
            'instructor_hired_at' => '10/10/0001',
            'editing_user_id' => (string) $user->id,
        ]));

        $response
            ->assertRedirect('/admin/users')
            ->assertSessionHasErrors('instructor_hired_at')
            ->assertSessionHasInput('editing_user_id', (string) $user->id);

        $this->assertSame('2026-01-01', $user->fresh('instructorProfile')->instructorProfile->hired_at);
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

    public function test_admin_can_create_instructor_without_pa_percentages(): void
    {
        $dept = Department::create(['name' => 'Dept A']);
        $this->actingAs($this->admin);

        $payload = $this->validInstructorPayload($dept);
        unset(
            $payload['instructor_teaching_pct'],
            $payload['instructor_research_pct'],
            $payload['instructor_service_pct'],
            $payload['instructor_culture_pct'],
            $payload['instructor_other_pct']
        );

        $response = $this->post('/admin/users', $payload);

        $response->assertRedirect('/admin/users');
        $user = User::where('username', 'inst1')->firstOrFail();
        $this->assertDatabaseHas('instructor_profiles', [
            'user_id' => $user->id,
            'teaching_pct' => 0,
            'research_pct' => 0,
            'service_pct' => 0,
            'culture_pct' => 0,
            'other_pct' => 0,
        ]);
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

    public function test_assigning_new_head_is_blocked_when_department_already_has_head(): void
    {
        $dept = Department::create(['name' => 'Handoff Dept']);
        $oldHead = $this->makeUser('oldhead', 'instructor');
        $dept->update(['head_user_id' => $oldHead->id]);
        $this->actingAs($this->admin);

        $response = $this->post('/admin/users', $this->validInstructorPayload($dept, [
            'username' => 'newhead',
            'email' => 'newhead@example.com',
            'roles' => ['instructor', 'executive'],
        ]));

        $response->assertSessionHasErrors('instructor_department_id');
        $this->assertSame($oldHead->id, $dept->fresh()->head_user_id);
        $this->assertDatabaseMissing('users', ['username' => 'newhead']);
    }

    public function test_course_head_department_syncs_profile_without_setting_department_head(): void
    {
        $dept = Department::create(['name' => 'Course Head Dept']);
        $this->actingAs($this->admin);

        $response = $this->post('/admin/users', $this->validInstructorPayload($dept, [
            'username' => 'course-head-user',
            'email' => 'course-head-user@example.com',
            'roles' => ['course_head'],
            'primary_role' => 'course_head',
        ]));

        $response->assertRedirect('/admin/users');
        $user = User::where('username', 'course-head-user')->firstOrFail();
        $this->assertDatabaseHas('instructor_profiles', [
            'user_id' => $user->id,
            'department_id' => $dept->id,
        ]);
        $this->assertDatabaseHas('user_roles', ['user_id' => $user->id, 'role' => 'instructor']);
        $this->assertNull($dept->fresh()->head_user_id);
    }

    public function test_executive_can_be_assigned_department_head(): void
    {
        $dept = Department::create(['name' => 'Executive Head Dept']);
        $this->actingAs($this->admin);

        $response = $this->post('/admin/users', $this->validInstructorPayload($dept, [
            'username' => 'executive-head',
            'email' => 'executive-head@example.com',
            'roles' => ['executive'],
            'primary_role' => 'executive',
        ]));

        $response->assertRedirect('/admin/users');
        $newHead = User::where('username', 'executive-head')->firstOrFail();
        $this->assertSame($newHead->id, $dept->fresh()->head_user_id);
        $this->assertDatabaseHas('user_roles', ['user_id' => $newHead->id, 'role' => 'instructor']);
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

    private function makeInstructorWithProfile(Department $dept, array $profileOverrides = []): User
    {
        $user = User::create([
            'username' => 'existing-inst-' . random_int(1000, 9999),
            'name' => 'Existing Instructor',
            'email' => 'existing-inst-' . random_int(1000, 9999) . '@example.com',
            'employee_id' => 'EMP' . random_int(10000, 99999),
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        UserRole::create(['user_id' => $user->id, 'role' => 'instructor', 'is_primary' => true]);

        InstructorProfile::create(array_merge([
            'user_id' => $user->id,
            'title' => 'อาจารย์',
            'department_id' => $dept->id,
            'academic_degree' => 'ปริญญาโท',
            'employment_type' => 'Full-time',
            'hired_at' => '2026-01-01',
            'teaching_pct' => 50,
            'research_pct' => 25,
            'service_pct' => 10,
            'culture_pct' => 10,
            'other_pct' => 5,
            'teaching_quota' => 0,
            'is_english_passed' => false,
        ], $profileOverrides));

        return $user->fresh(['roles', 'instructorProfile']);
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

    private function validInstructorUpdatePayload(User $user, array $overrides = []): array
    {
        $user = $user->fresh(['roles', 'instructorProfile']);
        $profile = $user->instructorProfile;

        return array_merge([
            'username' => $user->username,
            'prefix' => $user->prefix,
            'name' => $user->name,
            'email' => $user->email,
            'employee_id' => $user->employee_id,
            'roles' => ['instructor'],
            'primary_role' => 'instructor',
            'is_active' => (bool) $user->is_active,
            'instructor_title' => $profile->title,
            'instructor_department_id' => $profile->department_id,
            'instructor_academic_degree' => $profile->academic_degree,
            'instructor_employment_type' => $profile->employment_type,
            'instructor_hired_at' => $profile->hired_at,
            'instructor_teaching_pct' => $profile->teaching_pct,
            'instructor_research_pct' => $profile->research_pct,
            'instructor_service_pct' => $profile->service_pct,
            'instructor_culture_pct' => $profile->culture_pct,
            'instructor_other_pct' => $profile->other_pct,
            'instructor_teaching_quota' => $profile->teaching_quota,
            'instructor_is_english_passed' => $profile->is_english_passed ? 1 : 0,
        ], $overrides);
    }
}
