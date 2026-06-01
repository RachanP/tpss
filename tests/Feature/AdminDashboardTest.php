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
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
            'phase' => 'scheduling',
        ]);

        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('สถานะระบบปัจจุบัน')
            ->assertSee('ปีการศึกษา 2569')
            ->assertSee('เปิดจัดตาราง')          // phase pill (lifecycle)
            ->assertSee('ยังเปิดจัดตารางไม่ได้')   // critical-state title
            ->assertSee('ไปแก้เงื่อนไขสำคัญ')      // critical shortcut action
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

    public function test_admin_dashboard_renders_visual_overview_section(): void
    {
        $admin = $this->makeAdmin();
        AcademicYear::create([
            'name' => '2569',
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
            'phase' => 'scheduling',
        ]);

        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="admin-phase-stepper"', false) // lifecycle visual
            ->assertSee('data-testid="admin-visual-overview"', false)
            ->assertDontSee('สถานะรายวิชาเปิดสอน')
            ->assertDontSee('ภาพรวมทรัพยากร')
            ->assertSee('หลักสูตรแยกตามระดับ')
            ->assertSee('ห้องและสถานที่แยกประเภท')
            ->assertSee('<svg', false); // donut SVG render
    }

    public function test_admin_sidebar_uses_read_only_schedule_and_report_labels(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('จัดการตารางสอน')
            ->assertSee('ตารางและรายงาน')
            ->assertSee('ตารางสอนที่เผยแพร่แล้ว')
            ->assertSee('รายงานภาระงาน');
    }

    public function test_admin_role_cannot_access_schedule_management_workspace(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $this->get(route('maker.schedules.index'))->assertForbidden();
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
