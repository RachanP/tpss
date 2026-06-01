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

    public function test_staff_master_data_page_renders_with_write_routes(): void
    {
        $staff = $this->makeUser('staff');
        $this->actingAs($staff)->withSession(['active_role' => 'staff']);

        $this->get(route('staff.master_data'))->assertOk();
    }

    public function test_staff_can_store_department_activity_curriculum_and_cohort(): void
    {
        $staff = $this->makeUser('staff');
        $this->actingAs($staff)->withSession(['active_role' => 'staff']);

        $this->from(route('staff.master_data', ['tab' => 'departments']))
            ->post(route('staff.departments.store'), [
                'name' => 'ภาควิชา Staff',
            ])
            ->assertRedirect(route('staff.master_data', ['tab' => 'departments']));

        $this->post(route('staff.activity_types.store'), [
            'name'       => 'กิจกรรม Staff',
            'color_code' => '#22c55e',
            'category'   => 'lecture',
        ])->assertRedirect(route('staff.master_data', ['tab' => 'activity_types']));

        $this->post(route('staff.curriculums.store'), [
            'name'                   => 'หลักสูตร Staff 2569',
            'effective_year'         => 2569,
            'education_level'        => 'bachelor',
            'duration_years'         => 4,
            'uses_year_level'        => 1,
            'total_credits_required' => null,
            'is_active'              => 1,
        ])->assertRedirect(route('staff.master_data', ['tab' => 'curriculums']));

        $curriculum = Curriculum::where('name', 'หลักสูตร Staff 2569')->firstOrFail();

        $this->post(route('staff.student_cohorts.store'), [
            'curriculum_id' => $curriculum->id,
            'year_level'    => 1,
            'code'          => 'A',
            'student_count' => 80,
        ])->assertRedirect(route('staff.master_data', ['tab' => 'student_cohorts']));
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

    public function test_admin_can_confirm_same_person_as_department_head_and_secretary(): void
    {
        $admin = $this->makeUser('admin');
        $instructor = $this->makeUser('instructor');
        $department = Department::create(['name' => 'ภาควิชาทดสอบ']);

        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $this->put(route('admin.departments.update', $department), [
            'name' => $department->name,
            'head_user_id' => $instructor->id,
            'secretary_user_id' => $instructor->id,
            'force_position_override' => '1',
        ])->assertRedirect();

        $department->refresh();
        $this->assertSame($instructor->id, $department->head_user_id);
        $this->assertSame($instructor->id, $department->secretary_user_id);
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
            'is_required'                 => 1,
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
            'is_required'                 => 1,
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

    // ── Master curriculum (ป.โท) — uses_year_level=false ──────────────

    public function test_admin_can_create_master_curriculum_without_year_level(): void
    {
        $admin = $this->makeUser('admin');
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $this->post(route('admin.curriculums.store'), [
            'name'                   => 'หลักสูตรพยาบาลศาสตรมหาบัณฑิต 2569',
            'effective_year'         => 2569,
            'education_level'        => 'master',
            'duration_years'         => 2,
            'uses_year_level'        => 0,
            'total_credits_required' => 36,
            'is_active'              => 1,
        ])->assertRedirect(route('admin.master_data', ['tab' => 'curriculums']));

        $this->assertDatabaseHas('curriculums', [
            'name'             => 'หลักสูตรพยาบาลศาสตรมหาบัณฑิต 2569',
            'education_level'  => 'master',
            'duration_years'   => 2,
            'uses_year_level'  => 0,
        ]);
    }

    public function test_course_in_master_curriculum_does_not_require_year_level(): void
    {
        $admin = $this->makeUser('admin');
        $curr  = Curriculum::create([
            'name'            => 'หลักสูตรปริญญาโท ทดสอบ',
            'effective_year'  => 2569,
            'education_level' => 'master',
            'duration_years'  => 2,
            'uses_year_level' => false,
            'is_active'       => true,
        ]);

        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $payload = $this->coursePayload([
            'course_code'        => 'NSGM 501',
            'curriculum_id'      => $curr->id,
            'default_year_level' => '',
        ]);

        $this->post(route('admin.courses.store'), $payload)
            ->assertRedirect(route('admin.master_data', ['tab' => 'courses']));

        $this->assertDatabaseHas('courses', [
            'course_code'        => 'NSGM 501',
            'default_year_level' => null,
        ]);
    }

    public function test_toggling_curriculum_off_year_level_clears_course_year_levels(): void
    {
        $admin = $this->makeUser('admin');
        $curr  = Curriculum::create([
            'name'            => 'หลักสูตรทดสอบ cascade',
            'effective_year'  => 2569,
            'education_level' => 'bachelor',
            'duration_years'  => 4,
            'uses_year_level' => true,
            'is_active'       => true,
        ]);
        $dept = Department::create(['name' => 'ภาควิชา cascade']);
        $head = $this->makeUser('instructor');

        $course = Course::create($this->coursePayload([
            'course_code'        => 'CSC 101',
            'curriculum_id'      => $curr->id,
            'department_id'      => $dept->id,
            'head_instructor_id' => $head->id,
            'default_year_level' => 2,
        ]));

        $this->assertEquals(2, $course->fresh()->default_year_level);

        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $this->put(route('admin.curriculums.update', $curr), [
            'name'                   => $curr->name,
            'effective_year'         => $curr->effective_year,
            'education_level'        => 'master',
            'uses_year_level'        => 0,
            'total_credits_required' => 36,
            'is_active'              => 1,
        ])->assertRedirect(route('admin.master_data', ['tab' => 'curriculums']));

        $this->assertNull($course->fresh()->default_year_level);
    }

    public function test_credit_based_curriculum_requires_total_credits(): void
    {
        $admin = $this->makeUser('admin');
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $this->post(route('admin.curriculums.store'), [
            'name'            => 'หลักสูตรหน่วยกิตสะสมไม่มีเครดิต',
            'effective_year'  => 2569,
            'education_level' => 'master',
            'uses_year_level' => 0,
            'is_active'       => 1,
        ])->assertSessionHasErrors('total_credits_required');
    }

    public function test_course_year_level_max_capped_by_curriculum_duration(): void
    {
        $admin = $this->makeUser('admin');
        $curr  = Curriculum::create([
            'name'            => 'หลักสูตรปริญญาเอก ทดสอบ',
            'effective_year'  => 2569,
            'education_level' => 'doctorate',
            'duration_years'  => 3,
            'uses_year_level' => true,
            'is_active'       => true,
        ]);

        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $payload = $this->coursePayload([
            'course_code'        => 'NSDR 401',
            'curriculum_id'      => $curr->id,
            'default_year_level' => 4,
        ]);

        $this->post(route('admin.courses.store'), $payload)
            ->assertSessionHasErrors('default_year_level');
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
            'is_required'                 => 1,
        ], $overrides);
    }
}
