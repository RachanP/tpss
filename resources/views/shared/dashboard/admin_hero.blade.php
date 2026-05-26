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
                ? $currentAcademicYear->name . ' / เทอม ' . $currentAcademicYear->semester
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
@endphp

<div class="card admin-hero-card" data-testid="admin-hero">
    <div class="admin-hero-top">
        <div class="admin-hero-copy">
            <div class="admin-hero-kicker">ภาพรวม / ผู้ดูแลระบบ</div>
            <h1>ภาพรวมระบบ</h1>
            <p>สรุปสถานะระบบ ความพร้อมข้อมูลพื้นฐาน และภาระงานสอนของคณะ</p>
        </div>

        <div class="admin-status-control-row">
            <span class="admin-year-badge {{ $currentAcademicYear ? '' : 'is-warning' }}">
                @if($currentAcademicYear)
                    ปีการศึกษา {{ $currentAcademicYear->name }} / เทอม {{ $currentAcademicYear->semester }}
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
        </div>

        <div class="admin-status-summary" aria-label="สรุปความพร้อมระบบ">
            @foreach(array_slice($readinessItems, 1) as $item)
                <a href="{{ $item['href'] }}"
                   class="admin-status-summary-item is-{{ $item['tone'] }}"
                   aria-label="{{ $item['action'] }}">
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
        gap: 24px;
        flex-wrap: wrap;
        margin-bottom: 22px;
    }

    .admin-hero-copy {
        flex: 1 1 360px;
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

    .admin-hero-copy p {
        margin: 8px 0 0;
        max-width: 760px;
        font-size: 13px;
        line-height: 1.65;
        color: var(--fg-3);
    }

    .admin-status-banner {
        display: grid;
        position: relative;
        overflow: hidden;
        grid-template-columns: minmax(0, 1fr) minmax(0, 0.9fr);
        gap: 28px;
        align-items: center;
        min-height: 126px;
        padding: 22px 24px 22px 34px;
        border: 1px solid var(--border);
        border-radius: var(--r-lg);
        background: var(--surface);
    }

    .admin-status-banner::before {
        content: "";
        position: absolute;
        inset: 0 auto 0 0;
        width: 5px;
        background: var(--border);
        pointer-events: none;
    }

    .admin-status-banner.is-conflict {
        border-color: var(--status-conflict-border);
        background: color-mix(in oklch, var(--status-conflict) 4%, var(--surface));
    }

    .admin-status-banner.is-conflict::before {
        background: var(--status-conflict-fg);
    }

    .admin-status-banner.is-warning {
        border-color: var(--status-warning-border);
        background: color-mix(in oklch, var(--status-warning) 5%, var(--surface));
    }

    .admin-status-banner.is-warning::before {
        background: var(--status-warning-fg);
    }

    .admin-status-banner.is-success {
        border-color: var(--status-success-border);
        background: color-mix(in oklch, var(--status-success) 4%, var(--surface));
    }

    .admin-status-banner.is-success::before {
        background: var(--status-success-fg);
    }

    .admin-status-banner.is-info {
        border-color: var(--status-info-border);
        background: color-mix(in oklch, var(--status-info) 4%, var(--surface));
    }

    .admin-status-banner.is-info::before {
        background: var(--status-info-fg);
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
        flex: 0 1 430px;
        min-width: 0;
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

    .admin-status-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(108px, 1fr));
        gap: 10px;
        min-width: 0;
    }

    .admin-status-summary-item {
        display: flex;
        flex-direction: column;
        justify-content: center;
        min-width: 0;
        min-height: 82px;
        padding: 12px 10px;
        border: 1px solid var(--border);
        border-radius: var(--r-sm);
        background: color-mix(in oklch, var(--bg-2) 78%, var(--surface));
        text-align: center;
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

    .admin-summary-label {
        color: var(--fg-3);
        font-size: 11.5px;
        font-weight: 700;
        line-height: 1.3;
    }

    .admin-status-summary-item strong {
        margin-top: 6px;
        color: var(--fg-1);
        font-family: var(--font-display);
        font-size: clamp(17px, 1.6vw, 20px);
        font-weight: 800;
        line-height: 1.15;
        overflow-wrap: anywhere;
    }

    .admin-summary-status {
        margin-top: 5px;
        color: var(--fg-3);
        font-size: 11px;
        font-weight: 700;
        line-height: 1.3;
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

        .admin-status-control-row {
            flex: 1 1 100%;
            justify-content: flex-start;
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
            justify-content: flex-start;
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
            min-height: 74px;
            padding: 10px 8px;
        }
    }

    @media (max-width: 420px) {
        .admin-status-summary {
            grid-template-columns: 1fr;
        }
    }
</style>
