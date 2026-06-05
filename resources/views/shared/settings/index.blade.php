<x-app-layout title="{{ $isAdmin ? 'ตั้งค่าระบบ' : 'ตั้งค่าปีการศึกษา' }}">
    @php
        $canManageHolidays = $canManageHolidays ?? in_array($routePrefix ?? null, ['admin', 'staff'], true);
    @endphp
    <div class="settings-page" x-data="{
        activeTab: new URLSearchParams(window.location.search).get('tab') || 'academic',
        workloadWeeks: {{ $workloadWeeks }},
        teachingWeeks: {{ $teachingWeeks }},
        workloadHoursPerWeek: {{ $workloadHoursPerWeek }},
        get totalQuota() { return this.workloadWeeks * this.workloadHoursPerWeek },
        get teachingQuota() { return this.teachingWeeks * this.workloadHoursPerWeek },
        showModal: {{ $errors->hasAny(['name', 'terms', 'is_active']) ? 'true' : 'false' }},
        editMode: {{ ($errors->hasAny(['name', 'terms', 'is_active'])) && old('_method') === 'PUT' ? 'true' : 'false' }},
        currentYear: {
            id: '{{ old('year_id', '') }}',
            name: '{{ old('name', '') }}',
            start_date: {{ Js::from(\App\Support\ThaiDate::formatForInput(old('start_date', ''))) }},
            end_date: {{ Js::from(\App\Support\ThaiDate::formatForInput(old('end_date', ''))) }},
            is_active: {{ old('is_active') ? 'true' : 'false' }},
            hasSummer: false,
            terms: [],
        },
        openScheduleConfirmForm: null,
        openScheduleConfirmLabel: '',
        openScheduleCountdown: 0,
        openScheduleTimer: null,
        closeScheduleConfirmForm: null,
        closeScheduleConfirmLabel: '',
        closeScheduleCountdown: 0,
        closeScheduleTimer: null,
        emptyTerm(name) {
            return { name: name || '', start_date: '', end_date: '', midterm_start: '', midterm_end: '', final_start: '', final_end: '' };
        },
        buildTerms(yearTerms) {
            const list = Array.isArray(yearTerms) ? yearTerms : [];
            const slot = (i, fallbackName) => {
                const t = list[i];
                if (!t) return this.emptyTerm(fallbackName);
                return {
                    name: t.name || fallbackName,
                    start_date: this.thaiDateForInput(t.start_date),
                    end_date: this.thaiDateForInput(t.end_date),
                    midterm_start: this.thaiDateForInput(t.midterm_start),
                    midterm_end: this.thaiDateForInput(t.midterm_end),
                    final_start: this.thaiDateForInput(t.final_start),
                    final_end: this.thaiDateForInput(t.final_end),
                };
            };
            return [slot(0, 'ภาคเรียนที่ 1'), slot(1, 'ภาคเรียนที่ 2'), slot(2, 'ภาคฤดูร้อน')];
        },
        resetSummerTerm() {
            this.currentYear.terms[2] = this.emptyTerm('ภาคฤดูร้อน');
        },
        /* ── ปฏิทินการศึกษาตามกลุ่ม (V4 ข้อ 8) ── */
        calCurriculums: {{ Js::from($calendarCurriculums ?? []) }},
        showCalendarsModal: false,
        calYearId: '', calYearName: '', calList: [],
        showCalEditor: false,
        editCalMode: false,
        currentCal: { id: '', name: '', curriculum_id: '', year_level_min: '', year_level_max: '', hasSummer: false, terms: [] },
        openCalendars(year) {
            this.calYearId = year.id;
            this.calYearName = year.name;
            this.calList = Array.isArray(year.calendars) ? year.calendars : [];
            this.showCalendarsModal = true;
        },
        calBuildTerms(list) {
            const a = Array.isArray(list) ? list : [];
            const s = (i, fn) => {
                const t = a[i];
                if (!t) return this.emptyTerm(fn);
                return { name: t.name || fn, start_date: this.thaiDateForInput(t.start_date), end_date: this.thaiDateForInput(t.end_date), midterm_start: this.thaiDateForInput(t.midterm_start), midterm_end: this.thaiDateForInput(t.midterm_end), final_start: this.thaiDateForInput(t.final_start), final_end: this.thaiDateForInput(t.final_end) };
            };
            return [s(0, 'ภาคเรียนที่ 1'), s(1, 'ภาคเรียนที่ 2'), s(2, 'ภาคฤดูร้อน')];
        },
        openAddCalendar() {
            this.editCalMode = false;
            this.currentCal = { id: '', name: '', curriculum_id: '', year_level_min: '', year_level_max: '', hasSummer: false, terms: this.calBuildTerms([]) };
            this.showCalEditor = true;
        },
        openEditCalendar(cal) {
            this.editCalMode = true;
            const terms = cal.terms || [];
            this.currentCal = { id: cal.id, name: cal.name || '', curriculum_id: cal.curriculum_id ? String(cal.curriculum_id) : '', year_level_min: cal.year_level_min ?? '', year_level_max: cal.year_level_max ?? '', hasSummer: terms.length >= 3, terms: this.calBuildTerms(terms) };
            this.showCalEditor = true;
        },
        calCurriculumUsesYear() {
            const c = this.calCurriculums.find(x => String(x.id) === String(this.currentCal.curriculum_id));
            return c ? !!c.uses_year_level : false;
        },
        calYearLevels() {
            const c = this.calCurriculums.find(x => String(x.id) === String(this.currentCal.curriculum_id));
            const dur = c ? (c.duration_years || 4) : 4;
            return Array.from({ length: dur }, (_, i) => i + 1);
        },
        calScopeLabel(cal) {
            const parts = [];
            parts.push(cal.curriculum && cal.curriculum.name ? cal.curriculum.name : 'ทุกหลักสูตร');
            if (cal.year_level_min || cal.year_level_max) {
                const lo = cal.year_level_min || '?';
                const hi = (cal.year_level_max && cal.year_level_max != cal.year_level_min) ? ('-' + cal.year_level_max) : '';
                parts.push('ปี ' + lo + hi);
            }
            return parts.join(' · ');
        },
        showHolidayModal: false,
        editHolidayMode: false,
        currentHoliday: { id: '', date: '', name: '', remark: '' },
        openAddHoliday() {
            this.editHolidayMode = false;
            this.currentHoliday = { id: '', date: '', name: '', remark: '' };
            this.showHolidayModal = true;
        },
        openEditHoliday(h) {
            this.editHolidayMode = true;
            this.currentHoliday = {
                id: h.id,
                date: this.thaiDateForInput(h.date),
                name: h.name || '',
                remark: h.remark || '',
            };
            this.showHolidayModal = true;
        },
        openAddModal() {
            this.editMode = false;
            this.currentYear = { id: '', name: '', start_date: '', end_date: '', is_active: false, hasSummer: false, terms: this.buildTerms([]), calendars: [] };
            this.showModal = true;
        },
        openEditModal(year) {
            this.editMode = true;
            const terms = year.terms || [];
            this.currentYear = {
                id: year.id,
                name: year.name,
                start_date: this.thaiDateForInput(year.start_date),
                end_date: this.thaiDateForInput(year.end_date),
                is_active: !!year.is_active,
                hasSummer: terms.length >= 3,
                terms: this.buildTerms(terms),
                calendars: year.calendars || [],
            };
            this.showModal = true;
        },
        thaiDateForInput(value) {
            const raw = String(value || '').trim();
            if (!raw) return '';

            const iso = raw.match(/^(\d{4})-(\d{2})-(\d{2})/);
            if (iso) {
                return iso[3] + '/' + iso[2] + '/' + (parseInt(iso[1], 10) + 543);
            }

            const display = raw.match(/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/);
            if (display) {
                const year = parseInt(display[3], 10);
                return display[1].padStart(2, '0') + '/' + display[2].padStart(2, '0') + '/' + (year >= 2400 ? year : year + 543);
            }

            return raw;
        },
        startOpenScheduleCountdown(formId, label) {
            clearInterval(this.openScheduleTimer);
            this.openScheduleConfirmForm = formId;
            this.openScheduleConfirmLabel = label;
            this.openScheduleCountdown = 3;
            this.openScheduleTimer = setInterval(() => {
                this.openScheduleCountdown = Math.max(0, this.openScheduleCountdown - 1);
                if (this.openScheduleCountdown === 0) {
                    clearInterval(this.openScheduleTimer);
                }
            }, 1000);
        },
        cancelOpenScheduleCountdown() {
            clearInterval(this.openScheduleTimer);
            this.openScheduleConfirmForm = null;
            this.openScheduleConfirmLabel = '';
            this.openScheduleCountdown = 0;
        },
        confirmOpenSchedule() {
            if (this.openScheduleCountdown > 0 || !this.openScheduleConfirmForm) return;
            document.getElementById(this.openScheduleConfirmForm)?.submit();
        },
        startCloseScheduleConfirm(formId, label) {
            clearInterval(this.closeScheduleTimer);
            this.closeScheduleConfirmForm = formId;
            this.closeScheduleConfirmLabel = label;
            this.closeScheduleCountdown = 3;
            this.closeScheduleTimer = setInterval(() => {
                this.closeScheduleCountdown = Math.max(0, this.closeScheduleCountdown - 1);
                if (this.closeScheduleCountdown === 0) {
                    clearInterval(this.closeScheduleTimer);
                }
            }, 1000);
        },
        cancelCloseScheduleConfirm() {
            clearInterval(this.closeScheduleTimer);
            this.closeScheduleConfirmForm = null;
            this.closeScheduleConfirmLabel = '';
            this.closeScheduleCountdown = 0;
        },
        confirmCloseSchedule() {
            if (this.closeScheduleCountdown > 0 || !this.closeScheduleConfirmForm) return;
            document.getElementById(this.closeScheduleConfirmForm)?.submit();
        }
    }">

        @if($isAdmin)
        <div class="tabs-container" style="display: flex; justify-content: flex-end; margin-bottom: 24px; width: 100%; overflow: hidden;">
            <div class="tabs"
                style="display: flex; gap: 8px; background: var(--bg-2); padding: 4px; border-radius: 8px; border: 1px solid var(--border); overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch; max-width: 100%;">
                <button type="button" @click="activeTab = 'academic'"
                    :class="activeTab === 'academic' ? 'btn-primary' : 'btn btn-ghost'"
                    style="padding: 8px 16px; border-radius: 6px; flex-shrink: 0; display: flex; align-items: center;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
                        style="margin-right: 6px;">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    ปีการศึกษา
                </button>
                <button type="button" @click="activeTab = 'pa'"
                    :class="activeTab === 'pa' ? 'btn-primary' : 'btn btn-ghost'"
                    style="padding: 8px 16px; border-radius: 6px; flex-shrink: 0; display: flex; align-items: center;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
                        style="margin-right: 6px;">
                        <line x1="19" y1="5" x2="5" y2="19"></line>
                        <circle cx="6.5" cy="6.5" r="2.5"></circle>
                        <circle cx="17.5" cy="17.5" r="2.5"></circle>
                    </svg>
                    เกณฑ์ภาระงาน
                </button>
            </div>
        </div>
        @endif

        <!-- Tab: Academic Year (รวมการจัดการช่วงจัดตารางในตารางเดียวกัน) -->
        @php
            $schedulingCriticals = $schedulingCriticals ?? [];
            $hasSchedulingCriticals = $isAdmin && count($schedulingCriticals) > 0;
        @endphp
        <div x-show="activeTab === 'academic'" {{ $isAdmin ? 'x-cloak' : '' }}>
            @if(session('success'))
                <div class="settings-flash settings-flash--success">
                    <span class="settings-flash-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    </span>
                    <span>{{ session('success') }}</span>
                </div>
            @endif
            @if($isAdmin && session('error'))
                <div class="settings-flash settings-flash--error">
                    <span class="settings-flash-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
                    </span>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @if($hasSchedulingCriticals)
                <div style="background:var(--status-conflict-bg);border:1px solid var(--status-conflict-border);border-radius:8px;margin-bottom:16px;padding:14px 16px;color:var(--status-conflict-fg);">
                    <div style="font-weight:700;margin-bottom:6px;">ยังไม่สามารถเปิดช่วงจัดตารางได้</div>
                    <div style="font-size:13px;line-height:1.55;margin-bottom:10px;">ต้องแก้ Critical ให้หมดก่อนเปิดช่วงจัดตาราง เพื่อให้รายวิชาทุกวิชาพร้อมถูกสร้างเป็น Course Offering</div>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;">
                        @foreach($schedulingCriticals as $critical)
                            <a href="{{ $critical['link'] }}" style="text-decoration:none;color:var(--status-conflict-fg);background:color-mix(in oklch,var(--status-conflict) 8%,white);border:1px solid color-mix(in oklch,var(--status-conflict) 22%,white);border-radius:999px;padding:5px 10px;font-size:12px;font-weight:700;">
                                {{ $critical['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-hdr">
                    <div>
                        <div class="card-ttl">ตั้งค่าปีการศึกษา</div>
                    </div>
                    <div class="card-actions">
                        <button class="btn btn-primary" @click="openAddModal()">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round">
                                <line x1="12" y1="5" x2="12" y2="19" />
                                <line x1="5" y1="12" x2="19" y2="12" />
                            </svg>
                            เพิ่มปีการศึกษา
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ปีการศึกษา</th>
                                <th>เทอม</th>
                                <th>วันที่เริ่ม - สิ้นสุด</th>
                                <th>สถานะปัจจุบัน</th>
                                <th>ช่วงจัดตาราง</th>
                                <th style="text-align: center;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($academicYears as $year)
                                <tr>
                                    <td style="font-weight: 600; color: var(--fg-1);">{{ $year->name }}</td>
                                    <td style="font-size: 12px; color: var(--fg-2);">
                                        @php
                                            $fallbackCal = $year->calendars->first(fn ($c) => is_null($c->curriculum_id) && is_null($c->year_level_min) && is_null($c->year_level_max));
                                            $needsTerms = ! $fallbackCal || $fallbackCal->terms->isEmpty();
                                        @endphp
                                        @foreach($year->terms as $t)
                                            <span class="badge badge-gray" style="margin:1px 2px;display:inline-block;">{{ $t->name }}</span>
                                        @endforeach
                                        @if($needsTerms)
                                            <span class="badge" title="ยังไม่ได้กำหนดเทอม/ช่วงสอบในปฏิทินค่าเริ่มต้น (ทุกหลักสูตร)" style="display:inline-block;margin:1px 2px;background:oklch(95% 0.05 75);color:oklch(45% 0.13 65);border:1px solid oklch(80% 0.12 75);">⚠ ยังไม่ได้กำหนดเทอม</span>
                                        @endif
                                    </td>
                                    <td style="color: var(--fg-2); font-size: 13px;">
                                        {{ \App\Support\ThaiDate::formatForInput($year->start_date) }} -
                                        {{ \App\Support\ThaiDate::formatForInput($year->end_date) }}
                                    </td>
                                    <td>
                                        @if($year->is_active)
                                            <span class="badge badge-primary">
                                                <span style="width: 6px; height: 6px; border-radius: 50%; background: currentColor; margin-right: 6px;"></span>
                                                ปีปัจจุบัน
                                            </span>
                                        @else
                                            <span class="badge badge-gray">ทั่วไป</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($year->phase === 'scheduling')
                                            <span class="badge" style="background: oklch(90% 0.1 145); color: oklch(30% 0.15 145); border: 1px solid oklch(70% 0.15 145);">
                                                <span style="width: 6px; height: 6px; border-radius: 50%; background: oklch(50% 0.2 145); margin-right: 6px; display: inline-block;"></span>
                                                เปิดช่วงจัดตาราง
                                            </span>
                                        @elseif($year->phase === 'published')
                                            <span class="badge badge-primary">เผยแพร่แล้ว</span>
                                        @else
                                            <span class="badge badge-gray">เตรียมข้อมูล</span>
                                        @endif
                                    </td>
                                    <td class="settings-action-cell">
                                        <div class="academic-year-actions {{ $isAdmin ? '' : 'is-icon-only' }}">
                                            <div class="academic-year-icons">
                                            <button class="action-btn" title="แก้ไข"
                                                @click="openEditModal({{ json_encode($year) }})">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                                </svg>
                                            </button>
                                            <button class="action-btn" title="ปฏิทินการศึกษาตามกลุ่ม (หลักสูตร/ชั้นปี)"
                                                @click="openCalendars({{ json_encode($year) }})">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <rect x="3" y="4" width="18" height="18" rx="2" />
                                                    <line x1="16" y1="2" x2="16" y2="6" />
                                                    <line x1="8" y1="2" x2="8" y2="6" />
                                                    <line x1="3" y1="10" x2="21" y2="10" />
                                                </svg>
                                                <span x-text="'{{ $year->calendars->count() }}'" style="font-size:11px;font-weight:700;margin-left:3px;"></span>
                                            </button>
                                            </div>
                                            @if($isAdmin)
                                                <div class="academic-year-schedule-action">
                                                    @if(!$year->is_active)
                                                        <span style="font-size:12px;color:var(--fg-3);white-space:nowrap;">ตั้งเป็นปีปัจจุบันก่อน</span>
                                                    @elseif($year->phase === 'preparation')
                                                        <form id="open-scheduling-{{ $year->id }}" method="POST" action="{{ route('admin.settings.scheduling.open', $year) }}" style="margin:0;">
                                                            @csrf
                                                            @method('PATCH')
                                                            <button type="button"
                                                                class="{{ $hasSchedulingCriticals ? 'btn btn-ghost' : 'btn btn-primary' }}"
                                                                style="font-size: 13px; padding: 6px 14px; {{ $hasSchedulingCriticals ? 'opacity:0.55;cursor:not-allowed;' : '' }}"
                                                                @if($hasSchedulingCriticals)
                                                                    disabled
                                                                    title="ต้องแก้ Critical ให้หมดก่อนเปิดช่วงจัดตาราง"
                                                                @else
                                                                    @click="startOpenScheduleCountdown('open-scheduling-{{ $year->id }}', 'ปีการศึกษา {{ $year->name }}')"
                                                                @endif>
                                                                เปิดช่วงจัดตาราง
                                                            </button>
                                                        </form>
                                                    @elseif($year->phase === 'scheduling')
                                                        <form id="close-scheduling-{{ $year->id }}" method="POST" action="{{ route('admin.settings.scheduling.close', $year) }}" style="margin:0;">
                                                            @csrf
                                                            @method('PATCH')
                                                            <button type="button"
                                                                class="btn btn-ghost"
                                                                style="font-size: 13px; padding: 6px 14px; border: 1px solid var(--border);"
                                                                @click="startCloseScheduleConfirm('close-scheduling-{{ $year->id }}', 'ปีการศึกษา {{ $year->name }}')">
                                                                ปิดช่วงจัดตาราง
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 40px; color: var(--fg-3);">
                                        ยังไม่มีข้อมูลปีการศึกษา</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($canManageHolidays)
            {{-- วันหยุดราชการ (V3 ข้อ 2.4) --}}
            <div class="card" style="margin-top: 16px;">
                <div class="card-hdr">
                    <div>
                        <div class="card-ttl">วันหยุดราชการ (ระบบจะสร้างวันหยุดให้อัติโนมัติเมื่อเลือกปีการศึกษาปัจจุบัน)</div>
                    </div>
                    <div class="card-actions" style="display: flex; gap: 8px;">
                        <form method="POST" action="{{ route($routePrefix . '.settings.holidays.sync') }}" style="margin: 0;">
                            @csrf
                            <button type="submit" class="btn btn-ghost" style="font-size: 13px;">ดึงวันหยุดซ้ำ</button>
                        </form>
                        <button type="button" class="btn btn-primary" @click="openAddHoliday()">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="margin-right:6px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            เพิ่มวันหยุด
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 160px;">วันที่</th>
                                <th>ชื่อวันหยุด</th>
                                <th style="width: 110px;">ที่มา</th>
                                <th style="text-align: center; width: 80px;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($holidays as $h)
                                <tr @if($h->source === 'manual') style="background: var(--accent-bg, #eef4ff);" @endif>
                                    <td style="font-family: var(--font-mono);">{{ \App\Support\ThaiDate::formatForInput($h->date) }}</td>
                                    <td style="color: var(--fg-1);">{{ $h->name }}@if($h->remark)<span style="color: var(--fg-3); font-size: 12px;"> · {{ $h->remark }}</span>@endif</td>
                                    <td>
                                        @if($h->source === 'manual')
                                            <span class="badge" style="background: var(--accent-fg, #2563EB); color: #fff;">เพิ่มเอง</span>
                                        @else
                                            <span class="badge badge-gray">อัตโนมัติ</span>
                                        @endif
                                    </td>
                                    <td style="text-align: center;">
                                        <button type="button" class="action-btn" title="แก้ไข"
                                            @click="openEditHoliday({{ Js::from(['id' => $h->id, 'date' => optional($h->date)->format('Y-m-d'), 'name' => $h->name, 'remark' => $h->remark]) }})">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" style="text-align: center; color: var(--fg-3); padding: 28px;">ยังไม่มีวันหยุด — ระบบดึงให้อัตโนมัติเมื่อสร้างปีการศึกษา หรือกด "ดึงวันหยุดซ้ำ"</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @endif
            @if($isAdmin)
                <div x-show="openScheduleConfirmForm" x-cloak
                    style="position:fixed;inset:0;z-index:80;background:rgba(15,23,42,.36);"
                    @keydown.escape.window="cancelOpenScheduleCountdown()">
                    <div style="min-height:100vh;width:100%;display:flex;align-items:center;justify-content:center;padding:20px;">
                        <div style="width:min(520px,100%);background:oklch(99% 0.006 235);border:1px solid var(--border);border-radius:10px;box-shadow:0 24px 70px rgba(15,23,42,.24);overflow:hidden;">
                            <div style="padding:18px 20px;border-bottom:1px solid var(--border);background:oklch(97% 0.012 235);">
                                <div style="font-weight:800;color:var(--fg-1);font-size:16px;">ยืนยันเปิดช่วงจัดตาราง</div>
                                <div style="font-size:13px;color:var(--fg-3);margin-top:4px;" x-text="openScheduleConfirmLabel"></div>
                            </div>
                            <div style="padding:18px 20px;background:oklch(99% 0.006 235);">
                                <div style="font-size:14px;color:var(--fg-2);line-height:1.65;">
                                    ระบบจะสร้างและซิงก์ Course Offering จากรายวิชา active ทั้งหมด จากนั้นหัวหน้าวิชาจะเริ่มแก้ข้อมูลเพื่อจัดตารางได้
                                </div>
                                <div style="margin-top:14px;padding:12px 14px;border:1px solid oklch(84% 0.08 80);border-radius:8px;background:oklch(98% 0.025 85);color:oklch(38% 0.08 75);font-size:13px;font-weight:700;"
                                    x-text="openScheduleCountdown > 0 ? 'รอ ' + openScheduleCountdown + ' วินาที ก่อนยืนยัน' : 'พร้อมยืนยันเปิดช่วงจัดตาราง'"></div>
                            </div>
                            <div style="display:flex;justify-content:flex-end;gap:8px;padding:14px 20px;border-top:1px solid var(--border);background:oklch(98% 0.008 235);">
                                <button type="button" class="btn btn-ghost" @click="cancelOpenScheduleCountdown()">ยกเลิก</button>
                                <button type="button" class="btn btn-primary" :disabled="openScheduleCountdown > 0" :style="openScheduleCountdown > 0 ? 'opacity:.55;cursor:not-allowed;' : ''" @click="confirmOpenSchedule()">
                                    ยืนยันเปิดช่วงจัดตาราง
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div x-show="closeScheduleConfirmForm" x-cloak
                    style="position:fixed;inset:0;z-index:80;background:rgba(15,23,42,.36);"
                    @keydown.escape.window="cancelCloseScheduleConfirm()">
                    <div style="min-height:100vh;width:100%;display:flex;align-items:center;justify-content:center;padding:20px;">
                        <div style="width:min(520px,100%);background:oklch(99% 0.006 235);border:1px solid var(--border);border-radius:10px;box-shadow:0 24px 70px rgba(15,23,42,.24);overflow:hidden;">
                            <div style="padding:18px 20px;border-bottom:1px solid var(--border);background:oklch(98% 0.025 85);">
                                <div style="font-weight:800;color:var(--fg-1);font-size:16px;">ยืนยันปิดช่วงจัดตาราง</div>
                                <div style="font-size:13px;color:var(--fg-3);margin-top:4px;" x-text="closeScheduleConfirmLabel"></div>
                            </div>
                            <div style="padding:18px 20px;background:oklch(99% 0.006 235);">
                                <div style="font-size:14px;color:var(--fg-2);line-height:1.65;">
                                    ระบบจะเปลี่ยนสถานะกลับเป็น <strong style="color:var(--fg-1);">เตรียมข้อมูล</strong> และหัวหน้าวิชาจะไม่สามารถจัดหรือแก้ไขตารางในรอบนี้ต่อได้ จนกว่า Admin จะเปิดช่วงจัดตารางอีกครั้ง
                                </div>
                                <div style="margin-top:14px;padding:12px 14px;border:1px solid oklch(82% 0.055 235);border-radius:8px;background:oklch(97% 0.018 235);color:oklch(32% 0.075 245);font-size:13px;font-weight:700;line-height:1.55;">
                                    ข้อมูลตารางที่จัดไว้แล้วจะยังอยู่ (ระบบจะปิดเฉพาะสิทธิ์การจัด/แก้ไขตารางชั่วคราว)
                                </div>
                                <div style="margin-top:10px;padding:12px 14px;border:1px solid oklch(84% 0.08 80);border-radius:8px;background:oklch(98% 0.025 85);color:oklch(38% 0.08 75);font-size:13px;font-weight:700;"
                                    x-text="closeScheduleCountdown > 0 ? 'รอ ' + closeScheduleCountdown + ' วินาที ก่อนยืนยัน' : 'พร้อมยืนยันปิดช่วงจัดตาราง'"></div>
                            </div>
                            <div style="display:flex;justify-content:flex-end;gap:8px;padding:14px 20px;border-top:1px solid var(--border);background:oklch(98% 0.008 235);">
                                <button type="button" class="btn btn-ghost" @click="cancelCloseScheduleConfirm()">ยกเลิก</button>
                                <button type="button" class="btn btn-primary" :disabled="closeScheduleCountdown > 0" :style="closeScheduleCountdown > 0 ? 'opacity:.55;cursor:not-allowed;' : ''" @click="confirmCloseSchedule()">
                                    ยืนยันปิดช่วงจัดตาราง
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Add/Edit Modal (Academic Year) -->
        <template x-if="showModal && activeTab === 'academic'">
            <div class="overlay" x-cloak @keydown.escape.window="showModal = false">
                <div class="modal-center" x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                    <div class="modal-hdr" style="background: var(--bg-2);">
                        <div class="modal-ttl" style="font-family: var(--font-display);"
                            x-text="editMode ? 'แก้ไขปีการศึกษา' : 'เพิ่มปีการศึกษาใหม่'"></div>
                        <button type="button" class="modal-cls" @click="showModal = false">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18" />
                                <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                        </button>
                    </div>
                    <form
                        :action="editMode ? '{{ url($routePrefix . '/settings/academic-years') }}/' + currentYear.id : '{{ route($routePrefix . '.settings.years.store') }}'"
                        method="POST">
                        @csrf
                        <template x-if="editMode">
                            <input type="hidden" name="_method" value="PUT">
                        </template>
                        <template x-if="editMode">
                            <input type="hidden" name="year_id" x-model="currentYear.id">
                        </template>
                        <div class="modal-body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>ปีการศึกษา (พ.ศ.)</label>
                                    <input type="text" name="name" x-model="currentYear.name" required
                                        placeholder="เช่น 2569"
                                        style="{{ $errors->has('name') ? 'border-color: var(--red, #dc2626);' : '' }}">
                                    @error('name')
                                        <span style="color: var(--red, #dc2626); font-size: 12px; margin-top: 4px; display: block;">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div style="margin-top: 4px; border-top: 1px solid var(--border); padding-top: 14px;">
                                <div style="font-weight: 600; font-size: 13px; color: var(--fg-1); margin-bottom: 6px;">เทอม + ช่วงสอบ</div>
                                <div style="font-size:12px;color:var(--fg-3);line-height:1.6;padding:11px 13px;background:var(--surface-sunken);border-radius:8px;">
                                    กำหนดเทอม + ช่วงสอบใน <strong>ปฏิทินการศึกษา</strong> — 1 ปีมีได้หลายปฏิทินตามหลักสูตร/ชั้นปี · วันเริ่ม-สิ้นสุดปีคำนวณจากเทอมให้อัตโนมัติ
                                    <template x-if="editMode">
                                        <div style="margin-top:10px;">
                                            <button type="button" class="btn btn-primary" style="font-size:12px;padding:6px 14px;"
                                                @click="showModal = false; $nextTick(() => openCalendars(currentYear))">จัดการปฏิทินและเทอม →</button>
                                        </div>
                                    </template>
                                    <template x-if="!editMode">
                                        <div style="margin-top:8px;color:var(--fg-4);">บันทึกปีการศึกษาก่อน แล้วกดไอคอนปฏิทินที่แถวปีเพื่อกำหนดเทอม</div>
                                    </template>
                                </div>
                            </div>
                            <div class="form-group" style="margin-top: 16px;">
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; color: var(--fg-1);">
                                    <input type="checkbox" name="is_active" value="1" x-model="currentYear.is_active"
                                        style="width: 16px; height: 16px; accent-color: var(--brand-navy);">
                                    ตั้งเป็นปีการศึกษาปัจจุบัน (Active)
                                </label>
                                @error('is_active')
                                    <span style="color: var(--red, #dc2626); font-size: 12px; margin-top: 6px; display: block;">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="modal-foot">
                            <button type="button" class="btn btn-ghost" @click="showModal = false">ยกเลิก</button>
                            <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        {{-- ── ปฏิทินการศึกษาตามกลุ่ม (V4 ข้อ 8) — รายการปฏิทินต่อปี ── --}}
        <template x-if="showCalendarsModal">
            <div class="overlay" x-cloak @keydown.escape.window="showCalendarsModal = false">
                <div class="modal-center" style="max-width: 640px;">
                    <div class="modal-hdr" style="background: var(--bg-2);">
                        <div class="modal-ttl" style="font-family: var(--font-display);"
                            x-text="'ปฏิทินการศึกษา — ปี ' + calYearName"></div>
                        <button type="button" class="modal-cls" @click="showCalendarsModal = false">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div style="font-size:12px;color:var(--fg-3);line-height:1.6;margin-bottom:14px;">
                            1 ปีมีได้หลายปฏิทิน — เปิด/ปิดเทอม + ช่วงสอบต่างกันตามหลักสูตร/ชั้นปี ·
                            ปฏิทิน <strong>"ทุกหลักสูตร"</strong> (ค่าเริ่มต้น) ใช้กับกลุ่มที่ไม่มีปฏิทินเฉพาะ · เพิ่ม<strong>ปฏิทินตามกลุ่ม</strong>เพื่อกำหนดช่วงต่างออกไป
                        </div>
                        <template x-if="calList.length === 0">
                            <div style="font-size:12px;color:var(--fg-3);padding:14px;text-align:center;background:var(--surface-sunken);border-radius:8px;">
                                ยังไม่มีปฏิทิน — กด "เพิ่มปฏิทิน" เพื่อกำหนดเทอมและช่วงสอบ
                            </div>
                        </template>
                        <template x-for="cal in calList" :key="cal.id">
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 14px;border:1px solid var(--border);border-radius:8px;margin-bottom:8px;background:var(--surface);">
                                <div style="min-width:0;">
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <span style="font-weight:600;font-size:13px;color:var(--fg-1);" x-text="cal.name"></span>
                                        <span x-show="!cal.curriculum_id && !cal.year_level_min" style="font-size:10px;font-weight:700;color:var(--brand-navy);background:var(--brand-navy-50);padding:2px 8px;border-radius:999px;">ค่าเริ่มต้น</span>
                                    </div>
                                    <div style="font-size:11px;color:var(--fg-3);margin-top:3px;">
                                        <span x-text="calScopeLabel(cal)"></span> ·
                                        <span x-text="(cal.terms ? cal.terms.length : 0) + ' เทอม'"></span>
                                    </div>
                                </div>
                                <div style="display:flex;gap:4px;flex-shrink:0;">
                                    <button type="button" class="action-btn" title="แก้ไข" @click="openEditCalendar(cal)">
                                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                    <form method="POST" :action="'{{ url($routePrefix . '/settings/calendars') }}/' + cal.id" style="margin:0;"
                                        @submit="return confirm('ลบปฏิทิน ' + cal.name + ' และเทอมทั้งหมดในปฏิทินนี้?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="action-btn" title="ลบ" style="color:var(--status-conflict-fg);">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="modal-foot" style="display:flex;justify-content:space-between;">
                        <button type="button" class="btn btn-primary" @click="openAddCalendar()" style="font-size:13px;">+ เพิ่มปฏิทิน</button>
                        <button type="button" class="btn btn-ghost" @click="showCalendarsModal = false">ปิด</button>
                    </div>
                </div>
            </div>
        </template>

        {{-- ── ตัวแก้/เพิ่มปฏิทิน (styled แบบ modal หลักสูตร) ── --}}
        <template x-if="showCalEditor">
            <div class="overlay" x-cloak @keydown.escape.window="showCalEditor = false" style="z-index: calc(var(--z-modal) + 10);">
                <div class="modal-center" x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                    <div class="modal-hdr" style="background: var(--bg-2);">
                        <div class="modal-ttl" style="font-family: var(--font-display);"
                            x-text="editCalMode ? 'แก้ไขปฏิทิน' : 'เพิ่มปฏิทิน'"></div>
                        <button type="button" class="modal-cls" @click="showCalEditor = false">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg>
                        </button>
                    </div>
                    <form method="POST"
                        :action="editCalMode ? '{{ url($routePrefix . '/settings/calendars') }}/' + currentCal.id : ('{{ url($routePrefix . '/settings/academic-years') }}/' + calYearId + '/calendars')">
                        @csrf
                        <template x-if="editCalMode"><input type="hidden" name="_method" value="PUT"></template>
                        <div class="modal-body">
                            <div style="font-weight:700;font-size:12px;color:var(--brand-navy);border-bottom:1px solid var(--border);padding-bottom:6px;margin-bottom:14px;">ข้อมูลปฏิทิน</div>
                            <div class="form-group" style="margin-bottom:16px;">
                                <label>ชื่อปฏิทิน <span style="color: var(--status-conflict-fg)">*</span></label>
                                <input type="text" name="name" x-model="currentCal.name" required placeholder="เช่น ป.ตรี ปี 3-4, ป.โท">
                            </div>
                            {{-- ขอบเขต: หลักสูตร + ช่วงชั้นปี · เว้นว่าง = ปฏิทิน "ทุกหลักสูตร" (ค่าเริ่มต้น) --}}
                            <div class="form-group" style="margin-bottom:16px;">
                                <label>ใช้กับหลักสูตร</label>
                                <select name="curriculum_id" x-model="currentCal.curriculum_id">
                                    <option value="">ทุกหลักสูตร</option>
                                    <template x-for="c in calCurriculums" :key="c.id">
                                        <option :value="c.id" x-text="c.name"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="form-row" x-show="calCurriculumUsesYear()" x-cloak>
                                <div class="form-group">
                                    <label>ชั้นปีต่ำสุด</label>
                                    <select name="year_level_min" x-model="currentCal.year_level_min">
                                        <option value="">ทุกชั้นปี</option>
                                        <template x-for="y in calYearLevels()" :key="y">
                                            <option :value="y" x-text="'ปี ' + y"></option>
                                        </template>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>ชั้นปีสูงสุด</label>
                                    <select name="year_level_max" x-model="currentCal.year_level_max">
                                        <option value="">ทุกชั้นปี</option>
                                        <template x-for="y in calYearLevels()" :key="y">
                                            <option :value="y" x-text="'ปี ' + y"></option>
                                        </template>
                                    </select>
                                </div>
                            </div>
                            <div style="font-size:11px;color:var(--fg-4);margin-bottom:14px;">เว้นว่าง = ใช้กับทุกหลักสูตร/ทุกชั้นปี (ปฏิทินค่าเริ่มต้น) · ระบุหลักสูตร/ชั้นปีเพื่อให้กลุ่มนั้นใช้ปฏิทินนี้แทน</div>
                            @if($errors->has('calendar_terms'))
                                <div style="margin-bottom:10px;padding:8px 10px;background:oklch(97% 0.02 20);border:1px solid oklch(82% 0.08 25);border-radius:6px;color:var(--status-conflict-fg);font-size:12px;line-height:1.6;">
                                    @foreach($errors->get('calendar_terms') as $msg)<div>• {{ $msg }}</div>@endforeach
                                </div>
                            @endif
                            <div style="font-weight:700;font-size:12px;color:var(--brand-navy);border-bottom:1px solid var(--border);padding-bottom:6px;margin-bottom:12px;">ภาคการศึกษา (เทอม)</div>
                            @include('shared.settings._term_fields', ['index' => 0, 'seq' => 1, 'label' => 'ภาคเรียนที่ 1', 'model' => 'currentCal.terms'])
                            @include('shared.settings._term_fields', ['index' => 1, 'seq' => 2, 'label' => 'ภาคเรียนที่ 2', 'model' => 'currentCal.terms'])
                            <div x-show="currentCal.hasSummer" x-cloak>
                                @include('shared.settings._term_fields', ['index' => 2, 'seq' => 3, 'label' => 'ภาคฤดูร้อน', 'model' => 'currentCal.terms'])
                                <button type="button" @click="currentCal.hasSummer = false; currentCal.terms[2] = { name:'ภาคฤดูร้อน', start_date:'', end_date:'', midterm_start:'', midterm_end:'', final_start:'', final_end:'' }"
                                    style="font-size:12px;color:var(--status-conflict-fg);background:none;border:none;cursor:pointer;padding:2px 0;margin-bottom:6px;">ลบภาคฤดูร้อน</button>
                            </div>
                            <button type="button" x-show="!currentCal.hasSummer" @click="currentCal.hasSummer = true" class="btn btn-ghost" style="font-size:12px;padding:5px 12px;">+ เพิ่มภาคฤดูร้อน</button>
                        </div>
                        <div class="modal-foot">
                            <button type="button" class="btn btn-ghost" @click="showCalEditor = false">ยกเลิก</button>
                            <button type="submit" class="btn btn-primary">บันทึกปฏิทิน</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        @if($canManageHolidays)
        <!-- Add/Edit Holiday Modal -->
        <template x-if="showHolidayModal">
            <div class="overlay" x-cloak>
                <div class="modal-center" style="max-width: 480px;"
                    x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                    <div class="modal-hdr" style="background: var(--bg-2);">
                        <div class="modal-ttl" style="font-family: var(--font-display);" x-text="editHolidayMode ? 'แก้ไขวันหยุด' : 'เพิ่มวันหยุด'"></div>
                        <button type="button" class="modal-cls" @click="showHolidayModal = false">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <form :action="editHolidayMode ? '{{ url($routePrefix . '/settings/holidays') }}/' + currentHoliday.id : '{{ route($routePrefix . '.settings.holidays.store') }}'" method="POST">
                        @csrf
                        <input type="hidden" name="_method" value="PUT" :disabled="!editHolidayMode">
                        <div class="modal-body">
                            <div class="form-group" style="margin-bottom: 16px;">
                                <label>วันที่ <span style="color: var(--status-conflict-fg)">*</span></label>
                                <x-thai-date-input name="date" x-model="currentHoliday.date" required helper="" />
                                @error('date')<span style="color: var(--red, #dc2626); font-size: 12px; display: block; margin-top: 4px;">{{ $message }}</span>@enderror
                            </div>
                            <div class="form-group" style="margin-bottom: 16px;">
                                <label>ชื่อวันหยุด <span style="color: var(--status-conflict-fg)">*</span></label>
                                <input type="text" name="name" x-model="currentHoliday.name" required maxlength="255" placeholder="เช่น วันสงกรานต์">
                                @error('name')<span style="color: var(--red, #dc2626); font-size: 12px; display: block; margin-top: 4px;">{{ $message }}</span>@enderror
                            </div>
                            <div class="form-group">
                                <label>หมายเหตุ</label>
                                <input type="text" name="remark" x-model="currentHoliday.remark" maxlength="255" placeholder="(ถ้ามี)">
                            </div>
                        </div>
                        <div class="modal-foot" style="display: flex; justify-content: space-between;">
                            <button type="button" class="btn btn-ghost" x-show="editHolidayMode"
                                @click="$refs.deleteHolidayForm.submit()"
                                style="color: var(--status-conflict-fg);">ลบ</button>
                            <div style="display: flex; gap: 8px; margin-left: auto;">
                                <button type="button" class="btn btn-ghost" @click="showHolidayModal = false">ยกเลิก</button>
                                <button type="submit" class="btn btn-primary">บันทึก</button>
                            </div>
                        </div>
                    </form>
                    <form x-ref="deleteHolidayForm" :action="'{{ url($routePrefix . '/settings/holidays') }}/' + currentHoliday.id" method="POST" style="display: none;">
                        @csrf
                        @method('DELETE')
                    </form>
                </div>
            </div>
        </template>

        @endif
        @if($isAdmin)
        <!-- Tab: PA Rules -->
        <div x-show="activeTab === 'pa'" x-cloak>
            <form action="{{ route('admin.settings.constants.update') }}" method="POST">
                @csrf
                <div class="settings-grid">

                    <div class="card">
                        <div class="card-hdr">
                            <div class="card-ttl">ค่าคงที่ภาระงานประจำปี</div>
                        </div>

                        {{-- Input rows --}}
                        <div style="border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 12px 20px;">
                            <div>
                                <div style="font-size: 13px; font-weight: 600; color: var(--fg-1);">สัปดาห์ทำงานรวม / ปี</div>
                                <div style="font-size: 11px; color: var(--fg-3); margin-top: 1px;">ใช้คำนวณปฏิบัติงานรวม</div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <input type="number" name="teaching_quota_weeks" x-model.number="workloadWeeks" min="1" required style="width: 72px; text-align: center; font-weight: 700;">
                                <span style="font-size: 12px; color: var(--fg-3); width: 100px;">สัปดาห์</span>
                            </div>
                        </div>
                        <div style="border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 12px 20px;">
                            <div>
                                <div style="font-size: 13px; font-weight: 600; color: var(--fg-1);">สัปดาห์งานสอน / ปี</div>
                                <div style="font-size: 11px; color: var(--fg-3); margin-top: 1px;">ใช้คำนวณฐานงานสอน (PA)</div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <input type="number" name="teaching_load_weeks" x-model.number="teachingWeeks" min="1" required style="width: 72px; text-align: center; font-weight: 700;">
                                <span style="font-size: 12px; color: var(--fg-3); width: 100px;">สัปดาห์</span>
                            </div>
                        </div>
                        <div style="border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 12px 20px;">
                            <div>
                                <div style="font-size: 13px; font-weight: 600; color: var(--fg-1);">ชั่วโมงทำงาน / สัปดาห์</div>
                                <div style="font-size: 11px; color: var(--fg-3); margin-top: 1px;">ใช้กับทั้งสองสูตรข้างต้น</div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <input type="number" name="teaching_quota_hours_per_week" x-model.number="workloadHoursPerWeek" min="1" required style="width: 72px; text-align: center; font-weight: 700;">
                                <span style="font-size: 12px; color: var(--fg-3); width: 100px;">ชั่วโมง/สัปดาห์</span>
                            </div>
                        </div>

                        {{-- Calculated results --}}
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 20px; border-bottom: 1px solid var(--border); background: var(--bg-2);">
                            <span style="font-size: 12px; color: var(--fg-3);">ปฏิบัติงานรวม</span>
                            <span style="font-size: 15px; font-weight: 700; color: var(--fg-1);" x-text="totalQuota + ' ชั่วโมง/ปี'"></span>
                        </div>
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 20px; background: var(--brand-navy);">
                            <span style="font-size: 12px; color: rgba(255,255,255,0.7);">เกณฑ์ภาระงานสอน</span>
                            <span style="font-size: 15px; font-weight: 700; color: #fff;" x-text="teachingQuota + ' ชั่วโมง/ปี'"></span>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-hdr">
                            <div class="card-ttl">สัดส่วนเกณฑ์ PA ตามตำแหน่งทางวิชาการ</div>
                            <div style="font-size: 12px; color: var(--fg-3);">ระบุช่วง ต่ำสุด – สูงสุด (%)</div>
                        </div>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ตำแหน่ง / ประเภท</th>
                                        @foreach(['สอน','วิจัย','บริการฯ','ศิลปะฯ','มอบหมาย'] as $col)
                                        <th style="text-align: center;">{{ $col }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($paCriteria as $rank => $ranges)
                                    <tr>
                                        <td style="font-weight: 600; color: var(--fg-1); white-space: nowrap;">{{ str_replace('_', ' ', $rank) }}</td>
                                        @foreach(['t','r','s','c','o'] as $f)
                                        @php $range = $ranges[$f] ?? ['min'=>0,'max'=>100]; @endphp
                                        <td style="padding: 6px 8px;">
                                            <div style="display: flex; flex-direction: column; gap: 4px; align-items: center;">
                                                <div style="display: flex; align-items: center; gap: 4px;">
                                                    <span style="font-size: 10px; color: var(--fg-3); width: 22px;">min</span>
                                                    <input type="number" name="pa_criteria[{{ $rank }}][{{ $f }}][min]"
                                                           value="{{ $range['min'] }}" min="0" max="100"
                                                           class="pa-input" style="width: 52px; text-align: center;">
                                                    <span style="font-size: 11px; color: var(--fg-3);">%</span>
                                                </div>
                                                <div style="display: flex; align-items: center; gap: 4px;">
                                                    <span style="font-size: 10px; color: var(--fg-3); width: 22px;">max</span>
                                                    <input type="number" name="pa_criteria[{{ $rank }}][{{ $f }}][max]"
                                                           value="{{ $range['max'] }}" min="0" max="100"
                                                           class="pa-input" style="width: 52px; text-align: center;">
                                                    <span style="font-size: 11px; color: var(--fg-3);">%</span>
                                                </div>
                                            </div>
                                        </td>
                                        @endforeach
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div style="padding: 16px 20px; border-top: 1px solid var(--border); background: var(--surface); text-align: right;">
                            <button type="submit" class="btn btn-primary">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;">
                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                    <polyline points="7 3 7 8 15 8"></polyline>
                                </svg>
                                บันทึกการตั้งค่า
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        @endif

    </div>

    <style>
        [x-cloak] { display: none !important; }

        .settings-grid {
            display: grid;
            grid-template-columns: 360px 1fr;
            gap: 24px;
            align-items: start;
        }
        .settings-grid > div { min-width: 0; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            width: 100%;
        }

        .settings-hdr {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--surface);
            gap: 16px;
        }
        .hdr-helper {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .copy-box {
            display: flex;
            align-items: center;
            gap: 4px;
            background: var(--surface);
            padding: 4px 8px;
            border-radius: 6px;
            border: 1px solid var(--border);
        }
        .academic-year-icons {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }
        .academic-year-actions {
            display: grid;
            grid-template-columns: max-content minmax(150px, 1fr);
            align-items: center;
            gap: 8px;
            justify-content: center;
            margin: 0 auto;
            width: fit-content;
        }
        .settings-action-cell {
            text-align: center;
        }
        .academic-year-actions.is-icon-only {
            display: flex;
            justify-content: center;
            width: 100%;
        }
        .academic-year-schedule-action {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            min-width: 150px;
        }

        .settings-flash {
            position: relative;
            display: grid;
            grid-template-columns: 34px minmax(0, 1fr);
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
            padding: 14px 16px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 22%, var(--border));
            border-radius: var(--r-lg);
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 6%, var(--surface)), var(--surface));
            color: var(--fg-2);
            font-size: 14px;
            line-height: 1.6;
            box-shadow:
                0 1px 2px rgba(0, 36, 84, 0.08),
                0 16px 34px -28px rgba(0, 36, 84, 0.42);
            overflow: hidden;
        }

        .settings-flash::before {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: 4px;
            background: var(--settings-flash-accent, var(--brand-navy));
        }

        .settings-flash-icon {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: color-mix(in oklch, var(--settings-flash-accent, var(--brand-navy)) 12%, transparent);
            color: var(--settings-flash-accent, var(--brand-navy));
        }

        .settings-flash--success {
            --settings-flash-accent: var(--status-success-fg);
        }

        .settings-flash--error {
            --settings-flash-accent: var(--status-conflict-fg);
        }

        [x-show="openScheduleConfirmForm"],
        [x-show="closeScheduleConfirmForm"] {
            position: fixed !important;
            inset: 0 !important;
            z-index: 80 !important;
            display: grid !important;
            place-items: center !important;
            padding: clamp(14px, 2vw, 24px) !important;
            background:
                color-mix(in oklch, var(--brand-navy) 18%, transparent) !important;
            backdrop-filter: blur(3px);
        }

        [x-show="openScheduleConfirmForm"][style*="display: none"],
        [x-show="closeScheduleConfirmForm"][style*="display: none"] {
            display: none !important;
        }

        [x-show="openScheduleConfirmForm"] > div,
        [x-show="closeScheduleConfirmForm"] > div {
            min-height: auto !important;
            width: min(100%, 560px) !important;
            display: block !important;
            padding: 0 !important;
        }

        [x-show="openScheduleConfirmForm"] > div > div,
        [x-show="closeScheduleConfirmForm"] > div > div {
            width: 100% !important;
            max-height: min(720px, calc(100vh - 32px));
            overflow: hidden !important;
            display: grid;
            grid-template-rows: auto minmax(0, 1fr) auto;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 12%, var(--border)) !important;
            border-radius: var(--r-lg) !important;
            background: var(--surface) !important;
            box-shadow:
                0 1px 2px rgba(0, 36, 84, 0.06),
                0 28px 76px -38px rgba(0, 36, 84, 0.46) !important;
        }

        [x-show="openScheduleConfirmForm"] > div > div > div:first-child,
        [x-show="closeScheduleConfirmForm"] > div > div > div:first-child {
            position: relative;
            display: grid;
            grid-template-columns: 44px minmax(0, 1fr);
            grid-template-rows: auto auto;
            gap: 14px;
            align-items: center;
            padding: 20px 24px !important;
            border-bottom: 1px solid var(--border) !important;
            background: color-mix(in oklch, var(--brand-navy) 4%, var(--surface)) !important;
        }

        [x-show="openScheduleConfirmForm"] > div > div > div:first-child::before,
        [x-show="closeScheduleConfirmForm"] > div > div > div:first-child::before {
            content: "";
            width: 44px;
            height: 44px;
            border-radius: 12px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 18%, var(--border));
            background:
                linear-gradient(180deg,
                    color-mix(in oklch, var(--brand-navy) 10%, var(--surface)),
                    color-mix(in oklch, var(--brand-navy) 4%, var(--surface)));
            box-shadow: inset 0 0 0 1px color-mix(in oklch, var(--surface) 72%, transparent);
            grid-column: 1;
            grid-row: 1 / span 2;
            align-self: center;
            justify-self: start;
        }

        [x-show="openScheduleConfirmForm"] > div > div > div:first-child::after,
        [x-show="closeScheduleConfirmForm"] > div > div > div:first-child::after {
            content: "";
            width: 29px;
            height: 29px;
            grid-column: 1;
            grid-row: 1 / span 2;
            align-self: center;
            justify-self: center;
            pointer-events: none;
            background: var(--brand-navy);
            mask: center / contain no-repeat url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.7' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M8 2v4M16 2v4M3.5 9.5h17M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z'/%3E%3Cpath d='m8.8 14 2.2 2.2 4.5-5.1'/%3E%3C/svg%3E");
            -webkit-mask: center / contain no-repeat url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.7' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M8 2v4M16 2v4M3.5 9.5h17M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z'/%3E%3Cpath d='m8.8 14 2.2 2.2 4.5-5.1'/%3E%3C/svg%3E");
        }

        [x-show="closeScheduleConfirmForm"] > div > div > div:first-child::before {
            border-color: color-mix(in oklch, var(--status-warning-fg) 22%, var(--border));
            background:
                linear-gradient(180deg,
                    color-mix(in oklch, var(--status-warning-bg) 72%, var(--surface)),
                    color-mix(in oklch, var(--status-warning-bg) 38%, var(--surface)));
        }

        [x-show="closeScheduleConfirmForm"] > div > div > div:first-child::after {
            background: var(--status-warning-fg);
            mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M10.3 4.3 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 4.3a2 2 0 0 0-3.4 0z'/%3E%3Cpath d='M12 9v4M12 17h.01'/%3E%3C/svg%3E");
            -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M10.3 4.3 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 4.3a2 2 0 0 0-3.4 0z'/%3E%3Cpath d='M12 9v4M12 17h.01'/%3E%3C/svg%3E");
        }

        [x-show="openScheduleConfirmForm"] > div > div > div:first-child > div:first-child,
        [x-show="closeScheduleConfirmForm"] > div > div > div:first-child > div:first-child {
            grid-column: 2;
            grid-row: 1;
            font-size: 18px !important;
            line-height: 1.25 !important;
            color: var(--fg-1) !important;
        }

        [x-show="openScheduleConfirmForm"] > div > div > div:first-child > div:last-child,
        [x-show="closeScheduleConfirmForm"] > div > div > div:first-child > div:last-child {
            grid-column: 2;
            grid-row: 2;
            width: fit-content;
            max-width: 100%;
            display: inline-flex;
            align-items: center;
            margin-top: -6px !important;
            padding: 4px 10px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 18%, var(--border));
            border-radius: 999px;
            background: color-mix(in oklch, var(--brand-navy) 5%, var(--surface));
            color: var(--fg-2) !important;
            font-size: 12px !important;
            font-weight: 700;
            line-height: 1.35 !important;
        }

        [x-show="openScheduleConfirmForm"] > div > div > div:nth-child(2),
        [x-show="closeScheduleConfirmForm"] > div > div > div:nth-child(2) {
            overflow-y: auto;
            padding: 22px 24px !important;
            background: var(--surface) !important;
        }

        [x-show="openScheduleConfirmForm"] > div > div > div:nth-child(2) > div:first-child,
        [x-show="closeScheduleConfirmForm"] > div > div > div:nth-child(2) > div:first-child {
            color: var(--fg-2) !important;
            font-size: 14px !important;
            line-height: 1.7 !important;
        }

        [x-show="openScheduleConfirmForm"] > div > div > div:nth-child(2) > div:nth-child(n+2),
        [x-show="closeScheduleConfirmForm"] > div > div > div:nth-child(2) > div:nth-child(n+2) {
            margin-top: 12px !important;
            padding: 12px 14px !important;
            border-radius: var(--r-md) !important;
            font-size: 13px !important;
            font-weight: 700 !important;
            line-height: 1.55 !important;
        }

        [x-show="openScheduleConfirmForm"] > div > div > div:nth-child(2) > div:last-child,
        [x-show="closeScheduleConfirmForm"] > div > div > div:nth-child(2) > div:last-child {
            border: 1px solid var(--status-warning-border) !important;
            background: var(--status-warning-bg) !important;
            color: var(--status-warning-fg) !important;
        }

        [x-show="closeScheduleConfirmForm"] > div > div > div:nth-child(2) > div:nth-child(2) {
            border: 1px solid var(--status-info-border) !important;
            background: var(--status-info-bg) !important;
            color: var(--status-info-fg) !important;
        }

        [x-show="openScheduleConfirmForm"] > div > div > div:last-child,
        [x-show="closeScheduleConfirmForm"] > div > div > div:last-child {
            display: flex !important;
            justify-content: flex-end !important;
            gap: 10px !important;
            padding: 16px 24px !important;
            border-top: 1px solid var(--border) !important;
            background: color-mix(in oklch, var(--brand-navy) 3%, var(--bg-2)) !important;
        }

        [x-show="openScheduleConfirmForm"] > div > div > div:last-child .btn,
        [x-show="closeScheduleConfirmForm"] > div > div > div:last-child .btn {
            min-height: 42px;
            border-radius: var(--r-md);
            padding-inline: 18px;
            font-weight: 800;
        }

        [x-show="openScheduleConfirmForm"] > div > div > div:last-child .btn-primary:disabled,
        [x-show="closeScheduleConfirmForm"] > div > div > div:last-child .btn-primary:disabled {
            opacity: .58 !important;
            cursor: not-allowed !important;
            filter: saturate(.75);
        }

        @media (max-width: 1024px) {
            .settings-grid { grid-template-columns: 1fr; }
            .settings-hdr {
                flex-direction: column;
                align-items: flex-start;
            }
            .hdr-helper {
                width: 100%;
                justify-content: space-between;
                flex-wrap: wrap;
            }
        }

        @media (max-width: 640px) {
            .stats-grid { grid-template-columns: 1fr; }
            .tabs-container {
                justify-content: flex-start !important;
                width: 100%;
            }

            [x-show="openScheduleConfirmForm"],
            [x-show="closeScheduleConfirmForm"] {
                align-items: end !important;
                place-items: end center !important;
                padding: 10px !important;
            }

            [x-show="openScheduleConfirmForm"] > div,
            [x-show="closeScheduleConfirmForm"] > div {
                width: 100% !important;
            }

            [x-show="openScheduleConfirmForm"] > div > div,
            [x-show="closeScheduleConfirmForm"] > div > div {
                max-height: calc(100vh - 20px);
                border-radius: 14px !important;
            }

            [x-show="openScheduleConfirmForm"] > div > div > div:first-child,
            [x-show="closeScheduleConfirmForm"] > div > div > div:first-child {
                grid-template-columns: 38px minmax(0, 1fr);
                grid-template-rows: auto auto;
                gap: 12px;
                padding: 18px !important;
            }

            [x-show="openScheduleConfirmForm"] > div > div > div:first-child::before,
            [x-show="closeScheduleConfirmForm"] > div > div > div:first-child::before {
                width: 38px;
                height: 38px;
            }

            [x-show="openScheduleConfirmForm"] > div > div > div:first-child > div:last-child,
            [x-show="closeScheduleConfirmForm"] > div > div > div:first-child > div:last-child {
                margin-top: -4px !important;
                white-space: normal;
            }

            [x-show="openScheduleConfirmForm"] > div > div > div:nth-child(2),
            [x-show="closeScheduleConfirmForm"] > div > div > div:nth-child(2) {
                padding: 18px !important;
            }

            [x-show="openScheduleConfirmForm"] > div > div > div:last-child,
            [x-show="closeScheduleConfirmForm"] > div > div > div:last-child {
                display: grid !important;
                grid-template-columns: 1fr;
                padding: 14px 18px 18px !important;
            }

            [x-show="openScheduleConfirmForm"] > div > div > div:last-child .btn,
            [x-show="closeScheduleConfirmForm"] > div > div > div:last-child .btn {
                width: 100%;
            }
        }

        .pa-input {
            width: 80px; margin: 0 auto; display: block;
            padding: 8px !important; font-size: 13px !important;
            text-align: center; border: 1px solid var(--border) !important;
            border-radius: 6px !important; background: white; color: var(--fg-1);
        }
        .pa-input:focus {
            border-color: var(--brand-navy) !important; outline: none;
            box-shadow: inset 0 0 0 1px var(--brand-navy);
        }
        .btn-ghost:hover { background: var(--bg-3); }

        .settings-page {
            padding: clamp(14px, 2vw, 28px);
            background:
                radial-gradient(circle at 8% 0%, color-mix(in oklch, var(--brand-navy) 10%, transparent), transparent 30%),
                linear-gradient(180deg,
                    color-mix(in oklch, var(--brand-navy) 7%, var(--bg)) 0%,
                    color-mix(in oklch, var(--brand-navy) 4%, var(--bg)) 34%,
                    var(--bg) 100%);
        }

        .settings-page .card,
        .settings-page .settings-panel,
        .settings-page .settings-card,
        .settings-page .stats-card,
        .settings-page .stat-card,
        .settings-page .panel {
            border-color: color-mix(in oklch, var(--brand-navy) 24%, var(--border)) !important;
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 5%, var(--surface)), var(--surface) 46%),
                var(--surface) !important;
            box-shadow:
                0 1px 2px rgba(0, 36, 84, 0.09),
                0 16px 34px -22px rgba(0, 36, 84, 0.42) !important;
        }

        .settings-page .card-hdr,
        .settings-page .settings-hdr,
        .settings-page .panel-hdr,
        .settings-page thead th {
            border-bottom-color: color-mix(in oklch, var(--brand-navy) 20%, var(--border)) !important;
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 10%, var(--surface)), color-mix(in oklch, var(--brand-navy) 4%, var(--surface))) !important;
        }

        .settings-page .tabs,
        .settings-page [role="tablist"] {
            border-color: color-mix(in oklch, var(--brand-navy) 24%, var(--border)) !important;
            background: color-mix(in oklch, var(--brand-navy) 8%, var(--surface)) !important;
            box-shadow: 0 1px 2px rgba(0, 36, 84, 0.08);
        }

        .settings-page .btn-primary {
            border-color: var(--brand-navy) !important;
            background: var(--brand-navy) !important;
            color: var(--fg-on-brand) !important;
            box-shadow:
                0 1px 2px rgba(0, 36, 84, 0.16),
                0 10px 20px -16px rgba(0, 36, 84, 0.64);
        }

        .settings-page .btn-ghost,
        .settings-page .btn-secondary,
        .settings-page .btn:not(.btn-primary) {
            border-color: color-mix(in oklch, var(--brand-navy) 24%, var(--border));
            background: color-mix(in oklch, var(--brand-navy) 5%, var(--surface));
            color: var(--brand-navy);
        }

        .settings-page .btn-ghost:hover,
        .settings-page .btn-secondary:hover,
        .settings-page .btn:not(.btn-primary):hover {
            border-color: color-mix(in oklch, var(--brand-navy) 36%, var(--border));
            background: color-mix(in oklch, var(--brand-navy) 9%, var(--surface));
        }

        .settings-page .form-ctrl,
        .settings-page input,
        .settings-page select,
        .settings-page textarea,
        .settings-page .pa-input {
            border-color: color-mix(in oklch, var(--brand-navy) 22%, var(--border)) !important;
            background: color-mix(in oklch, var(--brand-navy) 3%, var(--surface)) !important;
        }

        .settings-page .form-ctrl:focus,
        .settings-page input:focus,
        .settings-page select:focus,
        .settings-page textarea:focus,
        .settings-page .pa-input:focus {
            border-color: var(--brand-navy) !important;
            box-shadow: 0 0 0 3px color-mix(in oklch, var(--brand-navy) 12%, transparent) !important;
        }

        .settings-page table th {
            color: color-mix(in oklch, var(--brand-navy) 72%, var(--fg-2));
        }

        .settings-page table td {
            border-bottom-color: color-mix(in oklch, var(--brand-navy) 10%, var(--border-subtle));
        }

        .settings-page tbody tr:hover {
            background: color-mix(in oklch, var(--brand-navy) 5%, var(--surface));
        }
    </style>
</x-app-layout>
