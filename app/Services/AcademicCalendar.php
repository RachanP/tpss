<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Holiday;
use App\Models\Term;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * ปฏิทินการศึกษา — จำแนกแต่ละวันของ "ปี" ว่าเป็นวันปกติ / วันหยุด / สัปดาห์สอบ / ปิดภาคเรียน
 * ใช้ร่วม: derive term_id ของ slot, ลงสี grid/list, และ validation บล็อกการจัดกิจกรรม
 *
 * กฎ (เคาะ 31 พ.ค. 2569):
 *  - วันที่อยู่ในช่วงของเทอมใดเทอมหนึ่ง (รวมภาคฤดูร้อนถ้ามี) → มี term · จัดได้
 *  - วันที่อยู่ในปีแต่ไม่มีเทอมคลุม → "ปิดภาคเรียน" → บล็อก
 *  - วันที่อยู่ในสัปดาห์สอบ (กลางภาค/ปลายภาค) ของเทอม → "สัปดาห์สอบ" → บล็อก
 *  - วันหยุดราชการ (อยู่ในเทอม ไม่ใช่สัปดาห์สอบ) → เตือน แต่จัดได้
 */
class AcademicCalendar
{
    private Collection $terms;

    /** @var array<string, string>  'Y-m-d' => ชื่อวันหยุด */
    private array $holidays = [];

    private ?CarbonImmutable $yearStart = null;
    private ?CarbonImmutable $yearEnd = null;

    public function __construct(private ?AcademicYear $year)
    {
        $this->terms = $year
            ? ($year->relationLoaded('terms') ? $year->terms : $year->terms()->get())
            : collect();

        $this->yearStart = $year?->start_date ? CarbonImmutable::parse($year->start_date)->startOfDay() : null;
        $this->yearEnd = $year?->end_date ? CarbonImmutable::parse($year->end_date)->startOfDay() : null;

        if ($this->yearStart && $this->yearEnd) {
            Holiday::query()
                ->whereBetween('date', [$this->yearStart->toDateString(), $this->yearEnd->toDateString()])
                ->get()
                ->each(function (Holiday $h) {
                    if ($h->date) {
                        $this->holidays[$h->date->toDateString()] = $h->name;
                    }
                });
        }
    }

    public static function forYear(?AcademicYear $year): self
    {
        return new self($year);
    }

    public function hasTerms(): bool
    {
        return $this->terms->isNotEmpty();
    }

    /** เทอมที่ช่วงวัน [start_date, end_date] คลุมวันนี้ */
    public function termForDate(CarbonInterface|string|null $date): ?Term
    {
        if ($date === null) {
            return null;
        }
        $d = CarbonImmutable::parse($date)->startOfDay();

        return $this->terms->first(fn (Term $t) => $this->inRange($d, $t->start_date, $t->end_date));
    }

    public function termIdForDate(CarbonInterface|string|null $date): ?int
    {
        return $this->termForDate($date)?->id;
    }

    public function holidayName(CarbonInterface|string $date): ?string
    {
        return $this->holidays[CarbonImmutable::parse($date)->toDateString()] ?? null;
    }

    /**
     * จำแนก "วันเดียว" สำหรับลงสี grid/list
     *
     * @return array{kind:string,label:?string,blocked:bool,term_id:?int,term_name:?string}
     */
    public function classifyDay(CarbonInterface|string $date): array
    {
        $d = CarbonImmutable::parse($date)->startOfDay();

        if ($this->yearStart && $this->yearEnd && ($d->lt($this->yearStart) || $d->gt($this->yearEnd))) {
            return $this->result('outside', 'นอกช่วงปีการศึกษา', true);
        }

        // ปียังไม่ได้ตั้งเทอม → ไม่จำแนก term/exam/break (กันเทา/บล็อกทั้งปฏิทิน) — demo/legacy
        if (! $this->hasTerms()) {
            return $this->result('normal', null, false);
        }

        $term = $this->termForDate($d);
        if (! $term) {
            return $this->result('break', 'ปิดภาคเรียน', true);
        }

        if ($this->isExamTerm($d)) {
            return $this->result('exam', 'สัปดาห์สอบ', true, $term->id, $term->name);
        }

        if ($name = $this->holidayName($d)) {
            return $this->result('holiday', $name, false, $term->id, $term->name);
        }

        return $this->result('normal', null, false, $term->id, $term->name);
    }

    /**
     * ตรวจช่วง [start, end] ของ slot ว่าชนวันที่บล็อกไหม (สัปดาห์สอบ / ปิดภาคเรียน)
     *
     * @return array{kind:string,message:string}|null  null = จัดได้
     */
    public function blockReasonForRange(CarbonInterface|string $start, CarbonInterface|string|null $end): ?array
    {
        if (! $this->hasTerms()) {
            return null; // ปียังไม่ตั้งเทอม → ไม่บล็อก (ไม่รู้ช่วงสอบ/ปิดเทอม)
        }

        $s = CarbonImmutable::parse($start)->startOfDay();
        $e = $end ? CarbonImmutable::parse($end)->startOfDay() : $s;
        if ($e->lt($s)) {
            $e = $s;
        }

        $hitExam = false;
        $hitBreak = false;
        $cursor = $s;
        $guard = 0;

        while ($cursor->lte($e) && $guard < 400) {
            $kind = $this->classifyDay($cursor)['kind'];
            if ($kind === 'exam') {
                $hitExam = true;
            } elseif ($kind === 'break' || $kind === 'outside') {
                $hitBreak = true;
            }
            $cursor = $cursor->addDay();
            $guard++;
        }

        if ($hitExam) {
            return ['kind' => 'exam', 'message' => 'ไม่สามารถจัดกิจกรรมในช่วงสัปดาห์สอบ (กลางภาค/ปลายภาค) ได้'];
        }
        if ($hitBreak) {
            return ['kind' => 'break', 'message' => 'ไม่สามารถจัดกิจกรรมในช่วงปิดภาคเรียนได้ (วันที่อยู่นอกช่วงเทอม)'];
        }

        return null;
    }

    private function isExamTerm(CarbonImmutable $d): bool
    {
        return $this->terms->contains(fn (Term $t) => $this->inRange($d, $t->midterm_start, $t->midterm_end)
            || $this->inRange($d, $t->final_start, $t->final_end));
    }

    private function inRange(CarbonImmutable $d, $start, $end): bool
    {
        if (! $start || ! $end) {
            return false;
        }

        return $d->gte(CarbonImmutable::parse($start)->startOfDay())
            && $d->lte(CarbonImmutable::parse($end)->startOfDay());
    }

    /**
     * @return array{kind:string,label:?string,blocked:bool,term_id:?int,term_name:?string}
     */
    private function result(string $kind, ?string $label, bool $blocked, ?int $termId = null, ?string $termName = null): array
    {
        return [
            'kind' => $kind,
            'label' => $label,
            'blocked' => $blocked,
            'term_id' => $termId,
            'term_name' => $termName,
        ];
    }
}
