<x-app-layout title="จัดการผู้ใช้งาน">
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('userManagement', () => ({
                showModal: false,
                showImportModal: false,
                editMode: false,
                errorMsg: '',
                teachingTotalHours: {{ \App\Models\SystemSetting::get('teaching_load_weeks', 39) * \App\Models\SystemSetting::get('teaching_quota_hours_per_week', 35) }}, 
                currentUser: {
                    id: '',
                    username: '',
                    prefix: '',
                    name: '',
                    email: '',
                    password: '',
                    roles: [],
                    primary_role: '',
                    is_active: 1
                },
                instructorProfile: {
                    title: '',
                    employee_id: '',
                    department_id: '',
                    employment_type: 'พนักงานมหาวิทยาลัย',
                    hired_at: '',
                    academic_degree: 'ปริญญาโท',
                    teaching_pct: 0,
                    research_pct: 0,
                    service_pct: 0,
                    culture_pct: 0,
                    other_pct: 0,
                    teaching_quota: 0,
                    is_english_passed: false,
                    department_position: ''
                },
                departmentsData: {{ Js::from($departments) }},
                paCriteria: {{ Js::from($paCriteria) }},
                get paRules() {
                    const title = this.instructorProfile.title;
                    const degree = this.instructorProfile.academic_degree;
                    const hiredAt = this.instructorProfile.hired_at;
                    const isEnglishPassed = this.instructorProfile.is_english_passed;

                    // หมายเหตุ 1: บรรจุก่อน 1 ต.ค. 2559 และจบปริญญาเอก -> ใช้เกณฑ์อาจารย์
                    const isNote1 = (title === 'ผู้ช่วยอาจารย์' && degree === 'ปริญญาเอก' && hiredAt && new Date(hiredAt) < new Date('2016-10-01'));
                    
                    // หมายเหตุ 2: บรรจุตั้งแต่ 1 ต.ค. 2559 และจบปริญญาเอก แต่ภาษาอังกฤษไม่ผ่าน -> ใช้เกณฑ์ผู้ช่วยอาจารย์
                    // (ถ้าผ่านภาษาอังกฤษแล้ว หรือบรรจุก่อน 2559 จะไปเข้าเงื่อนไขเกณฑ์อาจารย์)
                    const useInstructorRules = (
                        title === 'อาจารย์' || 
                        title === 'ผู้ช่วยศาสตราจารย์' || 
                        title === 'รองศาสตราจารย์' || 
                        title === 'ศาสตราจารย์' || 
                        isNote1 ||
                        (title === 'ผู้ช่วยอาจารย์' && degree === 'ปริญญาเอก' && isEnglishPassed)
                    );
                    
                    if (title === 'ผู้ช่วยอาจารย์ (คลินิก)') {
                        return this.paCriteria['ผู้ช่วยอาจารย์_คลินิก'] || {};
                    } else if (title === 'ผู้ช่วยอาจารย์ (สอนภาคปฏิบัติ)') {
                        return this.paCriteria['ผู้ช่วยอาจารย์_ปฏิบัติ'] || {};
                    } else if (title === 'ผู้ช่วยอาจารย์' && degree === 'ปริญญาตรี') {
                        return this.paCriteria['ผู้ช่วยอาจารย์_ปตรี'] || {};
                    } else if (useInstructorRules) {
                        return this.paCriteria['อาจารย์'] || {};
                    } else if (title === 'ผู้ช่วยอาจารย์') {
                        return this.paCriteria['ผู้ช่วยอาจารย์'] || {};
                    }
                    return { t: '-', r: '-', s: '-', c: '-', o: '-' };
                },
                get paTotal() {
                    return (this.instructorProfile.teaching_pct || 0) + 
                           (this.instructorProfile.research_pct || 0) + 
                           (this.instructorProfile.service_pct || 0) + 
                           (this.instructorProfile.culture_pct || 0) + 
                           (this.instructorProfile.other_pct || 0);
                },
                get hasInstructor() {
                    return this.currentUser.roles.includes('instructor');
                },
                isOutOfRange(value, rule) {
                    if (!rule || rule === '-') return false;
                    const val = parseInt(value) || 0;
                    
                    if (rule.includes('≤')) {
                        const max = parseInt(rule.replace('≤', '').trim()) || 0;
                        return val > max;
                    }
                    
                    if (rule.includes('-')) {
                        const parts = rule.split('-');
                        const min = parseInt(parts[0]) || 0;
                        const max = parseInt(parts[1]) || 0;
                        return val < min || val > max;
                    }
                    
                    return false;
                },
                updateQuota() {
                    this.instructorProfile.teaching_quota = Math.round((this.teachingTotalHours * (this.instructorProfile.teaching_pct || 0)) / 100);
                },
                openAddModal() {
                    this.editMode = false;
                    this.errorMsg = '';
                    this.currentUser = { id: '', username: '', prefix: '', name: '', email: '', password: '', roles: ['staff'], primary_role: 'staff', is_active: 1 };
                    this.instructorProfile = { title: '', employee_id: '', department_id: '', employment_type: 'พนักงานมหาวิทยาลัย', hired_at: '', academic_degree: 'ปริญญาโท', is_english_passed: false, teaching_pct: 0, research_pct: 0, service_pct: 0, culture_pct: 0, other_pct: 0, teaching_quota: 0, department_position: '' };
                    this.showModal = true;
                },
                openEditModal(user) {
                    this.editMode = true;
                    this.errorMsg = '';
                    this.currentUser = { 
                        id: user.id, 
                        username: user.username, 
                        prefix: user.prefix || '',
                        name: user.name, 
                        email: user.email, 
                        password: '', 
                        roles: user.roles ? user.roles.map(r => r.role) : [],
                        primary_role: (user.roles && user.roles.find(r => r.is_primary)) ? user.roles.find(r => r.is_primary).role : (user.roles && user.roles[0] ? user.roles[0].role : ''),
                        is_active: user.is_active ? 1 : 0
                    };
                    
                    const profile = user.instructor_profile || user.instructorProfile || null;
                    
                    this.instructorProfile = profile ? {
                        title: profile.title || '',
                        employee_id: profile.employee_id || '',
                        department_id: profile.department_id || '',
                        employment_type: profile.employment_type || 'พนักงานมหาวิทยาลัย',
                        hired_at: profile.hired_at || '',
                        academic_degree: profile.academic_degree || 'ปริญญาโท',
                        teaching_pct: profile.teaching_pct || 0,
                        research_pct: profile.research_pct || 0,
                        service_pct: profile.service_pct || 0,
                        culture_pct: profile.culture_pct || 0,
                        other_pct: profile.other_pct || 0,
                        teaching_quota: profile.teaching_quota || 0,
                        is_english_passed: !!profile.is_english_passed,
                        department_position: (user.head_of_departments && user.head_of_departments.length > 0) ? 'head' : ((user.secretary_of_departments && user.secretary_of_departments.length > 0) ? 'secretary' : '')
                    } : { title: '', employee_id: '', department_id: '', employment_type: 'พนักงานมหาวิทยาลัย', hired_at: '', academic_degree: 'ปริญญาโท', is_english_passed: false, teaching_pct: 0, research_pct: 0, service_pct: 0, culture_pct: 0, other_pct: 0, teaching_quota: 0, department_position: '' };
                    
                    this.showModal = true;
                },
                watchTitleChange(title) {
                    const highPositions = ['อาจารย์', 'ผู้ช่วยศาสตราจารย์', 'รองศาสตราจารย์', 'ศาสตราจารย์'];
                    if (highPositions.includes(title)) {
                        this.instructorProfile.academic_degree = 'ปริญญาเอก';
                    }
                },
                getConflictInfo() {
                    if (!this.instructorProfile.department_id || !this.instructorProfile.department_position) return null;
                    
                    const deptId = String(this.instructorProfile.department_id);
                    const pos = this.instructorProfile.department_position;
                    
                    const dept = this.departmentsData.find(d => String(d.id) === deptId);
                    if (!dept) return null;
                    
                    const existingUserId = pos === 'head' ? dept.head_user_id : dept.secretary_user_id;
                    const existingUser = pos === 'head' ? dept.head : dept.secretary;
                    
                    if (existingUserId && String(existingUserId) !== String(this.currentUser.id)) {
                        return {
                            posLabel: pos === 'head' ? 'หัวหน้าภาควิชา' : 'เลขานุการภาควิชา',
                            name: existingUser ? (existingUser.formatted_name || existingUser.name) : 'ผู้ใช้งานท่านอื่น'
                        };
                    }
                    return null;
                },
                confirmSave(e) {
                    this.errorMsg = '';
                    if (this.hasInstructor) {
                        const outOfRange = 
                            this.isOutOfRange(this.instructorProfile.teaching_pct, this.paRules.t) ||
                            this.isOutOfRange(this.instructorProfile.research_pct, this.paRules.r) ||
                            this.isOutOfRange(this.instructorProfile.service_pct, this.paRules.s) ||
                            this.isOutOfRange(this.instructorProfile.culture_pct, this.paRules.c) ||
                            this.isOutOfRange(this.instructorProfile.other_pct, this.paRules.o);
                            
                        if (outOfRange) {
                            this.errorMsg = `กรุณาระบุสัดส่วนภาระงานแต่ละด้านให้อยู่ในช่วงที่กำหนดตามเกณฑ์ตำแหน่ง`;
                            const modalBody = document.querySelector('.modal-body');
                            if(modalBody) modalBody.scrollTo({ top: 0, behavior: 'smooth' });
                            e.preventDefault();
                            return false;
                        }
                        
                        if (this.paTotal !== 100) {
                            this.errorMsg = `สัดส่วนภาระงานรวมต้องเท่ากับ 100% (ปัจจุบันรวมได้ ${this.paTotal}%)`;
                            const modalBody = document.querySelector('.modal-body');
                            if(modalBody) modalBody.scrollTo({ top: 0, behavior: 'smooth' });
                            e.preventDefault();
                            return false;
                        }
                    }
                    
                    const conflict = this.getConflictInfo();
                    if (conflict) {
                        e.preventDefault();
                        var form = e.target;
                        var posLabel = conflict.posLabel;
                        var name = conflict.name;

                        var innerHtml = '<div style="text-align:center;">'
                            + '<div style="width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,#fffbeb,#fef3c7);'
                            + 'border:2px solid #fcd34d;display:flex;align-items:center;justify-content:center;'
                            + 'margin:0 auto 16px;box-shadow:0 4px 16px rgba(217,119,6,0.15);">'
                            + '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="#d97706" stroke-width="2" '
                            + 'stroke-linecap="round" stroke-linejoin="round">'
                            + '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>'
                            + '<line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>'
                            + '<div style="font-family:Kanit,sans-serif;font-size:19px;font-weight:700;color:#0f172a;line-height:1.2;">'
                            + 'ตำแหน่งนี้มีผู้ดำรงอยู่แล้ว</div>'
                            + '<div style="font-size:13px;color:#94a3b8;margin-top:4px;">กรุณาตรวจสอบก่อนดำเนินการ</div>'
                            + '<div style="background:#fffbeb;border:1.5px solid #fde68a;border-radius:10px;padding:14px 16px;margin-top:14px;text-align:left;">'
                            + '<div style="font-size:12.5px;color:#92400e;line-height:1.7;">'
                            + 'ตำแหน่ง <strong>' + posLabel + '</strong> ของภาควิชานี้ มีคนครองอยู่แล้วคือ<br>'
                            + '<strong style="font-size:14px;color:#78350f;">' + name + '</strong>'
                            + '</div>'
                            + '<div style="font-size:12px;color:#b45309;margin-top:8px;padding-top:8px;border-top:1px solid #fde68a;">'
                            + 'หากบันทึก ระบบจะถอดถอนท่านเดิมและแต่งตั้งท่านนี้แทนโดยอัตโนมัติ'
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
                                actions:       'tpss-delete-actions',
                            }
                        }).then(function(result) {
                            if (result.isConfirmed) form.submit();
                        });
                        return false;
                    }
                    return true;
                },
                doDelete(formId, label) {
                    window.tpssConfirmDelete(formId, label, null);
                }
            }));
        });
    </script>

    <div x-data="userManagement" x-init="
        $watch('instructorProfile.teaching_pct', value => updateQuota());
        $watch('instructorProfile.title', value => watchTitleChange(value));
        @php $oldUserId = old('editing_user_id'); @endphp
        @if($errors->any() && $oldUserId)
            (function() {
                const allUsers = {{ Js::from($users) }};
                const targetUser = allUsers.find(u => String(u.id) === '{{ $oldUserId }}')
                if (targetUser) {
                    openEditModal(targetUser);
                } else {
                    showModal = true;
                }
                instructorProfile.department_id = '{{ old('instructor_department_id', '') }}';
                instructorProfile.department_position = '{{ old('instructor_department_position', '') }}';
            })();
        @elseif($errors->any())
            showModal = true;
            instructorProfile.department_id = '{{ old('instructor_department_id', '') }}';
            instructorProfile.department_position = '{{ old('instructor_department_position', '') }}';
        @endif
    ">

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
                    <button class="btn btn-secondary" @click="showImportModal = true">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="17 8 12 3 7 8"/>
                            <line x1="12" y1="3" x2="12" y2="15"/>
                        </svg>
                        นำเข้าจากไฟล์
                    </button>
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
                                        <div>
                                            @php
                                                $profile = $user->instructorProfile;
                                                $displayTitle = '';
                                                $cleanName = $user->name;
                                                $userPrefix = $user->prefix;
                                                
                                                if ($profile && $profile->title) {
                                                    $rawTitle = $profile->title;
                                                    $titleMap = [
                                                        'อาจารย์' => 'อ.',
                                                        'ผู้ช่วยศาสตราจารย์' => 'ผศ.',
                                                        'รองศาสตราจารย์' => 'รศ.',
                                                        'ศาสตราจารย์' => 'ศ.',
                                                        'ผู้ช่วยอาจารย์' => 'ผช.อ.',
                                                        'ผู้ช่วยอาจารย์ (คลินิก)' => 'ผช.อ. (คลินิก)',
                                                        'ผู้ช่วยอาจารย์ (สอนภาคปฏิบัติ)' => 'ผช.อ. (ปฏิบัติ)',
                                                    ];
                                                    $displayTitle = $titleMap[$rawTitle] ?? $rawTitle;
                                                    
                                                    if (str_contains($rawTitle, 'ผู้ช่วยอาจารย์')) {
                                                        if ($profile->academic_degree === 'ปริญญาเอก') {
                                                            $displayTitle = 'ดร.';
                                                        } else {
                                                            $displayTitle = ($userPrefix ?? '') ?: $displayTitle;
                                                        }
                                                    } else {
                                                        if ($profile->academic_degree === 'ปริญญาเอก') {
                                                            if ($displayTitle === 'อ.') {
                                                                $displayTitle = 'อ.ดร.';
                                                            } else if (!str_contains($displayTitle, 'ดร.')) {
                                                                $displayTitle .= 'ดร.';
                                                            }
                                                        }
                                                    }
                                                } else {
                                                    $displayTitle = $userPrefix ?? '';
                                                }

                                                // จัดการช่องว่าง: ให้ชิดชื่อเลยสำหรับคำนำหน้าทั่วไป และ ดร.
                                                $noSpacePrefixes = ['นาย', 'นาง', 'นางสาว', 'น.ส.', 'ดร.'];
                                                $needsSpace = true;
                                                foreach($noSpacePrefixes as $nsp) {
                                                    if (str_ends_with($displayTitle, $nsp)) {
                                                        $needsSpace = false;
                                                        break;
                                                    }
                                                }
                                                $separator = $needsSpace ? ' ' : '';
                                            @endphp
                                            <div style="font-weight: 600; color: var(--fg-1); line-height: 1.3;">
                                                {{ $displayTitle }}{{ $displayTitle ? $separator : '' }}{{ $cleanName }}
                                            </div>
                                            <div
                                                style="font-size: 12px; color: var(--fg-3); font-family: var(--font-mono); margin-top: 1px;">
                                                {{ $user->username }}
                                            </div>
                                            <div style="display: flex; gap: 4px; margin-top: 4px;">
                                                @if($user->headOfDepartments->isNotEmpty())
                                                    <span class="badge" style="background: var(--status-success-bg); color: var(--status-success-fg); font-size: 10px; padding: 1px 6px;">
                                                        หัวหน้าภาควิชา
                                                    </span>
                                                @endif
                                                @if($user->secretaryOfDepartments->isNotEmpty())
                                                    <span class="badge" style="background: var(--brand-gold-bg, #fef3c7); color: var(--brand-gold-fg, #92400e); font-size: 10px; padding: 1px 6px;">
                                                        เลขานุการภาควิชา
                                                    </span>
                                                @endif
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
                                        <form id="del-user-{{ $user->id }}" action="{{ route('admin.users.destroy', $user) }}" method="POST" style="display:none;">
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                        <button class="action-btn del" title="ลบ" type="button"
                                            data-form="del-user-{{ $user->id }}"
                                            data-label="{{ $user->name }}"
                                            onclick="tpssDelete(this)">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="3 6 5 6 21 6" />
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                            </svg>
                                        </button>
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
                        method="POST" @submit="confirmSave($event)">
                        @csrf
                        <input type="hidden" name="editing_user_id" :value="currentUser.id">
                        <template x-if="editMode">
                            <input type="hidden" name="_method" value="PUT">
                        </template>

                        <div class="modal-body">
                            <!-- Custom Alert UI -->
                            <div x-show="errorMsg" style="margin-bottom: 20px; padding: 12px 16px; background: #fef2f2; border: 1px solid #fca5a5; border-radius: 8px; display: flex; align-items: flex-start; gap: 12px;" x-transition x-cloak>
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-top: 2px;">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="8" x2="12" y2="12"></line>
                                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                </svg>
                                <div style="flex: 1;">
                                    <div style="color: #991b1b; font-weight: 700; font-size: 14px; margin-bottom: 2px;">ไม่สามารถบันทึกข้อมูลได้</div>
                                    <div style="color: #b91c1c; font-size: 13px;" x-text="errorMsg"></div>
                                </div>
                                <button type="button" @click="errorMsg = ''" style="background: transparent; border: none; cursor: pointer; color: #ef4444; padding: 4px; border-radius: 4px; display: flex; align-items: center; justify-content: center;" onmouseover="this.style.background='rgba(239,68,68,0.1)'" onmouseout="this.style.background='transparent'">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="18" y1="6" x2="6" y2="18"></line>
                                        <line x1="6" y1="6" x2="18" y2="18"></line>
                                    </svg>
                                </button>
                            </div>

                            <div class="form-row">
                                <div class="form-group" style="flex: 0 0 120px;">
                                    <label>คำนำหน้าชื่อ</label>
                                    <select name="prefix" x-model="currentUser.prefix">
                                        <option value="">-- ระบุ --</option>
                                        <option value="นาย">นาย</option>
                                        <option value="นาง">นาง</option>
                                        <option value="นางสาว">นางสาว</option>
                                    </select>
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label>ชื่อ-นามสกุล</label>
                                    <input type="text" name="name" x-model="currentUser.name" placeholder="ชื่อ และ นามสกุล" required>
                                </div>
                            </div>
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
                                <select name="is_active" x-model.number="currentUser.is_active">
                                    <option value="1">ใช้งานปกติ (Active)</option>
                                    <option value="0">ระงับการใช้งาน (Inactive)</option>
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
                                        <label style="font-size: 13px; font-weight: 600; color: var(--fg-2);">รหัสพนักงาน / รหัสอาจารย์ <span style="color: #ef4444;">*</span></label>
                                        <input type="text" name="instructor_employee_id" x-model="instructorProfile.employee_id" 
                                            placeholder="กรอกรหัสพนักงาน เช่น 600xxx" required
                                            style="background: oklch(98% 0.005 240); border: 1.5px solid var(--bg-3);">
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>ตำแหน่งทางวิชาการ <span style="color: #ef4444;">*</span></label>
                                            <select name="instructor_title" x-model="instructorProfile.title" required>
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
                                            <label>ภาควิชา / หน่วยงาน <span style="color: #ef4444;">*</span></label>
                                            <select name="instructor_department_id"
                                                x-model="instructorProfile.department_id" required>
                                                <option value="">-- เลือกภาควิชา --</option>
                                                @foreach($departments as $dept)
                                                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group" style="margin-bottom: 20px;">
                                        <label>ตำแหน่งบริหารในภาควิชา <span style="font-weight: normal; color: var(--fg-3); font-size: 0.9em;">(ไม่บังคับ)</span></label>
                                        <select name="instructor_department_position" x-model="instructorProfile.department_position">
                                            <option value="">-- ไม่มีตำแหน่งบริหาร --</option>
                                            <option value="head">หัวหน้าภาควิชา</option>
                                            <option value="secretary">เลขานุการภาควิชา</option>
                                        </select>

                                        {{-- Backend validation error (most reliable) --}}
                                        @error('instructor_department_position')
                                            <div style="margin-top: 8px; padding: 10px 14px; background: #fef2f2; border: 1.5px solid #fca5a5; border-radius: 8px; display: flex; align-items: flex-start; gap: 10px;">
                                                <div style="background: #ef4444; border-radius: 50%; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 1px;">
                                                    <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="white" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                                </div>
                                                <div style="font-size: 12px; color: #991b1b; line-height: 1.6;">
                                                    <strong style="display: block; font-size: 13px; margin-bottom: 2px;">ไม่สามารถบันทึกได้!</strong>
                                                    {{ $message }}
                                                </div>
                                            </div>
                                        @enderror

                                        <p style="font-size: 11px; color: var(--fg-3); margin-top: 4px;">* เมื่อเลือกตำแหน่ง ระบบจะอัปเดตข้อมูลในภาควิชาที่เลือกด้านบนให้อัตโนมัติ</p>
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
                                                <label>ประเภทการจ้างงาน <span style="color: #ef4444;">*</span></label>
                                                <select name="instructor_employment_type" x-model="instructorProfile.employment_type" required>
                                                    <option value="พนักงานมหาวิทยาลัย">พนักงานมหาวิทยาลัย</option>
                                                    <option value="ข้าราชการ">ข้าราชการ</option>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label>วันที่บรรจุเข้าทำงาน <span style="color: #ef4444;">*</span></label>
                                                <input type="date" name="instructor_hired_at" x-model="instructorProfile.hired_at" required>
                                            </div>
                                        </div>
                                        <div class="form-group" style="margin-top: 12px;" x-show='instructorProfile.title !== ""'>
                                            <label style="color: var(--status-success-fg); font-weight: 700;">วุฒิการศึกษาสูงสุด <span style="color: #ef4444;">*</span></label>
                                            <select name="instructor_academic_degree" x-model="instructorProfile.academic_degree" required>
                                                <option value="ปริญญาเอก">ปริญญาเอก</option>
                                                <option value="ปริญญาโท">ปริญญาโท</option>
                                                <option value="ปริญญาตรี">ปริญญาตรี</option>
                                            </select>
                                            <div style="font-size: 11px; color: var(--fg-3); margin-top: 4px;" x-show='instructorProfile.academic_degree === "ปริญญาเอก" && instructorProfile.hired_at && new Date(instructorProfile.hired_at) < new Date("2016-10-01")'>
                                                ✨ เข้าเงื่อนไขหมายเหตุ 1: บรรจุก่อน 2559 และจบ ป.เอก ให้ใช้เกณฑ์ "อาจารย์"
                                            </div>

                                            <!-- English Proficiency (Note 2) - Clean Rounded Buttons No Background -->
                                            <div style="margin-top: 15px;" 
                                                x-show='instructorProfile.title === "ผู้ช่วยอาจารย์" && instructorProfile.academic_degree === "ปริญญาเอก" && instructorProfile.hired_at && new Date(instructorProfile.hired_at) >= new Date("2016-10-01")'>
                                                
                                                <label style="font-size: 13px; font-weight: 700; color: var(--fg-2); margin-bottom: 10px; display: block; padding-left: 2px;">เกณฑ์ภาษาอังกฤษ</label>
                                                
                                                <div style="display: flex; gap: 12px; align-items: center;">
                                                    <!-- Option: Not Passed -->
                                                    <button type="button"
                                                        @click="instructorProfile.is_english_passed = false"
                                                        style="padding: 10px 20px; border-radius: 8px; cursor: pointer; transition: all 0.2s; font-size: 14px; font-weight: 600; appearance: none; outline: none; border: 1px solid; display: flex; align-items: center; gap: 8px;"
                                                        :style="!instructorProfile.is_english_passed
                                                            ? 'background: #fef2f2; color: #ef4444; border-color: #fca5a5; box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);'
                                                            : 'background: white; color: var(--fg-3); border-color: var(--border);'">
                                                        <svg x-show="!instructorProfile.is_english_passed" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                                        <svg x-show="instructorProfile.is_english_passed" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.5;"><circle cx="12" cy="12" r="10"></circle></svg>
                                                        ยังไม่ผ่านเกณฑ์
                                                    </button>

                                                    <!-- Option: Passed -->
                                                    <button type="button"
                                                        @click="instructorProfile.is_english_passed = true"
                                                        style="padding: 10px 20px; border-radius: 8px; cursor: pointer; transition: all 0.2s; font-size: 14px; font-weight: 600; appearance: none; outline: none; border: 1px solid; display: flex; align-items: center; gap: 8px;"
                                                        :style="instructorProfile.is_english_passed
                                                            ? 'background: #f0fdf4; color: #10b981; border-color: #6ee7b7; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);'
                                                            : 'background: white; color: var(--fg-3); border-color: var(--border);'">
                                                        <svg x-show="instructorProfile.is_english_passed" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                                        <svg x-show="!instructorProfile.is_english_passed" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.5;"><circle cx="12" cy="12" r="10"></circle></svg>
                                                        ผ่านเกณฑ์แล้ว
                                                    </button>
                                                </div>
                                                <!-- Hidden input for form submission -->
                                                <input type="hidden" name="instructor_is_english_passed" :value="instructorProfile.is_english_passed ? 1 : 0">
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
                                                    1. ด้านการสอน (<span x-text="paRules.t"></span>) <span style="color: #ef4444;">*</span>
                                                </label>
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <input type="number" name="instructor_teaching_pct" x-model.number="instructorProfile.teaching_pct" 
                                                        :style='isOutOfRange(instructorProfile.teaching_pct, paRules.t) ? "border-color: var(--status-conflict-fg); background: oklch(97% 0.02 20); color: var(--status-conflict-fg)" : ""'
                                                        style="font-weight: 700;" required>
                                                    <span style="font-size: 13px; color: var(--fg-3); width: 20px;">%</span>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label style="font-size: 12px; color: var(--fg-2);">
                                                    2. ด้านวิจัย (<span x-text="paRules.r"></span>) <span style="color: #ef4444;">*</span>
                                                </label>
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <input type="number" name="instructor_research_pct" x-model.number="instructorProfile.research_pct" 
                                                        :style='isOutOfRange(instructorProfile.research_pct, paRules.r) ? "border-color: var(--status-conflict-fg); background: oklch(97% 0.02 20); color: var(--status-conflict-fg)" : ""'
                                                        style="font-weight: 700;" required>
                                                    <span style="font-size: 13px; color: var(--fg-3); width: 20px;">%</span>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label style="font-size: 12px; color: var(--fg-2);">
                                                    3. บริการวิชาการ (<span x-text="paRules.s"></span>) <span style="color: #ef4444;">*</span>
                                                </label>
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <input type="number" name="instructor_service_pct" x-model.number="instructorProfile.service_pct" 
                                                        :style='isOutOfRange(instructorProfile.service_pct, paRules.s) ? "border-color: var(--status-conflict-fg); background: oklch(97% 0.02 20); color: var(--status-conflict-fg)" : ""'
                                                        style="font-weight: 700;" required>
                                                    <span style="font-size: 13px; color: var(--fg-3); width: 20px;">%</span>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label style="font-size: 12px; color: var(--fg-2);">
                                                    4. ศิลปวัฒนธรรม (<span x-text="paRules.c"></span>) <span style="color: #ef4444;">*</span>
                                                </label>
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <input type="number" name="instructor_culture_pct" x-model.number="instructorProfile.culture_pct" 
                                                        :style='isOutOfRange(instructorProfile.culture_pct, paRules.c) ? "border-color: var(--status-conflict-fg); background: oklch(97% 0.02 20); color: var(--status-conflict-fg)" : ""'
                                                        style="font-weight: 700;" required>
                                                    <span style="font-size: 13px; color: var(--fg-3); width: 20px;">%</span>
                                                </div>
                                            </div>
                                            <div class="form-group" style="grid-column: span 2;">
                                                <label style="font-size: 12px; color: var(--fg-2);">
                                                    5. งานอื่นๆ มอบหมาย (<span x-text="paRules.o"></span>) <span style="color: #ef4444;">*</span>
                                                </label>
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <input type="number" name="instructor_other_pct" x-model.number="instructorProfile.other_pct" 
                                                    :style='isOutOfRange(instructorProfile.other_pct, paRules.o) ? "border-color: var(--status-conflict-fg); background: oklch(97% 0.02 20); color: var(--status-conflict-fg)" : ""'
                                                    style="font-weight: 700;" required>
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

        <!-- Import CSV Modal -->
        <template x-if="showImportModal">
            <div class="overlay" x-cloak @click.self="showImportModal = false">
                <div class="modal-center" style="max-width: 480px;"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100">
                    <div class="modal-hdr" style="background: var(--bg-2);">
                        <div class="modal-ttl" style="font-family: var(--font-display);">นำเข้าผู้ใช้งานจากไฟล์ CSV</div>
                        <button type="button" class="modal-cls" @click="showImportModal = false">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                                stroke-width="2.5" stroke-linecap="round">
                                <line x1="18" y1="6" x2="6" y2="18"/>
                                <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>
                    <form method="POST" action="{{ route('admin.users.import') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="modal-body" style="padding: 24px;">
                            <div style="background: var(--bg-2); border: 1px solid var(--border); border-radius: 8px; padding: 14px 16px; margin-bottom: 20px; font-size: 13px; line-height: 1.7; color: var(--fg-muted);">
                                <strong style="color: var(--fg-base); display: block; margin-bottom: 4px;">รูปแบบไฟล์ CSV</strong>
                                คอลัมน์บังคับ: <code>prefix, name, email, username, password, roles, primary_role</code><br>
                                คอลัมน์เสริม: <code>employee_id, title, academic_degree, department_name, employment_type, teaching_pct, hired_date</code><br>
                                <span style="margin-top: 6px; display: block;">• roles คั่นด้วย <code>|</code> เช่น <code>instructor|course_head</code></span>
                                <span>• academic_degree: <code>doctoral</code> หรือ <code>non_doctoral</code></span>
                            </div>
                            <div style="margin-bottom: 16px;">
                                <a href="{{ asset('templates/users_import.csv') }}"
                                    style="font-size: 13px; color: var(--accent); text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                        <polyline points="7 10 12 15 17 10"/>
                                        <line x1="12" y1="15" x2="12" y2="3"/>
                                    </svg>
                                    ดาวน์โหลดไฟล์ตัวอย่าง (users_import.csv)
                                </a>
                            </div>
                            <div style="margin-bottom: 16px;">
                                <label class="frm-lbl">เลือกไฟล์ CSV <span style="color: var(--status-conflict-fg)">*</span></label>
                                <input type="file" name="csv_file" accept=".csv,.txt" required
                                    style="display: block; width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px; background: var(--bg-1);">
                                <div style="font-size: 12px; color: var(--fg-muted); margin-top: 4px;">UTF-8 (ไม่มี BOM), ไม่เกิน 5 MB, แนะนำไม่เกิน 500 แถว</div>
                            </div>
                            <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; padding: 12px 14px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-2);">
                                <input type="checkbox" name="update_on_duplicate" value="1" style="margin-top: 2px; flex-shrink: 0;">
                                <div>
                                    <div style="font-size: 13px; font-weight: 600; color: var(--fg-base);">อัปเดตข้อมูลถ้า email หรือ username ซ้ำ</div>
                                    <div style="font-size: 12px; color: var(--fg-muted); margin-top: 2px;">อัปเดต: ชื่อ, คำนำหน้า, บทบาท, ข้อมูลอาจารย์ — ไม่เปลี่ยนรหัสผ่าน</div>
                                </div>
                            </label>
                        </div>
                        <div class="modal-foot">
                            <button type="button" class="btn btn-ghost" @click="showImportModal = false">ยกเลิก</button>
                            <button type="submit" class="btn btn-primary">นำเข้าข้อมูล</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </div>

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