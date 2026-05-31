@php
    $summary = $conflictSummary ?? ['status' => 'disabled', 'total' => null, 'by_type' => []];
    $status = $summary['status'] ?? 'disabled';
    $isReady = $status === 'ready';
    $total = $summary['total'];
    $byType = $summary['by_type'] ?? [];
@endphp

<div class="card" data-testid="dashboard-conflict-summary">
    <div class="card-hdr">
        <div>
            <div class="card-ttl">Schedule conflict summary</div>
            <div style="font-size:12px;color:var(--fg-3);margin-top:2px;">
                @if($currentAcademicYear ?? null)
                    ปีการศึกษา {{ $currentAcademicYear->name }}
                @else
                    No active academic year
                @endif
            </div>
        </div>
        <span class="pill {{ $isReady ? 'p-success' : ($status === 'disabled' ? 'badge-gray' : 'p-warning') }}">
            {{ $status }}
        </span>
    </div>
    <div style="padding:16px 18px;">
        <div style="font-size:28px;font-weight:850;color:var(--fg-1);line-height:1;">
            {{ $isReady ? number_format((int) $total) : '-' }}
        </div>
        <div style="font-size:12px;color:var(--fg-3);margin-top:6px;">
            @if($status === 'disabled')
                Async conflict reads are disabled
            @elseif($isReady)
                Stored scoped results
            @else
                Results are {{ $status }}
            @endif
        </div>
        @if($isReady)
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;">
                <span class="pill p-conflict">Instructor {{ $byType['instructor_overlap'] ?? 0 }}</span>
                <span class="pill p-warning">Room {{ $byType['room_overlap'] ?? 0 }}</span>
                <span class="pill p-info">Group {{ $byType['group_overlap'] ?? 0 }}</span>
            </div>
        @endif
    </div>
</div>
