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
            'name'       => '2571',
            'start_date' => '2026-06-01',
            'end_date'   => '2027-03-12',
            'is_active'  => '1',
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
