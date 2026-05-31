<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AcademicYearV2Test extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::create([
            'username' => 'admin_year',
            'name'     => 'Admin Year',
            'email'    => 'adminyear@example.com',
            'password' => Hash::make('password'),
        ]);
        UserRole::create(['user_id' => $user->id, 'role' => 'admin', 'is_primary' => true]);
        return $user;
    }

    public function test_settings_page_renders(): void
    {
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);

        $this->get(route('admin.settings', ['tab' => 'academic']))
            ->assertOk()
            ->assertSee('ปีการศึกษา');
    }

    public function test_create_year_without_semester_auto_creates_two_terms(): void
    {
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);

        // วันจันทร์-ศุกร์ (กัน weekday rule): 2026-06-01 จันทร์, 2027-03-12 ศุกร์
        $this->post(route('admin.settings.years.store'), [
            'name'       => '2570',
            'start_date' => '2026-06-01',
            'end_date'   => '2027-03-12',
        ])->assertRedirect(route('admin.settings', ['tab' => 'academic']));

        $year = AcademicYear::where('name', '2570')->firstOrFail();
        $this->assertCount(2, $year->terms);
        $this->assertSame('ภาคเรียนที่ 1', $year->terms[0]->name);
        $this->assertSame(1, $year->terms[0]->sequence);
    }

    public function test_year_name_is_unique_without_semester(): void
    {
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);
        AcademicYear::create(['name' => '2570', 'start_date' => '2026-06-01', 'end_date' => '2027-03-12', 'is_active' => false]);

        $this->post(route('admin.settings.years.store'), [
            'name'       => '2570',
            'start_date' => '2026-06-01',
            'end_date'   => '2027-03-12',
        ])->assertSessionHasErrors('name');
    }
}
