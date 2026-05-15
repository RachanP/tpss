<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentGroup extends Model
{
    protected $fillable = ['group_code', 'course_offering_id', 'student_count', 'color_code'];

    protected $casts = [
        'course_offering_id' => 'integer',
        'student_count' => 'integer',
    ];

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }
}
