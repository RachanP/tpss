@php
    $course           = $courseOffering->course;
    $academicYear     = $courseOffering->academicYear;
    $canEdit          = $academicYear?->phase === 'scheduling';
    $lectureHours     = $course?->lecture_hours ?? 0;
    $labHours         = $course?->lab_hours ?? 0;
    $studentTotal     = $courseOffering->studentGroups->sum('student_count');
    $courseCapacity   = $course?->capacity ?? 0;
    $ungrouped        = max(0, $courseCapacity - $studentTotal);
@endphp

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

    @if(session('error'))
        <div style="background:oklch(95% 0.05 25);border:1px solid oklch(70% 0.15 25);color:oklch(35% 0.12 25);padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:14px;">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="card" style="border-color:var(--status-conflict-border);background:var(--status-conflict-bg);">
            <div style="padding:14px 18px;color:var(--status-conflict-fg);font-weight:600;">
                {{ $errors->first() }}
            </div>
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
            <div class="st-lbl">ชม.บรรยาย / ชม.ปฏิบัติ</div>
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
            <form method="POST" action="{{ route('maker.course_offerings.update', $courseOffering) }}" style="border-top:1px solid var(--border-1);padding-top:20px;">
                @csrf
                @method('PUT')
                <div class="form-row">
                    <div class="form-group">
                        <label>การจัดรอบฝึกปฏิบัติ</label>
                        <select name="requires_practicum_rotation">
                            <option value="0" @selected(! $courseOffering->requires_practicum_rotation)>ไม่มีการหมุนเวียนแหล่งฝึก</option>
                            <option value="1" @selected($courseOffering->requires_practicum_rotation)>มีการหมุนเวียนแหล่งฝึก</option>
                        </select>
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-end;">
                        <button type="submit" class="btn btn-primary">บันทึก</button>
                    </div>
                </div>
            </form>
            @else
            <div style="border-top:1px solid var(--border-1);padding-top:20px;display:grid;grid-template-columns:repeat(2,1fr);gap:16px;">
                <div>
                    <div class="caption">การจัดรอบฝึกปฏิบัติ</div>
                    <div style="font-weight:600;margin-top:4px;">{{ $courseOffering->requires_practicum_rotation ? 'มีการหมุนเวียนแหล่งฝึก' : 'ไม่มีการหมุนเวียนแหล่งฝึก' }}</div>
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
            'id'           => $u->id,
            'name'         => $u->formatted_name,
            'department'   => $u->instructorProfile?->department?->name ?? '-',
            'department_id'=> $u->instructorProfile?->department_id,
        ]);
        $allInstructors = $availableInstructors->map(fn($u) => [
            'id'           => $u->id,
            'name'         => $u->formatted_name,
            'department'   => $u->instructorProfile?->department?->name ?? '-',
            'department_id'=> $u->instructorProfile?->department_id,
        ]);
        $storeUrl    = route('maker.course_offerings.instructors.store', $courseOffering);
        $destroyBase = route('maker.course_offerings.instructors.destroy', [$courseOffering, '__ID__']);
        $courseDeptId = $course?->department_id;
    @endphp

    <div class="card" x-data="{
        pool: {{ $poolData->toJson() }},
        all: {{ $allInstructors->toJson() }},
        search: '',
        open: false,
        showAll: false,
        loading: false,
        error: '',
        ddTop: 0, ddLeft: 0, ddWidth: 0,
        storeUrl: '{{ $storeUrl }}',
        destroyBase: '{{ $destroyBase }}',
        csrfToken: '{{ csrf_token() }}',
        courseDeptId: {{ $courseDeptId ?? 'null' }},
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

            {{-- Error message --}}
            <div x-show="error" x-text="error" style="background:var(--status-conflict-bg);border:1px solid var(--status-conflict-border);color:var(--status-conflict-fg);padding:10px 14px;border-radius:6px;font-size:13px;margin-bottom:14px;"></div>

            @if($canEdit)
            {{-- Combobox --}}
            <div style="position:relative;margin-bottom:20px;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;" x-show="courseDeptId">
                    <span class="caption">แสดง:</span>
                    <button type="button"
                        @click="showAll = false; openDropdown()"
                        :style="!showAll ? 'font-weight:600;color:var(--brand-navy);text-decoration:underline;' : 'color:var(--fg-3);'"
                        style="background:none;border:none;cursor:pointer;font-size:13px;padding:0;">
                        เฉพาะภาควิชานี้
                    </button>
                    <span class="caption">·</span>
                    <button type="button"
                        @click="showAll = true; openDropdown()"
                        :style="showAll ? 'font-weight:600;color:var(--brand-navy);text-decoration:underline;' : 'color:var(--fg-3);'"
                        style="background:none;border:none;cursor:pointer;font-size:13px;padding:0;">
                        อาจารย์ทั้งหมด
                    </button>
                </div>
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
                        x-show="open && available.length > 0"
                        x-cloak
                        :style="`position:absolute;top:${ddTop}px;left:${ddLeft}px;width:${ddWidth}px;background:#fff;border:1px solid var(--border-1);border-radius:6px;box-shadow:0 4px 16px rgba(0,0,0,0.12);z-index:99;max-height:240px;overflow-y:auto;`"
                    >
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
                    </div>
                    <div
                        x-show="open && search.length > 0 && available.length === 0"
                        x-cloak
                        :style="`position:absolute;top:${ddTop}px;left:${ddLeft}px;width:${ddWidth}px;background:#fff;border:1px solid var(--border-1);border-radius:6px;padding:12px 14px;font-size:13px;color:var(--fg-3);z-index:99;`"
                    >ไม่พบอาจารย์ที่ตรงกัน</div>
                </template>
            </div>
            @endif

            {{-- Pills --}}
            <div style="display:flex;flex-wrap:wrap;gap:8px;" x-show="pool.length > 0">
                <template x-for="user in pool" :key="user.id">
                    <div style="display:inline-flex;align-items:center;gap:8px;background:var(--surface-2);border:1px solid var(--border-1);border-radius:6px;padding:6px 12px;font-size:14px;">
                        <div>
                            <span style="font-weight:600;" x-text="user.name"></span>
                            <span style="color:var(--fg-3);font-size:12px;margin-left:6px;" x-text="user.department"></span>
                        </div>
                        @if($canEdit)
                        <button type="button" @click="remove(user.id)" style="background:none;border:none;cursor:pointer;padding:0;display:flex;opacity:0.5;line-height:1;" @mouseenter="$el.style.opacity='1'" @mouseleave="$el.style.opacity='0.5'">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                        @endif
                    </div>
                </template>
            </div>
            <div x-show="pool.length === 0" style="color:var(--fg-3);font-size:14px;">ยังไม่มีผู้สอนในรายวิชานี้</div>
        </div>
    </div>

    <style>@keyframes spin { to { transform: translateY(-50%) rotate(360deg); } }</style>

    <div class="card">
        <div class="card-hdr">
            <div>
                <div class="card-ttl">กลุ่มนักศึกษา</div>
                <div class="caption" style="margin-top:4px;">เปิดรับ {{ $courseCapacity ?: '-' }} คน · จัดกลุ่มแล้ว {{ $studentTotal }} คน · ยังไม่ได้จัดกลุ่ม {{ $ungrouped }} คน</div>
            </div>
        </div>
        <div style="padding:20px;">
            @if($canEdit)
            <form method="POST" action="{{ route('maker.course_offerings.student_groups.store', $courseOffering) }}" class="form-row" style="margin-bottom:16px;">
                @csrf
                <div class="form-group">
                    <label>รหัสกลุ่ม</label>
                    <input type="text" name="group_code" value="{{ old('group_code') }}" required>
                </div>
                <div class="form-group">
                    <label>จำนวนนักศึกษา</label>
                    <input type="number" name="student_count" min="1" value="{{ old('student_count') }}" required>
                </div>
                <div class="form-group">
                    <label>สี</label>
                    <input type="text" name="color_code" value="{{ old('color_code') }}" placeholder="#2563eb">
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;">
                    <button class="btn btn-primary" type="submit">เพิ่มกลุ่ม</button>
                </div>
            </form>
            @endif

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>รหัสกลุ่ม</th>
                            <th>จำนวนนักศึกษา</th>
                            <th>สี</th>
                            @if($canEdit)<th></th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($courseOffering->studentGroups as $group)
                            <tr>
                                @if($canEdit)
                                <td>
                                    <form id="group-update-{{ $group->id }}" method="POST" action="{{ route('maker.course_offerings.student_groups.update', [$courseOffering, $group]) }}">
                                        @csrf
                                        @method('PUT')
                                    </form>
                                    <input form="group-update-{{ $group->id }}" type="text" name="group_code" value="{{ $group->group_code }}" required>
                                </td>
                                <td><input form="group-update-{{ $group->id }}" type="number" name="student_count" min="1" value="{{ $group->student_count }}" required></td>
                                <td><input form="group-update-{{ $group->id }}" type="text" name="color_code" value="{{ $group->color_code }}"></td>
                                <td style="text-align:right;">
                                    <div style="display:flex;gap:8px;justify-content:flex-end;">
                                        <button form="group-update-{{ $group->id }}" class="btn btn-secondary" type="submit">บันทึก</button>
                                        <form method="POST" action="{{ route('maker.course_offerings.student_groups.destroy', [$courseOffering, $group]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-ghost" type="submit">ลบ</button>
                                        </form>
                                    </div>
                                </td>
                                @else
                                <td style="font-weight:600;">{{ $group->group_code }}</td>
                                <td>{{ $group->student_count }} คน</td>
                                <td>
                                    @if($group->color_code)
                                        <span style="display:inline-block;width:14px;height:14px;border-radius:3px;background:{{ $group->color_code }};vertical-align:middle;margin-right:6px;"></span>{{ $group->color_code }}
                                    @else
                                        <span style="color:var(--fg-3);">—</span>
                                    @endif
                                </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $canEdit ? 4 : 3 }}" style="text-align:center;color:var(--fg-3);">ยังไม่มีกลุ่มนักศึกษา</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-hdr">
            <div>
                <div class="card-ttl">เงื่อนไขรายวิชา</div>
                <div class="caption" style="margin-top:4px;">รายวิชาที่ต้องเรียนมาก่อน</div>
            </div>
        </div>
        <div style="padding:20px;">
            @if($canEdit)
            <form method="POST" action="{{ route('maker.course_offerings.prerequisites.store', $courseOffering) }}" style="display:flex;gap:10px;align-items:flex-end;margin-bottom:12px;">
                @csrf
                <div style="flex:1;">
                    <select name="prerequisite_course_id" required>
                        <option value="">เลือกรายวิชาที่ต้องเรียนมาก่อน</option>
                        @foreach($availablePrerequisiteCourses as $candidateCourse)
                            <option value="{{ $candidateCourse->id }}" @selected(old('prerequisite_course_id') == $candidateCourse->id)>
                                {{ $candidateCourse->course_code }} {{ $candidateCourse->name_th ?? $candidateCourse->name_en }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-primary" type="submit">เพิ่ม</button>
            </form>
            @endif
            <div class="body-sm">
                @forelse($course?->prerequisites ?? collect() as $prerequisite)
                    @if($canEdit)
                    <form method="POST" action="{{ route('maker.course_offerings.prerequisites.destroy', [$courseOffering, $prerequisite]) }}" style="display:inline-flex;align-items:center;gap:6px;margin:0 6px 6px 0;">
                        @csrf
                        @method('DELETE')
                        <span class="badge badge-gray">{{ $prerequisite->course_code }}</span>
                        <button class="btn btn-ghost" type="submit" style="padding:4px 8px;">ลบ</button>
                    </form>
                    @else
                    <span class="badge badge-gray" style="margin:0 6px 6px 0;">{{ $prerequisite->course_code }}</span>
                    @endif
                @empty
                    <span style="color:var(--fg-3);">ไม่มีเงื่อนไขรายวิชาที่ต้องเรียนมาก่อน</span>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
