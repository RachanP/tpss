<?php

namespace Tests\Feature;

use App\Models\Curriculum;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * V4 ข้อ 4 — flag หลักสูตร "นับงานบริการวิชาการอย่างเดียว" (counts_service_only)
 * ครอบคลุม fill/validation: ติ๊ก=true, ไม่ติ๊ก/absent=false, แก้ไขสลับค่าได้
 */
class CurriculumServiceOnlyFlagTest extends TestCase
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

    /** payload ขั้นต่ำของหลักสูตร ป.ตรี (uses_year_level=1) */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'curriculum_form'        => '1',
            'name'                   => 'หลักสูตรการพยาบาลเฉพาะทาง 2568',
            'effective_year'         => 2568,
            'education_level'        => 'bachelor',
            'uses_year_level'        => '1',
            'duration_years'         => 4,
            'total_credits_required' => '',
            'is_active'              => '1',
        ], $overrides);
    }

    public function test_create_with_flag_on_stores_true(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $response = $this->post(
            route('admin.curriculums.store'),
            $this->validPayload(['counts_service_only' => '1'])
        );

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('curriculums', [
            'name'                => 'หลักสูตรการพยาบาลเฉพาะทาง 2568',
            'counts_service_only' => 1,
        ]);
    }

    public function test_create_without_field_defaults_to_false(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        // ไม่ส่ง counts_service_only เลย (เหมือน checkbox ไม่ติ๊ก) — normalize ต้องเซ็ตเป็น false
        $response = $this->post(route('admin.curriculums.store'), $this->validPayload());

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('curriculums', [
            'name'                => 'หลักสูตรการพยาบาลเฉพาะทาง 2568',
            'counts_service_only' => 0,
        ]);
    }

    public function test_checkbox_style_truthy_value_is_coerced_to_true(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        // ค่าจาก checkbox อาจมาเป็น "on" — fill ต้อง coerce เป็น true
        $response = $this->post(
            route('admin.curriculums.store'),
            $this->validPayload(['counts_service_only' => 'on'])
        );

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('curriculums', [
            'name'                => 'หลักสูตรการพยาบาลเฉพาะทาง 2568',
            'counts_service_only' => 1,
        ]);
    }

    public function test_update_can_toggle_flag(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $curriculum = $this->bachelorCurriculum(['counts_service_only' => false]);

        // เปิด flag
        $this->put(
            route('admin.curriculums.update', $curriculum),
            $this->validPayload([
                'name'                => $curriculum->name,
                'effective_year'      => $curriculum->effective_year,
                'counts_service_only' => '1',
            ])
        )->assertSessionHasNoErrors();

        $this->assertTrue((bool) $curriculum->fresh()->counts_service_only);

        // ปิด flag กลับ (ไม่ส่ง field = ไม่ติ๊ก)
        $this->put(
            route('admin.curriculums.update', $curriculum),
            $this->validPayload([
                'name'           => $curriculum->name,
                'effective_year' => $curriculum->effective_year,
            ])
        )->assertSessionHasNoErrors();

        $this->assertFalse((bool) $curriculum->fresh()->counts_service_only);
    }

    public function test_master_data_page_shows_service_only_field(): void
    {
        $admin = $this->makeAdmin();
        $this->bachelorCurriculum();
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $this->get(route('admin.master_data', ['tab' => 'curriculums']))
            ->assertOk()
            ->assertSee('curriculum-counts-service-only', false);
    }
}
