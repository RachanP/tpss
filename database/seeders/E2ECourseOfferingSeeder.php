<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Course;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class E2ECourseOfferingSeeder extends Seeder
{
    public function run(): void
    {
        $year = AcademicYear::whereDate('end_date', '>=', Carbon::today())
            ->orderBy('start_date')
            ->first()
            ?? AcademicYear::orderByDesc('start_date')->first();

        if (! $year) {
            $this->command->warn('E2ECourseOfferingSeeder: no academic year found.');

            return;
        }

        AcademicYear::query()->update(['is_active' => false]);
        $year->update(['is_active' => true]);

        Course::whereNotNull('head_instructor_id')
            ->where('status', '!=', 'active')
            ->update(['status' => 'active']);

        $this->call(CourseOfferingSeeder::class);
    }
}
