<?php

namespace Tests\Feature;

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

class ScheduleManagementTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 1;

    protected function setUp(): void
    {
        parent::setUp();

        config(['conflicts.async_reads' => false]);
        Cache::flush();
    }

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

    private function makePracticumActivityType(): ActivityType
    {
        $number = $this->sequence++;

        return ActivityType::create([
            'name' => "Practicum {$number}",
            'color_code' => '#16a34a',
            'category' => 'practicum',
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

    private function makeCohort(string $code): StudentCohort
    {
        return StudentCohort::create([
            'curriculum_id' => $this->curriculum()->id,
            'year_level' => 2,
            'code' => $code,
            'student_count' => 80,
        ]);
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
