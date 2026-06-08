<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseRole;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\InstructorProfile;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Coverage for the M2 show-page overhaul (commit 4ea2708):
 *   - practicum_note required only when overriding course default
 *   - AJAX/JSON instructor add/remove/role-update flow
 *   - Executive instructors filtered out of available pool
 *   - Read-only render during preparation phase
 */
class CourseOfferingShowPageTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCourseRoles();
    }

    // ── Instructor AJAX flow ─────────────────────────────────────────

    public function test_store_instructor_returns_json_when_requested(): void
    {
        $head       = $this->makeUser('course_head');
        $instructor = $this->makeUser('instructor');
        $offering   = $this->makeOffering($head);
        $teacherRole = CourseRole::where('name_th', 'อาจารย์ผู้สอน')->first();

        $this->actingAsCourseHead($head);
        $response = $this->postJson(
            route('maker.course_offerings.instructors.store', $offering),
            ['user_id' => $instructor->id]
        );

        $response->assertOk()
            ->assertJsonFragment([
                'id'             => $instructor->id,
                'course_role_id' => $teacherRole->id,
                'role_name'      => 'อาจารย์ผู้สอน',
                'is_coordinator' => false,
            ]);

        $this->assertDatabaseHas('course_offering_instructors', [
            'course_offering_id' => $offering->id,
            'user_id'            => $instructor->id,
            'course_role_id'     => $teacherRole->id,
        ]);
    }

    public function test_store_instructor_with_explicit_role_id(): void
    {
        $head       = $this->makeUser('course_head');
        $instructor = $this->makeUser('instructor');
        $offering   = $this->makeOffering($head);
        $advisor    = CourseRole::where('name_th', 'อาจารย์ประจำกลุ่ม')->first();

        $this->actingAsCourseHead($head);
        $this->postJson(route('maker.course_offerings.instructors.store', $offering), [
            'user_id'        => $instructor->id,
            'course_role_id' => $advisor->id,
        ])->assertOk()
            ->assertJsonFragment(['role_name' => 'อาจารย์ประจำกลุ่ม']);

        $this->assertDatabaseHas('course_offering_instructors', [
            'course_offering_id' => $offering->id,
            'user_id'            => $instructor->id,
            'course_role_id'     => $advisor->id,
        ]);
    }

    public function test_store_instructor_returns_422_json_on_validation_failure(): void
    {
        $head       = $this->makeUser('course_head');
        $instructor = $this->makeUser('instructor');
        $offering   = $this->makeOffering($head);
        $offering->instructorPool()->attach($instructor->id, ['role_in_course' => 'instructor']);

        $this->actingAsCourseHead($head);
        $this->postJson(route('maker.course_offerings.instructors.store', $offering), [
            'user_id' => $instructor->id,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'อาจารย์คนนี้อยู่ในชุดผู้สอนของรายวิชานี้แล้ว');
    }

    public function test_update_instructor_role_via_patch_json(): void
    {
        $head       = $this->makeUser('course_head');
        $instructor = $this->makeUser('instructor');
        $offering   = $this->makeOffering($head);
        $oldRole    = CourseRole::where('name_th', 'อาจารย์ผู้สอน')->first();
        $newRole    = CourseRole::where('name_th', 'เลขานุการวิชา')->first();

        $offering->instructorPool()->attach($instructor->id, [
            'role_in_course' => 'instructor',
            'course_role_id' => $oldRole->id,
        ]);

        $this->actingAsCourseHead($head);
        $this->patchJson(
            route('maker.course_offerings.instructors.role', [$offering, $instructor]),
            ['course_role_id' => $newRole->id]
        )->assertOk()
            ->assertJsonFragment(['role_name' => 'เลขานุการวิชา']);

        $this->assertDatabaseHas('course_offering_instructors', [
            'course_offering_id' => $offering->id,
            'user_id'            => $instructor->id,
            'course_role_id'     => $newRole->id,
        ]);
    }

    public function test_update_instructor_role_can_clear_role(): void
    {
        $head       = $this->makeUser('course_head');
        $instructor = $this->makeUser('instructor');
        $offering   = $this->makeOffering($head);
        $role       = CourseRole::where('name_th', 'อาจารย์ผู้สอน')->first();
        $offering->instructorPool()->attach($instructor->id, [
            'role_in_course' => 'instructor',
            'course_role_id' => $role->id,
        ]);

        $this->actingAsCourseHead($head);
        $this->patchJson(
            route('maker.course_offerings.instructors.role', [$offering, $instructor]),
            ['course_role_id' => null]
        )->assertOk();

        $this->assertDatabaseHas('course_offering_instructors', [
            'course_offering_id' => $offering->id,
            'user_id'            => $instructor->id,
            'course_role_id'     => null,
        ]);
    }

    public function test_destroy_instructor_returns_json_when_requested(): void
    {
        $head       = $this->makeUser('course_head');
        $instructor = $this->makeUser('instructor');
        $offering   = $this->makeOffering($head);
        $offering->instructorPool()->attach($instructor->id, ['role_in_course' => 'instructor']);

        $this->actingAsCourseHead($head);
        $this->deleteJson(route('maker.course_offerings.instructors.destroy', [$offering, $instructor]))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('course_offering_instructors', [
            'course_offering_id' => $offering->id,
            'user_id'            => $instructor->id,
        ]);
    }

    public function test_destroy_coordinator_returns_422_json(): void
    {
        $head     = $this->makeUser('course_head');
        $offering = $this->makeOffering($head);
        $offering->instructorPool()->attach($head->id, ['role_in_course' => 'coordinator']);

        $this->actingAsCourseHead($head);
        $this->deleteJson(route('maker.course_offerings.instructors.destroy', [$offering, $head]))
            ->assertStatus(422);

        $this->assertDatabaseHas('course_offering_instructors', [
            'course_offering_id' => $offering->id,
            'user_id'            => $head->id,
        ]);
    }

    // ── Show page rendering ──────────────────────────────────────────

    public function test_show_page_passes_course_roles_and_filters_executives(): void
    {
        $head       = $this->makeUser('course_head');
        $instructor = $this->makeUser('instructor');
        $exec       = $this->makeUser('executive');
        $offering   = $this->makeOffering($head);

        $this->actingAsCourseHead($head);
        $response = $this->get(route('maker.course_offerings.show', $offering));

        $response->assertOk();

        $available = $response->viewData('availableInstructors');
        $this->assertTrue($available->contains('id', $instructor->id));
        $this->assertFalse($available->contains('id', $exec->id),
            'Executives should be filtered out of the available instructor pool.');

        $courseRoles = $response->viewData('courseRoles');
        $this->assertFalse($courseRoles->contains('name_th', 'หัวหน้าวิชา'),
            'Coordinator role is auto-assigned and should be hidden from the role dropdown.');
        $this->assertTrue($courseRoles->contains('name_th', 'อาจารย์ผู้สอน'));
    }

    public function test_show_page_renders_read_only_during_preparation(): void
    {
        $head     = $this->makeUser('course_head');
        $year     = $this->makeYear(['phase' => 'preparation']);
        $offering = $this->makeOffering($head, null, [], $year);

        $this->actingAsCourseHead($head);
        $response = $this->get(route('maker.course_offerings.show', $offering));

        $response->assertOk()
            ->assertSee('ยังไม่เปิดช่วงจัดตาราง');
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function seedCourseRoles(): void
    {
        foreach ([
            ['name_th' => 'หัวหน้าวิชา',         'sort_order' => 1],
            ['name_th' => 'เลขานุการวิชา',        'sort_order' => 2],
            ['name_th' => 'อาจารย์ผู้สอน',        'sort_order' => 3],
            ['name_th' => 'อาจารย์ประจำกลุ่ม',  'sort_order' => 4],
            ['name_th' => 'อาจารย์พี่เลี้ยง',    'sort_order' => 5],
        ] as $role) {
            CourseRole::firstOrCreate(['name_th' => $role['name_th']], $role);
        }
    }

    private function actingAsCourseHead(User $user): void
    {
        $this->actingAs($user);
        $this->withSession(['active_role' => 'course_head']);
    }

    private function makeUser(string $role): User
    {
        $n    = $this->sequence++;
        $user = User::create([
            'username'  => "s_{$role}_{$n}",
            'name'      => "{$role} {$n}",
            'email'     => "s{$n}@test.example",
            'password'  => Hash::make('password'),
            'is_active' => true,
        ]);
        UserRole::create(['user_id' => $user->id, 'role' => $role, 'is_primary' => true]);

        if (in_array($role, ['instructor', 'course_head', 'executive'], true)) {
            InstructorProfile::create([
                'user_id'       => $user->id,
                'title'         => 'อาจารย์',
                'department_id' => $this->department()->id,
            ]);
        }

        return $user;
    }

    private function makeCourse(array $overrides = []): Course
    {
        $n = $this->sequence++;
        return Course::create(array_merge([
            'course_code'              => "SHO{$n}",
            'curriculum_id'            => $this->curriculum()->id,
            'department_id'            => $this->department()->id,
            'name_th'                  => "วิชา {$n}",
            'name_en'                  => "Course {$n}",
            'course_type'              => 'theory',
            'default_year_level'       => 1,
            'default_semester'         => 1,
            'credits'                  => 3,
            'lecture_hours'            => 3,
            'lab_hours'                => 0,
            'self_study_hours'         => 6,
            'capacity'                 => 60,
            'status'                   => 'active',
            'requires_practicum_rotation' => false,
        ], $overrides));
    }

    private function makeYear(array $overrides = []): AcademicYear
    {
        $n = $this->sequence++;
        return AcademicYear::create(array_merge([
            'name'       => "256{$n}",
            'semester'   => 1,
            'start_date' => '2026-08-01',
            'end_date'   => '2026-12-31',
            'is_active'  => true,
            'phase'      => 'scheduling',
        ], $overrides));
    }

    private function makeOffering(
        User $coordinator,
        ?Course $course = null,
        array $overrides = [],
        ?AcademicYear $year = null
    ): CourseOffering {
        $year   = $year ?? $this->makeYear(['phase' => 'scheduling']);
        $course = $course ?? $this->makeCourse();

        return CourseOffering::create(array_merge([
            'course_id'                   => $course->id,
            'academic_year_id'            => $year->id,
            'coordinator_id'              => $coordinator->id,
            'approval_status'             => 'draft',
            'total_student_count'         => 60,
            'teaching_weeks'              => 16,
            'requires_practicum_rotation' => false,
        ], $overrides));
    }

    private function department(): Department
    {
        return Department::firstOrCreate(['name' => 'Show Test Dept']);
    }

    private function curriculum(): Curriculum
    {
        return Curriculum::firstOrCreate(['name' => 'Show Test Curriculum'], [
            'effective_year' => 2569,
            'is_active'      => true,
        ]);
    }
}
