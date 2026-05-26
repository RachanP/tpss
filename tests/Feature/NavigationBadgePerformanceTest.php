<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\AlertController;
use Illuminate\Support\Facades\Cache;
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
}
