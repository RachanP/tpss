<x-app-layout title="ข้อมูลหลักระบบ">
    @php
        $canManageMasterData = $canManageMasterData ?? $isAdmin;
        $courseFormHasErrors = old('_form') === 'course' && $errors->any();
        $courseOldPrerequisiteIds = old('prerequisite_ids', []);
        if (! is_array($courseOldPrerequisiteIds)) {
            $courseOldPrerequisiteIds = [];
        }
        $courseOldStaffIds = old('staff_ids', []);
        if (! is_array($courseOldStaffIds)) {
            $courseOldStaffIds = [];
        }
        $courseOldInstructorIds = old('instructor_ids', []);
        if (! is_array($courseOldInstructorIds)) {
            $courseOldInstructorIds = [];
        }
        $courseOldInstructorRoleIds = old('instructor_role_ids', []);
        if (! is_array($courseOldInstructorRoleIds)) {
            $courseOldInstructorRoleIds = [];
        }
        $courseFormErrorState = [
            'has_errors' => $courseFormHasErrors,
            'mode' => old('_course_form_mode', 'create'),
            'values' => [
                'id' => old('_course_id', ''),
                'route_key' => old('_course_route_key', ''),
                'course_code' => old('course_code', ''),
                'name_th' => old('name_th', ''),
                'name_en' => old('name_en', ''),
                'curriculum_id' => old('curriculum_id', ''),
                'department_id' => old('department_id', ''),
                'head_instructor_id' => old('head_instructor_id', ''),
                'default_year_level' => old('default_year_level', ''),
                'is_required' => old('is_required', '1'),
                'default_semester' => old('default_semester', ''),
                'credits' => old('credits', ''),
                'lecture_hours' => old('lecture_hours', 0),
                'lab_hours' => old('lab_hours', 0),
                'self_study_hours' => old('self_study_hours', 0),
                'capacity' => old('capacity', ''),
                'color_code' => old('color_code', '#3b82f6'),
                'status' => old('status', 'active'),
                'requires_practicum_rotation' => old('requires_practicum_rotation', '0'),
                'prerequisite_ids' => array_map('strval', $courseOldPrerequisiteIds),
                'staff_ids' => array_map('intval', $courseOldStaffIds),
                'instructor_ids' => array_map('intval', $courseOldInstructorIds),
                'instructor_role_ids' => $courseOldInstructorRoleIds,
            ],
        ];
    @endphp
    <div class="master-data-page" x-data="{
        courseFormErrorState: {{ Js::from($courseFormErrorState) }},
        activeTab: {{ Js::from($courseFormHasErrors ? 'courses' : null) }} || new URLSearchParams(window.location.search).get('tab') || 'instructors',
        tabLoading: false,
        tabSwitchMinHeight: 0,
        tabSwitchTimer: null,
        filters: {
            instructors: { keyword: '', department_id: '' },
            departments: { keyword: '' },
            location_types: { keyword: '', location_type_id: '', status: '' },
            courses: { keyword: '', department_id: '', curriculum_id: '', year_level: '', status: '' },
            curriculums: { keyword: '', is_active: '' },
            activity_types: { keyword: '', category: '' },
            student_cohorts: { keyword: '', curriculum_id: '' },
        },
        validFilterIds: {
            departments: {{ Js::from($departments->pluck('id')->map(fn($id) => (string) $id)->values()) }},
            curriculums: {{ Js::from($curriculums->pluck('id')->map(fn($id) => (string) $id)->values()) }},
            locationTypes: {{ Js::from($locationTypes->pluck('id')->map(fn($id) => (string) $id)->values()) }},
        },
        instructorsData: {{ Js::from($instructors) }},
        roomsData: {{ Js::from($rooms->map(fn($room) => $room->only('id','room_code','room_name','building','capacity','location_type_id','status','address','equipment_type'))->values()) }},
        coursesData: {{ Js::from($courses->values()) }},
        linkedInstructorId: new URLSearchParams(window.location.search).get('edit_instructor'),
        linkedDepartmentId: new URLSearchParams(window.location.search).get('edit_department'),
        linkedRoomId: new URLSearchParams(window.location.search).get('edit_room'),
        linkedCourseId: new URLSearchParams(window.location.search).get('edit_course'),
        cleanMasterDataUrl(tab = this.activeTab) {
            history.replaceState(null, '', window.location.pathname + '?tab=' + encodeURIComponent(tab));
        },
        setActiveTab(tab) {
            if (this.activeTab === tab) return;

            const scrollY = window.scrollY;
            this.tabLoading = true;
            this.tabSwitchMinHeight = Math.ceil(this.$root.getBoundingClientRect().height);
            this.activeTab = tab;

            window.clearTimeout(this.tabSwitchTimer);
            this.$nextTick(() => {
                window.requestAnimationFrame(() => {
                    const maxScrollY = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
                    window.scrollTo({ top: Math.min(scrollY, maxScrollY), behavior: 'auto' });

                    this.tabSwitchTimer = window.setTimeout(() => {
                        this.tabLoading = false;
                        this.tabSwitchMinHeight = 0;
                    }, 220);
                });
            });
        },
        openLinkedRecordFromQuery() {
            const link = [
                { id: this.linkedInstructorId, tab: 'instructors', data: this.instructorsData, open: record => this.openEditInstructor(record), label: 'อาจารย์' },
                { id: this.linkedDepartmentId, tab: 'departments', data: this.departmentsData, open: record => this.openEditDept(record), label: 'ภาควิชา' },
                { id: this.linkedRoomId, tab: 'location_types', data: this.roomsData, open: record => this.openEditRoom(record), label: 'ห้อง/สถานที่' },
                { id: this.linkedCourseId, tab: 'courses', data: this.coursesData, open: record => this.openEditCourse(record), label: 'รายวิชา' },
            ].find(item => item.id);
            if (!link) return;

            this.activeTab = link.tab;
            this.$nextTick(() => {
                const record = link.data.find(item => String(item.id) === String(link.id));
                if (record) {
                    link.open(record);
                } else if (typeof window.tpssToast === 'function') {
                    window.tpssToast('ไม่พบข้อมูล' + link.label + 'ที่ต้องการแก้ไข', 'error');
                }
                this.linkedInstructorId = null;
                this.linkedDepartmentId = null;
                this.linkedRoomId = null;
                this.linkedCourseId = null;
                this.cleanMasterDataUrl(link.tab);
            });
        },
        filterStorageKey() {
            return 'tpss.masterData.filters.' + window.location.pathname;
        },
        restoreFilters() {
            try {
                const saved = JSON.parse(sessionStorage.getItem(this.filterStorageKey()) || '{}');
                if (!saved.filters || typeof saved.filters !== 'object') return;

                Object.keys(this.filters).forEach(tab => {
                    if (!saved.filters[tab] || typeof saved.filters[tab] !== 'object') return;
                    Object.keys(this.filters[tab]).forEach(key => {
                        if (Object.prototype.hasOwnProperty.call(saved.filters[tab], key)) {
                            this.filters[tab][key] = saved.filters[tab][key] ?? '';
                        }
                    });
                });
            } catch (e) {
                sessionStorage.removeItem(this.filterStorageKey());
            }
        },
        sanitizeRestoredFilters() {
            const isKnownId = (ids, value) => !value || ids.includes(String(value));
            let changed = false;

            if (!isKnownId(this.validFilterIds.curriculums, this.filters.courses.curriculum_id)) {
                this.filters.courses.curriculum_id = '';
                changed = true;
            }
            if (!isKnownId(this.validFilterIds.departments, this.filters.courses.department_id)) {
                this.filters.courses.department_id = '';
                changed = true;
            }
            if (!isKnownId(this.validFilterIds.locationTypes, this.filters.location_types.location_type_id)) {
                this.filters.location_types.location_type_id = '';
                changed = true;
            }

            if (changed) this.persistFilters();
        },
        persistFilters() {
            try {
                sessionStorage.setItem(this.filterStorageKey(), JSON.stringify({ filters: this.filters }));
            } catch (e) {}
        },
        registerFilterPersistence($watch) {
            Object.keys(this.filters).forEach(tab => {
                Object.keys(this.filters[tab]).forEach(key => {
                    $watch('filters.' + tab + '.' + key, () => this.persistFilters());
                });
            });
        },
        normalizeSearch(value) {
            return String(value || '').toLowerCase().replace(/[\s\-_./]+/g, '');
        },
        includesText(value, keyword) {
            if (!keyword) return true;
            const source = String(value || '').toLowerCase();
            const normSource = this.normalizeSearch(source);
            // แยก keyword เป็นคำย่อยตามช่องว่าง — ทุกคำต้องเจอ (AND) ไม่ต้องเรียงติดกัน/ลำดับเดียวกัน
            const tokens = String(keyword).toLowerCase().split(/\s+/).filter(Boolean);
            return tokens.every(token =>
                source.includes(token) || normSource.includes(this.normalizeSearch(token))
            );
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
        thaiDateToIso(value) {
            const raw = String(value || '').trim();
            if (!raw) return '';

            const iso = raw.match(/^(\d{4})-(\d{2})-(\d{2})/);
            if (iso) return iso[1] + '-' + iso[2] + '-' + iso[3];

            const display = raw.match(/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/);
            if (display) {
                let year = parseInt(display[3], 10);
                if (year >= 2400) year -= 543;
                if (year < 1900 || year > 2100) return '';
                return String(year).padStart(4, '0') + '-' + display[2].padStart(2, '0') + '-' + display[1].padStart(2, '0');
            }

            const digits = raw.replace(/\D/g, '');
            if (digits.length === 8) {
                return this.thaiDateToIso(digits.slice(0, 2) + '/' + digits.slice(2, 4) + '/' + digits.slice(4));
            }

            return '';
        },
        hasAnyCourseFilter() {
            return Object.values(this.filters.courses).some(value => !!value);
        },
        courseRowMatches(row) {
            const data = row.dataset || {};

            return this.includesText(data.search, this.filters.courses.keyword)
                && (this.filters.courses.department_id === '' || data.departmentId == this.filters.courses.department_id)
                && (this.filters.courses.curriculum_id === '' || data.curriculumId == this.filters.courses.curriculum_id)
                && (this.filters.courses.year_level === '' || data.yearLevel == this.filters.courses.year_level)
                && (this.filters.courses.status === '' || data.status == this.filters.courses.status);
        },
        hasMatchingCourseRows(tbody) {
            return Array.from(tbody.children).some(row => row.dataset?.search && this.courseRowMatches(row));
        },
        init() {
            if (!this.courseFormErrorState.has_errors) return;
            this.activeTab = 'courses';
            this.editCourseMode = this.courseFormErrorState.mode === 'edit';
            this.currentCourse = { ...this.currentCourse, ...this.courseFormErrorState.values };
            this.courseHeadSearch = (this.usersList.find(u => String(u.id) === String(this.currentCourse.head_instructor_id)) || {}).formatted_name || '';
            this.selectedStaff = staffUsers.filter(u => (this.currentCourse.staff_ids || []).map(String).includes(String(u.id)));
            this.selectedCourseInstructors = courseInstructorUsers
                .filter(u => (this.currentCourse.instructor_ids || []).map(String).includes(String(u.id)))
                .map(u => ({
                    id: u.id,
                    name: u.formatted_name || u.name,
                    department: u.department || '-',
                    department_id: u.department_id || null,
                    course_role_id: (this.currentCourse.instructor_role_ids || {})[u.id] || (this.currentCourse.instructor_role_ids || {})[String(u.id)] || this.defaultCourseRoleId()
                }));
            this.showCourseModal = true;
        },
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
            is_english_passed: false,
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
                hired_at: this.thaiDateForInput(instructor.instructor_profile?.hired_at || ''),
                teaching_pct: instructor.instructor_profile?.teaching_pct ?? 0,
                research_pct: instructor.instructor_profile?.research_pct ?? 0,
                service_pct:  instructor.instructor_profile?.service_pct  ?? 0,
                culture_pct:  instructor.instructor_profile?.culture_pct  ?? 0,
                other_pct:    instructor.instructor_profile?.other_pct    ?? 0,
                is_english_passed: !!instructor.instructor_profile?.is_english_passed,
            };
            this.showInstructorModal = true;
        },
        paCriteria: {{ Js::from($paCriteria) }},
        departmentsData: {{ Js::from($departments->map(fn($d) => ['id' => $d->id, 'name' => $d->name, 'head_user_id' => $d->head_user_id, 'secretary_user_id' => $d->secretary_user_id, 'head_active' => $d->head?->is_active ?? true, 'secretary_active' => $d->secretary?->is_active ?? true, 'head' => $d->head ? ['formatted_name' => $d->head->formatted_name, 'name' => $d->head->name] : null, 'secretary' => $d->secretary ? ['formatted_name' => $d->secretary->formatted_name, 'name' => $d->secretary->name] : null, 'instructor_ids' => $d->instructorProfiles->pluck('user_id')->values()])) }},
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
        currentLocType: { id: '', name: '', is_shared: false, rooms_count: 0 },
        openAddLocType() {
            this.editLocTypeMode = false;
            this.currentLocType = { id: '', name: '', is_shared: false, rooms_count: 0 };
            this.showLocTypeModal = true;
        },
        openEditLocType(type) {
            this.editLocTypeMode = true;
            this.currentLocType = { id: type.id, name: type.name, is_shared: type.is_shared ?? false, rooms_count: type.rooms_count };
            this.showLocTypeModal = true;
        },

        // Rooms
        locTypeMap: {{ Js::from($locationTypes->mapWithKeys(fn($t) => [$t->id => (bool) $t->is_shared])) }},
        locTypeOptions: {{ Js::from($locationTypes->map(fn($t) => ['id' => $t->id, 'name' => $t->name])->values()) }},
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
        roomLocationTypeName() {
            const selected = this.locTypeOptions.find(type => String(type.id) === String(this.currentRoom.location_type_id));
            return selected ? selected.name : '-- เลือกประเภท --';
        },

        // Courses
        showCourseModal: false,
        editCourseMode: false,
        curriculumMeta: {{ Js::from($curriculums->mapWithKeys(fn($c) => [(string) $c->id => [
            'uses_year_level' => (bool) $c->uses_year_level,
            'duration_years'  => (int) $c->duration_years,
            'education_level' => $c->education_level,
        ]])) }},
        currentCourse: {
            id: '',
            route_key: '',
            course_code: '',
            name_th: '',
            name_en: '',
            curriculum_id: '',
            department_id: '',
            head_instructor_id: '',
            default_year_level: '',
            default_semester: '',
            credits: '',
            lecture_hours: 0,
            lab_hours: 0,
            self_study_hours: 0,
            capacity: '',
            color_code: '#3b82f6',
            status: 'active',
            requires_practicum_rotation: false,
            is_required: '1',
            prerequisite_ids: [],
            has_locked_offering: false
        },
        currentCurriculumUsesYearLevel() {
            const meta = this.curriculumMeta[String(this.currentCourse.curriculum_id || '')];
            return meta ? !!meta.uses_year_level : true;
        },
        currentCurriculumDurationYears() {
            const meta = this.curriculumMeta[String(this.currentCourse.curriculum_id || '')];
            const selectedYear = parseInt(this.currentCourse.default_year_level || '0', 10);
            return Math.max(meta?.duration_years || 4, selectedYear || 0);
        },
        currentCurriculumYearOptions() {
            const n = this.currentCurriculumDurationYears();
            return Array.from({ length: n }, (_, i) => String(i + 1));
        },
        normalizeCourseFormSelects({ resetInvalidYear = true } = {}) {
            ['curriculum_id', 'department_id', 'head_instructor_id', 'default_year_level', 'default_semester'].forEach(key => {
                const value = this.currentCourse[key];
                this.currentCourse[key] = value === null || value === undefined ? '' : String(value);
            });

            if (!this.currentCurriculumUsesYearLevel()) {
                if (resetInvalidYear) {
                    this.currentCourse.default_year_level = '';
                }
                return;
            }

            const selectedYear = parseInt(this.currentCourse.default_year_level || '0', 10);
            const meta = this.curriculumMeta[String(this.currentCourse.curriculum_id || '')];
            const maxYear = meta && meta.duration_years > 0 ? meta.duration_years : 4;
            if (resetInvalidYear && selectedYear > maxYear) {
                this.currentCourse.default_year_level = '';
            }
        },
        hydrateCourseForm(course) {
            const stringValue = value => value === null || value === undefined ? '' : String(value);
            const hydrated = { ...course };

            hydrated.route_key = course.course_code || '';
            hydrated.curriculum_id = stringValue(course.curriculum_id);
            hydrated.department_id = stringValue(course.department_id);
            hydrated.head_instructor_id = stringValue(course.head_instructor_id);
            hydrated.default_year_level = stringValue(course.default_year_level);
            hydrated.default_semester = stringValue(course.default_semester);
            hydrated.requires_practicum_rotation = course.requires_practicum_rotation ? '1' : '0';
            hydrated.is_required = (course.is_required ?? true) ? '1' : '0';
            hydrated.prerequisite_ids = (course.prerequisites || []).map(prerequisite => String(prerequisite.id));

            return hydrated;
        },
        courseHeadSearch: '',
        showCourseHeadDropdown: false,
        selectedStaff: [],
        staffSearch: '',
        showStaffDropdown: false,
        selectedCourseInstructors: [],
        courseInstructorSearch: '',
        showCourseInstructorDropdown: false,
        showAllCourseInstructors: false,
        openAddCourse() {
            this.editCourseMode = false;
            this.currentCourse = { id: '', route_key: '', course_code: '', name_th: '', name_en: '', curriculum_id: '', department_id: '', head_instructor_id: '', default_year_level: '', default_semester: '', credits: '', lecture_hours: 0, lab_hours: 0, self_study_hours: 0, capacity: '', color_code: '#3b82f6', status: 'active', requires_practicum_rotation: '0', is_required: '1', prerequisite_ids: [], has_locked_offering: false };
            this.courseHeadSearch = '';
            this.selectedStaff = [];
            this.staffSearch = '';
            this.selectedCourseInstructors = [];
            this.courseInstructorSearch = '';
            this.showAllCourseInstructors = false;
            this.showCourseModal = true;
            this.$nextTick(() => this.normalizeCourseFormSelects({ resetInvalidYear: false }));
        },
        openEditCourse(course) {
            this.editCourseMode = true;
            this.currentCourse = this.hydrateCourseForm(course);
            this.normalizeCourseFormSelects({ resetInvalidYear: false });
            this.courseHeadSearch = course.head_instructor ? course.head_instructor.formatted_name : '';
            this.selectedStaff = course.assigned_staff ? course.assigned_staff.map(s => ({ id: s.id, name: s.formatted_name || s.name })) : [];
            this.staffSearch = '';
            this.selectedCourseInstructors = course.instructors ? course.instructors.map(u => ({
                id: u.id,
                name: u.formatted_name || u.name,
                department: u.instructor_profile?.department?.name || '-',
                department_id: u.instructor_profile?.department_id || null,
                course_role_id: u.pivot?.course_role_id || ''
            })) : [];
            this.courseInstructorSearch = '';
            this.showAllCourseInstructors = false;
            this.showCourseModal = true;
            this.$nextTick(() => this.normalizeCourseFormSelects({ resetInvalidYear: false }));
        },
        selectCourseHead(user) {
            this.currentCourse.head_instructor_id = user.id;
            this.courseHeadSearch = user.formatted_name || user.name;
            this.showCourseHeadDropdown = false;
        },
        clearCourseHead() {
            this.currentCourse.head_instructor_id = '';
            this.courseHeadSearch = '';
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
        courseAssignmentsLocked() {
            return this.editCourseMode && !!this.currentCourse.has_locked_offering;
        },
        filteredCourseHeadList() {
            const q = this.courseHeadSearch.toLowerCase();
            return this.courseHeadList.filter(u => !q || (u.formatted_name || u.name).toLowerCase().includes(q));
        },
        defaultCourseRoleId() {
            const defaultRole = courseRoleOptions.find(r => r.name === 'อาจารย์ผู้สอน');
            return defaultRole ? defaultRole.id : (courseRoleOptions[0]?.id || '');
        },
        addCourseInstructor(user) {
            if (!this.selectedCourseInstructors.find(s => s.id === user.id)) {
                this.selectedCourseInstructors.push({
                    id: user.id,
                    name: user.formatted_name || user.name,
                    department: user.department || '-',
                    department_id: user.department_id || null,
                    course_role_id: this.defaultCourseRoleId()
                });
            }
            this.courseInstructorSearch = '';
            this.showCourseInstructorDropdown = false;
        },
        removeCourseInstructor(id) {
            this.selectedCourseInstructors = this.selectedCourseInstructors.filter(s => s.id !== id);
        },
        filteredCourseInstructorList() {
            const q = this.courseInstructorSearch.toLowerCase();
            const deptId = this.currentCourse.department_id ? String(this.currentCourse.department_id) : null;

            return courseInstructorUsers.filter(u => {
                if (this.selectedCourseInstructors.find(s => s.id === u.id)) return false;
                if (!this.showAllCourseInstructors && deptId && String(u.department_id || '') !== deptId) return false;
                return !q || (u.formatted_name || u.name).toLowerCase().includes(q) || String(u.department || '').toLowerCase().includes(q);
            });
        },

        // Curriculums
        showCurriculumModal: false,
        editCurriculumMode: false,
        showYearModeOverride: false,
        currentCurriculum: {
            id: '', name: '', effective_year: '',
            education_level: 'bachelor', duration_years: 4, uses_year_level: '1',
            total_credits_required: '', counts_service_only: '0', is_active: '1',
        },
        openAddCurriculum() {
            this.editCurriculumMode = false;
            this.showYearModeOverride = false;
            this.currentCurriculum = {
                id: '', name: '', effective_year: '',
                education_level: 'bachelor', duration_years: 4, uses_year_level: '1',
                total_credits_required: '', counts_service_only: '0', is_active: '1',
            };
            this.showCurriculumModal = true;
        },
        openEditCurriculum(curr) {
            this.editCurriculumMode = true;
            this.showYearModeOverride = false;
            this.currentCurriculum = {
                ...curr,
                is_active: curr.is_active ? '1' : '0',
                uses_year_level: curr.uses_year_level ? '1' : '0',
                counts_service_only: curr.counts_service_only ? '1' : '0',
                education_level: curr.education_level || 'bachelor',
                duration_years: curr.duration_years || 4,
                total_credits_required: curr.total_credits_required ?? '',
            };
            this.showCurriculumModal = true;
        },
        applyEducationLevelDefaults() {
            // auto-default เฉพาะตอนสร้างใหม่ — แก้ไขแล้วไม่ทับค่าเดิมที่ admin ตั้งไว้
            if (this.editCurriculumMode) return;

            const lvl = this.currentCurriculum.education_level;
            if (lvl === 'master') {
                this.currentCurriculum.duration_years = 2;
                this.currentCurriculum.uses_year_level = '0';
                if (!this.currentCurriculum.total_credits_required) {
                    this.currentCurriculum.total_credits_required = 36;
                }
            } else if (lvl === 'doctorate') {
                this.currentCurriculum.duration_years = 3;
                this.currentCurriculum.uses_year_level = '0';
                if (!this.currentCurriculum.total_credits_required) {
                    this.currentCurriculum.total_credits_required = 48;
                }
            } else {
                this.currentCurriculum.duration_years = 4;
                this.currentCurriculum.uses_year_level = '1';
            }
        },
        confirmDeleteCurriculum() {
            // Delegate to global helper to avoid quoting issues inside x-data attribute
            window.tpssConfirmCascadeCurriculum(
                this.currentCurriculum.name,
                this.currentCurriculum.courses_count || 0,
                () => this.confirmDelete('deleteCurriculumForm', this.currentCurriculum.name, 'ไม่สามารถกู้คืนได้')
            );
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
            const hiredAt = this.thaiDateToIso(this.currentInstructor.hired_at);
            const isEnglishPassed = this.currentInstructor.is_english_passed;
            const isNote1 = title === 'ผู้ช่วยอาจารย์' && degree === 'ปริญญาเอก' && hiredAt && new Date(hiredAt) < new Date('2016-10-01');
            const useInstructorRules =
                ['อาจารย์', 'ผู้ช่วยศาสตราจารย์', 'รองศาสตราจารย์', 'ศาสตราจารย์'].includes(title) ||
                isNote1 ||
                (title === 'ผู้ช่วยอาจารย์' && degree === 'ปริญญาเอก' && isEnglishPassed);

            let group = 'อาจารย์';
            if (title === 'ผู้ช่วยอาจารย์ (คลินิก)') {
                group = 'ผู้ช่วยอาจารย์_คลินิก';
            } else if (title === 'ผู้ช่วยอาจารย์ (สอนภาคปฏิบัติ)') {
                group = 'ผู้ช่วยอาจารย์_ปฏิบัติ';
            } else if (title === 'ผู้ช่วยอาจารย์' && degree === 'ปริญญาตรี') {
                group = 'ผู้ช่วยอาจารย์_ปตรี';
            } else if (useInstructorRules) {
                group = 'อาจารย์';
            } else if (title === 'ผู้ช่วยอาจารย์') {
                group = 'ผู้ช่วยอาจารย์';
            }

            const rules = this.paCriteria[group] || this.paCriteria['อาจารย์'] || {};
            return {
                teaching: { ...(rules.t || { min: 0, max: 100 }), label: this.paRuleLabel(rules.t) },
                research: { ...(rules.r || { min: 0, max: 100 }), label: this.paRuleLabel(rules.r) },
                service:  { ...(rules.s || { min: 0, max: 100 }), label: this.paRuleLabel(rules.s) },
                culture:  { ...(rules.c || { min: 0, max: 100 }), label: this.paRuleLabel(rules.c) },
                other:    { ...(rules.o || { min: 0, max: 100 }), label: this.paRuleLabel(rules.o) },
            };
        },
        paRuleLabel(rule) {
            if (!rule) return '-';
            const min = rule.min ?? 0;
            const max = rule.max ?? 100;
            if (min === 0 && max === 0) return '0%';
            if (min === 0) return '<= ' + max + '%';
            return min + '-' + max + '%';
        },
        instructorPctStyle(value, rule) {
            const val = parseInt(value) || 0;
            if (!rule || val >= rule.min && val <= rule.max) return '';
            return 'border-color: var(--status-conflict-fg); background: oklch(97% 0.02 20); color: var(--status-conflict-fg); font-weight: 700;';
        },
        showInstructorEnglishCriterion() {
            return this.currentInstructor.title === 'ผู้ช่วยอาจารย์'
                && this.currentInstructor.academic_degree === 'ปริญญาเอก'
                && this.thaiDateToIso(this.currentInstructor.hired_at)
                && new Date(this.thaiDateToIso(this.currentInstructor.hired_at)) >= new Date('2016-10-01');
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
        currentActivityType: { id: '', name: '', color_code: '#3498db', category: 'lecture', counts_toward_workload: true },
        openAddActivityType() {
            this.editActivityTypeMode = false;
            this.currentActivityType = { id: '', name: '', color_code: '#3498db', category: 'lecture', counts_toward_workload: true };
            this.showActivityTypeModal = true;
        },
        openEditActivityType(at) {
            this.editActivityTypeMode = true;
            this.currentActivityType = { counts_toward_workload: true, ...at, counts_toward_workload: !!at.counts_toward_workload };
            this.showActivityTypeModal = true;
        },
        // default ตามหมวด: other = ไม่นับ · อื่น ๆ = นับ (Admin ติ๊กแก้เองได้)
        applyWorkloadDefaultFromCategory() {
            this.currentActivityType.counts_toward_workload = this.currentActivityType.category !== 'other';
        },
        showCohortModal: false,
        editCohortMode: false,
        cohortCurriculumDurations: {{ Js::from($cohortCurriculums->mapWithKeys(fn($c) => [(string) $c->id => (int) $c->duration_years])) }},
        cohortCurriculumUsesYear: {{ Js::from($cohortCurriculums->mapWithKeys(fn($c) => [(string) $c->id => (bool) $c->uses_year_level])) }},
        currentCohort: { id: '', curriculum_id: '', year_level: '', code: '', student_count: '', note: '' },
        cohortUsesYear() {
            return !!this.cohortCurriculumUsesYear[String(this.currentCohort.curriculum_id)];
        },
        cohortYearOptions() {
            const dur = this.cohortCurriculumDurations[String(this.currentCohort.curriculum_id)] || 0;
            return Array.from({ length: dur }, (_, i) => i + 1);
        },
        openAddCohort(curriculumId = '') {
            this.editCohortMode = false;
            this.currentCohort = { id: '', curriculum_id: curriculumId ? String(curriculumId) : '', year_level: '', code: '', student_count: '', note: '' };
            this.showCohortModal = true;
        },
        openEditCohort(co) {
            this.editCohortMode = true;
            this.currentCohort = {
                id: co.id,
                curriculum_id: String(co.curriculum_id),
                year_level: co.year_level != null ? String(co.year_level) : '',
                code: co.code,
                student_count: String(co.student_count),
                note: co.note || '',
            };
            this.showCohortModal = true;
        },
        activityCategoryHelp(category = null) {
            const value = category || this.currentActivityType.category;
            const descriptions = {
                lecture: 'ใช้กับกิจกรรมภาคทฤษฎี เช่น บรรยาย สัมมนา หรือ conference และจะถูกจัดกลุ่มเป็นชั่วโมงบรรยายเมื่อทำรายงานในอนาคต',
                practicum: 'ใช้กับกิจกรรมภาคปฏิบัติ เช่น lab ฝึกปฏิบัติ แหล่งฝึก หอผู้ป่วย หรือกิจกรรมกลุ่มย่อย',
                thesis: 'ใช้กับกิจกรรมวิทยานิพนธ์ ดุษฎีนิพนธ์ หรือการกำกับงานวิจัยระดับบัณฑิตศึกษา',
                other: 'ใช้กับกิจกรรมประกอบ เช่น ปฐมนิเทศ SDL วันหยุด หรือกิจกรรมพิเศษที่ไม่ควรจัดเป็นบรรยายหรือปฏิบัติ',
            };

            return descriptions[value] || 'เลือกหมวดหมู่ให้ตรงกับลักษณะกิจกรรม เพื่อให้การกรอง สรุป และรายงานภายหลังถูกต้อง';
        },

        confirmDelete(formId, itemLabel, warnText) {
            window.tpssConfirmDelete(formId, itemLabel, warnText);
        }
    }"
    class="master-data-page"
    :class="{ 'is-tab-loading': tabLoading }"
    :style="tabSwitchMinHeight ? 'min-height: ' + tabSwitchMinHeight + 'px' : ''"
    x-init="
        if (!['departments', 'curriculums', 'student_cohorts', 'courses', 'instructors', 'location_types', 'activity_types'].includes(activeTab)) {
            activeTab = 'instructors';
            cleanMasterDataUrl(activeTab);
        }
        restoreFilters();
        sanitizeRestoredFilters();
        @if($errors->hasAny(['course_code','name_th','name_en','curriculum_id','department_id','head_instructor_id','default_year_level','default_semester','credits','lecture_hours','lab_hours','self_study_hours','capacity','color_code','status','requires_practicum_rotation','is_required','prerequisite_ids','prerequisite_ids.*']))
            activeTab = 'courses';
            editCourseMode = {{ old('course_form_id') ? 'true' : 'false' }};
            currentCourse = {
                id: '{{ old('course_form_id', '') }}',
                course_code: {{ Js::from(old('course_code', '')) }},
                name_th: {{ Js::from(old('name_th', '')) }},
                name_en: {{ Js::from(old('name_en', '')) }},
                curriculum_id: {{ Js::from(old('curriculum_id', '')) }},
                department_id: {{ Js::from(old('department_id', '')) }},
                head_instructor_id: {{ Js::from(old('head_instructor_id', '')) }},
                default_year_level: {{ Js::from(old('default_year_level', '')) }},
                default_semester: {{ Js::from(old('default_semester', '')) }},
                credits: {{ Js::from(old('credits', '')) }},
                lecture_hours: {{ Js::from(old('lecture_hours', 0)) }},
                lab_hours: {{ Js::from(old('lab_hours', 0)) }},
                self_study_hours: {{ Js::from(old('self_study_hours', 0)) }},
                capacity: {{ Js::from(old('capacity', '')) }},
                color_code: {{ Js::from(old('color_code', '#3b82f6')) }},
                status: {{ Js::from(old('status', 'active')) }},
                requires_practicum_rotation: {{ Js::from(old('requires_practicum_rotation', '0')) }},
                is_required: {{ Js::from(old('is_required', '1')) }},
                prerequisite_ids: {{ Js::from(array_map('strval', old('prerequisite_ids', []))) }},
            };
            showCourseModal = true;
        @endif
        @if(old('curriculum_form') && $errors->hasAny(['name','effective_year','is_active','education_level','duration_years','uses_year_level','total_credits_required','counts_service_only']))
            activeTab = 'curriculums';
            editCurriculumMode = {{ old('curriculum_form_id') ? 'true' : 'false' }};
            currentCurriculum = {
                id: {{ Js::from(old('curriculum_form_id', '')) }},
                name: {{ Js::from(old('name', '')) }},
                effective_year: {{ Js::from(old('effective_year', '')) }},
                education_level: {{ Js::from(old('education_level', 'bachelor')) }},
                duration_years: {{ Js::from(old('duration_years', 4)) }},
                uses_year_level: {{ Js::from(old('uses_year_level', '1')) }},
                total_credits_required: {{ Js::from(old('total_credits_required', '')) }},
                counts_service_only: {{ Js::from(old('counts_service_only', '0')) }},
                is_active: {{ Js::from(old('is_active', '1')) }},
            };
            showCurriculumModal = true;
        @endif
        @if(old('cohort_form') && $errors->hasAny(['curriculum_id','year_level','code','student_count','note']))
            activeTab = 'student_cohorts';
            showCourseModal = false;
            editCohortMode = {{ old('cohort_form_id') ? 'true' : 'false' }};
            currentCohort = {
                id: {{ Js::from(old('cohort_form_id', '')) }},
                curriculum_id: {{ Js::from(old('curriculum_id', '')) }},
                year_level: {{ Js::from(old('year_level', '')) }},
                code: {{ Js::from(old('code', '')) }},
                student_count: {{ Js::from(old('student_count', '')) }},
                note: {{ Js::from(old('note', '')) }},
            };
            showCohortModal = true;
        @endif
        @if(old('clone_curriculum_form') && $errors->hasAny(['name','effective_year']))
            activeTab = 'curriculums';
            cloneSourceCurriculum = {
                id: {{ Js::from(old('clone_curriculum_source_id', '')) }},
                name: {{ Js::from(old('clone_curriculum_source_name', '')) }},
            };
            cloneNewName = {{ Js::from(old('name', '')) }};
            cloneNewYear = {{ Js::from(old('effective_year', '')) }};
            showCloneCurriculumModal = true;
        @endif
        registerFilterPersistence($watch);
        $watch('activeTab', tab => cleanMasterDataUrl(tab));
        openLinkedRecordFromQuery();
    ">

        <!-- Header -->
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px;">
            <div style="flex: 1;"></div>
            <div class="tabs"
                style="display: flex; gap: 8px; background: var(--bg-2); padding: 4px; border-radius: 8px; border: 1px solid var(--border); overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch;">
                {{-- 1. ภาควิชา (ต้องมีก่อนสร้างหลักสูตร/อาจารย์) --}}
                <button type="button" @click="setActiveTab('departments')"
                    :class="activeTab === 'departments' ? 'btn-primary' : 'btn btn-ghost'"
                    style="padding: 8px 16px; border-radius: 6px; flex-shrink: 0; display: flex; align-items: center;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
                        style="margin-right: 6px;">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    ภาควิชา
                    @if(!$canManageMasterData)@include('shared.master_data._lock_icon')@endif
                </button>
                {{-- 2. หลักสูตร (ต้องมีก่อนสร้างรายวิชา/กลุ่ม) --}}
                <button type="button" data-testid="master-data-tab-curriculums" @click="setActiveTab('curriculums')"
                    :class="activeTab === 'curriculums' ? 'btn-primary' : 'btn btn-ghost'"
                    style="padding: 8px 16px; border-radius: 6px; flex-shrink: 0; display: flex; align-items: center;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
                        style="margin-right: 6px;">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                    </svg>
                    หลักสูตร
                    @if(!$canManageMasterData)@include('shared.master_data._lock_icon')@endif
                </button>
                {{-- 2b. กลุ่มชั้นปี (cohort — V2, ป.ตรี) --}}
                <button type="button" data-testid="master-data-tab-student-cohorts" @click="setActiveTab('student_cohorts')"
                    :class="activeTab === 'student_cohorts' ? 'btn-primary' : 'btn btn-ghost'"
                    style="padding: 8px 16px; border-radius: 6px; flex-shrink: 0; display: flex; align-items: center;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
                        style="margin-right: 6px;">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    กลุ่มนักศึกษา
                    @if(!$canManageMasterData)@include('shared.master_data._lock_icon')@endif
                </button>
                {{-- 3. รายวิชา (ต้องมีหลักสูตรก่อน) --}}
                <button type="button" data-testid="master-data-tab-courses" @click="setActiveTab('courses')"
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
                <button type="button" @click="setActiveTab('instructors')"
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
                    @if(!$canManageMasterData)@include('shared.master_data._lock_icon')@endif
                </button>
                {{-- 5. ห้องและสถานที่ (รวมประเภท) --}}
                <button type="button" @click="setActiveTab('location_types')"
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
                <button type="button" @click="setActiveTab('activity_types')"
                    :class="activeTab === 'activity_types' ? 'btn-primary' : 'btn btn-ghost'"
                    style="padding: 8px 16px; border-radius: 6px; flex-shrink: 0; display: flex; align-items: center;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
                        style="margin-right: 6px;">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                    </svg>
                    ประเภทกิจกรรม
                    @if(!$canManageMasterData)@include('shared.master_data._lock_icon')@endif
                </button>

            </div>
        </div>

        <div class="m7-tab-skeleton" x-show="tabLoading" x-transition.opacity>
            <div class="m7-skel-toolbar">
                <span></span>
                <span></span>
            </div>
            <div class="m7-skel-table">
                <span></span>
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>

        <!-- Tab: Instructors -->
        <div x-show="activeTab === 'instructors'" x-cloak>
            <div class="card">
                <div class="card-hdr">
                    <div class="card-ttl">ข้อมูลอาจารย์ผู้สอน</div>
                </div>
                <div class="m7-filter-bar">
                    <div class="m7-filter-search">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round">
                            <circle cx="11" cy="11" r="8" />
                            <line x1="21" y1="21" x2="16.65" y2="16.65" />
                        </svg>
                        <input type="text" x-model="filters.instructors.keyword" placeholder="ค้นหาชื่อ รหัส วุฒิ ประเภท หรือภาระสอน">
                    </div>
                    <select class="m7-filter-select" x-model="filters.instructors.department_id">
                        <option value="">ทุกภาควิชา</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="table-responsive instructor-table-wrap">
                    <table class="instructor-table">
                        <thead>
                            <tr>
                                <th>รหัส</th>
                                <th>ชื่อ-นามสกุล</th>
                                <th>ตำแหน่งทางวิชาการ</th>
                                <th>ภาควิชา</th>
                                @if($canManageMasterData)<th style="text-align: center;">จัดการ</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($instructors as $instructor)
                                <tr
                                    data-search="{{ Str::lower($instructor->formatted_name . ' ' . ($instructor->employee_id ?? '') . ' ' . ($instructor->email ?? '') . ' ' . ($instructor->instructorProfile->title ?? '') . ' ' . ($instructor->instructorProfile->academic_degree ?? '') . ' ' . ($instructor->instructorProfile->employment_type ?? '') . ' ' . ($instructor->instructorProfile->department->name ?? '') . ' ' . ($instructor->instructorProfile->teaching_pct ?? '') . ' ' . ($instructor->instructorProfile->research_pct ?? '') . ' ' . ($instructor->instructorProfile->service_pct ?? '') . ' ' . ($instructor->instructorProfile->culture_pct ?? '') . ' ' . ($instructor->instructorProfile->other_pct ?? '') . ' ' . ($instructor->instructorProfile->teaching_quota ?? '')) }}"
                                    data-department-id="{{ $instructor->instructorProfile->department_id ?? '' }}"
                                    x-show="includesText($el.dataset.search, filters.instructors.keyword) && (filters.instructors.department_id === '' || $el.dataset.departmentId == filters.instructors.department_id)">
                                    <td class="instructor-code-cell" data-label="รหัส" style="font-weight: 600; color: var(--fg-2);">
                                        {{ $instructor->employee_id ?? '-' }}
                                    </td>
                                    <td class="instructor-name-cell" data-label="ชื่อ-นามสกุล">
                                        <div class="instructor-name-text" style="font-weight: 600; color: var(--fg-1);">
                                            {{ $instructor->formatted_name }}
                                        </div>
                                        @if($instructor->instructorProfile && $instructor->instructorProfile->employment_type)
                                        <div class="instructor-meta-text" style="font-size: 11px; color: var(--fg-3); margin-top: 2px;">
                                            {{ $instructor->instructorProfile->employment_type }}
                                        </div>
                                        @endif
                                    </td>
                                    <td class="instructor-title-cell" data-label="ตำแหน่งทางวิชาการ" style="color: var(--fg-2); font-size: 13px;">
                                        {{ $instructor->instructorProfile->title ?? '-' }}
                                        @if($instructor->instructorProfile && $instructor->instructorProfile->academic_degree)
                                            <span
                                                style="color: var(--fg-3);">({{ $instructor->instructorProfile->academic_degree }})</span>
                                        @endif
                                    </td>
                                    <td class="instructor-department-cell" data-label="ภาควิชา" style="color: var(--fg-2); font-size: 13px;">
                                        {{ $instructor->instructorProfile->department->name ?? '-' }}
                                    </td>
                                    @if($canManageMasterData)
                                    <td class="instructor-action-cell" data-label="จัดการ" style="text-align: center;">
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
                                x-show="(filters.instructors.keyword || filters.instructors.department_id) && !Array.from($el.parentNode.children).some(tr => tr.style.display !== 'none' && tr !== $el)">
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
                    @if($canManageMasterData)
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

                <div class="m7-filter-bar">
                    <div class="m7-filter-search">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                            <circle cx="11" cy="11" r="8" />
                            <line x1="21" y1="21" x2="16.65" y2="16.65" />
                        </svg>
                        <input type="text" x-model="filters.departments.keyword" placeholder="ค้นหาภาควิชา หัวหน้า เลขานุการ หรือจำนวนอาจารย์">
                    </div>
                </div>

                <div x-data="{ expandedDept: null }" style="padding: 16px 20px; display: flex; flex-direction: column; gap: 8px;">
                    @forelse($departments as $dept)
                        <div
                            data-search="{{ Str::lower($dept->name . ' ' . ($dept->head->formatted_name ?? '') . ' ' . ($dept->secretary->formatted_name ?? '') . ' ' . $dept->instructors_count . ' คน') }}"
                            x-show="includesText($el.dataset.search, filters.departments.keyword)"
                            style="border: 1px solid color-mix(in oklch, var(--brand-navy) 32%, var(--border-strong)); border-radius: 8px; overflow: hidden;">

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
                                @if($canManageMasterData)
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
                    <div
                        x-show="filters.departments.keyword && !Array.from($el.parentNode.children).some(el => el !== $el && el.dataset && el.dataset.search && el.style.display !== 'none')"
                        x-cloak
                        style="text-align: center; padding: 40px 20px; color: var(--fg-3);">
                        ไม่พบข้อมูลที่ค้นหา
                    </div>
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
                <div class="m7-filter-bar">
                    <div class="m7-filter-search">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                            <circle cx="11" cy="11" r="8" />
                            <line x1="21" y1="21" x2="16.65" y2="16.65" />
                        </svg>
                        <input type="text" x-model="filters.location_types.keyword" placeholder="ค้นหารหัส ห้อง อาคาร ความจุ อุปกรณ์ หรือสถานะ">
                    </div>
                    <select class="m7-filter-select" x-model="filters.location_types.location_type_id">
                        <option value="">ทุกประเภทสถานที่</option>
                        @foreach($locationTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                    <select class="m7-filter-select" x-model="filters.location_types.status">
                        <option value="">ทุกสถานะ</option>
                        <option value="active">ใช้งาน</option>
                        <option value="maintenance">ซ่อมบำรุง</option>
                        <option value="inactive">ปิดใช้งาน</option>
                    </select>
                </div>
                <div x-data="{ expandedType: null }" style="padding: 16px 20px; display: flex; flex-direction: column; gap: 8px;">
                    @forelse($locationTypes as $type)
                        @php
                            $activeCount = $type->room_status_counts['active'] ?? 0;
                            $inactiveCount = $type->room_status_counts['inactive'] ?? 0;
                            $maintenanceCount = $type->room_status_counts['maintenance'] ?? 0;
                        @endphp

                        {{-- Card wrapper --}}
                        <div
                            data-location-type-id="{{ $type->id }}"
                            data-statuses="{{ $type->room_statuses }}"
                            data-search="{{ $type->room_search_haystack }}"
                            x-show="(filters.location_types.location_type_id === '' || $el.dataset.locationTypeId == filters.location_types.location_type_id) && (filters.location_types.status === '' || $el.dataset.statuses.includes(filters.location_types.status)) && includesText($el.dataset.search, filters.location_types.keyword)"
                            style="border: 1px solid color-mix(in oklch, var(--brand-navy) 32%, var(--border-strong)); border-radius: 8px; overflow: hidden;">

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
                                        @click.stop="openEditLocType({{ Js::from(['id' => $type->id, 'name' => $type->name, 'is_shared' => $type->is_shared, 'rooms_count' => $type->rooms_count]) }})">
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
                                            @foreach($type->rooms as $room)
                                                @php $statusMap = ['active' => ['ใช้งาน', '#059669', '#d1fae5'], 'inactive' => ['ปิดใช้งาน', '#6b7280', '#f3f4f6'], 'maintenance' => ['ซ่อมบำรุง', '#d97706', '#fef3c7']]; $s = $statusMap[$room->status] ?? $statusMap['active']; @endphp
                                                <tr
                                                    data-search="{{ $room->search_haystack }}"
                                                    data-status="{{ $room->status }}"
                                                    x-show="(filters.location_types.status === '' || $el.dataset.status == filters.location_types.status) && includesText($el.dataset.search, filters.location_types.keyword)"
                                                    style="border-top: 1px solid var(--border); transition: background 0.1s;"
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
                                            <tr
                                                x-show="(filters.location_types.keyword || filters.location_types.status) && !Array.from($el.parentNode.children).some(tr => tr !== $el && tr.style.display !== 'none')"
                                                x-cloak>
                                                <td colspan="6" style="text-align: center; padding: 28px; color: var(--fg-3);">ไม่พบข้อมูลที่ค้นหา</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                @else
                                    <div style="padding: 20px 56px; font-size: 13px; color: var(--fg-3);">ยังไม่มีสถานที่ในประเภทนี้</div>
                                @endif

                                @if($canManageMasterData)
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
                    <div
                        x-show="(filters.location_types.keyword || filters.location_types.location_type_id || filters.location_types.status) && !Array.from($el.parentNode.children).some(el => el !== $el && el.dataset && el.dataset.search && el.style.display !== 'none')"
                        x-cloak
                        style="text-align: center; padding: 40px 20px; color: var(--fg-3);">
                        ไม่พบข้อมูลที่ค้นหา
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab: Courses -->
        <div x-show="activeTab === 'courses'" x-cloak>
            <div class="card">
                <div class="card-hdr">
                    <div class="card-ttl">คลังรายวิชา</div>
                    <div class="card-actions">
                        <button class="btn btn-secondary" @click="showImportCourseModal = true">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="17 8 12 3 7 8"/>
                                <line x1="12" y1="3" x2="12" y2="15"/>
                            </svg>
                            นำเข้าจากไฟล์
                        </button>
                        <button class="btn btn-primary" data-testid="courses-add-button" @click="openAddCourse()">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round">
                                <line x1="12" y1="5" x2="12" y2="19" />
                                <line x1="5" y1="12" x2="19" y2="12" />
                            </svg>
                            เพิ่มวิชาใหม่
                        </button>
                    </div>
                </div>
                <div class="m7-filter-bar is-course">
                    <div class="m7-filter-search">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round">
                            <circle cx="11" cy="11" r="8" />
                            <line x1="21" y1="21" x2="16.65" y2="16.65" />
                        </svg>
                        <input type="text" data-testid="courses-search-input" x-model="filters.courses.keyword" placeholder="ค้นหารหัส ชื่อวิชา เครดิต ชั่วโมง ปี ภาค หรือความจุ">
                    </div>
                    <select class="m7-filter-select" x-model="filters.courses.department_id">
                        <option value="">ทุกภาควิชา</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                        @endforeach
                    </select>
                    <select class="m7-filter-select" x-model="filters.courses.curriculum_id">
                        <option value="">ทุกหลักสูตร</option>
                        @foreach($curriculums as $curr)
                            <option value="{{ $curr->id }}">{{ $curr->name }}</option>
                        @endforeach
                    </select>
                    <select class="m7-filter-select is-narrow" x-model="filters.courses.year_level">
                        <option value="">ทุกชั้นปี</option>
                        <option value="1">ปี 1</option>
                        <option value="2">ปี 2</option>
                        <option value="3">ปี 3</option>
                        <option value="4">ปี 4</option>
                    </select>
                    <select class="m7-filter-select is-narrow" x-model="filters.courses.status">
                        <option value="">ทุกสถานะ</option>
                        <option value="active">เปิดสอน</option>
                        <option value="inactive">ปิดสอน</option>
                    </select>
                </div>
                <div class="table-responsive">
                    <table class="high-density">
                        <thead>
                            <tr>
                                <th style="width: 100px;">รหัสวิชา</th>
                                <th>ชื่อรายวิชา (ไทย / อังกฤษ)</th>
                                <th>ภาควิชา / หลักสูตร</th>
                                <th style="text-align: center;">หน่วยกิต</th>
                                <th>หัวหน้าวิชา/ผู้ประสานรายวิชา</th>
                                <th style="text-align: center;">สถานะ</th>
                                <th style="text-align: center;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($courses as $course)
                                <tr
                                    data-search="{{ Str::lower($course->course_code . ' ' . $course->name_th . ' ' . ($course->name_en ?? '') . ' ' . ($course->headInstructor->formatted_name ?? '') . ' ' . ($course->department->name ?? '') . ' ' . ($course->curriculum->name ?? '') . ' ' . ($course->credits ?? '') . ' หน่วยกิต ' . ($course->lecture_hours ?? 0) . '-' . ($course->lab_hours ?? 0) . '-' . ($course->self_study_hours ?? 0) . ' ' . ($course->default_year_level ?? '') . ' ปี ' . ($course->default_semester ?? '') . ' ภาค ' . ($course->capacity ?? '') . ' คน ' . ($course->is_required ? 'บังคับ' : 'เลือก') . ' ' . ($course->status ?? '') . ' ' . ($course->status === 'active' ? 'เปิดสอน' : 'ปิดสอน')) }}"
                                    data-department-id="{{ $course->department_id ?? '' }}"
                                    data-curriculum-id="{{ $course->curriculum_id ?? '' }}"
                                    data-year-level="{{ $course->default_year_level ?? '' }}"
                                    data-status="{{ $course->status ?? '' }}"
                                    x-show="courseRowMatches($el)"
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
                                                <span style="font-size: 10px; font-weight: 600; color: var(--fg-1); background: var(--bg-2); border: 1px solid var(--border-strong, #c8cdd6); border-radius: 4px; padding: 2px 7px; white-space: nowrap;">ปี {{ $course->default_year_level }}</span>
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
                                        @elseif($course->status === 'active')
                                            <div style="display: inline-flex; align-items: center; gap: 6px; padding: 3px 9px; border-radius: 99px; background: color-mix(in oklch, var(--status-conflict) 14%, transparent); border: 1px solid color-mix(in oklch, var(--status-conflict) 35%, transparent); color: var(--status-conflict-fg);"
                                                title="วิชาเปิดสอนต้องระบุหัวหน้าวิชา">
                                                <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                                                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                                    <line x1="12" y1="9" x2="12" y2="13"/>
                                                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                                                </svg>
                                                <span style="font-size: 11px; font-weight: 700;">ยังไม่มีหัวหน้าวิชา</span>
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
                                        <div style="display:inline-flex;gap:6px;align-items:center;">
                                            @if($canManageMasterData && $course->has_locked_offering)
                                                <a
                                                    href="{{ route($routePrefix . '.courses.instructor_deviation', $course) }}"
                                                    class="action-btn"
                                                    data-testid="courses-deviation-button"
                                                    title="{{ ($course->has_deviation ?? false) ? 'มีการเปลี่ยนแปลงนอกเหนือจากแม่แบบ — กดดูรายละเอียด' : 'ดูการใช้งานจริงของแม่แบบผู้สอน' }}"
                                                    style="position:relative;color:var(--brand-navy);text-decoration:none;"
                                                >
                                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <line x1="18" y1="20" x2="18" y2="10"/>
                                                        <line x1="12" y1="20" x2="12" y2="4"/>
                                                        <line x1="6" y1="20" x2="6" y2="14"/>
                                                    </svg>
                                                    @if($course->has_deviation ?? false)
                                                        <span
                                                            data-testid="courses-deviation-dot"
                                                            aria-label="มีการเปลี่ยนแปลงนอกเหนือจากแม่แบบ"
                                                            style="position:absolute;top:-2px;right:-2px;width:9px;height:9px;background:var(--status-warning-fg, #d97706);border:2px solid var(--bg-1, #fff);border-radius:50%;"
                                                        ></span>
                                                    @endif
                                                </a>
                                            @endif
                                            <button class="action-btn" title="แก้ไข" @click="openEditCourse({{ Js::from($course) }})">
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
                                    <td colspan="7" style="text-align: center; padding: 40px; color: var(--fg-3);">ยังไม่มีข้อมูลรายวิชา</td>
                                </tr>
                            @endforelse
                            <tr
                                data-testid="courses-empty-state"
                                x-show="hasAnyCourseFilter() && !hasMatchingCourseRows($el.parentNode)"
                                x-cloak>
                                <td colspan="7" style="text-align: center; padding: 40px; color: var(--fg-3);">ไม่พบข้อมูลที่ค้นหา</td>
                            </tr>
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
                    @if($canManageMasterData)
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

                <div class="m7-filter-bar">
                    <div class="m7-filter-search">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                            <circle cx="11" cy="11" r="8" />
                            <line x1="21" y1="21" x2="16.65" y2="16.65" />
                        </svg>
                        <input type="text" x-model="filters.curriculums.keyword" placeholder="ค้นหาหลักสูตร ปี รหัสวิชา หน่วยกิต หรือสถานะ">
                    </div>
                    <select class="m7-filter-select is-narrow" x-model="filters.curriculums.is_active">
                        <option value="">ทุกสถานะ</option>
                        <option value="1">กำลังใช้งาน</option>
                        <option value="0">ปิดใช้งาน</option>
                    </select>
                </div>

                <div class="curriculum-list" x-data="{ expandedCurriculum: null }" style="padding: 16px 20px; display: flex; flex-direction: column; gap: 8px;">
                    @forelse($curriculums as $curr)
                        <div
                            class="curriculum-list-item"
                            data-search="{{ Str::lower($curr->name . ' ' . ($curr->effective_year ?? '') . ' ' . ($curr->is_active ? 'กำลังใช้งาน active' : 'ปิดใช้งาน inactive') . ' ' . $curr->courses_count . ' วิชา ' . $curr->courses->pluck('course_code')->join(' ') . ' ' . $curr->courses->pluck('name_th')->join(' ') . ' ' . $curr->courses->pluck('name_en')->join(' ') . ' ' . $curr->courses->pluck('credits')->join(' ') . ' ' . $curr->courses->pluck('default_year_level')->join(' ') . ' ' . $curr->courses->pluck('default_semester')->join(' ')) }}"
                            data-is-active="{{ $curr->is_active ? '1' : '0' }}"
                            x-show="includesText($el.dataset.search, filters.curriculums.keyword) && (filters.curriculums.is_active === '' || $el.dataset.isActive == filters.curriculums.is_active)"
                            style="border: 1px solid color-mix(in oklch, var(--brand-navy) 32%, var(--border-strong)); border-radius: 8px; overflow: hidden;">

                            {{-- Header --}}
                            <div @click="expandedCurriculum = expandedCurriculum === {{ $curr->id }} ? null : {{ $curr->id }}"
                                style="cursor: pointer; user-select: none; transition: background 0.15s;"
                                :style="expandedCurriculum === {{ $curr->id }} ? 'background: #f0f4ff;' : 'background: #fff;'">
                            <div class="curriculum-card-head" style="display: flex; align-items: center; gap: 16px; padding: 14px 16px;">

                                {{-- Chevron --}}
                                <div class="curriculum-card-chevron" style="flex-shrink: 0; width: 28px; height: 28px; border-radius: 6px; display: flex; align-items: center; justify-content: center; transition: background 0.15s;"
                                    :style="expandedCurriculum === {{ $curr->id }} ? 'background: var(--brand-navy);' : 'background: var(--bg-3);'">
                                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                                        style="transition: transform 0.2s;"
                                        :style="expandedCurriculum === {{ $curr->id }} ? 'transform:rotate(90deg); color:#fff' : 'color: var(--fg-3)'">
                                        <polyline points="9 18 15 12 9 6"/>
                                    </svg>
                                </div>

                                {{-- Name + year --}}
                                <div class="curriculum-card-title-block" style="flex: 1; min-width: 0;">
                                    <div class="curriculum-card-title" style="font-weight: 600; font-size: 14px; color: var(--fg-1);">{{ $curr->name }}</div>
                                    <div class="curriculum-card-year" style="margin-top: 3px; font-size: 12px; color: var(--fg-3);">
                                        ปีที่เริ่มใช้: {{ $curr->effective_year ?? '-' }}
                                    </div>
                                </div>

                                {{-- Status badge --}}
                                @if($curr->is_active)
                                    <span class="curriculum-card-status" style="flex-shrink:0; background:#e6fffa; color:#047481; border:1px solid #b2f5ea; padding:3px 10px; border-radius:99px; font-size:11px; font-weight:700;">กำลังใช้งาน</span>
                                @else
                                    <span class="curriculum-card-status" style="flex-shrink:0; background:#f7fafc; color:#4a5568; border:1px solid #edf2f7; padding:3px 10px; border-radius:99px; font-size:11px; font-weight:700;">ปิดใช้งาน</span>
                                @endif

                                {{-- Course count --}}
                                @if($curr->courses_count > 0)
                                <div class="curriculum-card-count" style="flex-shrink: 0; background: var(--bg-3); border-radius: 20px; padding: 4px 12px; font-size: 12px; font-weight: 700; color: var(--fg-2);">
                                    {{ $curr->courses_count }} วิชา
                                </div>
                                @else
                                <div class="curriculum-card-count" style="flex-shrink: 0; font-size: 12px; color: var(--fg-4, #94a3b8);">ยังไม่มีวิชา</div>
                                @endif

                                {{-- Actions (admin only) --}}
                                @if($canManageMasterData)
                                <div class="curriculum-card-actions" @click.stop style="flex-shrink: 0; display: flex; gap: 4px;">
                                    <button class="action-btn" title="แก้ไขชื่อ/ปี" data-testid="curriculum-edit-button" data-curriculum-id="{{ $curr->id }}" @click.stop="openEditCurriculum({{ Js::from($curr) }})">
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
                    <div
                        x-show="(filters.curriculums.keyword || filters.curriculums.is_active) && !Array.from($el.parentNode.children).some(el => el !== $el && el.dataset && el.dataset.search && el.style.display !== 'none')"
                        x-cloak
                        style="text-align: center; padding: 40px 20px; color: var(--fg-3);">
                        ไม่พบข้อมูลที่ค้นหา
                    </div>
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
                        :action="editDeptMode ? '{{ url($routePrefix . '/master-data/departments') }}/' + currentDept.id : '{{ route($routePrefix . '.departments.store') }}'"
                        method="POST" @submit="confirmDeptSave($event)" style="overflow: visible;">
                        @csrf
                        <input type="hidden" name="_method" value="PUT" :disabled="!editDeptMode">
                        <div class="modal-body" style="overflow: visible;">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label>ชื่อภาควิชา <span style="color: var(--status-conflict-fg)">*</span></label>
                                <input type="text" name="name" x-model="currentDept.name" required
                                    placeholder="เช่น ภาควิชาการพยาบาลกุมารเวชศาสตร์">
                            </div>
                            <div class="dept-empty-assignment" x-show="deptInstructorUsers.length === 0" x-cloak>
                                ยังไม่มีอาจารย์ในภาควิชานี้ จึงยังเลือกหัวหน้าภาควิชาและเลขานุการภาควิชาไม่ได้
                            </div>
                            <div class="form-row">
                                <div class="form-group" style="position: relative;">
                                    <label>หัวหน้าภาควิชา</label>
                                    <div style="position: relative; display: flex; gap: 6px; align-items: flex-start;">
                                        <div style="flex: 1; position: relative;">
                                            <input type="text" x-model="headSearch" @input="showHeadDropdown = true"
                                                @focus="showHeadDropdown = true" @click.away="showHeadDropdown = false"
                                                :disabled="deptInstructorUsers.length === 0"
                                                :placeholder="deptInstructorUsers.length === 0 ? 'ไม่มีอาจารย์ในภาควิชานี้' : 'พิมพ์ชื่อเพื่อค้นหา...'"
                                                autocomplete="off">
                                            <div class="search-results" x-show="showHeadDropdown && deptInstructorUsers.length > 0" x-cloak>
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
                                                :disabled="deptInstructorUsers.length === 0"
                                                :placeholder="deptInstructorUsers.length === 0 ? 'ไม่มีอาจารย์ในภาควิชานี้' : 'พิมพ์ชื่อเพื่อค้นหา...'"
                                                autocomplete="off">
                                            <div class="search-results" x-show="showSecretaryDropdown && deptInstructorUsers.length > 0" x-cloak>
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
                    <form id="deleteDeptForm" :action="'{{ url($routePrefix . '/master-data/departments') }}/' + currentDept.id" method="POST" style="display: none;">
                        @csrf
                        @method('DELETE')
                    </form>
                </div>
            </div>
        </template>

        <!-- Edit Instructor Modal -->
        <template x-if="showInstructorModal">
            <div class="overlay" x-cloak>
                <div class="modal-center instructor-modal"
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
                    <form :action="'{{ url($routePrefix . '/master-data/instructors') }}/' + currentInstructor.id" method="POST"
                        @submit="confirmInstructorSave($event)">
                        @csrf
                        <input type="hidden" name="_method" value="PUT">
                        <div class="modal-body instructor-modal-body">
                            <section class="instructor-form-section">
                                <div class="instructor-section-head">
                                    <div class="instructor-section-title">ข้อมูลบุคคล</div>
                                    <div class="instructor-section-copy">ชื่อ รหัสพนักงาน และภาควิชาที่สังกัด</div>
                                </div>
                                <div class="instructor-form-grid instructor-form-grid--identity">
                                <div class="form-group instructor-prefix-field">
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
                            </section>

                            <section class="instructor-form-section">
                                <div class="instructor-section-head">
                                    <div class="instructor-section-title">ตำแหน่งและคุณสมบัติ</div>
                                    <div class="instructor-section-copy">ตำแหน่งทางวิชาการ วุฒิการศึกษา ประเภทบุคลากร และเกณฑ์ภาษาอังกฤษ</div>
                                </div>
                                <div class="instructor-form-grid instructor-form-grid--profile">
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

                                <div class="form-group">
                                    <label>ประเภทบุคลากร <span style="color:var(--status-conflict-fg)">*</span></label>
                                    <select name="employment_type" x-model="currentInstructor.employment_type" required>
                                        <option value="พนักงานมหาวิทยาลัย">พนักงานมหาวิทยาลัย</option>
                                        <option value="ข้าราชการ">ข้าราชการ</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>วันบรรจุ</label>
                                    <x-thai-date-input name="hired_at" x-model="currentInstructor.hired_at" />
                                </div>
                                </div>
                            <div class="instructor-english-panel" x-show="showInstructorEnglishCriterion()" x-cloak>
                                <label style="font-size:13px;font-weight:700;color:var(--fg-2);margin-bottom:10px;display:block;padding-left:2px;">เกณฑ์ภาษาอังกฤษ</label>
                                <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                                    <button type="button"
                                        @click="currentInstructor.is_english_passed = false"
                                        style="padding:10px 20px;border-radius:8px;cursor:pointer;transition:all 0.2s;font-size:14px;font-weight:600;appearance:none;outline:none;border:1px solid;display:flex;align-items:center;gap:8px;"
                                        :style="!currentInstructor.is_english_passed
                                            ? 'background:#fef2f2;color:#ef4444;border-color:#fca5a5;box-shadow:0 0 0 3px rgba(239,68,68,0.1);'
                                            : 'background:white;color:var(--fg-3);border-color:var(--border);'">
                                        <svg x-show="!currentInstructor.is_english_passed" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                        <svg x-show="currentInstructor.is_english_passed" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.5;"><circle cx="12" cy="12" r="10"></circle></svg>
                                        ยังไม่ผ่านเกณฑ์
                                    </button>
                                    <button type="button"
                                        @click="currentInstructor.is_english_passed = true"
                                        style="padding:10px 20px;border-radius:8px;cursor:pointer;transition:all 0.2s;font-size:14px;font-weight:600;appearance:none;outline:none;border:1px solid;display:flex;align-items:center;gap:8px;"
                                        :style="currentInstructor.is_english_passed
                                            ? 'background:#f0fdf4;color:#10b981;border-color:#6ee7b7;box-shadow:0 0 0 3px rgba(16,185,129,0.1);'
                                            : 'background:white;color:var(--fg-3);border-color:var(--border);'">
                                        <svg x-show="currentInstructor.is_english_passed" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                        <svg x-show="!currentInstructor.is_english_passed" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.5;"><circle cx="12" cy="12" r="10"></circle></svg>
                                        ผ่านเกณฑ์แล้ว
                                    </button>
                                </div>
                            </div>
                            <input type="hidden" name="is_english_passed" :value="currentInstructor.is_english_passed ? 1 : 0">
                            </section>
                            {{-- PA Ratio Section --}}
                            <section class="instructor-form-section instructor-pa-section">
                                <div class="instructor-section-head">
                                    <div>
                                        <div class="instructor-section-title">สัดส่วนภาระงาน</div>
                                        <div class="instructor-section-copy is-left">กำหนดสัดส่วน PA ให้รวมเป็น 100% ตามเกณฑ์ตำแหน่งปัจจุบัน</div>
                                    </div>
                                    <span :style="paTotal === 100 ? 'color:oklch(45% 0.15 150);font-weight:700;font-size:14px;' : 'color:var(--status-conflict-fg);font-weight:700;font-size:14px;'"
                                        x-text="paTotal + '%'"></span>
                                </div>
                                <div class="instructor-pa-grid">
                                    <div class="form-group" style="margin:0;">
                                        <label style="font-size:12px;">1. ด้านการสอน <span style="color:var(--status-conflict-fg)">*</span>
                                            <span x-text="'(' + getInstructorPARules().teaching.label + ')'" style="font-weight:400;color:var(--fg-3);"></span>
                                        </label>
                                        <div style="display:flex;align-items:center;gap:4px;">
                                            <input type="number" name="teaching_pct" x-model.number="currentInstructor.teaching_pct"
                                                :style="instructorPctStyle(currentInstructor.teaching_pct, getInstructorPARules().teaching)"
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
                                                :style="instructorPctStyle(currentInstructor.research_pct, getInstructorPARules().research)"
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
                                                :style="instructorPctStyle(currentInstructor.service_pct, getInstructorPARules().service)"
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
                                                :style="instructorPctStyle(currentInstructor.culture_pct, getInstructorPARules().culture)"
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
                                                :style="instructorPctStyle(currentInstructor.other_pct, getInstructorPARules().other)"
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
                            </section>
                        </div>
                        <div class="modal-foot instructor-modal-foot">
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
                            <div style="display: flex; align-items: flex-start; gap: 12px; padding: 14px; background: var(--bg-2); border: 1px solid var(--border); border-radius: 4px;">
                                <input type="hidden" name="is_shared" value="0">
                                <input type="checkbox" name="is_shared" value="1" id="is_shared_check"
                                    x-model="currentLocType.is_shared"
                                    style="width: 16px; height: 16px; margin-top: 2px; flex-shrink: 0; cursor: pointer; accent-color: var(--brand-navy);">
                                <div>
                                    <label for="is_shared_check" style="font-weight: 600; cursor: pointer; display: block; margin-bottom: 2px;">สถานที่ประเภทเปิด (ใช้ร่วมกันได้)</label>
                                    <div style="font-size: 12px; color: var(--fg-3);">ระบบจะไม่ตรวจสอบการชนของห้องและความจุที่นั่ง เหมาะสำหรับสถานที่ที่หลายวิชาใช้พร้อมกันได้ เช่น ชุมชน หอผู้ป่วย ลานกิจกรรม</div>
                                </div>
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
                <div class="modal-center room-modal"
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
                        <div class="modal-body room-modal-body">
                            <section class="room-form-section">
                                <div class="room-section-head">
                                    <div class="room-section-title">ข้อมูลห้อง/สถานที่</div>
                                    <div class="room-section-copy">รหัส ชื่อ และอาคารของสถานที่ที่ใช้จัดตาราง</div>
                                </div>
                                <div class="room-form-grid room-form-grid--identity">
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
                                <div class="form-group">
                                    <label>อาคาร</label>
                                    <input type="text" name="building" x-model="currentRoom.building"
                                        placeholder="เช่น อาคาร 1">
                                </div>
                                </div>
                            </section>

                            <section class="room-form-section">
                                <div class="room-section-head">
                                    <div class="room-section-title">ประเภทและการใช้งาน</div>
                                    <div class="room-section-copy">ประเภทสถานที่ ความจุ และสถานะการใช้งาน</div>
                                </div>
                                <div class="room-form-grid room-form-grid--usage">
                                <div class="form-group">
                                    <label>ประเภทสถานที่ <span style="color: var(--status-conflict-fg)">*</span></label>
                                    <div class="room-type-dropdown" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                                        <input type="hidden" name="location_type_id" x-model="currentRoom.location_type_id">
                                        <button type="button"
                                            class="room-type-trigger"
                                            :class="currentRoom.location_type_id ? '' : 'is-placeholder'"
                                            @click="open = !open"
                                            :aria-expanded="open.toString()">
                                            <span x-text="roomLocationTypeName()"></span>
                                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="6 9 12 15 18 9"></polyline>
                                            </svg>
                                        </button>
                                        <div class="room-type-menu" x-show="open" x-cloak>
                                            <button type="button" class="room-type-option is-placeholder"
                                                @click="currentRoom.location_type_id = ''; open = false">
                                                -- เลือกประเภท --
                                            </button>
                                            @foreach($locationTypes as $type)
                                                <button type="button" class="room-type-option"
                                                    :class="String(currentRoom.location_type_id) === '{{ $type->id }}' ? 'is-selected' : ''"
                                                    @click="currentRoom.location_type_id = '{{ $type->id }}'; open = false">
                                                    {{ $type->name }}
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <template x-if="!(locTypeMap[currentRoom.location_type_id] ?? true)">
                                        <label>ความจุ (คน) <span style="color: var(--fg-3); font-weight: 400;">— ไม่บังคับ</span></label>
                                    </template>
                                    <template x-if="(locTypeMap[currentRoom.location_type_id] ?? true)">
                                        <label>ความจุ (คน)</label>
                                    </template>
                                    <input type="number" name="capacity" x-model="currentRoom.capacity" min="0"
                                        :required="(locTypeMap[currentRoom.location_type_id] ?? true)">
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
                            </section>

                            <section class="room-form-section">
                                <div class="room-section-head">
                                    <div class="room-section-title">รายละเอียดเพิ่มเติม</div>
                                    <div class="room-section-copy">อุปกรณ์ประจำห้อง และรายละเอียดที่ตั้งสำหรับแหล่งฝึกภายนอก</div>
                                </div>
                                <div class="room-form-grid room-form-grid--details">
                                    <div class="form-group">
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
                            </section>
                        </div>
                        <div class="modal-foot room-modal-foot">
                            <div>
                                <button type="button" class="btn btn-ghost" x-show="editRoomMode" @click="confirmDelete('deleteRoomForm', currentRoom.room_name, null)" style="color: var(--status-conflict-fg);">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px; display: inline-block; vertical-align: middle;"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg> ลบข้อมูล
                                </button>
                            </div>
                            <div class="room-modal-actions">
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
                <div class="modal-center course-modal"
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
                    <form :action="editCourseMode ? '{{ route($routePrefix . '.courses.update', '__COURSE__') }}'.replace('__COURSE__', encodeURIComponent(currentCourse.route_key)) : '{{ route($routePrefix . '.courses.store') }}'"
                        method="POST">
                        @csrf
                        <input type="hidden" name="_method" value="PUT" :disabled="!editCourseMode">
                        <input type="hidden" name="_form" value="course">
                        <input type="hidden" name="_course_form_mode" :value="editCourseMode ? 'edit' : 'create'">
                        <input type="hidden" name="_course_route_key" :value="currentCourse.route_key">
                        <input type="hidden" name="_course_id" :value="currentCourse.id">
                        <template x-for="staff in selectedStaff" :key="`staff-${staff.id}`">
                            <input type="hidden" name="staff_ids[]" :value="staff.id" :disabled="courseAssignmentsLocked()">
                        </template>
                        <template x-for="user in selectedCourseInstructors" :key="`course-instructor-${user.id}`">
                            <div>
                                <input type="hidden" name="instructor_ids[]" :value="user.id" :disabled="courseAssignmentsLocked()">
                                <input type="hidden" :name="`instructor_role_ids[${user.id}]`" :value="user.course_role_id" :disabled="courseAssignmentsLocked()">
                            </div>
                        </template>
                        <div class="modal-body course-modal-body">
                            @if($courseFormHasErrors)
                                <div data-testid="course-form-validation-alert" style="background:color-mix(in oklch,var(--status-conflict-fg) 8%,white);border:1px solid color-mix(in oklch,var(--status-conflict-fg) 28%,white);border-radius:6px;padding:12px 14px;margin-bottom:16px;color:var(--status-conflict-fg);font-size:13px;line-height:1.5;">
                                    <div style="font-weight:700;margin-bottom:6px;">บันทึกรายวิชาไม่สำเร็จ กรุณาตรวจสอบข้อมูลที่ระบุ</div>
                                    <ul style="margin:0;padding-left:18px;">
                                        @foreach($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <section class="course-form-section">
                                <div class="course-section-head">
                                    <div class="course-section-title">ข้อมูลรายวิชา</div>
                                    <div class="course-section-copy">รหัส ชื่อรายวิชา หลักสูตร และหน่วยงานเจ้าของข้อมูล</div>
                                </div>
                                <div class="course-form-grid course-form-grid--basic">
                                    <div class="form-group course-code-field">
                                        <label>รหัสวิชา <span style="color:var(--status-conflict-fg)">*</span></label>
                                        <input type="text" name="course_code" data-testid="course-form-code" x-model="currentCourse.course_code" required placeholder="เช่น NSBS 212">
                                        @error('course_code')
                                            @if($courseFormHasErrors)
                                                <div data-testid="course-code-error" style="margin-top:6px;color:var(--status-conflict-fg);font-size:12px;line-height:1.45;">{{ $message }}</div>
                                            @endif
                                        @enderror
                                    </div>
                                    <div class="form-group">
                                        <label>ชื่อวิชา (ไทย) <span style="color:var(--status-conflict-fg)">*</span></label>
                                        <input type="text" name="name_th" data-testid="course-form-name-th" x-model="currentCourse.name_th" required placeholder="เช่น การพยาบาลเด็ก 1">
                                    </div>
                                    <div class="form-group">
                                        <label>ชื่อวิชา (อังกฤษ)</label>
                                        <input type="text" name="name_en" x-model="currentCourse.name_en" placeholder="Pediatric Nursing 1">
                                    </div>
                                    <div class="form-group course-wide-field">
                                        <label>หลักสูตร <span style="color:var(--status-conflict-fg)">*</span></label>
                                        <select name="curriculum_id" data-testid="course-form-curriculum" x-model="currentCourse.curriculum_id" @change="normalizeCourseFormSelects({ resetInvalidYear: true })" required>
                                            <option value="">-- เลือกหลักสูตร --</option>
                                            @foreach($curriculums as $curr)
                                                <option value="{{ $curr->id }}">{{ $curr->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group course-wide-field">
                                        <label>ภาควิชาที่ดูแล <span style="font-weight:400;color:var(--fg-4);font-size:11px;">(วิชาเรียนรวมไม่ต้องระบุ)</span></label>
                                        <select name="department_id" x-model="currentCourse.department_id">
                                            <option value="">-- ไม่สังกัดภาควิชา --</option>
                                            @foreach($departments as $dept)
                                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group course-small-field">
                                        <label>หน่วยกิต <span style="color:var(--status-conflict-fg)">*</span></label>
                                        <input type="number" name="credits" x-model="currentCourse.credits" required min="0" placeholder="2">
                                    </div>
                                </div>
                            </section>
                            <section class="course-form-section course-assignment-panel">
                                <div class="course-assignment-head">
                                    <div>
                                        <div class="course-assignment-title">ผู้รับผิดชอบรายวิชา</div>
                                        <div class="course-assignment-copy">กำหนดหัวหน้าวิชา เจ้าหน้าที่ดูแล และบทบาทอาจารย์ผู้สอนจาก modal รายวิชานี้</div>
                                    </div>
                                    <span class="course-lock-badge" x-show="courseAssignmentsLocked()">ล็อกแล้ว</span>
                                </div>

                                <div class="course-lock-note" x-show="courseAssignmentsLocked()">
                                    <div>แม่แบบผู้รับผิดชอบถูกล็อกแล้ว เพราะรายวิชานี้มี Course Offering ที่อยู่ในช่วงจัดตารางหรือเผยแพร่แล้ว แก้ชุดผู้สอนในหน้า Course Offering ของรอบนั้น</div>
                                    @if($canManageMasterData ?? true)
                                        <a x-show="currentCourse.course_code"
                                            :href="'{{ url('/' . $routePrefix . '/master-data/courses') }}/' + encodeURIComponent(currentCourse.course_code) + '/instructor-deviation'"
                                            data-testid="course-deviation-link"
                                            style="display:inline-flex;align-items:center;gap:6px;margin-top:8px;font-size:0.8125rem;font-weight:600;color:var(--brand-navy);text-decoration:underline;">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M14 3h7v7"/><line x1="10" y1="14" x2="21" y2="3"/><path d="M21 14v7H3V3h7"/>
                                            </svg>
                                            ดูการใช้งานจริงของแม่แบบในแต่ละรอบเปิดสอน
                                        </a>
                                    @endif
                                </div>

                                <div class="course-assignment-grid">
                                    <div class="form-group" style="margin-bottom:0;position:relative;" @click.outside="showCourseHeadDropdown = false">
                                        <label>หัวหน้าวิชา <span x-show="currentCourse.status === 'active' && !courseAssignmentsLocked()" style="color:var(--status-conflict-fg)">*</span></label>
                                        <div class="course-combobox">
                                            <input type="text" x-model="courseHeadSearch" :disabled="courseAssignmentsLocked()"
                                                @focus="showCourseHeadDropdown = !courseAssignmentsLocked()"
                                                @input="showCourseHeadDropdown = !courseAssignmentsLocked()"
                                                placeholder="พิมพ์ชื่อเพื่อค้นหา...">
                                            <button type="button" x-show="currentCourse.head_instructor_id && !courseAssignmentsLocked()" @click="clearCourseHead()" class="course-clear-btn">×</button>
                                            <div x-show="showCourseHeadDropdown && !courseAssignmentsLocked()" x-cloak class="course-combobox-menu">
                                                <template x-for="user in filteredCourseHeadList()" :key="user.id">
                                                    <button type="button" class="course-combobox-item" @click="selectCourseHead(user)">
                                                        <span x-text="user.formatted_name || user.name"></span>
                                                    </button>
                                                </template>
                                                <div class="course-combobox-empty" x-show="filteredCourseHeadList().length === 0">ไม่พบหัวหน้าวิชาที่ตรงกัน</div>
                                            </div>
                                        </div>
                                        <input type="hidden" name="head_instructor_id" x-model="currentCourse.head_instructor_id" :disabled="courseAssignmentsLocked()">
                                        @error('head_instructor_id')
                                            @if($courseFormHasErrors)
                                                <div style="margin-top:6px;color:var(--status-conflict-fg);font-size:12px;line-height:1.45;">{{ $message }}</div>
                                            @endif
                                        @enderror
                                    </div>

                                    <div class="form-group" style="margin-bottom:0;position:relative;" @click.outside="showStaffDropdown = false">
                                        <label>เจ้าหน้าที่ดูแลรายวิชา</label>
                                        <div class="course-combobox">
                                            <input type="text" x-model="staffSearch" :disabled="courseAssignmentsLocked()"
                                                @focus="showStaffDropdown = !courseAssignmentsLocked()"
                                                @input="showStaffDropdown = !courseAssignmentsLocked()"
                                                placeholder="พิมพ์ชื่อเพื่อค้นหา...">
                                            <div x-show="showStaffDropdown && !courseAssignmentsLocked()" x-cloak class="course-combobox-menu">
                                                <template x-for="user in filteredStaffList()" :key="user.id">
                                                    <button type="button" class="course-combobox-item" @click="addStaff(user)">
                                                        <span x-text="user.formatted_name || user.name"></span>
                                                    </button>
                                                </template>
                                                <div class="course-combobox-empty" x-show="filteredStaffList().length === 0">ไม่พบเจ้าหน้าที่ที่ตรงกัน</div>
                                            </div>
                                        </div>
                                        <div class="course-chip-list" x-show="selectedStaff.length > 0">
                                            <template x-for="staff in selectedStaff" :key="staff.id">
                                                <span class="course-chip">
                                                    <span x-text="staff.name"></span>
                                                    <button type="button" x-show="!courseAssignmentsLocked()" @click="removeStaff(staff.id)" aria-label="ลบเจ้าหน้าที่">×</button>
                                                </span>
                                            </template>
                                        </div>
                                        <div class="course-inline-empty" x-show="selectedStaff.length === 0">ยังไม่มีเจ้าหน้าที่ดูแล</div>
                                    </div>
                                </div>

                                <div class="course-instructor-block">
                                    <div class="course-instructor-head">
                                        <div>
                                            <label style="display:block;margin-bottom:3px;">อาจารย์ผู้สอน</label>
                                            <div class="course-assignment-copy">เลือกอาจารย์และกำหนดตำแหน่งในรายวิชา</div>
                                        </div>
                                        <span class="course-count-badge" x-text="selectedCourseInstructors.length + ' คน'"></span>
                                    </div>

                                    <div class="course-instructor-search" x-show="!courseAssignmentsLocked()" @click.outside="showCourseInstructorDropdown = false">
                                        <input type="text" x-model="courseInstructorSearch"
                                            @focus="showCourseInstructorDropdown = true"
                                            @input="showCourseInstructorDropdown = true"
                                            placeholder="ค้นหาชื่ออาจารย์หรือภาควิชา...">
                                        <div class="course-scope-toggle" x-show="currentCourse.department_id">
                                            <button type="button" @click="showAllCourseInstructors = false" :class="!showAllCourseInstructors ? 'is-active' : ''">ภาควิชานี้</button>
                                            <button type="button" @click="showAllCourseInstructors = true" :class="showAllCourseInstructors ? 'is-active' : ''">ทั้งหมด</button>
                                        </div>
                                        <div x-show="showCourseInstructorDropdown" x-cloak class="course-combobox-menu">
                                            <template x-for="user in filteredCourseInstructorList()" :key="user.id">
                                                <button type="button" class="course-combobox-item" @click="addCourseInstructor(user)">
                                                    <span>
                                                        <strong x-text="user.formatted_name || user.name"></strong>
                                                        <small x-text="user.department || '-'"></small>
                                                    </span>
                                                </button>
                                            </template>
                                            <div class="course-combobox-empty" x-show="filteredCourseInstructorList().length === 0">ไม่พบอาจารย์ที่ตรงกัน</div>
                                        </div>
                                    </div>

                                    <div class="course-instructor-list" x-show="selectedCourseInstructors.length > 0">
                                        <template x-for="user in selectedCourseInstructors" :key="user.id">
                                            <div class="course-instructor-row">
                                                <div class="course-instructor-name">
                                                    <strong x-text="user.name"></strong>
                                                    <span x-text="user.department"></span>
                                                </div>
                                                <select x-model="user.course_role_id" :disabled="courseAssignmentsLocked()">
                                                    <template x-for="role in courseRoleOptions" :key="role.id">
                                                        <option :value="role.id" x-text="role.name"></option>
                                                    </template>
                                                </select>
                                                <button type="button" x-show="!courseAssignmentsLocked()" class="course-remove-btn" @click="removeCourseInstructor(user.id)" aria-label="ลบอาจารย์">
                                                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                                                        <line x1="18" y1="6" x2="6" y2="18"/>
                                                        <line x1="6" y1="6" x2="18" y2="18"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                    <div class="course-inline-empty" x-show="selectedCourseInstructors.length === 0">ยังไม่มีอาจารย์ผู้สอนในรายวิชานี้</div>
                                </div>
                            </section>

                            <section class="course-form-section">
                                <div class="course-section-head">
                                    <div class="course-section-title">แผนการเรียนและสถานะ</div>
                                    <div class="course-section-copy">กำหนดภาคเรียน ประเภทวิชา สถานะ และสีที่ใช้ในตาราง</div>
                                </div>
                                <div class="course-form-grid course-form-grid--plan">
                                    <div class="form-group" x-show="currentCurriculumUsesYearLevel()">
                                        <label>ชั้นปีตามแผน <span style="color:var(--status-conflict-fg)">*</span></label>
                                        <select name="default_year_level" x-model="currentCourse.default_year_level" x-effect="$el.value = currentCourse.default_year_level || ''" :required="currentCurriculumUsesYearLevel()">
                                            <option value="">-- เลือกชั้นปี --</option>
                                            @for($yearOption = 1; $yearOption <= 10; $yearOption++)
                                                <option value="{{ $yearOption }}" :disabled="{{ $yearOption }} > currentCurriculumDurationYears()">ชั้นปีที่ {{ $yearOption }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                    <div class="form-group" x-show="!currentCurriculumUsesYearLevel()">
                                        <label>ชั้นปีตามแผน</label>
                                        <div class="course-disabled-note">
                                            ไม่ใช้ระบบชั้นปี — กำหนดผ่าน prerequisite/หน่วยกิตสะสม
                                        </div>
                                    </div>
                                    {{-- V2: วิชาเปิดทั้งปี — ตัด field ภาคเรียนตามแผนออก (เทอมเป็นป้ายของแต่ละ slot) --}}
                                    <div class="form-group">
                                        <label>ประเภทวิชา</label>
                                        <select name="is_required" x-model="currentCourse.is_required">
                                            <option value="1">วิชาบังคับ</option>
                                            <option value="0">วิชาเลือก</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>สถานะรายวิชา</label>
                                        <select name="status" x-model="currentCourse.status">
                                            <option value="active">เปิดสอน</option>
                                            <option value="inactive">ปิดสอน</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>สีประจำวิชา</label>
                                        <div x-data="{ open: false }" style="position: relative;">
                                            <button type="button" @click="open = !open"
                                                style="width:100%;height:38px;border:1px solid var(--border);border-radius:4px;display:flex;align-items:center;gap:10px;padding:0 10px;background:var(--surface);cursor:pointer;">
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
                            </section>

                            <section class="course-form-section">
                                <div class="course-section-head">
                                    <div class="course-section-title">ชั่วโมงและเงื่อนไข</div>
                                    <div class="course-section-copy">ชั่วโมงสอน จำนวนรับ การหมุนเวียนแหล่งฝึก และ prerequisite</div>
                                </div>
                                <div class="course-form-grid course-form-grid--hours">
                                    <div class="form-group">
                                        <label>บรรยาย (ชม.) <span style="color:var(--status-conflict-fg)">*</span></label>
                                        <input type="number" name="lecture_hours" x-model="currentCourse.lecture_hours" min="0" placeholder="0" required>
                                    </div>
                                    <div class="form-group">
                                        <label>ปฏิบัติ / แล็บ (ชม.) <span style="color:var(--status-conflict-fg)">*</span></label>
                                        <input type="number" name="lab_hours" x-model="currentCourse.lab_hours" min="0" placeholder="0" required>
                                    </div>
                                    <div class="form-group">
                                        <label>ศึกษาด้วยตนเอง (ชม.) <span style="color:var(--status-conflict-fg)">*</span></label>
                                        <input type="number" name="self_study_hours" x-model="currentCourse.self_study_hours" min="0" placeholder="0" required>
                                    </div>
                                    <div class="form-group">
                                        <label>จำนวนนักศึกษาสูงสุด (คน) <span style="color:var(--status-conflict-fg)">*</span></label>
                                        <input type="number" name="capacity" x-model="currentCourse.capacity" min="1" placeholder="เช่น 240" required onwheel="this.blur()">
                                    </div>
                                    <div class="form-group course-rotation-field">
                                        <label>การหมุนเวียนกลุ่มนักศึกษาระหว่างแหล่งฝึก</label>
                                        <select name="requires_practicum_rotation" x-model="currentCourse.requires_practicum_rotation">
                                            <option value="0">ไม่มีการหมุนเวียนแหล่งฝึก</option>
                                            <option value="1">มีการหมุนเวียนแหล่งฝึก</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="course-prerequisite-panel">
                                    <div class="course-prerequisite-head">
                                        <div>
                                            <label style="display:block;margin-bottom:3px;">เงื่อนไขรายวิชา <span style="font-weight:400;color:var(--fg-4);font-size:11px;">(ไม่บังคับ)</span></label>
                                            <div style="font-size:12px;color:var(--fg-3);line-height:1.5;">ระบุรายวิชาที่ควรเรียนมาก่อน ใช้เป็นข้อมูลหลักของรายวิชา ไม่ได้บังคับลำดับในหน้าจัดตาราง</div>
                                        </div>
                                        <span class="course-count-badge"
                                            x-text="(currentCourse.prerequisite_ids || []).length + ' วิชา'"></span>
                                    </div>
                                    <select name="prerequisite_ids[]" x-model="currentCourse.prerequisite_ids" multiple size="5"
                                        style="min-height:132px;background:var(--surface);">
                                        @foreach($courses as $candidateCourse)
                                            <option value="{{ $candidateCourse->id }}" :disabled="editCourseMode && String(currentCourse.id) === '{{ $candidateCourse->id }}'">
                                                {{ $candidateCourse->course_code }} - {{ $candidateCourse->name_th ?? $candidateCourse->name_en }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div style="margin-top:8px;font-size:11px;color:var(--fg-4);">กด Ctrl หรือ Cmd เพื่อเลือกได้หลายรายวิชา</div>
                                </div>
                            </section>

                        </div>
                        <div class="modal-foot course-modal-foot">
                            <div>
                                <button type="button" class="btn btn-ghost" x-show="editCourseMode" @click="confirmDelete('deleteCourseForm', currentCourse.name_th + (currentCourse.course_code ? ' (' + currentCourse.course_code + ')' : ''), 'หากมีการผูกตารางสอนแล้วจะไม่สามารถลบได้')" style="color: var(--status-conflict-fg);">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px; display: inline-block; vertical-align: middle;"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg> ลบข้อมูล
                                </button>
                            </div>
                            <div class="course-modal-actions">
                                <button type="button" class="btn btn-ghost" @click="showCourseModal = false">ยกเลิก</button>
                                <button type="submit" data-testid="course-form-submit" class="btn btn-primary">บันทึกข้อมูลวิชา</button>
                            </div>
                        </div>
                    </form>
                    <form id="deleteCourseForm" :action="'{{ route($routePrefix . '.courses.destroy', '__COURSE__') }}'.replace('__COURSE__', encodeURIComponent(currentCourse.route_key))" method="POST" style="display: none;">
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
                    <form :action="editCurriculumMode ? '{{ url($routePrefix . '/master-data/curriculums') }}/' + currentCurriculum.id : '{{ route($routePrefix . '.curriculums.store') }}'"
                        method="POST">
                        @csrf
                        <input type="hidden" name="_method" value="PUT" :disabled="!editCurriculumMode">
                        <input type="hidden" name="curriculum_form" value="1">
                        <input type="hidden" name="curriculum_form_id" :value="currentCurriculum.id" :disabled="!editCurriculumMode">
                        <div class="modal-body">
                            @if(old('curriculum_form') && $errors->hasAny(['name','effective_year','is_active','education_level','duration_years','uses_year_level','total_credits_required','counts_service_only']))
                                <div style="margin-bottom:16px;padding:12px 14px;background:oklch(97% 0.02 20);border:1px solid oklch(82% 0.08 25);border-radius:8px;color:var(--status-conflict-fg);font-size:13px;line-height:1.6;">
                                    <div style="font-weight:700;margin-bottom:4px;">ไม่สามารถบันทึกหลักสูตรได้</div>
                                    @foreach(['name','effective_year','is_active','education_level','duration_years','uses_year_level','total_credits_required','counts_service_only'] as $field)
                                        @foreach($errors->get($field) as $error)
                                            <div>{{ $error }}</div>
                                        @endforeach
                                    @endforeach
                                </div>
                            @endif
                            <div style="font-weight:700;font-size:12px;color:var(--brand-navy);border-bottom:1px solid var(--border);padding-bottom:6px;margin-bottom:14px;">ข้อมูลทั่วไป</div>
                            <div class="form-group" style="margin-bottom: 16px;">
                                <label>ชื่อหลักสูตร <span style="color: var(--status-conflict-fg)">*</span></label>
                                <input type="text" name="name" x-model="currentCurriculum.name" required placeholder="เช่น พยาบาลศาสตรบัณฑิต (2565)">
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 16px;">
                                <div class="form-group" style="margin-bottom:0;">
                                    <label>ระดับการศึกษา <span style="color: var(--status-conflict-fg)">*</span></label>
                                    <select name="education_level" x-model="currentCurriculum.education_level" @change="applyEducationLevelDefaults()" required>
                                        <option value="bachelor">ปริญญาตรี</option>
                                        <option value="master">ปริญญาโท</option>
                                        <option value="doctorate">ปริญญาเอก</option>
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label>ปีที่เริ่มใช้ (พ.ศ.) <span style="color: var(--status-conflict-fg)">*</span></label>
                                    <input type="number" name="effective_year" x-model="currentCurriculum.effective_year" required placeholder="2565">
                                </div>
                            </div>
                            <div style="font-weight:700;font-size:12px;color:var(--brand-navy);border-bottom:1px solid var(--border);padding-bottom:6px;margin-bottom:14px;">โครงสร้างหลักสูตร</div>
                            {{-- รูปแบบการจัดชั้นปี: ตั้งอัตโนมัติตามระดับการศึกษา + กด "ปรับเอง" ได้ --}}
                            <input type="hidden" name="uses_year_level" :value="currentCurriculum.uses_year_level">
                            <div class="form-group" style="margin-bottom: 16px;">
                                <label>การจัดชั้นปี</label>
                                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px;border:1px solid var(--border);border-radius:6px;background:var(--surface-sunken);">
                                    <span style="font-size:13px;color:var(--fg-1);" x-text="String(currentCurriculum.uses_year_level) === '1' ? ('แบ่งเป็นชั้นปี (ปี 1–' + (currentCurriculum.duration_years || 4) + ')') : 'ไม่แบ่งชั้นปี ใช้หน่วยกิตสะสม'"></span>
                                    <button type="button" @click="showYearModeOverride = !showYearModeOverride" data-testid="curriculum-year-mode-toggle" style="flex-shrink:0;background:none;border:0;padding:0;cursor:pointer;color:var(--brand-navy-500);font:inherit;font-size:12px;text-decoration:underline;">ปรับเอง</button>
                                </div>
                                <select x-model="currentCurriculum.uses_year_level" x-show="showYearModeOverride" style="margin-top:8px;" data-testid="curriculum-year-mode-override">
                                    <option value="1">แบ่งเป็นชั้นปี (ปี 1–4)</option>
                                    <option value="0">ไม่แบ่งชั้นปี ใช้หน่วยกิตสะสม</option>
                                </select>
                                <div style="margin-top:4px;font-size:11px;color:var(--fg-4);">ตั้งให้อัตโนมัติตามระดับการศึกษา — ป.ตรีแบ่งชั้นปี · ป.โท/เอกใช้หน่วยกิตสะสม</div>
                            </div>
                            {{-- แบ่งชั้นปี → จำนวนปี --}}
                            <div class="form-group" style="margin-bottom: 16px;" x-show="String(currentCurriculum.uses_year_level) === '1'">
                                <label>จำนวนปีของหลักสูตร <span style="color: var(--status-conflict-fg)">*</span></label>
                                <input type="number" name="duration_years" x-model="currentCurriculum.duration_years" min="1" max="10" :required="String(currentCurriculum.uses_year_level) === '1'" placeholder="4">
                            </div>
                            {{-- ใช้หน่วยกิตสะสม → หน่วยกิตขั้นต่ำ --}}
                            <div class="form-group" style="margin-bottom: 16px;" x-show="String(currentCurriculum.uses_year_level) === '0'">
                                <label>หน่วยกิตขั้นต่ำ <span style="color: var(--status-conflict-fg)">*</span></label>
                                <input type="number" name="total_credits_required" x-model="currentCurriculum.total_credits_required" min="0" :required="String(currentCurriculum.uses_year_level) === '0'" placeholder="เช่น 36">
                                <div style="margin-top:4px;font-size:11px;color:var(--fg-4);">ใช้เป็นเงื่อนไขจบการศึกษา (แทนระบบชั้นปี)</div>
                            </div>
                            <div style="font-weight:700;font-size:12px;color:var(--brand-navy);border-bottom:1px solid var(--border);padding-bottom:6px;margin:4px 0 14px;">การตั้งค่า</div>
                            <div class="form-group" style="margin-bottom:16px;">
                                <label>การนับภาระงาน <span style="font-weight:400;color:var(--fg-4);font-size:11px;">(หลักสูตรเฉพาะทาง)</span></label>
                                <input type="hidden" name="counts_service_only" :value="currentCurriculum.counts_service_only">
                                <div style="display:inline-flex;border:1px solid var(--border-strong);border-radius:8px;overflow:hidden;background:var(--surface);" data-testid="curriculum-counts-service-only">
                                    <button type="button" @click="currentCurriculum.counts_service_only = '0'"
                                        style="padding:11px 20px;border:0;border-right:1px solid var(--border-strong);cursor:pointer;font-family:inherit;font-size:13px;line-height:1.4;white-space:nowrap;transition:background .12s ease,color .12s ease;"
                                        :style="String(currentCurriculum.counts_service_only) === '0' ? 'background:var(--brand-navy);color:#fff;font-weight:600;' : 'background:transparent;color:var(--fg-2);font-weight:500;'">นับชั่วโมงปกติ</button>
                                    <button type="button" @click="currentCurriculum.counts_service_only = '1'"
                                        style="padding:11px 20px;border:0;cursor:pointer;font-family:inherit;font-size:13px;line-height:1.4;white-space:nowrap;transition:background .12s ease,color .12s ease;"
                                        :style="String(currentCurriculum.counts_service_only) === '1' ? 'background:var(--brand-navy);color:#fff;font-weight:600;' : 'background:transparent;color:var(--fg-2);font-weight:500;'">บริการวิชาการอย่างเดียว</button>
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label>สถานะ</label>
                                <input type="hidden" name="is_active" :value="currentCurriculum.is_active">
                                <div style="display:inline-flex;border:1px solid var(--border-strong);border-radius:8px;overflow:hidden;background:var(--surface);">
                                    <button type="button" @click="currentCurriculum.is_active = '1'"
                                        style="padding:11px 20px;border:0;border-right:1px solid var(--border-strong);cursor:pointer;font-family:inherit;font-size:13px;line-height:1.4;white-space:nowrap;transition:background .12s ease,color .12s ease;"
                                        :style="String(currentCurriculum.is_active) === '1' ? 'background:var(--brand-navy);color:#fff;font-weight:600;' : 'background:transparent;color:var(--fg-2);font-weight:500;'">กำลังใช้งาน</button>
                                    <button type="button" @click="currentCurriculum.is_active = '0'"
                                        style="padding:11px 20px;border:0;cursor:pointer;font-family:inherit;font-size:13px;line-height:1.4;white-space:nowrap;transition:background .12s ease,color .12s ease;"
                                        :style="String(currentCurriculum.is_active) === '0' ? 'background:var(--brand-navy);color:#fff;font-weight:600;' : 'background:transparent;color:var(--fg-2);font-weight:500;'">ปิดใช้งาน</button>
                                </div>
                            </div>
                        </div>
                        <div class="modal-foot" style="display: flex; justify-content: space-between;">
                            <div>
                                <button type="button" data-testid="curriculum-delete-button" class="btn btn-ghost" x-show="editCurriculumMode" @click="confirmDeleteCurriculum()" style="color: var(--status-conflict-fg);">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px; display: inline-block; vertical-align: middle;"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg> ลบข้อมูล
                                </button>
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="btn btn-ghost" @click="showCurriculumModal = false">ยกเลิก</button>
                                <button type="submit" class="btn btn-primary">บันทึกข้อมูลหลักสูตร</button>
                            </div>
                        </div>
                    </form>
                    <form id="deleteCurriculumForm" :action="'{{ url($routePrefix . '/master-data/curriculums') }}/' + currentCurriculum.id" method="POST" style="display: none;">
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
                    @if($canManageMasterData)
                    <div class="card-actions">
                        <button type="button" class="btn btn-primary" @click="openAddActivityType()">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            เพิ่มประเภทกิจกรรม
                        </button>
                    </div>
                    @endif
                </div>
                <div class="m7-filter-bar">
                    <div class="m7-filter-search">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                            <circle cx="11" cy="11" r="8" />
                            <line x1="21" y1="21" x2="16.65" y2="16.65" />
                        </svg>
                        <input type="text" x-model="filters.activity_types.keyword" placeholder="ค้นหาชื่อ หมวดหมู่ หรือรหัสสี">
                    </div>
                    <select class="m7-filter-select" x-model="filters.activity_types.category">
                        <option value="">ทุกหมวดหมู่</option>
                        <option value="lecture">บรรยาย</option>
                        <option value="practicum">ปฏิบัติ</option>
                        <option value="thesis">วิทยานิพนธ์</option>
                        <option value="other">อื่นๆ</option>
                    </select>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>สี</th>
                                <th>ชื่อประเภทกิจกรรม</th>
                                <th>หมวดหมู่</th>
                                <th>ภาระงาน</th>
                                @if($canManageMasterData)<th style="text-align: center;">จัดการ</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($activityTypes as $at)
                                @php
                                    $catLabel = ['lecture' => 'บรรยาย', 'practicum' => 'ปฏิบัติ', 'thesis' => 'วิทยานิพนธ์', 'other' => 'อื่นๆ'];
                                    $pillShape = 'border-radius:999px;padding:0 11px;height:21px;';
                                    $catStyle = [
                                        'lecture'   => 'background:#eef4ff;color:#1e40af;border:1px solid #c7d7fe;',
                                        'practicum' => 'background:#f3effd;color:#6b21a8;border:1px solid #ddd0f7;',
                                        'thesis'    => 'background:#eef6f9;color:#155e75;border:1px solid #cce4ed;',
                                        'other'     => 'background:#f7fafc;color:#475569;border:1px solid #e2e8f0;',
                                    ];
                                @endphp
                                <tr
                                    data-search="{{ Str::lower($at->name . ' ' . $at->category . ' ' . ($catLabel[$at->category] ?? '') . ' ' . ($at->color_code ?? '')) }}"
                                    data-category="{{ $at->category }}"
                                    x-show="includesText($el.dataset.search, filters.activity_types.keyword) && (filters.activity_types.category === '' || $el.dataset.category == filters.activity_types.category)">
                                    <td>
                                        <span style="display: inline-block; width: 20px; height: 20px; border-radius: 4px; background: {{ $at->color_code }}; border: 1px solid var(--border);"></span>
                                    </td>
                                    <td style="font-weight: 600; color: var(--fg-1);">{{ $at->name }}</td>
                                    <td>
                                        <span class="pill" style="{{ $catStyle[$at->category] ?? $catStyle['other'] }}{{ $pillShape }}">{{ $catLabel[$at->category] ?? $at->category }}</span>
                                    </td>
                                    <td>
                                        @if($at->counts_toward_workload)
                                            <span class="pill" style="background:#e6fffa;color:#047481;border:1px solid #b2f5ea;{{ $pillShape }}">นับภาระงาน</span>
                                        @else
                                            <span class="pill" style="background:#f7fafc;color:#718096;border:1px solid #e2e8f0;{{ $pillShape }}">ไม่นับ</span>
                                        @endif
                                    </td>
                                    @if($canManageMasterData)
                                    <td style="text-align: center;">
                                        <button type="button" class="action-btn" title="แก้ไข"
                                            @click="openEditActivityType({{ Js::from(['id' => $at->id, 'name' => $at->name, 'color_code' => $at->color_code, 'category' => $at->category, 'counts_toward_workload' => $at->counts_toward_workload]) }})">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </button>
                                    </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $canManageMasterData ? 5 : 4 }}" style="text-align: center; color: var(--fg-3); padding: 40px;">ยังไม่มีประเภทกิจกรรม</td>
                                </tr>
                            @endforelse
                            <tr
                                x-show="(filters.activity_types.keyword || filters.activity_types.category) && !Array.from($el.parentNode.children).some(tr => tr !== $el && tr.dataset && tr.dataset.search && tr.style.display !== 'none')"
                                x-cloak>
                                <td colspan="5" style="text-align: center; color: var(--fg-3); padding: 40px;">ไม่พบข้อมูลที่ค้นหา</td>
                            </tr>
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
                    <form :action="editActivityTypeMode ? '{{ url($routePrefix . '/master-data/activity-types') }}/' + currentActivityType.id : '{{ route($routePrefix . '.activity_types.store') }}'" method="POST">
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
                                    <select name="category" x-model="currentActivityType.category" required @change="applyWorkloadDefaultFromCategory()">
                                        <option value="lecture">บรรยาย (Lecture)</option>
                                        <option value="practicum">ปฏิบัติ (Practicum)</option>
                                        <option value="thesis">วิทยานิพนธ์ (Thesis)</option>
                                        <option value="other">อื่นๆ (Other)</option>
                                    </select>
                                    <div style="margin-top:6px;font-size:12px;line-height:1.55;color:var(--fg-3);" x-text="activityCategoryHelp()"></div>
                                </div>
                                <div class="form-group">
                                    <label>สีแสดงผล <span style="color: var(--status-conflict-fg)">*</span></label>
                                    <div x-data="{ open: false }" style="position: relative;">
                                        <button type="button" @click="open = !open"
                                            style="width: 100%; height: 38px; border: 1px solid var(--border); border-radius: 4px; display: flex; align-items: center; gap: 10px; padding: 0 10px; background: var(--surface); cursor: pointer;">
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
                            <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; padding: 12px 14px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-2);">
                                <input type="checkbox" name="counts_toward_workload" value="1" x-model="currentActivityType.counts_toward_workload" style="margin-top: 2px; flex-shrink: 0; width: 16px; height: 16px; accent-color: var(--brand-navy);">
                                <div>
                                    <div style="font-size: 13px; font-weight: 600; color: var(--fg-1);">นับเป็นภาระงานสอนของอาจารย์</div>
                                    <div style="font-size: 12px; color: var(--fg-3); margin-top: 2px;">เช่น ปฐมนิเทศ / SDL / สอบ / วันหยุด มักไม่นับ — ระบบตั้งค่าเริ่มต้นให้ตามหมวด (ปรับเองได้)</div>
                                </div>
                            </label>
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
                    <form id="deleteActivityTypeForm" :action="'{{ url($routePrefix . '/master-data/activity-types') }}/' + currentActivityType.id" method="POST" style="display: none;">
                        @csrf
                        @method('DELETE')
                    </form>
                </div>
            </div>
        </template>

        <!-- Tab: Student Cohorts (กลุ่มนักศึกษา — V2) -->
        @php
            $eduLabel = ['bachelor' => 'ปริญญาตรี', 'master' => 'ปริญญาโท', 'doctorate' => 'ปริญญาเอก'];
        @endphp
        <div x-show="activeTab === 'student_cohorts'" x-cloak>
            <div class="card">
                <div class="card-hdr">
                    <div>
                        <div class="card-ttl">กลุ่มนักศึกษาแต่ละหลักสูตร</div>
                    </div>
                    @if($canManageMasterData)
                    <div class="card-actions">
                        <button class="btn btn-primary" @click="openAddCohort()">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                            เพิ่มกลุ่มนักศึกษา
                        </button>
                    </div>
                    @endif
                </div>

                <div class="curriculum-list" x-data="{ expandedCohort: null }" style="padding: 16px 20px; display: flex; flex-direction: column; gap: 8px;">
                    @forelse($cohortCurriculums as $cur)
                        <div class="curriculum-list-item" style="border: 1px solid color-mix(in oklch, var(--brand-navy) 32%, var(--border-strong)); border-radius: 8px; overflow: hidden;">

                            {{-- Header --}}
                            <div @click="expandedCohort = expandedCohort === {{ $cur->id }} ? null : {{ $cur->id }}"
                                style="cursor: pointer; user-select: none; transition: background 0.15s;"
                                :style="expandedCohort === {{ $cur->id }} ? 'background: #f0f4ff;' : 'background: #fff;'">
                            <div class="curriculum-card-head" style="display: flex; align-items: center; gap: 16px; padding: 14px 16px;">

                                {{-- Chevron --}}
                                <div class="curriculum-card-chevron" style="flex-shrink: 0; width: 28px; height: 28px; border-radius: 6px; display: flex; align-items: center; justify-content: center; transition: background 0.15s;"
                                    :style="expandedCohort === {{ $cur->id }} ? 'background: var(--brand-navy);' : 'background: var(--bg-3);'">
                                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                                        style="transition: transform 0.2s;"
                                        :style="expandedCohort === {{ $cur->id }} ? 'transform:rotate(90deg); color:#fff' : 'color: var(--fg-3)'">
                                        <polyline points="9 18 15 12 9 6"/>
                                    </svg>
                                </div>

                                {{-- Name + level --}}
                                <div class="curriculum-card-title-block" style="flex: 1; min-width: 0;">
                                    <div class="curriculum-card-title" style="font-weight: 600; font-size: 14px; color: var(--fg-1);">{{ $cur->name }}</div>
                                    <div class="curriculum-card-year" style="margin-top: 3px; font-size: 12px; color: var(--fg-3);">
                                        {{ $eduLabel[$cur->education_level] ?? $cur->education_level }}@if($cur->uses_year_level) · แบ่งตามชั้นปี (ปี 1-{{ $cur->duration_years }})@else · ไม่ผูกชั้นปี@endif
                                    </div>
                                </div>

                                {{-- Group count --}}
                                @if($cur->studentCohorts->count() > 0)
                                <div class="curriculum-card-count" style="flex-shrink: 0; background: var(--bg-3); border-radius: 20px; padding: 4px 12px; font-size: 12px; font-weight: 700; color: var(--fg-2);">
                                    {{ $cur->studentCohorts->count() }} กลุ่ม
                                </div>
                                @else
                                <div class="curriculum-card-count" style="flex-shrink: 0; font-size: 12px; color: var(--fg-4, #94a3b8);">ยังไม่มีกลุ่ม</div>
                                @endif

                                {{-- Actions (admin only) — เพิ่มกลุ่มในหลักสูตรนี้ (auto-fill หลักสูตรใน modal) --}}
                                @if($canManageMasterData)
                                <div class="curriculum-card-actions" @click.stop style="flex-shrink: 0; display: flex; gap: 4px;">
                                    <button type="button" class="action-btn" title="เพิ่มกลุ่มในหลักสูตรนี้" @click.stop="openAddCohort({{ $cur->id }})" style="color: var(--brand-navy);">
                                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    </button>
                                </div>
                                @endif

                            </div>{{-- /flex inner --}}
                            </div>{{-- /click outer --}}

                            {{-- Expanded cohort list --}}
                            <div x-show="expandedCohort === {{ $cur->id }}" x-cloak
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                                x-transition:leave="transition ease-in duration-100"
                                x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                                style="border-top: 1px solid var(--border);">

                                @if($cur->studentCohorts->count() > 0)
                                    <table style="width: 100%; border-collapse: collapse;">
                                        <thead>
                                            <tr style="background: var(--bg-2);">
                                                @if($cur->uses_year_level)
                                                <th style="padding: 8px 16px 8px 56px; text-align: left; font-size: 11px; color: var(--fg-3); font-weight: 600; letter-spacing: 0.05em;">ชั้นปี</th>
                                                <th style="padding: 8px 16px; text-align: left; font-size: 11px; color: var(--fg-3); font-weight: 600; letter-spacing: 0.05em;">รหัสกลุ่ม</th>
                                                @else
                                                <th style="padding: 8px 16px 8px 56px; text-align: left; font-size: 11px; color: var(--fg-3); font-weight: 600; letter-spacing: 0.05em;">รหัสกลุ่ม</th>
                                                @endif
                                                <th style="padding: 8px 16px; text-align: center; font-size: 11px; color: var(--fg-3); font-weight: 600; letter-spacing: 0.05em; width: 180px;">จำนวนนักศึกษา</th>
                                                @if($canManageMasterData)<th style="padding: 8px 16px; text-align: center; font-size: 11px; color: var(--fg-3); font-weight: 600; letter-spacing: 0.05em; width: 80px;">จัดการ</th>@endif
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($cur->studentCohorts as $co)
                                                <tr style="border-top: 1px solid var(--border); transition: background 0.1s;"
                                                    onmouseover="this.style.background='var(--bg-2)'" onmouseout="this.style.background=''">
                                                    @if($cur->uses_year_level)
                                                    <td style="padding: 11px 16px 11px 56px; font-size: 13px; color: var(--fg-2);">@if($co->year_level)ปี {{ $co->year_level }}@else<span style="color: var(--fg-3);">—</span>@endif</td>
                                                    <td style="padding: 11px 16px; font-size: 13px; font-weight: 600; color: var(--fg-1);">{{ $co->code }}</td>
                                                    @else
                                                    <td style="padding: 11px 16px 11px 56px; font-size: 13px; font-weight: 600; color: var(--fg-1);">{{ $co->code }}</td>
                                                    @endif
                                                    <td style="padding: 11px 16px; font-size: 13px; color: var(--fg-2); text-align: center; font-family: var(--font-mono, monospace);">{{ number_format($co->student_count) }} คน</td>
                                                    @if($canManageMasterData)
                                                    <td style="padding: 11px 16px; text-align: center;">
                                                        <button type="button" class="action-btn" title="แก้ไข"
                                                            @click="openEditCohort({{ Js::from(['id' => $co->id, 'curriculum_id' => $co->curriculum_id, 'year_level' => $co->year_level, 'code' => $co->code, 'student_count' => $co->student_count, 'note' => $co->note]) }})">
                                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                        </button>
                                                    </td>
                                                    @endif
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @else
                                    <div style="padding: 20px 56px; font-size: 13px; color: var(--fg-3);">ยังไม่มีกลุ่มในหลักสูตรนี้@if($canManageMasterData) — กดปุ่ม "เพิ่มกลุ่มนักศึกษา" ด้านบน@endif</div>
                                @endif
                            </div>

                        </div>
                    @empty
                        <div style="text-align: center; padding: 48px 20px; color: var(--fg-3);">ยังไม่มีหลักสูตร — เพิ่มหลักสูตรในแท็บ "หลักสูตร" ก่อน</div>
                    @endforelse
                </div>
            </div>
        </div>

        @if($canManageMasterData)
        <!-- Add/Edit Modal (Student Cohort) -->
        <template x-if="showCohortModal">
            <div class="overlay" x-cloak>
                <div class="modal-center" style="max-width: 520px;"
                    x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100">
                    <div class="modal-hdr" style="background: var(--bg-2);">
                        <div class="modal-ttl" style="font-family: var(--font-display);"
                            x-text="editCohortMode ? 'แก้ไขกลุ่มชั้นปี' : 'เพิ่มกลุ่มชั้นปีใหม่'"></div>
                        <button type="button" class="modal-cls" @click="showCohortModal = false">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>
                    <form :action="editCohortMode ? '{{ url($routePrefix . '/master-data/student-cohorts') }}/' + currentCohort.id : '{{ route($routePrefix . '.student_cohorts.store') }}'" method="POST">
                        @csrf
                        <input type="hidden" name="cohort_form" value="1">
                        <input type="hidden" name="cohort_form_id" :value="currentCohort.id">
                        <input type="hidden" name="_method" value="PUT" :disabled="!editCohortMode">
                        <div class="modal-body">
                            <div class="form-group" style="margin-bottom: 18px;">
                                <label>หลักสูตร <span style="color: var(--status-conflict-fg)">*</span></label>
                                <select name="curriculum_id" x-model="currentCohort.curriculum_id" required
                                    @change="if (!cohortYearOptions().includes(Number(currentCohort.year_level))) currentCohort.year_level = ''">
                                    <option value="">— เลือกหลักสูตร —</option>
                                    @foreach($cohortCurriculums as $cur)
                                        <option value="{{ $cur->id }}">{{ $cur->name }}@if($cur->uses_year_level) (ปี 1-{{ $cur->duration_years }})@endif</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 18px;" x-show="cohortUsesYear()">
                                <label>ชั้นปี <span style="color: var(--status-conflict-fg)">*</span></label>
                                <select name="year_level" x-model="currentCohort.year_level" :required="cohortUsesYear()" :disabled="!currentCohort.curriculum_id">
                                    <option value="">— เลือกชั้นปี —</option>
                                    <template x-for="y in cohortYearOptions()" :key="y">
                                        <option :value="y" x-text="'ปี ' + y"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 18px;" x-show="currentCohort.curriculum_id && !cohortUsesYear()" x-cloak>
                                <div style="font-size:12px;line-height:1.55;color:var(--fg-3);padding:8px 10px;background:var(--bg-2);border-radius:6px;">
                                    หลักสูตรนี้ไม่ใช้ระบบชั้นปี (ใช้ prerequisite + หน่วยกิตสะสม) — กลุ่มนี้จะไม่ผูกกับชั้นปี
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom: 18px;">
                                <label>จำนวนนักศึกษา <span style="color: var(--status-conflict-fg)">*</span></label>
                                <input type="number" name="student_count" x-model="currentCohort.student_count" min="0" max="9999" required placeholder="เช่น 80">
                            </div>
                            <div class="form-group" style="margin-bottom: 18px;">
                                <label>รหัสกลุ่ม <span style="color: var(--status-conflict-fg)">*</span></label>
                                <input type="text" name="code" x-model="currentCohort.code" maxlength="50" required placeholder="เช่น กลุ่ม 1, A">
                                <div style="margin-top:6px;font-size:12px;color:var(--fg-3);">รหัสกลุ่มต้องไม่ซ้ำในชั้นปีเดียวกันของหลักสูตรนี้</div>
                            </div>
                            <div class="form-group">
                                <label>หมายเหตุ</label>
                                <input type="text" name="note" x-model="currentCohort.note" maxlength="255" placeholder="(ถ้ามี)">
                            </div>
                        </div>
                        <div class="modal-foot" style="display: flex; justify-content: space-between;">
                            <div>
                                <button type="button" class="btn btn-ghost" x-show="editCohortMode"
                                    @click="confirmDelete('deleteCohortForm', currentCohort.code, 'กลุ่มชั้นปีที่ลบแล้วจะไม่สามารถกู้คืนได้')"
                                    style="color: var(--status-conflict-fg);">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px; display: inline-block; vertical-align: middle;"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    ลบข้อมูล
                                </button>
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="btn btn-ghost" @click="showCohortModal = false">ยกเลิก</button>
                                <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
                            </div>
                        </div>
                    </form>
                    <form id="deleteCohortForm" :action="'{{ url($routePrefix . '/master-data/student-cohorts') }}/' + currentCohort.id" method="POST" style="display: none;">
                        @csrf
                        @method('DELETE')
                    </form>
                </div>
            </div>
        </template>
        @endif

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
                    <form :action="cloneSourceCurriculum ? '{{ url($routePrefix . '/master-data/curriculums') }}/' + cloneSourceCurriculum.id + '/clone' : '#'" method="POST">
                        @csrf
                        <input type="hidden" name="clone_curriculum_form" value="1">
                        <input type="hidden" name="clone_curriculum_source_id" :value="cloneSourceCurriculum?.id || ''">
                        <input type="hidden" name="clone_curriculum_source_name" :value="cloneSourceCurriculum?.name || ''">
                        <div class="modal-body">
                            @if(old('clone_curriculum_form') && $errors->hasAny(['name','effective_year']))
                                <div style="margin-bottom:16px;padding:12px 14px;background:oklch(97% 0.02 20);border:1px solid oklch(82% 0.08 25);border-radius:8px;color:var(--status-conflict-fg);font-size:13px;line-height:1.6;">
                                    <div style="font-weight:700;margin-bottom:4px;">ไม่สามารถคัดลอกหลักสูตรได้</div>
                                    @foreach(['name','effective_year'] as $field)
                                        @foreach($errors->get($field) as $error)
                                            <div>{{ $error }}</div>
                                        @endforeach
                                    @endforeach
                                </div>
                            @endif
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
                                <a href="{{ asset('templates/rooms_import.xlsx') }}" class="md-template-link">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                        <polyline points="7 10 12 15 17 10"/>
                                        <line x1="12" y1="15" x2="12" y2="3"/>
                                    </svg>
                                    <span class="md-template-link__label">ดาวน์โหลดไฟล์ Template Excel (rooms_import.xlsx)</span>
                                </a>
                                <div style="font-size: 12px; color: var(--fg-muted); margin-top: 6px; line-height: 1.55;">
                                    เปิดแท็บ <strong>Rooms</strong> → อ่านแท็บ <strong>Rooms หมายเหตุ</strong> → กรอกข้อมูล → บันทึก/Export เป็น <strong>CSV UTF-8</strong> → อัปโหลดไฟล์ CSV ที่ได้
                                </div>
                            </div>
                            <div style="margin-bottom: 16px;" x-data="{ fileName: '' }">
                                <label class="frm-lbl">เลือกไฟล์ CSV <span style="color: var(--status-conflict-fg)">*</span></label>
                                <label class="md-file-control" :class="{ 'has-file': fileName }">
                                    <span class="md-file-button">เลือกไฟล์ CSV</span>
                                    <span class="md-file-name" x-text="fileName || 'ยังไม่ได้เลือกไฟล์'"></span>
                                    <input type="file" name="csv_file" accept=".csv,.txt" required
                                        class="md-file-input"
                                        @change="fileName = $event.target.files.length ? $event.target.files[0].name : ''">
                                </label>
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
                                <a href="{{ asset('templates/courses_import.xlsx') }}" class="md-template-link">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                        <polyline points="7 10 12 15 17 10"/>
                                        <line x1="12" y1="15" x2="12" y2="3"/>
                                    </svg>
                                    <span class="md-template-link__label">ดาวน์โหลดไฟล์ Template Excel (courses_import.xlsx)</span>
                                </a>
                                <div style="font-size: 12px; color: var(--fg-muted); margin-top: 6px; line-height: 1.55;">
                                    เปิดแท็บ <strong>Courses</strong> → อ่านแท็บ <strong>Courses หมายเหตุ</strong> → กรอกข้อมูล → บันทึก/Export เป็น <strong>CSV UTF-8</strong> → อัปโหลดไฟล์ CSV ที่ได้
                                </div>
                            </div>
                            <div style="margin-bottom: 16px;" x-data="{ fileName: '' }">
                                <label class="frm-lbl">เลือกไฟล์ CSV <span style="color: var(--status-conflict-fg)">*</span></label>
                                <label class="md-file-control" :class="{ 'has-file': fileName }">
                                    <span class="md-file-button">เลือกไฟล์ CSV</span>
                                    <span class="md-file-name" x-text="fileName || 'ยังไม่ได้เลือกไฟล์'"></span>
                                    <input type="file" name="csv_file" accept=".csv,.txt" required
                                        class="md-file-input"
                                        @change="fileName = $event.target.files.length ? $event.target.files[0].name : ''">
                                </label>
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

        /* Template download — flat card with a prominent "ดาวน์โหลด" button
           (Impeccable Design: sharp, flat, no gradient). */
        .md-template-link {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            padding: 14px 16px;
            border: 1px solid color-mix(in srgb, var(--brand-navy) 30%, var(--border));
            border-radius: 8px;
            background: color-mix(in srgb, var(--brand-navy) 5%, #fff);
            color: var(--brand-navy);
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: border-color 160ms ease, background 160ms ease;
        }

        .md-template-link:hover,
        .md-template-link:focus-visible {
            border-color: var(--brand-navy);
            background: color-mix(in srgb, var(--brand-navy) 9%, #fff);
            outline: none;
        }

        .md-template-link svg {
            width: 20px;
            height: 20px;
            flex: 0 0 auto;
        }

        .md-template-link__label {
            flex: 1 1 auto;
            min-width: 0;
            line-height: 1.4;
        }

        .md-template-link::after {
            content: "ดาวน์โหลด";
            flex: 0 0 auto;
            padding: 8px 14px;
            border-radius: 8px;
            background: var(--brand-navy);
            color: #fff;
            font-size: 0.86rem;
            font-weight: 700;
            line-height: 1;
        }

        @media (max-width: 640px) {
            .md-template-link {
                flex-wrap: wrap;
            }

            .md-template-link::after {
                width: 100%;
                text-align: center;
            }
        }

        /* Custom CSV file picker — label wraps a visually-hidden native input
           so the Thai button/filename text renders with the page font instead
           of the browser's native "Choose File / No file chosen" control. */
        .md-file-control {
            display: flex;
            align-items: center;
            gap: 14px;
            width: 100%;
            min-height: 48px;
            padding: 7px 10px;
            border: 1px solid var(--border, #cfdbe5);
            border-radius: 8px;
            background: #fff;
            color: var(--fg-base, #111827);
            text-align: left;
            cursor: pointer;
            transition: border-color 160ms ease, box-shadow 160ms ease;
        }

        .md-file-control:hover,
        .md-file-control:focus-within {
            border-color: var(--brand-navy);
            box-shadow: 0 0 0 3px rgba(3, 49, 99, 0.08);
        }

        .md-file-input {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .md-file-button {
            flex: 0 0 auto;
            min-width: 116px;
            padding: 9px 14px;
            border: 1px solid color-mix(in srgb, var(--brand-navy) 24%, var(--border));
            border-radius: 8px;
            background: color-mix(in srgb, var(--brand-navy) 8%, #fff);
            color: var(--brand-navy, #033163);
            font-size: 0.9rem;
            font-weight: 700;
            line-height: 1;
            text-align: center;
        }

        .md-file-control:hover .md-file-button,
        .md-file-control:focus-within .md-file-button {
            border-color: var(--brand-navy);
            background: color-mix(in srgb, var(--brand-navy) 12%, #fff);
        }

        .md-file-name {
            min-width: 0;
            color: var(--fg-muted, #64748b);
            font-size: 0.9rem;
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .md-file-control.has-file .md-file-name {
            color: var(--fg-base, #111827);
        }

        .btn-ghost:hover {
            background: var(--bg-3);
        }

        .master-data-page.is-tab-loading > [x-show*="activeTab ==="] {
            display: none !important;
        }

        .m7-tab-skeleton {
            margin-bottom: 22px;
            padding: 16px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 12%, var(--border));
            border-radius: var(--r-lg);
            background: var(--surface);
            box-shadow:
                0 1px 2px rgba(0, 36, 84, 0.05),
                0 16px 34px -28px rgba(0, 36, 84, 0.26);
        }

        .m7-skel-toolbar {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 14px;
        }

        .m7-skel-toolbar span,
        .m7-skel-table span {
            display: block;
            border-radius: var(--r-md);
            background:
                linear-gradient(90deg,
                    color-mix(in oklch, var(--bg-2) 84%, var(--surface)) 0%,
                    color-mix(in oklch, var(--brand-navy) 5%, var(--surface)) 44%,
                    color-mix(in oklch, var(--bg-2) 84%, var(--surface)) 86%);
            background-size: 220% 100%;
            animation: m7Skeleton 1150ms ease-in-out infinite;
        }

        .m7-skel-toolbar span:first-child {
            width: min(420px, 62%);
            height: 42px;
        }

        .m7-skel-toolbar span:last-child {
            width: 168px;
            height: 42px;
        }

        .m7-skel-table {
            display: grid;
            gap: 10px;
        }

        .m7-skel-table span {
            height: 58px;
            border-radius: var(--r-sm);
        }

        @keyframes m7Skeleton {
            0% { background-position: 120% 0; }
            100% { background-position: -120% 0; }
        }

        .m7-filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            padding: 12px 20px;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            background: oklch(98% 0.006 220);
        }

        .m7-filter-search {
            display: flex;
            align-items: center;
            gap: 9px;
            flex: 1 1 420px;
            min-width: 320px;
            max-width: 680px;
            height: 40px;
            box-sizing: border-box;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: oklch(100% 0.004 220);
            padding: 0 12px;
            transition: border-color 150ms ease, background 150ms ease, box-shadow 150ms ease;
        }

        .m7-filter-search:focus-within {
            border-color: var(--brand-navy);
            background: var(--surface);
            box-shadow: 0 0 0 3px oklch(92% 0.025 250);
        }

        .m7-filter-search svg {
            width: 15px;
            height: 15px;
            flex-shrink: 0;
            color: var(--fg-3);
        }

        .m7-filter-search input {
            width: 100%;
            height: 100%;
            min-width: 0;
            border: 0;
            outline: 0;
            background: transparent;
            color: var(--fg-1);
            font: inherit;
            font-size: 13px;
        }

        .m7-filter-bar.is-course {
            display: grid;
            grid-template-columns:
                minmax(460px, 1.8fr)
                minmax(150px, .75fr)
                minmax(170px, .85fr)
                minmax(150px, .75fr)
                minmax(112px, .55fr)
                minmax(120px, .55fr);
            align-items: center;
        }

        .m7-filter-bar.is-course .m7-filter-search,
        .m7-filter-bar.is-course .m7-filter-select,
        .m7-filter-bar.is-course .m7-filter-select.is-narrow {
            width: 100%;
            min-width: 0;
            max-width: none;
        }

        .m7-filter-select {
            height: 40px;
            box-sizing: border-box;
            max-width: 240px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: oklch(100% 0.004 220);
            color: var(--fg-2);
            font-size: 13px;
            line-height: 1.4;
            padding: 7px 34px 7px 12px;
            transition: border-color 150ms ease, box-shadow 150ms ease;
        }

        .m7-filter-select:focus {
            outline: 0;
            border-color: var(--brand-navy);
            box-shadow: 0 0 0 3px oklch(92% 0.025 250);
        }

        .m7-filter-select.is-narrow {
            max-width: 130px;
        }

        .curriculum-list-item {
            min-width: 0;
        }

        .curriculum-card-head {
            min-width: 0;
        }

        .curriculum-card-title {
            line-height: 1.5;
            overflow-wrap: anywhere;
            word-break: normal;
        }

        .curriculum-card-year,
        .curriculum-card-status,
        .curriculum-card-count {
            line-height: 1.45;
        }

        .instructor-name-text,
        .instructor-title-cell,
        .instructor-department-cell {
            overflow-wrap: normal;
            word-break: keep-all;
            line-break: strict;
        }

        @media (max-width: 1280px) {
            .m7-filter-bar.is-course {
                grid-template-columns: minmax(360px, 1.4fr) repeat(3, minmax(140px, .7fr));
            }

            .m7-filter-bar.is-course .m7-filter-search {
                grid-column: span 2;
            }
        }

        @media (max-width: 760px) {
            .m7-tab-skeleton {
                padding: 12px;
            }

            .m7-skel-toolbar {
                flex-direction: column;
            }

            .m7-skel-toolbar span:first-child,
            .m7-skel-toolbar span:last-child {
                width: 100%;
            }

            .m7-skel-table span {
                height: 52px;
            }

            .m7-filter-bar {
                padding: 12px;
            }

            .m7-filter-bar.is-course {
                display: flex;
            }

            .m7-filter-search,
            .m7-filter-select,
            .m7-filter-select.is-narrow {
                flex: 1 1 100%;
                width: 100%;
                max-width: none;
            }

            .curriculum-list {
                padding: 12px !important;
            }

            .curriculum-card-head {
                display: grid !important;
                grid-template-columns: 28px minmax(0, 1fr);
                align-items: start !important;
                gap: 10px 12px !important;
                padding: 14px !important;
                height: auto !important;
                min-height: 0;
            }

            .curriculum-card-chevron {
                grid-column: 1;
                grid-row: 1;
            }

            .curriculum-card-title-block {
                grid-column: 2;
                grid-row: 1;
                min-width: 0;
            }

            .curriculum-card-status,
            .curriculum-card-count,
            .curriculum-card-actions {
                grid-column: 2;
                justify-self: start;
            }

            .curriculum-card-status,
            .curriculum-card-count {
                flex-shrink: 1 !important;
                max-width: 100%;
                white-space: normal;
            }

            .curriculum-card-actions {
                flex-wrap: wrap;
                align-items: center;
                margin-top: 2px;
            }

            .instructor-table-wrap {
                overflow-x: visible;
            }

            .instructor-table,
            .instructor-table tbody,
            .instructor-table tr,
            .instructor-table td {
                display: block;
                width: 100%;
            }

            .instructor-table thead {
                display: none;
            }

            .instructor-table tbody {
                padding: 12px;
                background: var(--surface);
            }

            .instructor-table tbody tr {
                border: 1px solid var(--border);
                border-radius: 8px;
                background: var(--surface);
                margin-bottom: 10px;
                padding: 12px 14px;
            }

            .instructor-table tbody tr:last-child {
                margin-bottom: 0;
            }

            .instructor-table tbody tr:hover td {
                background: transparent;
            }

            .instructor-table td {
                border-bottom: 0;
                padding: 0;
                font-size: 13px;
            }

            .instructor-table td + td {
                margin-top: 10px;
            }

            .instructor-table td::before {
                content: attr(data-label);
                display: block;
                margin-bottom: 3px;
                color: var(--fg-3);
                font-size: 11px;
                font-weight: 700;
                line-height: 1.35;
            }

            .instructor-name-text {
                font-size: 14px;
                line-height: 1.55;
            }

            .instructor-title-cell,
            .instructor-department-cell {
                line-height: 1.55;
            }

            .instructor-action-cell {
                display: flex !important;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                text-align: left !important;
            }

            .instructor-action-cell::before {
                margin-bottom: 0;
            }
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

        .dept-empty-assignment {
            margin: -8px 0 18px;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--bg-2);
            color: var(--fg-3);
            font-size: 12px;
            line-height: 1.55;
        }

        .modal-body input:disabled {
            background: var(--bg-2);
            color: var(--fg-3);
            cursor: not-allowed;
        }

        .room-modal {
            width: min(960px, calc(100vw - 48px));
            max-width: none;
            max-height: 90vh;
        }

        .room-modal-body {
            display: flex;
            flex-direction: column;
            gap: 14px;
            padding: 24px;
            background: oklch(99% 0.004 220);
        }

        .room-form-section {
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            padding: 18px;
        }

        .room-section-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 14px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
        }

        .room-section-title {
            color: var(--fg-1);
            font-size: 15px;
            font-weight: 800;
            line-height: 1.4;
        }

        .room-section-copy {
            max-width: 520px;
            color: var(--fg-3);
            font-size: 12px;
            line-height: 1.55;
            text-align: right;
        }

        .room-form-grid {
            display: grid;
            gap: 16px;
        }

        .room-form-grid .form-group {
            min-width: 0;
            margin-bottom: 0;
        }

        .room-form-grid--identity {
            grid-template-columns: minmax(160px, .85fr) minmax(260px, 1.35fr) minmax(220px, 1fr);
        }

        .room-form-grid--usage {
            grid-template-columns: minmax(280px, 1.4fr) minmax(160px, .75fr) minmax(200px, .9fr);
        }

        .room-form-grid--details {
            grid-template-columns: minmax(280px, 1fr) minmax(320px, 1.2fr);
        }

        .room-type-dropdown {
            position: relative;
        }

        .room-type-trigger {
            width: 100%;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            color: var(--fg-1);
            cursor: pointer;
            font: inherit;
            font-size: 14px;
            line-height: 1.4;
            padding: 8px 11px;
            text-align: left;
            transition: border-color 150ms ease, box-shadow 150ms ease;
        }

        .room-type-trigger.is-placeholder {
            color: var(--fg-3);
        }

        .room-type-trigger:hover,
        .room-type-trigger:focus-visible {
            border-color: var(--brand-navy);
            outline: 0;
            box-shadow: 0 0 0 3px oklch(92% 0.025 250);
        }

        .room-type-trigger span {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .room-type-trigger svg {
            flex-shrink: 0;
            color: var(--fg-3);
        }

        .room-type-menu {
            position: absolute;
            z-index: 80;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            max-height: 260px;
            overflow: auto;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            box-shadow: 0 12px 28px rgba(15, 23, 42, .16);
        }

        .room-type-option {
            display: block;
            width: 100%;
            border: 0;
            border-bottom: 1px solid var(--border);
            background: transparent;
            color: var(--fg-1);
            cursor: pointer;
            font: inherit;
            font-size: 14px;
            line-height: 1.45;
            padding: 10px 12px;
            text-align: left;
        }

        .room-type-option:last-child {
            border-bottom: 0;
        }

        .room-type-option:hover,
        .room-type-option:focus-visible,
        .room-type-option.is-selected {
            background: var(--bg-2);
            outline: 0;
        }

        .room-type-option.is-placeholder {
            color: var(--fg-3);
            font-weight: 700;
        }

        .room-modal-foot {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .room-modal-actions {
            display: flex;
            gap: 8px;
        }

        .instructor-modal {
            width: min(1080px, calc(100vw - 48px));
            max-width: none;
            max-height: 90vh;
        }

        .instructor-modal-body {
            display: flex;
            flex-direction: column;
            gap: 14px;
            padding: 24px;
            background: oklch(99% 0.004 220);
        }

        .instructor-form-section {
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            padding: 18px;
        }

        .instructor-section-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 14px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
        }

        .instructor-section-title {
            color: var(--fg-1);
            font-size: 15px;
            font-weight: 800;
            line-height: 1.4;
        }

        .instructor-section-copy {
            max-width: 520px;
            color: var(--fg-3);
            font-size: 12px;
            line-height: 1.55;
            text-align: right;
        }

        .instructor-section-copy.is-left {
            margin-top: 3px;
            text-align: left;
        }

        .instructor-form-grid {
            display: grid;
            gap: 16px;
        }

        .instructor-form-grid .form-group,
        .instructor-pa-grid .form-group {
            min-width: 0;
            margin-bottom: 0;
        }

        .instructor-form-grid--identity {
            grid-template-columns: 140px minmax(260px, 1.2fr) minmax(180px, .85fr) minmax(260px, 1.2fr);
        }

        .instructor-form-grid--profile {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .instructor-english-panel {
            margin-top: 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--bg-2);
            padding: 14px 16px;
        }

        .instructor-pa-section {
            background: var(--surface);
        }

        .instructor-pa-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 12px;
        }

        .instructor-pa-grid .form-group:last-child {
            grid-column: span 1 !important;
        }

        .instructor-pa-grid .form-group:last-child input {
            width: 100%;
            max-width: none;
        }

        .instructor-pa-grid label {
            min-height: 34px;
            line-height: 1.45;
        }

        .instructor-modal-foot {
            justify-content: flex-end;
        }

        .course-modal {
            width: min(1120px, calc(100vw - 48px));
            max-width: none;
            max-height: 90vh;
        }

        .course-modal-body {
            display: flex;
            flex-direction: column;
            gap: 14px;
            padding: 24px;
            background: oklch(99% 0.004 220);
        }

        .course-form-section {
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            padding: 18px;
        }

        .course-section-head {
            display: block;
            margin-bottom: 14px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
        }

        .course-section-title {
            color: var(--fg-1);
            font-size: 15px;
            font-weight: 800;
            line-height: 1.4;
        }

        .course-section-copy {
            max-width: 680px;
            margin-top: 3px;
            color: var(--fg-3);
            font-size: 12px;
            line-height: 1.55;
            text-align: left;
        }

        .course-form-grid {
            display: grid;
            gap: 16px;
        }

        .course-form-grid .form-group {
            min-width: 0;
            margin-bottom: 0;
        }

        .course-form-grid--basic {
            grid-template-columns: repeat(12, minmax(0, 1fr));
        }

        .course-form-grid--basic > .form-group {
            grid-column: span 5;
        }

        .course-form-grid--basic .course-code-field,
        .course-form-grid--basic .course-small-field {
            grid-column: span 2;
        }

        .course-form-grid--plan {
            grid-template-columns: repeat(5, minmax(130px, 1fr));
        }

        .course-form-grid--hours {
            grid-template-columns: repeat(4, minmax(130px, 1fr)) minmax(250px, 1.35fr);
            align-items: end;
        }

        .course-rotation-field {
            grid-column: span 1;
        }

        .course-disabled-note {
            min-height: 38px;
            display: flex;
            align-items: center;
            padding: 0 10px;
            border: 1px dashed var(--border);
            border-radius: 6px;
            background: var(--bg-2);
            color: var(--fg-3);
            font-size: 12px;
            line-height: 1.45;
        }

        .course-prerequisite-panel {
            margin-top: 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--bg-2);
            padding: 14px 16px;
        }

        .course-prerequisite-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 10px;
        }

        .course-modal-foot {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .course-modal-actions {
            display: flex;
            gap: 8px;
        }

        .course-assignment-panel {
            background: var(--surface);
        }

        .course-assignment-head,
        .course-instructor-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 14px;
        }

        .course-assignment-title {
            color: var(--fg-1);
            font-size: 14px;
            font-weight: 800;
            line-height: 1.4;
        }

        .course-assignment-copy {
            color: var(--fg-3);
            font-size: 12px;
            line-height: 1.5;
        }

        .course-lock-badge,
        .course-count-badge {
            flex-shrink: 0;
            border: 1px solid var(--border);
            border-radius: 999px;
            background: var(--surface);
            color: var(--fg-2);
            font-size: 11px;
            font-weight: 800;
            line-height: 1;
            padding: 6px 10px;
        }

        .course-lock-note {
            margin-bottom: 14px;
            border: 1px solid var(--status-warning-border);
            border-radius: 6px;
            background: var(--status-warning-bg);
            color: var(--status-warning-fg);
            font-size: 13px;
            font-weight: 600;
            line-height: 1.55;
            padding: 10px 12px;
        }

        .course-assignment-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .course-combobox {
            position: relative;
        }

        .course-clear-btn {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: var(--fg-3);
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
            padding: 4px;
        }

        .course-combobox-menu {
            position: absolute;
            z-index: 50;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            max-height: 220px;
            overflow: auto;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            box-shadow: 0 10px 24px rgba(15, 23, 42, .12);
        }

        .course-combobox-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            width: 100%;
            border: 0;
            border-bottom: 1px solid var(--border);
            background: transparent;
            color: var(--fg-1);
            cursor: pointer;
            font-size: 13px;
            padding: 10px 12px;
            text-align: left;
        }

        .course-combobox-item:hover,
        .course-combobox-item:focus-visible {
            background: var(--bg-2);
            outline: 0;
        }

        .course-combobox-item small {
            display: block;
            margin-top: 2px;
            color: var(--fg-3);
            font-size: 12px;
        }

        .course-combobox-empty,
        .course-inline-empty {
            color: var(--fg-3);
            font-size: 12px;
            line-height: 1.5;
        }

        .course-combobox-empty {
            padding: 11px 12px;
        }

        .course-chip-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .course-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--border);
            border-radius: 999px;
            background: var(--surface);
            color: var(--fg-1);
            font-size: 12px;
            font-weight: 700;
            padding: 6px 10px;
        }

        .course-chip button,
        .course-remove-btn {
            border: 0;
            background: transparent;
            color: var(--fg-3);
            cursor: pointer;
            line-height: 1;
            padding: 0;
        }

        .course-instructor-block {
            border-top: 1px solid var(--border);
            padding-top: 14px;
        }

        .course-instructor-search {
            position: relative;
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            margin-bottom: 12px;
        }

        .course-scope-toggle {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            padding: 3px;
        }

        .course-scope-toggle button {
            border: 0;
            border-radius: 6px;
            background: transparent;
            color: var(--fg-3);
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
            padding: 7px 10px;
        }

        .course-scope-toggle button.is-active {
            background: var(--brand-navy);
            color: var(--surface);
        }

        .course-instructor-list {
            display: grid;
            gap: 8px;
        }

        .course-instructor-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 190px 28px;
            align-items: center;
            gap: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            padding: 10px 12px;
        }

        .course-instructor-name strong,
        .course-instructor-name span {
            display: block;
        }

        .course-instructor-name strong {
            color: var(--fg-1);
            font-size: 13px;
            line-height: 1.4;
        }

        .course-instructor-name span {
            color: var(--fg-3);
            font-size: 12px;
            margin-top: 2px;
        }

        @media (max-width: 1024px) {
            .room-modal {
                width: min(900px, calc(100vw - 32px));
            }

            .room-form-grid--identity,
            .room-form-grid--usage,
            .room-form-grid--details {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .room-form-grid--identity .form-group:nth-child(2),
            .room-form-grid--details .form-group {
                grid-column: span 1;
            }

            .room-section-head {
                display: block;
            }

            .room-section-copy {
                max-width: none;
                margin-top: 3px;
                text-align: left;
            }

            .instructor-modal {
                width: min(900px, calc(100vw - 32px));
            }

            .instructor-form-grid--identity,
            .instructor-form-grid--profile,
            .instructor-pa-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .instructor-prefix-field {
                max-width: none;
            }

            .instructor-section-head {
                display: block;
            }

            .instructor-section-copy {
                max-width: none;
                margin-top: 3px;
                text-align: left;
            }

            .course-modal {
                width: min(920px, calc(100vw - 32px));
            }

            .course-form-grid--basic,
            .course-form-grid--plan,
            .course-form-grid--hours {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .course-form-grid--basic > .form-group,
            .course-form-grid--basic .course-code-field,
            .course-form-grid--basic .course-small-field {
                grid-column: span 1;
            }

            .course-section-copy {
                max-width: none;
            }
        }

        @media (max-width: 760px) {
            .room-modal {
                width: calc(100vw - 24px);
            }

            .room-modal-body {
                padding: 16px;
                gap: 12px;
            }

            .room-form-section {
                padding: 14px;
            }

            .room-form-grid--identity,
            .room-form-grid--usage,
            .room-form-grid--details {
                grid-template-columns: 1fr;
            }

            .room-form-grid--identity .form-group:nth-child(2),
            .room-form-grid--details .form-group {
                grid-column: 1 / -1;
            }

            .room-modal-foot {
                align-items: stretch;
                flex-direction: column-reverse;
                gap: 12px;
            }

            .room-modal-actions {
                justify-content: flex-end;
                flex-wrap: wrap;
            }

            .instructor-modal {
                width: calc(100vw - 24px);
            }

            .instructor-modal-body {
                padding: 16px;
                gap: 12px;
            }

            .instructor-form-section {
                padding: 14px;
            }

            .instructor-form-grid--identity,
            .instructor-form-grid--profile,
            .instructor-pa-grid {
                grid-template-columns: 1fr;
            }

            .instructor-pa-grid .form-group:last-child {
                grid-column: 1 / -1 !important;
            }

            .instructor-modal-foot {
                flex-wrap: wrap;
                justify-content: flex-end;
            }

            .course-modal {
                width: calc(100vw - 24px);
            }

            .course-modal-body {
                padding: 16px;
                gap: 12px;
            }

            .course-form-section {
                padding: 14px;
            }

            .course-form-grid--basic,
            .course-form-grid--plan,
            .course-form-grid--hours,
            .course-assignment-grid,
            .course-instructor-search,
            .course-instructor-row {
                grid-template-columns: 1fr;
            }

            .course-form-grid--basic > .form-group,
            .course-form-grid--basic .course-code-field,
            .course-form-grid--basic .course-small-field,
            .course-rotation-field {
                grid-column: 1 / -1;
            }

            .course-modal-foot {
                align-items: stretch;
                flex-direction: column-reverse;
                gap: 12px;
            }

            .course-modal-actions {
                justify-content: flex-end;
                flex-wrap: wrap;
            }

            .course-prerequisite-head,
            .course-assignment-head,
            .course-instructor-head {
                flex-direction: column;
                gap: 8px;
            }
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

        .master-data-page {
            padding: clamp(14px, 2vw, 28px);
            background:
                radial-gradient(circle at 8% 0%, color-mix(in oklch, var(--brand-navy) 10%, transparent), transparent 30%),
                linear-gradient(180deg,
                    color-mix(in oklch, var(--brand-navy) 7%, var(--bg)) 0%,
                    color-mix(in oklch, var(--brand-navy) 4%, var(--bg)) 34%,
                    var(--bg) 100%);
        }

        .master-data-page .card,
        .master-data-page .md-card,
        .master-data-page .master-card,
        .master-data-page .panel,
        .master-data-page .list-card,
        .master-data-page .modal-card {
            border-color: color-mix(in oklch, var(--brand-navy) 44%, var(--border-strong)) !important;
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 4%, var(--surface)), var(--surface) 40%),
                var(--surface) !important;
            box-shadow:
                0 1px 2px rgba(0, 36, 84, 0.14),
                0 18px 36px -20px rgba(0, 36, 84, 0.55) !important;
        }

        .master-data-page .card-hdr,
        .master-data-page .md-card-hdr,
        .master-data-page .panel-hdr,
        .master-data-page thead th {
            border-bottom-color: color-mix(in oklch, var(--brand-navy) 32%, var(--border-strong)) !important;
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 10%, var(--surface)), color-mix(in oklch, var(--brand-navy) 4%, var(--surface))) !important;
        }

        .master-data-page .tabs,
        .master-data-page [role="tablist"],
        .master-data-page .tab-nav,
        .master-data-page .md-tabs {
            border-color: color-mix(in oklch, var(--brand-navy) 40%, var(--border-strong)) !important;
            background: color-mix(in oklch, var(--brand-navy) 8%, var(--surface)) !important;
            box-shadow: 0 1px 2px rgba(0, 36, 84, 0.08);
            scrollbar-width: none;
            -ms-overflow-style: none;
            scroll-behavior: smooth;
            overscroll-behavior-inline: contain;
        }

        .master-data-page .tabs::-webkit-scrollbar,
        .master-data-page [role="tablist"]::-webkit-scrollbar,
        .master-data-page .tab-nav::-webkit-scrollbar,
        .master-data-page .md-tabs::-webkit-scrollbar {
            width: 0;
            height: 0;
            display: none;
        }

        .master-data-page .btn-primary {
            border-color: var(--brand-navy) !important;
            background: var(--brand-navy) !important;
            color: var(--fg-on-brand) !important;
            box-shadow:
                0 1px 2px rgba(0, 36, 84, 0.16),
                0 10px 20px -16px rgba(0, 36, 84, 0.64);
        }

        .master-data-page .btn-secondary,
        .master-data-page .btn-ghost,
        .master-data-page .btn:not(.btn-primary) {
            border-color: color-mix(in oklch, var(--brand-navy) 24%, var(--border));
            background: color-mix(in oklch, var(--brand-navy) 5%, var(--surface));
            color: var(--brand-navy);
        }

        .master-data-page .btn-secondary:hover,
        .master-data-page .btn-ghost:hover,
        .master-data-page .btn:not(.btn-primary):hover {
            border-color: color-mix(in oklch, var(--brand-navy) 36%, var(--border));
            background: color-mix(in oklch, var(--brand-navy) 9%, var(--surface));
        }

        .master-data-page input,
        .master-data-page select,
        .master-data-page textarea,
        .master-data-page .form-ctrl,
        .master-data-page .tpss-select-trigger {
            border-color: color-mix(in oklch, var(--brand-navy) 22%, var(--border)) !important;
            background: color-mix(in oklch, var(--brand-navy) 3%, var(--surface)) !important;
        }

        .master-data-page input:focus,
        .master-data-page select:focus,
        .master-data-page textarea:focus,
        .master-data-page .form-ctrl:focus,
        .master-data-page .tpss-select-trigger:focus {
            border-color: var(--brand-navy) !important;
            box-shadow: 0 0 0 3px color-mix(in oklch, var(--brand-navy) 12%, transparent) !important;
        }

        .master-data-page table th {
            color: color-mix(in oklch, var(--brand-navy) 72%, var(--fg-2));
        }

        .master-data-page table td {
            border-bottom-color: color-mix(in oklch, var(--brand-navy) 10%, var(--border-subtle));
        }

        .master-data-page tbody tr:hover {
            background: color-mix(in oklch, var(--brand-navy) 5%, var(--surface));
        }

        .master-data-page .search-item:hover {
            background: color-mix(in oklch, var(--brand-navy) 7%, var(--surface));
        }
    </style>

    <script>
        const staffUsers = {{ Js::from($staffUsers->map(fn($u) => ['id' => $u->id, 'name' => $u->formatted_name, 'formatted_name' => $u->formatted_name])) }};
        const courseInstructorUsers = {{ Js::from($courseInstructorUsers->map(fn($u) => ['id' => $u->id, 'name' => $u->formatted_name, 'formatted_name' => $u->formatted_name, 'department' => $u->instructorProfile?->department?->name ?? '-', 'department_id' => $u->instructorProfile?->department_id])) }};
        const courseRoleOptions = {{ Js::from($courseRoles->map(fn($role) => ['id' => $role->id, 'name' => $role->name_th])->values()) }};

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
