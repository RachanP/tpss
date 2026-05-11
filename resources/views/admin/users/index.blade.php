<x-app-layout title="จัดการผู้ใช้งาน">
    <div x-data='{ 
        showModal: false, 
        editMode: false,
        teachingTotalHours: {{ $systemSettings["teaching_quota_hours"] }},
        currentUser: {
            id: "",
            username: "",
            name: "",
            email: "",
            password: "",
            roles: [],
            primary_role: "",
            is_active: true
        },
        instructorProfile: {
            title: "",
            department_id: "",
            employment_type: "พนักงานมหาวิทยาลัย",
            hired_at: "",
            academic_degree: "ปริญญาโท",
            teaching_pct: 0,
            research_pct: 0,
            service_pct: 0,
            culture_pct: 0,
            other_pct: 0,
            teaching_quota: 0,
            employee_id: ""
        },
        paCriteria: {{ json_encode($paCriteria) }},
        get paRules() {
            const title = this.instructorProfile.title;
            const degree = this.instructorProfile.academic_degree;
            const hiredAt = this.instructorProfile.hired_at;
            const isNote1 = (title === "ผู้ช่วยอาจารย์" && degree === "ปริญญาเอก" && hiredAt && new Date(hiredAt) < new Date("2016-10-01"));
            
            if (title === "ผู้ช่วยอาจารย์ (คลินิก)") {
                return this.paCriteria["ผู้ช่วยอาจารย์_คลินิก"] || {};
            } else if (title === "ผู้ช่วยอาจารย์ (สอนภาคปฏิบัติ)") {
                return this.paCriteria["ผู้ช่วยอาจารย์_ปฏิบัติ"] || {};
            } else if (title === "ผู้ช่วยอาจารย์" && degree === "ปริญญาตรี") {
                return this.paCriteria["ผู้ช่วยอาจารย์_ปตรี"] || {};
            } else if (title === "ผู้ช่วยอาจารย์" || title === "อาจารย์" || title === "ผู้ช่วยศาสตราจารย์" || title === "รองศาสตราจารย์" || title === "ศาสตราจารย์" || isNote1) {
                if (isNote1 || title === "อาจารย์" || title === "ผู้ช่วยศาสตราจารย์" || title === "รองศาสตราจารย์" || title === "ศาสตราจารย์") {
                    return this.paCriteria["อาจารย์"] || {};
                }
                return this.paCriteria["ผู้ช่วยอาจารย์"] || {};
            }
            return { t: "-", r: "-", s: "-", c: "-", o: "-" };
        },
        get paTotal() {
            return (this.instructorProfile.teaching_pct || 0) + 
                   (this.instructorProfile.research_pct || 0) + 
                   (this.instructorProfile.service_pct || 0) + 
                   (this.instructorProfile.culture_pct || 0) + 
                   (this.instructorProfile.other_pct || 0);
        },
        get hasInstructor() {
            return this.currentUser.roles.includes("instructor");
        },
        updateQuota() {
            this.instructorProfile.teaching_quota = Math.round((this.teachingTotalHours * (this.instructorProfile.teaching_pct || 0)) / 100);
        },
        openAddModal() {
            this.editMode = false;
            this.currentUser = { id: "", username: "", name: "", email: "", password: "", roles: ["staff"], primary_role: "staff", is_active: true };
            this.instructorProfile = { title: "", employee_id: "", department_id: "", employment_type: "พนักงานมหาวิทยาลัย", hired_at: "", academic_degree: "ปริญญาโท", teaching_pct: 0, research_pct: 0, service_pct: 0, culture_pct: 0, other_pct: 0, teaching_quota: 0 };
            this.showModal = true;
        },
        openEditModal(user) {
            this.editMode = true;
            this.currentUser = { 
                id: user.id, 
                username: user.username, 
                name: user.name, 
                email: user.email, 
                password: "", 
                roles: user.roles ? user.roles.map(r => r.role) : [],
                primary_role: (user.roles && user.roles.find(r => r.is_primary)) ? user.roles.find(r => r.is_primary).role : (user.roles && user.roles[0] ? user.roles[0].role : ""),
                is_active: !!user.is_active
            };
            
            const profile = user.instructor_profile || user.instructorProfile || null;
            
            this.instructorProfile = profile ? {
                title: profile.title || "",
                employee_id: profile.employee_id || "",
                department_id: profile.department_id || "",
                employment_type: profile.employment_type || "พนักงานมหาวิทยาลัย",
                hired_at: profile.hired_at || "",
                academic_degree: profile.academic_degree || "ปริญญาโท",
                teaching_pct: profile.teaching_pct || 0,
                research_pct: profile.research_pct || 0,
                service_pct: profile.service_pct || 0,
                culture_pct: profile.culture_pct || 0,
                other_pct: profile.other_pct || 0,
                teaching_quota: profile.teaching_quota || 0
            } : { title: "", employee_id: "", department_id: "", employment_type: "พนักงานมหาวิทยาลัย", hired_at: "", academic_degree: "ปริญญาโท", teaching_pct: 0, research_pct: 0, service_pct: 0, culture_pct: 0, other_pct: 0, teaching_quota: 0 };
            
            this.showModal = true;
        }
    }' x-init='$watch("instructorProfile.teaching_pct", value => updateQuota())'>

        <!-- Header & Stats -->
        <div class="stats-grid">
            <div class="st-card">
                <div class="st-val">{{ $stats['total'] }}</div>
                <div class="st-lbl">ผู้ใช้งานทั้งหมด</div>
            </div>
            <div class="st-card">
                <div class="st-val" style="color: var(--status-success-fg)">{{ $stats['active'] }}</div>
                <div class="st-lbl">กำลังใช้งาน</div>
            </div>
            <div class="st-card">
                <div class="st-val" style="color: var(--status-conflict-fg)">{{ $stats['inactive'] }}</div>
                <div class="st-lbl">ระงับการใช้งาน</div>
            </div>
        </div>

        <!-- User Table Card -->
        <div class="card">
            <div class="card-hdr">
                <div class="card-ttl">รายชื่อผู้ใช้งานระบบ</div>
                <div class="card-actions">
                    <div class="search-box">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round">
                            <circle cx="11" cy="11" r="8" />
                            <line x1="21" y1="21" x2="16.65" y2="16.65" />
                        </svg>
                        <input type="text" placeholder="ค้นหาชื่อ หรือรหัส...">
                    </div>
                    <button class="btn btn-primary" @click="openAddModal()">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                            stroke-width="2.5" stroke-linecap="round">
                            <line x1="12" y1="5" x2="12" y2="19" />
                            <line x1="5" y1="12" x2="19" y2="12" />
                        </svg>
                        เพิ่มผู้ใช้
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ผู้ใช้งาน</th>
                            <th>บทบาท</th>
                            <th>อีเมล</th>
                            <th>สถานะ</th>
                            <th style="text-align: center;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $user)
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        @php
                                            $primaryRole = $user->roles->first()?->role ?? 'staff';
                                            $roleTheme = [
                                                'admin' => ['bg' => 'oklch(95% 0.02 240)', 'fg' => 'oklch(35% 0.10 240)', 'icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>'],
                                                'staff' => ['bg' => 'oklch(96% 0.02 200)', 'fg' => 'oklch(45% 0.10 200)', 'icon' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
                                                'course_head' => ['bg' => 'oklch(96% 0.04 80)', 'fg' => 'oklch(55% 0.12 80)', 'icon' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>'],
                                                'executive' => ['bg' => 'oklch(95% 0.04 290)', 'fg' => 'oklch(45% 0.15 290)', 'icon' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'],
                                                'instructor' => ['bg' => 'oklch(96% 0.05 150)', 'fg' => 'oklch(45% 0.15 150)', 'icon' => '<path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>'],
                                            ][$primaryRole] ?? ['bg' => '#f3f4f6', 'fg' => '#6b7280', 'icon' => ''];
                                        @endphp

                                        <div
                                            style="width: 38px; height: 38px; border-radius: 10px; background: {{ $roleTheme['bg'] }}; color: {{ $roleTheme['fg'] }}; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: inset 0 0 0 1px color-mix(in oklch, {{ $roleTheme['fg'] }} 15%, transparent);">
                                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none"
                                                stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                stroke-linejoin="round">
                                                {!! $roleTheme['icon'] !!}
                                            </svg>
                                        </div>
                                            <div style="font-weight: 600; color: var(--fg-1); line-height: 1.3;">
                                                {{ $user->name }}
                                            </div>
                                            <div
                                                style="font-size: 12px; color: var(--fg-3); font-family: var(--font-mono); margin-top: 1px;">
                                                {{ $user->username }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                                        @php
                                            $roleNames = [
                                                'admin' => 'ผู้ดูแลระบบ',
                                                'staff' => 'เจ้าหน้าที่',
                                                'course_head' => 'หัวหน้าวิชา',
                                                'executive' => 'ผู้บริหาร',
                                                'instructor' => 'อาจารย์ผู้สอน',
                                            ];
                                        @endphp
                                        @foreach($user->roles as $role)
                                            <span class="badge {{ $role->is_primary ? 'badge-primary' : 'badge-gray' }}"
                                                style="text-transform: uppercase; font-size: 10px;">
                                                {{ $roleNames[$role->role] ?? $role->role }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td style="color: var(--fg-2); font-size: 13px;">{{ $user->email }}</td>
                                <td>
                                    @if($user->is_active)
                                        <span class="badge badge-ok">
                                            <span
                                                style="width: 6px; height: 6px; border-radius: 50%; background: currentColor; margin-right: 6px;"></span>
                                            กำลังใช้งาน
                                        </span>
                                    @else
                                        <span class="badge badge-gray">ระงับการใช้งาน</span>
                                    @endif
                                </td>
                                <td style="text-align: center;">
                                    <div style="display: flex; gap: 6px; justify-content: center;">
                                        <button class="action-btn" title="แก้ไข"
                                            @click='openEditModal({!! json_encode($user->load("instructorProfile")) !!})'>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                            </svg>
                                        </button>
                                        <form action="{{ route('admin.users.destroy', $user) }}" method="POST"
                                            onsubmit="return confirm('ยืนยันการลบผู้ใช้?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="action-btn del" title="ลบ">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <polyline points="3 6 5 6 21 6" />
                                                    <path
                                                        d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <template x-if="showModal">
            <div class="overlay" x-cloak @click.self="showModal = false">
                <div class="modal-center" x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                    <div class="modal-hdr" style="background: var(--bg-2);">
                        <div class="modal-ttl" style="font-family: var(--font-display); letter-spacing: -0.01em;"
                            x-text="editMode ? 'แก้ไขข้อมูลผู้ใช้งาน' : 'เพิ่มผู้ใช้งานใหม่'"></div>
                        <button type="button" class="modal-cls" @click="showModal = false">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                                stroke-width="2.5" stroke-linecap="round">
                                <line x1="18" y1="6" x2="6" y2="18" />
                                <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                        </button>
                    </div>
                    <form
                        :action="editMode ? '{{ url('admin/users') }}/' + currentUser.id : '{{ route('admin.users.store') }}'"
                        method="POST">
                        @csrf
                        <template x-if="editMode">
                            <input type="hidden" name="_method" value="PUT">
                        </template>

                        <div class="modal-body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>รหัสเข้าระบบ</label>
                                    <input type="text" name="username" x-model="currentUser.username"
                                        :readonly="editMode"
                                        :style="editMode ? 'background: var(--bg-2); color: var(--fg-3)' : ''" required
                                        placeholder="เช่น staff_01">
                                </div>
                                <div class="form-group">
                                    <label
                                        x-text="editMode ? 'รหัสผ่านใหม่ (เว้นว่างไว้ถ้าไม่เปลี่ยน)' : 'รหัสผ่าน'"></label>
                                    <input type="password" name="password" :required="!editMode" placeholder="********">
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom: 16px;">
                                <label>ชื่อ-นามสกุล</label>
                                <input type="text" name="name" x-model="currentUser.name" required
                                    placeholder="ชื่อ นามสกุล">
                            </div>
                            <div class="form-group" style="margin-bottom: 16px;">
                                <label>อีเมล</label>
                                <input type="email" name="email" x-model="currentUser.email" required
                                    placeholder="email@mahidol.ac.th">
                            </div>

                            <div class="form-group" style="margin-bottom: 24px;">
                                <label
                                    style="margin-bottom: 12px; font-weight: 700; color: var(--fg-1); display: block; font-size: 14px;">บทบาทและสิทธิ์การใช้งาน
                                </label>
                                <div class="role-grid">
                                    @foreach(['admin' => 'ผู้ดูแลระบบ', 'staff' => 'เจ้าหน้าที่', 'course_head' => 'หัวหน้าวิชา', 'executive' => 'ผู้บริหาร', 'instructor' => 'อาจารย์ผู้สอน'] as $val => $label)
                                        @php
                                            $icon = [
                                                'admin' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
                                                'staff' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
                                                'course_head' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
                                                'executive' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
                                                'instructor' => '<path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>',
                                            ][$val];
                                        @endphp
                                        <div class="role-card"
                                            :class="{ 'is-selected': currentUser.roles.includes('{{ $val }}'), 'is-primary': currentUser.primary_role === '{{ $val }}' }"
                                            @click='if(currentUser.roles.includes("{{ $val }}")) { 
                                                                             if(currentUser.roles.length > 1 || currentUser.primary_role !== "{{ $val }}") {
                                                                                 currentUser.roles = currentUser.roles.filter(r => r !== "{{ $val }}"); 
                                                                                 if(currentUser.primary_role === "{{ $val }}") currentUser.primary_role = currentUser.roles[0] || ""; 
                                                                             }
                                                                          } else { 
                                                                             currentUser.roles.push("{{ $val }}"); 
                                                                             if(!currentUser.primary_role) currentUser.primary_role = "{{ $val }}"; 
                                                                          }'>

                                            <div class="role-check">
                                                <svg x-show='currentUser.roles.includes("{{ $val }}")' viewBox="0 0 24 24"
                                                    width="14" height="14" fill="none" stroke="currentColor"
                                                    stroke-width="4" stroke-linecap="round" stroke-linejoin="round">
                                                    <polyline points="20 6 9 17 4 12" />
                                                </svg>
                                            </div>

                                            <div class="role-icon-box">
                                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none"
                                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                    stroke-linejoin="round">
                                                    {!! $icon !!}
                                                </svg>
                                            </div>

                                            <div class="role-info">
                                                <div class="role-name">{{ $label }}</div>
                                            </div>

                                            <template x-if='currentUser.roles.includes("{{ $val }}")'>
                                                <div class="role-actions">
                                                    <button type="button" class="btn-primary-role"
                                                        :class="{ 'active': currentUser.primary_role === '{{ $val }}' }"
                                                        @click.stop='currentUser.primary_role = "{{ $val }}"'>
                                                        <span
                                                            x-text='currentUser.primary_role === "{{ $val }}" ? "บทบาทหลัก" : "ตั้งเป็นบทบาทหลัก"'></span>
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                    @endforeach
                                </div>

                                <input type="hidden" name="primary_role" :value="currentUser.primary_role">
                                <template x-for="role in currentUser.roles">
                                    <input type="hidden" name="roles[]" :value="role">
                                </template>
                            </div>

                            <div class="form-group">
                                <label>สถานะการใช้งาน</label>
                                <select name="is_active" x-model="currentUser.is_active">
                                    <option :value="1">ใช้งานปกติ (Active)</option>
                                    <option :value="0">ระงับการใช้งาน (Inactive)</option>
                                </select>
                            </div>

                            <!-- Instructor Profile Section (shown when instructor role is selected) -->
                            <template x-if="hasInstructor">
                                <div style="margin-top: 20px; border-top: 1px solid var(--border); padding-top: 20px;">
                                    <div style="font-weight: 700; color: var(--fg-1); font-size: 14px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none"
                                            stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <path d="M22 10v6M2 10l10-5 10 5-10 5z" />
                                            <path d="M6 12v5c3 3 9 3 12 0v-5" />
                                        </svg>
                                        ข้อมูลโปรไฟล์อาจารย์ผู้สอน
                                    </div>

                                    <!-- รหัสพนักงาน / อาจารย์ -->
                                    <div class="form-group" style="margin-bottom: 20px;">
                                        <label style="font-size: 13px; font-weight: 600; color: var(--fg-2);">รหัสพนักงาน / รหัสอาจารย์</label>
                                        <input type="text" name="instructor_employee_id" x-model="instructorProfile.employee_id" 
                                            placeholder="กรอกรหัสพนักงาน เช่น 600xxx"
                                            style="background: oklch(98% 0.005 240); border: 1.5px solid var(--bg-3);">
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>ตำแหน่งทางวิชาการ</label>
                                            <select name="instructor_title" x-model="instructorProfile.title">
                                                <option value="">-- เลือกตำแหน่ง --</option>
                                                <option value="อาจารย์">อาจารย์ (อ.)</option>
                                                <option value="ผู้ช่วยศาสตราจารย์">ผู้ช่วยศาสตราจารย์ (ผศ.)</option>
                                                <option value="รองศาสตราจารย์">รองศาสตราจารย์ (รศ.)</option>
                                                <option value="ศาสตราจารย์">ศาสตราจารย์ (ศ.)</option>
                                                <option disabled>──────────</option>
                                                <option value="ผู้ช่วยอาจารย์">ผู้ช่วยอาจารย์</option>
                                                <option value="ผู้ช่วยอาจารย์ (คลินิก)">ผู้ช่วยอาจารย์ (คลินิก)</option>
                                                <option value="ผู้ช่วยอาจารย์ (สอนภาคปฏิบัติ)">ผู้ช่วยอาจารย์ (สอนภาคปฏิบัติ)</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>ภาควิชา / หน่วยงาน</label>
                                            <select name="instructor_department_id"
                                                x-model="instructorProfile.department_id">
                                                <option value="">-- เลือกภาควิชา --</option>
                                                @foreach($departments as $dept)
                                                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div style="margin-top: 16px; margin-bottom: 24px;">
                                        <div style="font-weight: 700; color: var(--fg-1); font-size: 13px; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color: var(--fg-3);">
                                                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                                            </svg>
                                            การจ้างงาน (Employment)
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label>ประเภทการจ้างงาน</label>
                                                <select name="instructor_employment_type" x-model="instructorProfile.employment_type">
                                                    <option value="พนักงานมหาวิทยาลัย">พนักงานมหาวิทยาลัย</option>
                                                    <option value="ข้าราชการ">ข้าราชการ</option>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label>วันที่บรรจุเข้าทำงาน</label>
                                                <input type="date" name="instructor_hired_at" x-model="instructorProfile.hired_at">
                                            </div>
                                        </div>
                                        <div class="form-group" style="margin-top: 12px;" x-show='instructorProfile.title === "ผู้ช่วยอาจารย์"'>
                                            <label style="color: var(--status-success-fg); font-weight: 700;">วุฒิการศึกษาสูงสุด *</label>
                                            <select name="instructor_academic_degree" x-model="instructorProfile.academic_degree">
                                                <option value="ปริญญาเอก">ปริญญาเอก</option>
                                                <option value="ปริญญาโท">ปริญญาโท</option>
                                                <option value="ปริญญาตรี">ปริญญาตรี</option>
                                            </select>
                                            <div style="font-size: 11px; color: var(--fg-3); margin-top: 4px;" x-show='instructorProfile.academic_degree === "ปริญญาเอก" && instructorProfile.hired_at && new Date(instructorProfile.hired_at) < new Date("2016-10-01")'>
                                                ✨ เข้าเงื่อนไขหมายเหตุ 1: บรรจุก่อน 2559 และจบ ป.เอก ให้ใช้เกณฑ์ "อาจารย์"
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        style="background: var(--bg-1); border-radius: 12px; padding: 20px; border: 1px solid var(--border); margin-top: 8px;">
                                        <div
                                            style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                                            <div>
                                                <div style="font-weight: 700; color: var(--fg-1); font-size: 14px;">
                                                    สัดส่วนภาระงาน</div>
                                            </div>
                                            <div style="text-align: right;">
                                                <div style="font-size: 28px; font-weight: 800; line-height: 1;"
                                                    :style='paTotal === 100 ? "color: var(--status-success-fg)" : "color: var(--status-conflict-fg)"'>
                                                    <span x-text="paTotal"></span>%
                                                </div>
                                            </div>
                                        </div>

                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                            <div class="form-group">
                                                <label style="font-size: 12px; color: var(--fg-2);">
                                                    1. ด้านการสอน (<span x-text="paRules.t"></span>)
                                                </label>
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <input type="number" name="instructor_teaching_pct" x-model.number="instructorProfile.teaching_pct" min="0" max="80" style="font-weight: 700;">
                                                    <span style="font-size: 13px; color: var(--fg-3); width: 20px;">%</span>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label style="font-size: 12px; color: var(--fg-2);">
                                                    2. ด้านวิจัย (<span x-text="paRules.r"></span>)
                                                </label>
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <input type="number" name="instructor_research_pct" x-model.number="instructorProfile.research_pct" min="0" max="80" style="font-weight: 700;">
                                                    <span style="font-size: 13px; color: var(--fg-3); width: 20px;">%</span>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label style="font-size: 12px; color: var(--fg-2);">
                                                    3. บริการวิชาการ (<span x-text="paRules.s"></span>)
                                                </label>
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <input type="number" name="instructor_service_pct" x-model.number="instructorProfile.service_pct" min="0" max="80" style="font-weight: 700;">
                                                    <span style="font-size: 13px; color: var(--fg-3); width: 20px;">%</span>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label style="font-size: 12px; color: var(--fg-2);">
                                                    4. ศิลปวัฒนธรรม (<span x-text="paRules.c"></span>)
                                                </label>
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <input type="number" name="instructor_culture_pct" x-model.number="instructorProfile.culture_pct" min="0" max="80" style="font-weight: 700;">
                                                    <span style="font-size: 13px; color: var(--fg-3); width: 20px;">%</span>
                                                </div>
                                            </div>
                                            <div class="form-group" style="grid-column: span 2;">
                                                <label style="font-size: 12px; color: var(--fg-2);">
                                                    5. งานอื่นๆ มอบหมาย (<span x-text="paRules.o"></span>)
                                                </label>
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <input type="number" name="instructor_other_pct" x-model.number="instructorProfile.other_pct" min="0" max="80" style="font-weight: 700;">
                                                    <span style="font-size: 13px; color: var(--fg-3); width: 20px;">%</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Hidden field for background calculation -->
                                        <input type="hidden" name="instructor_teaching_quota"
                                            :value="instructorProfile.teaching_quota">
                                    </div>
                                </div>
                            </template>
                        </div>
                        <div class="modal-foot">
                            <button type="button" class="btn btn-ghost" @click="showModal = false">ยกเลิก</button>
                            <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </div>
</x-app-layout>