<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\AlertController;
use App\Http\Controllers\AdminSettingController;
use App\Models\AcademicYear;
use App\Models\ActivityType;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\InstructorProfile;
use App\Models\LocationType;
use App\Models\Room;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AlertSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AlertController::flushCache();
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function makeUser(string $role, array $attrs = []): User
    {
        static $seq = 0;
        $seq++;
        $user = User::create(array_merge([
            'username' => "{$role}_{$seq}",
            'name'     => ucfirst($role) . " User {$seq}",
            'email'    => "{$role}_{$seq}@example.com",
            'password' => Hash::make('password'),
        ], $attrs));
        UserRole::create(['user_id' => $user->id, 'role' => $role, 'is_primary' => true]);
        return $user;
    }

    private function makeInstructor(array $profileAttrs = []): User
    {
        $user = $this->makeUser('instructor');
        InstructorProfile::create(array_merge([
            'user_id'      => $user->id,
            'title'        => 'อาจารย์',
            'teaching_pct' => 40,
            'research_pct' => 30,
            'service_pct'  => 15,
            'culture_pct'  => 10,
            'other_pct'    => 5,
        ], $profileAttrs));
        return $user;
    }

    private function seedMinimalCriticals(): void
    {
        $year = AcademicYear::create(['name' => '2568', 'semester' => 1, 'start_date' => '2025-08-01', 'end_date' => '2026-01-31', 'is_active' => true]);
        // V4 ข้อ 8: ปีปัจจุบันต้องมีเทอมในปฏิทินค่าเริ่มต้น (ไม่งั้นเป็น critical)
        $year->fallbackCalendar()->terms()->create(['sequence' => 1, 'name' => 'ภาคเรียนที่ 1', 'start_date' => '2025-08-01', 'end_date' => '2026-01-31']);
        $dept = Department::create(['name' => 'ภาควิชาทดสอบ']);
        $curr = Curriculum::create(['name' => 'หลักสูตรทดสอบ', 'effective_year' => 2568, 'is_active' => true]);
        ActivityType::create(['name' => 'บรรยาย', 'color_code' => '#000000', 'category' => 'lecture']);
        LocationType::create(['name' => 'ห้องบรรยาย', 'is_shared' => false]);

        // An active course with a head_instructor clears the M2 hardening criticals
        // (no_active_course / active_courses_missing_head).
        $head = $this->makeInstructor();
        Course::create([
            'course_code'                 => 'CRT 101',
            'curriculum_id'               => $curr->id,
            'department_id'               => $dept->id,
            'name_th'                     => 'รายวิชาทดสอบ',
            'name_en'                     => 'Critical Test Course',
            'course_type'                 => 'theory',
            'default_year_level'          => 1,
            'default_semester'            => 1,
            'credits'                     => 3,
            'lecture_hours'               => 3,
            'lab_hours'                   => 0,
            'self_study_hours'            => 6,
            'status'                      => 'active',
            'requires_practicum_rotation' => false,
            'head_instructor_id'          => $head->id,
        ]);
    }

    private function defaultPaCriteria(): array
    {
        return AdminSettingController::defaultPaCriteria();
    }

    // ══ RBAC ═════════════════════════════════════════════════════════

    public function test_admin_can_access_alerts_page(): void
    {
        $admin = $this->makeUser('admin');
        $response = $this->actingAs($admin)
            ->withSession(['active_role' => 'admin'])
            ->get(route('admin.alerts'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.alerts.index');
    }

    public function test_non_admin_roles_cannot_access_alerts_page(): void
    {
        foreach (['staff', 'course_head', 'executive', 'instructor'] as $role) {
            $user = $this->makeUser($role);
            $response = $this->actingAs($user)
                ->withSession(['active_role' => $role])
                ->get(route('admin.alerts'));

            $this->assertTrue(
                in_array($response->getStatusCode(), [302, 403]),
                "Role '{$role}' should not access /admin/alerts (got {$response->getStatusCode()})"
            );
        }
    }

    public function test_unauthenticated_user_is_redirected_from_alerts_page(): void
    {
        $this->get(route('admin.alerts'))->assertRedirect(route('login'));
    }

    // ══ getCriticals ═════════════════════════════════════════════════

    public function test_get_criticals_returns_all_five_when_db_is_empty(): void
    {
        $criticals = AlertController::getCriticals();
        $keys = array_column($criticals, 'key');

        $this->assertContains('no_active_year',   $keys);
        $this->assertContains('no_department',    $keys);
        $this->assertContains('no_curriculum',    $keys);
        $this->assertContains('no_activity_type', $keys);
        $this->assertContains('no_location_type', $keys);
    }

    public function test_get_criticals_returns_empty_when_all_data_present(): void
    {
        $this->seedMinimalCriticals();
        $criticals = AlertController::getCriticals();
        $keys = array_column($criticals, 'key');

        $this->assertNotContains('no_active_year',   $keys);
        $this->assertNotContains('no_department',    $keys);
        $this->assertNotContains('no_curriculum',    $keys);
        $this->assertNotContains('no_activity_type', $keys);
        $this->assertNotContains('no_location_type', $keys);
    }

    public function test_get_criticals_includes_pa_violations_when_present(): void
    {
        $this->seedMinimalCriticals();
        SystemSetting::set('pa_criteria_config', json_encode($this->defaultPaCriteria()));

        // sum = 100 but teaching_pct=90 exceeds max=70 → violation
        $this->makeInstructor(['teaching_pct' => 90, 'research_pct' => 5, 'service_pct' => 2, 'culture_pct' => 2, 'other_pct' => 1]);

        $criticals = AlertController::getCriticals();
        $keys = array_column($criticals, 'key');
        $this->assertContains('pa_violations', $keys);
    }

    public function test_pa_violation_critical_links_to_admin_users(): void
    {
        $this->seedMinimalCriticals();
        SystemSetting::set('pa_criteria_config', json_encode($this->defaultPaCriteria()));
        $this->makeInstructor(['teaching_pct' => 90, 'research_pct' => 5, 'service_pct' => 2, 'culture_pct' => 2, 'other_pct' => 1]);

        $critical = collect(AlertController::getCriticals())->firstWhere('key', 'pa_violations');

        $this->assertSame(route('admin.users'), $critical['link']);
        $this->assertSame('ดูรายละเอียด', $critical['linkTxt']);
    }

    public function test_alerts_page_pa_violation_edit_link_targets_instructor_modal_deep_link(): void
    {
        $admin = $this->makeUser('admin');
        $this->seedMinimalCriticals();
        SystemSetting::set('pa_criteria_config', json_encode($this->defaultPaCriteria()));
        $instructor = $this->makeInstructor(['teaching_pct' => 90, 'research_pct' => 5, 'service_pct' => 2, 'culture_pct' => 2, 'other_pct' => 1]);

        $response = $this->actingAs($admin)
            ->withSession(['active_role' => 'admin'])
            ->get(route('admin.alerts'));

        $response->assertStatus(200);
        $response->assertSee(route('admin.master_data', [
            'tab' => 'instructors',
            'edit_instructor' => $instructor->id,
        ]));
    }

    public function test_alerts_page_warning_detail_links_target_record_deep_links(): void
    {
        $admin = $this->makeUser('admin');
        $this->seedMinimalCriticals();

        $department = Department::firstOrFail();
        $locationType = LocationType::firstOrFail();
        $room = Room::create([
            'location_type_id' => $locationType->id,
            'room_code' => 'WARN-01',
            'room_name' => '',
            'capacity' => 0,
            'status' => 'active',
        ]);
        $course = Course::where('course_code', 'CRT 101')->firstOrFail();

        $response = $this->actingAs($admin)
            ->withSession(['active_role' => 'admin'])
            ->get(route('admin.alerts'));

        $response->assertStatus(200);
        $response->assertSee(route('admin.master_data', [
            'tab' => 'departments',
            'edit_department' => $department->id,
        ]));
        $response->assertSee(route('admin.master_data', [
            'tab' => 'location_types',
            'edit_room' => $room->id,
        ]));
        $response->assertSee(route('admin.master_data', [
            'tab' => 'courses',
            'edit_course' => $course->id,
        ]));
    }

    public function test_active_courses_missing_head_critical_has_detail_anchor_and_edit_deep_link(): void
    {
        $admin = $this->makeUser('admin');
        $this->seedMinimalCriticals();
        $curriculum = Curriculum::firstOrFail();
        $department = Department::firstOrFail();
        $course = Course::create([
            'course_code' => 'NOHEAD 101',
            'curriculum_id' => $curriculum->id,
            'department_id' => $department->id,
            'name_th' => 'รายวิชาไม่มีหัวหน้า',
            'name_en' => 'Missing Head Course',
            'course_type' => 'theory',
            'default_year_level' => 1,
            'default_semester' => 1,
            'credits' => 3,
            'lecture_hours' => 3,
            'lab_hours' => 0,
            'self_study_hours' => 6,
            'capacity' => 30,
            'status' => 'active',
            'requires_practicum_rotation' => false,
            'head_instructor_id' => null,
        ]);

        $critical = collect(AlertController::getCriticals())->firstWhere('key', 'active_courses_missing_head');
        $this->assertSame(route('admin.alerts') . '#active-courses-missing-head', $critical['link']);
        $this->assertSame('ดูรายละเอียด', $critical['linkTxt']);

        $response = $this->actingAs($admin)
            ->withSession(['active_role' => 'admin'])
            ->get(route('admin.alerts'));

        $response->assertStatus(200);
        $response->assertSee('active-courses-missing-head');
        $response->assertSee(route('admin.master_data', [
            'tab' => 'courses',
            'edit_course' => $course->id,
        ]));
    }

    // ══ getSummary ════════════════════════════════════════════════════

    public function test_get_summary_counts_criticals_correctly(): void
    {
        $summary = AlertController::getSummary();
        // All 5 master-data criticals should fire on empty DB
        $this->assertGreaterThanOrEqual(5, $summary['critical']);
    }

    public function test_get_summary_returns_zero_critical_when_data_complete(): void
    {
        $this->seedMinimalCriticals();
        AlertController::flushCache();
        $summary = AlertController::getSummary();
        $this->assertEquals(0, $summary['critical']);
    }

    public function test_get_summary_does_not_have_courses_key(): void
    {
        $summary = AlertController::getSummary();
        $this->assertArrayNotHasKey('courses', $summary);
    }

    public function test_get_summary_does_not_have_instructors_key(): void
    {
        $summary = AlertController::getSummary();
        $this->assertArrayNotHasKey('instructors', $summary);
    }

    public function test_get_summary_is_cached(): void
    {
        $this->seedMinimalCriticals();
        AlertController::flushCache();

        AlertController::getSummary();
        $this->assertTrue(Cache::has('tpss_alert_summary'));
    }

    // ══ flushCache ════════════════════════════════════════════════════

    public function test_flush_cache_removes_cached_summary(): void
    {
        Cache::put('tpss_alert_summary', ['critical' => 99, 'warnings' => 0, 'total' => 99], 300);
        AlertController::flushCache();
        $this->assertFalse(Cache::has('tpss_alert_summary'));
    }

    public function test_cache_flushed_after_master_data_write(): void
    {
        $this->seedMinimalCriticals();
        $admin = $this->makeUser('admin');

        // Prime the cache
        AlertController::getSummary();
        $this->assertTrue(Cache::has('tpss_alert_summary'));

        // Write a new department — should flush cache
        $this->actingAs($admin)
            ->withSession(['active_role' => 'admin'])
            ->post(route('admin.departments.store'), ['name' => 'ภาควิชาใหม่']);

        $this->assertFalse(Cache::has('tpss_alert_summary'));
    }

    // ══ getPaViolations ═══════════════════════════════════════════════

    public function test_no_violations_when_pa_pcts_sum_100_and_within_range(): void
    {
        SystemSetting::set('pa_criteria_config', json_encode($this->defaultPaCriteria()));
        // teaching=40, research=30, service=15, culture=10, other=5 → sum=100, all within อาจารย์ range
        $this->makeInstructor();

        $violations = AlertController::getPaViolations();
        $this->assertEmpty($violations);
    }

    public function test_violation_when_pa_pcts_do_not_sum_to_100(): void
    {
        SystemSetting::set('pa_criteria_config', json_encode($this->defaultPaCriteria()));
        $this->makeInstructor(['teaching_pct' => 40, 'research_pct' => 30, 'service_pct' => 10, 'culture_pct' => 10, 'other_pct' => 5]);
        // sum = 95 ≠ 100

        $violations = AlertController::getPaViolations();
        $this->assertNotEmpty($violations);
        $this->assertStringContainsString('95%', $violations[0]['issues'][0]);
    }

    public function test_violation_when_pa_value_exceeds_max(): void
    {
        SystemSetting::set('pa_criteria_config', json_encode($this->defaultPaCriteria()));
        // teaching=90 > max=70 for อาจารย์
        $this->makeInstructor(['teaching_pct' => 90, 'research_pct' => 5, 'service_pct' => 2, 'culture_pct' => 2, 'other_pct' => 1]);

        $violations = AlertController::getPaViolations();
        $this->assertNotEmpty($violations);
        $issues = collect($violations[0]['issues']);
        $this->assertTrue($issues->contains(fn($i) => str_contains($i, 'สอน')));
    }

    public function test_violation_when_pa_value_below_min(): void
    {
        SystemSetting::set('pa_criteria_config', json_encode($this->defaultPaCriteria()));
        // research=5 < min=20 for อาจารย์
        $this->makeInstructor(['teaching_pct' => 70, 'research_pct' => 5, 'service_pct' => 15, 'culture_pct' => 5, 'other_pct' => 5]);

        $violations = AlertController::getPaViolations();
        $this->assertNotEmpty($violations);
        $issues = collect($violations[0]['issues']);
        $this->assertTrue($issues->contains(fn($i) => str_contains($i, 'วิจัย')));
    }

    public function test_sum_violation_does_not_also_report_range_violations(): void
    {
        SystemSetting::set('pa_criteria_config', json_encode($this->defaultPaCriteria()));
        // sum = 95 ≠ 100 AND teaching=90 > max=70, but range check should be skipped
        $this->makeInstructor(['teaching_pct' => 90, 'research_pct' => 5, 'service_pct' => 0, 'culture_pct' => 0, 'other_pct' => 0]);

        $violations = AlertController::getPaViolations();
        $this->assertCount(1, $violations[0]['issues']);
        $this->assertStringContainsString('95%', $violations[0]['issues'][0]);
    }

    public function test_instructor_without_profile_is_skipped(): void
    {
        SystemSetting::set('pa_criteria_config', json_encode($this->defaultPaCriteria()));
        $this->makeUser('instructor'); // no InstructorProfile

        $violations = AlertController::getPaViolations();
        $this->assertEmpty($violations);
    }

    public function test_pa_falls_back_to_default_when_config_is_empty(): void
    {
        SystemSetting::set('pa_criteria_config', '{}');
        // sum=100 and within default อาจารย์ range → no violation
        $this->makeInstructor();

        $violations = AlertController::getPaViolations();
        $this->assertEmpty($violations);
    }

    public function test_pa_falls_back_to_default_when_config_is_old_string_format(): void
    {
        SystemSetting::set('pa_criteria_config', json_encode([
            'อาจารย์' => ['t' => '20-70%', 'r' => '20-70%', 's' => '5-20%', 'c' => '5-15%', 'o' => '0-20%'],
        ]));
        // Should fall back to default {min,max} and not throw
        $this->makeInstructor();

        $violations = AlertController::getPaViolations();
        $this->assertEmpty($violations);
    }

    // ══ paGroup mapping (via getPaViolations) ═════════════════════════

    public function test_pa_group_clinical_assistant_uses_correct_criteria(): void
    {
        SystemSetting::set('pa_criteria_config', json_encode([
            'ผู้ช่วยอาจารย์_คลินิก' => [
                't' => ['min' => 0,  'max' => 10],
                'r' => ['min' => 0,  'max' => 5],
                's' => ['min' => 70, 'max' => 80],
                'c' => ['min' => 0,  'max' => 5],
                'o' => ['min' => 0,  'max' => 10],
            ],
        ]));

        // t=5,r=5,s=75,c=5,o=10 → sum=100, all within คลินิก range → no violation
        $this->makeInstructor([
            'title'        => 'ผู้ช่วยอาจารย์ (คลินิก)',
            'teaching_pct' => 5,
            'research_pct' => 5,
            'service_pct'  => 75,
            'culture_pct'  => 5,
            'other_pct'    => 10,
        ]);

        $violations = AlertController::getPaViolations();
        $this->assertEmpty($violations);
    }

    public function test_pa_group_clinical_assistant_violation_when_out_of_range(): void
    {
        SystemSetting::set('pa_criteria_config', json_encode([
            'ผู้ช่วยอาจารย์_คลินิก' => [
                't' => ['min' => 0,  'max' => 10],
                'r' => ['min' => 0,  'max' => 5],
                's' => ['min' => 70, 'max' => 80],
                'c' => ['min' => 0,  'max' => 5],
                'o' => ['min' => 0,  'max' => 10],
            ],
        ]));

        // teaching_pct=50 > max=10 for คลินิก → violation
        $this->makeInstructor([
            'title'        => 'ผู้ช่วยอาจารย์ (คลินิก)',
            'teaching_pct' => 50,
            'research_pct' => 0,
            'service_pct'  => 45,
            'culture_pct'  => 5,
            'other_pct'    => 0,
        ]);

        $violations = AlertController::getPaViolations();
        $this->assertNotEmpty($violations);
        $issues = collect($violations[0]['issues']);
        $this->assertTrue($issues->contains(fn($i) => str_contains($i, 'สอน')));
    }

    // ══ is_shared (open space) ═════════════════════════════════════════════════

    public function test_room_missing_capacity_in_standard_type_triggers_warning(): void
    {
        $lt = LocationType::create(['name' => 'ห้องบรรยาย', 'is_shared' => false]);
        Room::create(['room_code' => 'R01', 'room_name' => 'ห้อง 1', 'location_type_id' => $lt->id, 'status' => 'active', 'capacity' => 0]);

        $summary = AlertController::getSummary();
        $this->assertGreaterThan(0, $summary['rooms']);
    }

    public function test_room_missing_capacity_in_open_space_type_does_not_trigger_warning(): void
    {
        $lt = LocationType::create(['name' => 'ชุมชน', 'is_shared' => true]);
        Room::create(['room_code' => 'C01', 'room_name' => 'ชุมชนทดสอบ', 'location_type_id' => $lt->id, 'status' => 'active', 'capacity' => 0]);

        AlertController::flushCache();
        $summary = AlertController::getSummary();
        $this->assertEquals(0, $summary['rooms']);
    }

    public function test_room_with_capacity_in_standard_type_does_not_trigger_warning(): void
    {
        $lt = LocationType::create(['name' => 'ห้องบรรยาย', 'is_shared' => false]);
        Room::create(['room_code' => 'R02', 'room_name' => 'ห้อง 2', 'location_type_id' => $lt->id, 'status' => 'active', 'capacity' => 30]);

        AlertController::flushCache();
        $summary = AlertController::getSummary();
        $this->assertEquals(0, $summary['rooms']);
    }

    // ══ Dismissed warnings ═══════════════════════════════════════════

    public function test_dismissed_warnings_excluded_from_summary_count(): void
    {
        $lt = LocationType::create(['name' => 'ห้องบรรยาย', 'is_shared' => false]);
        Room::create(['room_code' => 'R01', 'room_name' => 'ห้อง 1', 'location_type_id' => $lt->id, 'status' => 'active', 'capacity' => 0]);

        SystemSetting::set('dismissed_warnings', json_encode(['rooms']));
        AlertController::flushCache();
        $summary = AlertController::getSummary();

        $this->assertEquals(0, $summary['rooms']);
    }

    public function test_non_dismissed_warnings_still_counted(): void
    {
        $lt = LocationType::create(['name' => 'ห้องบรรยาย', 'is_shared' => false]);
        Room::create(['room_code' => 'R01', 'room_name' => 'ห้อง 1', 'location_type_id' => $lt->id, 'status' => 'active', 'capacity' => 0]);
        Department::create(['name' => 'ภาควิชาไม่มีหัวหน้า']);

        SystemSetting::set('dismissed_warnings', json_encode(['rooms']));
        AlertController::flushCache();
        $summary = AlertController::getSummary();

        $this->assertEquals(0, $summary['rooms']);
        $this->assertGreaterThan(0, $summary['departments']);
    }

    public function test_update_dismissed_saves_to_system_settings(): void
    {
        $this->seedMinimalCriticals();
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)
            ->withSession(['active_role' => 'admin'])
            ->post(route('admin.alerts.dismissed'), ['dismissed' => ['rooms', 'course_staff']]);

        $saved = json_decode(SystemSetting::get('dismissed_warnings', '[]'), true);
        $this->assertContains('rooms', $saved);
        $this->assertContains('course_staff', $saved);
    }

    public function test_update_dismissed_rejects_invalid_keys(): void
    {
        $this->seedMinimalCriticals();
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)
            ->withSession(['active_role' => 'admin'])
            ->post(route('admin.alerts.dismissed'), ['dismissed' => ['rooms', 'no_active_year', 'fake_key']]);

        $saved = json_decode(SystemSetting::get('dismissed_warnings', '[]'), true);
        $this->assertContains('rooms', $saved);
        $this->assertNotContains('no_active_year', $saved);
        $this->assertNotContains('fake_key', $saved);
    }

    public function test_update_dismissed_flushes_cache(): void
    {
        $this->seedMinimalCriticals();
        $admin = $this->makeUser('admin');

        AlertController::getSummary();
        $this->assertTrue(Cache::has('tpss_alert_summary'));

        $this->actingAs($admin)
            ->withSession(['active_role' => 'admin'])
            ->post(route('admin.alerts.dismissed'), ['dismissed' => ['rooms']]);

        $this->assertFalse(Cache::has('tpss_alert_summary'));
    }

    public function test_get_dismissed_warnings_returns_empty_array_by_default(): void
    {
        $this->assertSame([], AlertController::getDismissedWarnings());
    }

    // ══ Alerts page view ═════════════════════════════════════════════

    public function test_alerts_page_shows_all_critical_sections(): void
    {
        $admin = $this->makeUser('admin');
        $response = $this->actingAs($admin)
            ->withSession(['active_role' => 'admin'])
            ->get(route('admin.alerts'));

        $response->assertStatus(200);
        $response->assertSee('Critical');
        $response->assertSee('ไม่มีปีการศึกษาที่ใช้งานอยู่');
    }

    public function test_alerts_page_shows_all_clear_when_data_complete(): void
    {
        $this->seedMinimalCriticals();
        $admin = $this->makeUser('admin');

        $response = $this->actingAs($admin)
            ->withSession(['active_role' => 'admin'])
            ->get(route('admin.alerts'));

        $response->assertStatus(200);
        $response->assertSee('ไม่พบปัญหา Critical');
    }
}
