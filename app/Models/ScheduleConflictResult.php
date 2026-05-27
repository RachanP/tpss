<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleConflictResult extends Model
{
    protected $fillable = [
        'run_id',
        'academic_year_id',
        'schedule_id',
        'conflicting_schedule_id',
        'conflict_type',
        'resource_type',
        'resource_id',
        'message',
        'pair_key',
    ];

    protected $casts = [
        'schedule_id' => 'integer',
        'conflicting_schedule_id' => 'integer',
        'resource_id' => 'integer',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(ScheduleConflictRun::class, 'run_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function sourceSchedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    public function conflictingSchedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'conflicting_schedule_id');
    }

    public function scopes(): HasMany
    {
        return $this->hasMany(ScheduleConflictResultScope::class, 'result_id');
    }
}
