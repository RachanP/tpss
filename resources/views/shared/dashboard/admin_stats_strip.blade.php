@php
    $usersPct   = $stats['users']['total']    > 0 ? round($stats['users']['active']   / $stats['users']['total']   * 100) : 0;
    $coursesPct = $stats['courses']['total']  > 0 ? round($stats['courses']['active'] / $stats['courses']['total'] * 100) : 0;
    $topRoomTypes = $stats['rooms']['by_type']->take(3);
    $byLevel = $stats['curriculums']['by_level'];

    $cells = [
        [
            'label'   => 'ผู้ใช้งาน',
            'value'   => number_format($stats['users']['active']),
            'unit'    => '/ ' . number_format($stats['users']['total']) . ' บัญชี',
            'progress'=> $usersPct,
            'foot'    => $usersPct . '% ของบัญชีทั้งหมดเปิดใช้งาน',
            'link'    => route('admin.users'),
        ],
        [
            'label'   => 'รายวิชาเปิดสอน',
            'value'   => number_format($stats['courses']['active']),
            'unit'    => '/ ' . number_format($stats['courses']['total']) . ' วิชา',
            'progress'=> $coursesPct,
            'foot'    => $coursesPct . '% ของรายวิชาทั้งหมดเปิดสอน',
            'link'    => route('admin.master_data', ['tab' => 'courses']),
        ],
    ];
@endphp

<div data-testid="admin-stats-strip" style="margin-bottom: 18px;">
    <div class="admin-stats-strip">

        {{-- 2 progress-bar cells --}}
        @foreach($cells as $cell)
            <a href="{{ $cell['link'] }}" class="admin-stats-cell" style="text-decoration: none;">
                <div class="admin-stats-head">
                    <div class="admin-stats-label">{{ $cell['label'] }}</div>
                </div>
                <div class="admin-stats-metric">
                    <div class="admin-stats-value">{{ $cell['value'] }}</div>
                    <div class="admin-stats-unit">{{ $cell['unit'] }}</div>
                </div>
                <div class="admin-stats-bar">
                    <div class="admin-stats-bar-fill" style="width: {{ $cell['progress'] }}%;"></div>
                </div>
                <div class="admin-stats-foot">{{ $cell['foot'] }}</div>
            </a>
        @endforeach

        {{-- Rooms — by type breakdown --}}
        <a href="{{ route('admin.master_data', ['tab' => 'location_types']) }}" class="admin-stats-cell" style="text-decoration: none;">
            <div class="admin-stats-head">
                <div class="admin-stats-label">ห้องและสถานที่</div>
            </div>
            <div class="admin-stats-metric">
                <div class="admin-stats-value">{{ number_format($stats['rooms']['total']) }}</div>
                <div class="admin-stats-unit">รายการ</div>
            </div>
            @if($topRoomTypes->isNotEmpty())
                <div class="admin-stats-list">
                    @foreach($topRoomTypes as $type)
                        <div class="admin-stats-list-row">
                            <span>{{ $type['label'] }}</span>
                            <strong>{{ number_format($type['count']) }}</strong>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="admin-stats-foot">ยังไม่มีประเภทสถานที่</div>
            @endif
        </a>

        {{-- Curriculums — by education level --}}
        <a href="{{ route('admin.master_data', ['tab' => 'curriculums']) }}" class="admin-stats-cell" style="text-decoration: none;">
            <div class="admin-stats-head">
                <div class="admin-stats-label">หลักสูตร</div>
            </div>
            <div class="admin-stats-metric">
                <div class="admin-stats-value">{{ number_format($stats['curriculums']['total']) }}</div>
                <div class="admin-stats-unit">หลักสูตร</div>
            </div>
            @if($stats['curriculums']['total'] > 0)
                <div class="admin-stats-list">
                    @foreach([['bachelor', 'ปริญญาตรี'], ['master', 'ปริญญาโท'], ['doctorate', 'ปริญญาเอก']] as [$key, $label])
                        @if($byLevel[$key] > 0)
                            <div class="admin-stats-list-row">
                                <span>{{ $label }}</span>
                                <strong>{{ number_format($byLevel[$key]) }}</strong>
                            </div>
                        @endif
                    @endforeach
                </div>
            @else
                <div class="admin-stats-foot">ยังไม่มีหลักสูตร</div>
            @endif
        </a>
    </div>
</div>

<style>
    .admin-stats-strip {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        background:
            linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 7%, var(--surface)), var(--surface) 48%),
            var(--surface);
        border: 1px solid color-mix(in oklch, var(--brand-navy) 24%, var(--border));
        border-radius: var(--r-lg);
        overflow: hidden;
        box-shadow:
            0 1px 2px rgba(0, 36, 84, 0.09),
            0 16px 34px -22px rgba(0, 36, 84, 0.42),
            inset 0 1px 0 rgba(255, 255, 255, 0.7);
    }
    .admin-stats-cell {
        display: flex;
        position: relative;
        flex-direction: column;
        padding: 18px 20px 20px;
        color: inherit;
        border-right: 1px solid color-mix(in oklch, var(--brand-navy) 20%, var(--border));
        background:
            linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 3%, var(--surface)), var(--surface) 64%);
        transition:
            background var(--dur-fast),
            box-shadow var(--dur-fast),
            transform var(--dur-fast),
            border-color var(--dur-fast);
        min-height: 164px;
        min-width: 0;
    }
    .admin-stats-cell:last-child { border-right: none; }
    .admin-stats-cell::before {
        content: "";
        position: absolute;
        inset: 0 0 auto;
        height: 3px;
        background: var(--brand-navy);
        opacity: 0;
        transition: opacity var(--dur-fast);
    }
    .admin-stats-cell:hover,
    .admin-stats-cell:focus-visible {
        background: color-mix(in oklch, var(--brand-navy) 8%, var(--surface));
        box-shadow:
            inset 0 0 0 1px color-mix(in oklch, var(--brand-navy) 22%, transparent),
            0 12px 24px -22px rgba(0, 36, 84, 0.42);
        outline: none;
        transform: translateY(-1px);
    }
    .admin-stats-cell:hover::before,
    .admin-stats-cell:focus-visible::before {
        opacity: 1;
    }

    .admin-stats-cell:hover .admin-stats-value,
    .admin-stats-cell:focus-visible .admin-stats-value {
        color: color-mix(in oklch, var(--brand-navy) 86%, var(--fg-1));
    }

    .admin-stats-cell:hover .admin-stats-bar-fill,
    .admin-stats-cell:focus-visible .admin-stats-bar-fill {
        filter: saturate(1.08);
    }

    .admin-stats-head {
        display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 12px;
    }
    .admin-stats-label {
        font-size: 12px; font-weight: 800; color: color-mix(in oklch, var(--brand-navy) 76%, var(--fg-2));
        letter-spacing: 0;
        overflow-wrap: anywhere;
    }
    .admin-stats-metric {
        display: flex;
        align-items: baseline;
        gap: 7px;
        margin-bottom: 10px;
    }
    .admin-stats-value {
        font-family: var(--font-display);
        font-size: 30px; font-weight: 800; color: var(--brand-navy);
        line-height: 1; font-variant-numeric: tabular-nums;
    }
    .admin-stats-unit {
        font-size: 12.5px;
        color: var(--fg-3);
        font-weight: 700;
        line-height: 1.3;
    }
    .admin-stats-bar {
        height: 8px;
        background: color-mix(in oklch, var(--brand-navy) 14%, var(--bg-2));
        border-radius: 999px; overflow: hidden;
        margin-bottom: 8px;
        border: 1px solid color-mix(in oklch, var(--brand-navy) 12%, transparent);
        box-shadow: inset 0 1px 2px rgba(0, 36, 84, 0.1);
    }
    .admin-stats-bar-fill {
        height: 100%;
        background: linear-gradient(180deg,
            color-mix(in oklch, var(--brand-navy) 88%, var(--surface)),
            var(--brand-navy));
        border-radius: 999px;
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.16),
            0 1px 2px rgba(0, 36, 84, 0.22);
        transition: width 240ms ease;
    }
    .admin-stats-foot {
        margin-top: auto;
        font-size: 11.5px; color: var(--fg-3); line-height: 1.45;
    }
    .admin-stats-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-top: auto;
    }
    .admin-stats-list-row {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 10px;
        font-size: 12px;
        color: var(--fg-2);
    }
    .admin-stats-list-row span {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .admin-stats-list-row strong {
        font-weight: 800;
        color: var(--brand-navy);
        font-variant-numeric: tabular-nums;
        flex-shrink: 0;
    }
    @media (max-width: 1100px) {
        .admin-stats-strip { grid-template-columns: repeat(2, 1fr); }
        .admin-stats-cell:nth-child(2) { border-right: none; }
        .admin-stats-cell:nth-child(1), .admin-stats-cell:nth-child(2) { border-bottom: 1px solid var(--border); }
    }
    @media (max-width: 540px) {
        .admin-stats-strip { grid-template-columns: 1fr; }
        .admin-stats-cell { border-right: none; border-bottom: 1px solid var(--border); }
        .admin-stats-cell:last-child { border-bottom: none; }
    }
</style>
