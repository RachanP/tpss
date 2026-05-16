<x-app-layout title="{{ $isAdmin ? 'ตั้งค่าระบบ' : 'ตั้งค่าปีการศึกษา' }}">
    <div x-data="{
        activeTab: new URLSearchParams(window.location.search).get('tab') || 'academic',
        workloadWeeks: {{ $workloadWeeks }},
        teachingWeeks: {{ $teachingWeeks }},
        workloadHoursPerWeek: {{ $workloadHoursPerWeek }},
        get totalQuota() { return this.workloadWeeks * this.workloadHoursPerWeek },
        get teachingQuota() { return this.teachingWeeks * this.workloadHoursPerWeek },
        showModal: {{ $errors->hasAny(['name', 'end_date']) ? 'true' : 'false' }},
        editMode: {{ ($errors->hasAny(['name', 'end_date'])) && old('_method') === 'PUT' ? 'true' : 'false' }},
        currentYear: {
            id: '{{ old('year_id', '') }}',
            name: '{{ old('name', '') }}',
            semester: '{{ old('semester', '1') }}',
            start_date: '{{ old('start_date', '') }}',
            end_date: '{{ old('end_date', '') }}',
            is_active: {{ old('is_active') ? 'true' : 'false' }}
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
                <button type="button" @click="activeTab = 'scheduling'"
                    :class="activeTab === 'scheduling' ? 'btn-primary' : 'btn btn-ghost'"
                    style="padding: 8px 16px; border-radius: 6px; flex-shrink: 0; display: flex; align-items: center;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
                        style="margin-right: 6px;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    ช่วงจัดตาราง
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
                                    <input type="date" name="end_date" x-model="currentYear.end_date" required
                                        style="{{ $errors->has('end_date') ? 'border-color: var(--red, #dc2626);' : '' }}">
                                    @error('end_date')
                                        <span style="color: var(--red, #dc2626); font-size: 12px; margin-top: 4px; display: block;">{{ $message }}</span>
                                    @enderror
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
        <!-- Tab: ช่วงจัดตาราง -->
        <div x-show="activeTab === 'scheduling'" x-cloak>
            @if(session('success') && request('tab') === 'scheduling')
                <div style="background: oklch(95% 0.05 145); border: 1px solid oklch(70% 0.15 145); color: oklch(35% 0.12 145); padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 14px;">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error') && request('tab') === 'scheduling')
                <div style="background: oklch(95% 0.05 25); border: 1px solid oklch(70% 0.15 25); color: oklch(35% 0.12 25); padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 14px;">
                    {{ session('error') }}
                </div>
            @endif

            <div class="card">
                <div class="card-hdr">
                    <div class="card-ttl">สถานะช่วงจัดตาราง ตามปีการศึกษา</div>
                    <div style="font-size: 12px; color: var(--fg-3);">Admin เปิด/ปิด — หัวหน้าวิชาทุกท่านจัดตารางได้พร้อมกัน</div>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ปีการศึกษา</th>
                                <th>ภาคเรียน</th>
                                <th>สถานะระบบ</th>
                                <th style="text-align: center;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($schedulingSummary as $year)
                                <tr>
                                    <td style="font-weight: 600; color: var(--fg-1);">{{ $year->name }}</td>
                                    <td>ภาคเรียนที่ {{ $year->semester }}</td>
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
                                    <td style="text-align: center;">
                                        <div style="display: flex; gap: 6px; justify-content: center;">
                                            @if($year->phase === 'preparation')
                                                @if($year->is_active)
                                                    <form method="POST" action="{{ route('admin.settings.scheduling.open', $year) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="btn btn-primary" style="font-size: 13px; padding: 6px 14px;">
                                                            เปิดช่วงจัดตาราง
                                                        </button>
                                                    </form>
                                                @else
                                                    <button type="button" class="btn btn-ghost" disabled style="font-size: 13px; padding: 6px 14px; opacity: 0.4; cursor: not-allowed;" title="เปิดได้เฉพาะปีการศึกษาปัจจุบัน">
                                                        เปิดช่วงจัดตาราง
                                                    </button>
                                                @endif
                                            @elseif($year->phase === 'scheduling')
                                                <form method="POST" action="{{ route('admin.settings.scheduling.close', $year) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="btn btn-ghost" style="font-size: 13px; padding: 6px 14px; border: 1px solid var(--border);"
                                                        onclick="return confirm('ปิดช่วงจัดตารางสำหรับปีการศึกษา {{ $year->name }} ภาค {{ $year->semester }}?')">
                                                        ปิดช่วงจัดตาราง
                                                    </button>
                                                </form>
                                            @else
                                                <span style="font-size: 12px; color: var(--fg-3);">—</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 40px; color: var(--fg-3);">
                                        ยังไม่มีปีการศึกษา — เพิ่มในแท็บปีการศึกษาก่อน
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

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
