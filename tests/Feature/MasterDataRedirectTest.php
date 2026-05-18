<?php

namespace Tests\Feature;

use App\Models\ActivityType;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\LocationType;
use App\Models\Room;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MasterDataRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        $user = User::create([
            'username' => "{$role}_user",
            'name'     => ucfirst($role) . ' User',
            'email'    => "{$role}@example.com",
            'password' => Hash::make('password'),
        ]);
        UserRole::create(['user_id' => $user->id, 'role' => $role, 'is_primary' => true]);
        return $user;
    }

    // ── Staff redirects ───────────────────────────────────────────────

    public function test_staff_store_location_type_redirects_to_staff_route(): void
    {
        $staff = $this->makeUser('staff');
        $this->actingAs($staff)->withSession(['active_role' => 'staff']);

        $response = $this->post(route('staff.location_types.store'), [
            'name' => 'ห้องทดสอบ Staff',
        ]);

        $response->assertRedirect(route('staff.master_data', ['tab' => 'location_types']));
    }

    public function test_staff_store_room_redirects_to_staff_route(): void
    {
        $staff = $this->makeUser('staff');
        $lt    = LocationType::create(['name' => 'ประเภททดสอบ']);
        $this->actingAs($staff)->withSession(['active_role' => 'staff']);

        $response = $this->post(route('staff.rooms.store'), [
            'room_code'        => 'TEST-01',
            'room_name'        => 'ห้องทดสอบ',
            'location_type_id' => $lt->id,
            'status'           => 'active',
        ]);

        $response->assertRedirect(route('staff.master_data', ['tab' => 'location_types']));
    }

    public function test_staff_destroy_location_type_redirects_to_staff_route(): void
    {
        $staff = $this->makeUser('staff');
        $lt    = LocationType::create(['name' => 'ประเภทลบ']);
        $this->actingAs($staff)->withSession(['active_role' => 'staff']);

        $response = $this->delete(route('staff.location_types.destroy', $lt));

        $response->assertRedirect(route('staff.master_data', ['tab' => 'location_types']));
    }

    // ── Admin redirects still work ────────────────────────────────────

    public function test_admin_store_location_type_redirects_to_admin_route(): void
    {
        $admin = $this->makeUser('admin');
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $response = $this->post(route('admin.location_types.store'), [
            'name' => 'ห้องทดสอบ Admin',
        ]);

        $response->assertRedirect(route('admin.master_data', ['tab' => 'location_types']));
    }

    public function test_admin_store_activity_type_redirects_to_admin_route(): void
    {
        $admin = $this->makeUser('admin');
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $response = $this->post(route('admin.activity_types.store'), [
            'name'       => 'กิจกรรมทดสอบ',
            'color_code' => '#FF0000',
            'category'   => 'lecture',
        ]);

        $response->assertRedirect(route('admin.master_data', ['tab' => 'activity_types']));
    }

    // ── Course composite unique ───────────────────────────────────────

    public function test_same_course_code_allowed_in_different_curriculums(): void
    {
        $admin = $this->makeUser('admin');
        $dept  = Department::create(['name' => 'ทดสอบ']);
        $curr1 = Curriculum::create(['name' => 'หลักสูตร 2562', 'effective_year' => 2562, 'is_active' => false]);
        $curr2 = Curriculum::create(['name' => 'หลักสูตร 2567', 'effective_year' => 2567, 'is_active' => true]);
        $head  = $this->makeUser('instructor');

        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $payload = [
            'course_code'                 => 'NSBS301',
            'name_th'                     => 'วิชาทดสอบ',
            'curriculum_id'               => $curr1->id,
            'department_id'               => $dept->id,
            'head_instructor_id'          => $head->id,
            'course_type'                 => 'theory',
            'default_year_level'          => 1,
            'default_semester'            => 1,
            'credits'                     => 3,
            'lecture_hours'               => 3,
            'lab_hours'                   => 0,
            'self_study_hours'            => 6,
            'capacity'                    => 30,
            'status'                      => 'active',
            'requires_practicum_rotation' => 0,
        ];

        $this->post(route('admin.courses.store'), $payload)->assertRedirect();

        // Same course_code in different curriculum → should succeed
        $payload['curriculum_id'] = $curr2->id;
        $this->post(route('admin.courses.store'), $payload)
            ->assertRedirect(route('admin.master_data', ['tab' => 'courses']));
    }

    public function test_duplicate_course_code_in_same_curriculum_is_rejected(): void
    {
        $admin = $this->makeUser('admin');
        $dept  = Department::create(['name' => 'ทดสอบ']);
        $curr  = Curriculum::create(['name' => 'หลักสูตร 2567', 'effective_year' => 2567, 'is_active' => true]);
        $head  = $this->makeUser('instructor');

        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $payload = [
            'course_code'                 => 'NSBS301',
            'name_th'                     => 'วิชาทดสอบ',
            'curriculum_id'               => $curr->id,
            'department_id'               => $dept->id,
            'head_instructor_id'          => $head->id,
            'course_type'                 => 'theory',
            'default_year_level'          => 1,
            'default_semester'            => 1,
            'credits'                     => 3,
            'lecture_hours'               => 3,
            'lab_hours'                   => 0,
            'self_study_hours'            => 6,
            'capacity'                    => 30,
            'status'                      => 'active',
            'requires_practicum_rotation' => 0,
        ];

        $this->post(route('admin.courses.store'), $payload)->assertRedirect();

        // Same code + same curriculum → should fail validation
        $this->post(route('admin.courses.store'), $payload)
            ->assertSessionHasErrors([
                'course_code' => 'รหัสวิชานี้มีอยู่แล้วในหลักสูตรนี้',
            ]);
    }

    public function test_course_create_accepts_course_code_with_spaces(): void
    {
        $admin = $this->makeUser('admin');
        $payload = $this->coursePayload(['course_code' => 'NSBS 301']);

        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $this->post(route('admin.courses.store'), $payload)
            ->assertRedirect(route('admin.master_data', ['tab' => 'courses']));

        $this->assertDatabaseHas('courses', ['course_code' => 'NSBS 301']);
    }

    public function test_course_update_accepts_course_code_with_spaces(): void
    {
        $admin = $this->makeUser('admin');
        $course = Course::create($this->coursePayload(['course_code' => 'NSBS301']));

        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $this->put(route('admin.courses.update', $course), $this->coursePayload([
            'course_code' => 'NSBS 302',
            'curriculum_id' => $course->curriculum_id,
            'department_id' => $course->department_id,
            'head_instructor_id' => $course->head_instructor_id,
        ]))->assertRedirect(route('admin.master_data', ['tab' => 'courses']));

        $this->assertSame('NSBS 302', $course->fresh()->course_code);
    }

    public function test_course_create_rejects_dangerous_course_code_and_preserves_modal_feedback_state(): void
    {
        $admin = $this->makeUser('admin');
        $payload = $this->coursePayload([
            'course_code' => 'NSBS/301',
            '_form' => 'course',
            '_course_form_mode' => 'create',
            '_course_route_key' => '',
            '_course_id' => '',
        ]);

        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $this->from(route('admin.master_data', ['tab' => 'courses']))
            ->post(route('admin.courses.store'), $payload)
            ->assertRedirect(route('admin.master_data', ['tab' => 'courses']))
            ->assertSessionHasErrors('course_code')
            ->assertSessionHasInput('_form', 'course')
            ->assertSessionHasInput('_course_form_mode', 'create');

        $this->assertDatabaseMissing('courses', ['course_code' => 'NSBS/301']);

        $this->followingRedirects()
            ->from(route('admin.master_data', ['tab' => 'courses']))
            ->post(route('admin.courses.store'), $payload)
            ->assertOk()
            ->assertSee('บันทึกรายวิชาไม่สำเร็จ')
            ->assertSee('รหัสวิชาต้องใช้เฉพาะตัวอักษรภาษาอังกฤษ ตัวเลข ช่องว่าง ขีดกลาง หรือขีดล่าง')
            ->assertSee('courseFormErrorState', false)
            ->assertSee('course-code-error', false);
    }

    public function test_course_update_rejects_dangerous_course_code_and_preserves_edit_route_key(): void
    {
        $admin = $this->makeUser('admin');
        $course = Course::create($this->coursePayload(['course_code' => 'NSBS301']));
        $payload = $this->coursePayload([
            'course_code' => 'NSBS?302',
            'curriculum_id' => $course->curriculum_id,
            'department_id' => $course->department_id,
            'head_instructor_id' => $course->head_instructor_id,
            '_form' => 'course',
            '_course_form_mode' => 'edit',
            '_course_route_key' => $course->course_code,
            '_course_id' => $course->id,
        ]);

        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $this->from(route('admin.master_data', ['tab' => 'courses']))
            ->put(route('admin.courses.update', $course), $payload)
            ->assertRedirect(route('admin.master_data', ['tab' => 'courses']))
            ->assertSessionHasErrors('course_code')
            ->assertSessionHasInput('_form', 'course')
            ->assertSessionHasInput('_course_form_mode', 'edit')
            ->assertSessionHasInput('_course_route_key', 'NSBS301');

        $this->assertSame('NSBS301', $course->fresh()->course_code);

        $this->followingRedirects()
            ->from(route('admin.master_data', ['tab' => 'courses']))
            ->put(route('admin.courses.update', $course), $payload)
            ->assertOk()
            ->assertSee('บันทึกรายวิชาไม่สำเร็จ')
            ->assertSee('NSBS?302')
            ->assertSee('NSBS301');
    }

    public function test_course_create_rejects_dangerous_course_code_characters(): void
    {
        $admin = $this->makeUser('admin');
        $dept = Department::create(['name' => 'Dangerous Code Dept']);
        $curr = Curriculum::create([
            'name' => 'Dangerous Code Curriculum',
            'effective_year' => 2567,
            'is_active' => true,
        ]);
        $head = $this->makeUser('instructor');

        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        foreach (['NSBS/301', 'NSBS?301', 'NSBS#301', 'NSBS%301', 'NSBS&301', 'NSBS=301'] as $code) {
            $payload = $this->coursePayload([
                'course_code' => $code,
                'curriculum_id' => $curr->id,
                'department_id' => $dept->id,
                'head_instructor_id' => $head->id,
                '_form' => 'course',
                '_course_form_mode' => 'create',
            ]);

            $this->from(route('admin.master_data', ['tab' => 'courses']))
                ->post(route('admin.courses.store'), $payload)
                ->assertRedirect(route('admin.master_data', ['tab' => 'courses']))
                ->assertSessionHasErrors('course_code');

            $this->assertDatabaseMissing('courses', ['course_code' => $code]);
        }
    }

    public function test_course_create_accepts_official_course_code_format(): void
    {
        $admin = $this->makeUser('admin');
        $payload = $this->coursePayload(['course_code' => 'NSBS_301-A']);

        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $this->post(route('admin.courses.store'), $payload)
            ->assertRedirect(route('admin.master_data', ['tab' => 'courses']));

        $this->assertDatabaseHas('courses', ['course_code' => 'NSBS_301-A']);
    }

    private function coursePayload(array $overrides = []): array
    {
        $dept = $overrides['department_id'] ?? Department::create(['name' => 'Course Dept ' . uniqid()])->id;
        $curr = $overrides['curriculum_id'] ?? Curriculum::create([
            'name' => 'Course Curriculum ' . uniqid(),
            'effective_year' => 2567,
            'is_active' => true,
        ])->id;
        $head = $overrides['head_instructor_id'] ?? $this->makeUser('instructor')->id;

        return array_merge([
            'course_code'                 => 'NSBS301',
            'name_th'                     => 'วิชาทดสอบ',
            'curriculum_id'               => $curr,
            'department_id'               => $dept,
            'head_instructor_id'          => $head,
            'course_type'                 => 'theory',
            'default_year_level'          => 1,
            'default_semester'            => 1,
            'credits'                     => 3,
            'lecture_hours'               => 3,
            'lab_hours'                   => 0,
            'self_study_hours'            => 6,
            'capacity'                    => 30,
            'status'                      => 'active',
            'requires_practicum_rotation' => 0,
        ], $overrides);
    }
}
