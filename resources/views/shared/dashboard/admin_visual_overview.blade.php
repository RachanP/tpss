{{--
    Visual overview (V3 8.1 — dashboard เชิงภาพ แทน text wall)
    ใช้ data ที่ DashboardController::admin ส่งมาอยู่แล้ว: $pipeline, $stats
    Impeccable: pure SVG/CSS · หลีกเลี่ยงซ้ำกับ offering_pipeline · ใช้ navy/gold tints สำหรับข้อมูลพื้นฐาน
--}}
@php
    // Donut หลักสูตรแยกระดับ — navy/gold tints (ไม่ใช่สถานะ → ไม่ใช้สีจัด)
    $byLevel = $stats['curriculums']['by_level'] ?? [];
    $levelSegments = [
        ['label' => 'ปริญญาตรี', 'count' => $byLevel['bachelor']  ?? 0, 'color' => 'var(--brand-navy)'],
        ['label' => 'ปริญญาโท',  'count' => $byLevel['master']    ?? 0, 'color' => '#3a5a82'],
        ['label' => 'ปริญญาเอก', 'count' => $byLevel['doctorate'] ?? 0, 'color' => '#c9a449'],
    ];

    // Bar ห้อง/สถานที่แยกประเภท
    $roomTypes = ($stats['rooms']['by_type'] ?? collect())->take(6);
    $roomMax = $roomTypes->max('count') ?: 1;
@endphp

<section class="admin-section">
    <div class="dash-visual-grid" data-testid="admin-visual-overview">
        @include('shared.dashboard._donut', [
            'title'    => 'หลักสูตรแยกตามระดับ',
            'segments' => $levelSegments,
            'unit'     => 'หลักสูตร',
        ])

        <div class="dash-chart-card">
            <div class="dash-chart-title">ห้องและสถานที่แยกประเภท</div>
            @if($roomTypes->isNotEmpty())
                <ul class="dash-bar-list">
                    @foreach($roomTypes as $type)
                        @php $pct = round(($type['count'] / $roomMax) * 100); @endphp
                        <li class="dash-bar-row">
                            <div class="dash-bar-head">
                                <span class="dash-bar-label" title="{{ $type['label'] }}">{{ $type['label'] }}</span>
                                <span class="dash-bar-val">{{ number_format($type['count']) }}</span>
                            </div>
                            <div class="dash-bar-track">
                                <div class="dash-bar-fill" style="width: {{ max($pct, 3) }}%;"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="dash-chart-empty">ยังไม่มีประเภทสถานที่</div>
            @endif
        </div>
    </div>
</section>

<style>
    .dash-visual-grid {
        display: grid;
        grid-template-columns: minmax(280px, 0.9fr) minmax(320px, 1.1fr);
        align-items: stretch;
        gap: 18px;
    }
    .dash-chart-card {
        background:
            linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 3%, var(--surface)), var(--surface) 44%),
            var(--surface);
        border: 1px solid color-mix(in oklch, var(--brand-navy) 14%, var(--border));
        border-radius: var(--r-lg);
        padding: 20px 22px;
        min-width: 0;
        min-height: 274px;
        box-shadow:
            0 1px 2px rgba(0, 36, 84, 0.05),
            0 14px 30px -22px rgba(0, 36, 84, 0.32),
            inset 0 1px 0 rgba(255, 255, 255, 0.7);
    }
    .dash-chart-title {
        font-size: 13px;
        font-weight: 800;
        color: var(--fg-2);
        letter-spacing: 0;
        margin-bottom: 18px;
    }

    /* ---- Donut ---- */
    .dash-donut-body {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 20px;
        min-height: 186px;
    }
    .dash-donut {
        width: 158px;
        height: 158px;
        flex-shrink: 0;
        filter: drop-shadow(0 8px 14px rgba(0, 36, 84, 0.12));
    }
    .dash-donut-total {
        font-family: var(--font-display);
        font-size: 31px;
        font-weight: 800;
        fill: var(--fg-1);
    }
    .dash-donut-unit {
        font-size: 11px;
        font-weight: 600;
        fill: var(--fg-3);
    }
    .dash-legend {
        list-style: none;
        margin: 0;
        padding: 0;
        width: min(100%, 360px);
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 9px;
    }
    .dash-legend-row {
        display: grid;
        grid-template-columns: 14px minmax(0, 1fr) auto;
        align-items: center;
        gap: 10px;
        font-size: 13px;
        color: var(--fg-2);
    }
    .dash-legend-dot {
        width: 12px;
        height: 12px;
        border-radius: 4px;
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.18),
            0 1px 2px rgba(0, 36, 84, 0.1);
    }
    .dash-legend-label {
        flex: 1 1 auto;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .dash-legend-val {
        font-weight: 800;
        color: var(--fg-1);
        font-variant-numeric: tabular-nums;
        flex-shrink: 0;
    }
    .dash-legend-val small {
        font-size: 10.5px;
        font-weight: 600;
        color: var(--fg-3);
        margin-left: 5px;
    }

    /* ---- Horizontal bars ---- */
    .dash-bar-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    .dash-bar-head {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 8px;
        margin-bottom: 7px;
    }
    .dash-bar-label {
        font-size: 13px;
        color: var(--fg-2);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .dash-bar-val {
        font-size: 13px;
        font-weight: 800;
        color: var(--fg-1);
        font-variant-numeric: tabular-nums;
        flex-shrink: 0;
    }
    .dash-bar-track {
        height: 11px;
        background: color-mix(in oklch, var(--brand-navy) 9%, var(--bg-2));
        border-radius: 999px;
        overflow: hidden;
        border: 1px solid color-mix(in oklch, var(--brand-navy) 8%, transparent);
        box-shadow: inset 0 1px 2px rgba(0, 36, 84, 0.1);
    }
    .dash-bar-fill {
        height: 100%;
        background: linear-gradient(180deg,
            color-mix(in oklch, var(--brand-navy) 88%, var(--surface)),
            var(--brand-navy));
        border-radius: 999px;
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.16),
            0 1px 2px rgba(0, 36, 84, 0.22);
        min-width: 10px;
        transition: width 240ms ease;
    }
    .dash-chart-empty {
        font-size: 12.5px;
        color: var(--fg-3);
        padding: 18px 0;
        text-align: center;
    }

    @media (max-width: 900px) {
        .dash-visual-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
