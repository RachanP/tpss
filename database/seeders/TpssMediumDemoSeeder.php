<?php

namespace Database\Seeders;

use App\Jobs\ConflictRecomputeJob;
use App\Models\AcademicYear;
use App\Models\ActivityType;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseRole;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\Holiday;
use App\Models\InstructorPaAllocation;
use App\Models\InstructorProfile;
use App\Models\LocationType;
use App\Models\PaRound;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ScheduleConflictRun;
use App\Models\StudentCohort;
use App\Models\StudentGroup;
use App\Models\User;
use App\Models\UserRole;
use App\Services\NavigationBadgeService;
use App\Services\ScheduleConflictIndex;
use App\Services\ScheduleConflictInvalidationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TpssMediumDemoSeeder extends Seeder
{
    private const TAG = '[tpss-medium-demo]';

    /**
     * Seed a medium-size, realistic demo dataset for Phase 1 end-to-end review.
     *
     * This seeder is intentionally opt-in. Do not call it from DatabaseSeeder unless
     * the team decides the main local seed should always include demo-heavy data.
     */
    public function run(): void
    {
        $this->callCoreSeeders();

        $summary = DB::transaction(function (): array {
            $year = $this->activateSchedulingYear();
            $departments = $this->ensureDepartments();
            $users = $this->ensureDemoUsers($departments);
            $curriculum = $this->activeCurriculum();
            $courseRoles = $this->courseRoles();
            $activities = $this->activityTypes();
            $rooms = $this->ensureRooms();

            $this->ensureHolidays();
            $this->ensurePaDemo($year, $users);

            $courses = $this->ensureCourses($curriculum, $departments, $users, $courseRoles);
            $offerings = $this->ensureOfferings($year, $courses, $users, $courseRoles);
            $groups = $this->ensureStudentGroups($offerings, $curriculum);

            $this->clearTaggedSchedules($year);
            $scheduleCount = $this->seedSchedules($year, $offerings, $groups, $activities, $rooms, $users);
            $this->seedAuditTrail($year, $users, $offerings);

            return [
                'year' => $year->fresh(),
                'course_count' => $courses->count(),
                'offering_count' => $offerings->count(),
                'group_count' => collect($groups)->flatten(1)->count(),
                'schedule_count' => $scheduleCount,
                'demo_instructor_count' => User::where('username', 'like', 'demo_instructor_%')->count(),
            ];
        });

        $this->warmConflictState($summary['year']);

        $this->command?->info('TPSS medium demo seeder completed.');
        $this->command?->line(sprintf(
            'Year %s: %d courses, %d offerings, %d student groups, %d schedules, %d extra demo instructors.',
            $summary['year']->name,
            $summary['course_count'],
            $summary['offering_count'],
            $summary['group_count'],
            $summary['schedule_count'],
            $summary['demo_instructor_count'],
        ));
    }

    private function callCoreSeeders(): void
    {
        $this->call([
            CurriculumSeeder::class,
            LocationTypeSeeder::class,
            SystemSettingSeeder::class,
            AcademicYearSeeder::class,
            DepartmentSeeder::class,
            UserSeeder::class,
            DepartmentSeeder::class,
            CourseRoleSeeder::class,
            ActivityTypeSeeder::class,
            StudentCohortSeeder::class,
            CourseSeeder::class,
        ]);
    }

    private function activateSchedulingYear(): AcademicYear
    {
        AcademicYear::query()
            ->where('name', '!=', '2569')
            ->update(['is_active' => false, 'phase' => 'preparation']);

        $year = AcademicYear::updateOrCreate(
            ['name' => '2569'],
            [
                'start_date' => '2026-06-01',
                'end_date' => '2027-03-15',
                'is_active' => true,
                'phase' => 'scheduling',
            ]
        );

        $year->fallbackCalendar()->terms()->updateOrCreate(
            ['sequence' => 1],
            [
                'name' => 'ภาคเรียนที่ 1',
                'start_date' => '2026-06-01',
                'end_date' => '2026-10-15',
                'midterm_start' => '2026-07-27',
                'midterm_end' => '2026-07-31',
                'final_start' => '2026-10-11',
                'final_end' => '2026-10-15',
            ]
        );

        $year->fallbackCalendar()->terms()->updateOrCreate(
            ['sequence' => 2],
            [
                'name' => 'ภาคเรียนที่ 2',
                'start_date' => '2026-11-02',
                'end_date' => '2027-03-15',
                'midterm_start' => '2026-12-21',
                'midterm_end' => '2026-12-25',
                'final_start' => '2027-03-11',
                'final_end' => '2027-03-15',
            ]
        );

        return $year;
    }

    /**
     * @return array<string, Department>
     */
    private function ensureDepartments(): array
    {
        $foundation = Department::where('name', 'like', '%รากฐาน%')->first()
            ?? Department::firstOrCreate(['name' => 'ภาควิชาการพยาบาลรากฐาน']);

        $mental = Department::where('name', 'like', '%สุขภาพจิต%')->first()
            ?? Department::firstOrCreate(['name' => 'ภาควิชาสุขภาพจิต และการพยาบาลจิตเวชศาสตร์']);

        $adult = Department::where('name', 'like', '%ผู้ใหญ่%')->first()
            ?? Department::firstOrCreate(['name' => 'ภาควิชาการพยาบาลผู้ใหญ่']);

        $external = Department::firstOrCreate(['name' => 'ภาควิชาภายนอกสำหรับ Demo/Test']);

        return compact('foundation', 'mental', 'adult', 'external');
    }

    /**
     * @param  array<string, Department>  $departments
     * @return array<string, User>
     */
    private function ensureDemoUsers(array $departments): array
    {
        $extraUsers = [];

        for ($i = 1; $i <= 10; $i++) {
            $extraUsers[] = [
                'username' => sprintf('demo_instructor_%02d', $i),
                'employee_id' => sprintf('DMI%02d', $i),
                'name' => sprintf('ตัวอย่าง UX11 %02d', $i),
                'email' => sprintf('demo.instructor.%02d@tpss.demo', $i),
                'department_id' => $departments['foundation']->id,
                'title' => 'อาจารย์',
                'active' => true,
            ];
        }

        $extraUsers[] = [
            'username' => 'demo_outside_01',
            'employee_id' => 'DMO01',
            'name' => 'กาญจนา วิสุทธิ์',
            'email' => 'demo.outside.01@tpss.demo',
            'department_id' => $departments['external']->id,
            'title' => 'รองศาสตราจารย์',
            'active' => true,
        ];

        $extraUsers[] = [
            'username' => 'demo_inactive_01',
            'employee_id' => 'DMI99',
            'name' => 'บัญชีตัวอย่าง ไม่ใช้งาน',
            'email' => 'demo.inactive.01@tpss.demo',
            'department_id' => $departments['foundation']->id,
            'title' => 'อาจารย์',
            'active' => false,
        ];

        foreach ($extraUsers as $userData) {
            $user = User::updateOrCreate(
                ['username' => $userData['username']],
                [
                    'prefix' => 'อ.',
                    'employee_id' => $userData['employee_id'],
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'password' => 'password',
                    'is_active' => $userData['active'],
                ]
            );

            UserRole::updateOrCreate(
                ['user_id' => $user->id, 'role' => 'instructor'],
                ['is_primary' => true]
            );

            InstructorProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'title' => $userData['title'],
                    'department_id' => $userData['department_id'],
                    'employment_type' => 'พนักงานมหาวิทยาลัย',
                    'academic_degree' => $userData['username'] === 'demo_outside_01' ? 'ปริญญาเอก' : 'ปริญญาโท',
                    'hired_at' => '2020-06-01',
                    'teaching_pct' => 55,
                    'research_pct' => 20,
                    'service_pct' => 15,
                    'culture_pct' => 5,
                    'other_pct' => 5,
                    'teaching_quota' => 12,
                ]
            );
        }

        $usernames = collect([
            'admin_01',
            'head_med',
            'head_psy',
            'exec_01',
            'instructor_01',
            'staff_01',
            'demo_outside_01',
            'demo_inactive_01',
        ])->merge(
            collect(range(1, 10))->map(fn (int $i) => sprintf('demo_instructor_%02d', $i))
        );

        return User::query()
            ->whereIn('username', $usernames->all())
            ->get()
            ->keyBy('username')
            ->all();
    }

    private function activeCurriculum(): Curriculum
    {
        $curriculum = Curriculum::where('name', 'like', '%2565%')->first()
            ?? Curriculum::where('is_active', true)->first();

        if (! $curriculum) {
            $curriculum = Curriculum::create([
                'name' => 'หลักสูตรพยาบาลศาสตรบัณฑิต 2565',
                'effective_year' => 2565,
                'education_level' => 'bachelor',
                'duration_years' => 4,
                'uses_year_level' => true,
                'total_credits_required' => 140,
                'counts_service_only' => false,
                'is_active' => true,
            ]);
        }

        $curriculum->forceFill(['is_active' => true])->save();

        return $curriculum;
    }

    /**
     * @return array<string, CourseRole>
     */
    private function courseRoles(): array
    {
        return [
            'head' => CourseRole::firstOrCreate(['name_th' => 'หัวหน้าวิชา'], ['sort_order' => 1]),
            'instructor' => CourseRole::firstOrCreate(['name_th' => 'อาจารย์ผู้สอน'], ['sort_order' => 2]),
            'advisor' => CourseRole::firstOrCreate(['name_th' => 'อาจารย์ประจำกลุ่ม'], ['sort_order' => 3]),
        ];
    }

    /**
     * @return array<string, ActivityType>
     */
    private function activityTypes(): array
    {
        return [
            'lecture' => $this->activityType('Bedside Teaching', 'lecture'),
            'conference' => $this->activityType('Conference / Case Conference', 'lecture'),
            'lab' => $this->activityType('Lab / ห้องปฏิบัติการ', 'practicum'),
            'practicum' => $this->activityType('Clinical Practice', 'practicum'),
            'post_conference' => $this->activityType('Post-Conference', 'lecture'),
        ];
    }

    private function activityType(string $name, string $category): ActivityType
    {
        return ActivityType::where('name', $name)->first()
            ?? ActivityType::where('category', $category)->first()
            ?? ActivityType::create([
                'name' => $name,
                'category' => $category,
                'color_code' => '#1d4ed8',
                'counts_toward_workload' => true,
            ]);
    }

    /**
     * @return array<string, Room>
     */
    private function ensureRooms(): array
    {
        $lecture = $this->locationType('ห้องเรียนทั่วไป', false);
        $lab = $this->locationType('ห้องปฏิบัติการ', false);
        $ward = $this->locationType('หอผู้ป่วย', true);
        $hospital = $this->locationType('โรงพยาบาล', true);

        $rooms = [
            'R-301' => [
                'room_name' => 'ห้องบรรยาย 301',
                'building' => 'อาคารเฉลิมพระเกียรติ',
                'capacity' => 120,
                'location_type_id' => $lecture->id,
                'equipment_type' => ['โปรเจคเตอร์', 'ไมโครโฟน'],
                'address' => null,
            ],
            'R-302' => [
                'room_name' => 'ห้องบรรยาย 302',
                'building' => 'อาคารเฉลิมพระเกียรติ',
                'capacity' => 100,
                'location_type_id' => $lecture->id,
                'equipment_type' => ['โปรเจคเตอร์'],
                'address' => null,
            ],
            'LAB-401' => [
                'room_name' => 'ห้องปฏิบัติการพยาบาล 1',
                'building' => 'อาคารพระศรีนครินทร์',
                'capacity' => 50,
                'location_type_id' => $lab->id,
                'equipment_type' => ['หุ่นจำลอง', 'เตียงพยาบาล'],
                'address' => null,
            ],
            'WARD-A' => [
                'room_name' => 'หอผู้ป่วยอายุรกรรมหญิง',
                'building' => 'โรงพยาบาลศิริราช',
                'capacity' => null,
                'location_type_id' => $ward->id,
                'equipment_type' => [],
                'address' => 'ตึก 84 ปี ชั้น 4 โรงพยาบาลศิริราช',
            ],
            'HOSP-RAMA' => [
                'room_name' => 'โรงพยาบาลรามาธิบดี',
                'building' => 'โรงพยาบาลรามาธิบดี',
                'capacity' => null,
                'location_type_id' => $hospital->id,
                'equipment_type' => [],
                'address' => '270 ถนนพระรามที่ 6 เขตราชเทวี กรุงเทพฯ',
            ],
            'DEMO-SMALL' => [
                'room_name' => 'ห้องกลุ่มย่อย Demo',
                'building' => 'อาคารเฉลิมพระเกียรติ',
                'capacity' => 8,
                'location_type_id' => $lecture->id,
                'equipment_type' => [],
                'address' => null,
            ],
        ];

        return collect($rooms)
            ->mapWithKeys(fn (array $room, string $code) => [
                $code => Room::updateOrCreate(
                    ['room_code' => $code],
                    [...$room, 'status' => 'active']
                ),
            ])
            ->all();
    }

    private function locationType(string $name, bool $isShared): LocationType
    {
        return LocationType::firstOrCreate(['name' => $name], ['is_shared' => $isShared]);
    }

    private function ensureHolidays(): void
    {
        $holidays = [
            '2026-06-03' => 'วันเฉลิมพระชนมพรรษาสมเด็จพระนางเจ้าฯ พระบรมราชินี',
            '2026-07-28' => 'วันเฉลิมพระชนมพรรษาพระบาทสมเด็จพระเจ้าอยู่หัว',
            '2026-08-12' => 'วันแม่แห่งชาติ',
            '2026-10-13' => 'วันนวมินทรมหาราช',
            '2026-10-23' => 'วันปิยมหาราช',
            '2026-12-05' => 'วันพ่อแห่งชาติ',
            '2026-12-10' => 'วันรัฐธรรมนูญ',
            '2026-12-31' => 'วันสิ้นปี',
            '2027-01-01' => 'วันขึ้นปีใหม่',
        ];

        foreach ($holidays as $date => $name) {
            Holiday::updateOrCreate(
                ['date' => $date],
                ['name' => $name, 'source' => 'demo', 'remark' => self::TAG]
            );
        }
    }

    /**
     * @param  array<string, User>  $users
     */
    private function ensurePaDemo(AcademicYear $year, array $users): void
    {
        $round = PaRound::updateOrCreate(
            ['academic_year_id' => $year->id, 'code' => PaRound::CODE_ANNUAL],
            [
                'name' => 'PA ประจำปีการศึกษา 2569',
                'start_date' => '2026-06-01',
                'end_date' => '2027-03-15',
            ]
        );

        foreach (['head_med', 'head_psy', 'instructor_01', 'demo_instructor_01'] as $username) {
            if (! isset($users[$username])) {
                continue;
            }

            InstructorPaAllocation::updateOrCreate(
                ['user_id' => $users[$username]->id, 'pa_round_id' => $round->id],
                [
                    'teaching_pct' => $username === 'instructor_01' ? 60 : 50,
                    'research_pct' => 20,
                    'service_pct' => 15,
                    'culture_pct' => 10,
                    'other_pct' => 5,
                    'teaching_quota' => $username === 'instructor_01' ? 14 : 12,
                    'submitted_at' => now(),
                ]
            );
        }
    }

    /**
     * @param  array<string, Department>  $departments
     * @param  array<string, User>  $users
     * @param  array<string, CourseRole>  $roles
     * @return \Illuminate\Support\Collection<string, Course>
     */
    private function ensureCourses(Curriculum $curriculum, array $departments, array $users, array $roles)
    {
        $definitions = [
            'NSBS 111' => ['name_th' => 'กระบวนการพยาบาล 1', 'name_en' => 'Nursing Process 1', 'type' => 'theory', 'year' => 1, 'credits' => 2, 'lecture' => 2, 'lab' => 0, 'department' => 'foundation', 'head' => 'head_med', 'color' => '#3b82f6'],
            'NSBS 212' => ['name_th' => 'การพยาบาลเด็ก 1', 'name_en' => 'Pediatric Nursing 1', 'type' => 'theory_practicum', 'year' => 2, 'credits' => 3, 'lecture' => 2, 'lab' => 1, 'department' => 'foundation', 'head' => 'head_med', 'color' => '#10b981'],
            'NSBS 213' => ['name_th' => 'สุขภาพจิตและการพยาบาลจิตเวช 1', 'name_en' => 'Mental Health and Psychiatric Nursing 1', 'type' => 'theory', 'year' => 2, 'credits' => 2, 'lecture' => 2, 'lab' => 0, 'department' => 'mental', 'head' => 'head_psy', 'color' => '#8b5cf6'],
            'NSBS 221' => ['name_th' => 'การพยาบาลเด็ก 2', 'name_en' => 'Pediatric Nursing 2', 'type' => 'practicum', 'year' => 2, 'credits' => 2, 'lecture' => 0, 'lab' => 2, 'department' => 'foundation', 'head' => 'head_med', 'color' => '#f59e0b'],
            'NSBS 222' => ['name_th' => 'การพยาบาลผู้ใหญ่ 1', 'name_en' => 'Adult Nursing 1', 'type' => 'theory_practicum', 'year' => 2, 'credits' => 3, 'lecture' => 2, 'lab' => 1, 'department' => 'adult', 'head' => 'head_med', 'color' => '#0891b2'],
            'NSBS 231' => ['name_th' => 'การพยาบาลมารดา ทารก และการผดุงครรภ์ 1', 'name_en' => 'Maternal-Newborn Nursing and Midwifery 1', 'type' => 'theory_practicum', 'year' => 2, 'credits' => 3, 'lecture' => 2, 'lab' => 1, 'department' => 'foundation', 'head' => 'head_med', 'color' => '#db2777'],
            'NSBS 314' => ['name_th' => 'สุขภาพจิตและการพยาบาลจิตเวช 2', 'name_en' => 'Mental Health and Psychiatric Nursing 2', 'type' => 'practicum', 'year' => 3, 'credits' => 2, 'lecture' => 0, 'lab' => 2, 'department' => 'mental', 'head' => 'head_psy', 'color' => '#7c3aed'],
        ];

        return collect($definitions)->mapWithKeys(function (array $data, string $code) use ($curriculum, $departments, $users, $roles) {
            $course = Course::updateOrCreate(
                ['course_code' => $code, 'curriculum_id' => $curriculum->id],
                [
                    'department_id' => $departments[$data['department']]->id,
                    'head_instructor_id' => $users[$data['head']]?->id,
                    'name_th' => $data['name_th'],
                    'name_en' => $data['name_en'],
                    'course_type' => $data['type'],
                    'default_year_level' => $data['year'],
                    'is_required' => true,
                    'credits' => $data['credits'],
                    'lecture_hours' => $data['lecture'],
                    'lab_hours' => $data['lab'],
                    'self_study_hours' => 4,
                    'color_code' => $data['color'],
                    'status' => 'active',
                ]
            );

            if (isset($users['staff_01'])) {
                $course->assignedStaff()->syncWithoutDetaching([$users['staff_01']->id]);
            }

            $instructorPayload = [];
            foreach (['instructor_01', 'demo_instructor_01', 'demo_instructor_02'] as $username) {
                if (isset($users[$username])) {
                    $instructorPayload[$users[$username]->id] = ['course_role_id' => $roles['instructor']->id];
                }
            }

            if ($code === 'NSBS 231') {
                for ($i = 1; $i <= 10; $i++) {
                    $username = sprintf('demo_instructor_%02d', $i);
                    if (isset($users[$username])) {
                        $instructorPayload[$users[$username]->id] = ['course_role_id' => $roles['instructor']->id];
                    }
                }
            }

            $course->instructors()->syncWithoutDetaching($instructorPayload);

            return [$code => $course];
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<string, Course>  $courses
     * @param  array<string, User>  $users
     * @param  array<string, CourseRole>  $roles
     * @return \Illuminate\Support\Collection<string, CourseOffering>
     */
    private function ensureOfferings(AcademicYear $year, $courses, array $users, array $roles)
    {
        $statuses = [
            'NSBS 111' => ['published', null],
            'NSBS 212' => ['draft', null],
            'NSBS 213' => ['pending', null],
            'NSBS 221' => ['published', null],
            'NSBS 222' => ['rejected', 'ตัวอย่างข้อมูล: ส่งกลับให้แก้ไขแผนสอนก่อนเผยแพร่'],
            'NSBS 231' => ['draft', null],
            'NSBS 314' => ['draft', null],
        ];

        return $courses->mapWithKeys(function (Course $course, string $code) use ($year, $users, $roles, $statuses) {
            [$status, $reason] = $statuses[$code] ?? ['draft', null];

            $offering = CourseOffering::updateOrCreate(
                ['course_id' => $course->id, 'academic_year_id' => $year->id],
                [
                    'coordinator_id' => $course->head_instructor_id,
                    'approval_status' => $status,
                    'rejection_reason' => $reason,
                    'planned_lecture_hours' => $course->lecture_hours * 15,
                    'planned_lab_hours' => $course->lab_hours * 15,
                    'teaching_weeks' => 15,
                    'instructor_pool_note' => self::TAG . ' medium demo instructor pool',
                ]
            );

            $pool = [];
            $coordinator = User::find($course->head_instructor_id);
            if ($coordinator) {
                $pool[$coordinator->id] = [
                    'role_in_course' => 'coordinator',
                    'course_role_id' => $roles['head']->id,
                    'schedule_permission' => 'schedule',
                ];
            }

            foreach (['instructor_01', 'demo_instructor_01', 'demo_instructor_02'] as $username) {
                if (isset($users[$username])) {
                    $pool[$users[$username]->id] = [
                        'role_in_course' => 'instructor',
                        'course_role_id' => $roles['instructor']->id,
                        'schedule_permission' => $username === 'instructor_01' ? 'schedule' : 'view',
                    ];
                }
            }

            if ($code === 'NSBS 212' && isset($users['demo_outside_01'])) {
                $pool[$users['demo_outside_01']->id] = [
                    'role_in_course' => 'instructor',
                    'course_role_id' => $roles['instructor']->id,
                    'schedule_permission' => 'view',
                ];
            }

            if ($code === 'NSBS 231') {
                for ($i = 1; $i <= 10; $i++) {
                    $username = sprintf('demo_instructor_%02d', $i);
                    if (! isset($users[$username])) {
                        continue;
                    }

                    $pool[$users[$username]->id] = [
                        'role_in_course' => 'instructor',
                        'course_role_id' => $roles['instructor']->id,
                        'schedule_permission' => $i <= 2 ? 'schedule' : 'view',
                    ];
                }
            }

            $offering->instructorPool()->sync($pool);

            return [$code => $offering];
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<string, CourseOffering>  $offerings
     * @return array<string, array<string, StudentGroup>>
     */
    private function ensureStudentGroups($offerings, Curriculum $curriculum): array
    {
        $cohorts = StudentCohort::where('curriculum_id', $curriculum->id)->get()->keyBy('code');
        $palette = ['#2563eb', '#16a34a', '#d97706', '#dc2626', '#7c3aed', '#0891b2'];
        $result = [];

        foreach ($offerings as $code => $offering) {
            $result[$code] = [];

            for ($i = 1; $i <= 12; $i++) {
                $groupCode = 'A' . $i;
                $group = StudentGroup::updateOrCreate(
                    ['course_offering_id' => $offering->id, 'group_code' => $groupCode],
                    [
                        'cohort_group_id' => $cohorts->get($groupCode)?->id,
                        'student_count' => 5,
                        'color_code' => $palette[($i - 1) % count($palette)],
                    ]
                );

                $result[$code][$groupCode] = $group;
            }
        }

        return $result;
    }

    private function clearTaggedSchedules(AcademicYear $year): void
    {
        Schedule::query()
            ->where('remark', self::TAG)
            ->whereHas('courseOffering', fn ($query) => $query->where('academic_year_id', $year->id))
            ->eachById(function (Schedule $schedule): void {
                $schedule->instructors()->detach();
                $schedule->studentGroups()->detach();
                $schedule->delete();
            });
    }

    /**
     * @param  \Illuminate\Support\Collection<string, CourseOffering>  $offerings
     * @param  array<string, array<string, StudentGroup>>  $groups
     * @param  array<string, ActivityType>  $activities
     * @param  array<string, Room>  $rooms
     * @param  array<string, User>  $users
     */
    private function seedSchedules(AcademicYear $year, $offerings, array $groups, array $activities, array $rooms, array $users): int
    {
        $count = 0;

        $count += $this->slot($year, $offerings['NSBS 111'], $activities['lecture'], $rooms['R-301'], ['instructor_01' => true], $this->groups($groups, 'NSBS 111', ['A1', 'A2', 'A3']), '2026-06-08', '09:00', '11:30', 'บรรยายกระบวนการพยาบาล', 'approved', $users);
        $count += $this->slot($year, $offerings['NSBS 111'], $activities['lab'], $rooms['LAB-401'], ['demo_instructor_01' => true], $this->groups($groups, 'NSBS 111', ['A4', 'A5', 'A6']), '2026-06-09', '13:00', '16:00', 'ฝึกปฏิบัติทักษะพื้นฐาน', 'approved', $users);
        $count += $this->slot($year, $offerings['NSBS 111'], $activities['practicum'], $rooms['WARD-A'], ['instructor_01' => true], $this->groups($groups, 'NSBS 111', ['A7', 'A8', 'A9']), '2026-06-12', '13:00', '16:00', 'ฝึกงานหอผู้ป่วย', 'approved', $users);

        $count += $this->slot($year, $offerings['NSBS 212'], $activities['lecture'], $rooms['R-301'], ['demo_instructor_02' => true], $this->groups($groups, 'NSBS 212', ['A4', 'A5', 'A6']), '2026-06-08', '09:00', '11:30', 'ตัวอย่างชนห้องเรียน', 'draft', $users);
        $count += $this->slot($year, $offerings['NSBS 212'], $activities['conference'], $rooms['R-302'], ['instructor_01' => true], $this->groups($groups, 'NSBS 212', ['A1', 'A2', 'A3']), '2026-06-09', '13:00', '16:00', 'ตัวอย่างชนอาจารย์', 'draft', $users);
        $count += $this->slot($year, $offerings['NSBS 212'], $activities['lecture'], null, [], $this->groups($groups, 'NSBS 212', ['A10', 'A11', 'A12']), '2026-06-10', '10:00', '12:00', 'ตัวอย่างข้อมูลไม่ครบ', 'draft', $users);
        $count += $this->slot($year, $offerings['NSBS 212'], $activities['lecture'], $rooms['R-302'], ['demo_outside_01' => true], $this->groups($groups, 'NSBS 212', ['A1', 'A2']), '2026-06-11', '09:00', '12:00', 'ตัวอย่างอาจารย์ต่างภาค', 'draft', $users);
        $count += $this->slot($year, $offerings['NSBS 212'], $activities['lecture'], $rooms['DEMO-SMALL'], ['head_med' => true], $this->groups($groups, 'NSBS 212', ['A1', 'A2']), '2026-06-12', '14:00', '16:00', 'ตัวอย่างกลุ่มเกินความจุห้อง', 'draft', $users);

        $count += $this->slot($year, $offerings['NSBS 213'], $activities['lecture'], $rooms['R-301'], ['head_psy' => true], $this->groups($groups, 'NSBS 213', ['A1', 'A2']), '2026-06-18', '08:00', '12:00', 'สุขภาพจิตและการพยาบาลจิตเวช', 'pending_approval', $users);
        $count += $this->slot($year, $offerings['NSBS 213'], $activities['lecture'], $rooms['R-301'], ['head_psy' => true], $this->groups($groups, 'NSBS 213', ['A3', 'A4']), '2026-06-25', '08:00', '12:00', 'สุขภาพจิตและการพยาบาลจิตเวช', 'pending_approval', $users);

        $count += $this->slot($year, $offerings['NSBS 314'], $activities['practicum'], $rooms['R-302'], ['head_psy' => true], $this->groups($groups, 'NSBS 314', ['A1', 'A2']), '2026-06-03', '09:00', '12:00', 'ตัวอย่างจัดตรงวันหยุด', 'draft', $users);
        $count += $this->slot($year, $offerings['NSBS 314'], $activities['post_conference'], $rooms['R-302'], ['head_psy' => true], $this->groups($groups, 'NSBS 314', ['A3']), '2026-06-06', '09:00', '11:00', 'ตัวอย่างวันเสาร์', 'draft', $users);

        $count += $this->slot($year, $offerings['NSBS 221'], $activities['practicum'], $rooms['WARD-A'], ['head_med' => true, 'demo_instructor_03' => false], $this->groups($groups, 'NSBS 221', ['A1', 'A2', 'A3']), '2026-06-22', '08:00', '16:00', 'บล็อกฝึกปฏิบัติต่อเนื่องหลายวัน', 'approved', $users, '2026-06-26');

        $count += $this->slot($year, $offerings['NSBS 231'], $activities['lecture'], $rooms['R-301'], ['demo_instructor_04' => true], $this->groups($groups, 'NSBS 231', ['A1']), '2026-06-16', '09:00', '11:30', 'รายการพร้อมคัดลอก 1', 'draft', $users);
        $count += $this->slot($year, $offerings['NSBS 231'], $activities['practicum'], $rooms['WARD-A'], ['demo_instructor_05' => true], $this->groups($groups, 'NSBS 231', ['A2']), '2026-06-17', '13:00', '16:00', 'รายการพร้อมคัดลอก 2', 'draft', $users);
        $count += $this->slot($year, $offerings['NSBS 231'], $activities['lecture'], $rooms['R-301'], ['demo_instructor_06' => true], $this->groups($groups, 'NSBS 231', ['A3']), '2026-06-18', '09:00', '11:30', 'รายการพร้อมคัดลอก 3', 'draft', $users);
        $count += $this->slot($year, $offerings['NSBS 231'], $activities['lab'], $rooms['LAB-401'], ['demo_instructor_07' => true], $this->groups($groups, 'NSBS 231', ['A4']), '2026-06-19', '10:00', '12:00', 'รายการพร้อมคัดลอก 4', 'draft', $users);
        $count += $this->slot($year, $offerings['NSBS 231'], $activities['lecture'], $rooms['HOSP-RAMA'], ['demo_instructor_08' => true], $this->groups($groups, 'NSBS 231', ['A5']), '2026-06-20', '09:00', '11:30', 'รายการพร้อมคัดลอก 5', 'draft', $users);
        $count += $this->slot($year, $offerings['NSBS 231'], $activities['lecture'], $rooms['R-301'], ['demo_instructor_04' => true], $this->groups($groups, 'NSBS 231', ['A6']), '2026-06-23', '09:00', '11:30', 'รายการปลายทางที่ทำให้ copy ชน', 'draft', $users);

        $count += $this->slot($year, $offerings['NSBS 222'], $activities['conference'], $rooms['R-302'], ['demo_instructor_09' => true], $this->groups($groups, 'NSBS 222', ['A1', 'A2']), '2026-06-24', '09:00', '12:00', 'รายการในรายวิชาถูกส่งกลับแก้ไข', 'revised', $users);

        return $count;
    }

    /**
     * @param  array<string, User>  $users
     * @param  array<int, StudentGroup>  $studentGroups
     * @param  array<string, bool>  $instructors
     */
    private function slot(
        AcademicYear $year,
        CourseOffering $offering,
        ActivityType $activity,
        ?Room $room,
        array $instructors,
        array $studentGroups,
        string $date,
        string $start,
        string $end,
        string $topic,
        string $status,
        array $users,
        ?string $endDate = null
    ): int {
        $schedule = Schedule::create([
            'course_offering_id' => $offering->id,
            'term_id' => $this->termIdFor($year, $date),
            'activity_type_id' => $activity->id,
            'room_id' => $room?->id,
            'teaching_date' => $date,
            'start_date' => $date,
            'end_date' => $endDate ?? $date,
            'start_time' => $start,
            'end_time' => $end,
            'topic' => $topic,
            'capacity_required' => collect($studentGroups)->sum(fn (StudentGroup $group) => (int) $group->student_count),
            'status' => $status,
            'remark' => self::TAG,
        ]);

        $schedule->instructors()->sync(
            collect($instructors)
                ->mapWithKeys(fn (bool $isLead, string $username) => isset($users[$username])
                    ? [$users[$username]->id => ['is_lead' => $isLead]]
                    : [])
                ->all()
        );

        $schedule->studentGroups()->sync(
            collect($studentGroups)->map(fn (StudentGroup $group) => $group->id)->all()
        );

        return 1;
    }

    private function termIdFor(AcademicYear $year, string $date): ?int
    {
        $target = CarbonImmutable::parse($date);

        return $year->terms()
            ->whereDate('start_date', '<=', $target)
            ->whereDate('end_date', '>=', $target)
            ->value('terms.id');
    }

    /**
     * @param  array<string, array<string, StudentGroup>>  $groups
     * @param  array<int, string>  $codes
     * @return array<int, StudentGroup>
     */
    private function groups(array $groups, string $courseCode, array $codes): array
    {
        return collect($codes)
            ->map(fn (string $code) => $groups[$courseCode][$code] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, User>  $users
     * @param  \Illuminate\Support\Collection<string, CourseOffering>  $offerings
     */
    private function seedAuditTrail(AcademicYear $year, array $users, $offerings): void
    {
        AuditLog::query()
            ->where('description', 'like', self::TAG . '%')
            ->delete();

        $rows = [
            [
                'user_id' => $users['head_med']?->id,
                'action' => 'seed.demo',
                'table_affected' => 'course_offerings',
                'record_id' => $offerings['NSBS 212']->id,
                'category' => 'schedule',
                'description' => self::TAG . ' สร้างข้อมูล demo สำหรับรายการแจ้งเตือนและ conflict',
                'new_values' => ['academic_year' => $year->name, 'course' => 'NSBS 212'],
            ],
            [
                'user_id' => $users['head_psy']?->id,
                'action' => 'seed.demo',
                'table_affected' => 'course_offerings',
                'record_id' => $offerings['NSBS 213']->id,
                'category' => 'approval',
                'description' => self::TAG . ' สร้างข้อมูล demo สำหรับรายการรออนุมัติ',
                'new_values' => ['academic_year' => $year->name, 'course' => 'NSBS 213'],
            ],
            [
                'user_id' => $users['admin_01']?->id,
                'action' => 'seed.demo',
                'table_affected' => 'academic_years',
                'record_id' => $year->id,
                'category' => 'system',
                'description' => self::TAG . ' เปิดปีการศึกษา 2569 สำหรับ demo/test',
                'new_values' => ['phase' => 'scheduling'],
            ],
        ];

        foreach ($rows as $row) {
            AuditLog::create([...$row, 'created_at' => now()]);
        }
    }

    private function warmConflictState(AcademicYear $year): void
    {
        if (config('conflicts.async_reads')) {
            $latestGeneration = (int) ScheduleConflictRun::where('academic_year_id', $year->id)->max('generation');
            $run = ScheduleConflictRun::create([
                'academic_year_id' => $year->id,
                'status' => 'pending',
                'generation' => $latestGeneration + 1,
                'source' => 'manual',
                'requested_at' => now(),
                'result_count' => 0,
                'metadata' => null,
            ]);

            (new ConflictRecomputeJob($year->id, $run->id, $run->generation, 'manual'))
                ->handle(app(ScheduleConflictIndex::class), app(ScheduleConflictInvalidationService::class));

            return;
        }

        CourseOffering::query()
            ->where('academic_year_id', $year->id)
            ->pluck('coordinator_id')
            ->filter()
            ->unique()
            ->each(fn (int $userId) => app(NavigationBadgeService::class)->refreshCourseHeadConflictCount($userId, $year->id));
    }
}
