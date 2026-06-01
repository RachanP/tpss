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
    $roomTotalRaw = (int) $roomTypes->sum('count');
    $roomTotal = max($roomTotalRaw, 1);
    $roomTypeAccents = [
        'oklch(43% 0.118 255)',
        'oklch(52% 0.095 184)',
        'oklch(61% 0.112 82)',
        'oklch(45% 0.105 282)',
        'oklch(50% 0.09 32)',
        'oklch(48% 0.085 150)',
    ];
@endphp

<section class="admin-section">
    <div class="dash-visual-grid" data-testid="admin-visual-overview">
        @include('shared.dashboard._donut', [
            'title'    => 'หลักสูตรแยกตามระดับ',
            'subtitle' => 'ดูจำนวนหลักสูตรแต่ละระดับและสัดส่วนรวมในจุดเดียว',
            'segments' => $levelSegments,
            'unit'     => 'หลักสูตร',
        ])

        <div class="dash-chart-card">
            <div class="dash-chart-headline">
                <div>
                    <div class="dash-chart-title">ห้องและสถานที่แยกประเภท</div>
                    <div class="dash-chart-subtitle">จำนวนสถานที่ที่บันทึกไว้ในระบบ แยกตามประเภท</div>
                </div>
                <div class="dash-chart-total-badge">
                    <strong>{{ number_format($roomTotalRaw) }}</strong>
                    <span>รายการ</span>
                </div>
            </div>
            @if($roomTypes->isNotEmpty())
                <ul class="dash-type-list">
                    @foreach($roomTypes as $type)
                        @php
                            $count = (int) $type['count'];
                            $share = round(($count / $roomTotal) * 100);
                            $accent = $roomTypeAccents[$loop->index % count($roomTypeAccents)];
                        @endphp
                        <li class="dash-type-row {{ $count === 0 ? 'is-empty' : '' }}" style="--dash-type-accent: {{ $accent }};">
                            <span class="dash-type-dot" aria-hidden="true"></span>
                            <span class="dash-type-main">
                                <span class="dash-type-label" title="{{ $type['label'] }}">{{ $type['label'] }}</span>
                                <span class="dash-type-share">สัดส่วน {{ $share }}%</span>
                            </span>
                            <div class="dash-type-count">
                                <strong>{{ number_format($count) }}</strong>
                                <span>รายการ</span>
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
        grid-template-columns: minmax(320px, 0.95fr) minmax(360px, 1.05fr);
        align-items: stretch;
        gap: 18px;
    }
    .dash-chart-card {
        background:
            linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 7%, var(--surface)), var(--surface) 46%),
            var(--surface);
        border: 1px solid color-mix(in oklch, var(--brand-navy) 24%, var(--border));
        border-radius: var(--r-lg);
        padding: 20px 22px 22px;
        min-width: 0;
        min-height: 274px;
        box-shadow:
            0 1px 2px rgba(0, 36, 84, 0.09),
            0 16px 34px -22px rgba(0, 36, 84, 0.42),
            inset 0 1px 0 rgba(255, 255, 255, 0.7);
    }
    .dash-chart-heading,
    .dash-chart-headline {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 16px;
    }
    .dash-chart-heading {
        display: block;
    }
    .dash-chart-title {
        font-size: 13px;
        font-weight: 800;
        color: color-mix(in oklch, var(--brand-navy) 78%, var(--fg-2));
        letter-spacing: 0;
        margin-bottom: 4px;
    }
    .dash-chart-subtitle {
        color: color-mix(in oklch, var(--brand-navy) 38%, var(--fg-3));
        font-size: 11.5px;
        line-height: 1.45;
    }
    .dash-chart-total-badge {
        min-width: 78px;
        display: grid;
        justify-items: center;
        gap: 0;
        padding: 8px 10px;
        border: 1px solid color-mix(in oklch, var(--brand-navy) 22%, var(--border));
        border-radius: 14px;
        background:
            linear-gradient(180deg, rgba(255, 255, 255, 0.82), rgba(255, 255, 255, 0.44)),
            color-mix(in oklch, var(--brand-navy) 8%, var(--surface));
        box-shadow:
            0 1px 2px rgba(0, 36, 84, 0.08),
            inset 0 1px 0 rgba(255, 255, 255, 0.78);
        color: var(--fg-3);
        font-size: 10.5px;
        line-height: 1.15;
        white-space: nowrap;
    }
    .dash-chart-total-badge strong {
        color: var(--brand-navy);
        font-family: var(--font-display);
        font-size: 20px;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
    }

    /* ---- Donut ---- */
    .dash-donut-body {
        display: grid;
        grid-template-columns: minmax(148px, 0.92fr) minmax(0, 1.08fr);
        align-items: center;
        gap: 18px;
        min-height: 186px;
    }
    .dash-donut-stage {
        width: min(100%, 220px);
        aspect-ratio: 1;
        display: grid;
        place-items: center;
        justify-self: center;
        border-radius: 50%;
        background:
            radial-gradient(circle at 50% 50%, rgba(255, 255, 255, 0.9) 0 42%, transparent 43%),
            radial-gradient(circle at 44% 36%, rgba(255, 255, 255, 0.98), color-mix(in oklch, var(--brand-navy) 9%, var(--surface)) 64%, transparent 65%);
        box-shadow:
            0 20px 38px -24px rgba(0, 36, 84, 0.58),
            inset 0 1px 0 rgba(255, 255, 255, 0.85);
        transition:
            box-shadow 180ms ease,
            transform 180ms ease,
            background 180ms ease;
    }
    .dash-donut-card:hover .dash-donut-stage,
    .dash-donut-card:focus-within .dash-donut-stage {
        transform: translateY(-1px);
        box-shadow:
            0 24px 44px -24px rgba(0, 36, 84, 0.66),
            inset 0 1px 0 rgba(255, 255, 255, 0.9);
    }
    .dash-donut {
        width: min(166px, 84%);
        height: min(166px, 84%);
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
        width: 100%;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .dash-legend-row {
        display: grid;
        grid-template-columns: 14px minmax(0, 1fr) auto;
        align-items: center;
        gap: 10px;
        min-height: 42px;
        padding: 9px 10px;
        border: 1px solid color-mix(in oklch, var(--brand-navy) 18%, var(--border));
        border-radius: var(--r-md);
        background: color-mix(in oklch, var(--brand-navy) 4%, var(--surface));
        box-shadow:
            0 1px 2px rgba(0, 36, 84, 0.08),
            inset 0 1px 0 rgba(255, 255, 255, 0.68);
        font-size: 13px;
        color: var(--fg-2);
        transition:
            border-color 180ms ease,
            background 180ms ease,
            box-shadow 180ms ease,
            transform 180ms ease;
    }
    .dash-legend-row:hover {
        transform: translateY(-1px);
        border-color: color-mix(in oklch, var(--brand-navy) 28%, var(--border));
        background: color-mix(in oklch, var(--brand-navy) 7%, var(--surface));
        box-shadow:
            0 1px 2px rgba(0, 36, 84, 0.1),
            0 12px 24px -22px rgba(0, 36, 84, 0.34);
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
        display: inline-flex;
        align-items: baseline;
        justify-content: flex-end;
        min-width: 62px;
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

    /* ---- Type distribution ---- */
    .dash-type-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }
    .dash-type-row {
        display: grid;
        grid-template-columns: 10px minmax(0, 1fr) auto;
        align-items: center;
        gap: 12px;
        min-height: 72px;
        padding: 12px;
        border: 1px solid color-mix(in oklch, var(--dash-type-accent, var(--brand-navy)) 30%, var(--border));
        border-radius: var(--r-md);
        background:
            radial-gradient(circle at 12% 0%, color-mix(in oklch, var(--dash-type-accent, var(--brand-navy)) 12%, transparent), transparent 36%),
            linear-gradient(180deg, color-mix(in oklch, var(--dash-type-accent, var(--brand-navy)) 8%, var(--surface)), transparent 70%),
            color-mix(in oklch, var(--brand-navy) 3%, var(--surface));
        box-shadow:
            0 1px 2px rgba(0, 36, 84, 0.08),
            0 14px 28px -24px rgba(0, 36, 84, 0.34);
        transition:
            border-color 180ms ease,
            background 180ms ease,
            box-shadow 180ms ease,
            transform 180ms ease;
    }
    .dash-type-row:hover {
        transform: translateY(-1px);
        border-color: color-mix(in oklch, var(--dash-type-accent, var(--brand-navy)) 42%, var(--border));
        background:
            radial-gradient(circle at 12% 0%, color-mix(in oklch, var(--dash-type-accent, var(--brand-navy)) 16%, transparent), transparent 36%),
            linear-gradient(180deg, color-mix(in oklch, var(--dash-type-accent, var(--brand-navy)) 11%, var(--surface)), transparent 70%),
            color-mix(in oklch, var(--brand-navy) 5%, var(--surface));
        box-shadow:
            0 2px 4px rgba(0, 36, 84, 0.1),
            0 16px 30px -24px rgba(0, 36, 84, 0.42);
    }
    .dash-type-row.is-empty {
        background:
            linear-gradient(180deg, rgba(255, 255, 255, 0.68), rgba(255, 255, 255, 0.34)),
            color-mix(in oklch, var(--brand-navy) 3%, var(--surface));
    }
    .dash-type-row.is-empty .dash-type-dot {
        background: color-mix(in oklch, var(--dash-type-accent, var(--brand-navy)) 42%, var(--fg-3));
        box-shadow: 0 0 0 4px color-mix(in oklch, var(--dash-type-accent, var(--brand-navy)) 7%, transparent);
    }
    .dash-type-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: color-mix(in oklch, var(--dash-type-accent, var(--brand-navy)) 88%, var(--brand-navy));
        box-shadow: 0 0 0 4px color-mix(in oklch, var(--dash-type-accent, var(--brand-navy)) 12%, transparent);
    }
    .dash-type-main {
        display: grid;
        gap: 6px;
        min-width: 0;
    }
    .dash-type-label {
        color: var(--fg-2);
        font-size: 13px;
        font-weight: 700;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .dash-type-share {
        width: fit-content;
        display: inline-flex;
        align-items: center;
        min-height: 22px;
        padding: 2px 8px;
        border: 1px solid color-mix(in oklch, var(--dash-type-accent, var(--brand-navy)) 26%, var(--border));
        border-radius: 999px;
        background: color-mix(in oklch, var(--dash-type-accent, var(--brand-navy)) 10%, var(--surface));
        color: var(--fg-3);
        font-size: 10.5px;
        line-height: 1.35;
    }
    .dash-type-count {
        display: grid;
        justify-items: end;
        gap: 1px;
        color: var(--fg-3);
        font-size: 11px;
        white-space: nowrap;
    }
    .dash-type-count strong {
        color: color-mix(in oklch, var(--dash-type-accent, var(--brand-navy)) 78%, var(--brand-navy));
        font-family: var(--font-display);
        font-size: 16px;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
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
    @media (max-width: 640px) {
        .dash-chart-card {
            padding: 18px;
        }
        .dash-chart-headline {
            align-items: stretch;
            flex-direction: column;
        }
        .dash-chart-total-badge {
            width: fit-content;
            justify-items: start;
            grid-template-columns: auto auto;
            align-items: baseline;
            gap: 5px;
        }
        .dash-donut-body,
        .dash-type-list {
            grid-template-columns: 1fr;
        }
        .dash-donut-stage {
            width: min(100%, 210px);
        }
    }
</style>
