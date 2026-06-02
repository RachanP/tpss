<x-app-layout title="ภาพรวม — อาจารย์">
    <div class="role-dashboard">
        @include('shared.dashboard.role_header', [
            'kicker' => 'ภาพรวม / อาจารย์',
            'title'  => 'ภาพรวมอาจารย์',
            'desc'   => 'ดูเกณฑ์ภาระงานสอน ชั่วโมงสอนสะสม และตารางสอนของคุณ',
        ])

        <div class="role-metric-grid">
            <div class="card role-metric-card">
                <div class="role-metric-label">เกณฑ์ภาระงานสอนของคุณ</div>
                @if($quota !== null)
                    <div class="role-metric-value-row">
                        <div class="role-metric-value">{{ number_format($quota, 1) }}</div>
                        <div class="role-metric-unit">ชั่วโมงทำการ / {{ $period }}</div>
                    </div>
                @else
                    <div class="role-metric-empty">- ไม่ระบุเกณฑ์ภาระงานสอน -</div>
                @endif
            </div>

            <div class="card role-metric-card">
                <div class="role-metric-label">ชั่วโมงสอนสะสม (เทอมปัจจุบัน)</div>
                <div class="role-metric-value-row">
                    <div class="role-metric-value is-muted">0.0</div>
                    <div class="role-metric-unit">ชั่วโมงทำการ</div>
                </div>
            </div>
        </div>

        @include('shared.dashboard.role_empty', [
            'icon'  => '<path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>',
            'title' => 'ตารางสอนของฉัน',
            'desc'  => 'หน้านี้อยู่ระหว่างพัฒนา',
        ])
    </div>

    <style>
        .role-metric-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: clamp(12px, 1.4vw, 18px);
        }

        .role-metric-card {
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 22px 24px;
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 4%, var(--surface)), var(--surface) 40%),
                var(--surface);
            border-color: color-mix(in oklch, var(--brand-navy) 22%, var(--border));
        }

        .role-metric-label {
            font-size: 13px;
            font-weight: 700;
            color: var(--fg-2);
        }

        .role-metric-value-row {
            display: flex;
            align-items: baseline;
            gap: 8px;
        }

        .role-metric-value {
            font-family: var(--font-display);
            font-size: 36px;
            font-weight: 800;
            line-height: 1;
            color: var(--brand-navy);
        }

        .role-metric-value.is-muted {
            color: var(--fg-3);
        }

        .role-metric-unit {
            font-size: 14px;
            font-weight: 500;
            color: var(--fg-3);
        }

        .role-metric-empty {
            font-size: 14px;
            font-style: italic;
            color: var(--fg-3);
        }
    </style>
</x-app-layout>
