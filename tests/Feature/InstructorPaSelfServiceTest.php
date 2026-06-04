<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\InstructorPaAllocation;
use App\Models\InstructorProfile;
use App\Models\PaRound;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InstructorPaSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_can_view_pa_form(): void
    {
        $instructor = $this->makeInstructor();
        $this->makeAcademicYear();
        session(['active_role' => 'instructor']);

        $response = $this->actingAs($instructor)->get(route('instructor.pa.edit'));

        $response
            ->assertOk()
            ->assertViewIs('instructor.pa.edit')
            ->assertSee('กรอกสัดส่วนภาระงาน PA');
    }

    public function test_instructor_can_submit_pa_allocation_for_current_round(): void
    {
        $instructor = $this->makeInstructor();
        $year = $this->makeAcademicYear();
        session(['active_role' => 'instructor']);

        $response = $this->actingAs($instructor)->put(route('instructor.pa.update'), [
            'teaching_pct' => 50,
            'research_pct' => 25,
            'service_pct' => 10,
            'culture_pct' => 10,
            'other_pct' => 5,
        ]);

        $response->assertRedirect(route('instructor.pa.edit'));

        $round = PaRound::where('academic_year_id', $year->id)->firstOrFail();
        $this->assertSame(PaRound::CODE_ANNUAL, $round->code);
        $this->assertDatabaseHas('instructor_pa_allocations', [
            'user_id' => $instructor->id,
            'pa_round_id' => $round->id,
            'teaching_pct' => 50,
            'research_pct' => 25,
            'service_pct' => 10,
            'culture_pct' => 10,
            'other_pct' => 5,
            'teaching_quota' => 683,
        ]);
        $this->assertNotNull(InstructorPaAllocation::first()->submitted_at);
        $this->assertDatabaseHas('instructor_profiles', [
            'user_id' => $instructor->id,
            'teaching_pct' => 50,
            'research_pct' => 25,
            'service_pct' => 10,
            'culture_pct' => 10,
            'other_pct' => 5,
            'teaching_quota' => 683,
        ]);
    }

    public function test_instructor_pa_percentages_must_total_100(): void
    {
        $instructor = $this->makeInstructor();
        $this->makeAcademicYear();
        session(['active_role' => 'instructor']);

        $response = $this->actingAs($instructor)->from(route('instructor.pa.edit'))->put(route('instructor.pa.update'), [
            'teaching_pct' => 50,
            'research_pct' => 20,
            'service_pct' => 10,
            'culture_pct' => 10,
            'other_pct' => 5,
        ]);

        $response
            ->assertRedirect(route('instructor.pa.edit'))
            ->assertSessionHasErrors('teaching_pct');
        $this->assertDatabaseCount('instructor_pa_allocations', 0);
    }

    public function test_non_instructor_cannot_access_pa_form(): void
    {
        $staff = User::create([
            'username' => 'staff-only',
            'name' => 'Staff Only',
            'email' => 'staff-only@example.com',
            'password' => Hash::make('password123'),
        ]);
        UserRole::create(['user_id' => $staff->id, 'role' => 'staff', 'is_primary' => true]);
        session(['active_role' => 'staff']);

        $this->actingAs($staff)->get(route('instructor.pa.edit'))->assertForbidden();
    }

    private function makeInstructor(): User
    {
        $user = User::create([
            'username' => 'pa-instructor',
            'name' => 'PA Instructor',
            'email' => 'pa-instructor@example.com',
            'employee_id' => 'PA-001',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        UserRole::create(['user_id' => $user->id, 'role' => 'instructor', 'is_primary' => true]);
        InstructorProfile::create([
            'user_id' => $user->id,
            'title' => 'อาจารย์',
            'academic_degree' => 'ปริญญาโท',
            'employment_type' => 'พนักงานมหาวิทยาลัย',
            'hired_at' => '2026-01-01',
            'teaching_pct' => 0,
            'research_pct' => 0,
            'service_pct' => 0,
            'culture_pct' => 0,
            'other_pct' => 0,
            'teaching_quota' => 0,
        ]);

        return $user;
    }

    private function makeAcademicYear(): AcademicYear
    {
        return AcademicYear::create([
            'name' => '2569',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
            'phase' => 'scheduling',
        ]);
    }
}
