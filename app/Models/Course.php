<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $fillable = [
        'course_code',
        'curriculum_id',
        'department_id',
        'head_instructor_id',
        'name_th',
        'name_en',
        'course_type',
        'default_year_level',
        'default_semester',
        'requires_practicum_rotation',
        'is_required',
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
        'is_required' => 'boolean',
        'credits' => 'integer',
        'lecture_hours' => 'integer',
        'lab_hours' => 'integer',
        'self_study_hours' => 'integer',
        'capacity' => 'integer',
        'default_year_level' => 'integer',
        'default_semester' => 'integer',
    ];

    protected $attributes = [
        'is_required' => true,
    ];

    public function getRouteKeyName(): string
    {
        return 'course_code';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $field ??= $this->getRouteKeyName();

        if ($field !== 'course_code') {
            return parent::resolveRouteBinding($value, $field);
        }

        $matches = $this->where($field, $value)->limit(2)->get();

        if ($matches->count() !== 1) {
            abort(404);
        }

        return $matches->first();
    }

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

    public function assignedStaff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_staff');
    }

    public function instructors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_instructors')
            ->withPivot('course_role_id');
    }

    public function courseOfferings(): HasMany
    {
        return $this->hasMany(CourseOffering::class);
    }

    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(
            Course::class,
            'course_prerequisites',
            'course_id',
            'prerequisite_course_id'
        );
    }

    public function requiredBy(): BelongsToMany
    {
        return $this->belongsToMany(
            Course::class,
            'course_prerequisites',
            'prerequisite_course_id',
            'course_id'
        );
    }
}
