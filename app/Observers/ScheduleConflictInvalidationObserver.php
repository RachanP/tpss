<?php

namespace App\Observers;

use App\Models\Schedule;
use App\Services\ScheduleConflictInvalidationService;

class ScheduleConflictInvalidationObserver
{
    public bool $afterCommit = true;

    public function saved(Schedule $schedule): void
    {
        app(ScheduleConflictInvalidationService::class)->markScheduleDirty($schedule, 'observer');
    }

    public function deleted(Schedule $schedule): void
    {
        app(ScheduleConflictInvalidationService::class)->markScheduleDirty($schedule, 'observer');
    }

    public function restored(Schedule $schedule): void
    {
        app(ScheduleConflictInvalidationService::class)->markScheduleDirty($schedule, 'observer');
    }
}
