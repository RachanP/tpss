<x-app-layout title="ภาพรวม — ผู้ดูแลระบบ">
    <div class="admin-dashboard"
         x-data="{ dashboardReady: (window.performance?.getEntriesByType?.('navigation')?.[0]?.type === 'back_forward') || sessionStorage.getItem('adminDashboardRestorePending') === '1' }"
         x-init="if (!dashboardReady) setTimeout(() => dashboardReady = true, 220)">
        <div class="admin-dashboard-skeleton" x-show="!dashboardReady">
            <div class="dash-skel-card dash-skel-hero">
                <span></span><span></span><span></span>
            </div>
            <div class="dash-skel-grid">
                <div class="dash-skel-card"></div>
                <div class="dash-skel-card"></div>
            </div>
            <div class="dash-skel-strip">
                <span></span><span></span><span></span><span></span>
            </div>
            <div class="dash-skel-grid">
                <div class="dash-skel-card"></div>
                <div class="dash-skel-card"></div>
            </div>
        </div>

        <div class="admin-dashboard-content" x-show="dashboardReady" x-cloak>

        {{-- HERO: title + system status + quick action --}}
        @include('shared.dashboard.admin_hero')

        <section class="admin-section">
            <div class="admin-action-grid">
                @include('shared.dashboard.master_data_alerts')
                @include('shared.dashboard.offering_pipeline')
            </div>
        </section>

        <section class="admin-section">
            @include('shared.dashboard.admin_stats_strip')
        </section>

        {{-- Visual overview (V3 8.1 — กราฟสรุปสถานะ) --}}
        @include('shared.dashboard.admin_visual_overview')

        <section class="admin-section">
            @include('shared.dashboard.instructors_workload', ['workloadPageSize' => 5])
        </section>

        <section class="admin-section">
            <div class="admin-secondary-grid">
                @include('shared.dashboard.recent-activity')
                @include('shared.dashboard.upcoming-schedules')
            </div>
        </section>
        </div>
    </div>

    <script>
        (() => {
            const scrollKey = 'adminDashboardScrollY';
            const pendingKey = 'adminDashboardRestorePending';
            const dashboard = () => document.querySelector('.admin-dashboard');

            window.addEventListener('pagehide', () => {
                if (!dashboard()) return;

                sessionStorage.setItem(scrollKey, String(window.scrollY));
                sessionStorage.setItem(pendingKey, '1');
            });

            const restoreDashboardScroll = () => {
                if (!dashboard() || sessionStorage.getItem(pendingKey) !== '1') return;

                const scrollY = Number(sessionStorage.getItem(scrollKey) || 0);
                sessionStorage.removeItem(pendingKey);

                if (scrollY <= 0) return;

                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        window.scrollTo({ top: scrollY, behavior: 'auto' });
                    });
                });
            };

            window.addEventListener('pageshow', restoreDashboardScroll);
            window.addEventListener('load', () => setTimeout(restoreDashboardScroll, 260));
            document.addEventListener('alpine:initialized', () => setTimeout(restoreDashboardScroll, 0));
        })();
    </script>

    <style>
        .admin-dashboard {
            width: 100%;
            max-width: 100%;
            padding: clamp(14px, 2vw, 28px) clamp(14px, 2vw, 28px) clamp(22px, 2.4vw, 32px);
            min-width: 0;
            overflow-x: hidden;
        }

        .admin-dashboard-content,
        .admin-dashboard-skeleton {
            display: flex;
            flex-direction: column;
            gap: clamp(18px, 1.8vw, 24px);
            min-width: 0;
        }

        .admin-dashboard-skeleton {
            pointer-events: none;
        }

        .dash-skel-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: clamp(12px, 1.4vw, 18px);
        }

        .dash-skel-card,
        .dash-skel-strip {
            border: 1px solid color-mix(in oklch, var(--brand-navy) 12%, var(--border));
            border-radius: var(--r-lg);
            background:
                linear-gradient(90deg,
                    color-mix(in oklch, var(--bg-2) 82%, var(--surface)) 0%,
                    color-mix(in oklch, var(--brand-navy) 5%, var(--surface)) 42%,
                    color-mix(in oklch, var(--bg-2) 82%, var(--surface)) 82%);
            background-size: 220% 100%;
            animation: dashboardSkeleton 1150ms ease-in-out infinite;
            box-shadow:
                0 1px 2px rgba(0, 36, 84, 0.05),
                0 14px 30px -24px rgba(0, 36, 84, 0.24);
        }

        .dash-skel-card {
            min-height: 180px;
        }

        .dash-skel-hero {
            min-height: 260px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .dash-skel-hero span {
            display: block;
            height: 18px;
            max-width: 460px;
            border-radius: 999px;
            background: color-mix(in oklch, var(--brand-navy) 9%, var(--surface));
        }

        .dash-skel-hero span:first-child {
            width: 32%;
            height: 28px;
        }

        .dash-skel-hero span:nth-child(2) {
            width: 58%;
        }

        .dash-skel-hero span:nth-child(3) {
            width: 78%;
            margin-top: auto;
        }

        .dash-skel-strip {
            min-height: 162px;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1px;
            overflow: hidden;
        }

        .dash-skel-strip span {
            background: color-mix(in oklch, var(--surface) 72%, transparent);
            border-right: 1px solid color-mix(in oklch, var(--brand-navy) 10%, var(--border));
        }

        .dash-skel-strip span:last-child {
            border-right: 0;
        }

        @keyframes dashboardSkeleton {
            0% { background-position: 120% 0; }
            100% { background-position: -120% 0; }
        }

        .admin-section {
            display: flex;
            flex-direction: column;
            gap: 12px;
            min-width: 0;
        }

        .admin-action-grid,
        .admin-secondary-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: clamp(12px, 1.4vw, 18px);
            align-items: stretch;
            min-width: 0;
        }

        /* action-grid: การ์ดซ้าย/ขวาสูงเท่ากัน และให้ body เติมพื้นที่ใต้หัวการ์ด */
        .admin-action-grid > .card {
            display: flex;
            flex-direction: column;
        }
        .admin-action-grid > .card > .card-hdr {
            flex: 0 0 auto;
            min-height: 84px;
            border-bottom: 1px solid var(--border);
        }

        .admin-action-grid > .card > :not(.card-hdr) {
            flex: 1 1 auto;
        }

        /* ---- Depth / elevation — ให้การ์ดมีมิติ ไม่แบน (ตาม feedback) ---- */
        .admin-dashboard .card,
        .admin-dashboard .dash-chart-card,
        .admin-dashboard .admin-stats-strip {
            box-shadow:
                0 1px 2px rgba(0, 36, 84, 0.05),
                0 8px 22px -12px rgba(0, 36, 84, 0.16);
        }

        .admin-secondary-grid > .card {
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .admin-secondary-grid > .card > .card-hdr {
            flex: 0 0 70px;
            min-height: 70px;
            box-sizing: border-box;
        }

        .admin-secondary-grid > .card > :not(.card-hdr) {
            flex: 1 1 auto;
        }

        .admin-dashboard .card {
            margin-bottom: 0;
            border-color: var(--border);
            min-width: 0;
        }

        .admin-dashboard .card-hdr {
            min-height: 54px;
            flex-wrap: wrap;
            gap: 10px 14px;
            min-width: 0;
        }

        .admin-dashboard .card-ttl {
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .admin-dashboard .card-actions {
            min-width: 0;
            flex-wrap: wrap;
        }

        .admin-dashboard .table-responsive {
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
        }

        .admin-dashboard .pill {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 4px 9px;
            border-radius: var(--r-pill);
            border: 1px solid var(--border);
            background: var(--bg-2);
            color: var(--fg-2);
            font-size: 11px;
            font-weight: 800;
            line-height: 1;
            text-align: center;
            white-space: normal;
        }

        .admin-dashboard .pill.p-conflict {
            border-color: var(--status-conflict-border);
            background: var(--status-conflict-bg);
            color: var(--status-conflict-fg);
        }

        .admin-dashboard .pill.p-warning {
            border-color: var(--status-warning-border);
            background: var(--status-warning-bg);
            color: var(--status-warning-fg);
        }

        .admin-dashboard .pill.p-success {
            border-color: var(--status-success-border);
            background: var(--status-success-bg);
            color: var(--status-success-fg);
        }

        .admin-dashboard .pill.p-info {
            border-color: var(--status-info-border);
            background: var(--status-info-bg);
            color: var(--status-info-fg);
        }

        .admin-dashboard .pill.badge-gray {
            border-color: var(--border);
            background: var(--bg-2);
            color: var(--fg-2);
        }

        /* ---- Pill variants for dashboard widgets (mirror .audit-page definitions) ---- */
        .admin-dashboard .pill.p-primary {
            border-color: color-mix(in oklch, var(--brand-navy) 25%, var(--border));
            background: color-mix(in oklch, var(--brand-navy) 8%, var(--surface));
            color: var(--brand-navy);
        }

        .admin-dashboard .pill.p-neutral {
            border-color: var(--border);
            background: var(--bg-2);
            color: var(--fg-2);
        }

        .admin-dashboard .pill.p-gold {
            border-color: var(--status-warning-border);
            background: var(--status-warning-bg);
            color: var(--status-warning-fg);
        }

        .admin-dashboard .pill.p-teal {
            border-color: var(--status-info-border);
            background: var(--status-info-bg);
            color: var(--status-info-fg);
        }

        .admin-dashboard .pill.p-purple {
            background: color-mix(in oklch, var(--brand-navy) 8%, var(--surface));
            border-color: color-mix(in oklch, var(--brand-navy) 22%, var(--border));
            color: var(--brand-navy);
        }

        /* ดูทั้งหมด — สีดำ/เทา แทนสี brand */
        .admin-dashboard .ra-view-all {
            color: var(--fg-1);
            background: transparent;
            border-color: var(--border);
        }

        .admin-dashboard .ra-view-all:hover {
            background: var(--bg-2);
            color: var(--fg-1);
        }

        .admin-dashboard [data-testid="admin-stats-strip"] {
            margin-bottom: 0 !important;
        }

        @media (max-width: 1280px) {
            .admin-dashboard {
                padding-inline: 18px;
            }

        }

        @media (max-width: 1100px) {
            .dash-skel-grid {
                grid-template-columns: 1fr;
            }

            .admin-action-grid,
            .admin-secondary-grid {
                grid-template-columns: 1fr;
            }

            .admin-secondary-grid > .card {
                height: auto;
            }

            .admin-secondary-grid > .card > .card-hdr {
                flex-basis: auto;
                min-height: 54px;
            }
        }

        @media (max-width: 900px) {
            .admin-dashboard {
                padding-inline: 16px;
            }

        }

        @media (max-width: 720px) {
            .admin-dashboard {
                padding: 16px;
                gap: 22px;
            }

            .admin-dashboard-content,
            .admin-dashboard-skeleton {
                gap: 22px;
            }

            .dash-skel-hero {
                min-height: 220px;
                padding: 18px;
            }

            .dash-skel-hero span:first-child,
            .dash-skel-hero span:nth-child(2),
            .dash-skel-hero span:nth-child(3) {
                width: 100%;
            }

            .dash-skel-strip {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .dash-skel-strip span:nth-child(2) {
                border-right: 0;
            }

            .admin-dashboard .card-hdr {
                align-items: flex-start;
            }

            .admin-dashboard .card-actions,
            .admin-dashboard .search-box {
                width: 100%;
            }
        }

        @media (max-width: 540px) {
            .admin-dashboard {
                padding: 12px;
            }
        }
    </style>
</x-app-layout>
