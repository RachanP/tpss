@php
    $formatDate = fn ($date) => $date ? \App\Support\ThaiDate::date($date) : '-';
    $formatTime = fn ($value) => substr((string) $value, 0, 5);
    $conflictTypeLabels = [
        'instructor_overlap' => 'ผู้สอนชน',
        'room_overlap' => 'ห้อง/สถานที่ชน',
        'group_overlap' => 'กลุ่มนักศึกษาชน',
    ];
    $conflictRunStatus = $conflictStatus['status'] ?? 'ready';
    $conflictChecking = (bool) ($conflictChecking ?? in_array($conflictRunStatus, ['missing', 'pending', 'processing'], true));
    $conflictStatusLabel = match ($conflictRunStatus) {
        'failed'  => 'ตรวจสอบรายการชนไม่สำเร็จ ระบบจะแสดงผลล่าสุดที่พร้อมใช้งานถ้ามี',
        'missing', 'pending', 'processing' => 'บันทึกข้อมูลแล้ว ระบบกำลังตรวจสอบรายการชนใหม่',
        'ready'   => 'ผลตรวจสอบพร้อมใช้งาน',
        default   => '',
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
        .conflict-offering {
            border: 1px solid var(--schedule-border);
            border-radius: 10px;
            background: var(--surface);
            box-shadow: 0 1px 3px oklch(0% 0 0 / 0.05);
        }
        .conflict-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            padding: 0 4px;
        }
        .conflict-header-row {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .conflict-title {
            margin: 0;
            font-size: 22px;
            font-weight: 950;
            color: var(--brand-navy);
            line-height: 1.2;
            letter-spacing: 0;
        }
        .conflict-year-inline {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border: 1px solid var(--schedule-border-strong);
            border-radius: 999px;
            background: var(--schedule-soft);
            color: var(--fg-1);
            font-size: 12px;
            font-weight: 850;
            line-height: 1.25;
        }
        .conflict-year-inline svg {
            flex-shrink: 0;
            color: var(--brand-navy);
        }
        .conflict-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
        }
        .conflict-summary-card {
            display: grid;
            grid-template-columns: 44px 1fr;
            align-items: center;
            gap: 14px;
            padding: 16px 18px;
            border: 1px solid var(--schedule-border);
            border-top: 3px solid var(--schedule-border-strong);
            border-radius: 10px;
            background: var(--surface);
            box-shadow: 0 1px 3px oklch(0% 0 0 / 0.04);
            transition: border-color 0.15s ease, transform 0.15s ease;
        }
        .conflict-summary-card:hover {
            transform: translateY(-1px);
        }
        .conflict-summary-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: var(--schedule-soft);
            color: var(--schedule-muted);
            flex-shrink: 0;
        }
        .conflict-summary-body {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 1px;
        }
        .conflict-summary-value {
            font-size: 28px;
            font-weight: 950;
            line-height: 1.05;
            color: var(--brand-navy);
            font-variant-numeric: tabular-nums;
        }
        .conflict-summary-label {
            color: var(--fg-1);
            font-size: 13px;
            font-weight: 900;
            line-height: 1.25;
        }
        .conflict-summary-sub {
            color: var(--schedule-muted);
            font-size: 11.5px;
            font-weight: 700;
            line-height: 1.3;
            margin-top: 1px;
        }
        .conflict-summary-card--total {
            border-top-color: var(--status-conflict-border, oklch(52% 0.16 28));
            background: color-mix(in oklch, var(--status-conflict-bg, oklch(96% 0.04 28)) 40%, var(--surface));
        }
        .conflict-summary-card--total .conflict-summary-icon {
            background: var(--status-conflict-bg, oklch(96% 0.04 28));
            color: var(--status-conflict-fg, oklch(40% 0.14 28));
        }
        .conflict-summary-card--total .conflict-summary-value {
            color: var(--status-conflict-fg, oklch(40% 0.14 28));
        }
        .conflict-summary-card--checking {
            border-top-color: var(--status-warning-border, oklch(72% 0.12 78));
            background: color-mix(in oklch, var(--status-warning-bg, oklch(96% 0.05 78)) 42%, var(--surface));
        }
        .conflict-summary-card--checking .conflict-summary-icon {
            background: var(--status-warning-bg, oklch(96% 0.05 78));
            color: var(--status-warning-fg, oklch(42% 0.11 78));
        }
        .conflict-summary-card--checking .conflict-summary-value {
            color: var(--status-warning-fg, oklch(42% 0.11 78));
        }
        .conflict-summary-value--checking {
            display: inline-flex;
            align-items: center;
            min-height: 30px;
            gap: 4px;
        }
        .conflict-loading-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: currentColor;
            animation: conflict-dot-bounce 1s ease-in-out infinite;
        }
        .conflict-loading-dot:nth-child(2) {
            animation-delay: 0.14s;
        }
        .conflict-loading-dot:nth-child(3) {
            animation-delay: 0.28s;
        }
        @keyframes conflict-dot-bounce {
            0%, 80%, 100% {
                transform: translateY(0);
                opacity: .45;
            }
            40% {
                transform: translateY(-5px);
                opacity: 1;
            }
        }
        .conflict-summary-card--instructor { border-top-color: oklch(48% 0.14 268); }
        .conflict-summary-card--instructor .conflict-summary-icon { background: oklch(96% 0.03 268); color: oklch(40% 0.14 268); }
        .conflict-summary-card--room { border-top-color: oklch(52% 0.16 28); }
        .conflict-summary-card--room .conflict-summary-icon { background: oklch(96% 0.04 28); color: oklch(48% 0.16 28); }
        .conflict-summary-card--group { border-top-color: oklch(48% 0.14 168); }
        .conflict-summary-card--group .conflict-summary-icon { background: oklch(95% 0.04 168); color: oklch(38% 0.14 168); }
        .conflict-status {
            border: 1px solid var(--status-warning-border, var(--schedule-border-strong));
            border-radius: 8px;
            background: var(--status-warning-bg, var(--schedule-soft));
            color: var(--status-warning-fg, var(--fg-1));
            padding: 10px 12px;
            font-size: 13px;
            font-weight: 750;
        }
        .conflict-status--checking {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .conflict-status-pulse {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: var(--status-warning-fg, oklch(42% 0.11 78));
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
        .conflict-empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            padding: 36px 24px;
            border: 1px solid var(--schedule-border);
            border-radius: 12px;
            background: var(--surface);
            text-align: center;
        }
        .conflict-empty-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: var(--schedule-soft);
            color: var(--schedule-muted);
            margin-bottom: 2px;
        }
        .conflict-empty-title {
            font-size: 17px;
            font-weight: 950;
            color: var(--brand-navy);
            line-height: 1.3;
        }
        .conflict-empty-sub {
            max-width: 560px;
            color: var(--fg-2);
            font-size: 13px;
            font-weight: 700;
            line-height: 1.55;
        }
        .conflict-empty-state--success {
            border-color: color-mix(in oklch, var(--status-success-border, oklch(70% 0.14 145)) 60%, var(--schedule-border));
            background: color-mix(in oklch, var(--status-success-bg, oklch(96% 0.04 145)) 45%, var(--surface));
        }
        .conflict-empty-state--success .conflict-empty-icon {
            background: var(--status-success-bg, oklch(94% 0.04 145));
            color: var(--status-success-fg, oklch(38% 0.14 145));
        }
        .conflict-empty-state--success .conflict-empty-title {
            color: var(--status-success-fg, oklch(38% 0.14 145));
        }
        .conflict-empty-state--info .conflict-empty-icon {
            background: oklch(94% 0.03 232);
            color: oklch(38% 0.12 232);
        }
        .conflict-pagination {
            display: flex;
            justify-content: center;
            padding: 6px 0 2px;
        }
        @media (max-width: 760px) {
            .conflict-item {
                grid-template-columns: 1fr;
            }
            .conflict-title {
                font-size: 20px;
            }
            .conflict-summary-card {
                padding: 14px;
            }
            .conflict-summary-value {
                font-size: 24px;
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

                @if($conflictChecking)
                    const conflictBadgeEndpoint = @json(route('maker.conflict_badge_status'));
                    let conflictPollTimer = null;
                    let conflictPollInflight = false;

                    const stopConflictPagePolling = () => {
                        if (conflictPollTimer) {
                            window.clearInterval(conflictPollTimer);
                            conflictPollTimer = null;
                        }
                    };

                    const pollConflictReady = async () => {
                        if (conflictPollInflight || document.hidden) {
                            return;
                        }

                        conflictPollInflight = true;

                        try {
                            const response = await fetch(conflictBadgeEndpoint, {
                                headers: { Accept: 'application/json' },
                                credentials: 'same-origin',
                            });

                            if ([401, 403, 429].includes(response.status)) {
                                stopConflictPagePolling();
                                return;
                            }

                            if (!response.ok) {
                                return;
                            }

                            const payload = await response.json();

                            if (payload.status === 'ready' || payload.poll === false) {
                                stopConflictPagePolling();
                                window.location.reload();
                            }
                        } catch (error) {
                            // Keep the conflict page quiet on transient polling errors.
                        } finally {
                            conflictPollInflight = false;
                        }
                    };

                    const startConflictPagePolling = () => {
                        if (conflictPollTimer || document.hidden) {
                            return;
                        }

                        conflictPollTimer = window.setInterval(pollConflictReady, 8000);
                    };

                    document.addEventListener('visibilitychange', () => {
                        if (document.hidden) {
                            stopConflictPagePolling();
                            return;
                        }

                        startConflictPagePolling();
                    });

                    startConflictPagePolling();
                @endif

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

    @php
        $typeCounts = $conflictTypeCounts ?? [];
        $summaryCards = [
            [
                'key' => 'instructor_overlap',
                'label' => 'ผู้สอนชน',
                'sub' => 'อาจารย์มีตารางซ้อน',
                'modifier' => 'instructor',
                'icon' => 'user',
            ],
            [
                'key' => 'room_overlap',
                'label' => 'ห้อง/สถานที่ชน',
                'sub' => 'ห้องถูกใช้ซ้ำเวลาเดียวกัน',
                'modifier' => 'room',
                'icon' => 'home',
            ],
            [
                'key' => 'group_overlap',
                'label' => 'กลุ่มนักศึกษาชน',
                'sub' => 'นักศึกษามีตารางซ้อน',
                'modifier' => 'group',
                'icon' => 'users',
            ],
        ];
    @endphp
    <div class="conflict-page">
        <section class="conflict-header">
            <div class="conflict-header-row">
                <h1 class="conflict-title">การแจ้งเตือนการชน</h1>
                @if($selectedAcademicYear)
                    <span class="conflict-year-inline" data-testid="maker-conflict-year">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        ปีการศึกษา {{ $selectedAcademicYear->name }}
                    </span>
                @endif
            </div>
        </section>

        <section class="conflict-summary-grid" data-testid="maker-conflict-summary">
            <div class="conflict-summary-card {{ $conflictChecking ? 'conflict-summary-card--checking' : 'conflict-summary-card--total' }}" data-testid="maker-conflict-total">
                <div class="conflict-summary-icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                </div>
                <div class="conflict-summary-body">
                    @if($conflictChecking)
                        <div class="conflict-summary-value conflict-summary-value--checking" aria-label="กำลังตรวจสอบ">
                            <span class="conflict-loading-dot"></span>
                            <span class="conflict-loading-dot"></span>
                            <span class="conflict-loading-dot"></span>
                        </div>
                        <div class="conflict-summary-label">กำลังตรวจสอบรายการชน</div>
                        <div class="conflict-summary-sub">ระบบกำลังคำนวณผลล่าสุด กรุณารอสักครู่</div>
                    @else
                        <div class="conflict-summary-value">{{ $totalConflictCount ?? '…' }}</div>
                        <div class="conflict-summary-label">รายการชนทั้งหมด</div>
                        <div class="conflict-summary-sub">ต้องแก้ไขให้หมดก่อนส่งอนุมัติ</div>
                    @endif
                </div>
            </div>
            @foreach($summaryCards as $card)
                <div class="conflict-summary-card conflict-summary-card--{{ $card['modifier'] }}">
                    <div class="conflict-summary-icon" aria-hidden="true">
                        @switch($card['icon'])
                            @case('user')
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                @break
                            @case('home')
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                                @break
                            @case('users')
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-3-3.87"></path><path d="M9 7a4 4 0 1 0 0 8 4 4 0 0 0 0-8z"></path><path d="M1 21v-2a4 4 0 0 1 3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                @break
                        @endswitch
                    </div>
                    <div class="conflict-summary-body">
                        @if($conflictChecking)
                            <div class="conflict-summary-value conflict-summary-value--checking" aria-label="กำลังตรวจสอบ">
                                <span class="conflict-loading-dot"></span>
                                <span class="conflict-loading-dot"></span>
                                <span class="conflict-loading-dot"></span>
                            </div>
                        @else
                            <div class="conflict-summary-value">{{ $typeCounts[$card['key']] ?? 0 }}</div>
                        @endif
                        <div class="conflict-summary-label">{{ $card['label'] }}</div>
                        <div class="conflict-summary-sub">{{ $card['sub'] }}</div>
                    </div>
                </div>
            @endforeach
        </section>

        @if(($asyncConflictReads ?? false) && ($conflictChecking || $conflictRunStatus === 'failed'))
            <div class="conflict-status {{ $conflictChecking ? 'conflict-status--checking' : '' }}" data-testid="maker-conflict-status">
                @if($conflictChecking)
                    <span class="conflict-status-pulse" aria-hidden="true">
                        <span class="conflict-loading-dot"></span>
                        <span class="conflict-loading-dot"></span>
                        <span class="conflict-loading-dot"></span>
                    </span>
                @endif
                {{ $conflictStatusLabel }}
            </div>
        @endif

        @if($conflictGroups->isEmpty() && $conflictChecking)
            {{-- background job กำลังทำงานจริง: แสดง subtle placeholder --}}
            <div class="conflict-empty" data-testid="maker-conflict-pending" style="opacity:.6">
                กำลังตรวจสอบรายการชน…
            </div>
        @elseif($conflictGroups->isEmpty())
            @php
                $emptyKey = $conflictEmptyStateKey ?? 'no_conflicts';
                $emptyStates = [
                    'preparation' => [
                        'icon' => 'clock',
                        'title' => 'อยู่ในสถานะเตรียมข้อมูล',
                        'sub' => 'ยังไม่ถึงช่วงเวลาการจัดตารางเรียน ระบบจะเริ่มตรวจสอบการชนเมื่อผู้ดูแลเปิดช่วงจัดตาราง',
                        'tone' => 'info',
                    ],
                    'no_offerings' => [
                        'icon' => 'inbox',
                        'title' => 'ไม่พบรายวิชาที่ต้องจัดตารางสอนในระบบ',
                        'sub' => 'ช่วงจัดตารางเปิดอยู่ แต่คุณยังไม่ได้รับมอบหมายเป็นหัวหน้าวิชาในรอบนี้ ติดต่อผู้ดูแลระบบหากต้องการรับผิดชอบรายวิชา',
                        'tone' => 'info',
                    ],
                    'no_conflicts' => [
                        'icon' => 'check',
                        'title' => 'ยังไม่พบการชนในรายวิชาที่รับผิดชอบ',
                        'sub' => 'ตารางสอนของคุณยังไม่มีรายการที่ชนกัน — พร้อมส่งขออนุมัติได้',
                        'tone' => 'success',
                    ],
                ];
                $state = $emptyStates[$emptyKey] ?? $emptyStates['no_conflicts'];
            @endphp
            <div class="conflict-empty-state conflict-empty-state--{{ $state['tone'] }}" data-testid="maker-conflict-empty" data-empty-state="{{ $emptyKey }}">
                <div class="conflict-empty-icon" aria-hidden="true">
                    @switch($state['icon'])
                        @case('clock')
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            @break
                        @case('inbox')
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"></polyline><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"></path></svg>
                            @break
                        @case('check')
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            @break
                    @endswitch
                </div>
                <div class="conflict-empty-title">{{ $state['title'] }}</div>
                <div class="conflict-empty-sub">{{ $state['sub'] }}</div>
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
                                $distinctCount = (int) (
                                    $summary['distinct_conflict_count']
                                    ?? $conflicts->pluck('schedule_id')->unique()->count()
                                );
                                $previewDistinctCount = (int) (
                                    $summary['preview_distinct_count']
                                    ?? $conflicts->pluck('schedule_id')->unique()->count()
                                );
                                $hasMoreConflicts = (bool) ($summary['has_more'] ?? ($distinctCount > $previewDistinctCount));
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
                                                ดูทั้งหมด {{ $distinctCount }} รายการ
                                            </button>
                                            <span class="conflict-detail-note" data-conflict-detail-note>
                                                แสดงตัวอย่าง {{ $previewDistinctCount }} จาก {{ $distinctCount }} รายการ
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
