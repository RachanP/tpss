{{--
  Shared Dashboard Widget: Recent Activity (Audit Log)
  =====================================================
  Usage: included by a dashboard owner controller that passes $recentActivity.

  CONTRACT:
    $recentActivity — Collection of AuditLog (with 'user' eager-loaded), latest 5.

  DO NOT modify admin/dashboard.blade.php directly.
  Lead includes this partial when ready:
    @include('shared.dashboard.recent_activity')
--}}
@php
    $categoryColors = [
        'ตารางสอน'               => '#1d4ed8',
        'การอนุมัติ'              => '#15803d',
        'ข้อมูลหลัก'             => '#6b7280',
        'รายวิชาและผู้รับผิดชอบ'  => '#b45309',
        'ตั้งค่าระบบ'            => '#c2410c',
        'ผู้ใช้และสิทธิ์'         => '#7c3aed',
        'รายงาน'                 => '#0f766e',
    ];
@endphp

<div class="card" style="margin-bottom:1.5rem;" data-testid="recent-activity-widget">
    <div class="card-hdr">
        <div style="display:flex;align-items:center;gap:10px;">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
                 style="color:var(--fg-2);">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
            </svg>
            <div class="card-ttl">กิจกรรมล่าสุด</div>
        </div>
        <div class="card-actions">
            <a href="{{ route('admin.audit_logs.index') }}" class="btn btn-sm">ดูทั้งหมด</a>
        </div>
    </div>

    <div style="border-top:1px solid var(--border);">
        @forelse($recentActivity as $log)
        @php
            $dotColor = $categoryColors[$log->category] ?? '#6b7280';
            $beYear   = $log->created_at->year + 543;
            $timeStr  = $log->created_at->format('d/m/') . $beYear . ' ' . $log->created_at->format('H:i');
        @endphp
        <div style="display:flex;align-items:flex-start;gap:12px;padding:10px 20px;border-bottom:1px solid var(--border);">
            {{-- Category dot --}}
            <div style="width:8px;height:8px;border-radius:50%;background:{{ $dotColor }};flex-shrink:0;margin-top:5px;"></div>

            <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <span style="font-size:13px;font-weight:600;color:var(--fg-1);">
                        {{ $log->user?->name ?? 'ระบบ' }}
                    </span>
                    @if($log->category)
                        <span style="font-size:10px;font-weight:600;color:{{ $dotColor }};background:color-mix(in srgb,{{ $dotColor }} 10%,white);border-radius:3px;padding:1px 6px;">
                            {{ $log->category }}
                        </span>
                    @endif
                </div>
                <div style="font-size:12px;color:var(--fg-2);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    {{ $log->description ?? $log->action }}
                </div>
            </div>

            <div style="font-size:11px;color:var(--fg-3);white-space:nowrap;flex-shrink:0;">
                {{ $timeStr }}
            </div>
        </div>
        @empty
        <div style="padding:24px;text-align:center;color:var(--fg-3);font-size:13px;">
            ยังไม่มีบันทึกกิจกรรม
        </div>
        @endforelse
    </div>
</div>
