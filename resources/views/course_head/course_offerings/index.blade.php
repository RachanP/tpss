@php
    $approvalLabels = [
        'draft'     => ['label' => 'แบบร่าง',    'badge' => 'badge-gray'],
        'pending'   => ['label' => 'รออนุมัติ',  'badge' => 'badge-warn'],
        'published' => ['label' => 'เผยแพร่แล้ว','badge' => 'badge-ok'],
        'rejected'  => ['label' => 'ตีกลับ',     'badge' => 'badge-error'],
    ];
@endphp

<x-app-layout title="จัดการรายวิชา">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px;flex-wrap:wrap;">
        <div>
            <div class="eyebrow">จัดการรายวิชา</div>
            <h1 class="h1" style="margin:4px 0 6px;">จัดการรายวิชาที่รับผิดชอบ</h1>
            <p class="body-sm" style="margin:0;">แสดงเฉพาะรายวิชาที่กำหนดให้คุณเป็นหัวหน้าวิชาในภาคการศึกษานั้น</p>
        </div>
    </div>

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
            <table>
                <thead>
                    <tr>
                        <th>รายวิชา</th>
                        <th>หลักสูตร / ปีการศึกษา</th>
                        <th>กลุ่มนักศึกษา</th>
                        <th>ชั่วโมงแผน</th>
                        <th>สถานะการจัดตาราง</th>
                        <th>สถานะรายวิชา</th>
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
                        @endphp
                        <tr>
                            <td>
                                <div style="font-weight:700;color:var(--fg-1);">{{ $course?->course_code ?? '-' }}</div>
                                <div class="body-sm" style="margin-top:3px;">{{ $course?->name_th ?? $course?->name_en ?? '-' }}</div>
                                <div class="caption" style="margin-top:5px;">{{ $course?->credits ?? '-' }} หน่วยกิต</div>
                            </td>
                            <td>
                                <div class="body-sm">{{ $course?->curriculum?->name ?? '-' }}</div>
                                <div class="caption" style="margin-top:4px;">
                                    {{ $year?->name ?? '-' }} / เทอม {{ $year?->semester ?? '-' }}
                                </div>
                            </td>
                            <td>
                                <div style="font-weight:700;">
                                    @if($offering->student_groups_count > 0)
                                        {{ $offering->student_groups_count }} กลุ่ม
                                    @else
                                        ยังไม่ได้จัดกลุ่ม
                                    @endif
                                </div>
                                <div class="caption" style="margin-top:4px;">รับได้ {{ $course?->capacity ?? '-' }} คน</div>
                            </td>
                            <td>
                                <div class="body-sm">บรรยาย {{ $lectureHours }} · ปฏิบัติ {{ $labHours }}</div>
                                <div class="caption" style="margin-top:4px;">ฝึกปฏิบัติ {{ $practicumHours }} · {{ $offering->teaching_weeks ?? '-' }} สัปดาห์</div>
                            </td>
                            <td>
                                @if($phase === 'scheduling')
                                    <span class="badge" style="background:oklch(90% 0.1 145);color:oklch(30% 0.15 145);border:1px solid oklch(70% 0.15 145);">เปิดจัดตาราง</span>
                                @elseif($phase === 'published')
                                    <span class="badge badge-primary">เผยแพร่แล้ว</span>
                                @else
                                    <span class="badge badge-gray">ยังไม่เปิด</span>
                                @endif
                                @if($offering->requires_practicum_rotation)
                                    <span class="badge badge-warn" style="margin-left:6px;">ฝึกปฏิบัติ</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $approvalMeta['badge'] }}">{{ $approvalMeta['label'] }}</span>
                            </td>
                            <td style="text-align:right;">
                                <a class="btn btn-secondary" href="{{ route('maker.course_offerings.show', $offering) }}">เปิดรายละเอียด</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align:center;padding:34px 20px;color:var(--fg-3);">
                                ไม่พบรายวิชาที่รับผิดชอบ
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
