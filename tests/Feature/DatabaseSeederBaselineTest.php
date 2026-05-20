<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseOffering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederBaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_seed_starts_without_selected_academic_year_or_course_offerings(): void
    {
        $this->seed();

        $this->assertGreaterThan(0, AcademicYear::count());
        $this->assertSame(0, AcademicYear::where('is_active', true)->count());

        $this->assertGreaterThan(0, Course::count());
        $this->assertSame(0, Course::where('status', 'active')->count());

        $this->assertSame(0, CourseOffering::count());
    }
}
