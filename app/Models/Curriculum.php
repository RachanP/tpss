<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Curriculum extends Model
{
    protected $table = 'curriculums';

    protected $fillable = [
        'name',
        'effective_year',
        'education_level',
        'duration_years',
        'uses_year_level',
        'total_credits_required',
        'is_active'
    ];

    protected $casts = [
        'effective_year' => 'integer',
        'duration_years' => 'integer',
        'uses_year_level' => 'boolean',
        'total_credits_required' => 'integer',
        'is_active' => 'boolean'
    ];

    protected $attributes = [
        'education_level' => 'bachelor',
        'duration_years' => 4,
        'uses_year_level' => true,
    ];

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }
}
