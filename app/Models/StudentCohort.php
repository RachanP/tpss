<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * กลุ่มนักศึกษาระดับชั้นปี (cohort) — ตั้งใน Master Data (Admin)
 * ดู .claude/rules/architecture.md "Requirement V2 Direction" ข้อ 2
 * V4: รองรับกลุ่มย่อย (subgroup) — กลุ่มใหญ่ A → กลุ่มย่อย A1, A2 ผ่าน parent_id
 */
class StudentCohort extends Model
{
    protected $fillable = [
        'curriculum_id',
        'academic_year_id',
        'parent_id',
        'year_level',
        'code',
        'student_count',
        'note',
    ];

    protected $casts = [
        'curriculum_id' => 'integer',
        'academic_year_id' => 'integer',
        'parent_id' => 'integer',
        'year_level' => 'integer',
        'student_count' => 'integer',
    ];

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** กลุ่มใหญ่ที่กลุ่มย่อยนี้สังกัด */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(StudentCohort::class, 'parent_id');
    }

    /** กลุ่มย่อยภายใต้กลุ่มใหญ่นี้ */
    public function subgroups(): HasMany
    {
        return $this->hasMany(StudentCohort::class, 'parent_id');
    }

    public function offeringGroups(): HasMany
    {
        return $this->hasMany(StudentGroup::class, 'cohort_group_id');
    }

    public function rootGroupId(): int
    {
        return (int) ($this->parent_id ?? $this->id);
    }

    /** เป็นกลุ่มใหญ่ (ไม่มี parent) หรือไม่ */
    public function isMajor(): bool
    {
        return $this->parent_id === null;
    }
}
