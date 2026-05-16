<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseOffering extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'course_id',
        'academic_year_id',
        'coordinator_id',
        'approval_status',
        'rejection_reason',
        'total_student_count',
        'planned_lecture_hours',
        'planned_lab_hours',
        'planned_practicum_hours',
        'teaching_weeks',
        'requires_practicum_rotation',
        'practicum_note',
    ];

    protected $casts = [
        'total_student_count' => 'integer',
        'planned_lecture_hours' => 'integer',
        'planned_lab_hours' => 'integer',
        'planned_practicum_hours' => 'integer',
        'teaching_weeks' => 'integer',
        'requires_practicum_rotation' => 'boolean',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function studentGroups(): HasMany
    {
        return $this->hasMany(StudentGroup::class);
    }

    public function instructorPool(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_offering_instructors')
            ->withPivot('role_in_course');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }
}
