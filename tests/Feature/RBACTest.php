<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RBACTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_admin_users_page(): void
    {
        $user = User::create([
            'username' => 'staff_user',
            'name' => 'Staff User',
            'email' => 'staff@example.com',
            'password' => Hash::make('password'),
        ]);

        UserRole::create([
            'user_id' => $user->id,
            'role' => 'staff',
            'is_primary' => true,
        ]);

        session(['active_role' => 'staff']);

        $this->actingAs($user);

        // This should be blocked if RBAC is implemented
        $response = $this->get('/admin/users');

        $response->assertStatus(403);
    }

    public function test_admin_can_access_admin_users_page(): void
    {
        $admin = User::create([
            'username' => 'admin_user',
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        UserRole::create([
            'user_id' => $admin->id,
            'role' => 'admin',
            'is_primary' => true,
        ]);

        session(['active_role' => 'admin']);

        $this->actingAs($admin);

        $response = $this->get('/admin/users');

        $response->assertStatus(200);
    }
}
