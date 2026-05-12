<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentGroup extends Model
{
    protected $fillable = ['group_code', 'curriculum_id', 'academic_year_id', 'student_count', 'color_code'];

    public function curriculum()
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
