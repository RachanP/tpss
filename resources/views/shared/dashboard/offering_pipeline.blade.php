@php
    $columns = [
        [
            'key'    => 'draft',
            'label'  => 'ฉบับร่าง',
            'count'  => $pipeline['draft'],
            'desc'   => 'หัวหน้าวิชากำลังจัดทำ ยังไม่ต้องอนุมัติ',
            'action' => 'ติดตามความคืบหน้า',
            'color'  => 'var(--fg-3)',
            'pill'   => 'badge-gray',
            'bg'     => 'transparent',
        ],
        [
            'key'    => 'pending',
            'label'  => 'รออนุมัติ',
            'count'  => $pipeline['pending'],
            'desc'   => 'มีรายวิชารอผู้บริหารพิจารณา',
            'action' => 'ควรติดตามก่อน',
            'color'  => 'var(--status-warning-fg)',
            'pill'   => 'p-warning',
            'bg'     => 'color-mix(in oklch, var(--status-warning) 3%, var(--surface))',
        ],
        [
            'key'    => 'published',
            'label'  => 'อนุมัติแล้ว',
            'count'  => $pipeline['published'],
            'desc'   => 'ผู้บริหารอนุมัติและเผยแพร่ให้ใช้งานแล้ว',
            'action' => 'เรียบร้อย',
            'color'  => 'var(--status-success-fg)',
            'pill'   => 'p-success',
            'bg'     => 'color-mix(in oklch, var(--status-success) 3%, var(--surface))',
        ],
        [
            'key'    => 'rejected',
            'label'  => 'ตีกลับ',
            'count'  => $pipeline['rejected'],
            'desc'   => 'ต้องแก้ไขรายละเอียดและส่งใหม่',
            'action' => 'ควรแก้ไขก่อน',
            'color'  => 'var(--status-conflict-fg)',
            'pill'   => 'p-conflict',
            'bg'     => 'color-mix(in oklch, var(--status-conflict) 3%, var(--surface))',
        ],
    ];
    $queue = collect($columns)->sortBy(fn($col) => match($col['key']) {
        'rejected' => 0,
        'pending' => 1,
        'draft' => 2,
        default => 3,
    });
    $total = array_sum(array_column($columns, 'count'));
@endphp

<div class="card" data-testid="offering-pipeline">
    <div class="card-hdr">
        <div style="display: flex; align-items: center; gap: 10px;">
            <div class="card-ttl">งานรายวิชาที่ต้องติดตาม</div>
        </div>
        <div class="card-actions">
            <span class="pill p-info" style="font-size: 11px;">
                รวม {{ number_format($total) }} วิชา
            </span>
        </div>
    </div>

    @if($total === 0)
        <div style="padding: 24px 20px; text-align: center; color: var(--fg-3); border-top: 1px solid var(--border);">
            <div style="font-size: 13px; font-weight: 600; color: var(--fg-2); margin-bottom: 4px;">ยังไม่มีรายวิชาเปิดสอน</div>
            <div style="font-size: 11.5px;">เปิดช่วงจัดตารางสอนเพื่อสร้างรายวิชาเปิดสอน</div>
        </div>
    @else
        <div class="offering-pipeline-list">
            @foreach($queue as $col)
                @php $pct = $total > 0 ? round($col['count'] / $total * 100) : 0; @endphp
                <div class="offering-pipeline-row" style="background: {{ $col['bg'] }};">
                    <div class="offering-pipeline-main">
                        <div class="offering-pipeline-title-row">
                            <span class="pill {{ $col['pill'] }}" style="font-size: 11px;">{{ $col['action'] }}</span>
                            <span class="offering-pipeline-title" style="color: {{ $col['color'] }};">{{ $col['label'] }}</span>
                        </div>
                        <div class="offering-pipeline-desc">{{ $col['desc'] }}</div>
                    </div>
                    <div class="offering-pipeline-count">
                        <span>{{ number_format($col['count']) }}</span>
                        <small>{{ $pct }}%</small>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<style>
    .offering-pipeline-list {
        display: flex;
        flex-direction: column;
        border-top: 1px solid var(--border);
    }

    [data-testid="offering-pipeline"] .card-hdr > div:first-child {
        flex-wrap: wrap;
        min-width: 0;
    }

    .offering-pipeline-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        min-height: 74px;
        padding: 14px 16px;
        border-bottom: 1px solid var(--border);
    }
    .offering-pipeline-row:last-child { border-bottom: none; }
    .offering-pipeline-main {
        flex: 1 1 auto;
        min-width: 0;
    }
    .offering-pipeline-title-row {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 5px;
    }
    .offering-pipeline-title {
        font-size: 12.5px;
        font-weight: 800;
        line-height: 1.35;
    }
    .offering-pipeline-desc {
        font-size: 12px;
        color: var(--fg-3);
        line-height: 1.45;
    }
    .offering-pipeline-count {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 2px;
        min-width: 62px;
        font-variant-numeric: tabular-nums;
    }
    .offering-pipeline-count span {
        font-family: var(--font-display);
        font-size: 25px;
        font-weight: 800;
        color: var(--fg-1);
        line-height: 1;
    }
    .offering-pipeline-count small {
        font-size: 11px;
        font-weight: 700;
        color: var(--fg-3);
        line-height: 1;
    }
    @media (max-width: 540px) {
        .offering-pipeline-row {
            align-items: flex-start;
            flex-direction: column;
            gap: 10px;
            padding: 12px 14px;
        }

        .offering-pipeline-count {
            flex-direction: row;
            align-items: baseline;
            justify-content: space-between;
            width: 100%;
        }
    }
</style>
