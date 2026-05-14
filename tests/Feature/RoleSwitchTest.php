<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user with two roles
        $this->user = User::create([
            'username' => 'multirole',
            'name' => 'Multi Role User',
            'email' => 'multi@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Role 1: Staff (Primary)
        UserRole::create([
            'user_id' => $this->user->id,
            'role' => 'staff',
            'is_primary' => true,
        ]);

        // Role 2: Instructor
        UserRole::create([
            'user_id' => $this->user->id,
            'role' => 'instructor',
            'is_primary' => false,
        ]);
    }

    public function test_user_can_switch_role(): void
    {
        $this->actingAs($this->user);
        session(['active_role' => 'staff']);

        $response = $this->post('/switch-role', [
            'role' => 'instructor',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertEquals('instructor', session('active_role'));
    }

    public function test_user_cannot_switch_to_unauthorized_role(): void
    {
        $this->actingAs($this->user);
        session(['active_role' => 'staff']);

        // Try switching to admin (which user doesn't have)
        $response = $this->post('/switch-role', [
            'role' => 'admin',
        ]);

        $response->assertRedirect('/dashboard');
        // Role should still be staff
        $this->assertEquals('staff', session('active_role'));
    }
}
