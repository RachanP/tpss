@php
    $columns = [
        [
            'key'    => 'draft',
            'label'  => 'ฉบับร่าง',
            'count'  => $pipeline['draft'],
            'desc'   => 'หัวหน้าวิชากำลังจัดทำ',
            'color'  => 'var(--fg-3)',
            'bar'    => 'var(--fg-3)',
            'bg'     => 'transparent',
        ],
        [
            'key'    => 'pending',
            'label'  => 'รออนุมัติ',
            'count'  => $pipeline['pending'],
            'desc'   => 'รอผู้บริหารพิจารณา',
            'color'  => 'var(--status-warning-fg)',
            'bar'    => 'var(--status-warning)',
            'bg'     => 'color-mix(in oklch, var(--status-warning) 3%, white)',
        ],
        [
            'key'    => 'published',
            'label'  => 'เผยแพร่แล้ว',
            'count'  => $pipeline['published'],
            'desc'   => 'อนุมัติและเผยแพร่',
            'color'  => 'var(--status-success-fg)',
            'bar'    => 'var(--status-success)',
            'bg'     => 'color-mix(in oklch, var(--status-success) 3%, white)',
        ],
        [
            'key'    => 'rejected',
            'label'  => 'ตีกลับ',
            'count'  => $pipeline['rejected'],
            'desc'   => 'ต้องแก้ไขและส่งใหม่',
            'color'  => 'var(--status-conflict-fg)',
            'bar'    => 'var(--status-conflict)',
            'bg'     => 'color-mix(in oklch, var(--status-conflict) 3%, white)',
        ],
    ];
    $total = array_sum(array_column($columns, 'count'));
@endphp

<div class="card" data-testid="offering-pipeline">
    <div class="card-hdr">
        <div style="display: flex; align-items: center; gap: 10px;">
            <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"
                 style="color: var(--brand-navy);">
                <line x1="3" y1="6" x2="21" y2="6"/>
                <line x1="3" y1="12" x2="21" y2="12"/>
                <line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
            <div class="card-ttl">สถานะรายวิชาเปิดสอน</div>
        </div>
        <div class="card-actions">
            <span class="pill p-info" style="font-size: 11px;">
                รวม {{ number_format($total) }} วิชา
            </span>
        </div>
    </div>

    @if($total === 0)
        <div style="padding: 24px 20px; text-align: center; color: var(--fg-3); border-top: 1px solid var(--border);">
            <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5"
                 style="margin-bottom: 8px; opacity: 0.5;">
                <rect x="3" y="3" width="18" height="18" rx="2"/>
                <line x1="9" y1="9" x2="15" y2="15"/>
                <line x1="15" y1="9" x2="9" y2="15"/>
            </svg>
            <div style="font-size: 13px; font-weight: 600; color: var(--fg-2); margin-bottom: 4px;">ยังไม่มีรายวิชาเปิดสอน</div>
            <div style="font-size: 11.5px;">เปิดช่วงจัดตารางสอนเพื่อสร้างรายวิชาเปิดสอน</div>
        </div>
    @else
        <div class="offering-pipeline-grid">
            @foreach($columns as $col)
                @php $pct = $total > 0 ? round($col['count'] / $total * 100) : 0; @endphp
                <div class="offering-pipeline-col" style="background: {{ $col['bg'] }};">
                    <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 10px;">
                        <span style="width: 7px; height: 7px; border-radius: 50%; background: {{ $col['color'] }};"></span>
                        <div style="font-size: 11.5px; font-weight: 700; color: {{ $col['color'] }}; text-transform: uppercase; letter-spacing: 0.04em;">
                            {{ $col['label'] }}
                        </div>
                    </div>
                    <div style="display: flex; align-items: baseline; gap: 6px; margin-bottom: 8px;">
                        <div style="font-family: var(--font-display); font-size: 28px; font-weight: 800; color: var(--fg-1); line-height: 1; font-variant-numeric: tabular-nums;">
                            {{ number_format($col['count']) }}
                        </div>
                        <div style="font-size: 11.5px; color: var(--fg-3); font-weight: 600;">{{ $pct }}%</div>
                    </div>
                    <div style="height: 4px; background: var(--bg-2); border-radius: 2px; overflow: hidden; margin-bottom: 8px;">
                        <div style="height: 100%; width: {{ $pct }}%; background: {{ $col['bar'] }}; border-radius: 2px;"></div>
                    </div>
                    <div style="font-size: 11px; color: var(--fg-3); line-height: 1.4;">
                        {{ $col['desc'] }}
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<style>
    .offering-pipeline-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
    }
    .offering-pipeline-col {
        padding: 16px 16px 18px;
        border-right: 1px solid var(--border);
        min-height: 136px;
    }
    .offering-pipeline-col:last-child { border-right: none; }
    @media (max-width: 900px) {
        .offering-pipeline-grid { grid-template-columns: repeat(2, 1fr); }
        .offering-pipeline-col:nth-child(2) { border-right: none; }
        .offering-pipeline-col:nth-child(1), .offering-pipeline-col:nth-child(2) {
            border-bottom: 1px solid var(--border);
        }
    }
    @media (max-width: 540px) {
        .offering-pipeline-grid { grid-template-columns: 1fr; }
        .offering-pipeline-col { border-right: none; border-bottom: 1px solid var(--border); }
        .offering-pipeline-col:last-child { border-bottom: none; }
    }
</style>
