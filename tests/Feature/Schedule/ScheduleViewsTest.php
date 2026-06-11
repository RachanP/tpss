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
 * M3/M8 — เข้าถึง/แสดงตาราง (list/grid/month), week-fragment, workspace, routes, sidebar
 */
class ScheduleViewsTest extends ScheduleTestCase
{
    public function test_course_head_can_access_own_offering_schedules(): void
    {
        [$head, $offering] = $this->makeReadyOffering();

        $this->actingAsCourseHead($head);

        $this->get(route('maker.course_offerings.schedules.index', $offering))
            ->assertOk()
            ->assertSee('ตารางสอน')
            ->assertSee($offering->course->course_code);
    }

    public function test_course_head_cannot_access_schedules_for_inactive_course_offering(): void
    {
        [$head, $offering] = $this->makeReadyOffering();
        $offering->course->update(['status' => 'inactive']);

        $this->actingAsCourseHead($head);

        $this->get(route('maker.course_offerings.schedules.index', $offering))
            ->assertForbidden();
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
            ->assertSee('data-testid="schedule-grid-view-co"', false)
            ->assertSee('Existing schedule')
            ->assertSee('08:00')
            ->assertSee($group->group_code)
            ->assertSee($room->room_name);
    }

    public function test_schedule_index_initial_dom_contains_only_collapsed_headers_before_lazy_week_load(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $weekOne = $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
            'topic' => 'Initial loaded week',
        ]);
        $weekTwo = $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
            'topic' => 'Lazy loaded week',
        ]);

        $this->actingAsCourseHead($head);

        $response = $this->get(route('maker.course_offerings.schedules.index', $offering));

        $response
            ->assertOk()
            ->assertSee('data-schedule-week-start="2026-08-03"', false)
            ->assertSee('data-schedule-week-start="2026-08-10"', false)
            ->assertSee('id="schedule-day-group-20260803"', false)
            ->assertSee('id="schedule-day-group-20260810"', false)
            ->assertSee('data-schedule-initial-collapsed="true"', false);

        $content = $response->getContent();

        $this->assertMatchesRegularExpression(
            "#lazyWeekFragmentUrl:\\s*'\\\\?/maker\\\\?/course-offerings\\\\?/[^']+\\\\?/schedules\\\\?/week-fragment'#",
            $content
        );
        $this->assertDoesNotMatchRegularExpression("~lazyWeekFragmentUrl:\\s*'https?://~", $content);
        $this->assertStringNotContainsString('class="co-sched-row"', $content);
        $this->assertStringNotContainsString('schedule-detail-title-' . $weekOne->id, $content);
        $this->assertStringNotContainsString('schedule-detail-title-' . $weekTwo->id, $content);
        $this->assertStringNotContainsString('action="' . route('maker.course_offerings.schedules.update', [$offering, $weekOne]) . '"', $content);
        $this->assertStringNotContainsString('action="' . route('maker.course_offerings.schedules.update', [$offering, $weekTwo]) . '"', $content);
        $this->assertStringContainsString("x-show=\"loadingDates['20260803'] || dayLoadErrors['20260803']\"", $content);
        $this->assertStringNotContainsString("x-show=\"loadingWeeks['2026-08-03'] || weekLoadErrors['2026-08-03']\"", $content);
    }

    public function test_schedule_index_deep_link_loads_target_week_rows_and_modal_initially(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $weekOne = $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
            'topic' => 'Collapsed normal week',
        ]);
        $weekTwo = $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
            'topic' => 'Focused lazy week',
        ]);

        $this->actingAsCourseHead($head);

        $response = $this->get(route('maker.course_offerings.schedules.index', [
            $offering,
            'edit_schedule_id' => $weekTwo->id,
            'focus_schedule_id' => $weekTwo->id,
        ]));

        $response
            ->assertOk()
            ->assertSee('id="schedule-day-group-20260803"', false)
            ->assertSee('id="schedule-day-group-20260810"', false)
            ->assertSee('data-schedule-initial-collapsed="false"', false)
            ->assertSee('data-schedule-id="' . $weekTwo->id . '"', false)
            ->assertSee('schedule-detail-title-' . $weekTwo->id, false)
            ->assertSee('action="' . route('maker.course_offerings.schedules.update', [$offering, $weekTwo]) . '"', false);

        $this->assertStringNotContainsString('schedule-detail-title-' . $weekOne->id, $response->getContent());
    }

    public function test_schedule_week_fragment_returns_rows_and_modals_for_requested_week(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $weekOne = $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
            'topic' => 'Initial loaded week',
        ]);
        $weekTwo = $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
            'topic' => 'Lazy loaded week',
        ]);

        $this->actingAsCourseHead($head);

        $response = $this->getJson(route('maker.course_offerings.schedules.week_fragment', [
            $offering,
            'week_start' => '2026-08-10',
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('loaded_schedule_ids.0', (string) $weekTwo->id)
            ->assertJsonFragment(['id' => (string) $weekTwo->id]);

        $decoded = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([0], array_keys($decoded['schedule_items']));
        $this->assertSame([0], array_keys($decoded['loaded_schedule_ids']));
        $this->assertSame([0], array_keys($decoded['schedule_items'][0]['groups']));
        $this->assertSame([0], array_keys($decoded['schedule_items'][0]['instructors']));

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);

        $this->assertStringContainsString('data-schedule-id="' . $weekTwo->id . '"', $response->json('html'));
        $this->assertStringContainsString('Lazy loaded week', $response->json('html'));
        $this->assertStringContainsString('data-lazy-schedule-modal="' . $weekTwo->id . '"', $response->json('modal_html'));
        $this->assertStringContainsString('schedule-detail-title-' . $weekTwo->id, $response->json('modal_html'));
        $this->assertStringContainsString('data-testid="schedule-edit-modal"', $response->json('modal_html'));
        $this->assertStringContainsString('action="' . route('maker.course_offerings.schedules.update', [$offering, $weekTwo]) . '"', $response->json('modal_html'));
        $this->assertStringContainsString(
            'name="return_url" value="' . e(route('maker.course_offerings.schedules.index', [$offering, 'week_start' => '2026-08-10'])) . '"',
            $response->json('modal_html')
        );
        $this->assertStringNotContainsString(
            'name="return_url" value="' . e(route('maker.course_offerings.schedules.week_fragment', [$offering, 'week_start' => '2026-08-10'])) . '"',
            $response->json('modal_html')
        );
        $this->assertStringNotContainsString('data-schedule-id="' . $weekOne->id . '"', $response->json('html'));
    }

    public function test_schedule_week_fragment_still_renders_when_database_cache_table_is_unavailable(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $schedule = $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
            'topic' => 'Cache fallback schedule',
        ]);

        config([
            'cache.default' => 'database',
            'cache.stores.database.table' => 'missing_cache_table_for_schedule_fragment_test',
        ]);
        Cache::forgetDriver('database');

        $this->actingAsCourseHead($head);

        $response = $this->getJson(route('maker.course_offerings.schedules.week_fragment', [
            $offering,
            'week_start' => '2026-08-03',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('data-schedule-id="' . $schedule->id . '"', $response->json('html'));
        $this->assertStringContainsString('data-schedule-modal-id="' . $schedule->id . '"', $response->json('modal_html'));
    }

    public function test_schedule_week_fragment_renders_recurring_schedule_modals(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $template = ScheduleTemplate::create([
            'course_offering_id' => $offering->id,
            'activity_type_id' => $activityType->id,
            'weekday' => 1,
            'start_time' => '08:00',
            'end_time' => '10:00',
            'start_week' => 1,
            'end_week' => 2,
            'topic' => 'Recurring lazy schedule',
        ]);
        $weekTwo = $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
            'topic' => 'Recurring lazy schedule',
            'schedule_template_id' => $template->id,
            'series_week_number' => 2,
        ]);

        $this->actingAsCourseHead($head);

        $response = $this->getJson(route('maker.course_offerings.schedules.week_fragment', [
            $offering,
            'week_start' => '2026-08-10',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('data-lazy-schedule-modal="' . $weekTwo->id . '"', $response->json('modal_html'));
        $this->assertStringContainsString('data-testid="schedule-series-edit-modal"', $response->json('modal_html'));
        $this->assertStringContainsString('series_edit_weekday_' . $weekTwo->id, $response->json('modal_html'));
        $this->assertStringContainsString('data-tp-hidden="series_edit_start_time_' . $weekTwo->id . '"', $response->json('modal_html'));
        $this->assertStringContainsString('data-tp-hidden="series_edit_end_time_' . $weekTwo->id . '"', $response->json('modal_html'));
        $this->assertStringNotContainsString('type="time"', $response->json('modal_html'));
    }

    public function test_month_view_initial_dom_includes_modals_for_visible_month_schedules(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
            'topic' => 'First visible week',
        ]);
        $weekTwo = $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
            'topic' => 'Second visible week',
        ]);

        $this->actingAsCourseHead($head);

        $response = $this->get(route('maker.course_offerings.schedules.index', [
            $offering,
            'period' => 'month',
            'date' => '2026-08-01',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('schedule-detail-title-' . $weekTwo->id, $response->getContent());
        $this->assertStringContainsString('data-schedule-modal-id="' . $weekTwo->id . '"', $response->getContent());
    }

    public function test_schedule_week_fragment_uses_offering_permissions(): void
    {
        [, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group]);
        $outsider = $this->makeUser('course_head');

        $this->actingAsCourseHead($outsider);

        $this->getJson(route('maker.course_offerings.schedules.week_fragment', [
            $offering,
            'week_start' => '2026-08-03',
        ]))->assertForbidden();
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

        $this->get(route('maker.course_offerings.schedules.index', [$offering, 'week_start' => '2026-05-18']))
            ->assertOk()
            ->assertSee('data-testid="schedule-list-view"', false)
            ->assertSee('data-testid="schedule-grid-view-co"', false)
            ->assertSee('18:23 - 21:19')
            ->assertSee('18:00')
            ->assertSee('Reflection');
    }

    public function test_schedule_index_supports_day_week_and_month_periods(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-17',
            'topic' => 'Monthly schedule item',
        ]);

        $this->actingAsCourseHead($head);

        $this->get(route('maker.course_offerings.schedules.index', [
            $offering,
            'period' => 'day',
            'date' => '2026-08-03',
        ]))
            ->assertOk()
            ->assertSee('03/08/2569');

        $this->get(route('maker.course_offerings.schedules.index', [
            $offering,
            'period' => 'week',
            'date' => '2026-08-03',
        ]))
            ->assertOk()
            ->assertSee('สัปดาห์ที่ 1');

        $this->get(route('maker.course_offerings.schedules.index', [
            $offering,
            'period' => 'month',
            'date' => '2026-08-01',
        ]))
            ->assertOk()
            ->assertSee('period=day', false)
            ->assertSee('period=week', false)
            ->assertSee('period=month', false)
            ->assertSee('data-testid="schedule-month-calendar-co"', false)
            ->assertSee('สิงหาคม 2569')
            ->assertSee('จันทร์')
            ->assertSee('อาทิตย์')
            ->assertSee('Monthly schedule item');

        $this->get(route('maker.course_offerings.schedules.index', [
            $offering,
            'period' => 'week',
            'date' => '2026-05-18',
        ]))
            ->assertOk()
            ->assertSee('นอกช่วงปีการศึกษา')
            ->assertDontSee('สัปดาห์ที่ 0');

        $this->get(route('maker.course_offerings.schedules.index', [
            $offering,
            'period' => 'month',
            'date' => '2026-05-01',
        ]))
            ->assertOk()
            ->assertSee('พฤษภาคม 2569')
            ->assertSee('นอกช่วงปีการศึกษา');
    }

    public function test_week_filter_summary_counts_every_week_a_schedule_spans(): void
    {
        // ข้อ 16: schedule ที่กินข้ามสัปดาห์ (10 วัน) ต้องถูกนับในทุกสัปดาห์ที่โผล่ในตาราง
        // ไม่ใช่แค่สัปดาห์เริ่ม (เดิม dropdown ขึ้น "1 สัปดาห์" ทั้งที่ตารางมีสัปดาห์ถัดไป)
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-12', // ครอบ 2 สัปดาห์ (Monday-aligned)
            'topic' => 'Block spanning two weeks',
        ]);

        $this->actingAsCourseHead($head);

        $this->get(route('maker.course_offerings.schedules.index', $offering))
            ->assertOk()
            ->assertSee('มีรายการสอน · 2 สัปดาห์');
    }

    public function test_weekend_activities_appear_in_day_and_month_views(): void
    {
        // ข้อ 18: กิจกรรม (รวม recurring) ที่ตกวันเสาร์/อาทิตย์ ต้องแสดงในมุมมองวัน/เดือน
        // (เดิม occurrences กรองเสาร์อาทิตย์ทิ้ง → เซลล์ว่าง/การ์ดหาย)
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
            'start_date' => '2026-08-08', // วันเสาร์
            'end_date' => '2026-08-08',
            'topic' => 'Weekend recurring item',
        ]);

        $this->actingAsCourseHead($head);

        // มุมมองวัน (วันเสาร์)
        $this->get(route('maker.course_offerings.schedules.index', [
            $offering,
            'period' => 'day',
            'date' => '2026-08-08',
        ]))
            ->assertOk()
            ->assertSee('Weekend recurring item');

        // มุมมองเดือน
        $this->get(route('maker.course_offerings.schedules.index', [
            $offering,
            'period' => 'month',
            'date' => '2026-08-01',
        ]))
            ->assertOk()
            ->assertSee('Weekend recurring item');
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
            ->assertSee('03/08/2569')
            ->assertSee('07/08/2569')
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

        $this->followingRedirects()
            ->get(route('maker.schedules.index', ['week_start' => '2026-08-03']))
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

        $this->followingRedirects()
            ->get(route('maker.schedules.index', ['week_start' => '2026-08-03']))
            ->assertOk()
            ->assertSee($offering->course->course_code)
            ->assertSee($secondOffering->course->course_code)
            ->assertDontSee($otherOffering->course->course_code);
    }

    public function test_schedule_workspace_redirects_to_first_offering_without_overview_option(): void
    {
        [$head, $offering] = $this->makeReadyOffering();

        $this->actingAsCourseHead($head);

        $this->followingRedirects()
            ->get(route('maker.schedules.index'))
            ->assertOk()
            ->assertSee($offering->course->course_code)
            ->assertDontSee('ภาพรวมตารางสอนที่รับผิดชอบ');
    }

    public function test_schedule_workspace_defaults_to_lowest_scheduling_course_code(): void
    {
        [$head, $highOffering] = $this->makeReadyOffering();
        [, $lowOffering] = $this->makeReadyOffering();
        [, $middleOffering] = $this->makeReadyOffering();

        $highOffering->course->update(['course_code' => 'NSBS 231']);
        $lowOffering->course->update(['course_code' => 'NSBS 111']);
        $middleOffering->course->update(['course_code' => 'NSBS 212']);
        $lowOffering->forceFill(['coordinator_id' => $head->id])->save();
        $middleOffering->forceFill(['coordinator_id' => $head->id])->save();

        $this->actingAsCourseHead($head);

        $this->get(route('maker.schedules.index'))
            ->assertRedirect(route('maker.course_offerings.schedules.index', ['courseOffering' => $lowOffering->id]));
    }

    public function test_schedule_workspace_query_course_offering_overrides_lowest_default(): void
    {
        [$head, $highOffering] = $this->makeReadyOffering();
        [, $lowOffering] = $this->makeReadyOffering();

        $highOffering->course->update(['course_code' => 'NSBS 231']);
        $lowOffering->course->update(['course_code' => 'NSBS 111']);
        $lowOffering->forceFill(['coordinator_id' => $head->id])->save();

        $this->actingAsCourseHead($head);

        $this->get(route('maker.schedules.index', ['course_offering_id' => $highOffering->id]))
            ->assertRedirect(route('maker.course_offerings.schedules.index', ['courseOffering' => $highOffering->id]));
    }

    public function test_schedule_workspace_create_button_opens_create_modal(): void
    {
        [$head, $offering] = $this->makeReadyOffering();

        $this->actingAsCourseHead($head);

        $this->get(route('maker.course_offerings.schedules.index', [$offering, 'week_start' => '2026-08-03']))
            ->assertOk()
            ->assertSee('data-testid="schedule-create-link"', false)
            ->assertSee('data-testid="schedule-floating-create-link"', false)
            ->assertSee('data-testid="schedule-create-modal"', false)
            ->assertSee('action="' . route('maker.course_offerings.schedules.store', $offering) . '"', false)
            ->assertSee('id="start_time" name="start_time" value=""', false)
            ->assertSee('id="end_time" name="end_time" value=""', false)
            ->assertSee('<span class="tp-val tp-val-hour">--</span>', false)
            ->assertSee('<span class="tp-val tp-val-min">--</span>', false)
            ->assertDontSee('id="start_time" name="start_time" value="08:00"', false)
            ->assertDontSee('id="end_time" name="end_time" value="09:00"', false)
            ->assertDontSee('href="' . route('maker.course_offerings.schedules.create', [$offering, 'week_start' => '2026-08-03']) . '"', false);
    }

    public function test_activity_cards_open_detail_modal_with_edit_and_delete_actions(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $schedule = $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group]);

        $this->actingAsCourseHead($head);

        $this->get(route('maker.course_offerings.schedules.index', [$offering, 'week_start' => '2026-08-03']))
            ->assertOk()
            ->assertSee('data-schedule-modal-trigger', false)
            ->assertDontSee('data-testid="schedule-detail-modal"', false)
            ->assertDontSee('data-testid="schedule-edit-modal"', false)
            ->assertDontSee('href="' . route('maker.course_offerings.schedules.edit', [$offering, $schedule]) . '"', false)
            ->assertDontSee('class="activity-actions"', false);

        $response = $this->getJson(route('maker.course_offerings.schedules.week_fragment', [
            $offering,
            'week_start' => '2026-08-03',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('data-schedule-modal-trigger', $response->json('html'));
        $this->assertStringContainsString('data-testid="schedule-detail-modal"', $response->json('modal_html'));
        $this->assertStringContainsString('data-testid="schedule-edit-modal-trigger"', $response->json('modal_html'));
        $this->assertStringContainsString('data-testid="schedule-edit-modal"', $response->json('modal_html'));
        $this->assertStringContainsString('data-testid="schedule-edit-form"', $response->json('modal_html'));
        $this->assertStringContainsString('action="' . route('maker.course_offerings.schedules.update', [$offering, $schedule]) . '"', $response->json('modal_html'));
        $this->assertStringContainsString('name="_method" value="PUT"', $response->json('modal_html'));
        $this->assertStringContainsString('value="03/08/2569"', $response->json('modal_html'));
        $this->assertStringContainsString('value="08:00"', $response->json('modal_html'));
        $this->assertStringContainsString('Existing schedule', $response->json('modal_html'));
        $this->assertStringContainsString('data-testid="schedule-delete-button"', $response->json('modal_html'));
    }

    public function test_schedule_workspace_create_modal_lists_only_scheduling_coordinated_offerings(): void
    {
        [$head, $offering, $instructor, $group] = $this->makeReadyOffering();
        [, $closedOffering] = $this->makeReadyOffering('preparation');
        [, $otherOffering] = $this->makeReadyOffering();
        $closedOffering->forceFill(['coordinator_id' => $head->id])->save();

        $this->actingAsCourseHead($head);

        $this->get(route('maker.course_offerings.schedules.index', $offering))
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
            ->assertSee('data-testid="schedule-no-offerings-empty"', false)
            ->assertDontSee('data-testid="schedule-create-link"', false)
            ->assertDontSee('data-testid="schedule-floating-create-link"', false)
            ->assertDontSee('data-testid="schedule-list-toggle"', false)
            ->assertDontSee('data-testid="schedule-grid-toggle"', false)
            ->assertDontSee('ดูข้อมูลอย่างเดียว')
            ->assertDontSee('class="schedule-title">ตารางสอน', false);
    }

    public function test_sidebar_schedule_link_points_to_workspace(): void
    {
        [$head] = $this->makeReadyOffering();

        $this->actingAsCourseHead($head);

        $this->followingRedirects()
            ->get(route('maker.schedules.index'))
            ->assertOk()
            ->assertSee('href="' . route('maker.schedules.index') . '"', false);
    }

    public function test_sidebar_conflict_link_points_to_conflict_alerts(): void
    {
        [$head] = $this->makeReadyOffering();

        $this->actingAsCourseHead($head);

        $this->followingRedirects()
            ->get(route('maker.schedules.index'))
            ->assertOk()
            ->assertSee('แจ้งเตือน')
            ->assertSee('href="' . route('maker.alerts.index') . '"', false);
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

}
