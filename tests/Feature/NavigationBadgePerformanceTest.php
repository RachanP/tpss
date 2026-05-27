<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\AlertController;
use App\Services\NavigationBadgeService;
use App\Services\ScheduleConflictIndex;
use App\Services\ScheduleConflictReadRepository;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class NavigationBadgePerformanceTest extends TestCase
{
    public function test_sidebar_does_not_run_heavy_badge_queries_directly(): void
    {
        $sidebar = file_get_contents(resource_path('views/components/sidebar.blade.php'));

        $this->assertStringNotContainsString('Schedule::query', $sidebar);
        $this->assertStringNotContainsString('ScheduleConflictChecker', $sidebar);
        $this->assertStringNotContainsString('AlertController::getSummary', $sidebar);
    }

    public function test_alert_flush_also_clears_admin_sidebar_badge_cache(): void
    {
        Cache::put('sidebar.badges.admin', ['critical' => 99, 'warnings' => 0], 300);

        AlertController::flushCache();

        $this->assertFalse(Cache::has('sidebar.badges.admin'));
    }

    public function test_course_head_badge_cache_miss_does_not_compute_conflicts(): void
    {
        Cache::forget('sidebar.badges.course_head.123');
        $index = Mockery::mock(ScheduleConflictIndex::class);
        $index->shouldNotReceive('countForCoordinator');
        $repository = Mockery::mock(ScheduleConflictReadRepository::class);

        $service = new NavigationBadgeService($index, $repository);

        $this->assertSame(0, $service->courseHeadConflictCount(123));
    }

    public function test_course_head_badge_uses_cached_value_without_compute(): void
    {
        Cache::put('sidebar.badges.course_head.123', 7, 300);
        $index = Mockery::mock(ScheduleConflictIndex::class);
        $index->shouldNotReceive('countForCoordinator');
        $repository = Mockery::mock(ScheduleConflictReadRepository::class);

        $service = new NavigationBadgeService($index, $repository);

        $this->assertSame(7, $service->courseHeadConflictCount(123));
    }
}
