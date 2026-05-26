<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseRole;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\InstructorProfile;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CoursePoolManagementTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCourseRoles();
    }

    public function test_course_pool_pages_are_removed(): void
    {
        $admin = $this->makeUser('admin');
        $this->makeCourse(['course_code' => 'NSBS 111']);

        $this->actingAsRole($admin, 'admin');

        $this->get('/admin/course-pool')->assertNotFound();
        $this->get('/admin/course-pool/NSBS%20111')->assertNotFound();
    }

    public function test_master_data_course_modal_contains_assignment_controls(): void
    {
        $admin = $this->makeUser('admin');
        $this->makeCourse(['course_code' => 'NSBS 111']);

        $this->actingAsRole($admin, 'admin');

        $this->get(route('admin.master_data', ['tab' => 'courses']))
            ->assertOk()
            ->assertSee('ผู้รับผิดชอบรายวิชา')
            ->assertSee('หัวหน้าวิชา')
            ->assertSee('เจ้าหน้าที่ดูแลรายวิชา')
            ->assertSee('อาจารย์ผู้สอน')
            ->assertDontSee('/admin/course-pool/NSBS%20111', false);
    }

    public function test_admin_can_update_course_assignments_from_course_modal_form(): void
    {
        $admin = $this->makeUser('admin');
        $head = $this->makeUser('course_head');
        $staff = $this->makeUser('staff');
        $instructor = $this->makeUser('instructor');
        $role = CourseRole::where('name_th', 'อาจารย์พี่เลี้ยง')->first();
        $course = $this->makeCourse(['head_instructor_id' => null]);

        $this->actingAsRole($admin, 'admin');

        $this->put(route('admin.courses.update', $course), $this->coursePayload($course, [
            'head_instructor_id' => $head->id,
            'staff_ids' => [$staff->id],
            'instructor_ids' => [$instructor->id],
            'instructor_role_ids' => [
                $instructor->id => $role->id,
            ],
        ]))
            ->assertRedirect(route('admin.master_data', ['tab' => 'courses']))
            ->assertSessionHas('success');

        $this->assertSame($head->id, $course->fresh()->head_instructor_id);
        $this->assertDatabaseHas('course_staff', [
            'course_id' => $course->id,
            'user_id' => $staff->id,
        ]);
        $this->assertDatabaseHas('course_instructors', [
            'course_id' => $course->id,
            'user_id' => $instructor->id,
            'course_role_id' => $role->id,
        ]);
    }

    public function test_active_course_requires_head_instructor_when_assignment_is_editable(): void
    {
        $admin = $this->makeUser('admin');
        $course = $this->makeCourse(['head_instructor_id' => null, 'status' => 'active']);

        $this->actingAsRole($admin, 'admin');

        $this->put(route('admin.courses.update', $course), $this->coursePayload($course, [
            'head_instructor_id' => null,
            'status' => 'active',
        ]))
            ->assertSessionHasErrors('head_instructor_id');

        $this->assertNull($course->fresh()->head_instructor_id);
    }

    public function test_inactive_course_can_be_saved_without_head_instructor(): void
    {
        $admin = $this->makeUser('admin');
        $course = $this->makeCourse(['head_instructor_id' => null, 'status' => 'inactive']);

        $this->actingAsRole($admin, 'admin');

        $this->put(route('admin.courses.update', $course), $this->coursePayload($course, [
            'head_instructor_id' => null,
            'status' => 'inactive',
        ]))
            ->assertRedirect(route('admin.master_data', ['tab' => 'courses']))
            ->assertSessionHas('success');

        $this->assertNull($course->fresh()->head_instructor_id);
    }

    public function test_locked_course_keeps_assignment_template_when_course_form_submits(): void
    {
        $admin = $this->makeUser('admin');
        $oldHead = $this->makeUser('course_head');
        $newHead = $this->makeUser('course_head');
        $staff = $this->makeUser('staff');
        $instructor = $this->makeUser('instructor');
        $course = $this->makeCourse(['head_instructor_id' => $oldHead->id]);
        $year = $this->makeYear(['phase' => 'scheduling']);

        CourseOffering::create([
            'course_id' => $course->id,
            'academic_year_id' => $year->id,
            'coordinator_id' => $oldHead->id,
            'approval_status' => 'draft',
        ]);

        $this->actingAsRole($admin, 'admin');

        $this->put(route('admin.courses.update', $course), $this->coursePayload($course, [
            'name_th' => 'แก้ไขชื่อรายวิชา',
            'head_instructor_id' => $newHead->id,
            'staff_ids' => [$staff->id],
            'instructor_ids' => [$instructor->id],
        ]))
            ->assertRedirect(route('admin.master_data', ['tab' => 'courses']))
            ->assertSessionHas('success');

        $course->refresh();
        $this->assertSame('แก้ไขชื่อรายวิชา', $course->name_th);
        $this->assertSame($oldHead->id, $course->head_instructor_id);
        $this->assertDatabaseMissing('course_staff', [
            'course_id' => $course->id,
            'user_id' => $staff->id,
        ]);
        $this->assertDatabaseMissing('course_instructors', [
            'course_id' => $course->id,
            'user_id' => $instructor->id,
        ]);
    }

    private function coursePayload(Course $course, array $overrides = []): array
    {
        return array_merge([
            '_form' => 'course',
            '_course_form_mode' => 'edit',
            '_course_route_key' => $course->course_code,
            '_course_id' => $course->id,
            'course_code' => $course->course_code,
            'name_th' => $course->name_th,
            'name_en' => $course->name_en,
            'curriculum_id' => $course->curriculum_id,
            'department_id' => $course->department_id,
            'head_instructor_id' => $course->head_instructor_id,
            'default_year_level' => $course->default_year_level,
            'is_required' => $course->is_required ? '1' : '0',
            'default_semester' => $course->default_semester,
            'credits' => $course->credits,
            'lecture_hours' => $course->lecture_hours,
            'lab_hours' => $course->lab_hours,
            'self_study_hours' => $course->self_study_hours,
            'capacity' => $course->capacity,
            'color_code' => $course->color_code ?? '#3b82f6',
            'status' => $course->status,
            'requires_practicum_rotation' => $course->requires_practicum_rotation ? '1' : '0',
            'prerequisite_ids' => [],
            'staff_ids' => [],
            'instructor_ids' => [],
            'instructor_role_ids' => [],
        ], $overrides);
    }

    private function seedCourseRoles(): void
    {
        foreach ([
            ['name_th' => 'หัวหน้าวิชา', 'sort_order' => 1],
            ['name_th' => 'เลขานุการวิชา', 'sort_order' => 2],
            ['name_th' => 'อาจารย์ผู้สอน', 'sort_order' => 3],
            ['name_th' => 'อาจารย์ประจำกลุ่ม', 'sort_order' => 4],
            ['name_th' => 'อาจารย์พี่เลี้ยง', 'sort_order' => 5],
        ] as $role) {
            CourseRole::firstOrCreate(['name_th' => $role['name_th']], $role);
        }
    }

    private function actingAsRole(User $user, string $role): void
    {
        $this->actingAs($user);
        $this->withSession(['active_role' => $role]);
    }

    private function makeUser(string $role, bool $active = true, bool $withProfile = true): User
    {
        $n = $this->sequence++;
        $user = User::create([
            'username' => "u_{$role}_{$n}",
            'name' => "{$role} {$n}",
            'email' => "u{$n}@test.example",
            'password' => Hash::make('password'),
            'is_active' => $active,
        ]);
        UserRole::create(['user_id' => $user->id, 'role' => $role, 'is_primary' => true]);

        if ($withProfile && in_array($role, ['instructor', 'course_head'], true)) {
            InstructorProfile::create([
                'user_id' => $user->id,
                'title' => 'อาจารย์',
                'department_id' => $this->department()->id,
            ]);
        }

        return $user;
    }

    private function makeCourse(array $overrides = []): Course
    {
        $n = $this->sequence++;

        return Course::create(array_merge([
            'course_code' => "POOL{$n}",
            'curriculum_id' => $this->curriculum()->id,
            'department_id' => $this->department()->id,
            'name_th' => "วิชา {$n}",
            'name_en' => "Course {$n}",
            'course_type' => 'theory',
            'default_year_level' => 1,
            'default_semester' => 1,
            'credits' => 3,
            'lecture_hours' => 3,
            'lab_hours' => 0,
            'self_study_hours' => 6,
            'capacity' => 120,
            'color_code' => '#3b82f6',
            'status' => 'active',
            'requires_practicum_rotation' => false,
        ], $overrides));
    }

    private function makeYear(array $overrides = []): AcademicYear
    {
        $n = $this->sequence++;

        return AcademicYear::create(array_merge([
            'name' => "256{$n}",
            'semester' => 1,
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
            'phase' => 'preparation',
        ], $overrides));
    }

    private function department(): Department
    {
        return Department::firstOrCreate(['name' => 'Pool Test Dept']);
    }

    private function curriculum(): Curriculum
    {
        return Curriculum::firstOrCreate(['name' => 'Pool Test Curriculum'], [
            'effective_year' => 2569,
            'is_active' => true,
        ]);
    }

    public function test_instructor_deviation_page_shows_diff_buckets_for_offering(): void
    {
        $admin = $this->makeUser('admin');
        $head = $this->makeUser('course_head');
        $templateOnly = $this->makeUser('instructor');   // อยู่ใน template แต่ Course Head ไม่ได้ใช้
        $sharedSame = $this->makeUser('instructor');     // อยู่ทั้งสองฝั่ง role เดียวกัน
        $sharedDiff = $this->makeUser('instructor');     // อยู่ทั้งสองฝั่ง แต่ role ต่าง
        $addedOnly = $this->makeUser('instructor');      // Course Head เพิ่มเข้ามาใหม่

        $course = $this->makeCourse(['head_instructor_id' => $head->id, 'course_code' => 'NSBS 999']);
        $year = $this->makeYear(['phase' => 'scheduling', 'name' => '2569', 'semester' => 1]);

        $instructorRole = CourseRole::where('name_th', 'อาจารย์ผู้สอน')->first();
        $assistantRole = CourseRole::where('name_th', 'อาจารย์พี่เลี้ยง')->first();

        // Template: templateOnly + sharedSame + sharedDiff (instructor role)
        $course->instructors()->attach([
            $templateOnly->id => ['course_role_id' => $instructorRole->id],
            $sharedSame->id   => ['course_role_id' => $instructorRole->id],
            $sharedDiff->id   => ['course_role_id' => $instructorRole->id],
        ]);

        $offering = CourseOffering::create([
            'course_id' => $course->id,
            'academic_year_id' => $year->id,
            'coordinator_id' => $head->id,
            'approval_status' => 'draft',
        ]);

        // Actual pool: sharedSame (same role) + sharedDiff (assistant role) + addedOnly
        $offering->instructorPool()->attach([
            $head->id        => ['role_in_course' => 'coordinator', 'course_role_id' => null],
            $sharedSame->id  => ['role_in_course' => 'instructor',  'course_role_id' => $instructorRole->id],
            $sharedDiff->id  => ['role_in_course' => 'instructor',  'course_role_id' => $assistantRole->id],
            $addedOnly->id   => ['role_in_course' => 'instructor',  'course_role_id' => $instructorRole->id],
        ]);

        $this->actingAsRole($admin, 'admin');

        $response = $this->get(route('admin.courses.instructor_deviation', $course));

        $response->assertOk();
        $response->assertSee('NSBS 999');
        $response->assertSee('ติดตามการใช้แม่แบบ');
        $response->assertSee('เพิ่มจากแม่แบบ');
        $response->assertSee('ไม่ได้ใช้จากแม่แบบ');
        $response->assertSee('เปลี่ยนบทบาท');
        $response->assertSee($addedOnly->name);    // โผล่ใน "เพิ่มจากแม่แบบ"
        $response->assertSee($templateOnly->name); // โผล่ใน "ไม่ได้ใช้จากแม่แบบ" + template panel
        $response->assertSee($sharedDiff->name);   // โผล่ใน "เปลี่ยนบทบาท" + template panel
        // หมายเหตุ: $sharedSame อยู่ใน template panel ด้วย เลยไม่ทำ assertDontSee ที่ระดับ page
        // จะดูว่า diff ถูกหรือไม่ผ่านจำนวน badge ในแถวประวัติ
        $response->assertSee('แก้ไข 3 รายการ');
    }

    public function test_instructor_deviation_shows_zero_when_offering_matches_template(): void
    {
        $admin = $this->makeUser('admin');
        $head = $this->makeUser('course_head');
        $instructor = $this->makeUser('instructor');

        $course = $this->makeCourse(['head_instructor_id' => $head->id]);
        $year = $this->makeYear(['phase' => 'scheduling']);
        $role = CourseRole::where('name_th', 'อาจารย์ผู้สอน')->first();

        $course->instructors()->attach($instructor->id, ['course_role_id' => $role->id]);

        $offering = CourseOffering::create([
            'course_id' => $course->id,
            'academic_year_id' => $year->id,
            'coordinator_id' => $head->id,
            'approval_status' => 'draft',
        ]);
        $offering->instructorPool()->attach([
            $head->id       => ['role_in_course' => 'coordinator', 'course_role_id' => null],
            $instructor->id => ['role_in_course' => 'instructor',  'course_role_id' => $role->id],
        ]);

        $this->actingAsRole($admin, 'admin');

        $this->get(route('admin.courses.instructor_deviation', $course))
            ->assertOk()
            ->assertSee('ตรงกับแม่แบบ');
    }

    public function test_master_data_courses_tab_shows_deviation_link_only_for_locked_courses(): void
    {
        $admin = $this->makeUser('admin');
        $head = $this->makeUser('course_head');

        $lockedCourse = $this->makeCourse(['head_instructor_id' => $head->id, 'course_code' => 'LOCK 111']);
        $unlockedCourse = $this->makeCourse(['head_instructor_id' => $head->id, 'course_code' => 'OPEN 111']);

        // Lock the first course by giving it an offering in scheduling phase
        $schedulingYear = $this->makeYear(['phase' => 'scheduling']);
        CourseOffering::create([
            'course_id' => $lockedCourse->id,
            'academic_year_id' => $schedulingYear->id,
            'coordinator_id' => $head->id,
            'approval_status' => 'draft',
        ]);

        $this->actingAsRole($admin, 'admin');

        $response = $this->get(route('admin.master_data', ['tab' => 'courses']));

        $response->assertOk();
        $response->assertSee(route('admin.courses.instructor_deviation', $lockedCourse), false);
        $response->assertDontSee(route('admin.courses.instructor_deviation', $unlockedCourse), false);
    }

    public function test_instructor_deviation_includes_all_phases_for_pattern_analysis(): void
    {
        // Admin ต้องเห็นทุกรอบ (รวม preparation + เก่า ๆ ที่เผยแพร่แล้ว) เพื่อดู pattern ข้ามปี
        $admin = $this->makeUser('admin');
        $head = $this->makeUser('course_head');
        $course = $this->makeCourse(['head_instructor_id' => $head->id]);

        $prepYear = $this->makeYear(['phase' => 'preparation', 'name' => '2570', 'semester' => 1]);
        $publishedYear = $this->makeYear(['phase' => 'published', 'name' => '2568', 'semester' => 2]);

        CourseOffering::create([
            'course_id' => $course->id,
            'academic_year_id' => $prepYear->id,
            'coordinator_id' => $head->id,
            'approval_status' => 'draft',
        ]);
        CourseOffering::create([
            'course_id' => $course->id,
            'academic_year_id' => $publishedYear->id,
            'coordinator_id' => $head->id,
            'approval_status' => 'published',
        ]);

        $this->actingAsRole($admin, 'admin');

        $this->get(route('admin.courses.instructor_deviation', $course))
            ->assertOk()
            ->assertSee('2570')
            ->assertSee('2568')
            ->assertSee('เตรียมข้อมูล')
            ->assertSee('เผยแพร่แล้ว');
    }

    public function test_instructor_deviation_shows_template_panel_and_course_status(): void
    {
        $admin = $this->makeUser('admin');
        $head = $this->makeUser('course_head');
        $instructor = $this->makeUser('instructor');

        $course = $this->makeCourse([
            'head_instructor_id' => $head->id,
            'course_code' => 'STAT 111',
            'status' => 'active',
            'is_required' => true,
        ]);

        $role = CourseRole::where('name_th', 'อาจารย์ผู้สอน')->first();
        $course->instructors()->attach($instructor->id, ['course_role_id' => $role->id]);

        $this->actingAsRole($admin, 'admin');

        $this->get(route('admin.courses.instructor_deviation', $course))
            ->assertOk()
            ->assertSee('STAT 111')
            ->assertSee('เปิดสอน')
            ->assertSee('วิชาบังคับ')
            ->assertSee('แม่แบบผู้รับผิดชอบ')
            ->assertSee('แก้ไขแม่แบบ')
            ->assertSee($head->name)
            ->assertSee($instructor->name);
    }

    public function test_master_data_shows_deviation_dot_only_when_course_actually_deviates(): void
    {
        $admin = $this->makeUser('admin');
        $head = $this->makeUser('course_head');
        $instructor = $this->makeUser('instructor');

        $year = $this->makeYear(['phase' => 'scheduling']);
        $role = CourseRole::where('name_th', 'อาจารย์ผู้สอน')->first();

        // วิชา A: template ตรงกับ offering ทุกอย่าง → ไม่ควรมี red dot
        $matchCourse = $this->makeCourse([
            'head_instructor_id' => $head->id,
            'course_code' => 'MATCH 1',
            'requires_practicum_rotation' => false,
        ]);
        $matchCourse->instructors()->attach($instructor->id, ['course_role_id' => $role->id]);
        $matchOffering = CourseOffering::create([
            'course_id' => $matchCourse->id,
            'academic_year_id' => $year->id,
            'coordinator_id' => $head->id,
            'approval_status' => 'draft',
            'requires_practicum_rotation' => false,
        ]);
        $matchOffering->instructorPool()->attach([
            $head->id       => ['role_in_course' => 'coordinator', 'course_role_id' => null],
            $instructor->id => ['role_in_course' => 'instructor',  'course_role_id' => $role->id],
        ]);

        // วิชา B: rotation override → ควรมี red dot
        $devCourse = $this->makeCourse([
            'head_instructor_id' => $head->id,
            'course_code' => 'DEV 1',
            'requires_practicum_rotation' => false,
        ]);
        $year2 = $this->makeYear(['phase' => 'scheduling']);
        CourseOffering::create([
            'course_id' => $devCourse->id,
            'academic_year_id' => $year2->id,
            'coordinator_id' => $head->id,
            'approval_status' => 'draft',
            'requires_practicum_rotation' => true,
        ]);

        $this->actingAsRole($admin, 'admin');

        $response = $this->get(route('admin.master_data', ['tab' => 'courses']));

        $response->assertOk();
        // ทั้งสองวิชาเห็นปุ่ม deviation (locked offering)
        $response->assertSee(route('admin.courses.instructor_deviation', $matchCourse), false);
        $response->assertSee(route('admin.courses.instructor_deviation', $devCourse), false);
        // มี red dot 1 ครั้ง — สำหรับ DEV 1 เท่านั้น
        $this->assertSame(1, substr_count($response->getContent(), 'data-testid="courses-deviation-dot"'));
    }

    public function test_instructor_deviation_detects_rotation_override_from_course_head(): void
    {
        $admin = $this->makeUser('admin');
        $head = $this->makeUser('course_head');

        $course = $this->makeCourse([
            'head_instructor_id' => $head->id,
            'course_code' => 'ROT 111',
            'requires_practicum_rotation' => false, // แม่แบบ: ไม่หมุนเวียน
        ]);
        $year = $this->makeYear(['phase' => 'scheduling']);

        // Offering override เป็น "มีการหมุนเวียน" + แนบเหตุผล
        CourseOffering::create([
            'course_id' => $course->id,
            'academic_year_id' => $year->id,
            'coordinator_id' => $head->id,
            'approval_status' => 'draft',
            'requires_practicum_rotation' => true,
            'practicum_note' => 'ปีนี้ต้องหมุนเวียนเพราะแหล่งฝึกใหม่',
        ]);

        $this->actingAsRole($admin, 'admin');

        $response = $this->get(route('admin.courses.instructor_deviation', $course));

        $response->assertOk();
        $response->assertSee('การตั้งค่าระดับรอบเปิดสอนต่างจากแม่แบบ');
        $response->assertSee('การหมุนเวียนแหล่งฝึก');
        $response->assertSee('ปีนี้ต้องหมุนเวียนเพราะแหล่งฝึกใหม่');
        $response->assertSee('แก้ไข 1 รายการ');
    }

    public function test_instructor_deviation_shows_empty_state_when_no_offerings_at_all(): void
    {
        $admin = $this->makeUser('admin');
        $head = $this->makeUser('course_head');
        $course = $this->makeCourse(['head_instructor_id' => $head->id]);

        $this->actingAsRole($admin, 'admin');

        $this->get(route('admin.courses.instructor_deviation', $course))
            ->assertOk()
            ->assertSee('ยังไม่มีรอบเปิดสอนของวิชานี้');
    }
}
