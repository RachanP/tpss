@foreach($daySchedules as $as)
    @php
        $asActivity = $as->activityType;
        $asRoom = $as->room;
        $asInstructorText = $scheduleInstructorText($as);
        $asConflicts = $scheduleConflicts->get($as->id, collect());
        $asIncompleteReasons = $scheduleIncompleteReasons($as);
        $asSameDay = $as->start_date?->format('d/m/Y') === $as->end_date?->format('d/m/Y');
    @endphp
    <tr
        role="button"
        tabindex="0"
        class="co-sched-row"
        :class="focusedScheduleClass('{{ $as->id }}')"
        style="--activity-color: {{ $activityTone($as) }};"
        x-show="matchesSchedule('{{ $as->id }}') && !collapsedDays['{{ $dateKey }}']"
        x-cloak
        data-schedule-id="{{ $as->id }}"
        data-schedule-modal-trigger
        @click="detailModal = 'schedule-{{ $as->id }}'"
        @keydown.enter.prevent="detailModal = 'schedule-{{ $as->id }}'"
        @keydown.space.prevent="detailModal = 'schedule-{{ $as->id }}'"
    >
        <td class="co-col-date" style="font-weight: 800; color: var(--fg-1); font-variant-numeric: tabular-nums; vertical-align: middle;">
            @if($asSameDay)
                {{ $formatDate($as->start_date) }}
            @else
                <div style="font-size: 12px; line-height: 1.35;">
                    {{ $formatDate($as->start_date) }}<br>
                    <span style="color: var(--fg-3); font-weight: 600; font-size: 11px;">ถึง</span> {{ $formatDate($as->end_date) }}
                </div>
            @endif
        </td>
        <td class="co-col-time">
            <div class="co-time-range">{{ $formatTime($as->start_time) }} - {{ $formatTime($as->end_time) }}</div>
            <div class="co-time-duration">{{ $formatDuration($durationForSchedule($as)) }}</div>
        </td>
        <td class="co-col-activity">
            @if($as->topic)
                <div class="co-activity-topic-main">{{ $as->topic }}</div>
                <div class="co-activity-type-badge" style="--activity-color: {{ $activityTone($as) }};">
                    <span class="co-activity-dot-small" aria-hidden="true"></span>
                    <span>{{ $asActivity?->name ?? 'กิจกรรม' }}</span>
                </div>
            @else
                <div class="co-activity-topic-main">{{ $asActivity?->name ?? 'กิจกรรม' }}</div>
            @endif
            @if($as->schedule_template_id)
                <div style="margin-top:6px;">
                    @include('shared.schedules._series_badge', ['schedule' => $as])
                </div>
            @endif
            @if($asIncompleteReasons->isNotEmpty())
                <div style="margin-top:6px;">
                    @include('shared.schedules._incomplete_badge', ['reasons' => $asIncompleteReasons])
                </div>
            @endif
            @if($asConflicts->isNotEmpty())
                <div style="margin-top:6px;">
                    @include('shared.schedules._conflict_pill', ['conflicts' => $asConflicts])
                </div>
            @endif
        </td>
        <td class="co-col-groups">
            <div class="co-groups-list">
                @foreach($as->studentGroups as $group)
                    <span class="co-group-badge" style="--group-color: {{ $groupTone($group) }};">
                        <span class="co-group-dot" aria-hidden="true"></span>
                        <span>{{ $group->group_code }}</span>
                    </span>
                @endforeach
            </div>
        </td>
        <td class="co-col-instructors">
            <div class="co-instructor-text">{{ $asInstructorText }}</div>
        </td>
        <td class="co-col-location">
            <div class="co-location-room">{{ $asRoom?->room_name ?? $asRoom?->room_code ?? 'ไม่ระบุสถานที่' }}</div>
            @if($asRoom?->building)
                <div class="co-location-building">{{ $asRoom->building }}</div>
            @endif
        </td>
    </tr>
@endforeach
