@php
    $recentAuditLogs = isset($recentAuditLogs)
        ? collect($recentAuditLogs)->take(5)
        : \App\Models\AuditLog::query()
            ->with('user')
            ->orderedForAudit()
            ->limit(5)
            ->get();

    // Category → pill class (matches audit-logs/_table.blade.php $categoryColors)
    $categoryColors = [
        'ตารางสอน'               => 'p-primary',
        'การอนุมัติ'              => 'p-success',
        'ข้อมูลหลัก'             => 'p-neutral',
        'รายวิชาและผู้รับผิดชอบ'  => 'p-gold',
        'ตั้งค่าระบบ'            => 'p-warning',
        'ผู้ใช้และสิทธิ์'         => 'p-purple',
        'รายงาน'                 => 'p-teal',
        'ระบบ'                   => 'p-neutral',
    ];

    // Action tone → pill class + label (matches audit-logs/_table.blade.php $actionToneFor)
    $actionToneFor = function (?string $action): array {
        $action = (string) $action;
        if (str_contains($action, 'ลบ') || str_contains($action, 'ปิดใช้งาน') || str_contains($action, 'ปฏิเสธ')) {
            return ['label' => 'สำคัญ', 'class' => 'p-conflict'];
        }
        if (str_contains($action, 'สร้าง') || str_contains($action, 'เปิดช่วงจัดตาราง') || str_contains($action, 'อนุมัติ')) {
            return ['label' => 'ปกติ', 'class' => 'p-success'];
        }
        if (str_contains($action, 'ปิดช่วงจัดตาราง') || str_contains($action, 'ซิงก์ข้อมูล')) {
            return ['label' => 'ติดตาม', 'class' => 'p-warning'];
        }
        return ['label' => 'ติดตาม', 'class' => 'p-info'];
    };

    // Dot color driven by action tone class (CSS variables not oklch literals)
    $dotByClass = [
        'p-success'  => 'var(--status-success-fg)',
        'p-warning'  => 'var(--status-warning-fg)',
        'p-conflict' => 'var(--status-conflict-fg)',
        'p-info'     => 'var(--status-info-fg)',
    ];

    $displayCategoryFor = function ($category) {
        $category = trim((string) $category);
        return preg_match('/^M\d+(\.|$)/i', $category) ? 'ระบบ' : ($category ?: 'ระบบ');
    };

    $displayActionFor = function ($action) {
        $action = trim((string) $action);
        if ($action === '') {
            return 'ดำเนินการ';
        }
        if (preg_match('/^M\d+$/i', $action)) {
            return 'ดำเนินการ';
        }
        $parts = explode('.', $action);
        $label = trim((string) end($parts));
        if (preg_match('/^M\d+$/i', $label)) {
            return 'ดำเนินการ';
        }
        return $label !== '' ? $label : 'ดำเนินการ';
    };
@endphp

<div class="card" data-testid="recent-activity-widget">
    <div class="card-hdr">
        <div style="display:flex;align-items:center;gap:10px;min-width:0;">
            <span class="dash-card-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></span>
            <div class="card-ttl" role="heading" aria-level="2">กิจกรรมล่าสุด</div>
        </div>

        <a href="{{ route('admin.audit_logs.index') }}" class="btn btn-sm ra-view-all">ดูทั้งหมด</a>
    </div>

    <div style="border-top:1px solid var(--border);">
        @forelse($recentAuditLogs as $log)
            @php
                $category   = $displayCategoryFor($log->category);
                $actionVerb = $displayActionFor($log->action);
                $catClass   = $categoryColors[$category] ?? 'p-neutral';
                $actionTone = $actionToneFor($log->action);
                $dotColor   = $dotByClass[$actionTone['class']] ?? 'var(--fg-3)';
                $createdAt  = $log->created_at;
                $timeText   = $createdAt ? \App\Support\ThaiDate::dateTime($createdAt) : '-';
            @endphp

            <div data-testid="recent-activity-row"
                 style="display:flex;gap:12px;align-items:flex-start;padding:12px 20px;border-bottom:1px solid var(--border);">
                <div aria-hidden="true"
                     style="width:9px;height:9px;border-radius:999px;background:{{ $dotColor }};margin-top:7px;flex-shrink:0;"></div>

                <div style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px;">
                        <span class="pill {{ $catClass }}" data-testid="recent-activity-category">
                            {{ $category }}
                        </span>
                        <span class="pill {{ $actionTone['class'] }}" data-testid="recent-activity-action">
                            {{ $actionVerb }}
                        </span>
                        <span class="pill p-neutral">
                            {{ $actionTone['label'] }}
                        </span>
                    </div>

                    <div style="font-size:13px;font-weight:600;color:var(--fg-1);line-height:1.55;overflow:hidden;text-overflow:ellipsis;">
                        {{ $log->description ?: $actionVerb }}
                    </div>

                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:3px;font-size:12px;color:var(--fg-3);line-height:1.55;">
                        <span>{{ $log->user?->name ?? 'ระบบ' }}</span>
                        <span aria-hidden="true" style="color:var(--border);">•</span>
                        <time datetime="{{ $createdAt?->toIso8601String() }}">{{ $timeText }}</time>
                    </div>
                </div>
            </div>
        @empty
            <div style="padding:28px 20px;text-align:center;color:var(--fg-3);font-size:13px;line-height:1.55;">
                ยังไม่มีกิจกรรมล่าสุด
            </div>
        @endforelse
    </div>
</div>

<style>
    [data-testid="recent-activity-widget"] {
        overflow: hidden;
        border: 1px solid color-mix(in oklch, var(--brand-navy) 24%, var(--border));
        background:
            linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 7%, var(--surface)), var(--surface) 46%),
            var(--surface);
        box-shadow:
            0 1px 2px rgba(0, 36, 84, 0.09),
            0 18px 38px -30px rgba(0, 36, 84, 0.42);
    }

    [data-testid="recent-activity-widget"] .card-hdr {
        flex-wrap: wrap;
        gap: 10px 14px;
        background:
            linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 9%, var(--surface)), transparent 72%),
            color-mix(in oklch, var(--brand-navy) 4%, var(--surface));
        border-bottom-color: color-mix(in oklch, var(--brand-navy) 18%, var(--border));
    }

    [data-testid="recent-activity-widget"] > div:not(.card-hdr) {
        background: var(--surface);
    }

    [data-testid="recent-activity-widget"] [data-testid="recent-activity-row"] {
        min-width: 0;
        border-bottom-color: color-mix(in oklch, var(--brand-navy) 14%, var(--border)) !important;
        background: var(--surface);
        transition: background 160ms ease;
    }

    [data-testid="recent-activity-widget"] [data-testid="recent-activity-row"]:nth-child(even) {
        background: color-mix(in oklch, var(--brand-navy) 2.5%, var(--surface));
    }

    [data-testid="recent-activity-widget"] [data-testid="recent-activity-row"]:hover {
        background: color-mix(in oklch, var(--brand-navy) 6%, var(--surface));
        box-shadow: inset 0 0 0 1px color-mix(in oklch, var(--brand-navy) 16%, transparent);
    }

    @media (max-width: 540px) {
        [data-testid="recent-activity-widget"] .ra-view-all {
            width: 100%;
            justify-content: center;
        }
    }
</style>
