<?php

namespace Tests\Feature;

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
