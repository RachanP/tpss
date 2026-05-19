<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\AlertController;
use App\Models\AcademicYear;
use App\Models\ActivityType;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseRole;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\InstructorProfile;
use App\Models\LocationType;
use App\Models\StudentGroup;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Coverage for the M2 hardening pass (commit 72d8141):
 *   - openSchedulingWindow syncs offering data + instructor pool from course template
 *   - Critical-gate prevents opening when baseline criticals exist
 *   - bulkStoreStudentGroups generates evenly-distributed groups with auto colors
 */
class CourseOfferingHardeningTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCourseRoles();
    }

    // ── openSchedulingWindow: template sync ──────────────────────────

    public function test_open_syncs_offering_planning_fields_from_course_template(): void
    {
        $admin  = $this->makeUser('admin');
        $head   = $this->makeUser('instructor');
        $year   = $this->makeYear(['phase' => 'preparation']);
        $course = $this->makeCourse([
            'head_instructor_id'          => $head->id,
            'capacity'                    => 180,
            'lecture_hours'               => 3,
            'lab_hours'                   => 2,
            'requires_practicum_rotation' => true,
        ]);
        SystemSetting::set('teaching_load_weeks', 16);
        $this->seedBaselineCriticals();

        $this->actingAsAdmin($admin);
        $this->patch(route('admin.settings.scheduling.open', $year))
            ->assertSessionHas('success');

        $offering = CourseOffering::firstWhere('course_id', $course->id);
        $this->assertNotNull($offering);
        $this->assertSame(180, $offering->total_student_count);
        $this->assertSame(3,   $offering->planned_lecture_hours);
        $this->assertSame(0,   $offering->planned_lab_hours);
        $this->assertSame(2,   $offering->planned_practicum_hours);
        $this->assertSame(16,  $offering->teaching_weeks);
        $this->assertTrue((bool) $offering->requires_practicum_rotation);
        $this->assertNull($offering->practicum_note);
    }

    public function test_open_syncs_instructor_pool_from_course_template(): void
    {
        $admin       = $this->makeUser('admin');
        $head        = $this->makeUser('instructor');
        $teacher     = $this->makeUser('instructor');
        $preceptor   = $this->makeUser('instructor');
        $year        = $this->makeYear(['phase' => 'preparation']);
        $course      = $this->makeCourse(['head_instructor_id' => $head->id]);
        $teacherRole = CourseRole::where('name_th', 'อาจารย์ผู้สอน')->first();
        $preceptRole = CourseRole::where('name_th', 'อาจารย์พี่เลี้ยง')->first();

        $course->instructors()->attach($teacher->id,   ['course_role_id' => $teacherRole->id]);
        $course->instructors()->attach($preceptor->id, ['course_role_id' => $preceptRole->id]);
        $this->seedBaselineCriticals();

        $this->actingAsAdmin($admin);
        $this->patch(route('admin.settings.scheduling.open', $year));

        $offering = CourseOffering::firstWhere('course_id', $course->id);

        $this->assertDatabaseHas('course_offering_instructors', [
            'course_offering_id' => $offering->id,
            'user_id'            => $head->id,
            'role_in_course'     => 'coordinator',
        ]);
        $this->assertDatabaseHas('course_offering_instructors', [
            'course_offering_id' => $offering->id,
            'user_id'            => $teacher->id,
            'course_role_id'     => $teacherRole->id,
        ]);
        $this->assertDatabaseHas('course_offering_instructors', [
            'course_offering_id' => $offering->id,
            'user_id'            => $preceptor->id,
            'course_role_id'     => $preceptRole->id,
        ]);
    }

    public function test_open_removes_stale_pool_entries_no_longer_in_template(): void
    {
        $admin   = $this->makeUser('admin');
        $head    = $this->makeUser('instructor');
        $stale   = $this->makeUser('instructor');
        $current = $this->makeUser('instructor');
        $year    = $this->makeYear(['phase' => 'preparation']);
        $course  = $this->makeCourse(['head_instructor_id' => $head->id]);

        // Pre-existing offering with a stale pool entry
        $offering = CourseOffering::create([
            'course_id'        => $course->id,
            'academic_year_id' => $year->id,
            'coordinator_id'   => $head->id,
            'approval_status'  => 'draft',
        ]);
        $offering->instructorPool()->attach($stale->id, ['role_in_course' => 'instructor']);

        // Template now contains only $current
        $course->instructors()->attach($current->id, [
            'course_role_id' => CourseRole::where('name_th', 'อาจารย์ผู้สอน')->value('id'),
        ]);
        $this->seedBaselineCriticals();

        $this->actingAsAdmin($admin);
        $this->patch(route('admin.settings.scheduling.open', $year));

        $this->assertDatabaseMissing('course_offering_instructors', [
            'course_offering_id' => $offering->id,
            'user_id'            => $stale->id,
        ]);
        $this->assertDatabaseHas('course_offering_instructors', [
            'course_offering_id' => $offering->id,
            'user_id'            => $current->id,
        ]);
        // Coordinator preserved
        $this->assertDatabaseHas('course_offering_instructors', [
            'course_offering_id' => $offering->id,
            'user_id'            => $head->id,
            'role_in_course'     => 'coordinator',
        ]);
    }

    public function test_sync_method_directly_uses_passed_coordinator_role_id(): void
    {
        // Hoisting refactor: callers pass coordinator_role_id once so the
        // inner loop doesn't re-query CourseRole per offering.
        $head   = $this->makeUser('instructor');
        $year   = $this->makeYear(['phase' => 'preparation']);
        $course = $this->makeCourse(['head_instructor_id' => $head->id]);
        $offering = CourseOffering::create([
            'course_id'        => $course->id,
            'academic_year_id' => $year->id,
            'coordinator_id'   => $head->id,
            'approval_status'  => 'draft',
        ]);

        $explicitRoleId = CourseRole::where('name_th', 'หัวหน้าวิชา')->value('id');
        $offering->syncInstructorPoolFromCourseTemplate($explicitRoleId);

        $this->assertDatabaseHas('course_offering_instructors', [
            'course_offering_id' => $offering->id,
            'user_id'            => $head->id,
            'course_role_id'     => $explicitRoleId,
            'role_in_course'     => 'coordinator',
        ]);
    }

    // ── bulkStoreStudentGroups ────────────────────────────────────────

    public function test_bulk_creates_evenly_distributed_groups_with_colors(): void
    {
        $head     = $this->makeUser('course_head');
        $offering = $this->makeOffering($head, ['total_student_count' => 30]);

        $this->actingAsCourseHead($head);
        $this->from(route('maker.course_offerings.show', $offering))
            ->post(route('maker.course_offerings.student_groups.bulk_store', $offering), [
                'group_prefix' => 'A',
                'start_number' => 1,
                'group_count'  => 3,
            ])
            ->assertSessionHasNoErrors();

        $groups = $offering->studentGroups()->orderBy('group_code')->get();
        $this->assertCount(3, $groups);
        $this->assertSame(['A1', 'A2', 'A3'], $groups->pluck('group_code')->all());
        $this->assertSame(30, (int) $groups->sum('student_count'));
        $this->assertSame(10, (int) $groups[0]->student_count);
        $this->assertSame(10, (int) $groups[1]->student_count);
        $this->assertSame(10, (int) $groups[2]->student_count);
        // Each group gets an auto-assigned color
        foreach ($groups as $g) {
            $this->assertMatchesRegularExpression('/^#[0-9a-fA-F]{6}$/', $g->color_code);
        }
    }

    public function test_bulk_distributes_remainder_to_first_groups(): void
    {
        $head     = $this->makeUser('course_head');
        $offering = $this->makeOffering($head, ['total_student_count' => 31]);

        $this->actingAsCourseHead($head);
        $this->from(route('maker.course_offerings.show', $offering))
            ->post(route('maker.course_offerings.student_groups.bulk_store', $offering), [
                'group_prefix' => 'B',
                'start_number' => 0,
                'group_count'  => 3,
            ])
            ->assertSessionHasNoErrors();

        $groups = $offering->studentGroups()->orderBy('group_code')->get();
        // 31 / 3 = 10 base + 1 remainder → first group gets the extra
        $this->assertSame(11, (int) $groups[0]->student_count);
        $this->assertSame(10, (int) $groups[1]->student_count);
        $this->assertSame(10, (int) $groups[2]->student_count);
    }

    public function test_bulk_uses_custom_per_group_counts_when_provided(): void
    {
        $head     = $this->makeUser('course_head');
        $offering = $this->makeOffering($head, ['total_student_count' => 60]);

        $this->actingAsCourseHead($head);
        $this->from(route('maker.course_offerings.show', $offering))
            ->post(route('maker.course_offerings.student_groups.bulk_store', $offering), [
                'group_prefix' => 'A',
                'start_number' => 1,
                'group_count'  => 3,
                'group_counts' => [25, 20, 15],
            ])
            ->assertSessionHasNoErrors();

        $groups = $offering->studentGroups()->orderBy('group_code')->get();
        $this->assertSame(25, (int) $groups[0]->student_count);
        $this->assertSame(20, (int) $groups[1]->student_count);
        $this->assertSame(15, (int) $groups[2]->student_count);
    }

    public function test_bulk_rejects_duplicate_group_codes(): void
    {
        $head     = $this->makeUser('course_head');
        $offering = $this->makeOffering($head, ['total_student_count' => 60]);
        StudentGroup::create([
            'course_offering_id' => $offering->id,
            'group_code'         => 'A2',
            'student_count'      => 10,
        ]);

        $this->actingAsCourseHead($head);
        $this->from(route('maker.course_offerings.show', $offering))
            ->post(route('maker.course_offerings.student_groups.bulk_store', $offering), [
                'group_prefix' => 'A',
                'start_number' => 1,
                'group_count'  => 3,
            ])
            ->assertSessionHasErrors('group_prefix');
    }

    public function test_bulk_rejects_when_remaining_capacity_is_zero(): void
    {
        $head     = $this->makeUser('course_head');
        $offering = $this->makeOffering($head, ['total_student_count' => 20]);
        StudentGroup::create([
            'course_offering_id' => $offering->id,
            'group_code'         => 'X1',
            'student_count'      => 20,
        ]);

        $this->actingAsCourseHead($head);
        $this->from(route('maker.course_offerings.show', $offering))
            ->post(route('maker.course_offerings.student_groups.bulk_store', $offering), [
                'group_prefix' => 'Y',
                'start_number' => 1,
                'group_count'  => 2,
            ])
            ->assertSessionHasErrors('group_count');
    }

    public function test_bulk_rejects_invalid_prefix_characters(): void
    {
        $head     = $this->makeUser('course_head');
        $offering = $this->makeOffering($head, ['total_student_count' => 30]);

        $this->actingAsCourseHead($head);
        $this->from(route('maker.course_offerings.show', $offering))
            ->post(route('maker.course_offerings.student_groups.bulk_store', $offering), [
                'group_prefix' => 'A B',  // space is invalid
                'start_number' => 1,
                'group_count'  => 2,
            ])
            ->assertSessionHasErrors('group_prefix');
    }

    public function test_bulk_blocked_during_preparation_phase(): void
    {
        $head     = $this->makeUser('course_head');
        $year     = $this->makeYear(['phase' => 'preparation']);
        $offering = $this->makeOffering($head, ['total_student_count' => 30], $year);

        $this->actingAsCourseHead($head);
        $this->from(route('maker.course_offerings.show', $offering))
            ->post(route('maker.course_offerings.student_groups.bulk_store', $offering), [
                'group_prefix' => 'A',
                'start_number' => 1,
                'group_count'  => 2,
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('student_groups', 0);
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

    private function seedBaselineCriticals(): void
    {
        ActivityType::firstOrCreate(['name' => 'Lecture'], [
            'color_code' => '#2563eb',
            'category'   => 'lecture',
        ]);
        LocationType::firstOrCreate(['name' => 'ห้องเรียน']);
        AlertController::flushCache();
    }

    private function actingAsAdmin(User $user): void
    {
        $this->actingAs($user);
        $this->withSession(['active_role' => 'admin']);
    }

    private function actingAsCourseHead(User $user): void
    {
        $this->actingAs($user);
        $this->withSession(['active_role' => 'course_head']);
    }

    private function makeUser(string $role, bool $active = true): User
    {
        $n    = $this->sequence++;
        $user = User::create([
            'username'  => "h_{$role}_{$n}",
            'name'      => "{$role} {$n}",
            'email'     => "h{$n}@test.example",
            'password'  => Hash::make('password'),
            'is_active' => $active,
        ]);
        UserRole::create(['user_id' => $user->id, 'role' => $role, 'is_primary' => true]);

        if (in_array($role, ['instructor', 'course_head'], true)) {
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
            'course_code'              => "HRD{$n}",
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
            'phase'      => 'preparation',
        ], $overrides));
    }

    private function makeOffering(User $coordinator, array $overrides = [], ?AcademicYear $year = null): CourseOffering
    {
        $year   = $year ?? $this->makeYear(['phase' => 'scheduling']);
        $course = $this->makeCourse();

        return CourseOffering::create([
            'course_id'           => $course->id,
            'academic_year_id'    => $year->id,
            'coordinator_id'      => $coordinator->id,
            'approval_status'     => 'draft',
            'total_student_count' => $overrides['total_student_count'] ?? null,
        ]);
    }

    private function department(): Department
    {
        return Department::firstOrCreate(['name' => 'Hardening Test Dept']);
    }

    private function curriculum(): Curriculum
    {
        return Curriculum::firstOrCreate(['name' => 'Hardening Test Curriculum'], [
            'effective_year' => 2569,
            'is_active'      => true,
        ]);
    }
}
