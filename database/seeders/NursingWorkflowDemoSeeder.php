<?php

namespace Database\Seeders;

use App\Jobs\ConflictRecomputeJob;
use App\Models\AcademicYear;
use App\Models\ActivityType;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseRole;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\InstructorProfile;
use App\Models\LocationType;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ScheduleConflictRun;
use App\Models\StudentGroup;
use App\Models\User;
use App\Models\UserRole;
use App\Services\NavigationBadgeService;
use App\Services\ScheduleConflictIndex;
use App\Services\ScheduleConflictInvalidationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class NursingWorkflowDemoSeeder extends Seeder
{
    private const TAG = '[NURSING_WORKFLOW_DEMO]';

    /**
     * Course codes and schedule shapes are based on the nursing timetable images
     * under Doc/ตัวอย่างตารางสอน. Instructor/staff names are intentionally fake.
     */
    public function run(): void
    {
        $this->call([
            CurriculumSeeder::class,
            LocationTypeSeeder::class,
            AcademicYearSeeder::class,
            DepartmentSeeder::class,
            CourseRoleSeeder::class,
            ActivityTypeSeeder::class,
        ]);

        $year = $this->activateDemoAcademicYear();
        $curriculum = Curriculum::query()
            ->where('name', 'LIKE', '%2565%')
            ->firstOrFail();

        $departments = $this->ensureDepartments();
        $users = $this->ensureUsers($departments);
        $roles = $this->courseRoleIds();
        $rooms = $this->ensureRooms();
        $activities = $this->activityIds();
        $courses = $this->ensureCourses($curriculum, $departments, $users, $roles);
        $offerings = $this->ensureOfferings($year, $courses, $roles);

        $this->ensureStaffAssignments($courses, $users);
        $groups = $this->ensureStudentGroups($offerings);
        $this->attachOutsideInstructorForWarning($offerings['RANS 205'], $users['demo_instructor_outside'], $roles);

        $this->clearDemoSchedules();
        $this->seedTimetableSchedules($offerings, $groups, $users, $rooms, $activities);
        $this->seedWarningSchedules($offerings, $groups, $users, $rooms, $activities);
        $this->seedBlockingSchedules($offerings, $groups, $users, $rooms, $activities);
        $this->refreshDerivedState($year);

        $this->command?->info('NursingWorkflowDemoSeeder: seeded nursing workflow demo data for academic year 2568 semester 1.');
        $this->command?->info('Login demo users with password: password');
    }

    private function activateDemoAcademicYear(): AcademicYear
    {
        $year = AcademicYear::query()
            ->where('name', '2568')
            ->where('semester', 1)
            ->firstOrFail();

        AcademicYear::query()
            ->whereKeyNot($year->id)
            ->update([
                'is_active' => false,
                'phase' => 'preparation',
            ]);

        $year->update([
            'is_active' => true,
            'phase' => 'scheduling',
        ]);

        return $year->fresh();
    }

    /**
     * @return array<string, Department>
     */
    private function ensureDepartments(): array
    {
        $names = [
            'foundation' => 'ภาควิชาการพยาบาลรากฐาน',
            'child' => 'ภาควิชาการพยาบาลกุมารเวชศาสตร์',
            'maternal' => 'ภาควิชาการพยาบาลสูติศาสตร์ - นรีเวชวิทยา',
            'adult' => 'ภาควิชาการพยาบาลอายุรศาสตร์',
            'general' => 'กลุ่มวิชาศึกษาทั่วไป (Demo)',
            'outside' => 'ภาควิชาภายนอกสำหรับทดสอบ Warning (Demo)',
        ];

        return collect($names)
            ->mapWithKeys(fn (string $name, string $key) => [
                $key => Department::query()->firstOrCreate(['name' => $name]),
            ])
            ->all();
    }

    /**
     * @param  array<string, Department>  $departments
     * @return array<string, User>
     */
    private function ensureUsers(array $departments): array
    {
        $definitions = [
            'demo_head_foundation' => [
                'prefix' => 'อ.',
                'employee_id' => 'D1001',
                'name' => 'นิรันดร์ ใจดี',
                'email' => 'demo.head.foundation@example.test',
                'roles' => ['instructor', 'course_head'],
                'department' => 'foundation',
                'title' => 'อาจารย์',
                'degree' => 'ปริญญาโท',
            ],
            'demo_head_child' => [
                'prefix' => 'อ.',
                'employee_id' => 'D1002',
                'name' => 'กมลพร แสงทอง',
                'email' => 'demo.head.child@example.test',
                'roles' => ['instructor', 'course_head'],
                'department' => 'child',
                'title' => 'อาจารย์',
                'degree' => 'ปริญญาเอก',
            ],
            'demo_head_maternal' => [
                'prefix' => 'อ.',
                'employee_id' => 'D1003',
                'name' => 'ธนวัฒน์ ศรีสุข',
                'email' => 'demo.head.maternal@example.test',
                'roles' => ['instructor', 'course_head'],
                'department' => 'maternal',
                'title' => 'ผู้ช่วยศาสตราจารย์',
                'degree' => 'ปริญญาเอก',
            ],
            'demo_head_general' => [
                'prefix' => 'อ.',
                'employee_id' => 'D1004',
                'name' => 'วรรณภา สุขเกษม',
                'email' => 'demo.head.general@example.test',
                'roles' => ['instructor', 'course_head'],
                'department' => 'general',
                'title' => 'อาจารย์',
                'degree' => 'ปริญญาโท',
            ],
            'demo_instructor_foundation_a' => [
                'prefix' => 'อ.',
                'employee_id' => 'D1101',
                'name' => 'ปริญญา พิมลชัย',
                'email' => 'demo.instructor.foundation.a@example.test',
                'roles' => ['instructor'],
                'department' => 'foundation',
                'title' => 'อาจารย์',
                'degree' => 'ปริญญาโท',
            ],
            'demo_instructor_foundation_b' => [
                'prefix' => 'อ.',
                'employee_id' => 'D1102',
                'name' => 'ศุภกานต์ เมธากุล',
                'email' => 'demo.instructor.foundation.b@example.test',
                'roles' => ['instructor'],
                'department' => 'foundation',
                'title' => 'ผู้ช่วยอาจารย์',
                'degree' => 'ปริญญาโท',
            ],
            'demo_instructor_child_a' => [
                'prefix' => 'อ.',
                'employee_id' => 'D1201',
                'name' => 'รัตนาภรณ์ วัฒนกิจ',
                'email' => 'demo.instructor.child.a@example.test',
                'roles' => ['instructor'],
                'department' => 'child',
                'title' => 'อาจารย์',
                'degree' => 'ปริญญาเอก',
            ],
            'demo_instructor_child_b' => [
                'prefix' => 'อ.',
                'employee_id' => 'D1202',
                'name' => 'ชลธิชา เกียรติพงศ์',
                'email' => 'demo.instructor.child.b@example.test',
                'roles' => ['instructor'],
                'department' => 'child',
                'title' => 'อาจารย์',
                'degree' => 'ปริญญาโท',
            ],
            'demo_instructor_maternal_a' => [
                'prefix' => 'อ.',
                'employee_id' => 'D1301',
                'name' => 'มธุรส ภักดี',
                'email' => 'demo.instructor.maternal.a@example.test',
                'roles' => ['instructor'],
                'department' => 'maternal',
                'title' => 'อาจารย์',
                'degree' => 'ปริญญาเอก',
            ],
            'demo_instructor_maternal_b' => [
                'prefix' => 'อ.',
                'employee_id' => 'D1302',
                'name' => 'สิริลักษณ์ พงษ์พิทักษ์',
                'email' => 'demo.instructor.maternal.b@example.test',
                'roles' => ['instructor'],
                'department' => 'maternal',
                'title' => 'อาจารย์',
                'degree' => 'ปริญญาโท',
            ],
            'demo_instructor_outside' => [
                'prefix' => 'อ.',
                'employee_id' => 'D1901',
                'name' => 'ปวีณา นอกภาค',
                'email' => 'demo.instructor.outside@example.test',
                'roles' => ['instructor'],
                'department' => 'outside',
                'title' => 'อาจารย์',
                'degree' => 'ปริญญาโท',
            ],
            'demo_staff_foundation' => [
                'prefix' => 'นางสาว',
                'employee_id' => 'DS201',
                'name' => 'อรพรรณ ช่วยงาน',
                'email' => 'demo.staff.foundation@example.test',
                'roles' => ['staff'],
            ],
            'demo_staff_child' => [
                'prefix' => 'นาย',
                'employee_id' => 'DS202',
                'name' => 'กิตติชัย ประสานงาน',
                'email' => 'demo.staff.child@example.test',
                'roles' => ['staff'],
            ],
            'demo_staff_maternal' => [
                'prefix' => 'นาง',
                'employee_id' => 'DS203',
                'name' => 'วิภาดา ตารางสอน',
                'email' => 'demo.staff.maternal@example.test',
                'roles' => ['staff'],
            ],
            'demo_staff_shared' => [
                'prefix' => 'นางสาว',
                'employee_id' => 'DS204',
                'name' => 'จิราภา งานกลาง',
                'email' => 'demo.staff.shared@example.test',
                'roles' => ['staff'],
            ],
        ];

        $users = [];

        foreach ($definitions as $username => $definition) {
            $user = User::query()->updateOrCreate(
                ['username' => $username],
                [
                    'prefix' => $definition['prefix'],
                    'employee_id' => $definition['employee_id'],
                    'name' => $definition['name'],
                    'email' => $definition['email'],
                    'password' => 'password',
                    'is_active' => true,
                ]
            );

            foreach ($definition['roles'] as $index => $role) {
                UserRole::query()->updateOrCreate(
                    ['user_id' => $user->id, 'role' => $role],
                    ['is_primary' => $index === 0]
                );
            }

            if (isset($definition['department'])) {
                InstructorProfile::query()->updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'title' => $definition['title'],
                        'department_id' => $departments[$definition['department']]->id,
                        'employment_type' => 'พนักงานมหาวิทยาลัย',
                        'academic_degree' => $definition['degree'],
                        'teaching_pct' => 45,
                        'research_pct' => 25,
                        'service_pct' => 15,
                        'culture_pct' => 10,
                        'other_pct' => 5,
                    ]
                );
            }

            $users[$username] = $user->fresh(['roles', 'instructorProfile']);
        }

        return $users;
    }

    /**
     * @return array<string, int|null>
     */
    private function courseRoleIds(): array
    {
        return CourseRole::query()
            ->pluck('id', 'name_th')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return array<string, Room>
     */
    private function ensureRooms(): array
    {
        $classroom = LocationType::query()->updateOrCreate(
            ['name' => 'ห้องเรียนทั่วไป'],
            ['is_shared' => false]
        );
        $lab = LocationType::query()->updateOrCreate(
            ['name' => 'ห้องปฏิบัติการ'],
            ['is_shared' => false]
        );

        $rooms = [
            'R-202' => [
                'room_name' => 'ห้อง 202',
                'building' => 'อาคารเรียนพยาบาล',
                'capacity' => 120,
                'location_type_id' => $classroom->id,
                'equipment_type' => ['โปรเจคเตอร์', 'ไมโครโฟน'],
                'address' => null,
                'status' => 'active',
            ],
            'R-204' => [
                'room_name' => 'ห้อง 204',
                'building' => 'อาคารเรียนพยาบาล',
                'capacity' => 90,
                'location_type_id' => $classroom->id,
                'equipment_type' => ['โปรเจคเตอร์'],
                'address' => null,
                'status' => 'active',
            ],
            'L2-201' => [
                'room_name' => 'L2-201',
                'building' => 'อาคารเรียนรวม',
                'capacity' => 140,
                'location_type_id' => $classroom->id,
                'equipment_type' => ['โปรเจคเตอร์', 'คอมพิวเตอร์'],
                'address' => null,
                'status' => 'active',
            ],
            'LAB-NURSE-DEMO' => [
                'room_name' => 'ห้องปฏิบัติการพยาบาล Demo',
                'building' => 'อาคารฝึกปฏิบัติการพยาบาล',
                'capacity' => 50,
                'location_type_id' => $lab->id,
                'equipment_type' => ['เตียงพยาบาล', 'หุ่นจำลอง'],
                'address' => null,
                'status' => 'active',
            ],
        ];

        return collect($rooms)
            ->mapWithKeys(fn (array $room, string $code) => [
                $code => Room::query()->updateOrCreate(['room_code' => $code], $room),
            ])
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function activityIds(): array
    {
        return ActivityType::query()
            ->whereIn('name', ['บรรยาย', 'Lab / ห้องปฏิบัติการ', 'สัมมนา', 'กลุ่มย่อย', 'ฝึกปฏิบัติในแหล่งจริง'])
            ->pluck('id', 'name')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array<string, Department>  $departments
     * @param  array<string, User>  $users
     * @param  array<string, int|null>  $roles
     * @return array<string, Course>
     */
    private function ensureCourses(Curriculum $curriculum, array $departments, array $users, array $roles): array
    {
        $courses = [
            'SCPM 202' => [
                'name_th' => 'เภสัชวิทยาพื้นฐาน',
                'name_en' => 'Basic Pharmacology',
                'department' => 'general',
                'head' => 'demo_head_general',
                'instructors' => ['demo_head_general', 'demo_instructor_foundation_a'],
                'credits' => 3,
                'lecture_hours' => 3,
                'lab_hours' => 0,
                'year' => 2,
                'capacity' => 120,
                'color' => '#2563eb',
            ],
            'LAEN 223' => [
                'name_th' => 'การสื่อสารด้วยภาษาอังกฤษตามสถานการณ์',
                'name_en' => 'English Communication in Contexts',
                'department' => 'general',
                'head' => 'demo_head_general',
                'instructors' => ['demo_head_general'],
                'credits' => 2,
                'lecture_hours' => 2,
                'lab_hours' => 0,
                'year' => 2,
                'capacity' => 120,
                'color' => '#0891b2',
            ],
            'RANS 204' => [
                'name_th' => 'การสร้างเสริมสุขภาพ',
                'name_en' => 'Health Promotion',
                'department' => 'foundation',
                'head' => 'demo_head_foundation',
                'instructors' => ['demo_head_foundation', 'demo_instructor_foundation_a'],
                'credits' => 3,
                'lecture_hours' => 3,
                'lab_hours' => 0,
                'year' => 2,
                'capacity' => 120,
                'color' => '#0d9488',
            ],
            'RANS 205' => [
                'name_th' => 'การพยาบาลรากฐาน',
                'name_en' => 'Fundamental Nursing',
                'department' => 'foundation',
                'head' => 'demo_head_foundation',
                'instructors' => ['demo_head_foundation', 'demo_instructor_foundation_a', 'demo_instructor_foundation_b'],
                'credits' => 3,
                'lecture_hours' => 3,
                'lab_hours' => 0,
                'year' => 2,
                'capacity' => 120,
                'color' => '#059669',
            ],
            'RANS 285' => [
                'name_th' => 'ปฏิบัติการพยาบาลรากฐาน 1',
                'name_en' => 'Fundamental Nursing Practicum 1',
                'department' => 'foundation',
                'head' => 'demo_head_foundation',
                'instructors' => ['demo_head_foundation', 'demo_instructor_foundation_a', 'demo_instructor_foundation_b'],
                'credits' => 2,
                'lecture_hours' => 0,
                'lab_hours' => 6,
                'year' => 2,
                'capacity' => 120,
                'color' => '#d97706',
                'practicum' => true,
            ],
            'RANS 303' => [
                'name_th' => 'สถิติและการวิจัยทางการพยาบาล 1',
                'name_en' => 'Statistics and Nursing Research 1',
                'department' => 'general',
                'head' => 'demo_head_general',
                'instructors' => ['demo_head_general', 'demo_instructor_child_a'],
                'credits' => 2,
                'lecture_hours' => 2,
                'lab_hours' => 0,
                'year' => 3,
                'capacity' => 90,
                'color' => '#6366f1',
            ],
            'RANS 326' => [
                'name_th' => 'การพยาบาลเด็กและวัยรุ่น',
                'name_en' => 'Child and Adolescent Nursing',
                'department' => 'child',
                'head' => 'demo_head_child',
                'instructors' => ['demo_head_child', 'demo_instructor_child_a', 'demo_instructor_child_b'],
                'credits' => 3,
                'lecture_hours' => 3,
                'lab_hours' => 0,
                'year' => 3,
                'capacity' => 90,
                'color' => '#dc2626',
            ],
            'RANS 327' => [
                'name_th' => 'การพยาบาลมารดา-ทารกและการผดุงครรภ์ 1',
                'name_en' => 'Maternal-Newborn Nursing and Midwifery 1',
                'department' => 'maternal',
                'head' => 'demo_head_maternal',
                'instructors' => ['demo_head_maternal', 'demo_instructor_maternal_a', 'demo_instructor_maternal_b'],
                'credits' => 3,
                'lecture_hours' => 3,
                'lab_hours' => 0,
                'year' => 3,
                'capacity' => 90,
                'color' => '#db2777',
            ],
            'RANS 371' => [
                'name_th' => 'ปฏิบัติการพยาบาลเด็กและวัยรุ่น',
                'name_en' => 'Child and Adolescent Nursing Practicum',
                'department' => 'child',
                'head' => 'demo_head_child',
                'instructors' => ['demo_head_child', 'demo_instructor_child_a', 'demo_instructor_child_b'],
                'credits' => 3,
                'lecture_hours' => 0,
                'lab_hours' => 9,
                'year' => 3,
                'capacity' => 90,
                'color' => '#9333ea',
                'practicum' => true,
            ],
            'RANS 374' => [
                'name_th' => 'ปฏิบัติการพยาบาลมารดา-ทารกและการผดุงครรภ์ 1',
                'name_en' => 'Maternal-Newborn Nursing and Midwifery Practicum 1',
                'department' => 'maternal',
                'head' => 'demo_head_maternal',
                'instructors' => ['demo_head_maternal', 'demo_instructor_maternal_a', 'demo_instructor_maternal_b'],
                'credits' => 4,
                'lecture_hours' => 0,
                'lab_hours' => 12,
                'year' => 3,
                'capacity' => 90,
                'color' => '#e11d48',
                'practicum' => true,
            ],
        ];

        $result = [];
        $instructorRoleId = $roles['อาจารย์ผู้สอน'] ?? null;

        foreach ($courses as $code => $data) {
            $course = Course::query()->updateOrCreate(
                ['course_code' => $code, 'curriculum_id' => $curriculum->id],
                [
                    'department_id' => $departments[$data['department']]->id,
                    'head_instructor_id' => $users[$data['head']]->id,
                    'name_th' => $data['name_th'],
                    'name_en' => $data['name_en'],
                    'course_type' => ($data['practicum'] ?? false) ? 'practicum' : 'theory',
                    'default_year_level' => $data['year'],
                    'default_semester' => 1,
                    'requires_practicum_rotation' => (bool) ($data['practicum'] ?? false),
                    'is_required' => true,
                    'credits' => $data['credits'],
                    'lecture_hours' => $data['lecture_hours'],
                    'lab_hours' => $data['lab_hours'],
                    'self_study_hours' => max(0, $data['credits'] * 2),
                    'capacity' => $data['capacity'],
                    'color_code' => $data['color'],
                    'status' => 'active',
                ]
            );

            $instructorPayload = [];
            foreach ($data['instructors'] as $username) {
                $instructorPayload[$users[$username]->id] = ['course_role_id' => $instructorRoleId];
            }
            $course->instructors()->sync($instructorPayload);

            $result[$code] = $course->fresh(['instructors']);
        }

        return $result;
    }

    /**
     * @param  array<string, Course>  $courses
     * @param  array<string, int|null>  $roles
     * @return array<string, CourseOffering>
     */
    private function ensureOfferings(AcademicYear $year, array $courses, array $roles): array
    {
        $offerings = [];
        $coordinatorRoleId = $roles['หัวหน้าวิชา'] ?? null;

        foreach ($courses as $code => $course) {
            $offering = CourseOffering::query()->updateOrCreate(
                ['course_id' => $course->id, 'academic_year_id' => $year->id],
                [
                    'coordinator_id' => $course->head_instructor_id,
                    'approval_status' => 'draft',
                    'total_student_count' => $course->capacity,
                    'planned_lecture_hours' => $course->lecture_hours,
                    'planned_lab_hours' => $course->requires_practicum_rotation ? 0 : $course->lab_hours,
                    'planned_practicum_hours' => $course->requires_practicum_rotation ? $course->lab_hours : 0,
                    'teaching_weeks' => 19,
                    'requires_practicum_rotation' => $course->requires_practicum_rotation,
                    'practicum_note' => null,
                ]
            );

            $offering->syncInstructorPoolFromCourseTemplate($coordinatorRoleId);
            $offerings[$code] = $offering->fresh(['course', 'academicYear', 'instructorPool']);
        }

        return $offerings;
    }

    /**
     * @param  array<string, Course>  $courses
     * @param  array<string, User>  $users
     */
    private function ensureStaffAssignments(array $courses, array $users): void
    {
        $assignments = [
            'demo_staff_foundation' => ['RANS 205', 'RANS 285'],
            'demo_staff_child' => ['RANS 326', 'RANS 371'],
            'demo_staff_maternal' => ['RANS 327', 'RANS 374'],
            'demo_staff_shared' => ['RANS 205', 'RANS 326', 'RANS 327', 'RANS 374'],
        ];

        foreach ($assignments as $username => $courseCodes) {
            foreach ($courseCodes as $code) {
                $courses[$code]->assignedStaff()->syncWithoutDetaching([$users[$username]->id]);
            }
        }
    }

    /**
     * @param  array<string, CourseOffering>  $offerings
     * @return array<string, array<string, StudentGroup>>
     */
    private function ensureStudentGroups(array $offerings): array
    {
        $groups = [];

        foreach ($offerings as $code => $offering) {
            $definitions = in_array($code, ['RANS 303', 'RANS 326', 'RANS 327', 'RANS 371', 'RANS 374'], true)
                ? [
                    'A1' => ['count' => 45, 'color' => '#2563eb'],
                ]
                : [
                    'A' => ['count' => 65, 'color' => '#2563eb'],
                    'B' => ['count' => 65, 'color' => '#16a34a'],
                ];

            foreach ($definitions as $groupCode => $definition) {
                $groups[$code][$groupCode] = StudentGroup::query()->updateOrCreate(
                    ['course_offering_id' => $offering->id, 'group_code' => $groupCode],
                    [
                        'student_count' => $definition['count'],
                        'color_code' => $definition['color'],
                    ]
                );
            }
        }

        return $groups;
    }

    /**
     * @param  array<string, int|null>  $roles
     */
    private function attachOutsideInstructorForWarning(CourseOffering $offering, User $outsideInstructor, array $roles): void
    {
        $offering->instructorPool()->syncWithoutDetaching([
            $outsideInstructor->id => [
                'role_in_course' => 'instructor',
                'course_role_id' => $roles['อาจารย์ผู้สอน'] ?? null,
            ],
        ]);
    }

    private function clearDemoSchedules(): void
    {
        Schedule::query()
            ->where('remark', 'LIKE', self::TAG . '%')
            ->each(function (Schedule $schedule): void {
                $schedule->instructors()->detach();
                $schedule->studentGroups()->detach();
                $schedule->delete();
            });
    }

    private function seedTimetableSchedules(array $offerings, array $groups, array $users, array $rooms, array $activities): void
    {
        $lecture = $activities['บรรยาย'];
        $lab = $activities['Lab / ห้องปฏิบัติการ'];
        $practicum = $activities['ฝึกปฏิบัติในแหล่งจริง'];

        $rows = [
            ['RANS 205', '2025-08-04', '09:00', '12:00', 'การพยาบาลรากฐาน', 'R-202', ['demo_head_foundation'], ['A', 'B'], $lecture],
            ['RANS 204', '2025-08-04', '13:00', '16:00', 'การสร้างเสริมสุขภาพ', 'R-202', ['demo_instructor_foundation_a'], ['A'], $lecture],
            ['RANS 204', '2025-08-04', '13:00', '16:00', 'การสร้างเสริมสุขภาพ', 'R-204', ['demo_instructor_foundation_b'], ['B'], $lecture],
            ['RANS 285', '2025-08-05', '09:00', '12:00', 'ปฏิบัติการพยาบาลรากฐาน 1', 'R-202', ['demo_instructor_foundation_a'], ['A'], $lab],
            ['RANS 285', '2025-08-05', '13:00', '16:00', 'ปฏิบัติการพยาบาลรากฐาน 1', 'R-202', ['demo_instructor_foundation_b'], ['B'], $lab],
            ['LAEN 223', '2025-08-06', '09:00', '11:00', 'การสื่อสารด้วยภาษาอังกฤษตามสถานการณ์', 'R-204', ['demo_head_general'], ['A', 'B'], $lecture],
            ['SCPM 202', '2025-08-07', '09:00', '12:00', 'เภสัชวิทยาพื้นฐาน', 'L2-201', ['demo_head_general'], ['A', 'B'], $lecture],
            ['RANS 205', '2025-08-07', '13:00', '16:00', 'การพยาบาลรากฐาน', 'R-202', ['demo_instructor_foundation_a'], ['B'], $lecture],
            ['RANS 285', '2025-08-08', '09:00', '12:00', 'ปฏิบัติการพยาบาลรากฐาน 1', 'R-202', ['demo_instructor_foundation_a'], ['A'], $lab],
            ['RANS 285', '2025-08-08', '13:00', '16:00', 'ปฏิบัติการพยาบาลรากฐาน 1', 'R-202', ['demo_instructor_foundation_b'], ['B'], $lab],
            ['RANS 303', '2025-07-21', '13:00', '15:00', 'สถิติและการวิจัยทางการพยาบาล 1', 'R-204', ['demo_head_general'], ['A1'], $lecture],
            ['RANS 326', '2025-07-22', '09:00', '12:00', 'การพยาบาลเด็กและวัยรุ่น', 'R-202', ['demo_head_child'], ['A1'], $lecture],
            ['RANS 326', '2025-07-22', '13:00', '16:00', 'การพยาบาลเด็กและวัยรุ่น', 'R-202', ['demo_instructor_child_a'], ['A1'], $lecture],
            ['RANS 371', '2025-07-23', '08:00', '11:00', 'ปฏิบัติการพยาบาลเด็กและวัยรุ่น', 'LAB-NURSE-DEMO', ['demo_instructor_child_b'], ['A1'], $practicum],
            ['RANS 371', '2025-07-24', '07:00', '15:00', 'ปฏิบัติการพยาบาลเด็กและวัยรุ่น', 'LAB-NURSE-DEMO', ['demo_head_child', 'demo_instructor_child_a'], ['A1'], $practicum],
            ['RANS 327', '2025-09-22', '09:00', '12:00', 'การพยาบาลมารดา-ทารกและการผดุงครรภ์ 1', 'R-204', ['demo_head_maternal'], ['A1'], $lecture],
            ['RANS 327', '2025-09-22', '13:00', '16:00', 'การพยาบาลมารดา-ทารกและการผดุงครรภ์ 1', 'R-204', ['demo_instructor_maternal_a'], ['A1'], $lecture],
            ['RANS 374', '2025-09-29', '07:00', '15:00', 'ปฏิบัติการพยาบาลมารดา-ทารกและการผดุงครรภ์ 1', 'LAB-NURSE-DEMO', ['demo_head_maternal', 'demo_instructor_maternal_b'], ['A1'], $practicum],
        ];

        foreach ($rows as [$code, $date, $start, $end, $topic, $roomCode, $instructors, $studentGroups, $activityId]) {
            $this->makeSchedule(
                $offerings[$code],
                $activityId,
                $rooms[$roomCode],
                $date,
                $date,
                $start,
                $end,
                $topic,
                collect($instructors)->map(fn (string $username) => $users[$username])->all(),
                collect($studentGroups)->map(fn (string $groupCode) => $groups[$code][$groupCode])->all(),
                self::TAG . ' timetable source sample'
            );
        }
    }

    private function seedWarningSchedules(array $offerings, array $groups, array $users, array $rooms, array $activities): void
    {
        $lecture = $activities['บรรยาย'];

        $this->makeSchedule(
            $offerings['RANS 205'],
            $lecture,
            null,
            '2025-09-01',
            '2025-09-01',
            '08:00',
            '09:00',
            'WARNING demo: ยังไม่ระบุห้อง',
            [$users['demo_head_foundation']],
            [$groups['RANS 205']['A']],
            self::TAG . ' warning missing_room'
        );

        $this->makeSchedule(
            $offerings['RANS 205'],
            $lecture,
            $rooms['R-204'],
            '2025-09-01',
            '2025-09-01',
            '09:00',
            '10:00',
            'WARNING demo: ยังไม่ระบุผู้สอน',
            [],
            [$groups['RANS 205']['A']],
            self::TAG . ' warning missing_instructors'
        );

        $this->makeSchedule(
            $offerings['RANS 205'],
            $lecture,
            $rooms['R-204'],
            '2025-09-01',
            '2025-09-01',
            '10:00',
            '11:00',
            'WARNING demo: ยังไม่ระบุกลุ่มนักศึกษา',
            [$users['demo_head_foundation']],
            [],
            self::TAG . ' warning missing_student_groups'
        );

        $this->makeSchedule(
            $offerings['RANS 205'],
            $lecture,
            $rooms['R-204'],
            '2025-09-01',
            '2025-09-01',
            '11:00',
            '12:00',
            'WARNING demo: ความจุรองรับน้อยกว่าจำนวนนักศึกษา',
            [$users['demo_instructor_foundation_a']],
            [$groups['RANS 205']['A'], $groups['RANS 205']['B']],
            self::TAG . ' warning capacity_exceeded',
            capacityRequired: 50
        );

        $this->makeSchedule(
            $offerings['RANS 205'],
            $lecture,
            $rooms['R-204'],
            '2025-09-01',
            '2025-09-01',
            '13:00',
            '14:00',
            'WARNING demo: ผู้สอนต่างภาควิชา',
            [$users['demo_instructor_outside']],
            [$groups['RANS 205']['A']],
            self::TAG . ' warning department_mismatch'
        );
    }

    private function seedBlockingSchedules(array $offerings, array $groups, array $users, array $rooms, array $activities): void
    {
        $lecture = $activities['บรรยาย'];

        $this->makeSchedule(
            $offerings['RANS 205'],
            $lecture,
            $rooms['R-202'],
            '2025-09-08',
            '2025-09-08',
            '08:00',
            '10:00',
            'BLOCK demo: ห้องชน A',
            [$users['demo_head_foundation']],
            [$groups['RANS 205']['A']],
            self::TAG . ' block room_overlap'
        );
        $this->makeSchedule(
            $offerings['RANS 204'],
            $lecture,
            $rooms['R-202'],
            '2025-09-08',
            '2025-09-08',
            '09:00',
            '11:00',
            'BLOCK demo: ห้องชน B',
            [$users['demo_instructor_foundation_b']],
            [$groups['RANS 204']['A']],
            self::TAG . ' block room_overlap'
        );

        $this->makeSchedule(
            $offerings['RANS 326'],
            $lecture,
            $rooms['R-204'],
            '2025-09-09',
            '2025-09-09',
            '08:00',
            '10:00',
            'BLOCK demo: ผู้สอนชน A',
            [$users['demo_instructor_child_a']],
            [$groups['RANS 326']['A1']],
            self::TAG . ' block instructor_overlap'
        );
        $this->makeSchedule(
            $offerings['RANS 303'],
            $lecture,
            $rooms['L2-201'],
            '2025-09-09',
            '2025-09-09',
            '09:00',
            '11:00',
            'BLOCK demo: ผู้สอนชน B',
            [$users['demo_instructor_child_a']],
            [$groups['RANS 303']['A1']],
            self::TAG . ' block instructor_overlap'
        );

        $this->makeSchedule(
            $offerings['RANS 327'],
            $lecture,
            $rooms['R-202'],
            '2025-09-10',
            '2025-09-10',
            '08:00',
            '10:00',
            'BLOCK demo: กลุ่มนักศึกษาชน A',
            [$users['demo_head_maternal']],
            [$groups['RANS 327']['A1']],
            self::TAG . ' block group_overlap'
        );
        $this->makeSchedule(
            $offerings['RANS 327'],
            $lecture,
            $rooms['R-204'],
            '2025-09-10',
            '2025-09-10',
            '09:00',
            '11:00',
            'BLOCK demo: กลุ่มนักศึกษาชน B',
            [$users['demo_instructor_maternal_a']],
            [$groups['RANS 327']['A1']],
            self::TAG . ' block group_overlap'
        );
    }

    /**
     * @param  array<int, User>  $instructors
     * @param  array<int, StudentGroup>  $studentGroups
     */
    private function makeSchedule(
        CourseOffering $offering,
        int $activityTypeId,
        ?Room $room,
        string $startDate,
        string $endDate,
        string $startTime,
        string $endTime,
        string $topic,
        array $instructors,
        array $studentGroups,
        string $remark,
        ?int $capacityRequired = null
    ): Schedule {
        $selectedCapacity = collect($studentGroups)->sum(fn (StudentGroup $group) => (int) $group->student_count);

        $schedule = Schedule::query()->create([
            'course_offering_id' => $offering->id,
            'activity_type_id' => $activityTypeId,
            'room_id' => $room?->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'teaching_date' => $startDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'topic' => $topic,
            'capacity_required' => $capacityRequired ?? ($selectedCapacity ?: null),
            'status' => 'draft',
            'remark' => $remark,
        ]);

        $instructorPayload = [];
        foreach (array_values($instructors) as $index => $instructor) {
            $instructorPayload[$instructor->id] = ['is_lead' => $index === 0];
        }
        $schedule->instructors()->sync($instructorPayload);
        $schedule->studentGroups()->sync(collect($studentGroups)->pluck('id')->all());

        return $schedule;
    }

    private function refreshDerivedState(AcademicYear $year): void
    {
        Cache::forget('tpss_alert_summary');

        $badgeService = app(NavigationBadgeService::class);
        CourseOffering::query()
            ->where('academic_year_id', $year->id)
            ->pluck('coordinator_id')
            ->filter()
            ->unique()
            ->each(fn ($userId) => $badgeService->refreshCourseHeadConflictCount((int) $userId, (int) $year->id));

        if (! config('conflicts.async_reads')) {
            return;
        }

        $generation = (int) ScheduleConflictRun::query()
            ->where('academic_year_id', $year->id)
            ->max('generation') + 1;

        $run = ScheduleConflictRun::query()->create([
            'academic_year_id' => $year->id,
            'status' => 'pending',
            'generation' => $generation,
            'source' => 'manual',
            'requested_at' => now(),
            'result_count' => 0,
        ]);

        (new ConflictRecomputeJob((int) $year->id, (int) $run->id, $generation, 'manual'))
            ->handle(app(ScheduleConflictIndex::class), app(ScheduleConflictInvalidationService::class));
    }
}
