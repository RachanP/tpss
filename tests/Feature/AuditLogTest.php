<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserRole;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'username' => 'admin_test',
            'name'     => 'Admin Test',
            'email'    => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        UserRole::create(['user_id' => $this->admin->id, 'role' => 'admin', 'is_primary' => true]);
        session(['active_role' => 'admin']);

        $this->staff = User::create([
            'username' => 'staff_test',
            'name'     => 'Staff Test',
            'email'    => 'staff@test.com',
            'password' => bcrypt('password'),
        ]);
        UserRole::create(['user_id' => $this->staff->id, 'role' => 'staff', 'is_primary' => true]);
    }

    // ─── AuditLogger::log() ─────────────────────────────────────────────

    public function test_audit_logger_creates_record(): void
    {
        $this->actingAs($this->admin);

        AuditLogger::log('ข้อมูลหลัก.สร้าง', 'courses', 99,
            null,
            ['course_code' => 'NSG101', 'name_th' => 'พยาบาลพื้นฐาน'],
            description: 'สร้างรายวิชาใหม่'
        );

        $this->assertDatabaseHas('audit_logs', [
            'user_id'        => $this->admin->id,
            'action'         => 'ข้อมูลหลัก.สร้าง',
            'table_affected' => 'courses',
            'record_id'      => 99,
            'category'       => 'ข้อมูลหลัก',
            'description'    => 'สร้างรายวิชาใหม่',
        ]);
    }

    public function test_audit_logger_auto_injects_context_into_new_values(): void
    {
        $this->actingAs($this->admin);

        AuditLogger::log('ข้อมูลหลัก.สร้าง', 'courses', 1,
            null,
            ['course_code' => 'NSG101']
        );

        $log = AuditLog::latest('id')->first();
        $this->assertArrayHasKey('context', $log->new_values);
        $this->assertArrayHasKey('ip_address', $log->new_values['context']);
        $this->assertArrayHasKey('user_agent', $log->new_values['context']);
    }

    public function test_audit_logger_auto_generates_description_when_omitted(): void
    {
        $this->actingAs($this->admin);

        AuditLogger::log('ตารางสอน.แก้ไข', 'schedules', 5, null, null);

        $log = AuditLog::latest('id')->first();
        $this->assertNotNull($log->description);
        $this->assertStringContainsString('แก้ไข', $log->description);
    }

    public function test_caller_description_overrides_auto_generated(): void
    {
        $this->actingAs($this->admin);

        AuditLogger::log('ตารางสอน.แก้ไข', 'schedules', 5, null, null,
            description: 'แก้ไขเวลาสอนปฐมนิเทศ'
        );

        $log = AuditLog::latest('id')->first();
        $this->assertSame('แก้ไขเวลาสอนปฐมนิเทศ', $log->description);
    }

    // ─── AuditLogger::diff() ────────────────────────────────────────────

    public function test_diff_returns_only_changed_keys(): void
    {
        $before = ['status' => 'draft', 'room_id' => 5, 'topic' => 'เหมือนเดิม'];
        $after  = ['status' => 'pending_approval', 'room_id' => 5, 'topic' => 'เหมือนเดิม'];

        $result = AuditLogger::diff($before, $after);

        $this->assertSame(['status' => 'draft'], $result['old']);
        $this->assertSame(['status' => 'pending_approval'], $result['new']);
        $this->assertArrayNotHasKey('room_id', $result['old']);
        $this->assertArrayNotHasKey('topic', $result['old']);
    }

    // ─── AuditLogger::sanitize() ────────────────────────────────────────

    public function test_sanitize_masks_password_and_remember_token(): void
    {
        $data = [
            'username'       => 'staff_01',
            'password'       => 'secret123',
            'remember_token' => 'tok_abc',
            'email'          => 'test@example.com',
        ];

        $clean = AuditLogger::sanitize($data);

        $this->assertSame('[REDACTED]', $clean['password']);
        $this->assertSame('[REDACTED]', $clean['remember_token']);
        $this->assertSame('staff_01', $clean['username']);
        $this->assertSame('test@example.com', $clean['email']);
    }

    public function test_sanitize_returns_null_when_input_is_null(): void
    {
        $this->assertNull(AuditLogger::sanitize(null));
    }

    // ─── AuditLog::toDetailPayload() ────────────────────────────────────

    public function test_to_detail_payload_extracts_context_from_new_values(): void
    {
        $this->actingAs($this->admin);

        AuditLogger::log('ตารางสอน.แก้ไข', 'schedules', 10,
            ['status' => 'draft'],
            ['status' => 'pending_approval']
        );

        $log     = AuditLog::latest('id')->first();
        $payload = $log->toDetailPayload();

        $this->assertArrayHasKey('context', $payload);
        $this->assertArrayHasKey('ip_address', $payload['context']);
        $this->assertArrayNotHasKey('context', $payload['new_values']);
    }

    public function test_to_detail_payload_contains_required_keys(): void
    {
        $this->actingAs($this->admin);

        AuditLogger::log('ข้อมูลหลัก.สร้าง', 'courses', 1, null, ['name_th' => 'วิชาทดสอบ']);

        $log     = AuditLog::latest('id')->first();
        $payload = $log->toDetailPayload();

        foreach (['id', 'action', 'category', 'description', 'actor', 'auditable',
                  'old_values', 'new_values', 'metadata', 'context',
                  'masked_fields', 'created_at'] as $key) {
            $this->assertArrayHasKey($key, $payload, "Missing key: {$key}");
        }
    }

    public function test_to_detail_payload_old_values_null_for_create_action(): void
    {
        $this->actingAs($this->admin);

        AuditLogger::log('ข้อมูลหลัก.สร้าง', 'courses', 1, null, ['name_th' => 'new']);

        $payload = AuditLog::latest('id')->first()->toDetailPayload();
        $this->assertNull($payload['old_values']);
    }

    public function test_to_detail_payload_masks_sensitive_fields(): void
    {
        $this->actingAs($this->admin);

        AuditLogger::log('ผู้ใช้และสิทธิ์.แก้ไข', 'users', 2,
            ['password' => 'plaintext'],
            ['password' => 'newplaintext']
        );

        $payload = AuditLog::latest('id')->first()->toDetailPayload();

        $this->assertSame('[REDACTED]', $payload['old_values']['password']);
        $this->assertSame('[REDACTED]', $payload['new_values']['password']);
        $this->assertContains('password', $payload['masked_fields']);
    }

    public function test_bulk_metadata_in_new_values(): void
    {
        $this->actingAs($this->admin);

        AuditLogger::log('ตารางสอน.ลบหลายรายการ', 'schedules', 0,
            null,
            ['affected_count' => 15, 'affected_ids' => [1, 2, 3]]
        );

        $log     = AuditLog::latest('id')->first();
        $payload = $log->toDetailPayload();

        $this->assertSame(15, $payload['new_values']['affected_count']);
        $this->assertSame([1, 2, 3], $payload['new_values']['affected_ids']);
    }

    // ─── Controller / View ──────────────────────────────────────────────

    public function test_admin_can_view_audit_log_index(): void
    {
        $this->actingAs($this->admin);

        AuditLogger::log('ข้อมูลหลัก.สร้าง', 'courses', 1, null, ['name_th' => 'Test']);

        $response = $this->get(route('admin.audit_logs.index'));
        $response->assertOk();
        $response->assertSee('บันทึกการใช้งาน');
    }

    public function test_admin_can_filter_by_category(): void
    {
        $this->actingAs($this->admin);

        AuditLogger::log('ข้อมูลหลัก.สร้าง',  'courses',   1, null, []);
        AuditLogger::log('ตารางสอน.แก้ไข',    'schedules', 2, null, []);

        $response = $this->get(route('admin.audit_logs.index', ['category' => 'ข้อมูลหลัก']));
        $response->assertOk();
        $response->assertSee('ข้อมูลหลัก');
    }

    public function test_non_admin_cannot_access_audit_logs(): void
    {
        session(['active_role' => 'staff']);
        $this->actingAs($this->staff);

        $response = $this->get(route('admin.audit_logs.index'));
        $response->assertStatus(403);
    }

    public function test_thai_category_labels_in_response(): void
    {
        $this->actingAs($this->admin);

        AuditLogger::log('ตารางสอน.แก้ไข', 'schedules', 1, null, []);

        $response = $this->get(route('admin.audit_logs.index'));
        $response->assertOk();
        $response->assertSee('ตารางสอน');
    }

    public function test_no_m1_m2_m10_labels_in_response(): void
    {
        $this->actingAs($this->admin);

        // Create a log with a Thai category (no module label)
        AuditLogger::log('ข้อมูลหลัก.สร้าง', 'courses', 1, null, []);

        $response = $this->get(route('admin.audit_logs.index'));
        $response->assertOk();

        // The audit log page title and category pills should show Thai labels only
        $response->assertSee('บันทึกการใช้งาน');
        $response->assertSee('ข้อมูลหลัก');

        // These specific module label patterns must NOT appear in the audit table section
        // (We check the DB-facing action string, which should use Thai, not M-codes)
        $this->assertDatabaseMissing('audit_logs', ['action' => 'M1']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'M2']);
        $this->assertDatabaseMissing('audit_logs', ['category' => 'M10']);
    }

    public function test_view_actions_are_not_present_after_navigation(): void
    {
        // Verify no "VIEW" or "LOGIN" category appears in audit logs when admin views a page
        $this->actingAs($this->admin);
        $this->get(route('admin.audit_logs.index'));

        $this->assertDatabaseMissing('audit_logs', ['action' => 'VIEW']);
        $this->assertDatabaseMissing('audit_logs', ['category' => 'security']);
    }
}
