<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\Department;
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

    /** สร้างปีผ่าน route (storeYear) → ได้ปี + ปฏิทินหลักว่าง */
    private function createYear(string $name = '2570', array $extra = []): AcademicYear
    {
        $this->post(route('admin.settings.years.store'), array_merge(['name' => $name], $extra))
            ->assertSessionHasNoErrors();
        return AcademicYear::where('name', $name)->firstOrFail();
    }

    /** เทอมที่ส่งจากฟอร์ม (รูปแบบ ISO — normalizeTermDates รับได้) */
    private function termsPayload(): array
    {
        return [
            ['sequence' => 1, 'name' => 'ภาคเรียนที่ 1', 'start_date' => '2026-06-01', 'end_date' => '2026-10-15',
             'midterm_start' => '2026-07-27', 'midterm_end' => '2026-07-31', 'final_start' => '2026-10-05', 'final_end' => '2026-10-09'],
            ['sequence' => 2, 'name' => 'ภาคเรียนที่ 2', 'start_date' => '2026-11-02', 'end_date' => '2027-03-12'],
        ];
    }

    public function test_settings_page_renders(): void
    {
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);

        $this->get(route('admin.settings', ['tab' => 'academic']))
            ->assertOk()
            ->assertSee('ปีการศึกษา');
    }

    public function test_create_year_without_terms_creates_default_calendar(): void
    {
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);

        // V4: ปีสร้างได้โดยไม่ต้องมีเทอม (เทอมไปอยู่ในปฏิทิน)
        $this->post(route('admin.settings.years.store'), ['name' => '2570'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.settings', ['tab' => 'academic']));

        $year = AcademicYear::where('name', '2570')->firstOrFail();
        $this->assertNull($year->start_date);                       // ยังไม่มีเทอม → span ว่าง
        $this->assertEquals(1, $year->calendars()->where('is_default', true)->count());
    }

    public function test_setting_default_calendar_terms_derives_year_span(): void
    {
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);
        $year = $this->createYear('2570');
        $default = $year->defaultCalendar();

        $this->put(route('admin.settings.calendars.update', $default), [
            'name'  => 'ปฏิทินหลัก',
            'terms' => $this->termsPayload(),
        ])->assertSessionHasNoErrors();

        $year->refresh();
        $this->assertSame('2026-06-01', (string) $year->start_date);  // min ของวันเริ่มเทอม
        $this->assertSame('2027-03-12', (string) $year->end_date);    // max ของวันสิ้นสุดเทอม
        $this->assertCount(2, $default->fresh()->terms);
        $this->assertSame('2026-07-27', $default->fresh()->terms[0]->midterm_start->format('Y-m-d'));
    }

    public function test_calendar_term_end_before_start_is_rejected(): void
    {
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);
        $year = $this->createYear('2570');

        $this->post(route('admin.settings.calendars.store', $year), [
            'name'  => 'ทดสอบ',
            'terms' => [
                ['sequence' => 1, 'name' => 'ภาคเรียนที่ 1', 'start_date' => '2026-10-15', 'end_date' => '2026-06-01'],
            ],
        ])->assertSessionHasErrors('calendar_terms');
    }

    public function test_calendar_exam_outside_term_range_is_rejected(): void
    {
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);
        $year = $this->createYear('2570');

        $this->post(route('admin.settings.calendars.store', $year), [
            'name'  => 'ทดสอบ',
            'terms' => [
                ['sequence' => 1, 'name' => 'ภาคเรียนที่ 1', 'start_date' => '2026-06-01', 'end_date' => '2026-10-15',
                 'midterm_start' => '2026-12-01', 'midterm_end' => '2026-12-05'],
            ],
        ])->assertSessionHasErrors('calendar_terms');
    }

    public function test_calendar_final_exam_before_midterm_is_rejected(): void
    {
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);
        $year = $this->createYear('2570');

        $this->post(route('admin.settings.calendars.store', $year), [
            'name'  => 'ทดสอบ',
            'terms' => [
                ['sequence' => 1, 'name' => 'ภาคเรียนที่ 1', 'start_date' => '2026-06-01', 'end_date' => '2026-10-15',
                 'midterm_start' => '2026-09-01', 'midterm_end' => '2026-09-05',
                 'final_start' => '2026-07-01', 'final_end' => '2026-07-05'],
            ],
        ])->assertSessionHasErrors('calendar_terms');
    }

    public function test_calendar_overlapping_terms_are_rejected(): void
    {
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);
        $year = $this->createYear('2570');

        $this->post(route('admin.settings.calendars.store', $year), [
            'name'  => 'ทดสอบ',
            'terms' => [
                ['sequence' => 1, 'name' => 'ภาคเรียนที่ 1', 'start_date' => '2026-06-01', 'end_date' => '2026-10-15'],
                ['sequence' => 2, 'name' => 'ภาคเรียนที่ 2', 'start_date' => '2026-10-10', 'end_date' => '2027-03-12'],
            ],
        ])->assertSessionHasErrors('calendar_terms');
    }

    public function test_year_name_is_unique(): void
    {
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);
        AcademicYear::create(['name' => '2570', 'start_date' => '2026-06-01', 'end_date' => '2027-03-12', 'is_active' => false]);

        $this->post(route('admin.settings.years.store'), ['name' => '2570'])
            ->assertSessionHasErrors('name');
    }

    /**
     * หัวใจ V2: ตั้งปี active → วิชาเปิดตาม "หลักสูตร active" (ไม่ผูกเทอม)
     */
    public function test_activating_year_syncs_course_status_by_curriculum_not_semester(): void
    {
        $dept = Department::create(['name' => 'ภาควิชาทดสอบ']);
        $head = User::create(['username' => 'head_x', 'name' => 'Head X', 'email' => 'hx@example.com', 'password' => Hash::make('p')]);
        UserRole::create(['user_id' => $head->id, 'role' => 'instructor', 'is_primary' => true]);

        $activeCurr = Curriculum::create(['name' => 'หลักสูตร active', 'effective_year' => 2568, 'education_level' => 'bachelor', 'duration_years' => 4, 'uses_year_level' => true, 'is_active' => true]);
        $inactiveCurr = Curriculum::create(['name' => 'หลักสูตร ปิด', 'effective_year' => 2560, 'education_level' => 'bachelor', 'duration_years' => 4, 'uses_year_level' => true, 'is_active' => false]);

        $courseActive = Course::create(['course_code' => 'ACT 101', 'name_th' => 'วิชาเปิด', 'curriculum_id' => $activeCurr->id, 'department_id' => $dept->id, 'head_instructor_id' => $head->id, 'credits' => 3, 'lecture_hours' => 3, 'lab_hours' => 0, 'self_study_hours' => 6, 'capacity' => 30, 'status' => 'inactive']);
        $courseClosed = Course::create(['course_code' => 'CLO 101', 'name_th' => 'วิชาปิด', 'curriculum_id' => $inactiveCurr->id, 'department_id' => $dept->id, 'head_instructor_id' => $head->id, 'credits' => 3, 'lecture_hours' => 3, 'lab_hours' => 0, 'self_study_hours' => 6, 'capacity' => 30, 'status' => 'active']);

        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);

        // V4: สร้างปี active โดยไม่ต้องมีเทอม
        $this->post(route('admin.settings.years.store'), [
            'name'      => '2571',
            'is_active' => '1',
        ])->assertRedirect(route('admin.settings', ['tab' => 'academic']));

        $this->assertSame('active', $courseActive->fresh()->status);
        $this->assertSame('inactive', $courseClosed->fresh()->status);

        $offerable = Course::offerableForActiveCurriculum()->pluck('course_code')->all();
        $this->assertContains('ACT 101', $offerable);
        $this->assertNotContains('CLO 101', $offerable);
    }
}
