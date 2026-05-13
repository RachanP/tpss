<x-app-layout title="ข้อมูลหลักระบบ (Master Data)">
    <div x-data="{
        activeTab: new URLSearchParams(window.location.search).get('tab') || 'instructors',
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
        departmentsData: {{ Js::from($departments->map(fn($d) => ['id' => $d->id, 'name' => $d->name, 'head_user_id' => $d->head_user_id, 'secretary_user_id' => $d->secretary_user_id])) }},
        headSearch: '',
        secretarySearch: '',
        showHeadDropdown: false,
        showSecretaryDropdown: false,
        selectHead(user) {
            this.currentDept.head_user_id = user.id;
            this.headSearch = user.name;
            this.showHeadDropdown = false;
        },
        clearHead() {
            this.currentDept.head_user_id = '';
            this.headSearch = '';
        },
        selectSecretary(user) {
            this.currentDept.secretary_user_id = user.id;
            this.secretarySearch = user.name;
            this.showSecretaryDropdown = false;
        },
        clearSecretary() {
            this.currentDept.secretary_user_id = '';
            this.secretarySearch = '';
        },

        // Location Types
        showLocTypeModal: false,
        editLocTypeMode: false,
        currentLocType: { id: '', name: '' },
        openAddLocType() {
            this.editLocTypeMode = false;
            this.currentLocType = { id: '', name: '' };
            this.showLocTypeModal = true;
        },
        openEditLocType(type) {
            this.editLocTypeMode = true;
            this.currentLocType = { id: type.id, name: type.name };
            this.showLocTypeModal = true;
        },

        // Rooms
        showRoomModal: false,
        showImportRoomModal: false,
        showImportCourseModal: false,
        editRoomMode: false,
        currentRoom: {
            id: '',
            room_code: '',
            room_name: '',
            building: '',
            capacity: '',
            location_type_id: '',
            status: 'active',
            address: '',
            equipment_type: ''
        },
        openAddRoom() {
            this.editRoomMode = false;
            this.currentRoom = { id: '', room_code: '', room_name: '', building: '', capacity: '', location_type_id: '', status: 'active', address: '', equipment_type: '' };
            this.showRoomModal = true;
        },
        openEditRoom(room) {
            this.editRoomMode = true;
            this.currentRoom = {
                id: room.id,
                room_code: room.room_code,
                room_name: room.room_name,
                building: room.building || '',
                capacity: room.capacity,
                location_type_id: room.location_type_id,
                status: room.status,
                address: room.address || '',
                equipment_type: Array.isArray(room.equipment_type) ? room.equipment_type.join(', ') : ''
            };
            this.showRoomModal = true;
        },

        // Courses
        showCourseModal: false,
        editCourseMode: false,
        currentCourse: {
            id: '',
            course_code: '',
            name_th: '',
            name_en: '',
            curriculum_id: '',
            department_id: '',
            head_instructor_id: '',
            assigned_staff_id: '',
            course_type: 'theory',
            academic_level: 'undergraduate',
            default_year_level: '',
            default_semester: '',
            credits: '',
            lecture_hours: 0,
            lab_hours: 0,
            self_study_hours: 0,
            capacity: '',
            color_code: '#3b82f6',
            status: 'active',
            requires_practicum_rotation: false
        },
        courseHeadSearch: '',
        showCourseHeadDropdown: false,
        openAddCourse() {
            this.editCourseMode = false;
            this.currentCourse = { id: '', course_code: '', name_th: '', name_en: '', curriculum_id: '', department_id: '', head_instructor_id: '', assigned_staff_id: '', course_type: 'theory', academic_level: 'undergraduate', default_year_level: '', default_semester: '', credits: '', lecture_hours: 0, lab_hours: 0, self_study_hours: 0, capacity: '', color_code: '#3b82f6', status: 'active', requires_practicum_rotation: false };
            this.courseHeadSearch = '';
            this.showCourseModal = true;
        },
        openEditCourse(course) {
            this.editCourseMode = true;
            this.currentCourse = { ...course };
            this.courseHeadSearch = course.head_instructor ? course.head_instructor.formatted_name : '';
            this.showCourseModal = true;
        },
        selectCourseHead(user) {
            this.currentCourse.head_instructor_id = user.id;
            this.courseHeadSearch = user.name;
            this.showCourseHeadDropdown = false;
        },

        // Curriculums
        showCurriculumModal: false,
        editCurriculumMode: false,
        currentCurriculum: { id: '', name: '', effective_year: '', is_active: true },
        openAddCurriculum() {
            this.editCurriculumMode = false;
            this.currentCurriculum = { id: '', name: '', effective_year: '', is_active: true };
            this.showCurriculumModal = true;
        },
        openEditCurriculum(curr) {
            this.editCurriculumMode = true;
            this.currentCurriculum = { ...curr };
            this.showCurriculumModal = true;
        },

        showCloneCurriculumModal: false,
        cloneSourceCurriculum: null,
        cloneNewName: '',
        cloneNewYear: '',
        openCloneCurriculum(curr) {
            this.cloneSourceCurriculum = curr;
            this.cloneNewName = curr.name + ' (ฉบับปรับปรุง)';
            this.cloneNewYear = parseInt(curr.effective_year) + 5;
            this.showCloneCurriculumModal = true;
        },

        usersList: {{ Js::from($users->map(fn($u) => ['id' => $u->id, 'name' => $u->formatted_name])) }},

        confirmDeptSave(e) {
            var form     = e.target;
            var headId   = String(this.currentDept.head_user_id || '');
            var secId    = String(this.currentDept.secretary_user_id || '');
            var deptId   = String(this.currentDept.id || '');

            var headConflict = headId ? this.departmentsData.find(
                function(d) { return String(d.head_user_id) === headId && String(d.id) !== deptId; }
            ) : null;
            var secConflict = secId ? this.departmentsData.find(
                function(d) { return String(d.secretary_user_id) === secId && String(d.id) !== deptId; }
            ) : null;

            if (!headConflict && !secConflict) return;
            e.preventDefault();

            var lines = [];
            if (headConflict) {
                var hName = (this.usersList.find(function(u) { return String(u.id) === headId; }) || {}).name || 'บุคคลนี้';
                lines.push(hName + ' เป็นหัวหน้าภาควิชา ' + headConflict.name + ' อยู่แล้ว');
            }
            if (secConflict) {
                var sName = (this.usersList.find(function(u) { return String(u.id) === secId; }) || {}).name || 'บุคคลนี้';
                lines.push(sName + ' เป็นเลขานุการภาควิชา ' + secConflict.name + ' อยู่แล้ว');
            }
            tpssDeptConflictWarn(form, lines);
        },
        getInstructorTeachingRule() {
            const title  = this.currentInstructor.title;
            const degree = this.currentInstructor.academic_degree;
            if (title === 'ผู้ช่วยอาจารย์ (คลินิก)')       return { label: '≤ 10%',   max: 10,  min: 0  };
            if (title === 'ผู้ช่วยอาจารย์ (สอนภาคปฏิบัติ)') return { label: '≤ 70%',   max: 70,  min: 0  };
            if (title === 'ผู้ช่วยอาจารย์' && degree === 'ปริญญาตรี') return { label: '30–60%', max: 60,  min: 30 };
            if (title === 'ผู้ช่วยอาจารย์')                 return { label: '≤ 70%',   max: 70,  min: 0  };
            return { label: '20–70%', max: 70, min: 20 };
        },
        confirmInstructorSave(e) {
            const rule = this.getInstructorTeachingRule();
            const pct  = parseInt(this.currentInstructor.teaching_pct) || 0;
            if (pct < rule.min || pct > rule.max) {
                e.preventDefault();
                tpssToast(
                    'สัดส่วนงานสอน ' + pct + '% ไม่อยู่ในเกณฑ์ที่กำหนด ('
                    + rule.label + ') สำหรับตำแหน่ง ' + (this.currentInstructor.title || 'ที่เลือก'),
                    'error'
                );
                return;
            }
        },
        // Activity Types
        showActivityTypeModal: false,
        editActivityTypeMode: false,
        currentActivityType: { id: '', name: '', color_code: '#3498db', category: 'lecture' },
        openAddActivityType() {
            this.editActivityTypeMode = false;
            this.currentActivityType = { id: '', name: '', color_code: '#3498db', category: 'lecture' };
            this.showActivityTypeModal = true;
        },
        openEditActivityType(at) {
            this.editActivityTypeMode = true;
            this.currentActivityType = { ...at };
            this.showActivityTypeModal = true;
        },

        confirmDelete(formId, itemLabel, warnText) {
            window.tpssConfirmDelete(formId, itemLabel, warnText);
        }
    }"
    x-init="$watch('activeTab', tab => history.replaceState(null, '', '?tab=' + tab))">

        <!-- Header -->
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px;">
            <div style="flex: 1;"></div>
            <div class="tabs"
                style="display: flex; gap: 8px; background: var(--bg-2); padding: 4px; border-radius: 8px; border: 1px solid var(--border);">
                {{-- 1. ภาควิชา (ต้องมีก่อนสร้างหลักสูตร/อาจารย์) --}}
                <button type="button" @click="activeTab = 'departments'"
                    :class="activeTab === 'departments' ? 'btn-primary' : 'btn btn-ghost'"
                    style="padding: 8px 16px; border-radius: 6px;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
                        style="margin-right: 6px;">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    ภาควิชา
                    @if(!$isAdmin)@include('shared.master_data._lock_icon')@endif
                </button>
                {{-- 2. หลักสูตร (ต้องมีก่อนสร้างรายวิชา/กลุ่ม) --}}
                <button type="button" @click="activeTab = 'curriculums'"
                    :class="activeTab === 'curriculums' ? 'btn-primary' : 'btn btn-ghost'"
                    style="padding: 8px 16px; border-radius: 6px;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
                        style="margin-right: 6px;">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                    </svg>
                    หลักสูตร
                    @if(!$isAdmin)@include('shared.master_data._lock_icon')@endif
                </button>
                {{-- 3. รายวิชา (ต้องมีหลักสูตรก่อน) --}}
                <button type="button" @click="activeTab = 'courses'"
                    :class="activeTab === 'courses' ? 'btn-primary' : 'btn btn-ghost'"
                    style="padding: 8px 16px; border-radius: 6px;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
                        style="margin-right: 6px;">
                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                    </svg>
                    รายวิชา
                </button>
                {{-- 4. อาจารย์ผู้สอน (ต้องมีภาควิชาก่อน) --}}
                <button type="button" @click="activeTab = 'instructors'"
                    :class="activeTab === 'instructors' ? 'btn-primary' : 'btn btn-ghost'"
                    style="padding: 8px 16px; border-radius: 6px;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
                        style="margin-right: 6px;">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    อาจารย์ผู้สอน
                    @if(!$isAdmin)@include('shared.master_data._lock_icon')@endif
                </button>
                {{-- 5. ประเภทสถานที่ (ต้องมีก่อนสร้างห้อง) --}}
                <button type="button" @click="activeTab = 'location_types'"
                    :class="activeTab === 'location_types' ? 'btn-primary' : 'btn btn-ghost'"
                    style="padding: 8px 16px; border-radius: 6px;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
                        style="margin-right: 6px;">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    </svg>
                    ประเภทสถานที่
                </button>
                {{-- 7. ห้องและสถานที่ (ต้องมีประเภทก่อน) --}}
                <button type="button" @click="activeTab = 'rooms'"
                    :class="activeTab === 'rooms' ? 'btn-primary' : 'btn btn-ghost'"
                    style="padding: 8px 16px; border-radius: 6px;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
                        style="margin-right: 6px;">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="9" y1="3" x2="9" y2="21"></line>
                    </svg>
                    ห้องและสถานที่
                </button>
                {{-- 8. ประเภทกิจกรรม (ใช้ตอนสร้างตาราง) --}}
                <button type="button" @click="activeTab = 'activity_types'"
                    :class="activeTab === 'activity_types' ? 'btn-primary' : 'btn btn-ghost'"
                    style="padding: 8px 16px; border-radius: 6px;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
                        style="margin-right: 6px;">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                    </svg>
                    ประเภทกิจกรรม
                    @if(!$isAdmin)@include('shared.master_data._lock_icon')@endif
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
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round">
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
                                <th style="text-align: right;">ชั่วโมงสอนสะสม</th>
                                <th style="text-align: right; padding-right: 24px;">เกณฑ์ภาระงานสอน</th>
                                @if($isAdmin)<th style="text-align: center;">จัดการ</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($instructors as $instructor)
                                <tr
                                    x-show="!searchQuery || '{{ $instructor->formatted_name }}'.toLowerCase().includes(searchQuery.toLowerCase())">
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
                                            <span
                                                style="color: var(--fg-3);">({{ $instructor->instructorProfile->academic_degree }})</span>
                                        @endif
                                    </td>
                                    <td style="color: var(--fg-2); font-size: 13px;">
                                        {{ $instructor->instructorProfile->department->name ?? '-' }}
                                    </td>
                                    <td style="color: var(--fg-2); font-size: 13px;">
                                        {{ $instructor->instructorProfile->employment_type ?? '-' }}
                                    </td>
                                    <td style="text-align: right; color: var(--fg-3); font-style: italic;">
                                        -
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
                                            <div style="font-size: 11px; color: var(--fg-3);">ชั่วโมงทำการ / {{ $period }}</div>
                                        @else
                                            <span style="color: var(--fg-3); font-style: italic;">- ไม่ระบุ -</span>
                                        @endif
                                    </td>

                                    @if($isAdmin)
                                    <td style="text-align: center;">
                                        <button type="button" class="action-btn" title="แก้ไขข้อมูลอาจารย์"
                                            @click="openEditInstructor({{ Js::from($instructor) }})">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                            </svg>
                                        </button>
                                    </td>
                                    @endif
                                </tr>
                            @endforeach
                            <tr
                                x-show="searchQuery && !Array.from($el.parentNode.children).some(tr => tr.style.display !== 'none' && tr !== $el)">
                                <td colspan="8" style="text-align: center; padding: 40px; color: var(--fg-3);">
                                    ไม่พบข้อมูลอาจารย์ที่ค้นหา</td>
                            </tr>
                            @if($instructors->isEmpty())
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px; color: var(--fg-3);">
                                        ยังไม่มีข้อมูลอาจารย์ผู้สอน</td>
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
                    @if($isAdmin)
                    <div class="card-actions">
                        <button class="btn btn-primary" @click="openAddDept()">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round">
                                <line x1="12" y1="5" x2="12" y2="19" />
                                <line x1="5" y1="12" x2="19" y2="12" />
                            </svg>
                            เพิ่มภาควิชา
                        </button>
                    </div>
                    @endif
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ชื่อภาควิชา</th>
                                <th>หัวหน้าภาควิชา</th>
                                <th>เลขานุการภาควิชา</th>
                                <th style="text-align: center;">จำนวนอาจารย์</th>
                                @if($isAdmin)<th style="text-align: center;">จัดการ</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($departments as $dept)
                                <tr>
                                    <td style="font-weight: 600; color: var(--fg-1);">{{ $dept->name }}</td>
                                    <td style="color: var(--fg-2); font-size: 13px;">{{ $dept->head->name ?? '-' }}</td>
                                    <td style="color: var(--fg-2); font-size: 13px;">{{ $dept->secretary->name ?? '-' }}
                                    </td>
                                    <td style="text-align: center; font-weight: 600; color: var(--brand-navy);">
                                        {{ $dept->instructors_count }} คน
                                    </td>
                                    @if($isAdmin)
                                    <td style="text-align: center;">
                                        <button class="action-btn" title="แก้ไข"
                                            @click="openEditDept({{ Js::from($dept) }})">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                            </svg>
                                        </button>
                                    </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 40px; color: var(--fg-3);">
                                        ยังไม่มีข้อมูลภาควิชา</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab: Location Types -->
        <div x-show="activeTab === 'location_types'" x-cloak>
            <div class="card">
                <div class="card-hdr">
                    <div class="card-ttl">ประเภทสถานที่ (Location Types)</div>
                    <div class="card-actions">
                        <button class="btn btn-primary" @click="openAddLocType()">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round">
                                <line x1="12" y1="5" x2="12" y2="19" />
                                <line x1="5" y1="12" x2="19" y2="12" />
                            </svg>
                            เพิ่มประเภท
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ชื่อประเภท</th>
                                <th style="text-align: center;">จำนวนสถานที่</th>
                                <th style="text-align: center;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($locationTypes as $type)
                                <tr>
                                    <td style="font-weight: 600; color: var(--fg-1);">{{ $type->name }}</td>
                                    <td style="text-align: center; color: var(--fg-2);">{{ $type->rooms_count }} แห่ง</td>
                                    <td style="text-align: center;">
                                        <button class="action-btn" title="แก้ไข"
                                            @click="openEditLocType({{ Js::from($type) }})">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" style="text-align: center; padding: 40px; color: var(--fg-3);">
                                        ยังไม่มีข้อมูลประเภทสถานที่</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab: Rooms -->
        <div x-show="activeTab === 'rooms'" x-cloak>
            <div class="card">
                <div class="card-hdr">
                    <div class="card-ttl">ห้องและสถานที่ (Rooms & Locations)</div>
                    <div class="card-actions">
                        <button class="btn btn-secondary" @click="showImportRoomModal = true">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="17 8 12 3 7 8"/>
                                <line x1="12" y1="3" x2="12" y2="15"/>
                            </svg>
                            นำเข้าจากไฟล์
                        </button>
                        <button class="btn btn-primary" @click="openAddRoom()">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round">
                                <line x1="12" y1="5" x2="12" y2="19" />
                                <line x1="5" y1="12" x2="19" y2="12" />
                            </svg>
                            เพิ่มห้อง/สถานที่
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>รหัสห้อง</th>
                                <th>ชื่อสถานที่</th>
                                <th>อาคาร</th>
                                <th>ประเภท</th>
                                <th style="text-align: center;">ความจุ</th>
                                <th style="text-align: center;">สถานะ</th>
                                <th style="text-align: center;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rooms as $room)
                                <tr>
                                    <td style="font-weight: 700; color: var(--brand-navy); font-family: var(--font-mono);">{{ $room->room_code }}</td>
                                    <td style="font-weight: 600; color: var(--fg-1);">{{ $room->room_name }}</td>
                                    <td style="color: var(--fg-2);">{{ $room->building ?? '-' }}</td>
                                    <td>
                                        <span class="badge badge-gray" style="font-size: 11px;">
                                            {{ $room->locationType->name ?? '-' }}
                                        </span>
                                    </td>
                                    <td style="text-align: center; font-weight: 600;">{{ $room->capacity }}</td>
                                    <td style="text-align: center;">
                                        @if($room->status === 'active')
                                            <span class="badge badge-success">พร้อมใช้งาน</span>
                                        @elseif($room->status === 'maintenance')
                                            <span class="badge badge-warning">ปิดซ่อมบำรุง</span>
                                        @else
                                            <span class="badge badge-gray">ไม่พร้อมใช้</span>
                                        @endif
                                    </td>
                                    <td style="text-align: center;">
                                        <button class="action-btn" title="แก้ไข"
                                            @click="openEditRoom({{ Js::from($room) }})">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 40px; color: var(--fg-3);">
                                        ยังไม่มีข้อมูลห้องและสถานที่</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab: Courses -->
        <div x-show="activeTab === 'courses'" x-cloak>
            <div class="card">
                <div class="card-hdr">
                    <div class="card-ttl">คลังรายวิชา (Courses Library)</div>
                    <div class="card-actions">
                        <div class="search-box" style="width: 240px;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round">
                                <circle cx="11" cy="11" r="8" />
                                <line x1="21" y1="21" x2="16.65" y2="16.65" />
                            </svg>
                            <input type="text" x-model="searchQuery" placeholder="รหัส หรือ ชื่อวิชา...">
                        </div>
                        <button class="btn btn-secondary" @click="showImportCourseModal = true">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="17 8 12 3 7 8"/>
                                <line x1="12" y1="3" x2="12" y2="15"/>
                            </svg>
                            นำเข้าจากไฟล์
                        </button>
                        <button class="btn btn-primary" @click="openAddCourse()">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round">
                                <line x1="12" y1="5" x2="12" y2="19" />
                                <line x1="5" y1="12" x2="19" y2="12" />
                            </svg>
                            เพิ่มวิชาใหม่
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="high-density">
                        <thead>
                            <tr>
                                <th style="width: 100px;">รหัสวิชา</th>
                                <th>ชื่อรายวิชา (ไทย / อังกฤษ)</th>
                                <th>ภาควิชา / หลักสูตร</th>
                                <th style="text-align: center;">หน่วยกิต</th>
                                <th style="text-align: center;">ปี/เทอม</th>
                                <th>หัวหน้าวิชาเริ่มต้น</th>
                                <th style="text-align: center;">สถานะ</th>
                                <th style="text-align: center;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($courses as $course)
                                <tr x-show="!searchQuery || '{{ $course->course_code }} {{ $course->name_th }} {{ $course->name_en }}'.toLowerCase().includes(searchQuery.toLowerCase())"
                                    style="{{ $course->status === 'inactive' ? 'opacity: 0.45; filter: grayscale(1); background: #fafafa;' : '' }}">
                                    <td style="vertical-align: middle;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <div style="width: 4px; height: 18px; border-radius: 2px; background: {{ $course->color_code ?? 'var(--bg-3)' }};"></div>
                                            <span style="font-weight: 700; color: var(--brand-navy); font-family: var(--font-mono);">{{ $course->course_code }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--fg-1);">{{ $course->name_th }}</div>
                                        <div style="font-size: 11px; color: var(--fg-3); font-style: italic;">{{ $course->name_en ?? '-' }}</div>
                                    </td>
                                    <td>
                                        <div style="font-size: 13px; color: var(--fg-2);">{{ $course->department->name ?? '-' }}</div>
                                        <div style="font-size: 11px; color: var(--brand-navy); font-weight: 500;">{{ $course->curriculum->name ?? '-' }}</div>
                                    </td>
                                    <td style="text-align: center;">
                                        <div style="font-weight: 700; color: var(--fg-1);">{{ $course->credits }}</div>
                                        <div style="font-size: 10px; color: var(--fg-3);">({{ $course->lecture_hours }}-{{ $course->lab_hours }}-{{ $course->self_study_hours }})</div>
                                    </td>
                                    <td style="text-align: center;">
                                        @if($course->default_year_level)
                                            <span class="badge badge-gray" style="font-size: 11px;">
                                                ปี {{ $course->default_year_level }} 
                                                ภาคเรียนที่ {{ $course->default_semester == 3 ? 'ฤดูร้อน' : ($course->default_semester ?? '-') }}
                                            </span>
                                        @else
                                            <span style="color: var(--fg-4); font-size: 11px;">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($course->headInstructor)
                                            <div style="display: flex; align-items: center; gap: 6px;">
                                                <span style="font-size: 13px; color: var(--fg-2);">{{ $course->headInstructor->formatted_name }}</span>
                                            </div>
                                        @else
                                            <span style="color: var(--fg-4); font-size: 11px; font-style: italic;">- ไม่ระบุ -</span>
                                        @endif
                                    </td>
                                    <td style="text-align: center;">
                                        @if($course->status === 'active')
                                            <span class="badge" style="background: #22c55e; color: #ffffff; padding: 4px 12px; border-radius: 99px; font-weight: 700; font-size: 11px; display: inline-block; box-shadow: 0 2px 4px rgba(34, 197, 94, 0.2);">เปิดสอน</span>
                                        @else
                                            <span class="badge" style="background: #e2e8f0; color: #64748b; padding: 4px 12px; border-radius: 99px; font-weight: 700; font-size: 11px; display: inline-block;">ปิดสอน</span>
                                        @endif
                                    </td>
                                    <td style="text-align: center;">
                                        <button class="action-btn" title="แก้ไข" @click="openEditCourse({{ Js::from($course) }})">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px; color: var(--fg-3);">ยังไม่มีข้อมูลรายวิชา</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab: Curriculums -->
        <div x-show="activeTab === 'curriculums'" x-cloak>
            <div class="card">
                <div class="card-hdr">
                    <div class="card-ttl">การจัดการหลักสูตร (Curriculum Management)</div>
                    @if($isAdmin)
                    <div class="card-actions">
                        <button class="btn btn-primary" @click="openAddCurriculum()">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round">
                                <line x1="12" y1="5" x2="12" y2="19" />
                                <line x1="5" y1="12" x2="19" y2="12" />
                            </svg>
                            เพิ่มหลักสูตรใหม่
                        </button>
                    </div>
                    @endif
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ชื่อหลักสูตร</th>
                                <th style="text-align: center;">ปีที่เริ่มใช้</th>
                                <th style="text-align: center;">จำนวนวิชา</th>
                                <th style="text-align: center;">สถานะ</th>
                                @if($isAdmin)<th style="text-align: center;">จัดการ</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($curriculums as $curr)
                                <tr>
                                    <td style="font-weight: 600; color: var(--fg-1);">{{ $curr->name }}</td>
                                    <td style="text-align: center; color: var(--fg-2);">{{ $curr->effective_year }}</td>
                                    <td style="text-align: center; font-weight: 700; color: var(--brand-navy);">{{ $curr->courses_count }} วิชา</td>
                                    <td style="text-align: center;">
                                        @if($curr->is_active)
                                            <span class="badge" style="background: #e6fffa; color: #047481; border: 1px solid #b2f5ea; padding: 4px 12px; border-radius: 99px; font-weight: 700; font-size: 11px; display: inline-block;">กำลังใช้งาน</span>
                                        @else
                                            <span class="badge" style="background: #f7fafc; color: #4a5568; border: 1px solid #edf2f7; padding: 4px 12px; border-radius: 99px; font-weight: 700; font-size: 11px; display: inline-block;">ปิดใช้งาน</span>
                                        @endif
                                    </td>
                                    @if($isAdmin)
                                    <td style="text-align: center;">
                                        <div style="display: flex; justify-content: center; gap: 8px;">
                                            <button class="action-btn" title="แก้ไขชื่อ/ปี" @click="openEditCurriculum({{ Js::from($curr) }})">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                                </svg>
                                            </button>
                                            @if(!$curr->is_active)
                                                <button class="action-btn" title="คัดลอกหลักสูตรและรายวิชา" @click="openCloneCurriculum({{ Js::from($curr) }})" style="color: var(--brand-navy);">
                                                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                                    </svg>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 40px; color: var(--fg-3);">ยังไม่มีข้อมูลหลักสูตร</td>
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
                <div class="modal-center" x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                    <div class="modal-hdr" style="background: var(--bg-2);">
                        <div class="modal-ttl" style="font-family: var(--font-display);"
                            x-text="editDeptMode ? 'แก้ไขภาควิชา' : 'เพิ่มภาควิชาใหม่'"></div>
                        <button type="button" class="modal-cls" @click="showDeptModal = false">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18" />
                                <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                        </button>
                    </div>
                    <form
                        :action="editDeptMode ? '{{ url('admin/master-data/departments') }}/' + currentDept.id : '{{ route('admin.departments.store') }}'"
                        method="POST" @submit="confirmDeptSave($event)">
                        @csrf
                        <input type="hidden" name="_method" value="PUT" :disabled="!editDeptMode">
                        <div class="modal-body">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label>ชื่อภาควิชา <span style="color: var(--status-conflict-fg)">*</span></label>
                                <input type="text" name="name" x-model="currentDept.name" required
                                    placeholder="เช่น ภาควิชาการพยาบาลกุมารเวชศาสตร์">
                            </div>
                            <div class="form-row">
                                <div class="form-group" style="position: relative;">
                                    <label>หัวหน้าภาควิชา</label>
                                    <div style="position: relative; display: flex; gap: 6px; align-items: flex-start;">
                                        <div style="flex: 1; position: relative;">
                                            <input type="text" x-model="headSearch" @input="showHeadDropdown = true"
                                                @focus="showHeadDropdown = true" @click.away="showHeadDropdown = false"
                                                placeholder="พิมพ์ชื่อเพื่อค้นหา..." autocomplete="off">
                                            <div class="search-results" x-show="showHeadDropdown && headSearch" x-cloak>
                                                <template
                                                    x-for="user in usersList.filter(u => u.name.toLowerCase().includes(headSearch.toLowerCase()))"
                                                    :key="user.id">
                                                    <div class="search-item" @click="selectHead(user)" x-text="user.name"></div>
                                                </template>
                                                <div x-show="usersList.filter(u => u.name.toLowerCase().includes(headSearch.toLowerCase())).length === 0"
                                                    class="search-item-empty">ไม่พบข้อมูล</div>
                                            </div>
                                        </div>
                                        <button type="button" x-show="currentDept.head_user_id" @click="clearHead()"
                                            title="ล้างข้อมูล"
                                            style="flex-shrink:0;margin-top:6px;width:32px;height:32px;border-radius:6px;border:1px solid var(--border);background:var(--bg-2);cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--fg-3);">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                        </button>
                                    </div>
                                    <input type="hidden" name="head_user_id" x-model="currentDept.head_user_id">
                                </div>
                                <div class="form-group" style="position: relative;">
                                    <label>เลขานุการภาควิชา</label>
                                    <div style="position: relative; display: flex; gap: 6px; align-items: flex-start;">
                                        <div style="flex: 1; position: relative;">
                                            <input type="text" x-model="secretarySearch"
                                                @input="showSecretaryDropdown = true" @focus="showSecretaryDropdown = true"
                                                @click.away="showSecretaryDropdown = false"
                                                placeholder="พิมพ์ชื่อเพื่อค้นหา..." autocomplete="off">
                                            <div class="search-results" x-show="showSecretaryDropdown && secretarySearch" x-cloak>
                                                <template
                                                    x-for="user in usersList.filter(u => u.name.toLowerCase().includes(secretarySearch.toLowerCase()))"
                                                    :key="user.id">
                                                    <div class="search-item" @click="selectSecretary(user)" x-text="user.name"></div>
                                                </template>
                                                <div x-show="usersList.filter(u => u.name.toLowerCase().includes(secretarySearch.toLowerCase())).length === 0"
                                                    class="search-item-empty">ไม่พบข้อมูล</div>
                                            </div>
                                        </div>
                                        <button type="button" x-show="currentDept.secretary_user_id" @click="clearSecretary()"
                                            title="ล้างข้อมูล"
                                            style="flex-shrink:0;margin-top:6px;width:32px;height:32px;border-radius:6px;border:1px solid var(--border);background:var(--bg-2);cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--fg-3);">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                        </button>
                                    </div>
                                    <input type="hidden" name="secretary_user_id" x-model="currentDept.secretary_user_id">
                                </div>
                            </div>
                        </div>
                        <div class="modal-foot" style="display: flex; justify-content: space-between;">
                            <div>
                                <button type="button" class="btn btn-ghost" x-show="editDeptMode" @click="confirmDelete('deleteDeptForm', currentDept.name, 'หากมีข้อมูลผูกพันอยู่จะไม่สามารถลบได้')" style="color: var(--status-conflict-fg);">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px; display: inline-block; vertical-align: middle;"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg> ลบข้อมูล
                                </button>
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="btn btn-ghost" @click="showDeptModal = false">ยกเลิก</button>
                                <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
                            </div>
                        </div>
                    </form>
                    <form id="deleteDeptForm" :action="'{{ url('admin/master-data/departments') }}/' + currentDept.id" method="POST" style="display: none;">
                        @csrf
                        @method('DELETE')
                    </form>
                </div>
            </div>
        </template>

        <!-- Edit Instructor Modal -->
        <template x-if="showInstructorModal">
            <div class="overlay" x-cloak @click.self="showInstructorModal = false">
                <div class="modal-center" style="max-width: 650px;"
                    x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100">
                    <div class="modal-hdr" style="background: var(--bg-2);">
                        <div class="modal-ttl" style="font-family: var(--font-display);">แก้ไขข้อมูลอาจารย์ผู้สอน</div>
                        <button type="button" class="modal-cls" @click="showInstructorModal = false">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18" />
                                <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                        </button>
                    </div>
                    <form :action="'{{ url('admin/master-data/instructors') }}/' + currentInstructor.id" method="POST"
                        @submit="confirmInstructorSave($event)">
                        @csrf
                        <input type="hidden" name="_method" value="PUT">
                        <div class="modal-body">
                            <div
                                style="display: grid; grid-template-columns: 140px 1fr; gap: 20px; margin-bottom: 20px;">
                                <div class="form-group">
                                    <label>คำนำหน้า <span style="color:var(--status-conflict-fg)">*</span></label>
                                    <select name="prefix" x-model="currentInstructor.prefix" required>
                                        <option value="นาย">นาย</option>
                                        <option value="นาง">นาง</option>
                                        <option value="นางสาว">นางสาว</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>ชื่อ-นามสกุล <span style="color:var(--status-conflict-fg)">*</span></label>
                                    <input type="text" name="name" x-model="currentInstructor.name" required>
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                <div class="form-group">
                                    <label>รหัสพนักงาน <span style="color:var(--status-conflict-fg)">*</span></label>
                                    <input type="text" name="employee_id" x-model="currentInstructor.employee_id"
                                        placeholder="รหัสพนักงาน" required>
                                </div>
                                <div class="form-group">
                                    <label>ภาควิชา <span style="color:var(--status-conflict-fg)">*</span></label>
                                    <select name="department_id" x-model="currentInstructor.department_id" required>
                                        <option value="">-- เลือกภาควิชา --</option>
                                        @foreach($departments as $dept)
                                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                <div class="form-group">
                                    <label>ตำแหน่งทางวิชาการ <span style="color:var(--status-conflict-fg)">*</span></label>
                                    <select name="title" x-model="currentInstructor.title" required>
                                        <option value="อาจารย์">อาจารย์</option>
                                        <option value="ผู้ช่วยศาสตราจารย์">ผู้ช่วยศาสตราจารย์</option>
                                        <option value="รองศาสตราจารย์">รองศาสตราจารย์</option>
                                        <option value="ศาสตราจารย์">ศาสตราจารย์</option>
                                        <option value="ผู้ช่วยอาจารย์">ผู้ช่วยอาจารย์</option>
                                        <option value="ผู้ช่วยอาจารย์ (คลินิก)">ผู้ช่วยอาจารย์ (คลินิก)</option>
                                        <option value="ผู้ช่วยอาจารย์ (สอนภาคปฏิบัติ)">ผู้ช่วยอาจารย์ (สอนภาคปฏิบัติ)
                                        </option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>วุฒิการศึกษา <span style="color:var(--status-conflict-fg)">*</span></label>
                                    <select name="academic_degree" x-model="currentInstructor.academic_degree" required>
                                        <option value="ปริญญาเอก">ปริญญาเอก</option>
                                        <option value="ปริญญาโท">ปริญญาโท</option>
                                        <option value="ปริญญาตรี">ปริญญาตรี</option>
                                    </select>
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label>ประเภทบุคลากร <span style="color:var(--status-conflict-fg)">*</span></label>
                                    <select name="employment_type" x-model="currentInstructor.employment_type" required>
                                        <option value="พนักงานมหาวิทยาลัย">พนักงานมหาวิทยาลัย</option>
                                        <option value="ข้าราชการ">ข้าราชการ</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>สัดส่วนงานสอน (%) <span style="color:var(--status-conflict-fg)">*</span></label>
                                    <input type="number" name="teaching_pct"
                                        x-model.number="currentInstructor.teaching_pct" min="0" max="100" required>
                                </div>
                            </div>
                        </div>
                        <div class="modal-foot">
                            <button type="button" class="btn btn-ghost"
                                @click="showInstructorModal = false">ยกเลิก</button>
                            <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        <!-- Add/Edit Modal (Location Type) -->
        <template x-if="showLocTypeModal">
            <div class="overlay" x-cloak @click.self="showLocTypeModal = false">
                <div class="modal-center" style="max-width: 450px;"
                    x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100">
                    <div class="modal-hdr" style="background: var(--bg-2);">
                        <div class="modal-ttl" style="font-family: var(--font-display);"
                            x-text="editLocTypeMode ? 'แก้ไขประเภทสถานที่' : 'เพิ่มประเภทสถานที่ใหม่'"></div>
                        <button type="button" class="modal-cls" @click="showLocTypeModal = false">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18" />
                                <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                        </button>
                    </div>
                    <form
                        :action="editLocTypeMode ? '{{ url($routePrefix . '/master-data/location-types') }}/' + currentLocType.id : '{{ route($routePrefix . '.location_types.store') }}'"
                        method="POST">
                        @csrf
                        <input type="hidden" name="_method" value="PUT" :disabled="!editLocTypeMode">
                        <div class="modal-body">
                            <div class="form-group">
                                <label>ชื่อประเภทสถานที่ <span style="color: var(--status-conflict-fg)">*</span></label>
                                <input type="text" name="name" x-model="currentLocType.name" required
                                    placeholder="เช่น ห้องบรรยาย, หอผู้ป่วย">
                            </div>
                        </div>
                        <div class="modal-foot" style="display: flex; justify-content: space-between;">
                            <div>
                                <button type="button" class="btn btn-ghost" x-show="editLocTypeMode" @click="confirmDelete('deleteLocTypeForm', currentLocType.name, null)" style="color: var(--status-conflict-fg);">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px; display: inline-block; vertical-align: middle;"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg> ลบข้อมูล
                                </button>
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="btn btn-ghost" @click="showLocTypeModal = false">ยกเลิก</button>
                                <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
                            </div>
                        </div>
                    </form>
                    <form id="deleteLocTypeForm" :action="'{{ url($routePrefix . '/master-data/location-types') }}/' + currentLocType.id" method="POST" style="display: none;">
                        @csrf
                        @method('DELETE')
                    </form>
                </div>
            </div>
        </template>

        <!-- Add/Edit Modal (Room) -->
        <template x-if="showRoomModal">
            <div class="overlay" x-cloak @click.self="showRoomModal = false">
                <div class="modal-center" style="max-width: 600px;"
                    x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100">
                    <div class="modal-hdr" style="background: var(--bg-2);">
                        <div class="modal-ttl" style="font-family: var(--font-display);"
                            x-text="editRoomMode ? 'แก้ไขห้อง/สถานที่' : 'เพิ่มห้อง/สถานที่ใหม่'"></div>
                        <button type="button" class="modal-cls" @click="showRoomModal = false">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18" />
                                <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                        </button>
                    </div>
                    <form
                        :action="editRoomMode ? '{{ url($routePrefix . '/master-data/rooms') }}/' + currentRoom.id : '{{ route($routePrefix . '.rooms.store') }}'"
                        method="POST">
                        @csrf
                        <input type="hidden" name="_method" value="PUT" :disabled="!editRoomMode">
                        <div class="modal-body">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                <div class="form-group">
                                    <label>รหัสห้อง/สถานที่ <span style="color: var(--status-conflict-fg)">*</span></label>
                                    <input type="text" name="room_code" x-model="currentRoom.room_code" required
                                        placeholder="เช่น 302, WARD-A">
                                </div>
                                <div class="form-group">
                                    <label>ชื่อห้อง/สถานที่ <span style="color: var(--status-conflict-fg)">*</span></label>
                                    <input type="text" name="room_name" x-model="currentRoom.room_name" required
                                        placeholder="เช่น ห้องบรรยาย 1">
                                </div>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                <div class="form-group">
                                    <label>อาคาร</label>
                                    <input type="text" name="building" x-model="currentRoom.building"
                                        placeholder="เช่น อาคาร 1">
                                </div>
                                <div class="form-group">
                                    <label>ความจุ (คน)</label>
                                    <input type="number" name="capacity" x-model="currentRoom.capacity"
                                        min="0">
                                </div>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                <div class="form-group">
                                    <label>ประเภทสถานที่ <span style="color: var(--status-conflict-fg)">*</span></label>
                                    <select name="location_type_id" x-model="currentRoom.location_type_id" required>
                                        <option value="">-- เลือกประเภท --</option>
                                        @foreach($locationTypes as $type)
                                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>สถานะ</label>
                                    <select name="status" x-model="currentRoom.status" required>
                                        <option value="active">พร้อมใช้งาน</option>
                                        <option value="maintenance">ปิดซ่อมบำรุง</option>
                                        <option value="inactive">ไม่พร้อมใช้</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label>ครุภัณฑ์ / อุปกรณ์</label>
                                <input type="text" name="equipment_type" x-model="currentRoom.equipment_type" 
                                    placeholder="เช่น โปรเจคเตอร์, คอมพิวเตอร์, ไมโครโฟน (คั่นด้วยลูกน้ำ ,)">
                            </div>
                            <div class="form-group">
                                <label>รายละเอียดที่ตั้ง / ที่อยู่ (แหล่งฝึกภายนอก)</label>
                                <textarea name="address" x-model="currentRoom.address" rows="2" 
                                    placeholder="เช่น ชั้น 3, โรงพยาบาลศิริราช..."></textarea>
                            </div>
                        </div>
                        <div class="modal-foot" style="display: flex; justify-content: space-between;">
                            <div>
                                <button type="button" class="btn btn-ghost" x-show="editRoomMode" @click="confirmDelete('deleteRoomForm', currentRoom.room_name, null)" style="color: var(--status-conflict-fg);">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px; display: inline-block; vertical-align: middle;"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg> ลบข้อมูล
                                </button>
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="btn btn-ghost" @click="showRoomModal = false">ยกเลิก</button>
                                <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
                            </div>
                        </div>
                    </form>
                    <form id="deleteRoomForm" :action="'{{ url($routePrefix . '/master-data/rooms') }}/' + currentRoom.id" method="POST" style="display: none;">
                        @csrf
                        @method('DELETE')
                    </form>
                </div>
            </div>
        </template>

        <!-- Add/Edit Modal (Course) -->
        <template x-if="showCourseModal">
            <div class="overlay" x-cloak @click.self="showCourseModal = false">
                <div class="modal-center" style="max-width: 800px;"
                    x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100">
                    <div class="modal-hdr" style="background: var(--bg-2);">
                        <div class="modal-ttl" style="font-family: var(--font-display);"
                            x-text="editCourseMode ? 'แก้ไขรายวิชา' : 'เพิ่มรายวิชาใหม่'"></div>
                        <button type="button" class="modal-cls" @click="showCourseModal = false">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18" />
                                <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                        </button>
                    </div>
                    <form :action="editCourseMode ? '{{ url($routePrefix . '/master-data/courses') }}/' + currentCourse.id : '{{ route($routePrefix . '.courses.store') }}'"
                        method="POST">
                        @csrf
                        <input type="hidden" name="_method" value="PUT" :disabled="!editCourseMode">
                        <div class="modal-body" style="display: flex; flex-direction: column; gap: 0;">

                            {{-- Section: ข้อมูลพื้นฐาน --}}
                            <div style="display: grid; grid-template-columns: 130px 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                                <div class="form-group" style="margin-bottom:0;">
                                    <label>รหัสวิชา <span style="color:var(--status-conflict-fg)">*</span></label>
                                    <input type="text" name="course_code" x-model="currentCourse.course_code" required placeholder="เช่น NSBS 212">
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label>ชื่อวิชา (ไทย) <span style="color:var(--status-conflict-fg)">*</span></label>
                                    <input type="text" name="name_th" x-model="currentCourse.name_th" required placeholder="เช่น การพยาบาลเด็ก 1">
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label>ชื่อวิชา (อังกฤษ)</label>
                                    <input type="text" name="name_en" x-model="currentCourse.name_en" placeholder="Pediatric Nursing 1">
                                </div>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr 80px; gap: 16px; margin-bottom: 20px;">
                                <div class="form-group" style="margin-bottom:0;">
                                    <label>หลักสูตร <span style="color:var(--status-conflict-fg)">*</span></label>
                                    <select name="curriculum_id" x-model="currentCourse.curriculum_id" required>
                                        <option value="">-- เลือกหลักสูตร --</option>
                                        @foreach($curriculums as $curr)
                                            <option value="{{ $curr->id }}">{{ $curr->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label>ภาควิชาที่ดูแล <span style="color:var(--status-conflict-fg)">*</span></label>
                                    <select name="department_id" x-model="currentCourse.department_id" required>
                                        <option value="">-- เลือกภาควิชา --</option>
                                        @foreach($departments as $dept)
                                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label>หน่วยกิต <span style="color:var(--status-conflict-fg)">*</span></label>
                                    <input type="number" name="credits" x-model="currentCourse.credits" required min="0" placeholder="2">
                                </div>
                            </div>

                            {{-- Divider: ผู้รับผิดชอบ --}}
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                                <span style="font-size:11px;font-weight:700;color:var(--fg-3);text-transform:uppercase;letter-spacing:.6px;white-space:nowrap;">ผู้รับผิดชอบ</span>
                                <div style="flex:1;height:1px;background:var(--border);"></div>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                                <div class="form-group" style="position:relative;margin-bottom:0;">
                                    <label>หัวหน้าวิชา / ผู้ประสานรายวิชา</label>
                                    <input type="text" x-model="courseHeadSearch" @input="showCourseHeadDropdown = true"
                                        @focus="showCourseHeadDropdown = true" @click.away="showCourseHeadDropdown = false"
                                        placeholder="พิมพ์ชื่อเพื่อค้นหา...">
                                    <div class="search-results" x-show="showCourseHeadDropdown && courseHeadSearch" x-cloak>
                                        <template x-for="user in usersList.filter(u => u.name.toLowerCase().includes(courseHeadSearch.toLowerCase()))" :key="user.id">
                                            <div class="search-item" @click="selectCourseHead(user)" x-text="user.name"></div>
                                        </template>
                                    </div>
                                    <input type="hidden" name="head_instructor_id" x-model="currentCourse.head_instructor_id">
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label>เจ้าหน้าที่ผู้ดูแลวิชา</label>
                                    <select name="assigned_staff_id" x-model="currentCourse.assigned_staff_id">
                                        <option value="">-- ไม่ระบุ --</option>
                                        @foreach($staffUsers as $staff)
                                            <option value="{{ $staff->id }}">{{ $staff->formatted_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            {{-- Divider: แผนการเรียน --}}
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                                <span style="font-size:11px;font-weight:700;color:var(--fg-3);text-transform:uppercase;letter-spacing:.6px;white-space:nowrap;">แผนการเรียน</span>
                                <div style="flex:1;height:1px;background:var(--border);"></div>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                                <div class="form-group" style="margin-bottom:0;">
                                    <label>ชั้นปีตามแผน</label>
                                    <select name="default_year_level" x-model="currentCourse.default_year_level">
                                        <option value="">-- ไม่ระบุ --</option>
                                        <option value="1">ชั้นปีที่ 1</option>
                                        <option value="2">ชั้นปีที่ 2</option>
                                        <option value="3">ชั้นปีที่ 3</option>
                                        <option value="4">ชั้นปีที่ 4</option>
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label>ภาคเรียนตามแผน</label>
                                    <select name="default_semester" x-model="currentCourse.default_semester">
                                        <option value="">-- ไม่ระบุ --</option>
                                        <option value="1">ภาคเรียนที่ 1</option>
                                        <option value="2">ภาคเรียนที่ 2</option>
                                        <option value="3">ภาคฤดูร้อน</option>
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label>สถานะรายวิชา</label>
                                    <select name="status" x-model="currentCourse.status">
                                        <option value="active">เปิดสอน</option>
                                        <option value="inactive">ปิดสอน</option>
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label>สีประจำวิชา</label>
                                    <div x-data="{ open: false }" style="position: relative;">
                                        <button type="button" @click="open = !open"
                                            style="width:100%;height:38px;border:1px solid var(--border);border-radius:4px;display:flex;align-items:center;gap:10px;padding:0 10px;background:var(--bg-1);cursor:pointer;">
                                            <span :style="'width:18px;height:18px;border-radius:3px;background:'+currentCourse.color_code+';border:1px solid rgba(0,0,0,.15);flex-shrink:0'"></span>
                                            <span style="font-size:12px;color:var(--fg-2);font-family:var(--font-mono);" x-text="currentCourse.color_code"></span>
                                        </button>
                                        <div x-show="open" @click.outside="open=false" x-cloak
                                            style="position:absolute;z-index:50;top:calc(100% + 4px);right:0;background:var(--bg-1);border:1px solid var(--border);border-radius:6px;padding:12px;box-shadow:0 4px 16px rgba(0,0,0,.12);width:220px;">
                                            <div style="display:grid;grid-template-columns:repeat(8,1fr);gap:6px;margin-bottom:10px;">
                                                <template x-for="c in ['#3B82F6','#2563EB','#1D4ED8','#0EA5E9','#06B6D4','#10B981','#059669','#047857','#F59E0B','#EF4444','#DC2626','#8B5CF6','#7C3AED','#EC4899','#F97316','#6B7280']">
                                                    <button type="button" @click="currentCourse.color_code=c;open=false"
                                                        :style="'width:20px;height:20px;border-radius:3px;background:'+c+';border:2px solid '+(currentCourse.color_code===c?'var(--brand-navy)':'transparent')+';cursor:pointer'"></button>
                                                </template>
                                            </div>
                                            <div style="display:flex;align-items:center;gap:8px;border-top:1px solid var(--border);padding-top:10px;">
                                                <input type="color" x-model="currentCourse.color_code"
                                                    style="width:32px;height:28px;padding:1px;border:1px solid var(--border);border-radius:3px;cursor:pointer;flex-shrink:0;">
                                                <span style="font-size:12px;color:var(--fg-3);">กำหนดเอง</span>
                                            </div>
                                        </div>
                                        <input type="hidden" name="color_code" x-model="currentCourse.color_code">
                                    </div>
                                </div>
                            </div>

                            {{-- Divider: ชั่วโมงการสอน --}}
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                                <span style="font-size:11px;font-weight:700;color:var(--fg-3);text-transform:uppercase;letter-spacing:.6px;white-space:nowrap;">ชั่วโมงการสอน</span>
                                <div style="flex:1;height:1px;background:var(--border);"></div>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:20px;background:var(--bg-2);padding:14px 16px;border-radius:8px;border:1px solid var(--border);">
                                <div class="form-group" style="margin-bottom:0;">
                                    <label>บรรยาย (ชม.)</label>
                                    <input type="number" name="lecture_hours" x-model="currentCourse.lecture_hours" min="0" placeholder="0">
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label>ปฏิบัติ / แล็บ (ชม.)</label>
                                    <input type="number" name="lab_hours" x-model="currentCourse.lab_hours" min="0" placeholder="0">
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label>ศึกษาด้วยตนเอง (ชม.)</label>
                                    <input type="number" name="self_study_hours" x-model="currentCourse.self_study_hours" min="0" placeholder="0">
                                </div>
                            </div>

                            <div class="form-group" style="margin-bottom: 20px;">
                                <label>จำนวนนักศึกษาสูงสุด (คน)</label>
                                <input type="number" name="capacity" x-model="currentCourse.capacity" min="1" placeholder="เช่น 240">
                            </div>

                            {{-- Practicum Rotation --}}
                            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:12px 14px;background:var(--bg-2);border-radius:8px;border:1px solid var(--border);">
                                <input type="checkbox" name="requires_practicum_rotation" x-model="currentCourse.requires_practicum_rotation" style="width:16px;height:16px;accent-color:var(--brand-navy);flex-shrink:0;">
                                <div>
                                    <div style="font-weight:600;font-size:14px;color:var(--fg-1);">วิชานี้ต้องมีการวนกลุ่มนักศึกษา</div>
                                    <div style="font-size:12px;color:var(--fg-3);margin-top:2px;">Practicum Rotation — นักศึกษาหมุนเวียนระหว่างกลุ่มและแหล่งฝึก</div>
                                </div>
                            </label>

                        </div>
                        <div class="modal-foot" style="display: flex; justify-content: space-between;">
                            <div>
                                <button type="button" class="btn btn-ghost" x-show="editCourseMode" @click="confirmDelete('deleteCourseForm', currentCourse.name_th + (currentCourse.course_code ? ' (' + currentCourse.course_code + ')' : ''), 'หากมีการผูกตารางสอนแล้วจะไม่สามารถลบได้')" style="color: var(--status-conflict-fg);">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px; display: inline-block; vertical-align: middle;"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg> ลบข้อมูล
                                </button>
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="btn btn-ghost" @click="showCourseModal = false">ยกเลิก</button>
                                <button type="submit" class="btn btn-primary">บันทึกข้อมูลวิชา</button>
                            </div>
                        </div>
                    </form>
                    <form id="deleteCourseForm" :action="'{{ url($routePrefix . '/master-data/courses') }}/' + currentCourse.id" method="POST" style="display: none;">
                        @csrf
                        @method('DELETE')
                    </form>
                </div>
            </div>
        </template>

        <!-- Add/Edit Modal (Curriculum) -->
        <template x-if="showCurriculumModal">
            <div class="overlay" x-cloak @click.self="showCurriculumModal = false">
                <div class="modal-center" style="max-width: 450px;"
                    x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100">
                    <div class="modal-hdr" style="background: var(--bg-2);">
                        <div class="modal-ttl" style="font-family: var(--font-display);"
                            x-text="editCurriculumMode ? 'แก้ไขหลักสูตร' : 'เพิ่มหลักสูตรใหม่'"></div>
                        <button type="button" class="modal-cls" @click="showCurriculumModal = false">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18" />
                                <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                        </button>
                    </div>
                    <form :action="editCurriculumMode ? '{{ url('admin/master-data/curriculums') }}/' + currentCurriculum.id : '{{ route('admin.curriculums.store') }}'"
                        method="POST">
                        @csrf
                        <input type="hidden" name="_method" value="PUT" :disabled="!editCurriculumMode">
                        <div class="modal-body">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label>ชื่อหลักสูตร <span style="color: var(--status-conflict-fg)">*</span></label>
                                <input type="text" name="name" x-model="currentCurriculum.name" required placeholder="เช่น พยาบาลศาสตรบัณฑิต (2565)">
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label>ปีที่เริ่มใช้ (พ.ศ.) <span style="color: var(--status-conflict-fg)">*</span></label>
                                    <input type="number" name="effective_year" x-model="currentCurriculum.effective_year" required placeholder="2565">
                                </div>
                                <div class="form-group">
                                    <label>สถานะ</label>
                                    <select name="is_active" x-model="currentCurriculum.is_active">
                                        <option value="1">เปิดใช้งาน</option>
                                        <option value="0">ปิดใช้งาน</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="modal-foot" style="display: flex; justify-content: space-between;">
                            <div>
                                <button type="button" class="btn btn-ghost" x-show="editCurriculumMode" @click="confirmDelete('deleteCurriculumForm', currentCurriculum.name, 'ต้องลบวิชาในหลักสูตรออกให้หมดก่อน')" style="color: var(--status-conflict-fg);">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px; display: inline-block; vertical-align: middle;"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg> ลบข้อมูล
                                </button>
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="btn btn-ghost" @click="showCurriculumModal = false">ยกเลิก</button>
                                <button type="submit" class="btn btn-primary">บันทึกข้อมูลหลักสูตร</button>
                            </div>
                        </div>
                    </form>
                    <form id="deleteCurriculumForm" :action="'{{ url('admin/master-data/curriculums') }}/' + currentCurriculum.id" method="POST" style="display: none;">
                        @csrf
                        @method('DELETE')
                    </form>
                </div>
            </div>
        </template>



        <!-- Tab: Activity Types -->
        <div x-show="activeTab === 'activity_types'" x-cloak>
            <div class="card">
                <div class="card-hdr">
                    <div class="card-ttl">ประเภทกิจกรรมการสอน</div>
                    @if($isAdmin)
                    <div class="card-actions">
                        <button type="button" class="btn btn-primary" @click="openAddActivityType()">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            เพิ่มประเภทกิจกรรม
                        </button>
                    </div>
                    @endif
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>สี</th>
                                <th>ชื่อประเภทกิจกรรม</th>
                                <th>หมวดหมู่</th>
                                @if($isAdmin)<th style="text-align: center;">จัดการ</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($activityTypes as $at)
                                <tr>
                                    <td>
                                        <span style="display: inline-block; width: 20px; height: 20px; border-radius: 4px; background: {{ $at->color_code }}; border: 1px solid var(--border);"></span>
                                    </td>
                                    <td style="font-weight: 600; color: var(--fg-1);">{{ $at->name }}</td>
                                    <td>
                                        @php
                                            $catLabel = ['lecture' => 'บรรยาย', 'practicum' => 'ปฏิบัติ', 'thesis' => 'วิทยานิพนธ์', 'other' => 'อื่นๆ'];
                                        @endphp
                                        <span class="pill pill-neutral">{{ $catLabel[$at->category] ?? $at->category }}</span>
                                    </td>
                                    @if($isAdmin)
                                    <td style="text-align: center;">
                                        <button type="button" class="action-btn" title="แก้ไข"
                                            @click="openEditActivityType({{ Js::from(['id' => $at->id, 'name' => $at->name, 'color_code' => $at->color_code, 'category' => $at->category]) }})">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </button>
                                    </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--fg-3); padding: 40px;">ยังไม่มีประเภทกิจกรรม</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add/Edit Modal (Activity Type) -->
        <template x-if="showActivityTypeModal">
            <div class="overlay" x-cloak @click.self="showActivityTypeModal = false">
                <div class="modal-center" style="max-width: 420px;"
                    x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100">
                    <div class="modal-hdr" style="background: var(--bg-2);">
                        <div class="modal-ttl" style="font-family: var(--font-display);"
                            x-text="editActivityTypeMode ? 'แก้ไขประเภทกิจกรรม' : 'เพิ่มประเภทกิจกรรมใหม่'"></div>
                        <button type="button" class="modal-cls" @click="showActivityTypeModal = false">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>
                    <form :action="editActivityTypeMode ? '{{ url('admin/master-data/activity-types') }}/' + currentActivityType.id : '{{ route('admin.activity_types.store') }}'" method="POST">
                        @csrf
                        <input type="hidden" name="_method" value="PUT" :disabled="!editActivityTypeMode">
                        <div class="modal-body">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label>ชื่อประเภทกิจกรรม <span style="color: var(--status-conflict-fg)">*</span></label>
                                <input type="text" name="name" x-model="currentActivityType.name" required placeholder="เช่น บรรยาย, ฝึกปฏิบัติในห้องเรียน">
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                <div class="form-group">
                                    <label>หมวดหมู่ <span style="color: var(--status-conflict-fg)">*</span></label>
                                    <select name="category" x-model="currentActivityType.category" required>
                                        <option value="lecture">บรรยาย (Lecture)</option>
                                        <option value="practicum">ปฏิบัติ (Practicum)</option>
                                        <option value="thesis">วิทยานิพนธ์ (Thesis)</option>
                                        <option value="other">อื่นๆ (Other)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>สีแสดงผล <span style="color: var(--status-conflict-fg)">*</span></label>
                                    <div x-data="{ open: false }" style="position: relative;">
                                        <button type="button" @click="open = !open"
                                            style="width: 100%; height: 38px; border: 1px solid var(--border); border-radius: 4px; display: flex; align-items: center; gap: 10px; padding: 0 10px; background: var(--bg-1); cursor: pointer;">
                                            <span :style="'width:20px;height:20px;border-radius:3px;background:' + currentActivityType.color_code + ';border:1px solid rgba(0,0,0,.15);flex-shrink:0'"></span>
                                            <span style="font-size: 13px; color: var(--fg-2); font-family: var(--font-mono);" x-text="currentActivityType.color_code"></span>
                                        </button>
                                        <div x-show="open" @click.outside="open = false" x-cloak
                                            style="position: absolute; z-index: 50; top: calc(100% + 4px); left: 0; background: var(--bg-1); border: 1px solid var(--border); border-radius: 6px; padding: 12px; box-shadow: 0 4px 16px rgba(0,0,0,.12); width: 220px;">
                                            <div style="display: grid; grid-template-columns: repeat(8, 1fr); gap: 6px; margin-bottom: 10px;">
                                                <template x-for="c in ['#3B82F6','#2563EB','#1D4ED8','#0EA5E9','#06B6D4','#10B981','#059669','#047857','#F59E0B','#EF4444','#DC2626','#8B5CF6','#7C3AED','#EC4899','#F97316','#6B7280']">
                                                    <button type="button" @click="currentActivityType.color_code = c; open = false"
                                                        :style="'width:20px;height:20px;border-radius:3px;background:' + c + ';border:2px solid ' + (currentActivityType.color_code === c ? 'var(--brand-navy)' : 'transparent') + ';cursor:pointer'"></button>
                                                </template>
                                            </div>
                                            <div style="display: flex; align-items: center; gap: 8px; border-top: 1px solid var(--border); padding-top: 10px;">
                                                <input type="color" x-model="currentActivityType.color_code"
                                                    style="width: 32px; height: 28px; padding: 1px; border: 1px solid var(--border); border-radius: 3px; cursor: pointer; flex-shrink: 0;">
                                                <span style="font-size: 12px; color: var(--fg-3);">กำหนดเอง</span>
                                            </div>
                                        </div>
                                        <input type="hidden" name="color_code" x-model="currentActivityType.color_code">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-foot" style="display: flex; justify-content: space-between;">
                            <div>
                                <button type="button" class="btn btn-ghost" x-show="editActivityTypeMode"
                                    @click="confirmDelete('deleteActivityTypeForm', currentActivityType.name, 'ประเภทกิจกรรมที่ลบแล้วจะไม่สามารถกู้คืนได้')"
                                    style="color: var(--status-conflict-fg);">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px; display: inline-block; vertical-align: middle;"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    ลบข้อมูล
                                </button>
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="btn btn-ghost" @click="showActivityTypeModal = false">ยกเลิก</button>
                                <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
                            </div>
                        </div>
                    </form>
                    <form id="deleteActivityTypeForm" :action="'{{ url('admin/master-data/activity-types') }}/' + currentActivityType.id" method="POST" style="display: none;">
                        @csrf
                        @method('DELETE')
                    </form>
                </div>
            </div>
        </template>

        <!-- Clone Curriculum Modal -->
        <template x-if="showCloneCurriculumModal">
            <div class="overlay" x-cloak @click.self="showCloneCurriculumModal = false">
                <div class="modal-center" x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                    <div class="modal-hdr" style="background: var(--bg-2);">
                        <div class="modal-ttl" style="font-family: var(--font-display);">คัดลอกหลักสูตรและรายวิชา</div>
                        <button type="button" class="modal-cls" @click="showCloneCurriculumModal = false">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18" />
                                <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                        </button>
                    </div>
                    <div style="padding: 16px 24px; background: #ebf8ff; border-bottom: 1px solid #bee3f8; font-size: 13px; color: #2b6cb0;">
                        <span style="font-weight: 700;">ต้นฉบับ:</span> <span x-text="cloneSourceCurriculum?.name"></span>
                        <div style="margin-top: 4px;">ระบบจะทำการก๊อปปี้ (Duplicate) รายวิชาทั้งหมดจากหลักสูตรนี้ ไปสร้างเป็นข้อมูลชุดใหม่ 100% โดยรักษารหัสวิชาเดิมไว้ทั้งหมด (ระบบรองรับรหัสวิชาซ้ำข้ามหลักสูตรได้)</div>
                    </div>
                    <form :action="cloneSourceCurriculum ? '{{ url('admin/master-data/curriculums') }}/' + cloneSourceCurriculum.id + '/clone' : '#'" method="POST">
                        @csrf
                        <div class="modal-body">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label>ชื่อหลักสูตรใหม่ <span style="color: var(--status-conflict-fg)">*</span></label>
                                <input type="text" name="name" x-model="cloneNewName" required>
                            </div>
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label>ปีที่เริ่มใช้หลักสูตรใหม่ (พ.ศ.) <span style="color: var(--status-conflict-fg)">*</span></label>
                                <input type="number" name="effective_year" x-model="cloneNewYear" required>
                            </div>
                        </div>
                        <div class="modal-foot">
                            <button type="button" class="btn btn-ghost" @click="showCloneCurriculumModal = false">ยกเลิก</button>
                            <button type="submit" class="btn btn-primary" style="background: #3182ce;">ยืนยันการคัดลอก</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        {{-- Import Room Modal --}}
        <template x-if="showImportRoomModal">
            <div class="overlay" x-cloak @click.self="showImportRoomModal = false">
                <div class="modal-center" style="max-width: 480px;"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100">
                    <div class="modal-hdr" style="background: var(--bg-2);">
                        <div class="modal-ttl" style="font-family: var(--font-display);">นำเข้าห้อง/สถานที่จากไฟล์ CSV</div>
                        <button type="button" class="modal-cls" @click="showImportRoomModal = false">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>
                    <form method="POST" action="{{ route($routePrefix . '.rooms.import') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="modal-body" style="padding: 24px;">
                            <div style="background: var(--bg-2); border: 1px solid var(--border); border-radius: 8px; padding: 14px 16px; margin-bottom: 20px; font-size: 13px; line-height: 1.7; color: var(--fg-muted);">
                                <strong style="color: var(--fg-base); display: block; margin-bottom: 4px;">รูปแบบไฟล์ CSV</strong>
                                คอลัมน์บังคับ: <code>name, code, location_type_name</code><br>
                                คอลัมน์เสริม: <code>capacity, floor, building, status</code><br>
                                <span style="margin-top: 6px; display: block;">• status: <code>active</code>, <code>inactive</code>, หรือ <code>maintenance</code></span>
                                <span>• ถ้า code ซ้ำ → อัปเดตข้อมูลแทน (upsert)</span>
                            </div>
                            <div style="margin-bottom: 16px;">
                                <a href="{{ asset('templates/rooms_import.csv') }}"
                                    style="font-size: 13px; color: var(--accent); text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                        <polyline points="7 10 12 15 17 10"/>
                                        <line x1="12" y1="15" x2="12" y2="3"/>
                                    </svg>
                                    ดาวน์โหลดไฟล์ตัวอย่าง (rooms_import.csv)
                                </a>
                            </div>
                            <div style="margin-bottom: 16px;">
                                <label class="frm-lbl">เลือกไฟล์ CSV <span style="color: var(--status-conflict-fg)">*</span></label>
                                <input type="file" name="csv_file" accept=".csv,.txt" required
                                    style="display: block; width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px; background: var(--bg-1);">
                                <div style="font-size: 12px; color: var(--fg-muted); margin-top: 4px;">UTF-8 (ไม่มี BOM), ไม่เกิน 5 MB</div>
                            </div>
                            <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; padding: 12px 14px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-2);">
                                <input type="checkbox" name="update_on_duplicate" value="1" checked style="margin-top: 2px; flex-shrink: 0;">
                                <div>
                                    <div style="font-size: 13px; font-weight: 600; color: var(--fg-base);">อัปเดตข้อมูลถ้า code ซ้ำ</div>
                                    <div style="font-size: 12px; color: var(--fg-muted); margin-top: 2px;">ถ้าไม่ติ๊ก จะข้ามแถวที่ซ้ำโดยไม่อัปเดต</div>
                                </div>
                            </label>
                        </div>
                        <div class="modal-foot">
                            <button type="button" class="btn btn-ghost" @click="showImportRoomModal = false">ยกเลิก</button>
                            <button type="submit" class="btn btn-primary">นำเข้าข้อมูล</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        {{-- Import Course Modal --}}
        <template x-if="showImportCourseModal">
            <div class="overlay" x-cloak @click.self="showImportCourseModal = false">
                <div class="modal-center" style="max-width: 480px;"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100">
                    <div class="modal-hdr" style="background: var(--bg-2);">
                        <div class="modal-ttl" style="font-family: var(--font-display);">นำเข้ารายวิชาจากไฟล์ CSV</div>
                        <button type="button" class="modal-cls" @click="showImportCourseModal = false">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>
                    <form method="POST" action="{{ route($routePrefix . '.courses.import') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="modal-body" style="padding: 24px;">
                            <div style="background: var(--bg-2); border: 1px solid var(--border); border-radius: 8px; padding: 14px 16px; margin-bottom: 20px; font-size: 13px; line-height: 1.7; color: var(--fg-muted);">
                                <strong style="color: var(--fg-base); display: block; margin-bottom: 4px;">รูปแบบไฟล์ CSV</strong>
                                คอลัมน์บังคับ: <code>course_code, name_th, curriculum_name, department_name, credits</code><br>
                                คอลัมน์เสริม: <code>name_en, lecture_hours, lab_hours, self_study_hours, default_year_level, default_semester, status</code><br>
                                <span style="margin-top: 6px; display: block;">• course_type คำนวณอัตโนมัติจาก lecture_hours + lab_hours</span>
                                <span>• ถ้า course_code + curriculum ซ้ำ → อัปเดตข้อมูลแทน (upsert)</span>
                            </div>
                            <div style="margin-bottom: 16px;">
                                <a href="{{ asset('templates/courses_import.csv') }}"
                                    style="font-size: 13px; color: var(--accent); text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                        <polyline points="7 10 12 15 17 10"/>
                                        <line x1="12" y1="15" x2="12" y2="3"/>
                                    </svg>
                                    ดาวน์โหลดไฟล์ตัวอย่าง (courses_import.csv)
                                </a>
                            </div>
                            <div style="margin-bottom: 16px;">
                                <label class="frm-lbl">เลือกไฟล์ CSV <span style="color: var(--status-conflict-fg)">*</span></label>
                                <input type="file" name="csv_file" accept=".csv,.txt" required
                                    style="display: block; width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px; background: var(--bg-1);">
                                <div style="font-size: 12px; color: var(--fg-muted); margin-top: 4px;">UTF-8 (ไม่มี BOM), ไม่เกิน 5 MB</div>
                            </div>
                            <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; padding: 12px 14px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-2);">
                                <input type="checkbox" name="update_on_duplicate" value="1" checked style="margin-top: 2px; flex-shrink: 0;">
                                <div>
                                    <div style="font-size: 13px; font-weight: 600; color: var(--fg-base);">อัปเดตข้อมูลถ้า course_code + หลักสูตร ซ้ำ</div>
                                    <div style="font-size: 12px; color: var(--fg-muted); margin-top: 2px;">ถ้าไม่ติ๊ก จะข้ามแถวที่ซ้ำโดยไม่อัปเดต</div>
                                </div>
                            </label>
                        </div>
                        <div class="modal-foot">
                            <button type="button" class="btn btn-ghost" @click="showImportCourseModal = false">ยกเลิก</button>
                            <button type="submit" class="btn btn-primary">นำเข้าข้อมูล</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </div>

    <style>
        [x-cloak] {
            display: none !important;
        }

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

    <script>
        function tpssDeptConflictWarn(form, lines) {
            var lineHtml = lines.map(function(l) { return '<li style="margin-bottom:4px;">' + l + '</li>'; }).join('');
            var innerHtml = '<div style="text-align:center;">'
                + '<div style="width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,#fffbeb,#fef3c7);'
                + 'border:2px solid #fcd34d;display:flex;align-items:center;justify-content:center;'
                + 'margin:0 auto 16px;box-shadow:0 4px 16px rgba(217,119,6,0.15);">'
                + '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="#d97706" stroke-width="2"'
                + ' stroke-linecap="round" stroke-linejoin="round">'
                + '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>'
                + '<line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>'
                + '<div style="font-family:Kanit,sans-serif;font-size:19px;font-weight:700;color:#0f172a;">ตำแหน่งซ้ำกับภาควิชาอื่น</div>'
                + '<div style="font-size:13px;color:#94a3b8;margin-top:4px;">กรุณาตรวจสอบก่อนดำเนินการ</div>'
                + '<div style="background:#fffbeb;border:1.5px solid #fde68a;border-radius:10px;padding:14px 16px;margin-top:14px;text-align:left;">'
                + '<ul style="margin:0;padding-left:18px;font-size:13px;color:#92400e;line-height:1.8;">' + lineHtml + '</ul>'
                + '<div style="font-size:12px;color:#b45309;margin-top:8px;padding-top:8px;border-top:1px solid #fde68a;">'
                + 'หากดำเนินการต่อ ระบบจะย้ายตำแหน่งออกจากภาควิชาเดิมให้อัตโนมัติ'
                + '</div></div></div>';

            Swal.fire({
                html: innerHtml,
                showCancelButton: true,
                confirmButtonText: 'ดำเนินการต่อ',
                cancelButtonText: 'ยกเลิก',
                reverseButtons: true,
                focusCancel: true,
                buttonsStyling: false,
                customClass: {
                    popup:         'tpss-delete-popup',
                    confirmButton: 'tpss-warn-confirm',
                    cancelButton:  'tpss-delete-cancel',
                    actions:       'tpss-delete-actions'
                }
            }).then(function(result) {
                if (result.isConfirmed) {
                    var input = document.createElement('input');
                    input.type  = 'hidden';
                    input.name  = 'force_position_override';
                    input.value = '1';
                    form.appendChild(input);
                    form.submit();
                }
            });
        }
    </script>

    @if(session('import_errors'))
    <div style="position: fixed; bottom: 20px; right: 20px; max-width: 420px; background: #fff; border: 1px solid var(--border); border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,.12); z-index: 9999; overflow: hidden;"
        x-data="{ open: true }" x-show="open">
        <div style="background: oklch(96% 0.02 30); border-bottom: 1px solid var(--border); padding: 10px 16px; display: flex; align-items: center; justify-content: space-between;">
            <span style="font-size: 13px; font-weight: 600; color: oklch(40% 0.12 30);">รายการที่นำเข้าไม่ได้ ({{ count(session('import_errors')) }} รายการ)</span>
            <button @click="open = false" style="background: none; border: none; cursor: pointer; color: var(--fg-muted);">✕</button>
        </div>
        <div style="max-height: 200px; overflow-y: auto; padding: 12px 16px;">
            @foreach(session('import_errors') as $err)
                <div style="font-size: 12px; color: var(--fg-base); padding: 4px 0; border-bottom: 1px solid var(--border-subtle);">{{ $err }}</div>
            @endforeach
        </div>
    </div>
    @endif
</x-app-layout>