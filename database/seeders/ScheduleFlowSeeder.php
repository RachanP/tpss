<?php

namespace Database\Seeders;

use App\Jobs\ConflictRecomputeJob;
use App\Models\AcademicYear;
use App\Models\ActivityType;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseRole;
use App\Models\Department;
use App\Models\InstructorProfile;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ScheduleConflictRun;
use App\Models\StudentGroup;
use App\Models\User;
use App\Models\UserRole;
use App\Services\NavigationBadgeService;
use App\Services\ScheduleConflictIndex;
use App\Services\ScheduleConflictInvalidationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ScheduleFlowSeeder extends Seeder
{
    public function run(): void
    {
        $year = $this->activateSchedulingYear();
        $this->activateDemoCourses();

        $this->call(CourseOfferingSeeder::class);

        $offerings = $this->activeOfferings($year);
        if ($offerings->count() < 2) {
            $this->command?->warn('ScheduleFlowSeeder: not enough active offerings for demo schedules.');

            return;
        }

        $this->ensureStudentGroups($offerings);
        $this->ensureGeneratedInstructors($offerings);

        $offerings = $this->activeOfferings($year);
        $this->seedStackedConflictDemo($year, $offerings);

        if (config('conflicts.async_reads')) {
            $this->warmAsyncReadModel($year);
        } else {
            $this->warmCourseHeadBadges($year);
        }
    }

    private function activateSchedulingYear(): AcademicYear
    {
        $year = AcademicYear::query()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->firstOrFail();

        AcademicYear::query()
            ->whereKeyNot($year->id)
            ->update(['is_active' => false]);

        $year->forceFill([
            'is_active' => true,
            'phase' => 'scheduling',
        ])->save();

        return $year->refresh();
    }

    private function activateDemoCourses(): void
    {
        Course::query()
            ->whereNotNull('head_instructor_id')
            ->orderBy('course_code')
            ->limit(6)
            ->get()
            ->each(fn (Course $course) => $course->forceFill(['status' => 'active'])->save());
    }

    private function activeOfferings(AcademicYear $year)
    {
        return CourseOffering::query()
            ->with(['course', 'academicYear', 'studentGroups', 'instructorPool'])
            ->where('academic_year_id', $year->id)
            ->whereHas('course', fn ($query) => $query->where('status', 'active'))
            ->join('courses', 'courses.id', '=', 'course_offerings.course_id')
            ->orderBy('courses.course_code')
            ->select('course_offerings.*')
            ->get();
    }

    private function ensureStudentGroups($offerings): void
    {
        $colors = ['#2563eb', '#0891b2', '#16a34a', '#ca8a04', '#dc2626', '#7c3aed'];

        foreach ($offerings as $index => $offering) {
            StudentGroup::query()->updateOrCreate(
                [
                    'course_offering_id' => $offering->id,
                    'group_code' => 'A1',
                ],
                [
                    'student_count' => max(1, min((int) ($offering->total_student_count ?: 30), 30)),
                    'color_code' => $colors[$index % count($colors)],
                ]
            );
        }
    }

    private function ensureGeneratedInstructors($offerings): void
    {
        $roleId = CourseRole::query()->orderBy('id')->value('id');
        $departmentId = Department::query()->orderBy('id')->value('id');

        foreach ($offerings->values() as $index => $offering) {
            $number = $index + 1;
            $username = sprintf('schedule_offering_%03d', $number);
            $name = sprintf('Schedule Flow Instructor %03d', $number);

            $user = User::query()->updateOrCreate(
                ['username' => $username],
                [
                    'prefix' => null,
                    'employee_id' => sprintf('SOF%05d', $number),
                    'name' => $name,
                    'email' => "{$username}@example.test",
                    'password' => 'password',
                    'is_active' => true,
                ]
            );

            UserRole::query()->firstOrCreate(
                ['user_id' => $user->id, 'role' => 'instructor'],
                ['is_primary' => true]
            );

            if ($departmentId) {
                InstructorProfile::query()->updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'title' => 'Instructor',
                        'department_id' => $departmentId,
                        'employment_type' => 'Demo',
                        'academic_degree' => 'Demo',
                        'teaching_pct' => 50,
                        'research_pct' => 20,
                        'service_pct' => 10,
                        'culture_pct' => 10,
                        'other_pct' => 10,
                    ]
                );
            }

            $offering->instructorPool()->syncWithoutDetaching([
                $user->id => [
                    'role_in_course' => 'instructor',
                    'course_role_id' => $roleId,
                ],
            ]);
        }
    }

    private function seedStackedConflictDemo(AcademicYear $year, $offerings): void
    {
        $primary = $offerings->first();
        $activity = ActivityType::query()
            ->where('category', 'practicum')
            ->orderBy('id')
            ->first()
            ?? ActivityType::query()->orderBy('id')->firstOrFail();
        $room = Room::query()
            ->where('status', 'active')
            ->whereHas('locationType', fn ($query) => $query->where('is_shared', false))
            ->orderBy('id')
            ->first()
            ?? Room::query()->where('status', 'active')->orderBy('id')->firstOrFail();
        $group = $primary->studentGroups()->orderBy('group_code')->firstOrFail();
        $instructor = $primary->instructorPool()
            ->where('username', 'like', 'schedule_offering_%')
            ->orderBy('users.id')
            ->first()
            ?? $primary->instructorPool()->orderBy('users.id')->first();

        $date = CarbonImmutable::parse($year->start_date)->addDays(2)->toDateString();

        DB::transaction(function () use ($primary, $activity, $room, $group, $instructor, $date): void {
            $this->deleteExistingScheduleFlowDemo($primary->id);

            $rows = [
                ['topic' => 'เวียนฐาน A', 'label' => 'A'],
                ['topic' => 'เวียนฐาน B', 'label' => 'B'],
                ['topic' => 'เวียนฐาน C', 'label' => 'C'],
                ['topic' => 'อภิปรายหลังเวียนฐาน D', 'label' => 'D'],
                ['topic' => 'อภิปรายหลังเวียนฐาน E', 'label' => 'E'],
                ['topic' => 'สรุปผลการฝึกปฏิบัติรายกลุ่ม F', 'label' => 'F'],
            ];

            foreach ($rows as $row) {
                $schedule = Schedule::query()->create([
                    'course_offering_id' => $primary->id,
                    'activity_type_id' => $activity->id,
                    'room_id' => $room->id,
                    'practicum_series_id' => null,
                    'start_date' => $date,
                    'end_date' => $date,
                    'teaching_date' => $date,
                    'start_time' => '09:00',
                    'end_time' => '12:00',
                    'topic' => $row['topic'],
                    'capacity_required' => $group->student_count,
                    'sub_group_label' => $row['label'],
                    'status' => 'draft',
                    'remark' => 'schedule_flow_demo',
                ]);

                if ($instructor) {
                    $schedule->instructors()->sync([$instructor->id => ['is_lead' => true]]);
                }

                $schedule->studentGroups()->sync([$group->id]);
            }
        });
    }

    private function deleteExistingScheduleFlowDemo(int $courseOfferingId): void
    {
        Schedule::query()
            ->where('course_offering_id', $courseOfferingId)
            ->where(function ($query): void {
                $query->where('remark', 'schedule_flow_demo')
                    ->orWhere('topic', 'like', 'เวียนฐาน%')
                    ->orWhere('topic', 'like', 'อภิปรายหลังเวียนฐาน%')
                    ->orWhere('topic', 'like', 'สรุปผลการฝึกปฏิบัติรายกลุ่ม%');
            })
            ->each(function (Schedule $schedule): void {
                $schedule->instructors()->detach();
                $schedule->studentGroups()->detach();
                $schedule->delete();
            });
    }

    private function warmCourseHeadBadges(AcademicYear $year): void
    {
        $badgeService = app(NavigationBadgeService::class);

        CourseOffering::query()
            ->where('academic_year_id', $year->id)
            ->whereHas('course', fn ($query) => $query->where('status', 'active'))
            ->pluck('coordinator_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->each(fn (int $userId) => $badgeService->refreshCourseHeadConflictCount($userId, $year->id));
    }

    private function warmAsyncReadModel(AcademicYear $year): void
    {
        $latestGeneration = (int) ScheduleConflictRun::query()
            ->where('academic_year_id', $year->id)
            ->max('generation');

        $run = ScheduleConflictRun::query()->create([
            'academic_year_id' => $year->id,
            'status' => 'pending',
            'generation' => $latestGeneration + 1,
            'source' => 'manual',
            'requested_at' => now(),
            'result_count' => 0,
            'metadata' => ['seeded_by' => self::class],
        ]);

        (new ConflictRecomputeJob(
            (int) $year->id,
            (int) $run->id,
            (int) $run->generation,
            'manual'
        ))->handle(
            app(ScheduleConflictIndex::class),
            app(ScheduleConflictInvalidationService::class)
        );
    }
}
