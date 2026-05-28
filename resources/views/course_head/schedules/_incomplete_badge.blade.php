@if(($reasons ?? collect())->isNotEmpty())
    @php $reasonList = $reasons->values()->all(); @endphp
    <span class="schedule-incomplete-badge schedule-conflict-pill" data-conflict-pill tabindex="0" aria-label="ข้อมูลการ์ดยังไม่ครบ: {{ implode(', ', $reasonList) }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="9"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        ข้อมูลยังไม่ครบ

        <span class="conflict-tt" role="tooltip" aria-hidden="true">
            <span class="conflict-tt-head">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <strong>ข้อมูลยังไม่ครบ</strong>
            </span>
            <span class="conflict-tt-body">
                <span class="conflict-tt-group">
                    <span class="conflict-tt-reasons">
                        @foreach($reasonList as $r)
                            <span class="conflict-tt-reason">
                                <span class="conflict-tt-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" style="color:oklch(52% 0.14 82)">
                                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                                    </svg>
                                </span>
                                <span class="conflict-tt-reason-label">{{ $r }}</span>
                                <span class="conflict-tt-reason-value conflict-tt-reason-value--muted">ยังไม่ได้กำหนด</span>
                            </span>
                        @endforeach
                    </span>
                </span>
            </span>
        </span>
    </span>
@endif
