<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstructorProfile extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'department_id',
        'employment_type',
        'hired_at',
        'academic_degree',
        'is_english_passed',
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

    public function getProfileWarnings(array $paCriteria): array
    {
        if (empty($this->title)) return [];

        $group = \App\Http\Controllers\Admin\AlertController::paGroup(
            $this->title,
            $this->academic_degree ?? ''
        );
        $rules = $paCriteria[$group] ?? null;
        if (!$rules) return [];

        $fields = [
            't' => ['value' => $this->teaching_pct, 'label' => 'สอน'],
            'r' => ['value' => $this->research_pct,  'label' => 'วิจัย'],
            's' => ['value' => $this->service_pct,   'label' => 'บริการวิชาการ'],
            'c' => ['value' => $this->culture_pct,   'label' => 'ศิลปวัฒนธรรม'],
            'o' => ['value' => $this->other_pct,     'label' => 'อื่นๆ'],
        ];

        $violations = [];
        foreach ($fields as $key => $field) {
            $range = $rules[$key] ?? null;
            if (!is_array($range)) continue;
            $val = (int) $field['value'];
            if ($val < $range['min'] || $val > $range['max']) {
                $violations[] = $field['label'];
            }
        }

        if (empty($violations)) return [];
        return ['สัดส่วนภาระงานผิดเกณฑ์ (' . implode(', ', $violations) . ')'];
    }
}
