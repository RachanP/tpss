<?php

namespace Tests\Feature;

use App\Models\Course;
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

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createUserWithRole('admin', 'csv_admin');
    }

    public function test_user_import_rejects_missing_required_headers(): void
    {
        $this->actingAsAdmin();
        $file = $this->csvFile('users_missing_header.csv', implode("\n", [
            'username,email,name,password,roles',
            'new_staff,new-staff@example.com,New Staff,password,staff',
        ]));

        $response = $this->from('/admin/users')
            ->post('/admin/users/import', $this->csrfPayload([
                'csv_file' => $file,
            ]));

        $response->assertRedirect('/admin/users');
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('users', [
            'username' => 'new_staff',
        ]);
    }

    public function test_room_import_reports_malformed_rows_and_continues_valid_rows(): void
    {
        LocationType::create(['name' => 'Ward']);
        $this->actingAsAdmin();
        $file = $this->csvFile('rooms_malformed.csv', implode("\n", [
            'name,code,location_type_name,capacity,floor,building,status',
            'Broken Room,BAD',
            'Good Room,GOOD,Ward,30,1,Building A,active',
        ]));

        $response = $this->post('/admin/master-data/rooms/import', $this->csrfPayload([
            'csv_file' => $file,
        ]));

        $response->assertRedirect('/admin/master-data?tab=location_types');
        $response->assertSessionHas('import_errors');
        $this->assertDatabaseMissing('rooms', [
            'room_code' => 'BAD',
        ]);
        $this->assertDatabaseHas('rooms', [
            'room_code' => 'GOOD',
            'room_name' => 'Good Room',
        ]);
    }

    public function test_course_import_skips_duplicate_when_update_on_duplicate_is_false(): void
    {
        [$curriculum, $department] = $this->courseFixtures('Duplicate Skip');
        $this->createCourse($curriculum, $department, [
            'course_code' => 'NS201',
            'name_th' => 'Original Course',
        ]);

        $this->actingAsAdmin();
        $file = $this->courseCsvFile('courses_duplicate_skip.csv', [
            'NS201,Updated Course,Updated Course EN,' . $curriculum->name . ',' . $department->name . ',3,3,0,6,60,1,1,active',
        ]);

        $response = $this->post('/admin/master-data/courses/import', $this->csrfPayload([
            'csv_file' => $file,
        ]));

        $response->assertRedirect('/admin/master-data?tab=courses');
        $response->assertSessionHas('import_errors');
        $this->assertDatabaseHas('courses', [
            'course_code' => 'NS201',
            'name_th' => 'Original Course',
        ]);
    }

    public function test_course_import_updates_duplicate_when_update_on_duplicate_is_true(): void
    {
        [$curriculum, $department] = $this->courseFixtures('Duplicate Update');
        $this->createCourse($curriculum, $department, [
            'course_code' => 'NS202',
            'name_th' => 'Original Course',
        ]);

        $this->actingAsAdmin();
        $file = $this->courseCsvFile('courses_duplicate_update.csv', [
            'NS202,Updated Course,Updated Course EN,' . $curriculum->name . ',' . $department->name . ',3,3,0,6,60,1,1,active',
        ]);

        $response = $this->post('/admin/master-data/courses/import', $this->csrfPayload([
            'csv_file' => $file,
            'update_on_duplicate' => '1',
        ]));

        $response->assertRedirect('/admin/master-data?tab=courses');
        $response->assertSessionMissing('import_errors');
        $this->assertDatabaseHas('courses', [
            'course_code' => 'NS202',
            'name_th' => 'Updated Course',
        ]);
    }

    public function test_user_import_duplicate_update_removes_profile_when_roles_are_not_instructor(): void
    {
        $department = Department::create(['name' => 'Nursing']);
        $user = $this->createUserWithRole('instructor', 'profile_guard');
        InstructorProfile::create([
            'user_id' => $user->id,
            'title' => 'Instructor',
            'department_id' => $department->id,
            'employment_type' => 'Full-time',
            'hired_at' => '2026-05-14',
            'academic_degree' => 'Master',
            'teaching_pct' => 50,
            'research_pct' => 20,
            'service_pct' => 10,
            'culture_pct' => 10,
            'other_pct' => 10,
            'teaching_quota' => 0,
        ]);

        $this->actingAsAdmin();
        $file = $this->csvFile('users_profile_guard.csv', implode("\n", [
            'prefix,name,email,username,password,roles,primary_role,employee_id,title,academic_degree,department_name,employment_type,hired_date,teaching_pct,research_pct,service_pct,culture_pct,other_pct',
            'นาย,Staff Only,' . $user->email . ',' . $user->username . ',password,staff,staff,MU100,Instructor,Master,Nursing,Full-time,2026-05-14,50,20,10,10,10',
        ]));

        $response = $this->post('/admin/users/import', $this->csrfPayload([
            'csv_file' => $file,
            'update_on_duplicate' => '1',
        ]));

        $response->assertRedirect('/admin/users');
        $response->assertSessionMissing('import_errors');
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $user->id,
            'role' => 'staff',
        ]);
        $this->assertDatabaseMissing('user_roles', [
            'user_id' => $user->id,
            'role' => 'instructor',
        ]);
        $this->assertDatabaseMissing('instructor_profiles', [
            'user_id' => $user->id,
        ]);
    }

    private function createUserWithRole(string $role, string $suffix): User
    {
        $user = User::create([
            'username' => "{$role}_{$suffix}",
            'name' => "{$role} {$suffix}",
            'email' => "{$role}_{$suffix}@example.com",
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        UserRole::create([
            'user_id' => $user->id,
            'role' => $role,
            'is_primary' => true,
        ]);

        return $user;
    }

    private function actingAsAdmin(): void
    {
        $this
            ->withSession([
                'active_role' => 'admin',
                '_token' => 'valid-test-csrf-token',
            ])
            ->actingAs($this->admin);
    }

    private function csrfPayload(array $overrides = []): array
    {
        return array_merge([
            '_token' => 'valid-test-csrf-token',
        ], $overrides);
    }

    private function csvFile(string $name, string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content . "\n");
    }

    private function courseCsvFile(string $name, array $rows): UploadedFile
    {
        return $this->csvFile($name, implode("\n", array_merge([
            'course_code,name_th,name_en,curriculum_name,department_name,credits,lecture_hours,lab_hours,self_study_hours,capacity,default_year_level,default_semester,status',
        ], $rows)));
    }

    private function courseFixtures(string $prefix): array
    {
        return [
            Curriculum::create([
                'name' => "{$prefix} Curriculum",
                'effective_year' => 2565,
                'is_active' => true,
            ]),
            Department::create([
                'name' => "{$prefix} Department",
            ]),
        ];
    }

    private function createCourse(Curriculum $curriculum, Department $department, array $overrides = []): Course
    {
        return Course::create(array_merge([
            'course_code' => 'NS200',
            'curriculum_id' => $curriculum->id,
            'department_id' => $department->id,
            'name_th' => 'Existing Course',
            'name_en' => 'Existing Course',
            'course_type' => 'theory',
            'academic_level' => 'undergraduate',
            'default_year_level' => 1,
            'default_semester' => 1,
            'requires_practicum_rotation' => false,
            'credits' => 3,
            'lecture_hours' => 3,
            'lab_hours' => 0,
            'self_study_hours' => 6,
            'capacity' => 60,
            'status' => 'active',
        ], $overrides));
    }
}
