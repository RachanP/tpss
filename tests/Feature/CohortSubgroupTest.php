<?php

namespace Tests\Feature;

use App\Models\Curriculum;
use App\Models\StudentCohort;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * V4 — กลุ่มย่อยจากกลุ่มใหญ่ (subgroup): กลุ่มใหญ่ A → A1, A2
 * - กลุ่มใหญ่: รหัสตัวอักษรล้วน (ห้ามมีตัวเลข)
 * - กลุ่มย่อย: optional, ผูก parent_id, รหัสมีตัวเลขได้
 */
class CohortSubgroupTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::create([
            'username' => 'admin_user',
            'name'     => 'Admin User',
            'email'    => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);
        UserRole::create(['user_id' => $user->id, 'role' => 'admin', 'is_primary' => true]);

        return $user;
    }

    private function bachelorCurriculum(): Curriculum
    {
        return Curriculum::create([
            'name'            => 'หลักสูตรพยาบาลศาสตรบัณฑิต 2565',
            'effective_year'  => 2565,
            'education_level' => 'bachelor',
            'duration_years'  => 4,
            'uses_year_level' => true,
            'is_active'       => true,
        ]);
    }

    private function actingAdmin(): void
    {
        $this->actingAs($this->makeAdmin())->withSession(['active_role' => 'admin']);
    }

    public function test_major_group_code_must_be_letters_only(): void
    {
        $this->actingAdmin();
        $curr = $this->bachelorCurriculum();

        $response = $this->from(route('admin.master_data'))->post(route('admin.student_cohorts.store'), [
            'cohort_form'   => '1',
            'curriculum_id' => $curr->id,
            'year_level'    => 3,
            'code'          => 'A1',          // มีตัวเลข → ต้องไม่ผ่าน
            'student_count' => 80,
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertDatabaseMissing('student_cohorts', ['code' => 'A1', 'curriculum_id' => $curr->id]);
    }

    public function test_create_major_with_subgroups(): void
    {
        $this->actingAdmin();
        $curr = $this->bachelorCurriculum();

        $this->post(route('admin.student_cohorts.store'), [
            'cohort_form'   => '1',
            'curriculum_id' => $curr->id,
            'year_level'    => 3,
            'code'          => 'A',
            'student_count' => 80,
            'subgroups'     => [
                ['code' => 'A1', 'student_count' => 40],
                ['code' => 'A2', 'student_count' => 40],
            ],
        ])->assertSessionHasNoErrors();

        $major = StudentCohort::where('curriculum_id', $curr->id)->where('code', 'A')->first();
        $this->assertNotNull($major);
        $this->assertNull($major->parent_id);

        $subs = StudentCohort::where('parent_id', $major->id)->orderBy('code')->get();
        $this->assertCount(2, $subs);
        $this->assertEquals(['A1', 'A2'], $subs->pluck('code')->all());
        $this->assertEquals(3, $subs->first()->year_level);            // สืบทอดชั้นปีจากกลุ่มใหญ่
        $this->assertEquals($curr->id, $subs->first()->curriculum_id);
    }

    public function test_subgroups_are_optional(): void
    {
        $this->actingAdmin();
        $curr = $this->bachelorCurriculum();

        $this->post(route('admin.student_cohorts.store'), [
            'cohort_form'   => '1',
            'curriculum_id' => $curr->id,
            'year_level'    => 1,
            'code'          => 'B',
            'student_count' => 120,
        ])->assertSessionHasNoErrors();

        $this->assertEquals(1, StudentCohort::where('curriculum_id', $curr->id)->count());
        $this->assertDatabaseHas('student_cohorts', ['code' => 'B', 'parent_id' => null]);
    }

    public function test_deleting_major_cascades_subgroups(): void
    {
        $this->actingAdmin();
        $curr = $this->bachelorCurriculum();

        $major = StudentCohort::create([
            'curriculum_id' => $curr->id, 'year_level' => 3, 'code' => 'A', 'student_count' => 80,
        ]);
        StudentCohort::create([
            'curriculum_id' => $curr->id, 'parent_id' => $major->id, 'year_level' => 3, 'code' => 'A1', 'student_count' => 40,
        ]);

        $this->delete(route('admin.student_cohorts.destroy', $major))->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('student_cohorts', ['code' => 'A', 'id' => $major->id]);
        $this->assertEquals(0, StudentCohort::where('curriculum_id', $curr->id)->count());
    }

    public function test_seeders_support_new_columns(): void
    {
        $this->seed(\Database\Seeders\CurriculumSeeder::class);
        $this->seed(\Database\Seeders\StudentCohortSeeder::class);

        // counts_service_only: ป.ตรี=false · หลักสูตรเฉพาะทาง=true
        $this->assertDatabaseHas('curriculums', [
            'name' => 'หลักสูตรพยาบาลศาสตรบัณฑิต (ปรับปรุง 2565)', 'counts_service_only' => 0,
        ]);
        $this->assertDatabaseHas('curriculums', ['counts_service_only' => 1]);

        // parent_id: กลุ่มใหญ่ A ปี 3 มีกลุ่มย่อย 2 กลุ่ม (A1, A2)
        $majorA = StudentCohort::where('code', 'A')->where('year_level', 3)->whereNull('parent_id')->first();
        $this->assertNotNull($majorA);
        $subs = StudentCohort::where('parent_id', $majorA->id)->orderBy('code')->pluck('code')->all();
        $this->assertEquals(['A1', 'A2'], $subs);

        // ปี 3-4 = 4 กลุ่มใหญ่ A–D (V4)
        $this->assertEquals(4, StudentCohort::where('year_level', 3)->whereNull('parent_id')->count());
    }

    public function test_duplicate_subgroup_codes_are_rejected(): void
    {
        $this->actingAdmin();
        $curr = $this->bachelorCurriculum();

        $this->from(route('admin.master_data'))->post(route('admin.student_cohorts.store'), [
            'cohort_form'   => '1',
            'curriculum_id' => $curr->id,
            'year_level'    => 3,
            'code'          => 'A',
            'student_count' => 80,
            'subgroups'     => [
                ['code' => 'A1', 'student_count' => 40],
                ['code' => 'A1', 'student_count' => 40], // ซ้ำ
            ],
        ])->assertSessionHasErrors();

        $this->assertDatabaseMissing('student_cohorts', ['code' => 'A', 'curriculum_id' => $curr->id]);
    }
}
