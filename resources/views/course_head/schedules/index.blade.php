@php
    $availableOfferings = ($availableOfferings ?? collect())->filter(fn ($offering) => $offering->academicYear?->phase === 'scheduling')->values();
    $activityTypes = $activityTypes ?? collect();
    $rooms = $rooms ?? collect();
    $scheduleConflicts = $scheduleConflicts ?? collect();
    $isWorkspace = (bool) ($isWorkspace ?? false);
    $activeOfferingCount = $availableOfferings->filter(fn ($offering) => $offering->academicYear?->phase === 'scheduling')->count();
    $academicYear = $courseOffering?->academicYear ?? $availableOfferings->first()?->academicYear;
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
    $focusedScheduleId = (string) request('focus_schedule_id', request('edit_schedule_id', ''));
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

        return $fieldLabel . 'มีข้อมูลซ้อนกับรายการอื่น ' . $items->count() . ' จุด';
    };
    $selectedInstructorIds = collect(old('instructor_ids', []))->map(fn ($id) => (string) $id)->all();
    $selectedGroupIds = collect(old('student_group_ids', []))->map(fn ($id) => (string) $id)->all();
    $leadInstructorId = (string) old('lead_instructor_id', '');
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
    $openCreateModal = $canEdit && ! $openEditScheduleId && (
        request('modal') === 'create'
        || $oldModalMode === 'create'
        || old('start_date')
        || old('course_offering_id')
    );
    $occurrencesByDate = $occurrencesByDate ?? $occurrences->groupBy(fn ($item) => $item['date']->toDateString());
    $gridOccurrences = $occurrences ?? collect();
    $gridOccurrencesByDate = $gridOccurrencesByDate ?? $gridOccurrences->groupBy(fn ($item) => $item['date']->toDateString());
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
    $gridMinuteStep = 5;
    $gridMinuteRowHeight = 8;
    $gridRowsPerHour = (int) (60 / $gridMinuteStep);
    $gridStartHour = max(0, min(
        $gridTimeSlots ? min(array_map(fn ($slot) => (int) substr($slot, 0, 2), $gridTimeSlots)) : 6,
        6
    ));
    $gridEndHour = max(
        ($gridTimeSlots ? max(array_map(fn ($slot) => (int) substr($slot, 0, 2), $gridTimeSlots)) : 16) + 1,
        (int) $gridOccurrences->max(function ($occurrence) {
            $endTime = (string) $occurrence['schedule']->end_time;
            $hour = (int) substr($endTime, 0, 2);
            $minute = (int) substr($endTime, 3, 2);

            return $minute > 0 ? $hour + 1 : $hour;
        }) ?: 17
    );
    $gridMinuteRowCount = max($gridRowsPerHour, ($gridEndHour - $gridStartHour) * $gridRowsPerHour);
    $gridHourSlots = collect(range($gridStartHour, $gridEndHour - 1))
        ->map(fn (int $hour) => sprintf('%02d:00', $hour))
        ->all();
    $gridMinutesFromStart = function (?string $time) use ($gridStartHour) {
        $time = strlen((string) $time) === 5 ? $time . ':00' : (string) $time;
        $hour = (int) substr($time, 0, 2);
        $minute = (int) substr($time, 3, 2);

        return max(0, ($hour * 60 + $minute) - ($gridStartHour * 60));
    };
    $gridRowStartForTime = fn (?string $time) => (int) floor($gridMinutesFromStart($time) / $gridMinuteStep) + 2;
    $gridRowSpanForOccurrence = fn ($occurrence) => max(
        1,
        (int) ceil(max(5, (int) $occurrence['duration_minutes']) / $gridMinuteStep)
    );
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
    $thaiMonthNames = [
        1 => 'มกราคม',
        2 => 'กุมภาพันธ์',
        3 => 'มีนาคม',
        4 => 'เมษายน',
        5 => 'พฤษภาคม',
        6 => 'มิถุนายน',
        7 => 'กรกฎาคม',
        8 => 'สิงหาคม',
        9 => 'กันยายน',
        10 => 'ตุลาคม',
        11 => 'พฤศจิกายน',
        12 => 'ธันวาคม',
    ];
    $academicStartDate = $academicYear?->start_date
        ? \Carbon\CarbonImmutable::parse($academicYear->start_date)->startOfDay()
        : null;
    $academicEndDate = $academicYear?->end_date
        ? \Carbon\CarbonImmutable::parse($academicYear->end_date)->endOfDay()
        : null;
    $calendarPeriodStart = \Carbon\CarbonImmutable::parse($weekStart)->startOfDay();
    $calendarPeriodEnd = \Carbon\CarbonImmutable::parse($weekEnd)->endOfDay();
    $calendarOutsideAcademicYear = $academicStartDate && $academicEndDate
        ? $calendarPeriodEnd->lt($academicStartDate) || $calendarPeriodStart->gt($academicEndDate)
        : false;
    $calendarOutsideNote = $calendarOutsideAcademicYear
        ? 'นอกช่วงปีการศึกษา ' . ($academicYear?->name ?? '-') . ' / เทอม ' . ($academicYear?->semester ?? '-')
        : null;
    $canCreateInCurrentPeriod = $canEdit && ! $calendarOutsideAcademicYear;
    $outsideCreateHint = 'เลือกวันที่ในช่วงปีการศึกษาก่อนเพิ่มรายการสอน';
    $weekNumberFromAcademicYear = $academicStartDate
        ? max(1, (int) floor($academicStartDate->diffInDays(\Carbon\CarbonImmutable::parse($weekStart)->startOfDay(), false) / 7) + 1)
        : null;
    $calendarHeadingText = match ($schedulePeriod ?? 'week') {
        'day' => $formatDate($weekStart),
        'month' => ($thaiMonthNames[(int) $weekStart->month] ?? '') . ' ' . ((int) $weekStart->year + 543),
        default => $calendarOutsideAcademicYear ? 'นอกช่วงปีการศึกษา' : 'สัปดาห์ที่ ' . ($weekNumberFromAcademicYear ?? '-'),
    };
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
            : 'ไม่มีผู้สอน';
    };
    $singleCourseSchedules = ($allSchedules ?? collect());
    $activityFilterOptions = $singleCourseSchedules->pluck('activityType')->filter()->unique('id')->sortBy('name')->values();
    $groupFilterOptions = $singleCourseSchedules->flatMap->studentGroups->unique('id')->sortBy('group_code')->values();
    $instructorFilterOptions = $isWorkspace
        ? $singleCourseSchedules
            ->flatMap(fn ($schedule) => $scheduleDepartmentInstructors($schedule))
            ->unique('id')
            ->sortBy(fn ($instructor) => $instructor->formatted_name ?? $instructor->name)
            ->values()
        : $eligibleScheduleInstructors($courseOffering)->sortBy(fn ($instructor) => $instructor->formatted_name ?? $instructor->name);
    $scheduleFilterItems = $singleCourseSchedules->map(function ($schedule) use ($formatDate, $formatTime, $scheduleDepartmentInstructors) {
        $instructors = $scheduleDepartmentInstructors($schedule);

        return [
            'id' => (string) $schedule->id,
            'activity' => (string) $schedule->activity_type_id,
            'groups' => $schedule->studentGroups->pluck('id')->map(fn ($id) => (string) $id)->values(),
            'instructors' => $instructors->pluck('id')->map(fn ($id) => (string) $id)->values(),
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
                $instructors->map(fn ($instructor) => $instructor->formatted_name ?? $instructor->name)->implode(' '),
            ])->filter()->implode(' '), 'UTF-8'),
        ];
    })->values();

    $groupOccurrencesIntoStacks = function($dayOccurrences) {
        $stacks = [];
        if (!$dayOccurrences || $dayOccurrences->isEmpty()) {
            return $stacks;
        }
        $sortedOccurrences = $dayOccurrences->sortBy(fn($occ) => $occ['schedule']->start_time)->values();

        foreach ($sortedOccurrences as $occ) {
            $inserted = false;
            foreach ($stacks as &$stack) {
                $overlaps = false;
                foreach ($stack as $existing) {
                    $s1 = $occ['schedule']->start_time;
                    $e1 = $occ['schedule']->end_time;
                    $s2 = $existing['schedule']->start_time;
                    $e2 = $existing['schedule']->end_time;
                    if ($s1 < $e2 && $s2 < $e1) {
                        $overlaps = true;
                        break;
                    }
                }
                if ($overlaps) {
                    $stack[] = $occ;
                    $inserted = true;
                    break;
                }
            }
            if (!$inserted) {
                $stacks[] = [$occ];
            }
        }
        return $stacks;
    };
@endphp

<x-app-layout title="ตารางสอน">
    <script>
        (() => {
            const scrollKey = 'tpss-schedule-scroll-y';
            const heightKey = 'tpss-schedule-scroll-height';
            const savedScroll = sessionStorage.getItem(scrollKey);
            const savedHeight = sessionStorage.getItem(heightKey);

            if ('scrollRestoration' in history) {
                history.scrollRestoration = 'manual';
            }

            if (savedScroll !== null) {
                const targetY = Number.parseInt(savedScroll, 10) || 0;
                const minHeight = Number.parseInt(savedHeight || '0', 10);

                if (minHeight > 0) {
                    document.documentElement.style.minHeight = `${minHeight}px`;
                    document.body.style.minHeight = `${minHeight}px`;
                }

                const restoreScroll = () => window.scrollTo(0, targetY);
                restoreScroll();
                requestAnimationFrame(restoreScroll);
                window.addEventListener('DOMContentLoaded', restoreScroll, { once: true });
                window.addEventListener('load', () => {
                    restoreScroll();
                    sessionStorage.removeItem(scrollKey);
                    sessionStorage.removeItem(heightKey);
                    document.documentElement.style.minHeight = '';
                    document.body.style.minHeight = '';
                }, { once: true });
            }
        })();
    </script>
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
            padding: 16px 18px;
            border: 1px solid var(--schedule-border);
            border-radius: 10px;
            background: var(--surface);
            box-shadow: 0 1px 3px oklch(0% 0 0 / 0.05);
            flex-wrap: wrap;
        }
        .schedule-title {
            font-size: 16px;
            font-weight: 900;
            color: var(--fg-1);
            margin-right: auto;
            padding-right: 18px;
        }
        .schedule-toolbar .week-nav {
            display: none !important;
        }
        .schedule-toolbar .grid-date-jump {
            height: 34px;
            padding: 0;
            border: 0;
            background: transparent;
        }
        .schedule-toolbar .grid-date-jump > span {
            display: none;
        }
        .schedule-toolbar .grid-date-jump .sched-datenav-stack {
            height: 34px;
        }
        .schedule-toolbar .grid-date-jump .sched-datenav-picker {
            width: 178px;
            flex-basis: 178px;
        }
        .schedule-toolbar .grid-date-jump .tdi-wrap,
        .schedule-toolbar .grid-date-jump .tdi-input-cal {
            width: 100% !important;
        }
        .schedule-toolbar .grid-date-jump .tdi-input-cal {
            height: 34px;
            border: 1px solid var(--schedule-border-strong);
            border-radius: 8px;
            background: var(--surface);
            box-shadow: none;
        }
        .schedule-toolbar .grid-date-jump .tdi-cal-btn {
            right: 7px;
            top: 50%;
            transform: translateY(-50%);
        }
        .schedule-toolbar .toolbar-actions {
            display: inline-flex;
            align-items: center;
            margin-left: 0;
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
            overflow: visible;
        }
        .sched-datenav-stack {
            display: inline-flex;
            position: relative;
            align-items: center;
            height: 34px;
            padding-bottom: 0;
        }
        .sched-datenav .tdi-wrap {
            width: 158px;
            flex: 0 0 158px;
        }
        .sched-datenav-picker {
            position: relative;
            width: 158px;
            flex: 0 0 158px;
        }
        .sched-datenav-picker .tdi-wrap {
            width: 100%;
            flex: none;
        }
        .sched-datenav-picker .tdi-input-cal {
            color: transparent;
            text-shadow: none;
        }
        .sched-datenav-picker .tdi-input-cal::placeholder {
            color: transparent;
        }
        .sched-datenav-label {
            position: absolute;
            inset: 1px 36px 1px 12px;
            display: flex;
            align-items: center;
            min-width: 0;
            pointer-events: none;
            color: var(--fg);
            font-size: 13px;
            font-weight: 850;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sched-datenav-note {
            position: absolute;
            top: calc(100% + 3px);
            left: 50%;
            transform: translateX(-50%);
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 15px;
            padding: 0 6px;
            border: 1px solid var(--status-warning-border);
            border-radius: 999px;
            background: var(--status-warning-bg);
            color: var(--status-warning-fg);
            font-size: 9px;
            font-weight: 700;
            line-height: 1;
            white-space: nowrap;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .schedule-caption-line {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 4px;
        }
        .schedule-caption-warning {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            min-height: 22px;
            padding: 2px 8px;
            border: 1px solid var(--status-warning-border);
            border-radius: 999px;
            background: var(--status-warning-bg);
            color: var(--status-warning-fg);
            font-size: 10.5px;
            font-weight: 800;
            line-height: 1.2;
            white-space: nowrap;
        }
        .schedule-card-hdr {
            align-items: center;
        }
        .schedule-card-hdr > div:first-child {
            flex: 1 1 0;
            min-width: 0;
        }
        .schedule-card-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            row-gap: 8px;
            flex-wrap: wrap;
            flex: 0 0 max-content;
            max-width: 100%;
            margin-left: auto;
        }
        @media (max-width: 1040px) {
            .schedule-card-actions {
                flex-basis: min-content;
            }
        }
        .schedule-card-actions > [x-cloak] {
            display: inline-flex !important;
            visibility: hidden;
        }
        @media (max-width: 640px) {
            .schedule-card-hdr {
                align-items: stretch;
                gap: 12px !important;
                padding: 14px 12px 16px;
            }
            .schedule-card-hdr > div:first-child {
                width: 100%;
                text-align: left;
            }
            .schedule-card-hdr .card-ttl {
                font-size: 15px;
                line-height: 1.3;
            }
            .schedule-caption-line {
                align-items: flex-start;
                gap: 6px;
            }
            .schedule-caption-warning {
                max-width: 100%;
                white-space: normal;
                text-align: left;
            }
            .schedule-card-actions {
                display: grid;
                grid-template-columns: minmax(0, 1fr);
                justify-items: center;
                width: 100%;
                gap: 8px;
                margin-left: 0;
                flex: 1 1 100%;
            }
            .schedule-card-actions .sched-datenav {
                display: grid;
                grid-template-columns: 36px minmax(0, 1fr) 36px;
                align-items: center;
                gap: 6px;
                width: min(100%, 236px);
            }
            .schedule-card-actions .sched-datenav-arrow {
                width: 36px;
                height: 36px;
            }
            .schedule-card-actions .sched-datenav-stack,
            .schedule-card-actions .sched-datenav-picker,
            .schedule-card-actions .sched-datenav .tdi-wrap {
                width: 100%;
                min-width: 0;
                flex: 1 1 auto;
            }
            .schedule-card-actions .sched-datenav-input {
                height: 36px;
                font-size: 12.5px;
                text-align: center;
                padding-left: 8px;
                padding-right: 34px;
            }
            .schedule-card-actions .period-toggle {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                width: min(100%, 168px);
            }
            .schedule-card-actions .schedule-toggle {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                width: min(100%, 176px);
            }
            .schedule-card-actions .period-toggle a,
            .schedule-card-actions .schedule-toggle button {
                min-height: 34px;
                padding: 6px 8px;
                font-size: 11.5px;
            }
            .schedule-card-actions .weekend-toggle {
                width: min(100%, 168px);
                min-height: 34px;
            }
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
            width: 100% !important;
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
        .weekend-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 32px;
            padding: 5px 10px;
            border: 1px solid var(--schedule-border);
            border-radius: 8px;
            background: var(--surface);
            color: var(--schedule-muted);
            font: inherit;
            font-size: 11.5px;
            font-weight: 850;
            cursor: pointer;
            white-space: nowrap;
        }
        .weekend-toggle.is-active {
            border-color: oklch(77% 0.07 210);
            background: oklch(94.5% 0.03 220);
            color: var(--brand-navy);
        }
        .toolbar-actions {
            margin-left: auto;
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        .schedule-shell .btn:disabled,
        .schedule-shell .btn.is-disabled,
        .day-add-link:disabled {
            cursor: not-allowed;
            opacity: .52;
            box-shadow: none;
        }
        .schedule-shell .btn:disabled:hover,
        .schedule-shell .btn.is-disabled:hover,
        .day-add-link:disabled:hover {
            transform: none;
        }
        .floating-create-button {
            position: fixed;
            right: 28px;
            bottom: 24px;
            z-index: 55;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 46px;
            padding: 10px 16px;
            border: 1px solid var(--brand-navy);
            border-radius: 999px;
            background: var(--brand-navy);
            color: oklch(98% 0.004 240);
            box-shadow: 0 10px 24px oklch(0% 0 0 / 0.18);
            font: inherit;
            font-size: 14px;
            font-weight: 900;
            cursor: pointer;
            transition: transform .18s ease, box-shadow .18s ease, opacity .18s ease, padding .18s ease, width .18s ease, height .18s ease;
            will-change: transform, opacity;
        }
        .floating-create-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 28px oklch(0% 0 0 / 0.22);
        }
        .floating-create-button:focus-visible {
            outline: 3px solid color-mix(in oklch, var(--brand-navy) 28%, transparent);
            outline-offset: 3px;
        }
        /* Compact state while scrolling: small circular button, faded */
        .floating-create-button.compact {
            padding: 8px;
            min-height: 40px;
            width: 40px;
            border-radius: 999px;
            gap: 0;
            opacity: .66;
            transform: translateY(0) scale(.98);
            box-shadow: 0 6px 12px oklch(0% 0 0 / 0.12);
        }
        .floating-create-button.compact span:not(.floating-create-icon) {
            display: none;
        }
        .floating-create-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            border-radius: 999px;
            background: oklch(98% 0.004 240 / 0.16);
            font-size: 18px;
            line-height: 1;
            transition: background .18s ease, width .18s ease, height .18s ease;
        }
        @media (max-width: 760px) {
            .floating-create-button {
                right: 16px;
                bottom: 16px;
                min-height: 44px;
                padding: 9px 14px;
                font-size: 13px;
            }
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
            max-width: 100%;
            -webkit-overflow-scrolling: touch;
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
            background: oklch(93.5% 0.022 232);
            color: oklch(35% 0.035 232);
            text-align: left;
            font-size: 12px;
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
            padding: 10px 14px;
            vertical-align: middle;
            font-size: 12.5px;
        }
        .sched-row:nth-child(even) > td {
            background: oklch(98.5% 0.006 232);
        }
        .sched-row > td:first-child {
            background: var(--surface);
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
            padding: 6px 10px;
            border-radius: 8px;
            background: oklch(96.5% 0.014 232);
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
            overflow-x: auto;
            overflow-y: visible;
            background: var(--surface);
            box-shadow: 0 1px 4px oklch(0% 0 0 / 0.05);
        }
        .schedule-grid.is-precise {
            grid-auto-rows: var(--grid-minute-row-height, 8px);
        }
        .grid-cell {
            min-height: 70px;
            border-right: 1px solid var(--schedule-border);
            border-bottom: 1px solid var(--schedule-border);
            padding: 7px;
            background: oklch(98.6% 0.004 232);
        }
        .schedule-grid.is-precise .grid-cell {
            min-height: 0;
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
        .schedule-grid.is-precise .grid-cell-activity {
            padding: 4px 7px;
            min-height: 0;
            overflow: visible;
            z-index: 3;
            border-bottom: 0;
            background: transparent;
            pointer-events: none;
        }
        .grid-cell-activity .grid-activity {
            margin-bottom: 0;
            flex: 1 1 auto;
        }
        .schedule-grid.is-precise .grid-cell-activity .grid-activity {
            height: 100%;
            min-height: 100%;
            padding: 8px 9px;
            gap: 5px;
            overflow: visible;
            pointer-events: auto;
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
            display: flex;
            flex-direction: column;
            gap: 5px;
            min-width: 0;
        }
        .grid-activity.is-compact {
            gap: 3px;
            padding: 6px 7px;
        }
        .grid-activity.is-tall {
            padding: 10px 10px 9px;
            gap: 7px;
        }
        .schedule-grid.is-precise .grid-activity.is-tall .grid-activity-foot {
            margin-top: auto;
        }
        .grid-activity strong,
        .grid-activity-title {
            display: block;
            color: var(--fg-1);
            font-size: 12px;
            line-height: 1.35;
            font-weight: 850;
        }
        .schedule-grid.is-precise .grid-activity-title {
            line-height: 1.25;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .schedule-grid.is-precise .grid-activity.is-tall .grid-activity-title {
            font-size: 12.2px;
            line-height: 1.34;
            -webkit-line-clamp: 3;
        }
        .schedule-grid.is-precise .grid-activity.is-compact .grid-activity-title {
            font-size: 11px;
            line-height: 1.22;
            -webkit-line-clamp: 1;
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
            display: inline-grid;
            place-items: center;
            justify-content: center;
            box-sizing: border-box;
            flex: 0 0 96px;
            width: 96px;
            max-width: 96px;
            min-height: 18px;
            padding: 1px 6px;
            font-size: 9.5px;
            line-height: 1.25;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            text-align: center;
        }
        .grid-activity.is-compact .activity-tag {
            flex-basis: 96px;
            width: 96px;
            max-width: 96px;
            min-height: 17px;
            padding: 1px 5px;
            font-size: 9px;
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
        .schedule-grid.is-precise .grid-activity-time {
            font-size: 10.5px;
            line-height: 1.2;
        }
        .grid-activity.is-tall .grid-activity-time {
            margin-top: 2px;
        }
        .grid-activity.is-compact .grid-activity-time {
            display: none;
        }
        .grid-activity.is-compact .grid-activity-sub,
        .grid-activity.is-compact .grid-activity-meta,
        .grid-activity.is-compact .grid-groups,
        .grid-activity.is-compact .grid-location-name,
        .grid-activity.is-compact .grid-location-building,
        .grid-activity.is-compact .grid-instructor,
        .grid-activity.is-compact .co-group-badge,
        .grid-activity.is-compact .group-chip,
        .grid-activity.is-compact .badge {
            display: none;
        }
        .schedule-conflict-pill {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            min-height: 20px;
            padding: 2px 7px;
            border: 1px solid var(--status-conflict-border);
            border-radius: 999px;
            background: var(--status-conflict-bg);
            color: var(--status-conflict-fg);
            font-size: 10px;
            font-weight: 850;
            line-height: 1.2;
            white-space: nowrap;
            cursor: help;
        }
        .schedule-conflict-pill > svg {
            width: 12px;
            height: 12px;
            flex: 0 0 auto;
        }

        /* ── Styled hover tooltip (replaces native title attr) ──────────
           position: fixed → escape ทุก stacking context และ overflow:hidden ของ card
           ตำแหน่งจะถูก set ผ่าน JS (transform: translate) ตอน hover/focus    */
        .conflict-tt {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 99999;
            display: none;
            min-width: 280px;
            max-width: 380px;
            padding: 0;
            border: 1px solid color-mix(in oklch, var(--status-conflict-border) 50%, oklch(85% 0.01 240));
            border-radius: 10px;
            background: var(--surface, white);
            box-shadow: 0 8px 24px oklch(0% 0 0 / 0.16), 0 2px 6px oklch(0% 0 0 / 0.08);
            white-space: normal;
            cursor: default;
            pointer-events: none;
            transform: translate(0, 0);
        }
        .schedule-conflict-pill[data-tt-open="true"] .conflict-tt {
            display: block;
        }
        .conflict-tt-head {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 10px 12px;
            border-bottom: 1px solid oklch(92% 0.01 240);
            background: color-mix(in oklch, var(--status-conflict-bg) 55%, white);
            color: var(--status-conflict-fg);
            font-size: 11.5px;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }
        .conflict-tt-head svg { flex-shrink: 0; }
        .conflict-tt-head strong { font-weight: 950; letter-spacing: 0; }
        .conflict-tt-body {
            display: block;
            padding: 10px 12px;
            max-height: 320px;
            overflow-y: auto;
        }
        .conflict-tt-group {
            display: block;
            padding-bottom: 8px;
            margin-bottom: 8px;
            border-bottom: 1px dashed oklch(92% 0.01 240);
        }
        .conflict-tt-group:last-child {
            border-bottom: 0;
            padding-bottom: 0;
            margin-bottom: 0;
        }
        .conflict-tt-target {
            display: block;
            font-size: 11.5px;
            line-height: 1.4;
            color: oklch(28% 0.04 240);
        }
        .conflict-tt-target-prefix {
            color: oklch(50% 0.02 240);
            font-weight: 700;
            margin-right: 4px;
        }
        .conflict-tt-target strong {
            color: var(--brand-navy, oklch(28% 0.08 245));
            font-weight: 950;
        }
        .conflict-tt-reasons {
            display: grid;
            gap: 4px;
            margin-top: 6px;
        }
        .conflict-tt-reason {
            display: grid;
            grid-template-columns: 14px auto 1fr;
            align-items: baseline;
            gap: 6px;
            font-size: 11.5px;
            line-height: 1.4;
        }
        .conflict-tt-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transform: translateY(1px);
        }
        .conflict-tt-reason--instructor .conflict-tt-icon { color: oklch(48% 0.14 268); }
        .conflict-tt-reason--room .conflict-tt-icon       { color: oklch(52% 0.16 28); }
        .conflict-tt-reason--group .conflict-tt-icon      { color: oklch(48% 0.14 168); }
        .conflict-tt-reason-label {
            color: oklch(45% 0.02 240);
            font-weight: 800;
            white-space: nowrap;
        }
        .conflict-tt-reason-value {
            color: oklch(20% 0.02 240);
            font-weight: 700;
            min-width: 0;
            word-break: break-word;
        }
        .conflict-tt-reason-value strong { font-weight: 900; }
        .conflict-tt-reason-value--muted {
            color: oklch(55% 0.02 240);
            font-style: italic;
            font-weight: 700;
        }
        .schedule-conflict-focus {
            border-color: color-mix(in oklch, var(--status-conflict) 58%, var(--schedule-border-strong)) !important;
            background: color-mix(in oklch, var(--status-conflict-bg) 58%, var(--surface)) !important;
            box-shadow: 0 0 0 3px color-mix(in oklch, var(--status-conflict) 14%, transparent), 0 12px 30px oklch(0% 0 0 / 0.12) !important;
        }
        .schedule-conflict-focus .schedule-conflict-pill {
            box-shadow: 0 0 0 2px color-mix(in oklch, var(--status-conflict) 12%, transparent);
        }
        .sched-row.schedule-conflict-focus td,
        .co-sched-row.schedule-conflict-focus td {
            background: color-mix(in oklch, var(--status-conflict-bg) 62%, var(--surface)) !important;
            box-shadow: inset 0 1px 0 var(--status-conflict-border), inset 0 -1px 0 var(--status-conflict-border);
        }
        .sched-row.schedule-conflict-focus td:first-child,
        .co-sched-row.schedule-conflict-focus td:first-child {
            box-shadow: inset 3px 0 0 var(--status-conflict), inset 0 1px 0 var(--status-conflict-border), inset 0 -1px 0 var(--status-conflict-border);
        }
        .modal-conflict-field {
            margin-top: 6px;
            color: var(--status-conflict-fg);
            font-size: 11px;
            font-weight: 800;
            line-height: 1.45;
        }
        .modal-field-has-conflict .modal-control,
        .modal-field-has-conflict .time-picker {
            border-color: var(--status-conflict-border) !important;
            box-shadow: 0 0 0 3px color-mix(in oklch, var(--status-conflict) 10%, transparent) !important;
        }
        .modal-section.modal-field-has-conflict .modal-choice:has(input:checked) {
            border-color: var(--status-conflict-border);
            box-shadow: 0 0 0 2px color-mix(in oklch, var(--status-conflict) 8%, transparent);
        }
        .grid-activity-foot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            min-width: 0;
            margin-top: auto;
            padding-top: 4px;
        }
        .grid-activity-foot:has(.grid-activity-groups) {
            justify-content: flex-end;
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
            position: relative;
            flex-shrink: 0;
            margin-left: auto;
            min-height: 19px;
            padding: 1px 7px;
            border-radius: 999px;
            background: oklch(96.5% 0.014 232);
            color: var(--fg-2);
            border: 1px solid color-mix(in oklch, var(--brand-navy) 18%, var(--schedule-border));
            font-size: 10px;
            font-weight: 700;
            line-height: 1.45;
        }
        .grid-activity-groups.has-tooltip {
            cursor: help;
        }
        .grid-activity.is-compact .grid-activity-foot {
            display: flex;
            padding-top: 2px;
        }
        .grid-activity-card.is-stacked-card {
            padding: 9px 10px !important;
            gap: 5px;
            overflow: hidden;
        }
        .grid-activity-card.is-stacked-card .grid-activity-top {
            min-height: 0;
        }
        .grid-activity-card.is-stacked-card .grid-activity-title {
            font-size: 12px;
            line-height: 1.42;
            margin-top: 0;
            word-break: normal;
            overflow-wrap: normal;
        }
        .grid-activity-card.is-stacked-card .grid-activity-time {
            font-size: 10.8px;
            line-height: 1.25;
        }
        .grid-activity-card.is-stacked-card .grid-activity-foot {
            min-height: 22px;
            align-items: flex-end;
            margin-top: auto;
            padding-top: 2px;
        }
        .grid-activity-card.is-stacked-card.has-no-visible-stack-switcher {
            padding-bottom: 9px !important;
        }
        .grid-activity-card.is-stacked-card.has-no-visible-stack-switcher {
            justify-content: flex-start;
        }
        .grid-activity-card.is-stacked-card.has-no-visible-stack-switcher .grid-activity-foot {
            margin-top: auto;
            padding-top: 8px;
        }
        .grid-activity-card.is-stacked-card:not(.is-compact).has-no-visible-stack-switcher {
            padding-bottom: 40px !important;
        }
        .grid-activity-card.is-stacked-card:not(.is-compact).has-no-visible-stack-switcher .grid-activity-foot {
            position: absolute;
            left: 10px;
            right: 10px;
            bottom: 10px;
            padding-top: 0;
        }
        .grid-activity-card.is-stacked-card.has-visible-stack-switcher .grid-activity-foot {
            position: absolute;
            left: 10px;
            right: 10px;
            bottom: 10px;
            margin-top: 0;
            padding-top: 0;
        }
        .grid-activity-card.is-stacked-card.has-visible-stack-switcher {
            gap: 4px;
            padding-bottom: 36px !important;
        }
        .grid-activity-card.is-stacked-card.has-visible-stack-switcher .grid-activity-top {
            min-height: 0;
        }
        .grid-activity-card.is-stacked-card.has-visible-stack-switcher .grid-activity-title {
            line-height: 1.42;
            -webkit-line-clamp: 1;
        }
        .grid-activity-card.is-stacked-card.has-visible-stack-switcher .grid-activity-time {
            font-size: 10.5px;
            line-height: 1.2;
        }
        .grid-activity-card.is-stacked-card.is-stack-back {
            gap: 4px;
            overflow: hidden;
        }
        .grid-activity-card.is-stacked-card.is-stack-back .grid-activity-top {
            min-height: 0;
        }
        .grid-activity-card.is-stacked-card.is-stack-back .grid-activity-title {
            font-size: 11.2px;
            line-height: 1.36;
            -webkit-line-clamp: 1;
        }
        .grid-activity-card.is-stacked-card.is-stack-back .grid-activity-sub,
        .grid-activity-card.is-stacked-card.is-stack-back .grid-activity-meta,
        .grid-activity-card.is-stacked-card.is-stack-back .grid-groups,
        .grid-activity-card.is-stacked-card.is-stack-back .grid-location-name,
        .grid-activity-card.is-stacked-card.is-stack-back .grid-location-building,
        .grid-activity-card.is-stacked-card.is-stack-back .grid-instructor,
        .grid-activity-card.is-stacked-card.is-stack-back .badge {
            display: none;
        }
        .grid-activity-card.is-stacked-card.is-stack-back .grid-activity-time {
            font-size: 10px;
            line-height: 1.2;
        }
        .grid-activity-card.is-stacked-card.is-compact .grid-activity-time {
            display: block;
            font-size: 10.2px;
            line-height: 1.18;
        }
        .grid-activity-card.is-stacked-card.is-compact.has-visible-stack-switcher .grid-activity-time {
            max-width: calc(100% - 70px);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .grid-activity-card.is-stacked-card.is-compact.has-visible-stack-switcher .grid-activity-foot {
            left: auto;
            max-width: 64px;
        }
        .schedule-grid.is-precise .grid-activity-card.is-stacked-card.is-compact .grid-activity-title {
            display: block;
            min-height: 17px;
            font-size: 11px;
            line-height: 1.5;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            -webkit-line-clamp: initial;
            -webkit-box-orient: initial;
        }
        .grid-activity-card.is-stacked-card.is-compact .grid-activity-top {
            min-height: 21px;
            flex: 0 0 auto;
        }
        .grid-activity-card.is-stacked-card.is-compact .schedule-conflict-pill {
            min-height: 19px;
            padding: 1px 7px;
            line-height: 1.25;
        }
        .grid-activity-card.is-stacked-card.is-stack-back.is-compact .grid-activity-title {
            margin-top: 1px;
        }
        .grid-activity-card.is-stacked-card.is-compact .grid-activity-room {
            display: none;
        }
        .grid-activity-card.is-stacked-card.is-stack-back.is-compact .grid-activity-time {
            display: none;
        }
        .grid-activity-card.is-stacked-card.is-stack-back .grid-activity-foot {
            justify-content: flex-end;
        }
        .grid-activity-card.is-stacked-card.is-stack-front .grid-activity-room {
            max-width: calc(100% - 64px);
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
        [data-testid="schedule-grid-view"] .grid-activity {
            gap: 4px;
            padding: 8px;
        }
        [data-testid="schedule-grid-view"] .grid-activity-sub {
            display: none;
        }
        [data-testid="schedule-grid-view"] .grid-activity-meta {
            gap: 1px;
        }
        [data-testid="schedule-grid-view"] .grid-instructor,
        [data-testid="schedule-grid-view"] .grid-location-building,
        [data-testid="schedule-grid-view"] .grid-groups {
            display: none;
        }
        [data-testid="schedule-grid-view"] .grid-activity > div:last-child {
            display: none;
        }
        [data-testid="schedule-grid-view"] .grid-location-name {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        [data-testid="schedule-grid-view"] .grid-course {
            background: oklch(94.5% 0.028 245);
            border-color: color-mix(in oklch, var(--brand-navy) 32%, var(--schedule-border));
            font-size: 9.5px;
        }
        @media (max-width: 640px) {
            [data-testid="schedule-grid-view"],
            [data-testid="schedule-grid-view-co"] {
                max-width: 100%;
                overflow-x: auto;
                overflow-y: visible;
                -webkit-overflow-scrolling: touch;
            }
            [data-testid="schedule-grid-view-co"] {
                padding: 10px !important;
            }
            .schedule-grid.is-precise {
                width: max-content;
                min-width: calc(48px + (var(--grid-day-count) * 118px));
                max-width: none;
                overflow: visible;
            }
            .schedule-grid.is-precise .grid-cell {
                padding: 4px 5px;
            }
            .schedule-grid.is-precise .grid-head {
                min-height: 40px;
                font-size: 10px;
                line-height: 1.2;
            }
            .schedule-grid.is-precise .grid-head .caption {
                font-size: 9px;
                line-height: 1.15;
            }
            .schedule-grid.is-precise .grid-time {
                font-size: 10px;
                padding-right: 4px;
            }
            .schedule-grid.is-precise .grid-cell-activity {
                padding: 3px;
            }
            .schedule-grid.is-precise .grid-cell-activity .grid-activity {
                padding: 6px 6px;
                border-left-width: 2px;
            }
            .schedule-grid.is-precise .grid-activity-card.is-stacked-card {
                padding: 6px 6px !important;
            }
            .schedule-grid.is-precise .grid-activity-title,
            .schedule-grid.is-precise .grid-activity-card.is-stacked-card .grid-activity-title {
                font-size: 10.5px;
                line-height: 1.24;
            }
            .schedule-grid.is-precise .grid-activity-time,
            .schedule-grid.is-precise .grid-activity-card.is-stacked-card .grid-activity-time {
                display: block;
                font-size: 9.5px;
                line-height: 1.2;
            }
            .schedule-grid.is-precise .schedule-conflict-pill {
                min-height: 17px;
                padding: 1px 5px;
                font-size: 8.5px;
            }
            .schedule-grid.is-precise .grid-activity-foot {
                min-height: 18px;
            }
            .schedule-grid.is-precise .grid-activity-groups {
                min-height: 17px;
                padding: 1px 5px;
                font-size: 8.5px;
            }
            .schedule-grid.is-precise .stack-indicator {
                right: 4px;
                bottom: 4px;
                max-width: calc(100% - 8px);
                padding: 3px 5px;
                font-size: 8.5px;
                gap: 3px;
            }
            .schedule-grid.is-precise .stack-sync-icon {
                width: 10px;
                height: 10px;
            }
        }
        .month-calendar {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            border: 1px solid var(--schedule-border-strong);
            border-radius: 10px;
            overflow: visible;
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
            display: flex;
            flex-direction: column;
            min-width: 0;
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
            gap: 4px;
            min-height: 0;
            overflow: visible;
            padding-right: 2px;
        }
        .month-activity {
            width: 100%;
            min-width: 0;
            border: 1px solid color-mix(in oklch, var(--activity-color) 26%, var(--schedule-border));
            border-left: 3px solid var(--activity-color);
            border-radius: 7px;
            background: var(--surface);
            padding: 6px 7px;
            cursor: pointer;
            text-align: left;
            font: inherit;
            color: var(--fg-2);
            box-shadow: 0 1px 3px oklch(0% 0 0 / 0.07);
        }
        .month-activity:focus-visible {
            outline: 2px solid var(--brand-navy);
            outline-offset: 2px;
        }
        .month-activity-time {
            color: var(--fg-1);
            font-size: 10.5px;
            font-weight: 900;
            font-variant-numeric: tabular-nums;
            line-height: 1.2;
        }
        .month-activity-title {
            margin-top: 2px;
            color: var(--fg-1);
            font-size: 10.6px;
            font-weight: 850;
            line-height: 1.25;
        }
        .month-activity-meta {
            margin-top: 1px;
            color: var(--schedule-muted);
            font-size: 9.5px;
            line-height: 1.25;
        }
        .month-activity-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 3px;
            margin-top: 3px;
        }
        .month-group-summary {
            display: inline-flex;
            align-items: center;
            min-height: 17px;
            padding: 1px 6px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 18%, var(--schedule-border));
            border-radius: 999px;
            color: var(--fg-2);
            background: oklch(96.5% 0.014 232);
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
        [data-testid="schedule-month-calendar"] .month-calendar-day {
            min-height: 132px;
        }
        [data-testid="schedule-month-calendar"] .month-day-items,
        [data-testid="schedule-month-calendar-co"] .month-day-items {
            overflow: visible;
        }
        [data-testid="schedule-month-calendar"] .month-empty {
            display: none;
        }
        [data-testid="schedule-month-calendar"] .month-activity {
            padding: 6px 7px;
        }
        [data-testid="schedule-month-calendar"] .month-activity-meta,
        [data-testid="schedule-month-calendar"] .month-activity .activity-tag,
        [data-testid="schedule-month-calendar"] .month-activity .month-group-summary,
        [data-testid="schedule-month-calendar-co"] .month-activity .activity-tag,
        [data-testid="schedule-month-calendar-co"] .month-activity .month-group-summary {
            display: none;
        }
        [data-testid="schedule-month-calendar"] .month-activity-title {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 10.5px;
        }
        [data-testid="schedule-month-calendar-co"] .month-activity-title {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        [data-testid="schedule-month-calendar"] .month-activity .badge {
            display: none;
        }
        [data-testid="schedule-month-calendar"] .month-activity:hover,
        [data-testid="schedule-month-calendar"] .month-activity:focus-visible,
        [data-testid="schedule-month-calendar-co"] .month-activity:hover,
        [data-testid="schedule-month-calendar-co"] .month-activity:focus-visible {
            border-color: color-mix(in oklch, var(--activity-color) 44%, var(--schedule-border-strong));
            background: color-mix(in oklch, var(--activity-color) 5%, var(--surface));
            box-shadow: 0 3px 10px oklch(0% 0 0 / 0.08);
            outline: none;
            position: relative;
            z-index: 4;
        }
        [data-testid="schedule-month-calendar"] .month-activity:hover .month-activity-title,
        [data-testid="schedule-month-calendar"] .month-activity:focus-visible .month-activity-title,
        [data-testid="schedule-month-calendar-co"] .month-activity:hover .month-activity-title,
        [data-testid="schedule-month-calendar-co"] .month-activity:focus-visible .month-activity-title {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            white-space: normal;
        }
        [data-testid="schedule-month-calendar"] .month-activity:hover .month-activity-meta,
        [data-testid="schedule-month-calendar"] .month-activity:focus-visible .month-activity-meta {
            display: block;
        }
        [data-testid="schedule-month-calendar"] .month-activity:hover .activity-tag,
        [data-testid="schedule-month-calendar"] .month-activity:focus-visible .activity-tag,
        [data-testid="schedule-month-calendar"] .month-activity:hover .month-group-summary,
        [data-testid="schedule-month-calendar"] .month-activity:focus-visible .month-group-summary,
        [data-testid="schedule-month-calendar"] .month-activity:hover .badge,
        [data-testid="schedule-month-calendar"] .month-activity:focus-visible .badge,
        [data-testid="schedule-month-calendar-co"] .month-activity:hover .activity-tag,
        [data-testid="schedule-month-calendar-co"] .month-activity:focus-visible .activity-tag,
        [data-testid="schedule-month-calendar-co"] .month-activity:hover .month-group-summary,
        [data-testid="schedule-month-calendar-co"] .month-activity:focus-visible .month-group-summary {
            display: inline-flex;
        }
        @media (max-width: 640px) {
            .month-calendar {
                min-width: 720px;
            }
            .sched-list {
                min-width: 640px;
            }
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
        .modal-inline-error {
            margin-top: 5px;
            color: var(--status-conflict-fg);
            font-size: 12px;
            font-weight: 800;
            line-height: 1.45;
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
            color: var(--fg-1);
        }
        /* ──────────────────────────────────────────────────────────
           Offerings Dropdown Panel (workspace top section)
           ────────────────────────────────────────────────────────── */
        .offerings-dropdown-panel {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 8px;
            background: linear-gradient(180deg, oklch(98% 0.01 228), oklch(96% 0.015 228));
            padding: 12px 18px;
            border: 1px solid var(--schedule-border);
            border-radius: 10px;
            box-shadow: 0 1px 3px oklch(0% 0 0 / 0.04);
            margin-bottom: 20px;
        }
        .offerings-panel-meta {
            display: flex;
            align-items: baseline;
            gap: 8px;
            flex-wrap: wrap;
            min-height: 24px;
        }
        .offering-selector-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
            width: 100%;
            min-width: 0;
        }
        .offering-selector-label {
            display: inline-flex;
            align-items: baseline;
            font-size: 13.5px;
            line-height: 1.25;
            font-weight: 800;
            color: var(--fg-2);
            white-space: nowrap;
            flex-shrink: 0;
        }
        .offering-summary-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            min-height: 22px;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11px;
            line-height: 1.25;
            font-weight: 850;
            white-space: nowrap;
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
            width: 100%;
            max-width: 100%;
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
            .offerings-dropdown-panel {
                padding: 10px 12px;
            }
            .offerings-panel-meta {
                align-items: flex-start;
                gap: 6px;
            }
            .offering-selector-wrapper {
                flex-direction: column;
                align-items: stretch;
                gap: 6px;
            }
            .offering-selector-label {
                white-space: normal;
            }
            .offering-select-control {
                min-width: 0;
                width: 100%;
                font-size: 12.5px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .course-overview {
                grid-template-columns: 1fr;
            }
            .course-overview-actions {
                justify-content: flex-start;
                width: 100%;
            }
            .course-overview-actions .btn {
                flex: 1 1 150px;
                justify-content: center;
            }
            .course-overview-name {
                font-size: 18px;
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
        .time-picker-group {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .time-unit {
            font-size: 14.5px;
            font-weight: 700;
            color: var(--fg-2);
            user-select: none;
        }
        .time-picker {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--schedule-border);
            border-radius: 8px;
            background: var(--surface);
            height: 38px;
            padding: 0 12px;
            box-sizing: border-box;
            width: 150px;
            gap: 6px;
            cursor: pointer;
            user-select: none;
            -webkit-user-select: none;
            transition: border-color 0.15s, box-shadow 0.15s;
            position: relative;
        }
        .time-picker:focus,
        .time-picker.tp-active {
            outline: none;
            border-color: var(--brand-navy);
            box-shadow: 0 0 0 3px oklch(45% 0.12 250 / 0.12);
        }
        .time-picker:hover {
            border-color: var(--schedule-border-strong);
            background: color-mix(in oklch, var(--brand-navy) 3%, transparent);
        }
        .tp-val {
            font-size: 14px;
            font-weight: 600;
            color: var(--fg-1);
            letter-spacing: 0.04em;
        }
        .tp-drop {
            position: fixed;
            background: var(--surface, #fff);
            border: 1px solid var(--schedule-border, #e2e8f0);
            border-radius: 8px;
            box-shadow: 0 6px 24px rgba(0,0,0,0.13);
            z-index: 10000;
            display: none;
            overflow: hidden;
        }
        .tp-drop.tp-open {
            display: block;
        }
        .tp-drop-columns {
            display: flex;
            height: 200px;
            width: 100%;
            background: var(--surface);
        }
        .tp-col {
            flex: 1;
            overflow-y: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
            padding: 4px 0;
            display: flex;
            flex-direction: column;
        }
        .tp-col::-webkit-scrollbar {
            display: none;
        }
        .tp-col-divider {
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            color: var(--fg-3);
            user-select: none;
            width: 10px;
        }
        .tp-col ul {
            list-style: none;
            padding: 0;
            margin: 0;
            width: 100%;
        }
        .tp-col li {
            padding: 6px 0;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            text-align: center;
            color: var(--fg-1, #1e293b);
            transition: background 0.08s, color 0.08s;
            border-radius: 4px;
            margin: 2px 4px;
            white-space: nowrap;
        }
        .tp-col li:hover {
            background: color-mix(in oklch, var(--brand-navy, #1e3a5f) 8%, transparent);
        }
        .tp-col li.tp-sel {
            background: var(--brand-navy, #1e3a5f);
            color: #fff;
            font-weight: 700;
        }
        .time-separator {
            font-weight: 800;
            color: var(--fg-3);
            padding: 0 1px;
            user-select: none;
            flex-shrink: 0;
        }
        /* Custom overlapping card stacks styling */
        .activity-stack {
            position: relative;
            width: 100%;
            height: 100%;
            min-height: 80px;
            overflow: hidden;
        }
        .grid-activity-card {
            position: absolute !important;
            min-height: 0 !important;
            margin-bottom: 0 !important;
            transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.25s ease, opacity 0.25s ease, left 0.25s cubic-bezier(0.4, 0, 0.2, 1), width 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            z-index: 10;
        }
        .grid-activity-card.has-visible-stack-switcher {
            padding-bottom: 34px !important;
        }
        .grid-activity-card:hover {
            z-index: 50 !important;
            transform: translateY(-1px);
            box-shadow: 0 10px 24px rgba(0,0,0,0.16);
            opacity: 1 !important;
        }
        /* Ghost/peek cards — cards outside the current page shown as faint background hints */
        .grid-activity-card.is-ghost-peek {
            pointer-events: none;
            z-index: 5 !important;
            filter: saturate(0.3);
        }
        /* ซ่อนเนื้อหาข้างในการ์ด ghost — ให้เห็นแค่กรอบสี */
        .grid-activity-card.is-ghost-peek > * {
            visibility: hidden !important;
        }
        .grid-activity-card.is-ghost-peek:hover {
            transform: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .grid-activity-card.is-ghost-prev {
            /* Ghost card peeking from the TOP-LEFT */
            transform-origin: top left;
        }
        .grid-activity-card.is-ghost-next {
            /* Ghost card peeking from the BOTTOM-RIGHT */
            transform-origin: bottom right;
        }
        .stack-indicator {
            position: absolute;
            right: 8px;
            bottom: 7px;
            width: fit-content;
            max-width: 100%;
            z-index: 2;
            background: var(--brand-navy);
            color: #fff;
            border-radius: 999px;
            padding: 3px 7px;
            font-size: 10px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            box-shadow: 0 3px 8px rgba(0,0,0,0.22);
            user-select: none;
            pointer-events: auto;
            line-height: 1;
        }
        .stack-switcher-zone {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 34px;
            z-index: 1;
            pointer-events: auto;
        }
        .stack-switcher-top-zone {
            position: absolute;
            left: 0;
            right: 0;
            top: 0;
            bottom: 34px;
            z-index: 1;
            pointer-events: auto;
        }
        .grid-activity-card.has-visible-stack-switcher .stack-switcher-top-zone:hover ~ .stack-indicator.is-stack-switcher {
            opacity: 0;
            pointer-events: none;
        }
        .grid-activity-card.has-visible-stack-switcher .stack-switcher-zone:hover ~ .stack-indicator.is-stack-switcher {
            opacity: 1;
            pointer-events: auto;
        }
        .stack-indicator:hover {
            background: color-mix(in oklch, var(--brand-navy) 85%, #000);
            transform: translateY(-1px);
        }
        .stack-indicator.is-stack-count {
            display: none;
        }
        .stack-sync-icon {
            width: 12px;
            height: 12px;
            animation: spin-slow 8s linear infinite;
            flex-shrink: 0;
        }
        @keyframes spin-slow {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>

    <div
        class="schedule-shell"
        x-data="{
            view: @js($focusedScheduleId ? 'grid' : null) || sessionStorage.getItem('tpss-schedule-view') || 'list',
            detailModal: null,
            editModal: @js($openEditScheduleId ? 'schedule-' . $openEditScheduleId : null),
            showCreate: @js($openCreateModal),
            focusedScheduleId: @js($focusedScheduleId),
            initialSelectedOfferingId: @js($selectedOfferingId),
            selectedOfferingId: @js($selectedOfferingId),
            scheduleItems: @js($scheduleFilterItems),
            scheduleSearch: '',
            scheduleActivity: '',
            scheduleGroup: '',
            scheduleInstructor: '',
            schedulePeriod: @js($schedulePeriod ?? 'week'),
            includeWeekends: @js((bool) ($includeWeekends ?? false)),
            gridJumpDate: @js($formatDate($selectedScheduleDate ?? $weekStart)),
            defaultCreateDate: @js(($schedulePeriod ?? 'week') === 'day' ? ($selectedScheduleDate ?? $weekStart)->toDateString() : null),
            calendarAllowsCreate: @js($canCreateInCurrentPeriod),
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
                    const restoreY = parseInt(scrollY, 10);
                    window.scrollTo(0, restoreY);
                    this.$nextTick(() => {
                        window.scrollTo(0, restoreY);
                    });
                }

                this.$el.addEventListener('click', (e) => {
                    const link = e.target.closest('a');
                    if (link && link.getAttribute('href') && !link.getAttribute('href').startsWith('#')) {
                        sessionStorage.setItem('tpss-schedule-scroll-y', window.scrollY);
                        sessionStorage.setItem('tpss-schedule-scroll-height', document.documentElement.scrollHeight);
                    }
                });

                this.$el.addEventListener('submit', () => {
                    sessionStorage.setItem('tpss-schedule-scroll-y', window.scrollY);
                    sessionStorage.setItem('tpss-schedule-scroll-height', document.documentElement.scrollHeight);
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

                if (this.focusedScheduleId) {
                    this.$nextTick(() => {
                        window.requestAnimationFrame(() => this.centerFocusedSchedule());
                    });
                }
            },
            isFocusedSchedule(id) {
                return this.focusedScheduleId && String(id) === String(this.focusedScheduleId);
            },
            focusedScheduleClass(id) {
                return this.isFocusedSchedule(id) ? 'schedule-conflict-focus' : '';
            },
            centerFocusedSchedule() {
                const rawId = String(this.focusedScheduleId);
                const safeId = window.CSS?.escape ? CSS.escape(rawId) : rawId.replace(/[^a-zA-Z0-9_-]/g, '');
                const target = this.$el.querySelector(`[data-schedule-id='${safeId}']`);
                if (!target) return;

                target.scrollIntoView({ block: 'center', inline: 'center', behavior: this.editModal ? 'auto' : 'smooth' });
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
                if (period === 'week' && this.includeWeekends) {
                    url.searchParams.set('include_weekends', '1');
                } else {
                    url.searchParams.delete('include_weekends');
                }
                sessionStorage.setItem('tpss-schedule-scroll-y', window.scrollY);
                sessionStorage.setItem('tpss-schedule-scroll-height', document.documentElement.scrollHeight);
                window.location.href = url.toString();
            },
            jumpToGridDate(value) {
                const iso = this.thaiDateToIso(value);
                if (!iso) return;
                this.navigateGrid(iso, @js($schedulePeriod ?? 'week'));
            },
            changeGridPeriod(period) {
                const iso = this.thaiDateToIso(this.gridJumpDate) || @js(($selectedScheduleDate ?? $weekStart)->toDateString());
                this.navigateGrid(iso, period);
            },
            centerStackCard(el) {
                const stack = el?.closest('.activity-stack');
                if (!stack) return;

                this.$nextTick(() => {
                    window.requestAnimationFrame(() => {
                        // กรอง ghost card ออก — นับเฉพาะการ์ดที่อยู่ใน page ปัจจุบัน
                        const activeCards = Array.from(stack.querySelectorAll('[data-stack-card]'))
                            .filter((card) => !card.classList.contains('is-ghost-peek'));
                        const targetCards = activeCards.length ? activeCards : [stack];
                        const bounds = targetCards.reduce((range, card) => {
                            const rect = card.getBoundingClientRect();
                            return {
                                top: Math.min(range.top, rect.top),
                                bottom: Math.max(range.bottom, rect.bottom),
                            };
                        }, { top: Number.POSITIVE_INFINITY, bottom: Number.NEGATIVE_INFINITY });

                        if (!Number.isFinite(bounds.top) || !Number.isFinite(bounds.bottom)) return;

                        const targetCenter = bounds.top + ((bounds.bottom - bounds.top) / 2);
                        const viewportCenter = window.innerHeight / 2;
                        window.scrollBy({
                            top: targetCenter - viewportCenter,
                            behavior: 'smooth',
                        });
                    });
                });
            },
            toggleWeekends() {
                if (this.schedulePeriod !== 'week') return;
                const url = new URL(@js($weekendToggleUrl), window.location.origin);
                sessionStorage.setItem('tpss-schedule-scroll-y', window.scrollY);
                sessionStorage.setItem('tpss-schedule-scroll-height', document.documentElement.scrollHeight);
                window.location.href = url.toString();
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
                // reset custom time pickers
                const resetTp = (hiddenId, hVal = '--', mVal = '--') => {
                    const picker = form.querySelector(`.time-picker[data-tp-hidden='${hiddenId}']`);
                    if (!picker) return;
                    picker.querySelector('.tp-val-hour').textContent = hVal;
                    picker.querySelector('.tp-val-min').textContent = mVal;
                    picker.dataset.tpHour = hVal === '--' ? '' : hVal;
                    picker.dataset.tpMin = mVal === '--' ? '' : mVal;
                    const drop = picker.querySelector('.tp-drop');
                    if (drop) {
                        drop.querySelectorAll('.tp-hour-item').forEach(li => {
                            li.classList.toggle('tp-sel', li.dataset.val === hVal);
                        });
                        drop.querySelectorAll('.tp-min-item').forEach(li => {
                            li.classList.toggle('tp-sel', li.dataset.val === mVal);
                        });
                    }
                    const hidden = document.getElementById(hiddenId);
                    if (hidden) {
                        hidden.value = hVal !== '--' && mVal !== '--' ? hVal + ':' + mVal : '';
                        hidden.dispatchEvent(new Event('input', { bubbles: true }));
                        hidden.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                };
                resetTp('start_time');
                resetTp('end_time');
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
                if (!this.calendarAllowsCreate) return;

                this.detailModal = null;
                this.editModal = null;
                this.resetCreateForm(date || this.defaultCreateDate);
                this.showCreate = true;
            },
            openEdit(id) {
                this.detailModal = null;
                this.showCreate = false;
                this.editModal = 'schedule-' + id;
            },
            closeCreate() { this.showCreate = false; },
            closeEdit() {
                @if(request()->boolean('from_conflict'))
                    window.location.href = @js(route('maker.schedule_conflicts.index'));
                @else
                    this.editModal = null;
                @endif
            }
        }"
        @keydown.escape.window="detailModal = null; showCreate = false; editModal = null"
    >
        @if($errors->has('schedule') && ! $openCreateModal && ! $openEditScheduleId)
            @php
                $alertMessages = $scheduleAlertMessages($errors, 'schedule');
            @endphp
            <div class="schedule-empty" style="border-color:var(--status-conflict-border);background:var(--status-conflict-bg);color:var(--status-conflict-fg);font-weight:800;text-align:left;margin-bottom:14px;">
                @foreach($alertMessages as $message)
                    <div style="{{ ! $loop->last ? 'margin-bottom:6px;' : '' }}">{{ $message }}</div>
                @endforeach
            </div>
        @endif

        @if(session('schedule_conflict_warning'))
            <div class="schedule-empty" style="border-color:var(--status-conflict-border);background:var(--status-conflict-bg);color:var(--status-conflict-fg);font-weight:800;text-align:left;margin-bottom:14px;" data-testid="schedule-conflict-save-warning">
                <div style="margin-bottom:6px;">บันทึกแล้ว แต่พบการชน ต้องแก้ไขก่อนส่งอนุมัติ</div>
                @foreach(collect(session('schedule_conflict_warning'))->take(4) as $message)
                    <div style="font-weight:700;">{{ $message }}</div>
                @endforeach
                <div style="margin-top:10px;">
                    <a href="{{ route('maker.schedule_conflicts.index') }}" class="btn btn-secondary" style="text-decoration:none;">ดูการแจ้งเตือนการชน</a>
                </div>
            </div>
        @endif

        @if($availableOfferings->isNotEmpty())
            <div class="offerings-dropdown-panel" data-testid="offerings-panel">
                <div class="offerings-panel-meta">
                    <label for="offering-selector" class="offering-selector-label">รายวิชาที่รับผิดชอบ:</label>
                    <span class="badge badge-gray offering-summary-chip">{{ $availableOfferings->count() }} รายวิชา</span>
                    @if($activeOfferingCount > 0)
                        <span class="badge badge-ok offering-summary-chip">{{ $activeOfferingCount }} เปิดจัดตาราง</span>
                    @endif
                </div>
                <div class="offering-selector-wrapper">
                    <select id="offering-selector" class="offering-select-control" onchange="sessionStorage.setItem('tpss-schedule-scroll-y', window.scrollY); window.location.href = this.value">
                        @php
                            $selectorDate = ($selectedScheduleDate ?? $weekStart)->toDateString();
                            $selectorPeriod = $schedulePeriod ?? 'week';
                            $selectorQuery = array_filter([
                                'date' => $selectorDate,
                                'period' => $selectorPeriod,
                                'include_weekends' => ($selectorPeriod === 'week' && ($includeWeekends ?? false)) ? 1 : null,
                            ]);
                        @endphp
                        @foreach($availableOfferings as $availOffering)
                            @php
                                $availCourse = $availOffering->course;
                                $isSelected = ! $isWorkspace && $courseOffering && $courseOffering->id === $availOffering->id;
                                $optionCourseName = $availCourse?->name_th ?? $availCourse?->name_en ?? 'ไม่มีชื่อรายวิชา';
                                $optUrl = route('maker.course_offerings.schedules.index', array_filter([
                                    $availOffering,
                                    ...$selectorQuery,
                                ]));
                            @endphp
                            <option value="{{ $optUrl }}" {{ $isSelected ? 'selected' : '' }}>
                                {{ $availCourse?->course_code ?? '-' }} - {{ \Illuminate\Support\Str::limit($optionCourseName, 36) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        @endif

        <script>
            (function () {
                // Toggle compact state on scroll, restore after user stops scrolling.
                const btnSelector = '.floating-create-button';
                let timer = null;
                let lastScrollY = 0;
                const COMPACT_CLASS = 'compact';

                function onScroll() {
                    const btn = document.querySelector(btnSelector);
                    if (!btn) return;

                    // add compact while scrolling
                    btn.classList.add(COMPACT_CLASS);

                    // clear previous timer
                    if (timer) clearTimeout(timer);

                    // set timer to remove compact state after 220ms of no scroll
                    timer = setTimeout(() => {
                        btn.classList.remove(COMPACT_CLASS);
                        timer = null;
                    }, 220);
                }

                // Listen with passive for performance
                window.addEventListener('scroll', onScroll, { passive: true });

                // Also shrink if touchmove (mobile)
                window.addEventListener('touchmove', onScroll, { passive: true });

                // Clean up on page hide
                window.addEventListener('beforeunload', () => {
                    if (timer) clearTimeout(timer);
                });
            })();
        </script>

        @if($isWorkspace && $availableOfferings->isNotEmpty())
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
                <div class="sched-datenav-stack">
                    <div class="sched-datenav-picker">
                        <x-thai-date-input
                            name="grid_jump_date"
                            :helper="false"
                            :value="($selectedScheduleDate ?? $weekStart)->toDateString()"
                            :year-start="$scheduleDatePickerYearStart"
                            :year-end="$scheduleDatePickerYearEnd"
                            class="sched-datenav-input"
                            x-model="gridJumpDate"
                            @change="jumpToGridDate(gridJumpDate)"
                            @keydown.enter.prevent="jumpToGridDate(gridJumpDate)"
                            aria-label="เลือกวันที่ที่ต้องการดูในตาราง" />
                        <span class="sched-datenav-label">{{ $calendarHeadingText }}</span>
                    </div>
                </div>
            </label>
            <div class="period-toggle" aria-label="ช่วงเวลาที่แสดง" x-show="view === 'grid'" x-cloak>
                <a href="{{ $dayViewUrl }}" class="{{ ($schedulePeriod ?? 'week') === 'day' ? 'is-active' : '' }}">วัน</a>
                <a href="{{ $weekViewUrl }}" class="{{ ($schedulePeriod ?? 'week') === 'week' ? 'is-active' : '' }}">สัปดาห์</a>
                <a href="{{ $monthViewUrl }}" class="{{ ($schedulePeriod ?? 'week') === 'month' ? 'is-active' : '' }}">เดือน</a>
            </div>
            <button
                type="button"
                class="weekend-toggle {{ ($includeWeekends ?? false) ? 'is-active' : '' }}"
                x-show="view === 'grid' && schedulePeriod === 'week'"
                x-cloak
                @click="toggleWeekends()"
                aria-pressed="{{ ($includeWeekends ?? false) ? 'true' : 'false' }}"
            >เสาร์-อาทิตย์</button>
            <div class="schedule-toggle" role="group" aria-label="รูปแบบการแสดงตาราง">
                <button type="button" :class="{ 'is-active': view === 'list' }" @click="view = 'list'" data-testid="schedule-list-toggle">แบบรายการ</button>
                <button type="button" :class="{ 'is-active': view === 'grid' }" @click="view = 'grid'" data-testid="schedule-grid-toggle">แบบตาราง</button>
            </div>
            @if($canEdit)
                <div class="toolbar-actions">
                    <button
                        type="button"
                        class="btn btn-primary {{ ! $canCreateInCurrentPeriod ? 'is-disabled' : '' }}"
                        data-testid="schedule-create-link"
                        @click="openCreate()"
                        @disabled(! $canCreateInCurrentPeriod)
                        title="{{ ! $canCreateInCurrentPeriod ? $outsideCreateHint : '' }}"
                        aria-disabled="{{ ! $canCreateInCurrentPeriod ? 'true' : 'false' }}"
                    >+ เพิ่ม</button>
                </div>
            @endif
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
                    'published' => ['label' => 'อนุมัติแล้ว', 'class' => 'badge-ok'],
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
                        <button
                            type="button"
                            class="btn btn-primary {{ ! $canCreateInCurrentPeriod ? 'is-disabled' : '' }}"
                            data-testid="schedule-create-link"
                            @click="openCreate()"
                            @disabled(! $canCreateInCurrentPeriod)
                            title="{{ ! $canCreateInCurrentPeriod ? $outsideCreateHint : '' }}"
                            aria-disabled="{{ ! $canCreateInCurrentPeriod ? 'true' : 'false' }}"
                            style="min-height:34px;padding:6px 12px;font-size:12.5px;"
                        >+ เพิ่มรายการสอน</button>
                    @endif
                </div>
                <div class="course-overview-stats">
                    <span class="course-stat"><strong>{{ $totalScheduleCount ?? $allSchedules->count() }}</strong> รายการสอน</span>
                    <span class="course-stat"><strong>{{ $courseOffering->studentGroups->count() }}</strong> กลุ่มนักศึกษา</span>
                    <span class="course-stat"><strong>{{ $eligibleScheduleInstructors($courseOffering)->count() }}</strong> ผู้สอน</span>
                </div>
            </section>

            {{-- ── รายการตารางสอน (Card Layout) ── --}}
            <div class="card">
                <div class="card-hdr schedule-card-hdr" style="flex-wrap:wrap;gap:12px;">
                    <div>
                        <div class="card-ttl">รายการตารางสอน</div>
                        <div class="schedule-caption-line">
                            <span class="caption">เรียงตามช่วงวันที่และเวลา</span>
                            @if($calendarOutsideNote)
                                <span class="schedule-caption-warning">{{ $calendarOutsideNote }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="schedule-card-actions">
                        <div class="sched-datenav" x-show="view === 'grid'" x-cloak>
                            <a class="sched-datenav-arrow" href="{{ $previousWeekUrl }}" data-testid="schedule-nav-prev" aria-label="ช่วงก่อนหน้า">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>
                            </a>
                            <div class="sched-datenav-stack">
                                <div class="sched-datenav-picker">
                                    <x-thai-date-input
                                        name="grid_jump_date"
                                        :helper="false"
                                        :value="($selectedScheduleDate ?? $weekStart)->toDateString()"
                                        :year-start="$scheduleDatePickerYearStart"
                                        :year-end="$scheduleDatePickerYearEnd"
                                        class="sched-datenav-input"
                                        x-model="gridJumpDate"
                                        @change="jumpToGridDate(gridJumpDate)"
                                        @keydown.enter.prevent="jumpToGridDate(gridJumpDate)"
                                        aria-label="พิมพ์วันที่ที่ต้องการดูในตาราง" />
                                    <span class="sched-datenav-label">{{ $calendarHeadingText }}</span>
                                </div>
                            </div>
                            <a class="sched-datenav-arrow" href="{{ $nextWeekUrl }}" data-testid="schedule-nav-next" aria-label="ช่วงถัดไป">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
                            </a>
                        </div>
                        <div class="period-toggle" aria-label="ช่วงเวลาที่แสดง" x-show="view === 'grid'" x-cloak>
                            <a href="{{ $dayViewUrl }}" class="{{ ($schedulePeriod ?? 'week') === 'day' ? 'is-active' : '' }}">วัน</a>
                            <a href="{{ $weekViewUrl }}" class="{{ ($schedulePeriod ?? 'week') === 'week' ? 'is-active' : '' }}">สัปดาห์</a>
                            <a href="{{ $monthViewUrl }}" class="{{ ($schedulePeriod ?? 'week') === 'month' ? 'is-active' : '' }}">เดือน</a>
                        </div>
                        <button
                            type="button"
                            class="weekend-toggle {{ ($includeWeekends ?? false) ? 'is-active' : '' }}"
                            x-show="view === 'grid' && schedulePeriod === 'week'"
                            x-cloak
                            @click="toggleWeekends()"
                            aria-pressed="{{ ($includeWeekends ?? false) ? 'true' : 'false' }}"
                        >เสาร์-อาทิตย์</button>
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
                                                    $asInstructorText = $scheduleInstructorText($as);
                                                    $asConflicts = $scheduleConflicts->get($as->id, collect());
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
                                                <tr role="button" tabindex="0" class="co-sched-row" :class="focusedScheduleClass('{{ $as->id }}')" style="--activity-color: {{ $activityTone($as) }};" x-show="matchesSchedule('{{ $as->id }}')" x-cloak data-schedule-id="{{ $as->id }}" data-schedule-modal-trigger @click="detailModal = 'schedule-{{ $as->id }}'" @keydown.enter.prevent="detailModal = 'schedule-{{ $as->id }}'" @keydown.space.prevent="detailModal = 'schedule-{{ $as->id }}'">
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
                                                        @if($asConflicts->isNotEmpty())
                                                            <div style="margin-top:6px;">
                                                                @include('course_head.schedules._conflict_pill', ['conflicts' => $asConflicts])
                                                            </div>
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
                                                $instructorText = $scheduleInstructorText($schedule);
                                                $itemConflicts = $scheduleConflicts->get($schedule->id, collect());
                                            @endphp
                                            <div role="button" tabindex="0" class="month-activity" :class="focusedScheduleClass('{{ $schedule->id }}')" style="--activity-color: {{ $activityTone($schedule) }};" data-schedule-id="{{ $schedule->id }}" data-schedule-modal-trigger @click="detailModal = 'schedule-{{ $schedule->id }}'" @keydown.enter.prevent="detailModal = 'schedule-{{ $schedule->id }}'" @keydown.space.prevent="detailModal = 'schedule-{{ $schedule->id }}'">
                                                <div class="month-activity-time">{{ $formatTime($schedule->start_time) }} - {{ $formatTime($schedule->end_time) }}</div>
                                                <div class="month-activity-title">{{ $schedule->topic ?: ($activity?->name ?? 'รายการสอน') }}</div>
                                                <div class="month-activity-tags">
                                                    <span class="activity-tag" style="--activity-color: {{ $activityTone($schedule) }};">{{ $activity?->name ?? 'กิจกรรม' }}</span>
                                                    @if($schedule->studentGroups->isNotEmpty())
                                                        <span class="month-group-summary">{{ $schedule->studentGroups->count() }} กลุ่ม</span>
                                                    @endif
                                                    @if($itemConflicts->isNotEmpty())
                                                        @include('course_head.schedules._conflict_pill', ['conflicts' => $itemConflicts])
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
                    <div class="schedule-grid is-precise" style="--grid-day-count: {{ max(1, $weekDays->count()) }}; --grid-minute-row-height: {{ $gridMinuteRowHeight }}px; grid-template-columns: 68px repeat({{ max(1, $weekDays->count()) }}, minmax({{ ($includeWeekends ?? false) && ($schedulePeriod ?? 'week') === 'week' ? 122 : 146 }}px, 1fr)); grid-template-rows: 44px repeat({{ $gridMinuteRowCount }}, var(--grid-minute-row-height));">
                        <div class="grid-cell grid-head" style="grid-area:1 / 1;"></div>
                        @foreach($weekDays as $dayIndex => $day)
                            <div class="grid-cell grid-head" style="grid-area:1 / {{ $dayIndex + 2 }};">
                                {{ $thaiDays[$day->dayOfWeekIso] ?? $day->format('l') }}<br>
                                <span class="caption">{{ $formatDate($day) }}</span>
                            </div>
                        @endforeach

                        @foreach($gridHourSlots as $slot)
                            @php
                                $hourRowStart = $gridRowStartForTime($slot);
                            @endphp
                            <div class="grid-cell grid-time" style="grid-column:1; grid-row:{{ $hourRowStart }} / span {{ $gridRowsPerHour }};">{{ $slot }}</div>
                            @foreach($weekDays as $dayIndex => $day)
                                <div class="grid-cell" style="grid-column:{{ $dayIndex + 2 }}; grid-row:{{ $hourRowStart }} / span {{ $gridRowsPerHour }};"></div>
                            @endforeach
                        @endforeach

                        @foreach($weekDays as $dayIndex => $day)
                            @php
                                $dayOccurrences = $gridOccurrences
                                    ->filter(fn ($occurrence) => $occurrence['date']->toDateString() === $day->toDateString());
                                $dayStacks = $groupOccurrencesIntoStacks($dayOccurrences);
                            @endphp
                            @foreach($dayStacks as $stack)
                                @php
                                    $minStart = null;
                                    $maxEnd = null;
                                    foreach ($stack as $occ) {
                                        $st = (string) $occ['schedule']->start_time;
                                        $et = (string) $occ['schedule']->end_time;
                                        if ($minStart === null || $st < $minStart) $minStart = $st;
                                        if ($maxEnd === null || $et > $maxEnd) $maxEnd = $et;
                                    }
                                    
                                    $startCarbon = \Carbon\CarbonImmutable::createFromFormat('H:i:s', strlen($minStart) === 5 ? $minStart . ':00' : $minStart);
                                    $endCarbon = \Carbon\CarbonImmutable::createFromFormat('H:i:s', strlen($maxEnd) === 5 ? $maxEnd . ':00' : $maxEnd);
                                    $stackDuration = (int) max(0, $startCarbon->diffInMinutes($endCarbon));
                                    
                                    $activityRowStart = $gridRowStartForTime($minStart);
                                    $activityRowSpan = max(1, (int) ceil(max(5, $stackDuration) / $gridMinuteStep));
                                @endphp
                                <div class="grid-cell grid-cell-activity" style="grid-column:{{ $dayIndex + 2 }}; grid-row:{{ $activityRowStart }} / span {{ $activityRowSpan }};">
                                    @if(count($stack) === 1)
                                        @php
                                            $occurrence = $stack[0];
                                            $schedule = $occurrence['schedule'];
                                            $activity = $schedule->activityType;
                                            $room = $schedule->room;
                                            $offeringCourse = $schedule->courseOffering?->course;
                                            $instructorText = $scheduleInstructorText($schedule);
                                            $itemConflicts = $scheduleConflicts->get($schedule->id, collect());
                                            $activityDuration = (int) $occurrence['duration_minutes'];
                                            $gridActivitySizeClass = $activityDuration < 75
                                                ? 'is-compact'
                                                : ($activityDuration >= 150 ? 'is-tall' : '');
                                        @endphp
                                        <div role="button" tabindex="0" class="grid-activity {{ $gridActivitySizeClass }}" :class="focusedScheduleClass('{{ $schedule->id }}')" style="--activity-color: {{ $activityTone($schedule) }};" data-schedule-id="{{ $schedule->id }}" data-schedule-modal-trigger @click="detailModal = 'schedule-{{ $schedule->id }}'" @keydown.enter.prevent="detailModal = 'schedule-{{ $schedule->id }}'" @keydown.space.prevent="detailModal = 'schedule-{{ $schedule->id }}'">
                                            <div class="grid-activity-top">
                                                <span class="activity-tag" style="--activity-color: {{ $activityTone($schedule) }};">{{ $activity?->name ?? 'กิจกรรม' }}</span>
                                                @if($itemConflicts->isNotEmpty())
                                                    @include('course_head.schedules._conflict_pill', ['conflicts' => $itemConflicts])
                                                @endif
                                            </div>
                                            <div class="grid-activity-title">{{ $schedule->topic ?: ($activity?->name ?? 'รายการสอน') }}</div>
                                            <div class="grid-activity-time">{{ $formatTime($schedule->start_time) }} - {{ $formatTime($schedule->end_time) }} · {{ $formatDuration($occurrence['duration_minutes']) }}</div>
                                            <div class="grid-activity-foot">
                                                <span class="grid-activity-room">{{ $room?->room_name ?? $room?->room_code ?? 'ไม่ระบุสถานที่' }}</span>
                                                <span class="grid-activity-groups">
                                                    {{ $schedule->studentGroups->isNotEmpty() ? $schedule->studentGroups->count() . ' กลุ่ม' : 'ไม่มีกลุ่ม' }}
                                                </span>
                                            </div>
                                        </div>
                                    @else
                                        @php
                                            $stackCount = count($stack);
                                            $focusedStackIndex = collect($stack)->search(fn ($occ) => (string) $occ['schedule']->id === $focusedScheduleId);
                                            $initialStackPage = $focusedStackIndex === false ? 0 : intdiv((int) $focusedStackIndex, 3);
                                        @endphp
                                        <div class="activity-stack" x-data="{ page: {{ $initialStackPage }}, count: {{ $stackCount }} }">
                                            @foreach($stack as $idx => $occurrence)
                                                @php
                                                    $schedule = $occurrence['schedule'];
                                                    $activity = $schedule->activityType;
                                                    $room = $schedule->room;
                                                    $offeringCourse = $schedule->courseOffering?->course;
                                                    $instructorText = $scheduleInstructorText($schedule);
                                                    $itemConflicts = $scheduleConflicts->get($schedule->id, collect());
                                                    $activityDuration = (int) $occurrence['duration_minutes'];
                                                    $gridActivitySizeClass = $activityDuration < 75
                                                        ? 'is-compact'
                                                        : ($activityDuration >= 150 ? 'is-tall' : '');

                                                    // Calculate relative top offset and height percentages inside the stack
                                                    $occStart = (string) $schedule->start_time;
                                                    $occEnd = (string) $schedule->end_time;
                                                    $occStartCarbon = \Carbon\CarbonImmutable::createFromFormat('H:i:s', strlen($occStart) === 5 ? $occStart . ':00' : $occStart);
                                                    $occEndCarbon = \Carbon\CarbonImmutable::createFromFormat('H:i:s', strlen($occEnd) === 5 ? $occEnd . ':00' : $occEnd);

                                                    $topOffset = (int) max(0, $startCarbon->diffInMinutes($occStartCarbon));
                                                    $occDuration = (int) max(0, $occStartCarbon->diffInMinutes($occEndCarbon));

                                                    $topPercent = $stackDuration > 0 ? ($topOffset / $stackDuration) * 100 : 0;
                                                    $heightPercent = $stackDuration > 0 ? ($occDuration / $stackDuration) * 100 : 100;
                                                @endphp
                                                <div
                                                    role="button"
                                                    tabindex="0"
                                                    class="grid-activity {{ $gridActivitySizeClass }} grid-activity-card is-stacked-card"
                                                    style="--activity-color: {{ $activityTone($schedule) }}; top: {{ round($topPercent, 4) }}%; height: {{ round($heightPercent, 4) }}%;"
                                                    :style="(function(){
                                                        const idx = {{ $idx }};
                                                        const inPage = idx >= page * 3 && idx < (page + 1) * 3;
                                                        if (inPage) {
                                                            return {
                                                                left: ((idx - page * 3) * 12) + '%',
                                                                width: (100 - (Math.min(3, count - page * 3) - 1) * 12) + '%',
                                                                zIndex: 10 + (idx - page * 3),
                                                                opacity: 1,
                                                                pointerEvents: 'auto',
                                                                display: 'flex',
                                                                transform: 'none'
                                                            };
                                                        } else if (idx < page * 3) {
                                                            /* Ghost cards BEFORE current page — peek from left, stacked by distance */
                                                            const dist = page * 3 - idx; /* 1 = closest */
                                                            const baseOpacity = Math.max(0.12, 0.45 - (dist - 1) * 0.08);
                                                            const scale = Math.max(0.84, 0.95 - (dist - 1) * 0.04);
                                                            const leftOffset = Math.max(0, 4 - (dist - 1) * 2);
                                                            return {
                                                                left: leftOffset + '%',
                                                                width: '72%',
                                                                zIndex: 6 - dist,
                                                                opacity: baseOpacity,
                                                                pointerEvents: 'none',
                                                                display: 'flex',
                                                                transform: 'scale(' + scale + ')',
                                                                transformOrigin: 'top left'
                                                            };
                                                        } else {
                                                            /* Ghost cards AFTER current page — peek from right, stacked by distance */
                                                            const dist = idx - (page + 1) * 3 + 1; /* 1 = closest */
                                                            const baseOpacity = Math.max(0.12, 0.45 - (dist - 1) * 0.08);
                                                            const scale = Math.max(0.84, 0.95 - (dist - 1) * 0.04);
                                                            const leftOffset = Math.min(24, 16 + (dist - 1) * 3);
                                                            return {
                                                                left: leftOffset + '%',
                                                                width: '72%',
                                                                zIndex: 6 - dist,
                                                                opacity: baseOpacity,
                                                                pointerEvents: 'none',
                                                                display: 'flex',
                                                                transform: 'scale(' + scale + ')',
                                                                transformOrigin: 'bottom right'
                                                            };
                                                        }
                                                    })()"
                                                    :class="{
                                                        'is-stack-front': {{ $idx }} === Math.min((page + 1) * 3 - 1, count - 1),
                                                        'is-stack-back': {{ $idx }} >= page * 3 && {{ $idx }} !== Math.min((page + 1) * 3 - 1, count - 1),
                                                        'has-visible-stack-switcher': count > 3 && {{ $idx }} === Math.min((page + 1) * 3 - 1, count - 1),
                                                        'has-no-visible-stack-switcher': count <= 3 || {{ $idx }} !== Math.min((page + 1) * 3 - 1, count - 1),
                                                        'is-ghost-peek': !({{ $idx }} >= page * 3 && {{ $idx }} < (page + 1) * 3),
                                                        'is-ghost-prev': {{ $idx }} < page * 3,
                                                        'is-ghost-next': {{ $idx }} >= (page + 1) * 3,
                                                        'schedule-conflict-focus': isFocusedSchedule('{{ $schedule->id }}')
                                                    }"
                                                    data-stack-card
                                                    data-schedule-id="{{ $schedule->id }}"
                                                    data-schedule-modal-trigger
                                                    @click="if({{ $idx }} >= page * 3 && {{ $idx }} < (page + 1) * 3) detailModal = 'schedule-{{ $schedule->id }}'"
                                                    @keydown.enter.prevent="if({{ $idx }} >= page * 3 && {{ $idx }} < (page + 1) * 3) detailModal = 'schedule-{{ $schedule->id }}'"
                                                    @keydown.space.prevent="if({{ $idx }} >= page * 3 && {{ $idx }} < (page + 1) * 3) detailModal = 'schedule-{{ $schedule->id }}'"
                                                >
                                                    @if($itemConflicts->isNotEmpty())
                                                        <div class="grid-activity-top">
                                                            @include('course_head.schedules._conflict_pill', ['conflicts' => $itemConflicts])
                                                        </div>
                                                    @endif
                                                    <div class="grid-activity-title">{{ $schedule->topic ?: ($activity?->name ?? 'รายการสอน') }}</div>
                                                    <div class="grid-activity-time">{{ $formatTime($schedule->start_time) }} - {{ $formatTime($schedule->end_time) }} · {{ $formatDuration($occurrence['duration_minutes']) }}</div>
                                                    <div class="grid-activity-foot">
                                                        <span class="grid-activity-room">{{ $room?->room_name ?? $room?->room_code ?? 'ไม่ระบุสถานที่' }}</span>
                                                        <span class="grid-activity-groups">
                                                            {{ $schedule->studentGroups->isNotEmpty() ? $schedule->studentGroups->count() . ' กลุ่ม' : 'ไม่มีกลุ่ม' }}
                                                        </span>
                                                    </div>

                                                    @if($stackCount > 1)
                                                        @if($stackCount > 3)
                                                            <div class="stack-switcher-top-zone" aria-hidden="true"></div>
                                                            <div class="stack-switcher-zone" aria-hidden="true"></div>
                                                        @endif
                                                        <div
                                                            class="stack-indicator {{ $stackCount > 3 ? 'is-stack-switcher' : 'is-stack-count' }}"
                                                            x-show="{{ $idx }} === Math.min((page + 1) * 3 - 1, count - 1)"
                                                            @if($stackCount > 3)
                                                                @click.stop="page = (page + 1) % Math.ceil(count / 3); centerStackCard($el)"
                                                                title="คลิกเพื่อดูการ์ดถัดไป"
                                                            @endif
                                                        >
                                                            @if($stackCount > 3)
                                                                <svg class="stack-sync-icon" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                                                </svg>
                                                                <span x-text="((page * 3) + 1) + '-' + Math.min((page + 1) * 3, count) + ' จาก ' + count + ' ใบ'"></span>
                                                            @else
                                                                <span>{{ $stackCount }} ใบ</span>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
        @endif {{-- end non-workspace --}}

        @if($isWorkspace)
            @if($availableOfferings->isEmpty())
                @php
                    $emptyKey = $coordinatorEmptyStateKey ?? 'no_offerings';
                    $emptyMessages = [
                        'preparation' => [
                            'title' => 'อยู่ในสถานะเตรียมข้อมูล',
                            'sub' => 'ยังไม่ถึงช่วงเวลาการจัดตารางเรียน — ระบบจะเปิดให้จัดตารางเมื่อผู้ดูแลตั้งค่าปีการศึกษาเป็นช่วงจัดตาราง',
                        ],
                        'no_offerings' => [
                            'title' => 'ไม่พบรายวิชาที่ต้องจัดตารางสอนในระบบ',
                            'sub' => 'ช่วงจัดตารางเปิดอยู่ แต่คุณยังไม่ได้รับมอบหมายเป็นหัวหน้าวิชาในรอบนี้ — ติดต่อผู้ดูแลระบบหากต้องการรับผิดชอบรายวิชา',
                        ],
                    ];
                    $msg = $emptyMessages[$emptyKey] ?? $emptyMessages['no_offerings'];
                @endphp
                <div class="schedule-empty" data-testid="schedule-no-offerings-empty" data-empty-state="{{ $emptyKey }}" style="padding:32px 20px;text-align:center;">
                    <div style="font-weight:950;font-size:16px;color:var(--brand-navy);margin-bottom:6px;">{{ $msg['title'] }}</div>
                    <div style="font-weight:700;font-size:13px;color:var(--fg-2);line-height:1.55;max-width:560px;margin:0 auto;">{{ $msg['sub'] }}</div>
                </div>
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
                                    $canCreateOnDay = $canEdit
                                        && (! $academicStartDate || ! $academicEndDate || $day->betweenIncluded($academicStartDate, $academicEndDate));
                                @endphp
                                <tr class="sched-day">
                                    <td colspan="6">
                                        <div class="sched-day-head">
                                            <span class="sched-day-name">{{ $thaiDays[$day->dayOfWeekIso] ?? $day->format('l') }}</span>
                                            <span class="sched-day-date">{{ $formatDate($day) }}</span>
                                            <span class="sched-day-count">· {{ $dayOccurrences->count() }} รายการ</span>
                                            <span class="sched-day-spacer"></span>
                                            @if($canEdit)
                                                <button
                                                    type="button"
                                                    class="day-add-link"
                                                    @click="openCreate('{{ $day->toDateString() }}')"
                                                    @disabled(! $canCreateOnDay)
                                                    title="{{ ! $canCreateOnDay ? $outsideCreateHint : '' }}"
                                                    aria-disabled="{{ ! $canCreateOnDay ? 'true' : 'false' }}"
                                                >+ เพิ่ม</button>
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
                                        $instructorText = $scheduleInstructorText($schedule);
                                        $status = $statusMeta[$schedule->status] ?? ['label' => $schedule->status, 'class' => 'badge-gray'];
                                        $itemConflicts = $scheduleConflicts->get($schedule->id, collect());
                                    @endphp
                                    <tr role="button" tabindex="0" class="sched-row" :class="focusedScheduleClass('{{ $schedule->id }}')" style="--activity-color: {{ $activityTone($schedule) }};" data-testid="schedule-row" data-schedule-id="{{ $schedule->id }}" data-schedule-modal-trigger @click="detailModal = 'schedule-{{ $schedule->id }}'" @keydown.enter.prevent="detailModal = 'schedule-{{ $schedule->id }}'" @keydown.space.prevent="detailModal = 'schedule-{{ $schedule->id }}'">
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
                                                @if($itemConflicts->isNotEmpty())
                                                    @include('course_head.schedules._conflict_pill', ['conflicts' => $itemConflicts])
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
                                            $instructorText = $scheduleInstructorText($schedule);
                                            $status = $statusMeta[$schedule->status] ?? ['label' => $schedule->status, 'class' => 'badge-gray'];
                                            $itemConflicts = $scheduleConflicts->get($schedule->id, collect());
                                        @endphp
                                        <div role="button" tabindex="0" class="month-activity" :class="focusedScheduleClass('{{ $schedule->id }}')" style="--activity-color: {{ $activityTone($schedule) }};" data-schedule-id="{{ $schedule->id }}" data-schedule-modal-trigger @click="detailModal = 'schedule-{{ $schedule->id }}'" @keydown.enter.prevent="detailModal = 'schedule-{{ $schedule->id }}'" @keydown.space.prevent="detailModal = 'schedule-{{ $schedule->id }}'">
                                            <div class="month-activity-time">{{ $formatTime($schedule->start_time) }} - {{ $formatTime($schedule->end_time) }}</div>
                                            <div class="month-activity-title">{{ $schedule->topic ?: ($activity?->name ?? 'รายการสอน') }}</div>
                                            <div class="month-activity-meta">
                                                @if($offeringCourse?->course_code)
                                                    <span class="grid-course">{{ $offeringCourse->course_code }}</span>
                                                @else
                                                    {{ $room?->room_name ?? $room?->room_code ?? 'ไม่ระบุสถานที่' }}
                                                @endif
                                            </div>
                                            <div class="month-activity-tags">
                                                <span class="activity-tag" style="--activity-color: {{ $activityTone($schedule) }};">{{ $activity?->name ?? 'กิจกรรม' }}</span>
                                                @if($schedule->studentGroups->isNotEmpty())
                                                    <span class="month-group-summary">{{ $schedule->studentGroups->count() }} กลุ่ม</span>
                                                @endif
                                                @if($itemConflicts->isNotEmpty())
                                                    @include('course_head.schedules._conflict_pill', ['conflicts' => $itemConflicts])
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
                <div class="schedule-grid is-precise" style="--grid-day-count: {{ max(1, $weekDays->count()) }}; --grid-minute-row-height: {{ $gridMinuteRowHeight }}px; grid-template-columns: 68px repeat({{ max(1, $weekDays->count()) }}, minmax({{ ($includeWeekends ?? false) && ($schedulePeriod ?? 'week') === 'week' ? 122 : 146 }}px, 1fr)); grid-template-rows: 44px repeat({{ $gridMinuteRowCount }}, var(--grid-minute-row-height));">
                    <div class="grid-cell grid-head" style="grid-area:1 / 1;"></div>
                    @foreach($weekDays as $dayIndex => $day)
                        <div class="grid-cell grid-head" style="grid-area:1 / {{ $dayIndex + 2 }};">
                            {{ $thaiDays[$day->dayOfWeekIso] ?? $day->format('l') }}<br>
                            <span class="caption">{{ $formatDate($day) }}</span>
                        </div>
                    @endforeach

                    @foreach($gridHourSlots as $slot)
                        @php
                            $hourRowStart = $gridRowStartForTime($slot);
                        @endphp
                        <div class="grid-cell grid-time" style="grid-column:1; grid-row:{{ $hourRowStart }} / span {{ $gridRowsPerHour }};">{{ $slot }}</div>
                        @foreach($weekDays as $dayIndex => $day)
                            <div class="grid-cell" style="grid-column:{{ $dayIndex + 2 }}; grid-row:{{ $hourRowStart }} / span {{ $gridRowsPerHour }};"></div>
                        @endforeach
                    @endforeach

                    @foreach($weekDays as $dayIndex => $day)
                        @php
                            $dayOccurrences = $occurrences
                                ->filter(fn ($occurrence) => $occurrence['date']->toDateString() === $day->toDateString());
                            $dayStacks = $groupOccurrencesIntoStacks($dayOccurrences);
                        @endphp
                        @foreach($dayStacks as $stack)
                            @php
                                $minStart = null;
                                $maxEnd = null;
                                foreach ($stack as $occ) {
                                    $st = (string) $occ['schedule']->start_time;
                                    $et = (string) $occ['schedule']->end_time;
                                    if ($minStart === null || $st < $minStart) $minStart = $st;
                                    if ($maxEnd === null || $et > $maxEnd) $maxEnd = $et;
                                }
                                
                                $startCarbon = \Carbon\CarbonImmutable::createFromFormat('H:i:s', strlen($minStart) === 5 ? $minStart . ':00' : $minStart);
                                $endCarbon = \Carbon\CarbonImmutable::createFromFormat('H:i:s', strlen($maxEnd) === 5 ? $maxEnd . ':00' : $maxEnd);
                                $stackDuration = (int) max(0, $startCarbon->diffInMinutes($endCarbon));
                                
                                $activityRowStart = $gridRowStartForTime($minStart);
                                $activityRowSpan = max(1, (int) ceil(max(5, $stackDuration) / $gridMinuteStep));
                            @endphp
                            <div class="grid-cell grid-cell-activity" style="grid-column:{{ $dayIndex + 2 }}; grid-row:{{ $activityRowStart }} / span {{ $activityRowSpan }};">
                                @if(count($stack) === 1)
                                    @php
                                        $occurrence = $stack[0];
                                        $schedule = $occurrence['schedule'];
                                        $activity = $schedule->activityType;
                                        $room = $schedule->room;
                                        $offeringCourse = $schedule->courseOffering?->course;
                                        $instructorText = $scheduleInstructorText($schedule);
                                        $status = $statusMeta[$schedule->status] ?? ['label' => $schedule->status, 'class' => 'badge-gray'];
                                        $itemConflicts = $scheduleConflicts->get($schedule->id, collect());
                                        $activityDuration = (int) $occurrence['duration_minutes'];
                                        $gridActivitySizeClass = $activityDuration < 75
                                            ? 'is-compact'
                                            : ($activityDuration >= 150 ? 'is-tall' : '');
                                    @endphp
                                    <div role="button" tabindex="0" class="grid-activity {{ $gridActivitySizeClass }}" :class="focusedScheduleClass('{{ $schedule->id }}')" style="--activity-color: {{ $activityTone($schedule) }};" data-schedule-id="{{ $schedule->id }}" data-schedule-modal-trigger @click="detailModal = 'schedule-{{ $schedule->id }}'" @keydown.enter.prevent="detailModal = 'schedule-{{ $schedule->id }}'" @keydown.space.prevent="detailModal = 'schedule-{{ $schedule->id }}'">
                                        <div class="grid-activity-top">
                                            @if($offeringCourse?->course_code)
                                                <span class="grid-course">{{ $offeringCourse->course_code }}</span>
                                            @endif
                                            <span class="activity-tag" style="--activity-color: {{ $activityTone($schedule) }};">{{ $activity?->name ?? 'กิจกรรม' }}</span>
                                            @if($itemConflicts->isNotEmpty())
                                                @include('course_head.schedules._conflict_pill', ['conflicts' => $itemConflicts])
                                            @endif
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
                                @else
                                    @php
                                        $stackCount = count($stack);
                                        $focusedStackIndex = collect($stack)->search(fn ($occ) => (string) $occ['schedule']->id === $focusedScheduleId);
                                        $initialStackPage = $focusedStackIndex === false ? 0 : intdiv((int) $focusedStackIndex, 3);
                                    @endphp
                                    <div class="activity-stack" x-data="{ page: {{ $initialStackPage }}, count: {{ $stackCount }} }">
                                        @foreach($stack as $idx => $occurrence)
                                            @php
                                                $schedule = $occurrence['schedule'];
                                                $activity = $schedule->activityType;
                                                $room = $schedule->room;
                                                $offeringCourse = $schedule->courseOffering?->course;
                                                $instructorText = $scheduleInstructorText($schedule);
                                                $status = $statusMeta[$schedule->status] ?? ['label' => $schedule->status, 'class' => 'badge-gray'];
                                                $itemConflicts = $scheduleConflicts->get($schedule->id, collect());
                                                $activityDuration = (int) $occurrence['duration_minutes'];
                                                $gridActivitySizeClass = $activityDuration < 75
                                                    ? 'is-compact'
                                                    : ($activityDuration >= 150 ? 'is-tall' : '');

                                                // Calculate relative top offset and height percentages inside the stack
                                                $occStart = (string) $schedule->start_time;
                                                $occEnd = (string) $schedule->end_time;
                                                $occStartCarbon = \Carbon\CarbonImmutable::createFromFormat('H:i:s', strlen($occStart) === 5 ? $occStart . ':00' : $occStart);
                                                $occEndCarbon = \Carbon\CarbonImmutable::createFromFormat('H:i:s', strlen($occEnd) === 5 ? $occEnd . ':00' : $occEnd);

                                                $topOffset = (int) max(0, $startCarbon->diffInMinutes($occStartCarbon));
                                                $occDuration = (int) max(0, $occStartCarbon->diffInMinutes($occEndCarbon));

                                                $topPercent = $stackDuration > 0 ? ($topOffset / $stackDuration) * 100 : 0;
                                                $heightPercent = $stackDuration > 0 ? ($occDuration / $stackDuration) * 100 : 100;
                                            @endphp
                                            <div
                                                role="button"
                                                tabindex="0"
                                                class="grid-activity {{ $gridActivitySizeClass }} grid-activity-card is-stacked-card"
                                                style="--activity-color: {{ $activityTone($schedule) }}; top: {{ round($topPercent, 4) }}%; height: {{ round($heightPercent, 4) }}%;"
                                                :style="(function(){
                                                    const idx = {{ $idx }};
                                                    const inPage = idx >= page * 3 && idx < (page + 1) * 3;
                                                    if (inPage) {
                                                        return {
                                                            left: ((idx - page * 3) * 12) + '%',
                                                            width: (100 - (Math.min(3, count - page * 3) - 1) * 12) + '%',
                                                            zIndex: 10 + (idx - page * 3),
                                                            opacity: 1,
                                                            pointerEvents: 'auto',
                                                            display: 'flex',
                                                            transform: 'none'
                                                        };
                                                    } else if (idx < page * 3) {
                                                        /* Ghost cards BEFORE current page — peek from left, stacked by distance */
                                                        const dist = page * 3 - idx; /* 1 = closest */
                                                        const baseOpacity = Math.max(0.12, 0.45 - (dist - 1) * 0.08);
                                                        const scale = Math.max(0.84, 0.95 - (dist - 1) * 0.04);
                                                        const leftOffset = Math.max(0, 4 - (dist - 1) * 2);
                                                        return {
                                                            left: leftOffset + '%',
                                                            width: '72%',
                                                            zIndex: 6 - dist,
                                                            opacity: baseOpacity,
                                                            pointerEvents: 'none',
                                                            display: 'flex',
                                                            transform: 'scale(' + scale + ')',
                                                            transformOrigin: 'top left'
                                                        };
                                                    } else {
                                                        /* Ghost cards AFTER current page — peek from right, stacked by distance */
                                                        const dist = idx - (page + 1) * 3 + 1; /* 1 = closest */
                                                        const baseOpacity = Math.max(0.12, 0.45 - (dist - 1) * 0.08);
                                                        const scale = Math.max(0.84, 0.95 - (dist - 1) * 0.04);
                                                        const leftOffset = Math.min(24, 16 + (dist - 1) * 3);
                                                        return {
                                                            left: leftOffset + '%',
                                                            width: '72%',
                                                            zIndex: 6 - dist,
                                                            opacity: baseOpacity,
                                                            pointerEvents: 'none',
                                                            display: 'flex',
                                                            transform: 'scale(' + scale + ')',
                                                            transformOrigin: 'bottom right'
                                                        };
                                                    }
                                                })()"
                                                :class="{
                                                    'is-stack-front': {{ $idx }} === Math.min((page + 1) * 3 - 1, count - 1),
                                                    'is-stack-back': {{ $idx }} >= page * 3 && {{ $idx }} !== Math.min((page + 1) * 3 - 1, count - 1),
                                                    'has-visible-stack-switcher': count > 3 && {{ $idx }} === Math.min((page + 1) * 3 - 1, count - 1),
                                                    'has-no-visible-stack-switcher': count <= 3 || {{ $idx }} !== Math.min((page + 1) * 3 - 1, count - 1),
                                                    'is-ghost-peek': !({{ $idx }} >= page * 3 && {{ $idx }} < (page + 1) * 3),
                                                    'is-ghost-prev': {{ $idx }} < page * 3,
                                                    'is-ghost-next': {{ $idx }} >= (page + 1) * 3,
                                                    'schedule-conflict-focus': isFocusedSchedule('{{ $schedule->id }}')
                                                }"
                                                data-stack-card
                                                data-schedule-id="{{ $schedule->id }}"
                                                data-schedule-modal-trigger
                                                @click="if({{ $idx }} >= page * 3 && {{ $idx }} < (page + 1) * 3) detailModal = 'schedule-{{ $schedule->id }}'"
                                                @keydown.enter.prevent="if({{ $idx }} >= page * 3 && {{ $idx }} < (page + 1) * 3) detailModal = 'schedule-{{ $schedule->id }}'"
                                                @keydown.space.prevent="if({{ $idx }} >= page * 3 && {{ $idx }} < (page + 1) * 3) detailModal = 'schedule-{{ $schedule->id }}'"
                                            >
                                                <div class="grid-activity-top">
                                                    @if($offeringCourse?->course_code)
                                                        <span class="grid-course">{{ $offeringCourse->course_code }}</span>
                                                    @endif
                                                    @if($itemConflicts->isNotEmpty())
                                                        @include('course_head.schedules._conflict_pill', ['conflicts' => $itemConflicts])
                                                    @endif
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

                                                @if($stackCount > 1)
                                                    @if($stackCount > 3)
                                                        <div class="stack-switcher-top-zone" aria-hidden="true"></div>
                                                        <div class="stack-switcher-zone" aria-hidden="true"></div>
                                                    @endif
                                                    <div
                                                        class="stack-indicator {{ $stackCount > 3 ? 'is-stack-switcher' : 'is-stack-count' }}"
                                                        x-show="{{ $idx }} === Math.min((page + 1) * 3 - 1, count - 1)"
                                                        @if($stackCount > 3)
                                                            @click.stop="page = (page + 1) % Math.ceil(count / 3); centerStackCard($el)"
                                                            title="คลิกเพื่อดูการ์ดถัดไป"
                                                        @endif
                                                    >
                                                        @if($stackCount > 3)
                                                            <svg class="stack-sync-icon" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                                            </svg>
                                                            <span x-text="((page * 3) + 1) + '-' + Math.min((page + 1) * 3, count) + ' จาก ' + count + ' ใบ'"></span>
                                                        @else
                                                            <span>{{ $stackCount }} ใบ</span>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
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
                $detailInstructors = $scheduleDepartmentInstructors($schedule);
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
                                    @if($detailInstructors->isNotEmpty())
                                        <div style="display:flex;flex-direction:column;gap:3px;">
                                            @foreach($detailInstructors as $inst)
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
                                <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
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
                    $editDepartmentInstructors = $scheduleDepartmentInstructors($schedule);
                    $editInstructorIds = collect($editUsesOld ? old('instructor_ids', []) : $editDepartmentInstructors->pluck('id')->all())
                        ->map(fn ($id) => (string) $id)
                        ->all();
                    $editGroupIds = collect($editUsesOld ? old('student_group_ids', []) : $schedule->studentGroups->pluck('id')->all())
                        ->map(fn ($id) => (string) $id)
                        ->all();
                    $editLeadInstructorId = (string) ($editUsesOld
                        ? old('lead_instructor_id', '')
                        : ($editDepartmentInstructors->first(fn ($instructor) => (bool) $instructor->pivot?->is_lead)?->id ?? ''));
                    $editOld = fn (string $key, mixed $default = null) => $editUsesOld ? old($key, $default) : $default;
                    $editDateDisplay = function (string $key, $date) use ($editOld, $formatDate) {
                        $value = (string) $editOld($key, $date?->format('Y-m-d'));

                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                            return $formatDate(\Carbon\CarbonImmutable::parse($value));
                        }

                        return $value;
                    };
                    $editConflicts = $scheduleConflicts->get($schedule->id, collect());
                    $showConflictHints = $editConflicts->isNotEmpty();
                    $dateTimeConflictNote = $showConflictHints ? $conflictFieldNote($editConflicts, ['instructor_overlap', 'room_overlap', 'group_overlap'], 'วันและเวลา') : null;
                    $roomConflictNote = $showConflictHints ? $conflictFieldNote($editConflicts, ['room_overlap'], 'ห้อง/สถานที่') : null;
                    $instructorConflictNote = $showConflictHints ? $conflictFieldNote($editConflicts, ['instructor_overlap'], 'ผู้สอน') : null;
                    $groupConflictNote = $showConflictHints ? $conflictFieldNote($editConflicts, ['group_overlap'], 'กลุ่มนักศึกษา') : null;
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
                            <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                            @if(request()->boolean('from_conflict'))
                                <input type="hidden" name="return_to_conflicts" value="1">
                            @endif
                            <div class="modal-form-body">
                                @if($editUsesOld && $errors->any())
                                    @php
                                        $alertMessages = $scheduleAlertMessages($errors);
                                    @endphp
                                    <div class="schedule-empty" style="margin-bottom:12px;border-color:var(--status-conflict-border);background:var(--status-conflict-bg);color:var(--status-conflict-fg);font-weight:800;text-align:left;">
                                        @foreach($alertMessages as $message)
                                            <div style="{{ ! $loop->last ? 'margin-bottom:6px;' : '' }}">{{ $message }}</div>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="modal-form-grid">
                                    <div class="{{ $dateTimeConflictNote ? 'modal-field-has-conflict' : '' }}">
                                        <label class="modal-label" for="edit_start_date_{{ $schedule->id }}">วันที่เริ่ม <span class="required-mark">*</span></label>
                                        <x-thai-date-input
                                            name="start_date"
                                            :value="$editOld('start_date', $schedule->start_date?->format('Y-m-d'))"
                                            id="edit_start_date_{{ $schedule->id }}"
                                            class="modal-control"
                                            :required="true"
                                            :helper="false"
                                            :year-start="$scheduleDatePickerYearStart"
                                            :year-end="$scheduleDatePickerYearEnd"
                                            x-model="startDateDisplay" />
                                    </div>
                                    <div class="{{ $dateTimeConflictNote ? 'modal-field-has-conflict' : '' }}">
                                        <label class="modal-label" for="edit_end_date_{{ $schedule->id }}">วันที่สิ้นสุด <span class="required-mark">*</span></label>
                                        <x-thai-date-input
                                            name="end_date"
                                            :value="$editOld('end_date', $schedule->end_date?->format('Y-m-d'))"
                                            id="edit_end_date_{{ $schedule->id }}"
                                            class="modal-control"
                                            :required="true"
                                            :helper="false"
                                            :year-start="$scheduleDatePickerYearStart"
                                            :year-end="$scheduleDatePickerYearEnd"
                                            x-model="endDateDisplay" />
                                    </div>
                                    <div class="{{ $dateTimeConflictNote ? 'modal-field-has-conflict' : '' }}">
                                        <label class="modal-label" for="edit_start_time_{{ $schedule->id }}">เวลาเริ่ม <span class="required-mark">*</span></label>
                                            @php
                                                $editStart = $editOld('start_time', $formatTime($schedule->start_time));
                                                [$editStartHour, $editStartMin] = $editStart ? explode(':', $editStart) : ['08','00'];
                                            @endphp
                                            <input type="hidden" id="edit_start_time_{{ $schedule->id }}" name="start_time" value="{{ $editStart }}">
                                            <div class="time-picker-group">
                                                <div class="time-picker" id="tp_edit_start_{{ $schedule->id }}" data-tp-hidden="edit_start_time_{{ $schedule->id }}" tabindex="0">
                                                    <span class="tp-val tp-val-hour">{{ $editStartHour ?? '08' }}</span>
                                                    <span class="time-separator">:</span>
                                                    <span class="tp-val tp-val-min">{{ $editStartMin ?? '00' }}</span>
                                                    <div class="tp-drop">
                                                        <div class="tp-drop-columns">
                                                            <div class="tp-col tp-col-hour">
                                                                <ul>
                                                                    @for($h = 0; $h < 24; $h++)
                                                                        @php $hh = sprintf('%02d', $h); @endphp
                                                                        <li data-val="{{ $hh }}" class="tp-hour-item {{ $hh === ($editStartHour ?? '08') ? 'tp-sel' : '' }}">{{ $hh }}</li>
                                                                    @endfor
                                                                </ul>
                                                            </div>
                                                            <div class="tp-col-divider">:</div>
                                                            <div class="tp-col tp-col-min">
                                                                <ul>
                                                                    @foreach(range(0,59) as $m)
                                                                        @php $mm = sprintf('%02d', $m); @endphp
                                                                        <li data-val="{{ $mm }}" class="tp-min-item {{ $mm === ($editStartMin ?? '00') ? 'tp-sel' : '' }}">{{ $mm }}</li>
                                                                    @endforeach
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <span class="time-unit">น.</span>
                                            </div>
                                    </div>
                                    <div class="{{ $dateTimeConflictNote ? 'modal-field-has-conflict' : '' }}">
                                        <label class="modal-label" for="edit_end_time_{{ $schedule->id }}">เวลาสิ้นสุด <span class="required-mark">*</span></label>
                                        @php
                                            $editEnd = $editOld('end_time', $formatTime($schedule->end_time));
                                            [$editEndHour, $editEndMin] = $editEnd ? explode(':', $editEnd) : ['09','00'];
                                        @endphp
                                        <input type="hidden" id="edit_end_time_{{ $schedule->id }}" name="end_time" value="{{ $editEnd }}">
                                        <div class="time-picker-group">
                                            <div class="time-picker" id="tp_edit_end_{{ $schedule->id }}" data-tp-hidden="edit_end_time_{{ $schedule->id }}" tabindex="0">
                                                <span class="tp-val tp-val-hour">{{ $editEndHour ?? '09' }}</span>
                                                <span class="time-separator">:</span>
                                                <span class="tp-val tp-val-min">{{ $editEndMin ?? '00' }}</span>
                                                <div class="tp-drop">
                                                    <div class="tp-drop-columns">
                                                        <div class="tp-col tp-col-hour">
                                                            <ul>
                                                                @for($h = 0; $h < 24; $h++)
                                                                    @php $hh = sprintf('%02d', $h); @endphp
                                                                    <li data-val="{{ $hh }}" class="tp-hour-item {{ $hh === ($editEndHour ?? '09') ? 'tp-sel' : '' }}">{{ $hh }}</li>
                                                                @endfor
                                                            </ul>
                                                        </div>
                                                        <div class="tp-col-divider">:</div>
                                                        <div class="tp-col tp-col-min">
                                                            <ul>
                                                                @foreach(range(0,59) as $m)
                                                                    @php $mm = sprintf('%02d', $m); @endphp
                                                                    <li data-val="{{ $mm }}" class="tp-min-item {{ $mm === ($editEndMin ?? '00') ? 'tp-sel' : '' }}">{{ $mm }}</li>
                                                                @endforeach
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <span class="time-unit">น.</span>
                                        </div>
                                        @if($dateTimeConflictNote)
                                            <div class="modal-conflict-field" data-testid="schedule-edit-conflict-focus">{{ $dateTimeConflictNote }}</div>
                                        @endif
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
                                    <div class="{{ $roomConflictNote ? 'modal-field-has-conflict' : '' }}">
                                        <label class="modal-label" for="edit_room_id_{{ $schedule->id }}">ห้อง/สถานที่</label>
                                        <select id="edit_room_id_{{ $schedule->id }}" name="room_id" class="modal-control tpss-choices">
                                            <option value="">ไม่ระบุสถานที่</option>
                                            @foreach($rooms as $roomOption)
                                                <option value="{{ $roomOption->id }}" @selected((string) $editOld('room_id', $schedule->room_id) === (string) $roomOption->id)>
                                                    {{ $roomOption->room_code }} · {{ $roomOption->room_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @if($roomConflictNote)
                                            <div class="modal-conflict-field">{{ $roomConflictNote }}</div>
                                        @endif
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

                                <div class="modal-section {{ $instructorConflictNote ? 'modal-field-has-conflict' : '' }}">
                                    <div class="modal-section-title">ผู้สอน <span class="required-mark">*</span></div>
                                    @php
                                        $editInstructorOptions = $eligibleScheduleInstructors($offering);
                                        $editInstructorSearchItems = $editInstructorOptions
                                            ->map(fn ($instructor) => mb_strtolower($instructor->formatted_name ?? $instructor->name, 'UTF-8'))
                                            ->values();
                                    @endphp
                                    <input type="search" class="modal-choice-search" x-model="editInstructorSearch" placeholder="ค้นหาชื่อผู้สอน" aria-label="ค้นหาผู้สอน">
                                    <div class="modal-choice-grid">
                                        @foreach($editInstructorOptions as $instructor)
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
                                    @if($instructorConflictNote)
                                        <div class="modal-conflict-field">{{ $instructorConflictNote }}</div>
                                    @endif
                                </div>

                                <div class="modal-section">
                                    <label class="modal-label" for="edit_lead_instructor_id_{{ $schedule->id }}">ผู้สอนหลัก</label>
                                    <select id="edit_lead_instructor_id_{{ $schedule->id }}" name="lead_instructor_id" class="modal-control">
                                        <option value="">ไม่ระบุ</option>
                                        @foreach($editInstructorOptions as $instructor)
                                            <option value="{{ $instructor->id }}" @selected($editLeadInstructorId === (string) $instructor->id)>
                                                {{ $instructor->formatted_name ?? $instructor->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="modal-section {{ $groupConflictNote ? 'modal-field-has-conflict' : '' }}">
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
                                    @if($groupConflictNote)
                                        <div class="modal-conflict-field">{{ $groupConflictNote }}</div>
                                    @endif
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

        @if($canCreateInCurrentPeriod)
            <button
                type="button"
                class="floating-create-button"
                data-testid="schedule-floating-create-link"
                x-show="!showCreate && !editModal && !detailModal"
                x-cloak
                @click="openCreate()"
            >
                <span class="floating-create-icon" aria-hidden="true">+</span>
                <span>เพิ่มรายการสอน</span>
            </button>
        @endif

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
                        <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                        <div class="modal-form-body">
                            @if($errors->any())
                                @php
                                    $alertMessages = $scheduleAlertMessages($errors);
                                @endphp
                                <div class="schedule-empty" style="margin-bottom:12px;border-color:var(--status-conflict-border);background:var(--status-conflict-bg);color:var(--status-conflict-fg);font-weight:800;text-align:left;">
                                    @foreach($alertMessages as $message)
                                        <div style="{{ ! $loop->last ? 'margin-bottom:6px;' : '' }}">{{ $message }}</div>
                                    @endforeach
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
                                        :year-start="$scheduleDatePickerYearStart"
                                        :year-end="$scheduleDatePickerYearEnd"
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
                                        :year-start="$scheduleDatePickerYearStart"
                                        :year-end="$scheduleDatePickerYearEnd"
                                        x-model="createEndDate" />
                                </div>
                                <div>
                                    <label class="modal-label" for="start_time">เวลาเริ่ม <span class="required-mark">*</span></label>
                                    @php
                                        $oldStart = old('start_time');
                                        [$oldStartHour, $oldStartMin] = is_string($oldStart) && preg_match('/^\d{2}:\d{2}$/', $oldStart)
                                            ? explode(':', $oldStart)
                                            : [null, null];
                                        $startHourDisplay = $oldStartHour ?: '--';
                                        $startMinDisplay = $oldStartMin ?: '--';
                                    @endphp
                                    <input type="hidden" id="start_time" name="start_time" value="{{ $oldStart }}">
                                    <div class="time-picker-group">
                                        <div class="time-picker" id="tp_start" data-tp-hidden="start_time" tabindex="0">
                                            <span class="tp-val tp-val-hour">{{ $startHourDisplay }}</span>
                                            <span class="time-separator">:</span>
                                            <span class="tp-val tp-val-min">{{ $startMinDisplay }}</span>
                                            <div class="tp-drop">
                                                <div class="tp-drop-columns">
                                                    <div class="tp-col tp-col-hour">
                                                        <ul>
                                                            @for($h = 0; $h < 24; $h++)
                                                                @php $hh = sprintf('%02d', $h); @endphp
                                                                <li data-val="{{ $hh }}" class="tp-hour-item {{ $oldStartHour && $hh === $oldStartHour ? 'tp-sel' : '' }}">{{ $hh }}</li>
                                                            @endfor
                                                        </ul>
                                                    </div>
                                                    <div class="tp-col-divider">:</div>
                                                    <div class="tp-col tp-col-min">
                                                        <ul>
                                                            @foreach(range(0,59) as $m)
                                                                @php $mm = sprintf('%02d', $m); @endphp
                                                                <li data-val="{{ $mm }}" class="tp-min-item {{ $oldStartMin && $mm === $oldStartMin ? 'tp-sel' : '' }}">{{ $mm }}</li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <span class="time-unit">น.</span>
                                    </div>
                                    <div class="modal-inline-error" data-time-error-for="start_time" hidden>กรุณาเลือกเวลาเริ่ม</div>
                                </div>
                                <div>
                                    <label class="modal-label" for="end_time">เวลาสิ้นสุด <span class="required-mark">*</span></label>
                                    @php
                                        $oldEnd = old('end_time');
                                        [$oldEndHour, $oldEndMin] = is_string($oldEnd) && preg_match('/^\d{2}:\d{2}$/', $oldEnd)
                                            ? explode(':', $oldEnd)
                                            : [null, null];
                                        $endHourDisplay = $oldEndHour ?: '--';
                                        $endMinDisplay = $oldEndMin ?: '--';
                                    @endphp
                                    <input type="hidden" id="end_time" name="end_time" value="{{ $oldEnd }}">
                                    <div class="time-picker-group">
                                        <div class="time-picker" id="tp_end" data-tp-hidden="end_time" tabindex="0">
                                            <span class="tp-val tp-val-hour">{{ $endHourDisplay }}</span>
                                            <span class="time-separator">:</span>
                                            <span class="tp-val tp-val-min">{{ $endMinDisplay }}</span>
                                            <div class="tp-drop">
                                                <div class="tp-drop-columns">
                                                    <div class="tp-col tp-col-hour">
                                                        <ul>
                                                            @for($h = 0; $h < 24; $h++)
                                                                @php $hh = sprintf('%02d', $h); @endphp
                                                                <li data-val="{{ $hh }}" class="tp-hour-item {{ $oldEndHour && $hh === $oldEndHour ? 'tp-sel' : '' }}">{{ $hh }}</li>
                                                            @endfor
                                                        </ul>
                                                    </div>
                                                    <div class="tp-col-divider">:</div>
                                                    <div class="tp-col tp-col-min">
                                                        <ul>
                                                            @foreach(range(0,59) as $m)
                                                                @php $mm = sprintf('%02d', $m); @endphp
                                                                <li data-val="{{ $mm }}" class="tp-min-item {{ $oldEndMin && $mm === $oldEndMin ? 'tp-sel' : '' }}">{{ $mm }}</li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <span class="time-unit">น.</span>
                                    </div>
                                    <div class="modal-inline-error" data-time-error-for="end_time" hidden>กรุณาเลือกเวลาสิ้นสุด</div>
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
                                            $createInstructorOptions = $eligibleScheduleInstructors($offeringOption);
                                            $createInstructorSearchItems = $createInstructorOptions
                                                ->map(fn ($instructor) => mb_strtolower($instructor->formatted_name ?? $instructor->name, 'UTF-8'))
                                                ->values();
                                        @endphp
                                        <input type="search" class="modal-choice-search" x-model="createInstructorSearch" placeholder="ค้นหาชื่อผู้สอน" aria-label="ค้นหาผู้สอน">
                                        <div class="modal-choice-grid">
                                            @foreach($createInstructorOptions as $instructor)
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
                                            @foreach($createInstructorOptions as $instructor)
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

<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Conflict pill tooltip: position fixed + delegated handler ───────────
    // ใช้ position:fixed + JS calc เพื่อหนี overflow:hidden ของ card cell
    (function () {
        var openPill = null;

        function place(pill) {
            var tt = pill.querySelector('.conflict-tt');
            if (!tt) return;

            // Show invisibly to measure size
            tt.style.visibility = 'hidden';
            pill.setAttribute('data-tt-open', 'true');
            var ttRect = tt.getBoundingClientRect();
            var pillRect = pill.getBoundingClientRect();
            var vw = window.innerWidth, vh = window.innerHeight;
            var margin = 8;

            // Prefer below pill; flip above if not enough space
            var top = pillRect.bottom + 6;
            if (top + ttRect.height > vh - margin) {
                top = Math.max(margin, pillRect.top - ttRect.height - 6);
            }

            // Align left with pill; clamp inside viewport
            var left = pillRect.left;
            if (left + ttRect.width > vw - margin) {
                left = Math.max(margin, vw - ttRect.width - margin);
            }
            if (left < margin) left = margin;

            tt.style.transform = 'translate(' + Math.round(left) + 'px, ' + Math.round(top) + 'px)';
            tt.style.visibility = '';
        }

        function close(pill) {
            if (!pill) return;
            pill.removeAttribute('data-tt-open');
            var tt = pill.querySelector('.conflict-tt');
            if (tt) tt.style.visibility = '';
        }

        function open(pill) {
            if (openPill && openPill !== pill) close(openPill);
            openPill = pill;
            place(pill);
        }

        document.addEventListener('mouseover', function (e) {
            var pill = e.target.closest('[data-conflict-pill]');
            if (pill && pill !== openPill) open(pill);
        });
        document.addEventListener('mouseout', function (e) {
            var pill = e.target.closest('[data-conflict-pill]');
            if (!pill) return;
            // Don't close if moving to a child of the same pill
            var related = e.relatedTarget;
            if (related && pill.contains(related)) return;
            if (pill === openPill) { close(pill); openPill = null; }
        });
        document.addEventListener('focusin', function (e) {
            var pill = e.target.closest('[data-conflict-pill]');
            if (pill) open(pill);
        });
        document.addEventListener('focusout', function (e) {
            var pill = e.target.closest('[data-conflict-pill]');
            if (pill === openPill) { close(pill); openPill = null; }
        });
        // Reposition on scroll/resize while open
        window.addEventListener('scroll', function () { if (openPill) place(openPill); }, true);
        window.addEventListener('resize', function () { if (openPill) place(openPill); });
    })();

    // ── Custom time-picker engine ───────────────────────────────────────────
    var _openDrop = null; // currently open .tp-drop

    function openDrop(drop, picker) {
        if (_openDrop && _openDrop !== drop) closeDrop(_openDrop);
        _openDrop = drop;

        // position the dropdown fixed below the spin trigger, clamped to viewport
        var rect = picker.getBoundingClientRect();
        var vpH = window.innerHeight;
        var dropH = Math.min(220, vpH - rect.bottom - 8);
        drop.style.maxHeight = Math.max(100, dropH) + 'px';
        drop.style.left = rect.left + 'px';
        drop.style.top  = (rect.bottom + 2) + 'px';
        drop.style.minWidth = Math.max(rect.width, 64) + 'px';
        drop.classList.add('tp-open');

        // scroll selected hour and minute to the top of their columns
        var selHour = drop.querySelector('.tp-hour-item.tp-sel');
        if (selHour) {
            var col = selHour.closest('.tp-col');
            if (col) col.scrollTop = selHour.offsetTop;
        }

        var selMin = drop.querySelector('.tp-min-item.tp-sel');
        if (selMin) {
            var col = selMin.closest('.tp-col');
            if (col) col.scrollTop = selMin.offsetTop;
        }

        picker.classList.add('tp-active');
    }

    function closeDrop(drop) {
        if (!drop) return;
        drop.classList.remove('tp-open');
        var picker = drop.closest('.time-picker');
        if (picker) {
            picker.classList.remove('tp-active');
        }
        if (_openDrop === drop) _openDrop = null;
    }

    function selectTimePart(li, part, picker, drop) {
        var val = li.dataset.val;
        var hiddenId = picker.dataset.tpHidden;
        var hidden = document.getElementById(hiddenId);
        if (!hidden) return;

        var isTimePart = function(value) {
            return /^\d{2}$/.test(value || '');
        };
        var currentHour = isTimePart(picker.dataset.tpHour)
            ? picker.dataset.tpHour
            : (isTimePart(picker.querySelector('.tp-val-hour').textContent.trim()) ? picker.querySelector('.tp-val-hour').textContent.trim() : '');
        var currentMin = isTimePart(picker.dataset.tpMin)
            ? picker.dataset.tpMin
            : (isTimePart(picker.querySelector('.tp-val-min').textContent.trim()) ? picker.querySelector('.tp-val-min').textContent.trim() : '');

        if (part === 'hour') {
            currentHour = val;
            picker.dataset.tpHour = val;
            picker.querySelector('.tp-val-hour').textContent = val;
            drop.querySelectorAll('.tp-hour-item').forEach(function(el) { el.classList.remove('tp-sel'); });
        } else {
            currentMin = val;
            picker.dataset.tpMin = val;
            picker.querySelector('.tp-val-min').textContent = val;
            drop.querySelectorAll('.tp-min-item').forEach(function(el) { el.classList.remove('tp-sel'); });
        }
        li.classList.add('tp-sel');
        hidden.value = currentHour && currentMin ? currentHour + ':' + currentMin : '';

        hidden.dispatchEvent(new Event('change', { bubbles: true }));
        hidden.dispatchEvent(new Event('input', { bubbles: true }));

        var error = document.querySelector(`[data-time-error-for="${hiddenId}"]`);
        if (error && hidden.value) {
            error.hidden = true;
        }
    }

    function initTimePickers() {
        document.querySelectorAll('.time-picker').forEach(function(picker) {
            var drop = picker.querySelector('.tp-drop');
            if (!drop || picker._tpInited) return;
            picker._tpInited = true;

            var hiddenId = picker.dataset.tpHidden;
            var hidden = document.getElementById(hiddenId);
            if (hidden) {
                if (hidden.value) {
                    var parts = hidden.value.split(':');
                    var h = parts[0] || '08';
                    var m = parts[1] || '00';
                    picker.dataset.tpHour = h;
                    picker.dataset.tpMin = m;
                    picker.querySelector('.tp-val-hour').textContent = h;
                    picker.querySelector('.tp-val-min').textContent = m;

                    drop.querySelectorAll('.tp-hour-item').forEach(function(li) {
                        li.classList.toggle('tp-sel', li.dataset.val === h);
                    });
                    drop.querySelectorAll('.tp-min-item').forEach(function(li) {
                        li.classList.toggle('tp-sel', li.dataset.val === m);
                    });
                } else {
                    picker.dataset.tpHour = '';
                    picker.dataset.tpMin = '';
                    picker.querySelector('.tp-val-hour').textContent = '--';
                    picker.querySelector('.tp-val-min').textContent = '--';
                    drop.querySelectorAll('.tp-hour-item, .tp-min-item').forEach(function(li) {
                        li.classList.remove('tp-sel');
                    });
                }
            }

            picker.addEventListener('click', function(e) {
                e.stopPropagation();
                if (drop.classList.contains('tp-open')) {
                    if (!drop.contains(e.target)) closeDrop(drop);
                } else {
                    openDrop(drop, picker);
                }
            });

            picker.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    if (drop.classList.contains('tp-open')) closeDrop(drop);
                    else openDrop(drop, picker);
                } else if (e.key === 'Escape') {
                    closeDrop(drop);
                }
            });

            drop.querySelectorAll('.tp-hour-item').forEach(function(li) {
                li.addEventListener('click', function(e) {
                    e.stopPropagation();
                    selectTimePart(li, 'hour', picker, drop);
                });
            });

            drop.querySelectorAll('.tp-min-item').forEach(function(li) {
                li.addEventListener('click', function(e) {
                    e.stopPropagation();
                    selectTimePart(li, 'min', picker, drop);
                });
            });
        });
    }

    // close on outside click or scroll
    document.addEventListener('click', function(e) {
        if (_openDrop && !_openDrop.contains(e.target) && !e.target.closest('.time-picker')) {
            closeDrop(_openDrop);
        }
    });
    document.addEventListener('scroll', function(e) {
        if (_openDrop && !_openDrop.contains(e.target)) {
            closeDrop(_openDrop);
        }
    }, true);

    // init on load
    initTimePickers();

    var createForm = document.querySelector('form[data-testid="schedule-form"]');
    if (createForm) {
        createForm.addEventListener('submit', function(e) {
            var missing = false;
            ['start_time', 'end_time'].forEach(function(fieldId) {
                var field = document.getElementById(fieldId);
                var error = document.querySelector(`[data-time-error-for="${fieldId}"]`);
                var isEmpty = !field || !field.value;

                if (error) {
                    error.hidden = !isEmpty;
                }
                missing = missing || isEmpty;
            });

            if (missing) {
                e.preventDefault();
                var firstError = createForm.querySelector('[data-time-error-for]:not([hidden])');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    }

    // re-init when Alpine opens an edit modal (new .time-picker may appear)
    document.addEventListener('tpss:edit-opened', initTimePickers);
    // also observe DOM additions for dynamically revealed modals
    var _tpObserver = new MutationObserver(function(muts) {
        var needInit = muts.some(function(m) {
            return Array.from(m.addedNodes).some(function(n) {
                return n.nodeType === 1 && (n.classList && n.classList.contains('time-picker') || n.querySelector && n.querySelector('.time-picker'));
            });
        });
        if (needInit) initTimePickers();
    });
    _tpObserver.observe(document.body, { childList: true, subtree: true });

});
</script>
