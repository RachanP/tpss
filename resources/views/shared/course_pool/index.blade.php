<x-app-layout title="ตั้งค่าผู้รับผิดชอบรายวิชา">
    @if(session('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-hdr">
            <div class="card-ttl">รายวิชาทั้งหมด ({{ $courses->count() }} รายการ)</div>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>รหัสวิชา</th>
                        <th>ชื่อวิชา</th>
                        <th>ภาควิชา</th>
                        <th style="text-align:center;">หัวหน้าวิชา</th>
                        <th style="text-align:center;">เจ้าหน้าที่</th>
                        <th style="text-align:center;">อาจารย์ผู้สอน</th>
                        <th style="text-align:center;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($courses as $course)
                        <tr>
                            <td style="font-weight:600;font-family:var(--font-mono);">{{ $course->course_code }}</td>
                            <td>
                                <div style="font-weight:600;">{{ $course->name_th }}</div>
                                <div class="caption" style="margin-top:2px;">{{ $course->name_en }}</div>
                            </td>
                            <td>{{ $course->department?->name ?? '-' }}</td>
                            <td style="text-align:center;">
                                @if($course->headInstructor)
                                    <span class="pill pill-success">{{ $course->headInstructor->formatted_name }}</span>
                                @else
                                    <span class="pill pill-warning">ยังไม่กำหนด</span>
                                @endif
                            </td>
                            <td style="text-align:center;">
                                <span style="font-weight:600;">{{ $course->assigned_staff_count }}</span>
                                <span style="color:var(--fg-3);font-size:12px;">คน</span>
                            </td>
                            <td style="text-align:center;">
                                <span style="font-weight:600;">{{ $course->instructors_count }}</span>
                                <span style="color:var(--fg-3);font-size:12px;">คน</span>
                            </td>
                            <td style="text-align:center;">
                                <a href="{{ route($routePrefix . '.course_pool.show', $course) }}" class="btn btn-ghost" style="padding:4px 12px;font-size:13px;">
                                    ตั้งค่า
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align:center;color:var(--fg-3);padding:40px;">ยังไม่มีรายวิชา — กรุณาเพิ่มรายวิชาในหน้า "ข้อมูลหลักระบบ" ก่อน</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
