@props(['title' => 'TPSS'])

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TPSS — {{ $title }}</title>

    <!-- Google Fonts: IBM Plex Sans Thai (UI) + Kanit (Headings) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700&family=Kanit:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Design System CSS -->
    @vite(['resources/css/app.css'])

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }

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
        .tpss-warn-confirm {
            background: #d97706 !important;
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
            box-shadow: 0 2px 8px rgba(217,119,6,0.30) !important;
        }
        .tpss-warn-confirm:hover { background: #b45309 !important; box-shadow: 0 4px 12px rgba(217,119,6,0.40) !important; transform: translateY(-1px) !important; }
        .tpss-warn-confirm:focus { outline: none !important; box-shadow: 0 0 0 3px rgba(217,119,6,0.25) !important; }
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
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar -->
        @include('components.sidebar')

        <!-- Main -->
        <div class="main-content">
            <!-- Topbar -->
            <div class="topbar">
                <span class="tb-title">{{ $title }}</span>
            </div>

            <!-- Content -->
            <div class="content-area">
                {{ $slot }}
            </div>
        </div>
    </div>

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

        /* ─── tpssToast(message, type) — slide-down notification bar ── */
        function tpssToast(message, type) {
            var existing = document.getElementById('tpss-toast');
            if (existing) existing.remove();

            var isSuccess = type !== 'error';
            var bg      = isSuccess ? '#f0fdf4' : '#fef2f2';
            var border  = isSuccess ? '#86efac' : '#fca5a5';
            var iconClr = isSuccess ? '#16a34a' : '#dc2626';
            var textClr = isSuccess ? '#14532d' : '#7f1d1d';
            var labelClr= isSuccess ? '#15803d' : '#b91c1c';
            var label   = isSuccess ? 'สำเร็จ' : 'เกิดข้อผิดพลาด';

            var iconSvg = isSuccess
                ? '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="' + iconClr + '" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
                : '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="' + iconClr + '" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';

            var toast = document.createElement('div');
            toast.id = 'tpss-toast';
            toast.innerHTML =
                '<div style="display:flex;align-items:center;gap:12px;flex:1;min-width:0;">'
                + '<div style="flex-shrink:0;width:36px;height:36px;border-radius:50%;background:' + (isSuccess ? '#dcfce7' : '#fee2e2') + ';display:flex;align-items:center;justify-content:center;">'
                + iconSvg + '</div>'
                + '<div style="min-width:0;">'
                + '<div style="font-size:13px;font-weight:700;color:' + labelClr + ';line-height:1.2;">' + label + '</div>'
                + '<div style="font-size:13px;color:' + textClr + ';line-height:1.5;margin-top:1px;word-break:break-word;">' + message + '</div>'
                + '</div></div>'
                + '<button onclick="document.getElementById(\'tpss-toast\').remove()" style="flex-shrink:0;background:transparent;border:none;cursor:pointer;padding:4px;border-radius:6px;color:' + iconClr + ';opacity:0.6;margin-left:8px;" title="ปิด">'
                + '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'
                + '</button>'
                + '<div id="tpss-toast-bar" style="position:absolute;bottom:0;left:0;height:3px;border-radius:0 0 12px 12px;background:' + iconClr + ';width:100%;transition:width linear;"></div>';

            Object.assign(toast.style, {
                position: 'fixed',
                top: '20px',
                right: '20px',
                zIndex: '99999',
                background: bg,
                border: '1.5px solid ' + border,
                borderRadius: '14px',
                padding: '14px 16px',
                display: 'flex',
                alignItems: 'center',
                gap: '0',
                maxWidth: '420px',
                width: 'calc(100vw - 40px)',
                boxShadow: '0 8px 32px rgba(15,23,42,0.14), 0 2px 8px rgba(15,23,42,0.08)',
                fontFamily: 'IBM Plex Sans Thai, sans-serif',
                transform: 'translateY(-80px)',
                opacity: '0',
                transition: 'transform 0.35s cubic-bezier(0.34,1.56,0.64,1), opacity 0.25s ease',
                overflow: 'hidden',
            });

            document.body.appendChild(toast);

            requestAnimationFrame(function() {
                requestAnimationFrame(function() {
                    toast.style.transform = 'translateY(0)';
                    toast.style.opacity = '1';
                });
            });

            if (isSuccess) {
                var duration = 4000;
                var bar = document.getElementById('tpss-toast-bar');
                if (bar) {
                    bar.style.transitionDuration = duration + 'ms';
                    requestAnimationFrame(function() {
                        requestAnimationFrame(function() { bar.style.width = '0%'; });
                    });
                }
                setTimeout(function() {
                    if (!toast.parentNode) return;
                    toast.style.transform = 'translateY(-80px)';
                    toast.style.opacity = '0';
                    setTimeout(function() { if (toast.parentNode) toast.remove(); }, 350);
                }, duration);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            @if(session('success'))
                tpssToast(@json(session('success')), 'success');
            @endif

            @if(session('error'))
                tpssToast(@json(session('error')), 'error');
            @endif
        });
    </script>
</body>
</html>
