@php
    $user = auth()->user();
    $activeRole = session('active_role', 'staff');
    $roles = $user ? $user->roles : collect();

    // Mapping internal roles to display names
    $roleNames = [
        'admin' => 'ผู้ดูแลระบบ (Admin)',
        'staff' => 'เจ้าหน้าที่ (Staff)',
        'course_head' => 'หัวหน้าวิชา (Maker)',
        'executive' => 'ผู้บริหาร (Approver)',
        'instructor' => 'อาจารย์ (Lecturer)',
    ];

    // Get current path to highlight active menu
    $currentPath = request()->path();
@endphp

<div class="sidebar">
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
    <div class="sb-user" x-data="{ roleMenuOpen: false }">
        <div class="sb-av">{{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}</div>
        <div>
            <div class="sb-uname">{{ $user->name ?? 'Guest' }}</div>

            <div class="role-sw" @click="roleMenuOpen = !roleMenuOpen" @click.outside="roleMenuOpen = false">
                {{ $roleNames[$activeRole] ?? $activeRole }}
                <svg class="role-sw-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M6 9l6 6 6-6" />
                </svg>
            </div>

            <!-- Role Dropdown Menu -->
            <div class="role-drop" :class="{ 'open': roleMenuOpen }" x-cloak>
                <div class="rd-hd">สลับบทบาท (Switch Role)</div>
                @if($roles->count() > 0)
                    @foreach($roles as $r)
                        @if($r->role === $activeRole)
                            <div class="rd-item rd-active">
                                <div class="rd-dot" style="background:var(--role)"></div>
                                {{ $roleNames[$r->role] ?? $r->role }}
                                <span class="rd-cur">ใช้งานอยู่</span>
                            </div>
                        @else
                            <form method="POST" action="{{ route('switch-role') }}" style="margin:0;">
                                @csrf
                                <input type="hidden" name="role" value="{{ $r->role }}">
                                <button type="submit" class="rd-item">
                                    <div class="rd-dot" style="background:transparent; border:1px solid oklch(50% .02 215)"></div>
                                    {{ $roleNames[$r->role] ?? $r->role }}
                                </button>
                            </form>
                        @endif
                    @endforeach
                @else
                    <div class="rd-item rd-active">ไม่มีบทบาทอื่น</div>
                @endif
            </div>
        </div>
    </div>

    <!-- Navigation Menus -->
    <div class="sb-nav">
        <div class="sb-sec">เมนูหลัก</div>

        @if($activeRole === 'staff')
            <!-- Staff Menus -->
            <a href="#" class="nv {{ str_contains($currentPath, 'overview') ? 'on' : '' }}">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                </svg>
                ภาพรวม
            </a>
            <a href="#" class="nv">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                    <path d="M2 17l10 5 10-5"></path>
                    <path d="M2 12l10 5 10-5"></path>
                </svg>
                งานวิชาการ
            </a>
            <a href="#" class="nv">
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

            <div class="sb-sec" style="margin-top: 15px;">การสื่อสาร</div>
            <a href="#" class="nv">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                    <polyline points="22,6 12,13 2,6"></polyline>
                </svg>
                กล่องข้อความ
            </a>

        @elseif($activeRole === 'course_head')
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
            <div class="sb-sec">ระบบ</div>
            <a href="#" class="nv {{ str_contains($currentPath, 'dashboard') ? 'on' : '' }}">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
                ภาพรวมระบบ
            </a>
            <a href="#" class="nv">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                จัดการผู้ใช้งาน
            </a>
            <a href="#" class="nv">
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
                    <path d="M4 22h14a2 2 0 002-2V7.5L14.5 2H6a2 2 0 00-2 2v4"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <path d="M2 15h10"></path>
                    <path d="M2 18h10"></path>
                    <path d="M2 12h10"></path>
                </svg>
                ข้อมูลหลัก
            </a>
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
        <a href="#" class="nv">
            <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
            </svg>
            ตั้งค่าบัญชี
        </a>
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