@php
    $course = $courseOffering->course;
    $academicYear = $courseOffering->academicYear;
    $schedule = $schedule ?? null;
    $isEditing = filled($schedule);
    $oldInstructors = collect(old('instructor_ids', $schedule?->instructors?->pluck('id')->all() ?? []))->map(fn ($id) => (string) $id);
    $oldGroups = collect(old('student_group_ids', $schedule?->studentGroups?->pluck('id')->all() ?? []))->map(fn ($id) => (string) $id);
    $activityOptions = $activityTypes->map(fn ($type) => ['id' => (string) $type->id, 'label' => $type->name])->values();
    $roomOptions = $rooms->map(fn ($room) => [
        'id' => (string) $room->id,
        'label' => trim($room->room_code.' '.($room->room_name ? '· '.$room->room_name : '')),
    ])->values();
    $groupOptions = $courseOffering->studentGroups
        ->map(fn ($group) => [
            'id' => (string) $group->id,
            'student_count' => (int) $group->student_count,
        ])
        ->values();
    $scheduleConflicts = $existingSchedules
        ->reject(fn ($item) => $isEditing && (int) $item->id === (int) $schedule->id)
        ->map(fn ($item) => [
            'id' => (string) $item->id,
            'start_date' => $item->start_date?->format('Y-m-d'),
            'end_date' => $item->end_date?->format('Y-m-d'),
            'start_time' => substr((string) $item->start_time, 0, 5),
            'end_time' => substr((string) $item->end_time, 0, 5),
            'groups' => $item->studentGroups
                ->map(fn ($group) => ['id' => (string) $group->id, 'code' => $group->group_code])
                ->values(),
        ])
        ->values();
    $formAction = $isEditing
        ? route('maker.course_offerings.schedules.update', [$courseOffering, $schedule])
        : route('maker.course_offerings.schedules.store', $courseOffering);
    $checkConflictUrl = route('maker.course_offerings.schedules.check_conflicts', $courseOffering);
    $pageTitle = $isEditing ? 'แก้ไขรายการสอน' : 'เพิ่มรายการสอน';
    $initialStartDate = old('start_date', $schedule?->start_date?->format('Y-m-d') ?? '');
    $initialEndDate = old('end_date', $schedule?->end_date?->format('Y-m-d') ?? '');
    $initialStartTime = old('start_time', $schedule ? substr((string) $schedule->start_time, 0, 5) : '08:00');
    $initialEndTime = old('end_time', $schedule ? substr((string) $schedule->end_time, 0, 5) : '10:00');
@endphp

<x-app-layout :title="$pageTitle">
    <style>
        .schedule-entry-head {
            display: grid;
            gap: 10px;
            margin-bottom: 18px;
        }
        .schedule-back-link {
            color: var(--brand-navy);
            text-decoration: none;
            font-weight: 700;
        }
        .schedule-back-link:hover {
            text-decoration: underline;
        }
        .schedule-entry-card {
            border: 1px solid oklch(82% 0.028 235);
            border-radius: 10px;
            background: linear-gradient(180deg, oklch(99% 0.006 235), oklch(96.5% 0.014 235));
            padding: 14px 18px;
            box-shadow: 0 1px 0 oklch(90% 0.018 235);
        }
        .schedule-entry-kicker {
            color: var(--fg-3);
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .schedule-entry-title {
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 800;
            line-height: 1.2;
            color: var(--fg-1);
            letter-spacing: 0;
            margin: 0;
        }
        .schedule-entry-desc {
            margin-top: 6px;
            color: var(--fg-2);
            font-size: 13px;
            line-height: 1.6;
        }
        .schedule-form-layout {
            display: grid;
            grid-template-columns: minmax(240px, 320px) minmax(0, 1fr);
            gap: 20px;
            align-items: start;
        }
        .schedule-panel {
            border: 1px solid oklch(84% 0.025 235);
            border-radius: 10px;
            background: var(--surface);
            box-shadow: 0 1px 0 oklch(90% 0.018 235);
            overflow: visible;
        }
        .schedule-panel-body {
            padding: 18px;
        }
        .schedule-side-head {
            background: var(--brand-navy);
            color: oklch(98% 0.005 240);
            border-radius: 10px 10px 0 0;
            padding: 18px;
        }
        .schedule-side-head .caption {
            color: oklch(80% 0.04 245);
        }
        .schedule-course-code {
            font-family: var(--font-display);
            font-size: 24px;
            font-weight: 700;
            color: oklch(98% 0.005 240);
            line-height: 1.25;
        }
        .schedule-side-list {
            display: grid;
            gap: 10px;
            padding: 14px;
            background: oklch(96% 0.012 235);
            border-radius: 0 0 10px 10px;
        }
        .schedule-side-item {
            border: 1px solid oklch(88% 0.018 235);
            border-radius: 8px;
            background: oklch(99% 0.004 240);
            padding: 12px;
        }
        .schedule-main-panel {
            background: oklch(96.5% 0.012 235);
        }
        .schedule-section {
            margin: 14px;
            border: 1px solid oklch(86% 0.02 235);
            border-radius: 10px;
            background: oklch(99% 0.004 240);
            overflow: visible;
        }
        .schedule-section:first-child {
            border-top: 1px solid oklch(86% 0.02 235);
        }
        .schedule-section-head {
            background: oklch(93.5% 0.02 235);
            border-bottom: 1px solid oklch(86% 0.02 235);
            padding: 14px 18px;
        }
        .schedule-section-body {
            padding: 18px;
        }
        .schedule-time-fields {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            align-items: start;
        }
        .schedule-period-fields {
            display: grid;
            grid-template-columns: minmax(320px, 1.4fr) minmax(150px, .55fr) minmax(150px, .55fr);
            gap: 14px;
            align-items: start;
        }
        .schedule-section-title {
            font-family: var(--font-display);
            font-size: 17px;
            font-weight: 700;
            color: var(--fg-1);
            margin-bottom: 4px;
        }
        .schedule-section-subtitle {
            color: var(--fg-3);
            font-size: 12px;
        }
        .schedule-check-list {
            display: grid;
            gap: 8px;
            border: 1px solid oklch(84% 0.025 235);
            border-radius: 8px;
            padding: 10px;
            background: oklch(96.5% 0.012 235);
            height: 136px;
            overflow: auto;
            align-content: start;
        }
        .schedule-check-option {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            padding: 8px;
            border-radius: 6px;
            font-weight: 500;
            line-height: 1.45;
        }
        .schedule-check-option:hover {
            background: var(--surface);
        }
        .schedule-check-option.has-conflict {
            color: var(--fg-3);
            opacity: .65;
        }
        .schedule-check-option.has-conflict:hover {
            background: var(--surface);
        }
        .schedule-inline-alert {
            color: var(--status-conflict-fg);
            font-weight: 700;
            font-size: 12px;
            padding: 0 4px;
        }
        .schedule-realtime-alert {
            color: var(--status-conflict-fg);
            font-size: 12px;
            font-weight: 700;
            margin-top: 6px;
        }
        .schedule-realtime-note {
            color: var(--status-warning-fg, oklch(42% 0.08 75));
            font-size: 12px;
            font-weight: 700;
            margin-top: 6px;
        }
        .schedule-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
            border-top: 1px solid oklch(84% 0.025 235);
            padding: 16px 20px;
            background: oklch(93.5% 0.02 235);
            border-radius: 0 0 10px 10px;
        }
        .schedule-actions .schedule-inline-alert {
            display: inline-flex;
            align-items: center;
            min-height: 38px;
        }
        .schedule-select {
            position: relative;
        }
        .schedule-select-button {
            width: 100%;
            min-height: 46px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: var(--r-md);
            background: var(--surface);
            color: var(--fg-1);
            text-align: left;
            font: inherit;
            cursor: pointer;
        }
        .schedule-select-button:focus {
            outline: none;
            border-color: var(--brand-navy);
            box-shadow: 0 0 0 3px oklch(45% 0.12 250 / 0.14);
        }
        .schedule-select-menu {
            position: absolute;
            z-index: 80;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            max-height: 240px;
            overflow-y: auto;
            border: 1px solid oklch(82% 0.025 235);
            border-radius: 8px;
            background: var(--surface);
            box-shadow: 0 12px 28px oklch(28% 0.03 240 / 0.18);
            padding: 6px;
        }
        .schedule-select-search,
        .schedule-check-search {
            width: 100%;
            min-height: 36px;
            border: 1px solid oklch(84% 0.025 235);
            border-radius: 8px;
            background: oklch(98% 0.008 235);
            color: var(--fg-1);
            font: inherit;
            padding: 7px 9px;
        }
        .schedule-select-search {
            margin-bottom: 6px;
        }
        .schedule-check-search {
            margin-bottom: 8px;
        }
        .schedule-select-search:focus,
        .schedule-check-search:focus {
            outline: none;
            border-color: var(--brand-navy);
            box-shadow: 0 0 0 3px oklch(45% 0.12 250 / 0.12);
        }
        .schedule-select-empty {
            padding: 10px 11px;
            color: var(--fg-3);
            font-size: 12px;
        }
        .schedule-select-option {
            width: 100%;
            display: block;
            padding: 10px 11px;
            border: 0;
            border-radius: 6px;
            background: transparent;
            text-align: left;
            color: var(--fg-1);
            font: inherit;
            cursor: pointer;
        }
        .schedule-select-option:hover,
        .schedule-select-option.is-selected {
            background: oklch(94% 0.025 245);
            color: var(--brand-navy);
            font-weight: 700;
        }
        .schedule-select-placeholder {
            color: var(--fg-3);
        }
        .schedule-picker {
            position: relative;
        }
        .schedule-picker-button {
            width: 100%;
            min-height: 42px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 8px 12px;
            border: 1px solid oklch(80% 0.032 235);
            border-radius: 10px;
            background: linear-gradient(180deg, oklch(99% 0.004 240), oklch(97% 0.01 235));
            color: var(--fg-1);
            text-align: left;
            font: inherit;
            cursor: pointer;
            box-shadow: inset 0 1px 0 oklch(100% 0 0 / 0.65);
        }
        .schedule-picker-button:focus {
            outline: none;
            border-color: var(--brand-navy);
            box-shadow: 0 0 0 3px oklch(45% 0.12 250 / 0.14);
        }
        .schedule-picker-value {
            display: flex;
            align-items: baseline;
            gap: 8px;
            min-width: 0;
        }
        .schedule-picker-main {
            font-weight: 700;
            color: var(--fg-1);
            white-space: nowrap;
        }
        .schedule-picker-sub {
            font-size: 12px;
            color: var(--fg-3);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .schedule-picker-popover {
            position: absolute;
            z-index: 90;
            top: calc(100% + 8px);
            left: 0;
            width: min(340px, 92vw);
            border: 1px solid oklch(76% 0.04 235);
            border-radius: 12px;
            background: var(--surface);
            box-shadow: 0 14px 28px oklch(28% 0.025 240 / 0.12);
            overflow: hidden;
        }
        .schedule-calendar-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 14px;
            background: var(--brand-navy);
            color: oklch(98% 0.005 240);
        }
        .schedule-calendar-title {
            font-weight: 700;
        }
        .schedule-calendar-nav {
            display: inline-flex;
            gap: 6px;
        }
        .schedule-calendar-nav button {
            width: 30px;
            height: 30px;
            border: 1px solid oklch(100% 0 0 / 0.2);
            border-radius: 7px;
            background: oklch(100% 0 0 / 0.08);
            color: inherit;
            cursor: pointer;
        }
        .schedule-calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 4px;
            padding: 12px;
        }
        .schedule-calendar-dow {
            text-align: center;
            font-size: 11px;
            font-weight: 700;
            color: var(--fg-3);
            padding: 5px 0;
        }
        .schedule-calendar-day {
            min-height: 36px;
            border: 1px solid transparent;
            border-radius: 8px;
            background: transparent;
            color: var(--fg-1);
            cursor: pointer;
            font-weight: 600;
        }
        .schedule-calendar-day:hover {
            background: oklch(94% 0.025 245);
            border-color: oklch(84% 0.035 245);
        }
        .schedule-calendar-day.is-muted {
            color: oklch(60% 0.025 240);
        }
        .schedule-calendar-day.is-selected {
            background: var(--brand-navy);
            color: oklch(98% 0.005 240);
            border-color: var(--brand-navy);
        }
        .schedule-calendar-day.is-range {
            background: oklch(94% 0.025 245);
            border-color: oklch(84% 0.035 245);
            color: var(--brand-navy);
        }
        .schedule-calendar-foot {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            padding: 10px 12px 12px;
            border-top: 1px solid oklch(90% 0.014 235);
            background: oklch(97% 0.012 235);
        }
        .schedule-calendar-foot button {
            border: 0;
            background: transparent;
            color: var(--brand-navy);
            font-weight: 700;
            cursor: pointer;
            padding: 6px 8px;
        }
        .schedule-time-field {
            width: 100%;
            display: block;
            box-sizing: border-box;
            min-height: 42px;
            padding: 8px 12px;
            border: 1px solid oklch(80% 0.032 235);
            border-radius: 10px;
            background: linear-gradient(180deg, oklch(99% 0.004 240), oklch(97% 0.01 235));
            font-weight: 700;
            color: var(--fg-1);
        }
        .schedule-time-field:focus {
            border-color: var(--brand-navy);
            box-shadow: 0 0 0 3px oklch(45% 0.12 250 / 0.14);
        }
        .schedule-time-picker {
            position: relative;
        }
        .schedule-time-menu {
            position: absolute;
            z-index: 90;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            border: 1px solid oklch(82% 0.025 235);
            border-radius: 12px;
            background: var(--surface);
            box-shadow: 0 14px 28px oklch(28% 0.025 240 / 0.12);
            padding: 8px;
        }
        .schedule-time-input-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid oklch(80% 0.032 235);
            border-radius: 10px;
            background: linear-gradient(180deg, oklch(99% 0.004 240), oklch(97% 0.01 235));
            padding: 0 9px 0 0;
        }
        .schedule-time-input-wrap:focus-within {
            border-color: var(--brand-navy);
            box-shadow: 0 0 0 3px oklch(45% 0.12 250 / 0.14);
        }
        .schedule-time-text {
            width: 100%;
            min-height: 42px;
            border: 0;
            background: transparent;
            color: var(--fg-1);
            font: inherit;
            font-weight: 700;
            padding: 8px 12px;
        }
        .schedule-time-text:focus {
            outline: none;
            box-shadow: none;
        }
        .schedule-time-icon {
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 7px;
            background: transparent;
            color: var(--fg-2);
            cursor: pointer;
        }
        .schedule-time-icon:hover {
            background: oklch(94% 0.025 245);
            color: var(--brand-navy);
        }
        .schedule-time-scroll-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            margin-top: 7px;
        }
        .schedule-time-scroll-column {
            border: 1px solid oklch(88% 0.018 235);
            border-radius: 9px;
            background: oklch(97% 0.012 235);
            overflow: hidden;
        }
        .schedule-time-scroll-label {
            padding: 6px 8px;
            border-bottom: 1px solid oklch(88% 0.018 235);
            color: var(--fg-3);
            font-size: 11px;
            font-weight: 700;
        }
        .schedule-time-scroll-list {
            max-height: 128px;
            overflow-y: auto;
            padding: 4px;
            scroll-snap-type: y proximity;
        }
        .schedule-time-scroll-option,
        .schedule-time-close {
            border: 1px solid oklch(82% 0.03 235);
            border-radius: 8px;
            background: oklch(97% 0.012 235);
            color: var(--fg-2);
            cursor: pointer;
            font: inherit;
            font-weight: 700;
            padding: 5px 8px;
            font-size: 12px;
        }
        .schedule-time-scroll-option {
            width: 100%;
            border-color: transparent;
            background: transparent;
            color: var(--fg-1);
            scroll-snap-align: center;
        }
        .schedule-time-scroll-option:hover,
        .schedule-time-scroll-option.is-selected {
            background: oklch(94% 0.025 245);
            color: var(--brand-navy);
        }
        .schedule-time-close:hover {
            border-color: var(--brand-navy);
            color: var(--brand-navy);
            background: oklch(94% 0.025 245);
        }
        .schedule-time-close-row {
            display: flex;
            justify-content: flex-end;
            margin-top: 10px;
        }
        .schedule-example-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        .schedule-example-chip {
            border: 1px solid oklch(84% 0.025 235);
            border-radius: 999px;
            background: oklch(96.5% 0.012 235);
            color: var(--fg-2);
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }
        .schedule-example-chip:hover {
            border-color: var(--brand-navy);
            color: var(--brand-navy);
            background: oklch(94% 0.025 245);
        }
        @media (max-width: 980px) {
            .schedule-form-layout {
                grid-template-columns: 1fr;
            }
            .schedule-period-fields {
                grid-template-columns: 1fr 1fr;
            }
            .schedule-range-field {
                grid-column: 1 / -1;
            }
        }
        @media (max-width: 640px) {
            .schedule-entry-card {
                padding: 15px;
            }
            .schedule-entry-title {
                font-size: 24px;
            }
            .schedule-time-fields,
            .schedule-period-fields {
                grid-template-columns: 1fr;
            }
            .schedule-range-field {
                grid-column: auto;
            }
        }
    </style>
    <script>
        function scheduleDateRangePicker(initialStart, initialEnd) {
            return {
                open: false,
                start: initialStart || '',
                end: initialEnd || initialStart || '',
                cursor: initialStart ? new Date(initialStart + 'T00:00:00') : new Date(),
                daysOfWeek: ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'],
                monthLabel() {
                    return this.cursor.toLocaleDateString('th-TH', { month: 'long', year: 'numeric' });
                },
                displayMain() {
                    if (!this.start && !this.end) return 'เลือกช่วงวันที่';
                    if (this.start && !this.end) return this.formatDate(this.start);
                    if (this.start === this.end) return this.formatDate(this.start);

                    return `${this.formatDate(this.start)} - ${this.formatDate(this.end)}`;
                },
                formatDate(iso) {
                    if (!iso) return '';
                    return new Date(iso + 'T00:00:00').toLocaleDateString('th-TH', {
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric',
                    });
                },
                displayHint() {
                    if (!this.start) return 'กดเลือกวันเริ่มต้น';
                    if (!this.end || this.start === this.end) return 'กดอีกครั้งเพื่อเลือกวันสิ้นสุด';
                    return 'เลือกช่วงวันที่แล้ว';
                },
                iso(date) {
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    return `${year}-${month}-${day}`;
                },
                calendarDays() {
                    const first = new Date(this.cursor.getFullYear(), this.cursor.getMonth(), 1);
                    const start = new Date(first);
                    start.setDate(first.getDate() - first.getDay());

                    return Array.from({ length: 42 }, (_, index) => {
                        const date = new Date(start);
                        date.setDate(start.getDate() + index);
                        return {
                            date,
                            iso: this.iso(date),
                            day: date.getDate(),
                            muted: date.getMonth() !== this.cursor.getMonth(),
                        };
                    });
                },
                previousMonth() {
                    this.cursor = new Date(this.cursor.getFullYear(), this.cursor.getMonth() - 1, 1);
                },
                nextMonth() {
                    this.cursor = new Date(this.cursor.getFullYear(), this.cursor.getMonth() + 1, 1);
                },
                choose(iso) {
                    if (!this.start || (this.start && this.end && this.start !== this.end) || iso < this.start) {
                        this.start = iso;
                        this.end = iso;
                    } else {
                        this.end = iso;
                    }
                    this.cursor = new Date(iso + 'T00:00:00');
                    this.notify();
                },
                today() {
                    const today = this.iso(new Date());
                    this.start = today;
                    this.end = today;
                    this.cursor = new Date(today + 'T00:00:00');
                    this.notify();
                },
                clear() {
                    this.start = '';
                    this.end = '';
                    this.notify();
                },
                done() {
                    this.open = false;
                },
                notify() {
                    window.dispatchEvent(new CustomEvent('schedule-date-range-change', {
                        detail: { start: this.start, end: this.end },
                    }));
                },
                inRange(iso) {
                    return this.start && this.end && this.start !== this.end && iso > this.start && iso < this.end;
                },
                isSelected(iso) {
                    return iso === this.start || iso === this.end;
                },
            };
        }

        function scheduleTimeInput(initialValue, field) {
            const normalized = /^\d{2}:\d{2}$/.test(initialValue || '') ? initialValue : '';

            return {
                open: false,
                value: normalized || '08:00',
                hours: Array.from({ length: 24 }, (_, index) => String(index).padStart(2, '0')),
                minutes: Array.from({ length: 60 }, (_, index) => String(index).padStart(2, '0')),
                scrub() {
                    this.value = this.value.replace(/[^\d:]/g, '').slice(0, 5);
                },
                normalize() {
                    const digits = this.value.replace(/\D/g, '').padEnd(4, '0').slice(0, 4);
                    const hour = Math.min(Number(digits.slice(0, 2)), 23);
                    const minute = Math.min(Number(digits.slice(2, 4)), 59);
                    this.value = `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
                    this.notify();
                },
                adjust(minutes) {
                    this.normalize();
                    const [hour, minute] = this.value.split(':').map(Number);
                    const total = (((hour * 60) + minute + minutes) % 1440 + 1440) % 1440;
                    this.value = `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
                    this.notify();
                },
                hour() {
                    this.normalize();
                    return this.value.slice(0, 2);
                },
                minute() {
                    this.normalize();
                    return this.value.slice(3, 5);
                },
                chooseHour(hour) {
                    this.normalize();
                    this.value = `${hour}:${this.value.slice(3, 5)}`;
                    this.notify();
                },
                chooseMinute(minute) {
                    this.normalize();
                    this.value = `${this.value.slice(0, 2)}:${minute}`;
                    this.notify();
                },
                close() {
                    this.normalize();
                    this.open = false;
                },
                notify() {
                    window.dispatchEvent(new CustomEvent('schedule-time-change', {
                        detail: { field, value: this.value },
                    }));
                },
            };
        }

        function scheduleFormState(initialCapacity, initialGroups, groups, schedules, initialStartDate, initialEndDate, initialStartTime, initialEndTime, conflictCheckUrl, currentScheduleId) {
            return {
                capacity: initialCapacity || '',
                selectedGroups: initialGroups,
                touchedCapacity: false,
                touchedGroups: false,
                serverErrorTouched: false,
                isCheckingConflicts: false,
                conflictTimer: null,
                realtime: {
                    groups: [],
                    groupIds: [],
                    instructors: [],
                    room: null,
                    capacity: null,
                },
                groups,
                schedules,
                startDate: initialStartDate || '',
                endDate: initialEndDate || '',
                startTime: initialStartTime || '',
                endTime: initialEndTime || '',
                conflictCheckUrl,
                currentScheduleId,
                selectedStudentCount() {
                    return this.selectedGroups.reduce((total, id) => {
                        const group = this.groups.find((item) => item.id === String(id));
                        return total + (group ? Number(group.student_count) : 0);
                    }, 0);
                },
                capacityExceeded() {
                    const capacity = Number(this.capacity || 0);

                    return capacity > 0 && this.selectedStudentCount() > capacity;
                },
                capacityMessageVisible() {
                    return this.capacityExceeded() || Boolean(this.realtime.capacity);
                },
                capacityMessage() {
                    if (this.realtime.capacity) {
                        return `จำนวนผู้เรียนที่เลือก (${this.realtime.capacity.selected} คน) เกินจำนวนรองรับที่ระบุ (${this.realtime.capacity.limit} คน)`;
                    }

                    return 'จำนวนผู้เรียนของกลุ่มที่เลือกเกินจำนวนรองรับที่ระบุ';
                },
                markCapacityChanged() {
                    this.touchedCapacity = true;
                    this.serverErrorTouched = true;
                    this.queueConflictCheck();
                },
                markGroupsChanged() {
                    this.touchedGroups = true;
                    this.serverErrorTouched = true;
                    this.queueConflictCheck();
                },
                markFormChanged() {
                    this.serverErrorTouched = true;
                    this.queueConflictCheck();
                },
                setDateRange(event) {
                    this.startDate = event.detail.start || '';
                    this.endDate = event.detail.end || '';
                    this.serverErrorTouched = true;
                    this.queueConflictCheck();
                },
                setTime(event) {
                    if (event.detail.field === 'start') {
                        this.startTime = event.detail.value;
                    } else {
                        this.endTime = event.detail.value;
                    }

                    this.serverErrorTouched = true;
                    this.queueConflictCheck();
                },
                groupUnavailable(groupId) {
                    if (this.realtime.groupIds.includes(String(groupId))) {
                        return true;
                    }

                    if (!this.startDate || !this.endDate || !this.startTime || !this.endTime) {
                        return false;
                    }

                    return this.schedules.some((schedule) => {
                        const hasGroup = schedule.groups.some((group) => group.id === String(groupId));
                        const dateOverlap = schedule.start_date <= this.endDate && schedule.end_date >= this.startDate;
                        const timeOverlap = schedule.start_time < this.endTime && schedule.end_time > this.startTime;

                        return hasGroup && dateOverlap && timeOverlap;
                    });
                },
                conflictGroups() {
                    return this.groups
                        .filter((group) => this.groupUnavailable(group.id))
                        .map((group) => group.id);
                },
                instructorConflictMessage() {
                    if (this.realtime.instructors.length === 0) return '';

                    return `ผู้สอนมีรายการสอนในช่วงเวลาเดียวกันแล้ว: ${this.realtime.instructors.join(', ')}`;
                },
                groupConflictMessage() {
                    if (this.realtime.groups.length === 0) return '';

                    return `กลุ่มนักศึกษามีรายการสอนในช่วงเวลาเดียวกันแล้ว: ${this.realtime.groups.join(', ')}`;
                },
                roomConflictMessage() {
                    if (!this.realtime.room) return '';

                    return `ห้องหรือสถานที่นี้มีรายการสอนในช่วงเวลาเดียวกันแล้ว: ${this.realtime.room}`;
                },
                resetRealtimeConflicts() {
                    this.realtime = {
                        groups: [],
                        groupIds: [],
                        instructors: [],
                        room: null,
                        capacity: null,
                    };
                },
                readyForConflictCheck() {
                    const timePattern = /^([01]\d|2[0-3]):[0-5]\d$/;

                    return this.startDate
                        && this.endDate
                        && timePattern.test(this.startTime)
                        && timePattern.test(this.endTime)
                        && this.startTime < this.endTime;
                },
                queueConflictCheck() {
                    window.clearTimeout(this.conflictTimer);

                    if (!this.readyForConflictCheck()) {
                        this.resetRealtimeConflicts();
                        return;
                    }

                    this.conflictTimer = window.setTimeout(() => this.checkConflicts(), 350);
                },
                async checkConflicts() {
                    const form = this.$root;
                    const formData = new FormData(form);
                    const payload = {
                        schedule_id: this.currentScheduleId || null,
                        start_date: formData.get('start_date'),
                        end_date: formData.get('end_date'),
                        start_time: formData.get('start_time'),
                        end_time: formData.get('end_time'),
                        room_id: formData.get('room_id') || null,
                        capacity_required: formData.get('capacity_required') || null,
                        instructor_ids: formData.getAll('instructor_ids[]'),
                        student_group_ids: formData.getAll('student_group_ids[]'),
                    };

                    this.isCheckingConflicts = true;

                    try {
                        const response = await fetch(this.conflictCheckUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': formData.get('_token'),
                            },
                            body: JSON.stringify(payload),
                        });

                        if (!response.ok) {
                            this.resetRealtimeConflicts();
                            return;
                        }

                        const data = await response.json();
                        this.realtime = {
                            groups: (data.conflicts?.groups || []).map(String),
                            groupIds: (data.conflicts?.group_ids || []).map(String),
                            instructors: data.conflicts?.instructors || [],
                            room: data.conflicts?.room || null,
                            capacity: data.conflicts?.capacity || null,
                        };
                    } catch (error) {
                        this.resetRealtimeConflicts();
                    } finally {
                        this.isCheckingConflicts = false;
                    }
                },
            };
        }

    </script>

    <div class="schedule-entry-head">
        <a href="{{ route('maker.course_offerings.schedules.index', $courseOffering) }}" class="body-sm schedule-back-link">← กลับไปตารางสอน</a>
        <div class="schedule-entry-card">
            <div class="schedule-entry-kicker">ตารางสอนรายวิชา</div>
            <h1 class="schedule-entry-title">{{ $pageTitle }}</h1>
            <div class="schedule-entry-desc">ระบุช่วงวันที่ เวลา ผู้สอน และกลุ่มนักศึกษาสำหรับรายการนี้</div>
        </div>
    </div>

    @if($errors->any())
        <div class="card" style="border-color:var(--status-conflict-border);background:var(--status-conflict-bg);margin-bottom:16px;">
            <div style="padding:14px 18px;color:var(--status-conflict-fg);font-weight:600;">
                {{ $errors->first() }}
            </div>
        </div>
    @endif

    <div class="schedule-form-layout">
        <aside class="schedule-panel">
            <div class="schedule-side-head">
                <div class="caption">รายวิชา</div>
                <div class="schedule-course-code">{{ $course?->course_code ?? '-' }}</div>
                <div class="body-sm" style="margin-top:6px;color:oklch(90% 0.018 245);">{{ $course?->name_th ?? $course?->name_en ?? '-' }}</div>
            </div>

            <div class="schedule-side-list">
                <div class="schedule-side-item">
                    <div class="caption">ปีการศึกษา</div>
                    <div style="font-weight:700;margin-top:3px;">{{ $academicYear?->name ?? '-' }} / เทอม {{ $academicYear?->semester ?? '-' }}</div>
                </div>
                <div class="schedule-side-item">
                    <div class="caption">กลุ่มนักศึกษา</div>
                    <div style="font-weight:700;margin-top:3px;">{{ $courseOffering->studentGroups->count() }} กลุ่ม</div>
                </div>
                    <div class="schedule-side-item">
                        <div class="caption">ผู้สอนในรายวิชา</div>
                        <div style="font-weight:700;margin-top:3px;">{{ $availableInstructors->count() }} คน</div>
                    </div>
            </div>
        </aside>

        <form method="POST" action="{{ $formAction }}" class="schedule-panel schedule-main-panel" data-testid="schedule-create-form" x-data="scheduleFormState(@js(old('capacity_required', $schedule?->capacity_required ?? '')), @js($oldGroups->values()), @js($groupOptions), @js($scheduleConflicts), @js($initialStartDate), @js($initialEndDate), @js($initialStartTime), @js($initialEndTime), @js($checkConflictUrl), @js($schedule?->id))" x-init="queueConflictCheck()" @schedule-date-range-change.window="setDateRange($event)" @schedule-time-change.window="setTime($event)" @schedule-room-change.window="queueConflictCheck()" @input="markFormChanged()" @change="markFormChanged()">
            @csrf
            @if($isEditing)
                @method('PUT')
            @endif

            <div class="schedule-section">
                <div class="schedule-section-head">
                    <div class="schedule-section-title">ช่วงเวลา</div>
                    <div class="schedule-section-subtitle">รองรับการจัดตารางแบบหลายวันต่อรายการ</div>
                </div>
                <div class="schedule-section-body">
                    <div class="schedule-period-fields">
                        <div class="form-group schedule-range-field">
                            <label>ช่วงวันที่ <span style="color:var(--status-conflict-fg)">*</span></label>
                            <div class="schedule-picker" x-data="scheduleDateRangePicker(@js($initialStartDate), @js($initialEndDate))" @click.outside="open = false" @keydown.escape.window="open = false">
                                <input type="hidden" name="start_date" :value="start">
                                <input type="hidden" name="end_date" :value="end">
                                <button type="button" class="schedule-picker-button" @click="open = !open" data-testid="schedule-date-range">
                                    <span class="schedule-picker-value">
                                        <span class="schedule-picker-main" x-text="displayMain()"></span>
                                        <span class="schedule-picker-sub" x-text="displayHint()"></span>
                                    </span>
                                    <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="4" width="18" height="18" rx="2"/>
                                        <line x1="16" y1="2" x2="16" y2="6"/>
                                        <line x1="8" y1="2" x2="8" y2="6"/>
                                        <line x1="3" y1="10" x2="21" y2="10"/>
                                    </svg>
                                </button>
                                <div class="schedule-picker-popover" x-show="open" x-transition.origin.top x-cloak>
                                    <div class="schedule-calendar-head">
                                        <div class="schedule-calendar-title" x-text="monthLabel()"></div>
                                        <div class="schedule-calendar-nav">
                                            <button type="button" @click="previousMonth()" aria-label="เดือนก่อนหน้า">‹</button>
                                            <button type="button" @click="nextMonth()" aria-label="เดือนถัดไป">›</button>
                                        </div>
                                    </div>
                                    <div class="schedule-calendar-grid">
                                        <template x-for="dayName in daysOfWeek" :key="dayName">
                                            <div class="schedule-calendar-dow" x-text="dayName"></div>
                                        </template>
                                        <template x-for="day in calendarDays()" :key="day.iso">
                                            <button type="button" class="schedule-calendar-day" :class="{ 'is-muted': day.muted, 'is-selected': isSelected(day.iso), 'is-range': inRange(day.iso) }" @click="choose(day.iso)" x-text="day.day"></button>
                                        </template>
                                    </div>
                                    <div class="schedule-calendar-foot">
                                        <button type="button" @click="clear()">ล้างค่า</button>
                                        <button type="button" @click="today()">วันนี้</button>
                                        <button type="button" @click="done()">ตกลง</button>
                                    </div>
                                </div>
                            </div>
                            @error('start_date')<div class="caption" style="color:var(--status-conflict-fg);margin-top:5px;">{{ $message }}</div>@enderror
                            @error('end_date')<div class="caption" style="color:var(--status-conflict-fg);margin-top:5px;">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-group">
                            <label>เวลาเริ่ม <span style="color:var(--status-conflict-fg)">*</span></label>
                            <div class="schedule-time-picker" x-data="scheduleTimeInput(@js($initialStartTime), 'start')" @click.outside="close()" @keydown.escape.window="open = false">
                                <div class="schedule-time-input-wrap">
                                    <input
                                        type="text"
                                        name="start_time"
                                        x-model="value"
                                        @input="scrub()"
                                        @blur="normalize()"
                                        inputmode="numeric"
                                        pattern="^([01]\d|2[0-3]):[0-5]\d$"
                                        placeholder="08:00"
                                        class="schedule-time-text"
                                        data-testid="schedule-start-time"
                                    >
                                    <button type="button" class="schedule-time-icon" @click="open = !open" aria-label="ปรับเวลาเริ่ม">
                                        <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="9"/>
                                            <path d="M12 7v5l3 2"/>
                                        </svg>
                                    </button>
                                </div>
                                <div class="schedule-time-menu" x-show="open" x-transition.origin.top x-cloak>
                                    <div class="caption">เลื่อนเลือกเวลา</div>
                                    <div class="schedule-time-scroll-grid">
                                        <div class="schedule-time-scroll-column">
                                            <div class="schedule-time-scroll-label">ชั่วโมง</div>
                                            <div class="schedule-time-scroll-list">
                                                <template x-for="option in hours" :key="option">
                                                    <button type="button" class="schedule-time-scroll-option" :class="{ 'is-selected': hour() === option }" @click="chooseHour(option)" x-text="option"></button>
                                                </template>
                                            </div>
                                        </div>
                                        <div class="schedule-time-scroll-column">
                                            <div class="schedule-time-scroll-label">นาที</div>
                                            <div class="schedule-time-scroll-list">
                                                <template x-for="option in minutes" :key="option">
                                                    <button type="button" class="schedule-time-scroll-option" :class="{ 'is-selected': minute() === option }" @click="chooseMinute(option)" x-text="option"></button>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="schedule-time-close-row">
                                        <button type="button" class="schedule-time-close" @click="close()">ตกลง</button>
                                    </div>
                                </div>
                            </div>
                            @error('start_time')<div class="caption" style="color:var(--status-conflict-fg);margin-top:5px;">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-group">
                            <label>เวลาสิ้นสุด <span style="color:var(--status-conflict-fg)">*</span></label>
                            <div class="schedule-time-picker" x-data="scheduleTimeInput(@js($initialEndTime), 'end')" @click.outside="close()" @keydown.escape.window="open = false">
                                <div class="schedule-time-input-wrap">
                                    <input
                                        type="text"
                                        name="end_time"
                                        x-model="value"
                                        @input="scrub()"
                                        @blur="normalize()"
                                        inputmode="numeric"
                                        pattern="^([01]\d|2[0-3]):[0-5]\d$"
                                        placeholder="10:00"
                                        class="schedule-time-text"
                                        data-testid="schedule-end-time"
                                    >
                                    <button type="button" class="schedule-time-icon" @click="open = !open" aria-label="ปรับเวลาสิ้นสุด">
                                        <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="9"/>
                                            <path d="M12 7v5l3 2"/>
                                        </svg>
                                    </button>
                                </div>
                                <div class="schedule-time-menu" x-show="open" x-transition.origin.top x-cloak>
                                    <div class="caption">เลื่อนเลือกเวลา</div>
                                    <div class="schedule-time-scroll-grid">
                                        <div class="schedule-time-scroll-column">
                                            <div class="schedule-time-scroll-label">ชั่วโมง</div>
                                            <div class="schedule-time-scroll-list">
                                                <template x-for="option in hours" :key="option">
                                                    <button type="button" class="schedule-time-scroll-option" :class="{ 'is-selected': hour() === option }" @click="chooseHour(option)" x-text="option"></button>
                                                </template>
                                            </div>
                                        </div>
                                        <div class="schedule-time-scroll-column">
                                            <div class="schedule-time-scroll-label">นาที</div>
                                            <div class="schedule-time-scroll-list">
                                                <template x-for="option in minutes" :key="option">
                                                    <button type="button" class="schedule-time-scroll-option" :class="{ 'is-selected': minute() === option }" @click="chooseMinute(option)" x-text="option"></button>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="schedule-time-close-row">
                                        <button type="button" class="schedule-time-close" @click="close()">ตกลง</button>
                                    </div>
                                </div>
                            </div>
                            @error('end_time')<div class="caption" style="color:var(--status-conflict-fg);margin-top:5px;">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="schedule-section">
                <div class="schedule-section-head">
                    <div class="schedule-section-title">รายละเอียดกิจกรรม</div>
                    <div class="schedule-section-subtitle">เลือกประเภทกิจกรรมและสถานที่ก่อนบันทึก</div>
                </div>
                <div class="schedule-section-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label>ประเภทกิจกรรม <span style="color:var(--status-conflict-fg)">*</span></label>
                            <div
                                class="schedule-select"
                                x-data="{
                                    open: false,
                                    selected: '{{ old('activity_type_id', $schedule?->activity_type_id ?? '') }}',
                                    query: '',
                                    options: {{ Js::from($activityOptions) }},
                                    label() {
                                        const item = this.options.find(option => option.id === this.selected);
                                        return item ? item.label : 'เลือกประเภทกิจกรรม';
                                    },
                                    filteredOptions() {
                                        const keyword = this.query.trim().toLowerCase();

                                        if (!keyword) return this.options;

                                        return this.options.filter(option => option.label.toLowerCase().includes(keyword));
                                    },
                                    choose(id) {
                                        this.selected = id;
                                        this.query = '';
                                        this.open = false;
                                    }
                                }"
                                @keydown.escape.window="open = false"
                                @click.outside="open = false"
                            >
                                <input type="hidden" name="activity_type_id" :value="selected">
                                <button type="button" class="schedule-select-button" @click="open = !open" data-testid="schedule-activity-type">
                                    <span :class="{ 'schedule-select-placeholder': !selected }" x-text="label()"></span>
                                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M6 9l6 6 6-6"/>
                                    </svg>
                                </button>
                                <div class="schedule-select-menu" x-show="open" x-transition.origin.top x-cloak>
                                    <input type="search" class="schedule-select-search" x-model="query" placeholder="ค้นหาประเภทกิจกรรม" @click.stop>
                                    <template x-for="option in filteredOptions()" :key="option.id">
                                        <button type="button" class="schedule-select-option" :class="{ 'is-selected': selected === option.id }" @click="choose(option.id)" x-text="option.label"></button>
                                    </template>
                                    <div class="schedule-select-empty" x-show="filteredOptions().length === 0" x-cloak>ไม่พบประเภทกิจกรรมที่ตรงกับคำค้นหา</div>
                                </div>
                            </div>
                            @error('activity_type_id')<div class="caption" style="color:var(--status-conflict-fg);margin-top:5px;">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-group">
                            <label>ห้อง / สถานที่</label>
                            <div
                                class="schedule-select"
                                x-data="{
                                    open: false,
                                    selected: '{{ old('room_id', $schedule?->room_id ?? '') }}',
                                    query: '',
                                    options: {{ Js::from($roomOptions) }},
                                    label() {
                                        if (!this.selected) return 'ไม่ระบุห้อง';
                                        const item = this.options.find(option => option.id === this.selected);
                                        return item ? item.label : 'ไม่ระบุห้อง';
                                    },
                                    filteredOptions() {
                                        const keyword = this.query.trim().toLowerCase();

                                        if (!keyword) return this.options;

                                        return this.options.filter(option => option.label.toLowerCase().includes(keyword));
                                    },
                                    choose(id) {
                                        this.selected = id;
                                        this.query = '';
                                        this.open = false;
                                        window.dispatchEvent(new CustomEvent('schedule-room-change'));
                                    }
                                }"
                                @keydown.escape.window="open = false"
                                @click.outside="open = false"
                            >
                                <input type="hidden" name="room_id" :value="selected">
                                <button type="button" class="schedule-select-button" @click="open = !open" data-testid="schedule-room">
                                    <span :class="{ 'schedule-select-placeholder': !selected }" x-text="label()"></span>
                                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M6 9l6 6 6-6"/>
                                    </svg>
                                </button>
                                <div class="schedule-select-menu" x-show="open" x-transition.origin.top x-cloak>
                                    <input type="search" class="schedule-select-search" x-model="query" placeholder="ค้นหาห้องหรือสถานที่" @click.stop>
                                    <button type="button" class="schedule-select-option" :class="{ 'is-selected': selected === '' }" @click="choose('')">ไม่ระบุห้อง</button>
                                    <template x-for="option in filteredOptions()" :key="option.id">
                                        <button type="button" class="schedule-select-option" :class="{ 'is-selected': selected === option.id }" @click="choose(option.id)" x-text="option.label"></button>
                                    </template>
                                    <div class="schedule-select-empty" x-show="filteredOptions().length === 0" x-cloak>ไม่พบห้องหรือสถานที่ที่ตรงกับคำค้นหา</div>
                                </div>
                            </div>
                            <div class="schedule-realtime-alert" x-show="roomConflictMessage()" x-text="roomConflictMessage()" x-cloak></div>
                            @error('room_id')<div class="caption" style="color:var(--status-conflict-fg);margin-top:5px;">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="form-row" style="margin-top:14px;margin-bottom:0;">
                        <div class="form-group" x-data="{ topic: @js(old('topic', $schedule?->topic ?? '')) }">
                            <label>หัวข้อ</label>
                            <input type="text" name="topic" x-model="topic" maxlength="255" placeholder="เช่น บรรยายหัวข้อหลักประจำสัปดาห์" data-testid="schedule-topic">
                            <div class="caption" style="margin-top:8px;">ตัวอย่างกิจกรรมสมมติ</div>
                            <div class="schedule-example-list">
                                @foreach(['บรรยายหัวข้อหลัก', 'ฝึกปฏิบัติในห้องจำลอง', 'ประชุมสะท้อนคิด', 'ทบทวนก่อนประเมิน', 'กิจกรรมพัฒนาผู้เรียน', 'ฝึกปฏิบัติในแหล่งฝึก'] as $example)
                                    <button type="button" class="schedule-example-chip" @click="topic = '{{ $example }}'">{{ $example }}</button>
                                @endforeach
                            </div>
                            @error('topic')<div class="caption" style="color:var(--status-conflict-fg);margin-top:5px;">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-group">
                            <label>จำนวนรองรับ</label>
                            <input type="number" name="capacity_required" x-model.number="capacity" @input="markCapacityChanged()" min="1" data-testid="schedule-capacity">
                            @error('capacity_required')<div class="caption" x-show="!touchedCapacity" style="color:var(--status-conflict-fg);margin-top:5px;">{{ $message }}</div>@enderror
                            <div class="caption" style="margin-top:6px;">
                                เลือกแล้ว <span x-text="selectedStudentCount()"></span> คน
                            </div>
                            <div class="schedule-realtime-alert" x-show="capacityMessageVisible()" x-text="capacityMessage()" x-cloak></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="schedule-section">
                <div class="schedule-section-head">
                    <div class="schedule-section-title">ผู้เกี่ยวข้อง</div>
                    <div class="schedule-section-subtitle">ต้องเลือกผู้สอนและกลุ่มนักศึกษาอย่างน้อยหนึ่งรายการ</div>
                </div>
                <div class="schedule-section-body">
                    <div class="form-row">
                        <div
                            class="form-group"
                            x-data="{
                                instructorQuery: '',
                                instructors: @js($availableInstructors->map(fn ($instructor) => mb_strtolower($instructor->formatted_name, 'UTF-8'))->values()),
                                hasInstructorMatches() {
                                    const keyword = this.instructorQuery.trim().toLowerCase();

                                    return !keyword || this.instructors.some((name) => name.includes(keyword));
                                }
                            }"
                        >
                            <label>ผู้สอน <span style="color:var(--status-conflict-fg)">*</span></label>
                            <input type="search" class="schedule-check-search" x-model="instructorQuery" placeholder="ค้นหาผู้สอน">
                            <div class="schedule-check-list">
                                @forelse($availableInstructors as $instructor)
                                    @php($instructorSearch = mb_strtolower($instructor->formatted_name, 'UTF-8'))
                                    <label class="schedule-check-option" x-show="@js($instructorSearch).includes(instructorQuery.trim().toLowerCase())" x-cloak>
                                        <input type="checkbox" name="instructor_ids[]" value="{{ $instructor->id }}" @checked($oldInstructors->contains((string) $instructor->id)) data-testid="schedule-instructor-option">
                                        <span>{{ $instructor->formatted_name }}</span>
                                    </label>
                                @empty
                                    <div class="caption">ยังไม่มีผู้สอนในภาควิชาของรายวิชานี้</div>
                                @endforelse
                                @if($availableInstructors->isNotEmpty())
                                    <div class="caption" x-show="!hasInstructorMatches()" x-cloak>ไม่พบข้อมูลผู้สอนที่ตรงกับคำค้นหา</div>
                                @endif
                            </div>
                            <div class="schedule-realtime-alert" x-show="instructorConflictMessage()" x-text="instructorConflictMessage()" x-cloak></div>
                            @error('instructor_ids')<div class="caption" style="color:var(--status-conflict-fg);margin-top:5px;">{{ $message }}</div>@enderror
                            @error('instructor_ids.*')<div class="caption" style="color:var(--status-conflict-fg);margin-top:5px;">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-group" x-data="{ groupQuery: '' }">
                            <label>กลุ่มนักศึกษา <span style="color:var(--status-conflict-fg)">*</span></label>
                            <input type="search" class="schedule-check-search" x-model="groupQuery" placeholder="ค้นหากลุ่มนักศึกษา">
                            <div class="schedule-check-list">
                                @forelse($courseOffering->studentGroups as $group)
                                    @php($groupSearch = mb_strtolower($group->group_code.' '.$group->student_count.' คน', 'UTF-8'))
                                    <label class="schedule-check-option" :class="{ 'has-conflict': groupUnavailable('{{ $group->id }}') }" x-show="@js($groupSearch).includes(groupQuery.trim().toLowerCase())" x-cloak>
                                        <input type="checkbox" name="student_group_ids[]" value="{{ $group->id }}" x-model="selectedGroups" @change="markGroupsChanged()" data-testid="schedule-group-option">
                                        <span>
                                            {{ $group->group_code }} <span class="caption">({{ $group->student_count }} คน)</span>
                                            <span class="caption" x-show="groupUnavailable('{{ $group->id }}')" style="display:block;color:var(--status-conflict-fg);" x-cloak>มีรายการสอนช่วงนี้แล้ว</span>
                                        </span>
                                    </label>
                                @empty
                                    <div class="caption">ยังไม่มีกลุ่มนักศึกษา</div>
                                @endforelse
                            </div>
                            <div class="schedule-realtime-alert" x-show="groupConflictMessage()" x-text="groupConflictMessage()" x-cloak></div>
                            @error('student_group_ids')<div class="caption" x-show="!touchedGroups && !touchedCapacity" style="color:var(--status-conflict-fg);margin-top:5px;">{{ $message }}</div>@enderror
                            @error('student_group_ids.*')<div class="caption" x-show="!touchedGroups" style="color:var(--status-conflict-fg);margin-top:5px;">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:14px;">
                        <label>หมายเหตุ</label>
                        <textarea name="remark" rows="3" data-testid="schedule-remark">{{ old('remark', $schedule?->remark) }}</textarea>
                        @error('remark')<div class="caption" style="color:var(--status-conflict-fg);margin-top:5px;">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div class="schedule-actions">
                @if($errors->any())
                    <div class="schedule-inline-alert" x-show="!serverErrorTouched && !capacityMessageVisible()" x-cloak>
                        {{ $errors->first() }}
                    </div>
                @endif
                <a href="{{ route('maker.course_offerings.schedules.index', $courseOffering) }}" class="btn btn-ghost">ยกเลิก</a>
                <button type="submit" class="btn btn-primary" data-testid="schedule-submit">{{ $isEditing ? 'บันทึกการแก้ไข' : 'บันทึกตาราง' }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
