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

    /**
     * เทียบ instructor pool ของ offering กับ template ของรายวิชา (live compare).
     * Coordinator (head_instructor_id) ถูก exclude จากทั้งสองฝั่ง — auto-assigned, ไม่นับเป็น choice ของ Course Head.
     *
     * @return array{added: array<int,array{user_id:int,role_id:?int}>, removed: array<int,array{user_id:int,role_id:?int}>, role_changed: array<int,array{user_id:int,template_role_id:?int,offering_role_id:?int}>}
     */
    public function instructorPoolDeviationFor(CourseOffering $offering): array
    {
        $coordinatorId = (int) ($this->head_instructor_id ?? 0);

        // ใช้ relation cache ถ้า caller eager-loaded มาแล้ว — กัน N+1 ใน controller ที่ loop หลาย offering
        $templateUsers = $this->relationLoaded('instructors')
            ? $this->getRelation('instructors')
            : $this->instructors()->get();
        $actualUsers = $offering->relationLoaded('instructorPool')
            ? $offering->getRelation('instructorPool')
            : $offering->instructorPool()->get();

        $template = $templateUsers
            ->filter(fn ($u) => (int) $u->id !== $coordinatorId)
            ->mapWithKeys(fn ($u) => [(int) $u->id => (int) ($u->pivot->course_role_id ?? 0) ?: null]);

        $actual = $actualUsers
            ->filter(fn ($u) => (int) $u->id !== $coordinatorId)
            ->mapWithKeys(fn ($u) => [(int) $u->id => (int) ($u->pivot->course_role_id ?? 0) ?: null]);

        $added = [];
        foreach ($actual as $userId => $roleId) {
            if (!$template->has($userId)) {
                $added[] = ['user_id' => (int) $userId, 'role_id' => $roleId];
            }
        }

        $removed = [];
        foreach ($template as $userId => $roleId) {
            if (!$actual->has($userId)) {
                $removed[] = ['user_id' => (int) $userId, 'role_id' => $roleId];
            }
        }

        $roleChanged = [];
        foreach ($actual as $userId => $offeringRoleId) {
            if ($template->has($userId) && $template[$userId] !== $offeringRoleId) {
                $roleChanged[] = [
                    'user_id' => (int) $userId,
                    'template_role_id' => $template[$userId],
                    'offering_role_id' => $offeringRoleId,
                ];
            }
        }

        return ['added' => $added, 'removed' => $removed, 'role_changed' => $roleChanged];
    }

    /**
     * เปรียบเทียบค่า "ระดับรอบเปิดสอน" ที่ Course Head override ได้ vs ค่าใน Master Data.
     * คืน array ของ field ที่ต่าง — ถ้าไม่ต่างคืน array ว่าง
     *
     * @return array<string, array{template: mixed, offering: mixed, note?: ?string}>
     */
    public function offeringDetailsDeviationFor(CourseOffering $offering): array
    {
        $diff = [];

        // requires_practicum_rotation — Course Head ปรับ override ได้ + แนบเหตุผลใน practicum_note
        $templateRotation = (bool) ($this->requires_practicum_rotation ?? false);
        $offeringRotation = (bool) ($offering->requires_practicum_rotation ?? false);
        if ($templateRotation !== $offeringRotation) {
            $diff['rotation'] = [
                'template' => $templateRotation,
                'offering' => $offeringRotation,
                'note'     => $offering->practicum_note,
            ];
        }

        return $diff;
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
