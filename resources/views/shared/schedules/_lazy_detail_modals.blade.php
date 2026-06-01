@php
    $formatDate = fn ($date) => $date ? \App\Support\ThaiDate::date($date) : '-';
    $formatTime = fn ($value) => substr((string) $value, 0, 5);
    $formatDuration = fn (int $minutes) => $minutes >= 60
        ? (int) floor($minutes / 60) . ' ชม.' . ($minutes % 60 ? ' ' . ($minutes % 60) . ' นาที' : '')
        : $minutes . ' นาที';
    $thaiDays = [
        1 => 'วันจันทร์',
        2 => 'วันอังคาร',
        3 => 'วันพุธ',
        4 => 'วันพฤหัสบดี',
        5 => 'วันศุกร์',
        6 => 'วันเสาร์',
        7 => 'วันอาทิตย์',
    ];
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
            : 'oklch(58% 0.095 84)';
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
    $scheduleIncompleteReasons = function ($schedule) use ($scheduleDepartmentInstructors) {
        return collect([
            $scheduleDepartmentInstructors($schedule)->isEmpty() ? 'รอกำหนดผู้สอน' : null,
            $schedule?->studentGroups?->isEmpty() ? 'รอกำหนดกลุ่ม' : null,
        ])->filter()->values();
    };
    $modalSchedules = $modalSchedules ?? collect();
    $scheduleAlertMessages = function ($errors, ?string $key = null) {
        $messages = $key && $errors->has($key)
            ? collect($errors->get($key))
            : collect($errors->all());

        return $messages
            ->flatMap(fn ($message) => preg_split('/\s+\/\s+/u', (string) $message) ?: [])
            ->map(fn ($message) => trim((string) $message))
            ->filter()
            ->unique()
            ->values();
    };
    $conflictFieldLabels = [
        'instructor_overlap' => 'ผู้สอนชน',
        'room_overlap' => 'ห้อง/สถานที่ชน',
        'group_overlap' => 'กลุ่มนักศึกษาชน',
    ];
    $conflictFieldNote = function ($conflicts, array $types, string $fieldLabel) use ($conflictFieldLabels) {
        $items = collect($conflicts)
            ->filter(fn ($conflict) => in_array($conflict['type'] ?? '', $types, true))
            ->values();

        if ($items->isEmpty()) {
            return null;
        }

        return $fieldLabel . 'มีข้อมูลซ้ำกับรายการอื่น ' . $items->count() . ' จุด';
    };
    $scheduleResourceCopyItems = ($allSchedules ?? $modalSchedules)
        ->filter(fn ($schedule) => $schedule->schedule_template_id && $scheduleIncompleteReasons($schedule)->isEmpty())
        ->map(function ($schedule) use ($formatDate, $scheduleDepartmentInstructors) {
            $instructors = $scheduleDepartmentInstructors($schedule);
            $leadInstructor = $schedule->instructors->first(fn ($instructor) => (bool) $instructor->pivot?->is_lead);

            return [
                'id' => (string) $schedule->id,
                'template_id' => (string) $schedule->schedule_template_id,
                'week' => $schedule->series_week_number ? (int) $schedule->series_week_number : null,
                'label' => collect([
                    $schedule->series_week_number ? 'สัปดาห์ ' . $schedule->series_week_number : null,
                    $formatDate($schedule->start_date ?? $schedule->teaching_date),
                    $schedule->studentGroups->pluck('group_code')->implode(', '),
                    $instructors->map(fn ($instructor) => $instructor->formatted_name ?? $instructor->name)->implode(', '),
                ])->filter()->implode(' · '),
                'room_id' => $schedule->room_id ? (string) $schedule->room_id : '',
                'remark' => (string) ($schedule->remark ?? ''),
                'lead_instructor_id' => $leadInstructor?->id ? (string) $leadInstructor->id : '',
                'instructor_ids' => $instructors->pluck('id')->map(fn ($id) => (string) $id)->values(),
                'student_group_ids' => $schedule->studentGroups->pluck('id')->map(fn ($id) => (string) $id)->values(),
            ];
        })
        ->values();
@endphp

@include('shared.schedules._schedule_modals', ['modalSchedules' => $modalSchedules, 'lazyModal' => true])
