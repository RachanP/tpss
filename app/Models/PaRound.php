<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaRound extends Model
{
    public const CODE_ANNUAL = 'annual';
    public const CODE_HALF_1 = 'half_1';
    public const CODE_HALF_2 = 'half_2';

    protected $fillable = [
        'academic_year_id',
        'code',
        'name',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(InstructorPaAllocation::class);
    }
}
