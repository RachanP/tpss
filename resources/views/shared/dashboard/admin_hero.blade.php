@php
    $phaseMeta = match($currentAcademicYear?->phase) {
        'scheduling' => ['label' => 'เปิดจัดตาราง',  'pill' => 'p-success', 'desc' => 'กำลังเปิดให้หัวหน้าวิชาจัดตารางสอน'],
        'published'  => ['label' => 'เผยแพร่แล้ว',   'pill' => 'p-info',    'desc' => 'ตารางสอนเผยแพร่ให้ใช้งานแล้ว'],
        default      => ['label' => 'เตรียมข้อมูล',  'pill' => 'p-warning', 'desc' => 'อยู่ระหว่างเตรียมข้อมูลก่อนเปิดจัดตาราง'],
    };
    $criticalCount = $alerts['critical'] ?? 0;
    $warningCount  = $alerts['warnings'] ?? 0;
    $pendingOfferings = $pipeline['pending'] ?? 0;
    $currentPhase = $currentAcademicYear?->phase ?? 'preparation';

    if (!$currentAcademicYear) {
        $systemStatus = [
            'title' => 'ยังไม่ได้ตั้งค่าปีการศึกษาที่ใช้งาน',
            'pill' => 'p-warning',
            'label' => 'ต้องตั้งค่าก่อน',
            'desc' => 'ตั้งค่าปีการศึกษาที่ใช้งานก่อน ระบบจึงจะประเมินความพร้อมและเปิดช่วงจัดตารางได้',
            'actionLabel' => 'ตั้งค่าปีการศึกษา',
            'actionRoute' => route('admin.settings', ['tab' => 'academic']),
            'tone' => 'warning',
        ];
    } elseif ($criticalCount > 0) {
        $systemStatus = [
            'title' => 'ยังเปิดจัดตารางไม่ได้',
            'pill' => 'p-conflict',
            'label' => 'พบเงื่อนไขสำคัญ',
            'desc' => "แก้เงื่อนไขสำคัญ {$criticalCount} รายการก่อนเปิดช่วงจัดตาราง เพื่อป้องกันข้อมูลตั้งต้นไม่ครบ",
            'actionLabel' => 'ไปแก้เงื่อนไขสำคัญ',
            'actionRoute' => route('admin.alerts'),
            'tone' => 'conflict',
        ];
    } elseif ($currentPhase === 'preparation' && $warningCount > 0) {
        $systemStatus = [
            'title' => 'ข้อมูลหลักรอตรวจสอบ',
            'pill' => 'p-warning',
            'label' => 'มีรายการควรตรวจสอบ',
            'desc' => "ตรวจสอบข้อมูล {$warningCount} รายการก่อนเปิดช่วงจัดตาราง หากไม่กระทบการจัดตารางสามารถดำเนินการต่อได้",
            'actionLabel' => 'จัดการสถานะระบบ',
            'actionRoute' => route('admin.settings', ['tab' => 'academic']),
            'tone' => 'warning',
        ];
    } elseif ($currentPhase === 'preparation') {
        $systemStatus = [
            'title' => 'พร้อมเปิดช่วงจัดตาราง',
            'pill' => 'p-success',
            'label' => 'พร้อมดำเนินการ',
            'desc' => 'ข้อมูลหลักพร้อมแล้ว สามารถเปิดช่วงจัดตารางให้หัวหน้าวิชาเริ่มจัดการรายวิชาได้',
            'actionLabel' => 'จัดการสถานะระบบ',
            'actionRoute' => route('admin.settings', ['tab' => 'academic']),
            'tone' => 'success',
        ];
    } elseif ($currentPhase === 'scheduling') {
        $systemStatus = [
            'title' => 'กำลังเปิดจัดตาราง',
            'pill' => 'p-success',
            'label' => 'สถานะระบบ',
            'desc' => $pendingOfferings > 0
                ? "ติดตามรายวิชาที่รออนุมัติ {$pendingOfferings} วิชา และความคืบหน้าการจัดตารางของแต่ละรายวิชา"
                : 'ติดตามความคืบหน้าการจัดตารางและรายการที่ส่งอนุมัติจากหัวหน้าวิชา',
            'actionLabel' => 'จัดการสถานะระบบ',
            'actionRoute' => route('admin.settings', ['tab' => 'academic']),
            'tone' => 'success',
        ];
    } else {
        $systemStatus = [
            'title' => 'เผยแพร่ตารางแล้ว',
            'pill' => 'p-info',
            'label' => 'เสร็จสิ้นรอบนี้',
            'desc' => 'ตารางสอนเผยแพร่แล้ว ใช้หน้านี้ติดตามข้อมูลสรุปและความเคลื่อนไหวของระบบ',
            'actionLabel' => 'จัดการสถานะระบบ',
            'actionRoute' => route('admin.settings', ['tab' => 'academic']),
            'tone' => 'info',
        ];
    }

    $nextPhaseLabel = match($currentPhase) {
        'preparation' => 'เปิดช่วงจัดตาราง',
        'scheduling' => 'เผยแพร่ตาราง',
        'published' => 'เริ่มรอบปีการศึกษาใหม่',
        default => 'เปิดช่วงจัดตาราง',
    };
    $criticalKeys = array_column($criticals ?? [], 'key');
    $activeCourseCount = $stats['courses']['active'] ?? 0;

    $readinessItems = [
        [
            'label' => 'ปีการศึกษา',
            'meta' => $currentAcademicYear
                ? 'ปีการศึกษา ' . $currentAcademicYear->name
                : 'ยังไม่ได้ตั้งค่า',
            'status' => $currentAcademicYear ? 'พร้อม' : 'ยังไม่ตั้งค่า',
            'tone' => $currentAcademicYear ? 'success' : 'warning',
        ],
        [
            'label' => 'เงื่อนไขสำคัญ',
            'meta' => $criticalCount > 0 ? $criticalCount . ' รายการ' : 'ผ่าน',
            'status' => $criticalCount > 0 ? 'ต้องแก้ไข' : 'พร้อม',
            'tone' => $criticalCount > 0 ? 'conflict' : 'success',
            'href' => route('admin.alerts'),
            'action' => 'ไปดูเงื่อนไขสำคัญ',
        ],
        [
            'label' => 'รายการตรวจสอบ',
            'meta' => $warningCount > 0 ? $warningCount . ' รายการ' : 'ไม่มี',
            'status' => $warningCount > 0 ? 'ควรตรวจสอบ' : 'พร้อม',
            'tone' => $warningCount > 0 ? 'warning' : 'success',
            'href' => route('admin.alerts'),
            'action' => 'ไปดูรายการตรวจสอบ',
        ],
        [
            'label' => 'รายวิชาเปิดสอน',
            'meta' => number_format($activeCourseCount) . ' วิชา',
            'status' => in_array('no_active_course', $criticalKeys, true) ? 'ต้องแก้ไข' : 'พร้อม',
            'tone' => in_array('no_active_course', $criticalKeys, true) ? 'conflict' : 'success',
            'href' => route('admin.master_data') . '?tab=courses',
            'action' => 'ไปจัดการรายวิชาเปิดสอน',
        ],
        [
            'label' => 'หัวหน้าวิชา',
            'meta' => in_array('active_courses_missing_head', $criticalKeys, true) ? 'ยังไม่ครบ' : 'ครบ',
            'status' => in_array('active_courses_missing_head', $criticalKeys, true) ? 'ต้องแก้ไข' : 'พร้อม',
            'tone' => in_array('active_courses_missing_head', $criticalKeys, true) ? 'conflict' : 'success',
            'href' => route('admin.master_data') . '?tab=courses',
            'action' => 'ไปจัดการหัวหน้าวิชา',
        ],
    ];

    // Visual phase stepper — lifecycle รอบปีการศึกษา (เตรียม → เปิดจัดตาราง → เผยแพร่)
    $phaseOrder = ['preparation', 'scheduling', 'published'];
    $currentPhaseIndex = $currentAcademicYear ? array_search($currentPhase, $phaseOrder, true) : -1;
    $phaseSteps = [
        ['label' => 'เตรียมข้อมูล', 'sub' => 'ตั้งค่า + ข้อมูลหลัก'],
        ['label' => 'เปิดจัดตาราง', 'sub' => 'หัวหน้าวิชาจัดตาราง'],
        ['label' => 'เผยแพร่ตาราง', 'sub' => 'อนุมัติ + แจกตาราง'],
    ];
    foreach ($phaseSteps as $idx => &$step) {
        $step['state'] = $currentPhaseIndex < 0
            ? 'upcoming'
            : ($idx < $currentPhaseIndex ? 'done' : ($idx === $currentPhaseIndex ? 'current' : 'upcoming'));
    }
    unset($step);
@endphp

<div class="card admin-hero-card" data-testid="admin-hero">
    <div class="admin-hero-top">
        <div class="admin-hero-copy">
            <div class="admin-hero-kicker">ภาพรวม / ผู้ดูแลระบบ</div>
            <h1>ภาพรวมระบบ</h1>
        </div>

        <div class="admin-status-control-row">
            <span class="admin-year-badge {{ $currentAcademicYear ? '' : 'is-warning' }}">
                @if($currentAcademicYear)
                    ปีการศึกษา {{ $currentAcademicYear->name }}
                @else
                    ยังไม่ได้ตั้งค่าปีการศึกษา
                @endif
            </span>
            <a href="{{ $systemStatus['actionRoute'] }}"
               class="btn btn-primary admin-hero-action"
               data-testid="system-settings-shortcut">
                จัดการสถานะระบบ
            </a>
        </div>
    </div>

    <div class="admin-status-banner is-{{ $systemStatus['tone'] }}">
        <div class="admin-status-primary">
            <div class="admin-status-state">
                <span class="admin-status-marker" aria-hidden="true"></span>
                <div class="admin-status-text">
                    <div class="admin-status-label">สถานะระบบปัจจุบัน</div>
                    <div class="admin-status-title">{{ $systemStatus['title'] }}</div>
                    <div class="admin-status-meta">
                        <span class="pill {{ $systemStatus['pill'] }}">{{ $phaseMeta['label'] }}</span>
                        <span>{{ $systemStatus['label'] }}</span>
                    </div>
                </div>
            </div>

            <div class="admin-status-desc">{{ $systemStatus['desc'] }}</div>
            <div class="admin-status-next">
                ขั้นตอนถัดไป: {{ $systemStatus['actionLabel'] }} · {{ $nextPhaseLabel }}
            </div>

            <div class="admin-phase-stepper" aria-label="ขั้นตอนรอบปีการศึกษา" data-testid="admin-phase-stepper">
                @foreach($phaseSteps as $idx => $step)
                    <div class="phase-step is-{{ $step['state'] }}">
                        <div class="phase-step-node" aria-hidden="true">
                            @if($step['state'] === 'done')
                                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                            @elseif($step['state'] === 'current')
                                <span class="phase-step-pulse"></span>
                            @else
                                <span>{{ $idx + 1 }}</span>
                            @endif
                        </div>
                        <div class="phase-step-text">
                            <div class="phase-step-label">{{ $step['label'] }}</div>
                            <div class="phase-step-sub">{{ $step['sub'] }}</div>
                        </div>
                    </div>
                    @if(! $loop->last)
                        <div class="phase-step-bar {{ $idx < $currentPhaseIndex ? 'is-filled' : '' }}" aria-hidden="true"></div>
                    @endif
                @endforeach
            </div>
        </div>

        <div class="admin-status-summary" aria-label="สรุปความพร้อมระบบ">
            @foreach(array_slice($readinessItems, 1) as $item)
                @php
                    $toneIcon = [
                        'success'  => '<path d="M20 6L9 17l-5-5"/>',
                        'warning'  => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
                        'conflict' => '<circle cx="12" cy="12" r="9"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>',
                    ][$item['tone']] ?? '<path d="M20 6L9 17l-5-5"/>';
                @endphp
                <a href="{{ $item['href'] }}"
                   class="admin-status-summary-item is-{{ $item['tone'] }}"
                   aria-label="{{ $item['action'] }}">
                    <span class="admin-summary-icon is-{{ $item['tone'] }}" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">{!! $toneIcon !!}</svg>
                    </span>
                    <span class="admin-summary-label">{{ $item['label'] }}</span>
                    <strong>{{ $item['meta'] }}</strong>
                    <span class="admin-summary-status">{{ $item['status'] }}</span>
                </a>
            @endforeach
        </div>
    </div>
</div>

<style>
    .admin-hero-card {
        padding: 24px;
        background: var(--surface);
        border-color: color-mix(in oklch, var(--brand-navy) 10%, var(--border));
        overflow: hidden;
    }

    .admin-hero-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: nowrap;
        margin-bottom: 22px;
    }

    .admin-hero-copy {
        flex: 1 1 0;
        min-width: 0;
    }

    .admin-hero-kicker {
        margin-bottom: 4px;
        font-size: 12px;
        font-weight: 700;
        line-height: 1.35;
        color: var(--fg-3);
    }

    .admin-hero-copy h1 {
        margin: 0;
        font-family: var(--font-display);
        font-size: 24px;
        font-weight: 800;
        line-height: 1.25;
        color: var(--fg-1);
    }

    .admin-status-banner {
        display: grid;
        position: relative;
        overflow: hidden;
        grid-template-columns: minmax(0, 1fr) minmax(0, 0.9fr);
        gap: 28px;
        align-items: center;
        min-height: 126px;
        padding: 22px 24px;
        border: 1px solid var(--border);
        border-radius: var(--r-lg);
        background: var(--surface);
    }

    .admin-status-banner.is-conflict {
        border-color: var(--status-conflict-border);
        background:
            linear-gradient(180deg, color-mix(in oklch, var(--status-conflict) 7%, transparent), transparent 58%),
            color-mix(in oklch, var(--status-conflict) 3%, var(--surface));
    }

    .admin-status-banner.is-warning {
        border-color: var(--status-warning-border);
        background:
            linear-gradient(180deg, color-mix(in oklch, var(--status-warning) 8%, transparent), transparent 58%),
            color-mix(in oklch, var(--status-warning) 4%, var(--surface));
    }

    .admin-status-banner.is-success {
        border-color: var(--status-success-border);
        background:
            linear-gradient(180deg, color-mix(in oklch, var(--status-success) 7%, transparent), transparent 58%),
            color-mix(in oklch, var(--status-success) 3%, var(--surface));
    }

    .admin-status-banner.is-info {
        border-color: var(--status-info-border);
        background:
            linear-gradient(180deg, color-mix(in oklch, var(--status-info) 7%, transparent), transparent 58%),
            color-mix(in oklch, var(--status-info) 3%, var(--surface));
    }

    .admin-status-primary {
        min-width: 0;
    }

    .admin-status-control-row {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        justify-content: flex-end;
        flex: 0 0 auto;
        min-width: 0;
        align-self: flex-start;
    }

    .admin-year-badge {
        display: inline-flex;
        align-items: center;
        min-height: 36px;
        padding: 7px 16px;
        border: 1px solid color-mix(in oklch, var(--brand-navy) 10%, var(--border));
        border-radius: var(--r-pill);
        background: color-mix(in oklch, var(--brand-navy) 7%, var(--surface));
        color: var(--brand-navy);
        font-size: 13px;
        font-weight: 800;
        line-height: 1.2;
        white-space: normal;
        text-align: center;
    }

    .admin-year-badge.is-warning {
        border-color: var(--status-warning-border);
        background: var(--status-warning-bg);
        color: var(--status-warning-fg);
    }

    .admin-hero-action {
        min-height: 36px;
        padding-inline: 18px;
        text-decoration: none;
        white-space: normal;
        text-align: center;
    }

    .admin-status-state {
        display: flex;
        align-items: center;
        gap: 14px;
        min-width: 0;
    }

    .admin-status-marker {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: var(--status-success-fg);
        box-shadow: 0 0 0 5px color-mix(in oklch, var(--status-success) 13%, transparent);
        flex-shrink: 0;
    }

    .admin-status-banner.is-conflict .admin-status-marker {
        background: var(--status-conflict-fg);
        box-shadow: 0 0 0 5px color-mix(in oklch, var(--status-conflict) 13%, transparent);
    }

    .admin-status-banner.is-warning .admin-status-marker {
        background: var(--status-warning-fg);
        box-shadow: 0 0 0 5px color-mix(in oklch, var(--status-warning) 15%, transparent);
    }

    .admin-status-banner.is-info .admin-status-marker {
        background: var(--status-info-fg);
        box-shadow: 0 0 0 5px color-mix(in oklch, var(--status-info) 13%, transparent);
    }

    .admin-status-text {
        min-width: 0;
    }

    .admin-status-label {
        margin-bottom: 2px;
        color: var(--fg-3);
        font-size: 12px;
        font-weight: 700;
        line-height: 1.35;
    }

    .admin-status-title {
        font-family: var(--font-display);
        color: var(--fg-1);
        font-size: 21px;
        font-weight: 800;
        line-height: 1.25;
    }

    .admin-status-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 8px;
        color: var(--fg-3);
        font-size: 12px;
        font-weight: 700;
        line-height: 1.35;
    }

    .admin-status-desc {
        margin-top: 12px;
        max-width: 68ch;
        color: var(--fg-2);
        font-size: 12.5px;
        line-height: 1.6;
    }

    .admin-status-next {
        margin-top: 8px;
        color: var(--fg-3);
        font-size: 12px;
        font-weight: 700;
        line-height: 1.45;
    }

    /* ---- Unified phase visual inside the current status banner ---- */
    .admin-phase-stepper {
        display: flex;
        align-items: flex-start;
        gap: 4px;
        margin-top: 18px;
        padding: 12px 14px;
        border: 1px solid color-mix(in oklch, var(--brand-navy) 9%, var(--border));
        border-radius: var(--r-md);
        background: color-mix(in oklch, var(--surface) 78%, transparent);
    }
    .phase-step {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        flex: 0 0 auto;
        width: clamp(86px, 24%, 132px);
        gap: 7px;
    }
    .phase-step-node {
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        border: 1px solid var(--border);
        background: var(--surface);
        color: var(--fg-3);
        font-family: var(--font-display);
        font-size: 13px;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
        flex-shrink: 0;
    }
    .phase-step.is-done .phase-step-node {
        background: var(--brand-navy);
        border-color: var(--brand-navy);
        color: var(--surface);
    }
    .phase-step.is-current .phase-step-node {
        border: 2px solid currentColor;
        color: var(--status-success-fg);
        background: color-mix(in oklch, var(--status-success) 9%, var(--surface));
        box-shadow: 0 0 0 4px color-mix(in oklch, var(--status-success) 13%, transparent);
    }
    .admin-status-banner.is-conflict .phase-step.is-current .phase-step-node {
        color: var(--status-conflict-fg);
        background: color-mix(in oklch, var(--status-conflict) 9%, var(--surface));
        box-shadow: 0 0 0 4px color-mix(in oklch, var(--status-conflict) 13%, transparent);
    }
    .admin-status-banner.is-warning .phase-step.is-current .phase-step-node {
        color: var(--status-warning-fg);
        background: color-mix(in oklch, var(--status-warning) 10%, var(--surface));
        box-shadow: 0 0 0 4px color-mix(in oklch, var(--status-warning) 15%, transparent);
    }
    .admin-status-banner.is-info .phase-step.is-current .phase-step-node {
        color: var(--status-info-fg);
        background: color-mix(in oklch, var(--status-info) 9%, var(--surface));
        box-shadow: 0 0 0 4px color-mix(in oklch, var(--status-info) 13%, transparent);
    }
    .phase-step-pulse {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: currentColor;
    }
    .phase-step-label {
        font-size: 12px;
        font-weight: 700;
        line-height: 1.3;
        color: var(--fg-3);
    }
    .phase-step.is-done .phase-step-label,
    .phase-step.is-current .phase-step-label {
        color: var(--fg-1);
    }
    .phase-step-sub {
        margin-top: 2px;
        font-size: 10.5px;
        line-height: 1.35;
        color: var(--fg-3);
    }
    .phase-step-bar {
        flex: 1 1 auto;
        min-width: 16px;
        height: 2px;
        margin-top: 15px;
        background: var(--border);
        border-radius: 2px;
    }
    .phase-step-bar.is-filled {
        background: var(--brand-navy);
    }

    .admin-status-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(108px, 1fr));
        gap: 8px;
        min-width: 0;
    }

    .admin-status-summary-item {
        display: grid;
        grid-template-columns: 28px minmax(0, 1fr);
        grid-template-rows: auto auto auto;
        column-gap: 10px;
        align-content: center;
        align-items: center;
        min-width: 0;
        min-height: 66px;
        padding: 10px 12px;
        border: 1px solid var(--border);
        border-radius: var(--r-sm);
        background: color-mix(in oklch, var(--bg-2) 78%, var(--surface));
        text-align: left;
        text-decoration: none;
        cursor: pointer;
        transition:
            transform 160ms ease,
            border-color 160ms ease,
            background 160ms ease,
            box-shadow 160ms ease;
    }

    .admin-status-summary-item:hover,
    .admin-status-summary-item:focus-visible {
        transform: translateY(-1px);
        border-color: color-mix(in oklch, var(--brand-navy) 22%, var(--border));
        background: color-mix(in oklch, var(--brand-navy) 5%, var(--surface));
        box-shadow: 0 10px 22px color-mix(in oklch, var(--brand-navy) 10%, transparent);
        outline: none;
    }

    .admin-status-summary-item.is-conflict:hover,
    .admin-status-summary-item.is-conflict:focus-visible {
        border-color: var(--status-conflict-border);
        background: color-mix(in oklch, var(--status-conflict) 7%, var(--surface));
    }

    .admin-status-summary-item.is-warning:hover,
    .admin-status-summary-item.is-warning:focus-visible {
        border-color: var(--status-warning-border);
        background: color-mix(in oklch, var(--status-warning) 8%, var(--surface));
    }

    .admin-status-summary-item.is-success:hover,
    .admin-status-summary-item.is-success:focus-visible {
        border-color: var(--status-success-border);
        background: color-mix(in oklch, var(--status-success) 7%, var(--surface));
    }

    .admin-status-summary-item.is-info:hover,
    .admin-status-summary-item.is-info:focus-visible {
        border-color: var(--status-info-border);
        background: color-mix(in oklch, var(--status-info) 7%, var(--surface));
    }

    .admin-summary-icon {
        display: inline-flex;
        grid-column: 1;
        grid-row: 1 / span 3;
        align-items: center;
        align-self: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        border-radius: 50%;
    }
    .admin-summary-icon.is-success {
        color: var(--status-success-fg);
        background: color-mix(in oklch, var(--status-success) 14%, transparent);
    }
    .admin-summary-icon.is-warning {
        color: var(--status-warning-fg);
        background: color-mix(in oklch, var(--status-warning) 16%, transparent);
    }
    .admin-summary-icon.is-conflict {
        color: var(--status-conflict-fg);
        background: color-mix(in oklch, var(--status-conflict) 14%, transparent);
    }

    .admin-summary-label {
        grid-column: 2;
        color: var(--fg-3);
        font-size: 11.5px;
        font-weight: 700;
        line-height: 1.25;
    }

    .admin-status-summary-item strong {
        grid-column: 2;
        margin-top: 2px;
        color: var(--fg-1);
        font-family: var(--font-display);
        font-size: clamp(16px, 1.35vw, 19px);
        font-weight: 800;
        line-height: 1.15;
        overflow-wrap: anywhere;
    }

    .admin-summary-status {
        grid-column: 2;
        margin-top: 1px;
        color: var(--fg-3);
        font-size: 11px;
        font-weight: 700;
        line-height: 1.25;
    }

    .admin-status-summary-item.is-conflict strong,
    .admin-status-summary-item.is-conflict .admin-summary-status {
        color: var(--status-conflict-fg);
    }

    .admin-status-summary-item.is-warning strong,
    .admin-status-summary-item.is-warning .admin-summary-status {
        color: var(--status-warning-fg);
    }

    .admin-status-summary-item.is-success strong,
    .admin-status-summary-item.is-success .admin-summary-status {
        color: var(--status-success-fg);
    }

    @media (max-width: 1600px) {
        .admin-status-banner {
            grid-template-columns: 1fr;
            gap: 18px;
        }
    }

    @media (max-width: 720px) {
        .admin-hero-card {
            padding: 18px;
        }

        .admin-hero-top {
            flex-direction: column;
            align-items: stretch;
            margin-bottom: 16px;
        }

        .admin-status-control-row {
            flex: 1 1 100%;
            justify-content: flex-start;
            align-self: auto;
        }

        .admin-hero-copy h1 {
            font-size: 22px;
        }

        .admin-status-banner {
            padding: 16px 16px 16px 26px;
            min-height: 0;
        }

        .admin-status-control-row {
            align-items: stretch;
        }

        .admin-year-badge,
        .admin-hero-action {
            width: 100%;
            justify-content: center;
            white-space: normal;
            text-align: center;
        }

        .admin-status-state {
            align-items: flex-start;
        }

        .admin-status-title {
            font-size: 19px;
        }

        .admin-status-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 540px) {
        .admin-hero-card {
            padding: 14px;
        }

        .admin-status-summary-item {
            min-height: 62px;
            padding: 10px 8px;
        }
    }

    @media (max-width: 420px) {
        .admin-status-summary {
            grid-template-columns: 1fr;
        }
    }
</style>
