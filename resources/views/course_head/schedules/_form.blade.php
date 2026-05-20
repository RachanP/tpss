@php
    $schedule = $schedule ?? null;
    $selectedInstructorIds = collect(old('instructor_ids', $schedule?->instructors?->pluck('id')->all() ?? []))->map(fn ($id) => (string) $id)->all();
    $selectedGroupIds = collect(old('student_group_ids', $schedule?->studentGroups?->pluck('id')->all() ?? []))->map(fn ($id) => (string) $id)->all();
    $leadInstructorId = old(
        'lead_instructor_id',
        $schedule?->instructors?->first(fn ($instructor) => (bool) $instructor->pivot?->is_lead)?->id
    );
    $course = $courseOffering->course;
    $academicYear = $courseOffering->academicYear;
    $isGlobalCreate = (bool) ($isGlobalCreate ?? false);
    $availableOfferings = $availableOfferings ?? collect();
    $backUrl = $backUrl ?? route('maker.course_offerings.schedules.index', $courseOffering);
    $selectedOfferingId = old('course_offering_id', $courseOffering->id);
@endphp

<style>
    .schedule-form-shell {
        max-width: 980px;
        margin: 0 auto;
    }
    .schedule-sheet {
        border: 1px solid var(--border-1);
        border-radius: 12px;
        background: var(--surface);
        box-shadow: 0 12px 36px oklch(0% 0 0 / .08);
        overflow: hidden;
    }
    .sheet-handle {
        width: 42px;
        height: 4px;
        border-radius: 999px;
        background: oklch(84% 0.012 240);
        margin: 14px auto 8px;
    }
    .sheet-head {
        padding: 16px 22px 14px;
        border-bottom: 1px solid var(--border-1);
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: flex-start;
    }
    .sheet-tag {
        display: inline-flex;
        align-items: center;
        min-height: 22px;
        padding: 2px 9px;
        border-radius: 999px;
        background: oklch(93% 0.05 255);
        color: var(--brand-navy);
        font-size: 12px;
        font-weight: 900;
    }
    .sheet-title {
        margin-top: 7px;
        color: var(--fg-1);
        font-size: 22px;
        line-height: 1.3;
        font-weight: 900;
    }
    .sheet-sub {
        margin-top: 4px;
        color: var(--fg-3);
        font-size: 13px;
        line-height: 1.55;
    }
    .source-strip {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        padding: 16px 22px;
        border-bottom: 1px solid var(--border-1);
        background: oklch(98% 0.006 240);
    }
    .source-box {
        border: 1px solid var(--border-1);
        border-radius: 8px;
        background: var(--surface);
        padding: 11px 12px;
    }
    .source-title {
        color: var(--fg-1);
        font-weight: 900;
        font-size: 13px;
    }
    .source-copy {
        margin-top: 3px;
        color: var(--fg-3);
        font-size: 12px;
        line-height: 1.45;
    }
    .form-body {
        padding: 22px;
        display: flex;
        flex-direction: column;
        gap: 18px;
    }
    .form-section {
        border: 1px solid var(--border-1);
        border-radius: 10px;
        padding: 16px;
        background: var(--surface);
    }
    .form-section-title {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        align-items: center;
        margin-bottom: 14px;
    }
    .form-section-title strong {
        color: var(--fg-1);
        font-size: 15px;
    }
    .source-badge {
        display: inline-flex;
        align-items: center;
        min-height: 22px;
        padding: 2px 8px;
        border-radius: 999px;
        background: oklch(97% 0.012 240);
        border: 1px solid var(--border-1);
        color: var(--fg-2);
        font-size: 11px;
        font-weight: 800;
        white-space: nowrap;
    }
    .field-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }
    .field-grid.single {
        grid-template-columns: 1fr;
    }
    .form-field {
        min-width: 0;
    }
    .field-label {
        display: flex;
        align-items: baseline;
        gap: 5px;
        color: var(--fg-2);
        font-size: 13px;
        font-weight: 800;
        margin-bottom: 6px;
    }
    .required-mark {
        color: var(--status-conflict-fg);
        font-weight: 900;
    }
    .optional-note {
        color: var(--fg-3);
        font-size: 11px;
        font-weight: 600;
    }
    .form-control {
        width: 100%;
        min-height: 42px;
        border: 1px solid var(--border-1);
        border-radius: 8px;
        background: var(--surface);
        color: var(--fg-1);
        padding: 9px 11px;
        font: inherit;
        font-size: 14px;
    }
    textarea.form-control {
        min-height: 76px;
        resize: vertical;
    }
    .field-help {
        margin-top: 5px;
        color: var(--fg-3);
        font-size: 12px;
        line-height: 1.45;
    }
    .choice-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 9px;
    }
    .choice-card {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        min-height: 44px;
        padding: 10px 12px;
        border: 1px solid var(--border-1);
        border-radius: 8px;
        background: oklch(99% 0.003 240);
        cursor: pointer;
    }
    .choice-card input {
        margin-top: 3px;
        flex-shrink: 0;
    }
    .choice-main {
        color: var(--fg-1);
        font-weight: 800;
        line-height: 1.4;
    }
    .choice-sub {
        color: var(--fg-3);
        font-size: 12px;
        line-height: 1.45;
    }
    .sheet-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 16px 22px 20px;
        border-top: 1px solid var(--border-1);
        background: oklch(98% 0.006 240);
    }
    @media (max-width: 760px) {
        .source-strip,
        .field-grid,
        .choice-grid {
            grid-template-columns: 1fr;
        }
        .sheet-head {
            flex-direction: column;
        }
        .sheet-actions {
            flex-direction: column-reverse;
        }
        .sheet-actions .btn {
            justify-content: center;
        }
    }
</style>

<div class="schedule-form-shell">
    <form method="POST" action="{{ $action }}" data-testid="schedule-form" class="schedule-sheet">
        @csrf
        @if(($method ?? 'POST') !== 'POST')
            @method($method)
        @endif

        <div class="sheet-handle"></div>
        <div class="sheet-head">
            <div>
                <span class="sheet-tag">{{ $schedule ? 'แก้ไขกิจกรรม' : 'กิจกรรมใหม่' }}</span>
                <div class="sheet-title">{{ $schedule ? 'แก้ไขรายละเอียดกิจกรรม' : 'เพิ่มกิจกรรมในตาราง' }}</div>
                <div class="sheet-sub">
                    {{ $isGlobalCreate ? 'เลือกวิชาที่รับผิดชอบ แล้วกรอกรายละเอียดกิจกรรม' : (($course?->course_code ?? '-') . ' ' . ($course?->name_th ?? $course?->name_en ?? '')) }}
                    · {{ $academicYear?->name ?? '-' }} / เทอม {{ $academicYear?->semester ?? '-' }}
                </div>
            </div>
            <a href="{{ $backUrl }}" class="btn btn-secondary">กลับ</a>
        </div>

        <div class="source-strip">
            <div class="source-box">
                <div class="source-title">ดึงจาก Master Data</div>
                <div class="source-copy">ประเภทกิจกรรม ห้อง/สถานที่ และข้อมูลรายวิชาหลัก</div>
            </div>
            <div class="source-box">
                <div class="source-title">ดึงจากจัดการรายวิชา</div>
                <div class="source-copy">ชุดผู้สอน กลุ่มนักศึกษา และแผนจำนวนผู้เรียน</div>
            </div>
            <div class="source-box">
                <div class="source-title">กรอกเอง</div>
                <div class="source-copy">วัน เวลา หัวข้อ หมายเหตุ และรายละเอียดเฉพาะรายการนี้</div>
            </div>
        </div>

        @if($errors->any())
            <div style="padding:16px 22px 0;">
                <div style="border:1px solid var(--status-conflict-border);background:var(--status-conflict-bg);color:var(--status-conflict-fg);border-radius:8px;padding:12px 14px;font-weight:700;line-height:1.6;">
                    {{ $errors->first() }}
                </div>
            </div>
        @endif

        <div class="form-body">
            @if($isGlobalCreate)
                <section class="form-section">
                    <div class="form-section-title">
                        <strong>รายวิชา</strong>
                        <span class="source-badge">ดึงจากจัดการรายวิชา</span>
                    </div>
                    <div class="form-field">
                        <label class="field-label" for="course_offering_id">รายวิชา <span class="required-mark">*</span></label>
                        <select
                            id="course_offering_id"
                            name="course_offering_id"
                            required
                            class="form-control"
                            data-testid="schedule-course-offering"
                            onchange="const u = new URL('{{ route('maker.schedules.create') }}', window.location.origin); u.searchParams.set('course_offering_id', this.value); @if($weekStart ?? null) u.searchParams.set('week_start', '{{ $weekStart }}'); @endif window.location.href = u.toString();">
                            @foreach($availableOfferings as $offeringOption)
                                @php
                                    $optionCourse = $offeringOption->course;
                                    $optionYear = $offeringOption->academicYear;
                                @endphp
                                <option value="{{ $offeringOption->id }}" @selected((string) $selectedOfferingId === (string) $offeringOption->id)>
                                    {{ $optionCourse?->course_code ?? '-' }} · {{ $optionCourse?->name_th ?? $optionCourse?->name_en ?? '-' }} · {{ $optionYear?->name ?? '-' }}/{{ $optionYear?->semester ?? '-' }}
                                </option>
                            @endforeach
                        </select>
                        <div class="field-help">ระบบจะแสดงผู้สอนและกลุ่มนักศึกษาตามรายวิชาที่เลือก</div>
                        @error('course_offering_id') <div class="error-msg">{{ $message }}</div> @enderror
                    </div>
                </section>
            @endif

            <section class="form-section">
                <div class="form-section-title">
                    <strong>วันและเวลา</strong>
                    <span class="source-badge">กรอกเอง</span>
                </div>
                <div class="field-grid">
                    <div class="form-field">
                        <label class="field-label" for="start_date">วันที่เริ่ม <span class="required-mark">*</span></label>
                        <input id="start_date" name="start_date" type="date" required class="form-control" data-testid="schedule-start-date"
                            value="{{ old('start_date', $schedule?->start_date?->format('Y-m-d')) }}">
                        @error('start_date') <div class="error-msg">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-field">
                        <label class="field-label" for="end_date">วันที่สิ้นสุด <span class="required-mark">*</span></label>
                        <input id="end_date" name="end_date" type="date" required class="form-control" data-testid="schedule-end-date"
                            value="{{ old('end_date', $schedule?->end_date?->format('Y-m-d')) }}">
                        @error('end_date') <div class="error-msg">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-field">
                        <label class="field-label" for="start_time">เวลาเริ่ม <span class="required-mark">*</span></label>
                        <input id="start_time" name="start_time" type="time" required class="form-control" data-testid="schedule-start-time"
                            value="{{ old('start_time', $schedule ? substr((string) $schedule->start_time, 0, 5) : '') }}">
                        @error('start_time') <div class="error-msg">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-field">
                        <label class="field-label" for="end_time">เวลาสิ้นสุด <span class="required-mark">*</span></label>
                        <input id="end_time" name="end_time" type="time" required class="form-control" data-testid="schedule-end-time"
                            value="{{ old('end_time', $schedule ? substr((string) $schedule->end_time, 0, 5) : '') }}">
                        @error('end_time') <div class="error-msg">{{ $message }}</div> @enderror
                    </div>
                </div>
            </section>

            <section class="form-section">
                <div class="form-section-title">
                    <strong>ประเภทและสถานที่</strong>
                    <span class="source-badge">ดึงจาก Master Data</span>
                </div>
                <div class="field-grid">
                    <div class="form-field">
                        <label class="field-label" for="activity_type_id">ประเภทกิจกรรม <span class="required-mark">*</span></label>
                        <select id="activity_type_id" name="activity_type_id" required class="form-control" data-testid="schedule-activity-type">
                            <option value="">เลือกประเภทกิจกรรม</option>
                            @foreach($activityTypes as $activityType)
                                <option value="{{ $activityType->id }}" @selected((string) old('activity_type_id', $schedule?->activity_type_id) === (string) $activityType->id)>
                                    {{ $activityType->name }} · {{ $activityType->category }}
                                </option>
                            @endforeach
                        </select>
                        <div class="field-help">ข้อมูลนี้มาจาก Master Data ประเภทกิจกรรม</div>
                        @error('activity_type_id') <div class="error-msg">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-field">
                        <label class="field-label" for="room_id">ห้อง/สถานที่ <span class="optional-note">ไม่บังคับ</span></label>
                        <select id="room_id" name="room_id" class="form-control" data-testid="schedule-room">
                            <option value="">ไม่ระบุสถานที่</option>
                            @foreach($rooms as $room)
                                <option value="{{ $room->id }}" @selected((string) old('room_id', $schedule?->room_id) === (string) $room->id)>
                                    {{ $room->room_code }} · {{ $room->room_name }}{{ $room->building ? ' · ' . $room->building : '' }}{{ $room->capacity ? ' · รองรับ ' . $room->capacity . ' คน' : '' }}
                                </option>
                            @endforeach
                        </select>
                        <div class="field-help">เลือกจากห้อง/แหล่งฝึกที่ active ใน Master Data หรือเว้นว่างได้</div>
                        @error('room_id') <div class="error-msg">{{ $message }}</div> @enderror
                    </div>
                </div>
            </section>

            <section class="form-section">
                <div class="form-section-title">
                    <strong>รายละเอียดเฉพาะรายการ</strong>
                    <span class="source-badge">กรอกเอง</span>
                </div>
                <div class="field-grid">
                    <div class="form-field" style="grid-column:1 / -1;">
                        <label class="field-label" for="topic">หัวข้อ <span class="optional-note">ไม่บังคับ</span></label>
                        <input id="topic" name="topic" type="text" class="form-control" maxlength="255" data-testid="schedule-topic"
                            value="{{ old('topic', $schedule?->topic) }}" placeholder="เช่น การพยาบาลผู้ป่วยระบบหัวใจและหลอดเลือด">
                        <div class="field-help">ถ้าไม่ระบุ ระบบจะแสดงชื่อประเภทกิจกรรมแทน</div>
                        @error('topic') <div class="error-msg">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-field">
                        <label class="field-label" for="capacity_required">จำนวนรองรับ <span class="optional-note">ไม่บังคับ</span></label>
                        <input id="capacity_required" name="capacity_required" type="number" min="1" class="form-control" data-testid="schedule-capacity"
                            value="{{ old('capacity_required', $schedule?->capacity_required) }}" placeholder="เช่น 30">
                        <div class="field-help">ใช้เมื่อต้องการกำหนดจำนวนผู้เรียนเฉพาะกิจกรรมนี้</div>
                        @error('capacity_required') <div class="error-msg">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-field">
                        <label class="field-label" for="sub_group_label">ป้ายกลุ่มย่อย <span class="optional-note">ไม่บังคับ</span></label>
                        <input id="sub_group_label" name="sub_group_label" type="text" maxlength="20" class="form-control" data-testid="schedule-sub-group-label"
                            value="{{ old('sub_group_label', $schedule?->sub_group_label) }}" placeholder="เช่น a, b, 1, 2">
                        <div class="field-help">ใช้เมื่อแบ่งกลุ่มหลักเป็นกลุ่มย่อย</div>
                        @error('sub_group_label') <div class="error-msg">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-field" style="grid-column:1 / -1;">
                        <label class="field-label" for="remark">หมายเหตุ <span class="optional-note">ไม่บังคับ</span></label>
                        <textarea id="remark" name="remark" rows="3" class="form-control" data-testid="schedule-remark" placeholder="หมายเหตุเพิ่มเติม เช่น นักศึกษาต้องเตรียมเอกสารก่อนเข้าเรียน">{{ old('remark', $schedule?->remark) }}</textarea>
                        @error('remark') <div class="error-msg">{{ $message }}</div> @enderror
                    </div>
                </div>
            </section>

            <section class="form-section">
                <div class="form-section-title">
                    <strong>ผู้สอน</strong>
                    <span class="source-badge">ดึงจากจัดการรายวิชา</span>
                </div>
                <div class="field-help" style="margin-top:-6px;margin-bottom:12px;">เลือกได้เฉพาะอาจารย์ในชุดผู้สอนของรายวิชานี้</div>
                <div class="choice-grid">
                    @foreach($courseOffering->instructorPool as $instructor)
                        <label class="choice-card">
                            <input type="checkbox" name="instructor_ids[]" value="{{ $instructor->id }}" @checked(in_array((string) $instructor->id, $selectedInstructorIds, true)) data-testid="schedule-instructor">
                            <span>
                                <span class="choice-main">{{ $instructor->formatted_name ?? $instructor->name }}</span>
                                <span class="choice-sub">{{ $instructor->instructorProfile?->department?->name ?? 'ไม่ระบุภาควิชา' }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <div class="field-label" style="margin-top:12px;">ผู้สอน <span class="required-mark">*</span></div>
                @error('instructor_ids') <div class="error-msg">{{ $message }}</div> @enderror
                @error('instructor_ids.*') <div class="error-msg">{{ $message }}</div> @enderror

                <div class="form-field" style="margin-top:14px;">
                    <label class="field-label" for="lead_instructor_id">ผู้สอนหลัก <span class="optional-note">ไม่บังคับ</span></label>
                    <select id="lead_instructor_id" name="lead_instructor_id" class="form-control" data-testid="schedule-lead-instructor">
                        <option value="">ไม่ระบุ</option>
                        @foreach($courseOffering->instructorPool as $instructor)
                            <option value="{{ $instructor->id }}" @selected((string) $leadInstructorId === (string) $instructor->id)>
                                {{ $instructor->formatted_name ?? $instructor->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="field-help">ถ้าเลือก ต้องเป็นหนึ่งในผู้สอนที่ติ๊กไว้ด้านบน</div>
                    @error('lead_instructor_id') <div class="error-msg">{{ $message }}</div> @enderror
                </div>
            </section>

            <section class="form-section">
                <div class="form-section-title">
                    <strong>กลุ่มนักศึกษา</strong>
                    <span class="source-badge">ดึงจากจัดการรายวิชา</span>
                </div>
                <div class="field-help" style="margin-top:-6px;margin-bottom:12px;">เลือกกลุ่มที่เข้าร่วมรายการนี้จากกลุ่มที่สร้างในหน้าจัดการรายวิชา</div>
                <div class="choice-grid">
                    @foreach($courseOffering->studentGroups as $group)
                        <label class="choice-card">
                            <input type="checkbox" name="student_group_ids[]" value="{{ $group->id }}" @checked(in_array((string) $group->id, $selectedGroupIds, true)) data-testid="schedule-student-group">
                            <span>
                                <span class="choice-main">{{ $group->group_code }}</span>
                                <span class="choice-sub">{{ $group->student_count }} คน</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <div class="field-label" style="margin-top:12px;">กลุ่มนักศึกษา <span class="required-mark">*</span></div>
                @error('student_group_ids') <div class="error-msg">{{ $message }}</div> @enderror
                @error('student_group_ids.*') <div class="error-msg">{{ $message }}</div> @enderror
            </section>
        </div>

        <div class="sheet-actions">
            <a href="{{ $backUrl }}" class="btn btn-secondary">ยกเลิก</a>
            <button type="submit" class="btn btn-primary" data-testid="schedule-submit">{{ $submitLabel }}</button>
        </div>
    </form>
</div>
