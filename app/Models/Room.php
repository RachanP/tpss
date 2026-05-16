<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    protected $fillable = [
        'room_code',
        'room_name',
        'building',
        'capacity',
        'location_type_id',
        'equipment_type',
        'address',
        'status'
    ];

    protected $casts = [
        'equipment_type' => 'array',
    ];

    public function locationType()
    {
        return $this->belongsTo(LocationType::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }
}
