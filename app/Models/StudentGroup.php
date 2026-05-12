<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentGroup extends Model
{
    protected $fillable = ['group_code', 'curriculum_id', 'year_level', 'student_count', 'color_code'];

    public function curriculum()
    {
        return $this->belongsTo(Curriculum::class);
    }
}
