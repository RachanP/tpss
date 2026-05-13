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
        'is_active'
    ];

    protected $casts = [
        'effective_year' => 'integer',
        'is_active' => 'boolean'
    ];

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }
}
