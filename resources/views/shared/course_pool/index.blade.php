<x-app-layout title="ตั้งค่าผู้รับผิดชอบรายวิชา">
    @if(session('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>
    @endif

    <div class="card" x-data="{ headFilter: 'all', termFilter: 'all' }">
        <div class="card-hdr" style="align-items:flex-start;gap:16px;flex-wrap:wrap;">
            <div>
                <div class="card-ttl">รายวิชาทั้งหมด ({{ $courses->count() }} รายการ)</div>
                <div class="caption" style="margin-top:6px;">
                    @if($activeAcademicYear)
                        รอบปัจจุบัน: ปีการศึกษา {{ $activeAcademicYear->name }} / เทอม {{ $activeAcademicYear->semester }}
                    @else
                        ยังไม่ได้ตั้งค่าปีการศึกษาปัจจุบัน
                    @endif
                </div>
            </div>
            <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-left:auto;">
                <label style="display:grid;gap:4px;">
                    <span class="caption">หัวหน้าวิชา</span>
                    <select class="form-control" x-model="headFilter" data-testid="course-pool-head-filter" style="min-width:180px;">
                        <option value="all">ทั้งหมด</option>
                        <option value="missing">ยังไม่มีหัวหน้าวิชา</option>
                        <option value="assigned">มีหัวหน้าวิชาแล้ว</option>
                    </select>
                </label>
                <label style="display:grid;gap:4px;">
                    <span class="caption">สถานะเทอมนี้</span>
                    <select class="form-control" x-model="termFilter" data-testid="course-pool-term-filter" style="min-width:180px;">
                        <option value="all">ทั้งหมด</option>
                        <option value="open">เปิดสอน</option>
                        <option value="closed">ปิดสอน</option>
                    </select>
                </label>
            </div>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width:96px;">รหัสวิชา</th>
                        <th>ชื่อวิชา</th>
                        <th>ภาควิชา</th>
                        <th style="text-align:center;width:140px;">สถานะเทอมนี้</th>
                        <th style="text-align:center;">หัวหน้าวิชา</th>
                        <th style="text-align:center;">เจ้าหน้าที่</th>
                        <th style="text-align:center;">อาจารย์ผู้สอน</th>
                        <th style="text-align:center;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($courses as $course)
                        @php
                            $headState = $course->headInstructor ? 'assigned' : 'missing';
                            $termState = $activeAcademicYear
                                ? (($course->has_current_offering ?? false) ? 'open' : 'closed')
                                : 'unknown';
                        @endphp
                        <tr
                            data-head-state="{{ $headState }}"
                            data-term-state="{{ $termState }}"
                            x-show="(headFilter === 'all' || headFilter === '{{ $headState }}') && (termFilter === 'all' || termFilter === '{{ $termState }}')"
                        >
                            <td style="font-weight:700;font-family:var(--font-mono);white-space:nowrap;">{{ $course->course_code }}</td>
                            <td>
                                <div style="font-weight:600;">{{ $course->name_th }}</div>
                                <div class="caption" style="margin-top:2px;">{{ $course->name_en }}</div>
                            </td>
                            <td>{{ $course->department?->name ?? '-' }}</td>
                            <td style="text-align:center;">
                                @if(! $activeAcademicYear)
                                    <span
                                        class="badge badge-gray"
                                        data-testid="course-pool-term-unknown"
                                        title="ยังไม่ทราบสถานะเทอมนี้ เพราะยังไม่ได้ตั้งค่าปีการศึกษาปัจจุบัน"
                                        style="padding:4px 12px;border-radius:99px;font-weight:700;font-size:11px;"
                                    >
                                        ไม่ทราบ
                                    </span>
                                @elseif($termState === 'open')
                                    <span
                                        class="badge"
                                        data-testid="course-pool-term-open"
                                        title="เปิดสอนในเทอมนี้"
                                        style="background:#22c55e;color:#ffffff;padding:4px 12px;border-radius:99px;font-weight:700;font-size:11px;box-shadow:0 2px 4px rgba(34,197,94,.2);"
                                    >
                                        เปิดสอน
                                    </span>
                                @else
                                    <span
                                        class="badge"
                                        data-testid="course-pool-term-closed"
                                        title="ไม่มี Course Offering ในเทอมนี้"
                                        style="background:#e2e8f0;color:#64748b;padding:4px 12px;border-radius:99px;font-weight:700;font-size:11px;"
                                    >
                                        ปิดสอน
                                    </span>
                                @endif
                            </td>
                            <td style="text-align:center;">
                                @if($course->headInstructor)
                                    <span class="pill pill-success">{{ $course->headInstructor->formatted_name }}</span>
                                @else
                                    <span
                                        data-testid="course-pool-missing-head-badge"
                                        style="display:inline-flex;align-items:center;gap:6px;color:var(--status-warning-fg);font-weight:800;font-size:13px;white-space:nowrap;"
                                    >
                                        <span aria-hidden="true" style="width:7px;height:7px;border-radius:999px;background:var(--status-warning);box-shadow:0 0 0 3px var(--status-warning-bg);"></span>
                                        ยังไม่มีหัวหน้าวิชา
                                    </span>
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
                                <a href="{{ route($routePrefix . '.course_pool.show', $course) }}" class="btn btn-ghost" data-testid="course-pool-show-link" style="display:inline-flex;align-items:center;justify-content:center;width:74px;min-height:32px;padding:4px 10px;font-size:13px;white-space:nowrap;">
                                    {{ $course->has_locked_offering ? 'ดูแม่แบบ' : 'ตั้งค่า' }}
                                </a>
                                @if($course->has_locked_offering)
                                    <div class="caption" style="margin-top:4px;">ล็อกแล้ว</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align:center;color:var(--fg-3);padding:40px;">ยังไม่มีรายวิชา กรุณาเพิ่มรายวิชาในหน้า "ข้อมูลหลักระบบ" ก่อน</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
