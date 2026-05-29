<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use Illuminate\Database\Seeder;

class AcademicYearSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $years = [
            [
                'name' => '2568',
                'semester' => 1,
                'start_date' => '2025-06-02',
                'end_date' => '2025-10-15',
                'is_active' => false,
            ],
            [
                'name' => '2568',
                'semester' => 2,
                'start_date' => '2025-11-03',
                'end_date' => '2026-03-13',
                'is_active' => false,
            ],
            [
                'name' => '2569',
                'semester' => 1,
                'start_date' => '2026-06-01',
                'end_date' => '2026-10-15',
                'is_active' => false,
            ],
            [
                'name' => '2569',
                'semester' => 2,
                'start_date' => '2026-11-02',
                'end_date' => '2027-03-15',
                'is_active' => false,
            ],
        ];

        foreach ($years as $yearData) {
            AcademicYear::updateOrCreate(
                ['name' => $yearData['name'], 'semester' => $yearData['semester']],
                $yearData
            );
        }
    }
}
