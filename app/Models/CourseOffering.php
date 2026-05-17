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
            ->withPivot('role_in_course', 'course_role_id');
    }

    public function attachCoordinator(): void
    {
        if (!$this->coordinator_id) return;
        if ($this->instructorPool()->where('users.id', $this->coordinator_id)->exists()) return;

        $coordinatorRole = \App\Models\CourseRole::where('name_th', 'หัวหน้าวิชา')->first();
        $this->instructorPool()->attach($this->coordinator_id, [
            'role_in_course' => 'coordinator',
            'course_role_id' => $coordinatorRole?->id,
        ]);
    }

    public function copyInstructorPoolFromCourse(): void
    {
        $course = $this->course ?? $this->course()->first();
        if (!$course) return;

        $sourcePool = $course->instructors()->get();
        $existing = $this->instructorPool()->pluck('users.id')->all();

        $payload = [];
        foreach ($sourcePool as $instructor) {
            if (in_array($instructor->id, $existing, true)) continue;
            $payload[$instructor->id] = [
                'role_in_course' => 'instructor',
                'course_role_id' => $instructor->pivot->course_role_id,
            ];
        }

        if (!empty($payload)) {
            $this->instructorPool()->attach($payload);
        }
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }
}
