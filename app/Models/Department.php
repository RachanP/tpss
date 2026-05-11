<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = ['name', 'head_user_id', 'secretary_user_id'];

    public function head()
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    public function secretary()
    {
        return $this->belongsTo(User::class, 'secretary_user_id');
    }

    public function instructorProfiles()
    {
        return $this->hasMany(InstructorProfile::class);
    }
}
