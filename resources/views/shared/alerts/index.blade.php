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
            width: 100%;
            max-width: 1440px;
            margin: 0 auto;
            padding: 28px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            background:
                radial-gradient(circle at 8% 0%, color-mix(in oklch, var(--brand-navy) 10%, transparent), transparent 30%),
                linear-gradient(180deg,
                    color-mix(in oklch, var(--brand-navy) 7%, var(--bg)) 0%,
                    color-mix(in oklch, var(--brand-navy) 4%, var(--bg)) 34%,
                    var(--bg) 100%);
        }
        .alert-hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 18px 20px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 36%, var(--border));
            border-radius: var(--r-lg);
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 6%, var(--surface)), var(--surface) 64%),
                var(--surface);
            box-shadow:
                0 1px 2px rgba(0, 36, 84, 0.08),
                0 16px 34px -28px rgba(0, 36, 84, 0.34),
                inset 0 1px 0 rgba(255, 255, 255, 0.74);
        }
        .alert-kicker {
            width: fit-content;
            margin-bottom: 6px;
            padding: 3px 8px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 24%, var(--border));
            border-radius: 999px;
            background: color-mix(in oklch, var(--brand-navy) 6%, var(--surface));
            color: color-mix(in oklch, var(--brand-navy) 84%, var(--fg-2));
            font-size: 11.5px;
            font-weight: 900;
        }
        .alert-hero-sub {
            margin: 6px 0 0;
            max-width: 78ch;
            color: var(--fg-3);
            font-size: 12.5px;
            font-weight: 700;
            line-height: 1.6;
        }
        .alert-title {
            margin: 0;
            font-family: var(--font-display);
            font-size: 24px;
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
            gap: 10px;
        }
        .alert-summary-total-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 30%, var(--border));
            border-radius: var(--r-lg);
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 5%, var(--surface)), var(--surface) 80%),
                var(--surface);
            box-shadow:
                0 1px 2px rgba(0, 36, 84, 0.07),
                0 14px 30px -26px rgba(0, 36, 84, 0.32),
                inset 0 1px 0 rgba(255, 255, 255, 0.74);
            transition: border-color 160ms ease, box-shadow 160ms ease;
        }
        .alert-summary-total-card:hover {
            border-color: color-mix(in oklch, var(--brand-navy) 42%, var(--border));
            box-shadow:
                0 1px 3px rgba(0, 36, 84, 0.09),
                0 18px 36px -28px rgba(0, 36, 84, 0.38),
                inset 0 1px 0 rgba(255, 255, 255, 0.82);
        }
        .alert-summary-grid {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            padding: 8px 10px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 20%, var(--border));
            border-radius: var(--r-lg);
            background: color-mix(in oklch, var(--brand-navy) 3%, var(--surface));
        }
        .alert-summary-card {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 38px;
            padding: 6px 10px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 22%, var(--border));
            border-radius: 999px;
            background: var(--surface);
            box-shadow: 0 1px 2px rgba(0, 36, 84, 0.05);
            transition: border-color 160ms ease, box-shadow 160ms ease, background 160ms ease;
        }
        .alert-summary-card:hover {
            border-color: color-mix(in oklch, var(--brand-navy) 40%, var(--border));
            background: color-mix(in oklch, var(--brand-navy) 4%, var(--surface));
            box-shadow: 0 8px 18px -16px rgba(0, 36, 84, 0.32);
        }
        .alert-summary-icon {
            display: inline-flex; align-items: center; justify-content: center;
            width: 28px; height: 28px; border-radius: 9px; flex-shrink: 0;
            box-shadow: 0 1px 2px rgba(0, 36, 84, 0.08);
        }
        .alert-summary-card > div:last-child {
            display: inline-flex;
            align-items: baseline;
            gap: 6px;
            min-width: 0;
        }
        .alert-summary-value { font-size: 18px; font-weight: 950; line-height: 1.05; color: var(--brand-navy); font-variant-numeric: tabular-nums; }
        .alert-summary-label { color: var(--fg-1); font-size: 12px; font-weight: 900; }
        .alert-summary-sub { color: var(--alert-muted); font-size: 12px; font-weight: 700; margin-top: 2px; line-height: 1.45; }
        .alert-group {
            border: 1px solid color-mix(in oklch, var(--brand-navy) 34%, var(--border));
            border-radius: var(--r-lg);
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 7%, var(--surface)), var(--surface) 44%),
                var(--surface);
            overflow: hidden;
            box-shadow:
                0 1px 2px rgba(0, 36, 84, 0.08),
                0 24px 52px -38px rgba(0, 36, 84, 0.42),
                inset 0 1px 0 rgba(255, 255, 255, 0.74);
            transition: border-color 160ms ease, box-shadow 160ms ease, transform 160ms ease;
        }
        .alert-group:hover {
            transform: translateY(-1px);
            border-color: color-mix(in oklch, var(--brand-navy) 50%, var(--border));
            box-shadow:
                0 2px 5px rgba(0, 36, 84, 0.1),
                0 28px 58px -40px rgba(0, 36, 84, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.78);
        }
        .alert-group-head {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 76px;
            padding: 14px 18px;
            border-bottom: 1px solid color-mix(in oklch, var(--brand-navy) 24%, var(--border));
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 10%, var(--surface)), color-mix(in oklch, var(--brand-navy) 4%, var(--surface)));
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
            padding: 15px 18px;
            border-bottom: 1px solid color-mix(in oklch, var(--brand-navy) 16%, var(--alert-border));
            align-items: start;
            transition: background 0.12s;
        }
        .alert-item:last-child { border-bottom: none; }
        .alert-item:hover { background: color-mix(in oklch, var(--brand-navy) 4%, var(--surface)); }
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
            border: 1px solid color-mix(in oklch, var(--brand-navy) 34%, var(--alert-border));
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--surface) 92%, white), color-mix(in oklch, var(--brand-navy) 5%, var(--surface)));
            color: var(--brand-navy);
            box-shadow: 0 1px 2px rgba(0, 36, 84, 0.06);
            transition: all 0.15s ease;
            text-decoration: none;
            cursor: pointer;
        }
        .alert-item-action .btn:hover {
            background-color: color-mix(in oklch, var(--brand-navy) 6%, var(--surface));
            border-color: color-mix(in oklch, var(--brand-navy) 58%, var(--alert-border));
            color: var(--brand-navy);
            transform: translateY(-1px);
            box-shadow: 0 9px 20px -16px rgba(0, 36, 84, 0.42);
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
            background: color-mix(in oklch, var(--brand-navy) 4%, var(--surface));
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
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--surface) 92%, white), color-mix(in oklch, var(--brand-navy) 5%, var(--surface)));
            color: var(--brand-navy);
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0, 36, 84, 0.06);
            transition: border-color 160ms ease, box-shadow 160ms ease, transform 160ms ease;
        }
        .alert-year-select select:hover,
        .alert-year-select select:focus-visible {
            border-color: color-mix(in oklch, var(--brand-navy) 56%, var(--alert-border));
            outline: none;
            transform: translateY(-1px);
            box-shadow: 0 10px 22px -18px rgba(0, 36, 84, 0.42);
        }
        @media (max-width: 640px) {
            .alert-page {
                padding: 14px;
            }
            .alert-hero {
                flex-direction: column;
                padding: 18px;
            }
            .alert-year-select {
                width: 100%;
                justify-content: space-between;
            }
            .alert-year-select select {
                flex: 1;
                min-width: 0;
            }
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
                display: flex;
                gap: 8px;
            }
            .alert-summary-card {
                padding: 6px 9px;
                gap: 8px;
            }
            .alert-summary-value {
                font-size: 17px;
            }
            .alert-summary-label {
                font-size: 11.5px;
            }
            .alert-summary-sub {
                font-size: 11.5px;
            }
            .alert-summary-total-card {
                padding: 14px 16px;
                gap: 12px;
            }
        }
        @media (max-width: 480px) {
            .alert-summary-grid {
                align-items: stretch;
            }
            .alert-summary-card {
                flex: 1 1 calc(50% - 8px);
            }
            .alert-summary-total-card {
                text-align: left;
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
    .alert-hero {
        display: grid !important;
        grid-template-columns: minmax(0, 1fr) minmax(240px, auto) !important;
        align-items: center !important;
        gap: 22px !important;
        padding: 18px 22px !important;
    }

    .alert-hero > div:first-child {
        min-width: 0;
    }

    .alert-hero > form.alert-year-select {
        justify-self: end;
    }

    .alert-year-select {
        width: 100%;
        max-width: 360px;
        padding: 10px !important;
        border: 1px solid rgba(15, 79, 128, 0.22) !important;
        border-radius: 10px !important;
        background: linear-gradient(180deg, #ffffff, #f4f8fb) !important;
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.85), 0 8px 18px rgba(0, 44, 91, 0.08);
    }

    .alert-year-select label {
        white-space: nowrap;
    }

    .alert-year-select select {
        min-width: 0 !important;
        flex: 1 1 auto;
    }

    .alert-summary-container {
        display: grid !important;
        grid-template-columns: minmax(280px, 0.9fr) minmax(0, 1.35fr);
        align-items: stretch;
        gap: 12px;
    }

    .alert-summary-total-card {
        min-height: 88px;
        height: 100%;
    }

    .alert-summary-grid {
        align-content: stretch;
        height: 100%;
    }

    .alert-summary-card {
        flex-basis: 190px;
    }

    @media (max-width: 980px) {
        .alert-hero {
            grid-template-columns: 1fr !important;
            align-items: start !important;
        }

        .alert-hero > form.alert-year-select {
            justify-self: stretch;
        }

        .alert-year-select {
            max-width: none;
        }

        .alert-summary-container {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 640px) {
        .alert-hero {
            padding: 16px !important;
            gap: 14px !important;
        }

        .alert-year-select {
            flex-wrap: wrap;
        }

        .alert-year-select label,
        .alert-year-select select {
            width: 100%;
        }

        .alert-summary-total-card {
            align-items: flex-start;
            flex-wrap: wrap;
        }

        .alert-summary-card {
            flex: 1 1 calc(50% - 8px);
            min-width: 150px;
        }
    }
    .alert-summary-container {
        padding: 14px;
        border: 1px solid rgba(15, 79, 128, 0.34);
        border-radius: 10px;
        background:
            linear-gradient(135deg, rgba(255,255,255,0.98), rgba(244,248,251,0.94)),
            linear-gradient(180deg, rgba(0,44,91,0.05), rgba(255,255,255,0));
        box-shadow: 0 12px 28px rgba(0, 44, 91, 0.10);
    }

    .alert-summary-total-card {
        border-color: rgba(15, 79, 128, 0.18) !important;
        background: linear-gradient(135deg, #ffffff, #f7fafc) !important;
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.9) !important;
        transition: transform 0.16s ease, border-color 0.16s ease, box-shadow 0.16s ease;
    }

    .alert-summary-grid {
        padding-left: 2px;
    }

    .alert-summary-card {
        border-color: rgba(15, 79, 128, 0.24) !important;
        background: linear-gradient(180deg, #ffffff, #f6f9fb) !important;
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.9), 0 6px 14px rgba(0, 44, 91, 0.06) !important;
        transition: transform 0.16s ease, border-color 0.16s ease, box-shadow 0.16s ease;
    }

    .alert-summary-card:hover,
    .alert-summary-total-card:hover {
        transform: translateY(-1px);
        border-color: rgba(0, 44, 91, 0.42) !important;
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.9), 0 10px 22px rgba(0, 44, 91, 0.10) !important;
    }

    @media (max-width: 980px) {
        .alert-summary-container {
            padding: 12px;
        }

        .alert-summary-grid {
            padding-left: 0;
        }
    }

    @media (max-width: 640px) {
        .alert-summary-container {
            gap: 10px;
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
        <section class="alert-hero">
            <div>
                <div class="alert-kicker">ตรวจรายการตารางสอน</div>
                <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                    <h1 class="alert-title">การแจ้งเตือน</h1>
                    @if($selectedAcademicYear && $availableYears->count() <= 1)
                        <span class="alert-year-inline">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                            ปีการศึกษา {{ $selectedAcademicYear->name }}
                        </span>
                    @endif
                </div>
                <p class="alert-hero-sub">ตรวจรายการชนกัน ข้อมูลไม่ครบ และเงื่อนไขที่ควรแก้ก่อนส่งตารางสอนเพื่ออนุมัติ</p>
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
                <div class="alert-summary-total-card">
                    <div class="alert-summary-icon" style="background:{{ $totBg }}; color:{{ $totFg }}; width: 36px; height: 36px;">
                        @if($hasWarn)
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        @endif
                    </div>
                    <div>
                        <div style="display: flex; align-items: baseline; gap: 8px;">
                            <span class="alert-summary-value" style="color:{{ $totFg }}; font-size: 24px;">{{ $totalWarningCount }}</span>
                            <span class="alert-summary-label" style="font-size: 13.5px; font-weight: 900;">รายการแจ้งเตือนทั้งหมด</span>
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
                        <div class="alert-summary-card" style="opacity: {{ $isZero ? '0.62' : '1' }}; transition: opacity 0.15s ease;">
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
