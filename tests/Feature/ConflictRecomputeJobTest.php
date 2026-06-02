<?php

namespace Tests\Feature;

use App\Jobs\ConflictRecomputeJob;
use App\Models\AcademicYear;
use App\Models\ActivityType;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\LocationType;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ScheduleConflictRun;
use App\Models\StudentGroup;
use App\Models\User;
use App\Models\UserRole;
use App\Services\NavigationBadgeService;
use App\Services\ScheduleConflictInvalidationService;
use App\Services\ScheduleConflictIndex;
use App\Services\ScheduleConflictReadRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ConflictRecomputeJobTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 1;

    protected function setUp(): void
    {
        parent::setUp();

        config(['conflicts.async_reads' => false]);
    }

    public function test_recompute_command_creates_pending_run_and_dispatches_job(): void
    {
        Queue::fake();
        [$year] = $this->makeConflictDataset();

        $this->artisan('conflicts:recompute', ['--academic-year' => $year->id])
            ->assertExitCode(0);

        $run = ScheduleConflictRun::query()->first();

        $this->assertNotNull($run);
        $this->assertSame('pending', $run->status);
        $this->assertSame(1, $run->generation);

        Queue::assertPushed(ConflictRecomputeJob::class, fn (ConflictRecomputeJob $job) => $job->academicYearId === $year->id
            && $job->runId === $run->id
            && $job->generation === 1);
    }

    public function test_sync_recompute_stores_results_and_visibility_scopes(): void
    {
        [$year, $head] = $this->makeConflictDataset();

        $this->artisan('conflicts:recompute', [
            '--academic-year' => $year->id,
            '--sync' => true,
        ])->assertExitCode(0);

        $run = ScheduleConflictRun::query()->firstOrFail();

        $this->assertSame('ready', $run->status);
        $this->assertGreaterThan(0, $run->result_count);
        $this->assertDatabaseCount('schedule_conflict_results', $run->result_count);
        $this->assertDatabaseHas('schedule_conflict_result_scopes', [
            'scope_type' => 'course_head_user',
            'user_id' => $head->id,
            'academic_year_id' => $year->id,
        ]);
        $this->assertDatabaseHas('schedule_conflict_result_scopes', [
            'scope_type' => 'admin_global',
            'role' => 'admin',
            'academic_year_id' => $year->id,
        ]);
        $this->assertDatabaseHas('schedule_conflict_result_scopes', [
            'scope_type' => 'executive_academic_year',
            'role' => 'executive',
            'academic_year_id' => $year->id,
        ]);
    }

    public function test_incremental_recompute_rebuilds_only_changed_conflict_cluster(): void
    {
        [$year, , $offering, $instructor, $group] = $this->makeReadyOffering();
        $activityType = $this->makeActivityType('lecture');
        $room = $this->makeRoom();

        $first = $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
            'topic' => 'Changed conflict source',
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
        ]);
        $second = $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
            'topic' => 'Changed conflict neighbor',
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
        ]);

        $otherInstructor = $this->makeUser('Other Instructor');
        $otherGroup = StudentGroup::query()->create([
            'course_offering_id' => $offering->id,
            'group_code' => 'B1',
            'student_count' => 15,
        ]);
        $otherRoom = $this->makeRoom();
        $third = $this->makeSchedule($offering, $activityType, $otherRoom, [$otherInstructor], [$otherGroup], [
            'topic' => 'Unchanged conflict source',
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
        ]);
        $fourth = $this->makeSchedule($offering, $activityType, $otherRoom, [$otherInstructor], [$otherGroup], [
            'topic' => 'Unchanged conflict neighbor',
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
        ]);

        $this->artisan('conflicts:recompute', [
            '--academic-year' => $year->id,
            '--sync' => true,
        ])->assertExitCode(0);

        $previousRun = ScheduleConflictRun::query()->firstOrFail();
        $this->assertSame(12, $previousRun->result_count);

        $first->forceFill([
            'start_time' => '10:00',
            'end_time' => '12:00',
        ])->save();

        config(['conflicts.async_reads' => true]);
        Cache::flush();
        Queue::fake();

        app(ScheduleConflictInvalidationService::class)->markScheduleDirty($first, 'pivot');

        $run = ScheduleConflictRun::query()
            ->where('status', 'pending')
            ->orderByDesc('generation')
            ->firstOrFail();

        $this->assertSame([$first->id], $run->metadata['affected_schedule_ids']);

        (new ConflictRecomputeJob($year->id, $run->id, (int) $run->generation, 'pivot'))
            ->handle(app(ScheduleConflictIndex::class), app(ScheduleConflictInvalidationService::class));

        $run->refresh();

        $this->assertSame('ready', $run->status);
        $this->assertSame(2, (int) $run->generation);
        $this->assertSame(6, $run->result_count);
        $this->assertDatabaseMissing('schedule_conflict_results', [
            'run_id' => $run->id,
            'schedule_id' => $first->id,
            'conflicting_schedule_id' => $second->id,
        ]);
        $this->assertDatabaseHas('schedule_conflict_results', [
            'run_id' => $run->id,
            'schedule_id' => $third->id,
            'conflicting_schedule_id' => $fourth->id,
        ]);
    }

    public function test_sync_recompute_preserves_team_supervision_policy_exception(): void
    {
        [$year] = $this->makeTeamSupervisionDataset();

        $this->artisan('conflicts:recompute', [
            '--academic-year' => $year->id,
            '--sync' => true,
        ])->assertExitCode(0);

        $run = ScheduleConflictRun::query()->firstOrFail();

        $this->assertSame('ready', $run->status);
        $this->assertSame(0, $run->result_count);
        $this->assertDatabaseCount('schedule_conflict_results', 0);
    }

    public function test_course_head_cannot_read_another_course_heads_stored_conflicts(): void
    {
        $year = AcademicYear::query()->create([
            'name' => '2571',
            'semester' => 1,
            'start_date' => '2027-08-01',
            'end_date' => '2027-12-31',
            'is_active' => true,
            'phase' => 'scheduling',
        ]);
        [, $firstHead] = $this->makeConflictDataset($year, 'First');
        [, $secondHead] = $this->makeConflictDataset($year, 'Second');

        $this->artisan('conflicts:recompute', [
            '--academic-year' => $year->id,
            '--sync' => true,
        ])->assertExitCode(0);

        $repository = app(ScheduleConflictReadRepository::class);

        $this->assertSame(6, $repository->getCountForUser($firstHead->id, $year->id));
        $this->assertSame(6, $repository->getCountForUser($secondHead->id, $year->id));
        $this->assertNull($repository->getCountForUser($this->makeUser('Outside')->id, $year->id));
    }

    public function test_conflict_detail_endpoint_enforces_scope_and_academic_year(): void
    {
        $year = AcademicYear::query()->create([
            'name' => '2572',
            'semester' => 1,
            'start_date' => '2028-08-01',
            'end_date' => '2028-12-31',
            'is_active' => true,
            'phase' => 'scheduling',
        ]);
        [$year, $firstHead, , $firstSchedule] = $this->makeConflictDataset($year, 'First');
        [$year, $secondHead, , $secondSchedule] = $this->makeConflictDataset($year, 'Second');
        $otherYear = AcademicYear::query()->create([
            'name' => '2576',
            'start_date' => '2029-01-01',
            'end_date' => '2029-05-31',
            'is_active' => false,
            'phase' => 'published',
        ]);

        $this->artisan('conflicts:recompute', [
            '--academic-year' => $year->id,
            '--sync' => true,
        ])->assertExitCode(0);

        $this->actingAs($firstHead)
            ->withSession(['active_role' => 'course_head'])
            ->getJson(route('schedule_conflicts.details', [
                $firstSchedule,
                'academic_year_id' => $year->id,
            ]))
            ->assertOk()
            ->assertJsonPath('total', 3);

        $this->actingAs($firstHead)
            ->withSession(['active_role' => 'course_head'])
            ->getJson(route('schedule_conflicts.details', [
                $secondSchedule,
                'academic_year_id' => $year->id,
            ]))
            ->assertForbidden();

        $admin = $this->makeUser('Admin');
        UserRole::query()->create(['user_id' => $admin->id, 'role' => 'admin', 'is_primary' => true]);

        $this->actingAs($admin)
            ->withSession(['active_role' => 'admin'])
            ->getJson(route('schedule_conflicts.details', [
                $secondSchedule,
                'academic_year_id' => $year->id,
            ]))
            ->assertOk();

        $this->actingAs($firstHead)
            ->withSession(['active_role' => 'course_head'])
            ->getJson(route('schedule_conflicts.details', [
                $firstSchedule,
                'academic_year_id' => $otherYear->id,
            ]))
            ->assertNotFound();
    }

    public function test_schedule_summary_page_limits_rows_and_preview_conflicts(): void
    {
        [$year, $head, $offering, $instructor, $group] = $this->makeReadyOffering();
        $activityType = $this->makeActivityType('lecture');
        $room = $this->makeRoom();

        for ($i = 0; $i < 30; $i++) {
            $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
                'topic' => "Conflict row {$i}",
            ]);
        }

        $this->artisan('conflicts:recompute', [
            '--academic-year' => $year->id,
            '--sync' => true,
        ])->assertExitCode(0);

        $page = app(ScheduleConflictReadRepository::class)
            ->getScheduleSummaryPageForUser($head->id, $year->id, 1);

        $this->assertCount(25, $page->items());
        $firstSummary = $page->getCollection()->first();
        // preview_conflicts \u0e21\u0e35\u0e44\u0e14\u0e49\u0e2b\u0e25\u0e32\u0e22 records \u0e15\u0e48\u0e2d 1 \u0e04\u0e39\u0e48 (หลาย conflict types) — \u0e15\u0e23\u0e27\u0e08\u0e1c\u0e48\u0e32\u0e19 distinct schedule count \u0e41\u0e17\u0e19
        $this->assertLessThanOrEqual(3, $firstSummary['preview_distinct_count']);
        $this->assertTrue($firstSummary['has_more']);
    }

    public function test_failed_new_run_does_not_hide_latest_ready_results(): void
    {
        [$year, $head] = $this->makeConflictDataset();

        $this->artisan('conflicts:recompute', [
            '--academic-year' => $year->id,
            '--sync' => true,
        ])->assertExitCode(0);

        ScheduleConflictRun::query()->create([
            'academic_year_id' => $year->id,
            'status' => 'failed',
            'generation' => 2,
            'source' => 'manual',
            'requested_at' => now(),
            'started_at' => now(),
            'failed_at' => now(),
            'error_message' => 'bulk insert failed',
            'result_count' => 0,
        ]);

        $repository = app(ScheduleConflictReadRepository::class);

        $this->assertSame('failed', $repository->getStatusForUser($head->id, $year->id)['status']);
        $this->assertSame(6, $repository->getCountForUser($head->id, $year->id));
        $this->assertGreaterThan(0, $repository->getScheduleSummaryPageForUser($head->id, $year->id, 1)->count());
    }

    public function test_async_sidebar_badge_keeps_pending_state_without_visible_label(): void
    {
        config(['conflicts.async_reads' => true]);
        [$year, $head] = $this->makeReadyOffering();
        ScheduleConflictRun::query()->create([
            'academic_year_id' => $year->id,
            'status' => 'pending',
            'generation' => 1,
            'source' => 'manual',
            'requested_at' => now(),
            'result_count' => 0,
        ]);

        $badge = app(NavigationBadgeService::class)->forRole('course_head', $head->id);

        $this->assertNull($badge['maker_conflict_count']);
        $this->assertSame('pending', $badge['maker_conflict_status']);
        $this->assertTrue($badge['maker_conflict_pending']);
        $this->assertNull($badge['maker_conflict_label']);
    }

    public function test_conflict_badge_status_endpoint_returns_ready_json_for_course_head(): void
    {
        Cache::flush();
        [$year, $head] = $this->makeConflictDataset();
        $this->attachRole($head, 'course_head');

        $this->artisan('conflicts:recompute', [
            '--academic-year' => $year->id,
            '--sync' => true,
        ])->assertExitCode(0);
        config(['conflicts.async_reads' => true]);
        Cache::flush();

        $response = $this->actingAs($head)
            ->withSession(['active_role' => 'course_head'])
            ->getJson(route('maker.conflict_badge_status'))
            ->assertOk()
            ->assertJsonStructure(['status', 'count', 'pending', 'label', 'poll'])
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('pending', false)
            ->assertJsonPath('poll', false);

        $this->assertGreaterThan(0, $response->json('count'));
        $this->assertSame((string) $response->json('count'), $response->json('label'));
    }

    public function test_conflict_badge_status_endpoint_polls_when_results_are_missing(): void
    {
        config(['conflicts.async_reads' => true]);
        Cache::flush();
        Queue::fake();
        [, $head] = $this->makeReadyOffering();
        $this->attachRole($head, 'course_head');

        $this->actingAs($head)
            ->withSession(['active_role' => 'course_head'])
            ->getJson(route('maker.conflict_badge_status'))
            ->assertOk()
            ->assertJsonPath('status', 'missing')
            ->assertJsonPath('count', null)
            ->assertJsonPath('pending', true)
            ->assertJsonPath('label', null)
            ->assertJsonPath('poll', true);

        Queue::assertPushed(ConflictRecomputeJob::class);
    }

    public function test_conflict_badge_status_endpoint_is_scoped_to_the_current_course_head(): void
    {
        Cache::flush();
        [$year, $firstHead] = $this->makeConflictDataset();
        [, $secondHead] = $this->makeReadyOffering($year, 'No conflicts');
        $this->attachRole($firstHead, 'course_head');
        $this->attachRole($secondHead, 'course_head');

        $this->artisan('conflicts:recompute', [
            '--academic-year' => $year->id,
            '--sync' => true,
        ])->assertExitCode(0);
        config(['conflicts.async_reads' => true]);
        Cache::flush();

        $firstResponse = $this->actingAs($firstHead)
            ->withSession(['active_role' => 'course_head'])
            ->getJson(route('maker.conflict_badge_status'))
            ->assertOk()
            ->assertJsonPath('status', 'ready');

        $secondResponse = $this->actingAs($secondHead)
            ->withSession(['active_role' => 'course_head'])
            ->getJson(route('maker.conflict_badge_status'))
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('count', 0)
            ->assertJsonPath('label', null);

        $this->assertGreaterThan(0, $firstResponse->json('count'));
        $this->assertSame(0, $secondResponse->json('count'));
    }

    public function test_conflict_badge_status_endpoint_rejects_non_course_heads(): void
    {
        $staff = $this->makeUser('Staff');
        $this->attachRole($staff, 'staff');

        $this->actingAs($staff)
            ->withSession(['active_role' => 'staff'])
            ->getJson(route('maker.conflict_badge_status'))
            ->assertForbidden();
    }

    public function test_conflict_badge_status_endpoint_is_rate_limited(): void
    {
        Cache::flush();
        [$year, $head] = $this->makeConflictDataset();
        $this->attachRole($head, 'course_head');

        $this->artisan('conflicts:recompute', [
            '--academic-year' => $year->id,
            '--sync' => true,
        ])->assertExitCode(0);
        config(['conflicts.async_reads' => true]);
        Cache::flush();

        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($head)
                ->withSession(['active_role' => 'course_head'])
                ->getJson(route('maker.conflict_badge_status'))
                ->assertOk();
        }

        $this->actingAs($head)
            ->withSession(['active_role' => 'course_head'])
            ->getJson(route('maker.conflict_badge_status'))
            ->assertStatus(429);
    }

    public function test_conflict_badge_status_endpoint_is_idle_without_scheduling_year(): void
    {
        config(['conflicts.async_reads' => true]);
        Cache::flush();
        $year = AcademicYear::query()->create([
            'name' => '2571',
            'semester' => 1,
            'start_date' => '2027-08-01',
            'end_date' => '2027-12-31',
            'is_active' => true,
            'phase' => 'preparation',
        ]);
        [, $head] = $this->makeReadyOffering($year, 'Preparation');
        $this->attachRole($head, 'course_head');
        Cache::put("sidebar.badges.course_head.async.{$head->id}", [
            'maker_conflict_count' => null,
            'maker_conflict_status' => 'pending',
            'maker_conflict_pending' => true,
            'maker_conflict_academic_year_id' => $year->id,
            'maker_conflict_label' => 'กำลังตรวจสอบ',
        ], 300);

        $this->actingAs($head)
            ->withSession(['active_role' => 'course_head'])
            ->getJson(route('maker.conflict_badge_status'))
            ->assertOk()
            ->assertJsonPath('status', 'idle')
            ->assertJsonPath('count', null)
            ->assertJsonPath('pending', false)
            ->assertJsonPath('label', null)
            ->assertJsonPath('poll', false);
    }

    // หมายเหตุ: เดิมมี test_conflict_alert_page_hides_stale_ready_counts_while_recompute_is_pending
    // ถูกลบเพราะหน้า /maker/schedule-conflicts ถูกแทนด้วยหน้า /maker/alerts (V2) — async recompute/badge
    // ยังทดสอบผ่าน test อื่นในไฟล์นี้ + sidebar badge polling

    public function test_async_sidebar_badge_starts_recompute_when_results_are_missing(): void
    {
        config(['conflicts.async_reads' => true]);
        Cache::flush();
        Queue::fake();
        [$year, $head] = $this->makeReadyOffering();

        $badge = app(NavigationBadgeService::class)->forRole('course_head', $head->id);

        $this->assertNull($badge['maker_conflict_count']);
        $this->assertSame('missing', $badge['maker_conflict_status']);
        $this->assertTrue($badge['maker_conflict_pending']);
        $this->assertNull($badge['maker_conflict_label']);
        $this->assertDatabaseHas('schedule_conflict_runs', [
            'academic_year_id' => $year->id,
            'status' => 'pending',
            'source' => 'manual',
        ]);
        Queue::assertPushed(ConflictRecomputeJob::class, fn (ConflictRecomputeJob $job) => $job->academicYearId === $year->id);
    }

    public function test_async_sidebar_badge_shows_failed_label_instead_of_zero(): void
    {
        config(['conflicts.async_reads' => true]);
        [$year, $head] = $this->makeReadyOffering();
        ScheduleConflictRun::query()->create([
            'academic_year_id' => $year->id,
            'status' => 'failed',
            'generation' => 1,
            'source' => 'manual',
            'requested_at' => now(),
            'failed_at' => now(),
            'error_message' => 'Queue failed',
            'result_count' => 0,
        ]);

        $badge = app(NavigationBadgeService::class)->forRole('course_head', $head->id);

        $this->assertNull($badge['maker_conflict_count']);
        $this->assertSame('failed', $badge['maker_conflict_status']);
        $this->assertTrue($badge['maker_conflict_pending']);
        $this->assertSame('ตรวจสอบไม่สำเร็จ', $badge['maker_conflict_label']);
    }

    public function test_async_sidebar_badge_shows_ready_count_after_recompute(): void
    {
        config(['conflicts.async_reads' => false]);
        [$year, $head] = $this->makeConflictDataset();

        $this->artisan('conflicts:recompute', [
            '--academic-year' => $year->id,
            '--sync' => true,
        ])->assertExitCode(0);

        config(['conflicts.async_reads' => true]);
        $badge = app(NavigationBadgeService::class)->forRole('course_head', $head->id);

        $this->assertSame('ready', $badge['maker_conflict_status']);
        $this->assertFalse($badge['maker_conflict_pending']);
        $this->assertIsInt($badge['maker_conflict_count']);
        $this->assertGreaterThan(0, $badge['maker_conflict_count']);
        $this->assertSame((string) $badge['maker_conflict_count'], $badge['maker_conflict_label']);
    }

    public function test_sync_feature_flag_false_uses_existing_badge_cache_path(): void
    {
        config(['conflicts.async_reads' => false]);
        [$year, $head] = $this->makeReadyOffering();
        ScheduleConflictRun::query()->create([
            'academic_year_id' => $year->id,
            'status' => 'pending',
            'generation' => 1,
            'source' => 'manual',
            'requested_at' => now(),
            'result_count' => 0,
        ]);
        Cache::put("sidebar.badges.course_head.{$head->id}", 4, 300);

        $badge = app(NavigationBadgeService::class)->forRole('course_head', $head->id);

        $this->assertSame(4, $badge['maker_conflict_count']);
        $this->assertSame('ready', $badge['maker_conflict_status']);
        $this->assertFalse($badge['maker_conflict_pending']);
        $this->assertNull($badge['maker_conflict_label']);
    }

    public function test_schedule_observer_dispatches_debounced_recompute_when_async_reads_are_enabled(): void
    {
        config(['conflicts.async_reads' => true]);
        Cache::flush();
        Queue::fake();

        [$year, , $offering, $instructor, $group] = $this->makeReadyOffering();
        $this->makeSchedule($offering, $this->makeActivityType('lecture'), $this->makeRoom(), [$instructor], [$group]);

        $this->assertDatabaseHas('schedule_conflict_runs', [
            'academic_year_id' => $year->id,
            'status' => 'pending',
            'source' => 'observer',
        ]);
        Queue::assertPushed(ConflictRecomputeJob::class, fn (ConflictRecomputeJob $job) => $job->academicYearId === $year->id);
    }

    public function test_bulk_import_invalidation_dispatches_one_recompute_job_per_academic_year(): void
    {
        config(['conflicts.async_reads' => false]);
        [$firstYear, , $firstOffering, $firstInstructor, $firstGroup] = $this->makeReadyOffering();
        $secondYear = AcademicYear::query()->create([
            'name' => '2577',
            'start_date' => '2027-01-01',
            'end_date' => '2027-05-31',
            'is_active' => false,
            'phase' => 'scheduling',
        ]);
        [$secondYear, , $secondOffering, $secondInstructor, $secondGroup] = $this->makeReadyOffering($secondYear, 'Second');
        $firstSchedule = $this->makeSchedule(
            $firstOffering,
            $this->makeActivityType('lecture'),
            $this->makeRoom(),
            [$firstInstructor],
            [$firstGroup]
        );
        $secondSchedule = $this->makeSchedule(
            $secondOffering,
            $this->makeActivityType('lecture'),
            $this->makeRoom(),
            [$secondInstructor],
            [$secondGroup]
        );

        config(['conflicts.async_reads' => true]);
        Cache::flush();
        Queue::fake();

        app(ScheduleConflictInvalidationService::class)->markImportedSchedulesDirty([
            $firstSchedule->id,
            $firstSchedule,
            $secondSchedule,
        ]);

        $this->assertDatabaseCount('schedule_conflict_runs', 2);
        $this->assertDatabaseHas('schedule_conflict_runs', [
            'academic_year_id' => $firstYear->id,
            'status' => 'pending',
            'source' => 'bulk_import',
        ]);
        $this->assertDatabaseHas('schedule_conflict_runs', [
            'academic_year_id' => $secondYear->id,
            'status' => 'pending',
            'source' => 'bulk_import',
        ]);

        Queue::assertPushed(ConflictRecomputeJob::class, 2);
        Queue::assertPushed(ConflictRecomputeJob::class, fn (ConflictRecomputeJob $job) => $job->academicYearId === $firstYear->id);
        Queue::assertPushed(ConflictRecomputeJob::class, fn (ConflictRecomputeJob $job) => $job->academicYearId === $secondYear->id);
    }

    public function test_recompute_command_active_or_scheduling_queues_only_relevant_academic_years(): void
    {
        Queue::fake();

        $activeYear = AcademicYear::query()->create([
            'name' => '2573',
            'semester' => 1,
            'start_date' => '2029-08-01',
            'end_date' => '2029-12-31',
            'is_active' => true,
            'phase' => 'published',
        ]);
        $schedulingYear = AcademicYear::query()->create([
            'name' => '2578',
            'start_date' => '2030-01-01',
            'end_date' => '2030-05-31',
            'is_active' => false,
            'phase' => 'scheduling',
        ]);
        $inactiveYear = AcademicYear::query()->create([
            'name' => '2574',
            'semester' => 1,
            'start_date' => '2030-08-01',
            'end_date' => '2030-12-31',
            'is_active' => false,
            'phase' => 'published',
        ]);

        $this->artisan('conflicts:recompute', [
            '--active-or-scheduling' => true,
            '--queue' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseCount('schedule_conflict_runs', 2);
        $this->assertDatabaseHas('schedule_conflict_runs', [
            'academic_year_id' => $activeYear->id,
            'source' => 'scheduled',
        ]);
        $this->assertDatabaseHas('schedule_conflict_runs', [
            'academic_year_id' => $schedulingYear->id,
            'source' => 'scheduled',
        ]);
        $this->assertDatabaseMissing('schedule_conflict_runs', [
            'academic_year_id' => $inactiveYear->id,
        ]);

        Queue::assertPushed(ConflictRecomputeJob::class, 2);
    }

    public function test_admin_dashboard_hides_conflict_summary_for_phase_one(): void
    {
        config(['conflicts.async_reads' => true]);

        $admin = $this->makeUser('Admin');
        UserRole::query()->create(['user_id' => $admin->id, 'role' => 'admin', 'is_primary' => true]);

        $this->actingAs($admin)->withSession(['active_role' => 'admin'])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('data-testid="dashboard-conflict-summary"', false);
    }

    public function test_executive_dashboard_widget_reads_stored_summary_when_async_reads_enabled(): void
    {
        config(['conflicts.async_reads' => true]);
        $year = AcademicYear::query()->create([
            'name' => '2575',
            'semester' => 1,
            'start_date' => '2031-08-01',
            'end_date' => '2031-12-31',
            'is_active' => true,
            'phase' => 'scheduling',
        ]);
        ScheduleConflictRun::query()->create([
            'academic_year_id' => $year->id,
            'status' => 'ready',
            'generation' => 1,
            'source' => 'scheduled',
            'requested_at' => now(),
            'started_at' => now(),
            'finished_at' => now(),
            'result_count' => 0,
        ]);

        $executive = $this->makeUser('Executive');
        UserRole::query()->create(['user_id' => $executive->id, 'role' => 'executive', 'is_primary' => true]);
        $this->actingAs($executive)->withSession(['active_role' => 'executive'])
            ->get(route('approver.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="dashboard-conflict-summary"', false)
            ->assertSee('ready');
    }

    /**
     * @return array{AcademicYear, User, CourseOffering, Schedule, Schedule}
     */
    private function makeConflictDataset(?AcademicYear $year = null, string $label = ''): array
    {
        [$year, $head, $offering, $instructor, $group] = $this->makeReadyOffering($year, $label);
        $activityType = $this->makeActivityType('lecture');
        $room = $this->makeRoom();

        $first = $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group]);
        $second = $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
            'topic' => 'Overlapping schedule',
        ]);

        return [$year, $head, $offering, $first, $second];
    }

    /**
     * @return array{AcademicYear, User}
     */
    private function makeTeamSupervisionDataset(): array
    {
        [$year, $head, $offering, $instructor, $firstGroup] = $this->makeReadyOffering();
        $secondGroup = StudentGroup::query()->create([
            'course_offering_id' => $offering->id,
            'group_code' => 'A2',
            'student_count' => 15,
        ]);
        $activityType = $this->makeActivityType('practicum');

        $this->makeSchedule($offering, $activityType, $this->makeRoom(), [$instructor], [$firstGroup]);
        $this->makeSchedule($offering, $activityType, $this->makeRoom(), [$instructor], [$secondGroup], [
            'topic' => 'Team supervision counterpart',
        ]);

        return [$year, $head];
    }

    /**
     * @return array{AcademicYear, User, CourseOffering, User, StudentGroup}
     */
    private function makeReadyOffering(?AcademicYear $year = null, string $label = ''): array
    {
        $year ??= AcademicYear::query()->create([
            'name' => '2570',
            'semester' => 1,
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
            'phase' => 'scheduling',
        ]);
        $head = $this->makeUser(trim("Course Head {$label}"));
        $instructor = $this->makeUser(trim("Instructor {$label}"));
        $course = Course::query()->create([
            'course_code' => 'NUR' . $this->sequence++,
            'curriculum_id' => Curriculum::query()->create([
                'name' => 'Curriculum ' . $this->sequence++,
                'effective_year' => 2569,
                'is_active' => true,
            ])->id,
            'department_id' => Department::query()->create(['name' => 'Department ' . $this->sequence++])->id,
            'head_instructor_id' => $head->id,
            'name_th' => 'Nursing Course',
            'name_en' => 'Nursing Course',
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
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'academic_year_id' => $year->id,
            'coordinator_id' => $head->id,
            'approval_status' => 'draft',
            'total_student_count' => 30,
        ]);
        $group = StudentGroup::query()->create([
            'course_offering_id' => $offering->id,
            'group_code' => 'A1',
            'student_count' => 15,
        ]);

        return [$year, $head, $offering, $instructor, $group];
    }

    private function makeUser(string $name): User
    {
        $number = $this->sequence++;

        return User::query()->create([
            'username' => "conflict_user_{$number}",
            'name' => "{$name} {$number}",
            'email' => "conflict_user_{$number}@example.com",
            'password' => 'password',
            'is_active' => true,
        ]);
    }

    private function attachRole(User $user, string $role): void
    {
        UserRole::query()->create([
            'user_id' => $user->id,
            'role' => $role,
            'is_primary' => true,
        ]);
    }

    private function makeActivityType(string $category): ActivityType
    {
        return ActivityType::query()->create([
            'name' => ucfirst($category) . ' ' . $this->sequence++,
            'color_code' => '#2563eb',
            'category' => $category,
        ]);
    }

    private function makeRoom(): Room
    {
        $number = $this->sequence++;

        return Room::query()->create([
            'room_code' => "R{$number}",
            'room_name' => "Room {$number}",
            'location_type_id' => LocationType::query()->firstOrCreate(['name' => 'Lecture'])->id,
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
        $schedule = Schedule::query()->create(array_merge([
            'course_offering_id' => $offering->id,
            'activity_type_id' => $activityType->id,
            'room_id' => $room->id,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'topic' => 'Schedule item',
            'status' => 'draft',
        ], $overrides));

        $schedule->instructors()->sync(collect($instructors)->mapWithKeys(fn (User $user) => [
            $user->id => ['is_lead' => false],
        ])->all());
        $schedule->studentGroups()->sync(collect($groups)->pluck('id')->all());

        return $schedule;
    }
}
