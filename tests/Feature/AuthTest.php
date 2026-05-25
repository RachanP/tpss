<?php

namespace Tests\Feature;

use App\Models\AuditLog;
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

    public function test_successful_login_creates_audit_log(): void
    {
        $response = $this->post('/login', [
            'username' => 'testuser',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');

        $log = AuditLog::where('action', 'ระบบ.เข้าสู่ระบบ')
            ->where('table_affected', 'users')
            ->where('record_id', $this->user->id)
            ->first();

        $this->assertNotNull($log, 'Expected audit log for login was not found');
        $this->assertEquals('ระบบ', $log->category);
        $this->assertArrayHasKey('username', $log->new_values);
        $this->assertArrayHasKey('login_via', $log->new_values);
        $this->assertEquals('username', $log->new_values['login_via']);
    }

    public function test_login_via_email_records_email_field(): void
    {
        $this->post('/login', [
            'username' => 'test@example.com',
            'password' => 'password123',
        ])->assertRedirect('/dashboard');

        $log = AuditLog::where('action', 'ระบบ.เข้าสู่ระบบ')
            ->where('record_id', $this->user->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('email', $log->new_values['login_via']);
    }

    public function test_failed_login_does_not_create_audit_log(): void
    {
        $this->post('/login', [
            'username' => 'testuser',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('username');

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_logout_creates_audit_log(): void
    {
        $this->actingAs($this->user);

        $this->post('/logout')->assertRedirect('/login');

        $log = AuditLog::where('action', 'ระบบ.ออกจากระบบ')
            ->where('table_affected', 'users')
            ->where('record_id', $this->user->id)
            ->first();

        $this->assertNotNull($log, 'Expected audit log for logout was not found');
        $this->assertEquals('ระบบ', $log->category);
    }

    public function test_switch_role_creates_audit_log_when_role_changes(): void
    {
        UserRole::create([
            'user_id'    => $this->user->id,
            'role'       => 'instructor',
            'is_primary' => false,
        ]);

        $this->actingAs($this->user)
            ->withSession(['active_role' => 'staff'])
            ->post('/switch-role', ['role' => 'instructor'])
            ->assertRedirect();

        $log = AuditLog::where('action', 'ระบบ.เปลี่ยนบทบาท')
            ->where('record_id', $this->user->id)
            ->first();

        $this->assertNotNull($log, 'Expected audit log for role switch was not found');
        $this->assertEquals('staff',      $log->old_values['active_role']);
        $this->assertEquals('instructor', $log->new_values['active_role']);
    }

    public function test_switch_role_to_same_role_does_not_create_audit_log(): void
    {
        $this->actingAs($this->user)
            ->withSession(['active_role' => 'staff'])
            ->post('/switch-role', ['role' => 'staff'])
            ->assertRedirect();

        $this->assertDatabaseMissing('audit_logs', ['action' => 'ระบบ.เปลี่ยนบทบาท']);
    }
}
