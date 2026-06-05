<?php

namespace Tests\Feature;

use App\Models\AcademicCalendar;
use App\Models\AcademicYear;
use App\Models\Curriculum;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * V4 ข้อ 8 — ปฏิทินการศึกษาตามกลุ่ม (academic_calendars)
 */
class AcademicCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::create(['username' => 'admin_u', 'name' => 'Admin', 'email' => 'a@e.com', 'password' => Hash::make('x')]);
        UserRole::create(['user_id' => $u->id, 'role' => 'admin', 'is_primary' => true]);
        return $u;
    }

    private function year(): AcademicYear
    {
        return AcademicYear::create(['name' => '2569', 'start_date' => '2026-06-01', 'end_date' => '2027-03-15', 'is_active' => false, 'phase' => 'preparation']);
    }

    private function terms(): array
    {
        return [
            ['sequence' => 1, 'name' => 'ภาคเรียนที่ 1', 'start_date' => '2026-08-01', 'end_date' => '2026-11-30',
             'midterm_start' => '2026-09-21', 'midterm_end' => '2026-09-25'],
            ['sequence' => 2, 'name' => 'ภาคเรียนที่ 2', 'start_date' => '2026-12-15', 'end_date' => '2027-03-15'],
        ];
    }

    public function test_admin_can_create_group_calendar_with_scope_and_terms(): void
    {
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);
        $year = $this->year();
        $curr = Curriculum::create(['name' => 'พย.บ. 2565', 'effective_year' => 2565, 'education_level' => 'bachelor', 'duration_years' => 4, 'uses_year_level' => true, 'is_active' => true]);

        $this->post(route('admin.settings.calendars.store', $year), [
            'name' => 'ป.ตรี ปี 3-4',
            'curriculum_id' => $curr->id,
            'year_levels' => [3, 4],
            'terms' => $this->terms(),
        ])->assertSessionHasNoErrors();

        $cal = AcademicCalendar::where('academic_year_id', $year->id)->whereNotNull('curriculum_id')->first();
        $this->assertNotNull($cal);
        $this->assertSame('ป.ตรี ปี 3-4', $cal->name);
        $this->assertSame($curr->id, $cal->curriculum_id);
        $this->assertSame([3, 4], $cal->year_levels);
        $this->assertEquals(2, $cal->terms()->count());
    }

    public function test_fallback_calendar_is_catch_all_and_auto_created(): void
    {
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);
        $year = $this->year();

        // fallback = ปฏิทินที่ curriculum/ชั้นปี = null (ทุกหลักสูตร) — สร้างอัตโนมัติ
        $fallback = $year->fallbackCalendar();
        $this->assertNull($fallback->curriculum_id);
        $this->assertNull($fallback->year_levels);

        // เรียกซ้ำได้ตัวเดิม (ไม่ซ้ำ)
        $this->assertSame($fallback->id, $year->fallbackCalendar()->id);
    }

    public function test_active_year_without_default_calendar_terms_is_critical(): void
    {
        $year = \App\Models\AcademicYear::create(['name' => '2569', 'is_active' => true, 'phase' => 'preparation']);
        $year->fallbackCalendar(); // ปฏิทินค่าเริ่มต้นว่าง (ยังไม่มีเทอม)

        $keys = collect(\App\Http\Controllers\Admin\AlertController::getCriticals())->pluck('key')->all();
        $this->assertContains('active_year_missing_calendar_terms', $keys);

        // กำหนดเทอม → critical หาย
        $year->fallbackCalendar()->terms()->create(['sequence' => 1, 'name' => 'เทอม 1', 'start_date' => '2026-08-01', 'end_date' => '2026-12-01']);
        $keys2 = collect(\App\Http\Controllers\Admin\AlertController::getCriticals())->pluck('key')->all();
        $this->assertNotContains('active_year_missing_calendar_terms', $keys2);
    }

    public function test_duplicate_scope_calendar_is_rejected(): void
    {
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);
        $year = $this->year();
        $curr = Curriculum::create(['name' => 'พย.บ. 2565', 'effective_year' => 2565, 'education_level' => 'bachelor', 'duration_years' => 4, 'uses_year_level' => true, 'is_active' => true]);

        $payload = ['name' => 'ป.ตรี ปี 3-4', 'curriculum_id' => $curr->id, 'year_levels' => [3, 4], 'terms' => $this->terms()];
        $this->post(route('admin.settings.calendars.store', $year), $payload)->assertSessionHasNoErrors();

        // สร้างซ้ำ scope เดิม (สลับลำดับชั้นปีก็ถือว่าซ้ำ) → ถูกบล็อก
        $this->post(route('admin.settings.calendars.store', $year), array_merge($payload, ['name' => 'อีกอัน', 'year_levels' => [4, 3]]))
            ->assertSessionHasErrors('curriculum_id');

        $this->assertEquals(1, AcademicCalendar::where('academic_year_id', $year->id)->where('curriculum_id', $curr->id)->count());
    }

    public function test_update_calendar_to_existing_scope_is_rejected(): void
    {
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);
        $year = $this->year();
        $curr = Curriculum::create(['name' => 'พย.บ. 2565', 'effective_year' => 2565, 'education_level' => 'bachelor', 'duration_years' => 4, 'uses_year_level' => true, 'is_active' => true]);

        $year->calendars()->create(['name' => 'A', 'curriculum_id' => $curr->id, 'year_levels' => [1]]);
        $calB = $year->calendars()->create(['name' => 'B', 'curriculum_id' => $curr->id, 'year_levels' => [2]]);

        // แก้ B ให้ scope ชนกับ A → บล็อก
        $this->put(route('admin.settings.calendars.update', $calB), [
            'name' => 'B', 'curriculum_id' => $curr->id, 'year_levels' => [1], 'terms' => $this->terms(),
        ])->assertSessionHasErrors('curriculum_id');

        // แก้ B โดยคง scope เดิม (ไม่ชนตัวเอง) → ผ่าน
        $this->put(route('admin.settings.calendars.update', $calB), [
            'name' => 'B2', 'curriculum_id' => $curr->id, 'year_levels' => [2], 'terms' => $this->terms(),
        ])->assertSessionHasNoErrors();
    }

    public function test_create_year_copying_from_previous_year_shifts_dates(): void
    {
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);

        // ปีต้นทางมีปฏิทิน + เทอม
        $source = AcademicYear::create(['name' => '2569', 'is_active' => false, 'phase' => 'preparation']);
        $sourceCal = $source->fallbackCalendar();
        $sourceCal->terms()->create(['sequence' => 1, 'name' => 'ภาคเรียนที่ 1', 'start_date' => '2026-08-01', 'end_date' => '2026-11-30']);

        // สร้างปีใหม่ผ่านโมดัล "เพิ่มปีการศึกษา" + เลือกคัดลอกจากปีต้นทาง
        $this->post(route('admin.settings.years.store'), [
            'name'              => '2571',
            'copy_from_year_id' => $source->id,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $target = AcademicYear::where('name', '2571')->firstOrFail();
        $copied = $target->calendars()->whereNull('curriculum_id')->first();
        $this->assertNotNull($copied);
        $term = $copied->terms()->first();
        // 2571 - 2569 = 2 ปี → เลื่อนวันที่ +2 ปี
        $this->assertSame('2028-08-01', $term->start_date->format('Y-m-d'));
        $this->assertSame('2028-11-30', $term->end_date->format('Y-m-d'));
        // วันเริ่ม-สิ้นสุดปีถูก derive จากเทอมที่คัดลอกมา
        $this->assertSame('2028-08-01', (string) $target->fresh()->start_date);
    }

    public function test_group_calendar_can_be_deleted_with_terms(): void
    {
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);
        $year = $this->year();
        $cal = $year->calendars()->create(['name' => 'ป.โท', 'curriculum_id' => null]);
        $cal->terms()->create(['sequence' => 1, 'name' => 'เทอม 1', 'start_date' => '2026-08-01', 'end_date' => '2026-12-01']);

        $this->delete(route('admin.settings.calendars.destroy', $cal))->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('academic_calendars', ['id' => $cal->id]);
        $this->assertDatabaseMissing('terms', ['academic_calendar_id' => $cal->id]);
    }
}
