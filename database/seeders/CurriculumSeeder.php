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
