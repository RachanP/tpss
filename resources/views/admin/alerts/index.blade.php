<x-app-layout title="แจ้งเตือน Master Data">
    <div style="padding: 2rem;">

        {{-- Header --}}
        <div style="margin-bottom: 1.5rem;">
            <div style="font-size: 1.35rem; font-weight: 700; color: var(--fg-1); font-family: var(--font-display); margin-bottom: 4px;">ความพร้อม Master Data</div>
            <div style="color: var(--fg-3); font-size: 13px;">ตรวจสอบข้อมูลที่ขาดหายก่อนเริ่มจัดตารางสอน — คลิกที่รายการเพื่อดูรายละเอียด</div>
        </div>

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
                    <a href="{{ $c['link'] }}" style="font-size: 12px; color: var(--brand-navy); text-decoration: none; font-weight: 600; white-space: nowrap;">{{ $c['linkTxt'] }} →</a>
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
                                    <a href="{{ route('admin.master_data') }}?tab=instructors" style="font-size: 12px; color: var(--brand-navy); text-decoration: none; font-weight: 600;">แก้ไข →</a>
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
                    'title'   => 'อาจารย์',
                    'sub'     => 'ข้อมูลบุคลากรไม่ครบ',
                    'icon'    => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
                    'count'   => $instructorsWithIssues->count(),
                    'unit'    => 'ท่าน',
                    'link'    => route('admin.master_data') . '?tab=instructors',
                    'linkTxt' => 'ไปจัดการอาจารย์',
                    'key'     => 'instructors',
                ],
                [
                    'title'   => 'ห้อง / สถานที่',
                    'sub'     => 'ข้อมูลห้องไม่ครบ',
                    'icon'    => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
                    'count'   => $roomsWithIssues->count(),
                    'unit'    => 'รายการ',
                    'link'    => route('admin.master_data') . '?tab=rooms',
                    'linkTxt' => 'ไปจัดการห้อง',
                    'key'     => 'rooms',
                ],
                [
                    'title'   => 'รายวิชา',
                    'sub'     => 'ยังไม่มีผู้ประสานงาน',
                    'icon'    => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
                    'count'   => $coursesWithoutCoordinator->count(),
                    'unit'    => 'วิชา',
                    'link'    => route('admin.master_data') . '?tab=courses',
                    'linkTxt' => 'ไปจัดการรายวิชา',
                    'key'     => 'courses',
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
            $totalWarnings = collect($warningSections)->sum('count');
        @endphp

        @if($totalWarnings > 0)
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" style="color: var(--status-warning-fg);">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--status-warning-fg);">Warning — ข้อมูลไม่ครบถ้วน</span>
            <span style="font-size: 11px; background: var(--status-warning); color: #fff; padding: 1px 7px; border-radius: 10px; font-weight: 700;">{{ $totalWarnings }}</span>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; align-items: start;">
            @foreach($warningSections as $sec)
            @php $hasIssue = $sec['count'] > 0; @endphp

            <div class="card" x-data="{ open: false }"
                 style="{{ $hasIssue ? 'border-left: 3px solid var(--status-warning);' : 'opacity: 0.55;' }}">

                {{-- Card header --}}
                <div class="card-hdr"
                     @click="{{ $hasIssue ? 'open = !open' : '' }}"
                     style="{{ $hasIssue ? 'cursor: pointer;' : '' }}">
                    <div style="display: flex; align-items: center; gap: 10px; min-width: 0; flex: 1;">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
                             style="color: {{ $hasIssue ? 'var(--status-warning-fg)' : 'var(--status-success-fg)' }}; flex-shrink: 0;">
                            {!! $sec['icon'] !!}
                        </svg>
                        <div style="min-width: 0;">
                            <div class="card-ttl">{{ $sec['title'] }}</div>
                            <div style="font-size: 11px; color: var(--fg-3); margin-top: 1px;">{{ $sec['sub'] }}</div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px; flex-shrink: 0;">
                        @if($hasIssue)
                            <span class="pill p-warning">{{ $sec['count'] }} {{ $sec['unit'] }}</span>
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"
                                 style="color: var(--fg-3); transition: transform 200ms;"
                                 :style="open ? 'transform: rotate(180deg)' : ''">
                                <polyline points="6 9 12 15 18 9"/>
                            </svg>
                        @else
                            <span class="pill p-success">ครบถ้วน</span>
                        @endif
                    </div>
                </div>

                {{-- Collapsible detail --}}
                @if($hasIssue)
                <div x-show="open" x-collapse style="border-top: 1px solid var(--border);">

                    {{-- Link to manage --}}
                    <div style="padding: 8px 16px; background: var(--bg-2); border-bottom: 1px solid var(--border);">
                        <a href="{{ $sec['link'] }}" style="font-size: 12px; color: var(--brand-navy); text-decoration: none; font-weight: 600;">{{ $sec['linkTxt'] }} →</a>
                    </div>

                    <div class="table-responsive" style="margin: 0; max-height: 260px; overflow-y: auto;">
                        @if($sec['key'] === 'departments')
                        <table>
                            <thead><tr><th>ภาควิชา</th><th>ขาด</th></tr></thead>
                            <tbody>
                                @foreach($departmentsWithIssues as $item)
                                <tr>
                                    <td style="font-weight:600;color:var(--fg-1);">{{ $item['dept']->name }}</td>
                                    <td>@foreach($item['missing'] as $m)<span class="pill p-warning" style="margin-right:4px;">{{ $m }}</span>@endforeach</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>

                        @elseif($sec['key'] === 'instructors')
                        <table>
                            <thead><tr><th>ชื่อ-นามสกุล</th><th>ขาด</th></tr></thead>
                            <tbody>
                                @foreach($instructorsWithIssues as $item)
                                <tr>
                                    <td style="font-weight:600;color:var(--fg-1);">{{ $item['user']->formatted_name }}<div style="font-size:11px;color:var(--fg-3);">{{ $item['user']->instructorProfile->department->name ?? '' }}</div></td>
                                    <td>@foreach($item['missing'] as $m)<span class="pill p-warning" style="margin-right:3px;margin-bottom:2px;">{{ $m }}</span>@endforeach</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>

                        @elseif($sec['key'] === 'rooms')
                        <table>
                            <thead><tr><th>ห้อง</th><th>ประเภท</th><th>ขาด</th></tr></thead>
                            <tbody>
                                @foreach($roomsWithIssues as $room)
                                <tr>
                                    <td style="font-weight:600;color:var(--fg-2);">{{ $room->room_name ?: $room->room_code ?: '—' }}</td>
                                    <td style="font-size:12px;color:var(--fg-3);">{{ $room->locationType->name ?? '—' }}</td>
                                    <td>
                                        @if(empty($room->room_name))<span class="pill p-conflict" style="margin-right:3px;">ไม่มีชื่อ</span>@endif
                                        @if(empty($room->capacity)||$room->capacity==0)<span class="pill p-warning">ความจุ 0</span>@endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>

                        @elseif($sec['key'] === 'courses')
                        <table>
                            <thead><tr><th>รหัส</th><th>ชื่อวิชา</th><th>ขาด</th></tr></thead>
                            <tbody>
                                @foreach($coursesWithoutCoordinator as $course)
                                <tr>
                                    <td style="font-weight:600;color:var(--fg-2);white-space:nowrap;">{{ $course->course_code }}</td>
                                    <td style="font-weight:600;color:var(--fg-1);">{{ $course->name_th }}</td>
                                    <td><span class="pill p-warning">ไม่มีผู้ประสานงาน</span></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>

                        @elseif($sec['key'] === 'course_staff')
                        <table>
                            <thead><tr><th>รหัส</th><th>ชื่อวิชา</th><th>ขาด</th></tr></thead>
                            <tbody>
                                @foreach($coursesWithoutStaff as $course)
                                <tr>
                                    <td style="font-weight:600;color:var(--fg-2);white-space:nowrap;">{{ $course->course_code }}</td>
                                    <td style="font-weight:600;color:var(--fg-1);">{{ $course->name_th }}</td>
                                    <td><span class="pill p-warning">ไม่มีเจ้าหน้าที่</span></td>
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
        @else
        <div style="display: flex; align-items: center; gap: 10px; padding: 11px 16px; background: color-mix(in oklch, var(--status-success) 6%, white); border: 1px solid color-mix(in oklch, var(--status-success) 25%, white); border-radius: 4px;">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" style="color: var(--status-success-fg);"><polyline points="20 6 9 17 4 12"/></svg>
            <span style="font-size: 13px; font-weight: 600; color: var(--status-success-fg);">ไม่มีปัญหา Warning — ข้อมูลครบถ้วนทุกหมวด</span>
        </div>
        @endif

    </div>
</x-app-layout>
