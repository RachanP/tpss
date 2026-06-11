<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\CourseOffering;
use App\Models\InstructorPaAllocation;
use App\Models\Schedule;
use App\Models\StudentGroup;
use App\Models\User;
use Database\Seeders\TpssMediumDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TpssMediumDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_medium_demo_seed_populates_phase_one_demo_data_and_is_idempotent(): void
    {
        config(['conflicts.async_reads' => false]);
        Cache::flush();

        $this->seed(TpssMediumDemoSeeder::class);

        $year = AcademicYear::where('name', '2569')->firstOrFail();

        $this->assertTrue((bool) $year->is_active);
        $this->assertSame('scheduling', $year->phase);
        $this->assertGreaterThanOrEqual(7, CourseOffering::where('academic_year_id', $year->id)->count());
        $this->assertGreaterThanOrEqual(10, User::where('username', 'like', 'demo_instructor_%')->count());
        $this->assertGreaterThanOrEqual(1, InstructorPaAllocation::count());

        foreach (['published', 'pending', 'rejected'] as $status) {
            $this->assertDatabaseHas('course_offerings', [
                'academic_year_id' => $year->id,
                'approval_status' => $status,
            ]);
        }

        $copyDemoOffering = CourseOffering::where('academic_year_id', $year->id)
            ->whereHas('course', fn ($query) => $query->where('course_code', 'NSBS 231'))
            ->firstOrFail();

        $this->assertSame(12, StudentGroup::where('course_offering_id', $copyDemoOffering->id)->count());
        $this->assertSame(
            5,
            Schedule::where('course_offering_id', $copyDemoOffering->id)
                ->whereBetween('teaching_date', ['2026-06-16', '2026-06-20'])
                ->count()
        );

        $this->assertTrue(
            Schedule::where('remark', '[tpss-medium-demo]')
                ->where('topic', 'like', 'บล็อกฝึกปฏิบัติ%')
                ->whereDate('start_date', '2026-06-22')
                ->whereDate('end_date', '2026-06-26')
                ->exists()
        );

        $this->assertTrue(
            Schedule::where('remark', '[tpss-medium-demo]')
                ->whereHas('courseOffering.course', fn ($query) => $query->where('course_code', 'NSBS 212'))
                ->whereNull('room_id')
                ->exists()
        );

        $this->assertDatabaseHas('holidays', [
            'date' => '2026-06-03',
            'source' => 'demo',
        ]);

        $taggedScheduleCount = Schedule::where('remark', '[tpss-medium-demo]')->count();

        $this->seed(TpssMediumDemoSeeder::class);

        $this->assertSame($taggedScheduleCount, Schedule::where('remark', '[tpss-medium-demo]')->count());
        $this->assertSame(1, AcademicYear::where('is_active', true)->count());
    }
}
