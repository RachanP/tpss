@php
    $hasCritical = $alerts['critical'] > 0;
    $hasWarning  = $alerts['warnings'] > 0;
    $allClear    = !$hasCritical && !$hasWarning;
@endphp

<div class="card">
    <div class="card-hdr">
        <div style="display: flex; align-items: center; gap: 10px;">
            <div class="card-ttl">ข้อมูลที่ต้องพร้อมก่อนจัดตาราง</div>
            @if($hasCritical)
                <span class="pill p-conflict">ต้องแก้ก่อน {{ $alerts['critical'] }}</span>
            @endif
            @if($hasWarning)
                <span class="pill p-warning">ควรตรวจ {{ $alerts['warnings'] }}</span>
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
    <div style="padding: 16px 20px; display: flex; align-items: center; gap: 10px; color: var(--status-success-fg); background: color-mix(in oklch, var(--status-success) 5%, var(--surface)); border-top: 1px solid var(--border);">
        <span style="font-size: 13px; font-weight: 600;">ข้อมูลทุกหมวดพร้อมสำหรับการจัดตารางสอน</span>
    </div>
    @else
    <div style="border-top: 1px solid var(--border);">

        {{-- Critical rows --}}
        @if($hasCritical)
        <div style="padding: 8px 0; background: color-mix(in oklch, var(--status-conflict) 4%, var(--surface));">
            <div class="admin-alert-group-label is-critical">ต้องแก้ก่อนเปิดจัดตาราง</div>
            @foreach($criticals as $c)
                <a href="{{ $c['link'] }}" class="admin-alert-row is-critical">
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
            <div class="admin-alert-group-label is-warning">ควรตรวจสอบเพื่อให้ข้อมูลครบถ้วน</div>
            @foreach($warningItems as $item)
                @if($item['count'] > 0)
                <a href="{{ route('admin.alerts') }}" class="admin-alert-row is-warning">
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

<style>
    .admin-alert-group-label {
        padding: 4px 16px 6px;
        font-size: 11px;
        font-weight: 800;
        line-height: 1.35;
    }

    .admin-alert-group-label.is-critical {
        color: var(--status-conflict-fg);
    }

    .admin-alert-group-label.is-warning {
        color: var(--status-warning-fg);
    }

    .admin-alert-row {
        display: flex;
        align-items: center;
        gap: 12px;
        margin: 0 12px 6px;
        padding: 9px 10px;
        border: 1px solid var(--border);
        border-radius: var(--r-sm);
        text-decoration: none;
        transition: background var(--dur-fast), border-color var(--dur-fast);
    }

    .admin-alert-row:hover {
        background: var(--surface);
    }

    .admin-alert-row.is-critical {
        border-color: color-mix(in oklch, var(--status-conflict) 28%, var(--border));
        background: color-mix(in oklch, var(--status-conflict) 5%, var(--surface));
    }

    .admin-alert-row.is-critical:hover {
        border-color: color-mix(in oklch, var(--status-conflict) 42%, var(--border));
    }

    .admin-alert-row.is-warning {
        border-color: color-mix(in oklch, var(--status-warning) 30%, var(--border));
        background: color-mix(in oklch, var(--status-warning) 5%, var(--surface));
    }

    .admin-alert-row.is-warning:hover {
        border-color: color-mix(in oklch, var(--status-warning) 44%, var(--border));
    }
</style>
