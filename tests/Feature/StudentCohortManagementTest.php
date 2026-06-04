<?php

namespace Tests\Feature;

use App\Models\Curriculum;
use App\Models\StudentCohort;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentCohortManagementTest extends TestCase
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

    private function bachelorCurriculum(array $overrides = []): Curriculum
    {
        return Curriculum::create(array_merge([
            'name'            => 'หลักสูตรพยาบาลศาสตรบัณฑิต 2565',
            'effective_year'  => 2565,
            'education_level' => 'bachelor',
            'duration_years'  => 4,
            'uses_year_level' => true,
            'is_active'       => true,
        ], $overrides));
    }

    public function test_master_data_page_renders_with_cohort_tab(): void
    {
        $admin = $this->makeUser('admin');
        $this->bachelorCurriculum();

        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $this->get(route('admin.master_data', ['tab' => 'student_cohorts']))
            ->assertOk()
            ->assertSee('master-data-tab-student-cohorts', false);
    }

    public function test_admin_can_create_student_cohort(): void
    {
        $admin = $this->makeUser('admin');
        $curr  = $this->bachelorCurriculum();

        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $this->post(route('admin.student_cohorts.store'), [
            'curriculum_id' => $curr->id,
            'year_level'    => 3,
            'code'          => 'A',
            'student_count' => 80,
        ])->assertRedirect(route('admin.master_data', ['tab' => 'student_cohorts']));

        $this->assertDatabaseHas('student_cohorts', [
            'curriculum_id' => $curr->id,
            'year_level'    => 3,
            'code'          => 'A',
            'student_count' => 80,
        ]);
    }

    public function test_admin_can_update_student_cohort(): void
    {
        $admin = $this->makeUser('admin');
        $curr  = $this->bachelorCurriculum();
        $cohort = StudentCohort::create([
            'curriculum_id' => $curr->id, 'year_level' => 3, 'code' => 'A', 'student_count' => 80,
        ]);

        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $this->put(route('admin.student_cohorts.update', $cohort), [
            'curriculum_id' => $curr->id,
            'year_level'    => 3,
            'code'          => 'A',
            'student_count' => 78,
        ])->assertRedirect(route('admin.master_data', ['tab' => 'student_cohorts']));

        $this->assertSame(78, $cohort->fresh()->student_count);
    }

    public function test_admin_can_delete_student_cohort(): void
    {
        $admin = $this->makeUser('admin');
        $curr  = $this->bachelorCurriculum();
        $cohort = StudentCohort::create([
            'curriculum_id' => $curr->id, 'year_level' => 1, 'code' => 'กลุ่มใหญ่', 'student_count' => 300,
        ]);

        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $this->delete(route('admin.student_cohorts.destroy', $cohort))
            ->assertRedirect(route('admin.master_data', ['tab' => 'student_cohorts']));

        $this->assertDatabaseMissing('student_cohorts', ['id' => $cohort->id]);
    }

    public function test_duplicate_code_in_same_curriculum_year_is_rejected(): void
    {
        $admin = $this->makeUser('admin');
        $curr  = $this->bachelorCurriculum();
        StudentCohort::create([
            'curriculum_id' => $curr->id, 'year_level' => 3, 'code' => 'A', 'student_count' => 80,
        ]);

        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $this->post(route('admin.student_cohorts.store'), [
            'curriculum_id' => $curr->id,
            'year_level'    => 3,
            'code'          => 'A',
            'student_count' => 80,
        ])->assertSessionHasErrors('code');
    }

    public function test_same_code_allowed_in_different_year_level(): void
    {
        $admin = $this->makeUser('admin');
        $curr  = $this->bachelorCurriculum();
        StudentCohort::create([
            'curriculum_id' => $curr->id, 'year_level' => 3, 'code' => 'A', 'student_count' => 80,
        ]);

        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        // A ในปี 4 ต่างปีกัน → อนุญาต
        $this->post(route('admin.student_cohorts.store'), [
            'curriculum_id' => $curr->id,
            'year_level'    => 4,
            'code'          => 'A',
            'student_count' => 80,
        ])->assertRedirect(route('admin.master_data', ['tab' => 'student_cohorts']));

        $this->assertDatabaseHas('student_cohorts', ['curriculum_id' => $curr->id, 'year_level' => 4, 'code' => 'A']);
    }

    public function test_master_curriculum_creates_cohort_without_year_level(): void
    {
        $admin = $this->makeUser('admin');
        $master = Curriculum::create([
            'name' => 'หลักสูตร ป.โท', 'effective_year' => 2569,
            'education_level' => 'master', 'duration_years' => 2, 'uses_year_level' => false, 'is_active' => true,
        ]);

        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        // ส่ง year_level มาด้วยก็ตาม — ระบบต้องบังคับเป็น null เพราะหลักสูตรไม่ใช้ชั้นปี
        $this->post(route('admin.student_cohorts.store'), [
            'curriculum_id' => $master->id,
            'year_level'    => 1,
            'code'          => 'กลุ่ม A',
            'student_count' => 20,
        ])->assertRedirect(route('admin.master_data', ['tab' => 'student_cohorts']));

        $this->assertDatabaseHas('student_cohorts', [
            'curriculum_id' => $master->id,
            'year_level'    => null,
            'code'          => 'กลุ่ม A',
        ]);
    }

    public function test_master_curriculum_duplicate_code_is_rejected(): void
    {
        $admin = $this->makeUser('admin');
        $master = Curriculum::create([
            'name' => 'หลักสูตร ป.เอก', 'effective_year' => 2569,
            'education_level' => 'doctorate', 'duration_years' => 3, 'uses_year_level' => false, 'is_active' => true,
        ]);
        StudentCohort::create([
            'curriculum_id' => $master->id, 'year_level' => null, 'code' => 'กลุ่ม A', 'student_count' => 15,
        ]);

        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $this->post(route('admin.student_cohorts.store'), [
            'curriculum_id' => $master->id,
            'code'          => 'กลุ่ม A',
            'student_count' => 15,
        ])->assertSessionHasErrors('code');
    }

    public function test_year_level_exceeding_curriculum_duration_is_rejected(): void
    {
        $admin = $this->makeUser('admin');
        $curr  = $this->bachelorCurriculum(['duration_years' => 4]);

        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $this->post(route('admin.student_cohorts.store'), [
            'curriculum_id' => $curr->id,
            'year_level'    => 5,
            'code'          => 'A',
            'student_count' => 80,
        ])->assertSessionHasErrors('year_level');
    }
}
