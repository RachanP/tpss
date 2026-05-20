<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ActivityType;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\InstructorProfile;
use App\Models\LocationType;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\StudentGroup;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ScheduleManagementTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 1;

    public function test_course_head_can_access_own_offering_schedules(): void
    {
        [$head, $offering] = $this->makeReadyOffering();

        $this->actingAsCourseHead($head);

        $this->get(route('maker.course_offerings.schedules.index', $offering))
            ->assertOk()
            ->assertSee('ตารางสอน')
            ->assertSee($offering->course->course_code);
    }

    public function test_schedule_index_renders_list_and_grid_views(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group]);

        $this->actingAsCourseHead($head);

        $this->get(route('maker.course_offerings.schedules.index', [$offering, 'week_start' => '2026-08-03']))
            ->assertOk()
            ->assertSee('data-testid="schedule-list-toggle"', false)
            ->assertSee('data-testid="schedule-grid-toggle"', false)
            ->assertSee('data-testid="schedule-list-view"', false)
            ->assertSee('data-testid="schedule-grid-view"', false)
            ->assertSee('Existing schedule')
            ->assertSee('08:00')
            ->assertSee($group->group_code)
            ->assertSee($room->room_name);
    }

    public function test_schedule_grid_includes_dynamic_evening_time_slots(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
            'start_date' => '2026-05-20',
            'end_date' => '2026-05-20',
            'start_time' => '18:23',
            'end_time' => '21:19',
            'topic' => 'Reflection',
        ]);

        $this->actingAsCourseHead($head);

        $this->get(route('maker.schedules.index', ['week_start' => '2026-05-18']))
            ->assertOk()
            ->assertSee('data-testid="schedule-list-view"', false)
            ->assertSee('data-testid="schedule-grid-view"', false)
            ->assertSee('18:23-21:19')
            ->assertSee('18:00')
            ->assertSee('Reflection')
            ->assertSee($group->group_code);
    }

    public function test_block_date_schedule_displays_across_matching_week_days(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group]);

        $this->actingAsCourseHead($head);

        $this->get(route('maker.course_offerings.schedules.index', [$offering, 'week_start' => '2026-08-03']))
            ->assertOk()
            ->assertSee('วันจันทร์')
            ->assertSee('วันศุกร์')
            ->assertSee('03/08/2026')
            ->assertSee('07/08/2026')
            ->assertSee('Existing schedule');
    }

    public function test_nested_create_route_redirects_to_schedule_modal(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();

        $this->actingAsCourseHead($head);

        $this->get(route('maker.course_offerings.schedules.create', [$offering, 'week_start' => '2026-08-03']))
            ->assertRedirect(route('maker.course_offerings.schedules.index', [
                $offering,
                'modal' => 'create',
                'week_start' => '2026-08-03',
            ]));
    }

    public function test_nested_edit_route_redirects_to_schedule_edit_modal(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $schedule = $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group]);

        $this->actingAsCourseHead($head);

        $this->get(route('maker.course_offerings.schedules.edit', [$offering, $schedule]))
            ->assertRedirect(route('maker.course_offerings.schedules.index', [
                $offering,
                'edit_schedule_id' => $schedule->id,
                'week_start' => '2026-08-03',
            ]));
    }

    public function test_course_head_can_access_schedule_workspace(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group]);

        $this->actingAsCourseHead($head);

        $this->get(route('maker.schedules.index', ['week_start' => '2026-08-03']))
            ->assertOk()
            ->assertSee('data-testid="schedule-list-toggle"', false)
            ->assertSee('data-testid="schedule-grid-toggle"', false)
            ->assertSee($offering->course->course_code)
            ->assertSee('Existing schedule')
            ->assertDontSee('รายวิชาสำหรับจัดตาราง');
    }

    public function test_schedule_workspace_default_offering_uses_only_coordinated_offerings(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        [, $secondOffering, $secondInstructor, $secondGroup, $secondActivityType, $secondRoom] = $this->makeReadyOffering();
        [, $otherOffering, $otherInstructor, $otherGroup, $otherActivityType, $otherRoom] = $this->makeReadyOffering();
        $secondOffering->forceFill(['coordinator_id' => $head->id])->save();
        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group]);
        $this->makeSchedule($secondOffering, $secondActivityType, $secondRoom, [$secondInstructor], [$secondGroup]);
        $this->makeSchedule($otherOffering, $otherActivityType, $otherRoom, [$otherInstructor], [$otherGroup]);

        $this->actingAsCourseHead($head);

        $this->get(route('maker.schedules.index', ['week_start' => '2026-08-03']))
            ->assertOk()
            ->assertSee($offering->course->course_code)
            ->assertSee($secondOffering->course->course_code)
            ->assertDontSee($otherOffering->course->course_code);
    }

    public function test_schedule_workspace_has_no_course_selector_or_course_detail_button(): void
    {
        [$head] = $this->makeReadyOffering();

        $this->actingAsCourseHead($head);

        $this->get(route('maker.schedules.index'))
            ->assertOk()
            ->assertDontSee('schedule-offering-select')
            ->assertDontSee('รายละเอียดรายวิชา');
    }

    public function test_schedule_workspace_create_button_opens_create_modal(): void
    {
        [$head] = $this->makeReadyOffering();

        $this->actingAsCourseHead($head);

        $this->get(route('maker.schedules.index', ['week_start' => '2026-08-03']))
            ->assertOk()
            ->assertSee('data-testid="schedule-create-link"', false)
            ->assertSee('data-testid="schedule-create-modal"', false)
            ->assertSee('action="' . route('maker.schedules.store') . '"', false)
            ->assertDontSee('href="' . route('maker.schedules.create', ['week_start' => '2026-08-03']) . '"', false);
    }

    public function test_activity_cards_open_detail_modal_with_edit_and_delete_actions(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $schedule = $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group]);

        $this->actingAsCourseHead($head);

        $this->get(route('maker.schedules.index', ['week_start' => '2026-08-03']))
            ->assertOk()
            ->assertSee('data-schedule-modal-trigger', false)
            ->assertSee('data-testid="schedule-detail-modal"', false)
            ->assertSee('data-testid="schedule-edit-modal-trigger"', false)
            ->assertSee('data-testid="schedule-edit-modal"', false)
            ->assertSee('data-testid="schedule-edit-form"', false)
            ->assertSee('action="' . route('maker.course_offerings.schedules.update', [$offering, $schedule]) . '"', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('value="2026-08-03"', false)
            ->assertSee('value="08:00"', false)
            ->assertSee('Existing schedule')
            ->assertSee('data-testid="schedule-delete-button"', false)
            ->assertDontSee('href="' . route('maker.course_offerings.schedules.edit', [$offering, $schedule]) . '"', false)
            ->assertDontSee('class="activity-actions"', false);
    }

    public function test_schedule_workspace_create_modal_lists_only_scheduling_coordinated_offerings(): void
    {
        [$head, $offering, $instructor, $group] = $this->makeReadyOffering();
        [, $closedOffering] = $this->makeReadyOffering('preparation');
        [, $otherOffering] = $this->makeReadyOffering();
        $closedOffering->forceFill(['coordinator_id' => $head->id])->save();

        $this->actingAsCourseHead($head);

        $this->get(route('maker.schedules.index'))
            ->assertOk()
            ->assertSee('data-testid="schedule-course-offering"', false)
            ->assertSee($offering->course->course_code)
            ->assertSee($instructor->name)
            ->assertSee($group->group_code)
            ->assertDontSee($closedOffering->course->course_code)
            ->assertDontSee($otherOffering->course->course_code);
    }

    public function test_schedule_workspace_empty_state(): void
    {
        $head = $this->makeUser('course_head');

        $this->actingAsCourseHead($head);

        $this->get(route('maker.schedules.index'))
            ->assertOk()
            ->assertSee('ยังไม่มีรายวิชาที่ต้องจัดตาราง');
    }

    public function test_sidebar_schedule_link_points_to_workspace(): void
    {
        [$head] = $this->makeReadyOffering();

        $this->actingAsCourseHead($head);

        $this->get(route('maker.schedules.index'))
            ->assertOk()
            ->assertSee('href="' . route('maker.schedules.index') . '"', false);
    }

    public function test_nested_schedule_routes_activate_schedule_sidebar_item(): void
    {
        [$head, $offering] = $this->makeReadyOffering();

        $this->actingAsCourseHead($head);

        $this->get(route('maker.course_offerings.schedules.index', $offering))
            ->assertOk()
            ->assertSee('href="' . route('maker.schedules.index') . '" class="nv on"', false)
            ->assertDontSee('href="' . route('maker.course_offerings.index') . '" class="nv on"', false);
    }

    public function test_course_offering_detail_no_longer_shows_prominent_schedule_button(): void
    {
        [$head, $offering] = $this->makeReadyOffering();

        $this->actingAsCourseHead($head);

        $this->get(route('maker.course_offerings.show', $offering))
            ->assertOk()
            ->assertDontSee('data-testid="course-offering-schedules-link"', false);
    }

    public function test_global_create_route_redirects_to_workspace_create_modal(): void
    {
        [$head] = $this->makeReadyOffering();

        $this->actingAsCourseHead($head);

        $this->get(route('maker.schedules.create'))
            ->assertRedirect(route('maker.schedules.index', ['modal' => 'create']));
    }

    public function test_global_create_route_preserves_selected_scheduling_offering(): void
    {
        [$head, $offering] = $this->makeReadyOffering();
        [, $closedOffering] = $this->makeReadyOffering('preparation');
        $closedOffering->forceFill(['coordinator_id' => $head->id])->save();

        $this->actingAsCourseHead($head);

        $this->get(route('maker.schedules.create', ['course_offering_id' => $offering->id]))
            ->assertRedirect(route('maker.schedules.index', [
                'modal' => 'create',
                'course_offering_id' => $offering->id,
            ]));
    }

    public function test_global_create_redirects_when_no_scheduling_offering_is_available(): void
    {
        [$head] = $this->makeReadyOffering('preparation');

        $this->actingAsCourseHead($head);

        $this->get(route('maker.schedules.create'))
            ->assertRedirect(route('maker.schedules.index'))
            ->assertSessionHasErrors('schedule');
    }

    public function test_global_store_creates_schedule_for_selected_offering(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();

        $this->actingAsCourseHead($head);

        $this->post(route('maker.schedules.store'), $this->schedulePayload($instructor, $group, $activityType, $room, [
            'course_offering_id' => $offering->id,
        ]))
            ->assertRedirect(route('maker.schedules.index', ['week_start' => '2026-08-03']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('schedules', [
            'course_offering_id' => $offering->id,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-07',
        ]);
        $this->assertDatabaseHas('schedule_instructors', ['user_id' => $instructor->id]);
        $this->assertDatabaseHas('schedule_student_groups', ['student_group_id' => $group->id]);
    }

    public function test_global_store_with_unowned_course_offering_is_rejected(): void
    {
        [$head, , $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        [, $otherOffering] = $this->makeReadyOffering();

        $this->actingAsCourseHead($head);

        $this->post(route('maker.schedules.store'), $this->schedulePayload($instructor, $group, $activityType, $room, [
            'course_offering_id' => $otherOffering->id,
        ]))
            ->assertSessionHasErrors('course_offering_id');

        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_global_store_outside_scheduling_phase_is_blocked(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering('preparation');

        $this->actingAsCourseHead($head);

        $this->post(route('maker.schedules.store'), $this->schedulePayload($instructor, $group, $activityType, $room, [
            'course_offering_id' => $offering->id,
        ]))
            ->assertRedirect(route('maker.schedules.index'))
            ->assertSessionHasErrors('schedule');

        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_non_coordinator_is_forbidden(): void
    {
        [, $offering] = $this->makeReadyOffering();
        $otherHead = $this->makeUser('course_head');

        $this->actingAsCourseHead($otherHead);

        $this->get(route('maker.course_offerings.schedules.index', $offering))->assertForbidden();
    }

    public function test_nested_schedule_route_binds_with_readable_course_offering_url(): void
    {
        [$head, $offering] = $this->makeReadyOffering();

        $this->actingAsCourseHead($head);

        $this->get('/maker/course-offerings/' . $offering->getRouteKey() . '/schedules')
            ->assertOk()
            ->assertSee($offering->course->course_code);
    }

    public function test_nested_schedule_route_binds_with_legacy_numeric_course_offering_url(): void
    {
        [$head, $offering] = $this->makeReadyOffering();

        $this->actingAsCourseHead($head);

        $this->get('/maker/course-offerings/' . $offering->id . '/schedules')
            ->assertOk()
            ->assertSee($offering->course->course_code);
    }

    public function test_malformed_week_start_falls_back_without_server_error(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group]);

        $this->actingAsCourseHead($head);

        $this->get(route('maker.schedules.index', ['week_start' => 'not-a-date']))
            ->assertOk()
            ->assertSee('Existing schedule');
    }

    public function test_schedule_mutations_blocked_outside_scheduling_phase(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering('preparation');

        $this->actingAsCourseHead($head);

        $this->post(route('maker.course_offerings.schedules.store', $offering), $this->schedulePayload($instructor, $group, $activityType, $room))
            ->assertRedirect(route('maker.course_offerings.schedules.index', $offering))
            ->assertSessionHasErrors('schedule');

        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_course_head_can_create_schedule_with_instructors_and_groups(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();

        $this->actingAsCourseHead($head);

        $this->post(route('maker.course_offerings.schedules.store', $offering), $this->schedulePayload($instructor, $group, $activityType, $room))
            ->assertRedirect(route('maker.course_offerings.schedules.index', $offering))
            ->assertSessionHasNoErrors();

        $schedule = Schedule::firstOrFail();
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'course_offering_id' => $offering->id,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-07',
            'teaching_date' => null,
        ]);
        $this->assertDatabaseHas('schedule_instructors', [
            'schedule_id' => $schedule->id,
            'user_id' => $instructor->id,
            'is_lead' => true,
        ]);
        $this->assertDatabaseHas('schedule_student_groups', [
            'schedule_id' => $schedule->id,
            'student_group_id' => $group->id,
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
            ->assertSessionHasErrors('schedule');

        $this->assertDatabaseCount('schedules', 1);
    }

    public function test_room_overlap_blocks_save(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $otherInstructor = $this->makeUser('instructor');
        $otherGroup = StudentGroup::create(['course_offering_id' => $offering->id, 'group_code' => 'A2', 'student_count' => 10]);
        $offering->instructorPool()->attach($otherInstructor->id, ['role_in_course' => 'instructor']);
        $this->makeSchedule($offering, $activityType, $room, [$otherInstructor], [$otherGroup]);

        $this->actingAsCourseHead($head);

        $this->post(route('maker.course_offerings.schedules.store', $offering), $this->schedulePayload($instructor, $group, $activityType, $room))
            ->assertSessionHasErrors('schedule');

        $this->assertDatabaseCount('schedules', 1);
    }

    public function test_student_group_overlap_blocks_save(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $otherInstructor = $this->makeUser('instructor');
        $otherRoom = $this->makeRoom();
        $offering->instructorPool()->attach($otherInstructor->id, ['role_in_course' => 'instructor']);
        $this->makeSchedule($offering, $activityType, $otherRoom, [$otherInstructor], [$group]);

        $this->actingAsCourseHead($head);

        $this->post(route('maker.course_offerings.schedules.store', $offering), $this->schedulePayload($instructor, $group, $activityType, $room))
            ->assertSessionHasErrors('schedule');

        $this->assertDatabaseCount('schedules', 1);
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

    public function test_partial_time_overlap_blocks_save(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group]);

        $this->actingAsCourseHead($head);

        $this->post(route('maker.course_offerings.schedules.store', $offering), $this->schedulePayload($instructor, $group, $activityType, $room, [
            'start_time' => '09:30',
            'end_time' => '10:30',
        ]))
            ->assertSessionHasErrors('schedule');

        $this->assertDatabaseCount('schedules', 1);
    }

    public function test_block_date_range_overlap_blocks_save(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group]);

        $this->actingAsCourseHead($head);

        $this->post(route('maker.course_offerings.schedules.store', $offering), $this->schedulePayload($instructor, $group, $activityType, $room, [
            'start_date' => '2026-08-07',
            'end_date' => '2026-08-10',
        ]))
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

    private function actingAsCourseHead(User $user): void
    {
        $this->actingAs($user);
        $this->withSession(['active_role' => 'course_head']);
    }

    /**
     * @return array{User, CourseOffering, User, StudentGroup, ActivityType, Room}
     */
    private function makeReadyOffering(string $phase = 'scheduling'): array
    {
        $head = $this->makeUser('course_head');
        $instructor = $this->makeUser('instructor');
        $year = $this->makeYear($phase);
        $course = $this->makeCourse($head);
        $offering = CourseOffering::create([
            'course_id' => $course->id,
            'academic_year_id' => $year->id,
            'coordinator_id' => $head->id,
            'approval_status' => 'draft',
            'total_student_count' => 30,
        ]);
        $offering->instructorPool()->attach($instructor->id, ['role_in_course' => 'instructor']);
        $group = StudentGroup::create([
            'course_offering_id' => $offering->id,
            'group_code' => 'A1',
            'student_count' => 15,
        ]);

        return [$head, $offering, $instructor, $group, $this->makeActivityType(), $this->makeRoom()];
    }

    private function makeUser(string $role): User
    {
        $number = $this->sequence++;
        $user = User::create([
            'username' => "schedule_user_{$number}",
            'name' => "Schedule User {$number}",
            'email' => "schedule_user_{$number}@example.com",
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        UserRole::create(['user_id' => $user->id, 'role' => $role, 'is_primary' => true]);
        InstructorProfile::create([
            'user_id' => $user->id,
            'title' => 'อาจารย์',
            'department_id' => $this->department()->id,
        ]);

        return $user;
    }

    private function makeYear(string $phase): AcademicYear
    {
        $number = $this->sequence++;

        return AcademicYear::create([
            'name' => "2570-{$number}",
            'semester' => 1,
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
            'phase' => $phase,
        ]);
    }

    private function makeCourse(User $head): Course
    {
        $number = $this->sequence++;

        return Course::create([
            'course_code' => "SCH{$number}",
            'curriculum_id' => $this->curriculum()->id,
            'department_id' => $this->department()->id,
            'head_instructor_id' => $head->id,
            'name_th' => "Schedule Course {$number}",
            'name_en' => "Schedule Course {$number}",
            'course_type' => 'theory_practicum',
            'default_year_level' => 2,
            'default_semester' => 1,
            'requires_practicum_rotation' => false,
            'credits' => 3,
            'lecture_hours' => 2,
            'lab_hours' => 1,
            'self_study_hours' => 3,
            'status' => 'active',
        ]);
    }

    private function makeActivityType(): ActivityType
    {
        $number = $this->sequence++;

        return ActivityType::create([
            'name' => "Lecture {$number}",
            'color_code' => '#2563eb',
            'category' => 'lecture',
        ]);
    }

    private function makeRoom(): Room
    {
        $number = $this->sequence++;

        return Room::create([
            'room_code' => "R{$number}",
            'room_name' => "Room {$number}",
            'location_type_id' => LocationType::firstOrCreate(['name' => 'ห้องเรียน'])->id,
            'status' => 'active',
        ]);
    }

    /**
     * @param  array<int, User>  $instructors
     * @param  array<int, StudentGroup>  $groups
     */
    private function makeSchedule(
        CourseOffering $offering,
        ActivityType $activityType,
        Room $room,
        array $instructors,
        array $groups,
        array $overrides = []
    ): Schedule {
        $schedule = Schedule::create(array_merge([
            'course_offering_id' => $offering->id,
            'activity_type_id' => $activityType->id,
            'room_id' => $room->id,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-07',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'topic' => 'Existing schedule',
            'status' => 'draft',
        ], $overrides));

        $schedule->instructors()->sync(collect($instructors)->mapWithKeys(fn (User $user) => [
            $user->id => ['is_lead' => false],
        ])->all());
        $schedule->studentGroups()->sync(collect($groups)->pluck('id')->all());

        return $schedule;
    }

    private function schedulePayload(
        User $instructor,
        StudentGroup $group,
        ActivityType $activityType,
        Room $room,
        array $overrides = []
    ): array {
        return array_merge([
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-07',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'activity_type_id' => $activityType->id,
            'room_id' => $room->id,
            'topic' => 'Schedule topic',
            'capacity_required' => 15,
            'sub_group_label' => null,
            'remark' => null,
            'instructor_ids' => [$instructor->id],
            'lead_instructor_id' => $instructor->id,
            'student_group_ids' => [$group->id],
        ], $overrides);
    }

    private function department(): Department
    {
        return Department::firstOrCreate(['name' => 'Schedule Department']);
    }

    private function curriculum(): Curriculum
    {
        return Curriculum::firstOrCreate(['name' => 'Schedule Curriculum'], [
            'effective_year' => 2569,
            'is_active' => true,
        ]);
    }
}
