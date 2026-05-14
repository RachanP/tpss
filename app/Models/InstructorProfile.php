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
        $warnings = [];

        if (empty($this->title)) $warnings[] = 'ไม่ระบุตำแหน่งทางวิชาการ';
        if (empty($this->department_id)) $warnings[] = 'ไม่พบภาควิชา';
        if (empty($this->hired_at)) $warnings[] = 'ไม่ระบุวันที่เข้าทำงาน';
        if (empty($this->academic_degree)) $warnings[] = 'ไม่ระบุวุฒิการศึกษาสูงสุด';
        if (empty($this->employment_type)) $warnings[] = 'ไม่ระบุประเภทการจ้างงาน';

        if (empty($this->title)) {
            return $warnings;
        }

        $title = $this->title;
        $degree = $this->academic_degree;
        $hiredAt = $this->hired_at;
        $isEnglishPassed = $this->is_english_passed;

        $isNote1 = ($title === 'ผู้ช่วยอาจารย์' && $degree === 'ปริญญาเอก' && !empty($hiredAt) && strtotime($hiredAt) < strtotime('2016-10-01'));
        
        $useInstructorRules = (
            $title === 'อาจารย์' || 
            $title === 'ผู้ช่วยศาสตราจารย์' || 
            $title === 'รองศาสตราจารย์' || 
            $title === 'ศาสตราจารย์' || 
            $isNote1 ||
            ($title === 'ผู้ช่วยอาจารย์' && $degree === 'ปริญญาเอก' && $isEnglishPassed)
        );

        $rules = ['t' => '-', 'r' => '-', 's' => '-', 'c' => '-', 'o' => '-'];
        
        if ($title === 'ผู้ช่วยอาจารย์ (คลินิก)') {
            $rules = $paCriteria['ผู้ช่วยอาจารย์_คลินิก'] ?? $rules;
        } else if ($title === 'ผู้ช่วยอาจารย์ (สอนภาคปฏิบัติ)') {
            $rules = $paCriteria['ผู้ช่วยอาจารย์_ปฏิบัติ'] ?? $rules;
        } else if ($title === 'ผู้ช่วยอาจารย์' && $degree === 'ปริญญาตรี') {
            $rules = $paCriteria['ผู้ช่วยอาจารย์_ปตรี'] ?? $rules;
        } else if ($useInstructorRules) {
            $rules = $paCriteria['อาจารย์'] ?? $rules;
        } else if ($title === 'ผู้ช่วยอาจารย์') {
            $rules = $paCriteria['ผู้ช่วยอาจารย์'] ?? $rules;
        }

        $isOutOfRange = function($value, $rule) {
            if (!$rule || $rule === '-') return false;
            $val = (int) $value;
            
            if (str_contains($rule, '≤')) {
                $max = (int) trim(str_replace('≤', '', $rule));
                return $val > $max;
            }
            
            if (str_contains($rule, '-')) {
                $parts = explode('-', $rule);
                $min = (int) ($parts[0] ?? 0);
                $max = (int) ($parts[1] ?? 0);
                return $val < $min || $val > $max;
            }
            return false;
        };

        $paViolations = [];
        if ($isOutOfRange($this->teaching_pct, $rules['t'] ?? '-')) $paViolations[] = 'สอน';
        if ($isOutOfRange($this->research_pct, $rules['r'] ?? '-')) $paViolations[] = 'วิจัย';
        if ($isOutOfRange($this->service_pct, $rules['s'] ?? '-')) $paViolations[] = 'บริการวิชาการ';
        if ($isOutOfRange($this->culture_pct, $rules['c'] ?? '-')) $paViolations[] = 'ศิลปวัฒนธรรม';
        if ($isOutOfRange($this->other_pct, $rules['o'] ?? '-')) $paViolations[] = 'อื่นๆ';

        if (!empty($paViolations)) {
            $warnings[] = 'สัดส่วนภาระงานผิดเกณฑ์ (' . implode(', ', $paViolations) . ')';
        }

        return $warnings;
    }
}
