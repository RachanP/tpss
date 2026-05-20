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
            'title' => 'ข้อมูลหลักเกือบพร้อม',
            'pill' => 'p-warning',
            'label' => 'มีรายการควรตรวจสอบ',
            'desc' => "ตรวจสอบข้อมูล {$warningCount} รายการก่อนเปิดช่วงจัดตาราง หากไม่กระทบการจัดตารางสามารถดำเนินการต่อได้",
            'actionLabel' => 'ตรวจสอบข้อมูล',
            'actionRoute' => route('admin.alerts'),
            'tone' => 'warning',
        ];
    } elseif ($currentPhase === 'preparation') {
        $systemStatus = [
            'title' => 'พร้อมเปิดช่วงจัดตาราง',
            'pill' => 'p-success',
            'label' => 'พร้อมดำเนินการ',
            'desc' => 'ข้อมูลหลักพร้อมแล้ว สามารถเปิดช่วงจัดตารางให้หัวหน้าวิชาเริ่มจัดการรายวิชาได้',
            'actionLabel' => 'เปิดช่วงจัดตาราง',
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
            'actionLabel' => 'ดูสถานะปีการศึกษา',
            'actionRoute' => route('admin.settings', ['tab' => 'academic']),
            'tone' => 'success',
        ];
    } else {
        $systemStatus = [
            'title' => 'เผยแพร่ตารางแล้ว',
            'pill' => 'p-info',
            'label' => 'เสร็จสิ้นรอบนี้',
            'desc' => 'ตารางสอนเผยแพร่แล้ว ใช้หน้านี้ติดตามข้อมูลสรุปและความเคลื่อนไหวของระบบ',
            'actionLabel' => 'ดูสถานะปีการศึกษา',
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
                : 'ยังไม่ตั้งค่า',
            'tone' => $currentAcademicYear ? 'success' : 'warning',
        ],
        [
            'label' => 'เงื่อนไขสำคัญ',
            'meta' => $criticalCount > 0 ? $criticalCount . ' รายการ' : 'ผ่าน',
            'tone' => $criticalCount > 0 ? 'conflict' : 'success',
        ],
        [
            'label' => 'รายการที่ควรตรวจสอบ',
            'meta' => $warningCount > 0 ? $warningCount . ' รายการ' : 'ไม่มี',
            'tone' => $warningCount > 0 ? 'warning' : 'success',
        ],
        [
            'label' => 'รายวิชาเปิดสอน',
            'meta' => number_format($activeCourseCount) . ' วิชา',
            'tone' => in_array('no_active_course', $criticalKeys, true) ? 'conflict' : 'success',
        ],
        [
            'label' => 'หัวหน้าวิชา',
            'meta' => in_array('active_courses_missing_head', $criticalKeys, true) ? 'ยังไม่ครบ' : 'ครบ',
            'tone' => in_array('active_courses_missing_head', $criticalKeys, true) ? 'conflict' : 'success',
        ],
    ];
@endphp

<div class="card" data-testid="admin-hero" style="margin-bottom: 18px; border-left: 4px solid var(--brand-navy);">
    <div style="padding: 22px 24px; display: grid; grid-template-columns: 1fr auto; gap: 24px; align-items: center;">

        {{-- LEFT: title + system status one-liner --}}
        <div style="min-width: 0;">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"
                     style="color: var(--brand-navy);">
                    <rect x="3" y="3" width="7" height="9"/>
                    <rect x="14" y="3" width="7" height="5"/>
                    <rect x="14" y="12" width="7" height="9"/>
                    <rect x="3" y="16" width="7" height="5"/>
                </svg>
                <div style="font-family: var(--font-display); font-size: 22px; font-weight: 700; color: var(--fg-1); line-height: 1.2;">
                    ภาพรวมของระบบ
                </div>
            </div>
            <div style="font-size: 13px; color: var(--fg-3); margin-bottom: 14px;">
                สรุปสถานะระบบ ความพร้อมข้อมูลพื้นฐาน และภาระงานสอนของคณะ
            </div>

            <div class="admin-status-card is-{{ $systemStatus['tone'] }}">
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 8px;">
                    <span class="pill {{ $systemStatus['pill'] }}">
                        <span class="pill-dot"></span>
                        {{ $systemStatus['label'] }}
                    </span>
                    <div style="font-family: var(--font-display); font-size: 20px; font-weight: 800; color: var(--fg-1); line-height: 1.25;">
                        {{ $systemStatus['title'] }}
                    </div>
                </div>
                <div style="font-size: 12.5px; color: var(--fg-2); line-height: 1.55;">
                    {{ $systemStatus['desc'] }}
                </div>
            </div>

            <div class="admin-readiness-grid" aria-label="สรุปความพร้อมระบบ">
                @foreach($readinessItems as $item)
                    <div class="admin-readiness-item is-{{ $item['tone'] }}">
                        <span class="admin-readiness-dot"></span>
                        <span class="admin-readiness-text">
                            <span class="admin-readiness-label">{{ $item['label'] }}</span>
                            <span class="admin-readiness-meta">{{ $item['meta'] }}</span>
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- RIGHT: academic year + phase + action --}}
        <div style="border-left: 1px solid var(--border); padding-left: 24px; min-width: 280px;">
            <div style="font-size: 10.5px; font-weight: 700; color: var(--fg-3); text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 6px;">
                สถานะระบบปัจจุบัน
            </div>

            @if($currentAcademicYear)
                <div style="font-family: var(--font-display); font-size: 18px; font-weight: 700; color: var(--fg-1); margin-bottom: 4px;">
                    ปีการศึกษา {{ $currentAcademicYear->name }} / เทอม {{ $currentAcademicYear->semester }}
                </div>
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 14px;">
                    <span class="pill {{ $phaseMeta['pill'] }}">
                        <span class="pill-dot"></span>
                        {{ $phaseMeta['label'] }}
                    </span>
                </div>
                <div style="font-size: 11.5px; color: var(--fg-3); margin-bottom: 12px; line-height: 1.45;">
                    {{ $phaseMeta['desc'] }}
                </div>
                <div class="admin-current-phase is-{{ $systemStatus['tone'] }}" aria-label="สถานะรอบการจัดตารางปัจจุบัน">
                    <div class="admin-current-phase-row">
                        <span class="admin-current-phase-dot"></span>
                        <span>สถานะปัจจุบัน: {{ $phaseMeta['label'] }}</span>
                    </div>
                    <div class="admin-current-phase-next">ขั้นตอนถัดไป: {{ $nextPhaseLabel }}</div>
                </div>
            @else
                <div style="font-family: var(--font-display); font-size: 15px; font-weight: 700; color: var(--status-warning-fg); margin-bottom: 6px;">
                    ยังไม่ได้ตั้งค่าปีการศึกษา
                </div>
                <div style="font-size: 11.5px; color: var(--fg-3); margin-bottom: 12px;">
                    กรุณาเพิ่มหรือเปิดใช้งานปีการศึกษาในหน้าตั้งค่าระบบ
                </div>
            @endif

            <a href="{{ $systemStatus['actionRoute'] }}" class="btn btn-primary"
               data-testid="system-settings-shortcut" style="text-decoration: none; width: 100%; justify-content: center;">
                {{ $systemStatus['actionLabel'] }}
                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
            </a>
        </div>
    </div>
</div>

<style>
    .admin-status-card {
        border: 1px solid var(--border);
        border-radius: var(--r-md);
        padding: 14px 16px;
        max-width: 760px;
    }
    .admin-status-card.is-conflict {
        border-color: color-mix(in oklch, var(--status-conflict) 35%, var(--border));
        background: color-mix(in oklch, var(--status-conflict) 7%, var(--surface));
    }
    .admin-status-card.is-warning {
        border-color: color-mix(in oklch, var(--status-warning) 35%, var(--border));
        background: color-mix(in oklch, var(--status-warning) 8%, var(--surface));
    }
    .admin-status-card.is-success {
        border-color: color-mix(in oklch, var(--status-success) 32%, var(--border));
        background: color-mix(in oklch, var(--status-success) 7%, var(--surface));
    }
    .admin-status-card.is-info {
        border-color: color-mix(in oklch, var(--status-info) 30%, var(--border));
        background: color-mix(in oklch, var(--status-info) 7%, var(--surface));
    }
    .admin-readiness-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(112px, 1fr));
        gap: 8px;
        max-width: 760px;
        margin-top: 10px;
    }
    .admin-readiness-item {
        display: flex;
        align-items: center;
        gap: 8px;
        min-width: 0;
        padding: 9px 10px;
        border: 1px solid var(--border);
        border-radius: var(--r-sm);
        background: var(--surface);
    }
    .admin-readiness-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--fg-3);
        flex-shrink: 0;
    }
    .admin-readiness-text {
        display: flex;
        flex-direction: column;
        min-width: 0;
        gap: 1px;
    }
    .admin-readiness-label {
        color: var(--fg-2);
        font-size: 10.5px;
        font-weight: 700;
        line-height: 1.25;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .admin-readiness-meta {
        color: var(--fg-3);
        font-size: 11px;
        line-height: 1.25;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .admin-readiness-item.is-conflict {
        border-color: color-mix(in oklch, var(--status-conflict) 28%, var(--border));
        background: color-mix(in oklch, var(--status-conflict) 5%, var(--surface));
    }
    .admin-readiness-item.is-conflict .admin-readiness-dot { background: var(--status-conflict); }
    .admin-readiness-item.is-conflict .admin-readiness-label { color: var(--status-conflict-fg); }
    .admin-readiness-item.is-warning {
        border-color: color-mix(in oklch, var(--status-warning) 30%, var(--border));
        background: color-mix(in oklch, var(--status-warning) 6%, var(--surface));
    }
    .admin-readiness-item.is-warning .admin-readiness-dot { background: var(--status-warning); }
    .admin-readiness-item.is-warning .admin-readiness-label { color: var(--status-warning-fg); }
    .admin-readiness-item.is-success {
        border-color: color-mix(in oklch, var(--status-success) 22%, var(--border));
        background: color-mix(in oklch, var(--status-success) 4%, var(--surface));
    }
    .admin-readiness-item.is-success .admin-readiness-dot { background: var(--status-success); }
    .admin-readiness-item.is-success .admin-readiness-label { color: var(--status-success-fg); }

    .admin-current-phase {
        margin: 2px 0 14px;
        padding: 9px 10px;
        border: 1px solid var(--border);
        border-radius: var(--r-sm);
        background: var(--surface);
    }
    .admin-current-phase.is-conflict {
        border-color: color-mix(in oklch, var(--status-conflict) 28%, var(--border));
        background: color-mix(in oklch, var(--status-conflict) 5%, var(--surface));
        color: var(--status-conflict-fg);
    }
    .admin-current-phase.is-warning {
        border-color: color-mix(in oklch, var(--status-warning) 30%, var(--border));
        background: color-mix(in oklch, var(--status-warning) 6%, var(--surface));
        color: var(--status-warning-fg);
    }
    .admin-current-phase.is-success {
        border-color: color-mix(in oklch, var(--status-success) 28%, var(--border));
        background: color-mix(in oklch, var(--status-success) 6%, var(--surface));
        color: var(--status-success-fg);
    }
    .admin-current-phase.is-info {
        border-color: color-mix(in oklch, var(--status-info) 28%, var(--border));
        background: color-mix(in oklch, var(--status-info) 6%, var(--surface));
        color: var(--status-info-fg);
    }
    .admin-current-phase-row {
        display: flex;
        align-items: center;
        gap: 7px;
        font-size: 11.5px;
        font-weight: 800;
        line-height: 1.25;
    }
    .admin-current-phase-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: currentColor;
        flex-shrink: 0;
    }
    .admin-current-phase-next {
        margin-top: 5px;
        padding-left: 14px;
        color: var(--fg-3);
        font-size: 11px;
        line-height: 1.35;
    }

    @media (max-width: 900px) {
        [data-testid="admin-hero"] > div {
            grid-template-columns: 1fr !important;
        }
        [data-testid="admin-hero"] > div > div:last-child {
            border-left: none !important;
            border-top: 1px solid var(--border) !important;
            padding-left: 0 !important;
            padding-top: 18px !important;
            min-width: 0 !important;
        }
    }
    @media (max-width: 1180px) {
        .admin-readiness-grid { grid-template-columns: repeat(3, minmax(120px, 1fr)); }
    }
    @media (max-width: 540px) {
        .admin-readiness-grid { grid-template-columns: 1fr; }
    }
</style>
