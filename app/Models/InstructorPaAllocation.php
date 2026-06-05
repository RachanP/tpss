<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstructorPaAllocation extends Model
{
    protected $fillable = [
        'user_id',
        'pa_round_id',
        'teaching_pct',
        'research_pct',
        'service_pct',
        'culture_pct',
        'other_pct',
        'teaching_quota',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'teaching_pct' => 'integer',
            'research_pct' => 'integer',
            'service_pct' => 'integer',
            'culture_pct' => 'integer',
            'other_pct' => 'integer',
            'teaching_quota' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paRound(): BelongsTo
    {
        return $this->belongsTo(PaRound::class);
    }

    public function totalPct(): int
    {
        return $this->teaching_pct
            + $this->research_pct
            + $this->service_pct
            + $this->culture_pct
            + $this->other_pct;
    }
}
