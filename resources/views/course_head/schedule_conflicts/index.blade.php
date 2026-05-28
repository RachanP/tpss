@php
    $formatDate = fn ($date) => $date ? \App\Support\ThaiDate::date($date) : '-';
    $formatTime = fn ($value) => substr((string) $value, 0, 5);
    $conflictTypeLabels = [
        'instructor_overlap' => 'ผู้สอนชน',
        'room_overlap' => 'ห้อง/สถานที่ชน',
        'group_overlap' => 'กลุ่มนักศึกษาชน',
    ];
    $conflictRunStatus = $conflictStatus['status'] ?? 'ready';
    $conflictStatusLabel = match ($conflictRunStatus) {
        'failed'  => 'ตรวจสอบรายการชนไม่สำเร็จ ระบบจะแสดงผลล่าสุดที่พร้อมใช้งานถ้ามี',
        'running' => 'กำลังประมวลผลรายการชน อาจใช้เวลาสักครู่',
        'ready'   => 'ผลตรวจสอบพร้อมใช้งาน',
        default   => '', // 'missing' — ไม่แสดงอะไร
    };
@endphp

<x-app-layout title="การแจ้งเตือนการชน">
    <style>
        .conflict-page {
            --schedule-border: oklch(86% 0.018 232);
            --schedule-border-strong: oklch(76% 0.03 232);
            --schedule-muted: oklch(42% 0.032 238);
            --schedule-soft: oklch(97% 0.014 228);
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .conflict-hero,
        .conflict-offering {
            border: 1px solid var(--schedule-border);
            border-radius: 10px;
            background: var(--surface);
            box-shadow: 0 1px 3px oklch(0% 0 0 / 0.05);
        }
        .conflict-hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 16px;
            align-items: center;
            padding: 16px 18px;
            border-color: var(--schedule-border-strong);
        }
        .conflict-heading-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .conflict-kicker {
            display: inline-flex;
            align-items: center;
            min-height: 22px;
            padding: 2px 10px;
            border: 1px solid var(--schedule-border-strong);
            border-radius: 999px;
            background: var(--schedule-soft);
            color: var(--schedule-muted);
            font-size: 10px;
            font-weight: 850;
            line-height: 1.2;
        }
        .conflict-title {
            margin-top: 7px;
            font-size: 26px;
            font-weight: 950;
            color: var(--brand-navy);
            line-height: 1.25;
            letter-spacing: 0;
        }
        .conflict-copy {
            max-width: 920px;
            margin-top: 5px;
            color: var(--fg-2);
            font-size: 13px;
            line-height: 1.55;
        }
        .conflict-year-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
            padding: 6px 12px;
            border: 1px solid var(--schedule-border-strong);
            border-radius: 999px;
            background: var(--schedule-soft);
            color: var(--fg-1);
            font-size: 12.5px;
            font-weight: 850;
            line-height: 1.3;
        }
        .conflict-year-badge svg {
            flex-shrink: 0;
            color: var(--brand-navy);
        }
        .conflict-total {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            min-height: 40px;
            padding: 7px 13px;
            border: 1px solid var(--status-conflict-border);
            border-radius: 999px;
            background: var(--status-conflict-bg);
            color: var(--status-conflict-fg);
            text-align: center;
        }
        .conflict-total strong {
            display: block;
            font-size: 22px;
            line-height: 1;
            font-weight: 900;
            font-variant-numeric: tabular-nums;
        }
        .conflict-total span {
            display: block;
            font-size: 11px;
            font-weight: 800;
        }
        .conflict-status {
            border: 1px solid var(--status-warning-border, var(--schedule-border-strong));
            border-radius: 8px;
            background: var(--status-warning-bg, var(--schedule-soft));
            color: var(--status-warning-fg, var(--fg-1));
            padding: 10px 12px;
            font-size: 13px;
            font-weight: 750;
        }
        .conflict-offering {
            overflow: hidden;
        }
        .conflict-offering-head {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border-bottom: 1px solid var(--schedule-border);
            background: var(--schedule-soft);
            cursor: pointer;
            user-select: none;
            transition: background 0.15s ease;
        }
        .conflict-offering-head:hover {
            background: oklch(94% 0.018 228);
        }
        .conflict-offering-head[aria-expanded="true"] {
            border-bottom-color: var(--schedule-border);
        }
        .conflict-offering-head[aria-expanded="false"] {
            border-bottom: none;
        }
        .conflict-chevron {
            flex-shrink: 0;
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            color: var(--schedule-muted);
            transition: transform 0.22s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .conflict-offering-head[aria-expanded="true"] .conflict-chevron {
            transform: rotate(180deg);
        }
        .conflict-list-wrapper {
            display: grid;
            grid-template-rows: 0fr;
            transition: grid-template-rows 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .conflict-list-wrapper.is-open {
            grid-template-rows: 1fr;
        }
        .conflict-list-inner {
            overflow: hidden;
        }
        .conflict-course {
            min-width: 0;
            flex: 1;
        }
        .conflict-course-code {
            display: inline-flex;
            align-items: center;
            min-height: 34px;
            padding: 3px 12px;
            border: 1px solid var(--brand-navy);
            border-radius: 8px;
            background: var(--brand-navy);
            color: oklch(98% 0.004 240);
            font-size: 20px;
            font-weight: 950;
            line-height: 1.2;
            letter-spacing: 0;
        }
        .conflict-course-name {
            margin-top: 5px;
            font-size: 13px;
            font-weight: 700;
            color: var(--fg-2);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .conflict-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 24px;
            padding: 3px 10px;
            border: 1px solid var(--status-conflict-border);
            border-radius: 999px;
            background: var(--status-conflict-bg);
            color: var(--status-conflict-fg);
            font-size: 11px;
            font-weight: 900;
            white-space: nowrap;
        }
        .conflict-list {
            display: flex;
            flex-direction: column;
        }
        .conflict-item {
            display: grid;
            grid-template-columns: 160px minmax(0, 1fr) 108px;
            gap: 16px;
            padding: 15px 16px;
            border-bottom: 1px solid var(--schedule-border);
        }
        .conflict-item:hover {
            background: oklch(98% 0.006 232);
        }
        .conflict-item:last-child {
            border-bottom: 0;
        }
        .conflict-time {
            font-size: 12.5px;
            font-weight: 850;
            color: var(--fg-1);
            font-variant-numeric: tabular-nums;
        }
        .conflict-topic {
            font-size: 14px;
            font-weight: 900;
            color: var(--fg-1);
            line-height: 1.4;
        }
        .conflict-compare {
            display: grid;
            gap: 7px;
            margin-top: 9px;
        }
        .conflict-compare-card {
            border: 1px solid color-mix(in oklch, var(--status-conflict-border) 72%, var(--schedule-border));
            border-radius: 8px;
            background: color-mix(in oklch, var(--status-conflict-bg) 52%, var(--surface));
            padding: 8px 10px;
        }
        .conflict-compare-title {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            gap: 6px;
            color: var(--fg-1);
            font-size: 12px;
            font-weight: 700;
            line-height: 1.35;
        }
        .conflict-compare-prefix {
            color: var(--schedule-muted);
            font-weight: 700;
        }
        .conflict-compare-target {
            color: var(--brand-navy);
            font-size: 13px;
            font-weight: 900;
            line-height: 1.3;
        }
        .conflict-reason-list {
            list-style: none;
            margin: 8px 0 0;
            padding: 0;
            display: grid;
            gap: 5px;
        }
        .conflict-reason-row {
            display: grid;
            grid-template-columns: 18px auto 1fr;
            align-items: baseline;
            gap: 6px;
            color: var(--fg-1);
            font-size: 12px;
            line-height: 1.4;
        }
        .conflict-reason-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--status-conflict-fg);
            transform: translateY(2px);
        }
        .conflict-reason-row--room_overlap .conflict-reason-icon { color: oklch(52% 0.16 28); }
        .conflict-reason-row--instructor_overlap .conflict-reason-icon { color: oklch(48% 0.14 268); }
        .conflict-reason-row--group_overlap .conflict-reason-icon { color: oklch(48% 0.14 168); }
        .conflict-reason-label {
            color: var(--schedule-muted);
            font-weight: 800;
            white-space: nowrap;
        }
        .conflict-reason-value {
            color: var(--fg-1);
            font-weight: 700;
            min-width: 0;
            word-break: break-word;
        }
        .conflict-reason-value strong {
            font-weight: 900;
            color: var(--fg-1);
        }
        .conflict-reason-value--muted {
            color: var(--schedule-muted);
            font-weight: 700;
            font-style: italic;
        }
        .conflict-detail-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
        }
        .conflict-detail-toggle {
            min-height: 30px;
            border: 1px solid var(--schedule-border-strong);
            border-radius: 8px;
            background: var(--surface);
            color: var(--brand-navy);
            padding: 5px 10px;
            font-size: 12px;
            font-weight: 850;
            cursor: pointer;
            transition: background 0.15s ease, border-color 0.15s ease;
        }
        .conflict-detail-toggle:hover:not(:disabled) {
            background: var(--schedule-soft);
            border-color: var(--brand-navy);
        }
        .conflict-detail-toggle:disabled {
            cursor: wait;
            opacity: .65;
        }
        .conflict-detail-note {
            color: var(--schedule-muted);
            font-size: 12px;
            font-weight: 700;
            transition: opacity 0.15s ease;
        }
        .conflict-messages {
            display: none;
        }
        .conflict-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }
        .conflict-actions .btn {
            min-height: 34px;
            padding: 7px 12px;
            font-size: 12.5px;
            font-weight: 850;
        }
        .conflict-empty {
            padding: 28px 14px;
            text-align: center;
            border: 1px dashed var(--schedule-border-strong);
            border-radius: 10px;
            background: var(--schedule-soft);
            color: var(--fg-2);
            font-weight: 800;
        }
        .conflict-pagination {
            display: flex;
            justify-content: center;
            padding: 6px 0 2px;
        }
        @media (max-width: 760px) {
            .conflict-hero,
            .conflict-item {
                grid-template-columns: 1fr;
            }
            .conflict-title {
                font-size: 22px;
            }
            .conflict-total {
                justify-content: flex-start;
                text-align: left;
            }
            .conflict-actions {
                align-items: flex-start;
                justify-content: flex-start;
            }
        }
    </style>
    <script>
        (() => {
            const scrollKey = 'tpss-conflict-alert-scroll-y';
            const savedScroll = sessionStorage.getItem(scrollKey);

            if (savedScroll !== null) {
                const targetY = Number.parseInt(savedScroll, 10) || 0;
                const restoreScroll = () => window.scrollTo(0, targetY);

                restoreScroll();
                requestAnimationFrame(restoreScroll);
                window.addEventListener('load', () => {
                    restoreScroll();
                    sessionStorage.removeItem(scrollKey);
                }, { once: true });
            }

            window.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('[data-conflict-edit-link]').forEach((link) => {
                    link.addEventListener('click', () => {
                        sessionStorage.setItem(scrollKey, String(window.scrollY));
                    });
                });

                // ── Fix 3: Offering header collapse / expand ──────────────────
                document.querySelectorAll('[data-offering-toggle]').forEach((header) => {
                    const offering = header.closest('.conflict-offering');
                    const wrapper = offering?.querySelector('.conflict-list-wrapper');

                    if (!wrapper) return;

                    // All collapsed by default — consistent UX regardless of count
                    header.setAttribute('aria-expanded', 'false');

                    header.addEventListener('click', () => {
                        const expanded = header.getAttribute('aria-expanded') === 'true';
                        header.setAttribute('aria-expanded', String(!expanded));
                        wrapper.classList.toggle('is-open', !expanded);
                    });
                });

                // ── Fix 1 & 2: Detail toggle — true two-way toggle ────────────
                const detailCache = new Map();
                const previewCache = new Map();
                const pendingCards = new Set();
                const queue = [];
                let activeRequests = 0;
                const maxRequests = 3;

                const runNext = () => {
                    if (activeRequests >= maxRequests || queue.length === 0) {
                        return;
                    }

                    activeRequests += 1;
                    const task = queue.shift();
                    task().finally(() => {
                        activeRequests -= 1;
                        runNext();
                    });
                };

                const enqueue = (task) => {
                    queue.push(task);
                    runNext();
                };

                document.querySelectorAll('[data-conflict-detail-toggle]').forEach((button) => {
                    const item = button.closest('[data-conflict-item]');
                    const detailUrl = item?.getAttribute('data-conflict-detail-url');
                    const target = item?.querySelector('[data-conflict-detail-target]');
                    const note = item?.querySelector('[data-conflict-detail-note]');
                    const originalLabel = button.textContent.trim();
                    let isExpanded = false;

                    if (!item || !detailUrl || !target) return;

                    // Cache the initial preview HTML so we can restore it on collapse
                    previewCache.set(detailUrl, target.innerHTML);

                    button.addEventListener('click', () => {
                        // ── Collapse: restore preview ──
                        if (isExpanded) {
                            target.innerHTML = previewCache.get(detailUrl) ?? '';
                            button.textContent = originalLabel;
                            if (note) note.style.display = '';
                            isExpanded = false;
                            return;
                        }

                        // ── Already fetched: show full detail ──
                        if (detailCache.has(detailUrl)) {
                            target.innerHTML = detailCache.get(detailUrl);
                            button.textContent = 'ยุบรายละเอียด';
                            if (note) note.style.display = 'none';
                            isExpanded = true;
                            return;
                        }

                        // ── Fetch full detail ──
                        if (pendingCards.has(detailUrl)) return;

                        pendingCards.add(detailUrl);
                        button.disabled = true;
                        button.textContent = 'กำลังโหลด…';
                        if (note) note.style.display = 'none';

                        enqueue(async () => {
                            try {
                                const response = await fetch(detailUrl, {
                                    headers: {
                                        'Accept': 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest',
                                    },
                                });

                                if (!response.ok) {
                                    throw new Error('detail-load-failed');
                                }

                                const payload = await response.json();
                                detailCache.set(detailUrl, payload.html || '');
                                target.innerHTML = payload.html || '';
                                button.disabled = false;
                                button.textContent = 'ยุบรายละเอียด';
                                isExpanded = true;
                            } catch (error) {
                                button.disabled = false;
                                button.textContent = 'ลองอีกครั้ง';
                                if (note) {
                                    note.textContent = 'โหลดรายละเอียดไม่สำเร็จ';
                                    note.style.display = '';
                                }
                            } finally {
                                pendingCards.delete(detailUrl);
                            }
                        });
                    });
                });
            });
        })();
    </script>

    <div class="conflict-page">
        <section class="conflict-hero">
            <div>
                <div class="conflict-heading-row">
                    <span class="conflict-kicker">รายการที่ต้องตรวจสอบก่อนส่งอนุมัติ</span>
                </div>
                <div class="conflict-title">การแจ้งเตือนการชน</div>
                <div class="conflict-copy">
                    แสดงรายการชนของทุกรายวิชาที่คุณรับผิดชอบ ระบบจะแสดงข้อมูลสรุปก่อนและโหลดรายละเอียดทั้งหมดเมื่อกดดูเพิ่มเติม
                </div>
                @if($selectedAcademicYear)
                    <div class="conflict-year-badge" data-testid="maker-conflict-year">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        ปีการศึกษา {{ $selectedAcademicYear->name }} / ภาค {{ $selectedAcademicYear->semester }}
                    </div>
                @endif
            </div>
            <div class="conflict-total" data-testid="maker-conflict-total">
                <strong>{{ $totalConflictCount ?? '...' }}</strong>
                <span>รายการชนที่ต้องแก้</span>
            </div>
        </section>

        @if(($asyncConflictReads ?? false) && in_array($conflictRunStatus, ['running', 'failed']))
            <div class="conflict-status" data-testid="maker-conflict-status">
                {{ $conflictStatusLabel }}
            </div>
        @endif

        @if($conflictGroups->isEmpty() && $conflictRunStatus === 'running')
            {{-- background job กำลังทำงานจริง: แสดง subtle placeholder --}}
            <div class="conflict-empty" data-testid="maker-conflict-pending" style="opacity:.6">
                กำลังประมวลผล…
            </div>
        @elseif($conflictGroups->isEmpty())
            {{-- ready / missing / failed แต่ไม่มีข้อมูลชน --}}
            <div class="conflict-empty" data-testid="maker-conflict-empty">
                ยังไม่พบการชนในรายวิชาที่รับผิดชอบ
            </div>
        @else
            @foreach($conflictGroups as $group)
                @php
                    $offering = $group['offering'];
                    $course = $offering->course;
                @endphp
                <section class="conflict-offering" data-testid="maker-conflict-offering">
                    <div class="conflict-offering-head" data-offering-toggle aria-expanded="false"
                         role="button" tabindex="0"
                         onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();this.click()}">
                        <div class="conflict-course">
                            <div class="conflict-course-code">{{ $course?->course_code ?? '-' }}</div>
                            <div class="conflict-course-name">{{ $course?->name_th ?? $course?->name_en ?? 'ไม่ระบุชื่อรายวิชา' }}</div>
                        </div>
                        <span class="conflict-count">{{ $group['conflict_count'] }} รายการชน</span>
                        <svg class="conflict-chevron" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </div>
                    <div class="conflict-list-wrapper">
                    <div class="conflict-list-inner">
                    <div class="conflict-list">
                        @foreach($group['schedules'] as $scheduleEntry)
                            @php
                                $summary = is_array($scheduleEntry) ? $scheduleEntry : null;
                                $schedule = $summary['schedule'] ?? $scheduleEntry;
                                $conflicts = $summary
                                    ? collect($summary['preview_conflicts'] ?? [])
                                    : $conflictMap->get($schedule->id, collect());
                                $hasMoreConflicts = (bool) ($summary['has_more'] ?? false);
                                $conflictCount = (int) ($summary['conflict_count'] ?? $conflicts->count());
                                $detailUrl = $summary
                                    ? route('schedule_conflicts.details', [
                                        $schedule,
                                        'academic_year_id' => $selectedAcademicYearId,
                                    ])
                                    : null;
                                $editUrl = route('maker.course_offerings.schedules.index', [
                                    $offering,
                                    'edit_schedule_id' => $schedule->id,
                                    'focus_schedule_id' => $schedule->id,
                                    'from_conflict' => 1,
                                    'date' => $schedule->start_date?->toDateString(),
                                    'week_start' => $schedule->start_date?->toDateString(),
                                    'period' => 'day',
                                ]);
                            @endphp
                            <article
                                class="conflict-item"
                                data-testid="maker-conflict-item"
                                data-conflict-item
                                @if($detailUrl) data-conflict-detail-url="{{ $detailUrl }}" @endif
                            >
                                <div class="conflict-time">
                                    <div>{{ $formatDate($schedule->start_date) }} @if($schedule->end_date && !$schedule->start_date?->isSameDay($schedule->end_date)) - {{ $formatDate($schedule->end_date) }} @endif</div>
                                    <div>{{ $formatTime($schedule->start_time) }} - {{ $formatTime($schedule->end_time) }}</div>
                                </div>
                                <div>
                                    <div class="conflict-topic">{{ $schedule->topic ?: ($schedule->activityType?->name ?? 'รายการสอน') }}</div>
                                    <div data-conflict-detail-target>
                                        @include('course_head.schedule_conflicts._conflict_sets', [
                                            'conflicts' => $conflicts,
                                            'conflictTypeLabels' => $conflictTypeLabels,
                                        ])
                                    </div>
                                    @if($hasMoreConflicts)
                                        <div class="conflict-detail-actions">
                                            <button type="button" class="conflict-detail-toggle" data-conflict-detail-toggle>
                                                ดูทั้งหมด {{ $conflictCount }} รายการ
                                            </button>
                                            <span class="conflict-detail-note" data-conflict-detail-note>
                                                แสดงตัวอย่าง 3 รายการ
                                            </span>
                                        </div>
                                    @endif
                                    <div class="conflict-messages">
                                        @foreach($conflicts as $conflict)
                                            <div class="conflict-message">
                                                <strong>{{ $conflictTypeLabels[$conflict['type']] ?? 'ตารางชน' }}:</strong>
                                                {{ $conflict['message'] }}
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="conflict-actions">
                                    <a href="{{ $editUrl }}" class="btn btn-secondary" style="text-decoration:none;" data-conflict-edit-link>ไปแก้ไข</a>
                                </div>
                            </article>
                        @endforeach
                    </div>
                    </div>
                    </div>
                </section>
            @endforeach

            @if(($conflictSchedules ?? null) && $conflictSchedules->hasPages())
                <div class="conflict-pagination">
                    {{ $conflictSchedules->links() }}
                </div>
            @endif
        @endif
    </div>
</x-app-layout>
