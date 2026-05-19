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

    // ── Helpers ───────────────────────────────────────────────────────

    private function actingAsAdmin(): static
    {
        return $this->actingAs($this->admin)->withSession(['active_role' => 'admin']);
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

    private function makeDepartment(): Department
    {
        return Department::firstOrCreate(['name' => 'ภาควิชาทดสอบ']);
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
        ];
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
