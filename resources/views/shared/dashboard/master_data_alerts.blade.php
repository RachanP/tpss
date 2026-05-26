@php
    $hasCritical = $alerts['critical'] > 0;
    $hasWarning  = $alerts['warnings'] > 0;
    $allClear    = !$hasCritical && !$hasWarning;
@endphp

<div class="card admin-alert-card">
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
            <a href="{{ route('admin.alerts') }}" class="btn btn-sm ra-view-all">ดูทั้งหมด</a>
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
                @php
                    $criticalAction = ($c['key'] ?? null) === 'pa_violations'
                        ? 'ดูรายละเอียด'
                        : ($c['linkTxt'] ?? 'ไปแก้ไขข้อมูล');
                @endphp
                <a href="{{ $c['link'] }}" class="admin-alert-row is-critical" aria-label="{{ $criticalAction }}">
                    <span class="admin-alert-main">{{ $c['label'] }}</span>
                    <span class="admin-alert-action">{{ $criticalAction }}</span>
                    <span class="admin-alert-status is-critical" aria-hidden="true">แก้ไข</span>
                </a>
            @endforeach
        </div>
        @endif

        {{-- Warning rows --}}
        @if($hasWarning)
        @php
            $warningItems = [
                [
                    'count' => $alerts['departments'],
                    'label' => 'ภาควิชา',
                    'unit' => 'ภาควิชา',
                    'link' => route('admin.master_data', ['tab' => 'departments']),
                    'action' => 'ไปจัดการภาควิชา',
                ],
                [
                    'count' => $alerts['rooms'],
                    'label' => 'ห้อง / สถานที่',
                    'unit' => 'รายการ',
                    'link' => route('admin.master_data', ['tab' => 'location_types']),
                    'action' => 'ไปจัดการห้อง / สถานที่',
                ],
                [
                    'count' => $alerts['course_staff'],
                    'label' => 'เจ้าหน้าที่ดูแลวิชา',
                    'unit' => 'วิชา',
                    'link' => route('admin.master_data', ['tab' => 'courses']),
                    'action' => 'ไปจัดการรายวิชา',
                ],
            ];
        @endphp
        @if($hasCritical)<div style="border-top: 1px solid var(--border);"></div>@endif
        <div style="padding: 8px 0;">
            <div class="admin-alert-group-label is-warning">ควรตรวจสอบเพื่อให้ข้อมูลครบถ้วน</div>
            @foreach($warningItems as $item)
                @if($item['count'] > 0)
                <a href="{{ $item['link'] }}" class="admin-alert-row is-warning" aria-label="{{ $item['action'] }}">
                    <span class="admin-alert-main">{{ $item['label'] }}</span>
                    <span class="admin-alert-action">{{ $item['action'] }}</span>
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
    .admin-alert-card {
        border: 1px solid var(--border);
    }

    .admin-alert-card .card-hdr > div:first-child {
        flex-wrap: wrap;
        min-width: 0;
    }

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
        transition:
            background var(--dur-fast),
            border-color var(--dur-fast),
            box-shadow var(--dur-fast),
            transform var(--dur-fast);
    }

    .admin-alert-row:hover,
    .admin-alert-row:focus-visible {
        background: var(--surface);
        border-color: color-mix(in oklch, var(--brand-navy) 26%, var(--border));
        box-shadow: 0 2px 10px rgba(0, 36, 84, 0.08);
        outline: none;
        transform: translateY(-1px);
    }

    .admin-alert-main {
        flex: 1;
        min-width: 0;
        font-size: 12.5px;
        font-weight: 700;
        color: var(--fg-1);
    }

    .admin-alert-action {
        font-size: 11px;
        font-weight: 700;
        color: var(--brand-navy);
        opacity: 0;
        transform: translateX(-4px);
        transition: opacity var(--dur-fast), transform var(--dur-fast);
        text-align: right;
        white-space: normal;
    }

    .admin-alert-status {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 24px;
        padding: 4px 9px;
        border-radius: var(--r-pill);
        border: 1px solid var(--border);
        font-size: 11px;
        font-weight: 800;
        line-height: 1;
        white-space: nowrap;
    }

    .admin-alert-status.is-critical {
        border-color: var(--status-conflict-border);
        background: var(--status-conflict-bg);
        color: var(--status-conflict-fg);
    }

    .admin-alert-row:hover .admin-alert-action,
    .admin-alert-row:focus-visible .admin-alert-action {
        opacity: 1;
        transform: translateX(0);
    }

    .admin-alert-row.is-critical {
        border-color: var(--border);
        background: color-mix(in oklch, var(--status-conflict) 5%, var(--surface));
    }

    .admin-alert-row.is-critical .admin-alert-main,
    .admin-alert-row.is-critical .admin-alert-action {
        color: var(--status-conflict-fg);
    }

    .admin-alert-row.is-critical:hover,
    .admin-alert-row.is-critical:focus-visible {
        border-color: color-mix(in oklch, var(--status-conflict) 34%, var(--border));
    }

    .admin-alert-row.is-warning {
        border-color: var(--border);
        background: color-mix(in oklch, var(--status-warning) 5%, var(--surface));
    }

    .admin-alert-row.is-warning:hover,
    .admin-alert-row.is-warning:focus-visible {
        border-color: color-mix(in oklch, var(--brand-navy) 30%, var(--border));
        background: color-mix(in oklch, var(--brand-navy) 4%, var(--surface));
    }

    @media (max-width: 900px) {
        .admin-alert-card .card-hdr {
            align-items: flex-start;
        }
    }

    @media (max-width: 720px) {
        .admin-alert-row {
            align-items: flex-start;
            flex-wrap: wrap;
        }

        .admin-alert-action {
            order: 3;
            width: 100%;
            opacity: 1;
            transform: none;
        }

        .admin-alert-status {
            order: 2;
        }
    }

    @media (max-width: 540px) {
        .admin-alert-row {
            margin-inline: 10px;
            padding: 9px;
        }

        .admin-alert-main {
            flex-basis: 100%;
        }
    }
</style>
