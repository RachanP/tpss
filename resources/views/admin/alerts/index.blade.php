<x-app-layout title="แจ้งเตือน Master Data">
    @php
        $warningDefs = [
            [
                'key' => 'departments',
                'title' => 'ภาควิชา',
                'desc' => 'หัวหน้าภาคหรือเลขานุการยังไม่ครบ',
                'count' => $departmentsWithIssues->count(),
                'unit' => 'ภาควิชา',
                'href' => route('admin.master_data', ['tab' => 'departments']),
                'action' => 'ไปจัดการภาควิชา',
                'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            ],
            [
                'key' => 'rooms',
                'title' => 'ห้องและสถานที่',
                'desc' => 'ชื่อห้องหรือความจุยังไม่ครบ',
                'count' => $roomsWithIssues->count(),
                'unit' => 'รายการ',
                'href' => route('admin.master_data', ['tab' => 'location_types']),
                'action' => 'ไปจัดการห้องและสถานที่',
                'icon' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
            ],
            [
                'key' => 'course_staff',
                'title' => 'รายวิชา',
                'desc' => 'รายวิชายังไม่มีเจ้าหน้าที่ดูแล',
                'count' => $coursesWithoutStaff->count(),
                'unit' => 'วิชา',
                'href' => route('admin.master_data', ['tab' => 'courses']),
                'action' => 'ไปจัดการรายวิชา',
                'icon' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
            ],
        ];

        $visibleWarnings = collect($warningDefs)
            ->filter(fn ($item) => $item['count'] > 0 && ! in_array($item['key'], $dismissedWarnings, true))
            ->values();
        $dismissedWarningDefs = collect($warningDefs)
            ->filter(fn ($item) => $item['count'] > 0 && in_array($item['key'], $dismissedWarnings, true))
            ->values();

        $criticalCount = count($criticals);
        $visibleWarningCount = $visibleWarnings->sum('count');
        $dismissedWarningCount = $dismissedWarningDefs->sum('count');
        $totalIssues = $criticalCount + $visibleWarningCount;
    @endphp

    <div class="alerts-page" x-data="{ showDismissModal: false, dismissed: {{ Js::from($dismissedWarnings) }} }">
        <section class="alerts-hero">
            <div class="alerts-hero-copy">
                <div class="alerts-kicker">Master Data Readiness</div>
                <h1>ความพร้อม Master Data</h1>
                <p>ตรวจสอบเงื่อนไขสำคัญและข้อมูลที่ควรเติมให้ครบก่อนเริ่มจัดตารางสอน</p>
            </div>
            <button type="button" class="alerts-settings-btn" @click="showDismissModal = true">
                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                </svg>
                ตั้งค่าการแจ้งเตือน
                <span x-show="dismissed.length > 0" x-text="dismissed.length" class="alerts-settings-count"></span>
            </button>
        </section>

        <section class="alerts-summary-grid" aria-label="สรุปสถานะการแจ้งเตือน">
            <div class="alerts-summary-card {{ $criticalCount > 0 ? 'is-conflict' : 'is-success' }}">
                <div class="alerts-summary-label">เงื่อนไขสำคัญ</div>
                <div class="alerts-summary-value">{{ number_format($criticalCount) }}</div>
                <div class="alerts-summary-note">{{ $criticalCount > 0 ? 'ต้องแก้ก่อนเปิดใช้งาน' : 'ผ่านทั้งหมด' }}</div>
            </div>
            <div class="alerts-summary-card {{ $visibleWarningCount > 0 ? 'is-warning' : 'is-success' }}">
                <div class="alerts-summary-label">ข้อมูลควรตรวจ</div>
                <div class="alerts-summary-value">{{ number_format($visibleWarningCount) }}</div>
                <div class="alerts-summary-note">{{ $visibleWarningCount > 0 ? 'ยังควรเติมให้ครบ' : 'ไม่มีรายการที่เปิดเตือน' }}</div>
            </div>
            <div class="alerts-summary-card is-muted">
                <div class="alerts-summary-label">ปิดแจ้งเตือนอยู่</div>
                <div class="alerts-summary-value">{{ number_format($dismissedWarningCount) }}</div>
                <div class="alerts-summary-note">ไม่นับใน badge ด้านซ้าย</div>
            </div>
        </section>

        <section class="alerts-status-banner {{ $totalIssues > 0 ? 'is-attention' : 'is-ready' }}">
            <span class="alerts-status-icon" aria-hidden="true">
                @if($totalIssues > 0)
                    <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                @else
                    <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m9 12 2 2 4-4"/></svg>
                @endif
            </span>
            <div>
                <strong>{{ $totalIssues > 0 ? 'ยังมีข้อมูลที่ต้องตรวจสอบ' : 'ข้อมูลหลักพร้อมใช้งาน' }}</strong>
                <span>{{ $totalIssues > 0 ? 'จัดการรายการด้านล่างเพื่อให้ข้อมูลตั้งต้นพร้อมก่อนเปิดช่วงจัดตาราง' : 'ไม่พบ Critical และ Warning ที่เปิดใช้งานอยู่' }}</span>
            </div>
        </section>

        @if($criticalCount > 0)
            <section class="alerts-section">
                <div class="alerts-section-head">
                    <div>
                        <div class="alerts-section-title is-conflict">Critical</div>
                        <div class="alerts-section-sub">ต้องแก้ไขก่อนใช้งานระบบจัดตาราง</div>
                    </div>
                    <span class="alerts-count-pill is-conflict">{{ number_format($criticalCount) }} รายการ</span>
                </div>
                <div class="alerts-action-list">
                    @foreach($criticals as $critical)
                        <a href="{{ $critical['link'] }}" class="alerts-action-row is-conflict">
                            <span class="alerts-row-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><path d="M12 17h.01"/></svg>
                            </span>
                            <span class="alerts-row-main">
                                <strong>{{ $critical['label'] }}</strong>
                                <small>{{ $critical['linkTxt'] }}</small>
                            </span>
                            <span class="alerts-row-action">เปิดรายการ</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @else
            <section class="alerts-section is-compact">
                <div class="alerts-clear-row">
                    <span class="alerts-row-icon is-success" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m9 12 2 2 4-4"/></svg>
                    </span>
                    <strong>ไม่พบปัญหา Critical</strong>
                    <span>ระบบผ่านเงื่อนไขสำคัญสำหรับการใช้งาน</span>
                </div>
            </section>
        @endif

        @if($activeCoursesMissingHead->count() > 0)
            <section id="active-courses-missing-head" class="alerts-section">
                <div class="alerts-section-head">
                    <div>
                        <div class="alerts-section-title is-conflict">รายวิชาเปิดสอนที่ยังไม่มีหัวหน้าวิชา</div>
                        <div class="alerts-section-sub">รายวิชา active ต้องมีหัวหน้าวิชาก่อนเปิดรอบจัดตาราง</div>
                    </div>
                    <span class="alerts-count-pill is-conflict">{{ number_format($activeCoursesMissingHead->count()) }} วิชา</span>
                </div>
                <div class="alerts-table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>รหัส</th>
                                <th>ชื่อวิชา</th>
                                <th>หลักสูตร / ภาควิชา</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($activeCoursesMissingHead as $course)
                                <tr>
                                    <td class="alerts-code">{{ $course->course_code }}</td>
                                    <td><strong>{{ $course->name_th }}</strong></td>
                                    <td class="alerts-muted">
                                        {{ $course->curriculum->name ?? '-' }}
                                        <span>{{ $course->department->name ?? 'ไม่สังกัดภาควิชา' }}</span>
                                    </td>
                                    <td class="alerts-table-action">
                                        <a href="{{ route('admin.master_data', ['tab' => 'courses', 'edit_course' => $course->id]) }}" class="alerts-mini-btn">แก้ไข</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if(count($paViolations) > 0)
            <section id="pa-violations" class="alerts-section">
                <div class="alerts-section-head">
                    <div>
                        <div class="alerts-section-title is-conflict">สัดส่วน PA ไม่อยู่ในเกณฑ์</div>
                        <div class="alerts-section-sub">ตรวจสัดส่วนภาระงานให้รวม 100% และตรงตามเกณฑ์ตำแหน่ง</div>
                    </div>
                    <span class="alerts-count-pill is-conflict">{{ number_format(count($paViolations)) }} ท่าน</span>
                </div>
                <div class="alerts-table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ชื่ออาจารย์</th>
                                <th>กลุ่ม PA</th>
                                <th>ปัญหาที่พบ</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($paViolations as $violation)
                                <tr>
                                    <td>
                                        <strong>{{ $violation['user']->formatted_name }}</strong>
                                        <span class="alerts-muted-inline">{{ $violation['user']->instructorProfile->department->name ?? '' }}</span>
                                    </td>
                                    <td class="alerts-muted">{{ $violation['group'] }}</td>
                                    <td>
                                        <div class="alerts-chip-list">
                                            @foreach($violation['issues'] as $issue)
                                                <span class="alerts-chip is-conflict">{{ $issue }}</span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="alerts-table-action">
                                        <a href="{{ route('admin.master_data', ['tab' => 'instructors', 'edit_instructor' => $violation['user']->id]) }}" class="alerts-mini-btn">แก้ไข</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="alerts-section">
            <div class="alerts-section-head">
                <div>
                    <div class="alerts-section-title is-warning">Warning</div>
                    <div class="alerts-section-sub">ข้อมูลที่ไม่บล็อกการใช้งาน แต่ควรเติมให้ครบเพื่อลดความผิดพลาด</div>
                </div>
                <div class="alerts-section-pills">
                    @if($visibleWarningCount > 0)
                        <span class="alerts-count-pill is-warning">{{ number_format($visibleWarningCount) }} รายการ</span>
                    @endif
                    @if($dismissedWarningCount > 0)
                        <span class="alerts-count-pill is-muted">ปิดอยู่ {{ number_format($dismissedWarningCount) }}</span>
                    @endif
                </div>
            </div>

            @if($visibleWarnings->isEmpty() && $dismissedWarningDefs->isEmpty())
                <div class="alerts-clear-row">
                    <span class="alerts-row-icon is-success" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m9 12 2 2 4-4"/></svg>
                    </span>
                    <strong>ไม่พบ Warning</strong>
                    <span>ข้อมูลหลักครบถ้วนทุกหมวด</span>
                </div>
            @else
                <div class="alerts-warning-grid">
                    @foreach($visibleWarnings as $section)
                        <article class="alerts-warning-card" x-data="{ open: false }">
                            <button type="button" class="alerts-warning-top" @click="open = !open">
                                <span class="alerts-warning-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">{!! $section['icon'] !!}</svg>
                                </span>
                                <span class="alerts-warning-main">
                                    <strong>{{ $section['title'] }}</strong>
                                    <small>{{ $section['desc'] }}</small>
                                </span>
                                <span class="alerts-warning-count">{{ number_format($section['count']) }} {{ $section['unit'] }}</span>
                                <svg class="alerts-chevron" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" :class="{ 'is-open': open }"><path d="m6 9 6 6 6-6"/></svg>
                            </button>

                            <div class="alerts-warning-detail" x-show="open" x-collapse>
                                <a href="{{ $section['href'] }}" class="alerts-manage-link">{{ $section['action'] }}</a>
                                <div class="alerts-table-wrap is-embedded">
                                    @if($section['key'] === 'departments')
                                        <table class="alerts-department-table">
                                            <thead><tr><th>ภาควิชา</th><th>สิ่งที่ขาด</th><th></th></tr></thead>
                                            <tbody>
                                                @foreach($departmentsWithIssues as $item)
                                                    <tr>
                                                        <td data-label="ภาควิชา"><strong>{{ $item['dept']->name }}</strong></td>
                                                        <td data-label="สิ่งที่ขาด">
                                                            <div class="alerts-chip-list">
                                                                @foreach($item['missing'] as $missing)
                                                                    <span class="alerts-chip is-warning">{{ $missing }}</span>
                                                                @endforeach
                                                            </div>
                                                        </td>
                                                        <td class="alerts-table-action" data-label="จัดการ">
                                                            <a href="{{ route('admin.master_data', ['tab' => 'departments', 'edit_department' => $item['dept']->id]) }}" class="alerts-mini-btn">แก้ไข</a>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    @elseif($section['key'] === 'rooms')
                                        <table>
                                            <thead><tr><th>ห้อง</th><th>ประเภท</th><th>สิ่งที่ขาด</th><th></th></tr></thead>
                                            <tbody>
                                                @foreach($roomsWithIssues as $room)
                                                    <tr>
                                                        <td><strong>{{ $room->room_name ?: $room->room_code ?: '-' }}</strong></td>
                                                        <td class="alerts-muted">{{ $room->locationType->name ?? '-' }}</td>
                                                        <td>
                                                            <div class="alerts-chip-list">
                                                                @if(empty($room->room_name))
                                                                    <span class="alerts-chip is-conflict">ไม่มีชื่อ</span>
                                                                @endif
                                                                @if(empty($room->capacity) || (int) $room->capacity === 0)
                                                                    <span class="alerts-chip is-warning">ความจุ 0</span>
                                                                @endif
                                                            </div>
                                                        </td>
                                                        <td class="alerts-table-action">
                                                            <a href="{{ route('admin.master_data', ['tab' => 'location_types', 'edit_room' => $room->id]) }}" class="alerts-mini-btn">แก้ไข</a>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    @else
                                        <table>
                                            <thead><tr><th>รหัส</th><th>ชื่อวิชา</th><th>สิ่งที่ขาด</th><th></th></tr></thead>
                                            <tbody>
                                                @foreach($coursesWithoutStaff as $course)
                                                    <tr>
                                                        <td class="alerts-code">{{ $course->course_code }}</td>
                                                        <td><strong>{{ $course->name_th }}</strong></td>
                                                        <td><span class="alerts-chip is-warning">ไม่มีเจ้าหน้าที่</span></td>
                                                        <td class="alerts-table-action">
                                                            <a href="{{ route('admin.master_data', ['tab' => 'courses', 'edit_course' => $course->id]) }}" class="alerts-mini-btn">แก้ไข</a>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach

                    @foreach($dismissedWarningDefs as $section)
                        <article class="alerts-warning-card is-dismissed">
                            <div class="alerts-warning-top">
                                <span class="alerts-warning-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">{!! $section['icon'] !!}</svg>
                                </span>
                                <span class="alerts-warning-main">
                                    <strong>{{ $section['title'] }}</strong>
                                    <small>{{ $section['desc'] }}</small>
                                </span>
                                <span class="alerts-dismissed-label">ปิดแจ้งเตือน</span>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <template x-if="showDismissModal">
            <div class="overlay" @click.self="showDismissModal = false">
                <div class="modal-center alerts-modal">
                    <div class="modal-hdr">
                        <div>
                            <div class="modal-ttl">ตั้งค่าการแจ้งเตือน</div>
                            <div class="alerts-modal-sub">เปิดหรือปิดการนับ Warning ใน badge เมนูด้านซ้าย, Critical ปิดไม่ได้</div>
                        </div>
                        <button type="button" class="modal-cls" @click="showDismissModal = false">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                        </button>
                    </div>
                    <form action="{{ route('admin.alerts.dismissed') }}" method="POST">
                        @csrf
                        <template x-for="key in dismissed" :key="key">
                            <input type="hidden" name="dismissed[]" :value="key">
                        </template>
                        <div class="modal-body alerts-modal-body">
                            @foreach($warningDefs as $warning)
                                <button type="button"
                                        class="alerts-toggle-row"
                                        :class="{ 'is-off': dismissed.includes('{{ $warning['key'] }}') }"
                                        @click="dismissed.includes('{{ $warning['key'] }}') ? dismissed = dismissed.filter(key => key !== '{{ $warning['key'] }}') : dismissed.push('{{ $warning['key'] }}')">
                                    <span class="alerts-toggle-text">
                                        <strong>{{ $warning['title'] }}</strong>
                                        <small>{{ $warning['desc'] }}: {{ number_format($warning['count']) }} {{ $warning['unit'] }}</small>
                                    </span>
                                    <span class="alerts-toggle-switch" aria-hidden="true">
                                        <span></span>
                                    </span>
                                    <span class="alerts-toggle-state" x-text="dismissed.includes('{{ $warning['key'] }}') ? 'ปิดอยู่' : 'เปิดอยู่'"></span>
                                </button>
                            @endforeach
                        </div>
                        <div class="modal-foot">
                            <button type="button" class="btn btn-ghost" @click="showDismissModal = false">ยกเลิก</button>
                            <button type="submit" class="btn btn-primary">บันทึก</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </div>

    <style>
        .alerts-page {
            width: 100%;
            max-width: 1440px;
            margin: 0 auto;
            padding: 28px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .alerts-hero {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            padding: 4px 0 2px;
        }

        .alerts-kicker {
            margin-bottom: 5px;
            color: var(--fg-3);
            font-size: 12px;
            font-weight: 800;
        }

        .alerts-hero h1 {
            margin: 0;
            font-family: var(--font-display);
            font-size: 26px;
            line-height: 1.25;
            color: var(--fg-1);
        }

        .alerts-hero p {
            margin: 8px 0 0;
            color: var(--fg-3);
            font-size: 13px;
            line-height: 1.6;
        }

        .alerts-settings-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            min-height: 42px;
            padding: 9px 15px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 14%, var(--border));
            border-radius: var(--r-md);
            background: color-mix(in oklch, var(--brand-navy) 4%, var(--surface));
            color: var(--fg-1);
            font-family: inherit;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
        }

        .alerts-settings-btn:hover,
        .alerts-settings-btn:focus-visible {
            border-color: color-mix(in oklch, var(--brand-navy) 28%, var(--border));
            background: color-mix(in oklch, var(--brand-navy) 7%, var(--surface));
            outline: none;
        }

        .alerts-settings-count {
            min-width: 20px;
            height: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: var(--fg-2);
            color: var(--surface);
            font-size: 11px;
        }

        .alerts-summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .alerts-summary-card {
            padding: 18px 20px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 14%, var(--border));
            border-radius: var(--r-lg);
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 3%, var(--surface)), var(--surface) 50%),
                var(--surface);
            box-shadow:
                0 1px 2px rgba(0, 36, 84, 0.05),
                0 14px 30px -24px rgba(0, 36, 84, 0.28),
                inset 0 1px 0 rgba(255, 255, 255, 0.65);
        }

        .alerts-summary-card.is-conflict {
            border-color: var(--status-conflict-border);
            background: color-mix(in oklch, var(--status-conflict) 5%, var(--surface));
        }

        .alerts-summary-card.is-warning {
            border-color: var(--status-warning-border);
            background: color-mix(in oklch, var(--status-warning) 5%, var(--surface));
        }

        .alerts-summary-card.is-success {
            border-color: var(--status-success-border);
            background: color-mix(in oklch, var(--status-success) 5%, var(--surface));
        }

        .alerts-summary-label {
            color: var(--fg-2);
            font-size: 12px;
            font-weight: 800;
            line-height: 1.35;
        }

        .alerts-summary-value {
            margin-top: 8px;
            font-family: var(--font-display);
            color: var(--fg-1);
            font-size: 32px;
            font-weight: 800;
            line-height: 1;
            font-variant-numeric: tabular-nums;
        }

        .alerts-summary-note {
            margin-top: 7px;
            color: var(--fg-3);
            font-size: 12px;
            font-weight: 700;
            line-height: 1.4;
        }

        .alerts-status-banner {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 16px;
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            background: var(--surface);
        }

        .alerts-status-banner.is-attention {
            border-color: var(--status-warning-border);
            background: color-mix(in oklch, var(--status-warning) 5%, var(--surface));
            color: var(--status-warning-fg);
        }

        .alerts-status-banner.is-ready {
            border-color: var(--status-success-border);
            background: color-mix(in oklch, var(--status-success) 5%, var(--surface));
            color: var(--status-success-fg);
        }

        .alerts-status-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: color-mix(in oklch, currentColor 12%, transparent);
            flex-shrink: 0;
        }

        .alerts-status-banner strong {
            display: block;
            font-size: 13px;
            line-height: 1.35;
        }

        .alerts-status-banner span:not(.alerts-status-icon) {
            display: block;
            margin-top: 2px;
            color: var(--fg-3);
            font-size: 12px;
            line-height: 1.5;
        }

        .alerts-section {
            border: 1px solid color-mix(in oklch, var(--brand-navy) 12%, var(--border));
            border-radius: var(--r-lg);
            background: var(--surface);
            overflow: hidden;
            box-shadow:
                0 1px 2px rgba(0, 36, 84, 0.05),
                0 14px 30px -24px rgba(0, 36, 84, 0.24);
        }

        .alerts-section.is-compact {
            padding: 14px;
        }

        .alerts-section-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            padding: 16px 18px;
            border-bottom: 1px solid var(--border);
            background: color-mix(in oklch, var(--bg-2) 54%, var(--surface));
        }

        .alerts-section-title {
            color: var(--fg-1);
            font-family: var(--font-display);
            font-size: 16px;
            font-weight: 800;
            line-height: 1.3;
        }

        .alerts-section-title.is-conflict {
            color: var(--status-conflict-fg);
        }

        .alerts-section-title.is-warning {
            color: var(--status-warning-fg);
        }

        .alerts-section-sub {
            margin-top: 3px;
            color: var(--fg-3);
            font-size: 12px;
            line-height: 1.45;
        }

        .alerts-section-pills {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            flex-wrap: wrap;
        }

        .alerts-count-pill,
        .alerts-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 24px;
            padding: 4px 9px;
            border-radius: 999px;
            border: 1px solid var(--border);
            font-size: 11px;
            font-weight: 800;
            line-height: 1;
            white-space: nowrap;
        }

        .alerts-count-pill.is-conflict,
        .alerts-chip.is-conflict {
            border-color: var(--status-conflict-border);
            background: var(--status-conflict-bg);
            color: var(--status-conflict-fg);
        }

        .alerts-count-pill.is-warning,
        .alerts-chip.is-warning {
            border-color: var(--status-warning-border);
            background: var(--status-warning-bg);
            color: var(--status-warning-fg);
        }

        .alerts-count-pill.is-muted {
            background: var(--bg-2);
            color: var(--fg-3);
        }

        .alerts-action-list {
            display: flex;
            flex-direction: column;
        }

        .alerts-action-row {
            display: grid;
            grid-template-columns: 34px minmax(0, 1fr) auto;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            text-decoration: none;
            border-bottom: 1px solid var(--border);
            color: inherit;
        }

        .alerts-action-row:last-child {
            border-bottom: 0;
        }

        .alerts-action-row:hover,
        .alerts-action-row:focus-visible {
            background: color-mix(in oklch, var(--brand-navy) 4%, var(--surface));
            outline: none;
        }

        .alerts-row-icon,
        .alerts-warning-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            color: var(--status-warning-fg);
            background: color-mix(in oklch, var(--status-warning) 14%, transparent);
        }

        .alerts-action-row.is-conflict .alerts-row-icon {
            color: var(--status-conflict-fg);
            background: color-mix(in oklch, var(--status-conflict) 12%, transparent);
        }

        .alerts-row-icon.is-success {
            color: var(--status-success-fg);
            background: color-mix(in oklch, var(--status-success) 14%, transparent);
        }

        .alerts-row-main {
            min-width: 0;
        }

        .alerts-row-main strong {
            display: block;
            color: var(--fg-1);
            font-size: 13px;
            line-height: 1.45;
        }

        .alerts-row-main small {
            display: block;
            margin-top: 2px;
            color: var(--fg-3);
            font-size: 11.5px;
            line-height: 1.4;
        }

        .alerts-row-action,
        .alerts-mini-btn,
        .alerts-manage-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 32px;
            padding: 6px 11px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 20%, var(--border));
            border-radius: var(--r-sm);
            background: color-mix(in oklch, var(--brand-navy) 5%, var(--surface));
            color: var(--brand-navy);
            font-size: 12px;
            font-weight: 800;
            text-decoration: none;
            white-space: nowrap;
        }

        .alerts-row-action:hover,
        .alerts-mini-btn:hover,
        .alerts-manage-link:hover,
        .alerts-row-action:focus-visible,
        .alerts-mini-btn:focus-visible,
        .alerts-manage-link:focus-visible {
            border-color: color-mix(in oklch, var(--brand-navy) 34%, var(--border));
            background: color-mix(in oklch, var(--brand-navy) 8%, var(--surface));
            outline: none;
        }

        .alerts-clear-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 18px;
            color: var(--status-success-fg);
            background: color-mix(in oklch, var(--status-success) 5%, var(--surface));
        }

        .alerts-clear-row strong {
            color: var(--status-success-fg);
            font-size: 13px;
        }

        .alerts-clear-row span:last-child {
            color: var(--fg-3);
            font-size: 12px;
        }

        .alerts-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .alerts-table-wrap.is-embedded {
            max-height: 300px;
            overflow-y: auto;
            border-top: 1px solid var(--border);
        }

        .alerts-table-wrap table {
            min-width: 720px;
            width: 100%;
            border-collapse: collapse;
        }

        .alerts-table-wrap.is-embedded table {
            min-width: min(720px, 100%);
        }

        .alerts-department-table {
            table-layout: fixed;
        }

        .alerts-department-table th:first-child,
        .alerts-department-table td:first-child {
            width: 48%;
        }

        .alerts-department-table th:nth-child(2),
        .alerts-department-table td:nth-child(2) {
            width: 34%;
        }

        .alerts-department-table th:last-child,
        .alerts-department-table td:last-child {
            width: 18%;
        }

        .alerts-department-table strong {
            overflow-wrap: anywhere;
            line-height: 1.45;
        }

        .alerts-table-wrap th {
            padding: 11px 16px;
            background: color-mix(in oklch, var(--bg-2) 70%, var(--surface));
            border-bottom: 1px solid var(--border);
            color: var(--fg-3);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0;
            text-transform: none;
        }

        .alerts-table-wrap td {
            padding: 13px 16px;
            border-bottom: 1px solid var(--border-subtle);
            color: var(--fg-2);
            font-size: 12.5px;
            vertical-align: middle;
        }

        .alerts-table-wrap tr:last-child td {
            border-bottom: 0;
        }

        .alerts-code {
            color: var(--fg-2);
            font-weight: 800;
            white-space: nowrap;
        }

        .alerts-muted {
            color: var(--fg-3);
        }

        .alerts-muted span,
        .alerts-muted-inline {
            display: block;
            margin-top: 2px;
            color: var(--fg-3);
            font-size: 11.5px;
        }

        .alerts-chip-list {
            display: flex;
            align-items: center;
            gap: 5px;
            flex-wrap: wrap;
        }

        .alerts-table-action {
            text-align: right;
            white-space: nowrap;
        }

        .alerts-warning-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(620px, 100%), 1fr));
            gap: 14px;
            padding: 16px;
        }

        .alerts-warning-card {
            border: 1px solid var(--border);
            border-radius: var(--r-md);
            background: var(--surface);
            overflow: hidden;
        }

        .alerts-warning-card.is-dismissed {
            opacity: .62;
            background: color-mix(in oklch, var(--bg-2) 72%, var(--surface));
        }

        .alerts-warning-top {
            width: 100%;
            display: grid;
            grid-template-columns: 34px minmax(0, 1fr) auto 16px;
            align-items: center;
            gap: 11px;
            padding: 13px 14px;
            border: 0;
            background: transparent;
            color: inherit;
            font-family: inherit;
            text-align: left;
            cursor: pointer;
        }

        .alerts-warning-card.is-dismissed .alerts-warning-top {
            grid-template-columns: 34px minmax(0, 1fr) auto;
            cursor: default;
        }

        .alerts-warning-top:hover,
        .alerts-warning-top:focus-visible {
            background: color-mix(in oklch, var(--brand-navy) 4%, var(--surface));
            outline: none;
        }

        .alerts-warning-main {
            min-width: 0;
        }

        .alerts-warning-main strong {
            display: block;
            color: var(--fg-1);
            font-size: 13px;
            line-height: 1.35;
        }

        .alerts-warning-main small {
            display: block;
            margin-top: 2px;
            color: var(--fg-3);
            font-size: 11.5px;
            line-height: 1.35;
        }

        .alerts-warning-count {
            color: var(--status-warning-fg);
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .alerts-chevron {
            color: var(--fg-3);
            transition: transform var(--dur-fast);
        }

        .alerts-chevron.is-open {
            transform: rotate(180deg);
        }

        .alerts-warning-detail {
            border-top: 1px solid var(--border);
            background: color-mix(in oklch, var(--bg-2) 42%, var(--surface));
        }

        .alerts-manage-link {
            margin: 12px 14px;
            width: calc(100% - 28px);
        }

        .alerts-dismissed-label {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 4px 9px;
            border-radius: 999px;
            border: 1px solid var(--border);
            color: var(--fg-3);
            background: var(--bg-2);
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .alerts-modal {
            max-width: 560px;
        }

        .alerts-modal-sub {
            margin-top: 2px;
            color: var(--fg-3);
            font-size: 11.5px;
            line-height: 1.45;
        }

        .alerts-modal-body {
            display: flex;
            flex-direction: column;
            gap: 0;
            padding: 0;
        }

        .alerts-toggle-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 48px 56px;
            align-items: center;
            gap: 12px;
            width: 100%;
            padding: 14px 20px;
            border: 0;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
            color: inherit;
            font-family: inherit;
            text-align: left;
            cursor: pointer;
        }

        .alerts-toggle-row:hover,
        .alerts-toggle-row:focus-visible {
            background: color-mix(in oklch, var(--brand-navy) 4%, var(--surface));
            outline: none;
        }

        .alerts-toggle-row.is-off {
            background: color-mix(in oklch, var(--bg-2) 62%, var(--surface));
        }

        .alerts-toggle-text {
            min-width: 0;
        }

        .alerts-toggle-text strong {
            display: block;
            color: var(--fg-1);
            font-size: 13px;
            line-height: 1.35;
        }

        .alerts-toggle-text small {
            display: block;
            margin-top: 2px;
            color: var(--fg-3);
            font-size: 11.5px;
            line-height: 1.45;
        }

        .alerts-toggle-switch {
            position: relative;
            width: 46px;
            height: 26px;
            border-radius: 999px;
            background: var(--brand-navy);
            transition: background var(--dur-fast);
        }

        .alerts-toggle-switch span {
            position: absolute;
            top: 4px;
            left: 23px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: var(--surface);
            box-shadow: 0 1px 4px rgba(0, 36, 84, 0.28);
            transition: left var(--dur-fast);
        }

        .alerts-toggle-row.is-off .alerts-toggle-switch {
            background: var(--fg-3);
        }

        .alerts-toggle-row.is-off .alerts-toggle-switch span {
            left: 5px;
        }

        .alerts-toggle-state {
            color: var(--brand-navy);
            font-size: 11px;
            font-weight: 800;
            text-align: right;
        }

        .alerts-toggle-row.is-off .alerts-toggle-state {
            color: var(--fg-3);
        }

        @media (max-width: 1100px) {
            .alerts-summary-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 720px) {
            .alerts-page {
                padding: 16px;
                gap: 14px;
            }

            .alerts-hero,
            .alerts-section-head {
                flex-direction: column;
                align-items: stretch;
            }

            .alerts-settings-btn {
                width: 100%;
            }

            .alerts-action-row {
                grid-template-columns: 34px minmax(0, 1fr);
            }

            .alerts-row-action {
                grid-column: 2;
                justify-self: start;
            }

            .alerts-warning-top {
                grid-template-columns: 34px minmax(0, 1fr) 16px;
            }

            .alerts-warning-count {
                grid-column: 2 / -1;
            }

            .alerts-warning-grid {
                padding: 12px;
            }

            .alerts-table-wrap.is-embedded {
                max-height: none;
                overflow: visible;
            }

            .alerts-table-wrap.is-embedded table,
            .alerts-table-wrap.is-embedded thead,
            .alerts-table-wrap.is-embedded tbody,
            .alerts-table-wrap.is-embedded tr,
            .alerts-table-wrap.is-embedded td {
                display: block;
                width: 100%;
                min-width: 0;
            }

            .alerts-table-wrap.is-embedded table {
                border-collapse: separate;
            }

            .alerts-table-wrap.is-embedded thead {
                display: none;
            }

            .alerts-table-wrap.is-embedded tr {
                padding: 12px 14px;
                border-bottom: 1px solid var(--border-subtle);
            }

            .alerts-table-wrap.is-embedded tr:last-child {
                border-bottom: 0;
            }

            .alerts-table-wrap.is-embedded td {
                display: grid;
                grid-template-columns: 92px minmax(0, 1fr);
                gap: 10px;
                padding: 5px 0;
                border-bottom: 0;
            }

            .alerts-table-wrap.is-embedded td::before {
                content: attr(data-label);
                color: var(--fg-3);
                font-size: 11px;
                font-weight: 800;
                line-height: 1.45;
            }

            .alerts-table-wrap.is-embedded td:not([data-label])::before {
                content: "";
            }

            .alerts-table-wrap.is-embedded .alerts-table-action {
                text-align: left;
            }

            .alerts-toggle-row {
                grid-template-columns: minmax(0, 1fr) 48px;
            }

            .alerts-toggle-state {
                grid-column: 1 / -1;
                text-align: left;
            }
        }

        @media (max-width: 480px) {
            .alerts-table-wrap.is-embedded td {
                grid-template-columns: 1fr;
                gap: 4px;
            }

            .alerts-mini-btn,
            .alerts-manage-link {
                width: 100%;
            }
        }
    </style>
</x-app-layout>
