<?php

namespace Tests\Feature;

use App\Models\Curriculum;
use App\Models\Department;
use App\Models\InstructorProfile;
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

    public function test_import_rooms_accepts_excel_template_csv_with_hint_row_and_required_markers(): void
    {
        $admin = $this->makeAdmin();
        LocationType::create(['name' => 'Classroom']);
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $csv = implode("\n", [
            'Template Rooms,,,,,,,',
            '# TPSS room import template',
            ',required,required',
            'room_code*,room_name*,location_type_name*',
            'RM-XLSX,Excel Template Room,Classroom',
            '',
        ]);

        $this->post(route('admin.rooms.import'), ['csv_file' => $this->csvFile($csv)])
            ->assertSessionMissing('import_errors');

        $this->assertDatabaseHas('rooms', [
            'room_code' => 'RM-XLSX',
            'room_name' => 'Excel Template Room',
        ]);
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

    public function test_import_courses_accepts_excel_template_csv_with_hint_row_and_required_markers(): void
    {
        $admin = $this->makeAdmin();
        $this->seedExcelCourseImportLookups();
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $csv = implode("\n", [
            'Template Courses,,,,,,,,,,,,,,,,,',
            '# TPSS course import template',
            ',required,,required,,required,,required,,,,required,required,required,,',
            'course_code*,name_th*,name_en,curriculum_name*,department_name,head_instructor_employee_id*,course_type,credits*,lecture_hours,lab_hours,self_study_hours,capacity*,default_year_level*,default_semester*,requires_practicum_rotation,status',
            'NSBS-XLSX,Excel Template Course,Excel Template Course EN,XLSX Curriculum,XLSX Department,MU-XLSX,theory,3,3,0,6,30,1,1,0,active',
            '',
        ]);

        $this->post(route('admin.courses.import'), ['csv_file' => $this->csvFile($csv)])
            ->assertSessionMissing('import_errors');

        $this->assertDatabaseHas('courses', [
            'course_code' => 'NSBS-XLSX',
            'name_th' => 'Excel Template Course',
        ]);
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

    private function seedExcelCourseImportLookups(): void
    {
        Curriculum::create([
            'name' => 'XLSX Curriculum',
            'effective_year' => 2567,
            'duration_years' => 4,
            'uses_year_level' => true,
            'is_active' => true,
        ]);
        Department::create(['name' => 'XLSX Department']);
        User::create([
            'username' => 'head_xlsx',
            'name' => 'Head XLSX',
            'email' => 'head_xlsx@example.com',
            'password' => Hash::make('password'),
            'employee_id' => 'MU-XLSX',
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

    public function test_import_users_accepts_buddhist_hired_date_and_stores_iso_date(): void
    {
        $admin = $this->makeAdmin();
        Department::create(['name' => 'CSV Date Dept']);
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $this->post(route('admin.users.import'), [
            'csv_file' => $this->csvFile($this->userImportCsv('csv_buddhist_2560', '1/1/2560')),
        ])->assertSessionMissing('import_errors');

        $user = User::where('username', 'csv_buddhist_2560')->firstOrFail();
        $this->assertDatabaseHas('instructor_profiles', [
            'user_id' => $user->id,
            'hired_at' => '2017-01-01',
        ]);
    }

    public function test_import_users_accepts_buddhist_hired_date_with_leading_zeroes(): void
    {
        $admin = $this->makeAdmin();
        Department::create(['name' => 'CSV Date Dept']);
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $this->post(route('admin.users.import'), [
            'csv_file' => $this->csvFile($this->userImportCsv('csv_buddhist_2570', '15/01/2570')),
        ])->assertSessionMissing('import_errors');

        $user = User::where('username', 'csv_buddhist_2570')->firstOrFail();
        $this->assertDatabaseHas('instructor_profiles', [
            'user_id' => $user->id,
            'hired_at' => '2027-01-15',
        ]);
    }

    public function test_import_users_accepts_iso_hired_date(): void
    {
        $admin = $this->makeAdmin();
        Department::create(['name' => 'CSV Date Dept']);
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $this->post(route('admin.users.import'), [
            'csv_file' => $this->csvFile($this->userImportCsv('csv_iso_date', '2027-01-15')),
        ])->assertSessionMissing('import_errors');

        $user = User::where('username', 'csv_iso_date')->firstOrFail();
        $this->assertDatabaseHas('instructor_profiles', [
            'user_id' => $user->id,
            'hired_at' => '2027-01-15',
        ]);
    }

    public function test_import_users_rejects_invalid_hired_dates_without_creating_profiles(): void
    {
        $admin = $this->makeAdmin();
        Department::create(['name' => 'CSV Date Dept']);
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        foreach ([
            'csv_bad_short_year' => '10/10/1',
            'csv_bad_out_of_range' => '10/10/0001',
            'csv_bad_us_style' => '3/15/2015',
        ] as $username => $hiredDate) {
            $this->post(route('admin.users.import'), [
                'csv_file' => $this->csvFile($this->userImportCsv($username, $hiredDate)),
            ])->assertSessionHas('import_errors');

            $this->assertDatabaseMissing('users', ['username' => $username]);
        }

        $this->assertSame(0, InstructorProfile::count());
    }

    public function test_import_users_invalid_hired_date_does_not_update_existing_profile(): void
    {
        $admin = $this->makeAdmin();
        $department = Department::create(['name' => 'CSV Date Dept']);
        $user = User::create([
            'username' => 'csv_existing_date',
            'name' => 'Existing Date User',
            'email' => 'csv_existing_date@example.com',
            'password' => Hash::make('password123'),
            'employee_id' => 'EMP-EXISTING-DATE',
            'is_active' => true,
        ]);
        UserRole::create(['user_id' => $user->id, 'role' => 'instructor', 'is_primary' => true]);
        InstructorProfile::create([
            'user_id' => $user->id,
            'title' => 'อาจารย์',
            'department_id' => $department->id,
            'academic_degree' => 'ปริญญาโท',
            'employment_type' => 'Full-time',
            'hired_at' => '2017-01-01',
            'teaching_pct' => 50,
            'research_pct' => 25,
            'service_pct' => 10,
            'culture_pct' => 10,
            'other_pct' => 5,
            'teaching_quota' => 0,
        ]);

        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $this->post(route('admin.users.import'), [
            'csv_file' => $this->csvFile($this->userImportCsv('csv_existing_date', '10/10/1')),
            'update_on_duplicate' => '1',
        ])->assertSessionHas('import_errors');

        $user->refresh();
        $this->assertSame('Existing Date User', $user->name);
        $this->assertSame('2017-01-01', $user->instructorProfile()->first()->hired_at);
    }

    public function test_import_users_accepts_excel_template_csv_with_hint_row_and_required_markers(): void
    {
        $admin = $this->makeAdmin();
        Department::create(['name' => 'XLSX Dept']);
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $csv = implode("\n", [
            'Template Users,,,,,,,,,,,,,,,,,',
            '# TPSS user import template',
            '# rows that begin with # are ignored',
            ',required,required,required,required,required,required,required for instructors,,,,,,,,,,',
            'prefix,name*,email*,username*,password*,roles*,primary_role*,employee_id,title,academic_degree,department_name,employment_type,hired_date,teaching_pct,research_pct,service_pct,culture_pct,other_pct',
            'Mr.,Excel Template User,xlsx_user@example.com,xlsx_user,password123,instructor,instructor,EMP-XLSX,Instructor,Master,XLSX Dept,Full-time,2026-01-15,50,25,10,10,5',
            '',
        ]);

        $this->post(route('admin.users.import'), ['csv_file' => $this->csvFile($csv)])
            ->assertSessionMissing('import_errors');

        $this->assertDatabaseHas('users', [
            'username' => 'xlsx_user',
            'email' => 'xlsx_user@example.com',
        ]);
    }

    private function userImportCsv(string $username, string $hiredDate): string
    {
        return implode("\n", [
            'prefix,name,email,username,password,roles,primary_role,employee_id,title,academic_degree,department_name,employment_type,hired_date,teaching_pct,research_pct,service_pct,culture_pct,other_pct',
            "นาย,CSV Date User,{$username}@example.com,{$username},password123,instructor,instructor,EMP-{$username},อาจารย์,ปริญญาโท,CSV Date Dept,Full-time,{$hiredDate},50,25,10,10,5",
            '',
        ]);
    }
}
