<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ActivityType;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\InstructorProfile;
use App\Models\LocationType;
use App\Models\Room;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuditLogIntegrationTest extends TestCase
{
    use RefreshDatabase;

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

    // ── Helpers ───────────────────────────────────────────────────────

    private function actingAsAdmin(): static
    {
        return $this->actingAs($this->admin)->withSession(['active_role' => 'admin']);
    }

    private function latestLog(string $action, string $table): AuditLog
    {
        return AuditLog::where('action', $action)
            ->where('table_affected', $table)
            ->latest('id')
            ->firstOrFail();
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
        return AcademicYear::create(array_merge([
            'name'       => '2569',
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
