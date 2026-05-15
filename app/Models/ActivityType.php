<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivityType extends Model
{
    protected $fillable = ['name', 'color_code', 'category'];

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }
}
