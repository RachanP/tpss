<?php

namespace Database\Seeders;

use App\Jobs\ConflictRecomputeJob;
use App\Models\AcademicYear;
use App\Models\ActivityType;
use App\Models\Course;
use App\Models\CourseOffering;
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
use Illuminate\Support\Facades\Hash;

class ScheduleFlowSeeder extends Seeder
{
    public function run(): void
    {
        $year = $this->activateSchedulingYear();

        Course::query()
            ->where('default_semester', $year->semester)
            ->whereNotNull('head_instructor_id')
            ->update(['status' => 'active']);

        $this->call(CourseOfferingSeeder::class);

        $offerings = CourseOffering::query()
            ->where('academic_year_id', $year->id)
            ->with(['course.department', 'academicYear', 'instructorPool', 'studentGroups'])
            ->orderBy('id')
            ->get();

        if ($offerings->count() < 2) {
            $this->command?->warn('ScheduleFlowSeeder: not enough offerings to build conflict demo.');

            return;
        }

        $offerings->each(function (CourseOffering $offering, int $index): void {
            $this->ensureStudentGroup($offering, 'A1', 30);
            $this->ensureGeneratedInstructor($offering, $index + 1);
        });

        $activity = ActivityType::query()->orderBy('id')->firstOrFail();
        $room = Room::query()
            ->where('status', 'active')
            ->whereHas('locationType', fn ($query) => $query->where('is_shared', false))
            ->orderBy('id')
            ->first()
            ?? Room::query()->where('status', 'active')->orderBy('id')->firstOrFail();

        $primary = $offerings->first()->fresh(['instructorPool', 'studentGroups', 'academicYear']);
        $secondary = $offerings->skip(1)->first()->fresh(['instructorPool', 'studentGroups', 'academicYear']);
        $demoDate = CarbonImmutable::parse($year->start_date)->next('monday')->toDateString();

        $this->clearDemoSchedules($offerings->pluck('id')->all());
        $this->seedRoomConflict($primary, $secondary, $activity, $room, $demoDate);
        $this->seedStackDemo($primary, $activity, $room, $demoDate);
        $this->refreshConflictBadges($year);
        $this->warmAsyncConflictReadModel($year);
    }

    private function activateSchedulingYear(): AcademicYear
    {
        $year = AcademicYear::query()
            ->orderByDesc('start_date')
            ->orderByDesc('semester')
            ->firstOrFail();

        AcademicYear::query()->whereKeyNot($year->id)->update([
            'is_active' => false,
            'phase' => 'preparation',
        ]);

        $year->update([
            'is_active' => true,
            'phase' => 'scheduling',
        ]);

        return $year->fresh();
    }

    private function ensureGeneratedInstructor(CourseOffering $offering, int $number): User
    {
        $departmentId = $offering->course?->department_id;
        $username = "schedule_offering_{$offering->id}_instructor";

        $user = User::query()->updateOrCreate(
            ['username' => $username],
            [
                'prefix' => 'อ.',
                'employee_id' => "SF{$offering->id}",
                'name' => "Schedule Offering Instructor {$number}",
                'email' => "{$username}@example.test",
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );

        UserRole::query()->firstOrCreate(
            ['user_id' => $user->id, 'role' => 'instructor'],
            ['is_primary' => true]
        );

        InstructorProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'title' => 'อาจารย์',
                'department_id' => $departmentId,
                'teaching_pct' => 50,
                'research_pct' => 20,
                'service_pct' => 10,
                'culture_pct' => 10,
                'other_pct' => 10,
            ]
        );

        $offering->instructorPool()->syncWithoutDetaching([
            $user->id => ['role_in_course' => 'instructor'],
        ]);

        return $user;
    }

    private function ensureStudentGroup(CourseOffering $offering, string $code, int $count): StudentGroup
    {
        return StudentGroup::query()->firstOrCreate(
            [
                'course_offering_id' => $offering->id,
                'group_code' => $code,
            ],
            [
                'student_count' => $count,
                'color_code' => '#2563eb',
            ]
        );
    }

    /**
     * @param  array<int>  $offeringIds
     */
    private function clearDemoSchedules(array $offeringIds): void
    {
        Schedule::query()
            ->whereIn('course_offering_id', $offeringIds)
            ->each(function (Schedule $schedule): void {
                $schedule->instructors()->detach();
                $schedule->studentGroups()->detach();
                $schedule->delete();
            });
    }

    private function seedRoomConflict(
        CourseOffering $primary,
        CourseOffering $secondary,
        ActivityType $activity,
        Room $room,
        string $date
    ): void {
        $this->makeSchedule($primary, $activity, $room, $date, '09:00', '11:00', 'Demo room conflict A');
        $this->makeSchedule($secondary, $activity, $room, $date, '10:00', '12:00', 'Demo room conflict B');
    }

    private function seedStackDemo(CourseOffering $offering, ActivityType $activity, Room $room, string $date): void
    {
        $slots = [
            ['09:00', '11:00', 'เวียนฐาน 1', 'A1'],
            ['09:20', '11:20', 'เวียนฐาน 2', 'A2'],
            ['09:40', '11:40', 'เวียนฐาน 3', 'A3'],
            ['10:00', '12:00', 'อภิปรายหลังเวียนฐาน 1', 'A4'],
            ['10:20', '12:20', 'สรุปผลการฝึกปฏิบัติรายกลุ่ม 1', 'A5'],
            ['10:40', '12:40', 'สรุปผลการฝึกปฏิบัติรายกลุ่ม 2', 'A6'],
        ];

        foreach ($slots as [$start, $end, $topic, $label]) {
            $this->makeSchedule($offering, $activity, $room, $date, $start, $end, $topic, $label);
        }
    }

    private function makeSchedule(
        CourseOffering $offering,
        ActivityType $activity,
        Room $room,
        string $date,
        string $start,
        string $end,
        string $topic,
        ?string $subGroupLabel = null
    ): Schedule {
        $group = $offering->studentGroups()->orderBy('id')->first()
            ?? $this->ensureStudentGroup($offering, 'A1', 30);
        $instructor = $offering->instructorPool()->orderBy('users.id')->first();

        $schedule = Schedule::query()->create([
            'course_offering_id' => $offering->id,
            'activity_type_id' => $activity->id,
            'room_id' => $room->id,
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'topic' => $topic,
            'capacity_required' => $group->student_count,
            'sub_group_label' => $subGroupLabel,
            'status' => 'draft',
        ]);

        if ($instructor) {
            $schedule->instructors()->sync([$instructor->id => ['is_lead' => true]]);
        }

        $schedule->studentGroups()->sync([$group->id]);

        return $schedule;
    }

    private function refreshConflictBadges(AcademicYear $year): void
    {
        $service = app(NavigationBadgeService::class);

        CourseOffering::query()
            ->where('academic_year_id', $year->id)
            ->pluck('coordinator_id')
            ->filter()
            ->unique()
            ->each(fn ($userId) => $service->refreshCourseHeadConflictCount((int) $userId, (int) $year->id));
    }

    private function warmAsyncConflictReadModel(AcademicYear $year): void
    {
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
