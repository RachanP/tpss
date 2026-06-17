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

<div class="card offering-pipeline-card" data-testid="offering-pipeline">
    <div class="card-hdr">
        <div style="display: flex; align-items: center; gap: 10px;">
            <span class="dash-card-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg></span>
            <div class="card-ttl" role="heading" aria-level="2">สถานะรายวิชา</div>
        </div>
        <div class="card-actions">
            <span class="pill p-info" style="font-size: 11px;">
                รวม {{ number_format($total) }} วิชา
            </span>
        </div>
    </div>

    @if($total === 0)
        <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; gap: 6px; padding: 32px 20px; border-top: 1px solid var(--border);">
            <span class="dash-empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg></span>
            <div style="font-size: 13px; font-weight: 600; color: var(--fg-2);">ยังไม่มีรายวิชาเปิดสอน</div>
            <div style="font-size: 11.5px; color: var(--fg-3);">เปิดช่วงจัดตารางสอนเพื่อสร้างรายวิชาเปิดสอน</div>
        </div>
    @else
        <div class="offering-pipeline-list">
            @foreach($queue as $col)
                @php
                    $pct = $total > 0 ? round($col['count'] / $total * 100) : 0;
                    $rowIcon = match($col['key']) {
                        'published' => '<circle cx="12" cy="12" r="9"/><path d="M9 12l2 2 4-4"/>',
                        'rejected'  => '<circle cx="12" cy="12" r="9"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>',
                        'pending'   => '<circle cx="12" cy="12" r="9"/><path d="M12 8v4l2.5 1.5"/>',
                        default     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
                    };
                    $rowTone = match($col['key']) {
                        'published' => 'success',
                        'rejected'  => 'conflict',
                        'pending'   => 'warning',
                        default     => 'muted',
                    };
                @endphp
                <div class="offering-pipeline-row is-{{ $rowTone }}">
                    <span class="offering-pipeline-icon is-{{ $rowTone }}" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">{!! $rowIcon !!}</svg>
                    </span>
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
    .offering-pipeline-card {
        border: 1px solid color-mix(in oklch, var(--brand-navy) 24%, var(--border));
        background: var(--surface);
        box-shadow:
            0 1px 2px rgba(0, 36, 84, 0.09),
            0 18px 38px -30px rgba(0, 36, 84, 0.42);
    }

    .offering-pipeline-card .card-hdr {
        min-height: 76px;
        background:
            linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 9%, var(--surface)), transparent 72%),
            color-mix(in oklch, var(--brand-navy) 4%, var(--surface));
    }

    .offering-pipeline-list {
        display: grid;
        gap: 10px;
        padding: 16px 18px 18px;
        background: var(--surface);
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
        min-height: 76px;
        padding: 13px 14px;
        border: 1px solid color-mix(in oklch, var(--brand-navy) 20%, var(--border));
        border-radius: var(--r-md);
        background: var(--surface);
        box-shadow: 0 1px 2px rgba(0, 36, 84, 0.06);
    }
    .offering-pipeline-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        flex-shrink: 0;
    }
    .offering-pipeline-icon.is-success {
        color: var(--status-success-fg);
        background: color-mix(in oklch, var(--status-success) 14%, var(--surface));
    }
    .offering-pipeline-icon.is-warning {
        color: var(--status-warning-fg);
        background: color-mix(in oklch, var(--status-warning) 16%, var(--surface));
    }
    .offering-pipeline-icon.is-conflict {
        color: var(--status-conflict-fg);
        background: color-mix(in oklch, var(--status-conflict) 14%, var(--surface));
    }
    .offering-pipeline-icon.is-muted {
        color: var(--fg-3);
        background: color-mix(in oklch, var(--brand-navy) 8%, var(--surface));
    }
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
        font-weight: 700;
        line-height: 1.35;
    }
    .offering-pipeline-desc {
        font-size: 12px;
        color: var(--fg-2);
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
        color: var(--brand-navy);
        line-height: 1;
        transition: color var(--dur-fast);
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
