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

    public function test_settings_page_renders(): void
    {
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);

        $this->get(route('admin.settings', ['tab' => 'academic']))
            ->assertOk()
            ->assertSee('ปีการศึกษา');
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

    public function test_create_year_persists_terms_and_derives_year_span(): void
    {
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);

        $this->post(route('admin.settings.years.store'), [
            'name'  => '2570',
            'terms' => $this->termsPayload(),
        ])->assertRedirect(route('admin.settings', ['tab' => 'academic']));

        $year = AcademicYear::where('name', '2570')->firstOrFail();
        $this->assertCount(2, $year->terms);
        $this->assertSame('ภาคเรียนที่ 1', $year->terms[0]->name);
        // วันปี = min(start)..max(end) ของเทอม
        $this->assertSame('2026-06-01', $year->start_date);
        $this->assertSame('2027-03-12', $year->end_date);
        // วันสอบเก็บถูก
        $this->assertSame('2026-07-27', $year->terms[0]->midterm_start->format('Y-m-d'));
    }

    public function test_create_year_requires_terms(): void
    {
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);

        $this->post(route('admin.settings.years.store'), ['name' => '2570'])
            ->assertSessionHasErrors('terms');
    }

    public function test_term_end_before_start_is_rejected(): void
    {
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);

        $this->post(route('admin.settings.years.store'), [
            'name'  => '2570',
            'terms' => [
                ['sequence' => 1, 'name' => 'ภาคเรียนที่ 1', 'start_date' => '2026-10-15', 'end_date' => '2026-06-01'],
            ],
        ])->assertSessionHasErrors('terms');

        $this->assertDatabaseMissing('academic_years', ['name' => '2570']);
    }

    public function test_exam_outside_term_range_is_rejected(): void
    {
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);

        $this->post(route('admin.settings.years.store'), [
            'name'  => '2570',
            'terms' => [
                ['sequence' => 1, 'name' => 'ภาคเรียนที่ 1', 'start_date' => '2026-06-01', 'end_date' => '2026-10-15',
                 'midterm_start' => '2026-12-01', 'midterm_end' => '2026-12-05'], // นอกช่วงเทอม
            ],
        ])->assertSessionHasErrors('terms');
    }

    public function test_overlapping_terms_are_rejected(): void
    {
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);

        $this->post(route('admin.settings.years.store'), [
            'name'  => '2570',
            'terms' => [
                ['sequence' => 1, 'name' => 'ภาคเรียนที่ 1', 'start_date' => '2026-06-01', 'end_date' => '2026-10-15'],
                ['sequence' => 2, 'name' => 'ภาคเรียนที่ 2', 'start_date' => '2026-10-10', 'end_date' => '2027-03-12'], // ซ้อนเทอม 1
            ],
        ])->assertSessionHasErrors('terms');
    }

    public function test_year_name_is_unique_without_semester(): void
    {
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);
        AcademicYear::create(['name' => '2570', 'start_date' => '2026-06-01', 'end_date' => '2027-03-12', 'is_active' => false]);

        $this->post(route('admin.settings.years.store'), [
            'name'  => '2570',
            'terms' => $this->termsPayload(),
        ])->assertSessionHasErrors('name');
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

        $this->post(route('admin.settings.years.store'), [
            'name'      => '2571',
            'is_active' => '1',
            'terms'     => $this->termsPayload(),
        ])->assertRedirect(route('admin.settings', ['tab' => 'academic']));

        // วิชาใน active curriculum → เปิด · วิชาใน inactive curriculum → ปิด (ไม่สนเทอม)
        $this->assertSame('active', $courseActive->fresh()->status);
        $this->assertSame('inactive', $courseClosed->fresh()->status);

        // scope ใหม่ดึงเฉพาะวิชา active ใน active curriculum
        $offerable = Course::offerableForActiveCurriculum()->pluck('course_code')->all();
        $this->assertContains('ACT 101', $offerable);
        $this->assertNotContains('CLO 101', $offerable);
    }
}
