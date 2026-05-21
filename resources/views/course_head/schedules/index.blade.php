@php
    $availableOfferings = $availableOfferings ?? collect();
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
    $thaiDays = [
        1 => 'วันจันทร์',
        2 => 'วันอังคาร',
        3 => 'วันพุธ',
        4 => 'วันพฤหัสบดี',
        5 => 'วันศุกร์',
    ];
    $formatDate = fn ($date) => $date->format('d/m/Y');
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
@endphp

<x-app-layout title="ตารางสอน">
    <style>
        .schedule-shell {
            --schedule-border: oklch(84% 0.018 232);
            --schedule-border-strong: oklch(74% 0.032 232);
            --schedule-muted: oklch(42% 0.032 238);
            --schedule-soft: oklch(96% 0.012 228);
            --schedule-soft-strong: oklch(93% 0.022 228);
            display: flex;
            flex-direction: column;
            gap: 9px;
        }
        .schedule-toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 11px;
            border: 1px solid var(--schedule-border);
            border-radius: 8px;
            background: var(--surface);
            box-shadow: 0 1px 3px oklch(0% 0 0 / 0.05);
            flex-wrap: wrap;
        }
        .schedule-title {
            font-size: 15px;
            font-weight: 900;
            color: var(--fg-1);
            padding-right: 8px;
            border-right: 1px solid var(--schedule-border);
        }
        .week-nav {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: var(--fg-2);
            font-size: 12.5px;
            font-weight: 800;
        }
        .week-btn {
            width: 27px;
            height: 27px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--schedule-border);
            border-radius: 7px;
            background: var(--surface);
            color: var(--fg-1);
            text-decoration: none;
            font-weight: 900;
        }
        .week-pill {
            min-height: 19px;
            padding: 1px 8px;
            border-radius: 999px;
            background: oklch(93% 0.05 255);
            color: var(--brand-navy);
            font-size: 10.5px;
            font-weight: 900;
        }
        .schedule-toggle {
            display: inline-flex;
            border: 1px solid var(--schedule-border);
            border-radius: 8px;
            overflow: hidden;
            background: var(--schedule-soft);
        }
        .schedule-toggle button {
            border: 0;
            background: transparent;
            color: var(--schedule-muted);
            font: inherit;
            font-size: 12px;
            font-weight: 800;
            min-height: 31px;
            padding: 5px 11px;
            cursor: pointer;
        }
        .schedule-toggle button.is-active {
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
            padding: 8px 12px;
            border: 1px solid var(--schedule-border);
            border-radius: 8px;
            background: var(--surface);
        }
        .nested-course {
            min-width: 0;
            color: var(--fg-1);
            font-weight: 900;
            font-size: 14px;
        }
        .nested-meta {
            margin-top: 2px;
            color: var(--fg-3);
            font-size: 12px;
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
            min-height: 17px;
            padding: 1px 6px;
            border-radius: 6px;
            border: 1px solid color-mix(in oklch, var(--activity-color) 34%, var(--schedule-border));
            background: color-mix(in oklch, var(--activity-color) 16%, var(--surface));
            color: color-mix(in oklch, var(--activity-color) 78%, var(--fg-1));
            font-size: 9.5px;
            font-weight: 900;
            text-transform: uppercase;
        }
        .grid-activity:hover,
        .grid-activity:focus-visible {
            border-color: color-mix(in oklch, var(--activity-color) 62%, var(--schedule-border-strong));
            box-shadow: 0 2px 8px oklch(0% 0 0 / 0.08);
            outline: none;
        }
        /* ── โหมดรายการ (list) — ตารางคอลัมน์ อ่านง่าย ───────────────── */
        .sched-list-wrap {
            overflow-x: auto;
            border: 1px solid var(--schedule-border-strong);
            border-radius: 8px;
        }
        .sched-list {
            width: 100%;
            min-width: 680px;
            border-collapse: collapse;
            background: var(--surface);
        }
        .sched-list thead th {
            background: var(--schedule-soft-strong);
            color: var(--schedule-muted);
            text-align: left;
            font-size: 11px;
            font-weight: 900;
            padding: 8px 11px;
            border-bottom: 1px solid var(--schedule-border-strong);
            white-space: nowrap;
        }
        .sched-day td {
            background: var(--schedule-soft);
            border-top: 1px solid var(--schedule-border);
            border-bottom: 1px solid var(--schedule-border);
            padding: 6px 11px;
        }
        .sched-day-head {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sched-day-name {
            color: var(--fg-1);
            font-size: 12.5px;
            font-weight: 900;
        }
        .sched-day-date {
            color: var(--schedule-muted);
            font-size: 11px;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
        }
        .sched-day-count {
            color: var(--schedule-muted);
            font-size: 11px;
            font-weight: 750;
        }
        .sched-day-spacer {
            flex: 1;
        }
        .sched-row {
            cursor: pointer;
            border-left: 3px solid var(--activity-color);
        }
        .sched-row > td {
            border-bottom: 1px solid var(--schedule-border);
            padding: 8px 11px;
            vertical-align: top;
            font-size: 12.5px;
        }
        .sched-row:hover > td,
        .sched-row:focus-visible > td {
            background: color-mix(in oklch, var(--activity-color) 8%, var(--surface));
        }
        .sched-row:focus-visible {
            outline: 2px solid var(--brand-navy);
            outline-offset: -2px;
        }
        .sched-time {
            color: var(--fg-1);
            font-size: 12.5px;
            font-weight: 900;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
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
            margin-top: 4px;
            color: var(--fg-1);
            font-size: 12.5px;
            font-weight: 800;
            line-height: 1.3;
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
            border-bottom: 1px solid var(--schedule-border);
        }
        .group-chip {
            display: inline-flex;
            align-items: center;
            min-height: 19px;
            padding: 1px 6px;
            border: 1px solid var(--schedule-border);
            border-radius: 7px;
            background: var(--surface);
            color: var(--fg-2);
            font-size: 11px;
            font-weight: 900;
        }
        /* จุดสีกลุ่มนักศึกษา — สีจาก student_groups.color_code */
        .group-dot {
            width: 8px;
            height: 8px;
            margin-right: 5px;
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
        .schedule-grid {
            display: grid;
            grid-template-columns: 66px repeat(5, minmax(132px, 1fr));
            border: 1px solid var(--schedule-border-strong);
            border-radius: 8px;
            overflow: auto;
            background: var(--surface);
        }
        .grid-cell {
            min-height: 56px;
            border-right: 1px solid var(--schedule-border);
            border-bottom: 1px solid var(--schedule-border);
            padding: 6px;
        }
        .grid-head {
            min-height: 43px;
            background: oklch(95% 0.016 62);
            color: var(--fg-1);
            text-align: center;
            font-size: 11.5px;
            font-weight: 900;
        }
        .grid-time {
            background: var(--schedule-soft-strong);
            color: var(--schedule-muted);
            font-size: 11px;
            font-weight: 900;
            text-align: right;
            font-variant-numeric: tabular-nums;
        }
        .grid-activity {
            width: 100%;
            border: 1px solid color-mix(in oklch, var(--activity-color) 42%, var(--schedule-border));
            border-radius: 7px;
            background: color-mix(in oklch, var(--activity-color) 14%, var(--surface));
            padding: 6px;
            margin-bottom: 5px;
            font-size: 10.5px;
            color: var(--fg-2);
            box-shadow: 0 1px 2px oklch(0% 0 0 / 0.05);
            cursor: pointer;
            text-align: left;
            font: inherit;
        }
        .grid-activity strong {
            display: block;
            color: var(--fg-1);
            font-size: 11.5px;
            line-height: 1.25;
        }
        .grid-course {
            color: var(--fg-3);
            font-size: 10px;
            font-weight: 800;
            margin-bottom: 2px;
        }
        .schedule-modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 80;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 22px;
            background: oklch(16% 0.02 240 / 0.5);
        }
        .schedule-modal {
            width: min(640px, 100%);
            max-height: min(88vh, 760px);
            overflow: auto;
            border: 1px solid var(--schedule-border);
            border-radius: 14px;
            background: var(--surface);
            box-shadow: 0 18px 48px oklch(0% 0 0 / 0.2);
        }
        .schedule-modal.is-form {
            width: min(760px, 100%);
        }
        .modal-handle {
            width: 38px;
            height: 4px;
            border-radius: 999px;
            background: oklch(84% 0.012 240);
            margin: 12px auto 4px;
        }
        .modal-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 12px 20px 14px;
            border-bottom: 1px solid var(--schedule-border);
        }
        .modal-title {
            margin-top: 5px;
            color: var(--fg-1);
            font-size: 20px;
            font-weight: 900;
            line-height: 1.3;
        }
        .modal-close {
            width: 34px;
            height: 34px;
            border: 0;
            border-radius: 999px;
            background: var(--schedule-soft);
            color: var(--schedule-muted);
            font-size: 22px;
            cursor: pointer;
        }
        .detail-body,
        .modal-form-body {
            padding: 16px 20px 18px;
        }
        .detail-list {
            display: grid;
            gap: 10px;
        }
        .detail-item {
            display: grid;
            grid-template-columns: 118px minmax(0, 1fr);
            gap: 12px;
            align-items: start;
            color: var(--fg-1);
            font-size: 14px;
        }
        .detail-label {
            color: var(--schedule-muted);
            font-size: 12px;
            font-weight: 800;
        }
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 14px 20px 18px;
            border-top: 1px solid var(--schedule-border);
            background: var(--schedule-soft);
        }
        .modal-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
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
            border-radius: 8px;
            background: var(--surface);
            color: var(--fg-1);
            padding: 8px 10px;
            font: inherit;
            font-size: 13px;
        }
        .modal-choice-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }
        .modal-choice {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            border: 1px solid var(--schedule-border);
            border-radius: 8px;
            padding: 8px 10px;
            background: oklch(98% 0.006 240);
            font-size: 12.5px;
            font-weight: 800;
        }
        .modal-section {
            margin-top: 14px;
        }
        .modal-section-title {
            margin-bottom: 8px;
            color: var(--fg-1);
            font-size: 13px;
            font-weight: 900;
        }
        [x-cloak] {
            display: none !important;
        }
        @media (max-width: 900px) {
            .schedule-toolbar {
                align-items: flex-start;
            }
            .modal-form-grid,
            .modal-choice-grid {
                grid-template-columns: 1fr;
            }
            .schedule-modal-backdrop {
                align-items: flex-start;
                padding: 12px;
            }
            .modal-actions {
                flex-direction: column-reverse;
            }
        }
    </style>

    <div
        class="schedule-shell"
        x-data="{
            view: 'list',
            detailModal: null,
            editModal: @js($openEditScheduleId ? 'schedule-' . $openEditScheduleId : null),
            showCreate: @js($openCreateModal),
            selectedOfferingId: @js($selectedOfferingId),
            openCreate(date = null) {
                this.detailModal = null;
                this.editModal = null;
                this.showCreate = true;
                this.$nextTick(() => {
                    if (date && this.$refs.startDate && !this.$refs.startDate.value) this.$refs.startDate.value = date;
                    if (date && this.$refs.endDate && !this.$refs.endDate.value) this.$refs.endDate.value = date;
                });
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
        <div class="schedule-toolbar">
            <div class="schedule-title">ตารางสอน</div>
            <div class="week-nav">
                <a class="week-btn" href="{{ $previousWeekUrl }}" aria-label="สัปดาห์ก่อนหน้า">‹</a>
                <span>{{ $formatDate($weekStart) }} - {{ $formatDate($weekEnd) }}</span>
                <span class="week-pill">สัปดาห์</span>
                <a class="week-btn" href="{{ $nextWeekUrl }}" aria-label="สัปดาห์ถัดไป">›</a>
            </div>
            <div class="schedule-toggle" role="group" aria-label="รูปแบบการแสดงตาราง">
                <button type="button" :class="{ 'is-active': view === 'list' }" @click="view = 'list'" data-testid="schedule-list-toggle">แบบรายการ</button>
                <button type="button" :class="{ 'is-active': view === 'grid' }" @click="view = 'grid'" data-testid="schedule-grid-toggle">แบบตาราง</button>
            </div>
            <div class="toolbar-actions">
                @if($isWorkspace && $availableOfferings->isNotEmpty())
                    <span class="compact-summary">{{ $availableOfferings->count() }} รายวิชา · {{ $occurrences->count() }} รายการในสัปดาห์</span>
                @endif
                @if($canEdit)
                    <button type="button" class="btn btn-primary" data-testid="schedule-create-link" @click="openCreate()">+ เพิ่ม</button>
                @else
                    <span class="badge badge-gray">ดูข้อมูลอย่างเดียว</span>
                @endif
            </div>
        </div>

        @if(! $isWorkspace && $courseOffering)
            @php
                $course = $courseOffering->course;
            @endphp
            <div class="nested-context">
                <div>
                    <div class="nested-course">{{ $course?->course_code ?? '-' }} {{ $course?->name_th ?? $course?->name_en ?? '' }}</div>
                    <div class="nested-meta">{{ $course?->curriculum?->name ?? '-' }} · {{ $academicYear?->name ?? '-' }} / เทอม {{ $academicYear?->semester ?? '-' }}</div>
                </div>
                <span class="compact-summary">{{ $occurrences->count() }} รายการในสัปดาห์</span>
            </div>
        @endif

        @if($errors->has('schedule') && ! $openCreateModal && ! $openEditScheduleId)
            <div class="schedule-empty" style="border-color:var(--status-conflict-border);background:var(--status-conflict-bg);color:var(--status-conflict-fg);font-weight:800;">
                {{ $errors->first('schedule') }}
            </div>
        @endif

        @if($isWorkspace && $availableOfferings->isEmpty())
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
                                <th style="width:120px;">เวลา</th>
                                <th>กิจกรรม</th>
                                <th style="width:128px;">กลุ่ม</th>
                                <th style="width:150px;">ผู้สอน</th>
                                <th style="width:158px;">สถานที่</th>
                                <th style="width:104px;">สถานะ</th>
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
                                            <div class="sched-time">{{ $timeText }}</div>
                                            <div class="sched-duration">{{ $formatDuration($occurrence['duration_minutes']) }}</div>
                                        </td>
                                        <td>
                                            <span class="activity-tag" style="--activity-color: {{ $activityTone($schedule) }};">{{ $activity?->name ?? 'กิจกรรม' }}</span>
                                            <span class="sched-activity-course">{{ $offeringCourse?->course_code ?? '-' }}</span>
                                            <div class="sched-activity-name">{{ $schedule->topic ?: ($activity?->name ?? 'รายการสอน') }}</div>
                                            @if($isWorkspace && ($offeringCourse?->name_th || $offeringCourse?->name_en))
                                                <div class="sched-muted" style="margin-top:2px;">{{ $offeringCourse?->name_th ?? $offeringCourse?->name_en }}</div>
                                            @endif
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
                <div class="schedule-grid">
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
                                        $room = $schedule->room;
                                        $offeringCourse = $schedule->courseOffering?->course;
                                    @endphp
                                    <div role="button" tabindex="0" class="grid-activity" style="--activity-color: {{ $activityTone($schedule) }};" data-schedule-modal-trigger @click="detailModal = 'schedule-{{ $schedule->id }}'" @keydown.enter.prevent="detailModal = 'schedule-{{ $schedule->id }}'" @keydown.space.prevent="detailModal = 'schedule-{{ $schedule->id }}'">
                                        <div class="grid-course">{{ $offeringCourse?->course_code ?? '-' }}</div>
                                        <strong>{{ $schedule->topic ?: ($schedule->activityType?->name ?? 'รายการสอน') }}</strong>
                                        <div>{{ $formatTime($schedule->start_time) }}-{{ $formatTime($schedule->end_time) }} · {{ $room?->room_name ?? $room?->room_code ?? 'ไม่ระบุสถานที่' }}</div>
                                        <div style="margin-top:4px;">
                                            @foreach($schedule->studentGroups as $group)
                                                <span class="group-chip" style="min-height:18px;padding-inline:5px;">{{ $group->group_code }}</span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </div>
        @endif

        @foreach($schedules as $schedule)
            @php
                $activity = $schedule->activityType;
                $room = $schedule->room;
                $offering = $schedule->courseOffering;
                $offeringCourse = $offering?->course;
                $timeText = $formatTime($schedule->start_time) . '-' . $formatTime($schedule->end_time);
                $dateText = $schedule->start_date?->format('d/m/Y') === $schedule->end_date?->format('d/m/Y')
                    ? $schedule->start_date?->format('d/m/Y')
                    : ($schedule->start_date?->format('d/m/Y') . ' - ' . $schedule->end_date?->format('d/m/Y'));
                $scheduleCanEdit = $offering?->academicYear?->phase === 'scheduling';
            @endphp
            <div class="schedule-modal-backdrop" x-show="detailModal === 'schedule-{{ $schedule->id }}'" x-cloak @click.self="detailModal = null" data-testid="schedule-detail-modal">
                <section class="schedule-modal" role="dialog" aria-modal="true" aria-labelledby="schedule-detail-title-{{ $schedule->id }}">
                    <div class="modal-handle"></div>
                    <div class="modal-head">
                        <div>
                            <span class="activity-tag" style="--activity-color: {{ $activityTone($schedule) }};">{{ $activity?->name ?? 'กิจกรรม' }}</span>
                            <div class="modal-title" id="schedule-detail-title-{{ $schedule->id }}">{{ $schedule->topic ?: ($activity?->name ?? 'รายการสอน') }}</div>
                        </div>
                        <button type="button" class="modal-close" @click="detailModal = null" aria-label="ปิด">×</button>
                    </div>
                    <div class="detail-body">
                        <div class="detail-list">
                            <div class="detail-item">
                                <div class="detail-label">วันและเวลา</div>
                                <div>{{ $dateText }} · {{ $timeText }} ({{ $formatDuration($durationForSchedule($schedule)) }})</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">รายวิชา</div>
                                <div>{{ $offeringCourse?->course_code ?? '-' }} {{ $offeringCourse?->name_th ?? $offeringCourse?->name_en ?? '' }}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">ผู้สอน</div>
                                <div>{{ $schedule->instructors->map(fn ($instructor) => $instructor->formatted_name ?? $instructor->name)->implode(', ') ?: 'ไม่มีผู้สอน' }}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">สถานที่</div>
                                <div>{{ $room?->room_name ?? $room?->room_code ?? 'ไม่ระบุสถานที่' }}{{ $room?->building ? ' · ' . $room->building : '' }}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">กลุ่มนักศึกษา</div>
                                <div>{{ $schedule->studentGroups->pluck('group_code')->implode(', ') ?: '-' }}</div>
                            </div>
                            @if($schedule->remark)
                                <div class="detail-item">
                                    <div class="detail-label">หมายเหตุ</div>
                                    <div>{{ $schedule->remark }}</div>
                                </div>
                            @endif
                        </div>
                    </div>
                    @if($scheduleCanEdit)
                        <div class="modal-actions">
                            <button type="button" class="btn btn-secondary" data-testid="schedule-edit-modal-trigger" @click="openEdit('{{ $schedule->id }}')">แก้ไขข้อมูล</button>
                            <form id="delete-schedule-{{ $schedule->id }}" method="POST" action="{{ route('maker.course_offerings.schedules.destroy', [$offering, $schedule]) }}" style="display:none;">
                                @csrf
                                @method('DELETE')
                            </form>
                            <button type="button" class="btn btn-red" data-form="delete-schedule-{{ $schedule->id }}" data-label="{{ $activity?->name ?? 'รายการสอน' }} {{ $timeText }}" onclick="tpssDelete(this)" data-testid="schedule-delete-button">ลบรายการ</button>
                        </div>
                    @endif
                </section>
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
                @endphp
                <div class="schedule-modal-backdrop" x-show="editModal === 'schedule-{{ $schedule->id }}'" x-cloak @click.self="closeEdit()" data-testid="schedule-edit-modal">
                    <section class="schedule-modal is-form" role="dialog" aria-modal="true" aria-labelledby="schedule-edit-title-{{ $schedule->id }}">
                        <div class="modal-handle"></div>
                        <div class="modal-head">
                            <div>
                                <span class="week-pill">แก้ไขกิจกรรม</span>
                                <div class="modal-title" id="schedule-edit-title-{{ $schedule->id }}">แก้ไขรายละเอียดกิจกรรม</div>
                            </div>
                            <button type="button" class="modal-close" @click="closeEdit()" aria-label="ปิด">×</button>
                        </div>
                        <form method="POST" action="{{ route('maker.course_offerings.schedules.update', [$offering, $schedule]) }}" data-testid="schedule-edit-form">
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
                                        <input id="edit_start_date_{{ $schedule->id }}" name="start_date" type="date" required class="modal-control" value="{{ $editOld('start_date', $schedule->start_date?->format('Y-m-d')) }}">
                                    </div>
                                    <div>
                                        <label class="modal-label" for="edit_end_date_{{ $schedule->id }}">วันที่สิ้นสุด <span class="required-mark">*</span></label>
                                        <input id="edit_end_date_{{ $schedule->id }}" name="end_date" type="date" required class="modal-control" value="{{ $editOld('end_date', $schedule->end_date?->format('Y-m-d')) }}">
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
                                        <select id="edit_activity_type_id_{{ $schedule->id }}" name="activity_type_id" required class="modal-control">
                                            @foreach($activityTypes as $activityType)
                                                <option value="{{ $activityType->id }}" @selected((string) $editOld('activity_type_id', $schedule->activity_type_id) === (string) $activityType->id)>
                                                    {{ $activityType->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="modal-label" for="edit_room_id_{{ $schedule->id }}">ห้อง/สถานที่ <span class="optional-note">ไม่บังคับ</span></label>
                                        <select id="edit_room_id_{{ $schedule->id }}" name="room_id" class="modal-control">
                                            <option value="">ไม่ระบุสถานที่</option>
                                            @foreach($rooms as $roomOption)
                                                <option value="{{ $roomOption->id }}" @selected((string) $editOld('room_id', $schedule->room_id) === (string) $roomOption->id)>
                                                    {{ $roomOption->room_code }} · {{ $roomOption->room_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="modal-field-full">
                                        <label class="modal-label" for="edit_topic_{{ $schedule->id }}">หัวข้อ <span class="optional-note">ไม่บังคับ</span></label>
                                        <input id="edit_topic_{{ $schedule->id }}" name="topic" type="text" maxlength="255" class="modal-control" value="{{ $editOld('topic', $schedule->topic) }}" placeholder="ระบุชื่อกิจกรรม">
                                    </div>
                                    <div>
                                        <label class="modal-label" for="edit_capacity_required_{{ $schedule->id }}">จำนวนรองรับ <span class="optional-note">ไม่บังคับ</span></label>
                                        <input id="edit_capacity_required_{{ $schedule->id }}" name="capacity_required" type="number" min="1" class="modal-control" value="{{ $editOld('capacity_required', $schedule->capacity_required) }}">
                                    </div>
                                    <div>
                                        <label class="modal-label" for="edit_sub_group_label_{{ $schedule->id }}">ป้ายกลุ่มย่อย <span class="optional-note">ไม่บังคับ</span></label>
                                        <input id="edit_sub_group_label_{{ $schedule->id }}" name="sub_group_label" type="text" maxlength="20" class="modal-control" value="{{ $editOld('sub_group_label', $schedule->sub_group_label) }}">
                                    </div>
                                    <div class="modal-field-full">
                                        <label class="modal-label" for="edit_remark_{{ $schedule->id }}">หมายเหตุ <span class="optional-note">ไม่บังคับ</span></label>
                                        <textarea id="edit_remark_{{ $schedule->id }}" name="remark" rows="2" class="modal-control">{{ $editOld('remark', $schedule->remark) }}</textarea>
                                    </div>
                                </div>

                                <div class="modal-section">
                                    <div class="modal-section-title">ผู้สอน <span class="required-mark">*</span></div>
                                    <div class="modal-choice-grid">
                                        @foreach($offering->instructorPool as $instructor)
                                            <label class="modal-choice">
                                                <input type="checkbox" name="instructor_ids[]" value="{{ $instructor->id }}" @checked(in_array((string) $instructor->id, $editInstructorIds, true)) data-testid="schedule-instructor">
                                                <span>{{ $instructor->formatted_name ?? $instructor->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="modal-section">
                                    <label class="modal-label" for="edit_lead_instructor_id_{{ $schedule->id }}">ผู้สอนหลัก <span class="optional-note">ไม่บังคับ</span></label>
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
                                    <div class="modal-choice-grid">
                                        @foreach($offering->studentGroups as $group)
                                            <label class="modal-choice">
                                                <input type="checkbox" name="student_group_ids[]" value="{{ $group->id }}" @checked(in_array((string) $group->id, $editGroupIds, true)) data-testid="schedule-student-group">
                                                <span>{{ $group->group_code }} · {{ $group->student_count }} คน</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <div class="modal-actions">
                                <button type="button" class="btn btn-secondary" @click="closeEdit()">ยกเลิก</button>
                                <button type="submit" class="btn btn-primary" data-testid="schedule-submit">บันทึกการแก้ไข</button>
                            </div>
                        </form>
                    </section>
                </div>
            @endif
        @endforeach

        @if($canEdit)
            <div class="schedule-modal-backdrop" x-show="showCreate" x-cloak @click.self="closeCreate()" data-testid="schedule-create-modal">
                <section class="schedule-modal is-form" role="dialog" aria-modal="true" aria-labelledby="schedule-create-title">
                    <div class="modal-handle"></div>
                    <div class="modal-head">
                        <div>
                            <span class="week-pill">กิจกรรมใหม่</span>
                            <div class="modal-title" id="schedule-create-title">เพิ่มกิจกรรมในตาราง</div>
                        </div>
                        <button type="button" class="modal-close" @click="closeCreate()" aria-label="ปิด">×</button>
                    </div>
                    <form method="POST" action="{{ $createAction }}" data-testid="schedule-form">
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
                                @endif

                                <div>
                                    <label class="modal-label" for="start_date">วันที่เริ่ม <span class="required-mark">*</span></label>
                                    <input x-ref="startDate" id="start_date" name="start_date" type="date" required class="modal-control" value="{{ old('start_date') }}">
                                </div>
                                <div>
                                    <label class="modal-label" for="end_date">วันที่สิ้นสุด <span class="required-mark">*</span></label>
                                    <input x-ref="endDate" id="end_date" name="end_date" type="date" required class="modal-control" value="{{ old('end_date') }}">
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
                                    <select id="activity_type_id" name="activity_type_id" required class="modal-control">
                                        <option value="">เลือกประเภทกิจกรรม</option>
                                        @foreach($activityTypes as $activityType)
                                            <option value="{{ $activityType->id }}" @selected((string) old('activity_type_id') === (string) $activityType->id)>
                                                {{ $activityType->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="modal-label" for="room_id">ห้อง/สถานที่ <span class="optional-note">ไม่บังคับ</span></label>
                                    <select id="room_id" name="room_id" class="modal-control">
                                        <option value="">ไม่ระบุสถานที่</option>
                                        @foreach($rooms as $room)
                                            <option value="{{ $room->id }}" @selected((string) old('room_id') === (string) $room->id)>
                                                {{ $room->room_code }} · {{ $room->room_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="modal-field-full">
                                    <label class="modal-label" for="topic">หัวข้อ <span class="optional-note">ไม่บังคับ</span></label>
                                    <input id="topic" name="topic" type="text" maxlength="255" class="modal-control" value="{{ old('topic') }}" placeholder="ระบุชื่อกิจกรรม">
                                </div>
                                <div>
                                    <label class="modal-label" for="capacity_required">จำนวนรองรับ <span class="optional-note">ไม่บังคับ</span></label>
                                    <input id="capacity_required" name="capacity_required" type="number" min="1" class="modal-control" value="{{ old('capacity_required') }}">
                                </div>
                                <div>
                                    <label class="modal-label" for="sub_group_label">ป้ายกลุ่มย่อย <span class="optional-note">ไม่บังคับ</span></label>
                                    <input id="sub_group_label" name="sub_group_label" type="text" maxlength="20" class="modal-control" value="{{ old('sub_group_label') }}">
                                </div>
                                <div class="modal-field-full">
                                    <label class="modal-label" for="remark">หมายเหตุ <span class="optional-note">ไม่บังคับ</span></label>
                                    <textarea id="remark" name="remark" rows="2" class="modal-control">{{ old('remark') }}</textarea>
                                </div>
                            </div>

                            @foreach($schedulingOfferings as $offeringOption)
                                <div x-show="selectedOfferingId === '{{ $offeringOption->id }}'" x-cloak>
                                    <div class="modal-section">
                                        <div class="modal-section-title">ผู้สอน <span class="required-mark">*</span></div>
                                        <div class="modal-choice-grid">
                                            @foreach($offeringOption->instructorPool as $instructor)
                                                <label class="modal-choice">
                                                    <input type="checkbox" name="instructor_ids[]" value="{{ $instructor->id }}" @checked(in_array((string) $instructor->id, $selectedInstructorIds, true)) :disabled="selectedOfferingId !== '{{ $offeringOption->id }}'" data-testid="schedule-instructor">
                                                    <span>{{ $instructor->formatted_name ?? $instructor->name }}</span>
                                                </label>
                                            @endforeach
                                        </div>
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
                                        <div class="modal-choice-grid">
                                            @foreach($offeringOption->studentGroups as $group)
                                                <label class="modal-choice">
                                                    <input type="checkbox" name="student_group_ids[]" value="{{ $group->id }}" @checked(in_array((string) $group->id, $selectedGroupIds, true)) :disabled="selectedOfferingId !== '{{ $offeringOption->id }}'" data-testid="schedule-student-group">
                                                    <span>{{ $group->group_code }} · {{ $group->student_count }} คน</span>
                                                </label>
                                            @endforeach
                                        </div>
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
