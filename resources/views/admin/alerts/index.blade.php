<x-app-layout title="แจ้งเตือน Master Data">
    @php
        $allWarningDefs = [
            ['key' => 'departments',  'label' => 'ภาควิชา',             'sub' => 'ขาดหัวหน้าภาค / เลขานุการ',          'count' => $departmentsWithIssues->count()],
            ['key' => 'rooms',        'label' => 'ห้อง / สถานที่',       'sub' => 'ขาด capacity หรือชื่อห้อง',           'count' => $roomsWithIssues->count()],
            ['key' => 'course_staff', 'label' => 'รายวิชา', 'sub' => 'วิชาที่ยังไม่มีเจ้าหน้าที่ดูแล', 'count' => $coursesWithoutStaff->count()],
        ];
    @endphp

    <div style="padding: 2rem;" x-data="{
        showDismissModal: false,
        dismissed: {{ Js::from($dismissedWarnings) }}
    }">

        {{-- Header --}}
        <div style="margin-bottom: 1.5rem; display: flex; align-items: flex-start; justify-content: space-between; gap: 16px;">
            <div>
                <div style="font-size: 1.35rem; font-weight: 700; color: var(--fg-1); font-family: var(--font-display); margin-bottom: 4px;">ความพร้อม Master Data</div>
                <div style="color: var(--fg-3); font-size: 13px;">ตรวจสอบข้อมูลที่ขาดหายก่อนเริ่มจัดตารางสอน — คลิกที่รายการเพื่อดูรายละเอียด</div>
            </div>
            <button type="button" @click="showDismissModal = true" class="btn btn-ghost" style="white-space: nowrap; flex-shrink: 0;">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;">
                    <circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                </svg>
                ตั้งค่าการแจ้งเตือน
                <span x-show="dismissed.length > 0" style="margin-left: 6px; background: var(--fg-3); color: #fff; font-size: 10px; padding: 1px 6px; border-radius: 10px;" x-text="dismissed.length + ' ปิดอยู่'"></span>
            </button>
        </div>

        {{-- ══ DISMISS MODAL ══════════════════════════════════════════ --}}
        <template x-if="showDismissModal">
            <div class="overlay" @click.self="showDismissModal = false">
                <div class="modal-center" style="max-width: 500px;">
                    <div class="modal-hdr">
                        <div>
                            <div class="modal-ttl" style="font-family: var(--font-display);">ตั้งค่าการแจ้งเตือน</div>
                            <div style="font-size: 11px; color: var(--fg-3); margin-top: 2px;">เปิด/ปิดการนับ Warning ใน badge — Critical ปิดไม่ได้</div>
                        </div>
                        <button type="button" class="modal-cls" @click="showDismissModal = false">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>

                    <form action="{{ route('admin.alerts.dismissed') }}" method="POST" style="display: flex; flex-direction: column; min-height: 0;">
                        @csrf
                        {{-- Hidden inputs generated from Alpine state --}}
                        <template x-for="key in dismissed" :key="key">
                            <input type="hidden" name="dismissed[]" :value="key">
                        </template>

                        <div class="modal-body" style="padding: 0;">
                            @foreach($allWarningDefs as $w)
                            @php $key = $w['key']; @endphp
                            <div style="display: flex; align-items: center; gap: 14px; padding: 13px 20px; border-bottom: 1px solid var(--border);"
                                 :style="{ background: dismissed.includes('{{ $key }}') ? 'var(--bg-2)' : 'transparent' }">

                                {{-- Text info --}}
                                <div style="flex: 1; min-width: 0;"
                                     :style="{ opacity: dismissed.includes('{{ $key }}') ? '0.5' : '1' }">
                                    <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 3px;">
                                        <span style="font-size: 11px; color: var(--fg-3);">แจ้งเตือน</span>
                                        <span style="font-size: 11px; font-weight: 700; background: var(--bg-2); border: 1px solid var(--border); color: var(--fg-1); padding: 1px 8px; border-radius: 4px;">{{ $w['label'] }}</span>
                                    </div>
                                    <div style="font-size: 12px; color: var(--fg-2);">
                                        {{ $w['sub'] }}
                                        @if($w['count'] > 0)
                                            <span style="color: var(--status-warning-fg); font-weight: 600;"> — {{ $w['count'] }} รายการ</span>
                                        @else
                                            <span style="color: var(--fg-3);"> — ไม่มีปัญหา</span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Toggle switch --}}
                                <div style="width: 44px; height: 26px; border-radius: 13px; flex-shrink: 0; cursor: pointer; position: relative; transition: background 200ms; background: #1b3c73;"
                                     :style="{ background: dismissed.includes('{{ $key }}') ? '#9ca3af' : '#1b3c73' }"
                                     @click="dismissed.includes('{{ $key }}') ? dismissed = dismissed.filter(k => k !== '{{ $key }}') : dismissed.push('{{ $key }}')">
                                    <div style="position: absolute; top: 4px; width: 18px; height: 18px; border-radius: 50%; background: #ffffff; box-shadow: 0 1px 4px rgba(0,0,0,0.35); transition: transform 200ms; transform: translateX(22px);"
                                         :style="{ transform: dismissed.includes('{{ $key }}') ? 'translateX(4px)' : 'translateX(22px)' }"></div>
                                </div>

                                {{-- Label --}}
                                <div style="width: 52px; text-align: right; flex-shrink: 0;">
                                    <span style="font-size: 11px; font-weight: 600;"
                                          :style="{ color: dismissed.includes('{{ $key }}') ? '#9ca3af' : '#1b3c73' }"
                                          x-text="dismissed.includes('{{ $key }}') ? 'ปิดอยู่' : 'เปิดอยู่'"></span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        <div class="modal-foot">
                            <button type="button" @click="showDismissModal = false" class="btn btn-ghost">ยกเลิก</button>
                            <button type="submit" class="btn btn-primary">บันทึก</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        {{-- ══ CRITICAL ══════════════════════════════════════════════ --}}
        @if(count($criticals) > 0)
        <div style="margin-bottom: 1.5rem;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" style="color: var(--status-conflict-fg);">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--status-conflict-fg);">Critical — ต้องแก้ไขก่อนใช้งานระบบ</span>
                <span style="font-size: 11px; background: var(--status-conflict); color: #fff; padding: 1px 7px; border-radius: 10px; font-weight: 700;">{{ count($criticals) }}</span>
            </div>
            <div style="border: 1.5px solid color-mix(in oklch, var(--status-conflict) 30%, white); border-radius: 4px; overflow: hidden;">
                @foreach($criticals as $c)
                <div style="display: flex; align-items: center; gap: 14px; padding: 13px 18px; background: color-mix(in oklch, var(--status-conflict) 5%, white); {{ !$loop->last ? 'border-bottom: 1px solid color-mix(in oklch, var(--status-conflict) 18%, white);' : '' }}">
                    <div style="width: 6px; height: 6px; border-radius: 50%; background: var(--status-conflict); flex-shrink: 0;"></div>
                    <span style="flex: 1; font-size: 13px; font-weight: 600; color: var(--status-conflict-fg);">{{ $c['label'] }}</span>
                    <a href="{{ $c['link'] }}" class="btn btn-primary" style="font-size: 12px; white-space: nowrap; width: 138px; min-height: 36px; padding: 7px 12px; justify-content: center;">{{ $c['linkTxt'] }}</a>
                </div>
                @endforeach
            </div>
        </div>
        @else
        <div style="display: flex; align-items: center; gap: 10px; padding: 11px 16px; background: color-mix(in oklch, var(--status-success) 6%, white); border: 1px solid color-mix(in oklch, var(--status-success) 25%, white); border-radius: 4px; margin-bottom: 1.5rem;">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" style="color: var(--status-success-fg);"><polyline points="20 6 9 17 4 12"/></svg>
            <span style="font-size: 13px; font-weight: 600; color: var(--status-success-fg);">ไม่มีปัญหา Critical — ระบบพร้อมใช้งาน</span>
        </div>
        @endif

        @if($activeCoursesMissingHead->count() > 0)
        <div id="active-courses-missing-head" style="margin-bottom: 1.5rem;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" style="color: var(--status-conflict-fg);">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                </svg>
                <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--status-conflict-fg);">รายละเอียด - รายวิชาที่ยังไม่มีหัวหน้าวิชา</span>
                <span style="font-size: 11px; background: var(--status-conflict); color: #fff; padding: 1px 7px; border-radius: 10px; font-weight: 700;">{{ $activeCoursesMissingHead->count() }} วิชา</span>
            </div>
            <div style="border: 1.5px solid color-mix(in oklch, var(--status-conflict) 30%, white); border-radius: 4px; overflow: hidden;">
                <div class="table-responsive" style="margin: 0;">
                    <table>
                        <thead>
                            <tr>
                                <th>รหัส</th>
                                <th>ชื่อวิชา</th>
                                <th>หลักสูตร/ภาควิชา</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($activeCoursesMissingHead as $course)
                            <tr>
                                <td style="font-weight:600;color:var(--fg-2);white-space:nowrap;">{{ $course->course_code }}</td>
                                <td style="font-weight:600;color:var(--fg-1);">{{ $course->name_th }}</td>
                                <td style="font-size:12px;color:var(--fg-3);">
                                    {{ $course->curriculum->name ?? '-' }}
                                    <div>{{ $course->department->name ?? 'ไม่สังกัดภาควิชา' }}</div>
                                </td>
                                <td style="text-align:right;white-space:nowrap;">
                                    <a href="{{ route('admin.master_data', ['tab' => 'courses', 'edit_course' => $course->id]) }}" class="btn btn-ghost" style="font-size: 12px; padding: 4px 10px;">แก้ไข</a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        {{-- ══ PA VIOLATIONS DETAIL ══════════════════════════════════ --}}
        @if(count($paViolations) > 0)
        <div id="pa-violations" style="margin-bottom: 1.5rem;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" style="color: var(--status-conflict-fg);">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                </svg>
                <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--status-conflict-fg);">รายละเอียด — สัดส่วน PA ไม่อยู่ในเกณฑ์</span>
                <span style="font-size: 11px; background: var(--status-conflict); color: #fff; padding: 1px 7px; border-radius: 10px; font-weight: 700;">{{ count($paViolations) }} ท่าน</span>
            </div>
            <div style="border: 1.5px solid color-mix(in oklch, var(--status-conflict) 30%, white); border-radius: 4px; overflow: hidden;">
                <div class="table-responsive" style="margin: 0;">
                    <table>
                        <thead>
                            <tr>
                                <th>ชื่อ-นามสกุล</th>
                                <th>กลุ่ม PA</th>
                                <th>ปัญหาที่พบ</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($paViolations as $v)
                            <tr>
                                <td style="font-weight: 600; color: var(--fg-1);">
                                    {{ $v['user']->formatted_name }}
                                    <div style="font-size: 11px; color: var(--fg-3);">{{ $v['user']->instructorProfile->department->name ?? '' }}</div>
                                </td>
                                <td style="font-size: 12px; color: var(--fg-2);">{{ $v['group'] }}</td>
                                <td>
                                    @foreach($v['issues'] as $issue)
                                        <span class="pill p-conflict" style="margin-right: 4px; margin-bottom: 2px;">{{ $issue }}</span>
                                    @endforeach
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <a href="{{ route('admin.master_data', ['tab' => 'instructors', 'edit_instructor' => $v['user']->id]) }}" class="btn btn-ghost" style="font-size: 12px; padding: 4px 10px;">แก้ไข</a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        {{-- ══ WARNINGS ══════════════════════════════════════════════ --}}
        @php
            $warningSections = [
                [
                    'title'   => 'ภาควิชา',
                    'sub'     => 'หัวหน้าภาค / เลขานุการ',
                    'icon'    => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
                    'count'   => $departmentsWithIssues->count(),
                    'unit'    => 'ภาควิชา',
                    'link'    => route('admin.master_data') . '?tab=departments',
                    'linkTxt' => 'ไปจัดการภาควิชา',
                    'key'     => 'departments',
                ],
                [
                    'title'   => 'ห้อง / สถานที่',
                    'sub'     => 'ข้อมูลห้องไม่ครบ',
                    'icon'    => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
                    'count'   => $roomsWithIssues->count(),
                    'unit'    => 'รายการ',
                    'link'    => route('admin.master_data') . '?tab=location_types',
                    'linkTxt' => 'ไปจัดการห้อง',
                    'key'     => 'rooms',
                ],
                [
                    'title'   => 'รายวิชา',
                    'sub'     => 'ยังไม่มีเจ้าหน้าที่ดูแล',
                    'icon'    => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
                    'count'   => $coursesWithoutStaff->count(),
                    'unit'    => 'วิชา',
                    'link'    => route('admin.master_data') . '?tab=courses',
                    'linkTxt' => 'ไปจัดการรายวิชา',
                    'key'     => 'course_staff',
                ],
            ];
            $warningSections = collect($warningSections)->filter(fn($s) => $s['count'] > 0)->values()->all();
            $totalWarnings        = collect($warningSections)->sum('count');
            $activeSections       = collect($warningSections)->filter(fn($s) => !in_array($s['key'], $dismissedWarnings))->values()->all();
            $dismissedSections    = collect($warningSections)->filter(fn($s) =>  in_array($s['key'], $dismissedWarnings))->values()->all();
            $activeWarningCount   = collect($activeSections)->sum('count');
        @endphp

        @if($totalWarnings > 0)
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" style="color: var(--status-warning-fg);">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--status-warning-fg);">Warning — ข้อมูลไม่ครบถ้วน</span>
            @if($activeWarningCount > 0)
                <span style="font-size: 11px; background: var(--status-warning); color: #fff; padding: 1px 7px; border-radius: 10px; font-weight: 700;">{{ $activeWarningCount }}</span>
            @endif
            @if(count($dismissedSections) > 0)
                <span style="font-size: 11px; background: var(--bg-2); color: var(--fg-3); border: 1px solid var(--border); padding: 1px 7px; border-radius: 10px;">ปิดแจ้งเตือน {{ count($dismissedSections) }} หมวด</span>
            @endif
        </div>

        {{-- Active warnings --}}
        @if(count($activeSections) > 0)
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; align-items: start; margin-bottom: 12px;">
            @foreach($activeSections as $sec)
            @php $hasIssue = $sec['count'] > 0; @endphp

            <div class="card" x-data="{ open: false }" style="border-left: 3px solid var(--status-warning);">

                {{-- Card header --}}
                <div class="card-hdr" @click="open = !open" style="cursor: pointer;">
                    <div style="display: flex; align-items: center; gap: 10px; min-width: 0; flex: 1;">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
                             style="color: var(--status-warning-fg); flex-shrink: 0;">
                            {!! $sec['icon'] !!}
                        </svg>
                        <div style="min-width: 0;">
                            <div class="card-ttl">{{ $sec['title'] }}</div>
                            <div style="font-size: 11px; color: var(--fg-3); margin-top: 1px;">{{ $sec['sub'] }}</div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px; flex-shrink: 0;">
                        <span class="pill p-warning">{{ $sec['count'] }} {{ $sec['unit'] }}</span>
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"
                             style="color: var(--fg-3); transition: transform 200ms;"
                             :style="open ? 'transform: rotate(180deg)' : ''">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </div>
                </div>

                {{-- Collapsible detail --}}
                @if($hasIssue)
                <div x-show="open" x-collapse style="border-top: 1px solid var(--border);">

                    {{-- Link to manage --}}
                    <div style="padding: 10px 16px; background: var(--bg-2); border-bottom: 1px solid var(--border);">
                        <a href="{{ $sec['link'] }}" class="btn btn-primary" style="font-size: 12px; width: 100%; justify-content: center;">
                            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right: 5px;"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                            {{ $sec['linkTxt'] }}
                        </a>
                    </div>

                    <div class="table-responsive" style="margin: 0; max-height: 260px; overflow-y: auto;">
                        @if($sec['key'] === 'departments')
                        <table>
                            <thead><tr><th>ภาควิชา</th><th style="min-width: 210px;">สิ่งที่ขาด</th><th></th></tr></thead>
                            <tbody>
                                @foreach($departmentsWithIssues as $item)
                                <tr>
                                    <td style="font-weight:600;color:var(--fg-1);">{{ $item['dept']->name }}</td>
                                    <td style="white-space: nowrap;">@foreach($item['missing'] as $m)<span class="pill p-warning" style="margin-right:4px;font-size:11px;white-space:nowrap;">{{ $m }}</span>@endforeach</td>
                                    <td style="text-align:right;white-space:nowrap;">
                                        <a href="{{ route('admin.master_data', ['tab' => 'departments', 'edit_department' => $item['dept']->id]) }}" class="btn btn-ghost" style="font-size: 12px; padding: 4px 10px;">แก้ไข</a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>

                        @elseif($sec['key'] === 'rooms')
                        <table>
                            <thead><tr><th>ห้อง</th><th>ประเภท</th><th>สิ่งที่ขาด</th><th></th></tr></thead>
                            <tbody>
                                @foreach($roomsWithIssues as $room)
                                <tr>
                                    <td style="font-weight:600;color:var(--fg-2);">{{ $room->room_name ?: $room->room_code ?: '—' }}</td>
                                    <td style="font-size:12px;color:var(--fg-3);">{{ $room->locationType->name ?? '—' }}</td>
                                    <td>
                                        @if(empty($room->room_name))<span class="pill p-conflict" style="margin-right:3px;">ไม่มีชื่อ</span>@endif
                                        @if(empty($room->capacity)||$room->capacity==0)<span class="pill p-warning">ความจุ 0</span>@endif
                                    </td>
                                    <td style="text-align:right;white-space:nowrap;">
                                        <a href="{{ route('admin.master_data', ['tab' => 'location_types', 'edit_room' => $room->id]) }}" class="btn btn-ghost" style="font-size: 12px; padding: 4px 10px;">แก้ไข</a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>

                        @elseif($sec['key'] === 'course_staff')
                        <table>
                            <thead><tr><th>รหัส</th><th>ชื่อวิชา</th><th>ขาด</th><th></th></tr></thead>
                            <tbody>
                                @foreach($coursesWithoutStaff as $course)
                                <tr>
                                    <td style="font-weight:600;color:var(--fg-2);white-space:nowrap;">{{ $course->course_code }}</td>
                                    <td style="font-weight:600;color:var(--fg-1);">{{ $course->name_th }}</td>
                                    <td><span class="pill p-warning">ไม่มีเจ้าหน้าที่</span></td>
                                    <td style="text-align:right;white-space:nowrap;">
                                        <a href="{{ route('admin.master_data', ['tab' => 'courses', 'edit_course' => $course->id]) }}" class="btn btn-ghost" style="font-size: 12px; padding: 4px 10px;">แก้ไข</a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @endif
                    </div>
                </div>
                @endif

            </div>
            @endforeach
        </div>
        @endif

        {{-- Dismissed sections --}}
        @if(count($dismissedSections) > 0)
        <div style="margin-top: 4px;">
            <div style="font-size: 11px; font-weight: 600; color: var(--fg-3); text-transform: uppercase; letter-spacing: 0.07em; margin-bottom: 8px;">ปิดแจ้งเตือนอยู่</div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                @foreach($dismissedSections as $sec)
                <div class="card" style="opacity: 0.5; border-left: 3px solid var(--border);">
                    <div class="card-hdr">
                        <div style="display: flex; align-items: center; gap: 10px; min-width: 0; flex: 1;">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--fg-3); flex-shrink: 0;">{!! $sec['icon'] !!}</svg>
                            <div style="min-width: 0;">
                                <div class="card-ttl" style="color: var(--fg-3);">{{ $sec['title'] }}</div>
                                <div style="font-size: 11px; color: var(--fg-3); margin-top: 1px;">{{ $sec['sub'] }}</div>
                            </div>
                        </div>
                        <span style="font-size: 10px; color: var(--fg-3); background: var(--bg-2); border: 1px solid var(--border); padding: 2px 8px; border-radius: 10px; white-space: nowrap;">ปิดแจ้งเตือน</span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        @else
        <div style="display: flex; align-items: center; gap: 10px; padding: 11px 16px; background: color-mix(in oklch, var(--status-success) 6%, white); border: 1px solid color-mix(in oklch, var(--status-success) 25%, white); border-radius: 4px;">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" style="color: var(--status-success-fg);"><polyline points="20 6 9 17 4 12"/></svg>
            <span style="font-size: 13px; font-weight: 600; color: var(--status-success-fg);">ไม่มีปัญหา Warning — ข้อมูลครบถ้วนทุกหมวด</span>
        </div>
        @endif

    </div>
</x-app-layout>
