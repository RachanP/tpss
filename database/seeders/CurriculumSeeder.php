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
                'is_active' => true,
            ]
        ];

        foreach ($programs as $programData) {
            Curriculum::updateOrCreate(
                ['name' => $programData['name']],
                $programData
            );
        }
    }
}
