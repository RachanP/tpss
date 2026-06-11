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
 * M3/M4 — หน้าแจ้งเตือน + สร้าง slot + validation + gate (capacity/department) + filter + check_conflicts
 */
class ScheduleStoreTest extends ScheduleTestCase
{
    public function test_maker_alerts_page_shows_warnings_and_cross_course_conflict(): void
    {
        config(['conflicts.async_reads' => false]);
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        [, $otherOffering, , $otherGroup, $otherActivityType, $otherRoom] = $this->makeReadyOffering();
        $otherOffering->instructorPool()->attach($instructor->id, ['role_in_course' => 'instructor']);
        $this->makeSchedule($otherOffering, $otherActivityType, $otherRoom, [$instructor], [$otherGroup]);
        // slot ของ head: instructor ชนข้ามวิชา + ไม่ระบุห้อง (ข้อมูลไม่ครบ)
        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], ['room_id' => null]);

        $this->actingAsCourseHead($head);

        $this->get(route('maker.alerts.index'))
            ->assertOk()
            ->assertSee('การแจ้งเตือน')
            ->assertSee('การชนข้ามวิชา')   // 🔴 conflict card
            ->assertSee('ข้อมูลไม่ครบ');    // 🟡 warning (ขาดห้อง)
    }

    public function test_maker_alerts_page_shows_count_card_even_when_no_warnings(): void
    {
        config(['conflicts.async_reads' => false]);
        [$head] = $this->makeReadyOffering(); // มี offering แต่ยังไม่มี slot → 0 แจ้งเตือน

        $this->actingAsCourseHead($head);

        $this->get(route('maker.alerts.index'))
            ->assertOk()
            ->assertSee('รายการแจ้งเตือนทั้งหมด')   // card นับยังแสดง
            ->assertSee('ไม่พบปัญหาที่ต้องแก้ไข');     // สถานะเขียว (0)
    }

    public function test_maker_alerts_page_handles_warning_only_schedules_without_conflicts(): void
    {
        config(['conflicts.async_reads' => false]);
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], ['room_id' => null]);

        $this->actingAsCourseHead($head);

        $this->get(route('maker.alerts.index'))
            ->assertOk()
            ->assertSee('ข้อมูลไม่ครบ')
            ->assertDontSee('Call to a member function getKey() on array');
    }

    public function test_maker_alert_groups_start_collapsed_and_paginate_after_ten_items(): void
    {
        config(['conflicts.async_reads' => false]);
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();

        for ($i = 0; $i < 12; $i++) {
            $date = '2026-08-' . str_pad((string) (3 + $i), 2, '0', STR_PAD_LEFT);
            $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
                'room_id' => null,
                'start_date' => $date,
                'end_date' => $date,
                'topic' => "Incomplete {$i}",
            ]);
        }

        $this->actingAsCourseHead($head);

        $this->get(route('maker.alerts.index'))
            ->assertOk()
            ->assertSee('data-testid="alert-group-incomplete"', false)
            ->assertSee('data-alert-initial-collapsed="true"', false)
            ->assertSee('data-alert-page-size="10"', false)
            ->assertSee('data-testid="alert-pagination-incomplete"', false)
            ->assertSee('tpssAlertGroup(12)', false);
    }

    public function test_conflict_alert_page_lists_owned_schedule_conflicts_with_edit_links(): void
    {
        config(['conflicts.async_reads' => false]);
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        [, $otherOffering, , $otherGroup, $otherActivityType, $otherRoom] = $this->makeReadyOffering();
        $otherOffering->instructorPool()->attach($instructor->id, ['role_in_course' => 'instructor']);
        $this->makeSchedule($otherOffering, $otherActivityType, $otherRoom, [$instructor], [$otherGroup]);
        $schedule = $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group]);

        $this->actingAsCourseHead($head);

        $this->get(route('maker.course_offerings.schedules.index', [
            $offering,
            'edit_schedule_id' => $schedule->id,
            'focus_schedule_id' => $schedule->id,
            'from_conflict' => 1,
            'date' => '2026-08-03',
            'period' => 'day',
        ]))
            ->assertOk()
            ->assertSee('data-testid="schedule-edit-conflict-focus"', false)
            ->assertSee('วันและเวลามีข้อมูลซ้อนกับรายการอื่น')
            ->assertSee(route('maker.alerts.index'), false)
            ->assertDontSee('พบข้อมูลซ้อนกับรายการอื่น แก้ไขช่องที่ไฮไลต์ก่อนส่งอนุมัติ')
            ->assertSee('data-schedule-id="' . $schedule->id . '"', false);

        $fragment = $this->getJson(route('maker.course_offerings.schedules.week_fragment', [
            $offering,
            'week_start' => '2026-08-03',
        ]));

        $fragment->assertOk();
        $this->assertStringContainsString('data-testid="schedule-edit-conflict-focus"', $fragment->json('modal_html'));
        $this->assertStringContainsString('modal-field-has-conflict', $fragment->json('modal_html'));
    }

    public function test_course_head_sidebar_hides_checking_badge_during_preparation_phase(): void
    {
        config(['conflicts.async_reads' => true]);
        Cache::flush();
        [$head] = $this->makeReadyOffering('preparation');

        Cache::put("sidebar.badges.course_head.async.{$head->id}", [
            'maker_conflict_count' => null,
            'maker_conflict_status' => 'pending',
            'maker_conflict_pending' => true,
            'maker_conflict_label' => 'กำลังตรวจสอบ',
        ], 300);

        $this->actingAsCourseHead($head);

        $this->get(route('maker.schedules.index'))
            ->assertOk()
            ->assertSee('data-status="idle"', false)
            ->assertSee('data-poll="false"', false)
            ->assertSee('data-conflict-badge', false)
            ->assertDontSee('>กำลังตรวจสอบ</span>', false);
    }

    public function test_course_head_sidebar_polls_pending_conflicts_without_showing_checking_text(): void
    {
        config(['conflicts.async_reads' => true]);
        Cache::flush();
        [$head, $offering] = $this->makeReadyOffering();

        Cache::put("sidebar.badges.course_head.async.{$head->id}", [
            'maker_conflict_count' => null,
            'maker_conflict_status' => 'pending',
            'maker_conflict_pending' => true,
            'maker_conflict_label' => 'กำลังตรวจสอบ',
        ], 300);

        $this->actingAsCourseHead($head);

        $this->get(route('maker.course_offerings.schedules.index', $offering))
            ->assertOk()
            ->assertSee('data-status="pending"', false)
            ->assertSee('data-pending="true"', false)
            ->assertSee('data-poll="true"', false)
            ->assertSee('data-conflict-badge', false)
            ->assertDontSee('>กำลังตรวจสอบ</span>', false)
            ->assertDontSee('กำลังตรวจสอบรายการชน');
    }

    public function test_conflict_edit_returns_to_conflict_alerts_after_update(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $schedule = $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group]);

        $this->actingAsCourseHead($head);

        $payload = $this->schedulePayload($instructor, $group, $activityType, $room, [
            'topic' => 'Resolved from conflict alert',
            'return_to_conflicts' => '1',
        ]);

        $this->put(route('maker.course_offerings.schedules.update', [$offering, $schedule]), $payload)
            ->assertRedirect(route('maker.alerts.index'))
            ->assertSessionHasNoErrors();
    }

    public function test_schedule_update_from_alert_deep_link_returns_to_alerts_after_update(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $schedule = $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group]);

        $this->actingAsCourseHead($head);

        $payload = $this->schedulePayload($instructor, $group, $activityType, $room, [
            'topic' => 'Resolved from alert deep link',
            'return_url' => route('maker.course_offerings.schedules.index', [
                $offering,
                'edit_schedule_id' => $schedule->id,
                'week_start' => '2026-08-03',
                'return_url' => route('maker.alerts.index'),
            ]),
        ]);

        $this->put(route('maker.course_offerings.schedules.update', [$offering, $schedule]), $payload)
            ->assertRedirect(route('maker.alerts.index'))
            ->assertSessionHasNoErrors();
    }

    public function test_schedule_update_clears_deep_link_query_after_successful_update(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $schedule = $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group]);

        $this->actingAsCourseHead($head);

        $payload = $this->schedulePayload($instructor, $group, $activityType, $room, [
            'topic' => 'Updated without reopening modal',
            'return_url' => route('maker.course_offerings.schedules.index', [
                $offering,
                'edit_schedule_id' => $schedule->id,
                'focus_schedule_id' => $schedule->id,
                'week_start' => '2026-08-03',
                'modal' => 'create',
            ]),
        ]);

        $this->put(route('maker.course_offerings.schedules.update', [$offering, $schedule]), $payload)
            ->assertRedirect(route('maker.course_offerings.schedules.index', [
                $offering,
                'week_start' => '2026-08-03',
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_schedule_update_ignores_week_fragment_return_url(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $schedule = $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group]);

        $this->actingAsCourseHead($head);

        $payload = $this->schedulePayload($instructor, $group, $activityType, $room, [
            'topic' => 'Updated from lazy modal',
            'return_url' => route('maker.course_offerings.schedules.week_fragment', [
                $offering,
                'week_start' => '2026-08-03',
            ]),
        ]);

        $this->put(route('maker.course_offerings.schedules.update', [$offering, $schedule]), $payload)
            ->assertRedirect(route('maker.course_offerings.schedules.index', $offering))
            ->assertSessionHasNoErrors();
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
        [$head, $offering] = $this->makeReadyOffering();

        $this->actingAsCourseHead($head);

        $this->get(route('maker.schedules.create'))
            ->assertRedirect(route('maker.course_offerings.schedules.index', [
                $offering,
                'modal' => 'create',
            ]));
    }

    public function test_global_create_route_preserves_selected_scheduling_offering(): void
    {
        [$head, $offering] = $this->makeReadyOffering();
        [, $closedOffering] = $this->makeReadyOffering('preparation');
        $closedOffering->forceFill(['coordinator_id' => $head->id])->save();

        $this->actingAsCourseHead($head);

        $this->get(route('maker.schedules.create', ['course_offering_id' => $offering->id]))
            ->assertRedirect(route('maker.course_offerings.schedules.index', [
                $offering,
                'modal' => 'create',
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
            ->assertRedirect(route('maker.course_offerings.schedules.index', [
                $offering,
                'week_start' => '2026-08-03',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('schedules', [
            'course_offering_id' => $offering->id,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-07',
        ]);
        $this->assertDatabaseHas('schedule_instructors', ['user_id' => $instructor->id]);
        $this->assertDatabaseHas('schedule_student_groups', ['student_group_id' => $group->id]);
    }

    public function test_schedule_store_requires_explicit_start_and_end_time(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();

        $this->actingAsCourseHead($head);

        $this->post(route('maker.course_offerings.schedules.store', $offering), $this->schedulePayload($instructor, $group, $activityType, $room, [
            'start_time' => '',
            'end_time' => '',
        ]))
            ->assertSessionHasErrors(['start_time', 'end_time']);

        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_schedule_store_rejects_invalid_thai_date_without_render_exception(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();

        $this->actingAsCourseHead($head);

        $page = route('maker.course_offerings.schedules.index', $offering);

        $this->from($page)
            ->post(route('maker.course_offerings.schedules.store', $offering), $this->schedulePayload($instructor, $group, $activityType, $room, [
                'start_date' => '32/13/2569',
                'end_date' => '32/13/2569',
            ]))
            ->assertRedirect($page)
            ->assertSessionHasErrors(['start_date', 'end_date']);

        $rendered = $this->get($page);

        $rendered
            ->assertOk()
            ->assertSee('32/13/2569', false);

        $this->assertDatabaseCount('schedules', 0);
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
            ->assertRedirect(route('maker.course_offerings.schedules.index', $offering))
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

        $this->followingRedirects()
            ->get(route('maker.schedules.index', ['week_start' => 'not-a-date']))
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

    public function test_schedule_creation_rejects_dates_outside_academic_year(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();

        $this->actingAsCourseHead($head);

        $this->post(route('maker.course_offerings.schedules.store', $offering), $this->schedulePayload($instructor, $group, $activityType, $room, [
            'start_date' => '2026-07-31',
            'end_date' => '2026-08-01',
        ]))
            ->assertSessionHasErrors('schedule');

        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_schedule_update_rejects_dates_outside_academic_year(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $schedule = $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group]);

        $this->actingAsCourseHead($head);

        $this->put(route('maker.course_offerings.schedules.update', [$offering, $schedule]), $this->schedulePayload($instructor, $group, $activityType, $room, [
            'start_date' => '2026-12-31',
            'end_date' => '2027-01-01',
        ]))
            ->assertSessionHasErrors('schedule');

        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-07',
        ]);
    }

    public function test_schedule_creation_blocked_on_exam_week_and_break_period(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        // ตั้งเทอม + สัปดาห์สอบ · เทอมจบ 30 พ.ย. → ธ.ค. (ยังในปี) = ปิดภาคเรียน
        // V4: term สังกัดปฏิทินหลัก (default calendar) ของปี
        \App\Models\AcademicYear::find($offering->academic_year_id)
            ->fallbackCalendar()
            ->terms()->create([
                'sequence' => 1, 'name' => 'ภาคเรียนที่ 1',
                'start_date' => '2026-08-01', 'end_date' => '2026-11-30',
                'midterm_start' => '2026-09-21', 'midterm_end' => '2026-09-25',
            ]);

        $this->actingAsCourseHead($head);

        // สัปดาห์สอบ → บล็อก
        $this->post(route('maker.course_offerings.schedules.store', $offering),
            $this->schedulePayload($instructor, $group, $activityType, $room, ['start_date' => '2026-09-21', 'end_date' => '2026-09-25']))
            ->assertSessionHasErrors('schedule');

        // ปิดภาคเรียน (ธ.ค. ไม่มีเทอมคลุม) → บล็อก
        $this->post(route('maker.course_offerings.schedules.store', $offering),
            $this->schedulePayload($instructor, $group, $activityType, $room, ['start_date' => '2026-12-10', 'end_date' => '2026-12-10']))
            ->assertSessionHasErrors('schedule');

        $this->assertDatabaseCount('schedules', 0);

        // วันปกติในเทอม → ผ่าน + ติด term_id อัตโนมัติ
        $this->post(route('maker.course_offerings.schedules.store', $offering),
            $this->schedulePayload($instructor, $group, $activityType, $room, ['start_date' => '2026-08-03', 'end_date' => '2026-08-07']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('schedules', ['course_offering_id' => $offering->id, 'start_date' => '2026-08-03']);
        $this->assertNotNull(Schedule::where('course_offering_id', $offering->id)->value('term_id'));
    }

    public function test_schedule_rejects_student_groups_over_capacity(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $extraGroup = StudentGroup::create([
            'course_offering_id' => $offering->id,
            'group_code' => 'A2',
            'student_count' => 20,
        ]);

        $this->actingAsCourseHead($head);

        $this->post(route('maker.course_offerings.schedules.store', $offering), $this->schedulePayload($instructor, $group, $activityType, $room, [
            'capacity_required' => 30,
            'student_group_ids' => [$group->id, $extraGroup->id],
        ]))
            ->assertStatus(302)
            ->assertSessionHasErrors('capacity_required');

        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_schedule_form_allows_instructors_from_other_departments_with_warning(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $outsideInstructor = $this->makeUser('instructor');
        $outsideInstructor->instructorProfile()->update([
            'department_id' => Department::create(['name' => 'Outside Schedule Department'])->id,
        ]);
        $outsideInstructor->refresh();
        $offering->instructorPool()->attach($outsideInstructor->id, ['role_in_course' => 'instructor']);

        $this->actingAsCourseHead($head);

        $this->get(route('maker.course_offerings.schedules.index', [$offering, 'modal' => 'create']))
            ->assertOk()
            ->assertSee($instructor->name)
            ->assertSee($outsideInstructor->name)
            ->assertSee('ต่างภาค');

        $this->post(route('maker.course_offerings.schedules.store', $offering), $this->schedulePayload($outsideInstructor, $group, $activityType, $room))
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('schedule_conflict_warning');

        $this->assertDatabaseCount('schedules', 1);
    }

    public function test_schedule_page_shows_existing_schedule_instructors_from_other_departments(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $outsideInstructor = $this->makeUser('instructor');
        $outsideInstructor->instructorProfile()->update([
            'department_id' => Department::create(['name' => 'Outside Schedule Detail Department'])->id,
        ]);
        $outsideInstructor->refresh();
        $offering->instructorPool()->attach($outsideInstructor->id, ['role_in_course' => 'instructor']);
        $this->makeSchedule($offering, $activityType, $room, [$instructor, $outsideInstructor], [$group]);

        $this->actingAsCourseHead($head);

        $this->get(route('maker.course_offerings.schedules.index', $offering))
            ->assertOk()
            ->assertSee($instructor->name)
            ->assertSee($outsideInstructor->name);
    }

    public function test_check_conflicts_warns_for_instructors_from_other_departments(): void
    {
        [$head, $offering, , $group, $activityType, $room] = $this->makeReadyOffering();
        $outsideInstructor = $this->makeUser('instructor');
        $outsideInstructor->instructorProfile()->update([
            'department_id' => Department::create(['name' => 'Outside Live Warning Department'])->id,
        ]);
        $outsideInstructor->refresh();
        $offering->instructorPool()->attach($outsideInstructor->id, ['role_in_course' => 'instructor']);

        $this->actingAsCourseHead($head);

        $response = $this->postJson(route('maker.course_offerings.schedules.check_conflicts', $offering), [
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-07',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'activity_type_id' => $activityType->id,
            'room_id' => $room->id,
            'instructor_ids' => [$outsideInstructor->id],
            'lead_instructor_id' => $outsideInstructor->id,
            'student_group_ids' => [$group->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('blocking', false);

        $this->assertSame([], $response->json('fields'));
        $this->assertNotEmpty($response->json('warnings.instructor_ids'));
    }

    public function test_schedule_index_filters_by_instructor(): void
    {
        [$head, $offering, $instructorA, $group, $activityType, $room] = $this->makeReadyOffering();
        $instructorB = $this->makeUser('instructor');
        $offering->instructorPool()->attach($instructorB->id, ['role_in_course' => 'instructor']);

        $this->makeSchedule($offering, $activityType, $room, [$instructorA], [$group], ['topic' => 'TopicAlpha']);
        $this->makeSchedule($offering, $activityType, $room, [$instructorB], [$group], [
            'topic' => 'TopicBravo',
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);

        $this->actingAsCourseHead($head);

        // ไม่กรอง → เห็นทั้งสองกิจกรรม
        $this->get(route('maker.course_offerings.schedules.index', $offering))
            ->assertOk()
            ->assertSee('TopicAlpha')
            ->assertSee('TopicBravo');

        // กรองตาม instructorA → เห็นเฉพาะ slot ของ A
        $this->get(route('maker.course_offerings.schedules.index', [$offering, 'instructor_id' => $instructorA->id]))
            ->assertOk()
            ->assertSee('TopicAlpha')
            ->assertDontSee('TopicBravo');
    }

    public function test_check_conflicts_endpoint_blocks_cross_course_overlap(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        [, $otherOffering, , $otherGroup, $otherActivityType, $otherRoom] = $this->makeReadyOffering();
        $otherOffering->instructorPool()->attach($instructor->id, ['role_in_course' => 'instructor']);
        // วิชาอื่นจองผู้สอนคนเดียวกันไว้ในวัน/เวลาเดียวกัน
        $this->makeSchedule($otherOffering, $otherActivityType, $otherRoom, [$instructor], [$otherGroup]);

        $this->actingAsCourseHead($head);

        $response = $this->postJson(route('maker.course_offerings.schedules.check_conflicts', $offering), [
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-07',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'activity_type_id' => $activityType->id,
            'room_id' => $room->id,
            'instructor_ids' => [$instructor->id],
            'lead_instructor_id' => $instructor->id,
            'student_group_ids' => [$group->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('blocking', true)
            ->assertJsonStructure(['blocking', 'fields' => ['instructor_ids'], 'warnings']);
        $this->assertNotEmpty($response->json('fields.instructor_ids'));
        $this->assertSame([], $response->json('warnings'));
    }

    public function test_check_conflicts_endpoint_blocks_hard_validation_errors(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();

        $this->actingAsCourseHead($head);

        $response = $this->postJson(route('maker.course_offerings.schedules.check_conflicts', $offering), [
            'start_date' => '2026-07-31',
            'end_date' => '2026-08-01',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'activity_type_id' => $activityType->id,
            'room_id' => $room->id,
            'instructor_ids' => [$instructor->id],
            'lead_instructor_id' => $instructor->id,
            'student_group_ids' => [$group->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('blocking', true)
            ->assertJsonStructure(['blocking', 'fields' => ['start_date'], 'warnings']);
        $this->assertSame([], $response->json('warnings'));
    }

    public function test_check_conflicts_endpoint_reports_invalid_thai_date_without_carbon_exception(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();

        $this->actingAsCourseHead($head);

        $response = $this->postJson(route('maker.course_offerings.schedules.check_conflicts', $offering), [
            'start_date' => '32/13/2569',
            'end_date' => '32/13/2569',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'activity_type_id' => $activityType->id,
            'room_id' => $room->id,
            'instructor_ids' => [$instructor->id],
            'lead_instructor_id' => $instructor->id,
            'student_group_ids' => [$group->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('blocking', true)
            ->assertJsonPath('fields.start_date.0', 'วันที่เริ่มไม่ถูกต้อง กรุณากรอกวันที่ในรูปแบบ วว/ดด/พ.ศ. เช่น 21/05/2569');
    }

    public function test_check_conflicts_endpoint_returns_clear_when_no_conflict(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();

        $this->actingAsCourseHead($head);

        $this->postJson(route('maker.course_offerings.schedules.check_conflicts', $offering), [
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-07',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'activity_type_id' => $activityType->id,
            'room_id' => $room->id,
            'instructor_ids' => [$instructor->id],
            'lead_instructor_id' => $instructor->id,
            'student_group_ids' => [$group->id],
        ])->assertOk()->assertJson(['blocking' => false]);
    }

    public function test_check_conflicts_ignores_the_schedule_being_edited(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $schedule = $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        $this->actingAsCourseHead($head);

        $payload = [
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-07',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'activity_type_id' => $activityType->id,
            'room_id' => $room->id,
            'instructor_ids' => [$instructor->id],
            'lead_instructor_id' => $instructor->id,
            'student_group_ids' => [$group->id],
        ];

        // Without schedule_id this is a duplicate create in the same offering, so it must block.
        $response = $this->postJson(route('maker.course_offerings.schedules.check_conflicts', $offering), $payload);
        $response->assertOk()->assertJsonPath('blocking', true);
        $this->assertNotEmpty($response->json('fields.instructor_ids'));
        $this->assertSame([], $response->json('warnings'));

        // With schedule_id the editor ignores the current row and returns clear.
        $response = $this->postJson(route('maker.course_offerings.schedules.check_conflicts', $offering), $payload + ['schedule_id' => $schedule->id])
            ->assertOk()->assertJson(['blocking' => false]);
        $this->assertSame([], $response->json('fields'));
        $this->assertSame([], $response->json('warnings'));
    }

}
