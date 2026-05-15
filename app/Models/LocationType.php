<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocationType extends Model
{
    protected $fillable = ['name', 'requires_capacity'];

    protected $casts = ['requires_capacity' => 'boolean'];

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }
}
