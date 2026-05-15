@php
    $user = auth()->user();
    $activeRole = session('active_role', 'staff');
    $roles = $user ? $user->roles : collect();

    $roleNames = [
        'admin' => 'ผู้ดูแลระบบ',
        'staff' => 'เจ้าหน้าที่',
        'course_head' => 'หัวหน้าวิชา',
        'executive' => 'ผู้บริหาร',
        'instructor' => 'อาจารย์ผู้สอน',
    ];

    // Get current path to highlight active menu
    $currentPath = request()->path();
@endphp

<div class="sidebar" :class="{ 'is-open': sidebarOpen }">
    <!-- Logo -->
    <div class="sb-logo">
        <img src="{{ asset('picture/Mahidol_U_logo.png') }}" alt="Logo"
            style="width: 42px; height: 42px; object-fit: contain; flex-shrink: 0;">
        <div>
            <div class="sb-name">ระบบจัดตารางสอนฯ</div>
            <div class="sb-sub">คณะพยาบาลศาสตร์ ม.มหิดล</div>
        </div>
    </div>

    <!-- User & Role Switcher -->
    @php
        $roleTheme = [
            'admin'       => ['bg' => 'oklch(95% 0.02 240 / 0.1)', 'fg' => 'var(--brand-gold)', 'icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>'],
            'staff'       => ['bg' => 'oklch(96% 0.02 200 / 0.1)', 'fg' => 'oklch(85% 0.10 200)', 'icon' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
            'course_head' => ['bg' => 'oklch(96% 0.04 80 / 0.1)',  'fg' => 'oklch(85% 0.12 80)',  'icon' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>'],
            'executive'   => ['bg' => 'oklch(95% 0.04 290 / 0.1)', 'fg' => 'oklch(85% 0.15 290)', 'icon' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'],
            'instructor'  => ['bg' => 'oklch(96% 0.05 150 / 0.1)', 'fg' => 'oklch(85% 0.15 150)', 'icon' => '<path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>'],
        ][$activeRole] ?? ['bg' => 'rgba(255,255,255,0.1)', 'fg' => '#fff', 'icon' => ''];
    @endphp

    <div class="sb-user" x-data="{ roleMenuOpen: false }">
        <div class="sb-av" style="background: {{ $roleTheme['bg'] }}; color: {{ $roleTheme['fg'] }}; border-color: color-mix(in oklch, {{ $roleTheme['fg'] }} 40%, transparent); border-radius: 10px;">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                {!! $roleTheme['icon'] !!}
            </svg>
        </div>


        <div>
            <div class="sb-uname">{{ $user->name ?? 'Guest' }}</div>

            <div class="role-sw" @click="roleMenuOpen = !roleMenuOpen" @click.outside="roleMenuOpen = false">
                {{ $roleNames[$activeRole] ?? $activeRole }}
                <svg class="role-sw-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M6 9l6 6 6-6" />
                </svg>

                <!-- Role Dropdown Menu -->
                <div class="role-drop" :class="{ 'open': roleMenuOpen }" x-cloak @click.stop>
                    <div class="rd-hd">สลับบทบาท</div>
                    @if($roles->count() > 0)
                        @php
                            $roleIcons = [
                                'admin'       => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
                                'staff'       => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
                                'course_head' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
                                'executive'   => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
                                'instructor'  => '<path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>',
                            ];
                        @endphp
                        @foreach($roles as $r)
                            @if($r->role === $activeRole)
                                <div class="rd-item rd-active">
                                    <svg class="rd-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $roleIcons[$r->role] ?? '' !!}</svg>
                                    <span class="rd-label">{{ $roleNames[$r->role] ?? $r->role }}</span>
                                    <span class="rd-cur">✓ ใช้งานอยู่</span>
                                </div>
                            @else
                                <form method="POST" action="{{ route('switch-role') }}" style="margin:0;">
                                    @csrf
                                    <input type="hidden" name="role" value="{{ $r->role }}">
                                    <button type="submit" class="rd-item rd-switch">
                                        <svg class="rd-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $roleIcons[$r->role] ?? '' !!}</svg>
                                        <span class="rd-label">{{ $roleNames[$r->role] ?? $r->role }}</span>
                                        <span class="rd-badge">สลับ →</span>
                                    </button>
                                </form>
                            @endif
                        @endforeach
                    @else
                        <div class="rd-item rd-active" style="opacity:.5;">ไม่มีบทบาทอื่น</div>
                    @endif
                </div>
                {{-- end .role-drop --}}
            </div>
            {{-- end .role-sw --}}
        </div>
    </div>

    <!-- Navigation Menus -->
    <div class="sb-nav">

        @if($activeRole === 'staff')
            <div class="sb-sec">เมนูหลัก</div>
            <!-- Staff Menus -->
            <a href="#" class="nv {{ str_contains($currentPath, 'overview') ? 'on' : '' }}">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                </svg>
                ภาพรวม
            </a>
            <a href="{{ route('staff.settings') }}" class="nv {{ Request::routeIs('staff.settings') ? 'on' : '' }}">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                ตั้งค่าปีการศึกษา
            </a>
            <a href="{{ route('staff.master_data') }}" class="nv {{ str_contains($currentPath, 'master-data') ? 'on' : '' }}">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="3" y1="9" x2="21" y2="9"></line>
                    <line x1="9" y1="21" x2="9" y2="9"></line>
                </svg>
                จัดการข้อมูลหลัก
            </a>
            <a href="#" class="nv">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                ตารางสอน
            </a>
            <a href="#" class="nv">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                รายงาน
            </a>

        @elseif($activeRole === 'course_head')
            <div class="sb-sec">เมนูหลัก</div>
            <!-- Maker Menus -->
            <a href="#" class="nv {{ str_contains($currentPath, 'dashboard') ? 'on' : '' }}">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
                ภาพรวมและแจ้งเตือน
            </a>
            <a href="#" class="nv">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                ตารางสอน
            </a>
            <a href="#" class="nv">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                </svg>
                จัดการรายวิชา
            </a>
            <a href="#" class="nv">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                ประวัติส่งอนุมัติ
            </a>

        @elseif($activeRole === 'instructor')
            <div class="sb-sec">เมนูหลัก</div>
            <!-- Lecturer Menus -->
            <a href="#" class="nv {{ str_contains($currentPath, 'dashboard') ? 'on' : '' }}">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                ตารางสอนของฉัน
            </a>
            <a href="#" class="nv">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                ภาระงานสอน
            </a>
            <a href="#" class="nv">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                </svg>
                วิชาที่รับผิดชอบ
            </a>

        @elseif($activeRole === 'executive')
            <div class="sb-sec">เมนูหลัก</div>
            <!-- Approver Menus -->
            <a href="#" class="nv {{ str_contains($currentPath, 'dashboard') ? 'on' : '' }}">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                รออนุมัติ
            </a>
            <a href="#" class="nv">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                ตารางทั้งหมด
            </a>
            <a href="#" class="nv">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                ตีกลับ / แก้ไข
            </a>
            <a href="#" class="nv">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="20" x2="18" y2="10"></line>
                    <line x1="12" y1="20" x2="12" y2="4"></line>
                    <line x1="6" y1="20" x2="6" y2="14"></line>
                </svg>
                รายงานภาพรวม
            </a>

        @elseif($activeRole === 'admin')
            @php
                $alertSummary = \App\Http\Controllers\Admin\AlertController::getSummary();
                $alertCount   = $alertSummary['total'];
                $alertCritical = $alertSummary['critical'];
            @endphp
            <div class="sb-sec">ระบบ</div>
            <a href="{{ route('admin.dashboard') }}" class="nv {{ Request::routeIs('admin.dashboard') ? 'on' : '' }}">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
                ภาพรวมระบบ
            </a>
            <a href="{{ route('admin.users') }}" class="nv {{ Request::routeIs('admin.users') ? 'on' : '' }}">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                จัดการผู้ใช้งาน
            </a>
            <a href="{{ route('admin.master_data') }}" class="nv {{ Request::routeIs('admin.master_data') ? 'on' : '' }}">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 22h14a2 2 0 002-2V7.5L14.5 2H6a2 2 0 00-2 2v4"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <path d="M2 15h10"></path>
                    <path d="M2 18h10"></path>
                    <path d="M2 12h10"></path>
                </svg>
                ข้อมูลหลัก
            </a>
            <a href="{{ route('admin.alerts') }}" class="nv {{ Request::routeIs('admin.alerts') ? 'on' : '' }}">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
                <span>แจ้งเตือน</span>
                @if($alertCritical > 0)
                    <span class="nv-bd nv-bd-red">{{ $alertCritical }} critical</span>
                @elseif($alertCount > 0)
                    <span class="nv-bd nv-bd-soft" style="background: var(--status-warning); color: #fff;">{{ $alertCount }}</span>
                @endif
            </a>
            <a href="{{ route('admin.settings') }}" class="nv {{ Request::routeIs('admin.settings') ? 'on' : '' }}">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path
                        d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z">
                    </path>
                </svg>
                ตั้งค่าระบบ
            </a>
            <a href="#" class="nv">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                Audit Logs
            </a>

            <div class="sb-sec" style="margin-top: 15px;">จัดการตารางสอน</div>
            <a href="#" class="nv">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                ตารางสอน
            </a>
            <a href="#" class="nv">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="20" x2="18" y2="10"></line>
                    <line x1="12" y1="20" x2="12" y2="4"></line>
                    <line x1="6" y1="20" x2="6" y2="14"></line>
                </svg>
                รายงาน
            </a>
        @endif

    </div>

    <!-- Sidebar Footer -->
    <div class="sb-foot">

        <button type="button" class="nv" @click="$dispatch('open-profile-modal')">
            <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
            </svg>
            ตั้งค่าบัญชี
        </button>
        <a href="#" class="nv">
            <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                <polyline points="15 3 21 3 21 9"></polyline>
                <line x1="10" y1="14" x2="21" y2="3"></line>
            </svg>
            คู่มือการใช้งาน
        </a>
        <form method="POST" action="{{ route('logout') }}" style="margin:0">
            @csrf
            <button type="submit" class="nv">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                    <polyline points="16 17 21 12 16 7" />
                    <line x1="21" y1="12" x2="9" y2="12" />
                </svg>
                ออกจากระบบ
            </button>
        </form>
    </div>
</div>