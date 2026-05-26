<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Schedule extends Model
{
    protected $fillable = [
        'course_offering_id',
        'activity_type_id',
        'room_id',
        'practicum_series_id',
        'start_date',
        'end_date',
        'teaching_date',
        'start_time',
        'end_time',
        'topic',
        'capacity_required',
        'sub_group_label',
        'status',
        'remark',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'teaching_date' => 'date',
        'capacity_required' => 'integer',
    ];

    public function getStartDateAttribute($value)
    {
        $value ??= $this->attributes['teaching_date'] ?? null;

        return $value ? $this->asDateTime($value)->startOfDay() : null;
    }

    public function getEndDateAttribute($value)
    {
        $value ??= $this->attributes['teaching_date'] ?? null;

        return $value ? $this->asDateTime($value)->startOfDay() : null;
    }

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function instructors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'schedule_instructors')
            ->withPivot('is_lead');
    }

    public function studentGroups(): BelongsToMany
    {
        return $this->belongsToMany(StudentGroup::class, 'schedule_student_groups');
    }
}
