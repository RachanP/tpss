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

    private function staff(): User
    {
        $u = User::create(['username' => 'staff_hol', 'name' => 'Staff Hol', 'email' => 'sh@example.com', 'password' => Hash::make('p')]);
        UserRole::create(['user_id' => $u->id, 'role' => 'staff', 'is_primary' => true]);
        return $u;
    }

    /** fake ปฏิทินวันหยุดไทยของ Google (ICS) — 2 วันปี 2026 + 1 วันปี 2027 */
    private function fakeGoogleHolidays(): void
    {
        $ics = implode("\n", [
            'BEGIN:VCALENDAR',
            'BEGIN:VEVENT', 'DTSTART;VALUE=DATE:20260101', 'SUMMARY:วันขึ้นปีใหม่', 'END:VEVENT',
            'BEGIN:VEVENT', 'DTSTART;VALUE=DATE:20260413', 'SUMMARY:วันสงกรานต์', 'END:VEVENT',
            'BEGIN:VEVENT', 'DTSTART;VALUE=DATE:20270101', 'SUMMARY:วันขึ้นปีใหม่', 'END:VEVENT',
            'END:VCALENDAR',
        ]);

        Http::fake(['calendar.google.com/*' => Http::response($ics, 200)]);
    }

    public function test_service_syncs_and_is_idempotent(): void
    {
        $this->fakeGoogleHolidays();
        $svc = app(HolidayService::class);

        $this->assertSame(2, $svc->syncYear(2026));
        $this->assertSame(2, Holiday::count());

        // รันซ้ำ → ไม่เพิ่มซ้ำ (updateOrCreate by date)
        $svc->syncYear(2026);
        $this->assertSame(2, Holiday::count());
        $this->assertDatabaseHas('holidays', ['date' => '2026-01-01', 'source' => 'google']);
    }

    public function test_resync_replaces_stale_auto_but_keeps_manual_and_other_years(): void
    {
        // ของเดิม: auto ปี 2026 ที่จะหายจาก ICS ใหม่, manual ปี 2026 (ห้ามลบ), auto ปี 2025 (ปีอื่น ห้ามลบ)
        Holiday::create(['date' => '2026-05-05', 'name' => 'auto เก่าที่ถูกยกเลิก', 'source' => 'google']);
        Holiday::create(['date' => '2026-04-13', 'name' => 'งดเรียนเฉพาะคณะ', 'source' => 'manual']);
        Holiday::create(['date' => '2025-12-31', 'name' => 'auto ปีก่อน', 'source' => 'google']);

        $this->fakeGoogleHolidays();
        app(HolidayService::class)->syncYear(2026);

        // auto เก่าของ 2026 ที่ไม่อยู่ใน ICS ใหม่ → ถูกลบ
        $this->assertDatabaseMissing('holidays', ['date' => '2026-05-05']);
        // manual ของ 2026 → คงอยู่ (ไม่ถูกทับ)
        $this->assertDatabaseHas('holidays', ['date' => '2026-04-13', 'name' => 'งดเรียนเฉพาะคณะ', 'source' => 'manual']);
        // ปีอื่น (2025) → คงอยู่
        $this->assertDatabaseHas('holidays', ['date' => '2025-12-31']);
        // วันใหม่จาก ICS → เพิ่มเข้ามา
        $this->assertDatabaseHas('holidays', ['date' => '2026-01-01', 'source' => 'google']);
    }

    public function test_service_fail_safe_returns_null_on_api_error(): void
    {
        Http::fake(['*' => Http::response('err', 500)]);
        $this->assertNull(app(HolidayService::class)->syncYear(2026));
        $this->assertSame(0, Holiday::count());
    }

    public function test_setting_calendar_terms_auto_fetches_holidays_for_spanned_years(): void
    {
        // V4: holiday ดึงตอน "ตั้งเทอมในปฏิทิน" (วันปี derive จากเทอม) ไม่ใช่ตอนสร้างปี
        $this->fakeGoogleHolidays();
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);

        $this->post(route('admin.settings.years.store'), ['name' => '2569'])->assertSessionHasNoErrors();
        $year = \App\Models\AcademicYear::where('name', '2569')->firstOrFail();

        $this->put(route('admin.settings.calendars.update', $year->fallbackCalendar()), [
            'name'  => 'ปฏิทินหลัก',
            'terms' => [
                ['sequence' => 1, 'name' => 'ภาคเรียนที่ 1', 'start_date' => '2026-06-01', 'end_date' => '2026-10-15'],
                ['sequence' => 2, 'name' => 'ภาคเรียนที่ 2', 'start_date' => '2026-11-02', 'end_date' => '2027-03-12'],
            ],
        ])->assertSessionHasNoErrors();

        // ปีคร่อม 2026+2027 → ดึงทั้งสองปี (2 + 1 = 3 วัน)
        $this->assertDatabaseHas('holidays', ['date' => '2026-01-01']);
        $this->assertDatabaseHas('holidays', ['date' => '2027-01-01']);
        $this->assertSame(3, Holiday::count());
    }

    public function test_setting_calendar_terms_succeeds_when_holiday_api_down(): void
    {
        Http::fake(['*' => Http::response('down', 503)]);
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);

        $this->post(route('admin.settings.years.store'), ['name' => '2569'])->assertSessionHasNoErrors();
        $year = \App\Models\AcademicYear::where('name', '2569')->firstOrFail();

        $this->put(route('admin.settings.calendars.update', $year->fallbackCalendar()), [
            'name'  => 'ปฏิทินหลัก',
            'terms' => [['sequence' => 1, 'name' => 'ภาคเรียนที่ 1', 'start_date' => '2026-06-01', 'end_date' => '2026-10-15']],
        ])->assertRedirect()->assertSessionHas('holiday_warning');

        $this->assertDatabaseHas('terms', ['name' => 'ภาคเรียนที่ 1']);
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

    public function test_staff_settings_lists_and_manages_holidays(): void
    {
        Holiday::create(['date' => '2026-12-10', 'name' => 'วันหยุด Staff', 'source' => 'manual']);

        $this->actingAs($this->staff())->withSession(['active_role' => 'staff']);

        $this->get(route('staff.settings'))
            ->assertOk()
            ->assertSee('วันหยุด Staff');

        $this->post(route('staff.settings.holidays.store'), [
            'date' => '2026-12-11',
            'name' => 'วันหยุดเพิ่มโดย Staff',
        ])->assertRedirect(route('staff.settings', ['tab' => 'academic']));

        $this->assertDatabaseHas('holidays', [
            'date' => '2026-12-11',
            'name' => 'วันหยุดเพิ่มโดย Staff',
            'source' => 'manual',
        ]);
    }

    public function test_duplicate_holiday_date_rejected(): void
    {
        Holiday::create(['date' => '2026-12-10', 'name' => 'X', 'source' => 'manual']);
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);

        $this->post(route('admin.settings.holidays.store'), ['date' => '2026-12-10', 'name' => 'ซ้ำ'])
            ->assertSessionHasErrors('date');
    }

    public function test_sync_button_requires_active_year(): void
    {
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);

        $this->post(route('admin.settings.holidays.sync'))
            ->assertRedirect(route('admin.settings', ['tab' => 'academic']))
            ->assertSessionHas('error');

        $this->assertSame(0, Holiday::count());
    }

    public function test_sync_button_fetches_active_year_span(): void
    {
        \App\Models\AcademicYear::create([
            'name' => '2569', 'start_date' => '2026-06-01', 'end_date' => '2027-03-12', 'is_active' => true,
        ]);
        $this->fakeGoogleHolidays();
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);

        $this->post(route('admin.settings.holidays.sync'))
            ->assertRedirect(route('admin.settings', ['tab' => 'academic']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('holidays', ['date' => '2026-01-01']);
        $this->assertDatabaseHas('holidays', ['date' => '2027-01-01']);
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
