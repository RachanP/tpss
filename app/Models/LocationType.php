<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocationType extends Model
{
    protected $fillable = ['name', 'requires_capacity', 'is_shared'];

    protected $casts = [
        'requires_capacity' => 'boolean',
        'is_shared'         => 'boolean',
    ];

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }
}
