<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleConflictResultScope extends Model
{
    protected $fillable = [
        'run_id',
        'result_id',
        'academic_year_id',
        'scope_type',
        'user_id',
        'role',
        'course_offering_id',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'course_offering_id' => 'integer',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(ScheduleConflictRun::class, 'run_id');
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(ScheduleConflictResult::class, 'result_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
