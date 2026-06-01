<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * เทอม (ภาคการศึกษา) — ลูกของปีการศึกษา
 * ดู .claude/rules/architecture.md "Master Data Cleanup Phase (V2)"
 */
class Term extends Model
{
    protected $fillable = [
        'academic_year_id',
        'sequence',
        'name',
        'start_date',
        'end_date',
        'midterm_start',
        'midterm_end',
        'final_start',
        'final_end',
    ];

    protected $casts = [
        'academic_year_id' => 'integer',
        'sequence' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'midterm_start' => 'date',
        'midterm_end' => 'date',
        'final_start' => 'date',
        'final_end' => 'date',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
