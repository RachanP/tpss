<?php

namespace App\Services;

use App\Models\Holiday;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ดึงวันหยุดราชการไทยจากปฏิทินวันหยุดไทยของ Google (ICS สาธารณะ ไม่ต้อง key)
 * → เก็บลงตาราง holidays · idempotent (updateOrCreate by date) · fail-safe (ดึงไม่ได้ไม่ทำให้ flow พัง)
 * (Nager.Date ไม่รองรับ TH — คืน 204)
 */
class HolidayService
{
    private const ICS_URL = 'https://calendar.google.com/calendar/ical/th.th%23holiday%40group.v.calendar.google.com/public/basic.ics';

    /**
     * CA bundle ที่แนบมากับโปรเจกต์ — กัน cURL error 60 (SSL) บนเครื่องที่ php.ini ไม่ตั้ง curl.cainfo
     * (เช่น MAMP บน Windows) · ถ้าไม่มีไฟล์ → ใช้ system CA (true)
     */
    private function caBundle(): string|bool
    {
        $path = resource_path('certs/cacert.pem');
        return is_file($path) ? $path : true;
    }

    /**
     * ดึง + เก็บวันหยุดของหลายปีปฏิทิน → คืนจำนวน upsert หรือ null ถ้าดึง ICS ไม่สำเร็จ
     *
     * @param  int[]  $calendarYears
     */
    public function syncForCalendarYears(array $calendarYears): ?int
    {
        $events = $this->fetchIcsEvents();
        if ($events === null) {
            return null;
        }

        $years = array_map('intval', $calendarYears);

        // รีเฟรช: ลบวันหยุด "อัตโนมัติ" เดิมของช่วงปีปฏิทินที่ดึง (กันวันที่ถูกยกเลิกค้าง)
        // คงไว้: วันหยุดที่ Admin เพิ่มเอง (source=manual) + วันหยุดของปีอื่น
        Holiday::where('source', 'google')
            ->whereBetween('date', [min($years) . '-01-01', max($years) . '-12-31'])
            ->delete();

        $count = 0;
        foreach ($events as $event) {
            if (! in_array((int) substr($event['date'], 0, 4), $years, true)) {
                continue;
            }
            // ไม่ทับวันที่ที่ Admin เพิ่มเอง
            if (Holiday::where('date', $event['date'])->where('source', 'manual')->exists()) {
                continue;
            }
            Holiday::updateOrCreate(
                ['date' => $event['date']],
                ['name' => $event['name'], 'source' => 'google']
            );
            $count++;
        }

        return $count;
    }

    public function syncForAcademicYearSpan(string $startDate, string $endDate): ?int
    {
        $startYear = (int) substr($startDate, 0, 4);
        $endYear = (int) substr($endDate, 0, 4);
        if ($startYear <= 0 || $endYear < $startYear) {
            return null;
        }

        return $this->syncForCalendarYears(range($startYear, $endYear));
    }

    public function syncYear(int $calendarYear): ?int
    {
        return $this->syncForCalendarYears([$calendarYear]);
    }

    /**
     * ดึง ICS + parse เป็น [['date' => 'Y-m-d', 'name' => ...], ...] · null ถ้าดึงไม่สำเร็จ
     *
     * @return array<int, array{date: string, name: string}>|null
     */
    private function fetchIcsEvents(): ?array
    {
        try {
            $response = Http::timeout(15)->retry(1, 300)
                ->withOptions(['verify' => $this->caBundle()])
                ->get(self::ICS_URL);

            if (! $response->successful() || $response->body() === '') {
                Log::warning("HolidayService: ดึงปฏิทินวันหยุดไม่สำเร็จ (status {$response->status()})");
                return null;
            }

            return $this->parseIcs($response->body());
        } catch (\Throwable $e) {
            Log::warning("HolidayService: ดึงปฏิทินวันหยุด error — {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Parse ICS → วันหยุดแบบ all-day (DTSTART;VALUE=DATE:YYYYMMDD + SUMMARY)
     *
     * @return array<int, array{date: string, name: string}>
     */
    private function parseIcs(string $ics): array
    {
        $events = [];
        if (! preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $ics, $blocks)) {
            return $events;
        }

        foreach ($blocks[1] as $block) {
            if (! preg_match('/DTSTART(?:;VALUE=DATE)?:(\d{8})/', $block, $dm)) {
                continue;
            }
            $ymd = $dm[1];
            $date = substr($ymd, 0, 4) . '-' . substr($ymd, 4, 2) . '-' . substr($ymd, 6, 2);

            $name = 'วันหยุด';
            if (preg_match('/\nSUMMARY:(.+)/', $block, $sm)) {
                $name = trim($sm[1]);
                // unescape ICS
                $name = str_replace(['\\,', '\\;', '\\n', '\\N'], [',', ';', ' ', ' '], $name);
            }

            $events[] = ['date' => $date, 'name' => $name];
        }

        return $events;
    }
}
