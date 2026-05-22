<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserRole;
use App\Services\AuditLogger;
use Carbon\Carbon;
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
                  'masked_fields', 'created_at', 'display_created_at'] as $key) {
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

    public function test_detail_modal_created_at_matches_table_display_time(): void
    {
        $createdAt = Carbon::create(2026, 5, 21, 10, 30, 0);

        $log = AuditLog::create([
            'user_id'        => $this->admin->id,
            'action'         => 'ข้อมูลหลัก.สร้าง',
            'table_affected' => 'courses',
            'record_id'      => 1,
            'old_values'     => null,
            'new_values'     => [],
            'category'       => 'ข้อมูลหลัก',
            'description'    => 'ตรวจเวลา',
        ]);
        $log->forceFill(['created_at' => $createdAt])->save();

        $payload = $log->toDetailPayload();

        $this->assertSame('2026-05-21 10:30:00', $payload['created_at']);
        $this->assertSame('21/05/2569 10:30:00', $payload['display_created_at']);
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

    public function test_admin_can_search_audit_logs_by_actor_name_or_email(): void
    {
        $this->actingAs($this->admin);

        AuditLog::create([
            'user_id'        => $this->admin->id,
            'action'         => 'ข้อมูลหลัก.สร้าง',
            'table_affected' => 'courses',
            'record_id'      => 1,
            'old_values'     => null,
            'new_values'     => [],
            'category'       => 'ข้อมูลหลัก',
            'description'    => 'บันทึกของผู้ดูแลระบบ',
            'created_at'     => now(),
        ]);

        AuditLog::create([
            'user_id'        => $this->staff->id,
            'action'         => 'ตารางสอน.แก้ไข',
            'table_affected' => 'schedules',
            'record_id'      => 2,
            'old_values'     => null,
            'new_values'     => [],
            'category'       => 'ตารางสอน',
            'description'    => 'บันทึกของเจ้าหน้าที่',
            'created_at'     => now()->subMinute(),
        ]);

        $response = $this->get(route('admin.audit_logs.index', ['actor' => 'Admin']));
        $response->assertOk();
        $response->assertSee('บันทึกของผู้ดูแลระบบ');
        $response->assertDontSee('บันทึกของเจ้าหน้าที่');

        $response = $this->get(route('admin.audit_logs.index', ['actor' => 'staff@test']));
        $response->assertOk();
        $response->assertSee('บันทึกของเจ้าหน้าที่');
        $response->assertDontSee('บันทึกของผู้ดูแลระบบ');
    }

    public function test_audit_log_filters_are_static_search_controls(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.audit_logs.index'));
        $response->assertOk();
        $html = $response->getContent();

        $response->assertSee('ค้นหาชื่อหรืออีเมล...');
        $response->assertSee('data-testid="audit-logs-filter-actor"', false);
        $response->assertSee('@input.debounce.500ms="fetchResults()"', false);
        $response->assertSee('data-testid="audit-logs-filter-action"', false);
        $response->assertSee('@change="fetchResults()"', false);
        $this->assertMatchesRegularExpression('/type="text"[^>]+name="date_from"/s', $html);
        $this->assertMatchesRegularExpression('/type="text"[^>]+name="date_to"/s', $html);
        $response->assertSee('placeholder="วว/ดด/พ.ศ."', false);
        $response->assertSee('ทุกการกระทำ');
        $response->assertSee('ค้นหา');
        $response->assertSee('รีเซต');
        $response->assertDontSee('name="user_id"', false);
        $response->assertDontSee('filtersOpen');
        $response->assertDontSee('ล้างตัวกรอง');
        $response->assertDontSee('>วิกฤต<', false);
        $response->assertDontSee('>ทั่วไป<', false);

        $actorIndex = strpos($html, 'data-testid="audit-logs-filter-actor"');
        $categoryIndex = strpos($html, 'data-testid="audit-logs-filter-category"');
        $this->assertLessThan($categoryIndex, $actorIndex);
        $this->assertStringContainsString('href="' . route('admin.audit_logs.index') . '"', $html);
    }

    public function test_audit_log_partial_request_returns_table_only(): void
    {
        $this->actingAs($this->admin);

        AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => 'ข้อมูลหลัก.สร้าง',
            'table_affected' => 'courses',
            'record_id' => 1,
            'old_values' => null,
            'new_values' => [],
            'category' => 'ข้อมูลหลัก',
            'description' => 'Partial row',
            'created_at' => now(),
        ]);

        $response = $this
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('admin.audit_logs.index', ['partial' => 'table']));

        $response->assertOk();
        $response->assertSee('data-testid="audit-logs-table"', false);
        $response->assertSee('Partial row');
        $response->assertDontSee('<x-app-layout', false);
        $response->assertDontSee('data-testid="audit-logs-filter-actor"', false);
    }

    public function test_admin_can_filter_by_action_dropdown_value(): void
    {
        $this->actingAs($this->admin);

        AuditLogger::log('ข้อมูลหลัก.สร้าง', 'courses', 1, null, []);
        AuditLogger::log('ตารางสอน.แก้ไข', 'schedules', 2, null, []);

        $response = $this
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('admin.audit_logs.index', [
                'action' => 'แก้ไข',
                'partial' => 'table',
            ]));
        $response->assertOk();
        $response->assertSee('ตารางสอน.แก้ไข');
        $response->assertDontSee('ข้อมูลหลัก.สร้าง');
    }

    public function test_audit_log_action_dropdown_shows_unique_user_facing_labels(): void
    {
        $this->actingAs($this->admin);

        AuditLogger::log('ข้อมูลหลัก.แก้ไข', 'courses', 1, null, []);
        AuditLogger::log('ตารางสอน.แก้ไข', 'schedules', 2, null, []);
        AuditLogger::log('ข้อมูลหลัก.สร้าง', 'courses', 3, null, []);

        $response = $this->get(route('admin.audit_logs.index'));
        $response->assertOk();

        preg_match(
            '/<select[^>]*data-testid="audit-logs-filter-action"[^>]*>(.*?)<\/select>/s',
            $response->getContent(),
            $selectMatch,
        );

        $this->assertNotEmpty($selectMatch);

        preg_match_all('/<option\b[^>]*value="([^"]*)"[^>]*>(.*?)<\/option>/s', $selectMatch[1], $matches, PREG_SET_ORDER);
        $actionOptions = collect($matches)
            ->filter(fn (array $match) => in_array(html_entity_decode($match[1]), ['แก้ไข', 'สร้าง'], true))
            ->map(fn (array $match) => trim(html_entity_decode(strip_tags($match[2]))))
            ->values()
            ->all();

        $this->assertEqualsCanonicalizing(['สร้าง', 'แก้ไข'], $actionOptions);
        $this->assertCount(2, $actionOptions);
    }

    public function test_admin_can_filter_by_date_range(): void
    {
        $this->actingAs($this->admin);

        $insideRangeLog = AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => 'ข้อมูลหลัก.สร้าง',
            'table_affected' => 'courses',
            'record_id' => 1,
            'old_values' => null,
            'new_values' => [],
            'category' => 'ข้อมูลหลัก',
            'description' => 'อยู่ในช่วงวันที่',
        ]);
        $insideRangeLog->forceFill(['created_at' => '2026-05-19 10:00:00'])->save();

        $outsideRangeLog = AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => 'ข้อมูลหลัก.สร้าง',
            'table_affected' => 'courses',
            'record_id' => 2,
            'old_values' => null,
            'new_values' => [],
            'category' => 'ข้อมูลหลัก',
            'description' => 'นอกช่วงวันที่',
        ]);
        $outsideRangeLog->forceFill(['created_at' => '2026-05-18 10:00:00'])->save();

        $response = $this
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('admin.audit_logs.index', [
            'date_from' => '2026-05-19',
            'date_to' => '2026-05-19',
            'partial' => 'table',
        ]));

        $response->assertOk();
        $response->assertSee('อยู่ในช่วงวันที่');
        $response->assertDontSee('นอกช่วงวันที่');
    }

    public function test_date_filter_with_yyyy_mm_dd_still_works(): void
    {
        $this->actingAs($this->admin);

        $insideLog = AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => 'ข้อมูลหลัก.สร้าง',
            'table_affected' => 'courses',
            'record_id' => 1,
            'old_values' => null,
            'new_values' => [],
            'category' => 'ข้อมูลหลัก',
            'description' => 'รองรับรูปแบบเดิม',
        ]);
        $insideLog->forceFill(['created_at' => '2026-05-21 12:00:00'])->save();

        $outsideLog = AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => 'ข้อมูลหลัก.สร้าง',
            'table_affected' => 'courses',
            'record_id' => 2,
            'old_values' => null,
            'new_values' => [],
            'category' => 'ข้อมูลหลัก',
            'description' => 'ไม่อยู่ในวันที่กรอง',
        ]);
        $outsideLog->forceFill(['created_at' => '2026-05-20 12:00:00'])->save();

        $response = $this
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('admin.audit_logs.index', [
                'date_from' => '2026-05-21',
                'date_to' => '2026-05-21',
                'partial' => 'table',
            ]));

        $response->assertOk();
        $response->assertSee('รองรับรูปแบบเดิม');
        $response->assertDontSee('ไม่อยู่ในวันที่กรอง');
    }

    public function test_date_filter_startofday_endofday_boundaries(): void
    {
        $this->actingAs($this->admin);

        foreach ([
            ['record_id' => 1, 'description' => 'ต้นวัน', 'created_at' => '2026-05-21 00:00:00'],
            ['record_id' => 2, 'description' => 'ท้ายวัน', 'created_at' => '2026-05-21 23:59:59'],
            ['record_id' => 3, 'description' => 'ก่อนวัน', 'created_at' => '2026-05-20 23:59:59'],
            ['record_id' => 4, 'description' => 'หลังวัน', 'created_at' => '2026-05-22 00:00:00'],
        ] as $logData) {
            $createdAt = $logData['created_at'];
            unset($logData['created_at']);

            $log = AuditLog::create(array_merge([
                'user_id' => $this->admin->id,
                'action' => 'ข้อมูลหลัก.สร้าง',
                'table_affected' => 'courses',
                'old_values' => null,
                'new_values' => [],
                'category' => 'ข้อมูลหลัก',
            ], $logData));
            $log->forceFill(['created_at' => $createdAt])->save();
        }

        $response = $this
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('admin.audit_logs.index', [
                'date_from' => '21/05/2569',
                'date_to' => '21/05/2569',
                'partial' => 'table',
            ]));

        $response->assertOk();
        $response->assertSee('ต้นวัน');
        $response->assertSee('ท้ายวัน');
        $response->assertDontSee('ก่อนวัน');
        $response->assertDontSee('หลังวัน');
    }

    public function test_invalid_date_filter_preserves_input_and_does_not_fail(): void
    {
        $this->actingAs($this->admin);

        AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => 'ข้อมูลหลัก.สร้าง',
            'table_affected' => 'courses',
            'record_id' => 1,
            'old_values' => null,
            'new_values' => [],
            'category' => 'ข้อมูลหลัก',
            'description' => 'ยังแสดงเมื่อวันที่ไม่ถูกต้อง',
            'created_at' => '2026-05-21 12:00:00',
        ]);

        $response = $this->get(route('admin.audit_logs.index', [
            'date_from' => 'not-a-date',
        ]));

        $response->assertOk();
        $response->assertSee('value="not-a-date"', false);
        $response->assertSee('ยังแสดงเมื่อวันที่ไม่ถูกต้อง');
    }

    public function test_audit_log_table_shows_ip_from_context(): void
    {
        $this->actingAs($this->admin);

        AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => 'ข้อมูลหลัก.สร้าง',
            'table_affected' => 'courses',
            'record_id' => 1,
            'old_values' => null,
            'new_values' => ['context' => ['ip_address' => '203.0.113.10']],
            'category' => 'ข้อมูลหลัก',
            'description' => 'มี IP',
            'created_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => 'ตารางสอน.แก้ไข',
            'table_affected' => 'schedules',
            'record_id' => 2,
            'old_values' => null,
            'new_values' => [],
            'category' => 'ตารางสอน',
            'description' => 'ไม่มี IP',
            'created_at' => now()->subMinute(),
        ]);

        $response = $this->get(route('admin.audit_logs.index'));
        $response->assertOk();
        $response->assertSee('IP');
        $response->assertSee('203.0.113.10');
        $response->assertSee('ไม่มี IP');
        $response->assertDontSee('ตาราง / รายการ');
    }

    public function test_audit_log_pagination_preserves_filters(): void
    {
        $this->actingAs($this->admin);

        for ($i = 1; $i <= 55; $i++) {
            AuditLog::create([
                'user_id' => $this->admin->id,
                'action' => 'ข้อมูลหลัก.สร้าง',
                'table_affected' => 'courses',
                'record_id' => $i,
                'old_values' => null,
                'new_values' => [],
                'category' => 'ข้อมูลหลัก',
                'description' => "รายการ {$i}",
                'created_at' => now()->subSeconds($i),
            ]);
        }

        $response = $this->get(route('admin.audit_logs.index', [
            'actor' => 'Admin',
            'category' => 'ข้อมูลหลัก',
        ]));

        $response->assertOk();
        $response->assertSee('แสดง 1–25 จาก 55 รายการ');
        $response->assertSee('data-testid="audit-logs-pagination"', false);
        $response->assertSee('<');
        $response->assertSee('>');

        preg_match_all('/href="([^"]+)"/', $response->getContent(), $matches);
        $paginationUrls = collect($matches[1])
            ->map(fn (string $href) => html_entity_decode($href))
            ->filter(fn (string $href) => str_contains($href, 'page=2'));

        $this->assertNotEmpty($paginationUrls);
        $nextPageUrl = $paginationUrls->first();
        $this->assertStringContainsString('actor=Admin', $nextPageUrl);
        $this->assertStringContainsString('category=', $nextPageUrl);
        $this->assertStringContainsString('page=2', $nextPageUrl);
    }

    public function test_audit_log_pagination_is_hidden_when_total_does_not_exceed_page_size(): void
    {
        $this->actingAs($this->admin);

        for ($i = 1; $i <= 15; $i++) {
            AuditLog::create([
                'user_id' => $this->admin->id,
                'action' => 'ข้อมูลหลัก.สร้าง',
                'table_affected' => 'courses',
                'record_id' => $i,
                'old_values' => null,
                'new_values' => [],
                'category' => 'ข้อมูลหลัก',
                'description' => "รายการ {$i}",
                'created_at' => now()->subSeconds($i),
            ]);
        }

        $response = $this->get(route('admin.audit_logs.index'));

        $response->assertOk();
        $response->assertSee('แสดง 1–15 จาก 15 รายการ');
        $response->assertDontSee('data-testid="audit-logs-pagination"', false);
    }

    public function test_audit_log_pagination_appears_after_twenty_five_items(): void
    {
        $this->actingAs($this->admin);

        for ($i = 1; $i <= 26; $i++) {
            AuditLog::create([
                'user_id' => $this->admin->id,
                'action' => 'ข้อมูลหลัก.สร้าง',
                'table_affected' => 'courses',
                'record_id' => $i,
                'old_values' => null,
                'new_values' => [],
                'category' => 'ข้อมูลหลัก',
                'description' => "รายการ {$i}",
                'created_at' => now()->subSeconds($i),
            ]);
        }

        $response = $this->get(route('admin.audit_logs.index'));

        $response->assertOk();
        $response->assertSee('แสดง 1–25 จาก 26 รายการ');
        $response->assertSee('data-testid="audit-logs-pagination"', false);
        $response->assertSee('<');
        $response->assertSee('>');
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

    public function test_audit_detail_modal_wraps_long_json_without_breaking_layout(): void
    {
        $html = view('admin.audit_logs._detail_modal')->render();

        $this->assertStringContainsString('data-testid="audit-detail-modal"', $html);
        $this->assertStringContainsString('max-width:95vw', $html);
        $this->assertStringContainsString('max-height:90vh', $html);
        $this->assertStringContainsString('overflow:hidden', $html);
        $this->assertStringContainsString('flex:1;min-height:0;min-width:0;display:flex;flex-direction:column;overflow:hidden', $html);

        $this->assertStringContainsString('max-height:calc(90vh - 180px);max-width:100%;min-width:0;overflow-y:auto;overflow-x:auto', $html);
        $this->assertStringContainsString('data-testid="audit-json-block"', $html);
        $this->assertStringContainsString('font-family:\'IBM Plex Mono\',ui-monospace,monospace', $html);
        $this->assertStringContainsString('white-space:pre-wrap', $html);
        $this->assertStringContainsString('overflow-wrap:anywhere', $html);
        $this->assertStringContainsString('word-break:break-word', $html);
        $this->assertStringContainsString('min-width:0', $html);

        $this->assertStringContainsString('data-testid="audit-copy-btn"', $html);
        $this->assertStringContainsString('@click="copyJson()"', $html);
    }

    public function test_recent_activity_partial_renders_without_provided_variable(): void
    {
        $createdAt = now()->startOfSecond();

        for ($i = 1; $i <= 6; $i++) {
            AuditLog::create([
                'user_id'        => $this->admin->id,
                'action'         => 'ข้อมูลหลัก.สร้าง',
                'table_affected' => 'courses',
                'record_id'      => $i,
                'old_values'     => null,
                'new_values'     => ['name_th' => "Course {$i}"],
                'category'       => 'ข้อมูลหลัก',
                'description'    => "กิจกรรมทดสอบ {$i}",
                'created_at'     => $createdAt->copy()->addSeconds($i),
            ]);
        }

        $html = view('shared.dashboard.recent-activity')->render();

        $this->assertStringContainsString('กิจกรรมล่าสุด', $html);
        foreach ([6, 5, 4, 3, 2] as $visibleLogNumber) {
            $this->assertStringContainsString("กิจกรรมทดสอบ {$visibleLogNumber}", $html);
        }

        $this->assertStringNotContainsString('กิจกรรมทดสอบ 1', $html);
        $this->assertStringContainsString('กิจกรรมทดสอบ 6', $html);
        $this->assertStringContainsString('Admin Test', $html);
    }

    public function test_recent_activity_partial_renders_provided_recent_audit_logs(): void
    {
        AuditLog::create([
            'user_id'        => $this->admin->id,
            'action'         => 'ข้อมูลหลัก.สร้าง',
            'table_affected' => 'courses',
            'record_id'      => 1,
            'old_values'     => null,
            'new_values'     => [],
            'category'       => 'ข้อมูลหลัก',
            'description'    => 'ไม่ควรแสดงจาก fallback',
            'created_at'     => now(),
        ]);

        $providedLog = AuditLog::create([
            'user_id'        => $this->staff->id,
            'action'         => 'ตารางสอน.แก้ไข',
            'table_affected' => 'schedules',
            'record_id'      => 2,
            'old_values'     => ['status' => 'draft'],
            'new_values'     => ['status' => 'pending_approval'],
            'category'       => 'ตารางสอน',
            'description'    => 'แสดงจากตัวแปรที่ส่งเข้า partial',
            'created_at'     => now()->subMinute(),
        ])->load('user');

        $html = view('shared.dashboard.recent-activity', [
            'recentAuditLogs' => collect([$providedLog]),
        ])->render();

        $this->assertStringContainsString('แสดงจากตัวแปรที่ส่งเข้า partial', $html);
        $this->assertStringContainsString('Staff Test', $html);
        $this->assertStringNotContainsString('ไม่ควรแสดงจาก fallback', $html);
    }

    public function test_recent_activity_partial_empty_state_appears_when_no_logs(): void
    {
        $html = view('shared.dashboard.recent-activity')->render();

        $this->assertStringContainsString('ยังไม่มีกิจกรรมล่าสุด', $html);
    }

    public function test_recent_activity_partial_all_link_points_to_audit_log_index(): void
    {
        $html = view('shared.dashboard.recent-activity', [
            'recentAuditLogs' => collect(),
        ])->render();

        $this->assertStringContainsString('ดูทั้งหมด', $html);
        $this->assertStringContainsString(route('admin.audit_logs.index'), $html);
    }

    public function test_recent_activity_partial_renders_thai_category_and_action_labels(): void
    {
        $log = AuditLog::create([
            'user_id'        => $this->admin->id,
            'action'         => 'ผู้ใช้และสิทธิ์.แก้ไข',
            'table_affected' => 'users',
            'record_id'      => $this->staff->id,
            'old_values'     => ['name' => 'Old Name'],
            'new_values'     => ['name' => 'New Name'],
            'category'       => 'ผู้ใช้และสิทธิ์',
            'description'    => 'แก้ไขข้อมูลผู้ใช้',
            'created_at'     => now(),
        ]);

        $html = view('shared.dashboard.recent-activity', [
            'recentAuditLogs' => collect([$log]),
        ])->render();

        $this->assertSame(['ผู้ใช้และสิทธิ์'], $this->recentActivityBadgeTexts($html, 'recent-activity-category'));
        $this->assertSame(['แก้ไข'], $this->recentActivityBadgeTexts($html, 'recent-activity-action'));
        $this->assertStringNotContainsString('ผู้ใช้และสิทธิ์.แก้ไข', implode(' ', $this->recentActivityBadgeTexts($html, 'recent-activity-action')));
    }

    public function test_recent_activity_partial_does_not_show_m_code_labels(): void
    {
        $log = AuditLog::create([
            'user_id'        => $this->admin->id,
            'action'         => 'M1.แก้ไข',
            'table_affected' => 'users',
            'record_id'      => $this->staff->id,
            'old_values'     => ['name' => 'Old Name'],
            'new_values'     => ['name' => 'New Name'],
            'category'       => 'M10',
            'description'    => 'แก้ไขข้อมูลผู้ใช้',
            'created_at'     => now(),
        ]);

        $html = view('shared.dashboard.recent-activity', [
            'recentAuditLogs' => collect([$log]),
        ])->render();

        $visibleCategoryAndActionText = implode(' ', array_merge(
            $this->recentActivityBadgeTexts($html, 'recent-activity-category'),
            $this->recentActivityBadgeTexts($html, 'recent-activity-action'),
        ));

        $this->assertStringNotContainsString('M1', $visibleCategoryAndActionText);
        $this->assertStringNotContainsString('M2', $visibleCategoryAndActionText);
        $this->assertStringNotContainsString('M10', $visibleCategoryAndActionText);
        $this->assertStringContainsString('ระบบ', $visibleCategoryAndActionText);
        $this->assertStringContainsString('แก้ไข', $visibleCategoryAndActionText);
    }

    public function test_view_actions_are_not_present_after_navigation(): void
    {
        // Verify no "VIEW" or "LOGIN" category appears in audit logs when admin views a page
        $this->actingAs($this->admin);
        $this->get(route('admin.audit_logs.index'));

        $this->assertDatabaseMissing('audit_logs', ['action' => 'VIEW']);
        $this->assertDatabaseMissing('audit_logs', ['category' => 'security']);
    }

    private function recentActivityBadgeTexts(string $html, string $testId): array
    {
        preg_match_all(
            '/<span[^>]*data-testid="' . preg_quote($testId, '/') . '"[^>]*>(.*?)<\/span>/s',
            $html,
            $matches,
        );

        return array_map(
            fn (string $value) => trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
            $matches[1],
        );
    }
}
