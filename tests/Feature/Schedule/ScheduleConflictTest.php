<?php

namespace Tests\Feature\Schedule;

use App\Models\AcademicYear;
use App\Models\ActivityType;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\InstructorProfile;
use App\Models\LocationType;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ScheduleTemplate;
use App\Models\StudentCohort;
use App\Models\StudentGroup;
use App\Models\User;
use App\Models\UserRole;
use App\Jobs\ConflictRecomputeJob;
use App\Services\ScheduleConflictIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * M3/M4 — copy-week + update/delete + overlap conflicts (instructor/room/group/time/block) + audit + conflict policy
 */
class ScheduleConflictTest extends ScheduleTestCase
{
    public function test_schedule_page_renders_copy_week_button(): void
    {
        [$head, $offering] = $this->makeReadyOffering();

        $this->actingAsCourseHead($head);

        $this->get(route('maker.course_offerings.schedules.index', $offering))
            ->assertOk()
            ->assertSee('schedule-copy-week-button')
            ->assertSee('schedule-copy-week-modal');
    }

    public function test_course_head_can_copy_week_into_empty_target_week(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
            'topic' => 'WeekOneActivity',
        ]);

        $this->actingAsCourseHead($head);

        $this->post(route('maker.course_offerings.schedules.copy_week', $offering), [
            'source_week_start' => '2026-08-03',
            'target_week_start' => '2026-08-10',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('schedules', [
            'course_offering_id' => $offering->id,
            'start_date' => '2026-08-10',
            'topic' => 'WeekOneActivity',
            'status' => 'draft',
        ]);
    }

    public function test_course_head_can_copy_single_day_into_empty_target_day(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
            'start_date' => '2026-08-05',
            'end_date' => '2026-08-05',
            'topic' => 'DayCopyActivity',
        ]);

        $this->actingAsCourseHead($head);

        $this->post(route('maker.course_offerings.schedules.copy_week', $offering), [
            'copy_mode' => 'day',
            'source_date' => '2026-08-05',
            'target_date' => '2026-08-12',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('schedules', [
            'course_offering_id' => $offering->id,
            'start_date' => '2026-08-12',
            'end_date' => '2026-08-12',
            'topic' => 'DayCopyActivity',
        ]);
    }

    public function test_course_head_can_copy_custom_date_range_into_empty_target_range(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
            'topic' => 'RangeCopyDayOne',
        ]);
        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
            'start_date' => '2026-08-04',
            'end_date' => '2026-08-04',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'topic' => 'RangeCopyDayTwo',
        ]);

        $this->actingAsCourseHead($head);

        $this->post(route('maker.course_offerings.schedules.copy_week', $offering), [
            'copy_mode' => 'range',
            'source_start_date' => '2026-08-03',
            'source_end_date' => '2026-08-04',
            'target_start_date' => '2026-08-17',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('schedules', [
            'course_offering_id' => $offering->id,
            'start_date' => '2026-08-17',
            'topic' => 'RangeCopyDayOne',
        ]);
        $this->assertDatabaseHas('schedules', [
            'course_offering_id' => $offering->id,
            'start_date' => '2026-08-18',
            'topic' => 'RangeCopyDayTwo',
        ]);
    }

    public function test_copy_week_skips_slot_conflicting_with_another_course(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'topic' => 'WillConflict',
        ]);

        // วิชาอื่นจองผู้สอนคนเดียวกันไว้แล้วในวัน/เวลาปลายทาง (cross-course conflict)
        [, $otherOffering, , $otherGroup, $otherActivity, $otherRoom] = $this->makeReadyOffering();
        $this->makeSchedule($otherOffering, $otherActivity, $otherRoom, [$instructor], [$otherGroup], [
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'topic' => 'Blocker',
        ]);

        $this->actingAsCourseHead($head);

        $this->post(route('maker.course_offerings.schedules.copy_week', $offering), [
            'source_week_start' => '2026-08-03',
            'target_week_start' => '2026-08-10',
        ])->assertRedirect()->assertSessionHasNoErrors();

        // slot ที่ชนต้องไม่ถูกสร้าง
        $this->assertDatabaseMissing('schedules', [
            'course_offering_id' => $offering->id,
            'start_date' => '2026-08-10',
            'topic' => 'WillConflict',
        ]);
        $this->assertEquals(['WillConflict'], collect(session('schedule_copy_skipped'))->pluck('topic')->all());
    }

    public function test_copy_week_preview_classifies_ready_and_blocked(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'topic' => 'Clean',
        ]);

        $this->actingAsCourseHead($head);

        $this->post(route('maker.course_offerings.schedules.copy_week.preview', $offering), [
            'source_week_start' => '2026-08-03',
            'target_week_start' => '2026-08-10',
        ])->assertOk()->assertJson([
            'total' => 1,
            'ready' => [['topic' => 'Clean', 'target_date' => '2026-08-10']],
            'blocked' => [],
        ]);
    }

    public function test_course_head_can_update_schedule_and_pivots(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $newInstructor = $this->makeUser('instructor');
        $newGroup = StudentGroup::create([
            'course_offering_id' => $offering->id,
            'group_code' => 'A2',
            'student_count' => 15,
        ]);
        $offering->instructorPool()->attach($newInstructor->id, ['role_in_course' => 'instructor']);
        $schedule = $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group]);

        $payload = $this->schedulePayload($newInstructor, $newGroup, $activityType, $room, [
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-14',
            'topic' => 'Updated topic',
        ]);

        $this->actingAsCourseHead($head);

        $this->put(route('maker.course_offerings.schedules.update', [$offering, $schedule]), $payload)
            ->assertRedirect(route('maker.course_offerings.schedules.index', $offering))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-14',
            'topic' => 'Updated topic',
        ]);
        $this->assertDatabaseMissing('schedule_instructors', ['schedule_id' => $schedule->id, 'user_id' => $instructor->id]);
        $this->assertDatabaseHas('schedule_instructors', ['schedule_id' => $schedule->id, 'user_id' => $newInstructor->id]);
        $this->assertDatabaseMissing('schedule_student_groups', ['schedule_id' => $schedule->id, 'student_group_id' => $group->id]);
        $this->assertDatabaseHas('schedule_student_groups', ['schedule_id' => $schedule->id, 'student_group_id' => $newGroup->id]);
    }

    public function test_update_blocks_overlap_within_same_offering(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $otherInstructor = $this->makeUser('instructor');
        $otherGroup = StudentGroup::create([
            'course_offering_id' => $offering->id,
            'group_code' => 'A2',
            'student_count' => 15,
        ]);
        $otherRoom = $this->makeRoom();
        $offering->instructorPool()->attach($otherInstructor->id, ['role_in_course' => 'instructor']);

        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
            'topic' => 'Blocker',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);
        $schedule = $this->makeSchedule($offering, $activityType, $otherRoom, [$otherInstructor], [$otherGroup], [
            'topic' => 'Original',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        $this->actingAsCourseHead($head);

        $this->put(route('maker.course_offerings.schedules.update', [$offering, $schedule]), $this->schedulePayload($instructor, $group, $activityType, $room, [
            'topic' => 'Should not save',
            'start_time' => '08:30',
            'end_time' => '09:30',
        ]))
            ->assertRedirect()
            ->assertSessionHasErrors('schedule');

        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'topic' => 'Original',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);
        $this->assertDatabaseMissing('schedules', [
            'id' => $schedule->id,
            'topic' => 'Should not save',
        ]);
    }

    public function test_update_blocks_overlap_across_offerings(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        [, $otherOffering, , $otherGroup, $otherActivityType, $otherRoom] = $this->makeReadyOffering();
        $otherOffering->instructorPool()->attach($instructor->id, ['role_in_course' => 'instructor']);

        $this->makeSchedule($otherOffering, $otherActivityType, $otherRoom, [$instructor], [$otherGroup], [
            'topic' => 'Cross course blocker',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);
        $schedule = $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
            'topic' => 'Original',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        $this->actingAsCourseHead($head);

        $this->put(route('maker.course_offerings.schedules.update', [$offering, $schedule]), $this->schedulePayload($instructor, $group, $activityType, $room, [
            'topic' => 'Should not save',
            'start_time' => '08:30',
            'end_time' => '09:30',
        ]))
            ->assertRedirect()
            ->assertSessionHasErrors('schedule');

        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'topic' => 'Original',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);
        $this->assertDatabaseMissing('schedules', [
            'id' => $schedule->id,
            'topic' => 'Should not save',
        ]);
    }

    public function test_course_head_can_delete_schedule_and_pivots(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $schedule = $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group]);

        $this->actingAsCourseHead($head);

        $this->delete(route('maker.course_offerings.schedules.destroy', [$offering, $schedule]))
            ->assertRedirect(route('maker.course_offerings.schedules.index', $offering));

        $this->assertDatabaseMissing('schedules', ['id' => $schedule->id]);
        $this->assertDatabaseMissing('schedule_instructors', ['schedule_id' => $schedule->id]);
        $this->assertDatabaseMissing('schedule_student_groups', ['schedule_id' => $schedule->id]);
    }

    public function test_instructor_overlap_blocks_save_across_offerings(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        [, $otherOffering, , $otherGroup, $otherActivityType, $otherRoom] = $this->makeReadyOffering();
        $otherOffering->instructorPool()->attach($instructor->id, ['role_in_course' => 'instructor']);
        $this->makeSchedule($otherOffering, $otherActivityType, $otherRoom, [$instructor], [$otherGroup]);

        $this->actingAsCourseHead($head);

        $this->post(route('maker.course_offerings.schedules.store', $offering), $this->schedulePayload($instructor, $group, $activityType, $room))
            ->assertRedirect()
            ->assertSessionHasErrors('schedule');

        $this->assertDatabaseCount('schedules', 1);
    }

    public function test_room_overlap_blocks_save_within_same_offering(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $otherInstructor = $this->makeUser('instructor');
        $otherGroup = StudentGroup::create(['course_offering_id' => $offering->id, 'group_code' => 'A2', 'student_count' => 10]);
        $offering->instructorPool()->attach($otherInstructor->id, ['role_in_course' => 'instructor']);
        $this->makeSchedule($offering, $activityType, $room, [$otherInstructor], [$otherGroup]);

        $this->actingAsCourseHead($head);

        $this->post(route('maker.course_offerings.schedules.store', $offering), $this->schedulePayload($instructor, $group, $activityType, $room))
            ->assertRedirect()
            ->assertSessionHasErrors('schedule');

        $this->assertDatabaseCount('schedules', 1);
    }

    public function test_same_offering_conflict_with_generated_week_one_blocks_save(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();

        $this->actingAsCourseHead($head);

        $this->post(route('maker.course_offerings.schedules.series.store', $offering), [
            'weekday' => 1,
            'start_week' => 1,
            'end_week' => 2,
            'start_time' => '08:00',
            'end_time' => '10:00',
            'activity_type_id' => $activityType->id,
            'room_id' => $room->id,
            'topic' => 'Weekly conflict source',
            'instructor_ids' => [$instructor->id],
            'lead_instructor_id' => $instructor->id,
            'student_group_ids' => [$group->id],
        ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // V4: เฉพาะสัปดาห์แรก (2026-08-03) ที่ได้ resource ครบ → ชนกับ slot ใหม่วันเดียวกัน
        // (สัปดาห์ที่สองเป็น shell ไม่มีผู้สอน/ห้อง/กลุ่ม จึงไม่ก่อให้เกิดการชน)
        $this->post(route('maker.course_offerings.schedules.store', $offering), $this->schedulePayload($instructor, $group, $activityType, $room, [
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
            'topic' => 'Conflicts with generated week one',
        ]))
            ->assertRedirect()
            ->assertSessionHasErrors('schedule');

        $this->assertDatabaseMissing('schedules', [
            'course_offering_id' => $offering->id,
            'topic' => 'Conflicts with generated week one',
        ]);
    }

    public function test_schedule_series_custom_range_rejects_invalid_thai_date(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();

        $this->actingAsCourseHead($head);

        $this->post(route('maker.course_offerings.schedules.series.store', $offering), [
            'weekday' => 1,
            'start_week' => 1,
            'end_week' => 2,
            'use_custom_series_range' => '1',
            'starts_on' => '32/13/2569',
            'ends_on' => '32/13/2569',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'activity_type_id' => $activityType->id,
            'room_id' => $room->id,
            'topic' => 'Invalid custom range',
            'instructor_ids' => [$instructor->id],
            'lead_instructor_id' => $instructor->id,
            'student_group_ids' => [$group->id],
        ])
            ->assertRedirect()
            ->assertSessionHasErrors(['starts_on', 'ends_on']);

        $this->assertDatabaseCount('schedule_templates', 0);
        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_room_overlap_blocks_save_across_offerings(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        [, $otherOffering, $otherInstructor, $otherGroup, $otherActivityType] = $this->makeReadyOffering();

        $this->makeSchedule($otherOffering, $otherActivityType, $room, [$otherInstructor], [$otherGroup]);

        $this->actingAsCourseHead($head);

        $this->post(route('maker.course_offerings.schedules.store', $offering), $this->schedulePayload($instructor, $group, $activityType, $room))
            ->assertRedirect()
            ->assertSessionHasErrors('schedule');

        $this->assertDatabaseCount('schedules', 1);
    }

    public function test_student_group_overlap_blocks_save_within_same_offering(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $otherInstructor = $this->makeUser('instructor');
        $otherRoom = $this->makeRoom();
        $offering->instructorPool()->attach($otherInstructor->id, ['role_in_course' => 'instructor']);
        $this->makeSchedule($offering, $activityType, $otherRoom, [$otherInstructor], [$group]);

        $this->actingAsCourseHead($head);

        $this->post(route('maker.course_offerings.schedules.store', $offering), $this->schedulePayload($instructor, $group, $activityType, $room))
            ->assertRedirect()
            ->assertSessionHasErrors('schedule');

        $this->assertDatabaseCount('schedules', 1);
    }

    public function test_student_group_overlap_blocks_save_across_offerings_when_sharing_root_cohort(): void
    {
        // V4 ข้อ 2: กลุ่มที่อ้างอิง cohort root เดียวกัน ห้ามอยู่ 2 วิชาพร้อมกันในเวลาทับ
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        [, $otherOffering, $otherInstructor, $otherGroup, $otherActivityType, $otherRoom] = $this->makeReadyOffering();

        $cohort = $this->makeCohort('Y2-A');
        $group->update(['cohort_group_id' => $cohort->id]);
        $otherGroup->update(['cohort_group_id' => $cohort->id]);

        // ตารางวิชาอื่น: คนละอาจารย์ คนละห้อง ชนเฉพาะ "กลุ่ม" (cohort เดียวกัน) เท่านั้น
        $this->makeSchedule($otherOffering, $otherActivityType, $otherRoom, [$otherInstructor], [$otherGroup]);

        $this->actingAsCourseHead($head);

        $this->post(route('maker.course_offerings.schedules.store', $offering), $this->schedulePayload($instructor, $group, $activityType, $room))
            ->assertRedirect()
            ->assertSessionHasErrors('schedule');

        $this->assertDatabaseCount('schedules', 1);
    }

    public function test_student_group_does_not_conflict_across_offerings_with_different_cohorts(): void
    {
        // regression: cross-course group conflict ต้องอิง cohort root จริงเท่านั้น
        // กลุ่มคนละ cohort (และกลุ่มที่ไม่ผูก cohort) ต้องไม่ชนกันข้ามวิชา
        // กัน false positive จากการเอา student_groups.id ไปเทียบกับ cohort_group_id
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        [, $otherOffering, $otherInstructor, $otherGroup, $otherActivityType, $otherRoom] = $this->makeReadyOffering();

        $group->update(['cohort_group_id' => $this->makeCohort('Y2-A')->id]);
        $otherGroup->update(['cohort_group_id' => $this->makeCohort('Y2-B')->id]);

        $this->makeSchedule($otherOffering, $otherActivityType, $otherRoom, [$otherInstructor], [$otherGroup]);

        $this->actingAsCourseHead($head);

        $this->post(route('maker.course_offerings.schedules.store', $offering), $this->schedulePayload($instructor, $group, $activityType, $room))
            ->assertRedirect(route('maker.course_offerings.schedules.index', $offering))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('schedules', 2);
    }

    public function test_adjacent_time_does_not_conflict(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group]);

        $this->actingAsCourseHead($head);

        $this->post(route('maker.course_offerings.schedules.store', $offering), $this->schedulePayload($instructor, $group, $activityType, $room, [
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]))
            ->assertRedirect(route('maker.course_offerings.schedules.index', $offering))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('schedules', 2);
    }

    public function test_partial_time_overlap_blocks_save_within_same_offering(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group]);

        $this->actingAsCourseHead($head);

        $this->post(route('maker.course_offerings.schedules.store', $offering), $this->schedulePayload($instructor, $group, $activityType, $room, [
            'start_time' => '09:30',
            'end_time' => '10:30',
        ]))
            ->assertRedirect()
            ->assertSessionHasErrors('schedule');

        $this->assertDatabaseCount('schedules', 1);
    }

    public function test_block_date_range_overlap_blocks_save_within_same_offering(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group]);

        $this->actingAsCourseHead($head);

        $this->post(route('maker.course_offerings.schedules.store', $offering), $this->schedulePayload($instructor, $group, $activityType, $room, [
            'start_date' => '2026-08-07',
            'end_date' => '2026-08-10',
        ]))
            ->assertRedirect()
            ->assertSessionHasErrors('schedule');

        $this->assertDatabaseCount('schedules', 1);
    }

    public function test_block_date_range_non_overlap_does_not_conflict(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group]);

        $this->actingAsCourseHead($head);

        $this->post(route('maker.course_offerings.schedules.store', $offering), $this->schedulePayload($instructor, $group, $activityType, $room, [
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-14',
        ]))
            ->assertRedirect(route('maker.course_offerings.schedules.index', $offering))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('schedules', 2);
    }

    public function test_schedule_on_soft_deleted_offering_does_not_create_false_conflict(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        [, $deletedOffering, , $deletedGroup, $deletedActivityType] = $this->makeReadyOffering();
        $deletedOffering->instructorPool()->attach($instructor->id, ['role_in_course' => 'instructor']);
        $this->makeSchedule($deletedOffering, $deletedActivityType, $room, [$instructor], [$deletedGroup]);
        $deletedOffering->delete();

        $this->actingAsCourseHead($head);

        $this->post(route('maker.course_offerings.schedules.store', $offering), $this->schedulePayload($instructor, $group, $activityType, $room))
            ->assertRedirect(route('maker.course_offerings.schedules.index', $offering))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('schedules', 2);
    }

    public function test_update_ignores_current_schedule_when_checking_conflicts(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $schedule = $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group]);

        $this->actingAsCourseHead($head);

        $this->put(route('maker.course_offerings.schedules.update', [$offering, $schedule]), $this->schedulePayload($instructor, $group, $activityType, $room, [
            'topic' => 'Self update',
        ]))
            ->assertRedirect(route('maker.course_offerings.schedules.index', $offering))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('schedules', ['id' => $schedule->id, 'topic' => 'Self update']);
    }

    public function test_create_schedule_creates_audit_log(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();

        $this->actingAsCourseHead($head);

        $this->post(
            route('maker.course_offerings.schedules.store', $offering),
            $this->schedulePayload($instructor, $group, $activityType, $room)
        )->assertRedirect()->assertSessionHasNoErrors();

        $schedule = Schedule::firstOrFail();

        $log = AuditLog::where('action', 'ตารางสอน.สร้าง')
            ->where('table_affected', 'schedules')
            ->where('record_id', $schedule->id)
            ->first();

        $this->assertNotNull($log, 'Expected audit log for schedule create was not found');
        $this->assertEquals('ตารางสอน', $log->category);
        $this->assertNull($log->old_values);
        $this->assertArrayHasKey('topic',        $log->new_values);
        $this->assertArrayHasKey('course_code',  $log->new_values);
        $this->assertArrayHasKey('instructors',  $log->new_values);
        $this->assertArrayHasKey('student_groups', $log->new_values);
    }

    public function test_update_schedule_creates_audit_log_with_diff_only(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $schedule = $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group]);

        $this->actingAsCourseHead($head);

        $this->put(
            route('maker.course_offerings.schedules.update', [$offering, $schedule]),
            $this->schedulePayload($instructor, $group, $activityType, $room, ['topic' => 'Audit updated topic'])
        )->assertRedirect()->assertSessionHasNoErrors();

        $log = AuditLog::where('action', 'ตารางสอน.แก้ไข')
            ->where('table_affected', 'schedules')
            ->where('record_id', $schedule->id)
            ->first();

        $this->assertNotNull($log, 'Expected audit log for schedule update was not found');
        // Only changed field (topic) should appear in old/new
        $this->assertArrayHasKey('topic', $log->old_values);
        $this->assertArrayHasKey('topic', $log->new_values);
        $this->assertEquals('Existing schedule',   $log->old_values['topic']);
        $this->assertEquals('Audit updated topic', $log->new_values['topic']);
        // Unchanged fields must NOT appear in diff
        $this->assertArrayNotHasKey('start_date', $log->old_values);
        $this->assertArrayNotHasKey('start_date', $log->new_values);
    }

    public function test_delete_schedule_creates_audit_log_with_snapshot(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $schedule = $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group]);
        $scheduleId = $schedule->id;

        $this->actingAsCourseHead($head);

        $this->delete(route('maker.course_offerings.schedules.destroy', [$offering, $schedule]))
            ->assertRedirect();

        $this->assertDatabaseMissing('schedules', ['id' => $scheduleId]);

        $log = AuditLog::where('action', 'ตารางสอน.ลบ')
            ->where('table_affected', 'schedules')
            ->where('record_id', $scheduleId)
            ->first();

        $this->assertNotNull($log, 'Expected audit log for schedule delete was not found');
        // new_values only contains the injected 'context' key (logger always adds it)
        $this->assertArrayHasKey('context', $log->new_values);
        $this->assertCount(1, $log->new_values);
        $this->assertArrayHasKey('topic',         $log->old_values);
        $this->assertArrayHasKey('course_code',   $log->old_values);
        $this->assertArrayHasKey('student_groups', $log->old_values);
        $this->assertEquals('Existing schedule',  $log->old_values['topic']);
    }

    public function test_blocked_store_outside_scheduling_phase_does_not_create_audit_log(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering('preparation');

        $this->actingAsCourseHead($head);

        $this->post(
            route('maker.course_offerings.schedules.store', $offering),
            $this->schedulePayload($instructor, $group, $activityType, $room)
        )->assertRedirect()->assertSessionHasErrors('schedule');

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_conflict_index_suppresses_team_supervision_instructor_overlap(): void
    {
        [, $offering, $instructor, $firstGroup, , $firstRoom] = $this->makeReadyOffering();
        $secondGroup = StudentGroup::create([
            'course_offering_id' => $offering->id,
            'group_code' => 'A2',
            'student_count' => 15,
        ]);
        $secondRoom = $this->makeRoom();
        $practicum = $this->makePracticumActivityType();

        $first = $this->makeSchedule($offering, $practicum, $firstRoom, [$instructor], [$firstGroup]);
        $second = $this->makeSchedule($offering, $practicum, $secondRoom, [$instructor], [$secondGroup]);

        $conflicts = app(ScheduleConflictIndex::class)->conflictsFor(
            Schedule::with(['activityType', 'courseOffering.course', 'room', 'instructors.instructorProfile', 'studentGroups'])
                ->whereIn('id', [$first->id, $second->id])
                ->get()
        );

        $this->assertTrue($conflicts->get($first->id)->isEmpty());
        $this->assertTrue($conflicts->get($second->id)->isEmpty());
    }

    public function test_conflict_index_suppresses_subgroup_practicum_split_group_overlap(): void
    {
        [, $offering, $firstInstructor, $group, , $firstRoom] = $this->makeReadyOffering();
        $secondInstructor = $this->makeUser('instructor');
        $secondRoom = $this->makeRoom();
        $practicum = $this->makePracticumActivityType();

        $first = $this->makeSchedule($offering, $practicum, $firstRoom, [$firstInstructor], [$group], [
            'sub_group_label' => 'A1a',
        ]);
        $second = $this->makeSchedule($offering, $practicum, $secondRoom, [$secondInstructor], [$group], [
            'sub_group_label' => 'A1b',
        ]);

        $conflicts = app(ScheduleConflictIndex::class)->conflictsFor(
            Schedule::with(['activityType', 'courseOffering.course', 'room', 'instructors.instructorProfile', 'studentGroups'])
                ->whereIn('id', [$first->id, $second->id])
                ->get()
        );

        $this->assertTrue($conflicts->get($first->id)->isEmpty());
        $this->assertTrue($conflicts->get($second->id)->isEmpty());
    }

    public function test_conflict_policy_does_not_suppress_room_overlap(): void
    {
        [, $offering, $instructor, $firstGroup, , $room] = $this->makeReadyOffering();
        $secondGroup = StudentGroup::create([
            'course_offering_id' => $offering->id,
            'group_code' => 'A2',
            'student_count' => 15,
        ]);
        $practicum = $this->makePracticumActivityType();

        $first = $this->makeSchedule($offering, $practicum, $room, [$instructor], [$firstGroup]);
        $second = $this->makeSchedule($offering, $practicum, $room, [$instructor], [$secondGroup]);

        $conflicts = app(ScheduleConflictIndex::class)->conflictsFor(
            Schedule::with(['activityType', 'courseOffering.course', 'room', 'instructors.instructorProfile', 'studentGroups'])
                ->whereIn('id', [$first->id, $second->id])
                ->get()
        );

        $this->assertTrue($conflicts->get($first->id)->contains(fn (array $conflict) => $conflict['type'] === 'room_overlap'));
        $this->assertFalse($conflicts->get($first->id)->contains(fn (array $conflict) => $conflict['type'] === 'instructor_overlap'));
    }

}
