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
                <div style="display: flex; align-items: baseline; gap: 6px; margin-bottom: 8px;">
                    <div class="admin-stats-value">{{ $cell['value'] }}</div>
                    <div style="font-size: 12px; color: var(--fg-3); font-weight: 500;">{{ $cell['unit'] }}</div>
                </div>
                <div class="admin-stats-bar">
                    <div class="admin-stats-bar-fill" style="width: {{ $cell['progress'] }}%;"></div>
                </div>
                <div class="admin-stats-foot">{{ $cell['foot'] }}</div>
            </a>
        @endforeach

        {{-- Rooms — by type breakdown --}}
        <a href="{{ route('admin.master_data', ['tab' => 'rooms']) }}" class="admin-stats-cell" style="text-decoration: none;">
            <div class="admin-stats-head">
                <div class="admin-stats-label">ห้องและสถานที่</div>
            </div>
            <div style="display: flex; align-items: baseline; gap: 6px; margin-bottom: 10px;">
                <div class="admin-stats-value">{{ number_format($stats['rooms']['total']) }}</div>
                <div style="font-size: 12px; color: var(--fg-3); font-weight: 500;">รายการ</div>
            </div>
            @if($topRoomTypes->isNotEmpty())
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    @foreach($topRoomTypes as $type)
                        <div style="display: flex; justify-content: space-between; font-size: 11.5px; color: var(--fg-2);">
                            <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; padding-right: 6px;">{{ $type['label'] }}</span>
                            <span style="font-weight: 700; color: var(--fg-1); font-variant-numeric: tabular-nums;">{{ number_format($type['count']) }}</span>
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
            <div style="display: flex; align-items: baseline; gap: 6px; margin-bottom: 10px;">
                <div class="admin-stats-value">{{ number_format($stats['curriculums']['total']) }}</div>
                <div style="font-size: 12px; color: var(--fg-3); font-weight: 500;">หลักสูตร</div>
            </div>
            @if($stats['curriculums']['total'] > 0)
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    @foreach([['bachelor', 'ปริญญาตรี'], ['master', 'ปริญญาโท'], ['doctorate', 'ปริญญาเอก']] as [$key, $label])
                        @if($byLevel[$key] > 0)
                            <div style="display: flex; justify-content: space-between; font-size: 11.5px; color: var(--fg-2);">
                                <span>{{ $label }}</span>
                                <span style="font-weight: 700; color: var(--fg-1); font-variant-numeric: tabular-nums;">{{ number_format($byLevel[$key]) }}</span>
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
        grid-template-columns: repeat(4, 1fr);
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--r-lg);
        overflow: hidden;
    }
    .admin-stats-cell {
        display: block;
        padding: 16px 18px 18px;
        color: inherit;
        border-right: 1px solid var(--border);
        transition: background 120ms;
        min-height: 156px;
    }
    .admin-stats-cell:last-child { border-right: none; }
    .admin-stats-cell:hover { background: var(--bg-2); }

    .admin-stats-head {
        display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 10px;
    }
    .admin-stats-label {
        font-size: 11.5px; font-weight: 600; color: var(--fg-2);
        text-transform: uppercase; letter-spacing: 0.04em;
    }
    .admin-stats-value {
        font-family: var(--font-display);
        font-size: 30px; font-weight: 800; color: var(--fg-1);
        line-height: 1; font-variant-numeric: tabular-nums;
    }
    .admin-stats-bar {
        height: 4px; background: var(--bg-2); border-radius: 2px; overflow: hidden;
        margin-bottom: 8px;
    }
    .admin-stats-bar-fill {
        height: 100%; background: var(--brand-navy); border-radius: 2px;
        transition: width 200ms;
    }
    .admin-stats-foot {
        font-size: 11px; color: var(--fg-3); line-height: 1.4;
    }
    @media (max-width: 900px) {
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
