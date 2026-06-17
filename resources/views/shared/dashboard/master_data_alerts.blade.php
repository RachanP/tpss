@php
    $hasCritical = $alerts['critical'] > 0;
    $hasWarning  = $alerts['warnings'] > 0;
    $allClear    = !$hasCritical && !$hasWarning;
@endphp

<div class="card admin-alert-card">
    <div class="card-hdr">
        <div style="display: flex; align-items: center; gap: 10px;">
            <span class="dash-card-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><path d="m9 14 2 2 4-4"/></svg></span>
            <div class="card-ttl" role="heading" aria-level="2">ข้อมูลที่ต้องพร้อมก่อนจัดตาราง</div>
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
    <div class="admin-alert-body admin-alert-empty">
        <span class="admin-alert-icon is-success" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9 12l2 2 4-4"/></svg>
        </span>
        <span style="font-size: 13px; font-weight: 600;">ข้อมูลทุกหมวดพร้อมสำหรับการจัดตารางสอน</span>
    </div>
    @else
    <div class="admin-alert-body">

        {{-- Critical rows --}}
        @if($hasCritical)
        <div class="admin-alert-group is-critical">
            <div class="admin-alert-group-label is-critical">ต้องแก้ก่อนเปิดจัดตาราง</div>
            @foreach($criticals as $c)
                @php
                    $criticalAction = ($c['key'] ?? null) === 'pa_violations'
                        ? 'ดูรายละเอียด'
                        : ($c['linkTxt'] ?? 'ไปแก้ไขข้อมูล');
                @endphp
                <a href="{{ $c['link'] }}" class="admin-alert-row is-critical" aria-label="{{ $criticalAction }}">
                    <span class="admin-alert-icon is-critical" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    </span>
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
        @if($hasCritical)<div class="admin-alert-divider"></div>@endif
        <div class="admin-alert-group is-warning">
            <div class="admin-alert-group-label is-warning">ควรตรวจสอบเพื่อให้ข้อมูลครบถ้วน</div>
            @foreach($warningItems as $item)
                @if($item['count'] > 0)
                <a href="{{ $item['link'] }}" class="admin-alert-row is-warning" aria-label="{{ $item['action'] }}">
                    <span class="admin-alert-icon is-warning" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    </span>
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
        border: 1px solid color-mix(in oklch, var(--brand-navy) 24%, var(--border));
        min-height: 100%;
        background:
            linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 7%, var(--surface)), var(--surface) 46%),
            var(--surface);
        box-shadow:
            0 1px 2px rgba(0, 36, 84, 0.09),
            0 18px 38px -30px rgba(0, 36, 84, 0.42);
    }

    .admin-alert-card .card-hdr {
        min-height: 76px;
        background:
            linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 9%, var(--surface)), transparent 72%),
            color-mix(in oklch, var(--brand-navy) 4%, var(--surface));
        border-bottom-color: color-mix(in oklch, var(--brand-navy) 18%, var(--border));
    }

    .admin-alert-card .card-hdr > div:first-child {
        flex-wrap: wrap;
        min-width: 0;
    }

    .admin-alert-body {
        flex: 1 1 auto;
        min-height: 0;
        padding: 16px 18px 18px;
        background:
            linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 5%, var(--surface)), transparent 44%),
            color-mix(in oklch, var(--brand-navy) 3%, var(--surface));
    }

    .admin-alert-empty {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
        padding: 14px 16px;
        border: 1px solid color-mix(in oklch, var(--brand-navy) 18%, var(--border));
        border-radius: var(--r-md);
        color: var(--status-success-fg);
        background: var(--surface);
    }

    .admin-alert-group {
        padding: 0;
    }

    .admin-alert-group.is-critical {
        background: transparent;
    }

    .admin-alert-divider {
        height: 10px;
        border-top: 0;
    }

    .admin-alert-group-label {
        padding: 0 2px 9px;
        font-size: 11px;
        font-weight: 700;
        line-height: 1.35;
    }

    .admin-alert-group-label.is-critical,
    .admin-alert-group-label.is-warning {
        color: var(--fg-2);
    }

    .admin-alert-row {
        display: grid;
        grid-template-columns: 28px minmax(0, 1fr) auto auto;
        align-items: center;
        gap: 12px;
        margin: 0 0 10px;
        min-height: 70px;
        padding: 13px 14px;
        border: 1px solid color-mix(in oklch, var(--brand-navy) 20%, var(--border));
        border-radius: var(--r-md);
        background: var(--surface);
        box-shadow: 0 1px 2px rgba(0, 36, 84, 0.06);
        text-decoration: none;
        transition:
            background var(--dur-fast),
            border-color var(--dur-fast),
            box-shadow var(--dur-fast),
            transform var(--dur-fast);
    }

    .admin-alert-row:hover,
    .admin-alert-row:focus-visible {
        border-color: color-mix(in oklch, var(--brand-navy) 34%, var(--border));
        box-shadow: 0 2px 10px -4px rgba(0, 36, 84, 0.18);
        outline: none;
        transform: translateY(-1px);
    }

    .admin-alert-row:hover .admin-alert-icon,
    .admin-alert-row:focus-visible .admin-alert-icon {
        box-shadow: 0 0 0 4px color-mix(in oklch, var(--brand-navy) 8%, transparent);
        transform: scale(1.03);
    }

    .admin-alert-row:last-child {
        margin-bottom: 0;
    }

    .admin-alert-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 26px;
        height: 26px;
        border-radius: 50%;
        flex-shrink: 0;
        transition:
            box-shadow var(--dur-fast),
            transform var(--dur-fast);
    }
    .admin-alert-icon.is-critical {
        color: var(--status-conflict-fg);
        background: color-mix(in oklch, var(--status-conflict) 14%, var(--surface));
    }
    .admin-alert-icon.is-warning {
        color: var(--status-warning-fg);
        background: color-mix(in oklch, var(--status-warning) 16%, var(--surface));
    }
    .admin-alert-icon.is-success {
        color: var(--status-success-fg);
        background: color-mix(in oklch, var(--status-success) 14%, var(--surface));
    }

    .admin-alert-main {
        min-width: 0;
        font-size: 13px;
        font-weight: 700;
        color: var(--fg-1);
        line-height: 1.4;
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
        line-height: 1.35;
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
        font-weight: 700;
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
        border-color: var(--status-conflict-border);
        background: var(--surface);
    }

    .admin-alert-row.is-critical .admin-alert-main {
        color: var(--fg-1);
    }

    .admin-alert-row.is-critical .admin-alert-action {
        color: var(--status-conflict-fg);
    }

    .admin-alert-row.is-critical:hover,
    .admin-alert-row.is-critical:focus-visible {
        border-color: color-mix(in oklch, var(--status-conflict) 40%, var(--border));
    }

    .admin-alert-row.is-warning {
        border-color: var(--status-warning-border);
        background: var(--surface);
    }

    .admin-alert-row.is-warning .admin-alert-action {
        color: var(--status-warning-fg);
    }

    .admin-alert-row.is-warning:hover,
    .admin-alert-row.is-warning:focus-visible {
        border-color: color-mix(in oklch, var(--status-warning) 40%, var(--border));
    }

    @media (max-width: 900px) {
        .admin-alert-card .card-hdr {
            align-items: flex-start;
        }
    }

    @media (max-width: 720px) {
        .admin-alert-row {
            align-items: flex-start;
            grid-template-columns: 28px minmax(0, 1fr) auto;
        }

        .admin-alert-action {
            grid-column: 2 / -1;
            order: 3;
            width: 100%;
            opacity: 1;
            transform: none;
            text-align: left;
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
            grid-column: 2 / -1;
        }
    }
</style>
