<?php

namespace App\Observers;

use App\Services\PerformanceCacheInvalidator;
use Illuminate\Database\Eloquent\Model;

class PerformanceCacheObserver
{
    public function saved(Model $model): void
    {
        PerformanceCacheInvalidator::flushForModel($model);
    }

    public function deleted(Model $model): void
    {
        PerformanceCacheInvalidator::flushForModel($model);
    }

    public function restored(Model $model): void
    {
        PerformanceCacheInvalidator::flushForModel($model);
    }

    public function forceDeleted(Model $model): void
    {
        PerformanceCacheInvalidator::flushForModel($model);
    }
}
