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

    private function createUserWithRole(string $role, string $suffix): User
    {
        $user = User::create([
            'username' => "{$role}_{$suffix}",
            'name' => "{$role} {$suffix}",
            'email' => "{$role}_{$suffix}@example.com",
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $this->assignRole($user, $role, true);

        return $user;
    }

    private function assignRole(User $user, string $role, bool $isPrimary = false): void
    {
        UserRole::create([
            'user_id' => $user->id,
            'role' => $role,
            'is_primary' => $isPrimary,
        ]);
    }

    private function actingAsRole(User $user, string $activeRole): self
    {
        return $this
            ->withSession(['active_role' => $activeRole])
            ->actingAs($user);
    }

    public function test_non_admin_cannot_access_admin_users_page(): void
    {
        $user = $this->createUserWithRole('staff', 'admin_users_denied');

        $this->actingAsRole($user, 'staff');

        // This should be blocked if RBAC is implemented
        $response = $this->get('/admin/users');

        $response->assertStatus(403);
    }

    public function test_admin_can_access_admin_users_page(): void
    {
        $admin = $this->createUserWithRole('admin', 'admin_users_allowed');

        $this->actingAsRole($admin, 'admin');

        $response = $this->get('/admin/users');

        $response->assertStatus(200);
    }

    public function test_each_role_can_access_own_dashboard(): void
    {
        $dashboards = [
            'admin' => '/admin/dashboard',
            'staff' => '/staff/dashboard',
            'course_head' => '/maker/dashboard',
            'executive' => '/approver/dashboard',
            'instructor' => '/lecturer/dashboard',
        ];

        foreach ($dashboards as $role => $dashboard) {
            $user = $this->createUserWithRole($role, 'own_dashboard');

            $this->actingAsRole($user, $role);

            $this->get($dashboard)->assertStatus(200);
        }
    }

    public function test_each_role_is_denied_a_representative_dashboard_it_does_not_own(): void
    {
        $deniedDashboards = [
            'admin' => '/staff/dashboard',
            'staff' => '/admin/dashboard',
            'course_head' => '/approver/dashboard',
            'executive' => '/lecturer/dashboard',
            'instructor' => '/maker/dashboard',
        ];

        foreach ($deniedDashboards as $role => $dashboard) {
            $user = $this->createUserWithRole($role, 'representative_denied');

            $this->actingAsRole($user, $role);

            $this->get($dashboard)->assertStatus(403);
        }
    }

    public function test_non_admin_roles_cannot_access_admin_dashboard(): void
    {
        foreach (['staff', 'course_head', 'executive', 'instructor'] as $role) {
            $user = $this->createUserWithRole($role, 'admin_dashboard_denied');

            $this->actingAsRole($user, $role);

            $this->get('/admin/dashboard')->assertStatus(403);
        }
    }

    public function test_dashboard_remains_auth_only_role_aware_hub(): void
    {
        $dashboards = [
            'admin' => '/admin/dashboard',
            'staff' => '/staff/dashboard',
            'course_head' => '/maker/dashboard',
            'executive' => '/approver/dashboard',
            'instructor' => '/lecturer/dashboard',
        ];

        foreach ($dashboards as $role => $dashboard) {
            $user = $this->createUserWithRole($role, 'hub_redirect');

            $this->actingAsRole($user, $role);

            $this->get('/dashboard')->assertRedirect($dashboard);
        }
    }

    public function test_dashboard_requires_active_role_session(): void
    {
        $admin = $this->createUserWithRole('admin', 'missing_active_role');

        $this->actingAs($admin);

        $this->get('/admin/dashboard')->assertStatus(403);
    }

    public function test_tampered_active_role_not_owned_by_user_is_denied(): void
    {
        $staff = $this->createUserWithRole('staff', 'tampered_role');

        $this->actingAsRole($staff, 'admin');

        $this->get('/admin/dashboard')->assertStatus(403);
    }

    public function test_stale_active_role_after_role_removal_is_denied(): void
    {
        $admin = $this->createUserWithRole('admin', 'stale_role');

        UserRole::where('user_id', $admin->id)
            ->where('role', 'admin')
            ->delete();

        $this->actingAsRole($admin, 'admin');

        $this->get('/admin/dashboard')->assertStatus(403);
    }

    public function test_multi_role_user_can_access_only_the_active_role_dashboard(): void
    {
        $user = $this->createUserWithRole('staff', 'active_role_only');
        $this->assignRole($user, 'instructor');

        $this->actingAsRole($user, 'instructor');
        $this->get('/lecturer/dashboard')->assertStatus(200);
        $this->get('/staff/dashboard')->assertStatus(403);

        $this->actingAsRole($user, 'staff');
        $this->get('/staff/dashboard')->assertStatus(200);
        $this->get('/lecturer/dashboard')->assertStatus(403);
    }
}
