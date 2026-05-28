@php
    $conflicts = collect($conflicts ?? []);
    $typeMeta = [
        'instructor_overlap' => ['label' => 'อาจารย์ที่ชน',  'icon' => 'user',  'modifier' => 'instructor'],
        'room_overlap'       => ['label' => 'ห้องที่ชน',      'icon' => 'home',  'modifier' => 'room'],
        'group_overlap'      => ['label' => 'กลุ่ม นศ. ที่ชน', 'icon' => 'users', 'modifier' => 'group'],
    ];

    // group by conflicting schedule_id เพื่อโชว์ "ชนกับ X" + reasons รวมต่อ schedule
    $groups = $conflicts
        ->groupBy('schedule_id')
        ->map(function ($items) use ($typeMeta) {
            $first = $items->first();

            return [
                'label' => $first['schedule_label'] ?? ($first['message'] ?? 'รายการสอนอื่น'),
                'reasons' => $items
                    ->groupBy('type')
                    ->map(fn ($byType, $type) => [
                        'type' => $type,
                        'meta' => $typeMeta[$type] ?? ['label' => 'ตารางชน', 'icon' => 'alert', 'modifier' => 'other'],
                        'resources' => $byType
                            ->pluck('resource_label')
                            ->filter()
                            ->unique()
                            ->values()
                            ->all(),
                    ])
                    ->values(),
            ];
        })
        ->values();
@endphp

<span class="schedule-conflict-pill" data-conflict-pill tabindex="0" aria-label="ชน {{ $conflicts->count() }} รายการ — กดเพื่อดูรายละเอียด">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
        <line x1="12" y1="9" x2="12" y2="13"/>
        <line x1="12" y1="17" x2="12.01" y2="17"/>
    </svg>
    ชน {{ $conflicts->count() }} รายการ

    <span class="conflict-tt" role="tooltip" aria-hidden="true">
        <span class="conflict-tt-head">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            <strong>การชน {{ $conflicts->count() }} รายการ</strong>
        </span>
        <span class="conflict-tt-body">
            @foreach($groups as $group)
                <span class="conflict-tt-group">
                    <span class="conflict-tt-target">
                        <span class="conflict-tt-target-prefix">ชนกับ</span>
                        <strong>{{ $group['label'] }}</strong>
                    </span>
                    <span class="conflict-tt-reasons">
                        @foreach($group['reasons'] as $reason)
                            <span class="conflict-tt-reason conflict-tt-reason--{{ $reason['meta']['modifier'] }}">
                                <span class="conflict-tt-icon" aria-hidden="true">
                                    @switch($reason['meta']['icon'])
                                        @case('user')
                                            <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                            @break
                                        @case('home')
                                            <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                                            @break
                                        @case('users')
                                            <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-3-3.87"></path><path d="M9 7a4 4 0 1 0 0 8 4 4 0 0 0 0-8z"></path><path d="M1 21v-2a4 4 0 0 1 3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                            @break
                                        @default
                                            <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                    @endswitch
                                </span>
                                <span class="conflict-tt-reason-label">{{ $reason['meta']['label'] }}:</span>
                                @if(empty($reason['resources']))
                                    <span class="conflict-tt-reason-value conflict-tt-reason-value--muted">(ไม่ระบุ)</span>
                                @else
                                    <span class="conflict-tt-reason-value">
                                        @foreach($reason['resources'] as $resource)
                                            <strong>{{ $resource }}</strong>@if(! $loop->last), @endif
                                        @endforeach
                                    </span>
                                @endif
                            </span>
                        @endforeach
                    </span>
                </span>
            @endforeach
        </span>
    </span>
</span>
