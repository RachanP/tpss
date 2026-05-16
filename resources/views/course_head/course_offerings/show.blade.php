@php
    $course        = $courseOffering->course;
    $academicYear  = $courseOffering->academicYear;
    $canEdit       = $academicYear?->phase === 'scheduling';
    $lectureFallback  = $courseOffering->planned_lecture_hours === null;
    $labFallback      = $courseOffering->planned_lab_hours === null;
    $lectureHours     = $courseOffering->planned_lecture_hours ?? $course?->lecture_hours ?? 0;
    $labHours         = $courseOffering->planned_lab_hours ?? $course?->lab_hours ?? 0;
    $practicumHours   = $courseOffering->planned_practicum_hours ?? 0;
    $studentTotal     = $courseOffering->studentGroups->sum('student_count');
    $studentLimit     = $courseOffering->total_student_count ?? 0;
    $studentRemaining = max(0, $studentLimit - $studentTotal);
@endphp

<x-app-layout title="รายละเอียดรายวิชา">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px;flex-wrap:wrap;">
        <div>
            <a href="{{ route('maker.course_offerings.index') }}" class="body-sm" style="color:var(--brand-navy);text-decoration:none;">← กลับไปรายการรายวิชา</a>
            <h1 class="h1" style="margin:8px 0 6px;">{{ $course?->course_code ?? '-' }} {{ $course?->name_th ?? $course?->name_en ?? '' }}</h1>
            <p class="body-sm" style="margin:0;">
                {{ $course?->curriculum?->name ?? '-' }} · {{ $academicYear?->name ?? '-' }} / เทอม {{ $academicYear?->semester ?? '-' }}
            </p>
        </div>
        <div class="card-actions">
            <a class="btn btn-secondary" href="{{ route('maker.course_offerings.schedules.index', $courseOffering) }}">จัดตารางสอน</a>
            @php $phase = $courseOffering->academicYear?->phase ?? 'preparation'; @endphp
            @if($phase === 'scheduling')
                <span class="badge" style="background:oklch(90% 0.1 145);color:oklch(30% 0.15 145);border:1px solid oklch(70% 0.15 145);">เปิดจัดตาราง</span>
            @elseif($phase === 'published')
                <span class="badge badge-primary">เผยแพร่แล้ว</span>
            @else
                <span class="badge badge-gray">ยังไม่เปิดจัดตาราง</span>
            @endif
            @if($courseOffering->requires_practicum_rotation)
                <span class="badge badge-warn">ฝึกปฏิบัติ</span>
            @endif
        </div>
    </div>

    @if(session('error'))
        <div style="background:oklch(95% 0.05 25);border:1px solid oklch(70% 0.15 25);color:oklch(35% 0.12 25);padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:14px;">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="card" style="border-color:var(--status-conflict-border);background:var(--status-conflict-bg);">
            <div style="padding:14px 18px;color:var(--status-conflict-fg);font-weight:600;">
                {{ $errors->first() }}
            </div>
        </div>
    @endif

    @if(!$canEdit)
        <div style="background:oklch(97% 0.02 250);border:1px solid oklch(80% 0.05 250);color:oklch(40% 0.08 250);padding:12px 18px;border-radius:6px;margin-bottom:20px;font-size:14px;display:flex;align-items:center;gap:10px;">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;opacity:0.6;">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            <span>ยังไม่เปิดช่วงจัดตาราง — ดูข้อมูลได้อย่างเดียว การแก้ไขจะเปิดใช้งานเมื่อ Admin เปิดช่วงจัดตาราง</span>
        </div>
    @endif

    <div class="stats-grid">
        <div class="st-card">
            <div class="st-val">{{ $studentTotal }} / {{ $courseOffering->total_student_count ?? '-' }}</div>
            <div class="st-lbl">นักศึกษาใช้แล้ว / ทั้งหมด</div>
        </div>
        <div class="st-card">
            <div class="st-val">{{ $studentRemaining }}</div>
            <div class="st-lbl">นักศึกษาคงเหลือ</div>
        </div>
        <div class="st-card">
            <div class="st-val">{{ $courseOffering->studentGroups->count() }}</div>
            <div class="st-lbl">กลุ่มนักศึกษา</div>
        </div>
        <div class="st-card">
            <div class="st-val">{{ $courseOffering->instructorPool->count() }}</div>
            <div class="st-lbl">ผู้สอนในรายวิชา</div>
        </div>
        <div class="st-card">
            <div class="st-val">{{ $lectureHours }}/{{ $labHours }}/{{ $practicumHours }}</div>
            <div class="st-lbl">บรรยาย / ปฏิบัติ / ฝึกปฏิบัติ</div>
        </div>
    </div>

    <div class="card">
        <div class="card-hdr">
            <div>
                <div class="card-ttl">ภาพรวมรายวิชา</div>
                <div class="caption" style="margin-top:4px;">ข้อมูลภาคการศึกษานี้ใช้แทนค่าเริ่มต้นจากข้อมูลรายวิชาหลักเมื่อมีการระบุไว้</div>
            </div>
        </div>
        <div style="padding:20px;">
            @if($canEdit)
            <form method="POST" action="{{ route('maker.course_offerings.update', $courseOffering) }}">
                @csrf
                @method('PUT')

                <div class="form-row">
                    <div class="form-group">
                        <label>จำนวนนักศึกษารวม</label>
                        <input type="number" name="total_student_count" min="1" value="{{ old('total_student_count', $courseOffering->total_student_count) }}" required>
                    </div>
                    <div class="form-group">
                        <label>จำนวนสัปดาห์สอน</label>
                        <input type="number" name="teaching_weeks" min="1" max="52" value="{{ old('teaching_weeks', $courseOffering->teaching_weeks) }}">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>ชั่วโมงบรรยายตามแผน</label>
                        <input type="number" name="planned_lecture_hours" min="0" value="{{ old('planned_lecture_hours', $courseOffering->planned_lecture_hours) }}" placeholder="{{ $course?->lecture_hours ?? 0 }}">
                        <div class="caption" style="margin-top:6px;">แสดงผล: {{ $lectureHours }} @if($lectureFallback)(ค่าเริ่มต้นจากรายวิชา)@endif</div>
                    </div>
                    <div class="form-group">
                        <label>ชั่วโมงปฏิบัติการตามแผน</label>
                        <input type="number" name="planned_lab_hours" min="0" value="{{ old('planned_lab_hours', $courseOffering->planned_lab_hours) }}" placeholder="{{ $course?->lab_hours ?? 0 }}">
                        <div class="caption" style="margin-top:6px;">แสดงผล: {{ $labHours }} @if($labFallback)(ค่าเริ่มต้นจากรายวิชา)@endif</div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>ชั่วโมงฝึกปฏิบัติตามแผน</label>
                        <input type="number" name="planned_practicum_hours" min="0" value="{{ old('planned_practicum_hours', $courseOffering->planned_practicum_hours) }}">
                    </div>
                    <div class="form-group">
                        <label>การจัดรอบฝึกปฏิบัติ</label>
                        <select name="requires_practicum_rotation">
                            <option value="0" @selected(! old('requires_practicum_rotation', $courseOffering->requires_practicum_rotation))>ไม่มีการหมุนเวียนแหล่งฝึก</option>
                            <option value="1" @selected(old('requires_practicum_rotation', $courseOffering->requires_practicum_rotation))>มีการหมุนเวียนแหล่งฝึก</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group full">
                        <label>หมายเหตุการฝึกปฏิบัติ / เงื่อนไขรายวิชา</label>
                        <textarea name="practicum_note" rows="3">{{ old('practicum_note', $courseOffering->practicum_note) }}</textarea>
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;">
                    <button class="btn btn-primary" type="submit">บันทึกข้อมูลรายวิชา</button>
                </div>
            </form>
            @else
            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;">
                <div><div class="caption">จำนวนนักศึกษารวม</div><div style="font-weight:600;margin-top:4px;">{{ $courseOffering->total_student_count ?? '-' }} คน</div></div>
                <div><div class="caption">จำนวนสัปดาห์สอน</div><div style="font-weight:600;margin-top:4px;">{{ $courseOffering->teaching_weeks ?? '-' }} สัปดาห์</div></div>
                <div><div class="caption">ชั่วโมงบรรยาย / ปฏิบัติ / ฝึกปฏิบัติ</div><div style="font-weight:600;margin-top:4px;">{{ $lectureHours }} / {{ $labHours }} / {{ $practicumHours }}</div></div>
                <div><div class="caption">การจัดรอบฝึกปฏิบัติ</div><div style="font-weight:600;margin-top:4px;">{{ $courseOffering->requires_practicum_rotation ? 'มีการหมุนเวียนแหล่งฝึก' : 'ไม่มีการหมุนเวียนแหล่งฝึก' }}</div></div>
                @if($courseOffering->practicum_note)
                <div style="grid-column:span 2;"><div class="caption">หมายเหตุ</div><div style="margin-top:4px;">{{ $courseOffering->practicum_note }}</div></div>
                @endif
            </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-hdr">
            <div>
                <div class="card-ttl">ชุดผู้สอน</div>
                <div class="caption" style="margin-top:4px;">เพิ่มหรือนำอาจารย์ออกจากชุดผู้สอนของรายวิชานี้</div>
            </div>
        </div>
        <div style="padding:20px;">
            @if($canEdit)
            <form method="POST" action="{{ route('maker.course_offerings.instructors.store', $courseOffering) }}" class="form-row" style="margin-bottom:16px;">
                @csrf
                <div class="form-group">
                    <label>อาจารย์</label>
                    <select name="user_id" required>
                        <option value="">เลือกอาจารย์</option>
                        @foreach($availableInstructors as $user)
                            <option value="{{ $user->id }}" @selected(old('user_id') == $user->id)>{{ $user->formatted_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;">
                    <button class="btn btn-primary" type="submit">เพิ่มผู้สอน</button>
                </div>
            </form>
            @endif

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ชื่อ</th>
                            <th>ภาควิชา</th>
                            @if($canEdit)<th></th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($courseOffering->instructorPool as $user)
                            <tr>
                                <td style="font-weight:700;">{{ $user->formatted_name }}</td>
                                <td class="body-sm">{{ $user->instructorProfile?->department?->name ?? '-' }}</td>
                                @if($canEdit)
                                <td style="text-align:right;">
                                    <form method="POST" action="{{ route('maker.course_offerings.instructors.destroy', [$courseOffering, $user]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-ghost" type="submit">นำออก</button>
                                    </form>
                                </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $canEdit ? 3 : 2 }}" style="text-align:center;color:var(--fg-3);">ยังไม่มีผู้สอนในรายวิชานี้</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-hdr">
            <div>
                <div class="card-ttl">กลุ่มนักศึกษา</div>
                <div class="caption" style="margin-top:4px;">ทั้งหมด {{ $studentLimit ?: '-' }} คน · จัดกลุ่มแล้ว {{ $studentTotal }} คน · คงเหลือ {{ $studentRemaining }} คน</div>
            </div>
        </div>
        <div style="padding:20px;">
            @if($canEdit)
            <form method="POST" action="{{ route('maker.course_offerings.student_groups.store', $courseOffering) }}" class="form-row" style="margin-bottom:16px;">
                @csrf
                <div class="form-group">
                    <label>รหัสกลุ่ม</label>
                    <input type="text" name="group_code" value="{{ old('group_code') }}" required>
                </div>
                <div class="form-group">
                    <label>จำนวนนักศึกษา</label>
                    <input type="number" name="student_count" min="1" value="{{ old('student_count') }}" required>
                </div>
                <div class="form-group">
                    <label>สี</label>
                    <input type="text" name="color_code" value="{{ old('color_code') }}" placeholder="#2563eb">
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;">
                    <button class="btn btn-primary" type="submit">เพิ่มกลุ่ม</button>
                </div>
            </form>
            @endif

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>รหัสกลุ่ม</th>
                            <th>จำนวนนักศึกษา</th>
                            <th>สี</th>
                            @if($canEdit)<th></th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($courseOffering->studentGroups as $group)
                            <tr>
                                @if($canEdit)
                                <td>
                                    <form id="group-update-{{ $group->id }}" method="POST" action="{{ route('maker.course_offerings.student_groups.update', [$courseOffering, $group]) }}">
                                        @csrf
                                        @method('PUT')
                                    </form>
                                    <input form="group-update-{{ $group->id }}" type="text" name="group_code" value="{{ $group->group_code }}" required>
                                </td>
                                <td><input form="group-update-{{ $group->id }}" type="number" name="student_count" min="1" value="{{ $group->student_count }}" required></td>
                                <td><input form="group-update-{{ $group->id }}" type="text" name="color_code" value="{{ $group->color_code }}"></td>
                                <td style="text-align:right;">
                                    <div style="display:flex;gap:8px;justify-content:flex-end;">
                                        <button form="group-update-{{ $group->id }}" class="btn btn-secondary" type="submit">บันทึก</button>
                                        <form method="POST" action="{{ route('maker.course_offerings.student_groups.destroy', [$courseOffering, $group]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-ghost" type="submit">ลบ</button>
                                        </form>
                                    </div>
                                </td>
                                @else
                                <td style="font-weight:600;">{{ $group->group_code }}</td>
                                <td>{{ $group->student_count }} คน</td>
                                <td>
                                    @if($group->color_code)
                                        <span style="display:inline-block;width:14px;height:14px;border-radius:3px;background:{{ $group->color_code }};vertical-align:middle;margin-right:6px;"></span>{{ $group->color_code }}
                                    @else
                                        <span style="color:var(--fg-3);">—</span>
                                    @endif
                                </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $canEdit ? 4 : 3 }}" style="text-align:center;color:var(--fg-3);">ยังไม่มีกลุ่มนักศึกษา</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-hdr">
            <div>
                <div class="card-ttl">เงื่อนไขรายวิชา</div>
                <div class="caption" style="margin-top:4px;">รายวิชาที่ต้องเรียนมาก่อน</div>
            </div>
        </div>
        <div style="padding:20px;">
            @if($canEdit)
            <form method="POST" action="{{ route('maker.course_offerings.prerequisites.store', $courseOffering) }}" style="display:flex;gap:10px;align-items:flex-end;margin-bottom:12px;">
                @csrf
                <div style="flex:1;">
                    <select name="prerequisite_course_id" required>
                        <option value="">เลือกรายวิชาที่ต้องเรียนมาก่อน</option>
                        @foreach($availablePrerequisiteCourses as $candidateCourse)
                            <option value="{{ $candidateCourse->id }}" @selected(old('prerequisite_course_id') == $candidateCourse->id)>
                                {{ $candidateCourse->course_code }} {{ $candidateCourse->name_th ?? $candidateCourse->name_en }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-primary" type="submit">เพิ่ม</button>
            </form>
            @endif
            <div class="body-sm">
                @forelse($course?->prerequisites ?? collect() as $prerequisite)
                    @if($canEdit)
                    <form method="POST" action="{{ route('maker.course_offerings.prerequisites.destroy', [$courseOffering, $prerequisite]) }}" style="display:inline-flex;align-items:center;gap:6px;margin:0 6px 6px 0;">
                        @csrf
                        @method('DELETE')
                        <span class="badge badge-gray">{{ $prerequisite->course_code }}</span>
                        <button class="btn btn-ghost" type="submit" style="padding:4px 8px;">ลบ</button>
                    </form>
                    @else
                    <span class="badge badge-gray" style="margin:0 6px 6px 0;">{{ $prerequisite->course_code }}</span>
                    @endif
                @empty
                    <span style="color:var(--fg-3);">ไม่มีเงื่อนไขรายวิชาที่ต้องเรียนมาก่อน</span>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
