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

    public function test_course_head_index_shows_offering_once_with_multiple_instructors_and_groups(): void
    {
        $head = $this->makeUser('course_head');
        $offering = $this->makeOffering($head, ['course_code' => 'NUR111']);
        $firstInstructor = $this->makeUser('instructor');
        $secondInstructor = $this->makeUser('instructor');

        $offering->instructorPool()->attach($head->id, ['role_in_course' => 'coordinator']);
        $offering->instructorPool()->attach($firstInstructor->id, ['role_in_course' => 'instructor']);
        $offering->instructorPool()->attach($secondInstructor->id, ['role_in_course' => 'assistant_teacher']);

        StudentGroup::create([
            'course_offering_id' => $offering->id,
            'group_code' => 'A1',
            'student_count' => 20,
        ]);
        StudentGroup::create([
            'course_offering_id' => $offering->id,
            'group_code' => 'A2',
            'student_count' => 10,
        ]);

        $this->actingAsCourseHead($head);

        $response = $this->get(route('maker.course_offerings.index'));

        $response->assertOk();
        $this->assertSame(
            1,
            substr_count($response->getContent(), $this->offeringPath($offering))
        );
        $response->assertSee('2 กลุ่ม');
    }

    public function test_course_head_index_shows_full_student_group_capacity_badge(): void
    {
        $head = $this->makeUser('course_head');
        $otherHead = $this->makeUser('course_head');
        $offering = $this->makeOffering($head, [
            'course_code' => 'FULL101',
            'total_student_count' => 60,
        ]);
        $unrelated = $this->makeOffering($otherHead, [
            'course_code' => 'HIDDEN101',
            'total_student_count' => 60,
        ]);

        StudentGroup::create([
            'course_offering_id' => $offering->id,
            'group_code' => 'A1',
            'student_count' => 30,
        ]);
        StudentGroup::create([
            'course_offering_id' => $offering->id,
            'group_code' => 'A2',
            'student_count' => 30,
        ]);

        $this->actingAsCourseHead($head);

        $this->get(route('maker.course_offerings.index'))
            ->assertOk()
            ->assertSee('FULL101')
            ->assertSee('กลุ่มเต็ม')
            ->assertSee('จัดแล้ว 60/60 คน')
            ->assertDontSee($unrelated->course->course_code);
    }

    public function test_course_head_index_shows_low_remaining_student_count_badge(): void
    {
        $head = $this->makeUser('course_head');
        $offering = $this->makeOffering($head, [
            'course_code' => 'LOW101',
            'total_student_count' => 60,
        ]);

        StudentGroup::create([
            'course_offering_id' => $offering->id,
            'group_code' => 'A1',
            'student_count' => 55,
        ]);

        $this->actingAsCourseHead($head);

        $this->get(route('maker.course_offerings.index'))
            ->assertOk()
            ->assertSee('LOW101')
            ->assertSee('เหลือ 5 คน')
            ->assertSee('จัดแล้ว 55/60 คน');
    }

    public function test_course_head_index_shows_no_groups_with_remaining_student_count(): void
    {
        $head = $this->makeUser('course_head');
        $this->makeOffering($head, [
            'course_code' => 'NOGROUP101',
            'total_student_count' => 60,
        ]);

        $this->actingAsCourseHead($head);

        $this->get(route('maker.course_offerings.index'))
            ->assertOk()
            ->assertSee('NOGROUP101')
            ->assertSee('ยังไม่มีกลุ่ม')
            ->assertSee('เหลือ 60 คน')
            ->assertSee('จัดแล้ว 0/60 คน');
    }

    public function test_course_head_index_shows_missing_student_total_badge(): void
    {
        $head = $this->makeUser('course_head');
        $this->makeOffering($head, [
            'course_code' => 'NOTOTAL101',
            'total_student_count' => null,
        ]);

        $this->actingAsCourseHead($head);

        $this->get(route('maker.course_offerings.index'))
            ->assertOk()
            ->assertSee('NOTOTAL101')
            ->assertSee('ยังไม่ได้กำหนดจำนวนนักศึกษา')
            ->assertSee('รับได้ - คน');
    }

    public function test_course_head_index_keeps_legitimate_distinct_offerings_separate(): void
    {
        $head = $this->makeUser('course_head');
        $course = $this->makeCourse(['course_code' => 'NUR222']);
        $firstOffering = $this->makeOffering($head, [
            'course_id' => $course->id,
            'academic_year_id' => $this->academicYear(101, 'scheduling')->id,
        ]);
        $secondOffering = $this->makeOffering($head, [
            'course_id' => $course->id,
            'academic_year_id' => $this->academicYear(102, 'scheduling')->id,
        ]);
        $unrelated = $this->makeOffering($this->makeUser('course_head'), ['course_code' => 'NUR333']);

        $this->actingAsCourseHead($head);

        $response = $this->get(route('maker.course_offerings.index'));

        $response->assertOk();
        $this->assertSame(
            1,
            substr_count($response->getContent(), $this->offeringPath($firstOffering))
        );
        $this->assertSame(
            1,
            substr_count($response->getContent(), $this->offeringPath($secondOffering))
        );
        $this->assertSame(
            0,
            substr_count($response->getContent(), $this->offeringPath($unrelated))
        );
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
            ->assertRedirect($this->studentGroupsUrl($secondOffering));

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

    public function test_bulk_student_group_delete_removes_selected_groups_only(): void
    {
        $head = $this->makeUser('course_head');
        $offering = $this->makeOffering($head, ['total_student_count' => 60]);
        $first = StudentGroup::create([
            'course_offering_id' => $offering->id,
            'group_code' => 'A1',
            'student_count' => 20,
        ]);
        $second = StudentGroup::create([
            'course_offering_id' => $offering->id,
            'group_code' => 'A2',
            'student_count' => 20,
        ]);
        $kept = StudentGroup::create([
            'course_offering_id' => $offering->id,
            'group_code' => 'A3',
            'student_count' => 20,
        ]);

        $this->actingAsCourseHead($head);

        $this->from(route('maker.course_offerings.show', $offering))
            ->delete(route('maker.course_offerings.student_groups.bulk_destroy', $offering), [
                'group_ids' => [$first->id, $second->id],
            ])
            ->assertRedirect($this->studentGroupsUrl($offering))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('student_groups', ['id' => $first->id]);
        $this->assertDatabaseMissing('student_groups', ['id' => $second->id]);
        $this->assertDatabaseHas('student_groups', ['id' => $kept->id]);
    }

    public function test_bulk_student_group_delete_blocks_selected_group_with_schedule_reference(): void
    {
        $head = $this->makeUser('course_head');
        $offering = $this->makeOffering($head);
        $blocked = StudentGroup::create([
            'course_offering_id' => $offering->id,
            'group_code' => 'A1',
            'student_count' => 20,
        ]);
        $selected = StudentGroup::create([
            'course_offering_id' => $offering->id,
            'group_code' => 'A2',
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
            'student_group_id' => $blocked->id,
        ]);

        $this->actingAsCourseHead($head);

        $this->from(route('maker.course_offerings.show', $offering))
            ->delete(route('maker.course_offerings.student_groups.bulk_destroy', $offering), [
                'group_ids' => [$blocked->id, $selected->id],
            ])
            ->assertRedirect($this->studentGroupsUrl($offering))
            ->assertSessionHasErrors('student_groups');

        $this->assertDatabaseHas('student_groups', ['id' => $blocked->id]);
        $this->assertDatabaseHas('student_groups', ['id' => $selected->id]);
    }

    public function test_bulk_destroy_rejects_group_ids_from_other_offerings(): void
    {
        $head = $this->makeUser('course_head');
        $myOffering = $this->makeOffering($head);
        $otherOffering = $this->makeOffering($this->makeUser('course_head'));

        $myGroup = StudentGroup::create([
            'course_offering_id' => $myOffering->id,
            'group_code' => 'A1',
            'student_count' => 20,
        ]);
        $foreignGroup = StudentGroup::create([
            'course_offering_id' => $otherOffering->id,
            'group_code' => 'B1',
            'student_count' => 20,
        ]);

        $this->actingAsCourseHead($head);

        // Trying to delete a group that belongs to someone else's offering must fail
        // with a 422-style error response (not 404) and not delete anything
        $this->from(route('maker.course_offerings.show', $myOffering))
            ->delete(route('maker.course_offerings.student_groups.bulk_destroy', $myOffering), [
                'group_ids' => [$myGroup->id, $foreignGroup->id],
            ])
            ->assertRedirect($this->studentGroupsUrl($myOffering))
            ->assertSessionHasErrors('student_groups');

        $this->assertDatabaseHas('student_groups', ['id' => $myGroup->id]);
        $this->assertDatabaseHas('student_groups', ['id' => $foreignGroup->id]);
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
        $courseId = $overrides['course_id'] ?? null;
        if (! $courseId) {
            $courseId = $this->makeCourse([
                'course_code' => $overrides['course_code'] ?? "NUR{$number}",
                'name_th' => $overrides['name_th'] ?? "Course {$number}",
                'name_en' => $overrides['name_en'] ?? "Course {$number}",
                'course_type' => $overrides['course_type'] ?? 'theory_practicum',
                'lecture_hours' => $overrides['lecture_hours'] ?? 2,
                'lab_hours' => $overrides['lab_hours'] ?? 1,
            ])->id;
        }

        return CourseOffering::create([
            'course_id' => $courseId,
            'academic_year_id' => $overrides['academic_year_id'] ?? $this->academicYear($number, $overrides['phase'] ?? 'scheduling')->id,
            'coordinator_id' => $coordinator->id,
            'approval_status' => 'draft',
            'total_student_count' => array_key_exists('total_student_count', $overrides) ? $overrides['total_student_count'] : 30,
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

    private function offeringPath(CourseOffering $offering): string
    {
        return parse_url(route('maker.course_offerings.show', $offering), PHP_URL_PATH);
    }

    private function studentGroupsUrl(CourseOffering $offering): string
    {
        return route('maker.course_offerings.show', $offering) . '#student-groups';
    }
}
