@if(($reasons ?? collect())->isNotEmpty())
    <span class="schedule-incomplete-badge" title="ข้อมูลการ์ดยังไม่ครบ: {{ $reasons->implode(', ') }}">
        <span class="schedule-incomplete-dot" aria-hidden="true"></span>
        <span>ข้อมูลยังไม่ครบ</span>
        <span class="schedule-incomplete-detail">{{ $reasons->implode(', ') }}</span>
    </span>
@endif
