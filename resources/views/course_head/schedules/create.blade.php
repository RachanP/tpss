@php
    $course = $courseOffering->course;
    $academicYear = $courseOffering->academicYear;
@endphp

<x-app-layout title="เพิ่มรายการสอน">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px;flex-wrap:wrap;">
        <div>
            <a href="{{ route('maker.course_offerings.schedules.index', $courseOffering) }}" class="body-sm" style="color:var(--brand-navy);text-decoration:none;">← กลับไปหน้ารายวิชา</a>
            <div class="eyebrow" style="margin-top:10px;">ตารางสอน</div>
            <h1 class="h1" style="margin:4px 0 6px;">เพิ่มรายการสอน</h1>
            <p class="body-sm" style="margin:0;">
                {{ $course?->course_code ?? '-' }} {{ $course?->name_th ?? $course?->name_en ?? '' }}
                · {{ $academicYear?->name ?? '-' }} / เทอม {{ $academicYear?->semester ?? '-' }}
            </p>
        </div>
        <div class="card-actions">
            <a class="btn btn-ghost" href="{{ route('maker.course_offerings.show', $courseOffering) }}">กลับไปหน้ารายวิชา</a>
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
                <div class="card-ttl">ข้อมูลรายการสอน</div>
                <div class="caption" style="margin-top:4px;">เลือกผู้สอนจากชุดผู้สอนและกลุ่มนักศึกษาของรายวิชานี้เท่านั้น</div>
            </div>
        </div>
        <div style="padding:20px;">
            <form method="POST" action="{{ route('maker.course_offerings.schedules.store', $courseOffering) }}">
                @csrf

                <div class="form-row">
                    <div class="form-group">
                        <label>วันที่สอน</label>
                        <input type="date" name="teaching_date" value="{{ old('teaching_date') }}" required>
                    </div>
                    <div class="form-group">
                        <label>เวลาเริ่ม</label>
                        <input type="time" name="start_time" value="{{ old('start_time') }}" required>
                    </div>
                    <div class="form-group">
                        <label>เวลาสิ้นสุด</label>
                        <input type="time" name="end_time" value="{{ old('end_time') }}" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>ประเภทกิจกรรม</label>
                        <select name="activity_type_id" required>
                            <option value="">เลือกประเภทกิจกรรม</option>
                            @foreach($activityTypes as $activityType)
                                <option value="{{ $activityType->id }}" @selected(old('activity_type_id') == $activityType->id)>
                                    {{ $activityType->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>ห้อง/สถานที่</label>
                        <select name="room_id">
                            <option value="">ไม่ระบุห้อง/สถานที่</option>
                            @foreach($rooms as $room)
                                <option value="{{ $room->id }}" @selected(old('room_id') == $room->id)>
                                    {{ $room->room_code }} - {{ $room->room_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>หัวข้อ</label>
                        <input type="text" name="topic" maxlength="255" value="{{ old('topic') }}">
                    </div>
                    <div class="form-group">
                        <label>จำนวนรองรับ</label>
                        <input type="number" name="capacity_required" min="1" value="{{ old('capacity_required') }}">
                    </div>
                    <div class="form-group">
                        <label>ป้ายกลุ่มย่อย</label>
                        <input type="text" name="sub_group_label" maxlength="20" value="{{ old('sub_group_label') }}">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group full">
                        <label>ผู้สอน</label>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;">
                            @forelse($courseOffering->instructorPool as $instructor)
                                <label style="display:flex;gap:8px;align-items:flex-start;padding:10px;border:1px solid var(--border);border-radius:8px;">
                                    <input type="checkbox" name="instructor_ids[]" value="{{ $instructor->id }}" @checked(in_array($instructor->id, old('instructor_ids', [])))>
                                    <span>
                                        <span style="display:block;font-weight:700;">{{ $instructor->formatted_name }}</span>
                                        <span class="caption">{{ $instructor->instructorProfile?->department?->name ?? '-' }}</span>
                                    </span>
                                </label>
                            @empty
                                <div class="caption">ยังไม่มีผู้สอนในรายวิชานี้</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group full">
                        <label>กลุ่มนักศึกษา</label>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;">
                            @forelse($courseOffering->studentGroups as $group)
                                <label style="display:flex;gap:8px;align-items:center;padding:10px;border:1px solid var(--border);border-radius:8px;">
                                    <input type="checkbox" name="student_group_ids[]" value="{{ $group->id }}" @checked(in_array($group->id, old('student_group_ids', [])))>
                                    <span style="font-weight:700;">{{ $group->group_code }}</span>
                                    <span class="caption">{{ $group->student_count }} คน</span>
                                </label>
                            @empty
                                <div class="caption">ยังไม่มีกลุ่มนักศึกษาในรายวิชานี้</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group full">
                        <label>หมายเหตุ</label>
                        <textarea name="remark" rows="3">{{ old('remark') }}</textarea>
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:10px;">
                    <a class="btn btn-ghost" href="{{ route('maker.course_offerings.schedules.index', $courseOffering) }}">ยกเลิก</a>
                    <button class="btn btn-primary" type="submit">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
