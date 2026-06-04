<?php

namespace Database\Seeders;

use App\Models\Curriculum;
use Illuminate\Database\Seeder;

class CurriculumSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $programs = [
            [
                'name' => 'หลักสูตรพยาบาลศาสตรบัณฑิต (ปรับปรุง 2565)',
                'effective_year' => 2565,
                'education_level' => 'bachelor',
                'duration_years' => 4,
                'uses_year_level' => true,
                'total_credits_required' => 140,
                'counts_service_only' => false,
                'is_active' => true,
            ],
            // V4: หลักสูตรเฉพาะทาง — นับเป็นงานบริการวิชาการอย่างเดียว (ไม่นับชั่วโมงทำการสอนปกติ)
            [
                'name' => 'หลักสูตรการพยาบาลเฉพาะทาง สาขาการพยาบาลผู้ป่วยวิกฤต (2566)',
                'effective_year' => 2566,
                'education_level' => 'master',
                'duration_years' => 1,
                'uses_year_level' => false,
                'total_credits_required' => 25,
                'counts_service_only' => true,
                'is_active' => true,
            ],
        ];

        foreach ($programs as $programData) {
            Curriculum::updateOrCreate(
                ['name' => $programData['name']],
                $programData
            );
        }
    }
}
