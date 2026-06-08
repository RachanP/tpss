<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * ครอบ Bug 8.1 (force logout เมื่อบัญชีถูกปิด) + 8.2 (กัน role แปลกปลอม)
 * จากรายงาน "Phase UX Refactor, Bug Fix, Important Features" ข้อ 8 กลุ่มความปลอดภัย
 */
class UserRoleSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'username' => 'admin_sec',
            'name'     => 'Admin Security',
            'email'    => 'admin_sec@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        UserRole::create([
            'user_id'    => $this->admin->id,
            'role'       => 'admin',
            'is_primary' => true,
        ]);

        session(['active_role' => 'admin']);
    }

    // ---- 8.2 กัน role แปลกปลอม ---------------------------------------------

    public function test_creating_user_strips_unknown_role(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post('/admin/users', [
            'username'     => 'mixeduser',
            'name'         => 'Mixed Roles',
            'email'        => 'mixed@example.com',
            'password'     => 'password123',
            'roles'        => ['staff', 'superadmin'],
            'primary_role' => 'staff',
            'is_active'    => true,
        ]);

        $response->assertRedirect('/admin/users');
        $this->assertDatabaseHas('user_roles', ['role' => 'staff']);
        // role แปลกปลอมต้องไม่ถูกบันทึก
        $this->assertDatabaseMissing('user_roles', ['role' => 'superadmin']);
    }

    public function test_creating_user_with_only_unknown_role_fails_validation(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post('/admin/users', [
            'username'     => 'badrole',
            'name'         => 'Bad Role',
            'email'        => 'badrole@example.com',
            'password'     => 'password123',
            'roles'        => ['superadmin'],
            'primary_role' => 'superadmin',
            'is_active'    => true,
        ]);

        $response->assertSessionHasErrors('roles');
        $this->assertDatabaseMissing('users', ['username' => 'badrole']);
        $this->assertDatabaseMissing('user_roles', ['role' => 'superadmin']);
    }

    public function test_switch_role_rejects_role_user_does_not_have(): void
    {
        $user = User::create([
            'username'  => 'multi',
            'name'      => 'Multi Role',
            'email'     => 'multi@example.com',
            'password'  => Hash::make('password123'),
            'is_active' => true,
        ]);
        UserRole::create(['user_id' => $user->id, 'role' => 'course_head', 'is_primary' => true]);

        $this->actingAs($user);
        session(['active_role' => 'course_head']);

        $this->post('/switch-role', ['role' => 'admin']);

        // ต้องไม่สลับไปเป็น admin เพราะ user ไม่มีบทบาทนั้น
        $this->assertSame('course_head', session('active_role'));
    }

    // ---- 8.1 force logout เมื่อบัญชีถูกปิด ----------------------------------

    public function test_deactivated_user_is_logged_out_on_next_request(): void
    {
        $this->actingAs($this->admin);
        $this->admin->update(['is_active' => false]);

        $response = $this->get('/admin/users');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_deactivated_user_gets_401_on_json_request(): void
    {
        $this->actingAs($this->admin);
        $this->admin->update(['is_active' => false]);

        $response = $this->getJson('/admin/users');

        $response->assertStatus(401);
        $this->assertGuest();
    }
}
