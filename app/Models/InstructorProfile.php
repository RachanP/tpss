<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstructorProfile extends Model
{
    protected $fillable = [
        'user_id',
        'employee_id',
        'title',
        'department_id',
        'employment_type',
        'hired_at',
        'academic_degree',
        'teaching_pct',
        'research_pct',
        'service_pct',
        'culture_pct',
        'other_pct',
        'teaching_quota',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
