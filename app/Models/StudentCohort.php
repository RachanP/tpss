<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * กลุ่มนักศึกษาระดับชั้นปี (cohort) — ตั้งใน Master Data (Admin)
 * ดู .claude/rules/architecture.md "Requirement V2 Direction" ข้อ 2
 */
class StudentCohort extends Model
{
    protected $fillable = [
        'curriculum_id',
        'year_level',
        'code',
        'student_count',
        'note',
    ];

    protected $casts = [
        'curriculum_id' => 'integer',
        'year_level' => 'integer',
        'student_count' => 'integer',
    ];

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }
}
