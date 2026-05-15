<x-app-layout title="ข้อมูลหลักระบบ">
    <div x-data="{
        activeTab: new URLSearchParams(window.location.search).get('tab') || 'instructors',
        searchQuery: '',
        showDeptModal: false,
        editDeptMode: false,
        currentDept: {
            id: '',
            name: '',
            head_user_id: '',
            secretary_user_id: '',
            head_active: true,
            secretary_active: true,
        },
        openAddDept() {
            this.editDeptMode = false;
            this.currentDept = { id: '', name: '', head_user_id: '', secretary_user_id: '', head_active: true, secretary_active: true };
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
                secretary_user_id: dept.secretary_user_id || '',
                head_active: dept.head_active ?? true,
                secretary_active: dept.secretary_active ?? true,
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
            hired_at: '',
            teaching_pct: 0,
            research_pct: 0,
            service_pct: 0,
            culture_pct: 0,
            other_pct: 0,
        },
        openEditInstructor(instructor) {
            this.currentInstructor = {
                id: instructor.id,
                prefix: instructor.prefix || '',
                name: instructor.name,
                employee_id: instructor.employee_id || '',
                title: instructor.instructor_profile?.title || '',
                academic_degree: instructor.instructor_profile?.academic_degree || '',
                department_id: instructor.instructor_profile?.department_id || '',
                employment_type: instructor.instructor_profile?.employment_type || '',
                hired_at: instructor.instructor_profile?.hired_at || '',
                teaching_pct: instructor.instructor_profile?.teaching_pct ?? 0,
                research_pct: instructor.instructor_profile?.research_pct ?? 0,
                service_pct:  instructor.instructor_profile?.service_pct  ?? 0,
                culture_pct:  instructor.instructor_profile?.culture_pct  ?? 0,
                other_pct:    instructor.instructor_profile?.other_pct    ?? 0,
            };
            this.showInstructorModal = true;
        },
        departmentsData: {{ Js::from($departments->map(fn($d) => ['id' => $d->id, 'name' => $d->name, 'head_user_id' => $d->head_user_id, 'secretary_user_id' => $d->secretary_user_id, 'head_active' => $d->head?->is_active ?? true, 'secretary_active' => $d->secretary?->is_active ?? true, 'instructor_ids' => $d->instructorProfiles->pluck('user_id')->values()])) }},
        headSearch: '',
        secretarySearch: '',
        showHeadDropdown: false,
        showSecretaryDropdown: false,
        get deptInstructorUsers() {
            if (!this.currentDept.id) return [];
            var dept = this.departmentsData.find(d => String(d.id) === String(this.currentDept.id));
            if (!dept || !dept.instructor_ids || dept.instructor_ids.length === 0) return [];
            return this.usersList.filter(u => dept.instructor_ids.includes(u.id));
        },
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
        currentLocType: { id: '', name: '', rooms_count: 0 },
        openAddLocType() {
            this.editLocTypeMode = false;
            this.currentLocType = { id: '', name: '', rooms_count: 0 };
            this.showLocTypeModal = true;
        },
        openEditLocType(type) {
            this.editLocTypeMode = true;
            this.currentLocType = { id: type.id, name: type.name, rooms_count: type.rooms_count };
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
        selectedStaff: [],
        staffSearch: '',
        showStaffDropdown: false,
        openAddCourse() {
            this.editCourseMode = false;
            this.currentCourse = { id: '', course_code: '', name_th: '', name_en: '', curriculum_id: '', department_id: '', head_instructor_id: '', course_type: 'theory', academic_level: 'undergraduate', default_year_level: '', default_semester: '', credits: '', lecture_hours: 0, lab_hours: 0, self_study_hours: 0, capacity: '', color_code: '#3b82f6', status: 'active', requires_practicum_rotation: '0' };
            this.courseHeadSearch = '';
            this.selectedStaff = [];
            this.staffSearch = '';
            this.showCourseModal = true;
        },
        openEditCourse(course) {
            this.editCourseMode = true;
            this.currentCourse = { ...course };
            this.currentCourse.requires_practicum_rotation = course.requires_practicum_rotation ? '1' : '0';
            this.courseHeadSearch = course.head_instructor ? course.head_instructor.formatted_name : '';
            this.selectedStaff = course.assigned_staff ? course.assigned_staff.map(s => ({ id: s.id, name: s.formatted_name || s.name })) : [];
            this.staffSearch = '';
            this.showCourseModal = true;
        },
        selectCourseHead(user) {
            this.currentCourse.head_instructor_id = user.id;
            this.courseHeadSearch = user.formatted_name || user.name;
            this.showCourseHeadDropdown = false;
        },
        addStaff(user) {
            if (!this.selectedStaff.find(s => s.id === user.id)) {
                this.selectedStaff.push({ id: user.id, name: user.formatted_name || user.name });
            }
            this.staffSearch = '';
            this.showStaffDropdown = false;
        },
        removeStaff(id) {
            this.selectedStaff = this.selectedStaff.filter(s => s.id !== id);
        },
        filteredStaffList() {
            return staffUsers.filter(u =>
                !this.selectedStaff.find(s => s.id === u.id) &&
                (u.formatted_name || u.name).toLowerCase().includes(this.staffSearch.toLowerCase())
            );
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
            this.currentCurriculum = { ...curr, is_active: curr.is_active ? '1' : '0' };
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

        usersList: {{ Js::from($users->map(fn($u) => ['id' => $u->id, 'name' => $u->formatted_name, 'formatted_name' => $u->formatted_name, 'roles' => $u->roles->pluck('role')->values(), 'department_id' => $u->instructorProfile?->department_id])) }},

        get courseHeadList() {
            var deptId = this.currentCourse.department_id ? String(this.currentCourse.department_id) : null;
            return this.usersList.filter(function(u) {
                if (!u.roles || !u.roles.includes('course_head')) return false;
                if (deptId) return u.department_id && String(u.department_id) === deptId;
                return true;
            });
        },

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

            var samePersonWarn = headId && secId && headId === secId;

            if (!headConflict && !secConflict && !samePersonWarn) return;
            e.preventDefault();

            var lines = [];
            if (samePersonWarn) {
                var spName = (this.usersList.find(function(u) { return String(u.id) === headId; }) || {}).name || 'บุคคลนี้';
                lines.push(spName + ' ถูกเลือกเป็นทั้งหัวหน้าและเลขานุการภาควิชาเดียวกัน');
            }
            if (headConflict) {
                var hName = (this.usersList.find(function(u) { return String(u.id) === headId; }) || {}).name || 'บุคคลนี้';
                lines.push(hName + ' เป็นหัวหน้าภาควิชา ' + headConflict.name + ' อยู่แล้ว');
            }
            if (secConflict) {
                var sName = (this.usersList.find(function(u) { return String(u.id) === secId; }) || {}).name || 'บุคคลนี้';
                lines.push(sName + ' เป็นเลขานุการภาควิชา ' + secConflict.name + ' อยู่แล้ว');
            }
            var warnOpts = (!headConflict && !secConflict && samePersonWarn)
                ? { title: 'บุคคลเดียวกันในทั้งสองตำแหน่ง', note: 'ยืนยันเพื่อบันทึกต่อ หรือกลับไปเลือกใหม่' }
                : {};
            tpssDeptConflictWarn(form, lines, warnOpts);
        },
        getInstructorPARules() {
            const title  = this.currentInstructor.title;
            const degree = this.currentInstructor.academic_degree;
            const isClinical   = title === 'ผู้ช่วยอาจารย์ (คลินิก)';
            const isPracticum  = title === 'ผู้ช่วยอาจารย์ (สอนภาคปฏิบัติ)';
            const isAssistBach = title === 'ผู้ช่วยอาจารย์' && degree === 'ปริญญาตรี';
            const isAssist     = title === 'ผู้ช่วยอาจารย์';
            if (isClinical)   return { teaching:{min:0,max:10,label:'≤ 10%'},   research:{min:0,max:5,label:'0–5%'},   service:{min:70,max:80,label:'70–80%'}, culture:{min:0,max:15,label:'0–15%'}, other:{min:0,max:20,label:'0–20%'} };
            if (isPracticum)  return { teaching:{min:0,max:70,label:'≤ 70%'},   research:{min:0,max:0,label:'0%'},     service:{min:5,max:20,label:'5–20%'},   culture:{min:0,max:15,label:'0–15%'}, other:{min:0,max:20,label:'0–20%'} };
            if (isAssistBach) return { teaching:{min:30,max:60,label:'30–60%'}, research:{min:0,max:0,label:'0%'},     service:{min:10,max:30,label:'10–30%'}, culture:{min:0,max:15,label:'0–15%'}, other:{min:0,max:20,label:'0–20%'} };
            if (isAssist)     return { teaching:{min:0,max:70,label:'≤ 70%'},   research:{min:15,max:20,label:'15–20%'},service:{min:5,max:20,label:'5–20%'},  culture:{min:0,max:15,label:'0–15%'}, other:{min:0,max:20,label:'0–20%'} };
            return             { teaching:{min:20,max:70,label:'20–70%'},        research:{min:20,max:70,label:'20–70%'},service:{min:5,max:20,label:'5–20%'},  culture:{min:5,max:15,label:'5–15%'}, other:{min:0,max:20,label:'0–20%'} };
        },
        get paTotal() {
            return (parseInt(this.currentInstructor.teaching_pct)||0)
                 + (parseInt(this.currentInstructor.research_pct)||0)
                 + (parseInt(this.currentInstructor.service_pct)||0)
                 + (parseInt(this.currentInstructor.culture_pct)||0)
                 + (parseInt(this.currentInstructor.other_pct)||0);
        },
        confirmInstructorSave(e) {
            const rules = this.getInstructorPARules();
            const fields = [
                { key: 'teaching_pct', label: 'ด้านการสอน',       rule: rules.teaching },
                { key: 'research_pct', label: 'ด้านวิจัย',         rule: rules.research },
                { key: 'service_pct',  label: 'บริการวิชาการ',      rule: rules.service  },
                { key: 'culture_pct',  label: 'ศิลปวัฒนธรรม',      rule: rules.culture  },
                { key: 'other_pct',    label: 'งานอื่นๆ มอบหมาย',   rule: rules.other    },
            ];
            for (const f of fields) {
                const val = parseInt(this.currentInstructor[f.key]) || 0;
                if (val < f.rule.min || val > f.rule.max) {
                    e.preventDefault();
                    tpssToast(f.label + ' ' + val + '% ไม่อยู่ในเกณฑ์ (' + f.rule.label + ')', 'error');
                    return;
                }
            }
            if (this.paTotal !== 100) {
                e.preventDefault();
                tpssToast('สัดส่วนรวมทั้งหมดต้องเท่ากับ 100% (ปัจจุบัน: ' + this.paTotal + '%)', 'error');
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
                style="display: flex; gap: 8px; background: var(--bg-2); padding: 4px; border-radius: 8px; border: 1px solid var(--border); overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch;">
                {{-- 1. ภาควิชา (ต้องมีก่อนสร้างหลักสูตร/อาจารย์) --}}
                <button type="button" @click="activeTab = 'departments'"
                    :class="activeTab === 'departments' ? 'btn-primary' : 'btn btn-ghost'"
                    style="padding: 8px 16px; border-radius: 6px; flex-shrink: 0; display: flex; align-items: center;">
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
                    style="padding: 8px 16px; border-radius: 6px; flex-shrink: 0; display: flex; align-items: center;">
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
                    style="padding: 8px 16px; border-radius: 6px; flex-shrink: 0; display: flex; align-items: center;">
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
                    style="padding: 8px 16px; border-radius: 6px; flex-shrink: 0; display: flex; align-items: center;">
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
                {{-- 5. ห้องและสถานที่ (รวมประเภท) --}}
                <button type="button" @click="activeTab = 'location_types'"
                    :class="activeTab === 'location_types' ? 'btn-primary' : 'btn btn-ghost'"
                    style="padding: 8px 16px; border-radius: 6px; flex-shrink: 0; display: flex; align-items: center;">
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
                    style="padding: 8px 16px; border-radius: 6px; flex-shrink: 0; display: flex; align-items: center;">
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
                                        {{ $instructor->employee_id ?? '-' }}
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--fg-1);">
                                            {{ $instructor->formatted_name }}
                                        </div>
                                        @if($instructor->instructorProfile && $instructor->instructorProfile->employment_type)
                                        <div style="font-size: 11px; color: var(--fg-3); margin-top: 2px;">
                                            {{ $instructor->instructorProfile->employment_type }}
                                        </div>
                                        @endif
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
                    <div class="card-ttl">ภาควิชา</div>
                    @if($isAdmin)
                    <div class="card-actions">
                        <button class="btn btn-primary" @click="openAddDept()">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                            เพิ่มภาควิชา
                        </button>
                    </div>
                    @endif
                </div>

                <div x-data="{ expandedDept: null }" style="padding: 16px 20px; display: flex; flex-direction: column; gap: 8px;">
                    @forelse($departments as $dept)
                        <div style="border: 1px solid var(--border); border-radius: 8px; overflow: hidden;">

                            {{-- Header --}}
                            <div @click="expandedDept = expandedDept === {{ $dept->id }} ? null : {{ $dept->id }}"
                                style="cursor: pointer; user-select: none; transition: background 0.15s;"
                                :style="expandedDept === {{ $dept->id }} ? 'background: #f0f4ff;' : 'background: #fff;'">
                            <div style="display: flex; align-items: center; gap: 16px; padding: 14px 16px;">

                                {{-- Chevron --}}
                                <div style="flex-shrink: 0; width: 28px; height: 28px; border-radius: 6px; display: flex; align-items: center; justify-content: center; transition: background 0.15s;"
                                    :style="expandedDept === {{ $dept->id }} ? 'background: var(--brand-navy);' : 'background: var(--bg-3);'">
                                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                                        style="transition: transform 0.2s;"
                                        :style="expandedDept === {{ $dept->id }} ? 'transform:rotate(90deg); color:#fff' : 'color: var(--fg-3)'">
                                        <polyline points="9 18 15 12 9 6"/>
                                    </svg>
                                </div>

                                {{-- Name + head/sec --}}
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-weight: 600; font-size: 14px; color: var(--fg-1);">{{ $dept->name }}</div>
                                    <div style="margin-top: 3px; font-size: 12px; color: var(--fg-3); display: flex; gap: 16px; flex-wrap: wrap;">
                                        <span>หัวหน้า:
                                            @if($dept->head)
                                                {{ $dept->head->formatted_name }}
                                                @if(!$dept->head->is_active)
                                                    <span style="display:inline-block;margin-left:4px;padding:1px 6px;font-size:10px;border-radius:4px;background:oklch(95% 0.04 25);color:oklch(45% 0.15 25);font-weight:600;">ถูกระงับ</span>
                                                @endif
                                            @else
                                                -
                                            @endif
                                        </span>
                                        <span>เลขานุการ:
                                            @if($dept->secretary)
                                                {{ $dept->secretary->formatted_name }}
                                                @if(!$dept->secretary->is_active)
                                                    <span style="display:inline-block;margin-left:4px;padding:1px 6px;font-size:10px;border-radius:4px;background:oklch(95% 0.04 25);color:oklch(45% 0.15 25);font-weight:600;">ถูกระงับ</span>
                                                @endif
                                            @else
                                                -
                                            @endif
                                        </span>
                                    </div>
                                </div>

                                {{-- Count badge --}}
                                @if($dept->instructors_count > 0)
                                <div style="flex-shrink: 0; background: var(--bg-3); border-radius: 20px; padding: 4px 12px; font-size: 12px; font-weight: 700; color: var(--fg-2);">
                                    {{ $dept->instructors_count }} คน
                                </div>
                                @else
                                <div style="flex-shrink: 0; font-size: 12px; color: var(--fg-4, #94a3b8);">ยังไม่มีอาจารย์</div>
                                @endif

                                {{-- Edit (admin only) --}}
                                @if($isAdmin)
                                <div @click.stop style="flex-shrink: 0;">
                                    <button class="action-btn" title="แก้ไข" @click.stop="openEditDept({{ Js::from($dept) }})">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                    </button>
                                </div>
                                @endif

                            </div>{{-- /flex inner --}}
                            </div>{{-- /click outer --}}

                            {{-- Expanded instructor list --}}
                            <div x-show="expandedDept === {{ $dept->id }}" x-cloak
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                                x-transition:leave="transition ease-in duration-100"
                                x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                                style="border-top: 1px solid var(--border);">

                                @if($dept->instructorProfiles->count() > 0)
                                    <table style="width: 100%; border-collapse: collapse;">
                                        <thead>
                                            <tr style="background: var(--bg-2);">
                                                <th style="padding: 8px 16px 8px 56px; text-align: left; font-size: 11px; color: var(--fg-3); font-weight: 600; letter-spacing: 0.05em;">ชื่ออาจารย์</th>
                                                <th style="padding: 8px 16px; text-align: left; font-size: 11px; color: var(--fg-3); font-weight: 600; letter-spacing: 0.05em;">ตำแหน่ง</th>
                                                <th style="padding: 8px 16px; text-align: left; font-size: 11px; color: var(--fg-3); font-weight: 600; letter-spacing: 0.05em;">ประเภทบุคลากร</th>
                                                <th style="padding: 8px 16px; text-align: center; font-size: 11px; color: var(--fg-3); font-weight: 600; letter-spacing: 0.05em;">วุฒิการศึกษา</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($dept->instructorProfiles->sortBy(fn($p) => $p->user->name ?? '') as $profile)
                                                @php
                                                    $pTitle  = $profile->title ?? '';
                                                    $pDegree = $profile->academic_degree ?? '';
                                                    $pName   = $profile->user->name ?? '-';
                                                    $isDr    = $pDegree === 'ปริญญาเอก';
                                                    $isAssistant = str_contains($pTitle, 'ผู้ช่วยอาจารย์');
                                                    $abbr = match(true) {
                                                        str_contains($pTitle, 'ศาสตราจารย์') && str_contains($pTitle, 'รอง') => 'รศ.',
                                                        str_contains($pTitle, 'ศาสตราจารย์') && str_contains($pTitle, 'ผู้ช่วย') => 'ผศ.',
                                                        str_contains($pTitle, 'ศาสตราจารย์') => 'ศ.',
                                                        $pTitle === 'อาจารย์' => 'อ.',
                                                        default => null,
                                                    };
                                                    if ($isAssistant) {
                                                        $displayName = ($isDr ? 'ดร.' : ($profile->user->prefix ?? '')) . $pName;
                                                    } elseif ($abbr) {
                                                        $displayName = $abbr . ($isDr ? 'ดร.' : '') . $pName;
                                                    } else {
                                                        $displayName = ($profile->user->prefix ?? '') . $pName;
                                                    }
                                                @endphp
                                                <tr style="border-top: 1px solid var(--border); transition: background 0.1s;"
                                                    onmouseover="this.style.background='var(--bg-2)'" onmouseout="this.style.background=''">
                                                    <td style="padding: 11px 16px 11px 56px; font-size: 13px; font-weight: 600; color: var(--fg-1);">
                                                        {{ trim($displayName) }}
                                                    </td>
                                                    <td style="padding: 11px 16px; font-size: 13px; color: var(--fg-2);">{{ $pTitle ?: '-' }}</td>
                                                    <td style="padding: 11px 16px; font-size: 13px; color: var(--fg-2);">{{ $profile->employment_type ?? '-' }}</td>
                                                    <td style="padding: 11px 16px; font-size: 13px; color: var(--fg-2); text-align: center;">{{ $pDegree ?: '-' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @else
                                    <div style="padding: 20px 56px; font-size: 13px; color: var(--fg-3);">ยังไม่มีอาจารย์ในภาควิชานี้</div>
                                @endif
                            </div>

                        </div>
                    @empty
                        <div style="text-align: center; padding: 48px 20px; color: var(--fg-3);">ยังไม่มีข้อมูลภาควิชา</div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Tab: Location Types + Rooms (merged) -->
        <div x-show="activeTab === 'location_types'" x-cloak>
            <div class="card">
                <div class="card-hdr">
                    <div class="card-ttl">ห้องและสถานที่</div>
                    <div class="card-actions">
                        <button class="btn btn-secondary" @click="showImportRoomModal = true">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="17 8 12 3 7 8"/>
                                <line x1="12" y1="3" x2="12" y2="15"/>
                            </svg>
                            นำเข้าจากไฟล์
                        </button>
                        <button class="btn btn-ghost" @click="openAddRoom()">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                <line x1="12" y1="5" x2="12" y2="19"/>
                                <line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                            เพิ่มสถานที่
                        </button>
                        <button class="btn btn-primary" @click="openAddLocType()">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                <line x1="12" y1="5" x2="12" y2="19"/>
                                <line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                            เพิ่มประเภท
                        </button>
                    </div>
                </div>
                <div x-data="{ expandedType: null }" style="padding: 16px 20px; display: flex; flex-direction: column; gap: 8px;">
                    @forelse($locationTypes as $type)
                        @php
                            $statusCounts = $type->rooms->groupBy('status');
                            $activeCount = $statusCounts->get('active', collect())->count();
                            $inactiveCount = $statusCounts->get('inactive', collect())->count();
                            $maintenanceCount = $statusCounts->get('maintenance', collect())->count();
                        @endphp

                        {{-- Card wrapper --}}
                        <div style="border: 1px solid var(--border); border-radius: 8px; overflow: hidden;">

                            {{-- Header row --}}
                            <div @click="expandedType = expandedType === {{ $type->id }} ? null : {{ $type->id }}"
                                style="cursor: pointer; user-select: none; transition: background 0.15s;"
                                :style="expandedType === {{ $type->id }} ? 'background: #f0f4ff;' : 'background: #fff;'">
                            <div style="display: flex; align-items: center; gap: 16px; padding: 14px 16px;">

                                {{-- Chevron pill --}}
                                <div style="flex-shrink: 0; width: 28px; height: 28px; border-radius: 6px; display: flex; align-items: center; justify-content: center; transition: background 0.15s;"
                                    :style="expandedType === {{ $type->id }} ? 'background: var(--brand-navy);' : 'background: var(--bg-3);'">
                                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor"
                                        stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                                        style="transition: transform 0.2s;"
                                        :style="expandedType === {{ $type->id }} ? 'transform:rotate(90deg); color:#fff' : 'color: var(--fg-3)'">
                                        <polyline points="9 18 15 12 9 6"/>
                                    </svg>
                                </div>

                                {{-- Name + status --}}
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-weight: 600; font-size: 14px; color: var(--fg-1);">{{ $type->name }}</div>
                                    <div style="margin-top: 4px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                                        @if($activeCount > 0)
                                            <span style="font-size: 11px; display: inline-flex; align-items: center; gap: 5px; color: #059669;">
                                                <span style="width: 7px; height: 7px; border-radius: 50%; background: #10b981; flex-shrink: 0;"></span>ใช้งาน {{ $activeCount }} แห่ง
                                            </span>
                                        @endif
                                        @if($maintenanceCount > 0)
                                            <span style="font-size: 11px; display: inline-flex; align-items: center; gap: 5px; color: #d97706;">
                                                <span style="width: 7px; height: 7px; border-radius: 50%; background: #f59e0b; flex-shrink: 0;"></span>ซ่อมบำรุง {{ $maintenanceCount }} แห่ง
                                            </span>
                                        @endif
                                        @if($inactiveCount > 0)
                                            <span style="font-size: 11px; display: inline-flex; align-items: center; gap: 5px; color: var(--fg-3);">
                                                <span style="width: 7px; height: 7px; border-radius: 50%; background: #cbd5e1; flex-shrink: 0;"></span>ปิด {{ $inactiveCount }} แห่ง
                                            </span>
                                        @endif
                                        @if($type->rooms_count === 0)
                                            <span style="font-size: 11px; color: var(--fg-4, #94a3b8);">ยังไม่มีสถานที่</span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Total count badge --}}
                                @if($type->rooms_count > 0)
                                <div style="flex-shrink: 0; background: var(--bg-3); border-radius: 20px; padding: 4px 12px; font-size: 12px; font-weight: 700; color: var(--fg-2);">
                                    {{ $type->rooms_count }} แห่ง
                                </div>
                                @endif

                                {{-- Edit --}}
                                <div @click.stop style="flex-shrink: 0;">
                                    <button class="action-btn" title="แก้ไขประเภท"
                                        @click.stop="openEditLocType({{ Js::from(['id' => $type->id, 'name' => $type->name, 'rooms_count' => $type->rooms_count]) }})">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>{{-- /flex inner --}}
                            </div>{{-- /click outer --}}

                            {{-- Expanded room list --}}
                            <div x-show="expandedType === {{ $type->id }}" x-cloak
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                                x-transition:leave="transition ease-in duration-100"
                                x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                                style="border-top: 1px solid var(--border);">

                                @if($type->rooms->count() > 0)
                                    <table style="width: 100%; border-collapse: collapse;">
                                        <thead>
                                            <tr style="background: var(--bg-2);">
                                                <th style="padding: 8px 16px 8px 56px; text-align: left; font-size: 11px; color: var(--fg-3); font-weight: 600; letter-spacing: 0.05em;">รหัส</th>
                                                <th style="padding: 8px 16px; text-align: left; font-size: 11px; color: var(--fg-3); font-weight: 600; letter-spacing: 0.05em;">ชื่อห้อง / สถานที่</th>
                                                <th style="padding: 8px 16px; text-align: left; font-size: 11px; color: var(--fg-3); font-weight: 600; letter-spacing: 0.05em;">อาคาร</th>
                                                <th style="padding: 8px 16px; text-align: center; font-size: 11px; color: var(--fg-3); font-weight: 600; letter-spacing: 0.05em;">ความจุ</th>
                                                <th style="padding: 8px 16px; text-align: center; font-size: 11px; color: var(--fg-3); font-weight: 600; letter-spacing: 0.05em;">สถานะ</th>
                                                <th style="padding: 8px 16px; width: 48px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($type->rooms->sortBy('room_name') as $room)
                                                @php $statusMap = ['active' => ['ใช้งาน', '#059669', '#d1fae5'], 'inactive' => ['ปิดใช้งาน', '#6b7280', '#f3f4f6'], 'maintenance' => ['ซ่อมบำรุง', '#d97706', '#fef3c7']]; $s = $statusMap[$room->status] ?? $statusMap['active']; @endphp
                                                <tr style="border-top: 1px solid var(--border); transition: background 0.1s;"
                                                    onmouseover="this.style.background='var(--bg-2)'" onmouseout="this.style.background=''">
                                                    <td style="padding: 11px 16px 11px 56px; font-size: 12px; color: var(--fg-3); font-family: var(--font-mono, monospace);">{{ $room->room_code ?: '—' }}</td>
                                                    <td style="padding: 11px 16px; font-size: 13px; font-weight: 600; color: var(--fg-1);">{{ $room->room_name }}</td>
                                                    <td style="padding: 11px 16px; font-size: 13px; color: var(--fg-2);">{{ $room->building ?: '—' }}</td>
                                                    <td style="padding: 11px 16px; font-size: 13px; color: var(--fg-2); text-align: center;">{{ $room->capacity ? number_format($room->capacity) . ' คน' : '—' }}</td>
                                                    <td style="padding: 11px 16px; text-align: center;">
                                                        <span style="font-size: 11px; font-weight: 600; color: {{ $s[1] }}; background: {{ $s[2] }}; border-radius: 20px; padding: 3px 10px;">{{ $s[0] }}</span>
                                                    </td>
                                                    <td style="padding: 8px 12px; text-align: center;">
                                                        <button class="action-btn" title="แก้ไข"
                                                            @click.stop="openEditRoom({{ Js::from($room->only('id','room_code','room_name','building','capacity','location_type_id','status','address','equipment_type')) }})">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                                            </svg>
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @else
                                    <div style="padding: 20px 56px; font-size: 13px; color: var(--fg-3);">ยังไม่มีสถานที่ในประเภทนี้</div>
                                @endif

                                @if($isAdmin)
                                <div style="padding: 10px 16px; border-top: 1px solid var(--border); background: var(--bg-2);">
                                    <button type="button" class="btn btn-ghost" style="font-size: 12px; padding: 5px 12px; gap: 6px;"
                                        @click="openAddRoom(); $nextTick(() => currentRoom.location_type_id = '{{ $type->id }}')">
                                        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                                        </svg>
                                        เพิ่มสถานที่ในประเภทนี้
                                    </button>
                                </div>
                                @endif
                            </div>
                        </div>{{-- end card --}}
                    @empty
                        <div style="text-align: center; padding: 48px 20px; color: var(--fg-3);">
                            <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" style="color: #cbd5e1; margin: 0 auto 12px; display: block;">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
                            </svg>
                            ยังไม่มีข้อมูลประเภทสถานที่
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Tab: Courses -->
        <div x-show="activeTab === 'courses'" x-cloak>
            <div class="card">
                <div class="card-hdr">
                    <div class="card-ttl">คลังรายวิชา</div>
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
                                        <div style="display: flex; gap: 4px; flex-wrap: wrap; margin-top: 4px;">
                                            @if($course->default_year_level)
                                                <span style="font-size: 10px; font-weight: 600; color: var(--fg-1); background: var(--bg-2); border: 1px solid var(--border-strong, #c8cdd6); border-radius: 4px; padding: 2px 7px; white-space: nowrap;">ปี {{ $course->default_year_level }} · ภาค {{ $course->default_semester == 3 ? 'ฤดูร้อน' : $course->default_semester }}</span>
                                            @endif
                                            @if($course->capacity)
                                                <span style="font-size: 10px; font-weight: 600; color: #1a56a0; background: #e8f0fb; border: 1px solid #b3cdf0; border-radius: 4px; padding: 2px 7px; white-space: nowrap;">รับ {{ number_format($course->capacity) }} คน</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size: 13px; color: var(--fg-2);">{{ $course->department->name ?? '-' }}</div>
                                        <div style="font-size: 11px; color: var(--brand-navy); font-weight: 500;">{{ $course->curriculum->name ?? '-' }}</div>
                                    </td>
                                    <td style="text-align: center;">
                                        <div style="font-weight: 700; color: var(--fg-1);">{{ $course->credits }}</div>
                                        <div style="font-size: 10px; color: var(--fg-3);">({{ $course->lecture_hours }}-{{ $course->lab_hours }}-{{ $course->self_study_hours }})</div>
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
                                    <td colspan="7" style="text-align: center; padding: 40px; color: var(--fg-3);">ยังไม่มีข้อมูลรายวิชา</td>
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
                    <div class="card-ttl">การจัดการหลักสูตร</div>
                    @if($isAdmin)
                    <div class="card-actions">
                        <button class="btn btn-primary" @click="openAddCurriculum()">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                            เพิ่มหลักสูตรใหม่
                        </button>
                    </div>
                    @endif
                </div>

                <div x-data="{ expandedCurriculum: null }" style="padding: 16px 20px; display: flex; flex-direction: column; gap: 8px;">
                    @forelse($curriculums as $curr)
                        <div style="border: 1px solid var(--border); border-radius: 8px; overflow: hidden;">

                            {{-- Header --}}
                            <div @click="expandedCurriculum = expandedCurriculum === {{ $curr->id }} ? null : {{ $curr->id }}"
                                style="cursor: pointer; user-select: none; transition: background 0.15s;"
                                :style="expandedCurriculum === {{ $curr->id }} ? 'background: #f0f4ff;' : 'background: #fff;'">
                            <div style="display: flex; align-items: center; gap: 16px; padding: 14px 16px;">

                                {{-- Chevron --}}
                                <div style="flex-shrink: 0; width: 28px; height: 28px; border-radius: 6px; display: flex; align-items: center; justify-content: center; transition: background 0.15s;"
                                    :style="expandedCurriculum === {{ $curr->id }} ? 'background: var(--brand-navy);' : 'background: var(--bg-3);'">
                                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                                        style="transition: transform 0.2s;"
                                        :style="expandedCurriculum === {{ $curr->id }} ? 'transform:rotate(90deg); color:#fff' : 'color: var(--fg-3)'">
                                        <polyline points="9 18 15 12 9 6"/>
                                    </svg>
                                </div>

                                {{-- Name + year --}}
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-weight: 600; font-size: 14px; color: var(--fg-1);">{{ $curr->name }}</div>
                                    <div style="margin-top: 3px; font-size: 12px; color: var(--fg-3);">
                                        ปีที่เริ่มใช้: {{ $curr->effective_year ?? '-' }}
                                    </div>
                                </div>

                                {{-- Status badge --}}
                                @if($curr->is_active)
                                    <span style="flex-shrink:0; background:#e6fffa; color:#047481; border:1px solid #b2f5ea; padding:3px 10px; border-radius:99px; font-size:11px; font-weight:700;">กำลังใช้งาน</span>
                                @else
                                    <span style="flex-shrink:0; background:#f7fafc; color:#4a5568; border:1px solid #edf2f7; padding:3px 10px; border-radius:99px; font-size:11px; font-weight:700;">ปิดใช้งาน</span>
                                @endif

                                {{-- Course count --}}
                                @if($curr->courses_count > 0)
                                <div style="flex-shrink: 0; background: var(--bg-3); border-radius: 20px; padding: 4px 12px; font-size: 12px; font-weight: 700; color: var(--fg-2);">
                                    {{ $curr->courses_count }} วิชา
                                </div>
                                @else
                                <div style="flex-shrink: 0; font-size: 12px; color: var(--fg-4, #94a3b8);">ยังไม่มีวิชา</div>
                                @endif

                                {{-- Actions (admin only) --}}
                                @if($isAdmin)
                                <div @click.stop style="flex-shrink: 0; display: flex; gap: 4px;">
                                    <button class="action-btn" title="แก้ไขชื่อ/ปี" @click.stop="openEditCurriculum({{ Js::from($curr) }})">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                    </button>
                                    <button class="action-btn" title="คัดลอกหลักสูตรและรายวิชา" @click.stop="openCloneCurriculum({{ Js::from($curr) }})" style="color: var(--brand-navy);">
                                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                        </svg>
                                    </button>
                                </div>
                                @endif

                            </div>{{-- /flex inner --}}
                            </div>{{-- /click outer --}}

                            {{-- Expanded course list --}}
                            <div x-show="expandedCurriculum === {{ $curr->id }}" x-cloak
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                                x-transition:leave="transition ease-in duration-100"
                                x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                                style="border-top: 1px solid var(--border);">

                                @if($curr->courses->count() > 0)
                                    <table style="width: 100%; border-collapse: collapse;">
                                        <thead>
                                            <tr style="background: var(--bg-2);">
                                                <th style="padding: 8px 16px 8px 56px; text-align: left; font-size: 11px; color: var(--fg-3); font-weight: 600; letter-spacing: 0.05em;">รหัสวิชา</th>
                                                <th style="padding: 8px 16px; text-align: left; font-size: 11px; color: var(--fg-3); font-weight: 600; letter-spacing: 0.05em;">ชื่อวิชา</th>
                                                <th style="padding: 8px 16px; text-align: center; font-size: 11px; color: var(--fg-3); font-weight: 600; letter-spacing: 0.05em;">หน่วยกิต</th>
                                                <th style="padding: 8px 16px; text-align: center; font-size: 11px; color: var(--fg-3); font-weight: 600; letter-spacing: 0.05em;">ชั้นปี</th>
                                                <th style="padding: 8px 16px; text-align: center; font-size: 11px; color: var(--fg-3); font-weight: 600; letter-spacing: 0.05em;">ภาค</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($curr->courses as $course)
                                                <tr style="border-top: 1px solid var(--border); transition: background 0.1s;"
                                                    onmouseover="this.style.background='var(--bg-2)'" onmouseout="this.style.background=''">
                                                    <td style="padding: 11px 16px 11px 56px; font-size: 12px; color: var(--fg-3); font-family: var(--font-mono, monospace);">{{ $course->course_code }}</td>
                                                    <td style="padding: 11px 16px; font-size: 13px; font-weight: 600; color: var(--fg-1);">
                                                        {{ $course->name_th }}
                                                        @if($course->name_en)
                                                            <div style="font-size: 11px; font-weight: 400; color: var(--fg-3); margin-top: 1px;">{{ $course->name_en }}</div>
                                                        @endif
                                                    </td>
                                                    <td style="padding: 11px 16px; font-size: 13px; color: var(--fg-2); text-align: center;">{{ $course->credits }}</td>
                                                    <td style="padding: 11px 16px; font-size: 13px; color: var(--fg-2); text-align: center;">{{ $course->default_year_level ? 'ปี ' . $course->default_year_level : '-' }}</td>
                                                    <td style="padding: 11px 16px; font-size: 13px; color: var(--fg-2); text-align: center;">{{ $course->default_semester ?? '-' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @else
                                    <div style="padding: 20px 56px; font-size: 13px; color: var(--fg-3);">ยังไม่มีรายวิชาในหลักสูตรนี้</div>
                                @endif
                            </div>

                        </div>
                    @empty
                        <div style="text-align: center; padding: 48px 20px; color: var(--fg-3);">ยังไม่มีข้อมูลหลักสูตร</div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Add/Edit Modal (Department) -->
        <template x-if="showDeptModal">
            <div class="overlay" x-cloak>
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
                        method="POST" @submit="confirmDeptSave($event)" style="overflow: visible;">
                        @csrf
                        <input type="hidden" name="_method" value="PUT" :disabled="!editDeptMode">
                        <div class="modal-body" style="overflow: visible;">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label>ชื่อภาควิชา <span style="color: var(--status-conflict-fg)">*</span></label>
                                <input type="text" name="name" x-model="currentDept.name" required
                                    placeholder="เช่น ภาควิชาการพยาบาลกุมารเวชศาสตร์">
                            </div>
                            <div class="form-row">
                                <div class="form-group" style="position: relative;">
                                    <label>หัวหน้าภาควิชา</label>
                                    <template x-if="editDeptMode && deptInstructorUsers.length === 0">
                                        <p style="font-size:12px;color:var(--fg-3);margin:4px 0 8px;">ยังไม่มีอาจารย์ในภาควิชานี้</p>
                                    </template>
                                    <div style="position: relative; display: flex; gap: 6px; align-items: flex-start;">
                                        <div style="flex: 1; position: relative;">
                                            <input type="text" x-model="headSearch" @input="showHeadDropdown = true"
                                                @focus="showHeadDropdown = true" @click.away="showHeadDropdown = false"
                                                :disabled="editDeptMode && deptInstructorUsers.length === 0"
                                                placeholder="พิมพ์ชื่อเพื่อค้นหา..." autocomplete="off">
                                            <div class="search-results" x-show="showHeadDropdown" x-cloak>
                                                <template
                                                    x-for="user in deptInstructorUsers.filter(u => u.name.toLowerCase().includes(headSearch.toLowerCase()))"
                                                    :key="user.id">
                                                    <div class="search-item" @click="selectHead(user)" x-text="user.name"></div>
                                                </template>
                                                <div x-show="deptInstructorUsers.filter(u => u.name.toLowerCase().includes(headSearch.toLowerCase())).length === 0"
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
                                    <div x-show="currentDept.head_user_id && !currentDept.head_active"
                                        x-cloak
                                        style="margin-top:6px;display:flex;align-items:center;gap:6px;font-size:12px;color:oklch(45% 0.15 25);">
                                        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                        บัญชีผู้ใช้งานนี้ถูกระงับการใช้งานอยู่
                                    </div>
                                </div>
                                <div class="form-group" style="position: relative;">
                                    <label>เลขานุการภาควิชา</label>
                                    <div style="position: relative; display: flex; gap: 6px; align-items: flex-start;">
                                        <div style="flex: 1; position: relative;">
                                            <input type="text" x-model="secretarySearch"
                                                @input="showSecretaryDropdown = true" @focus="showSecretaryDropdown = true"
                                                @click.away="showSecretaryDropdown = false"
                                                :disabled="editDeptMode && deptInstructorUsers.length === 0"
                                                placeholder="พิมพ์ชื่อเพื่อค้นหา..." autocomplete="off">
                                            <div class="search-results" x-show="showSecretaryDropdown" x-cloak>
                                                <template
                                                    x-for="user in deptInstructorUsers.filter(u => u.name.toLowerCase().includes(secretarySearch.toLowerCase()))"
                                                    :key="user.id">
                                                    <div class="search-item" @click="selectSecretary(user)" x-text="user.name"></div>
                                                </template>
                                                <div x-show="deptInstructorUsers.filter(u => u.name.toLowerCase().includes(secretarySearch.toLowerCase())).length === 0"
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
                                    <div x-show="currentDept.secretary_user_id && !currentDept.secretary_active"
                                        x-cloak
                                        style="margin-top:6px;display:flex;align-items:center;gap:6px;font-size:12px;color:oklch(45% 0.15 25);">
                                        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                        บัญชีผู้ใช้งานนี้ถูกระงับการใช้งานอยู่
                                    </div>
                                    <div x-show="currentDept.head_user_id && currentDept.secretary_user_id && String(currentDept.head_user_id) === String(currentDept.secretary_user_id)"
                                        x-cloak
                                        style="margin-top: 6px; display: flex; align-items: center; gap: 6px; font-size: 12px; color: oklch(55% 0.15 60);">
                                        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                        บุคคลนี้ถูกเลือกเป็นหัวหน้าภาควิชาด้วย
                                    </div>
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
            <div class="overlay" x-cloak>
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
                                    <label>รหัสพนักงาน</label>
                                    <input type="text" name="employee_id" x-model="currentInstructor.employee_id"
                                        placeholder="เช่น 52123">
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
                                    <label>วันบรรจุ
                                        <span style="font-weight:400;color:var(--fg-3);font-size:11px;margin-left:4px;">(ค.ศ.)</span>
                                    </label>
                                    <input type="date" name="hired_at" x-model="currentInstructor.hired_at">
                                </div>
                            </div>
                            {{-- PA Ratio Section --}}
                            <div style="margin-top:20px;padding:16px;background:var(--bg-2);border-radius:var(--r-lg);border:1px solid var(--border);">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                                    <span style="font-size:13px;font-weight:700;color:var(--fg-1);font-family:var(--font-display);">สัดส่วนการะงาน</span>
                                    <span :style="paTotal === 100 ? 'color:oklch(45% 0.15 150);font-weight:700;font-size:14px;' : 'color:var(--status-conflict-fg);font-weight:700;font-size:14px;'"
                                        x-text="paTotal + '%'"></span>
                                </div>
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                    <div class="form-group" style="margin:0;">
                                        <label style="font-size:12px;">1. ด้านการสอน <span style="color:var(--status-conflict-fg)">*</span>
                                            <span x-text="'(' + getInstructorPARules().teaching.label + ')'" style="font-weight:400;color:var(--fg-3);"></span>
                                        </label>
                                        <div style="display:flex;align-items:center;gap:4px;">
                                            <input type="number" name="teaching_pct" x-model.number="currentInstructor.teaching_pct"
                                                :min="getInstructorPARules().teaching.min" :max="getInstructorPARules().teaching.max" required style="flex:1;">
                                            <span style="color:var(--fg-3);font-size:13px;">%</span>
                                        </div>
                                    </div>
                                    <div class="form-group" style="margin:0;">
                                        <label style="font-size:12px;">2. ด้านวิจัย <span style="color:var(--status-conflict-fg)">*</span>
                                            <span x-text="'(' + getInstructorPARules().research.label + ')'" style="font-weight:400;color:var(--fg-3);"></span>
                                        </label>
                                        <div style="display:flex;align-items:center;gap:4px;">
                                            <input type="number" name="research_pct" x-model.number="currentInstructor.research_pct"
                                                :min="getInstructorPARules().research.min" :max="getInstructorPARules().research.max" required style="flex:1;">
                                            <span style="color:var(--fg-3);font-size:13px;">%</span>
                                        </div>
                                    </div>
                                    <div class="form-group" style="margin:0;">
                                        <label style="font-size:12px;">3. บริการวิชาการ <span style="color:var(--status-conflict-fg)">*</span>
                                            <span x-text="'(' + getInstructorPARules().service.label + ')'" style="font-weight:400;color:var(--fg-3);"></span>
                                        </label>
                                        <div style="display:flex;align-items:center;gap:4px;">
                                            <input type="number" name="service_pct" x-model.number="currentInstructor.service_pct"
                                                :min="getInstructorPARules().service.min" :max="getInstructorPARules().service.max" required style="flex:1;">
                                            <span style="color:var(--fg-3);font-size:13px;">%</span>
                                        </div>
                                    </div>
                                    <div class="form-group" style="margin:0;">
                                        <label style="font-size:12px;">4. ศิลปวัฒนธรรม <span style="color:var(--status-conflict-fg)">*</span>
                                            <span x-text="'(' + getInstructorPARules().culture.label + ')'" style="font-weight:400;color:var(--fg-3);"></span>
                                        </label>
                                        <div style="display:flex;align-items:center;gap:4px;">
                                            <input type="number" name="culture_pct" x-model.number="currentInstructor.culture_pct"
                                                :min="getInstructorPARules().culture.min" :max="getInstructorPARules().culture.max" required style="flex:1;">
                                            <span style="color:var(--fg-3);font-size:13px;">%</span>
                                        </div>
                                    </div>
                                    <div class="form-group" style="margin:0;grid-column:span 2;">
                                        <label style="font-size:12px;">5. งานอื่นๆ มอบหมาย <span style="color:var(--status-conflict-fg)">*</span>
                                            <span x-text="'(' + getInstructorPARules().other.label + ')'" style="font-weight:400;color:var(--fg-3);"></span>
                                        </label>
                                        <div style="display:flex;align-items:center;gap:4px;">
                                            <input type="number" name="other_pct" x-model.number="currentInstructor.other_pct"
                                                :min="getInstructorPARules().other.min" :max="getInstructorPARules().other.max" required style="max-width:120px;">
                                            <span style="color:var(--fg-3);font-size:13px;">%</span>
                                        </div>
                                    </div>
                                </div>
                                <div x-show="paTotal !== 100" x-cloak
                                    style="margin-top:10px;font-size:12px;color:var(--status-conflict-fg);display:flex;align-items:center;gap:5px;">
                                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                    สัดส่วนรวมต้องเท่ากับ 100% (ขาด/เกิน <span x-text="100 - paTotal"></span>%)
                                </div>
                            </div>
                            <div style="display:none;">{{-- placeholder to close the grid that was opened above --}}
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
            <div class="overlay" x-cloak>
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
                                <button type="button" class="btn btn-ghost" x-show="editLocTypeMode"
                                    @click="confirmDelete('deleteLocTypeForm', currentLocType.name, currentLocType.rooms_count > 0 ? 'ห้อง/สถานที่ในประเภทนี้อีก ' + currentLocType.rooms_count + ' แห่งจะถูกลบออกด้วย' : null)"
                                    style="color: var(--status-conflict-fg);">
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
            <div class="overlay" x-cloak>
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
            <div class="overlay" x-cloak>
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
                            <div style="display: grid; grid-template-columns: 1fr 1fr 80px; gap: 16px; margin-bottom: 16px;">
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
                                    <label>ภาควิชาที่ดูแล <span style="font-weight:400;color:var(--fg-4);font-size:11px;">(วิชาเรียนรวมไม่ต้องระบุ)</span></label>
                                    <select name="department_id" x-model="currentCourse.department_id">
                                        <option value="">-- ไม่สังกัดภาควิชา --</option>
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
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                                <div class="form-group" style="margin-bottom:0;">
                                    <label>ประเภทวิชา <span style="color:var(--status-conflict-fg)">*</span></label>
                                    <select name="course_type" x-model="currentCourse.course_type" required>
                                        <option value="theory">ภาคทฤษฎี</option>
                                        <option value="practicum">ภาคปฏิบัติ</option>
                                        <option value="theory_practicum">ทฤษฎี + ปฏิบัติ</option>
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label>ระดับการศึกษา</label>
                                    <select name="academic_level" x-model="currentCourse.academic_level">
                                        <option value="undergraduate">ปริญญาตรี</option>
                                        <option value="graduate">บัณฑิตศึกษา</option>
                                    </select>
                                </div>
                            </div>

                            {{-- Divider: ผู้รับผิดชอบ --}}
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                                <span style="font-size:11px;font-weight:700;color:var(--fg-3);text-transform:uppercase;letter-spacing:.6px;white-space:nowrap;">ผู้รับผิดชอบ</span>
                                <div style="flex:1;height:1px;background:var(--border);"></div>
                            </div>
                            {{-- หัวหน้าวิชา: combobox (full row) --}}
                            <div class="form-group" style="position:relative;margin-bottom:16px;">
                                <label>หัวหน้าวิชา / ผู้ประสานรายวิชา <span style="color:var(--status-conflict-fg)">*</span></label>
                                <div style="position:relative;">
                                    <input type="text" x-model="courseHeadSearch"
                                        @input="showCourseHeadDropdown = true"
                                        @focus="showCourseHeadDropdown = true"
                                        @click.away="showCourseHeadDropdown = false"
                                        placeholder="พิมพ์ชื่อเพื่อค้นหา...">
                                    <div class="search-results" x-show="showCourseHeadDropdown" x-cloak style="max-height:180px;overflow-y:auto;z-index:9999;">
                                        <template x-for="user in courseHeadList.filter(u => (u.formatted_name||u.name).toLowerCase().includes(courseHeadSearch.toLowerCase()))" :key="user.id">
                                            <div class="search-item" @click="selectCourseHead(user)" x-text="user.formatted_name || user.name"></div>
                                        </template>
                                        <div x-show="courseHeadList.filter(u => (u.formatted_name||u.name).toLowerCase().includes(courseHeadSearch.toLowerCase())).length === 0"
                                            style="padding:8px 12px;color:var(--fg-4);font-size:12px;">
                                            <span x-show="courseHeadList.length === 0">ไม่มีผู้ใช้ที่มี role หัวหน้าวิชา<span x-show="currentCourse.department_id">ในภาควิชานี้</span></span>
                                            <span x-show="courseHeadList.length > 0">ไม่พบชื่อที่ค้นหา</span>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="head_instructor_id" x-model="currentCourse.head_instructor_id">
                            </div>

                            {{-- เจ้าหน้าที่: multi-select chips (full row, dropdown ไม่บัง) --}}
                            <div class="form-group" style="margin-bottom:20px;position:relative;">
                                <label>เจ้าหน้าที่ผู้ดูแลวิชา <span style="font-weight:400;color:var(--fg-4);font-size:11px;">(เลือกได้หลายคน)</span></label>
                                <template x-for="s in selectedStaff" :key="s.id">
                                    <input type="hidden" name="staff_ids[]" :value="s.id">
                                </template>
                                {{-- chips --}}
                                <div x-show="selectedStaff.length > 0"
                                    style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:6px;max-height:72px;overflow-y:auto;padding:2px 0;">
                                    <template x-for="s in selectedStaff" :key="s.id">
                                        <span style="display:inline-flex;align-items:center;gap:4px;background:var(--brand-navy);color:#fff;font-size:11px;padding:3px 10px;border-radius:99px;white-space:nowrap;">
                                            <span x-text="s.name"></span>
                                            <button type="button" @click="removeStaff(s.id)"
                                                style="background:none;border:none;cursor:pointer;color:rgba(255,255,255,0.7);padding:0;line-height:1;font-size:15px;margin-left:2px;">&times;</button>
                                        </span>
                                    </template>
                                </div>
                                {{-- search input + dropdown --}}
                                <div style="position:relative;">
                                    <input type="text" x-model="staffSearch"
                                        @input="showStaffDropdown = true"
                                        @focus="showStaffDropdown = true"
                                        @click.away="showStaffDropdown = false"
                                        placeholder="พิมพ์ชื่อเพื่อเพิ่มเจ้าหน้าที่...">
                                    <div x-show="showStaffDropdown" x-cloak
                                        style="position:absolute;top:100%;left:0;right:0;background:var(--bg-1,#fff);border:1px solid var(--border);border-radius:6px;box-shadow:0 4px 16px rgba(0,0,0,.12);z-index:9999;max-height:160px;overflow-y:auto;margin-top:2px;">
                                        <template x-for="u in filteredStaffList()" :key="u.id">
                                            <div class="search-item" @click="addStaff(u)" x-text="u.formatted_name || u.name"></div>
                                        </template>
                                        <div x-show="filteredStaffList().length === 0"
                                            style="padding:8px 12px;color:var(--fg-4);font-size:12px;">ไม่พบเจ้าหน้าที่</div>
                                    </div>
                                </div>
                            </div>

                            {{-- Divider: แผนการเรียน --}}
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                                <span style="font-size:11px;font-weight:700;color:var(--fg-3);text-transform:uppercase;letter-spacing:.6px;white-space:nowrap;">แผนการเรียน</span>
                                <div style="flex:1;height:1px;background:var(--border);"></div>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                                <div class="form-group" style="margin-bottom:0;">
                                    <label>ชั้นปีตามแผน <span style="color:var(--status-conflict-fg)">*</span></label>
                                    <select name="default_year_level" x-model="currentCourse.default_year_level" required>
                                        <option value="">-- เลือกชั้นปี --</option>
                                        <option value="1">ชั้นปีที่ 1</option>
                                        <option value="2">ชั้นปีที่ 2</option>
                                        <option value="3">ชั้นปีที่ 3</option>
                                        <option value="4">ชั้นปีที่ 4</option>
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label>ภาคเรียนตามแผน <span style="color:var(--status-conflict-fg)">*</span></label>
                                    <select name="default_semester" x-model="currentCourse.default_semester" required>
                                        <option value="">-- เลือกภาคเรียน --</option>
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
                                            style="position:absolute;z-index:9999;top:calc(100% + 4px);right:0;background:#ffffff;border:1px solid var(--border);border-radius:6px;padding:12px;box-shadow:0 4px 16px rgba(0,0,0,.15);width:220px;">
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
                                    <label>บรรยาย (ชม.) <span style="color:var(--status-conflict-fg)">*</span></label>
                                    <input type="number" name="lecture_hours" x-model="currentCourse.lecture_hours" min="0" placeholder="0" required>
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label>ปฏิบัติ / แล็บ (ชม.) <span style="color:var(--status-conflict-fg)">*</span></label>
                                    <input type="number" name="lab_hours" x-model="currentCourse.lab_hours" min="0" placeholder="0" required>
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label>ศึกษาด้วยตนเอง (ชม.) <span style="color:var(--status-conflict-fg)">*</span></label>
                                    <input type="number" name="self_study_hours" x-model="currentCourse.self_study_hours" min="0" placeholder="0" required>
                                </div>
                            </div>

                            <div class="form-group" style="margin-bottom: 20px;">
                                <label>จำนวนนักศึกษาสูงสุด (คน) <span style="color:var(--status-conflict-fg)">*</span></label>
                                <input type="number" name="capacity" x-model="currentCourse.capacity" min="1" placeholder="เช่น 240" required onwheel="this.blur()">
                            </div>

                            {{-- Practicum Rotation: theory → hidden input 0, practicum/theory_practicum → select required --}}
                            <template x-if="currentCourse.course_type === 'theory'">
                                <input type="hidden" name="requires_practicum_rotation" value="0">
                            </template>
                            <template x-if="currentCourse.course_type !== 'theory'">
                                <div class="form-group" style="margin-bottom:0;">
                                    <label>การวนกลุ่มนักศึกษาระหว่างแหล่งฝึก (Rotation) <span style="color:var(--status-conflict-fg)">*</span></label>
                                    <select name="requires_practicum_rotation" x-model="currentCourse.requires_practicum_rotation" required>
                                        <option value="">-- กรุณาเลือก --</option>
                                        <option value="1">ใช่ — ทุกกลุ่มต้องหมุนเวียนครบทุกแหล่งฝึก</option>
                                        <option value="0">ไม่ใช่ — แต่ละกลุ่มอยู่แหล่งฝึกตายตัว</option>
                                    </select>
                                    <div style="font-size:11px;color:var(--fg-3);margin-top:4px;">นักศึกษาทุกกลุ่มต้องผ่านทุกแหล่งฝึกให้ครบเท่ากัน แต่เวลาต่างกัน</div>
                                </div>
                            </template>

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
            <div class="overlay" x-cloak>
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
                                        <option value="1">กำลังใช้งาน</option>
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
            <div class="overlay" x-cloak>
                <div class="modal-center" style="max-width: 520px;"
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
                        <div class="modal-body" style="overflow: visible;">
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
                                            style="position: absolute; z-index: 9999; bottom: calc(100% + 4px); left: 0; background: #ffffff; border: 1px solid var(--border); border-radius: 6px; padding: 12px; box-shadow: 0 4px 16px rgba(0,0,0,.12); width: 220px;">
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
            <div class="overlay" x-cloak>
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
                        <div style="margin-top: 4px;">ระบบจะคัดลอกรายวิชาทั้งหมดจากหลักสูตรนี้ไปสร้างเป็นข้อมูลชุดใหม่ โดยรักษารหัสวิชาเดิมไว้</div>
                        <div style="margin-top: 6px; padding: 6px 10px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px; color: #856404; font-size: 12px;">
                            หลักสูตรและรายวิชาที่คัดลอกจะถูกตั้งเป็น <strong>ปิดใช้งาน</strong> ทั้งหมด — กรุณาเปิดใช้งานด้วยตนเองหลังจากตรวจสอบข้อมูลแล้ว
                        </div>
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
            <div class="overlay" x-cloak>
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

                            @if($locationTypes->isEmpty())
                            <div style="display: flex; align-items: flex-start; gap: 10px; padding: 10px 14px; background: oklch(97% 0.04 85); border: 1px solid oklch(82% 0.10 85); border-radius: 6px; margin-bottom: 16px;">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="oklch(50% 0.14 85)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-top: 1px;">
                                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                                </svg>
                                <div>
                                    <div style="font-size: 13px; font-weight: 600; color: oklch(45% 0.14 85);">ยังไม่มีประเภทสถานที่ในระบบ</div>
                                    <div style="font-size: 12px; color: oklch(45% 0.14 85); margin-top: 2px;">การนำเข้าจะล้มเหลวทุกแถว — กรุณาเพิ่มประเภทสถานที่ (เช่น ห้องเรียน, หอผู้ป่วย) ในแท็บ <strong>ห้องและสถานที่</strong> ก่อน</div>
                                </div>
                            </div>
                            @endif

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
                                <div style="font-size: 12px; color: var(--fg-muted); margin-top: 4px;">รองรับไฟล์ภาษาไทย (UTF-8), ไม่เกิน 5 MB</div>
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
            <div class="overlay" x-cloak>
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

                            @php
                                $courseImportIssues = [];
                                if ($curriculums->isEmpty()) $courseImportIssues[] = 'ยังไม่มีหลักสูตร — ทุกแถวใน CSV ต้องระบุ <strong>curriculum_name</strong> ที่มีอยู่ในระบบ';
                                if ($usersWithEmployeeIdCount === 0) $courseImportIssues[] = 'ยังไม่มีผู้ใช้งานที่มี Employee ID — ทุกแถวต้องระบุ <strong>head_instructor_employee_id</strong> ที่มีอยู่ในระบบ';
                            @endphp
                            @if(count($courseImportIssues) > 0)
                            <div style="display: flex; align-items: flex-start; gap: 10px; padding: 10px 14px; background: oklch(97% 0.04 85); border: 1px solid oklch(82% 0.10 85); border-radius: 6px; margin-bottom: 16px;">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="oklch(50% 0.14 85)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-top: 1px;">
                                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                                </svg>
                                <div>
                                    <div style="font-size: 13px; font-weight: 600; color: oklch(45% 0.14 85);">ต้องเตรียมข้อมูลก่อนนำเข้า</div>
                                    <ul style="font-size: 12px; color: oklch(45% 0.14 85); margin: 4px 0 0 0; padding-left: 16px;">
                                        @foreach($courseImportIssues as $issue)
                                            <li style="margin-top: 2px;">{!! $issue !!}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                            @endif

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
                                <div style="font-size: 12px; color: var(--fg-muted); margin-top: 4px;">รองรับไฟล์ภาษาไทย (UTF-8), ไม่เกิน 5 MB</div>
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
            background: var(--bg-1, #ffffff);
            border: 1px solid var(--border);
            border-radius: var(--r-md);
            box-shadow: 0 4px 16px rgba(0,0,0,.12);
            z-index: 9999;
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
        const staffUsers = {{ Js::from($staffUsers->map(fn($u) => ['id' => $u->id, 'name' => $u->formatted_name, 'formatted_name' => $u->formatted_name])) }};

        function tpssDeptConflictWarn(form, lines, opts) {
            opts = opts || {};
            var title = opts.title || 'ตำแหน่งซ้ำกับภาควิชาอื่น';
            var note  = opts.note  || 'หากดำเนินการต่อ ระบบจะย้ายตำแหน่งออกจากภาควิชาเดิมให้อัตโนมัติ';
            var lineHtml = lines.map(function(l) { return '<li style="margin-bottom:4px;">' + l + '</li>'; }).join('');
            var innerHtml = '<div style="text-align:center;">'
                + '<div style="width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,#fffbeb,#fef3c7);'
                + 'border:2px solid #fcd34d;display:flex;align-items:center;justify-content:center;'
                + 'margin:0 auto 16px;box-shadow:0 4px 16px rgba(217,119,6,0.15);">'
                + '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="#d97706" stroke-width="2"'
                + ' stroke-linecap="round" stroke-linejoin="round">'
                + '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>'
                + '<line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>'
                + '<div style="font-family:Kanit,sans-serif;font-size:19px;font-weight:700;color:#0f172a;">' + title + '</div>'
                + '<div style="font-size:13px;color:#94a3b8;margin-top:4px;">กรุณาตรวจสอบก่อนดำเนินการ</div>'
                + '<div style="background:#fffbeb;border:1.5px solid #fde68a;border-radius:10px;padding:14px 16px;margin-top:14px;text-align:left;">'
                + '<ul style="margin:0;padding-left:18px;font-size:13px;color:#92400e;line-height:1.8;">' + lineHtml + '</ul>'
                + '<div style="font-size:12px;color:#b45309;margin-top:8px;padding-top:8px;border-top:1px solid #fde68a;">'
                + note
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