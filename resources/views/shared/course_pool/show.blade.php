<x-app-layout title="ตั้งค่าผู้รับผิดชอบรายวิชา · {{ $course->course_code }}">
    {{-- Breadcrumb / Back --}}
    <div style="margin-bottom:16px;">
        <a href="{{ route($routePrefix . '.course_pool.index') }}" style="color:var(--fg-3);font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            กลับไปยังรายการวิชา
        </a>
    </div>

    {{-- Header --}}
    <div class="page-hdr" style="margin-bottom:24px;">
        <div>
            <h1 class="page-ttl">{{ $course->course_code }} · {{ $course->name_th }}</h1>
            <p class="page-sub">
                {{ $course->name_en }} · {{ $course->department?->name }} · {{ $course->credits }} หน่วยกิต
            </p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-error" style="margin-bottom:16px;">
            @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
        </div>
    @endif
    @if($isLocked)
        <div class="alert" data-testid="course-pool-locked-alert" style="margin-bottom:16px;background:oklch(97% 0.045 82);border:1px solid oklch(84% 0.09 82);color:oklch(35% 0.09 72);">
            แม่แบบผู้รับผิดชอบรายวิชานี้ถูกล็อกแล้ว เพราะมี Course Offering ที่อยู่ในช่วงจัดตารางหรือเผยแพร่แล้ว การแก้รายชื่อผู้สอนให้ทำในหน้า Course Offering ของรอบนั้น
        </div>
    @endif

    {{-- หัวหน้าวิชา --}}
    <div class="card" style="margin-bottom:16px;">
        <div class="card-hdr">
            <div>
                <div class="card-ttl">หัวหน้าวิชา / ผู้ประสานรายวิชา</div>
                <div class="caption" style="margin-top:2px;">ผู้รับผิดชอบหลักของรายวิชา จะถูก auto-assign เป็น coordinator ในทุก course offering</div>
            </div>
        </div>
        <div style="padding:20px;">
            @if($isLocked)
            <div>
                <div class="caption">หัวหน้าวิชาปัจจุบัน</div>
                <div style="font-weight:700;margin-top:4px;">{{ $course->headInstructor?->formatted_name ?? 'ยังไม่กำหนด' }}</div>
                <div class="caption" style="margin-top:4px;">แม่แบบถูกล็อกหลังเปิดจัดตารางแล้ว</div>
            </div>
            @else
            <form method="POST" action="{{ route($routePrefix . '.course_pool.head.update', $course) }}" style="display:flex;gap:12px;align-items:end;">
                @csrf @method('PUT')
                <div style="flex:1;">
                    <label style="font-size:13px;color:var(--fg-3);margin-bottom:6px;display:block;">เลือกหัวหน้าวิชา</label>
                    <select name="head_instructor_id" style="width:100%;">
                        <option value="">— ยังไม่กำหนด —</option>
                        @foreach($availableInstructors as $u)
                            <option value="{{ $u->id }}" @selected($course->head_instructor_id == $u->id)>
                                {{ $u->formatted_name }} ({{ $u->instructorProfile?->department?->name ?? '-' }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">บันทึก</button>
            </form>
            @endif
        </div>
    </div>

    {{-- เจ้าหน้าที่ผู้ดูแลวิชา --}}
    @php
        $staffData = $course->assignedStaff->map(fn($u) => ['id' => $u->id, 'name' => $u->formatted_name]);
        $allStaff  = $availableStaff->map(fn($u) => ['id' => $u->id, 'name' => $u->formatted_name]);
    @endphp
    <div class="card" style="margin-bottom:16px;" x-data="{
        pool: {{ $staffData->toJson() }},
        all: {{ $allStaff->toJson() }},
        search: '',
        open: false,
        loading: false,
        error: '',
        ddTop: 0, ddLeft: 0, ddWidth: 0,
        storeUrl: '{{ route($routePrefix . '.course_pool.staff.store', $course) }}',
        destroyBase: '{{ route($routePrefix . '.course_pool.staff.destroy', [$course, '__ID__']) }}',
        csrfToken: '{{ csrf_token() }}',
        get available() {
            const s = this.search.toLowerCase();
            const inPool = new Set(this.pool.map(u => u.id));
            return this.all.filter(u => !inPool.has(u.id) && (s === '' || u.name.toLowerCase().includes(s)));
        },
        openDropdown() {
            const r = this.$refs.searchInput.getBoundingClientRect();
            this.ddTop = r.bottom + window.scrollY + 4;
            this.ddLeft = r.left + window.scrollX;
            this.ddWidth = r.width;
            this.open = true;
        },
        async add(u) {
            this.loading = true; this.error = '';
            try {
                const r = await fetch(this.storeUrl, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: u.id })
                });
                const data = await r.json();
                if (!r.ok) { this.error = data.message ?? 'เกิดข้อผิดพลาด'; return; }
                this.pool.push(data); this.search = ''; this.open = false;
            } catch { this.error = 'ไม่สามารถเชื่อมต่อได้'; }
            finally { this.loading = false; }
        },
        async remove(id) {
            this.loading = true; this.error = '';
            try {
                const r = await fetch(this.destroyBase.replace('__ID__', id), {
                    method: 'DELETE', credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                });
                const data = await r.json();
                if (!r.ok) { this.error = data.message ?? 'เกิดข้อผิดพลาด'; return; }
                this.pool = this.pool.filter(u => u.id !== id);
            } catch { this.error = 'ไม่สามารถเชื่อมต่อได้'; }
            finally { this.loading = false; }
        }
    }">
        <div class="card-hdr">
            <div>
                <div class="card-ttl">เจ้าหน้าที่ผู้ดูแลวิชา <span style="color:var(--fg-3);font-weight:400;font-size:13px;" x-text="`(${pool.length} คน)`"></span></div>
                <div class="caption" style="margin-top:2px;">เจ้าหน้าที่ที่ช่วยจัดการเอกสารและประสานงานของวิชานี้</div>
            </div>
        </div>
        <div style="padding:20px;">
            @unless($isLocked)
            <div style="position:relative;margin-bottom:16px;">
                <input x-ref="searchInput" type="text" x-model="search" @focus="openDropdown()" @input="openDropdown()"
                    placeholder="ค้นหาชื่อเจ้าหน้าที่..." style="width:100%;" autocomplete="off">

                <template x-teleport="body">
                    <div x-show="open" x-cloak @click="open = false; search = ''" style="position:fixed;inset:0;z-index:98;"></div>
                </template>
                <template x-teleport="body">
                    <div x-show="open" x-cloak
                        :style="`position:absolute;top:${ddTop}px;left:${ddLeft}px;width:${ddWidth}px;background:#fff;border:1px solid var(--border-1);border-radius:6px;box-shadow:0 4px 16px rgba(0,0,0,0.12);z-index:99;max-height:240px;overflow-y:auto;`">
                        <template x-for="user in available" :key="user.id">
                            <div @click="add(user)" style="padding:10px 14px;cursor:pointer;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border-1);"
                                @mouseenter="$el.style.background='var(--surface-2)'" @mouseleave="$el.style.background=''">
                                <div style="font-weight:600;font-size:14px;" x-text="user.name"></div>
                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="opacity:0.4;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            </div>
                        </template>
                        <div x-show="search.length > 0 && available.length === 0" style="padding:12px 14px;font-size:13px;color:var(--fg-3);">ไม่พบเจ้าหน้าที่ที่ตรงกัน</div>
                    </div>
                </template>
            </div>
            @endunless

            <div style="display:flex;flex-wrap:wrap;gap:8px;" x-show="pool.length > 0">
                <template x-for="user in pool" :key="user.id">
                    <div style="display:inline-flex;align-items:center;gap:8px;background:var(--surface-2);border:1px solid var(--border-1);border-radius:6px;padding:6px 12px;font-size:14px;">
                        <span style="font-weight:600;" x-text="user.name"></span>
                        @unless($isLocked)
                        <button type="button" @click="remove(user.id)" style="background:none;border:none;cursor:pointer;padding:0;display:flex;opacity:0.5;line-height:1;" @mouseenter="$el.style.opacity='1'" @mouseleave="$el.style.opacity='0.5'">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                        @endunless
                    </div>
                </template>
            </div>
            <div x-show="pool.length === 0" style="color:var(--fg-3);font-size:14px;">ยังไม่มีเจ้าหน้าที่ผู้ดูแลวิชานี้</div>
        </div>
    </div>

    {{-- อาจารย์ผู้สอน --}}
    @php
        $courseDeptId = $course->department_id;
        $instData = $course->instructors->map(fn($u) => [
            'id'             => $u->id,
            'name'           => $u->formatted_name,
            'department'     => $u->instructorProfile?->department?->name ?? '-',
            'department_id'  => $u->instructorProfile?->department_id,
            'course_role_id' => $u->pivot->course_role_id,
            'role_name'      => optional($courseRoles->firstWhere('id', $u->pivot->course_role_id))->name_th,
        ]);
        $allInst = $availableInstructors->map(fn($u) => [
            'id'            => $u->id,
            'name'          => $u->formatted_name,
            'department'    => $u->instructorProfile?->department?->name ?? '-',
            'department_id' => $u->instructorProfile?->department_id,
        ]);
        $courseRolesData = $courseRoles->map(fn($r) => ['id' => $r->id, 'name' => $r->name_th]);
    @endphp
    <div class="card" style="overflow:visible;" x-data="{
        pool: {{ $instData->toJson() }},
        all: {{ $allInst->toJson() }},
        roles: {{ $courseRolesData->toJson() }},
        search: '',
        open: false,
        showAll: false,
        loading: false,
        error: '',
        ddTop: 0, ddLeft: 0, ddWidth: 0,
        roleEditingId: null,
        courseDeptId: {{ $courseDeptId ?? 'null' }},
        storeUrl: '{{ route($routePrefix . '.course_pool.instructors.store', $course) }}',
        roleBase: '{{ route($routePrefix . '.course_pool.instructors.role', [$course, '__ID__']) }}',
        destroyBase: '{{ route($routePrefix . '.course_pool.instructors.destroy', [$course, '__ID__']) }}',
        csrfToken: '{{ csrf_token() }}',
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
                this.roleEditingId = null;
            } catch { this.error = 'ไม่สามารถเชื่อมต่อได้'; }
            finally { this.loading = false; }
        },
        get available() {
            const s = this.search.toLowerCase();
            const inPool = new Set(this.pool.map(u => u.id));
            return this.all.filter(u => {
                if (inPool.has(u.id)) return false;
                if (!this.showAll && this.courseDeptId && u.department_id !== this.courseDeptId) return false;
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
        async add(u) {
            this.loading = true; this.error = '';
            try {
                const r = await fetch(this.storeUrl, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: u.id })
                });
                const data = await r.json();
                if (!r.ok) { this.error = data.message ?? 'เกิดข้อผิดพลาด'; return; }
                this.pool.push(data); this.search = ''; this.open = false;
            } catch { this.error = 'ไม่สามารถเชื่อมต่อได้'; }
            finally { this.loading = false; }
        },
        async remove(id) {
            this.loading = true; this.error = '';
            try {
                const r = await fetch(this.destroyBase.replace('__ID__', id), {
                    method: 'DELETE', credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                });
                const data = await r.json();
                if (!r.ok) { this.error = data.message ?? 'เกิดข้อผิดพลาด'; return; }
                this.pool = this.pool.filter(u => u.id !== id);
            } catch { this.error = 'ไม่สามารถเชื่อมต่อได้'; }
            finally { this.loading = false; }
        }
    }">
        <div class="card-hdr">
            <div>
                <div class="card-ttl">อาจารย์ผู้สอน <span style="color:var(--fg-3);font-weight:400;font-size:13px;" x-text="`(${pool.length} คน)`"></span></div>
                <div class="caption" style="margin-top:2px;">รายชื่ออาจารย์แม่แบบของรายวิชา ใช้ก่อนเปิดช่วงจัดตารางและจะถูกล็อกเมื่อ Course Offering ถูกสร้างแล้ว</div>
            </div>
        </div>
        <div style="padding:20px;">
            @unless($isLocked)
            <div style="position:relative;margin-bottom:16px;">
                <input x-ref="searchInput" type="text" x-model="search" @focus="openDropdown()" @input="openDropdown()"
                    placeholder="ค้นหาชื่ออาจารย์หรือภาควิชา..." style="width:100%;" autocomplete="off">

                <template x-teleport="body">
                    <div x-show="open" x-cloak @click="open = false; search = ''" style="position:fixed;inset:0;z-index:98;"></div>
                </template>
                <template x-teleport="body">
                    <div x-show="open" x-cloak
                        :style="`position:absolute;top:${ddTop}px;left:${ddLeft}px;width:${ddWidth}px;background:#fff;border:1px solid var(--border-1);border-radius:6px;box-shadow:0 4px 16px rgba(0,0,0,0.12);z-index:99;`">
                        <div x-show="courseDeptId" style="display:flex;align-items:center;gap:4px;padding:8px 10px;border-bottom:1px solid var(--border-1);background:var(--surface-1);">
                            <button type="button" @click.stop="showAll = false"
                                :style="!showAll ? 'background:var(--brand-navy);color:#fff;' : 'background:transparent;color:var(--fg-3);'"
                                style="border:none;cursor:pointer;font-size:12px;padding:3px 10px;border-radius:3px;">เฉพาะภาควิชานี้</button>
                            <button type="button" @click.stop="showAll = true"
                                :style="showAll ? 'background:var(--brand-navy);color:#fff;' : 'background:transparent;color:var(--fg-3);'"
                                style="border:none;cursor:pointer;font-size:12px;padding:3px 10px;border-radius:3px;">อาจารย์ทั้งหมด</button>
                        </div>
                        <div style="max-height:220px;overflow-y:auto;">
                            <template x-for="user in available" :key="user.id">
                                <div @click="add(user)" style="padding:10px 14px;cursor:pointer;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border-1);"
                                    @mouseenter="$el.style.background='var(--surface-2)'" @mouseleave="$el.style.background=''">
                                    <div>
                                        <div style="font-weight:600;font-size:14px;" x-text="user.name"></div>
                                        <div style="font-size:12px;color:var(--fg-3);" x-text="user.department"></div>
                                    </div>
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="opacity:0.4;flex-shrink:0;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                </div>
                            </template>
                            <div x-show="search.length > 0 && available.length === 0" style="padding:12px 14px;font-size:13px;color:var(--fg-3);">ไม่พบอาจารย์ที่ตรงกัน</div>
                        </div>
                    </div>
                </template>
            </div>
            @endunless

            <div style="display:flex;flex-direction:column;gap:6px;" x-show="pool.length > 0">
                <template x-for="user in pool" :key="user.id">
                    <div style="display:flex;align-items:center;gap:16px;background:#fff;border:1px solid var(--border-1);border-radius:6px;padding:12px 16px;">
                        {{-- Name + department --}}
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:600;font-size:14px;color:var(--fg-1);" x-text="user.name"></div>
                            <div style="color:var(--fg-3);font-size:12px;margin-top:2px;" x-text="user.department"></div>
                        </div>

                        {{-- Role selector --}}
                        <div style="flex-shrink:0;width:200px;">
                            <div class="course-role-control">
                                @if($isLocked)
                                <div class="course-role-readonly" :class="user.role_name ? 'is-assigned' : 'is-empty'">
                                    <span class="course-role-dot"></span>
                                    <span x-text="user.role_name || 'ยังไม่กำหนดบทบาท'"></span>
                                </div>
                                @else
                                <button type="button"
                                    class="course-role-trigger"
                                    :class="user.role_name ? 'is-assigned' : 'is-empty'"
                                    @click.stop="roleEditingId = roleEditingId === user.id ? null : user.id"
                                    :aria-expanded="roleEditingId === user.id"
                                    aria-haspopup="listbox">
                                    <span class="course-role-dot"></span>
                                    <span class="course-role-trigger-text" x-text="user.role_name || 'ยังไม่กำหนดบทบาท'"></span>
                                    <svg class="course-role-chevron" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M6 9l6 6 6-6"/>
                                    </svg>
                                </button>
                                <div x-show="roleEditingId === user.id"
                                    x-cloak
                                    @click.outside="roleEditingId = null"
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
                                @endif
                            </div>
                        </div>

                        {{-- Remove --}}
                        @unless($isLocked)
                        <button type="button" @click="remove(user.id)" title="ลบอาจารย์ออกจากชุดผู้สอน"
                            style="background:transparent;border:none;cursor:pointer;width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center;color:var(--fg-3);border-radius:50%;flex-shrink:0;transition:all 0.15s;"
                            @mouseenter="$el.style.background='#fee2e2';$el.style.color='#dc2626'"
                            @mouseleave="$el.style.background='transparent';$el.style.color='var(--fg-3)'">
                            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                        @endunless
                    </div>
                </template>
            </div>
            <div x-show="pool.length === 0" style="color:var(--fg-3);font-size:14px;">ยังไม่มีอาจารย์ผู้สอนในวิชานี้</div>
        </div>
    </div>

    <style>
        .course-role-control {
            position: relative;
            width: 100%;
        }

        .course-role-trigger,
        .course-role-readonly {
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
            transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        }

        .course-role-trigger {
            cursor: pointer;
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
    </style>
</x-app-layout>
