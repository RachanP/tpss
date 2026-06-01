@php
    $thaiDays = [
        1 => 'วันจันทร์',
        2 => 'วันอังคาร',
        3 => 'วันพุธ',
        4 => 'วันพฤหัสบดี',
        5 => 'วันศุกร์',
        6 => 'วันเสาร์',
        7 => 'วันอาทิตย์',
    ];
    $formatDate = fn ($date) => $date ? \App\Support\ThaiDate::date($date) : '-';
    $formatTime = fn ($value) => substr((string) $value, 0, 5);
    $formatDuration = fn (int $minutes) => $minutes >= 60
        ? (int) floor($minutes / 60) . ' ชม.' . ($minutes % 60 ? ' ' . ($minutes % 60) . ' นาที' : '')
        : $minutes . ' นาที';
    $durationForSchedule = function ($schedule) {
        $startTime = (string) $schedule->start_time;
        $endTime = (string) $schedule->end_time;
        $start = \Carbon\CarbonImmutable::createFromFormat('H:i:s', strlen($startTime) === 5 ? $startTime . ':00' : $startTime);
        $end = \Carbon\CarbonImmutable::createFromFormat('H:i:s', strlen($endTime) === 5 ? $endTime . ':00' : $endTime);

        return (int) max(0, $start->diffInMinutes($end));
    };
    $activityTone = function ($schedule) {
        $color = $schedule->activityType?->color_code ?: 'var(--brand-navy)';
        return str_starts_with((string) $color, '#') || str_starts_with((string) $color, 'oklch') || str_starts_with((string) $color, 'var(')
            ? $color
            : 'var(--brand-navy)';
    };
    $groupTone = function ($group) {
        $color = (string) ($group->color_code ?? '');
        return str_starts_with($color, '#') || str_starts_with($color, 'oklch') || str_starts_with($color, 'var(')
            ? $color
            : 'var(--schedule-border-strong)';
    };
    $eligibleScheduleInstructors = function ($offering) {
        $departmentId = $offering?->course?->department_id;
        $pool = $offering?->instructorPool ?? collect();

        if (! $departmentId) {
            return $pool;
        }

        return $pool
            ->filter(fn ($instructor) => (int) $instructor->instructorProfile?->department_id === (int) $departmentId)
            ->values();
    };
    $scheduleDepartmentInstructors = function ($schedule) use ($eligibleScheduleInstructors) {
        $eligibleIds = $eligibleScheduleInstructors($schedule?->courseOffering)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $schedule?->instructors
            ? $schedule->instructors
                ->filter(fn ($instructor) => in_array((int) $instructor->id, $eligibleIds, true))
                ->values()
            : collect();
    };
    $scheduleInstructorText = function ($schedule) use ($scheduleDepartmentInstructors) {
        $instructors = $scheduleDepartmentInstructors($schedule);

        return $instructors->isNotEmpty()
            ? ($instructors->count() === 1
                ? ($instructors->first()->formatted_name ?? $instructors->first()->name)
                : $instructors->count() . ' ท่าน')
            : '-';
    };
    $scheduleIncompleteReasons = function ($schedule) use ($scheduleDepartmentInstructors) {
        return collect([
            $scheduleDepartmentInstructors($schedule)->isEmpty() ? 'รอกำหนดผู้สอน' : null,
            $schedule?->studentGroups?->isEmpty() ? 'รอกำหนดกลุ่ม' : null,
        ])->filter()->values();
    };
@endphp

@foreach($groupedSchedules as $dateString => $daySchedules)
    @php
        $dateKey = str_replace('-', '', $dateString);
    @endphp
    <template data-schedule-lazy-date="{{ $dateKey }}">
        @include('shared.schedules._list_rows')
    </template>
@endforeach
