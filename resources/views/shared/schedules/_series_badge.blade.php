{{-- Series badge with rich tooltip --}}
{{-- Variables: $schedule (model with series_week_number, schedule_template_id) --}}
@if(($schedule ?? null)?->schedule_template_id)
@php
    $seriesWeekNum = ($schedule ?? null)?->series_week_number;
    $seriesLabel = $seriesWeekNum ? "ทำซ้ำ · สัปดาห์ {$seriesWeekNum}" : 'ทำซ้ำรายสัปดาห์';
@endphp
<span
    class="series-badge"
    data-conflict-pill
    tabindex="0"
    aria-label="{{ $seriesLabel }}"
>
    <span class="series-dot" aria-hidden="true"></span>
    <span>ทำซ้ำ</span>
    @if($seriesWeekNum)
        <span>สัปดาห์ {{ $seriesWeekNum }}</span>
    @endif

    <span class="conflict-tt series-tt" role="tooltip" aria-hidden="true">
        <span class="conflict-tt-head">
            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="color:oklch(52% 0.13 245)">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
            </svg>
            <strong>กิจกรรมทำซ้ำรายสัปดาห์</strong>
        </span>
        <span class="conflict-tt-body">
            <span class="conflict-tt-group">
                <span class="conflict-tt-reasons">
                    @if($seriesWeekNum)
                        <span class="conflict-tt-reason">
                            <span class="conflict-tt-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                                </svg>
                            </span>
                            <span class="conflict-tt-reason-label">สัปดาห์ที่:</span>
                            <span class="conflict-tt-reason-value"><strong>{{ $seriesWeekNum }}</strong></span>
                        </span>
                    @endif
                    <span class="conflict-tt-reason">
                        <span class="conflict-tt-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>
                            </svg>
                        </span>
                        <span class="conflict-tt-reason-label">ชุดกิจกรรม:</span>
                        <span class="conflict-tt-reason-value conflict-tt-reason-value--muted">ทำซ้ำอัตโนมัติรายสัปดาห์</span>
                    </span>
                    <span class="conflict-tt-reason">
                        <span class="conflict-tt-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                            </svg>
                        </span>
                        <span class="conflict-tt-reason-value conflict-tt-reason-value--muted">ปรับห้อง ผู้สอน และกลุ่มรายสัปดาห์ได้</span>
                    </span>
                </span>
            </span>
        </span>
    </span>
</span>
@endif
