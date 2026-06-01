<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use Illuminate\Database\Seeder;

class AcademicYearSeeder extends Seeder
{
    /**
     * ปีการศึกษา = "ปี" (1 แถว/ปี) + เทอมเป็นรายการลูก (terms) พร้อมช่วงสอบ
     */
    public function run(): void
    {
        $years = [
            [
                'name' => '2568',
                'start_date' => '2025-06-02',
                'end_date' => '2026-03-13',
                'is_active' => false,
                'terms' => [
                    ['sequence' => 1, 'name' => 'ภาคเรียนที่ 1', 'start_date' => '2025-06-02', 'end_date' => '2025-10-15',
                     'midterm_start' => '2025-07-28', 'midterm_end' => '2025-08-01', 'final_start' => '2025-10-11', 'final_end' => '2025-10-15'],
                    ['sequence' => 2, 'name' => 'ภาคเรียนที่ 2', 'start_date' => '2025-11-03', 'end_date' => '2026-03-13',
                     'midterm_start' => '2025-12-22', 'midterm_end' => '2025-12-26', 'final_start' => '2026-03-09', 'final_end' => '2026-03-13'],
                ],
            ],
            [
                'name' => '2569',
                'start_date' => '2026-06-01',
                'end_date' => '2027-03-15',
                'is_active' => false,
                'terms' => [
                    ['sequence' => 1, 'name' => 'ภาคเรียนที่ 1', 'start_date' => '2026-06-01', 'end_date' => '2026-10-15',
                     'midterm_start' => '2026-07-27', 'midterm_end' => '2026-07-31', 'final_start' => '2026-10-11', 'final_end' => '2026-10-15'],
                    ['sequence' => 2, 'name' => 'ภาคเรียนที่ 2', 'start_date' => '2026-11-02', 'end_date' => '2027-03-15',
                     'midterm_start' => '2026-12-21', 'midterm_end' => '2026-12-25', 'final_start' => '2027-03-11', 'final_end' => '2027-03-15'],
                ],
            ],
        ];

        foreach ($years as $yearData) {
            $terms = $yearData['terms'];
            unset($yearData['terms']);

            $year = AcademicYear::updateOrCreate(['name' => $yearData['name']], $yearData);

            foreach ($terms as $termData) {
                $year->terms()->updateOrCreate(
                    ['sequence' => $termData['sequence']],
                    $termData
                );
            }
        }
    }
}
