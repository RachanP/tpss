@php
    $approvalLabels = [
        'draft'     => ['label' => 'แบบร่าง',    'badge' => 'badge-gray'],
        'pending'   => ['label' => 'รออนุมัติ',  'badge' => 'badge-warn'],
        'published' => ['label' => 'อนุมัติแล้ว','badge' => 'badge-ok'],
        'rejected'  => ['label' => 'ตีกลับ',     'badge' => 'badge-error'],
    ];
@endphp

<x-app-layout title="จัดการรายวิชา">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px;flex-wrap:wrap;">
        <div>
            <h1 class="h1" style="margin:0 0 6px;">จัดการรายวิชาที่รับผิดชอบ</h1>
            <p class="body-sm" style="margin:0;">แสดงเฉพาะรายวิชาที่กำหนดให้คุณเป็นหัวหน้าวิชาในภาคการศึกษานั้น</p>
        </div>
        @if($availableYears->count() > 0)
            <form method="GET" action="{{ route('maker.course_offerings.index') }}" style="
                display:inline-flex;
                align-items:stretch;
                border:2px solid var(--brand-navy);
                border-radius:10px;
                overflow:hidden;
                background:var(--bg-1);
            ">
                <label for="year-filter" style="
                    display:inline-flex;
                    align-items:center;
                    gap:6px;
                    padding:8px 14px;
                    background:var(--brand-navy);
                    color:#fff;
                    font-size:0.8125rem;
                    font-weight:600;
                    white-space:nowrap;
                    margin:0;
                ">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    ปีการศึกษา
                </label>
                <div style="position:relative;display:inline-flex;align-items:stretch;">
                    <select id="year-filter" name="year" onchange="this.form.submit()" data-testid="offering-year-filter" style="
                        appearance:none;
                        -webkit-appearance:none;
                        -moz-appearance:none;
                        padding:8px 38px 8px 14px;
                        min-width:160px;
                        max-width:240px;
                        background:transparent;
                        color:var(--fg-1);
                        font-size:0.875rem;
                        font-weight:500;
                        border:none;
                        cursor:pointer;
                        outline:none;
                        font-family:inherit;
                    ">
                        @foreach($availableYears as $year)
                            <option value="{{ $year->id }}" @selected($year->id === $selectedYearId)>
                                {{ $year->name }} / เทอม {{ $year->semester }}@if($year->is_active) · ปัจจุบัน @endif
                            </option>
                        @endforeach
                    </select>
                    <svg style="position:absolute;right:12px;top:50%;transform:translateY(-50%);pointer-events:none;color:var(--brand-navy);" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                </div>
            </form>
        @endif
    </div>

    @if($summary['total'] > 0)
        @php
            $summaryCards = [
                ['key' => 'total',     'label' => 'รายวิชาทั้งหมด',     'hint' => 'ในภาคการศึกษานี้',     'tone' => null,        'active' => true],
                ['key' => 'draft',     'label' => 'แบบร่าง',            'hint' => 'รอคุณส่งขออนุมัติ',     'tone' => 'neutral',   'active' => $summary['draft'] > 0],
                ['key' => 'pending',   'label' => 'รออนุมัติ',          'hint' => 'รอผู้บริหารพิจารณา',    'tone' => 'info',      'active' => $summary['pending'] > 0],
                ['key' => 'published', 'label' => 'อนุมัติแล้ว',        'hint' => 'ผ่านอนุมัติเรียบร้อย',  'tone' => 'success',   'active' => $summary['published'] > 0],
                ['key' => 'rejected',  'label' => 'ตีกลับ',             'hint' => 'ต้องแก้ไขและส่งใหม่',   'tone' => 'conflict',  'active' => $summary['rejected'] > 0],
            ];
        @endphp
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:16px;" data-testid="offering-summary">
            @foreach($summaryCards as $c)
                @php
                    $tone = $c['tone'];
                    if ($tone === 'neutral') {
                        // Default/idle state — gray tone (เช่น แบบร่าง)
                        $accent = '#64748b';
                        $borderColor = '#cbd5e1';
                        $labelColor = '#475569';
                    } elseif ($tone) {
                        $accent = "var(--status-{$tone})";
                        $borderColor = "var(--status-{$tone}-border)";
                        $labelColor = "var(--status-{$tone}-fg)";
                    } else {
                        $accent = 'var(--brand-navy)';
                        $borderColor = 'var(--brand-navy-300)';
                        $labelColor = 'var(--brand-navy-700)';
                    }
                    $dotOpacity = $c['active'] ? '1' : '0.25';
                @endphp
                <div style="
                    position:relative;
                    padding:12px 14px;
                    background:var(--bg-1);
                    border:2px solid {{ $borderColor }};
                    border-top:4px solid {{ $accent }};
                    border-radius:10px;
                    overflow:hidden;
                ">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;min-width:0;">
                        <div style="font-size:0.8125rem;font-weight:700;color:{{ $labelColor }};letter-spacing:0.01em;min-width:0;overflow-wrap:break-word;">
                            {{ $c['label'] }}
                        </div>
                        <span style="display:inline-block;width:9px;height:9px;background:{{ $accent }};border-radius:50%;flex-shrink:0;opacity:{{ $dotOpacity }};"></span>
                    </div>
                    <div style="font-size:1.75rem;font-weight:700;line-height:1;color:var(--fg-1);margin-top:8px;font-family:var(--font-display);letter-spacing:-0.01em;">
                        {{ $summary[$c['key']] }}
                    </div>
                    <div style="font-size:0.7rem;color:var(--fg-3);margin-top:6px;line-height:1.3;">
                        {{ $c['hint'] }}
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if($errors->any())
        <div class="card" style="border-color:var(--status-conflict-border);background:var(--status-conflict-bg);margin-bottom:16px;">
            <div style="padding:14px 18px;color:var(--status-conflict-fg);font-weight:600;">
                {{ $errors->first() }}
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-hdr">
            <div>
                <div class="card-ttl">รายวิชาที่รับผิดชอบ</div>
                <div class="caption" style="margin-top:4px;">{{ $offerings->count() }} รายการ</div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="course-offerings-table">
                <thead>
                    <tr>
                        <th>รายวิชา</th>
                        <th>หลักสูตร / ปีการศึกษา</th>
                        <th>กลุ่มนักศึกษา</th>
                        <th>ชั่วโมงแผน</th>
                        <th style="white-space:nowrap;">สถานะการจัดตาราง</th>
                        <th style="white-space:nowrap;">สถานะรายวิชา</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($offerings as $offering)
                        @php
                            $course      = $offering->course;
                            $year        = $offering->academicYear;
                            $phase       = $year?->phase ?? 'preparation';
                            $approval    = $offering->approval_status ?? 'draft';
                            $approvalMeta = $approvalLabels[$approval] ?? ['label' => $approval, 'badge' => 'badge-gray'];
                            $lectureHours  = $offering->planned_lecture_hours ?? $course?->lecture_hours ?? 0;
                            $labHours      = $offering->planned_lab_hours ?? $course?->lab_hours ?? 0;
                            $practicumHours = $offering->planned_practicum_hours ?? 0;
                            $studentTotal = $offering->total_student_count;
                            $allocatedStudents = (int) ($offering->allocated_student_count ?? 0);
                            $hasStudentTotal = $studentTotal !== null && (int) $studentTotal > 0;
                            $studentLimit = (int) ($studentTotal ?? 0);
                            $remainingStudents = $hasStudentTotal ? max(0, $studentLimit - $allocatedStudents) : null;
                        @endphp
                        <tr class="course-offering-row">
                            <td>
                                <div style="font-weight:700;color:var(--fg-1);">{{ $course?->course_code ?? '-' }}</div>
                                <div class="body-sm" style="margin-top:3px;">{{ $course?->name_th ?? $course?->name_en ?? '-' }}</div>
                                <div style="display:flex;align-items:center;gap:8px;margin-top:5px;flex-wrap:wrap;">
                                    <span class="caption">{{ $course?->credits ?? '-' }} หน่วยกิต</span>
                                    @if($offering->requires_practicum_rotation)
                                        <span class="badge badge-warn" style="font-size:0.7rem;">ฝึกปฏิบัติ</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="body-sm">{{ $course?->curriculum?->name ?? '-' }}</div>
                                <div class="caption" style="margin-top:4px;">
                                    {{ $year?->name ?? '-' }} / เทอม {{ $year?->semester ?? '-' }}
                                </div>
                            </td>
                            <td>
                                <div style="font-weight:700;white-space:nowrap;">
                                    @if($offering->student_groups_count > 0)
                                        {{ $offering->student_groups_count }} กลุ่ม
                                    @else
                                        ยังไม่ได้จัดกลุ่ม
                                    @endif
                                </div>
                                <div style="margin-top:8px;">
                                    @if(! $hasStudentTotal)
                                        <span class="badge badge-gray" data-testid="student-capacity-missing" style="white-space:nowrap;">
                                            ยังไม่ได้กำหนดจำนวนนักศึกษา
                                        </span>
                                    @elseif($offering->student_groups_count < 1)
                                        <span class="badge badge-gray" data-testid="student-capacity-no-groups" style="white-space:nowrap;">
                                            ยังไม่มีกลุ่ม
                                        </span>
                                        <div class="caption" style="margin-top:4px;white-space:nowrap;">เหลือ {{ $remainingStudents }} คน</div>
                                    @elseif($allocatedStudents >= $studentLimit)
                                        <span
                                            class="badge"
                                            data-testid="student-capacity-full"
                                            style="background:var(--status-success-bg);color:var(--status-success-fg);border:1px solid var(--status-success-border);white-space:nowrap;"
                                        >
                                            จัดสรรครบ
                                        </span>
                                    @elseif($remainingStudents <= 10)
                                        <span class="badge badge-warn" data-testid="student-capacity-low" style="white-space:nowrap;">
                                            เหลือ {{ $remainingStudents }} คน
                                        </span>
                                    @else
                                        <span class="badge badge-ok" data-testid="student-capacity-open" style="white-space:nowrap;">
                                            เหลือ {{ $remainingStudents }} คน
                                        </span>
                                    @endif
                                </div>
                                @if($hasStudentTotal)
                                    <div class="caption" style="margin-top:4px;white-space:nowrap;">จัดแล้ว {{ $allocatedStudents }}/{{ $studentLimit }} คน</div>
                                @else
                                    <div class="caption" style="margin-top:4px;white-space:nowrap;">รับได้ - คน</div>
                                @endif
                            </td>
                            <td style="white-space:nowrap;">
                                <div class="body-sm" style="white-space:nowrap;">
                                    บรรยาย <strong>{{ $lectureHours }}</strong> · ปฏิบัติ <strong>{{ $labHours }}</strong> ชม.
                                </div>
                                @if($practicumHours > 0)
                                    <div class="caption" style="margin-top:4px;white-space:nowrap;">ฝึกปฏิบัติ {{ $practicumHours }} ชม.</div>
                                @endif
                            </td>
                            <td style="white-space:nowrap;">
                                @if($phase === 'scheduling')
                                    <span class="badge" style="background:oklch(90% 0.1 145);color:oklch(30% 0.15 145);border:1px solid oklch(70% 0.15 145);white-space:nowrap;">เปิดจัดตาราง</span>
                                @elseif($phase === 'published')
                                    <span class="badge badge-primary" style="white-space:nowrap;">เผยแพร่แล้ว</span>
                                @else
                                    <span class="badge badge-gray" style="white-space:nowrap;">ยังไม่เปิด</span>
                                @endif
                            </td>
                            <td style="white-space:nowrap;">
                                <span class="badge {{ $approvalMeta['badge'] }}" style="white-space:nowrap;">{{ $approvalMeta['label'] }}</span>
                            </td>
                            <td class="course-offering-action-cell">
                                <div class="course-offering-actions">
                                    @if($phase === 'scheduling')
                                        <a class="course-offering-action-link is-primary" data-testid="course-offering-schedule-link" href="{{ route('maker.course_offerings.schedules.index', $offering) }}">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="3" y="4" width="18" height="18" rx="2"/>
                                                <line x1="3" y1="10" x2="21" y2="10"/>
                                            </svg>
                                            จัดตาราง
                                        </a>
                                    @endif
                                    <a class="course-offering-action-link is-secondary" data-testid="course-offering-show-link" href="{{ route('maker.course_offerings.show', $offering) }}">
                                        รายละเอียด
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        @php
                            $emptyKey = $coordinatorEmptyStateKey ?? 'no_offerings';
                            $emptyMessages = [
                                'preparation' => [
                                    'title' => 'อยู่ในสถานะเตรียมข้อมูล',
                                    'sub' => 'ยังไม่ถึงช่วงเวลาการจัดตารางเรียน — ระบบจะเปิดรายวิชาเมื่อผู้ดูแลตั้งค่าเป็นช่วงจัดตาราง',
                                ],
                                'no_offerings' => [
                                    'title' => 'ไม่พบรายวิชาที่รับผิดชอบในรอบนี้',
                                    'sub' => 'คุณยังไม่ได้รับมอบหมายเป็นหัวหน้าวิชาในรอบ scheduling นี้ — ติดต่อผู้ดูแลระบบหากต้องการรับผิดชอบรายวิชา',
                                ],
                                'ready' => [
                                    'title' => 'ไม่พบรายวิชาที่รับผิดชอบ',
                                    'sub' => 'ลองเลือกปีการศึกษาอื่นจากตัวกรองด้านบน',
                                ],
                            ];
                            $msg = $emptyMessages[$emptyKey] ?? $emptyMessages['ready'];
                        @endphp
                        <tr>
                            <td colspan="7" style="text-align:center;padding:34px 20px;" data-empty-state="{{ $emptyKey }}">
                                <div style="font-weight:950;font-size:15px;color:var(--brand-navy);margin-bottom:4px;">{{ $msg['title'] }}</div>
                                <div style="font-weight:700;font-size:12.5px;color:var(--fg-2);line-height:1.55;max-width:520px;margin:0 auto;">{{ $msg['sub'] }}</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <style>
        .course-offerings-table {
            table-layout: auto;
        }

        .course-offering-row {
            height: 104px;
        }

        .course-offering-row > td {
            vertical-align: middle;
        }

        .course-offering-action-cell {
            width: 148px;
            text-align: right;
        }

        .course-offering-actions {
            min-height: 76px;
            display: inline-flex;
            flex-direction: column;
            align-items: stretch;
            justify-content: center;
            gap: 8px;
            width: 120px;
        }

        .course-offering-action-link {
            min-height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 7px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.8125rem;
            font-weight: 600;
            line-height: 1.2;
            text-decoration: none;
            white-space: nowrap;
            box-sizing: border-box;
        }

        .course-offering-action-link svg {
            flex: 0 0 auto;
        }

        .course-offering-action-link.is-primary {
            background: var(--brand-navy);
            color: #fff;
            border-color: var(--brand-navy);
        }

        .course-offering-action-link.is-secondary {
            background: var(--bg-1);
            color: var(--fg-1);
            border-color: var(--border);
        }
    </style>
</x-app-layout>
