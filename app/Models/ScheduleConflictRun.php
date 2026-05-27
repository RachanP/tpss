<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleConflictRun extends Model
{
    protected $fillable = [
        'academic_year_id',
        'status',
        'generation',
        'source',
        'requested_at',
        'started_at',
        'finished_at',
        'failed_at',
        'error_message',
        'result_count',
        'metadata',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'failed_at' => 'datetime',
        'result_count' => 'integer',
        'metadata' => 'array',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(ScheduleConflictResult::class, 'run_id');
    }

    public function scopes(): HasMany
    {
        return $this->hasMany(ScheduleConflictResultScope::class, 'run_id');
    }
}
