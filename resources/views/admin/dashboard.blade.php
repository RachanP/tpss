<x-app-layout title="ภาพรวม — ผู้ดูแลระบบ">
    <div class="admin-dashboard">

        {{-- HERO: title + system status + quick action --}}
        @include('shared.dashboard.admin_hero')

        <section class="admin-section">
            <div class="admin-section-head">
                <div>
                    <h2>ต้องจัดการก่อนเปิดระบบ</h2>
                    <p>เริ่มจากรายการสีแดงและสีเหลืองก่อน เพื่อให้เปิดช่วงจัดตารางได้</p>
                </div>
            </div>
            <div class="admin-action-grid">
                @include('shared.dashboard.master_data_alerts')
                @include('shared.dashboard.offering_pipeline')
            </div>
        </section>

        <section class="admin-section">
            <div class="admin-section-head">
                <div>
                    <h2>ข้อมูลพื้นฐานสำหรับจัดตาราง</h2>
                    <p>ดูจำนวนข้อมูลหลักที่จำเป็นต่อการจัดตาราง เช่น ผู้ใช้งาน รายวิชา ห้อง และหลักสูตร</p>
                </div>
            </div>
            @include('shared.dashboard.admin_stats_strip')
        </section>

        <section class="admin-section">
            <div class="admin-section-head admin-section-head-with-action">
                <div>
                    <h2>ภาระงานสอนของอาจารย์</h2>
                    <p>ตรวจรายชื่ออาจารย์ ภาควิชา และเกณฑ์ชั่วโมงสอนก่อนเริ่มจัดตารางจริง</p>
                </div>
            </div>
            @include('shared.dashboard.instructors_workload', ['workloadPageSize' => 5])
        </section>

        <section class="admin-section">
            <div class="admin-section-head">
                <div>
                    <h2>กิจกรรมล่าสุด</h2>
                    <p>ติดตามการเปลี่ยนแปลงล่าสุดในระบบ และพื้นที่สำหรับตารางสอนถัดไป</p>
                </div>
            </div>
            <div class="admin-secondary-grid">
                @include('shared.dashboard.recent-activity')
                @include('shared.dashboard.upcoming-schedules')
            </div>
        </section>
    </div>

    <style>
        .admin-dashboard {
            padding: 24px 28px 32px;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .admin-section {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .admin-section-head {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
            padding: 0 2px;
        }

        .admin-section-head h2 {
            margin: 0;
            font-family: var(--font-display);
            font-size: 17px;
            font-weight: 700;
            line-height: 1.35;
            color: var(--fg-1);
        }

        .admin-section-head p {
            margin: 3px 0 0;
            max-width: 760px;
            font-size: 12.5px;
            line-height: 1.55;
            color: var(--fg-3);
        }

        .admin-action-grid,
        .admin-secondary-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 18px;
        }

        .admin-dashboard .card {
            margin-bottom: 0;
            border-color: var(--border);
        }

        .admin-dashboard .card-hdr {
            min-height: 54px;
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
            white-space: nowrap;
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

        .admin-dashboard [data-testid="recent-activity-widget"],
        .admin-dashboard [data-testid="dashboard-upcoming-schedules"] {
            align-self: stretch;
        }

        @media (max-width: 1100px) {
            .admin-action-grid,
            .admin-secondary-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 720px) {
            .admin-dashboard {
                padding: 16px;
                gap: 22px;
            }

            .admin-section-head {
                align-items: flex-start;
            }

            .admin-section-head h2 {
                font-size: 16px;
            }

            .admin-section-head p {
                font-size: 12px;
            }
        }
    </style>
</x-app-layout>
