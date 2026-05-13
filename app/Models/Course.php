<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Course extends Model
{
    protected $fillable = [
        'course_code',
        'curriculum_id',
        'department_id',
        'head_instructor_id',
        'assigned_staff_id',
        'name_th',
        'name_en',
        'course_type',
        'academic_level',
        'default_year_level',
        'default_semester',
        'requires_practicum_rotation',
        'credits',
        'lecture_hours',
        'lab_hours',
        'self_study_hours',
        'capacity',
        'color_code',
        'status'
    ];

    protected $casts = [
        'requires_practicum_rotation' => 'boolean',
        'credits' => 'integer',
        'lecture_hours' => 'integer',
        'lab_hours' => 'integer',
        'self_study_hours' => 'integer',
        'capacity' => 'integer',
        'default_year_level' => 'integer',
        'default_semester' => 'integer',
    ];

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function headInstructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_instructor_id');
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }
}
