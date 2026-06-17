{{--
  Audit Log Detail Modal — JSON Viewer
  Included inside the x-data="auditDetailModal()" component on index.blade.php.
  Opens when a row's detail button dispatches openModal(payload).
--}}

{{-- Backdrop --}}
<div
    x-show="open"
    x-cloak
    x-transition:enter="transition ease-out duration-150"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-100"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    @click="close()"
    style="position:fixed;inset:0 0 0 var(--sidebar-w,0px);background:rgba(0,0,0,.45);z-index:1000;"
    data-testid="audit-detail-modal-backdrop">
</div>

{{-- Modal Panel --}}
<div
    x-show="open"
    x-cloak
    x-transition:enter="transition ease-out duration-150"
    x-transition:enter-start="opacity-0 translate-y-2"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-100"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    data-testid="audit-detail-modal"
    @keydown.escape.window="close()"
    style="
        position:fixed;
        top:50%;left:calc(var(--sidebar-w,0px) + ((100vw - var(--sidebar-w,0px)) / 2));
        transform:translate(-50%,-50%);
        z-index:1001;
        width:min(780px,calc(100vw - var(--sidebar-w,0px) - 32px));
        max-width:calc(100vw - var(--sidebar-w,0px) - 32px);
        max-height:90vh;
        display:flex;
        flex-direction:column;
        overflow:hidden;
        background:var(--bg-1,#fff);
        border:1.5px solid var(--border);
        border-radius:6px;
        box-shadow:0 8px 32px rgba(0,0,0,.18);
    ">

    {{-- Header --}}
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <h2 style="font-size:14px;font-weight:700;margin:0;color:var(--fg-1);">
                JSON รายละเอียดบันทึก
                <span style="color:var(--fg-3);font-weight:400;" x-text="activeLog ? ' #' + activeLog.id : ''"></span>
            </h2>
            <template x-if="activeLog?.category">
                <span class="pill p-neutral" style="font-size:11px;" x-text="activeLog.category"></span>
            </template>
            <template x-if="activeLog?.action">
                <span class="pill p-primary" style="font-size:11px;" x-text="activeLog.action"></span>
            </template>
        </div>
        <button type="button" @click="close()"
                style="background:none;border:none;cursor:pointer;color:var(--fg-3);padding:4px;line-height:1;">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
    </div>

    {{-- Body --}}
    <div style="flex:1;min-height:0;min-width:0;display:flex;flex-direction:column;overflow:hidden;padding:16px 20px;">

        {{-- Parse error fallback --}}
        <template x-if="jsonError">
            <div style="padding:20px;text-align:center;color:var(--status-conflict-fg,#c0392b);">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"
                     style="display:block;margin:0 auto 8px;">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
                <p style="font-size:13px;font-weight:600;margin:0;">ไม่สามารถแสดง JSON ได้</p>
            </div>
        </template>

        {{-- JSON Code Block --}}
        <template x-if="!jsonError">
            <div style="max-height:calc(90vh - 180px);max-width:100%;min-width:0;overflow-y:auto;overflow-x:auto;border-radius:12px;">
                <pre
                    data-testid="audit-json-block"
                    x-text="formattedJson"
                    style="
                        margin:0;
                        padding:16px;
                        background:var(--bg-2,#f8f9fa);
                        border:1px solid var(--border);
                        border-radius:4px;
                        font-family:'IBM Plex Mono',ui-monospace,monospace;
                        font-size:12px;
                        line-height:1.65;
                        color:var(--fg-1);
                        white-space:pre-wrap;
                        overflow-wrap:anywhere;
                        word-break:break-word;
                        min-width:0;
                        tab-size:2;
                    "
                ></pre>
            </div>
        </template>
    </div>

    {{-- Footer --}}
    <div style="padding:12px 20px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:8px;flex-shrink:0;">
        <button
            type="button"
            class="btn btn-sm"
            data-testid="audit-copy-btn"
            @click="copyJson()"
            style="display:flex;align-items:center;gap:6px;">
            <template x-if="!copied">
                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                </svg>
            </template>
            <template x-if="copied">
                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
            </template>
            <span x-text="copied ? 'คัดลอกแล้ว' : 'คัดลอก JSON'"></span>
        </button>

        <button type="button" class="btn" @click="close()">ปิด</button>
    </div>
</div>
