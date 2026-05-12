<x-app-layout title="{{ $isAdmin ? 'ตั้งค่าระบบ' : 'ตั้งค่าปีการศึกษา' }}">
    <div x-data="{
        activeTab: new URLSearchParams(window.location.search).get('tab') || 'academic',
        workloadWeeks: {{ $workloadWeeks }},
        teachingWeeks: {{ $teachingWeeks }},
        workloadHoursPerWeek: {{ $workloadHoursPerWeek }},
        get totalQuota() { return this.workloadWeeks * this.workloadHoursPerWeek },
        get teachingQuota() { return this.teachingWeeks * this.workloadHoursPerWeek },
        showModal: false,
        editMode: false,
        currentYear: {
            id: '',
            name: '',
            semester: '1',
            start_date: '',
            end_date: '',
            is_active: false
        },
        openAddModal() {
            this.editMode = false;
            this.currentYear = { id: '', name: '', semester: '1', start_date: '', end_date: '', is_active: false };
            this.showModal = true;
        },
        openEditModal(year) {
            this.editMode = true;
            this.currentYear = {
                id: year.id,
                name: year.name,
                semester: year.semester,
                start_date: year.start_date.split(' ')[0],
                end_date: year.end_date.split(' ')[0],
                is_active: !!year.is_active
            };
            this.showModal = true;
        }
    }">

        @if($isAdmin)
        <div style="display: flex; justify-content: flex-end; margin-bottom: 24px;">
            <div class="tabs"
                style="display: flex; gap: 8px; background: var(--bg-2); padding: 4px; border-radius: 8px; border: 1px solid var(--border);">
                <button type="button" @click="activeTab = 'academic'"
                    :class="activeTab === 'academic' ? 'btn-primary' : 'btn btn-ghost'"
                    style="padding: 8px 16px; border-radius: 6px;">
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
                    style="padding: 8px 16px; border-radius: 6px;">
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

        <!-- Tab: Academic Year -->
        <div x-show="activeTab === 'academic'" {{ $isAdmin ? 'x-cloak' : '' }}>
            @if(session('success'))
                <div style="background: oklch(95% 0.05 145); border: 1px solid oklch(70% 0.15 145); color: oklch(35% 0.12 145); padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 14px;">
                    {{ session('success') }}
                </div>
            @endif
            <div class="card">
                <div class="card-hdr">
                    <div class="card-ttl">รายการปีการศึกษา</div>
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
                                <th style="text-align: center;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($academicYears as $year)
                                <tr>
                                    <td style="font-weight: 600; color: var(--fg-1);">{{ $year->name }}</td>
                                    <td>ภาคเรียนที่ {{ $year->semester }}</td>
                                    <td style="color: var(--fg-2); font-size: 13px;">
                                        {{ date('d/m/Y', strtotime($year->start_date)) }} -
                                        {{ date('d/m/Y', strtotime($year->end_date)) }}
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
                                    <td style="text-align: center;">
                                        <div style="display: flex; gap: 6px; justify-content: center;">
                                            <button class="action-btn" title="แก้ไข"
                                                @click="openEditModal({{ json_encode($year) }})">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 40px; color: var(--fg-3);">
                                        ยังไม่มีข้อมูลปีการศึกษา</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add/Edit Modal (Academic Year) -->
        <template x-if="showModal && activeTab === 'academic'">
            <div class="overlay" x-cloak @click.self="showModal = false">
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
                        <div class="modal-body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>ปีการศึกษา (พ.ศ.)</label>
                                    <input type="text" name="name" x-model="currentYear.name" required
                                        placeholder="เช่น 2569">
                                </div>
                                <div class="form-group">
                                    <label>ภาคเรียน</label>
                                    <select name="semester" x-model="currentYear.semester" required>
                                        <option value="1">ภาคเรียนที่ 1</option>
                                        <option value="2">ภาคเรียนที่ 2</option>
                                        <option value="3">ภาคเรียนฤดูร้อน</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>วันที่เริ่ม</label>
                                    <input type="date" name="start_date" x-model="currentYear.start_date" required>
                                </div>
                                <div class="form-group">
                                    <label>วันที่สิ้นสุด</label>
                                    <input type="date" name="end_date" x-model="currentYear.end_date" required>
                                </div>
                            </div>
                            <div class="form-group" style="margin-top: 16px;">
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; color: var(--fg-1);">
                                    <input type="checkbox" name="is_active" value="1" x-model="currentYear.is_active"
                                        style="width: 16px; height: 16px; accent-color: var(--brand-navy);">
                                    ตั้งเป็นปีการศึกษาปัจจุบัน (Active)
                                </label>
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
                <div style="display: grid; grid-template-columns: 360px 1fr; gap: 24px; align-items: start;">

                    <div style="display: flex; flex-direction: column; gap: 24px;">
                        <div class="card">
                            <div class="card-hdr">
                                <div class="card-ttl">ค่าคงที่ภาระงานประจำปี</div>
                            </div>
                            <div class="card-body" style="display: flex; flex-direction: column; align-items: center; gap: 20px;">
                                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; width: 100%;">
                                    <div class="form-group" style="display: flex; flex-direction: column; align-items: center;">
                                        <label style="text-align: center; font-size: 11px; white-space: nowrap;">สัปดาห์ทำงานรวม/ปี</label>
                                        <input type="number" name="teaching_quota_weeks" x-model.number="workloadWeeks"
                                            min="1" required
                                            style="font-weight: 700; text-align: center; width: 80px;">
                                    </div>
                                    <div class="form-group" style="display: flex; flex-direction: column; align-items: center;">
                                        <label style="text-align: center; font-size: 11px; white-space: nowrap;">สัปดาห์งานสอน/ปี</label>
                                        <input type="number" name="teaching_load_weeks" x-model.number="teachingWeeks"
                                            min="1" required
                                            style="font-weight: 700; text-align: center; width: 80px;">
                                    </div>
                                    <div class="form-group" style="display: flex; flex-direction: column; align-items: center;">
                                        <label style="text-align: center; font-size: 11px; white-space: nowrap;">ชั่วโมงทำงาน/สัปดาห์</label>
                                        <input type="number" name="teaching_quota_hours_per_week"
                                            x-model.number="workloadHoursPerWeek" min="1" required
                                            style="font-weight: 700; text-align: center; width: 80px;">
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; width: 100%;">
                                    <div style="padding: 16px 12px; background: var(--bg-2); border-radius: 12px; text-align: center; border: 1px solid var(--border); display: flex; flex-direction: column; justify-content: center; min-height: 80px;">
                                        <div style="font-size: 11px; font-weight: 600; color: var(--fg-3); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">ปฏิบัติงานรวม</div>
                                        <div style="font-size: 24px; font-weight: 800; color: var(--brand-navy); line-height: 1; display: flex; align-items: baseline; justify-content: center; gap: 4px;">
                                            <span x-text="totalQuota"></span>
                                            <span style="font-size: 13px; font-weight: 600; color: var(--fg-3);">ชม./ปี</span>
                                        </div>
                                    </div>
                                    <div style="padding: 16px 12px; background: var(--brand-navy); border-radius: 12px; text-align: center; border: 1px solid var(--brand-navy); display: flex; flex-direction: column; justify-content: center; min-height: 80px; box-shadow: 0 4px 12px rgba(0, 35, 71, 0.15);">
                                        <div style="font-size: 11px; font-weight: 600; color: rgba(255,255,255,0.7); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">ฐานงานสอน (PA)</div>
                                        <div style="font-size: 24px; font-weight: 800; color: white; line-height: 1; display: flex; align-items: baseline; justify-content: center; gap: 4px;">
                                            <span x-text="teachingQuota"></span>
                                            <span style="font-size: 13px; font-weight: 600; color: rgba(255,255,255,0.6);">ชม./ปี</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div style="padding: 16px; background: var(--status-success-bg); border-radius: 12px; display: flex; gap: 12px;">
                            <div style="color: var(--status-success-fg);"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="16" x2="12" y2="12"></line>
                                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                </svg></div>
                            <div style="font-size: 13px; color: var(--fg-1); line-height: 1.5;">
                                <strong style="color: var(--status-success-fg);">คำนวณอัตโนมัติ</strong><br>
                                ข้อมูลเหล่านี้จะถูกนำไปใช้อ้างอิงในการคำนวณสัดส่วนภาระงาน (Workload) ของอาจารย์ทั้งหมด
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-hdr" style="display: flex; justify-content: space-between; align-items: center;">
                            <div class="card-ttl">สัดส่วนเกณฑ์ PA ตามตำแหน่งทางวิชาการ</div>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="display: flex; align-items: center; gap: 4px; background: var(--bg-1); padding: 4px 8px; border-radius: 6px; border: 1px solid var(--border);">
                                    <span style="font-size: 11px; color: var(--fg-3); margin-right: 4px;">คลิกเพื่อก๊อปปี้:</span>
                                    @foreach(['≤', '≥', '-', '%'] as $sym)
                                        <button type="button"
                                            onclick="navigator.clipboard.writeText('{{ $sym }}')"
                                            class="btn-ghost"
                                            style="padding: 2px 6px; font-size: 13px; font-weight: 700; min-width: 28px;"
                                            title="คลิกเพื่อคัดลอก {{ $sym }}">
                                            {{ $sym }}
                                        </button>
                                    @endforeach
                                </div>
                                <div style="font-size: 12px; color: var(--fg-3);">หน่วย: เปอร์เซ็นต์ (%)</div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width: 25%;">ตำแหน่ง / ประเภท</th>
                                        <th style="text-align: center;">สอน</th>
                                        <th style="text-align: center;">วิจัย</th>
                                        <th style="text-align: center;">บริการฯ</th>
                                        <th style="text-align: center;">ศิลปะฯ</th>
                                        <th style="text-align: center;">มอบหมาย</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($paCriteria as $rank => $ranges)
                                        <tr>
                                            <td style="font-weight: 600; color: var(--fg-1);">
                                                {{ str_replace('_', ' ', $rank) }}
                                            </td>
                                            <td style="padding: 8px;"><input type="text" name="pa_criteria[{{ $rank }}][t]" value="{{ $ranges['t'] }}" class="pa-input"></td>
                                            <td style="padding: 8px;"><input type="text" name="pa_criteria[{{ $rank }}][r]" value="{{ $ranges['r'] }}" class="pa-input"></td>
                                            <td style="padding: 8px;"><input type="text" name="pa_criteria[{{ $rank }}][s]" value="{{ $ranges['s'] }}" class="pa-input"></td>
                                            <td style="padding: 8px;"><input type="text" name="pa_criteria[{{ $rank }}][c]" value="{{ $ranges['c'] }}" class="pa-input"></td>
                                            <td style="padding: 8px;"><input type="text" name="pa_criteria[{{ $rank }}][o]" value="{{ $ranges['o'] }}" class="pa-input"></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div style="padding: 20px; border-top: 1px solid var(--border); background: var(--bg-1); text-align: right;">
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
