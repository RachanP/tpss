<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\InstructorProfile;
use App\Models\StudentGroup;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\DevM2VisualVerificationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DevM2VisualVerificationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_dev_m2_visual_seeder_creates_visible_data_for_admin_course_head(): void
    {
        $this->seedBaseM2VisualData();
        $this->seed(DevM2VisualVerificationSeeder::class);

        $admin = User::where('username', 'admin_01')->firstOrFail();
        $activeCourse = Course::where('course_code', 'NSBS 212')->firstOrFail();
        $archivedCourse = Course::where('course_code', 'NSBS 213')->firstOrFail();
        $prerequisiteCourse = Course::where('course_code', 'NSBS 111')->firstOrFail();

        $activeOffering = CourseOffering::where([
            'course_id' => $activeCourse->id,
            'coordinator_id' => $admin->id,
            'status' => 'active',
        ])->first();

        $archivedOffering = CourseOffering::where([
            'course_id' => $archivedCourse->id,
            'coordinator_id' => $admin->id,
            'status' => 'archived',
        ])->first();

        $this->assertNotNull($activeOffering);
        $this->assertNotNull($archivedOffering);

        $this->assertDatabaseHas('user_roles', [
            'user_id' => $admin->id,
            'role' => 'course_head',
        ]);

        $this->assertSame(60, (int) $activeOffering->total_student_count);
        $this->assertSame(2, StudentGroup::where('course_offering_id', $activeOffering->id)->count());
        $this->assertSame(60, (int) StudentGroup::where('course_offering_id', $activeOffering->id)->sum('student_count'));

        $this->assertDatabaseHas('student_groups', [
            'course_offering_id' => $activeOffering->id,
            'group_code' => 'A1',
            'student_count' => 30,
        ]);

        $this->assertGreaterThanOrEqual(
            1,
            DB::table('course_offering_instructors')
                ->where('course_offering_id', $activeOffering->id)
                ->count()
        );

        $this->assertDatabaseHas('course_prerequisites', [
            'course_id' => $activeCourse->id,
            'prerequisite_course_id' => $prerequisiteCourse->id,
        ]);

        $this->actingAs($admin);
        $this->withSession(['active_role' => 'course_head']);

        $this->get(route('maker.course_offerings.index'))
            ->assertOk()
            ->assertSee('NSBS 212')
            ->assertDontSee('NSBS 213');

        $this->get(route('maker.course_offerings.index', ['archived' => 1]))
            ->assertOk()
            ->assertSee('NSBS 213')
            ->assertDontSee('NSBS 212');
    }

    public function test_dev_m2_visual_seeder_is_idempotent_and_does_not_create_schedules(): void
    {
        $this->seedBaseM2VisualData();
        $this->seed(DevM2VisualVerificationSeeder::class);

        $firstSummary = $this->visualDataSummary();

        $this->seed(DevM2VisualVerificationSeeder::class);

        $this->assertSame($firstSummary, $this->visualDataSummary());
        $this->assertSame(0, DB::table('schedules')->count());
    }

    private function seedBaseM2VisualData(): void
    {
        $department = Department::firstOrCreate([
            'name' => 'Dev Nursing Department',
        ]);

        $admin = User::updateOrCreate(
            ['username' => 'admin_01'],
            [
                'name' => 'ราชันย์ พิพัฒน์',
                'email' => 'rachan@example.test',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );

        UserRole::firstOrCreate(
            ['user_id' => $admin->id, 'role' => 'admin'],
            ['is_primary' => true]
        );

        InstructorProfile::updateOrCreate(
            ['user_id' => $admin->id],
            [
                'title' => 'อาจารย์',
                'department_id' => $department->id,
                'employment_type' => 'พนักงานมหาวิทยาลัย',
                'academic_degree' => 'ปริญญาโท',
            ]
        );

        $instructor = User::updateOrCreate(
            ['username' => 'dev_instructor_01'],
            [
                'name' => 'อาจารย์ทดสอบ',
                'email' => 'dev.instructor@example.test',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );

        UserRole::firstOrCreate(
            ['user_id' => $instructor->id, 'role' => 'instructor'],
            ['is_primary' => true]
        );

        InstructorProfile::updateOrCreate(
            ['user_id' => $instructor->id],
            [
                'title' => 'อาจารย์',
                'department_id' => $department->id,
                'employment_type' => 'พนักงานมหาวิทยาลัย',
                'academic_degree' => 'ปริญญาโท',
            ]
        );

        $curriculum = Curriculum::updateOrCreate(
            ['name' => 'Dev Nursing Curriculum 2565'],
            [
                'effective_year' => 2565,
                'is_active' => true,
            ]
        );

        AcademicYear::updateOrCreate(
            ['name' => '2569', 'semester' => 1],
            [
                'start_date' => '2026-06-01',
                'end_date' => '2026-10-15',
                'is_active' => true,
            ]
        );

        $this->course($curriculum, $department, 'NSBS 111', 'กระบวนการพยาบาล 1', 'theory', 2, 2, 0);
        $this->course($curriculum, $department, 'NSBS 212', 'การพยาบาลเด็ก 1', 'theory_practicum', 3, 2, 1);
        $this->course($curriculum, $department, 'NSBS 213', 'สุขภาพจิตและการพยาบาลจิตเวช 1', 'theory', 2, 2, 0);
    }

    private function course(
        Curriculum $curriculum,
        Department $department,
        string $code,
        string $name,
        string $type,
        int $credits,
        int $lectureHours,
        int $labHours
    ): Course {
        return Course::updateOrCreate(
            [
                'course_code' => $code,
                'curriculum_id' => $curriculum->id,
            ],
            [
                'department_id' => $department->id,
                'name_th' => $name,
                'name_en' => $name,
                'course_type' => $type,
                'academic_level' => 'undergraduate',
                'default_year_level' => 2,
                'default_semester' => 1,
                'requires_practicum_rotation' => false,
                'credits' => $credits,
                'lecture_hours' => $lectureHours,
                'lab_hours' => $labHours,
                'self_study_hours' => 3,
                'status' => 'active',
            ]
        );
    }

    private function visualDataSummary(): array
    {
        $admin = User::where('username', 'admin_01')->firstOrFail();
        $activeCourse = Course::where('course_code', 'NSBS 212')->firstOrFail();
        $archivedCourse = Course::where('course_code', 'NSBS 213')->firstOrFail();
        $prerequisiteCourse = Course::where('course_code', 'NSBS 111')->firstOrFail();

        $activeOffering = CourseOffering::where([
            'course_id' => $activeCourse->id,
            'coordinator_id' => $admin->id,
            'status' => 'active',
        ])->firstOrFail();

        $archivedOffering = CourseOffering::where([
            'course_id' => $archivedCourse->id,
            'coordinator_id' => $admin->id,
            'status' => 'archived',
        ])->firstOrFail();

        return [
            'admin_course_head_roles' => DB::table('user_roles')
                ->where('user_id', $admin->id)
                ->where('role', 'course_head')
                ->count(),
            'active_offerings' => CourseOffering::where([
                'course_id' => $activeCourse->id,
                'coordinator_id' => $admin->id,
                'status' => 'active',
            ])->count(),
            'archived_offerings' => CourseOffering::where([
                'course_id' => $archivedCourse->id,
                'coordinator_id' => $admin->id,
                'status' => 'archived',
            ])->count(),
            'student_groups' => StudentGroup::where('course_offering_id', $activeOffering->id)->count(),
            'student_total' => (int) StudentGroup::where('course_offering_id', $activeOffering->id)->sum('student_count'),
            'instructors' => DB::table('course_offering_instructors')
                ->where('course_offering_id', $activeOffering->id)
                ->count(),
            'prerequisites' => DB::table('course_prerequisites')
                ->where('course_id', $activeCourse->id)
                ->where('prerequisite_course_id', $prerequisiteCourse->id)
                ->count(),
            'archived_reason' => $archivedOffering->archive_reason,
        ];
    }
}
