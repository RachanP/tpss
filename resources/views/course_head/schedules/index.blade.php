@php
    $availableOfferings = ($availableOfferings ?? collect())->filter(fn ($offering) => $offering->academicYear?->phase === 'scheduling')->values();
    $activityTypes = $activityTypes ?? collect();
    $rooms = $rooms ?? collect();
    $isWorkspace = (bool) ($isWorkspace ?? false);
    $activeOfferingCount = $availableOfferings->filter(fn ($offering) => $offering->academicYear?->phase === 'scheduling')->count();
    $academicYear = $courseOffering?->academicYear;
    $canEdit = $isWorkspace ? $activeOfferingCount > 0 : ($courseOffering && $academicYear?->phase === 'scheduling');
    $schedulingOfferings = ($isWorkspace ? $availableOfferings : collect($courseOffering ? [$courseOffering] : []))
        ->filter(fn ($offering) => $offering?->academicYear?->phase === 'scheduling')
        ->values();
    $createAction = $isWorkspace
        ? route('maker.schedules.store')
        : ($courseOffering ? route('maker.course_offerings.schedules.store', $courseOffering) : '#');
    $queryOfferingId = request('course_offering_id');
    $selectedOfferingId = (string) old('course_offering_id', $queryOfferingId ?: ($schedulingOfferings->first()?->id ?? $courseOffering?->id ?? ''));
    $oldModalMode = old('modal_mode');
    $openEditScheduleId = (string) old('edit_schedule_id', request('edit_schedule_id', ''));
    $selectedInstructorIds = collect(old('instructor_ids', []))->map(fn ($id) => (string) $id)->all();
    $selectedGroupIds = collect(old('student_group_ids', []))->map(fn ($id) => (string) $id)->all();
    $leadInstructorId = (string) old('lead_instructor_id', '');
    $openCreateModal = $canEdit && ! $openEditScheduleId && (
        request('modal') === 'create'
        || $oldModalMode === 'create'
        || old('start_date')
        || old('course_offering_id')
    );
    $occurrencesByDate = $occurrences->groupBy(fn ($item) => $item['date']->toDateString());
    $gridOccurrenceSource = (! $isWorkspace && ($allSchedules ?? collect())->isNotEmpty())
        ? $allSchedules
        : ($schedules ?? collect());
    $gridOccurrences = $gridOccurrenceSource
        ->flatMap(function ($schedule) use ($weekStart, $weekEnd) {
            $startDate = \Carbon\CarbonImmutable::parse($schedule->start_date ?? $schedule->teaching_date);
            $endDate = \Carbon\CarbonImmutable::parse($schedule->end_date ?? $schedule->teaching_date);
            $rangeStart = $startDate->greaterThan($weekStart) ? $startDate : $weekStart;
            $rangeEnd = $endDate->lessThan($weekEnd) ? $endDate : $weekEnd;

            if ($rangeStart->greaterThan($rangeEnd)) {
                return collect();
            }

            $startTime = strlen((string) $schedule->start_time) === 5 ? $schedule->start_time . ':00' : (string) $schedule->start_time;
            $endTime = strlen((string) $schedule->end_time) === 5 ? $schedule->end_time . ':00' : (string) $schedule->end_time;
            $durationMinutes = (int) max(0, \Carbon\CarbonImmutable::createFromFormat('H:i:s', $startTime)
                ->diffInMinutes(\Carbon\CarbonImmutable::createFromFormat('H:i:s', $endTime)));

            return collect(\Carbon\CarbonPeriod::create($rangeStart, $rangeEnd))
                ->filter(fn ($date) => $date->dayOfWeekIso <= 5)
                ->map(fn ($date) => [
                    'schedule' => $schedule,
                    'date' => \Carbon\CarbonImmutable::parse($date),
                    'duration_minutes' => $durationMinutes,
                    'time_slot' => substr((string) $schedule->start_time, 0, 2) . ':00',
                ]);
        })
        ->sortBy(fn ($item) => $item['date']->toDateString()
            . ' ' . substr((string) $item['schedule']->start_time, 0, 8)
            . ' ' . str_pad((string) $item['schedule']->id, 10, '0', STR_PAD_LEFT))
        ->values();
    $gridOccurrencesByDate = $gridOccurrences->groupBy(fn ($item) => $item['date']->toDateString());
    $gridTimeSlots = collect(range(6, 16))
        ->map(fn (int $hour) => sprintf('%02d:00', $hour))
        ->merge($gridOccurrences->pluck('time_slot'))
        ->filter()
        ->unique()
        ->sortBy(fn (string $slot) => (int) substr($slot, 0, 2))
        ->values()
        ->all();
    // ── week-grid: คำนวณจำนวนแถวที่กิจกรรมครอบตาม duration (span) ──
    $gridSlotCount = count($gridTimeSlots);
    $gridSlotIndex = [];
    foreach ($gridTimeSlots as $gridSlotIdx => $gridSlotValue) {
        $gridSlotIndex[$gridSlotValue] = $gridSlotIdx;
    }
    $occurrenceSlotSpan = function ($occurrence) use ($gridTimeSlots, $gridSlotIndex, $gridSlotCount) {
        $startIdx = $gridSlotIndex[$occurrence['time_slot']] ?? null;
        if ($startIdx === null) {
            return 1;
        }
        $endTime = (string) $occurrence['schedule']->end_time;
        $endHour = (int) substr($endTime, 0, 2);
        $endMinute = (int) substr($endTime, 3, 2);
        $endCeilHour = $endMinute > 0 ? $endHour + 1 : $endHour;
        $span = 0;
        for ($j = $startIdx; $j < $gridSlotCount; $j++) {
            if ((int) substr($gridTimeSlots[$j], 0, 2) < $endCeilHour) {
                $span++;
            } else {
                break;
            }
        }
        return max(1, min($span, $gridSlotCount - $startIdx));
    };
    // เซลล์ (วันที่|ช่วงเวลา) ที่ถูกครอบโดยกิจกรรมที่เริ่มก่อนหน้า — ไม่ต้อง render เซลล์ว่าง
    $gridCoveredKeys = [];
    foreach ($gridOccurrences as $gridOccurrence) {
        $startIdx = $gridSlotIndex[$gridOccurrence['time_slot']] ?? null;
        if ($startIdx === null) {
            continue;
        }
        $coverSpan = $occurrenceSlotSpan($gridOccurrence);
        $coverDate = $gridOccurrence['date']->toDateString();
        for ($j = $startIdx + 1; $j < $startIdx + $coverSpan; $j++) {
            $gridCoveredKeys[$coverDate . '|' . $gridTimeSlots[$j]] = true;
        }
    }
    $thaiDays = [
        1 => 'วันจันทร์',
        2 => 'วันอังคาร',
        3 => 'วันพุธ',
        4 => 'วันพฤหัสบดี',
        5 => 'วันศุกร์',
        6 => 'วันเสาร์',
        7 => 'วันอาทิตย์',
    ];
    // แสดงวันที่ผ่านจุดกลาง — ThaiDate (พ.ศ.)
    $formatDate = fn ($date) => $date ? \App\Support\ThaiDate::date($date) : '-';
    $monthCalendarStart = \Carbon\CarbonImmutable::parse($weekStart)->startOfWeek(\Carbon\CarbonInterface::MONDAY);
    $monthCalendarEnd = \Carbon\CarbonImmutable::parse($weekEnd)->endOfWeek(\Carbon\CarbonInterface::SUNDAY);
    $monthCalendarDays = collect(\Carbon\CarbonPeriod::create($monthCalendarStart, $monthCalendarEnd))
        ->map(fn ($date) => \Carbon\CarbonImmutable::parse($date))
        ->values();
    $shortThaiDays = [
        1 => 'จันทร์',
        2 => 'อังคาร',
        3 => 'พุธ',
        4 => 'พฤหัสบดี',
        5 => 'ศุกร์',
        6 => 'เสาร์',
        7 => 'อาทิตย์',
    ];
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
    // สีกลุ่มนักศึกษา — ดึงจาก student_groups.color_code (ต่อ course offering)
    $groupTone = function ($group) {
        $color = (string) ($group->color_code ?? '');
        return str_starts_with($color, '#') || str_starts_with($color, 'oklch') || str_starts_with($color, 'var(')
            ? $color
            : 'var(--schedule-border-strong)';
    };
    $singleCourseSchedules = ($allSchedules ?? collect());
    $activityFilterOptions = $singleCourseSchedules->pluck('activityType')->filter()->unique('id')->sortBy('name')->values();
    $groupFilterOptions = $singleCourseSchedules->flatMap->studentGroups->unique('id')->sortBy('group_code')->values();
    $instructorFilterOptions = ($courseOffering?->instructorPool ?? collect())->sortBy(fn ($instructor) => $instructor->formatted_name ?? $instructor->name);
    $scheduleFilterItems = $singleCourseSchedules->map(function ($schedule) use ($formatDate, $formatTime) {
        return [
            'id' => (string) $schedule->id,
            'activity' => (string) $schedule->activity_type_id,
            'groups' => $schedule->studentGroups->pluck('id')->map(fn ($id) => (string) $id)->values(),
            'instructors' => $schedule->instructors->pluck('id')->map(fn ($id) => (string) $id)->values(),
            'search' => mb_strtolower(collect([
                $formatDate($schedule->start_date),
                $formatDate($schedule->end_date),
                $formatTime($schedule->start_time),
                $formatTime($schedule->end_time),
                $schedule->activityType?->name,
                $schedule->topic,
                $schedule->remark,
                $schedule->room?->room_code,
                $schedule->room?->room_name,
                $schedule->studentGroups->pluck('group_code')->implode(' '),
                $schedule->instructors->map(fn ($instructor) => $instructor->formatted_name ?? $instructor->name)->implode(' '),
            ])->filter()->implode(' '), 'UTF-8'),
        ];
    })->values();
@endphp

<x-app-layout title="ตารางสอน">
    <style>
        .schedule-shell {
            --schedule-border: oklch(86% 0.018 232);
            --schedule-border-strong: oklch(76% 0.03 232);
            --schedule-muted: oklch(42% 0.032 238);
            --schedule-soft: oklch(97% 0.014 228);
            --schedule-soft-strong: oklch(94% 0.026 228);
            --schedule-panel: oklch(98% 0.01 228);
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .schedule-toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            border: 1px solid var(--schedule-border);
            border-radius: 10px;
            background: var(--schedule-panel);
            box-shadow: 0 1px 3px oklch(0% 0 0 / 0.05);
            flex-wrap: wrap;
        }
        .schedule-title {
            font-size: 16px;
            font-weight: 900;
            color: var(--fg-1);
            padding-right: 8px;
            border-right: 1px solid var(--schedule-border);
        }
        .week-nav {
            display: flex;
            align-items: center;
            gap: 7px;
            height: 34px;
            color: var(--fg-2);
            font-size: 12.5px;
            font-weight: 800;
            line-height: 1;
        }

        /* ── ปุ่มเปลี่ยนวันที่ + ปฏิทิน พ.ศ. (sched-datenav) ── */
        .sched-datenav {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .sched-datenav-arrow {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border: 1px solid var(--schedule-border-strong);
            border-radius: 8px;
            background: var(--surface);
            color: var(--fg-2);
            text-decoration: none;
            transition: background .12s ease, color .12s ease;
        }
        .sched-datenav-arrow:hover {
            background: var(--schedule-soft);
            color: var(--brand-navy);
        }
        .sched-datenav-arrow svg {
            width: 17px;
            height: 17px;
        }
        .sched-datenav-input {
            width: 138px;
            height: 34px;
            border: 1px solid var(--schedule-border-strong);
            border-radius: 8px;
            background: var(--surface);
            color: var(--fg-1);
            font: inherit;
            font-size: 13px;
            font-weight: 850;
            padding-left: 10px;
            box-sizing: border-box;
            transition: border-color .12s ease, box-shadow .12s ease;
        }
        .sched-datenav-input:focus {
            outline: none;
            border-color: var(--brand-navy);
            box-shadow: 0 0 0 3px oklch(45% 0.12 250 / 0.12);
        }
        /* ปฏิทิน popup ของ date-nav ให้กางออกทางซ้าย กันหลุดขอบการ์ด */
        .sched-datenav .tdi-pop {
            left: auto;
            right: 0;
        }
        .week-btn {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid transparent;
            border-radius: 8px;
            background: transparent;
            color: var(--fg-1);
            text-decoration: none;
            font-weight: 900;
            box-sizing: border-box;
            transition: transform .12s ease, background .12s ease, border-color .12s ease;
        }
        .week-pill {
            display: flex;
            align-items: center;
            height: 22px;
            padding: 0 9px;
            border-radius: 999px;
            background: oklch(94% 0.035 245);
            color: var(--brand-navy);
            font-size: 11px;
            font-weight: 900;
        }
        .grid-date-jump {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            height: 34px;
            padding: 6px 10px;
            border-radius: 8px;
            background: oklch(99% 0.004 232);
            color: var(--schedule-muted);
            font-size: 13px;
            font-weight: 800;
            line-height: 1;
            white-space: nowrap;
            box-sizing: border-box;
            border: 1px solid transparent;
        }
        .grid-date-jump input {
            width: 132px;
            height: 22px;
            display: inline-flex;
            align-items: center;
            border: 0;
            background: transparent;
            color: var(--fg-1);
            font: inherit;
            font-size: 12px;
            font-weight: 850;
            padding: 0;
            box-sizing: border-box;
            line-height: 22px;
        }
        .grid-date-jump input:focus {
            outline: none;
        }
        .grid-date-jump input::-webkit-calendar-picker-indicator {
            margin: 0;
        }
        .schedule-toggle {
            display: inline-flex;
            border: 1px solid var(--schedule-border);
            border-radius: 8px;
            overflow: hidden;
            background: var(--schedule-soft);
            order: 2;
        }
        .schedule-toggle button {
            border: 0;
            background: transparent;
            color: var(--schedule-muted);
            font: inherit;
            font-size: 12px;
            font-weight: 800;
            min-height: 34px;
            padding: 6px 13px;
            cursor: pointer;
        }
        .schedule-toggle button.is-active {
            background: var(--surface);
            color: var(--fg-1);
            box-shadow: 0 1px 6px oklch(0% 0 0 / 0.11);
        }
        .period-toggle {
            display: inline-flex;
            border: 1px solid var(--schedule-border);
            border-radius: 8px;
            overflow: hidden;
            background: var(--schedule-soft);
            order: 1;
        }
        .period-toggle a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 34px;
            padding: 6px 12px;
            border-right: 1px solid var(--schedule-border);
            color: var(--schedule-muted);
            font-size: 12px;
            font-weight: 850;
            text-decoration: none;
            white-space: nowrap;
        }
        .period-toggle button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 34px;
            padding: 6px 12px;
            border: 0;
            border-right: 1px solid var(--schedule-border);
            background: transparent;
            color: var(--schedule-muted);
            font: inherit;
            font-size: 12px;
            font-weight: 850;
            cursor: pointer;
            white-space: nowrap;
        }
        .period-toggle a:last-child {
            border-right: 0;
        }
        .period-toggle button:last-child {
            border-right: 0;
        }
        .period-toggle a.is-active,
        .period-toggle button.is-active {
            background: var(--surface);
            color: var(--fg-1);
            box-shadow: 0 1px 6px oklch(0% 0 0 / 0.11);
        }
        .toolbar-actions {
            margin-left: auto;
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        .compact-summary {
            color: var(--schedule-muted);
            font-size: 12px;
            font-weight: 700;
        }
        .nested-context {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 13px 16px;
            border: 1px solid var(--schedule-border);
            border-radius: 10px;
            background: linear-gradient(180deg, oklch(98% 0.012 228), var(--surface));
        }
        .context-stack {
            display: grid;
            gap: 4px;
            min-width: 0;
        }
        .context-eyebrow {
            color: var(--schedule-muted);
            font-size: 11px;
            font-weight: 900;
        }
        .nested-course {
            min-width: 0;
            color: var(--fg-1);
            font-weight: 900;
            font-size: 18px;
            line-height: 1.35;
        }
        .nested-meta {
            margin-top: 2px;
            color: var(--fg-3);
            font-size: 12px;
            line-height: 1.45;
        }
        .context-metrics {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-end;
        }
        .context-pill {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 4px 10px;
            border: 1px solid var(--schedule-border);
            border-radius: 999px;
            background: var(--surface);
            color: var(--fg-2);
            font-size: 12px;
            font-weight: 900;
        }
        .course-focus {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 15px 16px;
            border: 1px solid var(--schedule-border);
            border-radius: 10px;
            background: var(--surface);
            box-shadow: 0 1px 4px oklch(0% 0 0 / 0.035);
            flex-wrap: wrap;
        }
        .course-focus-main {
            display: flex;
            align-items: baseline;
            gap: 10px;
            min-width: 0;
            flex-wrap: wrap;
        }
        .course-focus-label {
            flex-basis: 100%;
            color: var(--schedule-muted);
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 0;
        }
        .course-focus-code {
            display: inline-block;
            min-height: 0;
            margin: 0;
            padding: 0;
            border-radius: 0;
            background: transparent;
            color: var(--brand-navy);
            font-size: 26px;
            font-weight: 950;
            line-height: 1.2;
            letter-spacing: 0;
            box-shadow: none;
        }
        .course-focus-name {
            max-width: min(720px, 100%);
            color: var(--fg-1);
            font-size: 16px;
            font-weight: 800;
            line-height: 1.45;
        }
        .course-focus-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            margin-left: auto;
            flex-wrap: wrap;
        }
        .course-compact-info {
            background: var(--surface);
            border-color: var(--schedule-border-strong);
            box-shadow: 0 1px 4px oklch(0% 0 0 / 0.035);
        }
        .course-overview {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 14px;
            align-items: center;
            padding: 16px 18px;
            border: 1px solid var(--schedule-border-strong);
            border-radius: 10px;
            background: linear-gradient(180deg, oklch(97.5% 0.014 232), var(--surface));
            box-shadow: 0 1px 4px oklch(0% 0 0 / 0.035);
        }
        .course-overview-main {
            min-width: 0;
        }
        .course-overview-eyebrow {
            color: var(--brand-navy);
            font-size: 11px;
            font-weight: 900;
            margin-bottom: 6px;
        }
        .course-overview-title {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
            flex-wrap: wrap;
        }
        .course-overview-code {
            display: inline-flex;
            align-items: center;
            min-height: 42px;
            padding: 4px 14px;
            border: 1px solid var(--brand-navy);
            border-radius: 8px;
            background: var(--brand-navy);
            color: oklch(98% 0.004 240);
            font-size: 32px;
            font-weight: 950;
            line-height: 1.2;
            letter-spacing: 0;
            margin: 0;
            box-shadow: 0 2px 6px oklch(0% 0 0 / 0.08);
        }
        .course-overview-name {
            color: var(--fg-1);
            font-size: 18px;
            font-weight: 900;
            line-height: 1.45;
        }
        .course-overview-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 7px;
            color: var(--fg-2);
            font-size: 12.5px;
            font-weight: 750;
        }
        .course-overview-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
        .course-overview-stats {
            grid-column: 1 / -1;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            padding-top: 10px;
            border-top: 1px solid var(--schedule-border);
        }
        .course-stat {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--schedule-muted);
            font-size: 12px;
            font-weight: 800;
            min-height: 28px;
            padding: 3px 10px 3px 4px;
            border: 1px solid var(--schedule-border);
            border-radius: 999px;
            background: oklch(98.5% 0.005 232);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .course-stat:hover {
            border-color: var(--schedule-border-strong);
            background: oklch(97.5% 0.01 232);
            transform: translateY(-0.5px);
            box-shadow: 0 2px 4px oklch(0% 0 0 / 0.04);
        }
        .course-stat strong {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px;
            height: 20px;
            padding: 0 5px;
            border-radius: 999px;
            background: var(--brand-navy);
            color: #ffffff;
            font-size: 11px;
            font-weight: 800;
            line-height: 1;
        }
        .schedule-filter-bar {
            display: grid;
            grid-template-columns: minmax(220px, 1.2fr) repeat(4, minmax(130px, .7fr));
            gap: 10px;
            padding: 12px 16px;
            border-top: 1px solid var(--schedule-border);
            background: oklch(97% 0.012 232);
        }
        .schedule-filter-control {
            width: 100%;
            min-height: 38px;
            border: 1px solid var(--schedule-border);
            border-radius: 8px;
            background: var(--surface);
            color: var(--fg-1);
            padding: 7px 10px;
            font: inherit;
            font-size: 13px;
        }
        .schedule-filter-control:focus {
            outline: none;
            border-color: var(--brand-navy);
            box-shadow: 0 0 0 3px oklch(45% 0.12 250 / 0.12);
        }

        .day-add-link {
            min-height: 27px;
            padding: 3px 9px;
            border-radius: 7px;
            border: 1px solid oklch(76% 0.055 255);
            background: oklch(95% 0.035 255);
            color: var(--brand-navy);
            font-size: 12px;
            font-weight: 900;
            cursor: pointer;
        }
        /* activity-tag — ใช้ทั้งในโหมดรายการและ detail modal */
        .activity-tag {
            display: inline-flex;
            align-items: center;
            min-height: 20px;
            padding: 2px 8px;
            border-radius: 999px;
            border: 1px solid color-mix(in oklch, var(--activity-color) 26%, var(--schedule-border));
            background: color-mix(in oklch, var(--activity-color) 10%, var(--surface));
            color: var(--fg-1);
            font-size: 10.5px;
            font-weight: 900;
            text-transform: uppercase;
        }
        .grid-activity:hover,
        .grid-activity:focus-visible {
            border-color: color-mix(in oklch, var(--activity-color) 44%, var(--schedule-border-strong));
            background: color-mix(in oklch, var(--activity-color) 5%, var(--surface));
            box-shadow: 0 3px 10px oklch(0% 0 0 / 0.08);
            outline: none;
        }
        /* ── โหมดรายการ (list) — ตารางคอลัมน์ อ่านง่าย ───────────────── */
        .sched-list-wrap {
            overflow-x: auto;
            border: 1px solid var(--schedule-border-strong);
            border-radius: 10px;
            background: var(--surface);
            box-shadow: 0 1px 4px oklch(0% 0 0 / 0.05);
        }
        .sched-list {
            width: 100%;
            min-width: 680px;
            border-collapse: collapse;
            background: var(--surface);
        }
        .sched-list thead th {
            background: oklch(96% 0.012 232);
            color: oklch(35% 0.035 232);
            text-align: left;
            font-size: 11px;
            font-weight: 800;
            padding: 10px 14px;
            border-bottom: 1px solid oklch(88% 0.015 232);
            white-space: nowrap;
        }
        .sched-day td {
            background: oklch(95% 0.012 232);
            border-top: 1px solid oklch(88% 0.015 232);
            border-bottom: 1px solid oklch(88% 0.015 232);
            padding: 8px 14px;
        }
        .sched-day-head {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sched-day-name {
            color: var(--fg-1);
            font-size: 15px;
            font-weight: 900;
            min-width: 84px;
        }
        .sched-day-date {
            color: var(--brand-navy);
            font-size: 12px;
            font-weight: 900;
            font-variant-numeric: tabular-nums;
            background: var(--surface);
            border: 1px solid var(--schedule-border);
            border-radius: 999px;
            padding: 2px 9px;
        }
        .sched-day-count {
            color: var(--fg-2);
            font-size: 11px;
            font-weight: 900;
            background: oklch(96% 0.014 232);
            border: 1px solid var(--schedule-border);
            border-radius: 999px;
            padding: 2px 8px;
        }
        .sched-day-spacer {
            flex: 1;
        }
        .sched-row {
            cursor: pointer;
        }
        .sched-row > td {
            border-bottom: 1px solid oklch(94% 0.01 232);
            padding: 9px 14px;
            vertical-align: middle;
            font-size: 12.5px;
        }
        .sched-row:nth-child(even) > td {
            background: oklch(98.5% 0.006 232);
        }
        .sched-row > td:first-child {
            box-shadow: inset 4px 0 0 var(--activity-color);
            background: oklch(98% 0.008 232);
        }
        .sched-row:hover > td,
        .sched-row:focus-visible > td {
            background: color-mix(in oklch, var(--activity-color) 5%, var(--surface));
        }
        .sched-row:focus-visible {
            outline: 2px solid var(--brand-navy);
            outline-offset: -2px;
        }
        .sched-time {
            color: var(--fg-1);
            font-size: 15px;
            font-weight: 900;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }
        .sched-time-block {
            display: inline-grid;
            gap: 1px;
            padding: 5px 8px 5px 11px;
            border-radius: 8px;
            background: oklch(96% 0.014 232);
            border: 1px solid var(--schedule-border);
        }
        .sched-duration {
            margin-top: 2px;
            color: var(--schedule-muted);
            font-size: 10.5px;
            font-weight: 700;
        }
        .sched-activity-course {
            margin-left: 5px;
            color: var(--schedule-muted);
            font-size: 10.5px;
            font-weight: 800;
        }
        .sched-activity-name {
            margin-top: 3px;
            color: var(--fg-1);
            font-size: 12.5px;
            font-weight: 800;
            line-height: 1.4;
        }
        .sched-cell-groups {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }
        .sched-strong {
            color: var(--fg-1);
            font-size: 11.5px;
            font-weight: 750;
        }
        .sched-muted {
            color: var(--schedule-muted);
            font-size: 11px;
        }
        .sched-empty-cell {
            padding: 12px;
            text-align: center;
            background: var(--surface);
            color: var(--schedule-muted);
            font-size: 12px;
            font-weight: 750;
            border-bottom: 1px solid oklch(94% 0.01 232);
        }
        .group-chip {
            display: inline-flex;
            align-items: center;
            min-height: 20px;
            padding: 2px 7px;
            border: 1px solid var(--schedule-border);
            border-radius: 999px;
            background: oklch(97% 0.012 232);
            color: var(--fg-2);
            font-size: 10.5px;
            font-weight: 900;
        }
        /* จุดสีกลุ่มนักศึกษา — สีจาก student_groups.color_code */
        .group-dot {
            width: 6px;
            height: 6px;
            margin-right: 4px;
            border-radius: 999px;
            flex-shrink: 0;
            background: var(--schedule-border-strong);
        }
        .schedule-empty {
            border: 1px dashed var(--schedule-border-strong);
            border-radius: 8px;
            background: var(--schedule-soft);
            color: var(--schedule-muted);
            padding: 11px;
            text-align: center;
            font-size: 12.5px;
            font-weight: 750;
        }

        /* ── โหมดรายการตารางสอนของรายวิชาเดี่ยว (co-sched-table) ── */
        .co-sched-table-wrap {
            overflow-x: auto;
            border: 1px solid var(--schedule-border);
            border-radius: 0 0 10px 10px;
            background: var(--surface);
            box-shadow: none;
            margin-top: 0;
        }
        .co-sched-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
            color: var(--fg-1);
        }
        .co-sched-table th {
            background: oklch(96% 0.012 232);
            color: oklch(35% 0.035 232);
            font-weight: 800;
            font-size: 12px;
            padding: 10px 14px;
            text-align: left;
            border-bottom: 1px solid oklch(88% 0.015 232);
            white-space: nowrap;
        }
        .co-sched-row {
            transition: all 0.2s ease;
            cursor: pointer;
            border-bottom: 1px solid var(--schedule-border);
        }
        .co-sched-row:hover {
            background: oklch(97% 0.012 232) !important;
        }
        .co-sched-row:focus-within {
            background: oklch(97% 0.012 232) !important;
            outline: 2px solid var(--brand-navy);
            outline-offset: -2px;
        }
        .co-sched-row td {
            padding: 10px 14px;
            vertical-align: middle;
            line-height: 1.4;
            border-bottom: 1px solid oklch(94% 0.01 232);
        }
        .co-sched-row:nth-child(even) td {
            background: oklch(98.5% 0.006 232);
        }
        .co-sched-row td:first-child {
            background: var(--surface);
        }
        /* Column Widths & Balances */
        .co-col-date {
            width: 110px;
            min-width: 110px;
        }
        .co-col-time {
            width: 110px;
            min-width: 110px;
        }
        .co-col-activity {
            /* Flexible width, takes remaining space */
        }
        .co-col-groups {
            width: 220px;
            min-width: 220px;
        }
        .co-col-instructors {
            width: 120px;
            min-width: 120px;
        }
        .co-col-location {
            width: 150px;
            min-width: 150px;
        }
        /* UI Elements inside table */
        .co-day-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12.5px;
            font-weight: 850;
            line-height: 1;
            margin-bottom: 6px;
            border: 1px solid var(--schedule-border);
            box-shadow: 0 1px 2px oklch(0% 0 0 / 0.04);
        }
        /* Traditional Thai Day colors but refined */
        .co-day-badge.day-1 { background: oklch(96% 0.026 95); color: oklch(38% 0.09 95); } /* จันทร์ - เหลือง */
        .co-day-badge.day-2 { background: oklch(96% 0.026 0); color: oklch(40% 0.1 0); } /* อังคาร - ชมพู */
        .co-day-badge.day-3 { background: oklch(96% 0.026 145); color: oklch(38% 0.09 145); } /* พุธ - เขียว */
        .co-day-badge.day-4 { background: oklch(96% 0.026 60); color: oklch(40% 0.1 60); } /* พฤหัส - ส้ม */
        .co-day-badge.day-5 { background: oklch(96% 0.026 250); color: oklch(38% 0.09 250); } /* ศุกร์ - ฟ้า */
        .co-day-badge.day-6 { background: oklch(96% 0.026 300); color: oklch(38% 0.09 300); } /* เสาร์ - ม่วง */
        .co-day-badge.day-7 { background: oklch(96% 0.026 20); color: oklch(38% 0.09 20); } /* อาทิตย์ - แดง */
        .co-day-badge.day-multi {
            background: oklch(94% 0.015 232);
            color: var(--brand-navy);
        }
        .co-date-primary {
            font-size: 11.5px;
            color: var(--fg-3);
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }
        .co-time-range {
            display: inline-flex;
            align-items: center;
            min-height: 0;
            padding: 0;
            border: 0;
            border-radius: 0;
            background: transparent;
            font-size: 13px;
            font-weight: 900;
            color: var(--fg-1);
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }
        .co-time-duration {
            font-size: 10.5px;
            color: var(--schedule-muted);
            margin-top: 4px;
            font-weight: 700;
        }
        .co-activity-name {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            min-height: 24px;
            padding: 2px 9px;
            border: 1px solid color-mix(in oklch, var(--activity-color) 32%, var(--schedule-border));
            border-radius: 999px;
            background: color-mix(in oklch, var(--activity-color) 12%, var(--surface));
            font-size: 13px;
            font-weight: 900;
            color: color-mix(in oklch, var(--activity-color) 62%, var(--brand-navy));
        }
        .co-activity-dot {
            width: 6px;
            height: 6px;
            border-radius: 999px;
            background: var(--activity-color);
            flex: 0 0 auto;
        }
        .co-activity-topic {
            font-size: 11.5px;
            color: var(--fg-3);
            margin-top: 4px;
            font-weight: 600;
            line-height: 1.4;
        }
        .co-activity-topic-main {
            font-size: 13.5px;
            font-weight: 800;
            color: var(--fg-1);
            line-height: 1.45;
        }
        .co-activity-type-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 10.5px;
            font-weight: 750;
            color: color-mix(in oklch, var(--activity-color) 70%, var(--fg-2));
            background: color-mix(in oklch, var(--activity-color) 8%, var(--schedule-soft));
            border: 1px solid color-mix(in oklch, var(--activity-color) 20%, var(--schedule-border));
            border-radius: 4px;
            padding: 1px 6px;
            margin-top: 4.5px;
        }
        .co-activity-dot-small {
            width: 5px;
            height: 5px;
            border-radius: 999px;
            background: var(--activity-color);
            flex: 0 0 auto;
        }
        .co-groups-list {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
            max-width: 218px;
        }
        .co-group-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            min-width: unset;
            min-height: 22px;
            font-size: 11px;
            font-weight: 900;
            color: color-mix(in oklch, var(--group-color) 58%, var(--brand-navy));
            background: var(--surface);
            border: 1px solid color-mix(in oklch, var(--group-color) 38%, var(--schedule-border));
            border-radius: 999px;
            padding: 2px 7px;
            white-space: nowrap;
        }
        .co-group-dot {
            width: 6px;
            height: 6px;
            border-radius: 999px;
            background: var(--group-color);
            flex: 0 0 auto;
        }
        .co-instructor-text {
            font-size: 12.5px;
            color: var(--fg-1);
            font-weight: 700;
            white-space: normal;
            word-break: break-word;
        }
        .co-location-room {
            font-size: 12.5px;
            color: var(--fg-1);
            font-weight: 700;
        }
        .co-location-building {
            font-size: 11px;
            color: var(--fg-3);
            margin-top: 2px;
            font-weight: 600;
        }
        .schedule-grid {
            display: grid;
            grid-template-columns: 74px repeat(5, minmax(146px, 1fr));
            border: 1px solid var(--schedule-border-strong);
            border-radius: 10px;
            overflow: auto;
            background: var(--surface);
            box-shadow: 0 1px 4px oklch(0% 0 0 / 0.05);
        }
        .grid-cell {
            min-height: 70px;
            border-right: 1px solid var(--schedule-border);
            border-bottom: 1px solid var(--schedule-border);
            padding: 7px;
            background: oklch(98.6% 0.004 232);
        }
        .grid-head {
            min-height: 44px;
            background: oklch(95.5% 0.016 232);
            color: var(--fg-1);
            text-align: center;
            font-size: 11.5px;
            font-weight: 900;
        }
        .grid-time {
            background: oklch(97% 0.007 232);
            color: var(--schedule-muted);
            font-size: 11px;
            font-weight: 800;
            text-align: right;
            font-variant-numeric: tabular-nums;
        }
        .grid-cell-activity {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }
        .grid-cell-activity .grid-activity {
            margin-bottom: 0;
            flex: 1 1 auto;
        }
        .grid-activity {
            width: 100%;
            border: 1px solid color-mix(in oklch, var(--activity-color) 26%, var(--schedule-border));
            border-left: 3px solid var(--activity-color);
            border-radius: 7px;
            background: var(--surface);
            padding: 8px 9px;
            margin-bottom: 7px;
            font-size: 11px;
            color: var(--fg-2);
            box-shadow: 0 1px 3px oklch(0% 0 0 / 0.07);
            cursor: pointer;
            text-align: left;
            font: inherit;
            display: grid;
            gap: 5px;
            min-width: 0;
        }
        .grid-activity strong,
        .grid-activity-title {
            display: block;
            color: var(--fg-1);
            font-size: 12px;
            line-height: 1.35;
            font-weight: 850;
        }
        .grid-course {
            display: inline-flex;
            width: fit-content;
            align-items: center;
            min-height: 19px;
            padding: 1px 7px;
            border-radius: 999px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 24%, var(--schedule-border));
            background: oklch(95% 0.026 245);
            color: var(--brand-navy);
            font-size: 10px;
            font-weight: 900;
            line-height: 1.2;
        }
        .grid-activity-top {
            display: flex;
            align-items: center;
            gap: 5px;
            flex-wrap: wrap;
            min-width: 0;
        }
        .grid-activity .activity-tag {
            min-height: 18px;
            padding: 1px 6px;
            font-size: 9.5px;
            line-height: 1.25;
        }
        .grid-activity-sub,
        .grid-activity-meta {
            color: var(--schedule-muted);
            font-size: 10.5px;
            line-height: 1.4;
        }
        .grid-activity-meta {
            display: grid;
            gap: 2px;
        }
        .grid-activity-meta > div:first-child {
            color: var(--fg-1);
            font-weight: 800;
        }
        .grid-activity-time {
            color: var(--fg-1);
            font-size: 11px;
            font-weight: 850;
        }
        .grid-activity-foot {
            display: flex;
            align-items: center;
            gap: 6px;
            min-width: 0;
        }
        .grid-activity-room {
            flex: 1;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: var(--fg-2);
            font-size: 10.5px;
            font-weight: 700;
        }
        .grid-activity-groups {
            flex-shrink: 0;
            padding: 1px 7px;
            border-radius: 999px;
            background: var(--schedule-soft);
            color: var(--fg-2);
            font-size: 10px;
            font-weight: 800;
        }
        .grid-location-name,
        .grid-instructor {
            color: var(--fg-1);
            font-size: 10.8px;
            font-weight: 750;
            line-height: 1.35;
        }
        .grid-location-building {
            color: var(--schedule-muted);
            font-size: 10px;
            line-height: 1.35;
        }
        .grid-groups {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }
        .grid-activity .co-group-badge,
        .grid-activity .group-chip {
            min-height: 19px;
            padding: 1px 6px;
            font-size: 10px;
        }
        .month-calendar {
            display: grid;
            grid-template-columns: repeat(7, minmax(128px, 1fr));
            border: 1px solid var(--schedule-border-strong);
            border-radius: 10px;
            overflow: auto;
            background: var(--surface);
            box-shadow: 0 1px 4px oklch(0% 0 0 / 0.05);
        }
        .month-calendar-head,
        .month-calendar-day {
            border-right: 1px solid var(--schedule-border);
            border-bottom: 1px solid var(--schedule-border);
        }
        .month-calendar-head:nth-child(7n),
        .month-calendar-day:nth-child(7n) {
            border-right: 0;
        }
        .month-calendar-head {
            min-height: 42px;
            padding: 10px 8px;
            background: oklch(93.5% 0.022 232);
            color: var(--fg-1);
            text-align: center;
            font-size: 12px;
            font-weight: 900;
        }
        .month-calendar-day {
            min-height: 154px;
            padding: 8px;
            background: var(--surface);
        }
        .month-calendar-day.is-outside {
            background: oklch(98% 0.006 232);
            color: var(--fg-3);
        }
        .month-day-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 7px;
        }
        .month-day-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 28px;
            height: 26px;
            padding: 0 7px;
            border-radius: 999px;
            background: oklch(96% 0.014 232);
            color: var(--fg-1);
            font-size: 12px;
            font-weight: 900;
            font-variant-numeric: tabular-nums;
        }
        .month-calendar-day.is-outside .month-day-number {
            background: transparent;
            color: var(--fg-3);
            border: 1px solid var(--schedule-border);
        }
        .month-day-count {
            color: var(--schedule-muted);
            font-size: 10.5px;
            font-weight: 800;
            white-space: nowrap;
        }
        .month-day-items {
            display: grid;
            gap: 6px;
        }
        .month-activity {
            width: 100%;
            border: 1px solid color-mix(in oklch, var(--activity-color) 30%, var(--schedule-border));
            border-radius: 8px;
            background: color-mix(in oklch, var(--activity-color) 7%, var(--surface));
            padding: 7px;
            cursor: pointer;
            text-align: left;
            font: inherit;
        }
        .month-activity:focus-visible {
            outline: 2px solid var(--brand-navy);
            outline-offset: 2px;
        }
        .month-activity-time {
            color: var(--brand-navy);
            font-size: 10.5px;
            font-weight: 900;
            font-variant-numeric: tabular-nums;
        }
        .month-activity-title {
            margin-top: 2px;
            color: var(--fg-1);
            font-size: 11.2px;
            font-weight: 850;
            line-height: 1.35;
        }
        .month-activity-meta {
            margin-top: 2px;
            color: var(--schedule-muted);
            font-size: 10px;
            line-height: 1.35;
        }
        .month-activity-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 3px;
            margin-top: 5px;
        }
        .month-group-summary {
            display: inline-flex;
            align-items: center;
            min-height: 17px;
            padding: 1px 6px;
            border: 1px solid var(--schedule-border);
            border-radius: 999px;
            color: var(--schedule-muted);
            background: var(--surface);
            font-size: 9.5px;
            font-weight: 850;
        }
        .month-activity .activity-tag,
        .month-activity .co-group-badge {
            min-height: 17px;
            padding: 1px 5px;
            font-size: 9.5px;
        }
        .month-empty {
            color: var(--fg-3);
            font-size: 11px;
            font-weight: 650;
            padding: 8px 2px;
        }
        .schedule-modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 80;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 22px;
            background: oklch(16% 0.02 240 / 0.55);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }
        .schedule-modal {
            width: min(680px, 100%);
            max-height: min(88vh, 760px);
            overflow: auto;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            scrollbar-color: oklch(62% 0.012 240) transparent;
            border: 1px solid var(--schedule-border);
            border-radius: 10px;
            background: var(--surface);
            box-shadow: 0 20px 48px oklch(0% 0 0 / 0.2), 0 4px 10px oklch(0% 0 0 / 0.07);
            animation: modal-pop 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
            /* note: removed clip-path to avoid clipping native select dropdowns */
        }
        .schedule-modal::-webkit-scrollbar {
            width: 14px;
        }
        .schedule-modal::-webkit-scrollbar-track {
            margin: 12px 0;
            background: transparent;
        }
        .schedule-modal::-webkit-scrollbar-thumb {
            min-height: 44px;
            border: 4px solid transparent;
            border-radius: 999px;
            background: oklch(62% 0.012 240);
            background-clip: padding-box;
        }
        .schedule-modal::-webkit-scrollbar-thumb:hover {
            background: oklch(54% 0.014 240);
            background-clip: padding-box;
        }
        .schedule-modal::-webkit-scrollbar-corner {
            background: transparent;
        }
        @keyframes modal-pop {
            from { opacity: 0; transform: scale(0.94) translateY(8px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
        }
        .schedule-modal.is-form {
            width: min(900px, 100%);
        }
        .modal-handle {
            width: 32px;
            height: 3px;
            border-radius: 999px;
            background: oklch(84% 0.012 240);
            margin: 8px auto 2px;
        }
        /* ── Detail Modal: Header ── */
        .modal-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 14px 20px 16px;
            border-bottom: 1px solid var(--schedule-border);
            background: oklch(97% 0.014 232);
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }
        .modal-head-detail {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 18px 12px;
            border-bottom: 1px solid var(--schedule-border);
            background: oklch(97% 0.014 232);
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }
        .modal-head-detail .activity-tag {
            font-size: 9.5px;
            padding: 1.5px 7px;
            min-height: 18px;
            letter-spacing: 0.2px;
        }
        .modal-title {
            margin-top: 5px;
            color: var(--fg-1);
            font-size: 20px;
            font-weight: 900;
            line-height: 1.3;
        }
        .modal-title-detail {
            margin-top: 4px;
            color: var(--fg-1);
            font-size: 19px;
            font-weight: 900;
            line-height: 1.3;
            letter-spacing: -0.01em;
        }
        .modal-close {
            width: 28px;
            height: 28px;
            border: 0;
            border-radius: 999px;
            background: transparent;
            color: var(--schedule-muted);
            font-size: 20px;
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-close:hover {
            background: oklch(92% 0.015 232);
            color: var(--fg-1);
        }
        /* ── Detail Modal: Body ── */
        .detail-body {
            padding: 12px 18px 14px;
            background: oklch(97.5% 0.008 232);
        }
        .modal-form-body {
            padding: 18px 20px 20px;
        }

        /* Ensure native selects inside modals render above other content */
        .schedule-modal select.modal-control,
        .schedule-modal select {
            position: relative;
            z-index: 99999;
            text-align: left;
            text-align-last: left;
        }
        .schedule-modal .choices,
        .schedule-modal .choices__inner,
        .schedule-modal .choices__list--single,
        .schedule-modal .choices__list--single .choices__item,
        .schedule-modal .choices__list--dropdown .choices__item {
            text-align: left !important;
        }
        .schedule-modal select.modal-control.tpss-choices {
            text-align: left !important;
            text-align-last: left !important;
        }
        .schedule-modal .choices[data-type*="select-one"] {
            text-align: left !important;
        }
        .schedule-modal .choices[data-type*="select-one"] .choices__inner {
            display: flex !important;
            align-items: center !important;
            justify-content: flex-start !important;
            padding-left: 10px !important;
            padding-right: 34px !important;
        }
        .schedule-modal .choices[data-type*="select-one"] .choices__list--single {
            display: flex !important;
            align-items: center !important;
            justify-content: flex-start !important;
            flex: 1 1 auto !important;
            width: 100% !important;
            min-width: 0 !important;
            margin: 0 !important;
            padding: 0 22px 0 0 !important;
            text-align: left !important;
        }
        .schedule-modal .choices[data-type*="select-one"] .choices__list--single > .choices__item {
            display: block !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            text-align: left !important;
            text-align-last: left !important;
            white-space: normal !important;
            word-break: break-word !important;
        }
        .schedule-modal .choices[data-type*="select-one"]::after {
            right: 12px !important;
        }
        .detail-grid {
            display: grid;
            gap: 0;
        }
        .detail-row {
            display: flex;
            align-items: baseline;
            gap: 10px;
            padding: 7px 0;
        }
        .detail-row + .detail-row {
            border-top: 1px solid oklch(93% 0.008 232);
        }
        .detail-row-label {
            width: 72px;
            flex-shrink: 0;
            color: var(--fg-3);
            font-size: 11.5px;
            font-weight: 700;
        }
        .detail-row-value {
            flex: 1;
            min-width: 0;
            color: var(--fg-1);
            font-size: 13px;
            font-weight: 600;
            line-height: 1.45;
        }
        .detail-row-value .sub {
            font-size: 11px;
            color: var(--fg-3);
            font-weight: 600;
        }
        .detail-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            padding-top: 1px;
        }
        .detail-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            min-height: 20px;
            padding: 1px 7px;
            border-radius: 5px;
            border: 1px solid oklch(88% 0.012 232);
            background: oklch(97.5% 0.006 232);
            font-size: 11px;
            font-weight: 800;
            color: var(--fg-2);
        }
        .detail-chip-dot {
            width: 5px;
            height: 5px;
            border-radius: 999px;
            flex-shrink: 0;
        }
        .detail-lead-badge {
            font-size: 9px;
            font-weight: 800;
            color: var(--brand-navy);
            background: oklch(94% 0.035 245);
            border-radius: 3px;
            padding: 1px 4px;
            margin-left: 2px;
        }
        /* ── Detail Modal: Actions ── */
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            padding: 10px 18px 14px;
            border-top: 1px solid var(--schedule-border);
        }
        .modal-actions .btn {
            min-height: 32px;
            font-size: 12px;
            font-weight: 800;
            border-radius: 7px;
            padding: 4px 14px;
        }
        .modal-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }
        .modal-field-full {
            grid-column: 1 / -1;
        }
        .modal-label {
            display: flex;
            gap: 5px;
            align-items: baseline;
            margin-bottom: 5px;
            color: var(--fg-2);
            font-size: 12.5px;
            font-weight: 800;
        }
        .required-mark {
            color: var(--status-conflict-fg);
            font-weight: 900;
        }
        .optional-note {
            color: var(--fg-3);
            font-size: 11px;
            font-weight: 600;
        }
        .modal-control {
            width: 100%;
            min-height: 38px;
            border: 1px solid var(--schedule-border);
            border-radius: 8px !important;
            background: var(--surface);
            color: var(--fg-1);
            padding: 8px 10px;
            font: inherit;
            font-size: 13px;
            -webkit-appearance: none;
            appearance: none;
        }
        .modal-control:focus {
            outline: 2px solid color-mix(in oklch, var(--brand-navy) 40%, transparent);
            outline-offset: 1px;
            border-color: var(--brand-navy);
        }
        .modal-choice-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        .modal-choice-search {
            width: 100%;
            min-height: 36px;
            margin-bottom: 10px;
            border: 1px solid var(--schedule-border);
            border-radius: 8px;
            background: var(--surface);
            color: var(--fg-1);
            padding: 7px 10px;
            font: inherit;
            font-size: 12.5px;
        }
        .modal-choice-search:focus {
            outline: 2px solid color-mix(in oklch, var(--brand-navy) 35%, transparent);
            outline-offset: 1px;
            border-color: var(--brand-navy);
        }
        .modal-choice-empty {
            border: 1px dashed var(--schedule-border);
            border-radius: 8px;
            background: var(--surface);
            color: var(--schedule-muted);
            padding: 10px 12px;
            font-size: 12.5px;
            font-weight: 750;
            text-align: center;
        }
        .modal-choice {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            border: 1px solid var(--schedule-border);
            border-radius: 8px;
            padding: 10px 12px;
            background: oklch(98.5% 0.006 240);
            font-size: 12.5px;
            font-weight: 800;
        }
        .modal-choice:hover {
            border-color: var(--schedule-border-strong);
            background: oklch(96.5% 0.014 232);
        }
        .modal-section {
            margin-top: 16px;
            padding: 14px;
            border: 1px solid var(--schedule-border);
            border-radius: 10px;
            background: oklch(98% 0.008 232);
        }
        .modal-section-title {
            margin-bottom: 8px;
            color: var(--fg-1);        /* ──────────────────────────────────────────────────────────
           Offerings Dropdown Panel (workspace top section)
           ────────────────────────────────────────────────────────── */
        .offerings-dropdown-panel {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            background: linear-gradient(180deg, oklch(98% 0.01 228), oklch(96% 0.015 228));
            padding: 12px 18px;
            border: 1px solid var(--schedule-border);
            border-radius: 10px;
            box-shadow: 0 1px 3px oklch(0% 0 0 / 0.04);
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .offering-selector-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
            min-width: 280px;
        }
        .offering-selector-label {
            font-size: 13.5px;
            font-weight: 800;
            color: var(--fg-2);
            white-space: nowrap;
            flex-shrink: 0;
        }
        .offering-select-control {
            font-family: inherit;
            font-size: 13.5px;
            font-weight: 800;
            color: var(--brand-navy);
            background: var(--surface);
            border: 1px solid var(--schedule-border-strong);
            border-radius: 8px;
            padding: 8px 36px 8px 12px;
            flex: 1;
            max-width: 580px;
            min-width: 200px;
            cursor: pointer;
            box-shadow: 0 1px 2px oklch(0% 0 0 / 0.03);
            transition: all 0.2s ease;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='currentColor' stroke-width='2.5'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19.5 8.25l-7.5 7.5-7.5-7.5'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 14px;
        }
        .offering-select-control:hover {
            border-color: var(--brand-navy);
            box-shadow: 0 2px 8px oklch(0% 0 0 / 0.05);
        }
        .offering-select-control:focus {
            outline: none;
            border-color: var(--brand-navy);
            box-shadow: 0 0 0 3px oklch(30% 0.15 232 / 0.15);
        }
        .offerings-panel-pills {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: nowrap;
            flex-shrink: 0;
        }
        .offering-chip {
            display: inline-flex;
            align-items: center;
            font-size: 10.5px;
            font-weight: 700;
            color: var(--fg-3);
            background: var(--schedule-soft-strong);
            border-radius: 999px;
            padding: 2px 8px;
            white-space: nowrap;
        }
        .offering-chip.chip-ok {
            color: oklch(30% 0.13 165);
            background: oklch(93% 0.04 165);
        }
        .offering-chip.chip-muted {
            color: var(--fg-3);
            background: oklch(92% 0.01 240);
        }
        @media (max-width: 700px) {
            .offerings-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            }
        }
        @media (max-width: 640px) {
            .course-overview {
                grid-template-columns: 1fr;
            }
            .course-overview-actions {
                justify-content: flex-start;
            }
            .schedule-filter-bar {
                grid-template-columns: 1fr;
            }
            .info-divider {
                display: none !important;
            }
            .course-compact-info {
                flex-direction: column;
                align-items: stretch !important;
                gap: 16px !important;
            }
        }
    </style>

    <div
        class="schedule-shell"
        x-data="{
            view: sessionStorage.getItem('tpss-schedule-view') || 'list',
            detailModal: null,
            editModal: @js($openEditScheduleId ? 'schedule-' . $openEditScheduleId : null),
            showCreate: @js($openCreateModal),
            initialSelectedOfferingId: @js($selectedOfferingId),
            selectedOfferingId: @js($selectedOfferingId),
            scheduleItems: @js($scheduleFilterItems),
            scheduleSearch: '',
            scheduleActivity: '',
            scheduleGroup: '',
            scheduleInstructor: '',
            gridJumpDate: @js($formatDate($weekStart)),
            createInstructorSearch: '',
            createGroupSearch: '',
            createStartDate: @js(old('start_date') ? $formatDate(\Carbon\CarbonImmutable::parse(old('start_date'))) : ''),
            createEndDate: @js(old('end_date') ? $formatDate(\Carbon\CarbonImmutable::parse(old('end_date'))) : ''),
            init() {
                this.$watch('view', val => sessionStorage.setItem('tpss-schedule-view', val));
                this.$watch('selectedOfferingId', () => {
                    this.createInstructorSearch = '';
                    this.createGroupSearch = '';
                });

                const scrollY = sessionStorage.getItem('tpss-schedule-scroll-y');
                if (scrollY !== null) {
                    window.scrollTo(0, parseInt(scrollY, 10));
                    this.$nextTick(() => {
                        window.scrollTo(0, parseInt(scrollY, 10));
                    });
                    sessionStorage.removeItem('tpss-schedule-scroll-y');
                }

                this.$el.addEventListener('click', (e) => {
                    const link = e.target.closest('a');
                    if (link && link.getAttribute('href') && !link.getAttribute('href').startsWith('#')) {
                        sessionStorage.setItem('tpss-schedule-scroll-y', window.scrollY);
                    }
                });

                this.$el.addEventListener('submit', () => {
                    sessionStorage.setItem('tpss-schedule-scroll-y', window.scrollY);
                });

                // Re-init Choices when modals open (modal content may be rendered dynamically)
                this.$watch('showCreate', (val) => {
                    if (val) {
                        this.$nextTick(() => { window.tpssInitChoices(document.querySelector('.schedule-modal.is-form')); });
                    }
                });
                this.$watch('editModal', (val) => {
                    if (val) {
                        this.$nextTick(() => { window.tpssInitChoices(document.querySelector('.schedule-modal.is-form')); });
                    }
                });
                this.$watch('detailModal', (val) => {
                    if (val) {
                        this.$nextTick(() => { window.tpssInitChoices(document.querySelector('.schedule-modal')); });
                    }
                });
            },
            normalizedScheduleSearch() {
                return this.scheduleSearch.trim().toLowerCase();
            },
            navigateGrid(date, period = @js($schedulePeriod ?? 'week')) {
                if (!date) return;

                const url = new URL(window.location.href);
                url.searchParams.set('date', date);
                url.searchParams.set('week_start', date);
                url.searchParams.set('period', period);
                sessionStorage.setItem('tpss-schedule-scroll-y', window.scrollY);
                window.location.href = url.toString();
            },
            jumpToGridDate(value) {
                const iso = this.thaiDateToIso(value);
                if (!iso) return;
                this.navigateGrid(iso, @js($schedulePeriod ?? 'week'));
            },
            changeGridPeriod(period) {
                const iso = this.thaiDateToIso(this.gridJumpDate) || @js($weekStart->toDateString());
                this.navigateGrid(iso, period);
            },
            // แปลงค่าจากช่อง x-thai-date-input (วว/ดด/พ.ศ.) เป็น ISO Y-m-d ก่อนส่งเข้า URL
            // mirror logic ของ App\Support\ThaiDate::parseToIso ฝั่ง client
            thaiDateToIso(value) {
                const raw = String(value || '').trim();
                if (!raw) return null;
                if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) return raw;

                const parts = raw.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
                if (!parts) return null;

                let year = parseInt(parts[3], 10);
                if (year >= 2400) year -= 543;
                const month = parts[2].padStart(2, '0');
                const day = parts[1].padStart(2, '0');

                return `${year}-${month}-${day}`;
            },
            matchesCreateSearch(text, keyword) {
                const normalizedKeyword = String(keyword || '').trim().toLowerCase();
                return !normalizedKeyword || String(text || '').toLowerCase().includes(normalizedKeyword);
            },
            hasCreateSearch(keyword) {
                return String(keyword || '').trim().length > 0;
            },
            hasCreateSearchMatches(items, keyword) {
                const normalizedKeyword = String(keyword || '').trim().toLowerCase();
                if (!normalizedKeyword) return true;

                return items.some((item) => String(item || '').toLowerCase().includes(normalizedKeyword));
            },
            toThaiDateDisplay(value) {
                const trimmed = String(value || '').trim();
                if (!trimmed.match(/^\d{4}-\d{2}-\d{2}$/)) return '';

                const [year, month, day] = trimmed.split('-').map((part) => parseInt(part, 10));
                return `${String(day).padStart(2, '0')}/${String(month).padStart(2, '0')}/${year + 543}`;
            },
            matchesSchedule(id) {
                const item = this.scheduleItems.find((entry) => entry.id === String(id));
                if (! item) return false;

                const keyword = this.normalizedScheduleSearch();

                return (!keyword || item.search.includes(keyword))
                    && (!this.scheduleActivity || item.activity === this.scheduleActivity)
                    && (!this.scheduleGroup || item.groups.includes(this.scheduleGroup))
                    && (!this.scheduleInstructor || item.instructors.includes(this.scheduleInstructor));
            },
            matchedScheduleCount() {
                return this.scheduleItems.filter((item) => this.matchesSchedule(item.id)).length;
            },
            dayHasMatches(ids) {
                return ids.some((id) => this.matchesSchedule(id));
            },
            resetScheduleFilters() {
                this.scheduleSearch = '';
                this.scheduleActivity = '';
                this.scheduleGroup = '';
                this.scheduleInstructor = '';
            },
            resetCreateForm(date = null) {
                const form = this.$refs.createForm;
                this.createInstructorSearch = '';
                this.createGroupSearch = '';
                this.selectedOfferingId = this.initialSelectedOfferingId;

                if (!form) return;

                form.reset();
                form.querySelectorAll('input[type=checkbox], input[type=radio]').forEach((input) => {
                    input.checked = false;
                });
                form.querySelectorAll('input[type=text], input[type=date], input[type=time], input[type=number], input[type=search], textarea').forEach((field) => {
                    field.value = '';
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                });
                form.querySelectorAll('select').forEach((select) => {
                    select.selectedIndex = 0;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                });
                const offeringSelect = form.querySelector('[name=course_offering_id]');
                if (offeringSelect && this.initialSelectedOfferingId) {
                    offeringSelect.value = this.initialSelectedOfferingId;
                    offeringSelect.dispatchEvent(new Event('change', { bubbles: true }));
                }
                this.selectedOfferingId = this.initialSelectedOfferingId;
                this.createStartDate = '';
                this.createEndDate = '';

                this.$nextTick(() => {
                    if (date) {
                        this.createStartDate = this.toThaiDateDisplay(date);
                        this.createEndDate = this.toThaiDateDisplay(date);
                    }
                });
            },
            openCreate(date = null) {
                this.detailModal = null;
                this.editModal = null;
                this.resetCreateForm(date);
                this.showCreate = true;
            },
            openEdit(id) {
                this.detailModal = null;
                this.showCreate = false;
                this.editModal = 'schedule-' + id;
            },
            closeCreate() { this.showCreate = false; },
            closeEdit() { this.editModal = null; }
        }"
        @keydown.escape.window="detailModal = null; showCreate = false; editModal = null"
    >
        @if($availableOfferings->isNotEmpty())
            <div class="offerings-dropdown-panel" data-testid="offerings-panel" style="flex-direction: column; align-items: stretch; gap: 8px;">
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <label for="offering-selector" class="offering-selector-label">รายวิชาที่รับผิดชอบ:</label>
                    <span class="badge badge-gray" style="font-weight: 800; font-size: 11px; padding: 2px 8px; border-radius: 999px;">{{ $availableOfferings->count() }} รายวิชา</span>
                    @if($activeOfferingCount > 0)
                        <span class="badge badge-ok" style="font-weight: 800; font-size: 11px; padding: 2px 8px; border-radius: 999px;">{{ $activeOfferingCount }} เปิดจัดตาราง</span>
                    @endif
                </div>
                <div class="offering-selector-wrapper" style="width: 100%; min-width: 0;">
                    <select id="offering-selector" class="offering-select-control" style="max-width: 100%; width: 100%;" onchange="sessionStorage.setItem('tpss-schedule-scroll-y', window.scrollY); window.location.href = this.value">
                        @foreach($availableOfferings as $availOffering)
                            @php
                                $availCourse = $availOffering->course;
                                $isSelected = ! $isWorkspace && $courseOffering && $courseOffering->id === $availOffering->id;
                                $optUrl = route('maker.course_offerings.schedules.index', $availOffering);
                            @endphp
                            <option value="{{ $optUrl }}" {{ $isSelected ? 'selected' : '' }}>
                                {{ $availCourse?->course_code ?? '-' }} - {{ $availCourse?->name_th ?? $availCourse?->name_en ?? 'ไม่มีชื่อรายวิชา' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        @endif

        @if($isWorkspace)
        <div class="schedule-toolbar">
            <div class="schedule-title">ตารางสอน</div>
            <div class="week-nav" x-show="view === 'grid'" x-cloak>
                <a class="week-btn" href="{{ $previousWeekUrl }}" aria-label="สัปดาห์ก่อนหน้า">‹</a>
                <span>{{ $formatDate($weekStart) }} - {{ $formatDate($weekEnd) }}</span>
                <span class="week-pill">{{ ['day' => 'รายวัน', 'week' => 'รายสัปดาห์', 'month' => 'รายเดือน'][$schedulePeriod ?? 'week'] ?? 'รายสัปดาห์' }}</span>
                <a class="week-btn" href="{{ $nextWeekUrl }}" aria-label="สัปดาห์ถัดไป">›</a>
            </div>
            <label class="grid-date-jump" x-show="view === 'grid'" x-cloak>
                <span>ไปยังวันที่</span>
                <x-thai-date-input
                    name="grid_jump_date"
                    :helper="false"
                    :value="$weekStart->toDateString()"
                    x-model="gridJumpDate"
                    @change="jumpToGridDate(gridJumpDate)"
                    @keydown.enter.prevent="jumpToGridDate(gridJumpDate)"
                    aria-label="เลือกวันที่ที่ต้องการดูในตาราง" />
            </label>
            <div class="period-toggle" aria-label="ช่วงเวลาที่แสดง" x-show="view === 'grid'" x-cloak>
                <button type="button" data-period-url="{{ $dayViewUrl }}" @click="changeGridPeriod('day')" class="{{ ($schedulePeriod ?? 'week') === 'day' ? 'is-active' : '' }}">วัน</button>
                <button type="button" data-period-url="{{ $weekViewUrl }}" @click="changeGridPeriod('week')" class="{{ ($schedulePeriod ?? 'week') === 'week' ? 'is-active' : '' }}">สัปดาห์</button>
                <button type="button" data-period-url="{{ $monthViewUrl }}" @click="changeGridPeriod('month')" class="{{ ($schedulePeriod ?? 'week') === 'month' ? 'is-active' : '' }}">เดือน</button>
            </div>
            <div class="schedule-toggle" role="group" aria-label="รูปแบบการแสดงตาราง">
                <button type="button" :class="{ 'is-active': view === 'list' }" @click="view = 'list'" data-testid="schedule-list-toggle">แบบรายการ</button>
                <button type="button" :class="{ 'is-active': view === 'grid' }" @click="view = 'grid'" data-testid="schedule-grid-toggle">แบบตาราง</button>
            </div>
            <div class="toolbar-actions">
                @if($canEdit)
                    <button type="button" class="btn btn-primary" data-testid="schedule-create-link" @click="openCreate()">+ เพิ่ม</button>
                @else
                    <span class="badge badge-gray">ดูข้อมูลอย่างเดียว</span>
                @endif
            </div>
        </div>
        @endif

        @if(! $isWorkspace && $courseOffering)
            @php
                $course = $courseOffering->course;
                $curriculum = $course?->curriculum;
                $phase = $academicYear?->phase;
                $approvalMeta = [
                    'draft' => ['label' => 'แบบร่าง', 'class' => 'badge-gray'],
                    'pending' => ['label' => 'รออนุมัติ', 'class' => 'badge-warn'],
                    'published' => ['label' => 'เผยแพร่แล้ว', 'class' => 'badge-ok'],
                    'rejected' => ['label' => 'ส่งกลับแก้ไข', 'class' => 'badge-err'],
                ][$courseOffering->approval_status ?? 'draft'] ?? ['label' => $courseOffering->approval_status ?? 'แบบร่าง', 'class' => 'badge-gray'];
            @endphp

            <section class="course-overview" aria-label="ข้อมูลรายวิชาที่กำลังจัดตาราง">
                <div class="course-overview-main">
                    <div class="course-overview-eyebrow">ตารางสอนรายวิชา</div>
                    <div class="course-overview-title">
                        <h1 class="course-overview-code">{{ $course?->course_code ?? '-' }}</h1>
                        <span class="course-overview-name">{{ $course?->name_th ?? $course?->name_en ?? 'ไม่ระบุชื่อรายวิชา' }}</span>
                    </div>
                    <div class="course-overview-meta">
                        <span>{{ $curriculum?->name ?? 'ไม่ระบุหลักสูตร' }}</span>
                        <span>ปีการศึกษา {{ $academicYear?->name ?? '-' }} / เทอม {{ $academicYear?->semester ?? '-' }}</span>
                        @if($phase === 'scheduling')
                            <span class="badge badge-ok">เปิดจัดตาราง</span>
                        @elseif($phase === 'approving')
                            <span class="badge badge-warn">รออนุมัติ</span>
                        @else
                            <span class="badge badge-gray">{{ $phase ?? 'ไม่ระบุ' }}</span>
                        @endif
                        @if($courseOffering->requires_practicum_rotation || $course?->requires_practicum_rotation)
                            <span class="badge badge-warn">มีรอบฝึกปฏิบัติ</span>
                        @endif
                        <span class="badge {{ $approvalMeta['class'] }}">สถานะรายวิชา: {{ $approvalMeta['label'] }}</span>
                    </div>
                </div>
                <div class="course-overview-actions">
                    <a href="{{ route('maker.course_offerings.show', $courseOffering) }}" class="btn btn-secondary" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;min-height:34px;padding:6px 12px;font-size:12.5px;">
                        <span>รายละเอียดรายวิชา</span>
                    </a>
                    @if($canEdit)
                        <button type="button" class="btn btn-primary" data-testid="schedule-create-link" @click="openCreate()" style="min-height:34px;padding:6px 12px;font-size:12.5px;">+ เพิ่มรายการสอน</button>
                    @endif
                </div>
                <div class="course-overview-stats">
                    <span class="course-stat"><strong>{{ $allSchedules->count() }}</strong> รายการสอน</span>
                    <span class="course-stat"><strong>{{ $courseOffering->studentGroups->count() }}</strong> กลุ่มนักศึกษา</span>
                    <span class="course-stat"><strong>{{ $courseOffering->instructorPool->count() }}</strong> ผู้สอน</span>
                </div>
            </section>

            {{-- ── รายการตารางสอน (Card Layout) ── --}}
            <div class="card">
                <div class="card-hdr" style="flex-wrap:wrap;gap:12px;">
                    <div>
                        <div class="card-ttl">รายการตารางสอน</div>
                        <div class="caption" style="margin-top:4px;">เรียงตามช่วงวันที่และเวลา</div>
                    </div>
                    <div style="margin-left:auto; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                        <div class="sched-datenav" x-show="view === 'grid'" x-cloak>
                            <a class="sched-datenav-arrow" href="{{ $previousWeekUrl }}" data-testid="schedule-nav-prev" aria-label="ช่วงก่อนหน้า">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>
                            </a>
                            <x-thai-date-input
                                name="grid_jump_date"
                                :helper="false"
                                :value="$weekStart->toDateString()"
                                class="sched-datenav-input"
                                x-model="gridJumpDate"
                                @change="jumpToGridDate(gridJumpDate)"
                                @keydown.enter.prevent="jumpToGridDate(gridJumpDate)"
                                aria-label="พิมพ์วันที่ที่ต้องการดูในตาราง" />
                            <a class="sched-datenav-arrow" href="{{ $nextWeekUrl }}" data-testid="schedule-nav-next" aria-label="ช่วงถัดไป">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
                            </a>
                        </div>
                        <div class="period-toggle" aria-label="ช่วงเวลาที่แสดง" x-show="view === 'grid'" x-cloak>
                            <button type="button" data-period-url="{{ $dayViewUrl }}" @click="changeGridPeriod('day')" class="{{ ($schedulePeriod ?? 'week') === 'day' ? 'is-active' : '' }}">วัน</button>
                            <button type="button" data-period-url="{{ $weekViewUrl }}" @click="changeGridPeriod('week')" class="{{ ($schedulePeriod ?? 'week') === 'week' ? 'is-active' : '' }}">สัปดาห์</button>
                            <button type="button" data-period-url="{{ $monthViewUrl }}" @click="changeGridPeriod('month')" class="{{ ($schedulePeriod ?? 'week') === 'month' ? 'is-active' : '' }}">เดือน</button>
                        </div>
                        <div class="schedule-toggle" role="group" aria-label="รูปแบบการแสดงตาราง">
                            <button type="button" :class="{ 'is-active': view === 'list' }" @click="view = 'list'" data-testid="schedule-list-toggle">แบบรายการ</button>
                            <button type="button" :class="{ 'is-active': view === 'grid' }" @click="view = 'grid'" data-testid="schedule-grid-toggle">แบบตาราง</button>
                        </div>
                    </div>
                </div>
                <div class="schedule-filter-bar" x-show="view === 'list'" x-cloak>
                    <input
                        type="search"
                        class="schedule-filter-control"
                        x-model="scheduleSearch"
                        placeholder="ค้นหาวันที่ เวลา กิจกรรม ผู้สอน หรือสถานที่"
                        aria-label="ค้นหารายการตารางสอน"
                    >
                    <select class="schedule-filter-control" x-model="scheduleActivity" aria-label="กรองตามประเภทกิจกรรม">
                        <option value="">ทุกกิจกรรม</option>
                        @foreach($activityFilterOptions as $activity)
                            <option value="{{ $activity->id }}">{{ $activity->name }}</option>
                        @endforeach
                    </select>
                    <select class="schedule-filter-control" x-model="scheduleGroup" aria-label="กรองตามกลุ่มนักศึกษา">
                        <option value="">ทุกกลุ่ม</option>
                        @foreach($groupFilterOptions as $group)
                            <option value="{{ $group->id }}">{{ $group->group_code }}</option>
                        @endforeach
                    </select>
                    <select class="schedule-filter-control" x-model="scheduleInstructor" aria-label="กรองตามผู้สอน">
                        <option value="">ทุกผู้สอน</option>
                        @foreach($instructorFilterOptions as $instructor)
                            <option value="{{ $instructor->id }}">{{ $instructor->formatted_name ?? $instructor->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div x-show="view === 'list'" x-cloak data-testid="schedule-list-view">
                    <div class="co-sched-table-wrap">
                        <table class="co-sched-table">
                            <thead>
                                <tr>
                                    <th class="co-col-date">วัน / ช่วงวันที่</th>
                                    <th class="co-col-time">เวลา</th>
                                    <th class="co-col-activity">กิจกรรม</th>
                                    <th class="co-col-groups">กลุ่ม</th>
                                    <th class="co-col-instructors">ผู้สอน</th>
                                    <th class="co-col-location">สถานที่</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $groupedSchedules = $allSchedules->groupBy(fn ($s) => $s->start_date?->dayOfWeekIso);
                                @endphp
                                @if($allSchedules->isEmpty())
                                    <tr>
                                        <td colspan="6" style="padding:32px 16px;text-align:center;color:var(--fg-3);font-size:14px;">
                                            ยังไม่มีรายการสอน
                                            @if($canEdit)
                                                <div style="font-size:13px;margin-top:6px;">เพิ่มรายการสอนแรกของรายวิชาเพื่อเริ่มต้นตารางและความพร้อมของกลุ่ม</div>
                                            @endif
                                        </td>
                                    </tr>
                                @else
                                    @foreach($thaiDays as $dayIso => $dayName)
                                        @php
                                            $daySchedules = $groupedSchedules->get($dayIso, collect());
                                            $dayScheduleIds = $daySchedules->pluck('id')->map(fn ($id) => (string) $id)->values();
                                        @endphp
                                        @if($daySchedules->isNotEmpty())
                                            <tr class="sched-day-group-header" x-show="dayHasMatches(@js($dayScheduleIds))" x-cloak>
                                                <td colspan="6" style="background: oklch(93.5% 0.022 232); border-top: 1px solid var(--schedule-border-strong); border-bottom: 1px solid var(--schedule-border-strong); padding: 10px 16px;">
                                                    <div style="display: flex; align-items: center; gap: 8px;">
                                                        <span class="co-day-badge day-{{ $dayIso }}" style="margin-bottom: 0; font-size: 13px; padding: 4px 12px; border-radius: 6px;">{{ $dayName }}</span>
                                                        <span style="font-size: 12.5px; font-weight: 800; color: var(--fg-2);">· {{ $daySchedules->count() }} รายการสอน</span>
                                                    </div>
                                                </td>
                                            </tr>
                                            @foreach($daySchedules as $as)
                                                @php
                                                    $asActivity = $as->activityType;
                                                    $asRoom = $as->room;
                                                    $asInstructorText = $as->instructors->isNotEmpty()
                                                        ? ($as->instructors->count() === 1
                                                            ? ($as->instructors->first()->formatted_name ?? $as->instructors->first()->name)
                                                            : $as->instructors->count() . ' ท่าน')
                                                        : 'ไม่มีผู้สอน';
                                                    $asSameDay = $as->start_date?->format('d/m/Y') === $as->end_date?->format('d/m/Y');

                                                    $dayOfWeekName = $thaiDays[$as->start_date->dayOfWeekIso] ?? '';
                                                    if (! $asSameDay) {
                                                        $endDayName = $thaiDays[$as->end_date->dayOfWeekIso] ?? '';
                                                        if ($dayOfWeekName && $endDayName && $dayOfWeekName !== $endDayName) {
                                                            $dayOfWeekName = $dayOfWeekName . ' - ' . $endDayName;
                                                        }
                                                    }
                                                    $isMultiDay = ! $asSameDay;
                                                @endphp
                                                <tr role="button" tabindex="0" class="co-sched-row" style="--activity-color: {{ $activityTone($as) }};" x-show="matchesSchedule('{{ $as->id }}')" x-cloak data-schedule-modal-trigger @click="detailModal = 'schedule-{{ $as->id }}'" @keydown.enter.prevent="detailModal = 'schedule-{{ $as->id }}'" @keydown.space.prevent="detailModal = 'schedule-{{ $as->id }}'">
                                                    <td class="co-col-date" style="font-weight: 800; color: var(--fg-1); font-variant-numeric: tabular-nums; vertical-align: middle;">
                                                        @if($asSameDay)
                                                            {{ $formatDate($as->start_date) }}
                                                        @else
                                                            <div style="font-size: 12px; line-height: 1.35;">
                                                                {{ $formatDate($as->start_date) }}<br>
                                                                <span style="color: var(--fg-3); font-weight: 600; font-size: 11px;">ถึง</span> {{ $formatDate($as->end_date) }}
                                                            </div>
                                                        @endif
                                                    </td>
                                                    <td class="co-col-time">
                                                        <div class="co-time-range">{{ $formatTime($as->start_time) }} - {{ $formatTime($as->end_time) }}</div>
                                                        <div class="co-time-duration">{{ $formatDuration($durationForSchedule($as)) }}</div>
                                                    </td>
                                                    <td class="co-col-activity">
                                                        @if($as->topic)
                                                            <div class="co-activity-topic-main">{{ $as->topic }}</div>
                                                            <div class="co-activity-type-badge" style="--activity-color: {{ $activityTone($as) }};">
                                                                <span class="co-activity-dot-small" aria-hidden="true"></span>
                                                                <span>{{ $asActivity?->name ?? 'กิจกรรม' }}</span>
                                                            </div>
                                                        @else
                                                            <div class="co-activity-topic-main">{{ $asActivity?->name ?? 'กิจกรรม' }}</div>
                                                        @endif
                                                    </td>
                                                    <td class="co-col-groups">
                                                        <div class="co-groups-list">
                                                            @foreach($as->studentGroups as $group)
                                                                <span class="co-group-badge" style="--group-color: {{ $groupTone($group) }};">
                                                                    <span class="co-group-dot" aria-hidden="true"></span>
                                                                    <span>{{ $group->group_code }}</span>
                                                                </span>
                                                            @endforeach
                                                        </div>
                                                    </td>
                                                    <td class="co-col-instructors">
                                                        <div class="co-instructor-text">{{ $asInstructorText }}</div>
                                                    </td>
                                                    <td class="co-col-location">
                                                        <div class="co-location-room">{{ $asRoom?->room_name ?? $asRoom?->room_code ?? 'ไม่ระบุสถานที่' }}</div>
                                                        @if($asRoom?->building)
                                                            <div class="co-location-building">{{ $asRoom->building }}</div>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endif
                                    @endforeach
                                    <tr x-show="matchedScheduleCount() === 0" x-cloak>
                                        <td colspan="6">
                                            <div class="schedule-empty" style="margin:16px;">
                                                <div style="font-weight:800;color:var(--fg-2);margin-bottom:4px;">ไม่พบรายการที่ตรงกับตัวกรอง</div>
                                                <div>ลองปรับคำค้นหา ประเภทกิจกรรม กลุ่ม หรือผู้สอนอีกครั้ง</div>
                                                <div style="margin-top:12px;">
                                                    <button type="button" class="btn btn-ghost" @click="resetScheduleFilters()">ล้างตัวกรอง</button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Grid view + week nav --}}
                <div x-show="view === 'grid'" x-cloak data-testid="schedule-grid-view-co" style="padding: 16px;">

                    @if(($schedulePeriod ?? 'week') === 'month')
                        <div class="month-calendar" data-testid="schedule-month-calendar-co">
                            @foreach($shortThaiDays as $dayName)
                                <div class="month-calendar-head">{{ $dayName }}</div>
                            @endforeach

                            @foreach($monthCalendarDays as $day)
                                @php
                                    $dayOccurrences = $gridOccurrencesByDate->get($day->toDateString(), collect());
                                    $isOutsideMonth = $day->month !== $weekStart->month;
                                @endphp
                                <div class="month-calendar-day {{ $isOutsideMonth ? 'is-outside' : '' }}">
                                    <div class="month-day-top">
                                        <span class="month-day-number">{{ $day->day }}</span>
                                        @if($dayOccurrences->isNotEmpty())
                                            <span class="month-day-count">{{ $dayOccurrences->count() }} รายการ</span>
                                        @endif
                                    </div>
                                    <div class="month-day-items">
                                        @forelse($dayOccurrences as $occurrence)
                                            @php
                                                $schedule = $occurrence['schedule'];
                                                $activity = $schedule->activityType;
                                                $room = $schedule->room;
                                                $instructorText = $schedule->instructors->isNotEmpty()
                                                    ? ($schedule->instructors->count() === 1
                                                        ? ($schedule->instructors->first()->formatted_name ?? $schedule->instructors->first()->name)
                                                        : $schedule->instructors->count() . ' ท่าน')
                                                    : 'ไม่มีผู้สอน';
                                            @endphp
                                            <div role="button" tabindex="0" class="month-activity" style="--activity-color: {{ $activityTone($schedule) }};" data-schedule-modal-trigger @click="detailModal = 'schedule-{{ $schedule->id }}'" @keydown.enter.prevent="detailModal = 'schedule-{{ $schedule->id }}'" @keydown.space.prevent="detailModal = 'schedule-{{ $schedule->id }}'">
                                                <div class="month-activity-time">{{ $formatTime($schedule->start_time) }} - {{ $formatTime($schedule->end_time) }}</div>
                                                <div class="month-activity-title">{{ $schedule->topic ?: ($activity?->name ?? 'รายการสอน') }}</div>
                                                <div class="month-activity-tags">
                                                    <span class="activity-tag" style="--activity-color: {{ $activityTone($schedule) }};">{{ $activity?->name ?? 'กิจกรรม' }}</span>
                                                    @if($schedule->studentGroups->isNotEmpty())
                                                        <span class="month-group-summary">{{ $schedule->studentGroups->count() }} กลุ่ม</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @empty
                                            @if(! $isOutsideMonth)
                                                <div class="month-empty">ไม่มีรายการ</div>
                                            @endif
                                        @endforelse
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                    <div class="schedule-grid" style="grid-template-columns: 74px repeat({{ max(1, $weekDays->count()) }}, minmax(146px, 1fr));">
                        <div class="grid-cell grid-head" style="grid-area:1 / 1;"></div>
                        @foreach($weekDays as $dayIndex => $day)
                            <div class="grid-cell grid-head" style="grid-area:1 / {{ $dayIndex + 2 }};">
                                {{ $thaiDays[$day->dayOfWeekIso] ?? $day->format('l') }}<br>
                                <span class="caption">{{ $formatDate($day) }}</span>
                            </div>
                        @endforeach

                        @foreach($gridTimeSlots as $slotIndex => $slot)
                            <div class="grid-cell grid-time" style="grid-area:{{ $slotIndex + 2 }} / 1;">{{ $slot }}</div>
                            @foreach($weekDays as $dayIndex => $day)
                                @php
                                    $slotOccurrences = $gridOccurrences
                                        ->filter(fn ($occurrence) => $occurrence['date']->toDateString() === $day->toDateString() && $occurrence['time_slot'] === $slot);
                                    $cellSpan = $slotOccurrences->isEmpty() ? 1 : (int) $slotOccurrences->max($occurrenceSlotSpan);
                                @endphp
                                @if($slotOccurrences->isNotEmpty())
                                <div class="grid-cell grid-cell-activity" style="grid-column:{{ $dayIndex + 2 }}; grid-row:{{ $slotIndex + 2 }} / span {{ $cellSpan }};">
                                    @foreach($slotOccurrences as $occurrence)
                                        @php
                                            $schedule = $occurrence['schedule'];
                                            $activity = $schedule->activityType;
                                            $room = $schedule->room;
                                            $offeringCourse = $schedule->courseOffering?->course;
                                            $instructorText = $schedule->instructors->isNotEmpty()
                                                ? ($schedule->instructors->count() === 1
                                                    ? ($schedule->instructors->first()->formatted_name ?? $schedule->instructors->first()->name)
                                                    : $schedule->instructors->count() . ' ท่าน')
                                                : 'ไม่มีผู้สอน';
                                        @endphp
                                        <div role="button" tabindex="0" class="grid-activity" style="--activity-color: {{ $activityTone($schedule) }};" data-schedule-modal-trigger @click="detailModal = 'schedule-{{ $schedule->id }}'" @keydown.enter.prevent="detailModal = 'schedule-{{ $schedule->id }}'" @keydown.space.prevent="detailModal = 'schedule-{{ $schedule->id }}'">
                                            <div class="grid-activity-top">
                                                <span class="activity-tag" style="--activity-color: {{ $activityTone($schedule) }};">{{ $activity?->name ?? 'กิจกรรม' }}</span>
                                            </div>
                                            <div class="grid-activity-title">{{ $schedule->topic ?: ($activity?->name ?? 'รายการสอน') }}</div>
                                            <div class="grid-activity-time">{{ $formatTime($schedule->start_time) }} - {{ $formatTime($schedule->end_time) }} · {{ $formatDuration($occurrence['duration_minutes']) }}</div>
                                            <div class="grid-activity-foot">
                                                <span class="grid-activity-room">{{ $room?->room_name ?? $room?->room_code ?? 'ไม่ระบุสถานที่' }}</span>
                                                <span class="grid-activity-groups">{{ $schedule->studentGroups->isNotEmpty() ? $schedule->studentGroups->count() . ' กลุ่ม' : 'ไม่มีกลุ่ม' }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                @elseif(empty($gridCoveredKeys[$day->toDateString() . '|' . $slot]))
                                <div class="grid-cell" style="grid-column:{{ $dayIndex + 2 }}; grid-row:{{ $slotIndex + 2 }};"></div>
                                @endif
                            @endforeach
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
        @endif {{-- end non-workspace --}}

        @if($errors->has('schedule') && ! $openCreateModal && ! $openEditScheduleId)
            <div class="schedule-empty" style="border-color:var(--status-conflict-border);background:var(--status-conflict-bg);color:var(--status-conflict-fg);font-weight:800;">
                {{ $errors->first('schedule') }}
            </div>
        @endif

        @if($isWorkspace)
            @if($availableOfferings->isEmpty())
                <div class="schedule-empty" data-testid="schedule-no-offerings-empty">ยังไม่มีรายวิชาที่ต้องจัดตาราง</div>
            @else
            <div x-show="view === 'list'" x-cloak data-testid="schedule-list-view">
                @php
                    $statusMeta = [
                        'draft' => ['label' => 'แบบร่าง', 'class' => 'badge-gray'],
                        'pending_approval' => ['label' => 'รออนุมัติ', 'class' => 'badge-warn'],
                        'approved' => ['label' => 'อนุมัติแล้ว', 'class' => 'badge-ok'],
                        'revised' => ['label' => 'ส่งกลับแก้ไข', 'class' => 'badge-err'],
                    ];
                @endphp
                <div class="sched-list-wrap">
                    <table class="sched-list">
                        <thead>
                            <tr>
                                <th style="width:110px;">เวลา</th>
                                <th>กิจกรรม</th>
                                <th style="width:180px;">กลุ่ม</th>
                                <th style="width:140px;">ผู้สอน</th>
                                <th style="width:150px;">สถานที่</th>
                                <th style="width:100px;">สถานะ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($weekDays as $day)
                                @php
                                    $dayOccurrences = $occurrencesByDate->get($day->toDateString(), collect());
                                @endphp
                                <tr class="sched-day">
                                    <td colspan="6">
                                        <div class="sched-day-head">
                                            <span class="sched-day-name">{{ $thaiDays[$day->dayOfWeekIso] ?? $day->format('l') }}</span>
                                            <span class="sched-day-date">{{ $formatDate($day) }}</span>
                                            <span class="sched-day-count">· {{ $dayOccurrences->count() }} รายการ</span>
                                            <span class="sched-day-spacer"></span>
                                            @if($canEdit)
                                                <button type="button" class="day-add-link" @click="openCreate('{{ $day->toDateString() }}')">+ เพิ่ม</button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>

                                @forelse($dayOccurrences as $occurrence)
                                    @php
                                        $schedule = $occurrence['schedule'];
                                        $activity = $schedule->activityType;
                                        $room = $schedule->room;
                                        $offeringCourse = $schedule->courseOffering?->course;
                                        $timeText = $formatTime($schedule->start_time) . '-' . $formatTime($schedule->end_time);
                                        $instructorText = $schedule->instructors->isNotEmpty()
                                            ? ($schedule->instructors->count() === 1
                                                ? ($schedule->instructors->first()->formatted_name ?? $schedule->instructors->first()->name)
                                                : $schedule->instructors->count() . ' ท่าน')
                                            : 'ไม่มีผู้สอน';
                                        $status = $statusMeta[$schedule->status] ?? ['label' => $schedule->status, 'class' => 'badge-gray'];
                                    @endphp
                                    <tr role="button" tabindex="0" class="sched-row" style="--activity-color: {{ $activityTone($schedule) }};" data-testid="schedule-row" data-schedule-modal-trigger @click="detailModal = 'schedule-{{ $schedule->id }}'" @keydown.enter.prevent="detailModal = 'schedule-{{ $schedule->id }}'" @keydown.space.prevent="detailModal = 'schedule-{{ $schedule->id }}'">
                                        <td>
                                            <div class="sched-time-block">
                                                <div class="sched-time">{{ $timeText }}</div>
                                                <div class="sched-duration">{{ $formatDuration($occurrence['duration_minutes']) }}</div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="sched-activity-name" style="margin-top:0; font-size:13.5px; font-weight:800;">{{ $schedule->topic ?: ($activity?->name ?? 'รายการสอน') }}</div>
                                            <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap; margin-top:4.5px;">
                                                <span class="activity-tag" style="--activity-color: {{ $activityTone($schedule) }}; font-size: 9.5px; padding: 1px 6px; min-height: 18px; line-height: 1.2;">{{ $activity?->name ?? 'กิจกรรม' }}</span>
                                                <span class="sched-activity-course" style="margin-left:0; font-size: 10.5px;">{{ $offeringCourse?->course_code ?? '-' }}</span>
                                                @if($isWorkspace && ($offeringCourse?->name_th || $offeringCourse?->name_en))
                                                    <span class="sched-muted" style="font-size: 10.5px;">· {{ $offeringCourse?->name_th ?? $offeringCourse?->name_en }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            @if($schedule->studentGroups->isNotEmpty())
                                                <div class="sched-cell-groups">
                                                    @foreach($schedule->studentGroups as $group)
                                                        <span class="group-chip"><span class="group-dot" style="background: {{ $groupTone($group) }};"></span>{{ $group->group_code }}</span>
                                                    @endforeach
                                                </div>
                                            @else
                                                <span class="sched-muted">—</span>
                                            @endif
                                        </td>
                                        <td><span class="sched-strong">{{ $instructorText }}</span></td>
                                        <td>
                                            <div class="sched-strong">{{ $room?->room_name ?? $room?->room_code ?? 'ไม่ระบุสถานที่' }}</div>
                                            @if($room?->building)
                                                <div class="sched-muted" style="margin-top:1px;">{{ $room->building }}</div>
                                            @endif
                                        </td>
                                        <td><span class="badge {{ $status['class'] }}">{{ $status['label'] }}</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="sched-empty-cell">ยังไม่มีกิจกรรม@if($canEdit) · กด “+ เพิ่ม” เพื่อใส่กิจกรรม@endif</td>
                                    </tr>
                                @endforelse
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div x-show="view === 'grid'" x-cloak data-testid="schedule-grid-view">
                @if(($schedulePeriod ?? 'week') === 'month')
                    <div class="month-calendar" data-testid="schedule-month-calendar">
                        @foreach($shortThaiDays as $dayName)
                            <div class="month-calendar-head">{{ $dayName }}</div>
                        @endforeach

                        @foreach($monthCalendarDays as $day)
                            @php
                                $dayOccurrences = $occurrencesByDate->get($day->toDateString(), collect());
                                $isOutsideMonth = $day->month !== $weekStart->month;
                            @endphp
                            <div class="month-calendar-day {{ $isOutsideMonth ? 'is-outside' : '' }}">
                                <div class="month-day-top">
                                    <span class="month-day-number">{{ $day->day }}</span>
                                    @if($dayOccurrences->isNotEmpty())
                                        <span class="month-day-count">{{ $dayOccurrences->count() }} รายการ</span>
                                    @endif
                                </div>
                                <div class="month-day-items">
                                    @forelse($dayOccurrences as $occurrence)
                                        @php
                                            $schedule = $occurrence['schedule'];
                                            $activity = $schedule->activityType;
                                            $room = $schedule->room;
                                            $offeringCourse = $schedule->courseOffering?->course;
                                            $instructorText = $schedule->instructors->isNotEmpty()
                                                ? ($schedule->instructors->count() === 1
                                                    ? ($schedule->instructors->first()->formatted_name ?? $schedule->instructors->first()->name)
                                                    : $schedule->instructors->count() . ' ท่าน')
                                                : 'ไม่มีผู้สอน';
                                            $status = $statusMeta[$schedule->status] ?? ['label' => $schedule->status, 'class' => 'badge-gray'];
                                        @endphp
                                        <div role="button" tabindex="0" class="month-activity" style="--activity-color: {{ $activityTone($schedule) }};" data-schedule-modal-trigger @click="detailModal = 'schedule-{{ $schedule->id }}'" @keydown.enter.prevent="detailModal = 'schedule-{{ $schedule->id }}'" @keydown.space.prevent="detailModal = 'schedule-{{ $schedule->id }}'">
                                            <div class="month-activity-time">{{ $formatTime($schedule->start_time) }} - {{ $formatTime($schedule->end_time) }}</div>
                                            <div class="month-activity-title">{{ $schedule->topic ?: ($activity?->name ?? 'รายการสอน') }}</div>
                                            <div class="month-activity-meta">
                                                @if($offeringCourse?->course_code)
                                                    {{ $offeringCourse->course_code }}
                                                @else
                                                    {{ $room?->room_name ?? $room?->room_code ?? 'ไม่ระบุสถานที่' }}
                                                @endif
                                            </div>
                                            <div class="month-activity-tags">
                                                <span class="activity-tag" style="--activity-color: {{ $activityTone($schedule) }};">{{ $activity?->name ?? 'กิจกรรม' }}</span>
                                                @if($schedule->studentGroups->isNotEmpty())
                                                    <span class="month-group-summary">{{ $schedule->studentGroups->count() }} กลุ่ม</span>
                                                @endif
                                                <span class="badge {{ $status['class'] }}">{{ $status['label'] }}</span>
                                            </div>
                                        </div>
                                    @empty
                                        @if(! $isOutsideMonth)
                                            <div class="month-empty">ไม่มีรายการ</div>
                                        @endif
                                    @endforelse
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                <div class="schedule-grid" style="grid-template-columns: 74px repeat({{ max(1, $weekDays->count()) }}, minmax(146px, 1fr));">
                    <div class="grid-cell grid-head"></div>
                    @foreach($weekDays as $day)
                        <div class="grid-cell grid-head">
                            {{ $thaiDays[$day->dayOfWeekIso] ?? $day->format('l') }}<br>
                            <span class="caption">{{ $formatDate($day) }}</span>
                        </div>
                    @endforeach

                    @foreach($timeSlots as $slot)
                        <div class="grid-cell grid-time">{{ $slot }}</div>
                        @foreach($weekDays as $day)
                            @php
                                $slotOccurrences = $occurrences
                                    ->filter(fn ($occurrence) => $occurrence['date']->toDateString() === $day->toDateString() && $occurrence['time_slot'] === $slot);
                            @endphp
                            <div class="grid-cell">
                                @foreach($slotOccurrences as $occurrence)
                                    @php
                                        $schedule = $occurrence['schedule'];
                                        $activity = $schedule->activityType;
                                        $room = $schedule->room;
                                        $offeringCourse = $schedule->courseOffering?->course;
                                        $instructorText = $schedule->instructors->isNotEmpty()
                                            ? ($schedule->instructors->count() === 1
                                                ? ($schedule->instructors->first()->formatted_name ?? $schedule->instructors->first()->name)
                                                : $schedule->instructors->count() . ' ท่าน')
                                            : 'ไม่มีผู้สอน';
                                        $status = $statusMeta[$schedule->status] ?? ['label' => $schedule->status, 'class' => 'badge-gray'];
                                    @endphp
                                    <div role="button" tabindex="0" class="grid-activity" style="--activity-color: {{ $activityTone($schedule) }};" data-schedule-modal-trigger @click="detailModal = 'schedule-{{ $schedule->id }}'" @keydown.enter.prevent="detailModal = 'schedule-{{ $schedule->id }}'" @keydown.space.prevent="detailModal = 'schedule-{{ $schedule->id }}'">
                                        <div class="grid-activity-top">
                                            @if($offeringCourse?->course_code)
                                                <span class="grid-course">{{ $offeringCourse->course_code }}</span>
                                            @endif
                                            <span class="activity-tag" style="--activity-color: {{ $activityTone($schedule) }};">{{ $activity?->name ?? 'กิจกรรม' }}</span>
                                        </div>
                                        <div class="grid-activity-title">{{ $schedule->topic ?: ($activity?->name ?? 'รายการสอน') }}</div>
                                        @if($isWorkspace && ($offeringCourse?->name_th || $offeringCourse?->name_en))
                                            <div class="grid-activity-sub">{{ $offeringCourse?->name_th ?? $offeringCourse?->name_en }}</div>
                                        @elseif($schedule->topic && $activity?->name)
                                            <div class="grid-activity-sub">{{ $activity->name }}</div>
                                        @endif
                                        <div class="grid-activity-meta">
                                            <div>{{ $formatTime($schedule->start_time) }} - {{ $formatTime($schedule->end_time) }} · {{ $formatDuration($occurrence['duration_minutes']) }}</div>
                                            <div class="grid-instructor">{{ $instructorText }}</div>
                                            <div>
                                                <div class="grid-location-name">{{ $room?->room_name ?? $room?->room_code ?? 'ไม่ระบุสถานที่' }}</div>
                                                @if($room?->building)
                                                    <div class="grid-location-building">{{ $room->building }}</div>
                                                @endif
                                            </div>
                                        </div>
                                        @if($schedule->studentGroups->isNotEmpty())
                                            <div class="grid-groups">
                                                @foreach($schedule->studentGroups as $group)
                                                    <span class="co-group-badge" style="--group-color: {{ $groupTone($group) }};">
                                                        <span class="co-group-dot" aria-hidden="true"></span>
                                                        <span>{{ $group->group_code }}</span>
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="grid-activity-sub">ไม่มีกลุ่มนักศึกษา</div>
                                        @endif
                                        <div><span class="badge {{ $status['class'] }}">{{ $status['label'] }}</span></div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    @endforeach
                </div>
                @endif
            </div>
            @endif
        @endif

        @php
            $modalSchedules = $isWorkspace ? $schedules : $allSchedules;
        @endphp
        @foreach($modalSchedules as $schedule)
            @php
                $activity = $schedule->activityType;
                $room = $schedule->room;
                $offering = $schedule->courseOffering;
                $offeringCourse = $offering?->course;
                $timeText = $formatTime($schedule->start_time) . '-' . $formatTime($schedule->end_time);
                $dateText = $schedule->start_date?->format('Y-m-d') === $schedule->end_date?->format('Y-m-d')
                    ? $formatDate($schedule->start_date)
                    : ($formatDate($schedule->start_date) . ' - ' . $formatDate($schedule->end_date));
                $scheduleCanEdit = $offering?->academicYear?->phase === 'scheduling';
            @endphp
            <div class="schedule-modal-backdrop" x-show="detailModal === 'schedule-{{ $schedule->id }}'" x-cloak @click.self="detailModal = null" data-testid="schedule-detail-modal">
                <template x-if="detailModal === 'schedule-{{ $schedule->id }}'">
                    <section class="schedule-modal" role="dialog" aria-modal="true" aria-labelledby="schedule-detail-title-{{ $schedule->id }}" style="--activity-color: {{ $activityTone($schedule) }};">
                    <div class="modal-handle"></div>
                    <div class="modal-head-detail">
                        <div style="min-width:0;">
                            <div class="modal-title-detail" id="schedule-detail-title-{{ $schedule->id }}">{{ $schedule->topic ?: ($activity?->name ?? 'รายการสอน') }}</div>
                            <span class="activity-tag" style="--activity-color: {{ $activityTone($schedule) }}; margin-top:5px;">{{ $activity?->name ?? 'กิจกรรม' }}</span>
                        </div>
                        <button type="button" class="modal-close" @click="detailModal = null" aria-label="ปิด">×</button>
                    </div>
                    <div class="detail-body">
                        <div class="detail-grid">
                            <div class="detail-row">
                                <div class="detail-row-label">วันที่</div>
                                <div class="detail-row-value">{{ $dateText }} · {{ $timeText }} <span class="sub">({{ $formatDuration($durationForSchedule($schedule)) }})</span></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-row-label">รายวิชา</div>
                                <div class="detail-row-value">{{ $offeringCourse?->course_code ?? '-' }} {{ $offeringCourse?->name_th ?? $offeringCourse?->name_en ?? '' }}</div>
                            </div>
                            <div class="detail-row" style="align-items:flex-start;">
                                <div class="detail-row-label" style="padding-top:1px;">ผู้สอน</div>
                                <div class="detail-row-value">
                                    @if($schedule->instructors->isNotEmpty())
                                        <div style="display:flex;flex-direction:column;gap:3px;">
                                            @foreach($schedule->instructors as $inst)
                                                @php
                                                    $roleObj = $inst->pivot?->courseRole;
                                                    $roleName = $roleObj?->name_th ?? 'อาจารย์ผู้สอน';
                                                @endphp
                                                <div>
                                                    <span>{{ $inst->formatted_name ?? $inst->name }}</span>
                                                    @if($roleName !== 'อาจารย์ผู้สอน')<span style="color:var(--fg-3);font-size:11px;margin-left:4px;">({{ $roleName }})</span>@endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <span style="color:var(--fg-3);">ไม่มีผู้สอน</span>
                                    @endif
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-row-label">สถานที่</div>
                                <div class="detail-row-value">
                                    {{ $room?->room_name ?? $room?->room_code ?? 'ไม่ระบุ' }}@if($room?->building) <span class="sub">· {{ $room->building }}</span>@endif
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-row-label">กลุ่ม</div>
                                <div class="detail-row-value">
                                    @if($schedule->studentGroups->isNotEmpty())
                                        <div class="detail-chips">
                                            @foreach($schedule->studentGroups as $grp)
                                                <span class="detail-chip">
                                                    <span class="detail-chip-dot" style="background: {{ $groupTone($grp) }};"></span>
                                                    {{ $grp->group_code }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span style="color:var(--fg-3);">—</span>
                                    @endif
                                </div>
                            </div>
                            @if($schedule->remark)
                                <div class="detail-row">
                                    <div class="detail-row-label">หมายเหตุ</div>
                                    <div class="detail-row-value" style="color:var(--fg-2);">{{ $schedule->remark }}</div>
                                </div>
                            @endif
                        </div>
                    </div>
                    @if($scheduleCanEdit)
                        <div class="modal-actions">
                            <button type="button" class="btn btn-secondary" data-testid="schedule-edit-modal-trigger" @click="openEdit('{{ $schedule->id }}')" style="display:inline-flex;align-items:center;gap:5px;">
                                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                แก้ไข
                            </button>
                            <form id="delete-schedule-{{ $schedule->id }}" method="POST" action="{{ route('maker.course_offerings.schedules.destroy', [$offering, $schedule]) }}" style="display:none;">
                                @csrf
                                @method('DELETE')
                            </form>
                            <button type="button" class="btn btn-red" data-form="delete-schedule-{{ $schedule->id }}" data-label="{{ $activity?->name ?? 'รายการสอน' }} {{ $timeText }}" onclick="tpssDelete(this)" data-testid="schedule-delete-button" style="display:inline-flex;align-items:center;gap:5px;">
                                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                ลบ
                            </button>
                        </div>
                    @endif
                </section>
                </template>
            </div>

            @if($scheduleCanEdit)
                @php
                    $editUsesOld = (string) old('edit_schedule_id') === (string) $schedule->id;
                    $editInstructorIds = collect($editUsesOld ? old('instructor_ids', []) : $schedule->instructors->pluck('id')->all())
                        ->map(fn ($id) => (string) $id)
                        ->all();
                    $editGroupIds = collect($editUsesOld ? old('student_group_ids', []) : $schedule->studentGroups->pluck('id')->all())
                        ->map(fn ($id) => (string) $id)
                        ->all();
                    $editLeadInstructorId = (string) ($editUsesOld
                        ? old('lead_instructor_id', '')
                        : ($schedule->instructors->first(fn ($instructor) => (bool) $instructor->pivot?->is_lead)?->id ?? ''));
                    $editOld = fn (string $key, mixed $default = null) => $editUsesOld ? old($key, $default) : $default;
                    $editDateDisplay = function (string $key, $date) use ($editOld, $formatDate) {
                        $value = (string) $editOld($key, $date?->format('Y-m-d'));

                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                            return $formatDate(\Carbon\CarbonImmutable::parse($value));
                        }

                        return $value;
                    };
                @endphp
                <div class="schedule-modal-backdrop" x-show="editModal === 'schedule-{{ $schedule->id }}'" x-cloak @click.self="closeEdit()" data-testid="schedule-edit-modal">
                    <template x-if="editModal === 'schedule-{{ $schedule->id }}'">
                        <section class="schedule-modal is-form" role="dialog" aria-modal="true" aria-labelledby="schedule-edit-title-{{ $schedule->id }}">
                        <div class="modal-handle"></div>
                        <div class="modal-head">
                            <div>
                                <!-- removed edit tag -->
                                <div class="modal-title" id="schedule-edit-title-{{ $schedule->id }}">แก้ไขรายละเอียดกิจกรรม</div>
                            </div>
                            <button type="button" class="modal-close" @click="closeEdit()" aria-label="ปิด">×</button>
                        </div>
                        <form
                            method="POST"
                            action="{{ route('maker.course_offerings.schedules.update', [$offering, $schedule]) }}"
                            data-testid="schedule-edit-form"
                            x-data="{
                                startDateDisplay: @js($editDateDisplay('start_date', $schedule->start_date)),
                                endDateDisplay: @js($editDateDisplay('end_date', $schedule->end_date)),
                                editInstructorSearch: '',
                                editGroupSearch: '',
                            }"
                        >
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="modal_mode" value="edit">
                            <input type="hidden" name="edit_schedule_id" value="{{ $schedule->id }}">
                            <div class="modal-form-body">
                                @if($editUsesOld && $errors->any())
                                    <div class="schedule-empty" style="margin-bottom:12px;border-color:var(--status-conflict-border);background:var(--status-conflict-bg);color:var(--status-conflict-fg);font-weight:800;">
                                        {{ $errors->first() }}
                                    </div>
                                @endif

                                <div class="modal-form-grid">
                                    <div>
                                        <label class="modal-label" for="edit_start_date_{{ $schedule->id }}">วันที่เริ่ม <span class="required-mark">*</span></label>
                                        <x-thai-date-input
                                            name="start_date"
                                            :value="$editOld('start_date', $schedule->start_date?->format('Y-m-d'))"
                                            id="edit_start_date_{{ $schedule->id }}"
                                            class="modal-control"
                                            :required="true"
                                            :helper="false"
                                            x-model="startDateDisplay" />
                                    </div>
                                    <div>
                                        <label class="modal-label" for="edit_end_date_{{ $schedule->id }}">วันที่สิ้นสุด <span class="required-mark">*</span></label>
                                        <x-thai-date-input
                                            name="end_date"
                                            :value="$editOld('end_date', $schedule->end_date?->format('Y-m-d'))"
                                            id="edit_end_date_{{ $schedule->id }}"
                                            class="modal-control"
                                            :required="true"
                                            :helper="false"
                                            x-model="endDateDisplay" />
                                    </div>
                                    <div>
                                        <label class="modal-label" for="edit_start_time_{{ $schedule->id }}">เวลาเริ่ม <span class="required-mark">*</span></label>
                                        <input id="edit_start_time_{{ $schedule->id }}" name="start_time" type="time" required class="modal-control" value="{{ $editOld('start_time', $formatTime($schedule->start_time)) }}">
                                    </div>
                                    <div>
                                        <label class="modal-label" for="edit_end_time_{{ $schedule->id }}">เวลาสิ้นสุด <span class="required-mark">*</span></label>
                                        <input id="edit_end_time_{{ $schedule->id }}" name="end_time" type="time" required class="modal-control" value="{{ $editOld('end_time', $formatTime($schedule->end_time)) }}">
                                    </div>
                                    <div>
                                        <label class="modal-label" for="edit_activity_type_id_{{ $schedule->id }}">ประเภทกิจกรรม <span class="required-mark">*</span></label>
                                        <select id="edit_activity_type_id_{{ $schedule->id }}" name="activity_type_id" required class="modal-control tpss-choices">
                                            @foreach($activityTypes as $activityType)
                                                <option value="{{ $activityType->id }}" @selected((string) $editOld('activity_type_id', $schedule->activity_type_id) === (string) $activityType->id)>
                                                    {{ $activityType->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="modal-label" for="edit_room_id_{{ $schedule->id }}">ห้อง/สถานที่</label>
                                        <select id="edit_room_id_{{ $schedule->id }}" name="room_id" class="modal-control tpss-choices">
                                            <option value="">ไม่ระบุสถานที่</option>
                                            @foreach($rooms as $roomOption)
                                                <option value="{{ $roomOption->id }}" @selected((string) $editOld('room_id', $schedule->room_id) === (string) $roomOption->id)>
                                                    {{ $roomOption->room_code }} · {{ $roomOption->room_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="modal-field-full">
                                        <label class="modal-label" for="edit_topic_{{ $schedule->id }}">หัวข้อกิจกรรม <span class="required-mark">*</span></label>
                                        <input id="edit_topic_{{ $schedule->id }}" name="topic" type="text" maxlength="255" required class="modal-control" value="{{ $editOld('topic', $schedule->topic) }}" placeholder="เช่น บรรยายเรื่องการประเมินผู้ป่วย">
                                    </div>
                                    <div>
                                        <label class="modal-label" for="edit_capacity_required_{{ $schedule->id }}">จำนวนที่รองรับ <span class="optional-note">ไม่ระบุ = ไม่จำกัดจำนวน</span></label>
                                        <input id="edit_capacity_required_{{ $schedule->id }}" name="capacity_required" type="number" min="1" class="modal-control" value="{{ $editOld('capacity_required', $schedule->capacity_required) }}">
                                    </div>
                                    <div class="modal-field-full">
                                        <label class="modal-label" for="edit_remark_{{ $schedule->id }}">หมายเหตุ</label>
                                        <textarea id="edit_remark_{{ $schedule->id }}" name="remark" rows="2" class="modal-control" placeholder="เช่น ให้นักศึกษาเตรียมเอกสารก่อนเข้าเรียน หรือแจ้งอุปกรณ์ที่ต้องใช้">{{ $editOld('remark', $schedule->remark) }}</textarea>
                                    </div>
                                </div>

                                <div class="modal-section">
                                    <div class="modal-section-title">ผู้สอน <span class="required-mark">*</span></div>
                                    @php
                                        $editInstructorSearchItems = $offering->instructorPool
                                            ->map(fn ($instructor) => mb_strtolower($instructor->formatted_name ?? $instructor->name, 'UTF-8'))
                                            ->values();
                                    @endphp
                                    <input type="search" class="modal-choice-search" x-model="editInstructorSearch" placeholder="ค้นหาชื่อผู้สอน" aria-label="ค้นหาผู้สอน">
                                    <div class="modal-choice-grid">
                                        @foreach($offering->instructorPool as $instructor)
                                            @php
                                                $editInstructorSearchText = mb_strtolower($instructor->formatted_name ?? $instructor->name, 'UTF-8');
                                            @endphp
                                            <label class="modal-choice" x-show="matchesCreateSearch(@js($editInstructorSearchText), editInstructorSearch)" x-cloak>
                                                <input type="checkbox" name="instructor_ids[]" value="{{ $instructor->id }}" @checked(in_array((string) $instructor->id, $editInstructorIds, true)) data-testid="schedule-instructor">
                                                <span>{{ $instructor->formatted_name ?? $instructor->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <div class="modal-choice-empty" x-show="hasCreateSearch(editInstructorSearch) && !hasCreateSearchMatches(@js($editInstructorSearchItems), editInstructorSearch)" x-cloak>ไม่พบข้อมูลที่ค้นหา</div>
                                </div>

                                <div class="modal-section">
                                    <label class="modal-label" for="edit_lead_instructor_id_{{ $schedule->id }}">ผู้สอนหลัก</label>
                                    <select id="edit_lead_instructor_id_{{ $schedule->id }}" name="lead_instructor_id" class="modal-control">
                                        <option value="">ไม่ระบุ</option>
                                        @foreach($offering->instructorPool as $instructor)
                                            <option value="{{ $instructor->id }}" @selected($editLeadInstructorId === (string) $instructor->id)>
                                                {{ $instructor->formatted_name ?? $instructor->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="modal-section">
                                    <div class="modal-section-title">กลุ่มนักศึกษา <span class="required-mark">*</span></div>
                                    @php
                                        $editGroupSearchItems = $offering->studentGroups
                                            ->map(fn ($group) => mb_strtolower($group->group_code . ' ' . $group->student_count . ' คน', 'UTF-8'))
                                            ->values();
                                    @endphp
                                    <input type="search" class="modal-choice-search" x-model="editGroupSearch" placeholder="ค้นหารหัสกลุ่มนักศึกษา" aria-label="ค้นหากลุ่มนักศึกษา">
                                    <div class="modal-choice-grid">
                                        @foreach($offering->studentGroups as $group)
                                            @php
                                                $editGroupSearchText = mb_strtolower($group->group_code . ' ' . $group->student_count . ' คน', 'UTF-8');
                                            @endphp
                                            <label class="modal-choice" x-show="matchesCreateSearch(@js($editGroupSearchText), editGroupSearch)" x-cloak>
                                                <input type="checkbox" name="student_group_ids[]" value="{{ $group->id }}" @checked(in_array((string) $group->id, $editGroupIds, true)) data-testid="schedule-student-group">
                                                <span>{{ $group->group_code }} · {{ $group->student_count }} คน</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <div class="modal-choice-empty" x-show="hasCreateSearch(editGroupSearch) && !hasCreateSearchMatches(@js($editGroupSearchItems), editGroupSearch)" x-cloak>ไม่พบข้อมูลที่ค้นหา</div>
                                </div>
                            </div>
                            <div class="modal-actions">
                                <button type="button" class="btn btn-secondary" @click="closeEdit()">ยกเลิก</button>
                                <button type="submit" class="btn btn-primary" data-testid="schedule-submit">บันทึกการแก้ไข</button>
                            </div>
                        </form>
                    </section>
                    </template>
                </div>
            @endif
        @endforeach

        @if($canEdit)
            <div class="schedule-modal-backdrop" x-show="showCreate" x-cloak @click.self="closeCreate()" data-testid="schedule-create-modal">
                <section class="schedule-modal is-form" role="dialog" aria-modal="true" aria-labelledby="schedule-create-title">
                    <div class="modal-handle"></div>
                    <div class="modal-head">
                        <div>
                            <!-- removed create tag -->
                            <div class="modal-title" id="schedule-create-title">เพิ่มกิจกรรมในตาราง</div>
                        </div>
                        <button type="button" class="modal-close" @click="closeCreate()" aria-label="ปิด">×</button>
                    </div>
                    <form method="POST" action="{{ $createAction }}" data-testid="schedule-form" x-ref="createForm">
                        @csrf
                        <input type="hidden" name="modal_mode" value="create">
                        <div class="modal-form-body">
                            @if($errors->any())
                                <div class="schedule-empty" style="margin-bottom:12px;border-color:var(--status-conflict-border);background:var(--status-conflict-bg);color:var(--status-conflict-fg);font-weight:800;">
                                    {{ $errors->first() }}
                                </div>
                            @endif

                            <div class="modal-form-grid">
                                @if($isWorkspace)
                                    <div class="modal-field-full">
                                        <label class="modal-label" for="course_offering_id">รายวิชา <span class="required-mark">*</span></label>
                                        <select id="course_offering_id" name="course_offering_id" required class="modal-control" data-testid="schedule-course-offering" x-model="selectedOfferingId">
                                            @foreach($schedulingOfferings as $offeringOption)
                                                @php
                                                    $optionCourse = $offeringOption->course;
                                                    $optionYear = $offeringOption->academicYear;
                                                @endphp
                                                <option value="{{ $offeringOption->id }}">
                                                    {{ $optionCourse?->course_code ?? '-' }} · {{ $optionCourse?->name_th ?? $optionCourse?->name_en ?? '-' }} · {{ $optionYear?->name ?? '-' }}/{{ $optionYear?->semester ?? '-' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                @else
                                    <input type="hidden" name="course_offering_id" value="{{ $courseOffering->id }}" data-testid="schedule-course-offering">
                                @endif

                                <div>
                                    <label class="modal-label" for="start_date">วันที่เริ่ม <span class="required-mark">*</span></label>
                                    <x-thai-date-input
                                        name="start_date"
                                        id="start_date"
                                        class="modal-control"
                                        :required="true"
                                        :helper="false"
                                        x-model="createStartDate" />
                                </div>
                                <div>
                                    <label class="modal-label" for="end_date">วันที่สิ้นสุด <span class="required-mark">*</span></label>
                                    <x-thai-date-input
                                        name="end_date"
                                        id="end_date"
                                        class="modal-control"
                                        :required="true"
                                        :helper="false"
                                        x-model="createEndDate" />
                                </div>
                                <div>
                                    <label class="modal-label" for="start_time">เวลาเริ่ม <span class="required-mark">*</span></label>
                                    <input id="start_time" name="start_time" type="time" required class="modal-control" value="{{ old('start_time') }}">
                                </div>
                                <div>
                                    <label class="modal-label" for="end_time">เวลาสิ้นสุด <span class="required-mark">*</span></label>
                                    <input id="end_time" name="end_time" type="time" required class="modal-control" value="{{ old('end_time') }}">
                                </div>
                                <div>
                                    <label class="modal-label" for="activity_type_id">ประเภทกิจกรรม <span class="required-mark">*</span></label>
                                    <select id="activity_type_id" name="activity_type_id" required class="modal-control tpss-choices">
                                        <option value="">เลือกประเภทกิจกรรม</option>
                                        @foreach($activityTypes as $activityType)
                                            <option value="{{ $activityType->id }}" @selected((string) old('activity_type_id') === (string) $activityType->id)>
                                                {{ $activityType->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="modal-label" for="room_id">ห้อง/สถานที่</label>
                                    <select id="room_id" name="room_id" class="modal-control tpss-choices">
                                        <option value="">ไม่ระบุสถานที่</option>
                                        @foreach($rooms as $room)
                                            <option value="{{ $room->id }}" @selected((string) old('room_id') === (string) $room->id)>
                                                {{ $room->room_code }} · {{ $room->room_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="modal-field-full">
                                    <label class="modal-label" for="topic">หัวข้อกิจกรรม <span class="required-mark">*</span></label>
                                    <input id="topic" name="topic" type="text" maxlength="255" required class="modal-control" value="{{ old('topic') }}" placeholder="เช่น บรรยายเรื่องการประเมินผู้ป่วย">
                                </div>
                                <div>
                                    <label class="modal-label" for="capacity_required">จำนวนที่รองรับ <span class="optional-note">ไม่ระบุ = ไม่จำกัดจำนวน</span></label>
                                    <input id="capacity_required" name="capacity_required" type="number" min="1" class="modal-control" value="{{ old('capacity_required') }}">
                                </div>
                                <div class="modal-field-full">
                                    <label class="modal-label" for="remark">หมายเหตุ</label>
                                    <textarea id="remark" name="remark" rows="2" class="modal-control" placeholder="เช่น ให้นักศึกษาเตรียมเอกสารก่อนเข้าเรียน หรือแจ้งอุปกรณ์ที่ต้องใช้">{{ old('remark') }}</textarea>
                                </div>
                            </div>

                            @foreach($schedulingOfferings as $offeringOption)
                                <div x-show="selectedOfferingId === '{{ $offeringOption->id }}'" x-cloak>
                                    <div class="modal-section">
                                        <div class="modal-section-title">ผู้สอน <span class="required-mark">*</span></div>
                                        @php
                                            $createInstructorSearchItems = $offeringOption->instructorPool
                                                ->map(fn ($instructor) => mb_strtolower($instructor->formatted_name ?? $instructor->name, 'UTF-8'))
                                                ->values();
                                        @endphp
                                        <input type="search" class="modal-choice-search" x-model="createInstructorSearch" placeholder="ค้นหาชื่อผู้สอน" aria-label="ค้นหาผู้สอน">
                                        <div class="modal-choice-grid">
                                            @foreach($offeringOption->instructorPool as $instructor)
                                                @php
                                                    $instructorSearchText = mb_strtolower($instructor->formatted_name ?? $instructor->name, 'UTF-8');
                                                @endphp
                                                <label class="modal-choice" x-show="matchesCreateSearch(@js($instructorSearchText), createInstructorSearch)" x-cloak>
                                                    <input type="checkbox" name="instructor_ids[]" value="{{ $instructor->id }}" @checked(in_array((string) $instructor->id, $selectedInstructorIds, true)) :disabled="selectedOfferingId !== '{{ $offeringOption->id }}'" data-testid="schedule-instructor">
                                                    <span>{{ $instructor->formatted_name ?? $instructor->name }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        <div class="modal-choice-empty" x-show="hasCreateSearch(createInstructorSearch) && !hasCreateSearchMatches(@js($createInstructorSearchItems), createInstructorSearch)" x-cloak>ไม่พบข้อมูลที่ค้นหา</div>
                                    </div>

                                    <div class="modal-section">
                                        <label class="modal-label" for="lead_instructor_id_{{ $offeringOption->id }}">ผู้สอนหลัก <span class="optional-note">ไม่บังคับ</span></label>
                                        <select id="lead_instructor_id_{{ $offeringOption->id }}" name="lead_instructor_id" class="modal-control" :disabled="selectedOfferingId !== '{{ $offeringOption->id }}'">
                                            <option value="">ไม่ระบุ</option>
                                            @foreach($offeringOption->instructorPool as $instructor)
                                                <option value="{{ $instructor->id }}" @selected($leadInstructorId === (string) $instructor->id)>
                                                    {{ $instructor->formatted_name ?? $instructor->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="modal-section">
                                        <div class="modal-section-title">กลุ่มนักศึกษา <span class="required-mark">*</span></div>
                                        @php
                                            $createGroupSearchItems = $offeringOption->studentGroups
                                                ->map(fn ($group) => mb_strtolower($group->group_code . ' ' . $group->student_count . ' คน', 'UTF-8'))
                                                ->values();
                                        @endphp
                                        <input type="search" class="modal-choice-search" x-model="createGroupSearch" placeholder="ค้นหารหัสกลุ่มนักศึกษา" aria-label="ค้นหากลุ่มนักศึกษา">
                                        <div class="modal-choice-grid">
                                            @foreach($offeringOption->studentGroups as $group)
                                                @php
                                                    $groupSearchText = mb_strtolower($group->group_code . ' ' . $group->student_count . ' คน', 'UTF-8');
                                                @endphp
                                                <label class="modal-choice" x-show="matchesCreateSearch(@js($groupSearchText), createGroupSearch)" x-cloak>
                                                    <input type="checkbox" name="student_group_ids[]" value="{{ $group->id }}" @checked(in_array((string) $group->id, $selectedGroupIds, true)) :disabled="selectedOfferingId !== '{{ $offeringOption->id }}'" data-testid="schedule-student-group">
                                                    <span>{{ $group->group_code }} · {{ $group->student_count }} คน</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        <div class="modal-choice-empty" x-show="hasCreateSearch(createGroupSearch) && !hasCreateSearchMatches(@js($createGroupSearchItems), createGroupSearch)" x-cloak>ไม่พบข้อมูลที่ค้นหา</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-secondary" @click="closeCreate()">ยกเลิก</button>
                            <button type="submit" class="btn btn-primary" data-testid="schedule-submit">บันทึกรายการสอน</button>
                        </div>
                    </form>
                </section>
            </div>
        @endif
    </div>
</x-app-layout>
