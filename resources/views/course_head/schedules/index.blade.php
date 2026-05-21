@php
    $course = $courseOffering->course;
    $academicYear = $courseOffering->academicYear;
    $canCreate = $academicYear?->phase === 'scheduling';
    $statusMeta = [
        'draft' => ['label' => 'แบบร่าง', 'class' => 'badge-gray'],
        'pending_approval' => ['label' => 'รออนุมัติ', 'class' => 'badge-warn'],
        'approved' => ['label' => 'อนุมัติแล้ว', 'class' => 'badge-ok'],
        'revised' => ['label' => 'ส่งกลับแก้ไข', 'class' => 'badge-err'],
    ];
    $formatThaiDate = fn ($date) => $date ? $date->format('d/m/').($date->year + 543) : '-';
    $activityFilterOptions = $schedules->pluck('activityType')->filter()->unique('id')->sortBy('name')->values();
    $groupFilterOptions = $schedules->flatMap->studentGroups->unique('id')->sortBy('group_code')->values();
    $availableInstructorIds = $availableInstructors->pluck('id')->map(fn ($id) => (int) $id);
    $instructorFilterOptions = $availableInstructors;
    $warningScheduleCount = collect($scheduleWarnings ?? [])->filter(fn ($warnings) => count($warnings) > 0)->count();
    $scheduleItems = $schedules->map(function ($schedule) use ($statusMeta, $formatThaiDate, $availableInstructorIds, $scheduleWarnings) {
        $meta = $statusMeta[$schedule->status] ?? ['label' => $schedule->status, 'class' => 'badge-gray'];
        $warningLabels = collect($scheduleWarnings[$schedule->id] ?? [])->pluck('label')->implode(' ');

        return [
            'id' => (string) $schedule->id,
            'activity' => (string) $schedule->activity_type_id,
            'status' => (string) $schedule->status,
            'groups' => $schedule->studentGroups->pluck('id')->map(fn ($id) => (string) $id)->values(),
            'instructors' => $schedule->instructors->whereIn('id', $availableInstructorIds)->pluck('id')->map(fn ($id) => (string) $id)->values(),
            'search' => mb_strtolower(collect([
                $formatThaiDate($schedule->start_date),
                $formatThaiDate($schedule->end_date),
                substr((string) $schedule->start_time, 0, 5),
                substr((string) $schedule->end_time, 0, 5),
                $schedule->activityType?->name,
                $schedule->topic,
                $schedule->remark,
                $schedule->room?->room_code,
                $schedule->room?->room_name,
                $meta['label'],
                $warningLabels,
                $schedule->studentGroups->pluck('group_code')->implode(' '),
                $schedule->instructors->whereIn('id', $availableInstructorIds)->pluck('formatted_name')->implode(' '),
            ])->filter()->implode(' '), 'UTF-8'),
        ];
    })->values();
@endphp

<x-app-layout title="ตารางสอน">
    <style>
        .schedule-back-link {
            color: var(--brand-navy);
            text-decoration: none;
            font-weight: 700;
        }
        .schedule-back-link:hover {
            text-decoration: underline;
        }
        .schedule-meta {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }
        .schedule-table td {
            vertical-align: top;
        }
        .schedule-table thead th {
            background: oklch(93.5% 0.02 235);
            color: oklch(34% 0.045 240);
        }
        .schedule-time {
            font-weight: 700;
            color: var(--fg-1);
            white-space: nowrap;
        }
        .schedule-date-range {
            min-width: 132px;
        }
        .schedule-empty {
            padding: 42px 22px;
            text-align: center;
            color: var(--fg-3);
            background: oklch(97% 0.012 235);
            border: 1px solid oklch(88% 0.018 235);
            border-radius: 10px;
            margin: 16px;
        }
        .schedule-empty-title {
            font-weight: 700;
            color: var(--fg-2);
            margin-bottom: 4px;
        }
        .schedule-empty-actions {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 16px;
            flex-wrap: wrap;
        }
        .schedule-filter-bar {
            display: grid;
            grid-template-columns: minmax(240px, 1.2fr) repeat(4, minmax(140px, .65fr));
            gap: 10px;
            padding: 14px 18px;
            border-top: 1px solid var(--border);
            background: oklch(97% 0.012 235);
        }
        .schedule-filter-control {
            min-height: 40px;
            border: 1px solid oklch(84% 0.025 235);
            border-radius: 8px;
            background: var(--surface);
            color: var(--fg-1);
            padding: 8px 10px;
            font: inherit;
        }
        .schedule-filter-control:focus {
            outline: none;
            border-color: var(--brand-navy);
            box-shadow: 0 0 0 3px oklch(45% 0.12 250 / 0.12);
        }
        .schedule-row-actions {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .schedule-delete-button {
            border: 1px solid oklch(80% 0.035 25);
            border-radius: 8px;
            background: oklch(98% 0.012 25);
            color: var(--status-conflict-fg);
            font: inherit;
            font-weight: 700;
            padding: 6px 10px;
            cursor: pointer;
        }
        .schedule-delete-button:hover {
            border-color: var(--status-conflict-fg);
            background: var(--status-conflict-bg);
        }
        .schedule-warning-stack {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 7px;
        }
        .schedule-warning-stack .badge {
            margin: 0;
        }
        .schedule-create-modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 80;
            display: grid;
            place-items: center;
            padding: 24px;
            background: oklch(16% 0.025 240 / 0.48);
        }
        .schedule-create-modal {
            width: min(1180px, 100%);
            height: min(86vh, 860px);
            display: grid;
            grid-template-rows: auto minmax(0, 1fr);
            border: 1px solid oklch(82% 0.025 235);
            border-radius: 12px;
            background: oklch(98% 0.006 235);
            box-shadow: 0 18px 48px oklch(12% 0.025 240 / 0.22);
            overflow: hidden;
        }
        .schedule-create-modal-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 14px 18px;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
        }
        .schedule-create-modal-title {
            font-family: var(--font-display);
            font-size: 18px;
            font-weight: 700;
            color: var(--fg-1);
        }
        .schedule-create-modal-close {
            min-width: 38px;
            min-height: 38px;
            border: 1px solid oklch(84% 0.025 235);
            border-radius: 8px;
            background: oklch(97% 0.01 235);
            color: var(--fg-2);
            font: inherit;
            font-size: 22px;
            line-height: 1;
            cursor: pointer;
        }
        .schedule-create-modal-close:hover {
            border-color: var(--brand-navy);
            color: var(--brand-navy);
        }
        .schedule-create-frame {
            width: 100%;
            height: 100%;
            border: 0;
            background: oklch(97% 0.012 235);
        }
        @media (max-width: 980px) {
            .schedule-filter-bar {
                grid-template-columns: 1fr 1fr;
            }
            .schedule-create-modal-backdrop {
                padding: 12px;
            }
            .schedule-create-modal {
                height: 92vh;
            }
        }
        @media (max-width: 640px) {
            .schedule-filter-bar {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <script>
        function scheduleListFilter(items) {
            return {
                items,
                search: '',
                activity: '',
                group: '',
                instructor: '',
                status: '',
                normalizedSearch() {
                    return this.search.trim().toLowerCase();
                },
                matches(id) {
                    const item = this.items.find((entry) => entry.id === String(id));

                    if (!item) return false;

                    const keyword = this.normalizedSearch();

                    return (!keyword || item.search.includes(keyword))
                        && (!this.activity || item.activity === this.activity)
                        && (!this.group || item.groups.includes(this.group))
                        && (!this.instructor || item.instructors.includes(this.instructor))
                        && (!this.status || item.status === this.status);
                },
                matchedCount() {
                    return this.items.filter((item) => this.matches(item.id)).length;
                },
                reset() {
                    this.search = '';
                    this.activity = '';
                    this.group = '';
                    this.instructor = '';
                    this.status = '';
                },
            };
        }

        function scheduleCreateModal() {
            return {
                createOpen: false,
                frameUrl: 'about:blank',
                modalTitle: 'เพิ่มรายการสอน',
                modalSubtitle: 'กรอกช่วงวัน เวลา ผู้สอน และกลุ่มนักศึกษา',
                createUrl: @js(route('maker.course_offerings.schedules.create', ['courseOffering' => $courseOffering, 'embedded' => 1])),
                openCreateModal() {
                    this.modalTitle = 'เพิ่มรายการสอน';
                    this.modalSubtitle = 'กรอกช่วงวัน เวลา ผู้สอน และกลุ่มนักศึกษา';
                    this.frameUrl = this.createUrl;
                    this.createOpen = true;
                    document.documentElement.style.overflow = 'hidden';
                },
                openEditModal(url) {
                    this.modalTitle = 'แก้ไขรายการสอน';
                    this.modalSubtitle = 'ปรับช่วงวัน เวลา ผู้สอน หรือกลุ่มนักศึกษา';
                    this.frameUrl = url;
                    this.createOpen = true;
                    document.documentElement.style.overflow = 'hidden';
                },
                closeCreateModal() {
                    this.createOpen = false;
                    this.frameUrl = 'about:blank';
                    document.documentElement.style.overflow = '';
                },
            };
        }

        if (window.self !== window.top && window.location.pathname.endsWith('/schedules')) {
            window.top.location.href = window.location.href;
        }
    </script>

    <div x-data="scheduleCreateModal()" @keydown.escape.window="closeCreateModal()">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px;flex-wrap:wrap;">
        <div>
            <a href="{{ route('maker.course_offerings.show', $courseOffering) }}" class="body-sm schedule-back-link">← กลับไปรายละเอียดรายวิชา</a>
            <div class="eyebrow" style="margin-top:8px;">ตารางสอนรายวิชา</div>
            <h1 class="h1" style="margin:4px 0 6px;">{{ $course?->course_code ?? '-' }} {{ $course?->name_th ?? $course?->name_en ?? '' }}</h1>
            <p class="body-sm" style="margin:0;max-width:72ch;">
                {{ $course?->curriculum?->name ?? '-' }} · {{ $academicYear?->name ?? '-' }} / เทอม {{ $academicYear?->semester ?? '-' }}
            </p>
            <div class="schedule-meta">
                @if($canCreate)
                    <span class="badge badge-ok">เปิดจัดตาราง</span>
                @else
                    <span class="badge badge-gray">อ่านอย่างเดียว</span>
                @endif
                @if($courseOffering->requires_practicum_rotation)
                    <span class="badge badge-warn">มีรอบฝึกปฏิบัติ</span>
                @endif
                @if($warningScheduleCount > 0)
                    <span class="badge badge-warn">มีคำเตือน {{ $warningScheduleCount }} รายการ</span>
                @elseif($schedules->isNotEmpty())
                    <span class="badge badge-ok">ข้อมูลพร้อมใช้งาน</span>
                @endif
            </div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="st-card">
            <div class="st-val">{{ $schedules->count() }}</div>
            <div class="st-lbl">รายการสอน</div>
        </div>
        <div class="st-card">
            <div class="st-val">{{ $courseOffering->studentGroups->count() }}</div>
            <div class="st-lbl">กลุ่มนักศึกษา</div>
        </div>
        <div class="st-card">
            <div class="st-val">{{ $availableInstructors->count() }}</div>
            <div class="st-lbl">ผู้สอนในรายวิชา</div>
        </div>
        <div class="st-card">
            <div class="st-val">{{ $warningScheduleCount }}</div>
            <div class="st-lbl">รายการที่มีคำเตือน</div>
        </div>
    </div>

    @if($errors->any())
        <div class="card" style="border-color:var(--status-conflict-border);background:var(--status-conflict-bg);margin-bottom:16px;">
            <div style="padding:14px 18px;color:var(--status-conflict-fg);font-weight:600;">
                {{ $errors->first() }}
            </div>
        </div>
    @endif

    @if(session('success'))
        <div class="card" style="border-color:oklch(72% 0.13 145);background:oklch(96% 0.035 145);margin-bottom:16px;">
            <div style="padding:14px 18px;color:oklch(34% 0.12 145);font-weight:600;">
                {{ session('success') }}
            </div>
        </div>
    @endif

    <div class="card" x-data="scheduleListFilter(@js($scheduleItems))">
        <div class="card-hdr">
            <div>
                <div class="card-ttl">รายการตารางสอน</div>
                <div class="caption" style="margin-top:4px;">เรียงตามช่วงวันที่และเวลา</div>
            </div>
            <div class="card-actions">
                @if($canCreate)
                    <button type="button" class="btn btn-primary" data-testid="schedule-create-link" @click="openCreateModal()">เพิ่มรายการสอน</button>
                @else
                    <span class="badge badge-gray">ยังไม่เปิดช่วงจัดตาราง</span>
                @endif
            </div>
        </div>
        <div class="schedule-filter-bar">
            <input
                type="search"
                class="schedule-filter-control"
                x-model="search"
                placeholder="ค้นหาวันที่ เวลา กิจกรรม ผู้สอน หรือสถานที่"
                aria-label="ค้นหารายการตารางสอน"
            >
            <select class="schedule-filter-control" x-model="activity" aria-label="กรองตามประเภทกิจกรรม">
                <option value="">ทุกกิจกรรม</option>
                @foreach($activityFilterOptions as $activity)
                    <option value="{{ $activity->id }}">{{ $activity->name }}</option>
                @endforeach
            </select>
            <select class="schedule-filter-control" x-model="group" aria-label="กรองตามกลุ่มนักศึกษา">
                <option value="">ทุกกลุ่ม</option>
                @foreach($groupFilterOptions as $group)
                    <option value="{{ $group->id }}">{{ $group->group_code }}</option>
                @endforeach
            </select>
            <select class="schedule-filter-control" x-model="instructor" aria-label="กรองตามผู้สอน">
                <option value="">ทุกผู้สอน</option>
                @foreach($instructorFilterOptions as $instructor)
                    <option value="{{ $instructor->id }}">{{ $instructor->formatted_name }}</option>
                @endforeach
            </select>
            <select class="schedule-filter-control" x-model="status" aria-label="กรองตามสถานะ">
                <option value="">ทุกสถานะ</option>
                @foreach($statusMeta as $status => $meta)
                    <option value="{{ $status }}">{{ $meta['label'] }}</option>
                @endforeach
            </select>
        </div>

        <div class="table-responsive">
            <table class="schedule-table">
                <thead>
                    <tr>
                        <th>ช่วงวันที่</th>
                        <th>เวลา</th>
                        <th>กิจกรรม</th>
                        <th>กลุ่ม</th>
                        <th>ผู้สอน</th>
                        <th>สถานที่</th>
                        <th>สถานะ</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($schedules as $schedule)
                        @php
                            $meta = $statusMeta[$schedule->status] ?? ['label' => $schedule->status, 'class' => 'badge-gray'];
                        @endphp
                        <tr data-testid="schedule-row" x-show="matches('{{ $schedule->id }}')" x-cloak>
                            <td class="schedule-date-range">
                                <div style="font-weight:700;color:var(--fg-1);">{{ $formatThaiDate($schedule->start_date) }}</div>
                                <div class="caption" style="margin-top:3px;">ถึง {{ $formatThaiDate($schedule->end_date) }}</div>
                            </td>
                            <td>
                                <div class="schedule-time">{{ substr((string) $schedule->start_time, 0, 5) }} - {{ substr((string) $schedule->end_time, 0, 5) }}</div>
                            </td>
                            <td>
                                <div style="font-weight:700;color:var(--fg-1);">{{ $schedule->activityType?->name ?? '-' }}</div>
                                <div class="body-sm" style="margin-top:3px;">{{ $schedule->topic ?: 'ไม่ระบุหัวข้อ' }}</div>
                                @if($schedule->remark)
                                    <div class="caption" style="margin-top:5px;">{{ $schedule->remark }}</div>
                                @endif
                            </td>
                            <td>
                                @forelse($schedule->studentGroups as $group)
                                    <span class="badge badge-gray" style="margin:2px;">{{ $group->group_code }}</span>
                                @empty
                                    <span class="caption">-</span>
                                @endforelse
                            </td>
                            <td>
                                @forelse($schedule->instructors->whereIn('id', $availableInstructorIds) as $instructor)
                                    <div class="body-sm">{{ $instructor->formatted_name }}</div>
                                @empty
                                    <span class="caption">-</span>
                                @endforelse
                            </td>
                            <td>
                                <div class="body-sm">{{ $schedule->room?->room_code ?? '-' }}</div>
                                <div class="caption" style="margin-top:3px;">{{ $schedule->room?->room_name ?? 'ไม่ระบุห้อง' }}</div>
                            </td>
                            <td>
                                <span class="badge {{ $meta['class'] }}">{{ $meta['label'] }}</span>
                                @if(!empty($scheduleWarnings[$schedule->id] ?? []))
                                    <div class="schedule-warning-stack">
                                        @foreach($scheduleWarnings[$schedule->id] as $warning)
                                            <span class="badge {{ $warning['class'] }}">{{ $warning['label'] }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($canCreate)
                                    <div class="schedule-row-actions">
                                        <button type="button" class="btn btn-ghost" style="padding:6px 10px;" @click="openEditModal(@js(route('maker.course_offerings.schedules.edit', ['courseOffering' => $courseOffering, 'schedule' => $schedule, 'embedded' => 1])))">แก้ไข</button>
                                        <form method="POST" action="{{ route('maker.course_offerings.schedules.destroy', [$courseOffering, $schedule]) }}" onsubmit="return confirm('ต้องการลบรายการสอนนี้หรือไม่?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="schedule-delete-button">ลบ</button>
                                        </form>
                                    </div>
                                @else
                                    <span class="caption">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="schedule-empty">
                                    <div class="schedule-empty-title">ยังไม่มีรายการสอน</div>
                                    <div>เพิ่มรายการสอนแรกของรายวิชานี้เพื่อเริ่มตรวจตารางและความพร้อมของกลุ่ม</div>
                                    <div class="schedule-empty-actions">
                                        @if($canCreate)
                                            <button type="button" class="btn btn-primary" @click="openCreateModal()">เพิ่มรายการสอน</button>
                                        @else
                                            <span class="badge badge-gray">ยังไม่เปิดช่วงจัดตาราง</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    @if($schedules->isNotEmpty())
                        <tr x-show="matchedCount() === 0" x-cloak>
                            <td colspan="8">
                                <div class="schedule-empty">
                                    <div class="schedule-empty-title">ไม่พบรายการที่ตรงกับตัวกรอง</div>
                                    <div>ลองปรับคำค้นหา ประเภทกิจกรรม กลุ่ม ผู้สอน หรือสถานะอีกครั้ง</div>
                                    <div class="schedule-empty-actions">
                                        <button type="button" class="btn btn-ghost" @click="reset()">ล้างตัวกรอง</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    <div
        class="schedule-create-modal-backdrop"
        x-show="createOpen"
        x-transition.opacity
        x-cloak
        @click.self="closeCreateModal()"
        role="dialog"
        aria-modal="true"
        aria-label="เพิ่มรายการสอน"
    >
        <div class="schedule-create-modal">
            <div class="schedule-create-modal-head">
                <div>
                    <div class="schedule-create-modal-title" x-text="modalTitle"></div>
                    <div class="caption" style="margin-top:2px;" x-text="modalSubtitle"></div>
                </div>
                <button type="button" class="schedule-create-modal-close" @click="closeCreateModal()" aria-label="ปิด">&times;</button>
            </div>
            <iframe class="schedule-create-frame" :src="createOpen ? frameUrl : 'about:blank'" :title="modalTitle"></iframe>
        </div>
    </div>
    </div>
</x-app-layout>
