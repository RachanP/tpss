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
            color_code: '#3b82f6',
            status: 'active',
            requires_practicum_rotation: false
        },
        courseHeadSearch: '',
        showCourseHeadDropdown: false,
        openAddCourse() {
            this.editCourseMode = false;
            this.currentCourse = { id: '', course_code: '', name_th: '', name_en: '', curriculum_id: '', department_id: '', head_instructor_id: '', course_type: 'theory', academic_level: 'undergraduate', default_year_level: '', default_semester: '', credits: '', lecture_hours: 0, lab_hours: 0, self_study_hours: 0, color_code: '#3b82f6', status: 'active', requires_practicum_rotation: false };
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

        confirmDelete(formId, message = 'คุณแน่ใจหรือไม่ที่จะลบข้อมูลนี้?') {
            if (typeof Swal === 'undefined') {
                if (confirm(message)) {
                    document.getElementById(formId).submit();
                }
                return;
            }

            Swal.fire({
                title: 'ยืนยันการลบ',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'ใช่, ลบเลย!',
                cancelButtonText: 'ยกเลิก',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById(formId).submit();
                }
            });
        }
    }">

        <!-- Header -->
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px;">
            <div style="flex: 1;"></div>
            <div class="tabs"
                style="display: flex; gap: 8px; background: var(--bg-2); padding: 4px; border-radius: 8px; border: 1px solid var(--border);">
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
                </button>
                <button type="button" @click="activeTab = 'departments'"
                    :class="activeTab === 'departments' ? 'btn-primary' : 'btn btn-ghost'"
                    style="padding: 8px 16px; border-radius: 6px;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
                        style="margin-right: 6px;">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    ภาควิชา
                </button>
                <button type="button" @click="activeTab = 'location_types'"
                    :class="activeTab === 'location_types' ? 'btn-primary' : 'btn btn-ghost'"
                    style="padding: 8px 16px; border-radius: 6px;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
                        style="margin-right: 6px;">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    </svg>
                    ประเภทสถานที่
                </button>
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
                <button type="button" @click="activeTab = 'curriculums'"
                    :class="activeTab === 'curriculums' ? 'btn-primary' : 'btn btn-ghost'"
                    style="padding: 8px 16px; border-radius: 6px;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
                        style="margin-right: 6px;">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                    </svg>
                    หลักสูตร
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
                                <th style="text-align: center;">จัดการ</th>
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
                                    <td style="color: var(--fg-2); font-size: 13px;">{{ $dept->secretary->name ?? '-' }}
                                    </td>
                                    <td style="text-align: center; font-weight: 600; color: var(--brand-navy);">
                                        {{ $dept->instructors_count }} คน
                                    </td>
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
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ชื่อหลักสูตร</th>
                                <th style="text-align: center;">ปีที่เริ่มใช้</th>
                                <th style="text-align: center;">จำนวนวิชา</th>
                                <th style="text-align: center;">สถานะ</th>
                                <th style="text-align: center;">จัดการ</th>
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
                        method="POST">
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
                                    <div style="position: relative;">
                                        <input type="text" x-model="headSearch" @input="showHeadDropdown = true"
                                            @focus="showHeadDropdown = true" @click.away="showHeadDropdown = false"
                                            placeholder="พิมพ์ชื่อเพื่อค้นหา..." autocomplete="off">
                                        <div class="search-results" x-show="showHeadDropdown && headSearch" x-cloak>
                                            <template
                                                x-for="user in usersList.filter(u => u.name.toLowerCase().includes(headSearch.toLowerCase()))"
                                                :key="user.id">
                                                <div class="search-item" @click="selectHead(user)" x-text="user.name">
                                                </div>
                                            </template>
                                            <div x-show="usersList.filter(u => u.name.toLowerCase().includes(headSearch.toLowerCase())).length === 0"
                                                class="search-item-empty">ไม่พบข้อมูล</div>
                                        </div>
                                    </div>
                                    <input type="hidden" name="head_user_id" x-model="currentDept.head_user_id">
                                </div>
                                <div class="form-group" style="position: relative;">
                                    <label>เลขานุการภาควิชา</label>
                                    <div style="position: relative;">
                                        <input type="text" x-model="secretarySearch"
                                            @input="showSecretaryDropdown = true" @focus="showSecretaryDropdown = true"
                                            @click.away="showSecretaryDropdown = false"
                                            placeholder="พิมพ์ชื่อเพื่อค้นหา..." autocomplete="off">
                                        <div class="search-results" x-show="showSecretaryDropdown && secretarySearch"
                                            x-cloak>
                                            <template
                                                x-for="user in usersList.filter(u => u.name.toLowerCase().includes(secretarySearch.toLowerCase()))"
                                                :key="user.id">
                                                <div class="search-item" @click="selectSecretary(user)"
                                                    x-text="user.name"></div>
                                            </template>
                                            <div x-show="usersList.filter(u => u.name.toLowerCase().includes(secretarySearch.toLowerCase())).length === 0"
                                                class="search-item-empty">ไม่พบข้อมูล</div>
                                        </div>
                                    </div>
                                    <input type="hidden" name="secretary_user_id"
                                        x-model="currentDept.secretary_user_id">
                                </div>
                            </div>
                        </div>
                        <div class="modal-foot" style="display: flex; justify-content: space-between;">
                            <div>
                                <button type="button" class="btn btn-ghost" x-show="editDeptMode" @click="confirmDelete('deleteDeptForm', 'คุณแน่ใจหรือไม่ที่จะลบข้อมูลภาควิชานี้? (หากมีข้อมูลผูกพันอยู่จะไม่สามารถลบได้)')" style="color: var(--status-conflict-fg);">
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
                    <form :action="'{{ url('admin/master-data/instructors') }}/' + currentInstructor.id" method="POST">
                        @csrf
                        <input type="hidden" name="_method" value="PUT">
                        <div class="modal-body">
                            <div
                                style="display: grid; grid-template-columns: 140px 1fr; gap: 20px; margin-bottom: 20px;">
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
                                    <input type="text" :value="currentInstructor.name" disabled
                                        style="background: var(--bg-2); cursor: not-allowed;">
                                    <p style="font-size: 11px; color: var(--fg-3); margin-top: 4px;">* ชื่อ-นามสกุล
                                        แก้ไขได้ที่หน้าจัดการผู้ใช้งานเท่านั้น</p>
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                <div class="form-group">
                                    <label>รหัสพนักงาน</label>
                                    <input type="text" name="employee_id" x-model="currentInstructor.employee_id"
                                        placeholder="รหัสพนักงาน 6 หลัก">
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
                                        <option value="ผู้ช่วยอาจารย์ (สอนภาคปฏิบัติ)">ผู้ช่วยอาจารย์ (สอนภาคปฏิบัติ)
                                        </option>
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
                                    <input type="number" name="teaching_pct"
                                        x-model.number="currentInstructor.teaching_pct" min="0" max="100">
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
                        :action="editLocTypeMode ? '{{ url('admin/master-data/location-types') }}/' + currentLocType.id : '{{ route('admin.location_types.store') }}'"
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
                                <button type="button" class="btn btn-ghost" x-show="editLocTypeMode" @click="confirmDelete('deleteLocTypeForm', 'คุณแน่ใจหรือไม่ที่จะลบประเภทสถานที่นี้?')" style="color: var(--status-conflict-fg);">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px; display: inline-block; vertical-align: middle;"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg> ลบข้อมูล
                                </button>
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="btn btn-ghost" @click="showLocTypeModal = false">ยกเลิก</button>
                                <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
                            </div>
                        </div>
                    </form>
                    <form id="deleteLocTypeForm" :action="'{{ url('admin/master-data/location-types') }}/' + currentLocType.id" method="POST" style="display: none;">
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
                        :action="editRoomMode ? '{{ url('admin/master-data/rooms') }}/' + currentRoom.id : '{{ route('admin.rooms.store') }}'"
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
                                <button type="button" class="btn btn-ghost" x-show="editRoomMode" @click="confirmDelete('deleteRoomForm', 'คุณแน่ใจหรือไม่ที่จะลบห้องนี้?')" style="color: var(--status-conflict-fg);">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px; display: inline-block; vertical-align: middle;"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg> ลบข้อมูล
                                </button>
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="btn btn-ghost" @click="showRoomModal = false">ยกเลิก</button>
                                <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
                            </div>
                        </div>
                    </form>
                    <form id="deleteRoomForm" :action="'{{ url('admin/master-data/rooms') }}/' + currentRoom.id" method="POST" style="display: none;">
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
                    <form :action="editCourseMode ? '{{ url('admin/master-data/courses') }}/' + currentCourse.id : '{{ route('admin.courses.store') }}'"
                        method="POST">
                        @csrf
                        <input type="hidden" name="_method" value="PUT" :disabled="!editCourseMode">
                        <div class="modal-body">
                            <!-- Basic Info -->
                            <div style="display: grid; grid-template-columns: 140px 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                <div class="form-group">
                                    <label>รหัสวิชา <span style="color: var(--status-conflict-fg)">*</span></label>
                                    <input type="text" name="course_code" x-model="currentCourse.course_code" required placeholder="เช่น NSBS 212">
                                </div>
                                <div class="form-group">
                                    <label>ชื่อวิชา (ไทย) <span style="color: var(--status-conflict-fg)">*</span></label>
                                    <input type="text" name="name_th" x-model="currentCourse.name_th" required placeholder="เช่น การพยาบาลเด็ก 1">
                                </div>
                                <div class="form-group">
                                    <label>ชื่อวิชา (อังกฤษ)</label>
                                    <input type="text" name="name_en" x-model="currentCourse.name_en" placeholder="เช่น Pediatric Nursing 1">
                                </div>
                            </div>

                            <!-- Org & Plan -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                <div class="form-group">
                                    <label>หลักสูตร <span style="color: var(--status-conflict-fg)">*</span></label>
                                    <select name="curriculum_id" x-model="currentCourse.curriculum_id" required>
                                        <option value="">-- เลือกหลักสูตร --</option>
                                        @foreach($curriculums as $curr)
                                            <option value="{{ $curr->id }}">{{ $curr->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>ภาควิชาที่ดูแล <span style="color: var(--status-conflict-fg)">*</span></label>
                                    <select name="department_id" x-model="currentCourse.department_id" required>
                                        <option value="">-- เลือกภาควิชา --</option>
                                        @foreach($departments as $dept)
                                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>หน่วยกิตรวม <span style="color: var(--status-conflict-fg)">*</span></label>
                                    <input type="number" name="credits" x-model="currentCourse.credits" required min="0" placeholder="เช่น 2">
                                </div>
                            </div>

                            <!-- Hours Breakdown -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 20px; background: var(--bg-2); padding: 15px; border-radius: 8px;">
                                <div class="form-group">
                                    <label>ชั่วโมงบรรยาย</label>
                                    <input type="number" name="lecture_hours" x-model="currentCourse.lecture_hours" min="0" placeholder="0">
                                </div>
                                <div class="form-group">
                                    <label>ชั่วโมงแล็บ</label>
                                    <input type="number" name="lab_hours" x-model="currentCourse.lab_hours" min="0" placeholder="0">
                                </div>
                                <div class="form-group">
                                    <label>ชั่วโมงศึกษาเอง</label>
                                    <input type="number" name="self_study_hours" x-model="currentCourse.self_study_hours" min="0" placeholder="0">
                                </div>
                            </div>

                            <!-- Level & People -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr 2fr 1fr; gap: 20px; margin-bottom: 20px;">
                                <div class="form-group">
                                    <label>ชั้นปี</label>
                                    <select name="default_year_level" x-model="currentCourse.default_year_level">
                                        <option value="">-- เลือก --</option>
                                        <option value="1">ชั้นปีที่ 1</option>
                                        <option value="2">ชั้นปีที่ 2</option>
                                        <option value="3">ชั้นปีที่ 3</option>
                                        <option value="4">ชั้นปีที่ 4</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>ภาคเรียน</label>
                                    <select name="default_semester" x-model="currentCourse.default_semester">
                                        <option value="">-- เลือก --</option>
                                        <option value="1">ภาคเรียนที่ 1</option>
                                        <option value="2">ภาคเรียนที่ 2</option>
                                        <option value="3">ภาคฤดูร้อน</option>
                                    </select>
                                </div>
                                <div class="form-group" style="position: relative;">
                                    <label>หัวหน้าวิชาเริ่มต้น</label>
                                    <div style="position: relative;">
                                        <input type="text" x-model="courseHeadSearch" @input="showCourseHeadDropdown = true"
                                            @focus="showCourseHeadDropdown = true" @click.away="showCourseHeadDropdown = false"
                                            placeholder="พิมพ์ชื่อเพื่อค้นหาอาจารย์...">
                                        <div class="search-results" x-show="showCourseHeadDropdown && courseHeadSearch" x-cloak>
                                            <template x-for="user in usersList.filter(u => u.name.toLowerCase().includes(courseHeadSearch.toLowerCase()))" :key="user.id">
                                                <div class="search-item" @click="selectCourseHead(user)" x-text="user.name"></div>
                                            </template>
                                        </div>
                                    </div>
                                    <input type="hidden" name="head_instructor_id" x-model="currentCourse.head_instructor_id">
                                </div>
                                <div class="form-group">
                                    <label>สีประจำวิชา</label>
                                    <input type="color" name="color_code" x-model="currentCourse.color_code" style="height: 38px; padding: 2px;">
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label>สถานะรายวิชา</label>
                                    <select name="status" x-model="currentCourse.status">
                                        <option value="active">เปิดสอน (Active)</option>
                                        <option value="inactive">ปิดสอน (Inactive)</option>
                                    </select>
                                </div>
                            </div>

                            <div style="margin-top: 15px;">
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                    <input type="checkbox" name="requires_practicum_rotation" x-model="currentCourse.requires_practicum_rotation" style="width: 18px; height: 18px;">
                                    <span style="font-weight: 600; color: var(--fg-1);">วิชานี้ต้องมีการวนกลุ่มนักศึกษา (Practicum Rotation)</span>
                                </label>
                            </div>
                        </div>
                        <div class="modal-foot" style="display: flex; justify-content: space-between;">
                            <div>
                                <button type="button" class="btn btn-ghost" x-show="editCourseMode" @click="confirmDelete('deleteCourseForm', 'คุณแน่ใจหรือไม่ที่จะลบรายวิชานี้? (หากมีการผูกตารางสอนแล้วจะไม่สามารถลบได้)')" style="color: var(--status-conflict-fg);">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px; display: inline-block; vertical-align: middle;"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg> ลบข้อมูล
                                </button>
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="btn btn-ghost" @click="showCourseModal = false">ยกเลิก</button>
                                <button type="submit" class="btn btn-primary">บันทึกข้อมูลวิชา</button>
                            </div>
                        </div>
                    </form>
                    <form id="deleteCourseForm" :action="'{{ url('admin/master-data/courses') }}/' + currentCourse.id" method="POST" style="display: none;">
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
                                <button type="button" class="btn btn-ghost" x-show="editCurriculumMode" @click="confirmDelete('deleteCurriculumForm', 'คุณแน่ใจหรือไม่ที่จะลบหลักสูตรนี้? (ต้องลบวิชาในหลักสูตรออกให้หมดก่อน)')" style="color: var(--status-conflict-fg);">
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
</x-app-layout>