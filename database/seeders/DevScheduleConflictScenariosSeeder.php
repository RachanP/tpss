<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ActivityType;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\InstructorProfile;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\User;
use App\Models\UserRole;
use App\Services\NavigationBadgeService;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * [TEST / DEV ONLY] Seeder สร้างตารางสอนแบบจัดสถานการณ์ conflict ครบชนิด
 *
 *   php artisan db:seed --class=DevScheduleConflictScenariosSeeder
 *
 * จุดประสงค์: ใช้ทดสอบ UI หน้า conflicts / schedule list ให้เห็น
 *   - รายการที่ไม่ชน (clean)
 *   - in-course instructor overlap
 *   - in-course room overlap
 *   - in-course group overlap
 *   - cross-course overlap (instructor + room ข้ามวิชา)
 *
 * ห้ามใช้ใน production — seeder นี้จะลบ schedule ทั้งหมดของ 2 offering ที่เลือก
 */
class DevScheduleConflictScenariosSeeder extends Seeder
{
    public function run(): void
    {
        $year = AcademicYear::where('is_active', true)->first()
            ?? AcademicYear::orderByDesc('start_date')->first();

        if (! $year) {
            $this->command->warn('[Dev] ไม่พบปีการศึกษา — รัน AcademicYearSeeder ก่อน');

            return;
        }

        if (! $year->is_active) {
            AcademicYear::query()->update(['is_active' => false]);
            $year->update(['is_active' => true]);
        }

        Course::whereNotNull('head_instructor_id')
            ->where('status', '!=', 'active')
            ->update(['status' => 'active']);

        $this->call(CourseOfferingSeeder::class);

        [$offeringA, $offeringB] = $this->pickPairedOfferings($year);

        if (! $offeringA || ! $offeringB) {
            $this->command->warn('[Dev] ต้องมีอย่างน้อย 2 รายวิชาในภาควิชาเดียวกันที่เปิดสอนปีนี้');

            return;
        }

        $this->ensureStudentGroups($offeringA);
        $this->ensureStudentGroups($offeringB);

        $this->ensureOfferingInstructors($offeringA, 2);
        $this->ensureOfferingInstructors($offeringB, 1);

        $offeringA->load(['course', 'studentGroups', 'instructorPool.instructorProfile']);
        $offeringB->load(['course', 'studentGroups', 'instructorPool.instructorProfile']);

        $sharedInstructor = $this->ensureSharedInstructor($offeringA, $offeringB);
        $offeringA->load(['studentGroups', 'instructorPool.instructorProfile']);
        $offeringB->load(['studentGroups', 'instructorPool.instructorProfile']);

        $instructorsA = $this->eligibleInstructorIds($offeringA);
        $instructorsB = $this->eligibleInstructorIds($offeringB);
        $groupsA = $offeringA->studentGroups->pluck('id')->all();
        $groupsB = $offeringB->studentGroups->pluck('id')->all();

        if (count($instructorsA) < 2 || count($instructorsB) < 1 || count($groupsA) < 2 || count($groupsB) < 1) {
            $this->command->warn('[Dev] instructor หรือ student group ไม่พอสำหรับสร้างสถานการณ์ (ต้องการ A: 2 inst + 2 group, B: 1 inst + 1 group)');

            return;
        }

        $rooms = Room::where('status', 'active')->orderBy('id')->limit(3)->get();
        if ($rooms->count() < 2) {
            $this->command->warn('[Dev] ต้องมีห้องที่ active อย่างน้อย 2 ห้อง');

            return;
        }

        $lectureType = ActivityType::where('name', 'บรรยาย')->first()
            ?? ActivityType::where('category', 'lecture')->first()
            ?? ActivityType::first();

        if (! $lectureType) {
            $this->command->warn('[Dev] ต้องมี ActivityType อย่างน้อย 1 รายการ');

            return;
        }

        $dates = $this->pickWeekdays($year, 5);
        if (count($dates) < 5) {
            $this->command->warn('[Dev] หา weekday ในช่วงปีการศึกษาไม่ครบ 5 วัน');

            return;
        }

        Schedule::whereIn('course_offering_id', [$offeringA->id, $offeringB->id])->delete();

        DB::transaction(function () use (
            $offeringA, $offeringB, $sharedInstructor,
            $instructorsA, $instructorsB, $groupsA, $groupsB,
            $rooms, $lectureType, $dates
        ): void {
            // ── Day 1: CLEAN (ไม่มี conflict) — 2 slot คนละเวลา/ห้อง/อาจารย์ ──
            $this->createSlot($offeringA, $lectureType, $rooms[0]->id, $dates[0],
                '08:00', '10:00', '[CLEAN] บรรยายเช้า — ไม่ชน',
                [$instructorsA[0]], [$groupsA[0]]);
            $this->createSlot($offeringA, $lectureType, $rooms[1]->id, $dates[0],
                '13:00', '15:00', '[CLEAN] บรรยายบ่าย — ไม่ชน',
                [$instructorsA[1]], [$groupsA[1] ?? $groupsA[0]]);

            // ── Day 2: IN-COURSE INSTRUCTOR OVERLAP ──
            // 2 slot ในวิชา A: อาจารย์คนเดียวกัน เวลาซ้อน ห้องคนละห้อง กลุ่มคนละกลุ่ม
            $this->createSlot($offeringA, $lectureType, $rooms[0]->id, $dates[1],
                '09:00', '11:00', '[IN-COURSE/INSTRUCTOR] slot ที่ 1 — อาจารย์คนเดียวกัน',
                [$instructorsA[0]], [$groupsA[0]]);
            $this->createSlot($offeringA, $lectureType, $rooms[1]->id, $dates[1],
                '10:00', '12:00', '[IN-COURSE/INSTRUCTOR] slot ที่ 2 — อาจารย์คนเดียวกัน เวลาซ้อน',
                [$instructorsA[0]], [$groupsA[1] ?? $groupsA[0]]);

            // ── Day 3: IN-COURSE ROOM OVERLAP ──
            // 2 slot ในวิชา A: ห้องเดียวกัน เวลาซ้อน อาจารย์/กลุ่มคนละชุด
            $this->createSlot($offeringA, $lectureType, $rooms[0]->id, $dates[2],
                '09:00', '11:00', '[IN-COURSE/ROOM] slot ที่ 1 — ห้องเดียวกัน',
                [$instructorsA[0]], [$groupsA[0]]);
            $this->createSlot($offeringA, $lectureType, $rooms[0]->id, $dates[2],
                '10:00', '12:00', '[IN-COURSE/ROOM] slot ที่ 2 — ห้องเดียวกัน เวลาซ้อน',
                [$instructorsA[1]], [$groupsA[1] ?? $groupsA[0]]);

            // ── Day 4: IN-COURSE GROUP OVERLAP ──
            // 2 slot ในวิชา A: กลุ่มนักศึกษาเดียวกัน เวลาซ้อน ห้อง/อาจารย์คนละชุด
            $this->createSlot($offeringA, $lectureType, $rooms[0]->id, $dates[3],
                '09:00', '11:00', '[IN-COURSE/GROUP] slot ที่ 1 — กลุ่มเดียวกัน',
                [$instructorsA[0]], [$groupsA[0]]);
            $this->createSlot($offeringA, $lectureType, $rooms[1]->id, $dates[3],
                '10:00', '12:00', '[IN-COURSE/GROUP] slot ที่ 2 — กลุ่มเดียวกัน เวลาซ้อน',
                [$instructorsA[1]], [$groupsA[0]]);

            // ── Day 5: CROSS-COURSE OVERLAP ──
            // วิชา A และ B ใช้อาจารย์ + ห้องเดียวกัน เวลาซ้อน → cross-course instructor + room
            $this->createSlot($offeringA, $lectureType, $rooms[2]->id ?? $rooms[0]->id, $dates[4],
                '09:00', '11:00', '[CROSS-COURSE] วิชา A — อาจารย์/ห้องชนกับวิชา B',
                [$sharedInstructor->id], [$groupsA[0]]);
            $this->createSlot($offeringB, $lectureType, $rooms[2]->id ?? $rooms[0]->id, $dates[4],
                '10:00', '12:00', '[CROSS-COURSE] วิชา B — อาจารย์/ห้องชนกับวิชา A',
                [$sharedInstructor->id], [$groupsB[0]]);
        });

        $this->refreshConflictReadState($year);

        $coordA = User::find($offeringA->coordinator_id);
        $coordB = User::find($offeringB->coordinator_id);
        $monthLabel = Carbon::parse($dates[0])->locale('th')->isoFormat('MMMM') . ' ' . (Carbon::parse($dates[0])->year + 543);
        $urlA = url(sprintf('/maker/course-offerings/%d/schedules?period=month&date=%s', $offeringA->id, $dates[0]));
        $urlB = url(sprintf('/maker/course-offerings/%d/schedules?period=month&date=%s', $offeringB->id, $dates[0]));

        $this->command->info('[Dev] สร้างสถานการณ์ schedule conflict 10 รายการ (5 วัน × 4 ชนิด + clean) สำเร็จ');
        $this->command->info('');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('  วิธีดูบนหน้าเว็บ:');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info(sprintf('  วิชา A: %s (9 slots — clean + in-course conflicts)', $offeringA->course?->course_code));
        $this->command->info(sprintf('         coordinator: %s (username: %s)', $coordA?->name, $coordA?->username));
        $this->command->info(sprintf('         URL: %s', $urlA));
        $this->command->info('');
        $this->command->info(sprintf('  วิชา B: %s (1 slot — cross-course conflict)', $offeringB->course?->course_code));
        $this->command->info(sprintf('         coordinator: %s (username: %s)', $coordB?->name, $coordB?->username));
        $this->command->info(sprintf('         URL: %s', $urlB));
        $this->command->info('');
        $this->command->info(sprintf('  เดือนที่ต้องเปิดดู: %s (ช่วง %s ถึง %s)', $monthLabel, $dates[0], $dates[4]));
        $this->command->info('  Password เริ่มต้น: password');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }

    /**
     * @return array{0: ?CourseOffering, 1: ?CourseOffering}
     */
    private function pickPairedOfferings(AcademicYear $year): array
    {
        $offerings = CourseOffering::query()
            ->where('academic_year_id', $year->id)
            ->with('course')
            ->orderBy('id')
            ->get()
            ->filter(fn (CourseOffering $o) => $o->course?->department_id);

        $byDepartment = $offerings->groupBy(fn (CourseOffering $o) => (int) $o->course->department_id);

        foreach ($byDepartment as $group) {
            if ($group->count() >= 2) {
                return [$group->values()[0], $group->values()[1]];
            }
        }

        // fallback: คนละภาควิชาก็เอา (cross-course department gate อาจล้ม แต่ใช้ทดสอบ in-course ได้)
        return [$offerings->values()[0] ?? null, $offerings->values()[1] ?? null];
    }

    private function ensureStudentGroups(CourseOffering $offering): void
    {
        if ($offering->studentGroups()->exists()) {
            return;
        }

        $total = max(60, (int) ($offering->total_student_count ?: 60));
        $per = 30;
        $count = max(2, (int) ceil($total / $per));

        for ($i = 0; $i < $count; $i++) {
            $offering->studentGroups()->create([
                'group_code' => 'A' . ($i + 1),
                'student_count' => $per,
                'color_code' => '#2563eb',
            ]);
        }
    }

    private function ensureSharedInstructor(CourseOffering $a, CourseOffering $b): User
    {
        $eligibleA = $this->eligibleInstructorIds($a);
        $eligibleB = $this->eligibleInstructorIds($b);
        $shared = array_values(array_intersect($eligibleA, $eligibleB));

        if (! empty($shared)) {
            return User::find($shared[0]);
        }

        $instructor = User::find($eligibleA[0]);

        if ((int) $instructor->instructorProfile?->department_id === (int) $b->course?->department_id) {
            $b->instructorPool()->syncWithoutDetaching([
                $instructor->id => ['role_in_course' => 'instructor'],
            ]);
            $b->course?->instructors()->syncWithoutDetaching([
                $instructor->id => ['course_role_id' => null],
            ]);
        }

        return $instructor;
    }

    private function ensureOfferingInstructors(CourseOffering $offering, int $minimum): void
    {
        $offering->load(['course', 'instructorPool.instructorProfile']);
        $deptId = $offering->course?->department_id;

        if (! $deptId) {
            return;
        }

        $current = count($this->eligibleInstructorIds($offering));
        $sequence = 1;

        while ($current < $minimum) {
            $instructor = $this->createDevInstructor($offering, $deptId, $sequence++);

            $offering->instructorPool()->syncWithoutDetaching([
                $instructor->id => ['role_in_course' => 'instructor'],
            ]);
            $offering->course?->instructors()->syncWithoutDetaching([
                $instructor->id => ['course_role_id' => null],
            ]);

            $offering->load('instructorPool.instructorProfile');
            $current = count($this->eligibleInstructorIds($offering));
        }
    }

    private function createDevInstructor(CourseOffering $offering, int $departmentId, int $sequence): User
    {
        $username = sprintf('dev_conflict_%d_%02d', $offering->id, $sequence);

        $user = User::firstOrCreate(
            ['username' => $username],
            [
                'prefix' => 'อ.',
                'employee_id' => sprintf('9%04d%02d', $offering->id, $sequence),
                'name' => "ทดสอบ Conflict {$offering->id}-{$sequence}",
                'email' => $username . '@dev.local',
                'password' => 'password',
                'is_active' => true,
            ]
        );

        UserRole::firstOrCreate(
            ['user_id' => $user->id, 'role' => 'instructor'],
            ['is_primary' => true]
        );

        InstructorProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'title' => 'อาจารย์',
                'department_id' => $departmentId,
                'employment_type' => 'พนักงานมหาวิทยาลัย',
                'academic_degree' => 'ปริญญาโท',
                'hired_at' => '2020-06-01',
                'teaching_pct' => 50,
                'research_pct' => 25,
                'service_pct' => 15,
                'culture_pct' => 5,
                'other_pct' => 5,
            ]
        );

        return $user->fresh('instructorProfile');
    }

    /**
     * @return array<int, int>
     */
    private function eligibleInstructorIds(CourseOffering $offering): array
    {
        $deptId = $offering->course?->department_id;

        return $offering->instructorPool
            ->filter(fn ($user) => ! $deptId
                || (int) $user->instructorProfile?->department_id === (int) $deptId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function pickWeekdays(AcademicYear $year, int $count): array
    {
        $start = Carbon::parse($year->start_date)->startOfDay();
        $end = Carbon::parse($year->end_date)->endOfDay();
        $today = Carbon::today();

        if ($today->between($start, $end)) {
            $start = $today;
        }

        $dates = [];
        foreach (CarbonPeriod::create($start, $end) as $date) {
            $d = Carbon::parse($date);
            if ($d->isWeekday()) {
                $dates[] = $d->toDateString();
                if (count($dates) >= $count) {
                    break;
                }
            }
        }

        return $dates;
    }

    /**
     * @param  array<int, int>  $instructorIds
     * @param  array<int, int>  $groupIds
     */
    private function createSlot(
        CourseOffering $offering,
        ActivityType $activityType,
        int $roomId,
        string $date,
        string $start,
        string $end,
        string $topic,
        array $instructorIds,
        array $groupIds
    ): void {
        $schedule = Schedule::create([
            'course_offering_id' => $offering->id,
            'activity_type_id' => $activityType->id,
            'room_id' => $roomId,
            'practicum_series_id' => null,
            'start_date' => $date,
            'end_date' => $date,
            'teaching_date' => $date,
            'start_time' => $start . ':00',
            'end_time' => $end . ':00',
            'topic' => $topic,
            'capacity_required' => (int) $offering->total_student_count ?: null,
            'status' => 'draft',
            'remark' => '[Dev seeder] สร้างเพื่อทดสอบ conflict UI',
        ]);

        $payload = [];
        $first = true;
        foreach ($instructorIds as $id) {
            $payload[$id] = ['is_lead' => $first];
            $first = false;
        }

        $schedule->instructors()->sync($payload);
        $schedule->studentGroups()->sync($groupIds);
    }

    private function refreshConflictReadState(AcademicYear $year): void
    {
        if (config('conflicts.async_reads')) {
            Artisan::call('conflicts:recompute', [
                '--academic-year' => $year->id,
                '--sync' => true,
            ]);

            return;
        }

        $coordinatorIds = CourseOffering::where('academic_year_id', $year->id)
            ->pluck('coordinator_id')
            ->filter()
            ->unique();

        $badges = app(NavigationBadgeService::class);
        foreach ($coordinatorIds as $coordinatorId) {
            $badges->refreshCourseHeadConflictCount((int) $coordinatorId, (int) $year->id);
        }
    }
}
