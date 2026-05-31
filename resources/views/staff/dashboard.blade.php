<x-app-layout title="ภาพรวมเจ้าหน้าที่">
    @php
        $phaseLabels = [
            'preparation' => 'เตรียมข้อมูล',
            'scheduling' => 'เปิดจัดตาราง',
            'approving' => 'รออนุมัติ',
            'published' => 'เผยแพร่แล้ว',
        ];

        $phase = $currentAcademicYear?->phase;
        $phaseLabel = $phaseLabels[$phase] ?? ($phase ?: 'ไม่ระบุ');
        $phaseTone = match($phase) {
            'scheduling' => 'success',
            'published' => 'info',
            'preparation' => 'warning',
            default => $currentAcademicYear ? 'warning' : 'muted',
        };

        if ($staffPrimaryAlert) {
            $systemStatus = [
                'tone' => 'warning',
                'title' => 'ต้องอัปเดตข้อมูลหลัก',
                'label' => 'มีรายการที่ Staff แก้ได้',
                'desc' => 'ตรวจข้อมูลรายวิชา ห้อง และสถานที่ก่อนเริ่มจัดตาราง',
                'href' => $staffPrimaryAlert['href'],
                'action' => $staffPrimaryAlert['action_label'],
            ];
        } elseif (! $currentAcademicYear) {
            $systemStatus = [
                'tone' => 'warning',
                'title' => 'ยังไม่ได้ตั้งค่าปีการศึกษา',
                'label' => 'ต้องตั้งค่าก่อนใช้งาน',
                'desc' => 'เพิ่มปีการศึกษาและวันที่เริ่มต้นเพื่อให้ระบบประเมินความพร้อม',
                'href' => route('staff.settings', ['tab' => 'academic']),
                'action' => 'ตั้งค่าปีการศึกษา',
            ];
        } elseif ($phase === 'scheduling') {
            $systemStatus = [
                'tone' => 'success',
                'title' => 'กำลังเปิดจัดตาราง',
                'label' => 'Staff บันทึกตารางได้',
                'desc' => 'จัดตารางได้เฉพาะรายวิชาที่ได้รับมอบหมาย',
                'href' => route('staff.schedules.index'),
                'action' => 'เปิดตารางสอน',
            ];
        } elseif ($phase === 'published') {
            $systemStatus = [
                'tone' => 'info',
                'title' => 'เผยแพร่ตารางแล้ว',
                'label' => 'รอบนี้เสร็จสิ้น',
                'desc' => 'ใช้หน้านี้เพื่อติดตามสรุปและข้อมูลที่ต้องดูแล',
                'href' => route('staff.schedules.index'),
                'action' => 'ดูตารางสอน',
            ];
        } else {
            $systemStatus = [
                'tone' => 'success',
                'title' => 'พร้อมเตรียมข้อมูล',
                'label' => 'ตรวจข้อมูลก่อนเปิดจัดตาราง',
                'desc' => 'ข้อมูลหลักและรายวิชาที่เกี่ยวข้องพร้อมให้ตรวจสอบ',
                'href' => route('staff.master_data', ['tab' => 'courses']),
                'action' => 'ตรวจข้อมูลหลัก',
            ];
        }

        $conflictTotal = $conflictSummary['total'];
        $conflictValue = $conflictTotal === null ? 'รอข้อมูล' : number_format((int) $conflictTotal);
        $conflictHint = $conflictTotal === null ? 'ยังไม่มี summary ล่าสุด' : 'conflict/warning';

        $kpis = [
            [
                'label' => 'รายวิชาที่ดูแล',
                'value' => number_format($masterStats['assigned_courses']),
                'hint' => 'ผูกกับ Staff คนนี้',
                'href' => route('staff.master_data', ['tab' => 'courses']),
                'tone' => 'navy',
            ],
            [
                'label' => 'Offering รอบปัจจุบัน',
                'value' => number_format($masterStats['assigned_offerings']),
                'hint' => 'เฉพาะปีการศึกษาปัจจุบัน',
                'href' => route('staff.schedules.index'),
                'tone' => 'info',
            ],
            [
                'label' => 'ตารางที่บันทึกแล้ว',
                'value' => number_format($masterStats['staff_schedules']),
                'hint' => 'รายการสอนของ Staff',
                'href' => route('staff.schedules.index'),
                'tone' => 'success',
            ],
            [
                'label' => 'Conflict / Warning',
                'value' => $conflictValue,
                'hint' => $conflictHint,
                'href' => route('staff.reports.index'),
                'tone' => ($conflictTotal ?? 0) > 0 ? 'warning' : 'muted',
            ],
        ];
    @endphp

    <style>
        .staff-dash {
            display: flex;
            flex-direction: column;
            gap: 14px;
            padding: 24px 28px 28px;
            color: var(--fg-1);
        }
        .staff-card,
        .staff-kpi,
        .staff-panel,
        .staff-alert,
        .staff-offering,
        .staff-action-row,
        .staff-ready-row {
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
        }
        .staff-alert {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 12px 16px;
            color: var(--status-warning-fg);
            background: color-mix(in oklch, var(--status-warning) 10%, var(--surface));
            border-color: var(--status-warning-border);
            text-decoration: none;
            font-size: 14px;
            font-weight: 800;
            line-height: 1.45;
            transition: transform var(--dur-fast), box-shadow var(--dur-fast), border-color var(--dur-fast);
        }
        .staff-alert:hover,
        .staff-alert:focus-visible {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px color-mix(in oklch, var(--status-warning) 14%, transparent);
            outline: none;
        }
        .staff-alert-main {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }
        .staff-alert-icon {
            width: 22px;
            height: 22px;
            flex: 0 0 auto;
        }
        .staff-alert-action,
        .staff-link {
            color: var(--brand-navy);
            font-size: 12px;
            font-weight: 900;
            text-decoration: none;
            white-space: nowrap;
        }
        .staff-hero {
            padding: 22px 24px;
        }
        .staff-hero-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }
        .staff-kicker {
            margin-bottom: 4px;
            color: var(--fg-3);
            font-size: 12px;
            font-weight: 800;
        }
        .staff-title {
            margin: 0;
            font-family: var(--font-display);
            color: var(--fg-1);
            font-size: 24px;
            font-weight: 900;
            line-height: 1.25;
        }
        .staff-subtitle {
            margin-top: 8px;
            color: var(--fg-3);
            font-size: 13px;
            font-weight: 650;
        }
        .staff-year-badge,
        .staff-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 28px;
            padding: 5px 11px;
            border: 1px solid var(--border);
            border-radius: var(--r-pill);
            background: var(--bg-2);
            color: var(--fg-2);
            font-size: 11.5px;
            font-weight: 900;
            line-height: 1.2;
            white-space: nowrap;
        }
        .staff-year-badge {
            min-height: 36px;
            padding-inline: 16px;
            color: var(--brand-navy);
            background: color-mix(in oklch, var(--brand-navy) 6%, var(--surface));
            border-color: color-mix(in oklch, var(--brand-navy) 16%, var(--border));
            font-size: 13px;
        }
        .staff-status {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 16px;
            align-items: center;
            padding: 18px 20px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--bg-1);
        }
        .staff-status.is-warning {
            background: color-mix(in oklch, var(--status-warning) 7%, var(--surface));
            border-color: var(--status-warning-border);
        }
        .staff-status.is-success {
            background: color-mix(in oklch, var(--status-success) 6%, var(--surface));
            border-color: var(--status-success-border);
        }
        .staff-status.is-info {
            background: color-mix(in oklch, var(--status-info) 6%, var(--surface));
            border-color: var(--status-info-border);
        }
        .staff-status-label {
            color: var(--fg-3);
            font-size: 12px;
            font-weight: 800;
        }
        .staff-status-title {
            margin-top: 3px;
            color: var(--fg-1);
            font-family: var(--font-display);
            font-size: 20px;
            font-weight: 900;
            line-height: 1.25;
        }
        .staff-status-desc {
            margin-top: 7px;
            color: var(--fg-2);
            font-size: 12.5px;
            font-weight: 650;
            line-height: 1.55;
        }
        .staff-status-meta {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .staff-pill.success,
        .staff-pill.ready {
            color: var(--status-success-fg);
            background: var(--status-success-bg);
            border-color: var(--status-success-border);
        }
        .staff-pill.info {
            color: var(--status-info-fg);
            background: var(--status-info-bg);
            border-color: var(--status-info-border);
        }
        .staff-pill.warning,
        .staff-pill.watch,
        .staff-pill.critical {
            color: var(--status-warning-fg);
            background: var(--status-warning-bg);
            border-color: var(--status-warning-border);
        }
        .staff-pill.muted {
            color: var(--fg-3);
            background: var(--bg-2);
        }
        .staff-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 34px;
            padding: 8px 14px;
            border: 1px solid var(--brand-navy);
            border-radius: 7px;
            background: var(--brand-navy);
            color: var(--surface);
            font-size: 12px;
            font-weight: 900;
            text-decoration: none;
            white-space: nowrap;
        }
        .staff-kpis {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
        }
        .staff-kpi {
            display: block;
            min-height: 104px;
            padding: 15px;
            text-decoration: none;
            transition: transform var(--dur-fast), box-shadow var(--dur-fast), border-color var(--dur-fast);
        }
        .staff-kpi:hover,
        .staff-kpi:focus-visible {
            transform: translateY(-1px);
            border-color: color-mix(in oklch, var(--brand-navy) 24%, var(--border));
            box-shadow: 0 8px 20px color-mix(in oklch, var(--brand-navy) 10%, transparent);
            outline: none;
        }
        .staff-kpi-label {
            color: var(--fg-3);
            font-size: 12px;
            font-weight: 850;
        }
        .staff-kpi-value {
            margin-top: 8px;
            color: var(--fg-1);
            font-family: var(--font-display);
            font-size: 27px;
            font-weight: 900;
            line-height: 1;
        }
        .staff-kpi-hint {
            margin-top: 7px;
            color: var(--fg-3);
            font-size: 12px;
            font-weight: 650;
        }
        .staff-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(320px, .85fr);
            gap: 14px;
            align-items: start;
        }
        .staff-panel {
            padding: 16px;
        }
        .staff-panel + .staff-panel {
            margin-top: 14px;
        }
        .staff-panel-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
        }
        .staff-panel-title {
            color: var(--fg-1);
            font-size: 16px;
            font-weight: 900;
        }
        .staff-panel-note {
            margin-top: 3px;
            color: var(--fg-3);
            font-size: 12px;
            font-weight: 650;
        }
        .staff-action-list,
        .staff-offering-list,
        .staff-ready-list {
            display: grid;
            gap: 9px;
        }
        .staff-action-row,
        .staff-offering,
        .staff-ready-row {
            padding: 11px 12px;
        }
        .staff-action-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .staff-action-title,
        .staff-offering-title,
        .staff-ready-label {
            color: var(--fg-1);
            font-size: 13px;
            font-weight: 850;
            line-height: 1.45;
        }
        .staff-offering-meta,
        .staff-ready-value {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            margin-top: 7px;
            color: var(--fg-3);
            font-size: 12px;
            font-weight: 700;
        }
        .staff-offering-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 11px;
        }
        .staff-empty {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px;
            border: 1px dashed var(--border);
            border-radius: 8px;
            background: var(--bg-1);
            color: var(--fg-3);
            font-size: 13px;
            font-weight: 750;
            line-height: 1.5;
        }
        .staff-ready-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            align-items: center;
            background: var(--bg-1);
        }
        @media (max-width: 1200px) {
            .staff-kpis,
            .staff-layout {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .staff-layout {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 760px) {
            .staff-dash {
                padding: 18px;
            }
            .staff-alert,
            .staff-hero-top,
            .staff-status,
            .staff-empty {
                align-items: stretch;
                flex-direction: column;
            }
            .staff-hero-top {
                display: flex;
            }
            .staff-status {
                grid-template-columns: 1fr;
            }
            .staff-kpis {
                grid-template-columns: 1fr;
            }
            .staff-year-badge,
            .staff-btn,
            .staff-alert-action {
                width: 100%;
                white-space: normal;
            }
            .staff-action-row,
            .staff-ready-row {
                grid-template-columns: 1fr;
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>

    <div class="staff-dash">
        @if($staffPrimaryAlert)
            <a href="{{ $staffPrimaryAlert['href'] }}" class="staff-alert" data-testid="staff-primary-alert">
                <span class="staff-alert-main">
                    <svg class="staff-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    <span>{{ $staffPrimaryAlert['message'] }}</span>
                </span>
                <span class="staff-alert-action">{{ $staffPrimaryAlert['action_label'] }}</span>
            </a>
        @endif

        <section class="staff-card staff-hero" aria-label="ภาพรวมเจ้าหน้าที่">
            <div class="staff-hero-top">
                <div>
                    <div class="staff-kicker">ภาพรวม / เจ้าหน้าที่</div>
                    <h1 class="staff-title">ภาพรวมเจ้าหน้าที่</h1>
                    <div class="staff-subtitle">ติดตามข้อมูลหลัก ตารางสอน และรายการที่ต้องอัปเดต</div>
                </div>
                <span class="staff-year-badge">
                    @if($currentAcademicYear)
                        ปีการศึกษา {{ $currentAcademicYear->name }} / เทอม {{ $currentAcademicYear->semester }}
                    @else
                        ยังไม่ได้ตั้งค่าปีการศึกษา
                    @endif
                </span>
            </div>

            <div class="staff-status is-{{ $systemStatus['tone'] }}">
                <div>
                    <div class="staff-status-label">สถานะปัจจุบัน</div>
                    <div class="staff-status-title">{{ $systemStatus['title'] }}</div>
                    <div class="staff-status-desc">{{ $systemStatus['desc'] }}</div>
                    <div class="staff-status-meta">
                        <span class="staff-pill {{ $phaseTone }}">{{ $phaseLabel }}</span>
                        <span class="staff-pill {{ $systemStatus['tone'] }}">{{ $systemStatus['label'] }}</span>
                    </div>
                </div>
                <a href="{{ $systemStatus['href'] }}" class="staff-btn">{{ $systemStatus['action'] }}</a>
            </div>
        </section>

        <section class="staff-kpis" aria-label="ตัวเลขสำคัญ">
            @foreach($kpis as $kpi)
                <a href="{{ $kpi['href'] }}" class="staff-kpi" data-testid="staff-kpi-card">
                    <div class="staff-kpi-label">{{ $kpi['label'] }}</div>
                    <div class="staff-kpi-value">{{ $kpi['value'] }}</div>
                    <div class="staff-kpi-hint">{{ $kpi['hint'] }}</div>
                </a>
            @endforeach
        </section>

        <div class="staff-layout">
            <main>
                <section class="staff-panel" aria-label="งานที่ต้องทำโดย Staff">
                    <div class="staff-panel-head">
                        <div>
                            <div class="staff-panel-title">งานที่ต้องทำโดย Staff</div>
                            <div class="staff-panel-note">รายการที่แก้ได้จากสิทธิ์เจ้าหน้าที่</div>
                        </div>
                        <a class="staff-link" href="{{ route('staff.master_data', ['tab' => 'courses']) }}">ข้อมูลหลัก</a>
                    </div>

                    @if($staffActionGroups['staff']->isEmpty())
                        <div class="staff-empty">
                            <span>ไม่มีรายการที่ต้องแก้ตอนนี้</span>
                            <a class="staff-link" href="{{ route('staff.master_data', ['tab' => 'courses']) }}">ตรวจข้อมูลหลัก</a>
                        </div>
                    @else
                        <div class="staff-action-list">
                            @foreach($staffActionGroups['staff'] as $item)
                                <div class="staff-action-row">
                                    <div class="staff-action-title">{{ $item['label'] }}</div>
                                    <a class="staff-link" href="{{ $item['href'] }}">{{ $item['link_text'] }}</a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>

                <section class="staff-panel" aria-label="รายวิชาที่ได้รับมอบหมาย">
                    <div class="staff-panel-head">
                        <div>
                            <div class="staff-panel-title">รายวิชาที่ได้รับมอบหมาย</div>
                            <div class="staff-panel-note">แสดงเฉพาะรอบปีการศึกษาปัจจุบัน</div>
                        </div>
                        <a class="staff-link" href="{{ route('staff.schedules.index') }}">ตารางสอน</a>
                    </div>

                    @if($currentYearStaffOfferings->isEmpty())
                        <div class="staff-empty">
                            <span>ยังไม่มีรายวิชาที่ได้รับมอบหมาย</span>
                            <a class="staff-link" href="{{ route('staff.master_data', ['tab' => 'courses']) }}">ตรวจรายวิชา</a>
                        </div>
                    @else
                        <div class="staff-offering-list">
                            @foreach($currentYearStaffOfferings->take(5) as $offering)
                                <article class="staff-offering">
                                    <div class="staff-offering-title">
                                        {{ $offering->course?->course_code ?? '-' }} {{ $offering->course?->name_th ?? $offering->course?->name_en ?? 'ไม่ระบุชื่อรายวิชา' }}
                                    </div>
                                    <div class="staff-offering-meta">
                                        <span>{{ $offering->student_groups_count }} กลุ่ม</span>
                                        <span>{{ $offering->instructor_pool_count }} ผู้สอน</span>
                                        <span>{{ $offering->schedules_count }} ตาราง</span>
                                        <span class="staff-pill {{ $offering->academicYear?->phase === 'scheduling' ? 'success' : 'muted' }}">
                                            {{ $phaseLabels[$offering->academicYear?->phase] ?? ($offering->academicYear?->phase ?: 'ไม่ระบุ') }}
                                        </span>
                                    </div>
                                    <div class="staff-offering-actions">
                                        <a class="staff-btn" href="{{ route('staff.course_offerings.schedules.index', $offering) }}">เปิดตาราง</a>
                                        <a class="staff-link" href="{{ route('staff.master_data', ['tab' => 'courses']) }}">ดูข้อมูล</a>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </section>
            </main>

            <aside>
                <section class="staff-panel" aria-label="ความพร้อมข้อมูล">
                    <div class="staff-panel-head">
                        <div>
                            <div class="staff-panel-title">ความพร้อมข้อมูล</div>
                            <div class="staff-panel-note">สรุปสั้นสำหรับก่อนจัดตาราง</div>
                        </div>
                    </div>

                    <div class="staff-ready-list">
                        @foreach($readiness as $item)
                            <div class="staff-ready-row">
                                <div>
                                    <div class="staff-ready-label">{{ $item['label'] }}</div>
                                    <div class="staff-ready-value">
                                        <span>{{ $item['value'] }}</span>
                                    </div>
                                </div>
                                <a class="staff-pill {{ $item['status'] }}" href="{{ $item['href'] }}">
                                    {{ $item['status'] === 'ready' ? 'พร้อม' : ($item['status'] === 'critical' ? 'ต้องแก้' : 'ติดตาม') }}
                                </a>
                            </div>
                        @endforeach
                    </div>
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
