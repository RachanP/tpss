@php
    $conflicts = collect($conflicts ?? []);
    $conflictTypeLabels = $conflictTypeLabels ?? [
        'instructor_overlap' => 'ผู้สอนชน',
        'room_overlap' => 'ห้อง/สถานที่ชน',
        'group_overlap' => 'กลุ่มนักศึกษาชน',
    ];

    $typeMeta = [
        'instructor_overlap' => ['label' => 'อาจารย์ที่ชน',  'icon' => 'user'],
        'room_overlap'       => ['label' => 'ห้องที่ชน',      'icon' => 'home'],
        'group_overlap'      => ['label' => 'กลุ่ม นศ. ที่ชน', 'icon' => 'users'],
    ];

    $conflictSets = $conflicts
        ->groupBy('schedule_id')
        ->map(function ($items) use ($conflictTypeLabels) {
            $first = $items->first();
            $scheduleId = (int) ($first['schedule_id'] ?? 0);

            return [
                'schedule_id' => $scheduleId,
                'label' => $first['schedule_label'] ?? '',
                'reasons' => $items
                    ->groupBy('type')
                    ->map(function ($typedItems, $type) use ($conflictTypeLabels) {
                        $resources = $typedItems
                            ->pluck('resource_label')
                            ->filter()
                            ->flatMap(fn ($value) => collect(explode(',', (string) $value))->map(fn ($item) => trim($item)))
                            ->filter()
                            ->unique()
                            ->values()
                            ->all();

                        return [
                            'type' => $type,
                            'label' => $conflictTypeLabels[$type] ?? 'ตารางชน',
                            'resources' => $resources,
                        ];
                    })
                    ->values(),
            ];
        })
        ->values();
@endphp

<div class="conflict-compare" data-conflict-detail-content>
    @foreach($conflictSets as $conflictSet)
        @php
            $scheduleId = $conflictSet['schedule_id'];
            $labelText = $conflictSet['label']
                ?: ($scheduleId ? 'รายการสอน #' . $scheduleId : 'รายการสอนอื่น');
        @endphp
        <div class="conflict-compare-card">
            <div class="conflict-compare-title">
                <span class="conflict-compare-prefix">ชนกับ</span>
                <strong class="conflict-compare-target">{{ $labelText }}</strong>
            </div>
            <ul class="conflict-reason-list">
                @foreach($conflictSet['reasons'] as $reason)
                    @php
                        $meta = $typeMeta[$reason['type']] ?? ['label' => $reason['label'], 'icon' => 'alert'];
                        $resources = $reason['resources'] ?? [];
                    @endphp
                    <li class="conflict-reason-row conflict-reason-row--{{ $reason['type'] }}">
                        <span class="conflict-reason-icon" aria-hidden="true">
                            @switch($meta['icon'])
                                @case('user')
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                    @break
                                @case('home')
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                                    @break
                                @case('users')
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-3-3.87"></path><path d="M9 7a4 4 0 1 0 0 8 4 4 0 0 0 0-8z"></path><path d="M1 21v-2a4 4 0 0 1 3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                    @break
                                @default
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                            @endswitch
                        </span>
                        <span class="conflict-reason-label">{{ $meta['label'] }}:</span>
                        @if(empty($resources))
                            <span class="conflict-reason-value conflict-reason-value--muted">(ไม่ระบุ)</span>
                        @else
                            <span class="conflict-reason-value">
                                @foreach($resources as $resource)
                                    <strong>{{ $resource }}</strong>@if(! $loop->last), @endif
                                @endforeach
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</div>
