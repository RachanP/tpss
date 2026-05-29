@php
    $course           = $courseOffering->course;
    $academicYear     = $courseOffering->academicYear;
    $canEdit          = $academicYear?->phase === 'scheduling';
    $lectureHours     = $course?->lecture_hours ?? 0;
    $labHours         = $course?->lab_hours ?? 0;
    $studentTotal     = $courseOffering->studentGroups->sum('student_count');
    $courseCapacity   = $course?->capacity ?? 0;
    $studentLimit     = $courseOffering->total_student_count ?: $courseCapacity;
    $ungrouped        = max(0, $studentLimit - $studentTotal);
    $defaultRotation  = (bool) ($course?->requires_practicum_rotation ?? false);
    $courseInfoErrorKeys = ['requires_practicum_rotation', 'practicum_note'];
    $instructorErrorKeys = ['user_id', 'course_role_id', 'instructor_pool'];
    $studentGroupErrorKeys = [
        'group_code',
        'student_count',
        'color_code',
        'group_prefix',
        'start_number',
        'group_count',
        'group_counts',
        'group_counts.*',
        'total_students',
        'group_ids',
        'group_ids.*',
        'student_groups',
    ];
    $courseInfoErrorKey = collect($courseInfoErrorKeys)->first(fn ($key) => $errors->has($key));
    $instructorErrorKey = collect($instructorErrorKeys)->first(fn ($key) => $errors->has($key));
    $studentGroupErrorKey = collect($studentGroupErrorKeys)->first(fn ($key) => $errors->has($key));
    $errorSection = session('error_section');
@endphp

<script>
    (function () {
        var key = 'tpss.courseOffering.scrollY.{{ $courseOffering->id }}';
        try {
            if ('scrollRestoration' in history) {
                history.scrollRestoration = 'manual';
            }

            var saved = window.sessionStorage.getItem(key);
            if (saved !== null) {
                window.sessionStorage.removeItem(key);
                var top = parseInt(saved, 10);
                if (Number.isFinite(top) && top >= 0) {
                    window.scrollTo(0, top);
                    requestAnimationFrame(function () { window.scrollTo(0, top); });
                }
            }

            window.tpssRememberCourseOfferingScroll = function () {
                try {
                    window.sessionStorage.setItem(key, String(window.scrollY || window.pageYOffset || 0));
                } catch (error) {}
            };
        } catch (error) {}
    })();
</script>

<x-app-layout title="รายละเอียดรายวิชา">
    @if($canEdit)
        <script>
            document.addEventListener('alpine:init', () => {
                if (! Alpine.store('offeringPage')) {
                    Alpine.store('offeringPage', {
                        editing: {
                            courseInfo: false,
                            instructors: false,
                            studentGroups: false,
                        },
                    });
                }
            });
        </script>

        <style>
            /* ── Section quick toggle ("แก้ไข" ใน card-hdr ของแต่ละ section) ── */
            .section-edit-quick-toggle {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
                height: 32px;
                padding: 0 14px;
                margin-left: auto;
                border: 1px solid var(--border-1);
                background: var(--bg-2);
                color: var(--fg-2);
                border-radius: 8px;
                font-size: 13px;
                font-weight: 600;
                line-height: 1;
                cursor: pointer;
                font-family: inherit;
                outline: none;
                white-space: nowrap;
                transition: all 0.15s;
                flex-shrink: 0;
            }
            .section-edit-quick-toggle:hover {
                border-color: var(--brand-navy);
                color: var(--brand-navy);
                background: var(--brand-navy-50);
            }
            /* override .is-locked-section button opacity — ปุ่มนี้ต้องเด่นเสมอ */
            .is-locked-section button.section-edit-quick-toggle {
                opacity: 1 !important;
            }
        </style>
    @endif

    @php
        $phase = $courseOffering->academicYear?->phase ?? 'preparation';
        $instructorCount = $courseOffering->instructorPool->count();
        $groupCount = $courseOffering->studentGroups->count();
        $scheduleCount = $courseOffering->schedules_count ?? 0;
        $approvalLabels = [
            'draft'     => ['label' => 'แบบร่าง',    'tone' => null],
            'pending'   => ['label' => 'รออนุมัติ',  'tone' => 'info'],
            'published' => ['label' => 'อนุมัติแล้ว','tone' => 'success'],
            'rejected'  => ['label' => 'ตีกลับ',     'tone' => 'conflict'],
        ];
        $approval = $courseOffering->approval_status ?? 'draft';
        $approvalMeta = $approvalLabels[$approval] ?? ['label' => $approval, 'tone' => null];
    @endphp

    {{-- Header --}}
    <div style="margin-bottom:16px;">
        <a href="{{ route('maker.course_offerings.index') }}" class="back-link" data-testid="back-to-offerings">
            <span class="back-link-icon" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"/>
                    <polyline points="12 19 5 12 12 5"/>
                </svg>
            </span>
            <span>กลับไปรายการรายวิชา</span>
        </a>
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
            <div style="flex:1;min-width:220px;">
                <h1 class="h1" style="margin:0 0 6px;">{{ $course?->course_code ?? '-' }} {{ $course?->name_th ?? $course?->name_en ?? '' }}</h1>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <p class="body-sm" style="margin:0;">
                        {{ $course?->curriculum?->name ?? '-' }} · {{ $academicYear?->name ?? '-' }} / เทอม {{ $academicYear?->semester ?? '-' }}
                    </p>
                    @if($courseOffering->requires_practicum_rotation)
                        <span class="badge badge-warn" style="font-size:0.7rem;">ฝึกปฏิบัติ</span>
                    @endif
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                @if($phase === 'scheduling')
                    <span class="badge" style="background:var(--status-success-bg);color:var(--status-success-fg);border:1px solid var(--status-success-border);">เปิดจัดตาราง</span>
                @elseif($phase === 'published')
                    <span class="badge badge-primary">เผยแพร่แล้ว</span>
                @else
                    <span class="badge badge-gray">ยังไม่เปิดจัดตาราง</span>
                @endif
                @if($approvalMeta['tone'])
                    <span class="badge" style="background:var(--status-{{ $approvalMeta['tone'] }}-bg);color:var(--status-{{ $approvalMeta['tone'] }}-fg);border:1px solid var(--status-{{ $approvalMeta['tone'] }}-border);">
                        {{ $approvalMeta['label'] }}
                    </span>
                @else
                    <span class="badge badge-gray">{{ $approvalMeta['label'] }}</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Quick Summary Strip --}}
    @php
        $summaryStrip = [
            [
                'label' => 'จำนวนชุดผู้สอน',
                'value' => $instructorCount,
                'unit'  => 'คน',
                'href'  => '#instructors',
                'icon'  => '<circle cx="9" cy="7" r="4"/><path d="M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/><path d="M21 21v-2a4 4 0 0 0-3-3.87"/>',
            ],
            [
                'label' => 'กลุ่มนักศึกษา',
                'value' => $groupCount,
                'unit'  => $studentLimit > 0 ? "กลุ่ม · {$studentTotal}/{$studentLimit} คน" : 'กลุ่ม',
                'href'  => '#student-groups',
                'icon'  => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
            ],
            [
                'label' => 'ตารางสอนที่จัดแล้ว',
                'value' => $scheduleCount,
                'unit'  => 'รายการ',
                'href'  => $phase === 'scheduling' || $phase === 'published' ? route('maker.course_offerings.schedules.index', $courseOffering) : null,
                'icon'  => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
                'external' => true,
            ],
            [
                'label' => 'หน่วยกิต',
                'value' => $course?->credits ?? '-',
                'unit'  => 'หน่วยกิต · ' . ($lectureHours + $labHours) . ' ชม./สัปดาห์',
                'href'  => '#course-info',
                'icon'  => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
            ],
        ];
    @endphp
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:20px;">
        @foreach($summaryStrip as $item)
            <a href="{{ $item['href'] ?? '#' }}" style="
                display:flex;align-items:center;gap:12px;
                padding:14px 16px;
                background:var(--bg-1);
                border:2px solid var(--brand-navy-300);
                border-top:4px solid var(--brand-navy);
                border-radius:10px;
                text-decoration:none;
                color:var(--fg-1);
                transition:border-color 0.15s, background 0.15s;
            " onmouseover="this.style.background='var(--brand-navy-50)';this.style.borderColor='var(--brand-navy)'" onmouseout="this.style.background='var(--bg-1)';this.style.borderColor='var(--brand-navy-300)'">
                <div style="
                    display:flex;align-items:center;justify-content:center;
                    width:38px;height:38px;flex-shrink:0;
                    background:var(--brand-navy);color:#fff;
                    border-radius:8px;
                ">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        {!! $item['icon'] !!}
                    </svg>
                </div>
                <div style="min-width:0;flex:1;">
                    <div style="font-size:0.75rem;color:var(--fg-2);font-weight:600;">{{ $item['label'] }}</div>
                    <div style="display:flex;align-items:baseline;gap:4px;margin-top:3px;">
                        <span style="font-size:1.5rem;font-weight:700;color:var(--brand-navy);font-family:var(--font-display);line-height:1;">{{ $item['value'] }}</span>
                        <span style="font-size:0.75rem;color:var(--fg-3);">{{ $item['unit'] }}</span>
                    </div>
                </div>
            </a>
        @endforeach
    </div>

    @if(session('error') && ! $errorSection)
        <div style="background:var(--status-conflict-bg);border:1px solid var(--status-conflict-border);color:var(--status-conflict-fg);padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:14px;">
            {{ session('error') }}
        </div>
    @endif

    @if(!$canEdit)
        <div style="background:var(--status-info-bg);border:1px solid var(--status-info-border);color:var(--status-info-fg);padding:12px 18px;border-radius:8px;margin-bottom:20px;font-size:14px;display:flex;align-items:center;gap:10px;">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            <span>ยังไม่เปิดช่วงจัดตาราง — ดูข้อมูลได้อย่างเดียว การแก้ไขจะเปิดใช้งานเมื่อ Admin เปิดช่วงจัดตาราง</span>
        </div>
    @endif

    <div class="card" id="course-info">
        <div class="card-hdr">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="display:flex;align-items:center;justify-content:center;width:36px;height:36px;background:var(--brand-navy-50);color:var(--brand-navy);border-radius:8px;flex-shrink:0;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                    </svg>
                </div>
                <div>
                    <div class="card-ttl">ข้อมูลรายวิชา</div>
                    <div class="caption" style="margin-top:4px;">ข้อมูลจากรายวิชาหลักและการตั้งค่าระบบ</div>
                </div>
            </div>
            @if($canEdit)
                <button
                    type="button"
                    x-data
                    @click="$store.offeringPage.editing.courseInfo = !$store.offeringPage.editing.courseInfo"
                    class="section-edit-quick-toggle"
                    :aria-pressed="$store.offeringPage.editing.courseInfo ? 'true' : 'false'"
                    data-testid="section-edit-quick-toggle-course-info"
                    x-text="$store.offeringPage.editing.courseInfo ? 'เสร็จสิ้น' : 'แก้ไข'"
                ></button>
            @endif
        </div>
        <div style="padding:20px;">
            @if($courseInfoErrorKey)
                <div class="section-error-alert">
                    {{ $errors->first($courseInfoErrorKey) }}
                </div>
            @endif
            @if(session('error') && $errorSection === 'course-info')
                <div class="section-error-alert">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Primary fields --}}
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:18px;">
                <div>
                    <div class="caption">ภาควิชา</div>
                    <div style="font-weight:600;margin-top:4px;font-size:0.95rem;">{{ $course?->department?->name ?? '-' }}</div>
                </div>
                <div>
                    <div class="caption">หน่วยกิต</div>
                    <div style="font-weight:600;margin-top:4px;font-size:0.95rem;">{{ $course?->credits ?? '-' }} หน่วยกิต</div>
                </div>
                <div>
                    <div class="caption">ชั้นปี</div>
                    <div style="font-weight:600;margin-top:4px;font-size:0.95rem;">{{ $course?->default_year_level ? 'ชั้นปีที่ ' . $course->default_year_level : '-' }}</div>
                </div>
                <div>
                    <div class="caption">จำนวนที่เปิดรับ</div>
                    <div style="font-weight:600;margin-top:4px;font-size:0.95rem;">{{ $courseCapacity ?: '-' }} คน</div>
                </div>
            </div>

            {{-- Secondary fields --}}
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:20px;padding-top:14px;border-top:1px dashed var(--border-1);">
                <div>
                    <div class="caption">ชั่วโมงเรียน (บรรยาย / ปฏิบัติ)</div>
                    <div style="font-weight:600;margin-top:4px;">{{ $lectureHours }} / {{ $labHours }} <span class="caption">ชม.</span></div>
                </div>
                <div>
                    <div class="caption">จำนวนสัปดาห์สอน</div>
                    <div style="font-weight:600;margin-top:4px;">{{ $teachingWeeks }} <span class="caption">สัปดาห์ (ค่าตั้งระบบ)</span></div>
                </div>
            </div>

            @if($canEdit)
            <div
                x-data="rotationAutosave({
                    rotation: '{{ old('requires_practicum_rotation', $courseOffering->requires_practicum_rotation ? '1' : '0') }}',
                    note: {{ Js::from(old('practicum_note', $courseOffering->practicum_note ?? '')) }},
                    defaultRotation: '{{ $defaultRotation ? '1' : '0' }}',
                    updateUrl: {{ Js::from(route('maker.course_offerings.update', $courseOffering)) }},
                    csrfToken: {{ Js::from(csrf_token()) }},
                })"
                style="margin-top:24px;padding:18px 20px;background:var(--bg-2);border:1px solid var(--border-1);border-radius:10px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:4px;flex-wrap:wrap;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--brand-navy);">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                        <div style="font-weight:700;font-size:0.9375rem;color:var(--fg-1);">ค่าที่ปรับได้เฉพาะรอบเปิดสอนนี้</div>
                    </div>
                    {{-- Saving/saved status --}}
                    <div style="display:inline-flex;align-items:center;gap:6px;font-size:0.75rem;min-height:18px;">
                        <template x-if="saving">
                            <span style="display:inline-flex;align-items:center;gap:6px;color:var(--fg-3);">
                                <span style="width:12px;height:12px;border:2px solid var(--brand-navy-300);border-top-color:var(--brand-navy);border-radius:50%;animation:rotation-spin 0.8s linear infinite;"></span>
                                กำลังบันทึก...
                            </span>
                        </template>
                        <template x-if="!saving && savedFlash">
                            <span x-transition style="display:inline-flex;align-items:center;gap:4px;color:var(--status-success-fg);">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                บันทึกแล้ว
                            </span>
                        </template>
                        <template x-if="!saving && error">
                            <span style="color:var(--status-conflict-fg);" x-text="error"></span>
                        </template>
                    </div>
                </div>
                <div class="caption" style="margin-bottom:14px;">ข้อมูลข้างต้นมาจาก Master Data (อ่านอย่างเดียว) — ส่วนนี้คือค่าที่หัวหน้าวิชาปรับได้ตามสถานการณ์ของภาคเรียน</div>

                <fieldset :disabled="!$store.offeringPage.editing.courseInfo" style="border:0;padding:0;margin:0;min-width:0;">
                    <div class="form-group" style="margin-bottom:14px;">
                        <label>การจัดรอบฝึกปฏิบัติ</label>
                        <select x-model="rotation" @change="onRotationChange()">
                            <option value="0" @selected(! $courseOffering->requires_practicum_rotation)>ไม่มีการหมุนเวียนแหล่งฝึก</option>
                            <option value="1" @selected($courseOffering->requires_practicum_rotation)>มีการหมุนเวียนแหล่งฝึก</option>
                        </select>
                        <div class="caption" style="margin-top:6px;">
                            ค่าเริ่มต้นจาก Master Data: {{ $defaultRotation ? 'มีการหมุนเวียนแหล่งฝึก' : 'ไม่มีการหมุนเวียนแหล่งฝึก' }}
                        </div>
                    </div>

                    <div class="form-group" x-show="isOverride" x-cloak style="margin-bottom:0;">
                        <label style="display:flex;align-items:center;gap:6px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--status-warning-fg);flex-shrink:0;">
                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                <line x1="12" y1="9" x2="12" y2="13"/>
                                <line x1="12" y1="17" x2="12.01" y2="17"/>
                            </svg>
                            ระบุเหตุผลการแก้ไข <span style="color:var(--status-conflict-fg)">*</span>
                        </label>
                        <textarea
                            x-model="note"
                            @input.debounce.700ms="onNoteInput()"
                            rows="3"
                            maxlength="1000"
                            placeholder="เช่น ปีการศึกษานี้ใช้ simulation lab แทนการหมุนเวียนแหล่งฝึก"></textarea>
                    </div>
                </fieldset>
            </div>

            <style>@keyframes rotation-spin { to { transform: rotate(360deg); } }</style>
            <script>
                function rotationAutosave(config) {
                    return {
                        rotation: config.rotation,
                        note: config.note || '',
                        defaultRotation: config.defaultRotation,
                        updateUrl: config.updateUrl,
                        csrfToken: config.csrfToken,
                        saving: false,
                        savedFlash: false,
                        error: '',
                        _flashTimer: null,
                        _abort: null,
                        get isOverride() { return this.rotation !== this.defaultRotation; },
                        onRotationChange() {
                            // ถ้ากลับมาตรง default → ล้าง note + save ทันที
                            if (!this.isOverride) {
                                this.note = '';
                                this.save();
                                return;
                            }
                            // override mode — ถ้ามี note เดิมแล้ว save เลย ไม่งั้นรอ user พิมพ์
                            if (this.note.trim().length > 0) {
                                this.save();
                            }
                        },
                        onNoteInput() {
                            // save เฉพาะกรณี override + มี note
                            if (this.isOverride && this.note.trim().length > 0) {
                                this.save();
                            }
                        },
                        async save() {
                            if (this._abort) this._abort.abort();
                            const controller = new AbortController();
                            this._abort = controller;
                            this.error = '';
                            this.saving = true;
                            this.savedFlash = false;
                            try {
                                const formData = new FormData();
                                formData.append('_method', 'PUT');
                                formData.append('_token', this.csrfToken);
                                formData.append('requires_practicum_rotation', this.rotation);
                                if (this.isOverride) formData.append('practicum_note', this.note);
                                const res = await fetch(this.updateUrl, {
                                    method: 'POST',
                                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                    body: formData,
                                    signal: controller.signal,
                                });
                                const data = await res.json().catch(() => ({}));
                                if (!res.ok) {
                                    this.error = data.message || 'บันทึกไม่สำเร็จ';
                                } else {
                                    this.savedFlash = true;
                                    clearTimeout(this._flashTimer);
                                    this._flashTimer = setTimeout(() => { this.savedFlash = false; }, 2000);
                                }
                            } catch (e) {
                                if (e.name === 'AbortError') return;
                                this.error = 'เชื่อมต่อไม่ได้';
                            } finally {
                                if (this._abort === controller) {
                                    this._abort = null;
                                    this.saving = false;
                                }
                            }
                        },
                    };
                }
            </script>
            @else
            <div style="border-top:1px solid var(--border-1);padding-top:20px;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;">
                <div>
                    <div class="caption">การจัดรอบฝึกปฏิบัติ</div>
                    <div style="font-weight:600;margin-top:4px;">{{ $courseOffering->requires_practicum_rotation ? 'มีการหมุนเวียนแหล่งฝึก' : 'ไม่มีการหมุนเวียนแหล่งฝึก' }}</div>
                    @if($courseOffering->requires_practicum_rotation !== $defaultRotation)
                        <div class="caption" style="margin-top:5px;color:var(--status-warning-fg);">ต่างจากค่าเริ่มต้นใน Master Data</div>
                    @endif
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

    @php
        $poolData = $courseOffering->instructorPool->map(fn($u) => [
            'id'             => $u->id,
            'name'           => $u->formatted_name,
            'department'     => $u->instructorProfile?->department?->name ?? '-',
            'department_id'  => $u->instructorProfile?->department_id,
            'is_coordinator' => (int) $u->id === (int) $courseOffering->coordinator_id,
            'course_role_id' => $u->pivot->course_role_id,
            'role_name'      => optional($courseRoles->firstWhere('id', $u->pivot->course_role_id))->name_th
                ?? ($u->pivot->role_in_course === 'coordinator' ? 'หัวหน้าวิชา' : null),
        ]);
        $allInstructors = $availableInstructors->map(fn($u) => [
            'id'           => $u->id,
            'name'         => $u->formatted_name,
            'department'   => $u->instructorProfile?->department?->name ?? '-',
            'department_id'=> $u->instructorProfile?->department_id,
        ]);
        $courseRolesData = $courseRoles->map(fn($r) => ['id' => $r->id, 'name' => $r->name_th]);
        $storeUrl    = route('maker.course_offerings.instructors.store', $courseOffering);
        $roleBase    = route('maker.course_offerings.instructors.role', [$courseOffering, '__ID__']);
        $destroyBase = route('maker.course_offerings.instructors.destroy', [$courseOffering, '__ID__']);
        $courseDeptId = $course?->department_id;
    @endphp

    <div class="card" id="instructors" @if($canEdit) :class="!$store.offeringPage.editing.instructors ? 'is-locked-section' : ''" @endif style="overflow:visible;scroll-margin-top:72px;" x-data="{
        pool: {{ $poolData->toJson() }},
        all: {{ $allInstructors->toJson() }},
        roles: {{ $courseRolesData->toJson() }},
        search: '',
        open: false,
        showAll: false,
        loading: false,
        error: '',
        ddTop: 0, ddLeft: 0, ddWidth: 0,
        roleMenuId: null,
        storeUrl: '{{ $storeUrl }}',
        roleBase: '{{ $roleBase }}',
        destroyBase: '{{ $destroyBase }}',
        csrfToken: '{{ csrf_token() }}',
        courseDeptId: {{ $courseDeptId ?? 'null' }},
        async changeRole(userId, roleId) {
            this.loading = true; this.error = '';
            try {
                const r = await fetch(this.roleBase.replace('__ID__', userId), {
                    method: 'PATCH', credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ course_role_id: roleId })
                });
                const data = await r.json();
                if (!r.ok) { this.error = data.message ?? 'เกิดข้อผิดพลาด'; return; }
                const u = this.pool.find(x => x.id === userId);
                if (u) { u.course_role_id = data.course_role_id; u.role_name = data.role_name; }
                this.roleMenuId = null;
            } catch { this.error = 'ไม่สามารถเชื่อมต่อได้'; }
            finally { this.loading = false; }
        },
        get available() {
            const s = this.search.toLowerCase();
            const inPool = new Set(this.pool.map(u => u.id));
            return this.all.filter(u => {
                if (inPool.has(u.id)) return false;
                if (!this.showAll && this.courseDeptId) {
                    if (u.department_id !== this.courseDeptId) return false;
                }
                return s === '' || u.name.toLowerCase().includes(s) || u.department.toLowerCase().includes(s);
            });
        },
        openDropdown() {
            const r = this.$refs.searchInput.getBoundingClientRect();
            this.ddTop = r.bottom + window.scrollY + 4;
            this.ddLeft = r.left + window.scrollX;
            this.ddWidth = r.width;
            this.open = true;
        },
        async add(user) {
            this.loading = true; this.error = '';
            try {
                const r = await fetch(this.storeUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({ user_id: user.id })
                });
                const data = await r.json();
                if (!r.ok) { this.error = data.message ?? 'เกิดข้อผิดพลาด'; return; }
                this.pool.push(data);
                this.search = ''; this.open = false;
            } catch { this.error = 'ไม่สามารถเชื่อมต่อได้'; }
            finally { this.loading = false; }
        },
        async remove(userId) {
            this.error = '';
            const url = this.destroyBase.replace('__ID__', userId);
            try {
                const r = await fetch(url, {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken }
                });
                const data = await r.json();
                if (!r.ok) { this.error = data.message ?? 'เกิดข้อผิดพลาด'; return; }
                this.pool = this.pool.filter(u => u.id !== userId);
            } catch { this.error = 'ไม่สามารถเชื่อมต่อได้'; }
        }
    }">
        <div class="card-hdr">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="display:flex;align-items:center;justify-content:center;width:36px;height:36px;background:var(--brand-navy-50);color:var(--brand-navy);border-radius:8px;flex-shrink:0;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="9" cy="7" r="4"/><path d="M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/><path d="M21 21v-2a4 4 0 0 0-3-3.87"/>
                    </svg>
                </div>
                <div>
                    <div class="card-ttl">ชุดผู้สอน</div>
                    <div class="caption" style="margin-top:4px;" x-text="pool.length ? pool.length + ' คน' : 'ยังไม่มีผู้สอน'"></div>
                </div>
            </div>
            @if($canEdit)
                <button
                    type="button"
                    @click="$store.offeringPage.editing.instructors = !$store.offeringPage.editing.instructors"
                    class="section-edit-quick-toggle"
                    :aria-pressed="$store.offeringPage.editing.instructors ? 'true' : 'false'"
                    data-testid="section-edit-quick-toggle-instructors"
                    x-text="$store.offeringPage.editing.instructors ? 'เสร็จสิ้น' : 'แก้ไข'"
                ></button>
            @endif
        </div>
        <div style="padding:20px;" @if($canEdit) :inert="!$store.offeringPage.editing.instructors" @endif>
            @if($instructorErrorKey)
                <div class="section-error-alert">
                    {{ $errors->first($instructorErrorKey) }}
                </div>
            @endif
            @if(session('error') && $errorSection === 'instructors')
                <div class="section-error-alert">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Error message --}}
            <div x-show="error" x-text="error" style="background:var(--status-conflict-bg);border:1px solid var(--status-conflict-border);color:var(--status-conflict-fg);padding:10px 14px;border-radius:6px;font-size:13px;margin-bottom:14px;"></div>

            @if($canEdit)
            {{-- Combobox --}}
            <div style="position:relative;margin-bottom:20px;">
                <div style="position:relative;">
                    <input
                        x-ref="searchInput"
                        type="text"
                        x-model="search"
                        @focus="openDropdown()"
                        @input="openDropdown()"
                        placeholder="ค้นหาชื่ออาจารย์หรือภาควิชา..."
                        style="width:100%;padding-right:32px;"
                        autocomplete="off"
                    >
                    <svg x-show="!loading" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);opacity:0.4;pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <svg x-show="loading" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);opacity:0.4;pointer-events:none;animation:spin 1s linear infinite;"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4"/></svg>
                </div>

                {{-- Backdrop --}}
                <template x-teleport="body">
                    <div x-show="open" x-cloak @click="open = false; search = ''" style="position:fixed;inset:0;z-index:98;"></div>
                </template>

                {{-- Dropdown teleported to body --}}
                <template x-teleport="body">
                    <div
                        x-show="open"
                        x-cloak
                        :style="`position:absolute;top:${ddTop}px;left:${ddLeft}px;width:${ddWidth}px;background:#fff;border:1px solid var(--border-1);border-radius:6px;box-shadow:0 4px 16px rgba(0,0,0,0.12);z-index:99;`"
                    >
                        {{-- Filter toggle inside dropdown --}}
                        <div x-show="courseDeptId" style="padding:10px 12px;border-bottom:1px solid var(--border-1);background:var(--bg-2);">
                            <div style="display:inline-flex;gap:6px;">
                                <button type="button"
                                    @click.stop="showAll = false"
                                    :style="!showAll
                                        ? 'background:var(--brand-navy);color:#fff;border-color:var(--brand-navy);'
                                        : 'background:var(--bg-1);color:var(--fg-2);border-color:var(--border-1);'"
                                    style="cursor:pointer;font-size:12px;font-weight:600;padding:6px 14px;border-radius:999px;font-family:inherit;transition:all 0.15s;border-width:1px;border-style:solid;outline:none;appearance:none;-webkit-appearance:none;">
                                    เฉพาะภาควิชานี้
                                </button>
                                <button type="button"
                                    @click.stop="showAll = true"
                                    :style="showAll
                                        ? 'background:var(--brand-navy);color:#fff;border-color:var(--brand-navy);'
                                        : 'background:var(--bg-1);color:var(--fg-2);border-color:var(--border-1);'"
                                    style="cursor:pointer;font-size:12px;font-weight:600;padding:6px 14px;border-radius:999px;font-family:inherit;transition:all 0.15s;border-width:1px;border-style:solid;outline:none;appearance:none;-webkit-appearance:none;">
                                    อาจารย์ทั้งหมด
                                </button>
                            </div>
                        </div>
                        {{-- Results --}}
                        <div style="max-height:220px;overflow-y:auto;">
                            <template x-for="user in available" :key="user.id">
                                <div
                                    @click="add(user)"
                                    style="padding:10px 14px;cursor:pointer;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border-1);"
                                    @mouseenter="$el.style.background='var(--surface-2)'"
                                    @mouseleave="$el.style.background=''"
                                >
                                    <div>
                                        <div style="font-weight:600;font-size:14px;" x-text="user.name"></div>
                                        <div style="font-size:12px;color:var(--fg-3);" x-text="user.department"></div>
                                    </div>
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="opacity:0.4;flex-shrink:0;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                </div>
                            </template>
                            <div
                                x-show="search.length > 0 && available.length === 0"
                                style="padding:12px 14px;font-size:13px;color:var(--fg-3);"
                            >ไม่พบอาจารย์ที่ตรงกัน</div>
                        </div>
                    </div>
                </template>
            </div>
            @endif

            {{-- Pills --}}
            <div style="display:flex;flex-direction:column;gap:8px;" x-show="pool.length > 0">
                <template x-for="user in pool" :key="user.id">
                    <div style="display:flex;align-items:center;gap:14px;background:#fff;border:1px solid var(--border-1);border-radius:8px;padding:12px 16px;transition:border-color 0.15s;"
                         @mouseover="$el.style.borderColor='var(--brand-navy-300)'"
                         @mouseout="$el.style.borderColor='var(--border-1)'">
                        <div style="display:flex;align-items:center;justify-content:center;width:36px;height:36px;flex-shrink:0;background:var(--brand-navy-50);color:var(--brand-navy);border-radius:50%;font-weight:700;font-size:0.875rem;font-family:var(--font-display);"
                             x-text="user.name.replace(/^(อ\.|ดร\.|ผศ\.|รศ\.|ศ\.|นาย|นาง|นางสาว|น\.ส\.)+\s*/g, '').charAt(0)">
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:600;font-size:14px;color:var(--fg-1);" x-text="user.name"></div>
                            <div style="color:var(--fg-3);font-size:12px;margin-top:2px;" x-text="user.department"></div>
                        </div>

                        {{-- Role selector (coordinator = static badge) --}}
                        <template x-if="user.is_coordinator">
                            <div class="course-role-badge course-role-badge-head">
                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 3l7 4v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V7l7-4z"/>
                                    <path d="M9 12l2 2 4-5"/>
                                </svg>
                                <span>หัวหน้าวิชา</span>
                            </div>
                        </template>
                        <template x-if="!user.is_coordinator">
                            <div class="course-role-control">
                                @if($canEdit)
                                <button type="button"
                                    class="course-role-trigger"
                                    :class="user.role_name ? 'is-assigned' : 'is-empty'"
                                    @click.stop="roleMenuId = roleMenuId === user.id ? null : user.id"
                                    :aria-expanded="roleMenuId === user.id"
                                    aria-haspopup="listbox">
                                    <span class="course-role-trigger-text" x-text="user.role_name || 'ยังไม่กำหนดบทบาท'"></span>
                                    <svg class="course-role-chevron" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M6 9l6 6 6-6"/>
                                    </svg>
                                </button>
                                <div x-show="roleMenuId === user.id"
                                    x-cloak
                                    @click.outside="roleMenuId = null"
                                    x-transition:enter="transition ease-out duration-150"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    class="course-role-menu"
                                    role="listbox">
                                    <button type="button"
                                        class="course-role-option"
                                        :class="{ 'is-selected': !user.course_role_id }"
                                        @click="changeRole(user.id, null)"
                                        role="option">
                                        <span class="course-role-option-label">ยังไม่กำหนดบทบาท</span>
                                        <svg x-show="!user.course_role_id" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M20 6L9 17l-5-5"/>
                                        </svg>
                                    </button>
                                    <template x-for="role in roles" :key="role.id">
                                        <button type="button"
                                            class="course-role-option"
                                            :class="{ 'is-selected': user.course_role_id === role.id }"
                                            @click="changeRole(user.id, role.id)"
                                            role="option">
                                            <span class="course-role-option-label" x-text="role.name"></span>
                                            <svg x-show="user.course_role_id === role.id" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M20 6L9 17l-5-5"/>
                                            </svg>
                                        </button>
                                    </template>
                                </div>
                                @else
                                <div class="course-role-readonly" :class="user.role_name ? 'is-assigned' : 'is-empty'">
                                    <span class="course-role-dot"></span>
                                    <span x-text="user.role_name || 'ยังไม่กำหนดบทบาท'"></span>
                                </div>
                                @endif
                            </div>
                        </template>

                        @if($canEdit)
                        <button type="button" x-show="!user.is_coordinator" @click="remove(user.id)" title="ลบอาจารย์ออกจากชุดผู้สอน"
                            style="background:transparent;border:none;cursor:pointer;width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center;color:var(--fg-3);border-radius:50%;flex-shrink:0;transition:all 0.15s;"
                            @mouseenter="$el.style.background='#fee2e2';$el.style.color='#dc2626'"
                            @mouseleave="$el.style.background='transparent';$el.style.color='var(--fg-3)'">
                            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                        <div x-show="user.is_coordinator" style="width:32px;flex-shrink:0;"></div>
                        @endif
                    </div>
                </template>
            </div>
            <div x-show="pool.length === 0" style="color:var(--fg-3);font-size:14px;">ยังไม่มีผู้สอนในรายวิชานี้</div>
        </div>
    </div>

    <style>
        @keyframes spin { to { transform: translateY(-50%) rotate(360deg); } }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 9px 16px 9px 12px;
            margin-bottom: 14px;
            background: var(--brand-navy);
            color: #fff;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid var(--brand-navy);
            border-radius: 8px;
            transition: background 0.15s, transform 0.15s;
        }

        .back-link:hover {
            background: var(--brand-navy-700, #0f1e3a);
            color: #fff;
        }

        .back-link:hover .back-link-icon svg {
            transform: translateX(-2px);
        }

        .back-link:focus-visible {
            outline: 2px solid var(--brand-navy);
            outline-offset: 2px;
        }

        .back-link-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            background: rgba(255, 255, 255, 0.18);
            border-radius: 6px;
        }

        .back-link-icon svg {
            transition: transform 0.15s;
        }

        .section-error-alert {
            margin-bottom: 14px;
            padding: 10px 14px;
            border: 1px solid var(--status-conflict-border);
            border-radius: 6px;
            background: var(--status-conflict-bg);
            color: var(--status-conflict-fg);
            font-size: 13px;
            font-weight: 600;
            line-height: 1.55;
        }

        .course-role-control {
            position: relative;
            flex-shrink: 0;
            width: 250px;
        }

        .course-role-trigger,
        .course-role-readonly,
        .course-role-badge {
            min-height: 38px;
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 8px;
            padding: 8px 12px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.35;
            white-space: nowrap;
        }

        .course-role-trigger {
            cursor: pointer;
            transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        }

        .course-role-trigger:hover {
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
        }

        .course-role-trigger:focus-visible {
            outline: 2px solid rgba(0, 36, 84, 0.24);
            outline-offset: 2px;
        }

        .course-role-trigger.is-assigned,
        .course-role-readonly.is-assigned {
            background: oklch(96% 0.025 255);
            border: 1px solid oklch(82% 0.055 255);
            color: oklch(34% 0.09 255);
        }

        .course-role-trigger.is-empty,
        .course-role-readonly.is-empty {
            background: oklch(97% 0.045 82);
            border: 1px solid oklch(84% 0.09 82);
            color: oklch(43% 0.1 72);
            font-style: italic;
        }

        .course-role-badge-head {
            flex-shrink: 0;
            width: 250px;
            background: oklch(96% 0.055 150);
            border: 1px solid oklch(78% 0.12 150);
            color: oklch(33% 0.11 150);
        }

        .course-role-dot {
            width: 7px;
            height: 7px;
            flex: 0 0 7px;
            border-radius: 999px;
            background: currentColor;
            opacity: 0.72;
        }

        .course-role-trigger-text {
            flex: 1;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            text-align: center;
        }

        .course-role-chevron {
            flex: 0 0 auto;
            opacity: 0.7;
        }

        .course-role-menu {
            position: absolute;
            right: 0;
            top: calc(100% + 6px);
            width: 280px;
            max-height: 280px;
            overflow-y: auto;
            padding: 8px;
            border: 1px solid oklch(88% 0.018 240);
            border-radius: 8px;
            background: rgba(252, 254, 255, 0.98);
            box-shadow: 0 18px 38px rgba(15, 23, 42, 0.18), 0 2px 8px rgba(15, 23, 42, 0.08);
            z-index: 40;
            transform-origin: top right;
        }

        .course-role-option {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            min-height: 36px;
            border: 0;
            border-radius: 6px;
            background: rgba(252, 254, 255, 0.94);
            color: var(--fg-1);
            cursor: pointer;
            padding: 8px 10px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            text-align: left;
        }

        .course-role-option:hover,
        .course-role-option.is-selected {
            background: oklch(95% 0.025 240);
            color: var(--brand-navy);
        }

        .course-role-option-label {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        @keyframes banner-pulse {
            0%, 100% { box-shadow: 0 4px 12px rgba(190,140,0,0.15); }
            50% { box-shadow: 0 4px 24px rgba(190,140,0,0.45), 0 0 0 4px var(--status-warning-bg); }
        }

        [x-cloak] { display: none !important; }

        /* View-mode lock — pure visual; interactivity disabled via HTML `inert` attribute (a11y-safe) */
        .is-locked-section input,
        .is-locked-section textarea,
        .is-locked-section select {
            background: var(--bg-2) !important;
            color: var(--fg-2);
            cursor: default;
        }
        .is-locked-section input[type="checkbox"] {
            visibility: hidden;
        }
        .is-locked-section button:not([type="submit"]) {
            opacity: 0.35;
        }
        /* inert (HTML attr) handles pointer-events + tab order natively in all modern browsers */

        .group-builder {
            margin-bottom: 18px;
            padding: 0;
            border: 2px solid var(--brand-navy-300);
            border-radius: 12px;
            background: var(--bg-1);
            overflow: hidden;
        }

        .group-builder-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            background: var(--brand-navy-50);
            border-bottom: 1px solid var(--brand-navy-300);
        }

        .group-builder-header-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            background: var(--brand-navy);
            color: #fff;
            border-radius: 8px;
            flex-shrink: 0;
        }

        .group-builder-body {
            padding: 18px;
        }

        .group-builder-title {
            color: var(--fg-1);
            font-weight: 800;
            font-size: 15px;
            line-height: 1.2;
        }

        .group-builder-subtitle {
            color: var(--fg-3);
            font-size: 12px;
            margin-top: 2px;
        }

        .group-builder-section {
            margin-bottom: 16px;
        }

        .group-builder-section:last-child {
            margin-bottom: 0;
        }

        .group-builder-section-label {
            display: block;
            color: var(--fg-2);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .group-total-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid var(--status-warning-border);
            border-radius: 999px;
            background: var(--status-warning-bg);
            color: var(--status-warning-fg);
            padding: 5px 12px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .group-preset-chip {
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 999px;
            font-family: inherit;
            border: 1px solid var(--border-1);
            background: var(--bg-1);
            color: var(--fg-2);
            outline: none;
            transition: all 0.15s;
        }

        .group-preset-chip:hover {
            border-color: var(--brand-navy-300);
            color: var(--brand-navy);
        }

        .group-preset-chip.is-active {
            background: var(--brand-navy);
            color: #fff;
            border-color: var(--brand-navy);
        }

        .group-builder-fields {
            display: grid;
            grid-template-columns: 130px 130px 1fr;
            gap: 14px;
            align-items: end;
        }

        .group-builder-fields label {
            display: flex;
            flex-direction: column;
            gap: 8px;
            color: var(--fg-1);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .group-builder-fields input {
            padding: 10px 14px;
            background: var(--bg-1);
            border: 2px solid var(--border-1);
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            color: var(--fg-1);
            font-family: inherit;
            outline: none;
            transition: all 0.15s;
            box-shadow: 0 1px 0 rgba(0,0,0,0.02);
        }

        .group-builder-fields input:hover {
            border-color: var(--brand-navy-300);
        }

        .group-builder-fields input:focus {
            border-color: var(--brand-navy);
            box-shadow: 0 0 0 3px var(--brand-navy-50);
        }

        .group-stepper {
            display: inline-flex;
            align-items: stretch;
            border: 2px solid var(--border-1);
            border-radius: 8px;
            overflow: hidden;
            background: var(--bg-1);
            transition: all 0.15s;
            box-shadow: 0 1px 0 rgba(0,0,0,0.02);
        }

        .group-stepper:hover {
            border-color: var(--brand-navy-300);
        }

        .group-stepper:focus-within {
            border-color: var(--brand-navy);
            box-shadow: 0 0 0 3px var(--brand-navy-50);
        }

        .group-stepper button {
            width: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-2);
            color: var(--fg-1);
            border: 0;
            cursor: pointer;
            font-size: 18px;
            font-weight: 700;
            outline: none;
            transition: background 0.15s;
        }

        .group-stepper button:hover {
            background: var(--brand-navy);
            color: #fff;
        }

        .group-stepper input {
            width: 70px;
            border: 0 !important;
            border-radius: 0;
            background: transparent;
            text-align: center;
            font-size: 15px;
            font-weight: 700;
            color: var(--fg-1);
            -moz-appearance: textfield;
            box-shadow: none;
            padding: 10px 0;
        }

        .group-stepper input::-webkit-outer-spin-button,
        .group-stepper input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .group-mode-row {
            display: inline-flex;
            border: 1px solid var(--border-1);
            border-radius: 8px;
            background: var(--bg-1);
            padding: 3px;
            gap: 2px;
        }

        .group-mode-btn {
            border: 0;
            background: transparent;
            color: var(--fg-2);
            cursor: pointer;
            padding: 6px 14px;
            border-radius: 6px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            outline: none;
            transition: all 0.15s;
        }

        .group-mode-btn.is-active {
            background: var(--brand-navy);
            color: #fff;
        }

        .group-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .group-preview-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border: 1px solid var(--border-1);
            border-radius: 8px;
            background: var(--bg-1);
            color: var(--fg-1);
            font-size: 13px;
            transition: border-color 0.15s;
        }

        .group-preview-chip:hover {
            border-color: var(--brand-navy-300);
        }

        .group-preview-chip strong {
            font-weight: 700;
            font-size: 13px;
        }

        .group-preview-chip span:last-child {
            color: var(--fg-3);
            font-size: 12px;
        }

        .group-preview-color {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex: 0 0 10px;
            box-shadow: 0 0 0 1px rgba(0,0,0,0.08);
        }

        .group-count-mini {
            width: 56px;
            border: 1px solid var(--border-1) !important;
            border-radius: 6px;
            padding: 4px 8px;
            text-align: center;
            font-size: 13px;
            font-weight: 700;
            outline: none;
        }

        .group-builder-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid var(--border-1);
            flex-wrap: wrap;
        }

        .group-builder-submit {
            min-width: 160px;
        }

        .student-group-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .student-group-row {
            display: grid;
            grid-template-columns: minmax(220px, 1.3fr) minmax(120px, 0.55fr) minmax(100px, 0.45fr) auto;
            gap: 12px;
            align-items: center;
            padding: 10px 12px;
            border: 1px solid oklch(90% 0.014 235);
            border-radius: 8px;
            background: rgba(252, 254, 255, 0.98);
        }

        .student-group-row.has-bulk-select {
            grid-template-columns: 32px minmax(220px, 1.3fr) minmax(120px, 0.55fr) minmax(100px, 0.45fr) auto;
        }

        .student-group-row.is-readonly {
            grid-template-columns: minmax(220px, 1.3fr) minmax(120px, 0.55fr) minmax(100px, 0.45fr);
        }

        .student-group-select {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .student-group-select input {
            width: 18px;
            height: 18px;
            min-height: 18px;
            cursor: pointer;
        }

        .student-group-select-all {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: var(--fg-2);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .student-group-select-all input {
            width: 17px;
            height: 17px;
            cursor: pointer;
        }

        .student-group-row label {
            display: flex;
            flex-direction: column;
            gap: 4px;
            color: var(--fg-3);
            font-size: 11px;
            font-weight: 700;
        }

        .student-group-row input {
            min-height: 36px;
            font-size: 14px;
        }

        .student-group-code {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            min-width: 0;
        }

        .student-group-code label {
            flex: 1;
            min-width: 0;
        }

        .student-group-code-display {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .student-group-count input {
            max-width: 120px;
        }

        .student-group-color input[type='color'] {
            width: 52px;
            padding: 3px;
            cursor: pointer;
        }

        .student-group-swatch {
            width: 16px;
            height: 16px;
            flex: 0 0 16px;
            border-radius: 999px;
            margin-bottom: 10px;
            box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.14);
        }

        .student-group-row.has-bulk-select .student-group-swatch {
            position: absolute;
            left: 0;
            top: 31px;
            margin: 0;
        }

        .student-group-row.has-bulk-select .student-group-code {
            position: relative;
            padding-left: 26px;
        }

        .student-group-actions {
            display: inline-flex;
            justify-content: flex-end;
            gap: 6px;
        }

        .student-group-bulkbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 10px;
            padding: 10px 12px;
            border: 1px solid oklch(89% 0.018 235);
            border-radius: 8px;
            background: oklch(98% 0.006 235);
        }

        .student-group-bulkbar-actions {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-bulk-delete {
            min-height: 34px;
            border: 1px solid var(--status-conflict-border);
            border-radius: 8px;
            background: var(--status-conflict-bg);
            color: var(--status-conflict-fg);
            cursor: pointer;
            padding: 6px 12px;
            font-family: inherit;
            font-weight: 700;
        }

        .btn-bulk-delete:disabled {
            cursor: not-allowed;
            opacity: .45;
        }

        .student-group-confirm-overlay {
            position: fixed;
            inset: 0;
            z-index: var(--z-modal);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgba(15, 23, 42, .42);
        }

        .student-group-confirm-dialog {
            width: min(460px, 100%);
            border: 1px solid oklch(88% 0.014 235);
            border-radius: 14px;
            background: var(--surface);
            box-shadow: 0 24px 70px rgba(15, 23, 42, .2);
            padding: 22px;
        }

        .student-group-confirm-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .icon-btn-save,
        .icon-btn-delete {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            cursor: pointer;
            background: rgba(252, 254, 255, 0.96);
        }

        .icon-btn-save {
            border: 1px solid oklch(82% 0.055 245);
            color: var(--brand-navy);
        }

        .icon-btn-delete {
            border: 1px solid oklch(88% 0.028 30);
            color: oklch(42% 0.12 30);
        }

        .icon-btn-save:hover {
            background: oklch(96% 0.025 245);
        }

        .icon-btn-delete:hover {
            background: oklch(96% 0.035 30);
        }

        .student-group-empty {
            padding: 24px;
            text-align: center;
            color: var(--fg-3);
            border: 1px dashed oklch(86% 0.018 235);
            border-radius: 8px;
            background: oklch(98% 0.008 230);
        }

        @media (max-width: 720px) {
            .course-role-control,
            .course-role-badge-head {
                width: 100%;
            }

            .group-builder-main {
                flex-direction: column;
            }

            .group-builder-submit {
                width: 100%;
            }

            .group-builder-actions {
                width: 100%;
                justify-content: flex-start;
            }

            .group-builder-fields {
                grid-template-columns: 1fr 1fr;
            }

            .student-group-row,
            .student-group-row.is-readonly {
                grid-template-columns: 1fr;
            }

            .student-group-row.has-bulk-select {
                grid-template-columns: 32px 1fr;
            }

            .student-group-count input {
                max-width: none;
            }

            .student-group-actions {
                justify-content: flex-start;
            }
        }

        /* Narrow laptop (1280-1440px content area) — reduce paddings + group editor stacking */
        @media (max-width: 1280px) {
            .group-builder-fields {
                grid-template-columns: 110px 110px 1fr;
                gap: 10px;
            }
            .instructor-search-input,
            .course-instructor-search input {
                font-size: 13px;
            }
        }

        /* Inline student group editor — at narrow widths fold to simpler 2-col layout */
        @media (max-width: 900px) {
            .student-group-editor-row {
                grid-template-columns: 28px 32px 1fr 90px 28px !important;
                gap: 8px !important;
                padding: 10px 12px !important;
            }
            .student-group-editor-row input[type="text"],
            .student-group-editor-row input[type="number"] {
                font-size: 13px;
            }
        }
    </style>

    <div class="card" id="student-groups" style="scroll-margin-top:72px;">
        <div class="card-hdr">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="display:flex;align-items:center;justify-content:center;width:36px;height:36px;background:var(--brand-navy-50);color:var(--brand-navy);border-radius:8px;flex-shrink:0;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                    </svg>
                </div>
                <div style="flex:1;">
                    <div class="card-ttl">กลุ่มนักศึกษา</div>
                    <div class="caption" style="margin-top:4px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                        <span>เปิดรับ {{ $studentLimit ?: '-' }} คน · จัดกลุ่มแล้ว {{ $studentTotal }} คน</span>
                        @if($ungrouped > 0)
                            <span class="badge badge-warn" style="font-size:0.7rem;">ยังไม่ได้จัดกลุ่ม {{ $ungrouped }} คน</span>
                        @endif
                    </div>
                </div>
            </div>
            @if($canEdit)
                <button
                    type="button"
                    x-data
                    @click="$store.offeringPage.editing.studentGroups = !$store.offeringPage.editing.studentGroups"
                    class="section-edit-quick-toggle"
                    :aria-pressed="$store.offeringPage.editing.studentGroups ? 'true' : 'false'"
                    data-testid="section-edit-quick-toggle-student-groups"
                    x-text="$store.offeringPage.editing.studentGroups ? 'เสร็จสิ้น' : 'แก้ไข'"
                ></button>
            @endif
        </div>
        <div
            style="padding:20px;"
            x-data="{
                selectedGroups: [],
                groupIds: {{ Js::from($courseOffering->studentGroups->pluck('id')->map(fn ($id) => (string) $id)->values()) }},
                confirmBulkDeleteOpen: false,
                balanceNotice: '',
                get allGroupsSelected() {
                    return this.groupIds.length > 0 && this.selectedGroups.length === this.groupIds.length;
                },
                toggleAllGroups(checked) {
                    this.selectedGroups = checked ? [...this.groupIds] : [];
                },
                balanceAll() {
                    window.dispatchEvent(new CustomEvent('student-groups-balance', { detail: { studentLimit: {{ (int) $studentLimit }} } }));
                    this.balanceNotice = 'กำลังกระจายยอดให้ทุกกลุ่ม...';
                    setTimeout(() => { this.balanceNotice = ''; }, 4000);
                },
                handleGroupDeleted(id) {
                    const sid = String(id);
                    this.groupIds = this.groupIds.filter(g => String(g) !== sid);
                    this.selectedGroups = this.selectedGroups.filter(g => String(g) !== sid);
                }
            }"
            @student-group-deleted.window="handleGroupDeleted($event.detail.id)"
        >
            @if($studentGroupErrorKey)
                <div class="section-error-alert">
                    {{ $errors->first($studentGroupErrorKey) }}
                </div>
            @endif
            @if(session('error') && $errorSection === 'student-groups')
                <div class="section-error-alert">
                    {{ session('error') }}
                </div>
            @endif

            @if($canEdit)
            @php $hasExistingGroups = $courseOffering->studentGroups->isNotEmpty(); @endphp
            <div x-show="$store.offeringPage.editing.studentGroups" x-cloak x-data="{
                    open: {{ ($hasExistingGroups || $ungrouped <= 0) ? 'false' : 'true' }},
                    ungroupedCount: {{ (int) $ungrouped }},
                    showSuccessFlash: false,
                    _flashTimer: null,
                }"
                @student-groups-count-changed.window="
                    const newCount = parseInt($event.detail.ungrouped) || 0;
                    if (ungroupedCount > 0 && newCount === 0) {
                        showSuccessFlash = true;
                        clearTimeout(_flashTimer);
                        _flashTimer = setTimeout(() => { showSuccessFlash = false }, 4000);
                    }
                    ungroupedCount = newCount;
                "
                style="margin-bottom:18px;">
                {{-- Success flash banner --}}
                @if($hasExistingGroups)
                    <div x-show="showSuccessFlash"
                         x-cloak
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 -translate-y-3"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-300"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:var(--status-success-bg);border:2px solid var(--status-success);color:var(--status-success-fg);border-radius:8px;box-shadow:0 4px 12px rgba(40,140,80,0.15);margin-bottom:12px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                        <span style="font-size:14px;font-weight:600;">จัดกลุ่มครบตามจำนวนนักศึกษาที่เปิดรับแล้ว</span>
                    </div>
                @endif

                {{-- Warning banner --}}
                @if($hasExistingGroups)
                    <div x-show="!open && ungroupedCount > 0"
                         x-cloak
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 -translate-y-3"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-200"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         x-effect="$el.style.animation = ''; if (ungroupedCount > 0 && !showSuccessFlash) { $nextTick(() => { $el.style.animation = 'banner-pulse 0.6s ease-out 2'; }); }"
                         style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px;background:var(--status-warning-bg);border:2px solid var(--status-warning);color:var(--status-warning-fg);border-radius:8px;flex-wrap:wrap;box-shadow:0 4px 12px rgba(190,140,0,0.15);">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                            </svg>
                            <span style="font-size:14px;font-weight:600;" x-text="'มีนักศึกษา ' + ungroupedCount + ' คนที่ยังไม่ได้กระจายกลุ่ม'"></span>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <button type="button" @click="balanceAll()"
                                style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;font-weight:600;padding:7px 14px;border-radius:6px;font-family:inherit;border:1px solid var(--brand-navy);background:var(--brand-navy);color:#fff;outline:none;transition:all 0.15s;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
                                </svg>
                                กระจายให้กลุ่มเดิม
                            </button>
                            <button type="button" @click="open = true" data-testid="bulk-groups-open"
                                style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;font-weight:600;padding:7px 14px;border-radius:6px;font-family:inherit;border:1px solid var(--status-warning-fg);background:var(--bg-1);color:var(--status-warning-fg);outline:none;transition:all 0.15s;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                                </svg>
                                สร้างกลุ่มใหม่
                            </button>
                        </div>
                    </div>
                @endif

                {{-- Expandable bulk-create form --}}
                <div x-show="open" x-cloak>
            <form method="POST"
                action="{{ route('maker.course_offerings.student_groups.bulk_store', $courseOffering) }}"
                data-testid="bulk-groups-form"
                data-preserve-scroll
                @submit="window.tpssRememberCourseOfferingScroll && window.tpssRememberCourseOfferingScroll()"
                x-data="{
                    prefix: '{{ old('group_prefix', 'A') }}',
                    start: {{ (int) old('start_number', 1) }},
                    count: {{ (int) old('group_count', $ungrouped > 0 ? min(9, max(1, (int) ceil($ungrouped / 30))) : 1) }},
                    total: {{ (int) max(1, $ungrouped) }},
                    customMode: {{ old('group_counts') ? 'true' : 'false' }},
                    customCounts: {{ Js::from(array_map('intval', old('group_counts', []))) }},
                    palette: ['#2563eb', '#16a34a', '#ca8a04', '#dc2626', '#7c3aed', '#0891b2', '#db2777', '#4f46e5', '#65a30d', '#ea580c'],
                    get safeCount() { return Math.max(1, parseInt(this.count) || 1); },
                    get safeTotal() { return Math.max(1, parseInt(this.total) || 1); },
                    get base() { return Math.floor(this.safeTotal / this.safeCount); },
                    get remainder() { return this.safeTotal % this.safeCount; },
                    normalizeCounts() {
                        const counts = Array.from({ length: this.safeCount }, (_, i) => {
                            const fallback = this.base + (i < this.remainder ? 1 : 0);
                            const current = this.customCounts[i];

                            if (this.customMode && (current === '' || current === null)) {
                                return '';
                            }

                            if (current === undefined || current === '' || current === null) {
                                return fallback;
                            }

                            const parsed = parseInt(current);
                            return Number.isNaN(parsed) ? fallback : parsed;
                        });
                        this.customCounts = counts;
                    },
                    setEvenSplit() {
                        this.customMode = false;
                        this.customCounts = [];
                    },
                    applyPreset(groupCount) {
                        this.count = Math.max(1, groupCount);
                        this.setEvenSplit();
                    },
                    get suggestedAutoCount() {
                        return Math.max(1, Math.ceil(this.safeTotal / 30));
                    },
                    enableCustom() {
                        this.customMode = true;
                        this.normalizeCounts();
                    },
                    get customTotal() {
                        this.normalizeCounts();
                        return this.customCounts.reduce((sum, count) => sum + (parseInt(count) || 0), 0);
                    },
                    get preview() {
                        this.normalizeCounts();
                        return Array.from({ length: this.customMode ? this.safeCount : Math.min(this.safeCount, 12) }, (_, i) => ({
                            index: i,
                            code: `${this.prefix || 'A'}${(parseInt(this.start) || 0) + i}`,
                            count: this.customMode ? this.customCounts[i] : this.base + (i < this.remainder ? 1 : 0),
                            color: this.palette[i % this.palette.length],
                        }));
                    }
                }"
                class="group-builder">
                @csrf

                {{-- Header --}}
                <div class="group-builder-header">
                    <div class="group-builder-header-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                        </svg>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div class="group-builder-title">สร้างกลุ่มแบบเร็ว</div>
                        <div class="group-builder-subtitle">ตั้งเทมเพลตหรือกำหนดเอง — แบ่งยอด {{ $ungrouped }} คนให้ทุกกลุ่มอัตโนมัติ</div>
                    </div>
                    <div class="group-total-pill">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                        {{ $ungrouped }} คน
                    </div>
                </div>

                <div class="group-builder-body">
                    {{-- Field row --}}
                    <div class="group-builder-section">
                        <div class="group-builder-fields">
                            <label>
                                <span>รหัสนำหน้า</span>
                                <input type="text" name="group_prefix" x-model="prefix" data-testid="bulk-group-prefix" required>
                            </label>
                            <label>
                                <span>เริ่มที่</span>
                                <input type="number" name="start_number" x-model.number="start" data-testid="bulk-group-start" min="0" required>
                            </label>
                            <label>
                                <span style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                                    จำนวนกลุ่ม
                                    <button type="button" @click="applyPreset(suggestedAutoCount)"
                                        x-show="count !== suggestedAutoCount"
                                        x-cloak
                                        style="background:none;border:none;color:var(--brand-navy);font-size:11px;font-weight:600;cursor:pointer;padding:0;font-family:inherit;text-decoration:underline;"
                                        :title="'แนะนำ ' + suggestedAutoCount + ' กลุ่ม (กลุ่มละ ~30 คน)'">
                                        ↺ อัตโนมัติ
                                    </button>
                                </span>
                                <div class="group-stepper">
                                    <button type="button" @click="count = Math.max(1, count - 1); setEvenSplit()" aria-label="ลดจำนวนกลุ่ม">−</button>
                                    <input type="number" name="group_count" x-model.number="count" data-testid="bulk-group-count" min="1" max="100" required>
                                    <button type="button" @click="count = Math.min(100, count + 1); setEvenSplit()" aria-label="เพิ่มจำนวนกลุ่ม">+</button>
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- Mode toggle --}}
                    <div class="group-builder-section">
                        <label class="group-builder-section-label">การแบ่งจำนวน</label>
                        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                            <div class="group-mode-row">
                                <button type="button" class="group-mode-btn" :class="{ 'is-active': !customMode }" @click="setEvenSplit()">แบ่งเท่ากัน</button>
                                <button type="button" class="group-mode-btn" :class="{ 'is-active': customMode }" @click="enableCustom()">กำหนดเอง</button>
                            </div>
                            <span class="caption" x-show="customMode" x-text="'รวมที่กำหนด ' + customTotal + ' / {{ $ungrouped }} คน'" :style="customMode && customTotal != {{ $ungrouped }} ? 'color:var(--status-warning-fg);font-weight:600;' : ''"></span>
                        </div>
                    </div>

                    {{-- Preview --}}
                    <div class="group-builder-section">
                        <label class="group-builder-section-label">ตัวอย่างกลุ่มที่จะสร้าง</label>
                        <div class="group-preview" aria-label="ตัวอย่างกลุ่มที่จะสร้าง">
                            <template x-for="group in preview" :key="group.code">
                                <div class="group-preview-chip">
                                    <span class="group-preview-color" :style="{ background: group.color }"></span>
                                    <strong x-text="group.code"></strong>
                                    <template x-if="!customMode">
                                        <span x-text="group.count + ' คน'"></span>
                                    </template>
                                    <template x-if="customMode">
                                        <input class="group-count-mini" type="number" name="group_counts[]" min="1" max="9999" x-model="customCounts[group.index]">
                                    </template>
                                </div>
                            </template>
                        </div>
                        <div class="caption" x-show="!customMode && safeCount > 12" style="margin-top:6px;">แสดงตัวอย่าง 12 กลุ่มแรก · ระบบจะสร้างครบ <span x-text="safeCount"></span> กลุ่ม</div>
                    </div>

                    {{-- Footer with submit --}}
                    <div class="group-builder-footer">
                        <div class="caption">
                            สร้าง <strong style="color:var(--fg-1);font-weight:700;" x-text="safeCount"></strong> กลุ่ม
                            <span x-show="!customMode">— กลุ่มละ <strong style="color:var(--fg-1);font-weight:700;" x-text="base + (remainder > 0 ? '–' + (base + 1) : '')"></strong> คน</span>
                        </div>
                        <button class="btn btn-primary group-builder-submit" type="submit" data-testid="bulk-groups-submit">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;">
                                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                            สร้างกลุ่ม
                        </button>
                    </div>
                </div>
                @if($hasExistingGroups)
                    <div style="margin-top:12px;text-align:right;">
                        <button type="button" @click="open = false"
                            style="cursor:pointer;font-size:13px;font-weight:600;padding:6px 14px;border-radius:6px;font-family:inherit;border:1px solid var(--border-1);background:var(--bg-1);color:var(--fg-2);outline:none;">
                            ปิด
                        </button>
                    </div>
                @endif
            </form>
                </div>
            </div>
            @endif

            @if($canEdit && $courseOffering->studentGroups->isNotEmpty())
                <form
                    id="bulk-group-delete-form"
                    method="POST"
                    action="{{ route('maker.course_offerings.student_groups.bulk_destroy', $courseOffering) }}"
                    data-preserve-scroll
                    @submit="window.tpssRememberCourseOfferingScroll && window.tpssRememberCourseOfferingScroll()"
                >
                    @csrf
                    @method('DELETE')
                </form>
                <div class="student-group-bulkbar" x-show="$store.offeringPage.editing.studentGroups" x-cloak>
                    <div class="caption">
                        เลือกกลุ่มที่ต้องการลบได้หลายกลุ่ม
                        <span x-show="selectedGroups.length > 0" x-text="'· เลือกแล้ว ' + selectedGroups.length + ' กลุ่ม'"></span>
                    </div>
                    <div class="student-group-bulkbar-actions">
                        @if($courseOffering->studentGroups->count() >= 2)
                            <button type="button"
                                @click="balanceAll()"
                                data-testid="balance-all-groups"
                                style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;font-weight:600;padding:7px 14px;border-radius:6px;font-family:inherit;border:1px solid var(--brand-navy-300);background:var(--brand-navy-50);color:var(--brand-navy);outline:none;transition:all 0.15s;"
                                onmouseover="this.style.background='var(--brand-navy)';this.style.color='#fff'"
                                onmouseout="this.style.background='var(--brand-navy-50)';this.style.color='var(--brand-navy)'">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
                                </svg>
                                ปรับยอดเท่ากัน
                            </button>
                        @endif
                        <label class="student-group-select-all">
                            <input
                                type="checkbox"
                                :checked="allGroupsSelected"
                                @change="toggleAllGroups($event.target.checked)"
                                data-testid="bulk-group-select-all"
                            >
                            ทั้งหมด
                        </label>
                        <button type="button" class="btn btn-ghost" style="min-height:34px;padding:6px 12px;font-size:13px;" @click="selectedGroups = []" x-show="selectedGroups.length > 0">
                            ล้างที่เลือก
                        </button>
                        <button
                            type="button"
                            class="btn-bulk-delete"
                            data-testid="bulk-groups-delete"
                            :disabled="selectedGroups.length < 1"
                            @click="if (selectedGroups.length > 0) confirmBulkDeleteOpen = true"
                        >
                            ลบกลุ่มที่เลือก
                        </button>
                    </div>
                </div>
                <div x-show="balanceNotice" x-cloak x-transition style="margin-top:10px;padding:10px 14px;background:var(--status-info-bg);border:1px solid var(--status-info-border);color:var(--status-info-fg);border-radius:8px;font-size:13px;display:flex;align-items:center;gap:8px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
                    </svg>
                    <span x-text="balanceNotice"></span>
                </div>
            @endif

            @if($courseOffering->studentGroups->isEmpty())
                <div class="student-group-empty">ยังไม่มีกลุ่มนักศึกษา</div>
            @elseif($canEdit)
                @php
                    $palette = ['#2563eb', '#16a34a', '#ca8a04', '#dc2626', '#7c3aed', '#0891b2', '#db2777', '#4f46e5', '#65a30d', '#ea580c'];
                    $groupsJson = $courseOffering->studentGroups->map(fn($g) => [
                        'id' => $g->id,
                        'group_code' => $g->group_code,
                        'student_count' => (int) $g->student_count,
                        'color_code' => $g->color_code ?: '#2563eb',
                    ]);
                    $updateUrlBase = route('maker.course_offerings.student_groups.update', [$courseOffering, '__ID__']);
                    $destroyUrlBase = route('maker.course_offerings.student_groups.destroy', [$courseOffering, '__ID__']);
                @endphp

                <div x-data="studentGroupEditor({
                    groups: {{ Js::from($groupsJson) }},
                    palette: {{ Js::from($palette) }},
                    updateUrlBase: {{ Js::from($updateUrlBase) }},
                    destroyUrlBase: {{ Js::from($destroyUrlBase) }},
                    csrfToken: {{ Js::from(csrf_token()) }},
                    studentLimit: {{ (int) $studentLimit }},
                })"
                x-init="emitCount()"
                @student-groups-balance.window="balanceAcrossAll($event.detail.studentLimit)"
                :class="!$store.offeringPage.editing.studentGroups ? 'is-locked-section' : ''"
                :inert="!$store.offeringPage.editing.studentGroups"
                style="background:var(--bg-1);border:1px solid var(--border-1);border-radius:10px;overflow:visible;">
                    {{-- Column header --}}
                    <div class="student-group-editor-row" style="display:grid;grid-template-columns:32px 36px 1fr 110px 32px;align-items:center;gap:12px;padding:10px 16px;background:var(--bg-2);border-bottom:1px solid var(--border-1);font-size:0.7rem;font-weight:700;color:var(--fg-3);letter-spacing:0.04em;text-transform:uppercase;">
                        <div></div>
                        <div>สี</div>
                        <div>รหัสกลุ่ม</div>
                        <div>นักศึกษา</div>
                        <div></div>
                    </div>

                    {{-- Rows --}}
                    <template x-for="(row, idx) in rows" :key="row.id">
                        <div :data-testid="'student-group-row'"
                             :style="{ background: row.confirmDelete ? 'var(--status-conflict-bg)' : '' }"
                             class="student-group-editor-row" style="display:grid;grid-template-columns:32px 36px 1fr 110px 32px;align-items:center;gap:12px;padding:10px 16px;border-bottom:1px solid var(--border-1);position:relative;transition:background 0.15s;"
                             @mouseenter="row.hover = true"
                             @mouseleave="row.hover = false">

                            {{-- Bulk-delete checkbox --}}
                            <div>
                                <input type="checkbox"
                                    :name="'group_ids[]'"
                                    :value="row.id"
                                    form="bulk-group-delete-form"
                                    x-model="selectedGroups"
                                    data-testid="bulk-group-checkbox"
                                    :aria-label="'เลือกกลุ่ม ' + row.group_code">
                            </div>

                            {{-- Color swatch + palette popover --}}
                            <div style="position:relative;">
                                <button type="button"
                                    @click="row.palOpen = !row.palOpen"
                                    @click.outside="row.palOpen = false"
                                    :style="`background:${row.color_code};`"
                                    style="width:28px;height:28px;border-radius:6px;border:2px solid #fff;box-shadow:0 0 0 1px var(--border-1);cursor:pointer;outline:none;"
                                    :title="'เปลี่ยนสีกลุ่ม ' + row.group_code"></button>
                                <div x-show="row.palOpen" x-cloak x-transition.opacity
                                     style="position:absolute;top:36px;left:0;z-index:30;display:grid;grid-template-columns:repeat(5,1fr);gap:6px;padding:10px;background:#fff;border:1px solid var(--border-1);border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,0.12);min-width:170px;">
                                    <template x-for="color in palette" :key="color">
                                        <button type="button"
                                            @click="setColor(idx, color)"
                                            :style="`background:${color};${row.color_code === color ? 'box-shadow:0 0 0 2px var(--brand-navy);' : ''}`"
                                            style="width:24px;height:24px;border-radius:5px;border:1px solid rgba(0,0,0,0.1);cursor:pointer;outline:none;"></button>
                                    </template>
                                </div>
                            </div>

                            {{-- Group code --}}
                            <div>
                                <input type="text"
                                    x-model="row.group_code"
                                    @input.debounce.700ms="save(idx)"
                                    data-testid="student-group-code"
                                    style="width:100%;padding:7px 10px;border:1px solid var(--border-1);border-radius:6px;font-size:14px;font-weight:600;font-family:inherit;outline:none;"
                                    :style="row.error ? 'border-color:var(--status-conflict-border);background:var(--status-conflict-bg);' : ''">
                            </div>

                            {{-- Student count --}}
                            <div>
                                <input type="number"
                                    min="1" max="9999"
                                    x-model.number="row.student_count"
                                    @input.debounce.700ms="save(idx)"
                                    style="width:100%;padding:7px 10px;border:1px solid var(--border-1);border-radius:6px;font-size:14px;font-weight:600;font-family:inherit;outline:none;text-align:right;"
                                    :style="row.error ? 'border-color:var(--status-conflict-border);background:var(--status-conflict-bg);' : ''">
                            </div>

                            {{-- Status + delete --}}
                            <div style="display:flex;align-items:center;justify-content:flex-end;gap:4px;">
                                {{-- Saving spinner --}}
                                <span x-show="row.saving" style="width:14px;height:14px;border:2px solid var(--brand-navy-300);border-top-color:var(--brand-navy);border-radius:50%;animation:spin 0.8s linear infinite;"></span>
                                {{-- Saved checkmark --}}
                                <span x-show="row.savedFlash" x-cloak x-transition style="color:var(--status-success-fg);">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                </span>
                                {{-- Delete (hover or confirm-open) --}}
                                <button type="button"
                                    x-show="(row.hover || row.confirmDelete) && !row.saving && !row.savedFlash"
                                    x-cloak
                                    @click="row.confirmDelete = true"
                                    :title="'ลบกลุ่ม ' + row.group_code"
                                    style="width:26px;height:26px;display:flex;align-items:center;justify-content:center;border:1px solid var(--status-conflict-border);background:transparent;color:var(--status-conflict-fg);border-radius:5px;cursor:pointer;outline:none;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </div>

                            {{-- Inline error message --}}
                            <div x-show="row.error" x-cloak
                                 style="grid-column:1 / -1;font-size:12px;color:var(--status-conflict-fg);padding-top:4px;"
                                 x-text="row.error"></div>

                            {{-- Inline delete confirm bar --}}
                            <div x-show="row.confirmDelete" x-cloak x-transition
                                 style="grid-column:1 / -1;display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px;margin-top:6px;background:#fff;border:1px solid var(--status-conflict-border);border-radius:8px;">
                                <span style="font-size:13px;color:var(--status-conflict-fg);font-weight:600;" x-text="'ยืนยันลบกลุ่ม ' + row.group_code + '? (ลบแล้วย้อนกลับไม่ได้)'"></span>
                                <div style="display:flex;gap:6px;">
                                    <button type="button" @click="row.confirmDelete = false"
                                        style="padding:5px 12px;border:1px solid var(--border-1);background:var(--bg-1);color:var(--fg-2);border-radius:5px;font-size:12px;font-weight:600;cursor:pointer;outline:none;">ยกเลิก</button>
                                    <button type="button" @click="deleteRow(idx)"
                                        style="padding:5px 12px;border:1px solid var(--status-conflict-fg);background:var(--status-conflict-fg);color:#fff;border-radius:5px;font-size:12px;font-weight:600;cursor:pointer;outline:none;">
                                        ลบเลย
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                <style>@keyframes spin { to { transform: rotate(360deg); } }</style>
            @else
                <div style="background:var(--bg-1);border:1px solid var(--border-1);border-radius:10px;overflow:hidden;">
                    <div style="display:grid;grid-template-columns:36px 1fr 110px;gap:12px;padding:10px 16px;background:var(--bg-2);border-bottom:1px solid var(--border-1);font-size:0.7rem;font-weight:700;color:var(--fg-3);letter-spacing:0.04em;text-transform:uppercase;">
                        <div>สี</div>
                        <div>รหัสกลุ่ม</div>
                        <div style="text-align:right;">นักศึกษา</div>
                    </div>
                    @foreach($courseOffering->studentGroups as $group)
                        <div style="display:grid;grid-template-columns:36px 1fr 110px;gap:12px;padding:12px 16px;align-items:center;border-bottom:1px solid var(--border-1);">
                            <div><span style="display:inline-block;width:24px;height:24px;border-radius:6px;background:{{ $group->color_code ?: '#2563eb' }};box-shadow:0 0 0 1px var(--border-1);"></span></div>
                            <div style="font-weight:600;">{{ $group->group_code }}</div>
                            <div style="text-align:right;font-weight:600;">{{ $group->student_count }} คน</div>
                        </div>
                    @endforeach
                </div>
            @endif

            <script>
                function studentGroupEditor(config) {
                    return {
                        palette: config.palette,
                        updateUrlBase: config.updateUrlBase,
                        destroyUrlBase: config.destroyUrlBase,
                        csrfToken: config.csrfToken,
                        studentLimit: config.studentLimit || 0,
                        rows: config.groups.map(g => ({
                            ...g,
                            saving: false,
                            savedFlash: false,
                            error: '',
                            hover: false,
                            palOpen: false,
                            confirmDelete: false,
                            _abort: null,
                        })),
                        emitCount() {
                            const sum = this.rows.reduce((s, r) => s + (parseInt(r.student_count) || 0), 0);
                            const ungrouped = Math.max(0, this.studentLimit - sum);
                            window.dispatchEvent(new CustomEvent('student-groups-count-changed', { detail: { ungrouped } }));
                        },
                        setColor(idx, color) {
                            this.rows[idx].color_code = color;
                            this.rows[idx].palOpen = false;
                            this.save(idx);
                        },
                        async balanceAcrossAll(studentLimit) {
                            const n = this.rows.length;
                            if (n < 1) return;
                            const total = Math.max(1, parseInt(studentLimit) || 0);
                            const base = Math.floor(total / n);
                            const remainder = total % n;
                            // Sequential save เพื่อกัน server load + ensure ordering
                            for (let i = 0; i < this.rows.length; i++) {
                                const newVal = base + (i < remainder ? 1 : 0);
                                if (this.rows[i].student_count !== newVal) {
                                    this.rows[i].student_count = newVal;
                                    await this.save(i);
                                }
                            }
                            window.dispatchEvent(new CustomEvent('student-groups-balanced'));
                        },
                        async save(idx) {
                            const row = this.rows[idx];
                            if (!row.group_code || !row.student_count) return;
                            // Cancel ในคิวก่อนหน้าของ row นี้ — กัน race ตอน user พิมพ์เร็ว ๆ
                            if (row._abort) row._abort.abort();
                            const controller = new AbortController();
                            row._abort = controller;
                            row.error = '';
                            row.saving = true;
                            row.savedFlash = false;
                            try {
                                const formData = new FormData();
                                formData.append('_method', 'PUT');
                                formData.append('_token', this.csrfToken);
                                formData.append('group_code', row.group_code);
                                formData.append('student_count', row.student_count);
                                formData.append('color_code', row.color_code);
                                const url = this.updateUrlBase.replace('__ID__', row.id);
                                const res = await fetch(url, {
                                    method: 'POST',
                                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                    body: formData,
                                    signal: controller.signal,
                                });
                                const data = await res.json().catch(() => ({}));
                                if (!res.ok) {
                                    row.error = data.message || 'บันทึกไม่สำเร็จ';
                                } else {
                                    row.savedFlash = true;
                                    setTimeout(() => { row.savedFlash = false; }, 1500);
                                    this.emitCount();
                                }
                            } catch (e) {
                                if (e.name === 'AbortError') return;
                                row.error = 'เชื่อมต่อไม่ได้';
                            } finally {
                                if (row._abort === controller) {
                                    row._abort = null;
                                    row.saving = false;
                                }
                            }
                        },
                        async deleteRow(idx) {
                            const row = this.rows[idx];
                            if (row._abort) row._abort.abort();
                            row.saving = true;
                            row.error = '';
                            try {
                                const formData = new FormData();
                                formData.append('_method', 'DELETE');
                                formData.append('_token', this.csrfToken);
                                const url = this.destroyUrlBase.replace('__ID__', row.id);
                                const res = await fetch(url, {
                                    method: 'POST',
                                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                    body: formData,
                                });
                                const data = await res.json().catch(() => ({}));
                                if (!res.ok) {
                                    row.error = data.message || 'ลบไม่สำเร็จ';
                                    row.confirmDelete = false;
                                    row.saving = false;
                                    return;
                                }
                                // ลบจาก rows + แจ้ง parent ให้ sync selectedGroups + groupIds
                                const deletedId = row.id;
                                this.rows.splice(idx, 1);
                                this.emitCount();
                                window.dispatchEvent(new CustomEvent('student-group-deleted', { detail: { id: deletedId } }));
                            } catch (e) {
                                row.error = 'เชื่อมต่อไม่ได้';
                                row.saving = false;
                            }
                        },
                    };
                }
            </script>

            @if($canEdit)
                <div
                    class="student-group-confirm-overlay"
                    x-show="confirmBulkDeleteOpen"
                    x-cloak
                    @keydown.escape.window="confirmBulkDeleteOpen = false"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="bulk-delete-title"
                >
                    <div class="student-group-confirm-dialog" @click.outside="confirmBulkDeleteOpen = false">
                        <div id="bulk-delete-title" style="font-size:18px;font-weight:800;color:var(--fg-1);">ยืนยันการลบกลุ่มนักศึกษา</div>
                        <div class="body-sm" style="margin-top:8px;color:var(--fg-2);">
                            คุณกำลังจะลบกลุ่มนักศึกษาที่เลือก <strong x-text="selectedGroups.length"></strong> กลุ่ม การลบนี้ไม่สามารถย้อนกลับได้
                        </div>
                        <div class="student-group-confirm-actions">
                            <button type="button" class="btn btn-ghost" @click="confirmBulkDeleteOpen = false">ยกเลิก</button>
                            <button
                                type="submit"
                                form="bulk-group-delete-form"
                                class="btn-bulk-delete"
                                @click="confirmBulkDeleteOpen = false"
                            >
                                ลบกลุ่มที่เลือก
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

</x-app-layout>
