<x-app-layout title="{{ $isAdmin ? 'ตั้งค่าระบบ' : 'ตั้งค่าปีการศึกษา' }}">
    <div x-data="{
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
        openAddModal() {
            this.editMode = false;
            this.currentYear = { id: '', name: '', start_date: '', end_date: '', is_active: false, hasSummer: false, terms: this.buildTerms([]) };
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
                <div style="background: oklch(95% 0.05 145); border: 1px solid oklch(70% 0.15 145); color: oklch(35% 0.12 145); padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 14px;">
                    {{ session('success') }}
                </div>
            @endif
            @if($isAdmin && session('error'))
                <div style="background: oklch(95% 0.05 25); border: 1px solid oklch(70% 0.15 25); color: oklch(35% 0.12 25); padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 14px;">
                    {{ session('error') }}
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
                        <div class="card-ttl">รายการปีการศึกษา</div>
                        @if($isAdmin)
                            <div style="font-size: 12px; color: var(--fg-3); margin-top: 4px;">Admin เปิด/ปิดช่วงจัดตาราง — หัวหน้าวิชาทุกท่านจัดตารางได้พร้อมกัน</div>
                        @endif
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
                                        @forelse($year->terms as $t)
                                            <span class="badge badge-gray" style="margin:1px 2px;display:inline-block;">{{ $t->name }}</span>
                                        @empty
                                            <span style="color: var(--fg-3);">—</span>
                                        @endforelse
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
                                    <td>
                                        <div class="academic-year-actions">
                                            <button class="action-btn" title="แก้ไข"
                                                @click="openEditModal({{ json_encode($year) }})">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                                </svg>
                                            </button>
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
                            @error('terms')
                                <div style="margin-bottom: 10px; padding: 8px 10px; background: oklch(97% 0.02 20); border: 1px solid oklch(82% 0.08 25); border-radius: 6px; color: var(--status-conflict-fg); font-size: 12px;">{{ $message }}</div>
                            @enderror
                            <div style="margin-top: 4px; border-top: 1px solid var(--border); padding-top: 14px;">
                                <div style="font-weight: 600; font-size: 13px; color: var(--fg-1); margin-bottom: 4px;">ภาคการศึกษา (เทอม)</div>
                                <div style="font-size: 11px; color: var(--fg-3); margin-bottom: 10px; line-height: 1.5;">
                                    กำหนดช่วงวันของแต่ละเทอม + ช่วงสัปดาห์สอบ (ไม่บังคับ) · <strong>วันเริ่ม-สิ้นสุดของปีการศึกษาคำนวณจากเทอมให้อัตโนมัติ</strong> · ช่วงปิดภาคเรียน = ช่องว่างระหว่างเทอม
                                </div>
                                @include('shared.settings._term_fields', ['index' => 0, 'seq' => 1, 'label' => 'ภาคเรียนที่ 1'])
                                @include('shared.settings._term_fields', ['index' => 1, 'seq' => 2, 'label' => 'ภาคเรียนที่ 2'])
                                <div x-show="currentYear.hasSummer" x-cloak>
                                    @include('shared.settings._term_fields', ['index' => 2, 'seq' => 3, 'label' => 'ภาคฤดูร้อน'])
                                    <button type="button" @click="currentYear.hasSummer = false; resetSummerTerm()"
                                        style="font-size: 12px; color: var(--status-conflict-fg); background: none; border: none; cursor: pointer; padding: 2px 0; margin-bottom: 6px;">
                                        ลบภาคฤดูร้อน
                                    </button>
                                </div>
                                <button type="button" x-show="!currentYear.hasSummer" @click="currentYear.hasSummer = true"
                                    class="btn btn-ghost" style="font-size: 12px; padding: 5px 12px;">
                                    + เพิ่มภาคฤดูร้อน
                                </button>
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
                        <div style="padding: 16px 20px; border-top: 1px solid var(--border); background: var(--bg-1); text-align: right;">
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
            background: var(--bg-1);
            padding: 4px 8px;
            border-radius: 6px;
            border: 1px solid var(--border);
        }
        .academic-year-actions {
            display: grid;
            grid-template-columns: 32px minmax(150px, 1fr);
            align-items: center;
            gap: 8px;
            justify-content: center;
            margin: 0 auto;
            width: fit-content;
        }
        .academic-year-schedule-action {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            min-width: 150px;
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
    </style>
</x-app-layout>
