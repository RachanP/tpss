<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Holiday;
use App\Models\User;
use App\Models\UserRole;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * ครอบ Bug 8.3 (log สลับบทบาท), 8.4 (log จัดการวันหยุด/ปิดแจ้งเตือน),
 * 8.5 (mask รหัสผ่านลึกถึง nested) จากรายงานข้อ 8 กลุ่มบันทึกประวัติ
 */
class AuditLogSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'username'  => 'admin_audit',
            'name'      => 'Admin Audit',
            'email'     => 'admin_audit@example.com',
            'password'  => Hash::make('password123'),
            'is_active' => true,
        ]);
        UserRole::create(['user_id' => $this->admin->id, 'role' => 'admin', 'is_primary' => true]);
        session(['active_role' => 'admin']);
    }

    // ---- 8.5 mask รหัสผ่าน nested ------------------------------------------

    public function test_sanitize_masks_password_at_any_depth(): void
    {
        [$clean, $masked] = AuditLogger::sanitizeWithReport([
            'username' => 'someone',
            'user'     => [
                'password' => 'super-secret',
                'profile'  => ['api_token' => 'tok_123'],
            ],
        ]);

        $this->assertSame('[REDACTED]', $clean['user']['password']);
        $this->assertSame('[REDACTED]', $clean['user']['profile']['api_token']);
        $this->assertContains('password', $masked);
        $this->assertContains('api_token', $masked);
    }

    public function test_persisted_log_does_not_leak_nested_password(): void
    {
        $this->actingAs($this->admin);

        AuditLogger::log(
            action: 'ระบบ.ทดสอบ',
            table: 'users',
            recordId: $this->admin->id,
            oldValues: null,
            newValues: ['payload' => ['password' => 'leaky-secret']],
        );

        $log = AuditLog::latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('[REDACTED]', $log->new_values['payload']['password']);
        // ค่าจริงต้องไม่ปรากฏใน JSON ที่บันทึกเลย
        $this->assertStringNotContainsString('leaky-secret', json_encode($log->new_values));
    }

    // ---- 8.3 log สลับบทบาท -------------------------------------------------

    public function test_switching_role_is_audited(): void
    {
        $user = User::create([
            'username'  => 'multi_audit',
            'name'      => 'Multi Audit',
            'email'     => 'multi_audit@example.com',
            'password'  => Hash::make('password123'),
            'is_active' => true,
        ]);
        UserRole::create(['user_id' => $user->id, 'role' => 'course_head', 'is_primary' => true]);
        UserRole::create(['user_id' => $user->id, 'role' => 'instructor', 'is_primary' => false]);

        $this->actingAs($user);
        session(['active_role' => 'course_head']);

        $this->post('/switch-role', ['role' => 'instructor']);

        $this->assertSame('instructor', session('active_role'));
        $this->assertDatabaseHas('audit_logs', [
            'action'    => 'ระบบ.เปลี่ยนบทบาท',
            'user_id'   => $user->id,
            'record_id' => $user->id,
        ]);
    }

    // ---- 8.4 log จัดการวันหยุด + ปิดแจ้งเตือน -------------------------------

    public function test_holiday_create_update_delete_are_audited(): void
    {
        $this->actingAs($this->admin);

        // create
        $this->post(route('admin.settings.holidays.store'), [
            'date' => '2026-12-31',
            'name' => 'วันสิ้นปี',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ตั้งค่าระบบ.สร้าง', 'table_affected' => 'holidays']);

        $holiday = Holiday::where('name', 'วันสิ้นปี')->firstOrFail();

        // update
        $this->put(route('admin.settings.holidays.update', $holiday), [
            'date' => '2026-12-31',
            'name' => 'วันสิ้นปีเก่า',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ตั้งค่าระบบ.แก้ไข', 'table_affected' => 'holidays']);

        // delete
        $this->delete(route('admin.settings.holidays.destroy', $holiday));
        $this->assertDatabaseHas('audit_logs', ['action' => 'ตั้งค่าระบบ.ลบ', 'table_affected' => 'holidays']);
    }

    public function test_dismissing_alerts_is_audited(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('admin.alerts.dismissed'), ['dismissed' => ['rooms']]);

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'ตั้งค่าระบบ.แก้ไข',
            'table_affected' => 'system_settings',
        ]);
    }
}
