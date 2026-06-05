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
            'year_level_min' => 3,
            'year_level_max' => 4,
            'terms' => $this->terms(),
        ])->assertSessionHasNoErrors();

        $cal = AcademicCalendar::where('academic_year_id', $year->id)->whereNotNull('curriculum_id')->first();
        $this->assertNotNull($cal);
        $this->assertSame('ป.ตรี ปี 3-4', $cal->name);
        $this->assertSame($curr->id, $cal->curriculum_id);
        $this->assertSame(3, $cal->year_level_min);
        $this->assertEquals(2, $cal->terms()->count());
    }

    public function test_fallback_calendar_is_catch_all_and_auto_created(): void
    {
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);
        $year = $this->year();

        // fallback = ปฏิทินที่ curriculum/ชั้นปี = null (ทุกหลักสูตร) — สร้างอัตโนมัติ
        $fallback = $year->fallbackCalendar();
        $this->assertNull($fallback->curriculum_id);
        $this->assertNull($fallback->year_level_min);

        // เรียกซ้ำได้ตัวเดิม (ไม่ซ้ำ)
        $this->assertSame($fallback->id, $year->fallbackCalendar()->id);
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
