<?php

namespace Tests\Feature;

use App\Models\Curriculum;
use App\Models\Department;
use App\Models\LocationType;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CsvImportValidationTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::create([
            'username' => 'admin_csv',
            'name'     => 'Admin CSV',
            'email'    => 'admin_csv@example.com',
            'password' => Hash::make('password'),
        ]);
        UserRole::create(['user_id' => $user->id, 'role' => 'admin', 'is_primary' => true]);
        return $user;
    }

    private function csvFile(string $content, string $name = 'test.csv'): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($tmp, $content);
        return new UploadedFile($tmp, $name, 'text/csv', null, true);
    }

    // ── importRooms ───────────────────────────────────────────────────

    public function test_import_rooms_empty_file_returns_error(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $file     = $this->csvFile('');
        $response = $this->post(route('admin.rooms.import'), ['csv_file' => $file]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('ว่างเปล่า', session('error'));
    }

    public function test_import_rooms_missing_required_header_returns_error(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        // ขาด location_type_name
        $file     = $this->csvFile("room_code,room_name\nRM-01,ห้องทดสอบ\n");
        $response = $this->post(route('admin.rooms.import'), ['csv_file' => $file]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('location_type_name', session('error'));
    }

    public function test_import_rooms_column_count_mismatch_skips_row(): void
    {
        $admin = $this->makeAdmin();
        LocationType::create(['name' => 'ประเภทสอน']);
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        // header 3 columns, data row has 2 → combineCsvRow ควรจับ error นี้
        $csv  = "room_code,room_name,location_type_name\nRM-01,ห้องทดสอบ\n";
        $file = $this->csvFile($csv);
        $this->post(route('admin.rooms.import'), ['csv_file' => $file]);

        $this->assertCount(0, \App\Models\Room::all());
    }

    public function test_import_rooms_unknown_location_type_skips_row(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $csv  = "room_code,room_name,location_type_name\nRM-01,ห้องทดสอบ,ประเภทไม่มีอยู่\n";
        $file = $this->csvFile($csv);
        $this->post(route('admin.rooms.import'), ['csv_file' => $file]);

        $this->assertCount(0, \App\Models\Room::all());
    }

    public function test_import_rooms_valid_row_creates_room(): void
    {
        $admin = $this->makeAdmin();
        LocationType::create(['name' => 'ห้องเรียน']);
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $csv  = "room_code,room_name,location_type_name\nRM-01,ห้องทดสอบ,ห้องเรียน\n";
        $file = $this->csvFile($csv);
        $this->post(route('admin.rooms.import'), ['csv_file' => $file]);

        $this->assertDatabaseHas('rooms', ['room_code' => 'RM-01']);
    }

    // ── importCourses ─────────────────────────────────────────────────

    public function test_import_courses_missing_required_header_returns_error(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        // ขาด curriculum_name
        $file     = $this->csvFile("course_code,name_th,credits\nNSBS301,วิชาทดสอบ,3\n");
        $response = $this->post(route('admin.courses.import'), ['csv_file' => $file]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('curriculum_name', session('error'));
    }

    // ── importCourses course_code policy ──────────────────────────────

    public function test_import_courses_accepts_course_code_with_spaces(): void
    {
        $admin = $this->makeAdmin();
        $this->seedCourseImportLookups();
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $file = $this->csvFile(
            "course_code,name_th,curriculum_name,department_name,head_instructor_employee_id,course_type,credits,lecture_hours,lab_hours,self_study_hours,capacity,default_year_level,default_semester,requires_practicum_rotation,status\n" .
            "NSBS 301,วิชาทดสอบ,หลักสูตรทดสอบ,ภาควิชาทดสอบ,MU999,theory,3,3,0,6,30,1,1,0,active\n"
        );

        $this->post(route('admin.courses.import'), ['csv_file' => $file])
            ->assertSessionMissing('import_errors');

        $this->assertDatabaseHas('courses', ['course_code' => 'NSBS 301']);
    }

    public function test_import_courses_rejects_course_code_with_special_characters(): void
    {
        $admin = $this->makeAdmin();
        $this->seedCourseImportLookups();
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $file = $this->csvFile(
            "course_code,name_th,curriculum_name,department_name,head_instructor_employee_id,course_type,credits,lecture_hours,lab_hours,self_study_hours,capacity,default_year_level,default_semester,requires_practicum_rotation,status\n" .
            "NSBS/301,วิชาทดสอบ,หลักสูตรทดสอบ,ภาควิชาทดสอบ,MU999,theory,3,3,0,6,30,1,1,0,active\n"
        );

        $this->post(route('admin.courses.import'), ['csv_file' => $file])
            ->assertSessionHas('import_errors');

        $this->assertDatabaseMissing('courses', ['course_code' => 'NSBS/301']);
        $this->assertStringContainsString('แถว 2', session('import_errors')[0]);
        $this->assertStringContainsString('course_code', session('import_errors')[0]);
        $this->assertStringContainsString('รหัสวิชาต้องใช้เฉพาะตัวอักษรภาษาอังกฤษ ตัวเลข ช่องว่าง ขีดกลาง หรือขีดล่าง', session('import_errors')[0]);
    }

    public function test_import_courses_rejects_dangerous_course_code_characters(): void
    {
        $admin = $this->makeAdmin();
        $this->seedCourseImportLookups();
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        foreach (['NSBS/301', 'NSBS?301', 'NSBS#301', 'NSBS%301', 'NSBS&301', 'NSBS=301'] as $code) {
            $file = $this->csvFile(
                "course_code,name_th,curriculum_name,department_name,head_instructor_employee_id,course_type,credits,lecture_hours,lab_hours,self_study_hours,capacity,default_year_level,default_semester,requires_practicum_rotation,status\n" .
                "{$code},วิชาทดสอบ,หลักสูตรทดสอบ,ภาควิชาทดสอบ,MU999,theory,3,3,0,6,30,1,1,0,active\n"
            );

            $this->post(route('admin.courses.import'), ['csv_file' => $file])
                ->assertSessionHas('import_errors');

            $this->assertDatabaseMissing('courses', ['course_code' => $code]);
            $this->assertStringContainsString('course_code', session('import_errors')[0]);
        }
    }

    public function test_import_courses_accepts_allowed_course_code_symbols(): void
    {
        $admin = $this->makeAdmin();
        $this->seedCourseImportLookups();
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $file = $this->csvFile(
            "course_code,name_th,curriculum_name,department_name,head_instructor_employee_id,course_type,credits,lecture_hours,lab_hours,self_study_hours,capacity,default_year_level,default_semester,requires_practicum_rotation,status\n" .
            "NSBS_301-A,วิชาทดสอบ,หลักสูตรทดสอบ,ภาควิชาทดสอบ,MU999,theory,3,3,0,6,30,1,1,0,active\n"
        );

        $this->post(route('admin.courses.import'), ['csv_file' => $file])
            ->assertSessionMissing('import_errors');

        $this->assertDatabaseHas('courses', ['course_code' => 'NSBS_301-A']);
    }

    private function seedCourseImportLookups(): void
    {
        Curriculum::create([
            'name' => 'หลักสูตรทดสอบ',
            'effective_year' => 2567,
            'is_active' => true,
        ]);
        Department::create(['name' => 'ภาควิชาทดสอบ']);
        User::create([
            'username' => 'head_csv',
            'name' => 'Head CSV',
            'email' => 'head_csv@example.com',
            'password' => Hash::make('password'),
            'employee_id' => 'MU999',
        ]);
    }

    // ── importUsers ───────────────────────────────────────────────────

    public function test_import_users_missing_required_header_returns_error(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        // ขาด primary_role
        $file     = $this->csvFile("username,email,name,password,roles\ntest,t@t.com,Test,pass,staff\n");
        $response = $this->post(route('admin.users.import'), ['csv_file' => $file]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('primary_role', session('error'));
    }
}
