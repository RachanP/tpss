@php
    $hasCritical = $alerts['critical'] > 0;
    $hasWarning  = $alerts['warnings'] > 0;
    $allClear    = !$hasCritical && !$hasWarning;
@endphp

<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-hdr">
        <div style="display: flex; align-items: center; gap: 10px;">
            <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"
                 style="color: {{ $hasCritical ? 'var(--status-conflict-fg)' : ($hasWarning ? 'var(--status-warning-fg)' : 'var(--status-success-fg)') }};">
                @if($allClear)
                    <polyline points="20 6 9 17 4 12"/>
                @else
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                @endif
            </svg>
            <div class="card-ttl">ความพร้อม Master Data</div>
            @if($hasCritical)
                <span class="pill p-conflict">Critical {{ $alerts['critical'] }}</span>
            @endif
            @if($hasWarning)
                <span class="pill p-warning">Warning {{ $alerts['warnings'] }}</span>
            @endif
            @if($allClear)
                <span class="pill p-success">พร้อมทั้งหมด</span>
            @endif
        </div>
        <div class="card-actions">
            <a href="{{ route('admin.alerts') }}" class="btn btn-sm">ดูทั้งหมด</a>
        </div>
    </div>

    @if($allClear)
    <div style="padding: 16px 20px; display: flex; align-items: center; gap: 10px; color: var(--status-success-fg); background: color-mix(in oklch, var(--status-success) 5%, white); border-top: 1px solid var(--border);">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        <span style="font-size: 13px; font-weight: 600;">ข้อมูลทุกหมวดพร้อมสำหรับการจัดตารางสอน</span>
    </div>
    @else
    <div style="border-top: 1px solid var(--border);">

        {{-- Critical rows --}}
        @if($hasCritical)
        <div style="padding: 8px 0; background: color-mix(in oklch, var(--status-conflict) 4%, white);">
            <div style="padding: 4px 16px 6px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: var(--status-conflict-fg);">Critical</div>
            @foreach($criticals as $c)
                <a href="{{ $c['link'] }}" style="text-decoration:none; display:flex; align-items:center; gap:12px; padding:8px 16px; border-left: 3px solid var(--status-conflict);">
                    <div style="width:6px;height:6px;border-radius:50%;background:var(--status-conflict);flex-shrink:0;"></div>
                    <span style="flex:1;font-size:12.5px;font-weight:600;color:var(--status-conflict-fg);">{{ $c['label'] }}</span>
                    <span style="font-size:11px;color:var(--status-conflict-fg);opacity:.7;">{{ $c['key'] === 'pa_violations' ? 'ดูรายละเอียด →' : 'แก้ไข →' }}</span>
                </a>
            @endforeach
        </div>
        @endif

        {{-- Warning rows --}}
        @if($hasWarning)
        @php
            $warningItems = [
                ['count' => $alerts['departments'],  'label' => 'ภาควิชา',              'unit' => 'ภาควิชา'],
                ['count' => $alerts['rooms'],        'label' => 'ห้อง / สถานที่',        'unit' => 'รายการ'],
                ['count' => $alerts['course_staff'], 'label' => 'เจ้าหน้าที่ดูแลวิชา', 'unit' => 'วิชา'],
            ];
        @endphp
        @if($hasCritical)<div style="border-top: 1px solid var(--border);"></div>@endif
        <div style="padding: 8px 0;">
            <div style="padding: 4px 16px 6px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: var(--status-warning-fg);">Warning</div>
            @foreach($warningItems as $item)
                @if($item['count'] > 0)
                <a href="{{ route('admin.alerts') }}" style="text-decoration:none; display:flex; align-items:center; gap:12px; padding:8px 16px; border-left: 3px solid var(--status-warning);">
                    <div style="width:6px;height:6px;border-radius:50%;background:var(--status-warning);flex-shrink:0;"></div>
                    <span style="flex:1;font-size:12.5px;font-weight:600;color:var(--fg-1);">{{ $item['label'] }}</span>
                    <span class="pill p-warning" style="font-size:11px;">{{ $item['count'] }} {{ $item['unit'] }}</span>
                </a>
                @endif
            @endforeach
        </div>
        @endif

    </div>
    @endif
</div>
