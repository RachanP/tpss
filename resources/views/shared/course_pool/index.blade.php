<x-app-layout title="ตั้งค่าผู้รับผิดชอบรายวิชา">
    @if(session('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>
    @endif

    <div class="card" x-data="{
        headFilter: 'all',
        termFilter: 'all',
        selectedTemplate: null,
        openTemplateModal(template) {
            this.selectedTemplate = template;
        },
        closeTemplateModal() {
            this.selectedTemplate = null;
        }
    }" @keydown.escape.window="closeTemplateModal()">
        <div class="card-hdr" style="align-items:flex-start;gap:16px;flex-wrap:wrap;">
            <div>
                <div class="card-ttl">รายวิชาทั้งหมด ({{ $courses->count() }} รายการ)</div>
                <div class="caption" style="margin-top:6px;">
                    @if($activeAcademicYear)
                        รอบปัจจุบัน: ปีการศึกษา {{ $activeAcademicYear->name }} / เทอม {{ $activeAcademicYear->semester }}
                    @else
                        ยังไม่ได้ตั้งค่าปีการศึกษาปัจจุบัน
                    @endif
                </div>
            </div>
            <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-left:auto;">
                <label style="display:grid;gap:4px;">
                    <span class="caption">หัวหน้าวิชา</span>
                    <select class="form-control" x-model="headFilter" data-testid="course-pool-head-filter" style="min-width:180px;">
                        <option value="all">ทั้งหมด</option>
                        <option value="missing">ยังไม่มีหัวหน้าวิชา</option>
                        <option value="assigned">มีหัวหน้าวิชาแล้ว</option>
                    </select>
                </label>
                <label style="display:grid;gap:4px;">
                    <span class="caption">สถานะเทอมนี้</span>
                    <select class="form-control" x-model="termFilter" data-testid="course-pool-term-filter" style="min-width:180px;">
                        <option value="all">ทั้งหมด</option>
                        <option value="open">เปิดสอน</option>
                        <option value="closed">ปิดสอน</option>
                    </select>
                </label>
            </div>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width:96px;">รหัสวิชา</th>
                        <th>ชื่อวิชา</th>
                        <th>ภาควิชา</th>
                        <th style="text-align:center;width:140px;">สถานะเทอมนี้</th>
                        <th style="text-align:center;">หัวหน้าวิชา</th>
                        <th style="text-align:center;">เจ้าหน้าที่</th>
                        <th style="text-align:center;">อาจารย์ผู้สอน</th>
                        <th style="text-align:center;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($courses as $course)
                        @php
                            $headState = $course->headInstructor ? 'assigned' : 'missing';
                            $termState = $activeAcademicYear
                                ? (($course->has_current_offering ?? false) ? 'open' : 'closed')
                                : 'unknown';
                            $templateData = [
                                'code' => $course->course_code,
                                'nameTh' => $course->name_th,
                                'nameEn' => $course->name_en,
                                'department' => $course->department?->name ?? '-',
                                'credits' => $course->credits,
                                'lectureHours' => $course->lecture_hours,
                                'labHours' => $course->lab_hours,
                                'selfStudyHours' => $course->self_study_hours,
                                'yearLevel' => $course->default_year_level,
                                'semester' => $course->default_semester,
                                'type' => $course->course_type,
                                'level' => $course->academic_level,
                                'isLocked' => (bool) $course->has_locked_offering,
                                'head' => $course->headInstructor?->formatted_name ?? 'ยังไม่กำหนด',
                                'headId' => $course->head_instructor_id,
                                'headUpdateUrl' => route($routePrefix . '.course_pool.head.update', $course),
                                'staff' => $course->assignedStaff
                                    ->map(fn ($user) => ['name' => $user->formatted_name])
                                    ->values(),
                                'instructors' => $course->instructors
                                    ->map(fn ($user) => [
                                        'name' => $user->formatted_name,
                                        'department' => $user->instructorProfile?->department?->name ?? '-',
                                        'role' => ($user->pivot->course_role_id
                                            ? $courseRolesById->get($user->pivot->course_role_id)
                                            : null) ?? 'ยังไม่กำหนดบทบาท',
                                    ])
                                    ->values(),
                            ];
                        @endphp
                        <tr
                            data-head-state="{{ $headState }}"
                            data-term-state="{{ $termState }}"
                            x-show="(headFilter === 'all' || headFilter === '{{ $headState }}') && (termFilter === 'all' || termFilter === '{{ $termState }}')"
                        >
                            <td style="font-weight:700;font-family:var(--font-mono);white-space:nowrap;">{{ $course->course_code }}</td>
                            <td>
                                <div style="font-weight:600;">{{ $course->name_th }}</div>
                                <div class="caption" style="margin-top:2px;">{{ $course->name_en }}</div>
                            </td>
                            <td>{{ $course->department?->name ?? '-' }}</td>
                            <td style="text-align:center;">
                                @if(! $activeAcademicYear)
                                    <span
                                        class="badge badge-gray"
                                        data-testid="course-pool-term-unknown"
                                        title="ยังไม่ทราบสถานะเทอมนี้ เพราะยังไม่ได้ตั้งค่าปีการศึกษาปัจจุบัน"
                                        style="padding:4px 12px;border-radius:99px;font-weight:700;font-size:11px;"
                                    >
                                        ไม่ทราบ
                                    </span>
                                @elseif($termState === 'open')
                                    <span
                                        class="badge"
                                        data-testid="course-pool-term-open"
                                        title="เปิดสอนในเทอมนี้"
                                        style="background:#22c55e;color:#ffffff;padding:4px 12px;border-radius:99px;font-weight:700;font-size:11px;box-shadow:0 2px 4px rgba(34,197,94,.2);"
                                    >
                                        เปิดสอน
                                    </span>
                                @else
                                    <span
                                        class="badge"
                                        data-testid="course-pool-term-closed"
                                        title="ไม่มี Course Offering ในเทอมนี้"
                                        style="background:#e2e8f0;color:#64748b;padding:4px 12px;border-radius:99px;font-weight:700;font-size:11px;"
                                    >
                                        ปิดสอน
                                    </span>
                                @endif
                            </td>
                            <td style="text-align:center;">
                                @if($course->headInstructor)
                                    <span class="pill pill-success">{{ $course->headInstructor->formatted_name }}</span>
                                @else
                                    <span
                                        data-testid="course-pool-missing-head-badge"
                                        style="display:inline-flex;align-items:center;gap:6px;color:var(--status-warning-fg);font-weight:800;font-size:13px;white-space:nowrap;"
                                    >
                                        <span aria-hidden="true" style="width:7px;height:7px;border-radius:999px;background:var(--status-warning);box-shadow:0 0 0 3px var(--status-warning-bg);"></span>
                                        ยังไม่มีหัวหน้าวิชา
                                    </span>
                                @endif
                            </td>
                            <td style="text-align:center;">
                                <span style="font-weight:600;">{{ $course->assigned_staff_count }}</span>
                                <span style="color:var(--fg-3);font-size:12px;">คน</span>
                            </td>
                            <td style="text-align:center;">
                                <span style="font-weight:600;">{{ $course->instructors_count }}</span>
                                <span style="color:var(--fg-3);font-size:12px;">คน</span>
                            </td>
                            <td style="text-align:center;">
                                <button
                                    type="button"
                                    class="action-btn"
                                    data-testid="{{ $course->has_locked_offering ? 'course-pool-template-button' : 'course-pool-config-button' }}"
                                    title="{{ $course->has_locked_offering ? 'ดูแม่แบบ' : 'ตั้งค่า' }}"
                                    aria-label="{{ $course->has_locked_offering ? 'ดูแม่แบบ' : 'ตั้งค่า' }} {{ $course->course_code }}"
                                    @click="openTemplateModal(@js($templateData))"
                                >
                                    @if($course->has_locked_offering)
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                    @else
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                    @endif
                                </button>
                                @if($course->has_locked_offering)
                                    <div class="caption" style="margin-top:4px;">ล็อกแล้ว</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align:center;color:var(--fg-3);padding:40px;">ยังไม่มีรายวิชา กรุณาเพิ่มรายวิชาในหน้า "ข้อมูลหลักระบบ" ก่อน</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <template x-if="selectedTemplate">
            <div class="overlay" x-cloak @click.self="closeTemplateModal()">
                <div class="modal-center course-template-modal"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="course-template-title"
                    data-testid="course-pool-template-modal"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100">
                    <div class="modal-hdr" style="background: var(--bg-2);">
                        <div>
                            <div class="modal-ttl" id="course-template-title" style="font-family: var(--font-display);" x-text="selectedTemplate.isLocked ? 'แม่แบบผู้รับผิดชอบรายวิชา' : 'ตั้งค่าผู้รับผิดชอบรายวิชา'"></div>
                            <div class="caption" style="margin-top:4px;" x-text="`${selectedTemplate.code} · ${selectedTemplate.nameTh}`"></div>
                        </div>
                        <button type="button" class="modal-cls" @click="closeTemplateModal()" aria-label="ปิด">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                                stroke-width="2.5" stroke-linecap="round">
                                <line x1="18" y1="6" x2="6" y2="18"/>
                                <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>
                    <div class="modal-body course-template-body">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:18px;">
                            <div>
                                <label>รหัสวิชา</label>
                                <div class="course-template-field" x-text="selectedTemplate.code"></div>
                            </div>
                            <div>
                                <label>หน่วยกิต</label>
                                <div class="course-template-field" x-text="selectedTemplate.credits"></div>
                            </div>
                            <div>
                                <label>ชั่วโมงทฤษฎี</label>
                                <div class="course-template-field" x-text="selectedTemplate.lectureHours ?? 0"></div>
                            </div>
                            <div>
                                <label>ชั่วโมงปฏิบัติ/แล็บ</label>
                                <div class="course-template-field" x-text="selectedTemplate.labHours ?? 0"></div>
                            </div>
                            <div>
                                <label>ชั่วโมงศึกษาด้วยตนเอง</label>
                                <div class="course-template-field" x-text="selectedTemplate.selfStudyHours ?? 0"></div>
                            </div>
                            <div>
                                <label>ชั้นปี / เทอมเริ่มต้น</label>
                                <div class="course-template-field" x-text="`ปี ${selectedTemplate.yearLevel || '-'} / เทอม ${selectedTemplate.semester || '-'}`"></div>
                            </div>
                            <div>
                                <label>ชื่อวิชาภาษาไทย</label>
                                <div class="course-template-field" x-text="selectedTemplate.nameTh"></div>
                            </div>
                            <div>
                                <label>ภาควิชา</label>
                                <div class="course-template-field" x-text="selectedTemplate.department"></div>
                            </div>
                            <div>
                                <label>ประเภทวิชา</label>
                                <div class="course-template-field" x-text="selectedTemplate.type || '-'"></div>
                            </div>
                            <div>
                                <label>ระดับการศึกษา</label>
                                <div class="course-template-field" x-text="selectedTemplate.level || '-'"></div>
                            </div>
                            <div style="grid-column:1 / -1;">
                                <label>ชื่อวิชาภาษาอังกฤษ</label>
                                <div class="course-template-field" x-text="selectedTemplate.nameEn || '-'"></div>
                            </div>
                        </div>

                        <div class="course-template-lock" x-show="selectedTemplate.isLocked">
                            แม่แบบนี้ถูกล็อกแล้ว เพราะมี Course Offering ที่อยู่ในช่วงจัดตารางหรือเผยแพร่แล้ว
                        </div>
                        <div class="course-template-edit-note" x-show="!selectedTemplate.isLocked">
                            รายวิชานี้ยังแก้ไขแม่แบบได้ เลือกหัวหน้าวิชาได้จาก modal นี้โดยไม่ต้องเปิดหน้าเดิม
                        </div>

                        <div class="course-template-section">
                            <div class="course-template-heading">หัวหน้าวิชา / ผู้ประสานรายวิชา</div>
                            <div class="course-template-person" x-show="selectedTemplate.isLocked">
                                <div class="course-template-avatar" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M20 21a8 8 0 0 0-16 0"/>
                                        <circle cx="12" cy="7" r="4"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="course-template-name" x-text="selectedTemplate.head"></div>
                                    <div class="caption">ผู้รับผิดชอบหลักของรายวิชา</div>
                                </div>
                            </div>
                            <form method="POST" :action="selectedTemplate.headUpdateUrl" class="course-template-head-form" x-show="!selectedTemplate.isLocked">
                                @csrf
                                @method('PUT')
                                <div style="flex:1;min-width:0;">
                                    <label>เลือกหัวหน้าวิชา</label>
                                    <select name="head_instructor_id" x-model="selectedTemplate.headId">
                                        <option value="">— ยังไม่กำหนด —</option>
                                        @foreach($availableInstructors as $u)
                                            <option value="{{ $u->id }}">
                                                {{ $u->formatted_name }} ({{ $u->instructorProfile?->department?->name ?? '-' }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary">บันทึก</button>
                            </form>
                        </div>

                        <div class="course-template-section">
                            <div class="course-template-heading">
                                เจ้าหน้าที่ผู้ดูแลวิชา
                                <span class="course-template-count" x-text="`(${selectedTemplate.staff.length} คน)`"></span>
                            </div>
                            <template x-if="selectedTemplate.staff.length === 0">
                                <div class="course-template-empty">ยังไม่มีเจ้าหน้าที่ผู้ดูแลวิชา</div>
                            </template>
                            <div class="course-template-list">
                                <template x-for="staff in selectedTemplate.staff" :key="staff.name">
                                    <div class="course-template-person">
                                        <div class="course-template-avatar is-staff" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="3" y="7" width="18" height="13" rx="2"/>
                                                <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                            </svg>
                                        </div>
                                        <div class="course-template-name" x-text="staff.name"></div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="course-template-section">
                            <div class="course-template-heading">
                                อาจารย์ผู้สอน
                                <span class="course-template-count" x-text="`(${selectedTemplate.instructors.length} คน)`"></span>
                            </div>
                            <template x-if="selectedTemplate.instructors.length === 0">
                                <div class="course-template-empty">ยังไม่มีอาจารย์ผู้สอนในแม่แบบนี้</div>
                            </template>
                            <div class="course-template-list">
                                <template x-for="instructor in selectedTemplate.instructors" :key="`${instructor.name}-${instructor.role}`">
                                    <div class="course-template-person">
                                        <div style="flex:1;min-width:0;">
                                            <div class="course-template-name" x-text="instructor.name"></div>
                                            <div class="caption" x-text="instructor.department"></div>
                                        </div>
                                        <span class="course-template-role" x-text="instructor.role"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                    <div class="modal-foot">
                        <button type="button" class="btn btn-ghost" @click="closeTemplateModal()">ปิด</button>
                    </div>
                </div>
            </div>
        </template>

        <style>
            .course-template-modal {
                max-width: 720px;
            }

            .course-template-body {
                padding: 24px;
            }

            .course-template-field {
                min-height: 42px;
                display: flex;
                align-items: center;
                border: 1px solid var(--border);
                border-radius: var(--r-md);
                background: var(--surface);
                color: var(--fg-1);
                padding: 10px 12px;
                font-size: var(--fs-md);
                font-weight: var(--fw-semibold);
                line-height: var(--lh-snug);
            }

            .course-template-lock {
                margin-bottom: 18px;
                padding: 10px 14px;
                border: 1px solid var(--status-warning-border);
                border-radius: var(--r-md);
                background: var(--status-warning-bg);
                color: var(--status-warning-fg);
                font-size: var(--fs-base);
                font-weight: var(--fw-semibold);
                line-height: var(--lh-normal);
            }

            .course-template-edit-note {
                margin-bottom: 18px;
                padding: 10px 14px;
                border: 1px solid var(--status-info-border);
                border-radius: var(--r-md);
                background: var(--status-info-bg);
                color: var(--status-info-fg);
                font-size: var(--fs-base);
                font-weight: var(--fw-semibold);
                line-height: var(--lh-normal);
            }

            .course-template-section {
                display: grid;
                gap: 10px;
                margin-top: 18px;
            }

            .course-template-head-form {
                display: flex;
                flex-direction: row;
                align-items: flex-end;
                gap: 12px;
                overflow: visible;
                flex: none;
                min-height: auto;
                border: 1px solid var(--border);
                border-radius: var(--r-md);
                background: var(--surface);
                padding: 12px;
            }

            .course-template-heading {
                color: var(--fg-1);
                font-size: var(--fs-md);
                font-weight: var(--fw-bold);
            }

            .course-template-count {
                color: var(--fg-3);
                font-size: var(--fs-base);
                font-weight: var(--fw-medium);
            }

            .course-template-list {
                display: grid;
                gap: 8px;
            }

            .course-template-person {
                display: flex;
                align-items: center;
                gap: 12px;
                min-height: 52px;
                border: 1px solid var(--border);
                border-radius: var(--r-md);
                background: var(--surface);
                padding: 10px 12px;
            }

            .course-template-avatar {
                width: 34px;
                height: 34px;
                flex: 0 0 34px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: var(--r-md);
                background: var(--brand-navy-50);
                color: var(--brand-navy);
            }

            .course-template-avatar.is-staff {
                background: var(--bg-2);
                color: var(--fg-2);
            }

            .course-template-name {
                min-width: 0;
                color: var(--fg-1);
                font-size: var(--fs-md);
                font-weight: var(--fw-semibold);
                line-height: var(--lh-snug);
            }

            .course-template-role {
                flex: 0 0 auto;
                max-width: 190px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                border: 1px solid oklch(82% 0.055 255);
                border-radius: var(--r-pill);
                background: oklch(96% 0.025 255);
                color: oklch(34% 0.09 255);
                padding: 5px 10px;
                font-size: var(--fs-xs);
                font-weight: var(--fw-bold);
            }

            .course-template-empty {
                border: 1px dashed var(--border-strong);
                border-radius: var(--r-md);
                background: var(--bg-2);
                color: var(--fg-3);
                padding: 14px;
                font-size: var(--fs-base);
                text-align: center;
            }

            @media (max-width: 640px) {
                .course-template-modal {
                    max-width: calc(100vw - 24px);
                }

                .course-template-body {
                    padding: 18px;
                }

                .course-template-person {
                    align-items: flex-start;
                }

                .course-template-head-form {
                    flex-direction: column;
                    align-items: stretch;
                }

                .course-template-role {
                    max-width: 130px;
                }
            }
        </style>
    </div>
</x-app-layout>
