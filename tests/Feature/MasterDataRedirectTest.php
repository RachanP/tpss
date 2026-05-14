<?php

namespace Tests\Feature;

use App\Models\ActivityType;
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
            ->assertSessionHasErrors('course_code');
    }
}
