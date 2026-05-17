@php
    $course           = $courseOffering->course;
    $academicYear     = $courseOffering->academicYear;
    $canEdit          = $academicYear?->phase === 'scheduling';
    $lectureHours     = $course?->lecture_hours ?? 0;
    $labHours         = $course?->lab_hours ?? 0;
    $studentTotal     = $courseOffering->studentGroups->sum('student_count');
    $courseCapacity   = $course?->capacity ?? 0;
    $ungrouped        = max(0, $courseCapacity - $studentTotal);
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
            <div class="st-val">{{ $courseCapacity ?: '-' }}</div>
            <div class="st-lbl">จำนวนที่เปิดรับ</div>
        </div>
        <div class="st-card">
            <div class="st-val">{{ $studentTotal }}</div>
            <div class="st-lbl">จัดกลุ่มแล้ว</div>
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
            <div class="st-val">{{ $lectureHours }} / {{ $labHours }}</div>
            <div class="st-lbl">ชม.บรรยาย / ชม.ปฏิบัติ</div>
        </div>
    </div>

    <div class="card">
        <div class="card-hdr">
            <div>
                <div class="card-ttl">ข้อมูลรายวิชา</div>
                <div class="caption" style="margin-top:4px;">ข้อมูลจากรายวิชาหลักและการตั้งค่าระบบ</div>
            </div>
        </div>
        <div style="padding:20px;">
            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:20px;margin-bottom:20px;">
                <div>
                    <div class="caption">ภาควิชา</div>
                    <div style="font-weight:600;margin-top:4px;">{{ $course?->department?->name ?? '-' }}</div>
                </div>
                <div>
                    <div class="caption">หน่วยกิต</div>
                    <div style="font-weight:600;margin-top:4px;">{{ $course?->credits ?? '-' }} หน่วยกิต</div>
                </div>
                <div>
                    <div class="caption">ชั้นปี</div>
                    <div style="font-weight:600;margin-top:4px;">ปี {{ $course?->default_year_level ?? '-' }}</div>
                </div>
                <div>
                    <div class="caption">จำนวนที่เปิดรับ</div>
                    <div style="font-weight:600;margin-top:4px;">{{ $courseCapacity ?: '-' }} คน</div>
                </div>
                <div>
                    <div class="caption">จำนวนสัปดาห์สอน</div>
                    <div style="font-weight:600;margin-top:4px;">{{ $teachingWeeks }} สัปดาห์ <span class="caption">(ค่าตั้งระบบ)</span></div>
                </div>
                <div>
                    <div class="caption">ชั่วโมงบรรยาย</div>
                    <div style="font-weight:600;margin-top:4px;">{{ $lectureHours }} ชั่วโมง</div>
                </div>
                <div>
                    <div class="caption">ชั่วโมงปฏิบัติการ</div>
                    <div style="font-weight:600;margin-top:4px;">{{ $labHours }} ชั่วโมง</div>
                </div>
            </div>

            @if($canEdit)
            <form method="POST" action="{{ route('maker.course_offerings.update', $courseOffering) }}" style="border-top:1px solid var(--border-1);padding-top:20px;">
                @csrf
                @method('PUT')
                <div class="form-row">
                    <div class="form-group">
                        <label>การจัดรอบฝึกปฏิบัติ</label>
                        <select name="requires_practicum_rotation">
                            <option value="0" @selected(! $courseOffering->requires_practicum_rotation)>ไม่มีการหมุนเวียนแหล่งฝึก</option>
                            <option value="1" @selected($courseOffering->requires_practicum_rotation)>มีการหมุนเวียนแหล่งฝึก</option>
                        </select>
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-end;">
                        <button type="submit" class="btn btn-primary">บันทึก</button>
                    </div>
                </div>
            </form>
            @else
            <div style="border-top:1px solid var(--border-1);padding-top:20px;display:grid;grid-template-columns:repeat(2,1fr);gap:16px;">
                <div>
                    <div class="caption">การจัดรอบฝึกปฏิบัติ</div>
                    <div style="font-weight:600;margin-top:4px;">{{ $courseOffering->requires_practicum_rotation ? 'มีการหมุนเวียนแหล่งฝึก' : 'ไม่มีการหมุนเวียนแหล่งฝึก' }}</div>
                </div>
                @if($courseOffering->practicum_note)
                <div>
                    <div class="caption">หมายเหตุการฝึกปฏิบัติ</div>
                    <div style="margin-top:4px;">{{ $courseOffering->practicum_note }}</div>
                </div>
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
                <div class="caption" style="margin-top:4px;">เปิดรับ {{ $courseCapacity ?: '-' }} คน · จัดกลุ่มแล้ว {{ $studentTotal }} คน · ยังไม่ได้จัดกลุ่ม {{ $ungrouped }} คน</div>
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
