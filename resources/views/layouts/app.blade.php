<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TPSS - {{ $title ?? 'ระบบจัดตารางสอนและฝึกปฏิบัติ' }}</title>
    
    <!-- Vite CSS -->
    @vite(['resources/css/app.css'])

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Base Styles -->
    <style>
        /* Fallback if Vite is not ready */
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-(--bg) text-(--fg-1) antialiased" x-data="{
    sidebarOpen: window.innerWidth > 1024,
    init() {
        window.addEventListener('resize', () => {
            if (window.innerWidth > 1024) this.sidebarOpen = true;
            else this.sidebarOpen = false;
        });
    }
}">
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" :class="{ 'is-open': sidebarOpen }" @click="sidebarOpen = false"></div>

    <div class="app-layout">
        <!-- Sidebar Component -->
        <x-sidebar />

        <!-- Main Content -->
        <div class="main-content">
            <!-- Topbar -->
            <div class="topbar">
                <!-- Hamburger Menu -->
                <button @click="sidebarOpen = !sidebarOpen" class="action-btn hamburger-btn" data-testid="sidebar-toggle" style="border: none; background: transparent;">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>

                <div class="tb-title">{{ $title ?? 'Dashboard' }}</div>
                
                <div class="tb-right" style="margin-left: auto;">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="tb-btn" data-testid="topbar-logout" style="border: none; background: transparent; cursor: pointer; color: var(--fg-2);">
                            ออกจากระบบ
                        </button>
                    </form>
                </div>
            </div>

            <!-- Page Content -->
            <div class="content-area">
                {{ $slot }}
            </div>
        </div>
    </div>

    <style>
        /* ─── TPSS Delete Confirm Dialog ─────────────────────────────── */
        .tpss-delete-popup {
            border-radius: 20px !important;
            padding: 0 !important;
            max-width: 400px !important;
            width: 90vw !important;
            box-shadow: 0 32px 64px rgba(15,23,42,0.20), 0 0 0 1px rgba(15,23,42,0.06) !important;
            font-family: 'IBM Plex Sans Thai', sans-serif !important;
            overflow: hidden !important;
        }
        .tpss-delete-popup .swal2-html-container {
            margin: 0 !important;
            padding: 32px 28px 0 !important;
            overflow: visible !important;
        }
        .tpss-delete-actions {
            padding: 20px 28px 24px !important;
            margin: 0 !important;
            gap: 8px !important;
            justify-content: flex-end !important;
            border-top: 1px solid #f1f5f9 !important;
            margin-top: 20px !important;
            background: #fafafa !important;
        }
        .tpss-delete-confirm {
            background: #dc2626 !important;
            color: #fff !important;
            border: none !important;
            border-radius: 9px !important;
            padding: 10px 24px !important;
            font-size: 14px !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            font-family: inherit !important;
            letter-spacing: 0.01em !important;
            transition: all 0.15s !important;
            box-shadow: 0 2px 8px rgba(220,38,38,0.30) !important;
        }
        .tpss-delete-confirm:hover { background: #b91c1c !important; box-shadow: 0 4px 12px rgba(220,38,38,0.40) !important; transform: translateY(-1px) !important; }
        .tpss-delete-confirm:focus { outline: none !important; box-shadow: 0 0 0 3px rgba(220,38,38,0.25) !important; }
        .tpss-delete-cancel {
            background: #fff !important;
            color: #475569 !important;
            border: 1.5px solid #e2e8f0 !important;
            border-radius: 9px !important;
            padding: 10px 24px !important;
            font-size: 14px !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            font-family: inherit !important;
            transition: all 0.15s !important;
        }
        .tpss-delete-cancel:hover { background: #f8fafc !important; border-color: #cbd5e1 !important; }
        .tpss-delete-cancel:focus { outline: none !important; box-shadow: 0 0 0 3px rgba(148,163,184,0.25) !important; }
        .tpss-item-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 14px;
            margin: 14px 0 0;
            text-align: left;
        }
        .tpss-item-badge-icon {
            width: 30px;
            height: 30px;
            border-radius: 7px;
            background: #fff;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .tpss-item-badge-text {
            font-size: 13.5px;
            font-weight: 600;
            color: #1e293b;
            word-break: break-word;
            line-height: 1.4;
        }
    </style>

    <x-profile-modal />

    <script>
        /* ─── tpssDelete(btn) — call via onclick="tpssDelete(this)"
           btn must have data-form="<form-id>" and data-label="<item name>"
           Optionally data-warn="<warning text>" ─────────────────────── */
        function tpssDelete(btn) {
            var formId = btn.getAttribute('data-form');
            var label  = btn.getAttribute('data-label') || '';
            var warn   = btn.getAttribute('data-warn')  || 'การดำเนินการนี้ไม่สามารถย้อนกลับได้';

            function doSubmit() { document.getElementById(formId).submit(); }

            if (typeof Swal === 'undefined') {
                if (confirm('ยืนยันการลบ?\n\n' + label + '\n' + warn)) doSubmit();
                return;
            }

            var itemHtml = label
                ? '<div class="tpss-item-badge">'
                  + '<div class="tpss-item-badge-icon">'
                  + '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="#64748b" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">'
                  + '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>'
                  + '<polyline points="14 2 14 8 20 8"/></svg></div>'
                  + '<span class="tpss-item-badge-text">' + label + '</span></div>'
                : '';

            var warnHtml = '<div style="display:flex;align-items:flex-start;gap:7px;margin-top:14px;'
                + 'padding:10px 12px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;text-align:left;">'
                + '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="#d97706" stroke-width="2.5" '
                + 'stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px;">'
                + '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>'
                + '<line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>'
                + '<span style="font-size:12.5px;color:#92400e;line-height:1.65;">' + warn + '</span></div>';

            var innerHtml = '<div style="text-align:center;">'
                + '<div style="width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,#fef2f2,#fee2e2);'
                + 'border:2px solid #fca5a5;display:flex;align-items:center;justify-content:center;'
                + 'margin:0 auto 16px;box-shadow:0 4px 16px rgba(220,38,38,0.15);">'
                + '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="#dc2626" stroke-width="2" '
                + 'stroke-linecap="round" stroke-linejoin="round">'
                + '<path d="M3 6h18"/>'
                + '<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>'
                + '<line x1="10" y1="11" x2="10" y2="17"/>'
                + '<line x1="14" y1="11" x2="14" y2="17"/></svg></div>'
                + '<div style="font-family:Kanit,sans-serif;font-size:19px;font-weight:700;color:#0f172a;line-height:1.2;">'
                + 'ยืนยันการลบข้อมูล</div>'
                + '<div style="font-size:13px;color:#94a3b8;margin-top:4px;">กรุณาตรวจสอบข้อมูลก่อนดำเนินการ</div>'
                + itemHtml + warnHtml + '</div>';

            Swal.fire({
                html: innerHtml,
                showCancelButton: true,
                confirmButtonText: 'ลบข้อมูล',
                cancelButtonText: 'ยกเลิก',
                reverseButtons: true,
                focusCancel: true,
                buttonsStyling: false,
                customClass: {
                    popup:         'tpss-delete-popup',
                    confirmButton: 'tpss-delete-confirm',
                    cancelButton:  'tpss-delete-cancel',
                    actions:       'tpss-delete-actions',
                }
            }).then(function(result) {
                if (result.isConfirmed) doSubmit();
            });
        }

        /* ─── Back-compat: Alpine methods call this ──────────────────── */
        window.tpssConfirmDelete = function(formId, label, warn) {
            var fakeBtn = { getAttribute: function(k) {
                return k === 'data-form' ? formId : k === 'data-label' ? (label || '') : (warn || '');
            }};
            tpssDelete(fakeBtn);
        };

        document.addEventListener('DOMContentLoaded', function() {
            @if(session('success'))
                Swal.fire({
                    icon: 'success',
                    title: 'สำเร็จ',
                    text: "{!! addslashes(session('success')) !!}",
                    timer: 3000,
                    showConfirmButton: false,
                    timerProgressBar: true
                });
            @endif

            @if(session('error'))
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: "{!! addslashes(session('error')) !!}",
                    confirmButtonText: 'รับทราบ',
                    confirmButtonColor: '#002d62'
                });
            @endif
        });
    </script>
    <script>
        // Force reload when restored from bfcache (browser back after logout)
        window.addEventListener('pageshow', function(e) {
            if (e.persisted) { window.location.reload(); }
        });
    </script>

    <!-- ─── Session Warning ──────────────────────────────────────────── -->
    <style>
        #session-warning-overlay {
            display: none;
            position: fixed; inset: 0; z-index: 9999;
            background: rgba(15,23,42,0.55);
            align-items: center; justify-content: center;
        }
        #session-warning-overlay.show { display: flex; }
        #session-warning-box {
            background: #fff;
            border-radius: 16px;
            width: 90vw; max-width: 400px;
            box-shadow: 0 24px 48px rgba(15,23,42,0.18), 0 0 0 1px rgba(15,23,42,0.06);
            font-family: 'IBM Plex Sans Thai', sans-serif;
            overflow: hidden;
        }
        #session-warning-header {
            background: #fffbeb;
            border-bottom: 1.5px solid #fde68a;
            padding: 20px 24px 16px;
            display: flex; align-items: flex-start; gap: 12px;
        }
        #session-warning-icon {
            width: 44px; height: 44px; border-radius: 50%;
            background: #fef3c7; border: 2px solid #fbbf24;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        #session-warning-title {
            font-family: 'Kanit', sans-serif;
            font-size: 17px; font-weight: 700; color: #0f172a;
        }
        #session-warning-sub {
            font-size: 12.5px; color: #78716c; margin-top: 2px;
        }
        #session-warning-body { padding: 20px 24px; }
        #session-warning-msg { font-size: 14px; color: #334155; line-height: 1.6; }
        #session-countdown-wrap {
            display: flex; align-items: center; justify-content: center;
            margin: 16px 0;
            gap: 8px;
        }
        #session-countdown {
            font-family: 'Kanit', sans-serif;
            font-size: 40px; font-weight: 700; color: #d97706;
            letter-spacing: -1px; line-height: 1;
        }
        #session-countdown-label { font-size: 13px; color: #78716c; }
        #session-warning-bar-wrap {
            background: #f1f5f9; border-radius: 99px; height: 5px; overflow: hidden;
        }
        #session-warning-bar {
            height: 100%; border-radius: 99px;
            background: linear-gradient(90deg, #f59e0b, #d97706);
            transition: width 1s linear;
        }
        #session-warning-footer {
            padding: 14px 24px 20px;
            display: flex; gap: 10px; justify-content: flex-end;
            border-top: 1px solid #f1f5f9;
            background: #fafafa;
        }
        #session-extend-btn {
            background: #002d62; color: #fff;
            border: none; border-radius: 8px;
            padding: 9px 20px; font-size: 13.5px; font-weight: 600;
            cursor: pointer; font-family: inherit;
            transition: background 0.15s;
        }
        #session-extend-btn:hover { background: #003d85; }
        #session-logout-btn {
            background: #fff; color: #475569;
            border: 1.5px solid #e2e8f0; border-radius: 8px;
            padding: 9px 20px; font-size: 13.5px; font-weight: 600;
            cursor: pointer; font-family: inherit;
            transition: all 0.15s;
        }
        #session-logout-btn:hover { background: #f8fafc; border-color: #cbd5e1; }
    </style>

    <div id="session-warning-overlay" role="dialog" aria-modal="true" aria-labelledby="session-warning-title">
        <div id="session-warning-box">
            <div id="session-warning-header">
                <div id="session-warning-icon">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="#d97706" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                </div>
                <div>
                    <div id="session-warning-title">เซสชันใกล้หมดอายุ</div>
                    <div id="session-warning-sub">คุณยังอยู่ในระบบอยู่หรือไม่?</div>
                </div>
            </div>
            <div id="session-warning-body">
                <div id="session-warning-msg">ระบบจะออกจากการเข้าสู่ระบบโดยอัตโนมัติใน</div>
                <div id="session-countdown-wrap">
                    <div id="session-countdown">5:00</div>
                    <div id="session-countdown-label">นาที</div>
                </div>
                <div id="session-warning-bar-wrap">
                    <div id="session-warning-bar" style="width:100%"></div>
                </div>
            </div>
            <div id="session-warning-footer">
                <button id="session-logout-btn" onclick="document.getElementById('logout-form-session').submit()">ออกจากระบบ</button>
                <button id="session-extend-btn" onclick="tpssExtendSession()">ต่ออายุเซสชัน</button>
            </div>
        </div>
    </div>
    <form id="logout-form-session" method="POST" action="{{ route('logout') }}" style="display:none">@csrf</form>

    <script>
    (function() {
        var SESSION_MS    = {{ config('session.lifetime') }} * 60 * 1000;
        var WARN_BEFORE   = 5 * 60 * 1000;    // แจ้งเตือนก่อน 5 นาที
        var WARN_DURATION = 5 * 60 * 1000;    // ระยะเวลา countdown
        var PING_URL      = '{{ route('session.ping') }}';

        var startTime  = Date.now();
        var warnShown  = false;
        var countdownInterval = null;
        var overlay   = document.getElementById('session-warning-overlay');
        var bar       = document.getElementById('session-warning-bar');
        var countdown = document.getElementById('session-countdown');

        function fmt(ms) {
            var s = Math.max(0, Math.ceil(ms / 1000));
            var m = Math.floor(s / 60);
            s = s % 60;
            return m + ':' + (s < 10 ? '0' : '') + s;
        }

        function showWarning() {
            if (warnShown) return;
            warnShown = true;
            overlay.classList.add('show');

            var deadline = startTime + SESSION_MS;
            countdownInterval = setInterval(function() {
                var left = deadline - Date.now();
                countdown.textContent = fmt(left);
                var pct = Math.max(0, Math.min(100, (left / WARN_DURATION) * 100));
                bar.style.width = pct + '%';
                bar.style.background = left < 60000
                    ? 'linear-gradient(90deg,#ef4444,#dc2626)'
                    : 'linear-gradient(90deg,#f59e0b,#d97706)';

                if (left <= 0) {
                    clearInterval(countdownInterval);
                    // Session หมด — redirect ไป login พร้อมแสดงข้อความ
                    window.location.href = '{{ route('login') }}?expired=1';
                }
            }, 1000);
        }

        // เช็คทุก 30 วินาที
        setInterval(function() {
            var elapsed = Date.now() - startTime;
            if (!warnShown && elapsed >= SESSION_MS - WARN_BEFORE) {
                showWarning();
            }
        }, 30000);

        // เช็คทันทีกรณีโหลดหน้าตอนเกือบหมด
        (function() {
            var elapsed = Date.now() - startTime;
            if (elapsed >= SESSION_MS - WARN_BEFORE) showWarning();
        })();

        window.tpssExtendSession = function() {
            fetch(PING_URL, { credentials: 'same-origin' })
                .then(function(r) {
                    if (!r.ok) throw new Error('session expired');
                    // รีเซ็ต timer
                    startTime = Date.now();
                    warnShown = false;
                    clearInterval(countdownInterval);
                    overlay.classList.remove('show');
                    bar.style.width = '100%';
                    bar.style.background = 'linear-gradient(90deg,#f59e0b,#d97706)';
                })
                .catch(function() {
                    window.location.href = '{{ route('login') }}?expired=1';
                });
        };
    })();
    </script>
</body>
</html>
