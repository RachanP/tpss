<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class StudentGroup extends Model
{
    protected $fillable = ['group_code', 'course_offering_id', 'cohort_group_id', 'student_count', 'color_code'];

    protected $casts = [
        'course_offering_id' => 'integer',
        'cohort_group_id' => 'integer',
        'student_count' => 'integer',
    ];

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function cohortGroup(): BelongsTo
    {
        return $this->belongsTo(StudentCohort::class, 'cohort_group_id');
    }

    public function schedules(): BelongsToMany
    {
        return $this->belongsToMany(Schedule::class, 'schedule_student_groups');
    }
}
