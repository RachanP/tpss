<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivityType extends Model
{
    protected $fillable = ['name', 'color_code', 'category', 'counts_toward_workload'];

    protected $casts = [
        'counts_toward_workload' => 'boolean',
    ];

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function scheduleTemplates(): HasMany
    {
        return $this->hasMany(ScheduleTemplate::class);
    }
}
