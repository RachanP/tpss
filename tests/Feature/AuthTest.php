<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user with basic role
        $this->user = User::create([
            'username' => 'testuser',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        // Assign a role
        UserRole::create([
            'user_id' => $this->user->id,
            'role' => 'staff',
            'is_primary' => true,
        ]);
    }

    public function test_user_can_view_login_form(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertViewIs('auth.login');
    }

    public function test_user_can_login_with_username(): void
    {
        $response = $this->post('/login', [
            'username' => 'testuser',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->user);
        $this->assertEquals('staff', session('active_role'));
    }

    public function test_user_can_login_with_email(): void
    {
        $response = $this->post('/login', [
            'username' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_user_cannot_login_with_incorrect_password(): void
    {
        $response = $this->post('/login', [
            'username' => 'testuser',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->user->update(['is_active' => false]);

        $response = $this->post('/login', [
            'username' => 'testuser',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $this->actingAs($this->user);

        $response = $this->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}
