@php
    $formatDate = fn ($date) => $date ? \App\Support\ThaiDate::date($date) : '-';
    $formatTime = fn ($value) => substr((string) $value, 0, 5);

    $warningTypeLabels = [
        'conflict'         => 'การชนข้ามวิชา',
        'incomplete'       => 'ข้อมูลไม่ครบ',
        'no_role'          => 'ไม่กำหนดบทบาท',
        'dept_mismatch'    => 'ผู้สอนต่างภาควิชา',
        'capacity_exceeded'=> 'ความจุเกิน',
        'holiday'          => 'ตรงวันหยุด',
    ];
    $warningTypeColors = [
        'conflict'         => [
            'border' => 'oklch(52% 0.2 25)',   'bg' => 'oklch(96% 0.05 25)',  'fg' => 'oklch(42% 0.19 25)',
            'svg' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>'
        ],
        'holiday'          => [
            'border' => 'oklch(72% 0.12 80)',  'bg' => 'oklch(97% 0.05 85)',  'fg' => 'oklch(45% 0.12 70)',
            'svg' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'
        ],
        'incomplete'       => [
            'border' => 'oklch(65% 0.12 50)',  'bg' => 'oklch(97% 0.04 50)',  'fg' => 'oklch(40% 0.14 50)',
            'svg' => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>'
        ],
        'capacity_exceeded'=> [
            'border' => 'oklch(52% 0.16 28)',  'bg' => 'oklch(96% 0.04 28)',  'fg' => 'oklch(40% 0.14 28)',
            'svg' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>'
        ],
        'no_role'          => [
            'border' => 'oklch(48% 0.14 268)', 'bg' => 'oklch(96% 0.03 268)', 'fg' => 'oklch(40% 0.14 268)',
            'svg' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'
        ],
        'dept_mismatch'    => [
            'border' => 'oklch(48% 0.14 168)', 'bg' => 'oklch(95% 0.04 168)', 'fg' => 'oklch(38% 0.14 168)',
            'svg' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'
        ],
    ];
@endphp

<x-app-layout title="การแจ้งเตือน">
    <style>
        .alert-page {
            --alert-border: oklch(86% 0.018 232);
            --alert-border-strong: oklch(76% 0.03 232);
            --alert-muted: oklch(42% 0.032 238);
            --alert-soft: oklch(97% 0.014 228);
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .alert-title {
            margin: 0;
            font-size: 22px;
            font-weight: 950;
            color: var(--brand-navy);
            line-height: 1.2;
        }
        .alert-year-inline {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border: 1px solid var(--alert-border-strong);
            border-radius: 999px;
            background: var(--alert-soft);
            color: var(--fg-1);
            font-size: 12px;
            font-weight: 850;
        }
        .alert-summary-container {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .alert-summary-total-card {
            display: grid;
            grid-template-columns: 44px 1fr;
            align-items: center;
            gap: 16px;
            padding: 16px 20px;
            border: 1px solid var(--alert-border);
            border-left: 4px solid oklch(65% 0.12 50);
            border-radius: 10px;
            background: var(--surface);
            box-shadow: 0 1px 3px oklch(0% 0 0 / 0.04);
        }
        .alert-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
        }
        .alert-summary-card {
            display: grid;
            grid-template-columns: 40px 1fr;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border: 1px solid var(--alert-border);
            border-top: 3px solid var(--alert-border-strong);
            border-radius: 10px;
            background: var(--surface);
            box-shadow: 0 1px 3px oklch(0% 0 0 / 0.04);
            transition: transform 0.15s ease;
        }
        .alert-summary-card:hover { transform: translateY(-1px); }
        .alert-summary-icon {
            display: inline-flex; align-items: center; justify-content: center;
            width: 40px; height: 40px; border-radius: 10px; flex-shrink: 0;
        }
        .alert-summary-value { font-size: 26px; font-weight: 950; line-height: 1.05; color: var(--brand-navy); font-variant-numeric: tabular-nums; }
        .alert-summary-label { color: var(--fg-1); font-size: 12.5px; font-weight: 900; }
        .alert-summary-sub { color: var(--alert-muted); font-size: 11.5px; font-weight: 700; margin-top: 3px; line-height: 1.4; }
        .alert-group {
            border: 1px solid var(--alert-border);
            border-radius: 10px;
            background: var(--surface);
            overflow: hidden;
            box-shadow: 0 1px 3px oklch(0% 0 0 / 0.05);
        }
        .alert-group-head {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-bottom: 1px solid var(--alert-border);
            background: var(--alert-soft);
        }
        .alert-group-head.is-collapsed {
            border-bottom-color: transparent;
        }
        .alert-group-title {
            font-size: 14px;
            font-weight: 900;
            color: var(--brand-navy);
            flex: 1;
        }
        .alert-count-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 900;
        }
        .alert-list {
            display: flex;
            flex-direction: column;
            divide-y: 1px solid var(--alert-border);
        }
        .alert-item {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 12px;
            padding: 13px 16px;
            border-bottom: 1px solid var(--alert-border);
            align-items: start;
            transition: background 0.12s;
        }
        .alert-item:last-child { border-bottom: none; }
        .alert-item:hover { background: oklch(98.5% 0.006 232); }
        .alert-item-label {
            font-size: 13px;
            font-weight: 900;
            color: var(--fg-1);
            line-height: 1.4;
        }
        .alert-item-msg {
            font-size: 12px;
            font-weight: 700;
            color: var(--alert-muted);
            margin-top: 3px;
            line-height: 1.5;
        }
        .alert-item-action .btn {
            min-height: 38px;
            padding: 8px 18px;
            font-size: 13.5px;
            font-weight: 850;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 8px;
            border: 1px solid var(--alert-border-strong);
            background-color: var(--surface);
            color: var(--brand-navy);
            transition: all 0.15s ease;
            text-decoration: none;
            cursor: pointer;
        }
        .alert-item-action .btn:hover {
            background-color: var(--alert-soft);
            border-color: var(--brand-navy);
            color: var(--brand-navy);
        }
        .alert-item-action .btn:active,
        .alert-item-action .btn:focus,
        .alert-item-action .btn:visited {
            color: var(--brand-navy) !important;
            border-color: var(--alert-border-strong) !important;
            background-color: var(--surface) !important;
        }
        .alert-item-action .btn:hover:visited {
            background-color: var(--alert-soft) !important;
            border-color: var(--brand-navy) !important;
            color: var(--brand-navy) !important;
        }
        .alert-pagination {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
            padding: 12px 16px;
            border-top: 1px solid var(--alert-border);
            background: oklch(98.5% 0.006 232);
        }
        .alert-page-btn {
            min-width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--alert-border-strong);
            border-radius: 8px;
            background: var(--surface);
            color: var(--brand-navy);
            font-size: 12px;
            font-weight: 900;
            cursor: pointer;
        }
        .alert-page-btn:hover:not(:disabled) {
            background: var(--alert-soft);
            border-color: var(--brand-navy);
        }
        .alert-page-btn.is-active {
            background: var(--brand-navy);
            border-color: var(--brand-navy);
            color: white;
        }
        .alert-page-btn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }
        .alert-page-ellipsis {
            min-width: 24px;
            text-align: center;
            color: var(--alert-muted);
            font-weight: 900;
        }
        .alert-empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            padding: 40px 24px;
            border: 1px solid var(--alert-border);
            border-radius: 12px;
            background: var(--surface);
            text-align: center;
        }
        .alert-empty-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: color-mix(in oklch, var(--status-success-bg, oklch(96% 0.04 145)) 45%, var(--surface));
            color: var(--status-success-fg, oklch(38% 0.14 145));
        }
        .alert-empty-title {
            font-size: 17px;
            font-weight: 950;
            color: var(--status-success-fg, oklch(38% 0.14 145));
        }
        .alert-empty-sub {
            max-width: 540px;
            color: var(--fg-2);
            font-size: 13px;
            font-weight: 700;
            line-height: 1.55;
        }
        .alert-year-select { display: flex; align-items: center; gap: 8px; }
        .alert-year-select select {
            min-height: 32px;
            padding: 4px 10px;
            border: 1px solid var(--alert-border-strong);
            border-radius: 8px;
            background: var(--surface);
            color: var(--fg-1);
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
        }
        @media (max-width: 640px) {
            .alert-item {
                grid-template-columns: 1fr;
                gap: 12px;
                padding: 16px;
            }
            .alert-item-action {
                width: 100%;
                margin-top: 4px;
            }
            .alert-item-action .btn {
                width: 100%;
                justify-content: center;
                min-height: 40px;
                font-size: 14px;
            }
            .alert-summary-grid {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }
            .alert-summary-card {
                padding: 12px;
                gap: 8px;
            }
            .alert-summary-value {
                font-size: 22px;
            }
            .alert-summary-label {
                font-size: 11.5px;
            }
            .alert-summary-sub {
                font-size: 10px;
            }
            .alert-summary-total-card {
                padding: 14px 16px;
                gap: 12px;
            }
        }
        @media (max-width: 480px) {
            .alert-summary-grid {
                grid-template-columns: 1fr;
            }
            .alert-summary-total-card {
                grid-template-columns: 1fr;
                text-align: center;
                justify-items: center;
                gap: 8px;
            }
            .alert-group-head {
                padding: 10px 12px;
            }
            .alert-group-title {
                font-size: 13px;
            }
            .alert-count-badge {
                font-size: 10px;
                padding: 1px 8px;
            }
            .alert-pagination {
                justify-content: center;
                flex-wrap: wrap;
                padding: 10px 12px;
            }
        }
    </style>
    <script>
        window.tpssAlertGroup = function(total) {
            return {
                collapsed: true,
                page: 1,
                perPage: 10,
                total: Number(total) || 0,
                get totalPages() {
                    return Math.max(1, Math.ceil(this.total / this.perPage));
                },
                toggle() {
                    this.collapsed = !this.collapsed;
                },
                visible(index) {
                    return index >= ((this.page - 1) * this.perPage) && index < (this.page * this.perPage);
                },
                setPage(page) {
                    const next = Number(page);
                    if (Number.isNaN(next)) return;
                    this.page = Math.min(this.totalPages, Math.max(1, next));
                },
                pageItems() {
                    const last = this.totalPages;
                    const current = this.page;
                    const items = [];

                    for (let i = 1; i <= last; i++) {
                        if (i === 1 || i === last || Math.abs(i - current) <= 1) {
                            items.push(i);
                        } else if (items[items.length - 1] !== '...') {
                            items.push('...');
                        }
                    }

                    return items;
                },
            };
        };
    </script>

    <div class="alert-page">
        {{-- Header --}}
        <section style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; padding:0 4px;">
            <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                <h1 class="alert-title">การแจ้งเตือน</h1>
                @if($selectedAcademicYear)
                    <span class="alert-year-inline">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        ปีการศึกษา {{ $selectedAcademicYear->name }}
                    </span>
                @endif
            </div>
            @if($availableYears->count() > 1)
                <form method="GET" class="alert-year-select">
                    <label for="alert-year-sel" style="font-size:12px;font-weight:800;color:var(--fg-2);">ปีการศึกษา</label>
                    <select id="alert-year-sel" name="academic_year_id" onchange="this.form.submit()">
                        @foreach($availableYears as $year)
                            <option value="{{ $year->id }}" {{ (int)$selectedAcademicYearId === (int)$year->id ? 'selected' : '' }}>
                                {{ $year->name }}
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif
        </section>

        {{-- Summary Cards — แสดงตลอด (เห็นจำนวนแม้เป็น 0) --}}
        @php
            $hasWarn = $totalWarningCount > 0;
            $totFg = $hasWarn ? 'oklch(40% 0.14 50)' : 'oklch(38% 0.14 145)';
            $totBg = $hasWarn ? 'oklch(97% 0.04 50)' : 'oklch(96% 0.04 145)';
        @endphp
            <section class="alert-summary-container">
                {{-- Total Card --}}
                <div class="alert-summary-total-card" style="border-left-color: {{ $totFg }};">
                    <div class="alert-summary-icon" style="background:{{ $totBg }}; color:{{ $totFg }}; width: 44px; height: 44px;">
                        @if($hasWarn)
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        @endif
                    </div>
                    <div>
                        <div style="display: flex; align-items: baseline; gap: 8px;">
                            <span class="alert-summary-value" style="color:{{ $totFg }}; font-size: 28px;">{{ $totalWarningCount }}</span>
                            <span class="alert-summary-label" style="font-size: 14px; font-weight: 900;">รายการแจ้งเตือนทั้งหมด</span>
                        </div>
                        <div class="alert-summary-sub">{{ $hasWarn ? 'กรุณาตรวจสอบรายละเอียดและแก้ไขข้อมูลตารางสอนให้ครบถ้วนก่อนส่งอนุมัติ' : 'ตารางสอนทุกรายการมีข้อมูลครบถ้วน — ไม่พบปัญหาที่ต้องแก้ไข' }}</div>
                    </div>
                </div>

                {{-- Detail Grid --}}
                <div class="alert-summary-grid">
                    @foreach($warningTypeLabels as $type => $label)
                        @php
                            $count = $warningTypeCounts[$type] ?? 0;
                            $colors = $warningTypeColors[$type];
                            $isZero = $count === 0;
                        @endphp
                        <div class="alert-summary-card" style="border-top: 3px solid {{ $isZero ? 'var(--alert-border)' : $colors['border'] }}; opacity: {{ $isZero ? '0.55' : '1' }}; transition: opacity 0.15s ease;">
                            <div class="alert-summary-icon" style="background:{{ $isZero ? 'var(--alert-soft)' : $colors['bg'] }}; color:{{ $isZero ? 'var(--alert-muted)' : $colors['fg'] }};">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round">{!! $colors['svg'] !!}</svg>
                            </div>
                            <div>
                                <div class="alert-summary-value" style="color:{{ $isZero ? 'var(--alert-muted)' : $colors['fg'] }};">{{ $count }}</div>
                                <div class="alert-summary-label">{{ $label }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

        {{-- Warning Groups --}}
        @if(! $selectedAcademicYear)
            <div class="alert-empty-state">
                <div class="alert-empty-icon" style="background:var(--alert-soft); color:var(--alert-muted);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                </div>
                <div class="alert-empty-title" style="color:var(--brand-navy);">ยังไม่มีปีการศึกษา</div>
                <div class="alert-empty-sub">ยังไม่มีรายวิชาที่รับผิดชอบในระบบ กรุณาติดต่อผู้ดูแลระบบ</div>
            </div>

        @else
            @foreach($warningTypeLabels as $type => $typeLabel)
                @php
                    $groupItems = $warnings->filter(fn ($w) => $w['type'] === $type)->values();
                    $colors = $warningTypeColors[$type];
                @endphp
                @if($groupItems->isNotEmpty())
                    <div
                        class="alert-group"
                        x-data="tpssAlertGroup({{ $groupItems->count() }})"
                        data-testid="alert-group-{{ $type }}"
                        data-alert-initial-collapsed="true"
                        data-alert-page-size="10"
                    >
                        <div
                            class="alert-group-head"
                            :class="{ 'is-collapsed': collapsed }"
                            @click="toggle()"
                            style="cursor: pointer; user-select: none;"
                        >
                            <div style="display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:8px;background:{{ $colors['bg'] }};color:{{ $colors['fg'] }};flex-shrink:0;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round">{!! $colors['svg'] !!}</svg>
                            </div>
                            <div class="alert-group-title">{{ $typeLabel }}</div>
                            <span class="alert-count-badge" style="background:{{ $colors['bg'] }};color:{{ $colors['fg'] }};border:1px solid {{ $colors['border'] }};">
                                {{ $groupItems->count() }} รายการ
                            </span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="transition: transform 0.2s; margin-left: 8px; color: var(--fg-2);" :style="collapsed ? 'transform: rotate(-90deg)' : 'transform: rotate(0deg)'">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </div>
                        <div class="alert-list" x-show="!collapsed" x-transition x-cloak>
                            @foreach($groupItems as $item)
                                @php
                                    /** @var \App\Models\Schedule $schedule */
                                    $schedule = $item['schedule'];
                                    $offering = $schedule->courseOffering;
                                    $editUrl  = route('maker.course_offerings.schedules.index', [
                                        $offering,
                                        'edit_schedule_id' => $schedule->id,
                                        'week_start'       => $schedule->start_date?->toDateString(),
                                        'return_url'       => request()->fullUrl(),
                                    ]);
                                @endphp
                                <div class="alert-item" x-show="visible({{ $loop->index }})" x-cloak>
                                    <div>
                                        <div class="alert-item-label">
                                            {{ $offering?->course?->course_code ?? 'รายวิชา' }}
                                            @if($offering?->course?->name_th)
                                                <span style="font-weight:700;color:var(--fg-2);font-size:12px;"> — {{ $offering->course->name_th }}</span>
                                            @endif
                                        </div>
                                        <div class="alert-item-msg" style="display: flex; flex-direction: column; gap: 4px;">
                                            <div style="display: inline-flex; align-items: center; gap: 8px;">
                                                <span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background-color: var(--brand-navy, #1e293b);"></span>
                                                <span>{{ $item['label'] }}</span>
                                            </div>
                                            <div style="display: inline-flex; align-items: center; gap: 8px;">
                                                <span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background-color: {{ $colors['fg'] }};"></span>
                                                <span>{{ $item['message'] }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="alert-item-action">
                                        <a href="{{ $editUrl }}" class="btn">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                            แก้ไข
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                            @if($groupItems->count() > 10)
                                <div
                                    class="alert-pagination"
                                    data-testid="alert-pagination-{{ $type }}"
                                >
                                    <button type="button" class="alert-page-btn" @click="setPage(page - 1)" :disabled="page <= 1" aria-label="ก่อนหน้า">&lt;</button>
                                    <template x-for="(item, idx) in pageItems()" :key="`${item}-${idx}`">
                                        <span>
                                            <button
                                                type="button"
                                                class="alert-page-btn"
                                                x-show="item !== '...'"
                                                :class="{ 'is-active': item === page }"
                                                @click="setPage(item)"
                                                x-text="item"
                                            ></button>
                                            <span class="alert-page-ellipsis" x-show="item === '...'">...</span>
                                        </span>
                                    </template>
                                    <button type="button" class="alert-page-btn" @click="setPage(page + 1)" :disabled="page >= totalPages" aria-label="ถัดไป">&gt;</button>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            @endforeach
        @endif
    </div>
</x-app-layout>
