@php
    $conflicts = collect($conflicts ?? []);
    $conflictTypeLabels = $conflictTypeLabels ?? [
        'instructor_overlap' => 'ผู้สอนชน',
        'room_overlap' => 'ห้อง/สถานที่ชน',
        'group_overlap' => 'กลุ่มนักศึกษาชน',
    ];
    $conflictSets = $conflicts
        ->groupBy('schedule_id')
        ->map(function ($items) use ($conflictTypeLabels) {
            $first = $items->first();

            return [
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
                            ->implode(', ');

                        return [
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
        <div class="conflict-compare-card">
            <div class="conflict-compare-title">ชนกับ {{ $conflictSet['label'] ?: 'รายการสอนอื่น' }}</div>
            <div class="conflict-reasons">
                @foreach($conflictSet['reasons'] as $reason)
                    <span class="conflict-reason">
                        {{ $reason['label'] }}@if($reason['resources']): {{ $reason['resources'] }}@endif
                    </span>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
