<x-app-layout title="ข้อมูลหลักระบบ (Master Data)">
    <div x-data="{ 
        activeTab: 'instructors',
        searchQuery: '',
        showDeptModal: false,
        editDeptMode: false,
        currentDept: {
            id: '',
            name: '',
            head_user_id: '',
            secretary_user_id: ''
        },
        openAddDept() {
            this.editDeptMode = false;
            this.currentDept = { id: '', name: '', head_user_id: '', secretary_user_id: '' };
            this.headSearch = '';
            this.secretarySearch = '';
            this.showDeptModal = true;
        },
        openEditDept(dept) {
            this.editDeptMode = true;
            this.currentDept = { 
                id: dept.id, 
                name: dept.name, 
                head_user_id: dept.head_user_id || '', 
                secretary_user_id: dept.secretary_user_id || '' 
            };
            this.headSearch = dept.head ? dept.head.formatted_name : '';
            this.secretarySearch = dept.secretary ? dept.secretary.formatted_name : '';
            this.showDeptModal = true;
        },
        showInstructorModal: false,
        currentInstructor: {
            id: '',
            prefix: '',
            name: '',
            employee_id: '',
            title: '',
            academic_degree: '',
            department_id: '',
            employment_type: '',
            teaching_pct: 0
        },
        openEditInstructor(instructor) {
            this.currentInstructor = {
                id: instructor.id,
                prefix: instructor.prefix || '',
                name: instructor.name,
                employee_id: instructor.instructor_profile?.employee_id || '',
                title: instructor.instructor_profile?.title || '',
                academic_degree: instructor.instructor_profile?.academic_degree || '',
                department_id: instructor.instructor_profile?.department_id || '',
                employment_type: instructor.instructor_profile?.employment_type || '',
                teaching_pct: instructor.instructor_profile?.teaching_pct || 0
            };
            this.showInstructorModal = true;
        },
        headSearch: '',
        secretarySearch: '',
        showHeadDropdown: false,
        showSecretaryDropdown: false,
        selectHead(user) {
            this.currentDept.head_user_id = user.id;
            this.headSearch = user.name;
            this.showHeadDropdown = false;
        },
        selectSecretary(user) {
            this.currentDept.secretary_user_id = user.id;
            this.secretarySearch = user.name;
            this.showSecretaryDropdown = false;
        },
        usersList: {{ Js::from($users->map(fn($u) => ['id' => $u->id, 'name' => $u->formatted_name])) }}
    }">

        <!-- Header -->
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px;">
            <div>
                <h1 style="font-size: 24px; font-weight: 700; color: var(--fg-1); margin-bottom: 4px; font-family: var(--font-display);">ข้อมูลหลัก (Master Data)</h1>
                <p style="color: var(--fg-3); font-size: 14px;">จัดการข้อมูลภาควิชา และดูรายชื่ออาจารย์ผู้สอน</p>
            </div>
            <div class="tabs" style="display: flex; gap: 8px; background: var(--bg-2); padding: 4px; border-radius: 8px; border: 1px solid var(--border);">
                <button type="button" @click="activeTab = 'instructors'" :class="activeTab === 'instructors' ? 'btn-primary' : 'btn btn-ghost'" style="padding: 8px 16px; border-radius: 6px;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    อาจารย์ผู้สอน
                </button>
                <button type="button" @click="activeTab = 'departments'" :class="activeTab === 'departments' ? 'btn-primary' : 'btn btn-ghost'" style="padding: 8px 16px; border-radius: 6px;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                    ภาควิชา
                </button>
            </div>
        </div>

        <!-- Tab: Instructors -->
        <div x-show="activeTab === 'instructors'" x-cloak>
            <div class="card">
                <div class="card-hdr">
                    <div class="card-ttl">ข้อมูลอาจารย์ผู้สอน</div>
                    <div class="card-actions">
                        <div class="search-box">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                <circle cx="11" cy="11" r="8" />
                                <line x1="21" y1="21" x2="16.65" y2="16.65" />
                            </svg>
                            <input type="text" x-model="searchQuery" placeholder="ค้นหาชื่ออาจารย์...">
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>รหัส</th>
                                <th>ชื่อ-นามสกุล</th>
                                <th>ตำแหน่งทางวิชาการ</th>
                                <th>ภาควิชา</th>
                                <th>ประเภทบุคลากร</th>
                                <th style="text-align: right; padding-right: 24px;">เกณฑ์ภาระงานสอน</th>
                                <th style="text-align: center;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($instructors as $instructor)
                                <tr x-show="!searchQuery || '{{ $instructor->formatted_name }}'.toLowerCase().includes(searchQuery.toLowerCase())">
                                    <td style="font-weight: 600; color: var(--fg-2);">
                                        {{ $instructor->instructorProfile->employee_id ?? '-' }}
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--fg-1);">
                                            {{ $instructor->formatted_name }}
                                        </div>
                                        <div style="font-size: 12px; color: var(--fg-3); font-family: var(--font-mono);">
                                            {{ $instructor->email }}
                                        </div>
                                    </td>
                                    <td style="color: var(--fg-2); font-size: 13px;">
                                        {{ $instructor->instructorProfile->title ?? '-' }}
                                        @if($instructor->instructorProfile && $instructor->instructorProfile->academic_degree)
                                            <span style="color: var(--fg-3);">({{ $instructor->instructorProfile->academic_degree }})</span>
                                        @endif
                                    </td>
                                    <td style="color: var(--fg-2); font-size: 13px;">
                                        {{ $instructor->instructorProfile->department->name ?? '-' }}
                                    </td>
                                    <td style="color: var(--fg-2); font-size: 13px;">
                                        {{ $instructor->instructorProfile->employment_type ?? '-' }}
                                    </td>
                                    <td style="text-align: right; padding-right: 24px;">
                                        @if($instructor->instructorProfile && $instructor->instructorProfile->teaching_pct)
                                            @php
                                                $isGov = ($instructor->instructorProfile->employment_type === 'ข้าราชการ');
                                                $teachingWeeks = \App\Models\SystemSetting::get('teaching_load_weeks', 39);
                                                $hoursPerWeek = \App\Models\SystemSetting::get('teaching_quota_hours_per_week', 35);
                                                $base = $isGov ? ($teachingWeeks * $hoursPerWeek / 2) : ($teachingWeeks * $hoursPerWeek);
                                                $period = $isGov ? '6 เดือน' : 'ปี';
                                                $quota = ($base * $instructor->instructorProfile->teaching_pct) / 100;
                                            @endphp
                                            <div style="font-weight: 700; color: var(--brand-navy); font-size: 14px;">
                                                {{ number_format($quota, 1) }}
                                            </div>
                                            <div style="font-size: 11px; color: var(--fg-3);">ชม.ทำการ / {{ $period }}</div>
                                        @else
                                            <span style="color: var(--fg-3); font-style: italic;">- ไม่ระบุ -</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($instructor->is_active)
                                            <span class="badge badge-primary">
                                                <span style="width: 6px; height: 6px; border-radius: 50%; background: currentColor; margin-right: 6px;"></span>
                                                ใช้งาน
                                            </span>
                                        @else
                                            <span class="badge badge-gray">ระงับ</span>
                                        @endif
                                    </td>
                                    <td style="text-align: center;">
                                        <button type="button" class="action-btn" title="แก้ไขข้อมูลอาจารย์" 
                                            @click="openEditInstructor({{ Js::from($instructor) }})">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" /><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" /></svg>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                            <tr x-show="searchQuery && !Array.from($el.parentNode.children).some(tr => tr.style.display !== 'none' && tr !== $el)">
                                <td colspan="8" style="text-align: center; padding: 40px; color: var(--fg-3);">ไม่พบข้อมูลอาจารย์ที่ค้นหา</td>
                            </tr>
                            @if($instructors->isEmpty())
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px; color: var(--fg-3);">ยังไม่มีข้อมูลอาจารย์ผู้สอน</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab: Departments -->
        <div x-show="activeTab === 'departments'" x-cloak>
            <div class="card">
                <div class="card-hdr">
                    <div class="card-ttl">ภาควิชา (Departments)</div>
                    <div class="card-actions">
                        <button class="btn btn-primary" @click="openAddDept()">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                <line x1="12" y1="5" x2="12" y2="19" />
                                <line x1="5" y1="12" x2="19" y2="12" />
                            </svg>
                            เพิ่มภาควิชา
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ชื่อภาควิชา</th>
                                <th>หัวหน้าภาควิชา</th>
                                <th>เลขานุการภาควิชา</th>
                                <th style="text-align: center;">จำนวนอาจารย์</th>
                                <th style="text-align: center;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($departments as $dept)
                                <tr>
                                    <td style="font-weight: 600; color: var(--fg-1);">{{ $dept->name }}</td>
                                    <td style="color: var(--fg-2); font-size: 13px;">{{ $dept->head->name ?? '-' }}</td>
                                    <td style="color: var(--fg-2); font-size: 13px;">{{ $dept->secretary->name ?? '-' }}</td>
                                    <td style="text-align: center; font-weight: 600; color: var(--brand-navy);">
                                        {{ $dept->instructors_count }} คน
                                    </td>
                                    <td style="text-align: center;">
                                        <button class="action-btn" title="แก้ไข" @click="openEditDept({{ Js::from($dept) }})">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" /><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" /></svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 40px; color: var(--fg-3);">ยังไม่มีข้อมูลภาควิชา</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add/Edit Modal (Department) -->
        <template x-if="showDeptModal">
            <div class="overlay" x-cloak @click.self="showDeptModal = false">
                <div class="modal-center" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                    <div class="modal-hdr" style="background: var(--bg-2);">
                        <div class="modal-ttl" style="font-family: var(--font-display);" x-text="editDeptMode ? 'แก้ไขภาควิชา' : 'เพิ่มภาควิชาใหม่'"></div>
                        <button type="button" class="modal-cls" @click="showDeptModal = false">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg>
                        </button>
                    </div>
                    <form :action="editDeptMode ? '{{ url('admin/master-data/departments') }}/' + currentDept.id : '{{ route('admin.departments.store') }}'" method="POST">
                        @csrf
                        <template x-if="editDeptMode">
                            <input type="hidden" name="_method" value="PUT">
                        </template>
                        <div class="modal-body">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label>ชื่อภาควิชา <span style="color: var(--status-conflict-fg)">*</span></label>
                                <input type="text" name="name" x-model="currentDept.name" required placeholder="เช่น ภาควิชาการพยาบาลกุมารเวชศาสตร์">
                            </div>
                            <div class="form-row">
                                <div class="form-group" style="position: relative;">
                                    <label>หัวหน้าภาควิชา</label>
                                    <div style="position: relative;">
                                        <input type="text" x-model="headSearch" 
                                            @input="showHeadDropdown = true"
                                            @focus="showHeadDropdown = true"
                                            @click.away="showHeadDropdown = false"
                                            placeholder="พิมพ์ชื่อเพื่อค้นหา..."
                                            autocomplete="off">
                                        <div class="search-results" x-show="showHeadDropdown && headSearch" x-cloak>
                                            <template x-for="user in usersList.filter(u => u.name.toLowerCase().includes(headSearch.toLowerCase()))" :key="user.id">
                                                <div class="search-item" @click="selectHead(user)" x-text="user.name"></div>
                                            </template>
                                            <div x-show="usersList.filter(u => u.name.toLowerCase().includes(headSearch.toLowerCase())).length === 0" class="search-item-empty">ไม่พบข้อมูล</div>
                                        </div>
                                    </div>
                                    <input type="hidden" name="head_user_id" x-model="currentDept.head_user_id">
                                </div>
                                <div class="form-group" style="position: relative;">
                                    <label>เลขานุการภาควิชา</label>
                                    <div style="position: relative;">
                                        <input type="text" x-model="secretarySearch" 
                                            @input="showSecretaryDropdown = true"
                                            @focus="showSecretaryDropdown = true"
                                            @click.away="showSecretaryDropdown = false"
                                            placeholder="พิมพ์ชื่อเพื่อค้นหา..."
                                            autocomplete="off">
                                        <div class="search-results" x-show="showSecretaryDropdown && secretarySearch" x-cloak>
                                            <template x-for="user in usersList.filter(u => u.name.toLowerCase().includes(secretarySearch.toLowerCase()))" :key="user.id">
                                                <div class="search-item" @click="selectSecretary(user)" x-text="user.name"></div>
                                            </template>
                                            <div x-show="usersList.filter(u => u.name.toLowerCase().includes(secretarySearch.toLowerCase())).length === 0" class="search-item-empty">ไม่พบข้อมูล</div>
                                        </div>
                                    </div>
                                    <input type="hidden" name="secretary_user_id" x-model="currentDept.secretary_user_id">
                                </div>
                            </div>
                        </div>
                        <div class="modal-foot">
                            <button type="button" class="btn btn-ghost" @click="showDeptModal = false">ยกเลิก</button>
                            <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
        
        <!-- Edit Instructor Modal -->
        <template x-if="showInstructorModal">
            <div class="overlay" x-cloak @click.self="showInstructorModal = false">
                <div class="modal-center" style="max-width: 650px;" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                    <div class="modal-hdr" style="background: var(--bg-2);">
                        <div class="modal-ttl" style="font-family: var(--font-display);">แก้ไขข้อมูลอาจารย์ผู้สอน</div>
                        <button type="button" class="modal-cls" @click="showInstructorModal = false">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg>
                        </button>
                    </div>
                    <form :action="'{{ url('admin/master-data/instructors') }}/' + currentInstructor.id" method="POST">
                        @csrf
                        <input type="hidden" name="_method" value="PUT">
                        <div class="modal-body">
                            <div style="display: grid; grid-template-columns: 140px 1fr; gap: 20px; margin-bottom: 20px;">
                                <div class="form-group">
                                    <label>คำนำหน้า</label>
                                    <select name="prefix" x-model="currentInstructor.prefix">
                                        <option value="นาย">นาย</option>
                                        <option value="นาง">นาง</option>
                                        <option value="นางสาว">นางสาว</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>ชื่อ-นามสกุล</label>
                                    <input type="text" :value="currentInstructor.name" disabled style="background: var(--bg-2); cursor: not-allowed;">
                                    <p style="font-size: 11px; color: var(--fg-3); margin-top: 4px;">* ชื่อ-นามสกุล แก้ไขได้ที่หน้าจัดการผู้ใช้งานเท่านั้น</p>
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                <div class="form-group">
                                    <label>รหัสพนักงาน</label>
                                    <input type="text" name="employee_id" x-model="currentInstructor.employee_id" placeholder="รหัสพนักงาน 6 หลัก">
                                </div>
                                <div class="form-group">
                                    <label>ภาควิชา</label>
                                    <select name="department_id" x-model="currentInstructor.department_id">
                                        <option value="">-- ไม่ระบุ --</option>
                                        @foreach($departments as $dept)
                                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                <div class="form-group">
                                    <label>ตำแหน่งทางวิชาการ</label>
                                    <select name="title" x-model="currentInstructor.title">
                                        <option value="อาจารย์">อาจารย์</option>
                                        <option value="ผู้ช่วยศาสตราจารย์">ผู้ช่วยศาสตราจารย์</option>
                                        <option value="รองศาสตราจารย์">รองศาสตราจารย์</option>
                                        <option value="ศาสตราจารย์">ศาสตราจารย์</option>
                                        <option value="ผู้ช่วยอาจารย์">ผู้ช่วยอาจารย์</option>
                                        <option value="ผู้ช่วยอาจารย์ (คลินิก)">ผู้ช่วยอาจารย์ (คลินิก)</option>
                                        <option value="ผู้ช่วยอาจารย์ (สอนภาคปฏิบัติ)">ผู้ช่วยอาจารย์ (สอนภาคปฏิบัติ)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>วุฒิการศึกษา</label>
                                    <select name="academic_degree" x-model="currentInstructor.academic_degree">
                                        <option value="ปริญญาเอก">ปริญญาเอก</option>
                                        <option value="ปริญญาโท">ปริญญาโท</option>
                                        <option value="ปริญญาตรี">ปริญญาตรี</option>
                                    </select>
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label>ประเภทบุคลากร</label>
                                    <select name="employment_type" x-model="currentInstructor.employment_type">
                                        <option value="พนักงานมหาวิทยาลัย">พนักงานมหาวิทยาลัย</option>
                                        <option value="ข้าราชการ">ข้าราชการ</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>สัดส่วนงานสอน (%)</label>
                                    <input type="number" name="teaching_pct" x-model.number="currentInstructor.teaching_pct" min="0" max="100">
                                </div>
                            </div>
                        </div>
                        <div class="modal-foot">
                            <button type="button" class="btn btn-ghost" @click="showInstructorModal = false">ยกเลิก</button>
                            <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>

    </div>

    <style>
        [x-cloak] { display: none !important; }
        .btn-ghost:hover {
            background: var(--bg-3);
        }
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-md);
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            max-height: 200px;
            overflow-y: auto;
            margin-top: 4px;
        }
        .search-item {
            padding: 10px 12px;
            cursor: pointer;
            font-size: var(--fs-md);
            transition: background 0.1s;
        }
        .search-item:hover {
            background: var(--bg-2);
            color: var(--brand-navy);
        }
        .search-item-empty {
            padding: 10px 12px;
            color: var(--fg-3);
            font-size: var(--fs-md);
            font-style: italic;
        }
    </style>
</x-app-layout>