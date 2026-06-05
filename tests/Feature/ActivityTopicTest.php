<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * V4 ข้อ 1 — หัวข้อกิจกรรมสำเร็จรูปต่อรายวิชา (activity_topics)
 */
class ActivityTopicTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::create(['username' => 'admin_t', 'name' => 'Admin T', 'email' => 'at@e.com', 'password' => Hash::make('x')]);
        UserRole::create(['user_id' => $u->id, 'role' => 'admin', 'is_primary' => true]);
        return $u;
    }

    private function course(): Course
    {
        $dept = Department::create(['name' => 'ภาควิชาทดสอบ']);
        $curr = Curriculum::create(['name' => 'พย.บ. 2565', 'effective_year' => 2565, 'education_level' => 'bachelor', 'duration_years' => 4, 'uses_year_level' => true, 'is_active' => true]);

        return Course::create([
            'course_code' => 'NSBS 301', 'name_th' => 'การพยาบาลผู้ใหญ่', 'curriculum_id' => $curr->id,
            'department_id' => $dept->id, 'credits' => 3, 'lecture_hours' => 2, 'lab_hours' => 2,
            'self_study_hours' => 6, 'capacity' => 30, 'status' => 'active',
        ]);
    }

    public function test_admin_can_save_topics_ordered_and_filtered(): void
    {
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);
        $course = $this->course();

        $this->post(route('admin.courses.topics.sync', $course), [
            'topics' => ['ปฐมนิเทศรายวิชา', '  ', 'การพยาบาลผู้ป่วยวิกฤต', 'การนำเสนอ case'],
        ])->assertSessionHasNoErrors();

        $topics = $course->topics()->get();
        // แถวว่างถูกกรองออก → เหลือ 3
        $this->assertSame(['ปฐมนิเทศรายวิชา', 'การพยาบาลผู้ป่วยวิกฤต', 'การนำเสนอ case'], $topics->pluck('name')->all());
        $this->assertSame([0, 1, 2], $topics->pluck('sort_order')->all());
    }

    public function test_saving_again_replaces_previous_set(): void
    {
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);
        $course = $this->course();
        $course->topics()->create(['name' => 'เก่า 1', 'sort_order' => 0]);
        $course->topics()->create(['name' => 'เก่า 2', 'sort_order' => 1]);

        $this->post(route('admin.courses.topics.sync', $course), ['topics' => ['ใหม่เท่านั้น']])
            ->assertSessionHasNoErrors();

        $this->assertSame(['ใหม่เท่านั้น'], $course->topics()->pluck('name')->all());
    }

    public function test_empty_payload_clears_all_topics(): void
    {
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);
        $course = $this->course();
        $course->topics()->create(['name' => 'ลบทีหลัง', 'sort_order' => 0]);

        $this->post(route('admin.courses.topics.sync', $course), [])->assertSessionHasNoErrors();

        $this->assertSame(0, $course->topics()->count());
    }

    public function test_topics_json_endpoint_returns_list(): void
    {
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);
        $course = $this->course();
        $course->topics()->create(['name' => 'หัวข้อ A', 'sort_order' => 0]);
        $course->topics()->create(['name' => 'หัวข้อ B', 'sort_order' => 1]);

        $this->getJson(route('admin.courses.topics.index', $course))
            ->assertOk()
            ->assertJsonPath('topics.0.name', 'หัวข้อ A')
            ->assertJsonPath('topics.1.name', 'หัวข้อ B');
    }

    public function test_deleting_course_cascades_topics(): void
    {
        $course = $this->course();
        $topic = $course->topics()->create(['name' => 'จะหายไป', 'sort_order' => 0]);

        $course->delete();

        $this->assertDatabaseMissing('activity_topics', ['id' => $topic->id]);
    }
}
