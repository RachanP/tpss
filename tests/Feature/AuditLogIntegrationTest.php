<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ActivityType;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseRole;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\InstructorProfile;
use App\Models\LocationType;
use App\Models\Room;
use App\Models\StudentGroup;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuditLogIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD_ACTION = 'ผู้ใช้และสิทธิ์.เปลี่ยนรหัสผ่าน';

    private User $admin;
    private int  $seq = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makeAdmin();
    }

    // ── Scheduling Window ─────────────────────────────────────────────

    public function test_open_scheduling_window_creates_audit_log_with_phase_change(): void
    {
        $year = $this->makeYear(['phase' => 'preparation']);
        $this->seedCriticals();
        $head = $this->makeInstructor();
        $this->makeCourse(['head_instructor_id' => $head->id]);

        $this->actingAsAdmin()
            ->patch(route('admin.settings.scheduling.open', $year))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'ตั้งค่าระบบ.เปิดช่วงจัดตาราง',
            'table_affected' => 'academic_years',
            'record_id'      => $year->id,
            'category'       => 'ตั้งค่าระบบ',
        ]);

        $log = AuditLog::where('action', 'ตั้งค่าระบบ.เปิดช่วงจัดตาราง')->first();
        $this->assertSame('preparation', $log->old_values['phase']);
        $this->assertSame('scheduling',  $log->new_values['phase']);

        $syncLog = $this->latestLog('รายวิชาและผู้รับผิดชอบ.ซิงก์ข้อมูล', 'course_offerings');
        $this->assertSame('รายวิชาและผู้รับผิดชอบ', $syncLog->category);
        $this->assertSame(1, $syncLog->new_values['offerings_created']);
        $this->assertSame(1, $syncLog->new_values['offerings_synced']);
    }

    public function test_close_scheduling_window_creates_audit_log_with_phase_change(): void
    {
        $year = $this->makeYear(['phase' => 'scheduling']);

        $this->actingAsAdmin()
            ->patch(route('admin.settings.scheduling.close', $year))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'ตั้งค่าระบบ.ปิดช่วงจัดตาราง',
            'table_affected' => 'academic_years',
            'record_id'      => $year->id,
        ]);

        $log = AuditLog::where('action', 'ตั้งค่าระบบ.ปิดช่วงจัดตาราง')->first();
        $this->assertSame('scheduling',  $log->old_values['phase']);
        $this->assertSame('preparation', $log->new_values['phase']);
    }

    public function test_blocked_open_scheduling_does_not_create_audit_log(): void
    {
        // No ActivityType / LocationType → criticals block opening
        $year = $this->makeYear(['phase' => 'preparation']);

        $this->actingAsAdmin()
            ->patch(route('admin.settings.scheduling.open', $year))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('audit_logs', 0);
    }

    // ── Course CRUD ───────────────────────────────────────────────────

    public function test_create_course_creates_audit_log(): void
    {
        $curriculum = $this->makeCurriculum();
        $dept       = $this->makeDepartment();
        $head       = $this->makeInstructor();

        $this->actingAsAdmin()
            ->post(route('admin.courses.store'), [
                'course_code'                 => 'NUR999',
                'name_th'                     => 'วิชาทดสอบ',
                'curriculum_id'               => $curriculum->id,
                'department_id'               => $dept->id,
                'head_instructor_id'          => $head->id,
                'status'                      => 'active',
                'academic_level'              => 'undergraduate',
                'default_year_level'          => 1,
                'default_semester'            => 1,
                'credits'                     => 3,
                'lecture_hours'               => 3,
                'lab_hours'                   => 0,
                'self_study_hours'            => 6,
                'capacity'                    => 30,
                'requires_practicum_rotation' => false,
                'is_required'                 => true,
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'ข้อมูลหลัก.สร้าง',
            'table_affected' => 'courses',
            'category'       => 'ข้อมูลหลัก',
        ]);

        $log = AuditLog::where('action', 'ข้อมูลหลัก.สร้าง')->first();
        $this->assertNull($log->old_values);
        $this->assertSame('NUR999', $log->new_values['course_code']);
        $this->assertSame('วิชาทดสอบ', $log->new_values['name_th']);
    }

    public function test_update_course_logs_only_changed_fields(): void
    {
        $course = $this->makeFullCourse();

        // Only change name_th
        $this->actingAsAdmin()
            ->put(route('admin.courses.update', $course), array_merge(
                $this->coursePayload($course),
                ['name_th' => 'ชื่อวิชาใหม่']
            ));

        $log = AuditLog::where('action', 'ข้อมูลหลัก.แก้ไข')->first();
        $this->assertNotNull($log);

        // old_values should contain only the changed field
        $this->assertArrayHasKey('name_th', $log->old_values);
        $this->assertArrayNotHasKey('credits', $log->old_values ?? []);

        // new_values should reflect the new name
        $this->assertSame('ชื่อวิชาใหม่', $log->new_values['name_th']);
    }

    public function test_update_course_with_no_changes_does_not_create_audit_log(): void
    {
        $course = $this->makeFullCourse();

        // Submit with identical values
        $this->actingAsAdmin()
            ->put(route('admin.courses.update', $course), $this->coursePayload($course));

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_delete_course_creates_audit_log_with_snapshot(): void
    {
        $course = $this->makeFullCourse();
        $code   = $course->course_code;
        $nameTh = $course->name_th;

        $this->actingAsAdmin()
            ->delete(route('admin.courses.destroy', $course));

        $log = AuditLog::where('action', 'ข้อมูลหลัก.ลบ')->first();
        $this->assertNotNull($log);
        $this->assertSame($code,   $log->old_values['course_code']);
        $this->assertSame($nameTh, $log->old_values['name_th']);
        $this->assertArrayHasKey('context', $log->new_values);
    }

    // ── User CRUD ─────────────────────────────────────────────────────

    public function test_create_user_creates_audit_log(): void
    {
        $dept = $this->makeDepartment();

        $this->actingAsAdmin()
            ->post(route('admin.users.store'), [
                'username'                    => 'newuser',
                'name'                        => 'ผู้ใช้ใหม่',
                'email'                       => 'new@test.example',
                'password'                    => 'password123',
                'roles'                       => ['staff'],
                'primary_role'                => 'staff',
                'is_active'                   => true,
            ]);

        $log = AuditLog::where('action', 'ผู้ใช้และสิทธิ์.สร้าง')->first();
        $this->assertNotNull($log);
        $this->assertSame('ผู้ใช้และสิทธิ์', $log->category);
        $this->assertNull($log->old_values);
        $this->assertSame('newuser', $log->new_values['username']);
        // Password must not appear in new_values
        $this->assertArrayNotHasKey('password', $log->new_values);
    }

    public function test_update_user_logs_role_change_in_old_new_values(): void
    {
        // Use a simple role pair that doesn't require instructor profile (staff → admin)
        $user = $this->makeUserWithRole('staff');

        $this->actingAsAdmin()
            ->put(route('admin.users.update', $user), [
                'username'     => $user->username,
                'name'         => $user->name,
                'email'        => $user->email,
                'roles'        => ['admin'],
                'primary_role' => 'admin',
                'is_active'    => true,
            ]);

        $log = AuditLog::where('action', 'ผู้ใช้และสิทธิ์.แก้ไข')->first();
        $this->assertNotNull($log);
        $this->assertContains('staff', $log->old_values['roles']);
        $this->assertContains('admin', $log->new_values['roles']);
    }

    public function test_role_only_update_does_not_create_password_audit_log(): void
    {
        $user = $this->makeUserWithRole('staff');

        $this->actingAsAdmin()
            ->put(route('admin.users.update', $user), [
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => ['admin'],
                'primary_role' => 'admin',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('audit_logs', [
            'action' => self::PASSWORD_ACTION,
            'table_affected' => 'users',
            'record_id' => $user->id,
        ]);
    }

    public function test_update_user_logs_primary_role_change_even_when_role_list_is_same(): void
    {
        $user = $this->makeUserWithRole('staff');
        UserRole::create(['user_id' => $user->id, 'role' => 'admin', 'is_primary' => false]);

        $this->actingAsAdmin()
            ->put(route('admin.users.update', $user), [
                'username'     => $user->username,
                'name'         => $user->name,
                'email'        => $user->email,
                'roles'        => ['staff', 'admin'],
                'primary_role' => 'admin',
                'is_active'    => true,
            ])
            ->assertRedirect();

        $log = AuditLog::where('action', 'ผู้ใช้และสิทธิ์.แก้ไข')->first();
        $this->assertNotNull($log);
        $this->assertSame('staff', $log->old_values['primary_role']);
        $this->assertSame('admin', $log->new_values['primary_role']);
    }

    public function test_update_user_with_no_changes_does_not_create_audit_log(): void
    {
        $user = $this->makeUserWithRole('staff');

        $this->actingAsAdmin()
            ->put(route('admin.users.update', $user), [
                'username'     => $user->username,
                'name'         => $user->name,
                'email'        => $user->email,
                'roles'        => ['staff'],
                'primary_role' => 'staff',
                'is_active'    => true,
            ]);

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_admin_changing_another_user_password_creates_password_audit_log(): void
    {
        $user = $this->makeUserWithRole('staff');

        $this->actingAsAdmin()
            ->put(route('admin.users.update', $user), [
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'changed-password-123',
                'roles' => ['staff'],
                'primary_role' => 'staff',
                'is_active' => true,
            ])
            ->assertRedirect();

        $log = $this->latestLog('ผู้ใช้และสิทธิ์.เปลี่ยนรหัสผ่าน', 'users');
        $this->assertSame($user->id, $log->record_id);
        $this->assertSame([], $log->old_values);
        $this->assertTrue($log->new_values['password_changed']);
        $this->assertSame($user->id, $log->new_values['target_user']['id']);
        $this->assertSame($user->name, $log->new_values['target_user']['name']);
        $this->assertSame($user->email, $log->new_values['target_user']['email']);
        $this->assertArrayHasKey('context', $log->new_values);
        $this->assertNoSensitivePasswordFields($log->old_values);
        $this->assertNoSensitivePasswordFields($log->new_values);

        $this->assertSame(1, AuditLog::where('action', self::PASSWORD_ACTION)
            ->where('table_affected', 'users')
            ->where('record_id', $user->id)
            ->count());
    }

    public function test_admin_changing_password_for_multiple_users_creates_audit_log_per_target(): void
    {
        $first = $this->makeUserWithRole('staff');
        $second = $this->makeUserWithRole('staff');

        foreach ([$first, $second] as $index => $user) {
            $this->actingAsAdmin()
                ->put(route('admin.users.update', $user), [
                    'username' => $user->username,
                    'name' => $user->name,
                    'email' => $user->email,
                    'password' => "changed-password-{$index}-123",
                    'roles' => ['staff'],
                    'primary_role' => 'staff',
                    'is_active' => true,
                ])
                ->assertRedirect();
        }

        $logs = AuditLog::where('action', 'ผู้ใช้และสิทธิ์.เปลี่ยนรหัสผ่าน')
            ->where('table_affected', 'users')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $logs);
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $logs->pluck('new_values.target_user.id')->all(),
        );
    }

    public function test_admin_updating_user_with_blank_password_does_not_create_password_audit_log(): void
    {
        $user = $this->makeUserWithRole('staff');

        $this->actingAsAdmin()
            ->put(route('admin.users.update', $user), [
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'password' => '',
                'roles' => ['staff'],
                'primary_role' => 'staff',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'ผู้ใช้และสิทธิ์.เปลี่ยนรหัสผ่าน',
            'table_affected' => 'users',
            'record_id' => $user->id,
        ]);
    }

    public function test_role_update_with_same_password_does_not_log_password_change(): void
    {
        $user = $this->makeUserWithRole('staff');
        $originalHash = $user->password;

        $this->actingAsAdmin()
            ->put(route('admin.users.update', $user), [
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'password',
                'roles' => ['admin'],
                'primary_role' => 'admin',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertSame($originalHash, $user->fresh()->password);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => self::PASSWORD_ACTION,
            'table_affected' => 'users',
            'record_id' => $user->id,
        ]);
    }

    public function test_admin_editing_own_account_without_password_does_not_log_password_change(): void
    {
        $this->actingAsAdmin()
            ->put(route('admin.users.update', $this->admin), [
                'username' => $this->admin->username,
                'name' => $this->admin->name,
                'email' => $this->admin->email,
                'password' => '',
                'roles' => ['admin'],
                'primary_role' => 'admin',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('audit_logs', [
            'action' => self::PASSWORD_ACTION,
            'table_affected' => 'users',
            'record_id' => $this->admin->id,
        ]);
    }

    public function test_password_change_audit_payload_has_no_raw_password_or_hash(): void
    {
        $user = $this->makeUserWithRole('staff');
        $rawPassword = 'changed-password-123';

        $this->actingAsAdmin()
            ->put(route('admin.users.update', $user), [
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'password' => $rawPassword,
                'roles' => ['staff'],
                'primary_role' => 'staff',
                'is_active' => true,
            ])
            ->assertRedirect();

        $log = $this->latestLog(self::PASSWORD_ACTION, 'users');
        $payloadJson = json_encode([$log->old_values, $log->new_values], JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString($rawPassword, $payloadJson);
        $this->assertStringNotContainsString($user->fresh()->password, $payloadJson);
        $this->assertNoSensitivePasswordFields($log->old_values);
        $this->assertNoSensitivePasswordFields($log->new_values);
    }

    public function test_admin_updating_role_and_password_creates_profile_and_password_audits(): void
    {
        $user = $this->makeUserWithRole('staff');

        $this->actingAsAdmin()
            ->put(route('admin.users.update', $user), [
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'changed-password-123',
                'roles' => ['staff', 'admin'],
                'primary_role' => 'admin',
                'is_active' => true,
            ])
            ->assertRedirect();

        $profileLog = $this->latestLog('ผู้ใช้และสิทธิ์.แก้ไข', 'users');
        $passwordLog = $this->latestLog('ผู้ใช้และสิทธิ์.เปลี่ยนรหัสผ่าน', 'users');

        $this->assertSame($user->id, $profileLog->record_id);
        $this->assertSame($user->id, $passwordLog->record_id);
        $this->assertSame('staff', $profileLog->old_values['primary_role']);
        $this->assertSame('admin', $profileLog->new_values['primary_role']);
        $this->assertTrue($passwordLog->new_values['password_changed']);
        $this->assertNoSensitivePasswordFields($profileLog->old_values);
        $this->assertNoSensitivePasswordFields($profileLog->new_values);
        $this->assertNoSensitivePasswordFields($passwordLog->old_values);
        $this->assertNoSensitivePasswordFields($passwordLog->new_values);
    }

    public function test_admin_password_validation_failure_does_not_create_audit_log(): void
    {
        $user = $this->makeUserWithRole('staff');

        $this->actingAsAdmin()
            ->put(route('admin.users.update', $user), [
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'short',
                'roles' => ['staff'],
                'primary_role' => 'staff',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_toggle_status_deactivate_creates_audit_log(): void
    {
        $user = $this->makeUserWithRole('staff', isActive: true);

        $this->actingAsAdmin()
            ->patch(route('admin.users.toggle', $user));

        $log = AuditLog::where('action', 'ผู้ใช้และสิทธิ์.เปลี่ยนสถานะ')->first();
        $this->assertNotNull($log);
        $this->assertTrue($log->old_values['is_active']);
        $this->assertFalse($log->new_values['is_active']);
        $this->assertStringContainsString('ปิดใช้งาน', $log->description);
    }

    public function test_toggle_status_activate_creates_audit_log(): void
    {
        $user = $this->makeUserWithRole('staff', isActive: false);

        $this->actingAsAdmin()
            ->patch(route('admin.users.toggle', $user));

        $log = AuditLog::where('action', 'ผู้ใช้และสิทธิ์.เปลี่ยนสถานะ')->first();
        $this->assertNotNull($log);
        $this->assertFalse($log->old_values['is_active']);
        $this->assertTrue($log->new_values['is_active']);
        $this->assertStringContainsString('เปิดใช้งาน', $log->description);
    }

    public function test_destroy_user_creates_audit_log_with_snapshot(): void
    {
        $user = $this->makeUserWithRole('staff');
        $name = $user->name;
        $uname = $user->username;

        $this->actingAsAdmin()
            ->delete(route('admin.users.destroy', $user));

        $log = AuditLog::where('action', 'ผู้ใช้และสิทธิ์.ลบ')->first();
        $this->assertNotNull($log);
        $this->assertSame($name,  $log->old_values['name']);
        $this->assertSame($uname, $log->old_values['username']);
        // new_values only contains context (ip/ua) injected by the logger
        $this->assertArrayHasKey('context', $log->new_values);
        $this->assertStringContainsString($name, $log->description);
    }

    public function test_curriculum_crud_creates_audit_logs(): void
    {
        $this->actingAsAdmin()
            ->post(route('admin.curriculums.store'), $this->curriculumPayload([
                'name' => 'หลักสูตรตรวจสอบ Audit',
            ]))
            ->assertRedirect();

        $curriculum = Curriculum::where('name', 'หลักสูตรตรวจสอบ Audit')->firstOrFail();
        $createLog = $this->latestLog('ข้อมูลหลัก.สร้าง', 'curriculums');
        $this->assertSame('ข้อมูลหลัก', $createLog->category);
        $this->assertNull($createLog->old_values);
        $this->assertSame('หลักสูตรตรวจสอบ Audit', $createLog->new_values['name']);

        $this->actingAsAdmin()
            ->put(route('admin.curriculums.update', $curriculum), $this->curriculumPayload([
                'name' => $curriculum->name,
                'effective_year' => $curriculum->effective_year,
                'total_credits_required' => 130,
            ]))
            ->assertRedirect();

        $updateLog = $this->latestLog('ข้อมูลหลัก.แก้ไข', 'curriculums');
        $this->assertSame(['total_credits_required'], array_keys($updateLog->old_values));
        $this->assertSame(120, $updateLog->old_values['total_credits_required']);
        $this->assertSame(130, $updateLog->new_values['total_credits_required']);

        $this->makeCourse(['curriculum_id' => $curriculum->id]);
        $this->actingAsAdmin()
            ->delete(route('admin.curriculums.destroy', $curriculum), ['confirm_cascade' => 1])
            ->assertRedirect();

        $deleteLog = $this->latestLog('ข้อมูลหลัก.ลบ', 'curriculums');
        $this->assertSame('หลักสูตรตรวจสอบ Audit', $deleteLog->old_values['name']);
        $this->assertSame(1, $deleteLog->new_values['deleted_course_count']);
    }

    public function test_department_crud_creates_audit_logs(): void
    {
        $this->actingAsAdmin()
            ->post(route('admin.departments.store'), ['name' => 'ภาควิชา Audit'])
            ->assertRedirect();

        $department = Department::where('name', 'ภาควิชา Audit')->firstOrFail();
        $createLog = $this->latestLog('ข้อมูลหลัก.สร้าง', 'departments');
        $this->assertSame('ข้อมูลหลัก', $createLog->category);
        $this->assertSame('ภาควิชา Audit', $createLog->new_values['name']);

        $this->actingAsAdmin()
            ->put(route('admin.departments.update', $department), ['name' => 'ภาควิชา Audit ใหม่'])
            ->assertRedirect();

        $updateLog = $this->latestLog('ข้อมูลหลัก.แก้ไข', 'departments');
        $this->assertSame(['name'], array_keys($updateLog->old_values));
        $this->assertSame('ภาควิชา Audit', $updateLog->old_values['name']);
        $this->assertSame('ภาควิชา Audit ใหม่', $updateLog->new_values['name']);

        $this->actingAsAdmin()
            ->delete(route('admin.departments.destroy', $department->fresh()))
            ->assertRedirect();

        $deleteLog = $this->latestLog('ข้อมูลหลัก.ลบ', 'departments');
        $this->assertSame('ภาควิชา Audit ใหม่', $deleteLog->old_values['name']);
    }

    public function test_room_and_location_type_crud_create_audit_logs(): void
    {
        $this->actingAsAdmin()
            ->post(route('admin.location_types.store'), [
                'name' => 'ประเภทห้อง Audit',
                'requires_capacity' => 1,
            ])
            ->assertRedirect();

        $locationType = LocationType::where('name', 'ประเภทห้อง Audit')->firstOrFail();
        $this->assertSame('ประเภทห้อง Audit', $this->latestLog('ข้อมูลหลัก.สร้าง', 'location_types')->new_values['name']);

        $this->actingAsAdmin()
            ->put(route('admin.location_types.update', $locationType), [
                'name' => 'ประเภทห้อง Audit',
                'requires_capacity' => 0,
            ])
            ->assertRedirect();

        $locationUpdateLog = $this->latestLog('ข้อมูลหลัก.แก้ไข', 'location_types');
        $this->assertSame(['requires_capacity'], array_keys($locationUpdateLog->old_values));
        $this->assertTrue($locationUpdateLog->old_values['requires_capacity']);
        $this->assertFalse($locationUpdateLog->new_values['requires_capacity']);

        $this->actingAsAdmin()
            ->post(route('admin.rooms.store'), [
                'room_code' => 'LAB-201',
                'room_name' => 'ห้องปฏิบัติการ 201',
                'building' => 'อาคาร 1',
                'capacity' => 40,
                'location_type_id' => $locationType->id,
                'status' => 'active',
                'equipment_type' => 'projector,bed',
            ])
            ->assertRedirect();

        $room = Room::where('room_code', 'LAB-201')->firstOrFail();
        $this->assertSame('LAB-201', $this->latestLog('ข้อมูลหลัก.สร้าง', 'rooms')->new_values['room_code']);

        $this->actingAsAdmin()
            ->put(route('admin.rooms.update', $room), [
                'room_code' => 'LAB-201',
                'room_name' => 'ห้องปฏิบัติการ 201',
                'building' => 'อาคาร 1',
                'capacity' => 45,
                'location_type_id' => $locationType->id,
                'status' => 'active',
                'equipment_type' => 'projector,bed',
            ])
            ->assertRedirect();

        $roomUpdateLog = $this->latestLog('ข้อมูลหลัก.แก้ไข', 'rooms');
        $this->assertSame(['capacity'], array_keys($roomUpdateLog->old_values));
        $this->assertSame(40, $roomUpdateLog->old_values['capacity']);
        $this->assertSame(45, $roomUpdateLog->new_values['capacity']);

        $this->actingAsAdmin()
            ->delete(route('admin.rooms.destroy', $room->fresh()))
            ->assertRedirect();
        $this->assertSame('LAB-201', $this->latestLog('ข้อมูลหลัก.ลบ', 'rooms')->old_values['room_code']);

        $this->actingAsAdmin()
            ->delete(route('admin.location_types.destroy', $locationType->fresh()))
            ->assertRedirect();
        $this->assertSame('ประเภทห้อง Audit', $this->latestLog('ข้อมูลหลัก.ลบ', 'location_types')->old_values['name']);
    }

    public function test_activity_type_crud_creates_audit_logs(): void
    {
        $this->actingAsAdmin()
            ->post(route('admin.activity_types.store'), [
                'name' => 'กิจกรรม Audit',
                'color_code' => '#336699',
                'category' => 'lecture',
            ])
            ->assertRedirect();

        $activityType = ActivityType::where('name', 'กิจกรรม Audit')->firstOrFail();
        $this->assertSame('กิจกรรม Audit', $this->latestLog('ข้อมูลหลัก.สร้าง', 'activity_types')->new_values['name']);

        $this->actingAsAdmin()
            ->put(route('admin.activity_types.update', $activityType), [
                'name' => 'กิจกรรม Audit ใหม่',
                'color_code' => '#336699',
                'category' => 'lecture',
            ])
            ->assertRedirect();

        $updateLog = $this->latestLog('ข้อมูลหลัก.แก้ไข', 'activity_types');
        $this->assertSame(['name'], array_keys($updateLog->old_values));
        $this->assertSame('กิจกรรม Audit', $updateLog->old_values['name']);
        $this->assertSame('กิจกรรม Audit ใหม่', $updateLog->new_values['name']);

        $this->actingAsAdmin()
            ->delete(route('admin.activity_types.destroy', $activityType->fresh()))
            ->assertRedirect();

        $deleteLog = $this->latestLog('ข้อมูลหลัก.ลบ', 'activity_types');
        $this->assertSame('กิจกรรม Audit ใหม่', $deleteLog->old_values['name']);
    }

    public function test_academic_year_create_and_update_use_master_data_category(): void
    {
        $this->actingAsAdmin()
            ->post(route('admin.settings.years.store'), [
                'name' => '2570',
                'semester' => 1,
                'start_date' => '2027-08-01',
                'end_date' => '2027-12-31',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $year = AcademicYear::where('name', '2570')->where('semester', 1)->firstOrFail();
        $createLog = $this->latestLog('ข้อมูลหลัก.สร้าง', 'academic_years');
        $this->assertSame('ข้อมูลหลัก', $createLog->category);
        $this->assertSame('2570', $createLog->new_values['name']);

        $this->actingAsAdmin()
            ->put(route('admin.settings.years.update', $year), [
                'name' => '2570',
                'semester' => 1,
                'start_date' => '2027-08-15',
                'end_date' => '2027-12-31',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $updateLog = $this->latestLog('ข้อมูลหลัก.แก้ไข', 'academic_years');
        $this->assertSame(['start_date'], array_keys($updateLog->old_values));
        $this->assertSame('2027-08-01', $updateLog->old_values['start_date']);
        $this->assertSame('2027-08-15', $updateLog->new_values['start_date']);
    }

    public function test_academic_year_dates_accept_thai_buddhist_input(): void
    {
        $this->actingAsAdmin()
            ->post(route('admin.settings.years.store'), [
                'name' => '2571',
                'semester' => 2,
                'start_date' => '23/06/2569',
                'end_date' => '24/06/2569',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('academic_years', [
            'name' => '2571',
            'semester' => 2,
            'start_date' => '2026-06-23',
            'end_date' => '2026-06-24',
        ]);
    }

    public function test_instructor_master_data_update_logs_profile_fields_only_when_changed(): void
    {
        $instructor = $this->makeInstructor();
        $profile = $instructor->instructorProfile;
        $department = $this->makeDepartment(['name' => 'ภาควิชาใหม่สำหรับอาจารย์']);

        $this->actingAsAdmin()
            ->put(route('admin.instructors.update', $instructor->id), [
                'name' => $instructor->name,
                'prefix' => $instructor->prefix ?? 'อ.',
                'employee_id' => $instructor->employee_id,
                'department_id' => $department->id,
                'title' => $profile->title,
                'academic_degree' => $profile->academic_degree ?? 'ปริญญาโท',
                'employment_type' => $profile->employment_type ?? 'พนักงานมหาวิทยาลัย',
                'hired_at' => $profile->hired_at,
                'is_english_passed' => 0,
                'teaching_pct' => 45,
                'research_pct' => 25,
                'service_pct' => 10,
                'culture_pct' => 10,
                'other_pct' => 10,
            ])
            ->assertRedirect();

        $log = $this->latestLog('ข้อมูลหลัก.แก้ไข', 'instructor_profiles');
        $this->assertSame('ข้อมูลหลัก', $log->category);
        $this->assertArrayHasKey('department_id', $log->old_values);
        $this->assertArrayHasKey('teaching_pct', $log->old_values);
        $this->assertArrayNotHasKey('roles', $log->old_values);
        $this->assertSame($department->id, $log->new_values['department_id']);
        $this->assertSame(45, $log->new_values['teaching_pct']);
    }

    public function test_master_data_instructor_hired_date_accepts_thai_buddhist_input(): void
    {
        $instructor = $this->makeInstructor();
        $department = $this->makeDepartment(['name' => 'Thai Date Master Data Department']);

        $this->actingAsAdmin()
            ->put(route('admin.instructors.update', $instructor->id), [
                'name' => $instructor->name,
                'prefix' => $instructor->prefix ?? 'อ.',
                'employee_id' => $instructor->employee_id,
                'department_id' => $department->id,
                'title' => 'อาจารย์',
                'academic_degree' => 'ปริญญาโท',
                'employment_type' => 'พนักงานมหาวิทยาลัย',
                'hired_at' => '23/06/2569',
                'is_english_passed' => 0,
                'teaching_pct' => 50,
                'research_pct' => 20,
                'service_pct' => 10,
                'culture_pct' => 10,
                'other_pct' => 10,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('instructor_profiles', [
            'user_id' => $instructor->id,
            'hired_at' => '2026-06-23',
        ]);
    }

    public function test_no_op_and_validation_failure_do_not_create_phase_2b_logs(): void
    {
        $activityType = ActivityType::create([
            'name' => 'ไม่เปลี่ยนแปลง',
            'color_code' => '#336699',
            'category' => 'lecture',
        ]);

        $this->actingAsAdmin()
            ->put(route('admin.activity_types.update', $activityType), [
                'name' => $activityType->name,
                'color_code' => $activityType->color_code,
                'category' => $activityType->category,
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('audit_logs', 0);

        $this->actingAsAdmin()
            ->post(route('admin.activity_types.store'), [
                'color_code' => '#336699',
                'category' => 'lecture',
            ])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_course_offering_practicum_update_logs_changed_fields_and_no_op_is_skipped(): void
    {
        $head = $this->makeCourseHead();
        $offering = $this->makeOffering($head);

        $this->actingAsCourseHead($head)
            ->put(route('maker.course_offerings.update', $offering), [
                'requires_practicum_rotation' => 1,
                'practicum_note' => 'หมุนเวียนแหล่งฝึก',
            ])
            ->assertRedirect();

        $log = $this->latestLog('รายวิชาและผู้รับผิดชอบ.แก้ไข', 'course_offerings');
        $this->assertSame('รายวิชาและผู้รับผิดชอบ', $log->category);
        $this->assertEqualsCanonicalizing(
            ['requires_practicum_rotation', 'practicum_note'],
            array_keys($log->old_values)
        );
        $this->assertFalse($log->old_values['requires_practicum_rotation']);
        $this->assertTrue($log->new_values['requires_practicum_rotation']);
        $this->assertSame($offering->id, $log->new_values['course_offering_id']);

        $this->actingAsCourseHead($head)
            ->put(route('maker.course_offerings.update', $offering->fresh()), [
                'requires_practicum_rotation' => 1,
                'practicum_note' => 'หมุนเวียนแหล่งฝึก',
            ])
            ->assertRedirect();

        $this->assertSame(1, AuditLog::where('action', 'รายวิชาและผู้รับผิดชอบ.แก้ไข')
            ->where('table_affected', 'course_offerings')
            ->count());
    }

    public function test_offering_instructor_add_role_update_and_remove_are_audited(): void
    {
        $head = $this->makeCourseHead();
        $instructor = $this->makeInstructor();
        $offering = $this->makeOffering($head);
        $roleA = $this->makeCourseRole('ผู้สอนบรรยาย');
        $roleB = $this->makeCourseRole('ผู้สอนปฏิบัติ');

        $this->actingAsCourseHead($head)
            ->post(route('maker.course_offerings.instructors.store', $offering), [
                'user_id' => $instructor->id,
                'course_role_id' => $roleA->id,
            ])
            ->assertRedirect();

        $createLog = $this->latestLog('รายวิชาและผู้รับผิดชอบ.สร้าง', 'course_offering_instructors');
        $this->assertSame($instructor->id, $createLog->new_values['user_id']);
        $this->assertSame($roleA->id, $createLog->new_values['course_role_id']);

        $this->actingAsCourseHead($head)
            ->patch(route('maker.course_offerings.instructors.role', [$offering, $instructor]), [
                'course_role_id' => $roleB->id,
            ])
            ->assertRedirect();

        $updateLog = $this->latestLog('รายวิชาและผู้รับผิดชอบ.แก้ไข', 'course_offering_instructors');
        $this->assertSame($roleA->id, $updateLog->old_values['course_role_id']);
        $this->assertSame($roleB->id, $updateLog->new_values['course_role_id']);

        $this->actingAsCourseHead($head)
            ->patch(route('maker.course_offerings.instructors.role', [$offering, $instructor]), [
                'course_role_id' => $roleB->id,
            ])
            ->assertRedirect();

        $this->assertSame(1, AuditLog::where('action', 'รายวิชาและผู้รับผิดชอบ.แก้ไข')
            ->where('table_affected', 'course_offering_instructors')
            ->count());

        $this->actingAsCourseHead($head)
            ->delete(route('maker.course_offerings.instructors.destroy', [$offering, $instructor]))
            ->assertRedirect();

        $deleteLog = $this->latestLog('รายวิชาและผู้รับผิดชอบ.ลบ', 'course_offering_instructors');
        $this->assertSame($instructor->id, $deleteLog->old_values['user_id']);
        $this->assertSame($roleB->id, $deleteLog->old_values['course_role_id']);
    }

    public function test_student_group_crud_and_bulk_actions_are_audited(): void
    {
        $head = $this->makeCourseHead();
        $offering = $this->makeOffering($head, ['total_student_count' => 40]);

        $this->actingAsCourseHead($head)
            ->post(route('maker.course_offerings.student_groups.store', $offering), [
                'group_code' => 'A1',
                'student_count' => 10,
                'color_code' => '#2563eb',
            ])
            ->assertRedirect();

        $group = StudentGroup::where('course_offering_id', $offering->id)->where('group_code', 'A1')->firstOrFail();
        $createLog = $this->latestLog('รายวิชาและผู้รับผิดชอบ.สร้าง', 'student_groups');
        $this->assertSame('A1', $createLog->new_values['group_code']);

        $this->actingAsCourseHead($head)
            ->put(route('maker.course_offerings.student_groups.update', [$offering, $group]), [
                'group_code' => 'A1',
                'student_count' => 12,
                'color_code' => '#2563eb',
            ])
            ->assertRedirect();

        $updateLog = $this->latestLog('รายวิชาและผู้รับผิดชอบ.แก้ไข', 'student_groups');
        $this->assertSame(['student_count'], array_keys($updateLog->old_values));
        $this->assertSame(10, $updateLog->old_values['student_count']);
        $this->assertSame(12, $updateLog->new_values['student_count']);

        $this->actingAsCourseHead($head)
            ->delete(route('maker.course_offerings.student_groups.destroy', [$offering, $group->fresh()]))
            ->assertRedirect();

        $deleteLog = $this->latestLog('รายวิชาและผู้รับผิดชอบ.ลบ', 'student_groups');
        $this->assertSame('A1', $deleteLog->old_values['group_code']);

        $this->actingAsCourseHead($head)
            ->post(route('maker.course_offerings.student_groups.bulk_store', $offering), [
                'group_prefix' => 'B',
                'start_number' => 1,
                'group_count' => 2,
                'group_counts' => [10, 10],
            ])
            ->assertRedirect();

        $bulkCreateLog = $this->latestLog('รายวิชาและผู้รับผิดชอบ.สร้าง', 'student_groups');
        $this->assertSame(2, $bulkCreateLog->new_values['affected_count']);
        $this->assertSame(['B1', 'B2'], $bulkCreateLog->new_values['sample_group_codes']);

        $bulkIds = StudentGroup::where('course_offering_id', $offering->id)
            ->whereIn('group_code', ['B1', 'B2'])
            ->pluck('id')
            ->all();

        $this->actingAsCourseHead($head)
            ->delete(route('maker.course_offerings.student_groups.bulk_destroy', $offering), [
                'group_ids' => $bulkIds,
            ])
            ->assertRedirect();

        $bulkDeleteLog = $this->latestLog('รายวิชาและผู้รับผิดชอบ.ลบ', 'student_groups');
        $this->assertSame(2, $bulkDeleteLog->new_values['affected_count']);
        $this->assertSame(['B1', 'B2'], $bulkDeleteLog->old_values['sample_group_codes']);
    }

    public function test_validation_unauthorized_and_phase_blocked_course_management_actions_do_not_log(): void
    {
        $head = $this->makeCourseHead();
        $otherHead = $this->makeCourseHead();
        $offering = $this->makeOffering($head);

        $this->actingAsCourseHead($head)
            ->post(route('maker.course_offerings.student_groups.store', $offering), [
                'student_count' => 10,
            ])
            ->assertSessionHasErrors('group_code');

        $this->actingAsCourseHead($otherHead)
            ->put(route('maker.course_offerings.update', $offering), [
                'requires_practicum_rotation' => 1,
                'practicum_note' => 'ไม่ได้รับสิทธิ์',
            ])
            ->assertForbidden();

        $blockedOffering = $this->makeOffering($head, [], ['phase' => 'preparation']);
        $this->actingAsCourseHead($head)
            ->put(route('maker.course_offerings.update', $blockedOffering), [
                'requires_practicum_rotation' => 1,
                'practicum_note' => 'ยังไม่เปิดช่วง',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_course_pool_responsibility_changes_log_under_course_management_category_and_locked_course_skips(): void
    {
        $course = $this->makeFullCourse();
        $newHead = $this->makeInstructor();
        $responsible = $this->makeInstructor();
        $role = $this->makeCourseRole('ผู้รับผิดชอบร่วม');

        $this->actingAsAdmin()
            ->put(route('admin.courses.update', $course), array_merge($this->coursePayload($course), [
                'head_instructor_id' => $newHead->id,
                'instructor_ids' => [$responsible->id],
                'instructor_role_ids' => [$responsible->id => $role->id],
            ]))
            ->assertRedirect();

        $log = $this->latestLog('รายวิชาและผู้รับผิดชอบ.แก้ไข', 'course_instructors');
        $this->assertSame('รายวิชาและผู้รับผิดชอบ', $log->category);
        $this->assertSame($course->head_instructor_id, $log->old_values['head_instructor_id']);
        $this->assertSame($newHead->id, $log->new_values['head_instructor_id']);
        $this->assertSame($responsible->id, $log->new_values['responsible_instructors'][0]['user_id']);

        $lockedCourse = $this->makeFullCourse();
        $this->makeOffering($this->makeCourseHead(), [], ['phase' => 'scheduling'], $lockedCourse);

        $beforeCount = AuditLog::where('category', 'รายวิชาและผู้รับผิดชอบ')->count();
        $this->actingAsAdmin()
            ->put(route('admin.courses.update', $lockedCourse), array_merge($this->coursePayload($lockedCourse), [
                'head_instructor_id' => $newHead->id,
                'instructor_ids' => [$responsible->id],
                'instructor_role_ids' => [$responsible->id => $role->id],
            ]))
            ->assertRedirect();

        $this->assertSame($beforeCount, AuditLog::where('category', 'รายวิชาและผู้รับผิดชอบ')->count());
    }

    // ── Phase 3A: Bulk / Settings Writes ─────────────────────────────

    public function test_import_users_creates_one_aggregate_audit_log_without_passwords(): void
    {
        $csv = implode("\n", [
            'username,email,name,password,roles,primary_role',
            'csv_user_1,csv1@test.example,CSV User 1,password123,staff,staff',
            'csv_user_2,csv2@test.example,CSV User 2,password456,staff,staff',
            '',
        ]);

        $this->actingAsAdmin()
            ->post(route('admin.users.import'), ['csv_file' => $this->csvFile($csv)])
            ->assertRedirect();

        $log = $this->latestLog('ผู้ใช้และสิทธิ์.นำเข้า CSV', 'users');
        $this->assertSame('ผู้ใช้และสิทธิ์', $log->category);
        $this->assertSame(2, $log->new_values['success_count']);
        $this->assertSame(2, $log->new_values['created_count']);
        $this->assertSame(0, $log->new_values['updated_count']);
        $this->assertSame(['csv_user_1', 'csv_user_2'], $log->new_values['sample_usernames']);
        $this->assertArrayHasKey('context', $log->new_values);
        $this->assertArrayNotHasKey('password', $log->new_values);
        $this->assertArrayNotHasKey('password_hash', $log->new_values);
    }

    public function test_invalid_user_import_does_not_create_audit_log(): void
    {
        $this->actingAsAdmin()
            ->post(route('admin.users.import'), [
                'csv_file' => $this->csvFile("username,email,name,password,roles\nbad,bad@test.example,Bad,password123,staff\n"),
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_import_rooms_creates_one_aggregate_audit_log_with_counts_and_samples(): void
    {
        LocationType::create(['name' => 'ห้องเรียน']);
        Room::create([
            'room_code' => 'OLD-1',
            'room_name' => 'ห้องเดิม',
            'location_type_id' => LocationType::where('name', 'ห้องเรียน')->value('id'),
            'status' => 'active',
        ]);

        $csv = implode("\n", [
            'room_code,room_name,location_type_name,status',
            'OLD-1,ห้องเดิมปรับปรุง,ห้องเรียน,active',
            'NEW-1,ห้องใหม่,ห้องเรียน,active',
            '',
        ]);

        $this->actingAsAdmin()
            ->post(route('admin.rooms.import'), [
                'csv_file' => $this->csvFile($csv),
                'update_on_duplicate' => '1',
            ])
            ->assertRedirect();

        $log = $this->latestLog('ข้อมูลหลัก.นำเข้า CSV', 'rooms');
        $this->assertSame(2, $log->new_values['success_count']);
        $this->assertSame(1, $log->new_values['created_count']);
        $this->assertSame(1, $log->new_values['updated_count']);
        $this->assertSame(['OLD-1', 'NEW-1'], $log->new_values['sample_room_codes']);
        $this->assertTrue($log->new_values['update_on_duplicate']);
        $this->assertArrayHasKey('context', $log->new_values);
    }

    public function test_invalid_room_import_does_not_create_audit_log(): void
    {
        $this->actingAsAdmin()
            ->post(route('admin.rooms.import'), [
                'csv_file' => $this->csvFile("room_code,room_name\nRM-01,ห้องทดสอบ\n"),
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_empty_import_file_does_not_create_audit_log(): void
    {
        $this->actingAsAdmin()
            ->post(route('admin.rooms.import'), ['csv_file' => $this->csvFile('')])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_import_courses_creates_one_aggregate_audit_log_with_counts_and_samples(): void
    {
        $curriculum = $this->makeCurriculum();
        $department = $this->makeDepartment();
        $head = $this->makeInstructor();

        $csv = implode("\n", [
            'course_code,name_th,curriculum_name,department_name,head_instructor_employee_id,course_type,credits,lecture_hours,lab_hours,self_study_hours,capacity,default_year_level,default_semester,requires_practicum_rotation,status',
            "CSV101,วิชา CSV 1,{$curriculum->name},{$department->name},{$head->employee_id},theory,3,3,0,6,30,1,1,0,active",
            "CSV102,วิชา CSV 2,{$curriculum->name},{$department->name},{$head->employee_id},theory,3,3,0,6,30,1,1,0,active",
            '',
        ]);

        $this->actingAsAdmin()
            ->post(route('admin.courses.import'), ['csv_file' => $this->csvFile($csv)])
            ->assertRedirect();

        $log = $this->latestLog('ข้อมูลหลัก.นำเข้า CSV', 'courses');
        $this->assertSame(2, $log->new_values['success_count']);
        $this->assertSame(2, $log->new_values['created_count']);
        $this->assertSame(0, $log->new_values['updated_count']);
        $this->assertSame(['CSV101', 'CSV102'], $log->new_values['sample_course_codes']);
        $this->assertArrayHasKey('context', $log->new_values);
    }

    public function test_invalid_course_import_does_not_create_audit_log(): void
    {
        $this->actingAsAdmin()
            ->post(route('admin.courses.import'), [
                'csv_file' => $this->csvFile("course_code,name_th,credits\nCSV101,วิชา CSV,3\n"),
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_curriculum_clone_creates_audit_log_with_cloned_course_count(): void
    {
        $curriculum = $this->makeCurriculum();
        $this->makeCourse(['curriculum_id' => $curriculum->id, 'course_code' => 'CLONE101']);
        $this->makeCourse(['curriculum_id' => $curriculum->id, 'course_code' => 'CLONE102']);

        $this->actingAsAdmin()
            ->post(route('admin.curriculums.clone', $curriculum), [
                'name' => 'หลักสูตร Clone Audit',
                'effective_year' => 2570,
            ])
            ->assertRedirect();

        $newCurriculum = Curriculum::where('name', 'หลักสูตร Clone Audit')->firstOrFail();
        $log = $this->latestLog('ข้อมูลหลัก.คัดลอก', 'curriculums');
        $this->assertSame($newCurriculum->id, $log->record_id);
        $this->assertSame($curriculum->id, $log->old_values['source_curriculum_id']);
        $this->assertSame(2, $log->new_values['cloned_course_count']);
        $this->assertSame(['CLONE101', 'CLONE102'], $log->new_values['sample_course_codes']);
        $this->assertArrayHasKey('context', $log->new_values);
    }

    public function test_update_constants_logs_changed_keys_only(): void
    {
        $criteria = [
            'อาจารย์' => [
                't' => ['min' => 20, 'max' => 70],
                'r' => ['min' => 20, 'max' => 70],
            ],
        ];
        SystemSetting::set('teaching_quota_weeks', 46);
        SystemSetting::set('teaching_load_weeks', 39);
        SystemSetting::set('teaching_quota_hours_per_week', 35);
        SystemSetting::set('teaching_quota_hours', 1610);
        SystemSetting::set('pa_criteria_config', json_encode($criteria));

        $this->actingAsAdmin()
            ->post(route('admin.settings.constants.update'), [
                'teaching_quota_weeks' => 46,
                'teaching_load_weeks' => 40,
                'teaching_quota_hours_per_week' => 35,
                'pa_criteria' => $criteria,
            ])
            ->assertRedirect();

        $log = $this->latestLog('ตั้งค่าระบบ.แก้ไข', 'system_settings');
        $this->assertSame(['teaching_load_weeks'], array_keys($log->old_values));
        $this->assertSame(39, $log->old_values['teaching_load_weeks']);
        $this->assertSame(40, $log->new_values['teaching_load_weeks']);
        $this->assertArrayHasKey('context', $log->new_values);
    }

    public function test_password_change_creates_audit_log_without_raw_password(): void
    {
        $user = $this->makeUserWithRole('staff');

        $this->actingAs($user)->withSession(['active_role' => 'staff'])
            ->put(route('profile.password.update'), [
                'new_password' => 'new-password-123',
                'new_password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect();

        $log = $this->latestLog('ผู้ใช้และสิทธิ์.เปลี่ยนรหัสผ่าน', 'users');
        $this->assertSame($user->id, $log->record_id);
        $this->assertNull($log->old_values);
        $this->assertTrue($log->new_values['password_changed']);
        $this->assertArrayHasKey('context', $log->new_values);
        $this->assertArrayNotHasKey('password', $log->new_values);
        $this->assertArrayNotHasKey('new_password', $log->new_values);
        $this->assertNoSensitivePasswordFields($log->old_values);
        $this->assertNoSensitivePasswordFields($log->new_values);
    }

    public function test_date_filter_with_buddhist_year_text_input_parses_correctly(): void
    {
        $insideLog = AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => 'ข้อมูลหลัก.สร้าง',
            'table_affected' => 'courses',
            'record_id' => 1,
            'old_values' => null,
            'new_values' => [],
            'category' => 'ข้อมูลหลัก',
            'description' => 'พบจากวันที่ พ.ศ.',
        ]);
        $insideLog->forceFill(['created_at' => '2026-05-21 09:00:00'])->save();

        $outsideLog = AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => 'ข้อมูลหลัก.สร้าง',
            'table_affected' => 'courses',
            'record_id' => 2,
            'old_values' => null,
            'new_values' => [],
            'category' => 'ข้อมูลหลัก',
            'description' => 'ก่อนช่วงวันที่ พ.ศ.',
        ]);
        $outsideLog->forceFill(['created_at' => '2026-05-20 09:00:00'])->save();

        $response = $this->actingAsAdmin()
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('admin.audit_logs.index', [
                'date_from' => '21/05/2569',
                'partial' => 'table',
            ]));

        $response->assertOk();
        $response->assertSee('พบจากวันที่ พ.ศ.');
        $response->assertDontSee('ก่อนช่วงวันที่ พ.ศ.');
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function actingAsAdmin(): static
    {
        return $this->actingAs($this->admin)->withSession(['active_role' => 'admin']);
    }

    private function actingAsCourseHead(User $user): static
    {
        return $this->actingAs($user)->withSession(['active_role' => 'course_head']);
    }

    private function latestLog(string $action, string $table): AuditLog
    {
        return AuditLog::where('action', $action)
            ->where('table_affected', $table)
            ->latest('id')
            ->firstOrFail();
    }

    private function csvFile(string $content, string $name = 'import.csv'): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'audit-csv');
        file_put_contents($tmp, $content);

        return new UploadedFile($tmp, $name, 'text/csv', null, true);
    }

    private function assertNoSensitivePasswordFields(?array $payload): void
    {
        foreach (['password', 'password_hash', 'password_confirmation', 'current_password', 'new_password'] as $field) {
            $this->assertFalse(
                $this->arrayHasKeyRecursive($field, $payload ?? []),
                "Sensitive audit field [{$field}] should not be present.",
            );
        }
    }

    private function arrayHasKeyRecursive(string $needle, array $payload): bool
    {
        foreach ($payload as $key => $value) {
            if ($key === $needle) {
                return true;
            }

            if (is_array($value) && $this->arrayHasKeyRecursive($needle, $value)) {
                return true;
            }
        }

        return false;
    }

    private function makeAdmin(): User
    {
        $n    = $this->seq++;
        $user = User::create([
            'username'  => "admin_{$n}",
            'name'      => "Admin {$n}",
            'email'     => "admin_{$n}@test.example",
            'password'  => Hash::make('password'),
            'is_active' => true,
        ]);
        UserRole::create(['user_id' => $user->id, 'role' => 'admin', 'is_primary' => true]);
        return $user;
    }

    private function makeInstructor(): User
    {
        $n    = $this->seq++;
        $user = User::create([
            'username'    => "instr_{$n}",
            'name'        => "Instructor {$n}",
            'prefix'      => 'อ.',
            'email'       => "instr_{$n}@test.example",
            'employee_id' => "EMP{$n}",
            'password'    => Hash::make('password'),
            'is_active'   => true,
        ]);
        UserRole::create(['user_id' => $user->id, 'role' => 'instructor', 'is_primary' => true]);
        InstructorProfile::create([
            'user_id'       => $user->id,
            'title'         => 'อาจารย์',
            'department_id' => $this->makeDepartment()->id,
        ]);
        return $user;
    }

    private function makeCourseHead(): User
    {
        $user = $this->makeInstructor();
        UserRole::updateOrCreate(
            ['user_id' => $user->id, 'role' => 'course_head'],
            ['is_primary' => false]
        );

        return $user;
    }

    private function makeCourseRole(string $name): CourseRole
    {
        return CourseRole::firstOrCreate(
            ['name_th' => $name],
            ['sort_order' => $this->seq++]
        );
    }

    private function makeUserWithRole(string $role, bool $isActive = true): User
    {
        $n    = $this->seq++;
        $user = User::create([
            'username'  => "user_{$n}",
            'name'      => "User {$n}",
            'email'     => "user_{$n}@test.example",
            'password'  => Hash::make('password'),
            'is_active' => $isActive,
        ]);
        UserRole::create(['user_id' => $user->id, 'role' => $role, 'is_primary' => true]);
        return $user;
    }

    private function makeYear(array $overrides = []): AcademicYear
    {
        $n = $this->seq++;

        return AcademicYear::create(array_merge([
            'name'       => "2569-{$n}",
            'semester'   => 1,
            'start_date' => '2026-08-01',
            'end_date'   => '2026-12-31',
            'is_active'  => true,
            'phase'      => 'preparation',
        ], $overrides));
    }

    private function makeCurriculum(): Curriculum
    {
        return Curriculum::firstOrCreate(
            ['name' => 'หลักสูตรทดสอบ'],
            ['effective_year' => 2569, 'is_active' => true]
        );
    }

    private function makeDepartment(array $overrides = []): Department
    {
        return Department::firstOrCreate(
            ['name' => $overrides['name'] ?? 'ภาควิชาทดสอบ'],
            $overrides
        );
    }

    private function makeCourse(array $overrides = []): Course
    {
        $n = $this->seq++;
        return Course::create(array_merge([
            'course_code'              => "NUR{$n}",
            'curriculum_id'            => $this->makeCurriculum()->id,
            'department_id'            => $this->makeDepartment()->id,
            'name_th'                  => "วิชา {$n}",
            'name_en'                  => "Course {$n}",
            'course_type'              => 'theory',
            'academic_level'           => 'undergraduate',
            'default_year_level'       => 1,
            'default_semester'         => 1,
            'credits'                  => 3,
            'lecture_hours'            => 3,
            'lab_hours'                => 0,
            'self_study_hours'         => 6,
            'capacity'                 => 30,
            'status'                   => 'active',
            'requires_practicum_rotation' => false,
            'is_required'                 => true,
        ], $overrides));
    }

    private function makeOffering(
        User $coordinator,
        array $overrides = [],
        array $yearOverrides = [],
        ?Course $course = null
    ): CourseOffering {
        $course ??= $this->makeCourse([
            'head_instructor_id' => $coordinator->id,
            'capacity' => $overrides['total_student_count'] ?? 30,
        ]);
        $year = $this->makeYear(array_merge(['phase' => 'scheduling'], $yearOverrides));

        return CourseOffering::create(array_merge([
            'course_id' => $course->id,
            'academic_year_id' => $year->id,
            'coordinator_id' => $coordinator->id,
            'approval_status' => 'draft',
            'total_student_count' => 30,
            'planned_lecture_hours' => 3,
            'planned_lab_hours' => 0,
            'planned_practicum_hours' => 0,
            'teaching_weeks' => 16,
            'requires_practicum_rotation' => false,
            'practicum_note' => null,
        ], $overrides));
    }

    /** Course with all required fields for PUT update payload */
    private function makeFullCourse(): Course
    {
        $head = $this->makeInstructor();
        return $this->makeCourse(['head_instructor_id' => $head->id]);
    }

    /** Build a PUT update payload from existing course (no changes) */
    private function coursePayload(Course $course): array
    {
        return [
            'course_code'                 => $course->course_code,
            'name_th'                     => $course->name_th,
            'name_en'                     => $course->name_en,
            'curriculum_id'               => $course->curriculum_id,
            'department_id'               => $course->department_id,
            'head_instructor_id'          => $course->head_instructor_id,
            'status'                      => $course->status,
            'academic_level'              => $course->academic_level,
            'default_year_level'          => $course->default_year_level,
            'default_semester'            => $course->default_semester,
            'credits'                     => $course->credits,
            'lecture_hours'               => $course->lecture_hours,
            'lab_hours'                   => $course->lab_hours,
            'self_study_hours'            => $course->self_study_hours,
            'capacity'                    => $course->capacity,
            'requires_practicum_rotation' => $course->requires_practicum_rotation ? 1 : 0,
            'is_required'                 => $course->is_required ? 1 : 0,
        ];
    }

    private function curriculumPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'หลักสูตรทดสอบ Audit',
            'effective_year' => 2569,
            'education_level' => 'bachelor',
            'duration_years' => 4,
            'uses_year_level' => 1,
            'total_credits_required' => 120,
            'is_active' => 1,
        ], $overrides);
    }

    private function seedCriticals(): void
    {
        ActivityType::firstOrCreate(['name' => 'Lecture'], [
            'color_code' => '#2563eb',
            'category'   => 'lecture',
        ]);
        LocationType::firstOrCreate(['name' => 'ห้องเรียน']);
        \App\Http\Controllers\Admin\AlertController::flushCache();
    }
}
