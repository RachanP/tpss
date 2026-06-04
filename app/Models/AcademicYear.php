<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class AcademicYear extends Model
{
    protected $fillable = ['name', 'start_date', 'end_date', 'is_active', 'phase'];

    /**
     * ปฏิทินการศึกษาของปีนี้ (V4 — 1 ปีมีได้หลายปฏิทิน)
     */
    public function calendars(): HasMany
    {
        return $this->hasMany(AcademicCalendar::class);
    }

    /**
     * ปฏิทินหลัก (default) ของปี — สร้างให้อัตโนมัติถ้ายังไม่มี
     * ใช้เป็นที่เก็บ terms ของ flow เดิม (ปฏิทินเดียวต่อปี)
     */
    public function defaultCalendar(): AcademicCalendar
    {
        return $this->calendars()->firstOrCreate(
            ['is_default' => true],
            ['name' => 'ปฏิทินหลัก']
        );
    }

    /**
     * เทอม (ภาคการศึกษา) ของปีนี้ — รวมทุกปฏิทิน เรียงตามลำดับ
     * คงชื่อ relation "terms" ไว้เพื่อให้ reader เดิม ($year->terms) ใช้ได้เหมือนเดิม
     */
    public function terms(): HasManyThrough
    {
        return $this->hasManyThrough(Term::class, AcademicCalendar::class)
            ->orderBy('terms.sequence');
    }

    public function courseOfferings(): HasMany
    {
        return $this->hasMany(CourseOffering::class);
    }
}
