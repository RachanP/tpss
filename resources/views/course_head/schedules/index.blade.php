@php
    $course = $courseOffering->course;
    $academicYear = $courseOffering->academicYear;
    $statusLabels = [
        'draft' => 'แบบร่าง',
        'pending_approval' => 'รออนุมัติ',
        'approved' => 'อนุมัติแล้ว',
        'revised' => 'ปรับแก้',
    ];
@endphp

<x-app-layout title="ตารางสอน">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px;flex-wrap:wrap;">
        <div>
            <a href="{{ route('maker.course_offerings.show', $courseOffering) }}" class="body-sm" style="color:var(--brand-navy);text-decoration:none;">← กลับไปหน้ารายวิชา</a>
            <div class="eyebrow" style="margin-top:10px;">ตารางสอน</div>
            <h1 class="h1" style="margin:4px 0 6px;">{{ $course?->course_code ?? '-' }} {{ $course?->name_th ?? $course?->name_en ?? '' }}</h1>
            <p class="body-sm" style="margin:0;">
                {{ $course?->curriculum?->name ?? '-' }} · {{ $academicYear?->name ?? '-' }} / เทอม {{ $academicYear?->semester ?? '-' }}
            </p>
        </div>
        <div class="card-actions">
            @if($courseOffering->status !== 'archived')
                <a class="btn btn-primary" href="{{ route('maker.course_offerings.schedules.create', $courseOffering) }}">เพิ่มรายการสอน</a>
            @endif
            <span class="badge {{ $courseOffering->status === 'archived' ? 'badge-gray' : 'badge-ok' }}">
                {{ $courseOffering->status === 'archived' ? 'เก็บเข้าคลัง' : 'ใช้งาน' }}
            </span>
        </div>
    </div>

    @if(session('success'))
        <div class="card" style="border-color:var(--status-success);background:oklch(97% 0.03 145);">
            <div style="padding:14px 18px;color:var(--status-success);font-weight:600;">
                {{ session('success') }}
            </div>
        </div>
    @endif

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
                <div class="card-ttl">รายการตารางสอน</div>
                <div class="caption" style="margin-top:4px;">{{ $schedules->count() }} รายการ</div>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>วันที่สอน</th>
                        <th>เวลา</th>
                        <th>ประเภทกิจกรรม</th>
                        <th>ห้อง/สถานที่</th>
                        <th>หัวข้อ</th>
                        <th>ผู้สอน</th>
                        <th>กลุ่มนักศึกษา</th>
                        <th>สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($schedules as $schedule)
                        <tr>
                            <td style="font-weight:700;">{{ optional($schedule->teaching_date)->format('Y-m-d') }}</td>
                            <td class="body-sm">{{ substr((string) $schedule->start_time, 0, 5) }} - {{ substr((string) $schedule->end_time, 0, 5) }}</td>
                            <td>
                                <span class="badge badge-gray">{{ $schedule->activityType?->name ?? '-' }}</span>
                            </td>
                            <td class="body-sm">
                                {{ $schedule->room?->room_code ?? '-' }}
                                @if($schedule->room?->room_name)
                                    <div class="caption" style="margin-top:4px;">{{ $schedule->room->room_name }}</div>
                                @endif
                            </td>
                            <td class="body-sm">{{ $schedule->topic ?: '-' }}</td>
                            <td class="body-sm">
                                @forelse($schedule->instructors as $instructor)
                                    <div>{{ $instructor->formatted_name }}</div>
                                @empty
                                    -
                                @endforelse
                            </td>
                            <td class="body-sm">
                                @forelse($schedule->studentGroups as $group)
                                    <span class="badge badge-gray" style="margin:0 4px 4px 0;">{{ $group->group_code }}</span>
                                @empty
                                    -
                                @endforelse
                            </td>
                            <td>
                                <span class="badge badge-gray">{{ $statusLabels[$schedule->status] ?? 'แบบร่าง' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align:center;padding:34px 20px;color:var(--fg-3);">
                                ยังไม่มีรายการตารางสอน
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
