<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleTemplate extends Model
{
    protected $fillable = [
        'course_offering_id',
        'activity_type_id',
        'weekday',
        'start_time',
        'end_time',
        'start_week',
        'end_week',
        'starts_on',
        'ends_on',
        'topic',
        'capacity_required',
        'sub_group_label',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'weekday' => 'integer',
        'start_week' => 'integer',
        'end_week' => 'integer',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'capacity_required' => 'integer',
    ];

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
