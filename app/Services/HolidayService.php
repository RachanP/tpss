<?php

namespace App\Services;

use App\Models\Holiday;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ดึงวันหยุดราชการไทยจาก Nager.Date (ฟรี ไม่ต้อง key) → เก็บลงตาราง holidays
 * idempotent (updateOrCreate by date) · fail-safe (API ล่มไม่ทำให้ flow พัง)
 */
class HolidayService
{
    private const ENDPOINT = 'https://date.nager.at/api/v3/PublicHolidays';

    /**
     * ดึงวันหยุดของหลายปีปฏิทิน → คืนจำนวนที่ sync สำเร็จ (รวมทุกปี) หรือ null ถ้าล้มเหลวทั้งหมด
     *
     * @param  int[]  $calendarYears
     */
    public function syncForCalendarYears(array $calendarYears): ?int
    {
        $total = 0;
        $anySuccess = false;

        foreach (array_unique($calendarYears) as $year) {
            $count = $this->syncYear((int) $year);
            if ($count !== null) {
                $anySuccess = true;
                $total += $count;
            }
        }

        return $anySuccess ? $total : null;
    }

    /**
     * ดึงปีปฏิทินที่ปีการศึกษาคร่อม (เช่น เริ่ม 2026 จบ 2027 → [2026, 2027])
     */
    public function syncForAcademicYearSpan(string $startDate, string $endDate): ?int
    {
        $startYear = (int) substr($startDate, 0, 4);
        $endYear = (int) substr($endDate, 0, 4);
        if ($startYear <= 0 || $endYear < $startYear) {
            return null;
        }

        return $this->syncForCalendarYears(range($startYear, $endYear));
    }

    /**
     * ดึงวันหยุด 1 ปีปฏิทิน → คืนจำนวน upsert หรือ null ถ้าดึงไม่สำเร็จ (fail-safe)
     */
    public function syncYear(int $calendarYear): ?int
    {
        try {
            $response = Http::timeout(5)->retry(1, 200)->acceptJson()
                ->get(self::ENDPOINT . "/{$calendarYear}/TH");

            if (! $response->successful() || ! is_array($response->json())) {
                Log::warning("HolidayService: ดึงวันหยุดปี {$calendarYear} ไม่สำเร็จ (status {$response->status()})");
                return null;
            }

            $count = 0;
            foreach ($response->json() as $item) {
                $date = $item['date'] ?? null;
                if (! $date) {
                    continue;
                }
                Holiday::updateOrCreate(
                    ['date' => $date],
                    [
                        'name'   => $item['localName'] ?? $item['name'] ?? 'วันหยุด',
                        'source' => 'nager',
                    ]
                );
                $count++;
            }

            return $count;
        } catch (\Throwable $e) {
            Log::warning("HolidayService: ดึงวันหยุดปี {$calendarYear} error — {$e->getMessage()}");
            return null;
        }
    }
}
