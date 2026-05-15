@php
    $statusLabels = [
        'active' => 'ใช้งาน',
        'archived' => 'เก็บเข้าคลัง',
        'draft' => 'แบบร่าง',
        'pending' => 'รออนุมัติ',
        'approved' => 'อนุมัติแล้ว',
        'rejected' => 'ตีกลับ',
    ];
    $courseTypeLabels = [
        'theory' => 'ทฤษฎี',
        'practicum' => 'ปฏิบัติ/ฝึกปฏิบัติ',
        'theory_practicum' => 'ทฤษฎีและปฏิบัติ',
        'lab' => 'ปฏิบัติการ',
        'other' => 'อื่น ๆ',
    ];
@endphp

<x-app-layout title="จัดการรายวิชา">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px;flex-wrap:wrap;">
        <div>
            <div class="eyebrow">จัดการรายวิชา</div>
            <h1 class="h1" style="margin:4px 0 6px;">จัดการรายวิชาที่รับผิดชอบ</h1>
            <p class="body-sm" style="margin:0;">แสดงเฉพาะรายวิชาที่กำหนดให้คุณเป็นหัวหน้าวิชาในภาคการศึกษานั้น</p>
        </div>
        <div class="card-actions">
            <a class="btn {{ $showArchived ? 'btn-ghost' : 'btn-primary' }}" href="{{ route('maker.course_offerings.index') }}">ใช้งาน</a>
            <a class="btn {{ $showArchived ? 'btn-primary' : 'btn-ghost' }}" href="{{ route('maker.course_offerings.index', ['archived' => 1]) }}">คลัง</a>
        </div>
    </div>

    @if($errors->any())
        <div class="card" style="border-color:var(--status-conflict-border);background:var(--status-conflict-bg);">
            <div style="padding:14px 18px;color:var(--status-conflict-fg);font-weight:600;">
                {{ $errors->first() }}
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-hdr">
            <div>
                <div class="card-ttl">{{ $showArchived ? 'รายวิชาในคลัง' : 'รายวิชาที่เปิดใช้งาน' }}</div>
                <div class="caption" style="margin-top:4px;">{{ $offerings->count() }} รายการ</div>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>รายวิชา</th>
                        <th>หลักสูตร / ภาคเรียน</th>
                        <th>นักศึกษา</th>
                        <th>ผู้สอน</th>
                        <th>ชั่วโมงแผน</th>
                        <th>สถานะ</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($offerings as $offering)
                        @php
                            $course = $offering->course;
                            $academicYear = $offering->academicYear;
                            $lectureHours = $offering->planned_lecture_hours ?? $course?->lecture_hours ?? 0;
                            $labHours = $offering->planned_lab_hours ?? $course?->lab_hours ?? 0;
                            $practicumHours = $offering->planned_practicum_hours ?? 0;
                            $studentTotal = $offering->student_groups_sum_student_count ?? 0;
                        @endphp
                        <tr>
                            <td>
                                <div style="font-weight:700;color:var(--fg-1);">{{ $course?->course_code ?? '-' }}</div>
                                <div class="body-sm" style="margin-top:3px;">{{ $course?->name_th ?? $course?->name_en ?? '-' }}</div>
                                <div class="caption" style="margin-top:5px;">
                                    {{ $course?->credits ?? '-' }} หน่วยกิต · {{ $courseTypeLabels[$course?->course_type] ?? '-' }}
                                </div>
                            </td>
                            <td>
                                <div class="body-sm">{{ $course?->curriculum?->name ?? '-' }}</div>
                                <div class="caption" style="margin-top:4px;">
                                    {{ $academicYear?->name ?? '-' }} / เทอม {{ $academicYear?->semester ?? '-' }}
                                </div>
                            </td>
                            <td>
                                <div style="font-weight:700;">{{ $studentTotal }} / {{ $offering->total_student_count ?? '-' }}</div>
                                <div class="caption" style="margin-top:4px;">{{ $offering->student_groups_count }} กลุ่ม</div>
                            </td>
                            <td>
                                <span class="badge badge-gray">{{ $offering->instructor_pool_count }} คน</span>
                            </td>
                            <td>
                                <div class="body-sm">บรรยาย {{ $lectureHours }} · ปฏิบัติ {{ $labHours }}</div>
                                <div class="caption" style="margin-top:4px;">ฝึกปฏิบัติ {{ $practicumHours }} · {{ $offering->teaching_weeks ?? '-' }} สัปดาห์</div>
                            </td>
                            <td>
                                <span class="badge {{ $offering->status === 'archived' ? 'badge-gray' : 'badge-ok' }}">{{ $statusLabels[$offering->status ?? 'active'] ?? $offering->status ?? 'ใช้งาน' }}</span>
                                @if($offering->requires_practicum_rotation)
                                    <span class="badge badge-warn" style="margin-left:6px;">ฝึกปฏิบัติ</span>
                                @endif
                            </td>
                            <td style="text-align:right;">
                                <a class="btn btn-secondary" href="{{ route('maker.course_offerings.show', $offering) }}">เปิดรายละเอียด</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align:center;padding:34px 20px;color:var(--fg-3);">
                                ไม่พบรายวิชาในมุมมองนี้
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
