@php
    $categoryColors = [
        'ตารางสอน'               => 'p-primary',
        'การอนุมัติ'              => 'p-success',
        'ข้อมูลหลัก'             => 'p-neutral',
        'รายวิชาและผู้รับผิดชอบ'  => 'p-gold',
        'ตั้งค่าระบบ'            => 'p-warning',
        'ผู้ใช้และสิทธิ์'         => 'p-purple',
        'รายงาน'                 => 'p-teal',
    ];
@endphp

@if($logs->isEmpty())
    <div style="padding:60px 20px;text-align:center;color:var(--fg-3);">
        <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5"
             style="opacity:.3;margin-bottom:12px;display:block;margin-left:auto;margin-right:auto;">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
            <line x1="16" y1="13" x2="8" y2="13"/>
            <line x1="16" y1="17" x2="8" y2="17"/>
        </svg>
        <p style="font-size:14px;font-weight:600;margin:0 0 4px;">ไม่พบบันทึก</p>
        <p style="font-size:12px;margin:0;">ลองปรับตัวกรองหรือช่วงเวลา</p>
    </div>
@else
    <div style="overflow-x:auto;">
        <table class="data-table" data-testid="audit-logs-table">
            <thead>
                <tr>
                    <th style="width:60px;">#</th>
                    <th style="width:150px;">เวลา</th>
                    <th style="width:160px;">ผู้ดำเนินการ</th>
                    <th style="width:150px;">หมวดหมู่</th>
                    <th>การกระทำ</th>
                    <th style="width:120px;">IP</th>
                    <th style="width:60px;text-align:center;">รายละเอียด</th>
                </tr>
            </thead>
            <tbody>
                @foreach($logs as $log)
                    @php
                        $catClass = $categoryColors[$log->category] ?? 'p-neutral';
                        $beYear   = ($log->created_at->year + 543);
                        $dateStr  = $log->created_at->format('d/m/') . $beYear . ' ' . $log->created_at->format('H:i');
                        $ipAddress = data_get($log->new_values, 'context.ip_address');
                    @endphp
                    <tr data-testid="audit-logs-row" data-log-id="{{ $log->id }}">
                        <td style="font-size:11px;color:var(--fg-3);font-variant-numeric:tabular-nums;">
                            {{ $log->id }}
                        </td>
                        <td style="font-size:12px;white-space:nowrap;color:var(--fg-2);">
                            {{ $dateStr }}
                        </td>
                        <td style="font-size:13px;">
                            {{ $log->user?->name ?? '—' }}
                        </td>
                        <td>
                            @if($log->category)
                                <span class="pill {{ $catClass }}" style="font-size:11px;">
                                    {{ $log->category }}
                                </span>
                            @else
                                <span style="color:var(--fg-3);font-size:12px;">—</span>
                            @endif
                        </td>
                        <td style="font-size:13px;">
                            <div style="font-weight:600;">{{ $log->action }}</div>
                            @if($log->description)
                                <div style="font-size:11px;color:var(--fg-3);margin-top:2px;">{{ $log->description }}</div>
                            @endif
                        </td>
                        <td style="font-size:12px;color:var(--fg-2);white-space:nowrap;font-family:monospace;">
                            {{ $ipAddress ?: '-' }}
                        </td>
                        <td style="text-align:center;">
                            <button
                                type="button"
                                class="btn btn-sm"
                                style="padding:4px 8px;"
                                data-testid="audit-detail-btn-{{ $log->id }}"
                                @click="openModal({{ Js::from($log->toDetailPayload()) }})"
                                title="ดู JSON รายละเอียด">
                                <svg viewBox="0 0 24 24" width="13" height="13" fill="none"
                                     stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"/>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                </svg>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:12px 20px;border-top:1px solid var(--border);">
        <span style="font-size:12px;color:var(--fg-3);">แสดง {{ $logs->firstItem() ?? 0 }}–{{ $logs->lastItem() ?? 0 }} จาก {{ number_format($logs->total()) }} รายการ</span>

        @if($logs->hasPages())
            @php
                $paginationWindow = \Illuminate\Pagination\UrlWindow::make($logs);
                $paginationElements = array_values(array_filter([
                    $paginationWindow['first'] ?? null,
                    is_array($paginationWindow['slider'] ?? null) ? '...' : null,
                    $paginationWindow['slider'] ?? null,
                    is_array($paginationWindow['last'] ?? null) ? '...' : null,
                    $paginationWindow['last'] ?? null,
                ], fn ($element) => $element !== null && $element !== []));
            @endphp
            <nav aria-label="Pagination" data-testid="audit-logs-pagination" style="display:flex;align-items:center;gap:4px;flex-wrap:wrap;">
                @if($logs->onFirstPage())
                    <span style="display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;border:1px solid var(--border);border-radius:6px;color:var(--fg-3);opacity:.55;">‹</span>
                @else
                    <a href="{{ $logs->previousPageUrl() }}" rel="prev" style="display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;border:1px solid var(--border);border-radius:6px;color:var(--fg-2);text-decoration:none;">‹</a>
                @endif

                @foreach($paginationElements as $element)
                    @if(is_string($element))
                        <span style="display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;color:var(--fg-3);">{{ $element }}</span>
                    @endif

                    @if(is_array($element))
                        @foreach($element as $page => $url)
                            @if($page == $logs->currentPage())
                                <span aria-current="page" style="display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;border:1px solid var(--fg-1);border-radius:6px;background:var(--fg-1);color:#fff;font-size:13px;">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" style="display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;border:1px solid var(--border);border-radius:6px;color:var(--fg-2);text-decoration:none;font-size:13px;">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if($logs->hasMorePages())
                    <a href="{{ $logs->nextPageUrl() }}" rel="next" style="display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;border:1px solid var(--border);border-radius:6px;color:var(--fg-2);text-decoration:none;">›</a>
                @else
                    <span style="display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;border:1px solid var(--border);border-radius:6px;color:var(--fg-3);opacity:.55;">›</span>
                @endif
            </nav>
        @endif
    </div>
@endif
