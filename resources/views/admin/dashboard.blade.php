<x-app-layout title="ภาพรวม — ผู้ดูแลระบบ">
    <div style="padding: 24px 28px;">

        {{-- HERO: title + system status + quick action --}}
        @include('shared.dashboard.admin_hero')

        {{-- ════════════════ SECTION: Data overview ════════════════ --}}
        <div class="dash-section-label">
            <span>ภาพรวมข้อมูลในระบบ</span>
        </div>
        @include('shared.dashboard.admin_stats_strip')

        {{-- ════════════════ SECTION: Readiness + Approval ════════════════ --}}
        <div class="dash-section-label">
            <span>ความพร้อมและสถานะอนุมัติ</span>
        </div>
        <div class="dashboard-2col" style="margin-bottom: 22px;">
            @include('shared.dashboard.master_data_alerts')
            @include('shared.dashboard.offering_pipeline')
        </div>

        {{-- ════════════════ SECTION: Teaching workload ════════════════ --}}
        <div class="dash-section-label">
            <span>ภาระงานสอนของอาจารย์</span>
        </div>
        @include('shared.dashboard.instructors_workload')

        {{-- ════════════════ SECTION: Recent activity + Upcoming schedules ════════════════ --}}
        <div class="dash-section-label">
            <span>ความเคลื่อนไหวล่าสุด</span>
        </div>
        <div class="dashboard-2col">
            @include('shared.dashboard.recent-activity')
            @include('shared.dashboard.upcoming-schedules')
        </div>
    </div>

    <style>
        .dash-section-label {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 6px 0 12px;
            font-family: var(--font-display);
            font-size: 12px;
            font-weight: 700;
            color: var(--fg-3);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .dash-section-label > span {
            position: relative;
            padding-left: 10px;
        }
        .dash-section-label > span::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 14px;
            background: var(--brand-navy);
            border-radius: 1px;
        }
        .dash-section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .dashboard-2col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }
        /* shared/dashboard partials have their own .card with margin-bottom: 24px from app.css.
           Inside the 2-col grid we want them to fill the cell with no extra bottom margin. */
        .dashboard-2col > .card { margin-bottom: 0; }
        @media (max-width: 1100px) {
            .dashboard-2col { grid-template-columns: 1fr; }
            .dashboard-2col > .card { margin-bottom: 0; }
        }
    </style>
</x-app-layout>
