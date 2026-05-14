<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentGroup extends Model
{
    protected $fillable = ['group_code', 'course_offering_id', 'student_count', 'color_code'];

    public function courseOffering()
    {
        return $this->belongsTo(CourseOffering::class);
    }
}
