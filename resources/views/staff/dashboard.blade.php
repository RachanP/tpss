<x-app-layout title="ภาพรวม Staff">
    @php
        $phaseLabels = [
            'preparation' => 'เตรียมข้อมูล',
            'scheduling' => 'เปิดจัดตาราง',
            'approving' => 'รออนุมัติ',
            'published' => 'เผยแพร่แล้ว',
        ];
        $phase = $currentAcademicYear?->phase;
        $phaseLabel = $phaseLabels[$phase] ?? ($phase ?: 'ไม่ระบุ');
        $phaseTone = $phase === 'scheduling' ? 'ok' : ($phase ? 'warn' : 'muted');
    @endphp

    <style>
        .staff-dash {
            padding: 28px;
            color: var(--fg-1);
        }
        .staff-hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 18px;
            align-items: end;
            margin-bottom: 18px;
        }
        .staff-kicker {
            color: var(--brand-navy);
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .02em;
            text-transform: uppercase;
        }
        .staff-title {
            margin: 4px 0 6px;
            font-size: 25px;
            font-weight: 950;
            line-height: 1.2;
            color: var(--fg-1);
        }
        .staff-copy {
            max-width: 74ch;
            color: var(--fg-3);
            font-size: 13.5px;
            line-height: 1.65;
            font-weight: 650;
        }
        .staff-phase {
            display: inline-flex;
            flex-direction: column;
            gap: 3px;
            min-width: 210px;
            padding: 13px 15px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
        }
        .staff-phase span:first-child,
        .staff-stat span,
        .staff-panel-note {
            color: var(--fg-3);
            font-size: 12px;
            font-weight: 800;
        }
        .staff-phase strong {
            color: var(--brand-navy);
            font-size: 18px;
            font-weight: 950;
        }
        .staff-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        .staff-stat,
        .staff-panel,
        .staff-action,
        .staff-offering {
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
        }
        .staff-stat {
            padding: 14px;
            min-height: 94px;
        }
        .staff-stat strong {
            display: block;
            margin-top: 5px;
            color: var(--fg-1);
            font-size: 24px;
            font-weight: 950;
        }
        .staff-stat small {
            display: block;
            margin-top: 4px;
            color: var(--fg-3);
            font-size: 12px;
            line-height: 1.45;
            font-weight: 650;
        }
        .staff-columns {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(320px, .65fr);
            gap: 14px;
            align-items: start;
        }
        .staff-panel {
            padding: 16px;
            margin-bottom: 14px;
        }
        .staff-panel-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .staff-panel-title {
            color: var(--fg-1);
            font-size: 16px;
            font-weight: 950;
        }
        .staff-readiness {
            display: grid;
            gap: 9px;
        }
        .staff-ready-row {
            display: grid;
            grid-template-columns: 180px minmax(0, 1fr) auto;
            gap: 12px;
            align-items: center;
            padding: 11px 12px;
            border: 1px solid color-mix(in oklch, var(--border) 82%, var(--surface));
            border-radius: 7px;
            background: var(--bg-1);
        }
        .staff-ready-label {
            color: var(--fg-2);
            font-size: 12.5px;
            font-weight: 900;
        }
        .staff-ready-value {
            color: var(--fg-1);
            font-size: 13px;
            font-weight: 850;
        }
        .staff-ready-hint {
            margin-top: 2px;
            color: var(--fg-3);
            font-size: 12px;
            line-height: 1.45;
            font-weight: 600;
        }
        .staff-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 24px;
            padding: 3px 9px;
            border-radius: 999px;
            border: 1px solid var(--border);
            font-size: 11px;
            font-weight: 900;
            white-space: nowrap;
        }
        .staff-pill.ok,
        .staff-pill.ready {
            background: var(--status-ok-bg);
            border-color: var(--status-ok-border);
            color: var(--status-ok-fg);
        }
        .staff-pill.warn,
        .staff-pill.watch,
        .staff-pill.warning {
            background: var(--status-warning-bg);
            border-color: var(--status-warning-border);
            color: var(--status-warning-fg);
        }
        .staff-pill.critical {
            background: var(--status-conflict-bg);
            border-color: var(--status-conflict-border);
            color: var(--status-conflict-fg);
        }
        .staff-pill.muted {
            background: var(--bg-2);
            color: var(--fg-3);
        }
        .staff-action-list {
            display: grid;
            gap: 8px;
        }
        .staff-action {
            padding: 11px 12px;
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: center;
        }
        .staff-action-main {
            min-width: 0;
        }
        .staff-action-title {
            color: var(--fg-2);
            font-size: 12.5px;
            font-weight: 850;
            line-height: 1.45;
        }
        .staff-action a,
        .staff-soft-link {
            color: var(--brand-navy);
            font-size: 12px;
            font-weight: 900;
            text-decoration: none;
        }
        .staff-empty {
            padding: 18px;
            border: 1px dashed var(--border);
            border-radius: 8px;
            background: var(--bg-1);
            color: var(--fg-3);
            font-size: 13px;
            font-weight: 750;
            line-height: 1.55;
        }
        .staff-offering-list {
            display: grid;
            gap: 9px;
        }
        .staff-offering {
            padding: 12px;
        }
        .staff-offering-title {
            color: var(--fg-1);
            font-size: 13px;
            font-weight: 950;
        }
        .staff-offering-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            margin-top: 7px;
            color: var(--fg-3);
            font-size: 12px;
            font-weight: 700;
        }
        @media (max-width: 1100px) {
            .staff-grid,
            .staff-columns,
            .staff-hero {
                grid-template-columns: 1fr;
            }
            .staff-phase {
                min-width: 0;
            }
        }
        @media (max-width: 760px) {
            .staff-dash {
                padding: 18px;
            }
            .staff-grid {
                grid-template-columns: 1fr;
            }
            .staff-ready-row {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="staff-dash">
        <section class="staff-hero" aria-label="ภาพรวม Staff">
            <div>
                <div class="staff-kicker">Staff Phase 1</div>
                <h1 class="staff-title">ภาพรวมระบบสำหรับเจ้าหน้าที่</h1>
                <div class="staff-copy">
                    บทบาทนี้ช่วยเตรียมข้อมูลหลักที่ได้รับมอบหมายและช่วยกรอกตารางสอนแบบ block/rotation ตามข้อมูลที่หัวหน้าวิชาและ Admin เตรียมไว้ ส่วนรายงานเต็มและ export ยังอยู่ระหว่างพัฒนาสำหรับ phase ถัดไป
                </div>
            </div>
            <div class="staff-phase">
                <span>ปีการศึกษาปัจจุบัน</span>
                <strong>{{ $currentAcademicYear ? $currentAcademicYear->name . ' / เทอม ' . $currentAcademicYear->semester : 'ยังไม่ได้ตั้งค่า' }}</strong>
                <span class="staff-pill {{ $phaseTone }}">{{ $phaseLabel }}</span>
            </div>
        </section>

        <section class="staff-grid" aria-label="ตัวเลขภาพรวม">
            <div class="staff-stat">
                <span>รายวิชาที่เปิดใช้งาน</span>
                <strong>{{ number_format($masterStats['courses']) }}</strong>
                <small>ข้อมูลหลักที่ Staff แก้รายละเอียดรายวิชาได้</small>
            </div>
            <div class="staff-stat">
                <span>ห้อง/สถานที่พร้อมใช้</span>
                <strong>{{ number_format($masterStats['active_rooms']) }}</strong>
                <small>{{ number_format($masterStats['location_types']) }} ประเภทสถานที่ในระบบ</small>
            </div>
            <div class="staff-stat">
                <span>Offering รอบปัจจุบัน</span>
                <strong>{{ number_format($masterStats['course_offerings']) }}</strong>
                <small>draft {{ number_format($pipeline['draft']) }}, pending {{ number_format($pipeline['pending']) }}, published {{ number_format($pipeline['published']) }}</small>
            </div>
            <div class="staff-stat">
                <span>ตารางของ Staff</span>
                <strong>{{ number_format($masterStats['staff_schedules']) }}</strong>
                <small>จาก {{ number_format($masterStats['assigned_offerings']) }} offering ที่ได้รับมอบหมาย</small>
            </div>
        </section>

        <div class="staff-columns">
            <main>
                <section class="staff-panel" aria-label="ความพร้อมก่อนจัดตาราง">
                    <div class="staff-panel-head">
                        <div>
                            <div class="staff-panel-title">Readiness ก่อนจัดตาราง</div>
                            <div class="staff-panel-note">ตรวจจากปีการศึกษา ข้อมูลหลัก offering ตาราง และ conflict summary</div>
                        </div>
                        <a class="staff-soft-link" href="{{ route('staff.schedules.index') }}">ไปหน้าตารางสอน</a>
                    </div>

                    <div class="staff-readiness">
                        @foreach($readiness as $item)
                            <div class="staff-ready-row">
                                <div class="staff-ready-label">{{ $item['label'] }}</div>
                                <div>
                                    <div class="staff-ready-value">{{ $item['value'] }}</div>
                                    <div class="staff-ready-hint">{{ $item['hint'] }}</div>
                                </div>
                                <a class="staff-pill {{ $item['status'] }}" href="{{ $item['href'] }}">{{ $item['status'] === 'ready' ? 'พร้อม' : ($item['status'] === 'critical' ? 'ต้องแก้' : 'ติดตาม') }}</a>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="staff-panel" aria-label="รายวิชาที่ได้รับมอบหมาย">
                    <div class="staff-panel-head">
                        <div>
                            <div class="staff-panel-title">รายวิชาที่ Staff คนนี้ช่วยจัดตาราง</div>
                            <div class="staff-panel-note">จำกัด scope ด้วย course_staff ตามแผน Phase 1</div>
                        </div>
                    </div>
                    @if($currentYearStaffOfferings->isEmpty())
                        <div class="staff-empty">
                            ยังไม่มี course offering ในปีการศึกษาปัจจุบันที่ผูกกับ Staff คนนี้ ให้ตรวจที่รายวิชาในข้อมูลหลัก หรือให้หัวหน้าวิชา/Admin เตรียม offering ก่อน demo
                        </div>
                    @else
                        <div class="staff-offering-list">
                            @foreach($currentYearStaffOfferings->take(5) as $offering)
                                <div class="staff-offering">
                                    <div class="staff-offering-title">{{ $offering->course?->course_code ?? '-' }} {{ $offering->course?->name_th ?? $offering->course?->name_en ?? 'ไม่ระบุชื่อรายวิชา' }}</div>
                                    <div class="staff-offering-meta">
                                        <span>{{ $offering->student_groups_count }} กลุ่ม</span>
                                        <span>{{ $offering->instructor_pool_count }} ผู้สอน</span>
                                        <span>{{ $offering->schedules_count }} รายการสอน</span>
                                        <span class="staff-pill {{ $offering->academicYear?->phase === 'scheduling' ? 'ok' : 'muted' }}">{{ $phaseLabels[$offering->academicYear?->phase] ?? ($offering->academicYear?->phase ?: 'ไม่ระบุ') }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            </main>

            <aside>
                <section class="staff-panel" aria-label="สิ่งที่ Staff แก้ได้">
                    <div class="staff-panel-head">
                        <div>
                            <div class="staff-panel-title">แก้ได้โดย Staff</div>
                            <div class="staff-panel-note">รายการที่พาไปยัง settings หรือ master data ฝั่ง Staff</div>
                        </div>
                    </div>
                    @if($staffActionGroups['staff']->isEmpty())
                        <div class="staff-empty">ยังไม่มีรายการที่ต้องให้ Staff แก้ในตอนนี้</div>
                    @else
                        <div class="staff-action-list">
                            @foreach($staffActionGroups['staff'] as $item)
                                <div class="staff-action">
                                    <div class="staff-action-main">
                                        <div class="staff-action-title">{{ $item['label'] }}</div>
                                    </div>
                                    <a href="{{ $item['href'] }}">{{ $item['link_text'] }}</a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>

                <section class="staff-panel" aria-label="ต้องให้ Admin แก้">
                    <div class="staff-panel-head">
                        <div>
                            <div class="staff-panel-title">ต้องให้ Admin แก้</div>
                            <div class="staff-panel-note">Staff เห็นสถานะเพื่อประสานงาน แต่ไม่เปิดให้แก้ไข</div>
                        </div>
                    </div>
                    @if($staffActionGroups['admin']->isEmpty())
                        <div class="staff-empty">ส่วนที่ล็อกไว้ยังไม่พบปัญหาสำคัญ</div>
                    @else
                        <div class="staff-action-list">
                            @foreach($staffActionGroups['admin'] as $item)
                                <div class="staff-action">
                                    <div class="staff-action-main">
                                        <div class="staff-action-title">{{ $item['label'] }}</div>
                                    </div>
                                    <span class="staff-pill muted">{{ $item['link_text'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>

            </aside>
        </div>
    </div>
</x-app-layout>
