@php
    $course           = $courseOffering->course;
    $academicYear     = $courseOffering->academicYear;
    $canEdit          = $academicYear?->phase === 'scheduling';
    $lectureHours     = $course?->lecture_hours ?? 0;
    $labHours         = $course?->lab_hours ?? 0;
    $studentTotal     = $courseOffering->studentGroups->sum('student_count');
    $courseCapacity   = $course?->capacity ?? 0;
    $studentLimit     = $courseOffering->total_student_count ?: $courseCapacity;
    $ungrouped        = max(0, $studentLimit - $studentTotal);
    $defaultRotation  = (bool) ($course?->requires_practicum_rotation ?? false);
    $courseInfoErrorKeys = ['requires_practicum_rotation', 'practicum_note'];
    $instructorErrorKeys = ['user_id', 'course_role_id', 'instructor_pool'];
    $studentGroupErrorKeys = [
        'group_code',
        'student_count',
        'color_code',
        'group_prefix',
        'start_number',
        'group_count',
        'group_counts',
        'group_counts.*',
        'total_students',
        'group_ids',
        'group_ids.*',
        'student_groups',
    ];
    $courseInfoErrorKey = collect($courseInfoErrorKeys)->first(fn ($key) => $errors->has($key));
    $instructorErrorKey = collect($instructorErrorKeys)->first(fn ($key) => $errors->has($key));
    $studentGroupErrorKey = collect($studentGroupErrorKeys)->first(fn ($key) => $errors->has($key));
    $errorSection = session('error_section');
@endphp

<script>
    (function () {
        var key = 'tpss.courseOffering.scrollY.{{ $courseOffering->id }}';
        try {
            if ('scrollRestoration' in history) {
                history.scrollRestoration = 'manual';
            }

            var saved = window.sessionStorage.getItem(key);
            if (saved !== null) {
                window.sessionStorage.removeItem(key);
                var top = parseInt(saved, 10);
                if (Number.isFinite(top) && top >= 0) {
                    window.scrollTo(0, top);
                    requestAnimationFrame(function () { window.scrollTo(0, top); });
                }
            }

            window.tpssRememberCourseOfferingScroll = function () {
                try {
                    window.sessionStorage.setItem(key, String(window.scrollY || window.pageYOffset || 0));
                } catch (error) {}
            };
        } catch (error) {}
    })();
</script>

<x-app-layout title="รายละเอียดรายวิชา">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px;flex-wrap:wrap;">
        <div>
            <a href="{{ route('maker.course_offerings.index') }}" class="body-sm" style="color:var(--brand-navy);text-decoration:none;">← กลับไปรายการรายวิชา</a>
            <h1 class="h1" style="margin:8px 0 6px;">{{ $course?->course_code ?? '-' }} {{ $course?->name_th ?? $course?->name_en ?? '' }}</h1>
            <p class="body-sm" style="margin:0;">
                {{ $course?->curriculum?->name ?? '-' }} · {{ $academicYear?->name ?? '-' }} / เทอม {{ $academicYear?->semester ?? '-' }}
            </p>
        </div>
        <div class="card-actions">
            @php $phase = $courseOffering->academicYear?->phase ?? 'preparation'; @endphp
            <a href="{{ route('maker.course_offerings.schedules.index', $courseOffering) }}" class="btn btn-primary" data-testid="course-offering-schedules-link">จัดตารางสอน</a>
            @if($phase === 'scheduling')
                <span class="badge" style="background:oklch(90% 0.1 145);color:oklch(30% 0.15 145);border:1px solid oklch(70% 0.15 145);">เปิดจัดตาราง</span>
            @elseif($phase === 'published')
                <span class="badge badge-primary">เผยแพร่แล้ว</span>
            @else
                <span class="badge badge-gray">ยังไม่เปิดจัดตาราง</span>
            @endif
            @if($courseOffering->requires_practicum_rotation)
                <span class="badge badge-warn">ฝึกปฏิบัติ</span>
            @endif
        </div>
    </div>

    @if(session('error') && ! $errorSection)
        <div style="background:oklch(95% 0.05 25);border:1px solid oklch(70% 0.15 25);color:oklch(35% 0.12 25);padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:14px;">
            {{ session('error') }}
        </div>
    @endif

    @if(!$canEdit)
        <div style="background:oklch(97% 0.02 250);border:1px solid oklch(80% 0.05 250);color:oklch(40% 0.08 250);padding:12px 18px;border-radius:6px;margin-bottom:20px;font-size:14px;display:flex;align-items:center;gap:10px;">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;opacity:0.6;">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            <span>ยังไม่เปิดช่วงจัดตาราง — ดูข้อมูลได้อย่างเดียว การแก้ไขจะเปิดใช้งานเมื่อ Admin เปิดช่วงจัดตาราง</span>
        </div>
    @endif

    <div class="stats-grid">
        <div class="st-card">
            <div class="st-val">{{ $courseCapacity ?: '-' }}</div>
            <div class="st-lbl">จำนวนที่เปิดรับ</div>
        </div>
        <div class="st-card">
            <div class="st-val">{{ $studentTotal }}</div>
            <div class="st-lbl">จัดกลุ่มแล้ว</div>
        </div>
        <div class="st-card">
            <div class="st-val">{{ $courseOffering->studentGroups->count() }}</div>
            <div class="st-lbl">กลุ่มนักศึกษา</div>
        </div>
        <div class="st-card">
            <div class="st-val">{{ $courseOffering->instructorPool->count() }}</div>
            <div class="st-lbl">ผู้สอนในรายวิชา</div>
        </div>
        <div class="st-card">
            <div class="st-val">{{ $lectureHours }} / {{ $labHours }}</div>
            <div class="st-lbl">บรรยาย / ปฏิบัติ (ชั่วโมง)</div>
        </div>
    </div>

    <div class="card" id="course-info">
        <div class="card-hdr">
            <div>
                <div class="card-ttl">ข้อมูลรายวิชา</div>
                <div class="caption" style="margin-top:4px;">ข้อมูลจากรายวิชาหลักและการตั้งค่าระบบ</div>
            </div>
        </div>
        <div style="padding:20px;">
            @if($courseInfoErrorKey)
                <div class="section-error-alert">
                    {{ $errors->first($courseInfoErrorKey) }}
                </div>
            @endif
            @if(session('error') && $errorSection === 'course-info')
                <div class="section-error-alert">
                    {{ session('error') }}
                </div>
            @endif

            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:20px;margin-bottom:20px;">
                <div>
                    <div class="caption">ภาควิชา</div>
                    <div style="font-weight:600;margin-top:4px;">{{ $course?->department?->name ?? '-' }}</div>
                </div>
                <div>
                    <div class="caption">หน่วยกิต</div>
                    <div style="font-weight:600;margin-top:4px;">{{ $course?->credits ?? '-' }} หน่วยกิต</div>
                </div>
                <div>
                    <div class="caption">ชั้นปี</div>
                    <div style="font-weight:600;margin-top:4px;">ปี {{ $course?->default_year_level ?? '-' }}</div>
                </div>
                <div>
                    <div class="caption">จำนวนที่เปิดรับ</div>
                    <div style="font-weight:600;margin-top:4px;">{{ $courseCapacity ?: '-' }} คน</div>
                </div>
                <div>
                    <div class="caption">จำนวนสัปดาห์สอน</div>
                    <div style="font-weight:600;margin-top:4px;">{{ $teachingWeeks }} สัปดาห์ <span class="caption">(ค่าตั้งระบบ)</span></div>
                </div>
                <div>
                    <div class="caption">ชั่วโมงบรรยาย</div>
                    <div style="font-weight:600;margin-top:4px;">{{ $lectureHours }} ชั่วโมง</div>
                </div>
                <div>
                    <div class="caption">ชั่วโมงปฏิบัติการ</div>
                    <div style="font-weight:600;margin-top:4px;">{{ $labHours }} ชั่วโมง</div>
                </div>
            </div>

            @if($canEdit)
            <form method="POST"
                action="{{ route('maker.course_offerings.update', $courseOffering) }}"
                x-data="{
                    rotation: '{{ old('requires_practicum_rotation', $courseOffering->requires_practicum_rotation ? '1' : '0') }}',
                    defaultRotation: '{{ $defaultRotation ? '1' : '0' }}',
                    get isOverride() { return this.rotation !== this.defaultRotation; }
                }"
                style="border-top:1px solid var(--border-1);padding-top:20px;">
                @csrf
                @method('PUT')
                <div class="form-row">
                    <div class="form-group">
                        <label>การจัดรอบฝึกปฏิบัติ</label>
                        <select name="requires_practicum_rotation" x-model="rotation">
                            <option value="0" @selected(! $courseOffering->requires_practicum_rotation)>ไม่มีการหมุนเวียนแหล่งฝึก</option>
                            <option value="1" @selected($courseOffering->requires_practicum_rotation)>มีการหมุนเวียนแหล่งฝึก</option>
                        </select>
                        <div class="caption" style="margin-top:6px;">
                            ค่าเริ่มต้นจาก Master Data: {{ $defaultRotation ? 'มีการหมุนเวียนแหล่งฝึก' : 'ไม่มีการหมุนเวียนแหล่งฝึก' }}
                        </div>
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-start;padding-top:24px;">
                        <button type="submit" class="btn btn-primary" style="min-height:46px;padding-inline:22px;">บันทึก</button>
                    </div>
                </div>
                <div x-show="isOverride" x-cloak
                    style="margin-top:4px;margin-bottom:14px;padding:12px 14px;border:1px solid oklch(84% 0.08 80);border-radius:8px;background:oklch(98% 0.025 85);color:oklch(38% 0.08 75);font-size:13px;line-height:1.55;">
                    รอบเปิดสอนนี้กำลังใช้ค่าการหมุนเวียนต่างจาก Master Data กรุณาระบุเหตุผลเพื่อใช้ตรวจสอบย้อนหลัง
                </div>
                <div class="form-group" x-show="isOverride" x-cloak style="margin-bottom:0;">
                    <label>หมายเหตุเมื่อเปลี่ยนต่างจาก Master Data <span style="color:var(--status-conflict-fg)">*</span></label>
                    <textarea name="practicum_note" rows="3" maxlength="1000"
                        :required="isOverride"
                        placeholder="เช่น ปีการศึกษานี้ใช้ simulation lab แทนการหมุนเวียนแหล่งฝึก">{{ old('practicum_note', $courseOffering->practicum_note) }}</textarea>
                </div>
            </form>
            @else
            <div style="border-top:1px solid var(--border-1);padding-top:20px;display:grid;grid-template-columns:repeat(2,1fr);gap:16px;">
                <div>
                    <div class="caption">การจัดรอบฝึกปฏิบัติ</div>
                    <div style="font-weight:600;margin-top:4px;">{{ $courseOffering->requires_practicum_rotation ? 'มีการหมุนเวียนแหล่งฝึก' : 'ไม่มีการหมุนเวียนแหล่งฝึก' }}</div>
                    @if($courseOffering->requires_practicum_rotation !== $defaultRotation)
                        <div class="caption" style="margin-top:5px;color:oklch(42% 0.09 75);">ต่างจากค่าเริ่มต้นใน Master Data</div>
                    @endif
                </div>
                @if($courseOffering->practicum_note)
                <div>
                    <div class="caption">หมายเหตุการฝึกปฏิบัติ</div>
                    <div style="margin-top:4px;">{{ $courseOffering->practicum_note }}</div>
                </div>
                @endif
            </div>
            @endif
        </div>
    </div>

    @php
        $poolData = $courseOffering->instructorPool->map(fn($u) => [
            'id'             => $u->id,
            'name'           => $u->formatted_name,
            'department'     => $u->instructorProfile?->department?->name ?? '-',
            'department_id'  => $u->instructorProfile?->department_id,
            'is_coordinator' => (int) $u->id === (int) $courseOffering->coordinator_id,
            'course_role_id' => $u->pivot->course_role_id,
            'role_name'      => optional($courseRoles->firstWhere('id', $u->pivot->course_role_id))->name_th
                ?? ($u->pivot->role_in_course === 'coordinator' ? 'หัวหน้าวิชา' : null),
        ]);
        $allInstructors = $availableInstructors->map(fn($u) => [
            'id'           => $u->id,
            'name'         => $u->formatted_name,
            'department'   => $u->instructorProfile?->department?->name ?? '-',
            'department_id'=> $u->instructorProfile?->department_id,
        ]);
        $courseRolesData = $courseRoles->map(fn($r) => ['id' => $r->id, 'name' => $r->name_th]);
        $storeUrl    = route('maker.course_offerings.instructors.store', $courseOffering);
        $roleBase    = route('maker.course_offerings.instructors.role', [$courseOffering, '__ID__']);
        $destroyBase = route('maker.course_offerings.instructors.destroy', [$courseOffering, '__ID__']);
        $courseDeptId = $course?->department_id;
    @endphp

    <div class="card" id="instructors" style="overflow:visible;scroll-margin-top:72px;" x-data="{
        pool: {{ $poolData->toJson() }},
        all: {{ $allInstructors->toJson() }},
        roles: {{ $courseRolesData->toJson() }},
        search: '',
        open: false,
        showAll: false,
        loading: false,
        error: '',
        ddTop: 0, ddLeft: 0, ddWidth: 0,
        roleMenuId: null,
        storeUrl: '{{ $storeUrl }}',
        roleBase: '{{ $roleBase }}',
        destroyBase: '{{ $destroyBase }}',
        csrfToken: '{{ csrf_token() }}',
        courseDeptId: {{ $courseDeptId ?? 'null' }},
        async changeRole(userId, roleId) {
            this.loading = true; this.error = '';
            try {
                const r = await fetch(this.roleBase.replace('__ID__', userId), {
                    method: 'PATCH', credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ course_role_id: roleId })
                });
                const data = await r.json();
                if (!r.ok) { this.error = data.message ?? 'เกิดข้อผิดพลาด'; return; }
                const u = this.pool.find(x => x.id === userId);
                if (u) { u.course_role_id = data.course_role_id; u.role_name = data.role_name; }
                this.roleMenuId = null;
            } catch { this.error = 'ไม่สามารถเชื่อมต่อได้'; }
            finally { this.loading = false; }
        },
        get available() {
            const s = this.search.toLowerCase();
            const inPool = new Set(this.pool.map(u => u.id));
            return this.all.filter(u => {
                if (inPool.has(u.id)) return false;
                if (!this.showAll && this.courseDeptId) {
                    if (u.department_id !== this.courseDeptId) return false;
                }
                return s === '' || u.name.toLowerCase().includes(s) || u.department.toLowerCase().includes(s);
            });
        },
        openDropdown() {
            const r = this.$refs.searchInput.getBoundingClientRect();
            this.ddTop = r.bottom + window.scrollY + 4;
            this.ddLeft = r.left + window.scrollX;
            this.ddWidth = r.width;
            this.open = true;
        },
        async add(user) {
            this.loading = true; this.error = '';
            try {
                const r = await fetch(this.storeUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({ user_id: user.id })
                });
                const data = await r.json();
                if (!r.ok) { this.error = data.message ?? 'เกิดข้อผิดพลาด'; return; }
                this.pool.push(data);
                this.search = ''; this.open = false;
            } catch { this.error = 'ไม่สามารถเชื่อมต่อได้'; }
            finally { this.loading = false; }
        },
        async remove(userId) {
            this.error = '';
            const url = this.destroyBase.replace('__ID__', userId);
            try {
                const r = await fetch(url, {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken }
                });
                const data = await r.json();
                if (!r.ok) { this.error = data.message ?? 'เกิดข้อผิดพลาด'; return; }
                this.pool = this.pool.filter(u => u.id !== userId);
            } catch { this.error = 'ไม่สามารถเชื่อมต่อได้'; }
        }
    }">
        <div class="card-hdr">
            <div>
                <div class="card-ttl">ชุดผู้สอน</div>
                <div class="caption" style="margin-top:4px;" x-text="pool.length ? pool.length + ' คน' : 'ยังไม่มีผู้สอน'"></div>
            </div>
        </div>
        <div style="padding:20px;">
            @if($instructorErrorKey)
                <div class="section-error-alert">
                    {{ $errors->first($instructorErrorKey) }}
                </div>
            @endif
            @if(session('error') && $errorSection === 'instructors')
                <div class="section-error-alert">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Error message --}}
            <div x-show="error" x-text="error" style="background:var(--status-conflict-bg);border:1px solid var(--status-conflict-border);color:var(--status-conflict-fg);padding:10px 14px;border-radius:6px;font-size:13px;margin-bottom:14px;"></div>

            @if($canEdit)
            {{-- Combobox --}}
            <div style="position:relative;margin-bottom:20px;">
                <div style="position:relative;">
                    <input
                        x-ref="searchInput"
                        type="text"
                        x-model="search"
                        @focus="openDropdown()"
                        @input="openDropdown()"
                        placeholder="ค้นหาชื่ออาจารย์หรือภาควิชา..."
                        style="width:100%;padding-right:32px;"
                        autocomplete="off"
                    >
                    <svg x-show="!loading" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);opacity:0.4;pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <svg x-show="loading" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);opacity:0.4;pointer-events:none;animation:spin 1s linear infinite;"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4"/></svg>
                </div>

                {{-- Backdrop --}}
                <template x-teleport="body">
                    <div x-show="open" x-cloak @click="open = false; search = ''" style="position:fixed;inset:0;z-index:98;"></div>
                </template>

                {{-- Dropdown teleported to body --}}
                <template x-teleport="body">
                    <div
                        x-show="open"
                        x-cloak
                        :style="`position:absolute;top:${ddTop}px;left:${ddLeft}px;width:${ddWidth}px;background:#fff;border:1px solid var(--border-1);border-radius:6px;box-shadow:0 4px 16px rgba(0,0,0,0.12);z-index:99;`"
                    >
                        {{-- Filter toggle inside dropdown --}}
                        <div x-show="courseDeptId" style="display:flex;align-items:center;gap:4px;padding:8px 10px;border-bottom:1px solid var(--border-1);background:var(--surface-1);">
                            <button type="button"
                                @click.stop="showAll = false"
                                :style="!showAll ? 'background:var(--brand-navy);color:#fff;' : 'background:transparent;color:var(--fg-3);'"
                                style="border:none;cursor:pointer;font-size:12px;padding:3px 10px;border-radius:3px;font-family:var(--font-sans);transition:background 0.1s;">
                                เฉพาะภาควิชานี้
                            </button>
                            <button type="button"
                                @click.stop="showAll = true"
                                :style="showAll ? 'background:var(--brand-navy);color:#fff;' : 'background:transparent;color:var(--fg-3);'"
                                style="border:none;cursor:pointer;font-size:12px;padding:3px 10px;border-radius:3px;font-family:var(--font-sans);transition:background 0.1s;">
                                อาจารย์ทั้งหมด
                            </button>
                        </div>
                        {{-- Results --}}
                        <div style="max-height:220px;overflow-y:auto;">
                            <template x-for="user in available" :key="user.id">
                                <div
                                    @click="add(user)"
                                    style="padding:10px 14px;cursor:pointer;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border-1);"
                                    @mouseenter="$el.style.background='var(--surface-2)'"
                                    @mouseleave="$el.style.background=''"
                                >
                                    <div>
                                        <div style="font-weight:600;font-size:14px;" x-text="user.name"></div>
                                        <div style="font-size:12px;color:var(--fg-3);" x-text="user.department"></div>
                                    </div>
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="opacity:0.4;flex-shrink:0;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                </div>
                            </template>
                            <div
                                x-show="search.length > 0 && available.length === 0"
                                style="padding:12px 14px;font-size:13px;color:var(--fg-3);"
                            >ไม่พบอาจารย์ที่ตรงกัน</div>
                        </div>
                    </div>
                </template>
            </div>
            @endif

            {{-- Pills --}}
            <div style="display:flex;flex-direction:column;gap:6px;" x-show="pool.length > 0">
                <template x-for="user in pool" :key="user.id">
                    <div style="display:flex;align-items:center;gap:16px;background:#fff;border:1px solid var(--border-1);border-radius:6px;padding:12px 16px;">
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:600;font-size:14px;color:var(--fg-1);" x-text="user.name"></div>
                            <div style="color:var(--fg-3);font-size:12px;margin-top:2px;" x-text="user.department"></div>
                        </div>

                        {{-- Role selector (coordinator = static badge) --}}
                        <template x-if="user.is_coordinator">
                            <div class="course-role-badge course-role-badge-head">
                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 3l7 4v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V7l7-4z"/>
                                    <path d="M9 12l2 2 4-5"/>
                                </svg>
                                <span>หัวหน้าวิชา</span>
                            </div>
                        </template>
                        <template x-if="!user.is_coordinator">
                            <div class="course-role-control">
                                @if($canEdit)
                                <button type="button"
                                    class="course-role-trigger"
                                    :class="user.role_name ? 'is-assigned' : 'is-empty'"
                                    @click.stop="roleMenuId = roleMenuId === user.id ? null : user.id"
                                    :aria-expanded="roleMenuId === user.id"
                                    aria-haspopup="listbox">
                                    <span class="course-role-trigger-text" x-text="user.role_name || 'ยังไม่กำหนดบทบาท'"></span>
                                    <svg class="course-role-chevron" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M6 9l6 6 6-6"/>
                                    </svg>
                                </button>
                                <div x-show="roleMenuId === user.id"
                                    x-cloak
                                    @click.outside="roleMenuId = null"
                                    x-transition:enter="transition ease-out duration-150"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    class="course-role-menu"
                                    role="listbox">
                                    <button type="button"
                                        class="course-role-option"
                                        :class="{ 'is-selected': !user.course_role_id }"
                                        @click="changeRole(user.id, null)"
                                        role="option">
                                        <span class="course-role-option-label">ยังไม่กำหนดบทบาท</span>
                                        <svg x-show="!user.course_role_id" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M20 6L9 17l-5-5"/>
                                        </svg>
                                    </button>
                                    <template x-for="role in roles" :key="role.id">
                                        <button type="button"
                                            class="course-role-option"
                                            :class="{ 'is-selected': user.course_role_id === role.id }"
                                            @click="changeRole(user.id, role.id)"
                                            role="option">
                                            <span class="course-role-option-label" x-text="role.name"></span>
                                            <svg x-show="user.course_role_id === role.id" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M20 6L9 17l-5-5"/>
                                            </svg>
                                        </button>
                                    </template>
                                </div>
                                @else
                                <div class="course-role-readonly" :class="user.role_name ? 'is-assigned' : 'is-empty'">
                                    <span class="course-role-dot"></span>
                                    <span x-text="user.role_name || 'ยังไม่กำหนดบทบาท'"></span>
                                </div>
                                @endif
                            </div>
                        </template>

                        @if($canEdit)
                        <button type="button" x-show="!user.is_coordinator" @click="remove(user.id)" title="ลบอาจารย์ออกจากชุดผู้สอน"
                            style="background:transparent;border:none;cursor:pointer;width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center;color:var(--fg-3);border-radius:50%;flex-shrink:0;transition:all 0.15s;"
                            @mouseenter="$el.style.background='#fee2e2';$el.style.color='#dc2626'"
                            @mouseleave="$el.style.background='transparent';$el.style.color='var(--fg-3)'">
                            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                        <div x-show="user.is_coordinator" style="width:32px;flex-shrink:0;"></div>
                        @endif
                    </div>
                </template>
            </div>
            <div x-show="pool.length === 0" style="color:var(--fg-3);font-size:14px;">ยังไม่มีผู้สอนในรายวิชานี้</div>
        </div>
    </div>

    <style>
        @keyframes spin { to { transform: translateY(-50%) rotate(360deg); } }

        .section-error-alert {
            margin-bottom: 14px;
            padding: 10px 14px;
            border: 1px solid var(--status-conflict-border);
            border-radius: 6px;
            background: var(--status-conflict-bg);
            color: var(--status-conflict-fg);
            font-size: 13px;
            font-weight: 600;
            line-height: 1.55;
        }

        .course-role-control {
            position: relative;
            flex-shrink: 0;
            width: 250px;
        }

        .course-role-trigger,
        .course-role-readonly,
        .course-role-badge {
            min-height: 38px;
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 8px;
            padding: 8px 12px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.35;
            white-space: nowrap;
        }

        .course-role-trigger {
            cursor: pointer;
            transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        }

        .course-role-trigger:hover {
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
        }

        .course-role-trigger:focus-visible {
            outline: 2px solid rgba(0, 36, 84, 0.24);
            outline-offset: 2px;
        }

        .course-role-trigger.is-assigned,
        .course-role-readonly.is-assigned {
            background: oklch(96% 0.025 255);
            border: 1px solid oklch(82% 0.055 255);
            color: oklch(34% 0.09 255);
        }

        .course-role-trigger.is-empty,
        .course-role-readonly.is-empty {
            background: oklch(97% 0.045 82);
            border: 1px solid oklch(84% 0.09 82);
            color: oklch(43% 0.1 72);
            font-style: italic;
        }

        .course-role-badge-head {
            flex-shrink: 0;
            width: 250px;
            background: oklch(96% 0.055 150);
            border: 1px solid oklch(78% 0.12 150);
            color: oklch(33% 0.11 150);
        }

        .course-role-dot {
            width: 7px;
            height: 7px;
            flex: 0 0 7px;
            border-radius: 999px;
            background: currentColor;
            opacity: 0.72;
        }

        .course-role-trigger-text {
            flex: 1;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            text-align: center;
        }

        .course-role-chevron {
            flex: 0 0 auto;
            opacity: 0.7;
        }

        .course-role-menu {
            position: absolute;
            right: 0;
            top: calc(100% + 6px);
            width: 280px;
            max-height: 280px;
            overflow-y: auto;
            padding: 8px;
            border: 1px solid oklch(88% 0.018 240);
            border-radius: 8px;
            background: rgba(252, 254, 255, 0.98);
            box-shadow: 0 18px 38px rgba(15, 23, 42, 0.18), 0 2px 8px rgba(15, 23, 42, 0.08);
            z-index: 40;
            transform-origin: top right;
        }

        .course-role-option {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            min-height: 36px;
            border: 0;
            border-radius: 6px;
            background: rgba(252, 254, 255, 0.94);
            color: var(--fg-1);
            cursor: pointer;
            padding: 8px 10px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            text-align: left;
        }

        .course-role-option:hover,
        .course-role-option.is-selected {
            background: oklch(95% 0.025 240);
            color: var(--brand-navy);
        }

        .course-role-option-label {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .group-builder {
            margin-bottom: 18px;
            padding: 16px;
            border: 1px solid oklch(89% 0.02 235);
            border-radius: 8px;
            background: oklch(98% 0.012 230);
        }

        .group-builder-main {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 14px;
        }

        .group-builder-copy {
            min-width: 0;
            flex: 1;
        }

        .group-builder-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .group-builder-actions {
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }

        .group-builder-title {
            color: var(--fg-1);
            font-weight: 800;
            font-size: 15px;
        }

        .group-total-pill {
            min-height: 34px;
            display: inline-flex;
            align-items: center;
            border: 1px solid oklch(82% 0.055 245);
            border-radius: 999px;
            background: oklch(96% 0.025 245);
            color: var(--brand-navy);
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;
        }

        .group-builder-submit {
            flex: 0 0 auto;
            width: auto;
            min-width: 0;
            padding-inline: 14px;
            white-space: nowrap;
        }

        .group-builder-fields {
            display: grid;
            grid-template-columns: 1.2fr repeat(2, minmax(120px, 1fr));
            gap: 12px;
            align-items: end;
        }

        .group-builder-fields label {
            display: flex;
            flex-direction: column;
            gap: 6px;
            color: var(--fg-2);
            font-size: 13px;
            font-weight: 700;
        }

        .group-mode-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .group-mode-btn {
            min-height: 32px;
            border: 1px solid oklch(86% 0.018 235);
            border-radius: 999px;
            background: rgba(252, 254, 255, 0.96);
            color: var(--fg-2);
            cursor: pointer;
            padding: 5px 12px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 700;
        }

        .group-mode-btn.is-active {
            border-color: oklch(72% 0.08 245);
            background: oklch(95% 0.03 245);
            color: var(--brand-navy);
        }

        .group-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            margin-top: 12px;
        }

        .group-preview-chip {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            min-height: 30px;
            padding: 5px 10px;
            border: 1px solid oklch(88% 0.018 235);
            border-radius: 999px;
            background: rgba(252, 254, 255, 0.96);
            color: var(--fg-1);
            font-size: 13px;
        }

        .group-preview-chip span:last-child {
            color: var(--fg-3);
        }

        .group-preview-color {
            width: 9px;
            height: 9px;
            border-radius: 999px;
            flex: 0 0 9px;
        }

        .group-count-mini {
            width: 72px;
            min-height: 28px;
            border-radius: 999px;
            padding: 3px 8px;
            text-align: center;
            font-size: 13px;
            font-weight: 700;
        }

        .student-group-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .student-group-row {
            display: grid;
            grid-template-columns: minmax(220px, 1.3fr) minmax(120px, 0.55fr) minmax(100px, 0.45fr) auto;
            gap: 12px;
            align-items: center;
            padding: 10px 12px;
            border: 1px solid oklch(90% 0.014 235);
            border-radius: 8px;
            background: rgba(252, 254, 255, 0.98);
        }

        .student-group-row.has-bulk-select {
            grid-template-columns: 32px minmax(220px, 1.3fr) minmax(120px, 0.55fr) minmax(100px, 0.45fr) auto;
        }

        .student-group-row.is-readonly {
            grid-template-columns: minmax(220px, 1.3fr) minmax(120px, 0.55fr) minmax(100px, 0.45fr);
        }

        .student-group-select {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .student-group-select input {
            width: 18px;
            height: 18px;
            min-height: 18px;
            cursor: pointer;
        }

        .student-group-select-all {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: var(--fg-2);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .student-group-select-all input {
            width: 17px;
            height: 17px;
            cursor: pointer;
        }

        .student-group-row label {
            display: flex;
            flex-direction: column;
            gap: 4px;
            color: var(--fg-3);
            font-size: 11px;
            font-weight: 700;
        }

        .student-group-row input {
            min-height: 36px;
            font-size: 14px;
        }

        .student-group-code {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            min-width: 0;
        }

        .student-group-code label {
            flex: 1;
            min-width: 0;
        }

        .student-group-code-display {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .student-group-count input {
            max-width: 120px;
        }

        .student-group-color input[type='color'] {
            width: 52px;
            padding: 3px;
            cursor: pointer;
        }

        .student-group-swatch {
            width: 16px;
            height: 16px;
            flex: 0 0 16px;
            border-radius: 999px;
            margin-bottom: 10px;
            box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.14);
        }

        .student-group-row.has-bulk-select .student-group-swatch {
            position: absolute;
            left: 0;
            top: 31px;
            margin: 0;
        }

        .student-group-row.has-bulk-select .student-group-code {
            position: relative;
            padding-left: 26px;
        }

        .student-group-actions {
            display: inline-flex;
            justify-content: flex-end;
            gap: 6px;
        }

        .student-group-bulkbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 10px;
            padding: 10px 12px;
            border: 1px solid oklch(89% 0.018 235);
            border-radius: 8px;
            background: oklch(98% 0.006 235);
        }

        .student-group-bulkbar-actions {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-bulk-delete {
            min-height: 34px;
            border: 1px solid var(--status-conflict-border);
            border-radius: 8px;
            background: var(--status-conflict-bg);
            color: var(--status-conflict-fg);
            cursor: pointer;
            padding: 6px 12px;
            font-family: inherit;
            font-weight: 700;
        }

        .btn-bulk-delete:disabled {
            cursor: not-allowed;
            opacity: .45;
        }

        .student-group-confirm-overlay {
            position: fixed;
            inset: 0;
            z-index: var(--z-modal);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgba(15, 23, 42, .42);
        }

        .student-group-confirm-dialog {
            width: min(460px, 100%);
            border: 1px solid oklch(88% 0.014 235);
            border-radius: 14px;
            background: var(--surface);
            box-shadow: 0 24px 70px rgba(15, 23, 42, .2);
            padding: 22px;
        }

        .student-group-confirm-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .icon-btn-save,
        .icon-btn-delete {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            cursor: pointer;
            background: rgba(252, 254, 255, 0.96);
        }

        .icon-btn-save {
            border: 1px solid oklch(82% 0.055 245);
            color: var(--brand-navy);
        }

        .icon-btn-delete {
            border: 1px solid oklch(88% 0.028 30);
            color: oklch(42% 0.12 30);
        }

        .icon-btn-save:hover {
            background: oklch(96% 0.025 245);
        }

        .icon-btn-delete:hover {
            background: oklch(96% 0.035 30);
        }

        .student-group-empty {
            padding: 24px;
            text-align: center;
            color: var(--fg-3);
            border: 1px dashed oklch(86% 0.018 235);
            border-radius: 8px;
            background: oklch(98% 0.008 230);
        }

        @media (max-width: 720px) {
            .course-role-control,
            .course-role-badge-head {
                width: 100%;
            }

            .group-builder-main {
                flex-direction: column;
            }

            .group-builder-submit {
                width: 100%;
            }

            .group-builder-actions {
                width: 100%;
                justify-content: flex-start;
            }

            .group-builder-fields {
                grid-template-columns: 1fr 1fr;
            }

            .student-group-row,
            .student-group-row.is-readonly {
                grid-template-columns: 1fr;
            }

            .student-group-row.has-bulk-select {
                grid-template-columns: 32px 1fr;
            }

            .student-group-count input {
                max-width: none;
            }

            .student-group-actions {
                justify-content: flex-start;
            }
        }
    </style>

    <div class="card" id="student-groups" style="scroll-margin-top:72px;">
        <div class="card-hdr">
            <div>
                <div class="card-ttl">กลุ่มนักศึกษา</div>
                <div class="caption" style="margin-top:4px;">เปิดรับ {{ $studentLimit ?: '-' }} คน · จัดกลุ่มแล้ว {{ $studentTotal }} คน · ยังไม่ได้จัดกลุ่ม {{ $ungrouped }} คน</div>
            </div>
        </div>
        <div
            style="padding:20px;"
            x-data="{
                selectedGroups: [],
                groupIds: {{ Js::from($courseOffering->studentGroups->pluck('id')->map(fn ($id) => (string) $id)->values()) }},
                confirmBulkDeleteOpen: false,
                get allGroupsSelected() {
                    return this.groupIds.length > 0 && this.selectedGroups.length === this.groupIds.length;
                },
                toggleAllGroups(checked) {
                    this.selectedGroups = checked ? [...this.groupIds] : [];
                }
            }"
        >
            @if($studentGroupErrorKey)
                <div class="section-error-alert">
                    {{ $errors->first($studentGroupErrorKey) }}
                </div>
            @endif
            @if(session('error') && $errorSection === 'student-groups')
                <div class="section-error-alert">
                    {{ session('error') }}
                </div>
            @endif

            @if($canEdit && $ungrouped > 0)
            <form method="POST"
                action="{{ route('maker.course_offerings.student_groups.bulk_store', $courseOffering) }}"
                data-testid="bulk-groups-form"
                data-preserve-scroll
                @submit="window.tpssRememberCourseOfferingScroll && window.tpssRememberCourseOfferingScroll()"
                x-data="{
                    prefix: '{{ old('group_prefix', 'A') }}',
                    start: {{ (int) old('start_number', 1) }},
                    count: {{ (int) old('group_count', $ungrouped > 0 ? min(9, max(1, (int) ceil($ungrouped / 30))) : 1) }},
                    total: {{ (int) max(1, $ungrouped) }},
                    customMode: {{ old('group_counts') ? 'true' : 'false' }},
                    customCounts: {{ Js::from(array_map('intval', old('group_counts', []))) }},
                    palette: ['#2563eb', '#16a34a', '#ca8a04', '#dc2626', '#7c3aed', '#0891b2', '#db2777', '#4f46e5', '#65a30d', '#ea580c'],
                    get safeCount() { return Math.max(1, parseInt(this.count) || 1); },
                    get safeTotal() { return Math.max(1, parseInt(this.total) || 1); },
                    get base() { return Math.floor(this.safeTotal / this.safeCount); },
                    get remainder() { return this.safeTotal % this.safeCount; },
                    normalizeCounts() {
                        const counts = Array.from({ length: this.safeCount }, (_, i) => {
                            const fallback = this.base + (i < this.remainder ? 1 : 0);
                            const current = this.customCounts[i];

                            if (this.customMode && (current === '' || current === null)) {
                                return '';
                            }

                            if (current === undefined || current === '' || current === null) {
                                return fallback;
                            }

                            const parsed = parseInt(current);
                            return Number.isNaN(parsed) ? fallback : parsed;
                        });
                        this.customCounts = counts;
                    },
                    setEvenSplit() {
                        this.customMode = false;
                        this.customCounts = [];
                    },
                    enableCustom() {
                        this.customMode = true;
                        this.normalizeCounts();
                    },
                    get customTotal() {
                        this.normalizeCounts();
                        return this.customCounts.reduce((sum, count) => sum + (parseInt(count) || 0), 0);
                    },
                    get preview() {
                        this.normalizeCounts();
                        return Array.from({ length: this.customMode ? this.safeCount : Math.min(this.safeCount, 12) }, (_, i) => ({
                            index: i,
                            code: `${this.prefix || 'A'}${(parseInt(this.start) || 0) + i}`,
                            count: this.customMode ? this.customCounts[i] : this.base + (i < this.remainder ? 1 : 0),
                            color: this.palette[i % this.palette.length],
                        }));
                    }
                }"
                class="group-builder">
                @csrf
                <div class="group-builder-main">
                    <div class="group-builder-copy">
                        <div class="group-builder-title">สร้างกลุ่มแบบเร็ว</div>
                        <div class="caption" style="margin-top:3px;">ระบบใช้ยอดนักศึกษาที่ยังไม่ได้จัดกลุ่มจากข้อมูลรายวิชา แล้วช่วยแบ่งหรือให้ปรับรายกลุ่มได้</div>
                    </div>
                    <div class="group-builder-actions">
                        <div class="group-total-pill">ยังไม่ได้จัดกลุ่ม {{ $ungrouped }} คน</div>
                        <button class="btn btn-primary group-builder-submit" type="submit" data-testid="bulk-groups-submit">สร้างกลุ่ม+</button>
                    </div>
                </div>
                <div class="group-builder-fields">
                    <label>
                        <span>รหัสนำหน้า</span>
                        <input type="text" name="group_prefix" x-model="prefix" data-testid="bulk-group-prefix" required>
                    </label>
                    <label>
                        <span>เริ่มที่</span>
                        <input type="number" name="start_number" x-model.number="start" data-testid="bulk-group-start" min="0" required>
                    </label>
                    <label>
                        <span>จำนวนกลุ่ม</span>
                        <input type="number" name="group_count" x-model.number="count" data-testid="bulk-group-count" min="1" max="100" required>
                    </label>
                </div>
                <div class="group-mode-row">
                    <button type="button" class="group-mode-btn" :class="{ 'is-active': !customMode }" @click="setEvenSplit()">แบ่งเท่า ๆ กัน</button>
                    <button type="button" class="group-mode-btn" :class="{ 'is-active': customMode }" @click="enableCustom()">กำหนดเองรายกลุ่ม</button>
                    <span class="caption" x-show="customMode" x-text="'รวม ' + customTotal + ' คน'"></span>
                </div>
                <div class="group-preview" aria-label="ตัวอย่างกลุ่มที่จะสร้าง">
                    <template x-for="group in preview" :key="group.code">
                        <div class="group-preview-chip">
                            <span class="group-preview-color" :style="`background:${group.color}`"></span>
                            <strong x-text="group.code"></strong>
                            <template x-if="!customMode">
                                <span x-text="group.count + ' คน'"></span>
                            </template>
                            <template x-if="customMode">
                                <input class="group-count-mini" type="number" name="group_counts[]" min="1" max="9999" x-model="customCounts[group.index]">
                            </template>
                        </div>
                    </template>
                    <div class="caption" x-show="!customMode && safeCount > 12">แสดงตัวอย่าง 12 กลุ่มแรก</div>
                </div>
            </form>
            @elseif($canEdit)
                <div style="margin-bottom:18px;padding:12px 14px;border:1px solid oklch(88% 0.02 235);border-radius:8px;background:oklch(98% 0.012 230);color:var(--fg-2);font-size:14px;">
                    จัดกลุ่มครบตามจำนวนนักศึกษาที่เปิดรับแล้ว
                </div>
            @endif

            @if($canEdit && $courseOffering->studentGroups->isNotEmpty())
                <form
                    id="bulk-group-delete-form"
                    method="POST"
                    action="{{ route('maker.course_offerings.student_groups.bulk_destroy', $courseOffering) }}"
                    data-preserve-scroll
                    @submit="window.tpssRememberCourseOfferingScroll && window.tpssRememberCourseOfferingScroll()"
                >
                    @csrf
                    @method('DELETE')
                </form>
                <div class="student-group-bulkbar">
                    <div class="caption">
                        เลือกกลุ่มที่ต้องการลบได้หลายกลุ่ม
                        <span x-show="selectedGroups.length > 0" x-text="'· เลือกแล้ว ' + selectedGroups.length + ' กลุ่ม'"></span>
                    </div>
                    <div class="student-group-bulkbar-actions">
                        <label class="student-group-select-all">
                            <input
                                type="checkbox"
                                :checked="allGroupsSelected"
                                @change="toggleAllGroups($event.target.checked)"
                                data-testid="bulk-group-select-all"
                            >
                            ทั้งหมด
                        </label>
                        <button type="button" class="btn btn-ghost" style="min-height:34px;padding:6px 12px;font-size:13px;" @click="selectedGroups = []" x-show="selectedGroups.length > 0">
                            ล้างที่เลือก
                        </button>
                        <button
                            type="button"
                            class="btn-bulk-delete"
                            data-testid="bulk-groups-delete"
                            :disabled="selectedGroups.length < 1"
                            @click="if (selectedGroups.length > 0) confirmBulkDeleteOpen = true"
                        >
                            ลบกลุ่มที่เลือก
                        </button>
                    </div>
                </div>
            @endif

            <div class="student-group-list">
                @forelse($courseOffering->studentGroups as $group)
                    @if($canEdit)
                    <form method="POST" action="{{ route('maker.course_offerings.student_groups.update', [$courseOffering, $group]) }}" class="student-group-row has-bulk-select" data-testid="student-group-row" data-preserve-scroll @submit="window.tpssRememberCourseOfferingScroll && window.tpssRememberCourseOfferingScroll()">
                        @csrf
                        @method('PUT')
                        <div class="student-group-select">
                            <input
                                type="checkbox"
                                name="group_ids[]"
                                value="{{ $group->id }}"
                                form="bulk-group-delete-form"
                                x-model="selectedGroups"
                                data-testid="bulk-group-checkbox"
                                aria-label="เลือกกลุ่ม {{ $group->group_code }} เพื่อลบ"
                            >
                        </div>
                        <div class="student-group-code">
                            <span class="student-group-swatch" style="background:{{ $group->color_code ?: '#2563eb' }}"></span>
                            <label>
                                <span>รหัสกลุ่ม</span>
                                <input type="text" name="group_code" value="{{ $group->group_code }}" data-testid="student-group-code" required>
                            </label>
                        </div>
                        <label class="student-group-count">
                            <span>นักศึกษา</span>
                            <input type="number" name="student_count" min="1" value="{{ $group->student_count }}" required>
                        </label>
                        <label class="student-group-color">
                            <span>สี</span>
                            <input type="color" name="color_code" value="{{ $group->color_code ?: '#2563eb' }}">
                        </label>
                        <div class="student-group-actions">
                            <button class="icon-btn-save" type="submit" title="บันทึกกลุ่ม {{ $group->group_code }}">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/></svg>
                            </button>
                            <button form="group-delete-{{ $group->id }}" class="icon-btn-delete" type="submit" title="ลบกลุ่ม {{ $group->group_code }}">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            </button>
                        </div>
                    </form>
                    <form id="group-delete-{{ $group->id }}" method="POST" action="{{ route('maker.course_offerings.student_groups.destroy', [$courseOffering, $group]) }}" data-preserve-scroll onsubmit="window.tpssRememberCourseOfferingScroll && window.tpssRememberCourseOfferingScroll()">
                        @csrf
                        @method('DELETE')
                    </form>
                    @else
                    <div class="student-group-row is-readonly">
                        <div class="student-group-code-display">
                            <span class="student-group-swatch" style="background:{{ $group->color_code ?: '#2563eb' }}"></span>
                            <strong>{{ $group->group_code }}</strong>
                        </div>
                        <div>{{ $group->student_count }} คน</div>
                        <div class="caption">{{ $group->color_code ?: '-' }}</div>
                    </div>
                    @endif
                @empty
                    <div class="student-group-empty">ยังไม่มีกลุ่มนักศึกษา</div>
                @endforelse
            </div>

            @if($canEdit)
                <div
                    class="student-group-confirm-overlay"
                    x-show="confirmBulkDeleteOpen"
                    x-cloak
                    @keydown.escape.window="confirmBulkDeleteOpen = false"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="bulk-delete-title"
                >
                    <div class="student-group-confirm-dialog" @click.outside="confirmBulkDeleteOpen = false">
                        <div id="bulk-delete-title" style="font-size:18px;font-weight:800;color:var(--fg-1);">ยืนยันการลบกลุ่มนักศึกษา</div>
                        <div class="body-sm" style="margin-top:8px;color:var(--fg-2);">
                            คุณกำลังจะลบกลุ่มนักศึกษาที่เลือก <strong x-text="selectedGroups.length"></strong> กลุ่ม การลบนี้ไม่สามารถย้อนกลับได้
                        </div>
                        <div class="student-group-confirm-actions">
                            <button type="button" class="btn btn-ghost" @click="confirmBulkDeleteOpen = false">ยกเลิก</button>
                            <button
                                type="submit"
                                form="bulk-group-delete-form"
                                class="btn-bulk-delete"
                                @click="confirmBulkDeleteOpen = false"
                            >
                                ลบกลุ่มที่เลือก
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

</x-app-layout>
