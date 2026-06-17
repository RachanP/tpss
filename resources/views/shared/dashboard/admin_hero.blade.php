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
    $instructorCount = isset($instructors) ? $instructors->count() : 0;
    $roomTotal = $stats['rooms']['total'] ?? 0;

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
        [
            'label' => 'อาจารย์ผู้สอน',
            'meta' => number_format($instructorCount) . ' คน',
            'status' => $instructorCount > 0 ? 'พร้อม' : 'ยังไม่มี',
            'tone' => $instructorCount > 0 ? 'success' : 'warning',
            'href' => route('admin.users'),
            'action' => 'ไปจัดการผู้ใช้',
        ],
        [
            'label' => 'ห้อง / สถานที่',
            'meta' => number_format($roomTotal) . ' รายการ',
            'status' => $roomTotal > 0 ? 'พร้อม' : 'ยังไม่มี',
            'tone' => $roomTotal > 0 ? 'success' : 'warning',
            'href' => route('admin.master_data', ['tab' => 'location_types']),
            'action' => 'ไปจัดการห้อง / สถานที่',
        ],
    ];

    // Visual phase stepper — lifecycle รอบปีการศึกษา (เตรียม → เปิดจัดตาราง → เผยแพร่)
    $phaseOrder = ['preparation', 'scheduling', 'published'];
    $currentPhaseIndex = $currentAcademicYear ? array_search($currentPhase, $phaseOrder, true) : -1;
    $phaseSteps = [
        ['label' => 'เตรียมข้อมูล', 'sub' => 'ตั้งค่า + ข้อมูลหลัก'],
        ['label' => 'เปิดจัดตาราง', 'sub' => 'หัวหน้าวิชาจัดตาราง'],
        ['label' => 'เผยแพร่ตาราง', 'sub' => 'อนุมัติ + เผยแพร่ตาราง'],
    ];
    foreach ($phaseSteps as $idx => &$step) {
        $step['state'] = $currentPhaseIndex < 0
            ? 'upcoming'
            : ($idx < $currentPhaseIndex ? 'done' : ($idx === $currentPhaseIndex ? 'current' : 'upcoming'));
    }
    unset($step);

    // ⭐ Phase-aware summary — ช่องสรุปสลับเนื้อหาตามสถานะระบบ
    //    เตรียม = ความพร้อมข้อมูล · เปิดจัดตาราง = ความคืบหน้า · เผยแพร่ = สรุปรอบ
    $pipe = $pipeline ?? ['draft' => 0, 'pending' => 0, 'published' => 0, 'rejected' => 0];

    if ($currentPhase === 'scheduling') {
        $summaryHeading = 'ความคืบหน้าการจัดตาราง';
        $summaryItems = [
            [
                'label' => 'รออนุมัติ',
                'meta' => number_format($pipe['pending']) . ' วิชา',
                'status' => $pipe['pending'] > 0 ? 'รอผู้บริหาร' : 'ไม่มีค้าง',
                'tone' => $pipe['pending'] > 0 ? 'warning' : 'success',
                'href' => route('admin.alerts'),
                'action' => 'ดูรายการรออนุมัติ',
            ],
            [
                'label' => 'ตีกลับ',
                'meta' => number_format($pipe['rejected']) . ' วิชา',
                'status' => $pipe['rejected'] > 0 ? 'ต้องแก้ไข' : 'ไม่มี',
                'tone' => $pipe['rejected'] > 0 ? 'conflict' : 'success',
                'href' => route('admin.alerts'),
                'action' => 'ดูรายการตีกลับ',
            ],
            [
                'label' => 'อนุมัติแล้ว',
                'meta' => number_format($pipe['published']) . ' วิชา',
                'status' => 'เผยแพร่ได้',
                'tone' => 'success',
                'href' => route('admin.master_data', ['tab' => 'courses']),
                'action' => 'ดูรายวิชา',
            ],
            [
                'label' => 'กำลังจัด (ร่าง)',
                'meta' => number_format($pipe['draft']) . ' วิชา',
                'status' => $pipe['draft'] > 0 ? 'อยู่ระหว่างจัด' : 'ไม่มี',
                'tone' => 'info',
                'href' => route('admin.master_data', ['tab' => 'courses']),
                'action' => 'ดูรายวิชา',
            ],
        ];
    } elseif ($currentPhase === 'published') {
        $summaryHeading = 'สรุปรอบปีการศึกษา';
        $summaryItems = [
            [
                'label' => 'เผยแพร่แล้ว',
                'meta' => number_format($pipe['published']) . ' วิชา',
                'status' => 'ใช้งานได้',
                'tone' => 'success',
                'href' => route('admin.master_data', ['tab' => 'courses']),
                'action' => 'ดูรายวิชา',
            ],
            [
                'label' => 'รายวิชาเปิดสอน',
                'meta' => number_format($activeCourseCount) . ' วิชา',
                'status' => 'ทั้งรอบ',
                'tone' => 'info',
                'href' => route('admin.master_data', ['tab' => 'courses']),
                'action' => 'ดูรายวิชา',
            ],
            [
                'label' => 'อาจารย์ผู้สอน',
                'meta' => number_format($instructorCount) . ' คน',
                'status' => 'ในระบบ',
                'tone' => 'info',
                'href' => route('admin.users'),
                'action' => 'ไปจัดการผู้ใช้',
            ],
            [
                'label' => 'ห้อง / สถานที่',
                'meta' => number_format($roomTotal) . ' รายการ',
                'status' => 'ในระบบ',
                'tone' => 'info',
                'href' => route('admin.master_data', ['tab' => 'location_types']),
                'action' => 'ไปจัดการห้อง / สถานที่',
            ],
        ];
    } else {
        $summaryHeading = 'ความพร้อมข้อมูล';
        $summaryItems = array_slice($readinessItems, 3);
    }
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
                <div class="admin-status-text">
                    <div class="admin-status-label">
                        <span>สถานะระบบปัจจุบัน</span>
                    </div>
                    <div class="admin-status-headline">
                        <span class="admin-status-marker" aria-hidden="true"></span>
                        <div class="admin-status-title">{{ $systemStatus['title'] }}</div>
                    </div>
                </div>
            </div>

            <div class="admin-status-desc">{{ $systemStatus['desc'] }}</div>
            <div class="admin-status-next">
                ขั้นตอนถัดไป: {{ $systemStatus['actionLabel'] }} · {{ $nextPhaseLabel }}
            </div>

            <div class="admin-phase-stepper is-phase-{{ $currentPhaseIndex < 0 ? 'none' : $currentPhaseIndex }}" aria-label="ขั้นตอนรอบปีการศึกษา" data-testid="admin-phase-stepper">
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

        <div class="admin-status-summary" aria-label="{{ $summaryHeading }}">
            <div class="admin-summary-heading">{{ $summaryHeading }}</div>
            @foreach($summaryItems as $item)
                @php
                    $toneIcon = [
                        'success'  => '<path d="M20 6L9 17l-5-5"/>',
                        'warning'  => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
                        'conflict' => '<circle cx="12" cy="12" r="9"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>',
                        'info'     => '<circle cx="12" cy="12" r="9"/><path d="M12 8v4l2.5 1.5"/>',
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
        background:
            linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 4%, var(--surface)), var(--surface) 30%),
            var(--surface);
        border-color: color-mix(in oklch, var(--brand-navy) 26%, var(--border));
        overflow: hidden;
    }

    .admin-hero-card:hover,
    .admin-hero-card:focus-within {
        transform: none;
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
        color: var(--fg-2);
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
        border: 1px solid color-mix(in oklch, var(--brand-navy) 30%, var(--border));
        border-radius: var(--r-lg);
        background:
            radial-gradient(circle at 9% 4%, color-mix(in oklch, var(--brand-navy) 14%, transparent), transparent 28%),
            linear-gradient(135deg,
                color-mix(in oklch, var(--brand-navy) 13%, var(--surface)) 0%,
                color-mix(in oklch, var(--brand-navy) 5%, var(--surface)) 46%,
                var(--surface) 100%);
        box-shadow:
            0 1px 2px rgba(0, 36, 84, 0.1),
            0 20px 46px -34px rgba(0, 36, 84, 0.52),
            inset 0 1px 0 color-mix(in oklch, var(--surface) 84%, transparent);
        transition:
            border-color 180ms ease,
            box-shadow 180ms ease,
            background 180ms ease;
    }

    .admin-status-banner:hover,
    .admin-status-banner:focus-within {
        border-color: color-mix(in oklch, var(--brand-navy) 42%, var(--border));
        box-shadow:
            0 2px 4px rgba(0, 36, 84, 0.11),
            0 24px 52px -32px rgba(0, 36, 84, 0.58),
            inset 0 1px 0 color-mix(in oklch, var(--surface) 88%, transparent);
    }

    .admin-status-banner.is-conflict,
    .admin-status-banner.is-warning,
    .admin-status-banner.is-success,
    .admin-status-banner.is-info {
        border-color: color-mix(in oklch, var(--brand-navy) 30%, var(--border));
        background:
            radial-gradient(circle at 9% 4%, color-mix(in oklch, var(--brand-navy) 14%, transparent), transparent 28%),
            linear-gradient(135deg,
                color-mix(in oklch, var(--brand-navy) 10%, var(--surface)) 0%,
                color-mix(in oklch, var(--brand-navy) 4%, var(--surface)) 46%,
                var(--surface) 100%);
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
        border: 1px solid color-mix(in oklch, var(--brand-navy) 20%, var(--border));
        border-radius: var(--r-pill);
        background: color-mix(in oklch, var(--brand-navy) 10%, var(--surface));
        color: var(--brand-navy);
        font-size: 13px;
        font-weight: 700;
        line-height: 1.2;
        white-space: normal;
        text-align: center;
    }

    .admin-year-badge.is-warning {
        border-color: color-mix(in oklch, var(--brand-navy) 20%, var(--border));
        background: color-mix(in oklch, var(--brand-navy) 10%, var(--surface));
        color: var(--brand-navy);
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
        background: var(--brand-navy);
        box-shadow: 0 0 0 5px color-mix(in oklch, var(--brand-navy) 15%, transparent);
        flex-shrink: 0;
    }

    .admin-status-banner.is-conflict .admin-status-marker {
        background: var(--status-conflict);
        box-shadow: 0 0 0 5px color-mix(in oklch, var(--status-conflict) 22%, transparent);
    }

    .admin-status-banner.is-warning .admin-status-marker {
        background: var(--status-warning);
        box-shadow: 0 0 0 5px color-mix(in oklch, var(--status-warning) 22%, transparent);
    }

    .admin-status-banner.is-success .admin-status-marker {
        background: var(--status-success);
        box-shadow: 0 0 0 5px color-mix(in oklch, var(--status-success) 22%, transparent);
    }

    .admin-status-banner.is-info .admin-status-marker {
        background: var(--status-info);
        box-shadow: 0 0 0 5px color-mix(in oklch, var(--status-info) 22%, transparent);
    }

    .admin-status-text {
        min-width: 0;
    }

    /* จุด marker อยู่หน้า title (แถวเดียวกัน) — บรรทัดอื่นชิดซ้ายตามเดิม */
    .admin-status-headline {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .admin-status-label {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 6px;
        color: var(--fg-3);
        font-size: 12px;
        font-weight: 700;
        line-height: 1.35;
    }

    .admin-status-phase-pill {
        font-size: 11px;
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
        color: var(--fg-2);
        font-size: 12px;
        font-weight: 700;
        line-height: 1.45;
    }

    /* ---- Unified phase visual inside the current status banner ---- */
    .admin-phase-stepper {
        --phase-track-start: calc(16.666% + 10px);
        --phase-track-end: calc(83.333% - 10px);
        --phase-track-top: 38px;
        display: grid;
        position: relative;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        column-gap: 10px;
        margin-top: 18px;
        padding: 16px 18px 6px;
        border: 0;
        border-top: 1px solid color-mix(in oklch, var(--brand-navy) 14%, var(--border));
        background: transparent;
        overflow: hidden;
    }

    .admin-phase-stepper::before,
    .admin-phase-stepper::after {
        content: "";
        position: absolute;
        top: var(--phase-track-top);
        left: var(--phase-track-start);
        height: 2px;
        border-radius: 999px;
        pointer-events: none;
    }

    .admin-phase-stepper::before {
        right: calc(100% - var(--phase-track-end));
        background: color-mix(in oklch, var(--brand-navy) 24%, var(--border));
    }

    .admin-phase-stepper::after {
        width: 0;
        background: color-mix(in oklch, var(--brand-navy) 76%, var(--surface));
    }

    .admin-phase-stepper.is-phase-1::after {
        width: calc((var(--phase-track-end) - var(--phase-track-start)) / 2);
    }

    .admin-phase-stepper.is-phase-2::after {
        width: calc(var(--phase-track-end) - var(--phase-track-start));
    }

    .phase-step {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        position: relative;
        z-index: 1;
        min-width: 0;
        gap: 8px;
    }
    .phase-step-node {
        width: 42px;
        height: 42px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        border: 2px solid color-mix(in oklch, var(--brand-navy) 18%, var(--border));
        background: var(--surface);
        color: var(--fg-3);
        font-family: var(--font-display);
        font-size: 14px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        flex-shrink: 0;
        box-shadow: 0 1px 2px rgba(0, 36, 84, 0.06);
    }
    .phase-step.is-done .phase-step-node {
        background: linear-gradient(180deg,
            color-mix(in oklch, var(--brand-navy) 82%, var(--surface)),
            var(--brand-navy));
        border-color: var(--brand-navy);
        color: var(--surface);
        box-shadow: 0 1px 2px rgba(0, 36, 84, 0.10);
    }
    .phase-step.is-current .phase-step-node {
        border: 3px solid var(--brand-navy);
        color: var(--brand-navy);
        background: color-mix(in oklch, var(--brand-navy) 7%, var(--surface));
        box-shadow:
            inset 0 0 0 4px var(--surface),
            0 0 0 3px color-mix(in oklch, var(--brand-navy) 9%, transparent);
    }
    .phase-step-pulse {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: currentColor;
        box-shadow: 0 1px 2px rgba(0, 36, 84, 0.18);
    }
    .phase-step-text {
        min-width: 0;
        max-width: 150px;
    }
    .phase-step-label {
        font-size: 12.5px;
        font-weight: 700;
        line-height: 1.3;
        color: var(--fg-3);
    }
    .phase-step.is-done .phase-step-label,
    .phase-step.is-current .phase-step-label {
        color: var(--fg-1);
    }
    .phase-step-sub {
        margin-top: 3px;
        font-size: 11px;
        line-height: 1.4;
        color: var(--fg-3);
    }
    .phase-step-bar {
        display: none;
    }

    .admin-status-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        min-width: 0;
    }

    .admin-status-summary-item {
        --admin-summary-accent: var(--brand-navy);
        display: grid;
        grid-template-columns: 28px minmax(0, 1fr);
        grid-template-rows: auto auto auto;
        column-gap: 10px;
        align-content: center;
        align-items: center;
        min-width: 0;
        min-height: 66px;
        padding: 10px 12px;
        border: 1px solid color-mix(in oklch, var(--admin-summary-accent) 30%, var(--border));
        border-radius: var(--r-sm);
        background: color-mix(in oklch, var(--admin-summary-accent) 7%, var(--surface));
        text-align: left;
        text-decoration: none;
        cursor: pointer;
        box-shadow: 0 1px 2px rgba(0, 36, 84, 0.08);
        transition:
            transform 160ms ease,
            border-color 160ms ease,
            background 160ms ease,
            box-shadow 160ms ease;
    }

    .admin-status-summary-item:hover,
    .admin-status-summary-item:focus-visible {
        transform: translateY(-1px);
        border-color: color-mix(in oklch, var(--admin-summary-accent) 42%, var(--border));
        background: color-mix(in oklch, var(--admin-summary-accent) 11%, var(--surface));
        box-shadow: 0 6px 16px -8px color-mix(in oklch, var(--admin-summary-accent) 30%, transparent);
        outline: none;
    }

    .admin-status-summary-item.is-conflict {
        --admin-summary-accent: var(--status-conflict-fg);
    }

    .admin-status-summary-item.is-warning {
        --admin-summary-accent: var(--status-warning-fg);
    }

    .admin-status-summary-item.is-success {
        --admin-summary-accent: var(--brand-navy);
    }

    .admin-status-summary-item.is-info {
        --admin-summary-accent: var(--brand-navy);
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
        background: color-mix(in oklch, var(--status-success-fg) 14%, transparent);
    }
    .admin-summary-icon.is-warning {
        color: var(--status-warning-fg);
        background: color-mix(in oklch, var(--status-warning-fg) 14%, transparent);
    }
    .admin-summary-icon.is-conflict {
        color: var(--status-conflict-fg);
        background: color-mix(in oklch, var(--status-conflict-fg) 14%, transparent);
    }
    .admin-summary-icon.is-info {
        color: var(--brand-navy);
        background: color-mix(in oklch, var(--brand-navy) 12%, transparent);
    }

    .admin-summary-label {
        grid-column: 2;
        color: var(--fg-2);
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

    .admin-status-summary-item.is-success strong {
        color: var(--fg-1);
    }

    .admin-status-summary-item.is-success .admin-summary-status {
        color: var(--status-success-fg);
    }

    .admin-summary-heading {
        grid-column: 1 / -1;
        margin-bottom: 2px;
        font-size: 11.5px;
        font-weight: 700;
        line-height: 1.3;
        color: var(--fg-2);
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
            padding: 16px;
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
