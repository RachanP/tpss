<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_renders_current_phase_badge_and_critical_shortcut(): void
    {
        $admin = $this->makeAdmin();
        AcademicYear::create([
            'name' => '2569',
            'semester' => 1,
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
            'phase' => 'scheduling',
        ]);

        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('สถานะระบบปัจจุบัน')
            ->assertSee('ปีการศึกษา 2569 / เทอม 1')
            ->assertSee('เปิดจัดตาราง')
            ->assertSee('พบเงื่อนไขสำคัญ')
            ->assertSee('ยังเปิดจัดตารางไม่ได้')
            ->assertSee('ไปแก้เงื่อนไขสำคัญ')
            ->assertSee(route('admin.alerts'), false);
    }

    public function test_admin_dashboard_renders_no_active_year_fallback(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('ยังไม่ได้ตั้งค่าปีการศึกษาที่ใช้งาน')
            ->assertSee('ตั้งค่าปีการศึกษา')
            ->assertSee(route('admin.settings', ['tab' => 'academic']), false);
    }

    public function test_admin_dashboard_uses_thai_phase_labels(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        foreach ([
            'preparation' => 'เตรียมข้อมูล',
            'scheduling' => 'เปิดจัดตาราง',
            'published' => 'เผยแพร่แล้ว',
        ] as $phase => $label) {
            AcademicYear::query()->delete();
            AcademicYear::create([
                'name' => '2569',
                'semester' => 1,
                'start_date' => '2026-08-01',
                'end_date' => '2026-12-31',
                'is_active' => true,
                'phase' => $phase,
            ]);

            $this->get(route('admin.dashboard'))
                ->assertOk()
                ->assertSee($label);
        }
    }

    private function makeAdmin(): User
    {
        $user = User::create([
            'username' => 'admin_dashboard',
            'name' => 'Admin Dashboard',
            'email' => 'admin_dashboard@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        UserRole::create([
            'user_id' => $user->id,
            'role' => 'admin',
            'is_primary' => true,
        ]);

        return $user;
    }
}
