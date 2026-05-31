<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\User;
use App\Models\UserRole;
use App\Services\HolidayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HolidayManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::create(['username' => 'admin_hol', 'name' => 'Admin Hol', 'email' => 'ah@example.com', 'password' => Hash::make('p')]);
        UserRole::create(['user_id' => $u->id, 'role' => 'admin', 'is_primary' => true]);
        return $u;
    }

    private function fakeNager(): void
    {
        Http::fake([
            '*/PublicHolidays/2026/TH' => Http::response([
                ['date' => '2026-01-01', 'localName' => 'วันขึ้นปีใหม่', 'name' => "New Year's Day"],
                ['date' => '2026-04-13', 'localName' => 'วันสงกรานต์', 'name' => 'Songkran'],
            ], 200),
            '*/PublicHolidays/2027/TH' => Http::response([
                ['date' => '2027-01-01', 'localName' => 'วันขึ้นปีใหม่', 'name' => "New Year's Day"],
            ], 200),
        ]);
    }

    public function test_service_syncs_and_is_idempotent(): void
    {
        $this->fakeNager();
        $svc = app(HolidayService::class);

        $this->assertSame(2, $svc->syncYear(2026));
        $this->assertSame(2, Holiday::count());

        // รันซ้ำ → ไม่เพิ่มซ้ำ (updateOrCreate by date)
        $svc->syncYear(2026);
        $this->assertSame(2, Holiday::count());
        $this->assertDatabaseHas('holidays', ['date' => '2026-01-01', 'source' => 'nager']);
    }

    public function test_service_fail_safe_returns_null_on_api_error(): void
    {
        Http::fake(['*' => Http::response('err', 500)]);
        $this->assertNull(app(HolidayService::class)->syncYear(2026));
        $this->assertSame(0, Holiday::count());
    }

    public function test_creating_year_auto_fetches_holidays_for_spanned_calendar_years(): void
    {
        $this->fakeNager();
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);

        $this->post(route('admin.settings.years.store'), [
            'name'  => '2569',
            'terms' => [
                ['sequence' => 1, 'name' => 'ภาคเรียนที่ 1', 'start_date' => '2026-06-01', 'end_date' => '2026-10-15'],
                ['sequence' => 2, 'name' => 'ภาคเรียนที่ 2', 'start_date' => '2026-11-02', 'end_date' => '2027-03-12'],
            ],
        ])->assertRedirect(route('admin.settings', ['tab' => 'academic']));

        // ปีคร่อม 2026+2027 → ดึงทั้งสองปี (2 + 1 = 3 วัน)
        $this->assertDatabaseHas('holidays', ['date' => '2026-01-01']);
        $this->assertDatabaseHas('holidays', ['date' => '2027-01-01']);
        $this->assertSame(3, Holiday::count());
    }

    public function test_year_create_still_succeeds_when_holiday_api_down(): void
    {
        Http::fake(['*' => Http::response('down', 503)]);
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);

        $this->post(route('admin.settings.years.store'), [
            'name'  => '2569',
            'terms' => [['sequence' => 1, 'name' => 'ภาคเรียนที่ 1', 'start_date' => '2026-06-01', 'end_date' => '2026-10-15']],
        ])->assertRedirect(route('admin.settings', ['tab' => 'academic']))
            ->assertSessionHas('holiday_warning');

        $this->assertDatabaseHas('academic_years', ['name' => '2569']);
        $this->assertSame(0, Holiday::count());
    }

    public function test_admin_can_add_holiday_manually(): void
    {
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);

        $this->post(route('admin.settings.holidays.store'), [
            'date' => '2026-12-10', 'name' => 'วันรัฐธรรมนูญ',
        ])->assertRedirect(route('admin.settings', ['tab' => 'academic']));

        $this->assertDatabaseHas('holidays', ['date' => '2026-12-10', 'name' => 'วันรัฐธรรมนูญ', 'source' => 'manual']);
    }

    public function test_duplicate_holiday_date_rejected(): void
    {
        Holiday::create(['date' => '2026-12-10', 'name' => 'X', 'source' => 'manual']);
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);

        $this->post(route('admin.settings.holidays.store'), ['date' => '2026-12-10', 'name' => 'ซ้ำ'])
            ->assertSessionHasErrors('date');
    }

    public function test_admin_can_delete_holiday(): void
    {
        $h = Holiday::create(['date' => '2026-12-10', 'name' => 'X', 'source' => 'manual']);
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);

        $this->delete(route('admin.settings.holidays.destroy', $h))
            ->assertRedirect(route('admin.settings', ['tab' => 'academic']));
        $this->assertDatabaseMissing('holidays', ['id' => $h->id]);
    }
}
