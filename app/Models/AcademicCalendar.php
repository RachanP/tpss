<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ปฏิทินการศึกษา (V4 ข้อ 8) — ปฏิทินย่อยใต้ปีการศึกษา
 * 1 ปีมีได้หลายปฏิทิน · แต่ละปฏิทินมีชุด terms ของตัวเอง (เปิด/ปิดเทอม + ช่วงสอบ)
 * ผูกขอบเขต curriculum + ช่วงชั้นปี เพื่อ resolve ว่ากลุ่มไหนใช้ปฏิทินใด
 * ดู .claude/rules/database.md + requirement_v4 ข้อ 8
 */
class AcademicCalendar extends Model
{
    protected $fillable = [
        'academic_year_id',
        'name',
        'curriculum_id',
        'year_level_min',
        'year_level_max',
        'is_default',
    ];

    protected $casts = [
        'academic_year_id' => 'integer',
        'curriculum_id' => 'integer',
        'year_level_min' => 'integer',
        'year_level_max' => 'integer',
        'is_default' => 'boolean',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class)->orderBy('sequence');
    }
}
