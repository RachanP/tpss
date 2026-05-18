<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\InstructorProfile;
use App\Models\StudentGroup;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CourseOfferingManagementTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 1;

    public function test_course_head_sees_only_assigned_active_offerings(): void
    {
        $head = $this->makeUser('course_head');
        $otherHead = $this->makeUser('course_head');

        $mine = $this->makeOffering($head, ['course_code' => 'NUR101']);
        $other = $this->makeOffering($otherHead, ['course_code' => 'NUR202']);

        $this->actingAsCourseHead($head);

        $response = $this->get(route('maker.course_offerings.index'));

        $response->assertOk();
        $response->assertSee($mine->course->course_code);
        $response->assertDontSee($other->course->course_code);
    }

    public function test_unrelated_offering_access_is_blocked(): void
    {
        $head = $this->makeUser('course_head');
        $otherHead = $this->makeUser('course_head');
        $offering = $this->makeOffering($otherHead);

        $this->actingAsCourseHead($head);

        $this->get(route('maker.course_offerings.show', $offering))->assertForbidden();
    }

    public function test_course_offering_routes_remain_numeric_id_based(): void
    {
        $head = $this->makeUser('course_head');
        $offering = $this->makeOffering($head, ['course_code' => 'OFFER101']);

        $url = route('maker.course_offerings.show', $offering);

        $this->assertStringContainsString("/maker/course-offerings/{$offering->id}", $url);
        $this->assertStringNotContainsString('OFFER101', $url);
    }

    public function test_detail_renders_core_fields_from_course_master(): void
    {
        // After M2 overhaul, the show page reads hour fields directly from
        // courses.lecture_hours / lab_hours (no per-offering override) and
        // displays them in stat cards + course-info panel.
        $head = $this->makeUser('course_head');
        $offering = $this->makeOffering($head, [
            'course_code' => 'NUR303',
            'lecture_hours' => 3,
            'lab_hours' => 2,
        ]);

        $this->actingAsCourseHead($head);

        $response = $this->get(route('maker.course_offerings.show', $offering));

        $response->assertOk();
        $response->assertSee('NUR303');
        $response->assertSee('ข้อมูลรายวิชา');
        $response->assertSee('ชั่วโมงบรรยาย');
        $response->assertSee('ชั่วโมงปฏิบัติการ');
    }

    public function test_student_group_code_is_unique_within_offering_and_reusable_across_offerings(): void
    {
        $head = $this->makeUser('course_head');
        $firstOffering = $this->makeOffering($head, ['total_student_count' => 40]);
        $secondOffering = $this->makeOffering($head, ['total_student_count' => 40]);

        $this->actingAsCourseHead($head);

        $this->from(route('maker.course_offerings.show', $firstOffering))
            ->post(route('maker.course_offerings.student_groups.store', $firstOffering), [
                'group_code' => 'A1',
                'student_count' => 20,
            ])
            ->assertSessionHasNoErrors();

        $this->from(route('maker.course_offerings.show', $firstOffering))
            ->post(route('maker.course_offerings.student_groups.store', $firstOffering), [
                'group_code' => 'A1',
                'student_count' => 5,
            ])
            ->assertSessionHasErrors('group_code');

        $this->from(route('maker.course_offerings.show', $secondOffering))
            ->post(route('maker.course_offerings.student_groups.store', $secondOffering), [
                'group_code' => 'A1',
                'student_count' => 15,
            ])
            ->assertRedirect(route('maker.course_offerings.show', $secondOffering));

        $this->assertDatabaseHas('student_groups', [
            'course_offering_id' => $secondOffering->id,
            'group_code' => 'A1',
        ]);
    }

    public function test_student_group_total_cannot_exceed_offering_total(): void
    {
        $head = $this->makeUser('course_head');
        $offering = $this->makeOffering($head, ['total_student_count' => 30]);

        StudentGroup::create([
            'course_offering_id' => $offering->id,
            'group_code' => 'A1',
            'student_count' => 20,
        ]);

        $this->actingAsCourseHead($head);

        $this->from(route('maker.course_offerings.show', $offering))
            ->post(route('maker.course_offerings.student_groups.store', $offering), [
                'group_code' => 'A2',
                'student_count' => 15,
            ])
            ->assertSessionHasErrors('student_count');
    }

    public function test_instructor_pool_rejects_inactive_duplicate_and_coordinator_removal(): void
    {
        $head = $this->makeUser('course_head');
        $offering = $this->makeOffering($head);
        $inactiveInstructor = $this->makeUser('instructor', false);
        $activeInstructor = $this->makeUser('instructor');

        $this->actingAsCourseHead($head);

        $this->from(route('maker.course_offerings.show', $offering))
            ->post(route('maker.course_offerings.instructors.store', $offering), [
                'user_id' => $inactiveInstructor->id,
            ])
            ->assertSessionHasErrors('user_id');

        $this->from(route('maker.course_offerings.show', $offering))
            ->post(route('maker.course_offerings.instructors.store', $offering), [
                'user_id' => $activeInstructor->id,
            ])
            ->assertRedirect(route('maker.course_offerings.show', $offering));

        $this->assertDatabaseHas('course_offering_instructors', [
            'course_offering_id' => $offering->id,
            'user_id' => $activeInstructor->id,
            'role_in_course' => 'instructor',
        ]);

        $this->from(route('maker.course_offerings.show', $offering))
            ->post(route('maker.course_offerings.instructors.store', $offering), [
                'user_id' => $activeInstructor->id,
            ])
            ->assertSessionHasErrors('user_id');

        DB::table('course_offering_instructors')->insert([
            'course_offering_id' => $offering->id,
            'user_id' => $head->id,
            'role_in_course' => 'coordinator',
        ]);

        $this->from(route('maker.course_offerings.show', $offering))
            ->delete(route('maker.course_offerings.instructors.destroy', [$offering, $head]))
            ->assertSessionHasErrors('instructor_pool');

        $this->assertDatabaseHas('course_offering_instructors', [
            'course_offering_id' => $offering->id,
            'user_id' => $head->id,
        ]);
    }

    public function test_instructor_role_controls_are_hidden_from_course_head_detail(): void
    {
        $head = $this->makeUser('course_head');
        $offering = $this->makeOffering($head);
        $instructor = $this->makeUser('instructor');

        DB::table('course_offering_instructors')->insert([
            'course_offering_id' => $offering->id,
            'user_id' => $instructor->id,
            'role_in_course' => 'assistant_teacher',
        ]);

        $this->actingAsCourseHead($head);

        $this->get(route('maker.course_offerings.show', $offering))
            ->assertOk()
            ->assertSee('ชุดผู้สอน')
            ->assertSee($instructor->formatted_name)
            ->assertDontSee('บทบาทหลัก')
            ->assertDontSee('ผู้ช่วยสอน')
            ->assertDontSee('พรีเซปเตอร์');
    }

    // Prerequisite tests removed: M2 hardening moved prerequisite management
    // from per-offering to per-course (Master Data). See MasterDataCourseTest
    // for coverage of the new flow.

    public function test_student_group_stats_visible_on_show_page(): void
    {
        // The "นักศึกษาคงเหลือ" wording was removed in the show-page overhaul;
        // current stats card shows the grouped total instead.
        $head = $this->makeUser('course_head');
        $offering = $this->makeOffering($head, ['total_student_count' => 30]);

        StudentGroup::create([
            'course_offering_id' => $offering->id,
            'group_code' => 'A1',
            'student_count' => 12,
        ]);

        $this->actingAsCourseHead($head);

        $this->get(route('maker.course_offerings.show', $offering))
            ->assertOk()
            ->assertSee('จัดกลุ่มแล้ว')
            ->assertSee('12');
    }

    public function test_student_group_delete_blocks_downstream_schedule_references(): void
    {
        $head = $this->makeUser('course_head');
        $offering = $this->makeOffering($head);
        $group = StudentGroup::create([
            'course_offering_id' => $offering->id,
            'group_code' => 'A1',
            'student_count' => 20,
        ]);
        $activityTypeId = $this->createActivityType();
        $scheduleId = DB::table('schedules')->insertGetId([
            'course_offering_id' => $offering->id,
            'activity_type_id' => $activityTypeId,
            'teaching_date' => '2026-08-01',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('schedule_student_groups')->insert([
            'schedule_id' => $scheduleId,
            'student_group_id' => $group->id,
        ]);

        $this->actingAsCourseHead($head);

        $this->from(route('maker.course_offerings.show', $offering))
            ->delete(route('maker.course_offerings.student_groups.destroy', [$offering, $group]))
            ->assertSessionHasErrors('student_groups');

        $this->assertDatabaseHas('student_groups', [
            'id' => $group->id,
        ]);
    }

    public function test_student_group_delete_succeeds_when_no_downstream_references_exist(): void
    {
        $head = $this->makeUser('course_head');
        $offering = $this->makeOffering($head);
        $group = StudentGroup::create([
            'course_offering_id' => $offering->id,
            'group_code' => 'A1',
            'student_count' => 20,
        ]);

        $this->actingAsCourseHead($head);

        $this->from(route('maker.course_offerings.show', $offering))
            ->delete(route('maker.course_offerings.student_groups.destroy', [$offering, $group]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('student_groups', [
            'id' => $group->id,
        ]);
    }

    private function actingAsCourseHead(User $user): void
    {
        $this->actingAs($user);
        $this->withSession(['active_role' => 'course_head']);
    }

    private function makeUser(string $role, bool $active = true): User
    {
        $number = $this->sequence++;

        $user = User::create([
            'username' => "user{$number}",
            'name' => "User {$number}",
            'email' => "user{$number}@example.com",
            'password' => Hash::make('password'),
            'is_active' => $active,
        ]);

        UserRole::create([
            'user_id' => $user->id,
            'role' => $role,
            'is_primary' => true,
        ]);

        InstructorProfile::create([
            'user_id' => $user->id,
            'title' => 'Instructor',
            'department_id' => $this->department()->id,
        ]);

        return $user;
    }

    private function makeOffering(User $coordinator, array $overrides = []): CourseOffering
    {
        $number = $this->sequence++;
        $course = $this->makeCourse([
            'course_code' => $overrides['course_code'] ?? "NUR{$number}",
            'name_th' => $overrides['name_th'] ?? "Course {$number}",
            'name_en' => $overrides['name_en'] ?? "Course {$number}",
            'course_type' => $overrides['course_type'] ?? 'theory_practicum',
            'lecture_hours' => $overrides['lecture_hours'] ?? 2,
            'lab_hours' => $overrides['lab_hours'] ?? 1,
        ]);

        return CourseOffering::create([
            'course_id' => $course->id,
            'academic_year_id' => $this->academicYear($number, $overrides['phase'] ?? 'scheduling')->id,
            'coordinator_id' => $coordinator->id,
            'approval_status' => 'draft',
            'total_student_count' => $overrides['total_student_count'] ?? 30,
            'planned_lecture_hours' => $overrides['planned_lecture_hours'] ?? null,
            'planned_lab_hours' => $overrides['planned_lab_hours'] ?? null,
            'planned_practicum_hours' => $overrides['planned_practicum_hours'] ?? null,
            'teaching_weeks' => $overrides['teaching_weeks'] ?? 15,
            'requires_practicum_rotation' => $overrides['requires_practicum_rotation'] ?? false,
        ]);
    }

    private function makeCourse(array $overrides = []): Course
    {
        $number = $this->sequence++;

        return Course::create([
            'course_code' => $overrides['course_code'] ?? "COURSE{$number}",
            'curriculum_id' => $overrides['curriculum_id'] ?? $this->curriculum()->id,
            'department_id' => $overrides['department_id'] ?? $this->department()->id,
            'name_th' => $overrides['name_th'] ?? "Course {$number}",
            'name_en' => $overrides['name_en'] ?? "Course {$number}",
            'course_type' => $overrides['course_type'] ?? 'theory_practicum',
            'academic_level' => $overrides['academic_level'] ?? 'undergraduate',
            'default_year_level' => $overrides['default_year_level'] ?? 2,
            'default_semester' => $overrides['default_semester'] ?? 1,
            'requires_practicum_rotation' => $overrides['requires_practicum_rotation'] ?? false,
            'credits' => $overrides['credits'] ?? 3,
            'lecture_hours' => $overrides['lecture_hours'] ?? 2,
            'lab_hours' => $overrides['lab_hours'] ?? 1,
            'self_study_hours' => $overrides['self_study_hours'] ?? 3,
            'status' => $overrides['status'] ?? 'active',
        ]);
    }

    private function department(): Department
    {
        return Department::firstOrCreate(['name' => 'Nursing Department']);
    }

    private function curriculum(): Curriculum
    {
        return Curriculum::firstOrCreate([
            'name' => 'Nursing Curriculum',
        ], [
            'effective_year' => 2569,
            'is_active' => true,
        ]);
    }

    private function academicYear(int $number, string $phase = 'scheduling'): AcademicYear
    {
        return AcademicYear::create([
            'name' => "2569-{$number}",
            'semester' => 1,
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
            'phase' => $phase,
        ]);
    }

    private function createActivityType(): int
    {
        return DB::table('activity_types')->insertGetId([
            'name' => 'Lecture',
            'color_code' => '#2563eb',
            'category' => 'lecture',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
