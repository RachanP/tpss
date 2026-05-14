<?php

namespace Tests\Feature;

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
        $this->actingAs($this->admin);

        $response = $this->post('/admin/users', [
            'username' => 'newuser',
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
            'roles' => ['staff', 'instructor'],
            'primary_role' => 'staff',
            'is_active' => true,
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
}
