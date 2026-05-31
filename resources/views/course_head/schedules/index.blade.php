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
    $seriesCreateAction = (! $isWorkspace && $courseOffering)
        ? route('maker.course_offerings.schedules.series.store', $courseOffering)
        : $createAction;
    $queryOfferingId = request('course_offering_id');
    $selectedOfferingId = (string) old('course_offering_id', $queryOfferingId ?: ($schedulingOfferings->first()?->id ?? $courseOffering?->id ?? ''));
    $oldModalMode = old('modal_mode');
    // Default = สร้างรายการเดียว (รายวัน) — series เป็น opt-in ผ่าน checkbox ใน modal
    $defaultCreateMode = 'single';
    $oldCreateMode = (! $isWorkspace && $courseOffering)
        ? old('create_mode', $defaultCreateMode)
        : 'single';
    $openEditScheduleId = (string) old('edit_schedule_id', request('edit_schedule_id', ''));
    $openEditSeriesTemplateId = (string) old('edit_series_template_id', '');
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
    $openCreateModal = $canEdit && ! $openEditScheduleId && ! $openEditSeriesTemplateId && (
        request('modal') === 'create'
        || $oldModalMode === 'create'
        || old('create_mode')
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
        ? 'นอกช่วงปีการศึกษา ' . ($academicYear?->name ?? '-')
        : null;
    $canCreateInCurrentPeriod = $canEdit && ! $calendarOutsideAcademicYear;
    $outsideCreateHint = 'เลือกวันที่ในช่วงปีการศึกษาก่อนเพิ่มรายการสอน';
    // เช็คว่าวันนี้ตกนอกช่วงปีการศึกษาไหม (per-day)
    $isDayOutsideAcademic = function ($day) use ($academicStartDate, $academicEndDate) {
        if (! $academicStartDate || ! $academicEndDate || ! $day) return false;
        $d = \Carbon\CarbonImmutable::parse($day)->startOfDay();
        return $d->lt($academicStartDate) || $d->gt($academicEndDate);
    };
    // V2: จำแนกวัน (วันหยุด/สัปดาห์สอบ/ปิดภาคเรียน) สำหรับลงสีปฏิทิน — จาก AcademicCalendar
    $academicCalendar = $academicCalendar ?? null;
    $selectedTermId = $selectedTermId ?? null;
    $dayInfo = function ($day) use ($academicCalendar, $selectedTermId) {
        $fallback = ['kind' => 'normal', 'label' => null, 'blocked' => false, 'term_id' => null, 'term_name' => null];
        if (! $academicCalendar || ! $day) return $fallback;
        $info = $academicCalendar->classifyDay($day);
        // ถ้า filter เทอมเจาะจง และวันนี้เป็น "เทอมอื่น" → off-term (เทา + กดเพิ่มไม่ได้ กันวางผิดเทอม)
        if ($selectedTermId && $info['term_id'] && (int) $info['term_id'] !== (int) $selectedTermId) {
            $info['kind'] = 'offterm';
            $info['label'] = $info['term_name'] ?? 'เทอมอื่น';
            $info['blocked'] = true;
        }
        return $info;
    };
    $weekNumberForDate = function ($date) use ($academicStartDate) {
        if (! $academicStartDate || ! $date) return null;
        return max(1, (int) floor(
            $academicStartDate->startOfWeek(\Carbon\CarbonInterface::MONDAY)
                ->diffInDays(
                    \Carbon\CarbonImmutable::parse($date)->startOfDay()->startOfWeek(\Carbon\CarbonInterface::MONDAY),
                    false
                ) / 7
        ) + 1);
    };
    $weekNumberFromAcademicYear = $weekNumberForDate($weekStart);
    // จำนวนสัปดาห์รวมของปีการศึกษา (Monday-aligned)
    $totalAcademicWeeks = ($academicStartDate && $academicEndDate)
        ? max(1, (int) ceil(
            $academicStartDate->startOfWeek(\Carbon\CarbonInterface::MONDAY)
                ->diffInDays($academicEndDate->endOfWeek(\Carbon\CarbonInterface::SUNDAY), false) / 7
        ))
        : 0;
    // Monday-อิงตามสัปดาห์ที่ N ของปีการศึกษา → คืน date string 'YYYY-MM-DD'
    $mondayOfAcademicWeek = function (int $weekNumber) use ($academicStartDate) {
        if (! $academicStartDate || $weekNumber < 1) return null;
        return $academicStartDate->startOfWeek(\Carbon\CarbonInterface::MONDAY)
            ->addWeeks($weekNumber - 1)
            ->toDateString();
    };
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
            : '-';
    };
    $scheduleIncompleteReasons = function ($schedule) use ($scheduleDepartmentInstructors) {
        return collect([
            $scheduleDepartmentInstructors($schedule)->isEmpty() ? 'รอกำหนดผู้สอน' : null,
            $schedule?->studentGroups?->isEmpty() ? 'รอกำหนดกลุ่ม' : null,
        ])->filter()->values();
    };
    $scheduleResourceCopyItems = ($allSchedules ?? collect())
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
    $scheduleFilterItems = $singleCourseSchedules->map(function ($schedule) use ($formatDate, $formatTime, $scheduleDepartmentInstructors, $weekNumberForDate) {
        $instructors = $scheduleDepartmentInstructors($schedule);

        return [
            'id' => (string) $schedule->id,
            'activity' => (string) $schedule->activity_type_id,
            'groups' => $schedule->studentGroups->pluck('id')->map(fn ($id) => (string) $id)->values(),
            'instructors' => $instructors->pluck('id')->map(fn ($id) => (string) $id)->values(),
            'week' => (string) ($weekNumberForDate($schedule->start_date) ?? ''),
            'date' => $schedule->start_date?->toDateString(),
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
    // ลิสต์สัปดาห์ทั้งหมดของปีการศึกษา (1..N) พร้อม count + Monday ของสัปดาห์
    // user เลือกสัปดาห์ว่างก็ได้ เพื่อ jump ไปสร้างรายการ
    $countsByWeek = $scheduleFilterItems
        ->filter(fn ($item) => $item['week'] !== '')
        ->groupBy('week')
        ->map(fn ($items) => $items->count());
    $scheduleWeekOptions = collect(range(1, max(1, $totalAcademicWeeks)))
        ->map(fn ($week) => [
            'week' => $week,
            'count' => (int) ($countsByWeek->get((string) $week) ?? 0),
            'monday' => $mondayOfAcademicWeek($week),
        ])
        ->values();

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
            grid-template-columns: minmax(180px, 1fr) minmax(220px, 1.4fr) repeat(3, minmax(130px, .7fr));
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
        .series-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            min-height: 20px;
            padding: 1px 6px;
            border-radius: 4px;
            border: 1px solid oklch(79% 0.055 245);
            background: oklch(96.5% 0.016 245);
            color: oklch(34% 0.075 245);
            font-size: 10.5px;
            font-weight: 750;
            line-height: 1.2;
            white-space: nowrap;
        }
        .co-sched-table .series-badge {
            max-width: 100%;
            white-space: normal;
            overflow-wrap: anywhere;
        }
        .series-dot {
            width: 5px;
            height: 5px;
            border-radius: 999px;
            background: oklch(52% 0.13 245);
            flex: 0 0 auto;
        }
        .schedule-incomplete-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            min-height: 20px;
            padding: 1px 6px;
            border-radius: 4px;
            border: 1px solid oklch(82% 0.08 82);
            background: oklch(97% 0.032 88);
            color: oklch(36% 0.08 75);
            font-size: 10.5px;
            font-weight: 750;
            line-height: 1.2;
            white-space: nowrap;
        }
        .co-sched-table .schedule-incomplete-badge {
            max-width: 100%;
            white-space: normal;
            overflow-wrap: anywhere;
        }
        .grid-activity .schedule-incomplete-badge,
        .month-activity .schedule-incomplete-badge {
            max-width: 100%;
            white-space: normal;
            overflow-wrap: anywhere;
        }
        .grid-activity .schedule-incomplete-detail,
        .month-activity .schedule-incomplete-detail {
            display: none;
        }
        .schedule-incomplete-dot {
            width: 5px;
            height: 5px;
            flex: 0 0 auto;
            border-radius: 999px;
            background: oklch(58% 0.16 72);
        }
        .schedule-incomplete-detail {
            color: oklch(46% 0.07 75);
            font-weight: 750;
        }
        .grid-activity-top .series-badge,
        .month-activity-tags .series-badge {
            min-height: 18px;
            padding: 1px 6px;
            font-size: 10px;
            font-weight: 900;
            border-radius: 999px;
            background: oklch(96% 0.018 245);
            border-color: oklch(72% 0.05 245);
            color: var(--brand-navy);
        }
        /* Series tooltip variant — same structure as conflict-tt but blue-tinted */
        .series-tt {
            border-color: oklch(79% 0.055 245);
        }
        .series-tt .conflict-tt-head {
            color: oklch(34% 0.075 245);
            border-bottom-color: oklch(88% 0.03 245);
        }
        .series-badge[data-conflict-pill] {
            cursor: default;
            position: relative;
        }
        .series-toggle-panel {
            display: grid;
            gap: 12px;
            margin: 4px 0 14px;
            padding: 16px 18px;
            border: 1px solid var(--schedule-border);
            border-radius: 8px;
            background: color-mix(in oklch, var(--surface) 76%, oklch(97.5% 0.01 232));
        }
        .series-toggle-choice {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            color: var(--fg-1);
            font-size: 13px;
            font-weight: 850;
            line-height: 1.45;
            cursor: pointer;
        }
        .series-toggle-choice input {
            width: 18px;
            height: 18px;
            margin-top: 1px;
            accent-color: var(--brand-navy);
            flex: 0 0 auto;
        }
        .series-toggle-choice small {
            display: block;
            margin-top: 2px;
            color: var(--fg-3);
            font-size: 12px;
            font-weight: 650;
            line-height: 1.45;
        }
        .series-summary {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 14px;
            align-items: start;
            cursor: default;
        }
        .series-summary-title {
            color: var(--fg-1);
            font-size: 17px;
            font-weight: 900;
            line-height: 1.35;
        }
        .series-summary-copy {
            max-width: 64ch;
            margin-top: 4px;
            color: var(--fg-2);
            font-size: 12.5px;
            font-weight: 700;
            line-height: 1.6;
        }
        .series-summary-note {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 32px;
            margin-top: 0;
            padding: 5px 9px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 18%, var(--schedule-border));
            border-radius: 999px;
            background: color-mix(in oklch, var(--surface) 70%, oklch(97.5% 0.01 232));
            color: var(--fg-2);
            font-size: 12px;
            font-weight: 800;
            line-height: 1.3;
        }
        .series-fields {
            display: grid;
            grid-template-columns: repeat(2, minmax(220px, 1fr));
            gap: 14px 18px;
            align-items: start;
        }
        /* ── Copy week ─────────────────────────────────────────── */
        .copy-week-trigger { cursor: pointer; }
        .copy-week-subtitle {
            margin-top: 4px;
            color: var(--fg-3);
            font-size: 12.5px;
            font-weight: 650;
            line-height: 1.5;
        }
        .copy-week-weeks {
            display: flex;
            align-items: stretch;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .copy-week-pill {
            flex: 1 1 200px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 12px 14px;
            border: 1px solid var(--schedule-border);
            border-radius: 8px;
            background: color-mix(in oklch, var(--surface) 80%, oklch(97.5% 0.01 232));
        }
        .copy-week-pill.is-target {
            border-color: color-mix(in oklch, var(--brand-navy) 30%, var(--schedule-border));
        }
        .copy-week-pill-label {
            color: var(--fg-3);
            font-size: 11.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .copy-week-pill-date {
            color: var(--fg-1);
            font-size: 14px;
            font-weight: 850;
        }
        .copy-week-arrow {
            align-self: center;
            color: var(--fg-3);
            font-size: 20px;
            font-weight: 900;
        }
        .copy-week-target-control {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .copy-week-step {
            width: 26px;
            height: 26px;
            border: 1px solid var(--schedule-border);
            border-radius: 6px;
            background: var(--surface);
            color: var(--fg-1);
            font-size: 16px;
            font-weight: 900;
            line-height: 1;
            cursor: pointer;
        }
        .copy-week-step:hover { background: color-mix(in oklch, var(--brand-navy) 8%, var(--surface)); }
        .copy-week-status {
            padding: 10px 12px;
            border: 1px solid var(--schedule-border);
            border-radius: 8px;
            color: var(--fg-2);
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 12px;
        }
        .copy-week-status.is-error {
            border-color: var(--status-conflict-border);
            background: var(--status-conflict-bg);
            color: var(--status-conflict-fg);
        }
        .copy-week-summary {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }
        .copy-week-badge {
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 850;
            border: 1px solid transparent;
        }
        .copy-week-badge.is-ready {
            border-color: color-mix(in oklch, var(--status-ok-border, #16a34a) 60%, transparent);
            background: var(--status-ok-bg, #ecfdf5);
            color: var(--status-ok-fg, #166534);
        }
        .copy-week-badge.is-blocked {
            border-color: var(--status-conflict-border);
            background: var(--status-conflict-bg);
            color: var(--status-conflict-fg);
        }
        .copy-week-list {
            list-style: none;
            margin: 0 0 12px;
            padding: 0;
            display: grid;
            gap: 6px;
        }
        .copy-week-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 10px 12px;
            border: 1px solid var(--schedule-border);
            border-radius: 8px;
            background: var(--surface);
        }
        .copy-week-item.is-blocked {
            border-color: var(--status-conflict-border);
            background: var(--status-conflict-bg);
        }
        .copy-week-mark { font-size: 14px; line-height: 1.5; flex: 0 0 auto; }
        .copy-week-item-body { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
        .copy-week-item-body strong { color: var(--fg-1); font-size: 13.5px; font-weight: 850; }
        .copy-week-item-body small { color: var(--fg-3); font-size: 12px; font-weight: 650; }
        .copy-week-reason { color: var(--status-conflict-fg) !important; font-weight: 750 !important; }
        /* ── Realtime inline conflict warnings ─────────────────── */
        .field-live-error {
            margin-top: 6px;
            padding: 7px 10px;
            border: 1px solid var(--status-conflict-border);
            border-radius: 6px;
            background: var(--status-conflict-bg);
            color: var(--status-conflict-fg);
            font-size: 12px;
            font-weight: 750;
            line-height: 1.45;
            display: grid;
            gap: 2px;
        }
        .has-live-error {
            border-color: var(--status-conflict-border) !important;
            box-shadow: 0 0 0 2px color-mix(in oklch, var(--status-conflict-border) 30%, transparent);
        }
        .schedule-live-block {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 0 10px;
            padding: 9px 12px;
            border: 1px solid var(--status-conflict-border);
            border-radius: 8px;
            background: var(--status-conflict-bg);
            color: var(--status-conflict-fg);
            font-size: 12.5px;
            font-weight: 800;
        }
        .schedule-live-block-icon { font-size: 14px; }
        /* ── Date hint + multi-day toggle ──────────────────────── */
        .date-day-hint {
            margin-top: 5px;
            display: inline-flex;
            align-items: center;
            padding: 2px 9px;
            border-radius: 999px;
            background: color-mix(in oklch, var(--brand-navy) 8%, var(--surface));
            border: 1px solid color-mix(in oklch, var(--brand-navy) 20%, var(--schedule-border));
            color: var(--brand-navy);
            font-size: 12px;
            font-weight: 800;
        }
        /* V2: เตือนวันหยุด (ไม่บล็อก) — โทนอำพันตาม semantic warning */
        .date-holiday-warn {
            margin-top: 5px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 2px 9px;
            border-radius: 999px;
            background: var(--status-warning-bg, oklch(95% 0.05 85));
            border: 1px solid var(--status-warning-border, oklch(85% 0.08 80));
            color: var(--status-warning-fg, oklch(45% 0.1 70));
            font-size: 12px;
            font-weight: 800;
        }
        .schedule-multiday-toggle {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-top: 9px;
            cursor: pointer;
            color: var(--fg-2);
            font-size: 12.5px;
            font-weight: 750;
            line-height: 1.4;
        }
        .schedule-multiday-toggle input {
            width: 16px;
            height: 16px;
            margin-top: 1px;
            accent-color: var(--brand-navy);
            flex: 0 0 auto;
        }
        .schedule-multiday-toggle small {
            display: block;
            margin-top: 1px;
            color: var(--fg-3);
            font-weight: 650;
            font-size: 11.5px;
        }
        .schedule-date-fields {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px 18px;
            align-items: start;
        }
        @media (max-width: 640px) {
            .schedule-date-fields { grid-template-columns: 1fr; }
        }
        .schedule-type-head {
            font-size: 12px;
            font-weight: 850;
            color: var(--fg-2);
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 8px;
        }
        .schedule-type-seg {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
        }
        .schedule-type-opt {
            display: flex;
            flex-direction: column;
            gap: 2px;
            padding: 10px 12px;
            border: 1px solid var(--schedule-border);
            border-radius: 8px;
            background: var(--surface);
            cursor: pointer;
            text-align: left;
            transition: border-color 0.12s, background 0.12s;
        }
        .schedule-type-opt strong { font-size: 13.5px; font-weight: 850; color: var(--fg-1); }
        .schedule-type-opt small { font-size: 11.5px; font-weight: 650; color: var(--fg-3); line-height: 1.35; }
        .schedule-type-opt:hover { border-color: var(--schedule-border-strong); }
        .schedule-type-opt.is-active {
            border-color: var(--brand-navy);
            background: color-mix(in oklch, var(--brand-navy) 8%, var(--surface));
            box-shadow: inset 0 0 0 1px var(--brand-navy);
        }
        .schedule-type-opt.is-active strong { color: var(--brand-navy); }
        @media (max-width: 640px) {
            .schedule-type-seg { grid-template-columns: 1fr; }
        }
        .series-range-switch {
            min-height: 44px;
            height: 44px;
            align-items: center;
            padding: 8px 12px;
            border: 1px solid var(--schedule-border);
            border-radius: 8px;
            background: var(--surface);
        }
        .series-range-placeholder {
            visibility: hidden;
            pointer-events: none;
        }
        .series-range-switch input {
            margin-top: 0;
        }
        .series-range-switch span {
            display: flex;
            min-width: 0;
            align-items: baseline;
            gap: 8px;
            flex-wrap: wrap;
            line-height: 1.25;
        }
        .series-range-switch small {
            display: inline;
            margin-top: 0;
            line-height: 1.25;
        }
        .series-range-fields {
            grid-column: 1 / -1;
            display: none;
            grid-template-columns: repeat(2, minmax(220px, 1fr));
            gap: 12px 16px;
            padding-top: 2px;
        }
        .series-range-panel:has(.series-range-checkbox:checked) .series-range-fields {
            display: grid;
        }
        .series-fields .tdi-wrap,
        .series-fields .tdi-control,
        .series-fields .tdi-input-cal {
            width: 100%;
        }
        .resource-copy-panel {
            border: 1px solid color-mix(in oklch, var(--brand-navy) 16%, var(--border));
            background: color-mix(in oklch, var(--info-bg) 58%, var(--surface));
            border-radius: 8px;
            padding: 12px;
            margin: 12px 0;
        }
        .resource-copy-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            align-items: end;
        }
        .resource-copy-note {
            color: var(--fg-2);
            font-size: 12px;
            font-weight: 700;
            line-height: 1.55;
            margin-top: 6px;
        }
        @media (max-width: 760px) {
            .series-summary {
                grid-template-columns: 1fr;
            }
            .series-summary-note {
                justify-self: start;
            }
            .series-fields,
            .series-range-fields {
                grid-template-columns: 1fr;
            }
            .series-range-switch {
                height: auto;
                min-height: 52px;
                align-items: flex-start;
            }
            .series-range-placeholder {
                display: none;
            }
            .series-range-switch input {
                margin-top: 2px;
            }
            .series-range-switch span,
            .series-range-switch small {
                display: block;
            }
            .series-range-switch small {
                margin-top: 2px;
            }
            .resource-copy-row {
                grid-template-columns: 1fr;
            }
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
            overflow-x: hidden;
            overflow-y: visible;
            border: 1px solid var(--schedule-border);
            border-radius: 0 0 10px 10px;
            background: var(--surface);
            box-shadow: none;
            margin-top: 0;
        }
        /* ── วันนอกช่วงปีการศึกษา — soft gray + disable interaction ── */
        .is-outside-academic {
            background: oklch(96.2% 0.003 232) !important;
            color: oklch(62% 0.012 232) !important;
            cursor: not-allowed;
        }
        .grid-cell.grid-head.is-outside-academic {
            color: oklch(58% 0.012 232) !important;
            font-weight: 600;
        }
        .grid-cell.grid-head.is-outside-academic .caption {
            opacity: 0.7;
        }
        .month-calendar-day.is-outside-academic .month-day-number {
            color: oklch(62% 0.012 232);
            opacity: 0.75;
        }
        .month-calendar-day.is-outside-academic .month-empty {
            display: none;
        }
        .month-calendar-day.is-outside-academic .month-day-items {
            pointer-events: none;
        }
        /* ── V2: วันพิเศษในปฏิทิน (วันหยุด / สัปดาห์สอบ / ปิดภาคเรียน) ── */
        .grid-cell.day-holiday { background: oklch(97% 0.035 85); }
        .grid-cell.day-exam    { background: oklch(93.5% 0.004 232); cursor: not-allowed; }
        .grid-cell.day-break   { background: oklch(95.5% 0.008 250); cursor: not-allowed; }
        .grid-cell.day-offterm { background: oklch(95% 0.003 250); cursor: not-allowed; opacity: 0.85; }
        .grid-cell.grid-head.day-holiday { color: oklch(52% 0.09 70) !important; }
        .grid-cell.grid-head.day-exam,
        .grid-cell.grid-head.day-break,
        .grid-cell.grid-head.day-offterm { color: oklch(50% 0.012 232) !important; }
        .grid-day-chip {
            display: block; max-width: 100%; margin: 3px auto 0; padding: 0 6px; border-radius: 2px;
            font-size: 9.5px; font-weight: 700; line-height: 15px; letter-spacing: 0.02em;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .grid-day-chip.is-holiday { background: oklch(90% 0.07 80); color: oklch(42% 0.1 65); }
        .grid-day-chip.is-exam    { background: oklch(88% 0.006 232); color: oklch(40% 0.014 232); }
        .grid-day-chip.is-break   { background: oklch(90% 0.01 250); color: oklch(42% 0.014 250); }
        .grid-day-chip.is-offterm { background: oklch(89% 0.006 255); color: oklch(42% 0.03 262); }
        .month-calendar-day.day-holiday { background: oklch(97.5% 0.03 85); }
        .month-calendar-day.day-exam    { background: oklch(94.5% 0.004 232); }
        .month-calendar-day.day-break   { background: oklch(96% 0.008 250); }
        .month-day-flag {
            font-size: 9.5px; font-weight: 700; padding: 0 5px; border-radius: 2px; line-height: 14px;
            display: inline-block; margin-top: 2px;
        }
        .month-day-flag.is-holiday { background: oklch(90% 0.07 80); color: oklch(42% 0.1 65); }
        .month-day-flag.is-exam    { background: oklch(88% 0.006 232); color: oklch(40% 0.014 232); }
        .month-day-flag.is-break   { background: oklch(90% 0.01 250); color: oklch(42% 0.014 250); }
        .month-day-flag.is-offterm { background: oklch(89% 0.006 255); color: oklch(42% 0.03 262); }
        .month-calendar-day.day-offterm { background: oklch(96.5% 0.003 250); opacity: 0.8; }
        /* list: day badge เป็นสีเทาเมื่อเป็นสัปดาห์สอบ (แทนสีวัน) */
        .co-day-badge.is-exam-day { background: oklch(88% 0.006 232) !important; color: oklch(40% 0.014 232) !important; }
        /* term badge บนการ์ดกิจกรรม + แถวรายการ */
        .term-badge {
            display: inline-block; padding: 0 7px; border-radius: 2px; font-size: 10px; font-weight: 700;
            background: oklch(92% 0.03 255); color: oklch(40% 0.08 262); border: 1px solid oklch(86% 0.04 258); white-space: nowrap;
        }
        /* ── หัวกลุ่ม "วัน" ในตารางแบบรายการ — กดได้เพื่อย่อ/ขยาย ── */
        .sched-day-group-header {
            scroll-margin-top: 80px;
            cursor: pointer;
            transition: background 0.15s;
        }
        .sched-day-group-header:focus-visible {
            outline: 2px solid var(--brand-navy);
            outline-offset: -2px;
        }
        /* selector เจาะจง (tr+td) เพื่อชนะ .co-sched-row td:first-child ที่ specificity สูง — กันแถบแรกสีเพี้ยน */
        tr.sched-day-group-header td.sched-day-group-cell {
            padding: 11px 16px;
            /* หัวข้อวัน = พื้น tint + แถบซ้ายบาง ๆ → อ่านออกว่าเป็นหัวข้อ แต่เส้นบาง สีอ่อน ไม่เข้ม */
            background: oklch(96% 0.008 232);
            color: var(--fg-1);
            /* เลิกใช้ position:sticky — บน border-collapse ทำให้เกิดช่องว่าง 13px ใต้หัวคอลัมน์ (Chromium) */
            border-top: 1px solid var(--schedule-border);
            border-bottom: 1px solid var(--schedule-border);
            box-shadow: inset 3px 0 0 0 color-mix(in oklch, var(--brand-navy) 45%, transparent);
        }
        .sched-day-group-header.is-collapsed td.sched-day-group-cell {
            background: oklch(97.5% 0.004 232);
            box-shadow: inset 3px 0 0 0 var(--schedule-border-strong);
        }
        .sched-day-group-inner {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sched-day-group-chevron {
            color: var(--brand-navy);
            transition: transform 0.2s ease;
            flex-shrink: 0;
        }
        .sched-day-group-chevron.is-collapsed {
            transform: rotate(-90deg);
        }
        .sched-day-group-hint {
            font-size: 12px;
            font-weight: 600;
            color: var(--fg-3);
            font-style: italic;
        }
        .sched-day-group-badge {
            margin-bottom: 0;
            font-size: 14px !important;
            font-weight: 900 !important;
            padding: 5px 14px !important;
            border-radius: 6px;
            letter-spacing: 0.02em;
        }
        .sched-day-group-date {
            font-size: 14px;
            font-weight: 800;
            color: var(--fg-1);
            font-variant-numeric: tabular-nums;
            letter-spacing: 0.01em;
        }
        .sched-day-group-week {
            display: inline-flex;
            align-items: center;
            padding: 3px 9px;
            border: 1px solid var(--brand-navy);
            border-radius: 999px;
            background: #fff;
            color: var(--brand-navy);
            font-size: 11.5px;
            font-weight: 800;
            letter-spacing: 0.02em;
            white-space: nowrap;
        }
        .sched-day-group-count {
            font-size: 13px;
            font-weight: 700;
            color: var(--brand-navy);
        }
        .sched-day-add {
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border: 1px solid var(--brand-navy);
            border-radius: 6px;
            background: var(--surface);
            color: var(--brand-navy);
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
            cursor: pointer;
            transition: background 0.12s, color 0.12s;
        }
        .sched-day-add:hover {
            background: var(--brand-navy);
            color: #fff;
        }
        .sched-day-group-header.is-empty-day {
            cursor: default;
        }
        tr.sched-day-group-header.is-empty-day td.sched-day-group-cell {
            background: oklch(97.7% 0.006 232);
            box-shadow: inset 3px 0 0 0 color-mix(in oklch, var(--schedule-muted) 34%, transparent);
        }
        .sched-day-group-count.is-muted {
            color: var(--fg-3);
            font-weight: 700;
        }
        .sched-day-empty-copy {
            margin-left: 2px;
            color: var(--fg-3);
            font-size: 12.5px;
            font-weight: 600;
        }
        /* ── Week filter — custom dropdown ── */
        .week-filter {
            position: relative;
        }
        .week-filter-trigger {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            width: 100%;
            min-height: 42px;
            padding: 8px 14px;
            border: 1px solid var(--schedule-border-strong);
            border-radius: 8px;
            background: var(--surface);
            color: var(--fg-1);
            font: inherit;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 0.01em;
            cursor: pointer;
            white-space: nowrap;
            transition: border-color 0.12s, background 0.12s;
        }
        .week-filter-trigger:hover {
            border-color: var(--brand-navy);
        }
        .week-filter-trigger.is-active {
            background: var(--brand-navy);
            border-color: var(--brand-navy);
            color: #fff;
        }
        .week-filter-trigger-chevron { color: var(--fg-3); }
        .week-filter-trigger.is-active .week-filter-trigger-chevron { color: rgba(255, 255, 255, 0.8); }
        .week-filter-trigger-label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .week-filter-trigger-chevron {
            flex-shrink: 0;
            transition: transform 0.18s;
        }
        .week-filter-trigger-chevron.is-open {
            transform: rotate(180deg);
        }
        .week-filter-pop {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            z-index: 30;
            max-height: 320px;
            overflow-y: auto;
            padding: 4px;
            border: 1px solid var(--schedule-border-strong);
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 12px 28px -8px rgba(15, 30, 60, 0.22), 0 2px 6px rgba(15, 30, 60, 0.08);
        }
        .week-filter-pop::-webkit-scrollbar {
            width: 10px;
        }
        .week-filter-pop::-webkit-scrollbar-track {
            background: transparent;
        }
        .week-filter-pop::-webkit-scrollbar-thumb {
            background: oklch(82% 0.025 232);
            border-radius: 999px;
            border: 2px solid #fff;
        }
        .week-filter-section {
            padding: 8px 12px 4px;
            font-size: 11px;
            font-weight: 800;
            color: var(--fg-3);
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .week-filter-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            width: 100%;
            padding: 8px 12px;
            border: 0;
            border-radius: 7px;
            background: transparent;
            color: var(--fg-1);
            font: inherit;
            font-size: 13px;
            font-weight: 600;
            text-align: left;
            cursor: pointer;
            transition: background 0.1s;
        }
        .week-filter-item:hover {
            background: var(--brand-navy-50);
        }
        .week-filter-item.is-selected {
            background: var(--brand-navy);
            color: #fff;
        }
        .week-filter-item.is-empty {
            color: var(--fg-3);
            font-weight: 500;
        }
        .week-filter-item.is-empty.is-selected {
            color: #fff;
        }
        .week-filter-item.is-all {
            font-weight: 800;
            color: var(--brand-navy);
        }
        .week-filter-item.is-all.is-selected {
            color: #fff;
        }
        .week-filter-count {
            font-size: 12px;
            font-weight: 700;
            color: var(--brand-navy);
            white-space: nowrap;
        }
        .week-filter-item.is-selected .week-filter-count {
            color: oklch(92% 0.018 232);
        }
        .week-filter-check {
            font-weight: 900;
            color: inherit;
        }
        .co-sched-table {
            width: 100%;
            table-layout: fixed;
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
            cursor: pointer;
            border-bottom: 1px solid var(--schedule-border);
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
            width: 12%;
            min-width: 0;
        }
        .co-col-time {
            width: 13%;
            min-width: 0;
        }
        .co-col-activity {
            width: 34%;
            min-width: 0;
        }
        .co-col-groups {
            width: 12%;
            min-width: 0;
        }
        .co-col-instructors {
            width: 14%;
            min-width: 0;
        }
        .co-col-location {
            width: 15%;
            min-width: 0;
        }
        @media (max-width: 900px) {
            .co-sched-table-wrap {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            .co-sched-table {
                min-width: 880px;
            }
            .co-col-date     { width: 110px; }
            .co-col-time     { width: 110px; }
            .co-col-activity { width: 280px; }
            .co-col-groups   { width: 110px; }
            .co-col-instructors { width: 140px; }
            .co-col-location { width: 130px; }
            .co-sched-table th,
            .co-sched-row td {
                padding: 10px 12px;
            }
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
            overflow-wrap: anywhere;
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
            max-width: 100%;
            white-space: normal;
            overflow-wrap: anywhere;
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
            max-width: 100%;
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
            overflow-wrap: anywhere;
        }
        .co-location-room {
            font-size: 12.5px;
            color: var(--fg-1);
            font-weight: 700;
            overflow-wrap: anywhere;
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
        /* ช่องว่างในตาราง grid — คลิกเพื่อเพิ่มกิจกรรม (เติมวัน+เวลาให้) */
        .grid-cell.is-addable {
            position: relative;
            cursor: pointer;
            transition: background-color 0.16s, box-shadow 0.16s;
        }
        .grid-cell.is-addable::before {
            content: "+";
            position: absolute;
            bottom: 8px;
            right: 8px;
            width: 24px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 7px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 10%, var(--schedule-border));
            border-bottom-color: color-mix(in oklch, var(--brand-navy) 16%, var(--schedule-border));
            background: linear-gradient(
                180deg,
                color-mix(in oklch, var(--surface) 96%, oklch(96% 0.016 232)),
                color-mix(in oklch, var(--brand-navy) 2%, var(--surface))
            );
            color: color-mix(in oklch, var(--brand-navy) 30%, var(--schedule-muted));
            font-size: 15px;
            font-weight: 800;
            line-height: 1;
            opacity: 0.34;
            pointer-events: none;
            box-shadow: 0 1px 2px oklch(0% 0 0 / 0.025), inset 0 1px 0 oklch(100% 0 0 / 0.5);
            transition: opacity 0.16s, border-color 0.16s, background 0.16s, color 0.16s, transform 0.16s, box-shadow 0.16s;
        }
        .grid-cell.is-addable:hover,
        .grid-cell.is-addable:focus-visible {
            background-color: color-mix(in oklch, var(--brand-navy) 4%, var(--surface));
            box-shadow: inset 0 0 0 1px color-mix(in oklch, var(--brand-navy) 16%, transparent);
        }
        .grid-cell.is-addable:hover::before,
        .grid-cell.is-addable:focus-visible::before {
            opacity: 1;
            border-color: color-mix(in oklch, var(--brand-navy) 72%, var(--schedule-border));
            background: var(--brand-navy);
            color: var(--surface);
            transform: translateY(-1px);
            box-shadow: 0 4px 10px oklch(0% 0 0 / 0.11);
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
            max-width: 100%;
            min-width: 0;
            white-space: normal;
            overflow-wrap: anywhere;
            text-align: left;
            line-height: 1.2;
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
        .schedule-action-menu {
            position: relative;
            display: inline-flex;
        }
        .schedule-action-menu-list {
            position: absolute;
            right: 0;
            bottom: calc(100% + 4px);
            min-width: 156px;
            background: var(--surface);
            border: 1px solid var(--schedule-border);
            border-radius: 6px;
            box-shadow: 0 4px 14px oklch(0% 0 0 / 0.1);
            padding: 3px;
            display: flex;
            flex-direction: column;
            gap: 1px;
            z-index: 30;
        }
        .schedule-action-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 8px;
            border: 0;
            background: transparent;
            color: var(--fg-1);
            font-size: 11.5px;
            font-weight: 700;
            text-align: left;
            border-radius: 4px;
            cursor: pointer;
            white-space: nowrap;
        }
        .schedule-action-item svg {
            width: 12px;
            height: 12px;
            flex: 0 0 12px;
        }
        .schedule-action-item:hover,
        .schedule-action-item:focus-visible {
            background: oklch(95% 0.012 245);
            outline: none;
        }
        .schedule-action-item.is-danger {
            color: oklch(48% 0.18 28);
        }
        .schedule-action-item.is-danger:hover,
        .schedule-action-item.is-danger:focus-visible {
            background: oklch(95% 0.04 28);
        }
        .schedule-action-divider {
            height: 1px;
            background: var(--schedule-border);
            margin: 3px 2px;
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
            box-sizing: border-box;
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
        .modal-control:not(textarea) {
            height: 44px;
            min-height: 44px;
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
            height: 44px;
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
            width: 238px;
            --tp-row-height: 42px;
        }
        .tp-drop::before {
            content: "";
            position: absolute;
            left: 10px;
            right: 10px;
            top: 50%;
            height: var(--tp-row-height);
            transform: translateY(-50%);
            border: 1.5px solid color-mix(in oklch, var(--brand-navy) 34%, var(--schedule-border));
            border-radius: 8px;
            background:
                linear-gradient(
                    90deg,
                    color-mix(in oklch, var(--brand-navy) 5%, var(--surface)) 0 calc(50% - 12px),
                    transparent calc(50% - 12px) calc(50% + 12px),
                    color-mix(in oklch, var(--brand-navy) 5%, var(--surface)) calc(50% + 12px) 100%
                );
            pointer-events: none;
            z-index: 0;
        }
        .tp-drop::after {
            content: "";
            position: absolute;
            left: 14px;
            right: 14px;
            top: 50%;
            height: 1px;
            background: color-mix(in oklch, var(--brand-navy) 18%, transparent);
            pointer-events: none;
            z-index: 0;
        }
        .tp-drop.tp-open {
            display: block;
        }
        .tp-drop-columns {
            display: flex;
            position: relative;
            z-index: 1;
            height: 220px;
            width: 100%;
            background: var(--surface);
        }
        .tp-col {
            flex: 1;
            overflow-y: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
            padding: 0;
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
            position: relative;
            z-index: 3;
            height: var(--tp-row-height);
            align-self: center;
            border-radius: 6px;
            background: var(--surface);
        }
        .tp-col ul {
            list-style: none;
            padding: calc((220px - var(--tp-row-height)) / 2) 0;
            margin: 0;
            width: 100%;
        }
        .tp-col li {
            height: var(--tp-row-height);
            min-height: var(--tp-row-height);
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-align: center;
            color: var(--fg-1, #1e293b);
            transition: background 0.08s, color 0.08s, box-shadow 0.08s;
            border-radius: 7px;
            margin: 0 7px;
            white-space: nowrap;
            position: relative;
            z-index: 2;
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
            editSeriesModal: @js($openEditSeriesTemplateId ? 'schedule-' . $openEditSeriesTemplateId : null),
            showCreate: @js($openCreateModal),
            focusedScheduleId: @js($focusedScheduleId),
            initialSelectedOfferingId: @js($selectedOfferingId),
            selectedOfferingId: @js($selectedOfferingId),
            scheduleItems: @js($scheduleFilterItems),
            scheduleResourceCopies: @js($scheduleResourceCopyItems->keyBy('id')),
            scheduleSearch: '',
            scheduleActivity: '',
            scheduleGroup: '',
            scheduleInstructor: '',
            scheduleWeek: '',
            scheduleWeeks: @js($scheduleWeekOptions->mapWithKeys(fn ($option) => [(string) $option['week'] => [
                'week' => (string) $option['week'],
                'monday' => $option['monday'],
            ]])->toArray() ?: (object) []),
            collapsedDays: @js($groupedSchedules->keys()->mapWithKeys(fn ($k) => [str_replace('-', '', $k) => false])->toArray() ?: (object) []),
            schedulePeriod: @js($schedulePeriod ?? 'week'),
            includeWeekends: @js((bool) ($includeWeekends ?? false)),
            gridJumpDate: @js($formatDate($selectedScheduleDate ?? $weekStart)),
            defaultCreateDate: @js(($schedulePeriod ?? 'week') === 'day' ? ($selectedScheduleDate ?? $weekStart)->toDateString() : null),
            calendarAllowsCreate: @js($canCreateInCurrentPeriod),
            createInstructorSearch: '',
            createGroupSearch: '',
            createMode: @js($oldCreateMode),
            defaultCreateMode: @js($defaultCreateMode),
            normalCreateAction: @js($createAction),
            seriesCreateAction: @js($seriesCreateAction),
            defaultSeriesEndWeek: @js(max(1, (int) ($courseOffering->teaching_weeks ?? 1))),
            createStartDate: @js(old('start_date') ? $formatDate(\Carbon\CarbonImmutable::parse(old('start_date'))) : ''),
            createEndDate: @js(old('end_date') ? $formatDate(\Carbon\CarbonImmutable::parse(old('end_date'))) : ''),
            createMultiDay: @js((bool) (old('start_date') && old('end_date') && old('start_date') !== old('end_date'))),
            academicStartMonday: @js($academicStartDate ? $academicStartDate->startOfWeek(\Carbon\CarbonInterface::MONDAY)->toDateString() : null),
            scheduleDateHint(thaiStr) {
                const info = window.tpssDateInfo(thaiStr, this.academicStartMonday);
                if (!info) return '';
                return info.week ? `${info.dayName} · สัปดาห์ที่ ${info.week}` : info.dayName;
            },
            // V2: เตือน (ไม่บล็อก) เมื่อวันที่เลือกตรงวันหยุดราชการ
            scheduleDateWarning(thaiStr) {
                const info = window.tpssDateInfo(thaiStr, this.academicStartMonday);
                if (!info || !info.iso) return '';
                const name = (window.tpssHolidays || {})[info.iso];
                return name ? `วันหยุด: ${name} — งดการเรียนการสอน` : '';
            },
            csrf: @js(csrf_token()),
            copyWeekOpen: false,
            copyWeekCurrent: @js(($weekStart ?? null) ? $weekStart->toDateString() : null),
            copyWeekSource: @js(($weekStart ?? null) ? $weekStart->subDays(7)->toDateString() : null),
            copyWeekTarget: @js(($weekStart ?? null) ? $weekStart->toDateString() : null),
            copyWeekPreview: null,
            copyWeekLoading: false,
            copyWeekError: '',
            liveIssues: {},
            liveChecking: false,
            liveTimer: null,
            get liveBlocking() { return Object.keys(this.liveIssues).length > 0; },
            liveIssue(field) { return this.liveIssues[field] || []; },
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
                // กิจกรรมวันเดียว: ให้วันจบ = วันเริ่ม อัตโนมัติ (ผู้ใช้กรอกช่องเดียว)
                this.$watch('createStartDate', (v) => { if (!this.createMultiDay) this.createEndDate = v; });
                this.$watch('createMultiDay', (v) => { if (!v) this.createEndDate = this.createStartDate; });
                this.$watch('editModal', (val) => {
                    this.liveIssues = {};
                    if (this.liveTimer) { clearTimeout(this.liveTimer); this.liveTimer = null; }
                    if (val) {
                        this.$nextTick(() => {
                            window.tpssInitChoices(document.querySelector('.schedule-modal.is-form'));
                            // ตรวจการชนทันทีจากค่าที่กรอกไว้แล้วในรายการที่เปิดแก้ (selector ใช้ single quote เท่านั้น — กัน x-data attribute พัง)
                            const editForm = document.querySelector('form[data-schedule-check]');
                            if (editForm) this.runScheduleCheck(editForm);
                        });
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

                // Ensure grid rows reflect any dynamic changes (e.g., edits made via modals)
                this.$nextTick(() => {
                    window.requestAnimationFrame(() => {
                        if (typeof window.tpssRecomputeGridRows === 'function') window.tpssRecomputeGridRows();
                    });
                });
            },
            setCreateMode(mode) {
                this.createMode = mode === 'series' ? 'series' : 'single';

                if (this.createMode === 'series') {
                    this.closeCreateDatePickers();
                    this.restoreSeriesWeekDefaults();
                }
            },
            closeCreateDatePickers() {
                document.dispatchEvent(new CustomEvent('tpss:close-date-popovers'));
            },
            openCopyWeek() {
                this.copyWeekError = '';
                this.copyWeekPreview = null;
                // มาที่ = สัปดาห์ที่กำลังดูอยู่ (มักเป็นสัปดาห์ว่าง), ดึงจาก = สัปดาห์ก่อนหน้าเป็น default
                this.copyWeekTarget = this.copyWeekCurrent;
                this.copyWeekSource = window.tpssShiftIso(this.copyWeekCurrent, -7);
                this.copyWeekOpen = true;
                this.$nextTick(() => this.refreshCopyWeekPreview());
            },
            shiftCopyWeekSource(weeks) {
                if (!this.copyWeekSource) return;
                const next = window.tpssShiftIso(this.copyWeekSource, weeks * 7);
                if (next === this.copyWeekTarget) return; // ต้นทางต้องไม่ใช่สัปดาห์เดียวกับปลายทาง
                this.copyWeekSource = next;
                this.refreshCopyWeekPreview();
            },
            shiftCopyWeekTarget(weeks) {
                if (!this.copyWeekTarget) return;
                const next = window.tpssShiftIso(this.copyWeekTarget, weeks * 7);
                if (next === this.copyWeekSource) return; // ปลายทางต้องไม่ใช่สัปดาห์เดียวกับต้นทาง
                this.copyWeekTarget = next;
                this.refreshCopyWeekPreview();
            },
            async refreshCopyWeekPreview() {
                const url = @js((! $isWorkspace && $courseOffering) ? route('maker.course_offerings.schedules.copy_week.preview', $courseOffering) : '');
                if (!url || !this.copyWeekSource || !this.copyWeekTarget) return;
                this.copyWeekLoading = true;
                this.copyWeekError = '';
                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                        body: JSON.stringify({ source_week_start: this.copyWeekSource, target_week_start: this.copyWeekTarget }),
                    });
                    if (!res.ok) throw new Error('preview failed');
                    this.copyWeekPreview = await res.json();
                } catch (e) {
                    this.copyWeekError = 'ไม่สามารถตรวจสอบได้ ลองใหม่อีกครั้ง';
                } finally {
                    this.copyWeekLoading = false;
                }
            },
            submitCopyWeek() {
                if (this.$refs.copyWeekForm) this.$refs.copyWeekForm.submit();
            },
            queueScheduleCheck(formEl) {
                const form = formEl || this.$refs.createForm;
                if (this.liveTimer) clearTimeout(this.liveTimer);
                this.liveTimer = setTimeout(() => this.runScheduleCheck(form), 400);
            },
            async runScheduleCheck(formEl) {
                const url = @js((! $isWorkspace && $courseOffering) ? route('maker.course_offerings.schedules.check_conflicts', $courseOffering) : '');
                const form = formEl || this.$refs.createForm;
                if (!url || !form) return;
                this.liveChecking = true;
                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                        body: new FormData(form),
                    });
                    if (!res.ok) throw new Error('check failed');
                    const data = await res.json();
                    this.liveIssues = data.fields || {};
                } catch (e) {
                    this.liveIssues = {};
                } finally {
                    this.liveChecking = false;
                }
            },
            restoreSeriesWeekDefaults() {
                this.$nextTick(() => {
                    const form = this.$refs.createForm;
                    if (!form) return;

                    const startWeek = form.querySelector('#series_start_week');
                    const endWeek = form.querySelector('#series_end_week');

                    if (startWeek && !startWeek.value) {
                        startWeek.value = startWeek.dataset.defaultValue || '1';
                    }

                    if (endWeek && !endWeek.value) {
                        endWeek.value = endWeek.dataset.defaultValue || String(this.defaultSeriesEndWeek || 1);
                    }
                });
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
            applyResourceCopy(form, sourceId) {
                const source = this.scheduleResourceCopies[String(sourceId || '')];
                if (!source || !form) return;

                const setSelect = (name, value) => {
                    const select = form.querySelector(`[name='${name}']`);
                    if (!select) return;
                    select.value = value || '';
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    select.dispatchEvent(new Event('input', { bubbles: true }));
                    select._tpssSelect?.sync?.();
                };
                const setTextarea = (name, value) => {
                    const field = form.querySelector(`[name='${name}']`);
                    if (!field) return;
                    field.value = value || '';
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                };
                const setCheckedGroup = (name, values) => {
                    const selected = new Set((values || []).map((value) => String(value)));
                    form.querySelectorAll(`input[name='${name}[]']`).forEach((input) => {
                        input.checked = selected.has(String(input.value));
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                };

                setSelect('room_id', source.room_id);
                setSelect('lead_instructor_id', source.lead_instructor_id);
                setTextarea('remark', source.remark);
                setCheckedGroup('instructor_ids', source.instructor_ids);
                setCheckedGroup('student_group_ids', source.student_group_ids);
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
                    && (!this.scheduleInstructor || item.instructors.includes(this.scheduleInstructor))
                    && (!this.scheduleWeek || item.week === this.scheduleWeek);
            },
            matchedScheduleCount() {
                return this.scheduleItems.filter((item) => this.matchesSchedule(item.id)).length;
            },
            dayHasMatches(ids) {
                return ids.some((id) => this.matchesSchedule(id));
            },
            hasActiveScheduleFilters() {
                return !!(this.normalizedScheduleSearch()
                    || this.scheduleActivity
                    || this.scheduleGroup
                    || this.scheduleInstructor);
            },
            scheduleDateHasMatches(date) {
                return this.scheduleItems.some((item) => item.date === date && this.matchesSchedule(item.id));
            },
            scheduleWeekDays() {
                if (!this.scheduleWeek || !this.scheduleWeeks[this.scheduleWeek]?.monday) return [];

                const base = new Date(`${this.scheduleWeeks[this.scheduleWeek].monday}T00:00:00`);
                if (Number.isNaN(base.getTime())) return [];

                const dayNames = {
                    1: 'จันทร์',
                    2: 'อังคาร',
                    3: 'พุธ',
                    4: 'พฤหัสบดี',
                    5: 'ศุกร์',
                    6: 'เสาร์',
                    7: 'อาทิตย์',
                };
                const length = this.includeWeekends ? 7 : 5;

                return Array.from({ length }, (_, index) => {
                    const date = new Date(base);
                    date.setDate(base.getDate() + index);
                    const iso = [
                        date.getFullYear(),
                        String(date.getMonth() + 1).padStart(2, '0'),
                        String(date.getDate()).padStart(2, '0'),
                    ].join('-');
                    const isoDay = index + 1;

                    return {
                        iso,
                        isoDay,
                        name: dayNames[isoDay] || '',
                        display: this.toThaiDateDisplay(iso),
                        week: this.scheduleWeek,
                    };
                });
            },
            toggleDay(dateKey) {
                this.collapsedDays = { ...this.collapsedDays, [dateKey]: ! this.collapsedDays[dateKey] };
            },
            resetScheduleFilters() {
                this.scheduleSearch = '';
                this.scheduleActivity = '';
                this.scheduleGroup = '';
                this.scheduleInstructor = '';
                this.scheduleWeek = '';
            },
            resetCreateForm(date = null) {
                const form = this.$refs.createForm;
                this.createInstructorSearch = '';
                this.createGroupSearch = '';
                this.selectedOfferingId = this.initialSelectedOfferingId;
                this.liveIssues = {};
                this.createMultiDay = false;
                if (this.liveTimer) { clearTimeout(this.liveTimer); this.liveTimer = null; }
                this.setCreateMode(this.defaultCreateMode);

                if (!form) return;

                form.reset();
                form.querySelectorAll('input[type=checkbox], input[type=radio]').forEach((input) => {
                    input.checked = false;
                });
                document.dispatchEvent(new CustomEvent('tpss:reset-series-range'));
                form.querySelectorAll('input[type=text], input[type=date], input[type=time], input[type=number], input[type=search], textarea').forEach((field) => {
                    field.value = '';
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                });
                form.querySelectorAll('select').forEach((select) => {
                    select.selectedIndex = 0;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                });
                this.restoreSeriesWeekDefaults();
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
                            li.classList.toggle('tp-sel', li.dataset.val === hVal && li.dataset.cycle === '1');
                        });
                        drop.querySelectorAll('.tp-min-item').forEach(li => {
                            li.classList.toggle('tp-sel', li.dataset.val === mVal && li.dataset.cycle === '1');
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
                // เติมวันที่ให้เฉพาะตอนกดจากหัววันใน grid หรือคลิกช่องว่าง (ส่ง date มาตรง ๆ) — ปุ่มเพิ่มทั่วไปเว้นว่างให้ผู้ใช้เลือกเอง
                this.resetCreateForm(date);
                this.showCreate = true;
            },
            // คลิกช่องว่างใน grid → เปิด modal พร้อมเติมวัน + เวลาเริ่มของช่องนั้น
            openCreateAt(date, time) {
                if (!this.calendarAllowsCreate) return;
                this.openCreate(date);
                this.$nextTick(() => {
                    setTimeout(() => { window.tpssSetTimePicker('start_time', time); }, 50);
                });
            },
            openEdit(id) {
                this.detailModal = null;
                this.showCreate = false;
                this.editSeriesModal = null;
                this.editModal = 'schedule-' + id;
            },
            openSeriesEdit(id) {
                this.detailModal = null;
                this.showCreate = false;
                this.editModal = null;
                this.editSeriesModal = 'schedule-' + id;
            },
            closeCreate() { this.showCreate = false; },
            closeEdit() {
                @if(request()->boolean('from_conflict'))
                    window.location.href = @js(route('maker.alerts.index'));
                @else
                    this.editModal = null;
                    this.$nextTick(() => {
                        // small delay to allow DOM/modal transitions to finish
                        setTimeout(() => {
                            if (typeof window.tpssRecomputeGridRows === 'function') window.tpssRecomputeGridRows();
                        }, 120);
                    });
                @endif
            }
        }"
        @keydown.escape.window="detailModal = null; showCreate = false; editModal = null; editSeriesModal = null"
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
                    <a href="{{ route('maker.alerts.index') }}" class="btn btn-secondary" style="text-decoration:none;">ดูการแจ้งเตือน</a>
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

        <script>
            /**
             * Bug #2: applyInstructorFilter — redirect พร้อม instructor_id query param
             * รักษา query params อื่น (period, week_start, date, include_weekends) ไว้ครบ
             */
            function applyInstructorFilter(instructorId) {
                const url = new URL(window.location.href);
                if (instructorId) {
                    url.searchParams.set('instructor_id', instructorId);
                } else {
                    url.searchParams.delete('instructor_id');
                }
                window.location.href = url.toString();
            }

            /**
             * V2: applyTermFilter — เลือกภาคเรียน → ตั้ง term_id + ล้างวันที่
             * เพื่อให้ระบบเด้งปฏิทินไปวันเริ่มของเทอมนั้น (controller jump เมื่อเลือกเทอมชัดเจน)
             * value="" (ทุกเทอม) → ส่ง term_id=0 ให้ param "มีอยู่" จะได้ไม่ default เทอมปัจจุบัน
             */
            function applyTermFilter(termId) {
                const url = new URL(window.location.href);
                url.searchParams.set('term_id', termId || '0');
                url.searchParams.delete('date');
                url.searchParams.delete('week_start');
                window.location.href = url.toString();
            }

            // ── Copy-week helpers (วันที่แบบ local ไม่โดน UTC shift) ──
            window.tpssShiftIso = function (iso, days) {
                if (!iso) return iso;
                const dt = new Date(iso + 'T00:00:00');
                dt.setDate(dt.getDate() + days);
                const y = dt.getFullYear();
                const m = String(dt.getMonth() + 1).padStart(2, '0');
                const d = String(dt.getDate()).padStart(2, '0');
                return `${y}-${m}-${d}`;
            };
            window.tpssThaiWeekLabel = function (iso) {
                if (!iso) return '-';
                const months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
                const [y, m, d] = iso.split('-').map(Number);
                const end = window.tpssShiftIso(iso, 6).split('-').map(Number);
                return `${d} ${months[m - 1]} ${y + 543} – ${end[2]} ${months[end[1] - 1]} ${end[0] + 543}`;
            };
            // แปลงวันที่ พ.ศ. (วว/ดด/พ.ศ.) → { dayName, week } สำหรับ hint ใต้ช่องวันที่
            // V2: วันหยุดราชการของปีนี้ (ISO → ชื่อ) สำหรับเตือนตอนเลือกวันใน modal
            window.tpssHolidays = @js($academicCalendar?->holidaysMap() ?? []);
            window.tpssDateInfo = function (thaiStr, academicMondayIso) {
                const m = (thaiStr || '').trim().match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
                if (!m) return null;
                const date = new Date(+m[3] - 543, +m[2] - 1, +m[1]);
                if (isNaN(date.getTime())) return null;
                const days = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
                const dayName = 'วัน' + days[date.getDay()];
                const pad = (n) => String(n).padStart(2, '0');
                const iso = `${+m[3] - 543}-${pad(+m[2])}-${pad(+m[1])}`;
                let week = null;
                if (academicMondayIso) {
                    const toMonday = (dt) => { const x = new Date(dt); x.setHours(0, 0, 0, 0); x.setDate(x.getDate() - ((x.getDay() + 6) % 7)); return x; };
                    const a = toMonday(new Date(academicMondayIso + 'T00:00:00'));
                    const t = toMonday(date);
                    week = Math.max(1, Math.floor((t - a) / (7 * 86400000)) + 1);
                }
                return { dayName, week, iso };
            };
            // ตั้งค่า time-picker (custom widget) แบบ programmatic — ใช้ตอนคลิกช่องว่างใน grid
            window.tpssSetTimePicker = function (hiddenId, value) {
                const hidden = document.getElementById(hiddenId);
                const picker = document.querySelector(`.time-picker[data-tp-hidden='${hiddenId}']`);
                const m = String(value || '').match(/^(\d{1,2}):(\d{2})$/);
                if (!hidden || !picker || !m) return;
                const h = m[1].padStart(2, '0');
                const mi = m[2];
                hidden.value = h + ':' + mi;
                picker.dataset.tpHour = h;
                picker.dataset.tpMin = mi;
                const hourEl = picker.querySelector('.tp-val-hour');
                const minEl = picker.querySelector('.tp-val-min');
                if (hourEl) hourEl.textContent = h;
                if (minEl) minEl.textContent = mi;
                const drop = picker.querySelector('.tp-drop');
                if (drop) {
                    drop.querySelectorAll('.tp-hour-item').forEach((li) => li.classList.toggle('tp-sel', li.dataset.val === h && li.dataset.cycle === '1'));
                    drop.querySelectorAll('.tp-min-item').forEach((li) => li.classList.toggle('tp-sel', li.dataset.val === mi && li.dataset.cycle === '1'));
                }
                hidden.dispatchEvent(new Event('input', { bubbles: true }));
                hidden.dispatchEvent(new Event('change', { bubbles: true }));
            };

            // Recompute grid-row start/span for activity items based on visible times.
            // This helps the grid reflect edited start/end times without a full reload.
            window.tpssRecomputeGridRows = function () {
                try {
                    const grid = document.querySelector('.schedule-grid.is-precise');
                    if (!grid) return;
                    const minuteStep = parseInt(grid.dataset.gridMinuteStep || '5', 10) || 5;
                    const firstTimeCell = grid.querySelector('.grid-cell.grid-time');
                    if (!firstTimeCell) return;
                    const startText = (firstTimeCell.textContent || '').trim().split('\n')[0].trim();
                    const startParts = startText.match(/(\d{1,2}):(\d{2})/);
                    if (!startParts) return;
                    const gridStartMinutes = parseInt(startParts[1], 10) * 60 + parseInt(startParts[2], 10);

                    function parseTimeMinutes(timeStr) {
                        const p = (timeStr || '').match(/(\d{1,2}):(\d{2})/);
                        if (!p) return null;
                        return parseInt(p[1], 10) * 60 + parseInt(p[2], 10);
                    }

                    grid.querySelectorAll('.grid-cell.grid-cell-activity').forEach(cell => {
                        const stack = cell.querySelector('.activity-stack');
                        if (stack) {
                            const timeEls = stack.querySelectorAll('.grid-activity-time');
                            let minStart = null;
                            let maxEnd = null;
                            timeEls.forEach(el => {
                                const txt = (el.textContent || '').trim();
                                const m = txt.match(/(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/);
                                if (!m) return;
                                const s = parseTimeMinutes(m[1]);
                                const e = parseTimeMinutes(m[2]);
                                if (s !== null && (minStart === null || s < minStart)) minStart = s;
                                if (e !== null && (maxEnd === null || e > maxEnd)) maxEnd = e;
                            });
                            if (minStart === null || maxEnd === null) return;
                            const minutesFromStart = Math.max(0, minStart - gridStartMinutes);
                            const rowsStart = Math.floor(minutesFromStart / minuteStep) + 2;
                            const span = Math.max(1, Math.ceil(Math.max(5, maxEnd - minStart) / minuteStep));
                            cell.style.gridRow = `${rowsStart} / span ${span}`;
                            const stackDuration = maxEnd - minStart;
                            stack.querySelectorAll('.grid-activity-card').forEach(card => {
                                const timeEl = card.querySelector('.grid-activity-time');
                                if (!timeEl) return;
                                const txt = (timeEl.textContent || '').trim();
                                const m = txt.match(/(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/);
                                if (!m) return;
                                const s = parseTimeMinutes(m[1]);
                                const e = parseTimeMinutes(m[2]);
                                if (s === null || e === null) return;
                                const topPct = stackDuration > 0 ? ((s - minStart) / stackDuration) * 100 : 0;
                                const heightPct = stackDuration > 0 ? ((e - s) / stackDuration) * 100 : 100;
                                card.style.top = Math.max(0, topPct).toFixed(4) + '%';
                                card.style.height = Math.max(5, heightPct).toFixed(4) + '%';
                            });
                            return;
                        }
                        const card = cell.querySelector('.grid-activity, .grid-activity-card');
                        if (!card) return;
                        const timeEl = card.querySelector('.grid-activity-time, .month-activity-time');
                        if (!timeEl) return;
                        const timeText = (timeEl.textContent || '').trim();
                        const m = timeText.match(/(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/);
                        if (!m) return;
                        const startMinutes = parseTimeMinutes(m[1]);
                        const endMinutes = parseTimeMinutes(m[2]);
                        if (startMinutes === null || endMinutes === null) return;
                        const minutesFromStart = Math.max(0, startMinutes - gridStartMinutes);
                        const rowsStart = Math.floor(minutesFromStart / minuteStep) + 2;
                        const span = Math.max(1, Math.ceil((endMinutes - startMinutes) / minuteStep));
                        cell.style.gridRow = `${rowsStart} / span ${span}`;
                    });
                } catch (err) {
                    console.error('tpssRecomputeGridRows error', err);
                }
            };
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
            {{-- V2: filter ภาคเรียน — วิชาเปิดทั้งปี เลือกเทอมเพื่อโฟกัส + เด้งปฏิทินไปช่วงนั้น --}}
            @if(($terms ?? collect())->isNotEmpty())
            <div class="term-filter-wrap">
                <select
                    class="schedule-filter-control"
                    aria-label="เลือกภาคเรียน"
                    onchange="applyTermFilter(this.value)"
                    data-testid="schedule-term-filter"
                >
                    <option value="">ทุกเทอม</option>
                    @foreach($terms as $t)
                        <option value="{{ $t->id }}" {{ ($selectedTermId ?? null) == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            {{-- Bug #2: instructor server-side filter (ใช้ได้ทุก view) --}}
            @if($instructorFilterOptions->isNotEmpty())
            <div class="instructor-filter-wrap">
                <select
                    id="workspace-instructor-filter"
                    class="schedule-filter-control"
                    aria-label="กรองตามผู้สอน"
                    onchange="applyInstructorFilter(this.value)"
                >
                    <option value="">อาจารย์ทุกท่าน</option>
                    @foreach($instructorFilterOptions as $instructor)
                        <option value="{{ $instructor->id }}" {{ ($selectedInstructorId ?? null) == $instructor->id ? 'selected' : '' }}>
                            {{ $instructor->formatted_name ?? $instructor->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            @endif
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
                        <span>ปีการศึกษา {{ $academicYear?->name ?? '-' }}</span>
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
                        @php
                            // V2: ไม่บังคับมีกลุ่มก่อนสร้าง slot (กลุ่มย่อยมาหลังอนุมัติ โดยอาจารย์)
                            $canCreateScheduleNow = $canCreateInCurrentPeriod;
                            $createDisabledHint = ! $canCreateInCurrentPeriod ? $outsideCreateHint : '';
                        @endphp
                        <button
                            type="button"
                            class="btn btn-primary {{ ! $canCreateScheduleNow ? 'is-disabled' : '' }}"
                            data-testid="schedule-create-link"
                            @click="openCreate()"
                            @disabled(! $canCreateScheduleNow)
                            title="{{ $createDisabledHint }}"
                            aria-disabled="{{ ! $canCreateScheduleNow ? 'true' : 'false' }}"
                            style="min-height:34px;padding:6px 12px;font-size:12.5px;"
                        >+ เพิ่มรายการสอน</button>
                    @endif
                </div>
                <div class="course-overview-stats">
                    <span class="course-stat"><strong>{{ $totalScheduleCount ?? $allSchedules->count() }}</strong> รายการสอน</span>
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
                        @if($canEdit)
                        <button
                            type="button"
                            class="weekend-toggle copy-week-trigger"
                            x-show="schedulePeriod === 'week'"
                            x-cloak
                            @click="openCopyWeek()"
                            data-testid="schedule-copy-week-button"
                        >คัดลอกกิจกรรมจากสัปดาห์อื่น</button>
                        @endif
                        {{-- V2: filter ภาคเรียน (per-offering) — วิชาเปิดทั้งปี เลือกเทอมเพื่อโฟกัส + เด้งปฏิทิน --}}
                        @if(($terms ?? collect())->isNotEmpty())
                        <div class="term-filter-wrap">
                            <select
                                class="schedule-filter-control"
                                aria-label="เลือกภาคเรียน"
                                onchange="applyTermFilter(this.value)"
                                data-testid="schedule-term-filter-offering"
                            >
                                <option value="">ทุกเทอม</option>
                                @foreach($terms as $t)
                                    <option value="{{ $t->id }}" {{ ($selectedTermId ?? null) == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                        <div class="schedule-toggle" role="group" aria-label="รูปแบบการแสดงตาราง">
                            <button type="button" :class="{ 'is-active': view === 'list' }" @click="view = 'list'" data-testid="schedule-list-toggle">แบบรายการ</button>
                            <button type="button" :class="{ 'is-active': view === 'grid' }" @click="view = 'grid'" data-testid="schedule-grid-toggle">แบบตาราง</button>
                        </div>
                    </div>
                </div>
                <div class="schedule-filter-bar" x-show="view === 'list'" x-cloak>
                    @php
                        $activeWeeks = $scheduleWeekOptions->filter(fn ($w) => $w['count'] > 0)->values();
                        $emptyWeeks = $scheduleWeekOptions->filter(fn ($w) => $w['count'] === 0)->values();
                    @endphp
                    <div class="week-filter" x-data="{ weekOpen: false }" @click.outside="weekOpen = false" @keydown.escape.window="weekOpen = false">
                        <button
                            type="button"
                            class="week-filter-trigger"
                            :class="scheduleWeek ? 'is-active' : ''"
                            @click="weekOpen = !weekOpen"
                            :aria-expanded="weekOpen ? 'true' : 'false'"
                            aria-haspopup="listbox"
                            data-testid="schedule-week-filter"
                        >
                            <span class="week-filter-trigger-label">
                                <span x-text="scheduleWeek ? 'สัปดาห์ที่ ' + scheduleWeek : 'ทุกสัปดาห์'"></span>
                            </span>
                            <svg class="week-filter-trigger-chevron" :class="weekOpen ? 'is-open' : ''" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polyline points="6 9 12 15 18 9"/>
                            </svg>
                        </button>
                        <div class="week-filter-pop" x-show="weekOpen" x-transition.opacity.duration.120ms x-cloak role="listbox">
                            <button type="button" class="week-filter-item is-all" :class="!scheduleWeek ? 'is-selected' : ''" @click="scheduleWeek = ''; weekOpen = false">
                                <span>ทุกสัปดาห์</span>
                                <svg class="week-filter-check" x-show="!scheduleWeek" x-cloak width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                            </button>
                            @if($activeWeeks->isNotEmpty())
                                <div class="week-filter-section">มีรายการสอน · {{ $activeWeeks->count() }} สัปดาห์</div>
                                @foreach($activeWeeks as $option)
                                    <button type="button" class="week-filter-item" :class="scheduleWeek === '{{ $option['week'] }}' ? 'is-selected' : ''" @click="scheduleWeek = '{{ $option['week'] }}'; weekOpen = false">
                                        <span>สัปดาห์ที่ {{ $option['week'] }}</span>
                                        <span class="week-filter-count">{{ $option['count'] }} รายการ</span>
                                    </button>
                                @endforeach
                            @endif
                            @if($emptyWeeks->isNotEmpty())
                                <div class="week-filter-section">สัปดาห์อื่นๆ · {{ $emptyWeeks->count() }} สัปดาห์</div>
                                @foreach($emptyWeeks as $option)
                                    <button type="button" class="week-filter-item is-empty" :class="scheduleWeek === '{{ $option['week'] }}' ? 'is-selected' : ''" @click="scheduleWeek = '{{ $option['week'] }}'; weekOpen = false">
                                        <span>สัปดาห์ที่ {{ $option['week'] }}</span>
                                    </button>
                                @endforeach
                            @endif
                        </div>
                    </div>
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
                    <select class="schedule-filter-control" x-model="scheduleInstructor" aria-label="กรองตามผู้สอน"
                        @change="applyInstructorFilter($event.target.value)"
                        id="co-instructor-filter">
                        <option value="">ทุกผู้สอน</option>
                        @foreach($instructorFilterOptions as $instructor)
                            <option value="{{ $instructor->id }}" {{ ($selectedInstructorId ?? null) == $instructor->id ? 'selected' : '' }}>
                                {{ $instructor->formatted_name ?? $instructor->name }}
                            </option>
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
                                    @foreach($groupedSchedules as $dateString => $daySchedules)
                                        @php
                                            $firstSchedule = $daySchedules->first();
                                            $dateObj = $firstSchedule?->start_date;
                                            $dayIso = $dateObj?->dayOfWeekIso ?? 1;
                                            $dayName = $thaiDays[$dayIso] ?? '';
                                            $dateKey = str_replace('-', '', $dateString);
                                            $dayWeekNumber = $weekNumberForDate($dateObj);
                                            $dayScheduleIds = $daySchedules->pluck('id')->map(fn ($id) => (string) $id)->values();
                                            $di = $dayInfo($dateObj);
                                            $dayTermName = $firstSchedule?->term?->name ?? $di['term_name'];
                                        @endphp
                                        @if($daySchedules->isNotEmpty() && $dateObj)
                                            <tr
                                                class="sched-day-group-header"
                                                id="schedule-day-group-{{ $dateKey }}"
                                                x-show="dayHasMatches(@js($dayScheduleIds))"
                                                x-cloak
                                                role="button"
                                                tabindex="0"
                                                :aria-expanded="!collapsedDays['{{ $dateKey }}'] ? 'true' : 'false'"
                                                aria-label="สลับการแสดงรายการ {{ $dayName }} {{ $formatDate($dateObj) }}"
                                                @click="toggleDay('{{ $dateKey }}')"
                                                @keydown.enter.prevent="toggleDay('{{ $dateKey }}')"
                                                @keydown.space.prevent="toggleDay('{{ $dateKey }}')"
                                                :class="collapsedDays['{{ $dateKey }}'] ? 'is-collapsed' : ''"
                                                data-testid="schedule-day-group-toggle"
                                            >
                                                <td colspan="6" class="sched-day-group-cell">
                                                    <div class="sched-day-group-inner">
                                                        <svg class="sched-day-group-chevron" :class="collapsedDays['{{ $dateKey }}'] ? 'is-collapsed' : ''" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                            <polyline points="6 9 12 15 18 9"/>
                                                        </svg>
                                                        <span class="co-day-badge {{ in_array($di['kind'], ['exam', 'offterm', 'break'], true) ? 'is-exam-day' : 'day-' . $dayIso }} sched-day-group-badge">{{ $dayName }}</span>
                                                        <span class="sched-day-group-date">{{ $formatDate($dateObj) }}</span>
                                                        @if($dayTermName)
                                                            <span class="term-badge">{{ $dayTermName }}</span>
                                                        @endif
                                                        @if($di['kind'] !== 'normal' && $di['kind'] !== 'outside')
                                                            <span class="grid-day-chip is-{{ $di['kind'] }}">{{ $di['label'] }}</span>
                                                        @endif
                                                        @if($dayWeekNumber)
                                                            <span class="sched-day-group-week">สัปดาห์ที่ {{ $dayWeekNumber }}</span>
                                                        @endif
                                                        <span class="sched-day-group-count">· {{ $daySchedules->count() }} รายการสอน</span>
                                                        @if($canEdit && ! $di['blocked'])
                                                            <button type="button" class="sched-day-add" @click.stop="openCreate('{{ $dateObj->toDateString() }}')" data-testid="list-day-add" title="เพิ่มกิจกรรมในวันนี้">+ เพิ่มกิจกรรม</button>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                            @foreach($daySchedules as $as)
                                                @php
                                                    $asActivity = $as->activityType;
                                                    $asRoom = $as->room;
                                                    $asInstructorText = $scheduleInstructorText($as);
                                                    $asConflicts = $scheduleConflicts->get($as->id, collect());
                                                    $asIncompleteReasons = $scheduleIncompleteReasons($as);
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
                                                <tr role="button" tabindex="0" class="co-sched-row" :class="focusedScheduleClass('{{ $as->id }}')" style="--activity-color: {{ $activityTone($as) }};" x-show="matchesSchedule('{{ $as->id }}') && !collapsedDays['{{ $dateKey }}']" x-cloak data-schedule-id="{{ $as->id }}" data-schedule-modal-trigger @click="detailModal = 'schedule-{{ $as->id }}'" @keydown.enter.prevent="detailModal = 'schedule-{{ $as->id }}'" @keydown.space.prevent="detailModal = 'schedule-{{ $as->id }}'">
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
                                                        @if($as->schedule_template_id)
                                                            <div style="margin-top:6px;">
                                                                @include('course_head.schedules._series_badge', ['schedule' => $as])
                                                            </div>
                                                        @endif
                                                        @if($asIncompleteReasons->isNotEmpty())
                                                            <div style="margin-top:6px;">
                                                                @include('course_head.schedules._incomplete_badge', ['reasons' => $asIncompleteReasons])
                                                            </div>
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
                                    <template x-for="day in scheduleWeekDays()" :key="'empty-week-day-' + day.iso">
                                        <tr class="sched-day-group-header is-empty-day" x-show="scheduleWeek && !scheduleDateHasMatches(day.iso)" x-cloak>
                                            <td colspan="6" class="sched-day-group-cell">
                                                <div class="sched-day-group-inner">
                                                    <span class="co-day-badge sched-day-group-badge" :class="'day-' + day.isoDay" x-text="day.name"></span>
                                                    <span class="sched-day-group-date" x-text="day.display"></span>
                                                    <span class="sched-day-group-week" x-text="'สัปดาห์ที่ ' + day.week"></span>
                                                    <span class="sched-day-group-count is-muted">· 0 รายการสอน</span>
                                                    <span class="sched-day-empty-copy" x-text="hasActiveScheduleFilters() ? 'ไม่มีรายการที่ตรงกับตัวกรองในวันนี้' : 'ยังไม่มีกิจกรรมในวันนี้'"></span>
                                                    @if($canEdit)
                                                        <button type="button" class="sched-day-add" @click.stop="openCreate(day.iso)" data-testid="list-empty-day-add" title="เพิ่มกิจกรรมในวันนี้">+ เพิ่มกิจกรรม</button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="matchedScheduleCount() === 0 && scheduleWeek && scheduleWeekDays().length === 0" x-cloak>
                                        <td colspan="6">
                                            <div class="schedule-empty" style="margin:16px;">
                                                <div style="font-weight:800;color:var(--fg-2);">ยังไม่มีกิจกรรมในสัปดาห์นี้</div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr x-show="matchedScheduleCount() === 0 && !scheduleWeek" x-cloak>
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
                                    $isOutsideAcademic = $isDayOutsideAcademic($day);
                                    $di = $dayInfo($day);
                                    $dayKindClass = ! $isOutsideAcademic && $di['kind'] !== 'normal' ? 'day-' . $di['kind'] : '';
                                @endphp
                                <div class="month-calendar-day {{ $isOutsideMonth ? 'is-outside' : '' }} {{ $isOutsideAcademic ? 'is-outside-academic' : '' }} {{ $dayKindClass }}"
                                    @if($isOutsideAcademic) title="นอกช่วงปีการศึกษา" @elseif($di['label']) title="{{ $di['label'] }}" @endif>
                                    <div class="month-day-top">
                                        <span class="month-day-number">{{ $day->day }}</span>
                                        @if($dayOccurrences->isNotEmpty())
                                            <span class="month-day-count">{{ $dayOccurrences->count() }} รายการ</span>
                                        @endif
                                    </div>
                                    @if($dayKindClass)<span class="month-day-flag is-{{ $di['kind'] }}">{{ $di['label'] }}</span>@endif
                                    <div class="month-day-items">
                                        @forelse($dayOccurrences as $occurrence)
                                            @php
                                                $schedule = $occurrence['schedule'];
                                                $activity = $schedule->activityType;
                                                $room = $schedule->room;
                                                $instructorText = $scheduleInstructorText($schedule);
                                                $itemConflicts = $scheduleConflicts->get($schedule->id, collect());
                                                $incompleteReasons = $scheduleIncompleteReasons($schedule);
                                            @endphp
                                            <div role="button" tabindex="0" class="month-activity {{ $schedule->schedule_template_id ? 'has-series' : '' }}" :class="focusedScheduleClass('{{ $schedule->id }}')" style="--activity-color: {{ $activityTone($schedule) }};" data-schedule-id="{{ $schedule->id }}" data-schedule-modal-trigger @click="detailModal = 'schedule-{{ $schedule->id }}'" @keydown.enter.prevent="detailModal = 'schedule-{{ $schedule->id }}'" @keydown.space.prevent="detailModal = 'schedule-{{ $schedule->id }}'">
                                                <div class="month-activity-time">{{ $formatTime($schedule->start_time) }} - {{ $formatTime($schedule->end_time) }}</div>
                                                <div class="month-activity-title">{{ $schedule->topic ?: ($activity?->name ?? 'รายการสอน') }}</div>
                                                <div class="month-activity-tags">
                                                    <span class="activity-tag" style="--activity-color: {{ $activityTone($schedule) }};">{{ $activity?->name ?? 'กิจกรรม' }}</span>
                                                    @if($schedule->studentGroups->isNotEmpty())
                                                        <span class="month-group-summary">{{ $schedule->studentGroups->count() }} กลุ่ม</span>
                                                    @endif
                                                    @if($schedule->schedule_template_id)
                                                        @include('course_head.schedules._series_badge', ['schedule' => $schedule])
                                                    @endif
                                                    @include('course_head.schedules._incomplete_badge', ['reasons' => $incompleteReasons])
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
                    <div class="schedule-grid is-precise" data-grid-minute-step="{{ $gridMinuteStep }}" style="--grid-day-count: {{ max(1, $weekDays->count()) }}; --grid-minute-row-height: {{ $gridMinuteRowHeight }}px; grid-template-columns: 68px repeat({{ max(1, $weekDays->count()) }}, minmax({{ ($includeWeekends ?? false) && ($schedulePeriod ?? 'week') === 'week' ? 122 : 146 }}px, 1fr)); grid-template-rows: 44px repeat({{ $gridMinuteRowCount }}, var(--grid-minute-row-height));">
                        <div class="grid-cell grid-head" style="grid-area:1 / 1;"></div>
                        @foreach($weekDays as $dayIndex => $day)
                            @php $dayOutside = $isDayOutsideAcademic($day); $di = $dayInfo($day); $dayKindClass = ! $dayOutside && $di['kind'] !== 'normal' ? 'day-' . $di['kind'] : ''; @endphp
                            <div class="grid-cell grid-head {{ $dayOutside ? 'is-outside-academic' : '' }} {{ $dayKindClass }}" style="grid-area:1 / {{ $dayIndex + 2 }};" @if($dayOutside) title="นอกช่วงปีการศึกษา" @elseif($di['label']) title="{{ $di['label'] }}" @endif>
                                {{ $thaiDays[$day->dayOfWeekIso] ?? $day->format('l') }}<br>
                                <span class="caption">{{ $formatDate($day) }}</span>
                                @if($dayKindClass)<span class="grid-day-chip is-{{ $di['kind'] }}">{{ $di['label'] }}</span>@endif
                            </div>
                        @endforeach

                        @foreach($gridHourSlots as $slot)
                            @php
                                $hourRowStart = $gridRowStartForTime($slot);
                            @endphp
                            <div class="grid-cell grid-time" style="grid-column:1; grid-row:{{ $hourRowStart }} / span {{ $gridRowsPerHour }};">{{ $slot }}</div>
                            @foreach($weekDays as $dayIndex => $day)
                                @php $dayOutside = $isDayOutsideAcademic($day); $di = $dayInfo($day); $dayBlocked = $dayOutside || $di['blocked']; $dayKindClass = ! $dayOutside && $di['kind'] !== 'normal' ? 'day-' . $di['kind'] : ''; @endphp
                                <div class="grid-cell {{ $dayOutside ? 'is-outside-academic' : '' }} {{ $dayKindClass }} {{ $canEdit && ! $dayBlocked ? 'is-addable' : '' }}" style="grid-column:{{ $dayIndex + 2 }}; grid-row:{{ $hourRowStart }} / span {{ $gridRowsPerHour }};" @if($canEdit && ! $dayBlocked) @click="openCreateAt('{{ $day->toDateString() }}', '{{ $slot }}')" @keydown.enter.prevent="openCreateAt('{{ $day->toDateString() }}', '{{ $slot }}')" @keydown.space.prevent="openCreateAt('{{ $day->toDateString() }}', '{{ $slot }}')" role="button" tabindex="0" aria-label="เพิ่มรายการสอน {{ $formatDate($day) }} เวลา {{ $slot }}" data-testid="grid-empty-cell" title="คลิกเพื่อเพิ่มกิจกรรม {{ $slot }}" @elseif($canEdit && $dayBlocked && $di['label']) title="{{ $di['label'] }} — เพิ่มกิจกรรมไม่ได้" @endif></div>
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
                                            $incompleteReasons = $scheduleIncompleteReasons($schedule);
                                            $activityDuration = (int) $occurrence['duration_minutes'];
                                            $gridActivitySizeClass = $activityDuration < 75
                                                ? 'is-compact'
                                                : ($activityDuration >= 150 ? 'is-tall' : '');
                                        @endphp
                                        <div role="button" tabindex="0" class="grid-activity {{ $gridActivitySizeClass }} {{ $schedule->schedule_template_id ? 'has-series' : '' }}" :class="focusedScheduleClass('{{ $schedule->id }}')" style="--activity-color: {{ $activityTone($schedule) }};" data-schedule-id="{{ $schedule->id }}" data-schedule-modal-trigger @click="detailModal = 'schedule-{{ $schedule->id }}'" @keydown.enter.prevent="detailModal = 'schedule-{{ $schedule->id }}'" @keydown.space.prevent="detailModal = 'schedule-{{ $schedule->id }}'">
                                            <div class="grid-activity-top">
                                                <span class="activity-tag" style="--activity-color: {{ $activityTone($schedule) }};">{{ $activity?->name ?? 'กิจกรรม' }}</span>
                                                @if($schedule->schedule_template_id)
                                                    @include('course_head.schedules._series_badge', ['schedule' => $schedule])
                                                @endif
                                                @if($itemConflicts->isNotEmpty())
                                                    @include('course_head.schedules._conflict_pill', ['conflicts' => $itemConflicts])
                                                @endif
                                                @include('course_head.schedules._incomplete_badge', ['reasons' => $incompleteReasons])
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
                                        $incompleteReasons = $scheduleIncompleteReasons($schedule);
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
                                                @include('course_head.schedules._incomplete_badge', ['reasons' => $incompleteReasons])
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
                                        @if($instructorText === '-')
                                            <td><span class="sched-muted">-</span></td>
                                        @else
                                            <td><span class="sched-strong">{{ $instructorText }}</span></td>
                                        @endif
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
                                $isOutsideAcademic = $isDayOutsideAcademic($day);
                                $di = $dayInfo($day);
                                $dayKindClass = ! $isOutsideAcademic && $di['kind'] !== 'normal' ? 'day-' . $di['kind'] : '';
                            @endphp
                            <div class="month-calendar-day {{ $isOutsideMonth ? 'is-outside' : '' }} {{ $isOutsideAcademic ? 'is-outside-academic' : '' }} {{ $dayKindClass }}"
                                @if($isOutsideAcademic) title="นอกช่วงปีการศึกษา" @elseif($di['label']) title="{{ $di['label'] }}" @endif>
                                <div class="month-day-top">
                                    <span class="month-day-number">{{ $day->day }}</span>
                                    @if($dayOccurrences->isNotEmpty())
                                        <span class="month-day-count">{{ $dayOccurrences->count() }} รายการ</span>
                                    @endif
                                </div>
                                @if($dayKindClass)<span class="month-day-flag is-{{ $di['kind'] }}">{{ $di['label'] }}</span>@endif
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
                                        $incompleteReasons = $scheduleIncompleteReasons($schedule);
                                    @endphp
                                        <div role="button" tabindex="0" class="month-activity {{ $schedule->schedule_template_id ? 'has-series' : '' }}" :class="focusedScheduleClass('{{ $schedule->id }}')" style="--activity-color: {{ $activityTone($schedule) }};" data-schedule-id="{{ $schedule->id }}" data-schedule-modal-trigger @click="detailModal = 'schedule-{{ $schedule->id }}'" @keydown.enter.prevent="detailModal = 'schedule-{{ $schedule->id }}'" @keydown.space.prevent="detailModal = 'schedule-{{ $schedule->id }}'">
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
                                                @include('course_head.schedules._incomplete_badge', ['reasons' => $incompleteReasons])
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
                <div class="schedule-grid is-precise" data-grid-minute-step="{{ $gridMinuteStep }}" style="--grid-day-count: {{ max(1, $weekDays->count()) }}; --grid-minute-row-height: {{ $gridMinuteRowHeight }}px; grid-template-columns: 68px repeat({{ max(1, $weekDays->count()) }}, minmax({{ ($includeWeekends ?? false) && ($schedulePeriod ?? 'week') === 'week' ? 122 : 146 }}px, 1fr)); grid-template-rows: 44px repeat({{ $gridMinuteRowCount }}, var(--grid-minute-row-height));">
                    <div class="grid-cell grid-head" style="grid-area:1 / 1;"></div>
                    @foreach($weekDays as $dayIndex => $day)
                        @php $dayOutside = $isDayOutsideAcademic($day); $di = $dayInfo($day); $dayKindClass = ! $dayOutside && $di['kind'] !== 'normal' ? 'day-' . $di['kind'] : ''; @endphp
                        <div class="grid-cell grid-head {{ $dayOutside ? 'is-outside-academic' : '' }} {{ $dayKindClass }}" style="grid-area:1 / {{ $dayIndex + 2 }};" @if($dayOutside) title="นอกช่วงปีการศึกษา" @elseif($di['label']) title="{{ $di['label'] }}" @endif>
                            {{ $thaiDays[$day->dayOfWeekIso] ?? $day->format('l') }}<br>
                            <span class="caption">{{ $formatDate($day) }}</span>
                            @if($dayKindClass)<span class="grid-day-chip is-{{ $di['kind'] }}">{{ $di['label'] }}</span>@endif
                        </div>
                    @endforeach

                    @foreach($gridHourSlots as $slot)
                        @php
                            $hourRowStart = $gridRowStartForTime($slot);
                        @endphp
                        <div class="grid-cell grid-time" style="grid-column:1; grid-row:{{ $hourRowStart }} / span {{ $gridRowsPerHour }};">{{ $slot }}</div>
                        @foreach($weekDays as $dayIndex => $day)
                            @php $dayOutside = $isDayOutsideAcademic($day); $di = $dayInfo($day); $dayBlocked = $dayOutside || $di['blocked']; $dayKindClass = ! $dayOutside && $di['kind'] !== 'normal' ? 'day-' . $di['kind'] : ''; @endphp
                            <div class="grid-cell {{ $dayOutside ? 'is-outside-academic' : '' }} {{ $dayKindClass }} {{ $canEdit && ! $dayBlocked ? 'is-addable' : '' }}" style="grid-column:{{ $dayIndex + 2 }}; grid-row:{{ $hourRowStart }} / span {{ $gridRowsPerHour }};" @if($canEdit && ! $dayBlocked) @click="openCreateAt('{{ $day->toDateString() }}', '{{ $slot }}')" @keydown.enter.prevent="openCreateAt('{{ $day->toDateString() }}', '{{ $slot }}')" @keydown.space.prevent="openCreateAt('{{ $day->toDateString() }}', '{{ $slot }}')" role="button" tabindex="0" aria-label="เพิ่มรายการสอน {{ $formatDate($day) }} เวลา {{ $slot }}" data-testid="grid-empty-cell" title="คลิกเพื่อเพิ่มกิจกรรม {{ $slot }}" @elseif($canEdit && $dayBlocked && $di['label']) title="{{ $di['label'] }} — เพิ่มกิจกรรมไม่ได้" @endif></div>
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
                                        $incompleteReasons = $scheduleIncompleteReasons($schedule);
                                        $activityDuration = (int) $occurrence['duration_minutes'];
                                        $gridActivitySizeClass = $activityDuration < 75
                                            ? 'is-compact'
                                            : ($activityDuration >= 150 ? 'is-tall' : '');
                                    @endphp
                                    <div role="button" tabindex="0" class="grid-activity {{ $gridActivitySizeClass }} {{ $schedule->schedule_template_id ? 'has-series' : '' }}" :class="focusedScheduleClass('{{ $schedule->id }}')" style="--activity-color: {{ $activityTone($schedule) }};" data-schedule-id="{{ $schedule->id }}" data-schedule-modal-trigger @click="detailModal = 'schedule-{{ $schedule->id }}'" @keydown.enter.prevent="detailModal = 'schedule-{{ $schedule->id }}'" @keydown.space.prevent="detailModal = 'schedule-{{ $schedule->id }}'">
                                        <div class="grid-activity-top">
                                            @if($offeringCourse?->course_code)
                                                <span class="grid-course">{{ $offeringCourse->course_code }}</span>
                                            @endif
                                            <span class="activity-tag" style="--activity-color: {{ $activityTone($schedule) }};">{{ $activity?->name ?? 'กิจกรรม' }}</span>
                                            @if($schedule->schedule_template_id)
                                                @include('course_head.schedules._series_badge', ['schedule' => $schedule])
                                            @endif
                                            @if($itemConflicts->isNotEmpty())
                                                @include('course_head.schedules._conflict_pill', ['conflicts' => $itemConflicts])
                                            @endif
                                            @include('course_head.schedules._incomplete_badge', ['reasons' => $incompleteReasons])
                                        </div>
                                        <div class="grid-activity-title">{{ $schedule->topic ?: ($activity?->name ?? 'รายการสอน') }}</div>
                                        @if($isWorkspace && ($offeringCourse?->name_th || $offeringCourse?->name_en))
                                            <div class="grid-activity-sub">{{ $offeringCourse?->name_th ?? $offeringCourse?->name_en }}</div>
                                        @elseif($schedule->topic && $activity?->name)
                                            <div class="grid-activity-sub">{{ $activity->name }}</div>
                                        @endif
                                        <div class="grid-activity-meta">
                                            <div class="grid-activity-time">{{ $formatTime($schedule->start_time) }} - {{ $formatTime($schedule->end_time) }} · {{ $formatDuration($occurrence['duration_minutes']) }}</div>
                                            <div class="grid-instructor">
                                                @if($instructorText === '-')
                                                    <span class="sched-muted">-</span>
                                                @else
                                                    {{ $instructorText }}
                                                @endif
                                            </div>
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
                                                @include('course_head.schedules._incomplete_badge', ['reasons' => $incompleteReasons])
                                            </div>
                                                <div class="grid-activity-title">{{ $schedule->topic ?: ($activity?->name ?? 'รายการสอน') }}</div>
                                                @if($isWorkspace && ($offeringCourse?->name_th || $offeringCourse?->name_en))
                                                    <div class="grid-activity-sub">{{ $offeringCourse?->name_th ?? $offeringCourse?->name_en }}</div>
                                                @elseif($schedule->topic && $activity?->name)
                                                    <div class="grid-activity-sub">{{ $activity->name }}</div>
                                                @endif
                                                <div class="grid-activity-meta">
                                                    <div class="grid-activity-time">{{ $formatTime($schedule->start_time) }} - {{ $formatTime($schedule->end_time) }} · {{ $formatDuration($occurrence['duration_minutes']) }}</div>
                                                    <div class="grid-instructor">
                                                        @if($instructorText === '-')
                                                            <span class="sched-muted">-</span>
                                                        @else
                                                            {{ $instructorText }}
                                                        @endif
                                                    </div>
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
                $detailIncompleteReasons = $scheduleIncompleteReasons($schedule);
            @endphp
            <div class="schedule-modal-backdrop" x-show="detailModal === 'schedule-{{ $schedule->id }}'" x-cloak @click.self="detailModal = null" data-testid="schedule-detail-modal">
                <template x-if="detailModal === 'schedule-{{ $schedule->id }}'">
                    <section class="schedule-modal" role="dialog" aria-modal="true" aria-labelledby="schedule-detail-title-{{ $schedule->id }}" style="--activity-color: {{ $activityTone($schedule) }};">
                    <div class="modal-handle"></div>
                    <div class="modal-head-detail">
                        <div style="min-width:0;">
                            <div class="modal-title-detail" id="schedule-detail-title-{{ $schedule->id }}">{{ $schedule->topic ?: ($activity?->name ?? 'รายการสอน') }}</div>
                            <span class="activity-tag" style="--activity-color: {{ $activityTone($schedule) }}; margin-top:5px;">{{ $activity?->name ?? 'กิจกรรม' }}</span>
                            @if($schedule->schedule_template_id)
                                <span class="series-badge" style="margin-top:5px;" title="กิจกรรมทำซ้ำรายสัปดาห์">
                                    <span class="series-dot" aria-hidden="true"></span>
                                    <span>ทำซ้ำ</span>
                                    @if($schedule->series_week_number)
                                        <span>สัปดาห์ {{ $schedule->series_week_number }}</span>
                                    @endif
                                </span>
                            @endif
                            <span style="display:inline-flex;margin-top:5px;">
                                @include('course_head.schedules._incomplete_badge', ['reasons' => $detailIncompleteReasons])
                            </span>
                        </div>
                        <button type="button" class="modal-close" @click="detailModal = null" aria-label="ปิด">×</button>
                    </div>
                    <div class="detail-body">
                        <div class="detail-grid">
                            <div class="detail-row">
                                <div class="detail-row-label">วันที่</div>
                                <div class="detail-row-value">{{ $dateText }} · {{ $timeText }} <span class="sub">({{ $formatDuration($durationForSchedule($schedule)) }})</span></div>
                            </div>
                            @if($schedule->schedule_template_id)
                                <div class="detail-row">
                                    <div class="detail-row-label">ชุดทำซ้ำ</div>
                                    <div class="detail-row-value">รายการนี้มาจากกิจกรรมรายสัปดาห์ ห้องและกลุ่มนักศึกษาปรับรายสัปดาห์ได้</div>
                                </div>
                            @endif
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
                                        <span style="color:var(--fg-3);">-</span>
                                    @endif
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-row-label">สถานที่</div>
                                <div class="detail-row-value">
                                    @if($room?->room_name || $room?->room_code)
                                        {{ $room->room_name ?? $room->room_code }}@if($room?->building) <span class="sub">· {{ $room->building }}</span>@endif
                                    @else
                                        <span style="color:var(--fg-3);">-</span>
                                    @endif
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
                                        <span style="color:var(--fg-3);">-</span>
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
                            <form id="delete-schedule-{{ $schedule->id }}" method="POST" action="{{ route('maker.course_offerings.schedules.destroy', [$offering, $schedule]) }}" style="display:none;">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                            </form>
                            @if($schedule->schedule_template_id && $schedule->scheduleTemplate)
                                <form id="delete-series-from-{{ $schedule->id }}" method="POST" action="{{ route('maker.course_offerings.schedules.destroy', [$offering, $schedule]) }}" style="display:none;">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="series_delete_scope" value="from_current">
                                    <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                                </form>
                                <form id="delete-series-all-{{ $schedule->id }}" method="POST" action="{{ route('maker.course_offerings.schedules.destroy', [$offering, $schedule]) }}" style="display:none;">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="series_delete_scope" value="all">
                                    <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                                </form>
                            @endif
                            @php
                                $seriesLabel = $schedule->topic ?: ($activity?->name ?? 'รายการทำซ้ำ');
                                $weekLabel = $schedule->series_week_number ? ' (สัปดาห์ ' . $schedule->series_week_number . ')' : '';
                                $seriesTimeLabel = $schedule->start_time && $schedule->end_time
                                    ? ' · ' . substr((string) $schedule->start_time, 0, 5) . '-' . substr((string) $schedule->end_time, 0, 5)
                                    : '';
                            @endphp
                            @if($schedule->schedule_template_id)
                                <button type="button" class="btn btn-secondary" data-testid="schedule-series-edit-modal-trigger" @click="openSeriesEdit('{{ $schedule->id }}')" style="display:inline-flex;align-items:center;gap:5px;">
                                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 1l4 4-4 4"></path><path d="M3 11V9a4 4 0 0 1 4-4h14"></path><path d="M7 23l-4-4 4-4"></path><path d="M21 13v2a4 4 0 0 1-4 4H3"></path></svg>
                                    แก้ชุดทำซ้ำ
                                </button>
                            @endif
                            <button type="button" class="btn btn-secondary" data-testid="schedule-edit-modal-trigger" @click="openEdit('{{ $schedule->id }}')" style="display:inline-flex;align-items:center;gap:5px;">
                                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                แก้ไข
                            </button>
                            @if($schedule->schedule_template_id && $schedule->scheduleTemplate)
                                <button type="button" class="btn btn-secondary" data-form="delete-series-from-{{ $schedule->id }}" data-label="{{ $seriesLabel }}{{ $weekLabel }} ตั้งแต่ {{ $formatDate($schedule->start_date ?? $schedule->teaching_date) }}{{ $seriesTimeLabel }}" data-warn="ระบบจะลบรายการนี้และรายการสัปดาห์ถัดไปในชุดเดียวกัน ส่วนสัปดาห์ก่อนหน้าจะยังอยู่" onclick="tpssDelete(this)" data-testid="schedule-series-delete-from-button" style="display:inline-flex;align-items:center;gap:5px;">
                                    ลบตั้งแต่สัปดาห์นี้
                                </button>
                                <button type="button" class="btn btn-red" data-form="delete-series-all-{{ $schedule->id }}" data-label='ชุดทำซ้ำ "{{ $seriesLabel }}"{{ $seriesTimeLabel }}' data-warn="ระบบจะลบรายการทุกสัปดาห์ในชุดทำซ้ำนี้ทั้งหมด" onclick="tpssDelete(this)" data-testid="schedule-series-delete-all-button" style="display:inline-flex;align-items:center;gap:5px;">
                                    ลบทั้งชุด
                                </button>
                            @endif
                            <button type="button" class="btn btn-red" data-form="delete-schedule-{{ $schedule->id }}" data-label="{{ $seriesLabel }}{{ $weekLabel }} · {{ $timeText }}" onclick="tpssDelete(this)" data-testid="schedule-delete-button" style="display:inline-flex;align-items:center;gap:5px;">
                                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                {{ $schedule->schedule_template_id ? 'ลบการ์ดนี้' : 'ลบ' }}
                            </button>
                        </div>
                    @endif
                </section>
                </template>
            </div>

            @if($scheduleCanEdit && $schedule->scheduleTemplate)
                @php
                    $seriesTemplate = $schedule->scheduleTemplate;
                    $seriesUsesOld = (string) old('edit_series_template_id') === (string) $schedule->id;
                    $seriesOld = fn (string $key, mixed $default = null) => $seriesUsesOld ? old($key, $default) : $default;
                @endphp
                <div class="schedule-modal-backdrop" x-show="editSeriesModal === 'schedule-{{ $schedule->id }}'" x-cloak @click.self="editSeriesModal = null" data-testid="schedule-series-edit-modal">
                    <template x-if="editSeriesModal === 'schedule-{{ $schedule->id }}'">
                        <section class="schedule-modal is-form" role="dialog" aria-modal="true" aria-labelledby="schedule-series-edit-title-{{ $schedule->id }}">
                            <div class="modal-handle"></div>
                            <div class="modal-head">
                                <div>
                                    <div class="modal-title" id="schedule-series-edit-title-{{ $schedule->id }}">แก้ชุดทำซ้ำรายสัปดาห์</div>
                                    <div style="font-size:12px;font-weight:700;color:var(--fg-2);margin-top:3px;">การเปลี่ยนวัน เวลา ประเภทกิจกรรม และหัวข้อ จะ sync ไปยังรายการในชุดนี้ โดยไม่ทับห้องและกลุ่มรายสัปดาห์</div>
                                </div>
                                <button type="button" class="modal-close" @click="editSeriesModal = null" aria-label="ปิด">×</button>
                            </div>
                            <form method="POST" action="{{ route('maker.course_offerings.schedules.templates.update', [$offering, $seriesTemplate]) }}" data-testid="schedule-series-edit-form">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="modal_mode" value="series_edit">
                                <input type="hidden" name="edit_series_template_id" value="{{ $schedule->id }}">
                                <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                                <div class="modal-form-body">
                                    @if($seriesUsesOld && $errors->any())
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
                                        <div>
                                            <label class="modal-label" for="series_edit_weekday_{{ $schedule->id }}">วันในสัปดาห์ <span class="required-mark">*</span></label>
                                            <select id="series_edit_weekday_{{ $schedule->id }}" name="weekday" required class="modal-control">
                                                @foreach($thaiDays as $dayIso => $dayName)
                                                    <option value="{{ $dayIso }}" @selected((string) $seriesOld('weekday', $seriesTemplate->weekday) === (string) $dayIso)>{{ $dayName }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="modal-label" for="series_edit_start_week_{{ $schedule->id }}">สัปดาห์เริ่ม <span class="required-mark">*</span></label>
                                            <input id="series_edit_start_week_{{ $schedule->id }}" name="start_week" type="number" min="1" max="{{ max(1, (int) ($offering->teaching_weeks ?? 52)) }}" required class="modal-control" value="{{ $seriesOld('start_week', $seriesTemplate->start_week) }}">
                                        </div>
                                        <div>
                                            <label class="modal-label" for="series_edit_end_week_{{ $schedule->id }}">สัปดาห์สิ้นสุด <span class="required-mark">*</span></label>
                                            <input id="series_edit_end_week_{{ $schedule->id }}" name="end_week" type="number" min="1" max="{{ max(1, (int) ($offering->teaching_weeks ?? 52)) }}" required class="modal-control" value="{{ $seriesOld('end_week', $seriesTemplate->end_week) }}">
                                        </div>
                                        <div>
                                            <label class="modal-label" for="series_edit_start_time_{{ $schedule->id }}">เวลาเริ่ม <span class="required-mark">*</span></label>
                                            <input id="series_edit_start_time_{{ $schedule->id }}" name="start_time" type="time" required class="modal-control" value="{{ $seriesOld('start_time', substr((string) $seriesTemplate->start_time, 0, 5)) }}">
                                        </div>
                                        <div>
                                            <label class="modal-label" for="series_edit_end_time_{{ $schedule->id }}">เวลาสิ้นสุด <span class="required-mark">*</span></label>
                                            <input id="series_edit_end_time_{{ $schedule->id }}" name="end_time" type="time" required class="modal-control" value="{{ $seriesOld('end_time', substr((string) $seriesTemplate->end_time, 0, 5)) }}">
                                        </div>
                                        <div>
                                            <label class="modal-label" for="series_edit_activity_type_id_{{ $schedule->id }}">ประเภทกิจกรรม <span class="required-mark">*</span></label>
                                            <select id="series_edit_activity_type_id_{{ $schedule->id }}" name="activity_type_id" required class="modal-control">
                                                @foreach($activityTypes as $activityType)
                                                    <option value="{{ $activityType->id }}" @selected((string) $seriesOld('activity_type_id', $seriesTemplate->activity_type_id) === (string) $activityType->id)>
                                                        {{ $activityType->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="modal-field-full">
                                            <label class="modal-label" for="series_edit_topic_{{ $schedule->id }}">หัวข้อกิจกรรม <span class="required-mark">*</span></label>
                                            <input id="series_edit_topic_{{ $schedule->id }}" name="topic" type="text" maxlength="255" required class="modal-control" value="{{ $seriesOld('topic', $seriesTemplate->topic) }}">
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-actions">
                                    <button type="button" class="btn btn-secondary" @click="editSeriesModal = null">ยกเลิก</button>
                                    <button type="submit" class="btn btn-primary" data-testid="schedule-series-submit">บันทึกชุดทำซ้ำ</button>
                                </div>
                            </form>
                        </section>
                    </template>
                </div>
            @endif

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
                            data-schedule-check
                            @input="queueScheduleCheck($el)"
                            @change="queueScheduleCheck($el)"
                            x-data="{
                                startDateDisplay: @js($editDateDisplay('start_date', $schedule->start_date)),
                                endDateDisplay: @js($editDateDisplay('end_date', $schedule->end_date)),
                                multiDay: @js((bool) ($schedule->start_date && $schedule->end_date && $schedule->start_date->toDateString() !== $schedule->end_date->toDateString())),
                                editInstructorSearch: '',
                                editGroupSearch: '',
                                resourceCopySource: '',
                                init() {
                                    this.$watch('multiDay', (v) => { if (!v) this.endDateDisplay = this.startDateDisplay; });
                                    this.$watch('startDateDisplay', (v) => { if (!this.multiDay) this.endDateDisplay = v; });
                                },
                            }"
                        >
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="modal_mode" value="edit">
                            <input type="hidden" name="edit_schedule_id" value="{{ $schedule->id }}">
                            <input type="hidden" name="schedule_id" value="{{ $schedule->id }}">
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

                                @if($schedule->schedule_template_id)
                                    @php
                                        $resourceCopyOptions = $scheduleResourceCopyItems
                                            ->filter(fn ($item) => $item['template_id'] === (string) $schedule->schedule_template_id && $item['id'] !== (string) $schedule->id)
                                            ->values();
                                    @endphp
                                    <div class="series-toggle-panel" style="margin-bottom:12px;">
                                        <span class="series-badge" title="กิจกรรมทำซ้ำรายสัปดาห์">
                                            <span class="series-dot" aria-hidden="true"></span>
                                            <span>รายการทำซ้ำ</span>
                                            @if($schedule->series_week_number)
                                                <span>สัปดาห์ {{ $schedule->series_week_number }}</span>
                                            @endif
                                        </span>
                                        <div style="font-size:12px;font-weight:700;color:var(--fg-2);line-height:1.55;">
                                            ปรับ<strong>ห้อง · เวลา · ผู้สอน · กลุ่มนักศึกษา · หมายเหตุ</strong>แยกรายสัปดาห์ได้ ประเภทกิจกรรมและหัวข้อยึดตามชุดทำซ้ำ
                                        </div>
                                    </div>
                                    @if($resourceCopyOptions->isNotEmpty())
                                        <div class="resource-copy-panel">
                                            <div class="resource-copy-row">
                                                <div>
                                                    <label class="modal-label" for="resource_copy_{{ $schedule->id }}">ดึงรายละเอียดจากสัปดาห์อื่น</label>
                                                    <select id="resource_copy_{{ $schedule->id }}" class="modal-control" x-model="resourceCopySource">
                                                        <option value="">เลือกการ์ดต้นทาง</option>
                                                        @foreach($resourceCopyOptions as $copyOption)
                                                            <option value="{{ $copyOption['id'] }}">{{ $copyOption['label'] }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <button type="button" class="btn btn-secondary" @click="applyResourceCopy($el.closest('form'), resourceCopySource)" :disabled="!resourceCopySource">
                                                    คัดลอกรายละเอียด
                                                </button>
                                            </div>
                                            <div class="resource-copy-note">คัดลอกเฉพาะห้อง ผู้สอน กลุ่มนักศึกษา และหมายเหตุ แล้วกดบันทึกเพื่อใช้กับสัปดาห์นี้</div>
                                        </div>
                                    @endif
                                @endif

                                <div class="modal-form-grid">
                                    <div class="modal-field-full schedule-date-block {{ $dateTimeConflictNote ? 'modal-field-has-conflict' : '' }}">
                                        <div class="schedule-date-fields">
                                            <div>
                                                <label class="modal-label" for="edit_start_date_{{ $schedule->id }}"><span x-text="multiDay ? 'วันที่เริ่ม' : 'วันที่'">วันที่</span> <span class="required-mark">*</span></label>
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
                                                <div class="date-day-hint" x-show="scheduleDateHint(startDateDisplay)" x-cloak x-text="scheduleDateHint(startDateDisplay)"></div>
                                                <div class="date-holiday-warn" x-show="scheduleDateWarning(startDateDisplay)" x-cloak x-text="scheduleDateWarning(startDateDisplay)"></div>
                                                <label class="schedule-multiday-toggle">
                                                    <input type="checkbox" :checked="multiDay" @change="multiDay = $event.target.checked" data-testid="edit-multiday-toggle">
                                                    <span>กิจกรรมต่อเนื่องหลายวัน <small>(เช่น บล็อกฝึกปฏิบัติ)</small></span>
                                                </label>
                                            </div>
                                            <div x-show="multiDay" x-cloak>
                                                <label class="modal-label" for="edit_end_date_{{ $schedule->id }}">ถึงวันที่ <span class="required-mark">*</span></label>
                                                <x-thai-date-input
                                                    name="end_date"
                                                    :value="$editOld('end_date', $schedule->end_date?->format('Y-m-d'))"
                                                    id="edit_end_date_{{ $schedule->id }}"
                                                    class="modal-control"
                                                    :required="false"
                                                    :helper="false"
                                                    :year-start="$scheduleDatePickerYearStart"
                                                    :year-end="$scheduleDatePickerYearEnd"
                                                    x-bind:required="multiDay"
                                                    x-model="endDateDisplay" />
                                                <div class="date-day-hint" x-show="scheduleDateHint(endDateDisplay)" x-cloak x-text="scheduleDateHint(endDateDisplay)"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <template x-if="liveIssue('start_date').length || liveIssue('schedule').length">
                                        <div class="modal-field-full field-live-error" data-testid="live-error-start_date">
                                            <template x-for="msg in [...liveIssue('start_date'), ...liveIssue('schedule')]" :key="msg"><div x-text="msg"></div></template>
                                        </div>
                                    </template>
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
                                                                    @for($cycle = 0; $cycle < 3; $cycle++)
                                                                        @for($h = 0; $h < 24; $h++)
                                                                            @php $hh = sprintf('%02d', $h); @endphp
                                                                            <li data-val="{{ $hh }}" data-cycle="{{ $cycle }}" class="tp-hour-item {{ $cycle === 1 && $hh === ($editStartHour ?? '08') ? 'tp-sel' : '' }}">{{ $hh }}</li>
                                                                        @endfor
                                                                    @endfor
                                                                </ul>
                                                            </div>
                                                            <div class="tp-col-divider">:</div>
                                                            <div class="tp-col tp-col-min">
                                                                <ul>
                                                                    @for($cycle = 0; $cycle < 3; $cycle++)
                                                                        @foreach(range(0,59) as $m)
                                                                            @php $mm = sprintf('%02d', $m); @endphp
                                                                            <li data-val="{{ $mm }}" data-cycle="{{ $cycle }}" class="tp-min-item {{ $cycle === 1 && $mm === ($editStartMin ?? '00') ? 'tp-sel' : '' }}">{{ $mm }}</li>
                                                                        @endforeach
                                                                    @endfor
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
                                                                @for($cycle = 0; $cycle < 3; $cycle++)
                                                                    @for($h = 0; $h < 24; $h++)
                                                                        @php $hh = sprintf('%02d', $h); @endphp
                                                                        <li data-val="{{ $hh }}" data-cycle="{{ $cycle }}" class="tp-hour-item {{ $cycle === 1 && $hh === ($editEndHour ?? '09') ? 'tp-sel' : '' }}">{{ $hh }}</li>
                                                                    @endfor
                                                                @endfor
                                                            </ul>
                                                        </div>
                                                        <div class="tp-col-divider">:</div>
                                                        <div class="tp-col tp-col-min">
                                                            <ul>
                                                                @for($cycle = 0; $cycle < 3; $cycle++)
                                                                    @foreach(range(0,59) as $m)
                                                                        @php $mm = sprintf('%02d', $m); @endphp
                                                                        <li data-val="{{ $mm }}" data-cycle="{{ $cycle }}" class="tp-min-item {{ $cycle === 1 && $mm === ($editEndMin ?? '00') ? 'tp-sel' : '' }}">{{ $mm }}</li>
                                                                    @endforeach
                                                                @endfor
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
                                        <select id="edit_room_id_{{ $schedule->id }}" name="room_id" class="modal-control tpss-choices" :class="liveIssue('room_id').length ? 'has-live-error' : ''">
                                            <option value="">ไม่ระบุสถานที่</option>
                                            @foreach($rooms as $roomOption)
                                                <option value="{{ $roomOption->id }}" @selected((string) $editOld('room_id', $schedule->room_id) === (string) $roomOption->id)>
                                                    {{ $roomOption->room_code }} · {{ $roomOption->room_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <template x-if="liveIssue('room_id').length">
                                            <div class="field-live-error" data-testid="live-error-room_id">
                                                <template x-for="msg in liveIssue('room_id')" :key="msg"><div x-text="msg"></div></template>
                                            </div>
                                        </template>
                                        @if($roomConflictNote)
                                            <div class="modal-conflict-field">{{ $roomConflictNote }}</div>
                                        @endif
                                    </div>
                                    <div class="modal-field-full">
                                        <label class="modal-label" for="edit_topic_{{ $schedule->id }}">หัวข้อกิจกรรม <span class="required-mark">*</span></label>
                                        <input id="edit_topic_{{ $schedule->id }}" name="topic" type="text" maxlength="255" required class="modal-control" value="{{ $editOld('topic', $schedule->topic) }}" placeholder="เช่น บรรยายเรื่องการประเมินผู้ป่วย">
                                    </div>
                                    <div class="modal-field-full">
                                        <label class="modal-label" for="edit_remark_{{ $schedule->id }}">หมายเหตุ</label>
                                        <textarea id="edit_remark_{{ $schedule->id }}" name="remark" rows="2" class="modal-control" placeholder="เช่น ให้นักศึกษาเตรียมเอกสารก่อนเข้าเรียน หรือแจ้งอุปกรณ์ที่ต้องใช้">{{ $editOld('remark', $schedule->remark) }}</textarea>
                                    </div>
                                </div>

                                <div class="modal-section {{ $instructorConflictNote ? 'modal-field-has-conflict' : '' }}">
                                    <div class="modal-section-title">ผู้สอน <span class="required-mark">*</span></div>
                                    <template x-if="liveIssue('instructor_ids').length || liveIssue('lead_instructor_id').length">
                                        <div class="field-live-error" data-testid="live-error-instructor_ids">
                                            <template x-for="msg in [...liveIssue('instructor_ids'), ...liveIssue('lead_instructor_id')]" :key="msg"><div x-text="msg"></div></template>
                                        </div>
                                    </template>
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

                                {{-- V2: หัวหน้าวิชาไม่จัดกลุ่มย่อย (กลุ่มจัดหลังอนุมัติ โดยอาจารย์) — เก็บกลุ่มเดิมไว้ผ่าน hidden กัน update ล้าง --}}
                                @foreach($editGroupIds as $gid)
                                    <input type="hidden" name="student_group_ids[]" value="{{ $gid }}">
                                @endforeach
                            </div>
                            <div class="schedule-live-block" x-show="liveBlocking" x-cloak data-testid="schedule-live-block">
                                <span class="schedule-live-block-icon" aria-hidden="true">⛔</span>
                                <span>พบการชน/ข้อมูลไม่ถูกต้อง — แก้ไขจุดที่ไฮไลต์สีแดงก่อนจึงจะบันทึกได้</span>
                            </div>
                            <div class="modal-actions">
                                <button type="button" class="btn btn-secondary" @click="closeEdit()">ยกเลิก</button>
                                <button type="submit" class="btn btn-primary" data-testid="schedule-submit" x-bind:disabled="liveBlocking" x-bind:title="liveBlocking ? 'แก้ไขการชนก่อนบันทึก' : ''">บันทึกการแก้ไข</button>
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
                    <form method="POST" action="{{ $createAction }}" x-bind:action="createMode === 'series' ? seriesCreateAction : normalCreateAction" @submit="$el.action = createMode === 'series' ? seriesCreateAction : normalCreateAction" @input="queueScheduleCheck($el)" @change="queueScheduleCheck($el)" data-testid="schedule-form" x-ref="createForm">
                        @csrf
                        <input type="hidden" name="modal_mode" value="create">
                        <input type="hidden" name="create_mode" x-bind:value="createMode">
                        <input type="hidden" name="repeat_weekly" value="1" x-bind:disabled="createMode !== 'series'">
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

                            @if(! $isWorkspace && $courseOffering)
                                <div
                                    class="series-toggle-panel series-range-panel"
                                >
                                    <div class="schedule-type-head">ประเภทการจัดตาราง</div>
                                    <div class="schedule-type-seg" role="group" aria-label="ประเภทการจัดตาราง" data-testid="schedule-repeat-weekly">
                                        <button type="button" class="schedule-type-opt" :class="{ 'is-active': createMode !== 'series' && !createMultiDay }" @click="setCreateMode('single'); createMultiDay = false" data-testid="schedule-type-single">
                                            <strong>วันเดียว</strong>
                                            <small>กิจกรรมจบในวันเดียว</small>
                                        </button>
                                        <button type="button" class="schedule-type-opt" :class="{ 'is-active': createMode !== 'series' && createMultiDay }" @click="setCreateMode('single'); createMultiDay = true" data-testid="schedule-type-block">
                                            <strong>ต่อเนื่องหลายวัน</strong>
                                            <small>บล็อกฝึกปฏิบัติ</small>
                                        </button>
                                        <button type="button" class="schedule-type-opt" :class="{ 'is-active': createMode === 'series' }" @click="setCreateMode('series')" data-testid="schedule-series-toggle">
                                            <strong>ซ้ำรายสัปดาห์</strong>
                                            <small>Series ทั้งเทอม</small>
                                        </button>
                                    </div>
                                    <div class="series-fields" x-show="createMode === 'series'" x-cloak>
                                        @php
                                            $seriesDefaultStartDate = $academicStartDate?->toDateString() ?? ($selectedScheduleDate ?? $weekStart)->toDateString();
                                            $seriesDefaultEndDate = $academicStartDate
                                                ? $academicStartDate->addWeeks(max(1, (int) ($courseOffering->teaching_weeks ?? 1)))->subDay()->toDateString()
                                                : ($weekEnd ?? $weekStart)->toDateString();
                                        @endphp
                                        <input id="series_start_week" name="start_week" type="hidden" value="{{ old('start_week', 1) }}" data-default-value="1" x-bind:disabled="createMode !== 'series'">
                                        <input id="series_end_week" name="end_week" type="hidden" value="{{ old('end_week', $courseOffering->teaching_weeks ?? 1) }}" data-default-value="{{ max(1, (int) ($courseOffering->teaching_weeks ?? 1)) }}" x-bind:disabled="createMode !== 'series'">
                                        <div>
                                            <label class="modal-label" for="series_weekday">วันในสัปดาห์ <span class="required-mark">*</span></label>
                                            <select id="series_weekday" name="weekday" class="modal-control" x-bind:required="createMode === 'series'" x-bind:disabled="createMode !== 'series'">
                                                @foreach($thaiDays as $dayIso => $dayName)
                                                    <option value="{{ $dayIso }}" @selected((string) old('weekday', $selectedScheduleDate?->dayOfWeekIso ?? $weekStart->dayOfWeekIso) === (string) $dayIso)>{{ $dayName }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <span class="modal-label series-range-placeholder" aria-hidden="true">ช่วงวันที่</span>
                                            <label class="series-toggle-choice series-range-switch">
                                                <input class="series-range-checkbox" type="checkbox" name="use_custom_series_range" value="1" @checked(old('use_custom_series_range')) x-on:change="if (! $event.target.checked) document.dispatchEvent(new CustomEvent('tpss:close-date-popovers'))">
                                                <span>
                                                    เลือกช่วงวันที่เอง
                                                    <small>ใช้เฉพาะกรณีไม่ต้องสร้างครบทั้งช่วงรายวิชา</small>
                                                </span>
                                            </label>
                                        </div>
                                        <div class="series-range-fields">
                                            <div>
                                                <label class="modal-label" for="series_starts_on">เริ่มวันที่</label>
                                                <x-thai-date-input
                                                    name="starts_on"
                                                    id="series_starts_on"
                                                    class="modal-control"
                                                    :value="old('starts_on', $seriesDefaultStartDate)"
                                                    :required="false"
                                                    :helper="false"
                                                    :year-start="$scheduleDatePickerYearStart"
                                                    :year-end="$scheduleDatePickerYearEnd" />
                                            </div>
                                            <div>
                                                <label class="modal-label" for="series_ends_on">สิ้นสุดวันที่</label>
                                                <x-thai-date-input
                                                    name="ends_on"
                                                    id="series_ends_on"
                                                    class="modal-control"
                                                    :value="old('ends_on', $seriesDefaultEndDate)"
                                                    :required="false"
                                                    :helper="false"
                                                    :year-start="$scheduleDatePickerYearStart"
                                                    :year-end="$scheduleDatePickerYearEnd" />
                                            </div>
                                        </div>
                                    </div>
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
                                                    {{ $optionCourse?->course_code ?? '-' }} · {{ $optionCourse?->name_th ?? $optionCourse?->name_en ?? '-' }} · ปี {{ $optionYear?->name ?? '-' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                @else
                                    <input type="hidden" name="course_offering_id" value="{{ $courseOffering->id }}" data-testid="schedule-course-offering">
                                @endif

                                <div class="modal-field-full schedule-date-block" x-show="createMode !== 'series'" x-cloak>
                                    <div class="schedule-date-fields">
                                        <div>
                                            <label class="modal-label" for="start_date"><span x-text="createMultiDay ? 'วันที่เริ่ม' : 'วันที่'">วันที่</span> <span class="required-mark">*</span></label>
                                            <x-thai-date-input
                                                name="start_date"
                                                id="start_date"
                                                class="modal-control"
                                                :required="false"
                                                :helper="false"
                                                :year-start="$scheduleDatePickerYearStart"
                                                :year-end="$scheduleDatePickerYearEnd"
                                                x-bind:required="createMode !== 'series'"
                                                x-bind:disabled="createMode === 'series'"
                                                x-model="createStartDate" />
                                            <div class="date-day-hint" x-show="scheduleDateHint(createStartDate)" x-cloak x-text="scheduleDateHint(createStartDate)" data-testid="create-date-hint"></div>
                                            <div class="date-holiday-warn" x-show="scheduleDateWarning(createStartDate)" x-cloak x-text="scheduleDateWarning(createStartDate)" data-testid="create-date-holiday-warn"></div>
                                        </div>
                                        <div x-show="createMultiDay" x-cloak>
                                            <label class="modal-label" for="end_date">ถึงวันที่ <span class="required-mark">*</span></label>
                                            <x-thai-date-input
                                                name="end_date"
                                                id="end_date"
                                                class="modal-control"
                                                :required="false"
                                                :helper="false"
                                                :year-start="$scheduleDatePickerYearStart"
                                                :year-end="$scheduleDatePickerYearEnd"
                                                x-bind:required="createMode !== 'series' && createMultiDay"
                                                x-bind:disabled="createMode === 'series'"
                                                x-model="createEndDate" />
                                            <div class="date-day-hint" x-show="scheduleDateHint(createEndDate)" x-cloak x-text="scheduleDateHint(createEndDate)"></div>
                                        </div>
                                    </div>
                                </div>
                                <template x-if="liveIssue('start_date').length || liveIssue('schedule').length">
                                    <div class="modal-field-full field-live-error" data-testid="live-error-start_date">
                                        <template x-for="msg in [...liveIssue('start_date'), ...liveIssue('schedule')]" :key="msg"><div x-text="msg"></div></template>
                                    </div>
                                </template>
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
                                                            @for($cycle = 0; $cycle < 3; $cycle++)
                                                                @for($h = 0; $h < 24; $h++)
                                                                    @php $hh = sprintf('%02d', $h); @endphp
                                                                    <li data-val="{{ $hh }}" data-cycle="{{ $cycle }}" class="tp-hour-item {{ $cycle === 1 && $oldStartHour && $hh === $oldStartHour ? 'tp-sel' : '' }}">{{ $hh }}</li>
                                                                @endfor
                                                            @endfor
                                                        </ul>
                                                    </div>
                                                    <div class="tp-col-divider">:</div>
                                                    <div class="tp-col tp-col-min">
                                                        <ul>
                                                            @for($cycle = 0; $cycle < 3; $cycle++)
                                                                @foreach(range(0,59) as $m)
                                                                    @php $mm = sprintf('%02d', $m); @endphp
                                                                    <li data-val="{{ $mm }}" data-cycle="{{ $cycle }}" class="tp-min-item {{ $cycle === 1 && $oldStartMin && $mm === $oldStartMin ? 'tp-sel' : '' }}">{{ $mm }}</li>
                                                                @endforeach
                                                            @endfor
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
                                                            @for($cycle = 0; $cycle < 3; $cycle++)
                                                                @for($h = 0; $h < 24; $h++)
                                                                    @php $hh = sprintf('%02d', $h); @endphp
                                                                    <li data-val="{{ $hh }}" data-cycle="{{ $cycle }}" class="tp-hour-item {{ $cycle === 1 && $oldEndHour && $hh === $oldEndHour ? 'tp-sel' : '' }}">{{ $hh }}</li>
                                                                @endfor
                                                            @endfor
                                                        </ul>
                                                    </div>
                                                    <div class="tp-col-divider">:</div>
                                                    <div class="tp-col tp-col-min">
                                                        <ul>
                                                            @for($cycle = 0; $cycle < 3; $cycle++)
                                                                @foreach(range(0,59) as $m)
                                                                    @php $mm = sprintf('%02d', $m); @endphp
                                                                    <li data-val="{{ $mm }}" data-cycle="{{ $cycle }}" class="tp-min-item {{ $cycle === 1 && $oldEndMin && $mm === $oldEndMin ? 'tp-sel' : '' }}">{{ $mm }}</li>
                                                                @endforeach
                                                            @endfor
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
                                    <select id="room_id" name="room_id" class="modal-control tpss-choices" :class="liveIssue('room_id').length ? 'has-live-error' : ''">
                                        <option value="">ไม่ระบุสถานที่</option>
                                        @foreach($rooms as $room)
                                            <option value="{{ $room->id }}" @selected((string) old('room_id') === (string) $room->id)>
                                                {{ $room->room_code }} · {{ $room->room_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <template x-if="liveIssue('room_id').length">
                                        <div class="field-live-error" data-testid="live-error-room_id">
                                            <template x-for="msg in liveIssue('room_id')" :key="msg"><div x-text="msg"></div></template>
                                        </div>
                                    </template>
                                </div>
                                <div class="modal-field-full">
                                    <label class="modal-label" for="topic">หัวข้อกิจกรรม <span class="required-mark">*</span></label>
                                    <input id="topic" name="topic" type="text" maxlength="255" required class="modal-control" value="{{ old('topic') }}" placeholder="เช่น บรรยายเรื่องการประเมินผู้ป่วย">
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
                                        <template x-if="liveIssue('instructor_ids').length || liveIssue('lead_instructor_id').length">
                                            <div class="field-live-error" data-testid="live-error-instructor_ids">
                                                <template x-for="msg in [...liveIssue('instructor_ids'), ...liveIssue('lead_instructor_id')]" :key="msg"><div x-text="msg"></div></template>
                                            </div>
                                        </template>
                                        <div class="optional-note" x-show="createMode === 'series'" x-cloak style="margin-bottom:8px;">ถ้าเลือกไว้ ระบบจะใส่ให้เฉพาะการ์ดสัปดาห์แรก สัปดาห์ถัดไปเติมหรือคัดลอกภายหลังได้</div>
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

                                    {{-- V2: หัวหน้าวิชาไม่จัดกลุ่มย่อยตอนสร้างกิจกรรม (กลุ่มจัดหลังอนุมัติ โดยอาจารย์) — ตัด selector ออก --}}
                                </div>
                            @endforeach
                        </div>
                        <div class="schedule-live-block" x-show="liveBlocking" x-cloak data-testid="schedule-live-block">
                            <span class="schedule-live-block-icon" aria-hidden="true">⛔</span>
                            <span>พบการชน/ข้อมูลไม่ถูกต้อง — แก้ไขจุดที่ไฮไลต์สีแดงก่อนจึงจะบันทึกได้</span>
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-secondary" @click="closeCreate()">ยกเลิก</button>
                            <button type="submit" class="btn btn-primary" data-testid="schedule-submit" x-bind:disabled="liveBlocking" x-bind:title="liveBlocking ? 'แก้ไขการชนก่อนบันทึก' : ''">บันทึกรายการสอน</button>
                        </div>
                    </form>
                </section>
            </div>
        @endif

        @if(! $isWorkspace && $courseOffering && $canEdit)
            <form method="POST" action="{{ route('maker.course_offerings.schedules.copy_week', $courseOffering) }}" x-ref="copyWeekForm" style="display:none;">
                @csrf
                <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                <input type="hidden" name="source_week_start" :value="copyWeekSource">
                <input type="hidden" name="target_week_start" :value="copyWeekTarget">
            </form>

            <div class="schedule-modal-backdrop" x-show="copyWeekOpen" x-cloak @click.self="copyWeekOpen = false" @keydown.escape.window="copyWeekOpen = false" data-testid="schedule-copy-week-modal">
                <section class="schedule-modal is-form" role="dialog" aria-modal="true" aria-labelledby="copy-week-title">
                    <div class="modal-handle"></div>
                    <div class="modal-head">
                        <div>
                            <div class="modal-title" id="copy-week-title">คัดลอกกิจกรรมจากสัปดาห์อื่น</div>
                            <div class="copy-week-subtitle">ดึงทุกกิจกรรมจากสัปดาห์ที่จัดไว้แล้วมาลงสัปดาห์นี้ — ระบบจะข้ามรายการที่ชนให้อัตโนมัติ</div>
                        </div>
                        <button type="button" class="modal-close" @click="copyWeekOpen = false" aria-label="ปิด">×</button>
                    </div>
                    <div class="modal-form-body">
                        <div class="copy-week-weeks">
                            <div class="copy-week-pill">
                                <span class="copy-week-pill-label">ดึงจากสัปดาห์</span>
                                <span class="copy-week-target-control">
                                    <button type="button" class="copy-week-step" @click="shiftCopyWeekSource(-1)" aria-label="สัปดาห์ก่อนหน้า">‹</button>
                                    <span class="copy-week-pill-date" x-text="window.tpssThaiWeekLabel(copyWeekSource)"></span>
                                    <button type="button" class="copy-week-step" @click="shiftCopyWeekSource(1)" aria-label="สัปดาห์ถัดไป">›</button>
                                </span>
                            </div>
                            <span class="copy-week-arrow" aria-hidden="true">→</span>
                            <div class="copy-week-pill is-target">
                                <span class="copy-week-pill-label">มาที่สัปดาห์</span>
                                <span class="copy-week-target-control">
                                    <button type="button" class="copy-week-step" @click="shiftCopyWeekTarget(-1)" aria-label="สัปดาห์ก่อนหน้า">‹</button>
                                    <span class="copy-week-pill-date" x-text="window.tpssThaiWeekLabel(copyWeekTarget)"></span>
                                    <button type="button" class="copy-week-step" @click="shiftCopyWeekTarget(1)" aria-label="สัปดาห์ถัดไป">›</button>
                                </span>
                            </div>
                        </div>

                        <div class="copy-week-status" x-show="copyWeekLoading">กำลังตรวจสอบการชน…</div>
                        <div class="copy-week-status is-error" x-show="copyWeekError" x-text="copyWeekError"></div>

                        <template x-if="copyWeekPreview && !copyWeekLoading">
                            <div>
                                <div class="copy-week-status" x-show="copyWeekPreview.total === 0">ไม่พบรายการในสัปดาห์ต้นทางให้คัดลอก</div>

                                <div class="copy-week-summary" x-show="copyWeekPreview.total > 0">
                                    <span class="copy-week-badge is-ready" x-text="'พร้อมคัดลอก ' + copyWeekPreview.ready.length + ' รายการ'"></span>
                                    <span class="copy-week-badge is-blocked" x-show="copyWeekPreview.blocked.length" x-text="'ชน ' + copyWeekPreview.blocked.length + ' รายการ'"></span>
                                </div>

                                <ul class="copy-week-list" x-show="copyWeekPreview.ready.length">
                                    <template x-for="item in copyWeekPreview.ready" :key="'r' + item.target_date + item.time + (item.topic || '')">
                                        <li class="copy-week-item is-ready">
                                            <span class="copy-week-mark" aria-hidden="true">✓</span>
                                            <span class="copy-week-item-body">
                                                <strong x-text="item.topic || item.activity || 'กิจกรรม'"></strong>
                                                <small x-text="item.target_date + ' · ' + item.time + (item.room ? ' · ' + item.room : '')"></small>
                                            </span>
                                        </li>
                                    </template>
                                </ul>

                                <ul class="copy-week-list" x-show="copyWeekPreview.blocked.length">
                                    <template x-for="item in copyWeekPreview.blocked" :key="'b' + item.target_date + item.time + (item.topic || '')">
                                        <li class="copy-week-item is-blocked">
                                            <span class="copy-week-mark" aria-hidden="true">⛔</span>
                                            <span class="copy-week-item-body">
                                                <strong x-text="item.topic || item.activity || 'กิจกรรม'"></strong>
                                                <small x-text="item.target_date + ' · ' + item.time"></small>
                                                <template x-for="reason in item.reasons" :key="reason">
                                                    <small class="copy-week-reason" x-text="reason"></small>
                                                </template>
                                            </span>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </template>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" @click="copyWeekOpen = false">ยกเลิก</button>
                        <button
                            type="button"
                            class="btn btn-primary"
                            @click="submitCopyWeek()"
                            x-bind:disabled="copyWeekLoading || !copyWeekPreview || copyWeekPreview.ready.length === 0"
                            data-testid="schedule-copy-week-confirm"
                            x-text="(copyWeekPreview && copyWeekPreview.ready.length) ? ('คัดลอก ' + copyWeekPreview.ready.length + ' รายการ') : 'คัดลอก'"
                        >คัดลอก</button>
                    </div>
                </section>
            </div>
        @endif
    </div>
</x-app-layout>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Conflict pill tooltip: PORTAL pattern ───────────────────────────────
    // เหตุผล: card parent มี transform บน :hover → สร้าง containing block ใหม่
    // ทำให้ position:fixed ของ tooltip ติดอยู่ใน card อีก
    // วิธีแก้: ย้าย tooltip content ไปอยู่ใต้ <body> (escape ทุก ancestor)
    (function () {
        var portal = document.createElement('div');
        portal.id = 'conflict-tt-portal';
        portal.style.cssText = 'position:fixed;top:0;left:0;z-index:99999;display:none;';
        portal.setAttribute('role', 'tooltip');
        document.body.appendChild(portal);

        var openPill = null;

        function place(pill) {
            // Clone content from this pill's hidden tooltip into the portal
            var source = pill.querySelector('.conflict-tt');
            if (!source) return;
            portal.innerHTML = source.innerHTML;
            portal.className = 'conflict-tt conflict-tt--portal';

            // Show invisibly to measure
            portal.style.visibility = 'hidden';
            portal.style.display = 'block';
            var ttRect = portal.getBoundingClientRect();
            var pillRect = pill.getBoundingClientRect();
            var vw = document.documentElement.clientWidth;  // exclude scrollbar
            var vh = document.documentElement.clientHeight;
            var margin = 16;  // breathing room from viewport edges

            // Vertical: prefer below; flip above if not enough space
            var top = pillRect.bottom + 6;
            if (top + ttRect.height > vh - margin) {
                top = Math.max(margin, pillRect.top - ttRect.height - 6);
            }

            // Horizontal: prefer side of pill with more space
            // - pill in left half → align tooltip's LEFT with pill's left
            // - pill in right half → align tooltip's RIGHT with pill's right
            var pillCenter = pillRect.left + pillRect.width / 2;
            var left = pillCenter < vw / 2
                ? pillRect.left
                : pillRect.right - ttRect.width;

            // Clamp inside viewport
            left = Math.max(margin, Math.min(left, vw - ttRect.width - margin));

            portal.style.top = Math.round(top) + 'px';
            portal.style.left = Math.round(left) + 'px';
            portal.style.visibility = '';
        }

        function close() {
            portal.style.display = 'none';
            portal.innerHTML = '';
            openPill = null;
        }

        function open(pill) {
            openPill = pill;
            place(pill);
        }

        document.addEventListener('mouseover', function (e) {
            var pill = e.target.closest('[data-conflict-pill]');
            if (pill && pill !== openPill) open(pill);
        });
        document.addEventListener('mouseout', function (e) {
            var pill = e.target.closest('[data-conflict-pill]');
            if (!pill || pill !== openPill) return;
            var related = e.relatedTarget;
            if (related && pill.contains(related)) return;
            close();
        });
        document.addEventListener('focusin', function (e) {
            var pill = e.target.closest('[data-conflict-pill]');
            if (pill) open(pill);
        });
        document.addEventListener('focusout', function (e) {
            var pill = e.target.closest('[data-conflict-pill]');
            if (pill === openPill) close();
        });
        window.addEventListener('scroll', function () { if (openPill) place(openPill); }, true);
        window.addEventListener('resize', function () { if (openPill) place(openPill); });
    })();

    // ── Custom time-picker engine ───────────────────────────────────────────
    var _openDrop = null; // currently open .tp-drop

    function centerTimeItem(selected, behavior) {
        if (!selected) return;
        var col = selected.closest('.tp-col');
        if (!col) return;

        var targetTop = selected.offsetTop - ((col.clientHeight - selected.offsetHeight) / 2);
        col.scrollTo({
            top: Math.max(0, targetTop),
            behavior: behavior || 'auto'
        });
    }

    function timePartSelector(part) {
        return part === 'hour' ? '.tp-hour-item' : '.tp-min-item';
    }

    function findCanonicalTimeItem(drop, part, value) {
        var selector = timePartSelector(part) + '[data-val="' + value + '"][data-cycle="1"]';
        return drop.querySelector(selector) || drop.querySelector(timePartSelector(part) + '[data-val="' + value + '"]');
    }

    function bindCyclicTimeColumn(col) {
        if (!col || col._tpCycleBound) return;
        col._tpCycleBound = true;

        col.addEventListener('scroll', function() {
            if (col._tpCycling) return;

            var first = col.querySelector('li');
            if (!first) return;

            var items = col.querySelectorAll('li');
            var uniqueCount = col.classList.contains('tp-col-hour') ? 24 : 60;
            if (items.length < uniqueCount * 3) return;

            var segmentHeight = first.offsetHeight * uniqueCount;
            if (!segmentHeight) return;

            if (col.scrollTop < segmentHeight * 0.25) {
                col._tpCycling = true;
                col.scrollTop += segmentHeight;
                requestAnimationFrame(function() { col._tpCycling = false; });
            } else if (col.scrollTop > segmentHeight * 1.75) {
                col._tpCycling = true;
                col.scrollTop -= segmentHeight;
                requestAnimationFrame(function() { col._tpCycling = false; });
            }
        }, { passive: true });
    }

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
        drop.querySelectorAll('.tp-col').forEach(bindCyclicTimeColumn);

        // align selected hour and minute with the center guide frame.
        // Empty controls still open in the middle cycle to avoid the top padding gap.
        var fallbackHour = picker.id && picker.id.indexOf('end') !== -1 ? '09' : '08';
        var selHour = drop.querySelector('.tp-hour-item.tp-sel[data-cycle="1"]')
            || drop.querySelector('.tp-hour-item.tp-sel')
            || findCanonicalTimeItem(drop, 'hour', fallbackHour);
        centerTimeItem(selHour);

        var selMin = drop.querySelector('.tp-min-item.tp-sel[data-cycle="1"]')
            || drop.querySelector('.tp-min-item.tp-sel')
            || findCanonicalTimeItem(drop, 'min', '00');
        centerTimeItem(selMin);

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
        var selected = findCanonicalTimeItem(drop, part, val) || li;
        selected.classList.add('tp-sel');
        centerTimeItem(selected, 'smooth');
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
                        li.classList.toggle('tp-sel', li.dataset.val === h && li.dataset.cycle === '1');
                    });
                    drop.querySelectorAll('.tp-min-item').forEach(function(li) {
                        li.classList.toggle('tp-sel', li.dataset.val === m && li.dataset.cycle === '1');
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
