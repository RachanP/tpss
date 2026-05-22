@php
    $recentAuditLogs = isset($recentAuditLogs)
        ? collect($recentAuditLogs)->take(5)
        : \App\Models\AuditLog::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

    $categoryStyles = [
        'ตารางสอน' => ['bg' => 'oklch(96% 0.025 250)', 'fg' => 'oklch(38% 0.13 250)', 'dot' => 'oklch(48% 0.16 250)'],
        'การอนุมัติ' => ['bg' => 'oklch(96% 0.03 150)', 'fg' => 'oklch(36% 0.12 150)', 'dot' => 'oklch(45% 0.14 150)'],
        'ข้อมูลหลัก' => ['bg' => 'oklch(96% 0.008 220)', 'fg' => 'oklch(36% 0.03 235)', 'dot' => 'oklch(50% 0.04 235)'],
        'รายวิชาและผู้รับผิดชอบ' => ['bg' => 'oklch(96% 0.035 85)', 'fg' => 'oklch(38% 0.10 85)', 'dot' => 'oklch(52% 0.13 85)'],
        'ตั้งค่าระบบ' => ['bg' => 'oklch(96% 0.035 55)', 'fg' => 'oklch(40% 0.12 55)', 'dot' => 'oklch(54% 0.14 55)'],
        'ผู้ใช้และสิทธิ์' => ['bg' => 'oklch(96% 0.025 295)', 'fg' => 'oklch(39% 0.13 295)', 'dot' => 'oklch(50% 0.15 295)'],
        'รายงาน' => ['bg' => 'oklch(96% 0.028 190)', 'fg' => 'oklch(36% 0.11 190)', 'dot' => 'oklch(48% 0.13 190)'],
    ];

    $severityFor = function ($log) {
        $text = trim(($log->action ?? '') . ' ' . ($log->description ?? ''));

        if (str_contains($text, 'ลบ') || str_contains($text, 'ปิดใช้งาน') || str_contains($text, 'ปฏิเสธ')) {
            return ['label' => 'สำคัญ', 'fg' => 'oklch(42% 0.15 35)', 'bg' => 'oklch(96% 0.035 35)', 'dot' => 'oklch(55% 0.16 35)'];
        }

        if (str_contains($text, 'อนุมัติ') || str_contains($text, 'สร้าง') || str_contains($text, 'เปิด')) {
            return ['label' => 'ปกติ', 'fg' => 'oklch(35% 0.12 150)', 'bg' => 'oklch(96% 0.03 150)', 'dot' => 'oklch(45% 0.14 150)'];
        }

        return ['label' => 'ติดตาม', 'fg' => 'oklch(38% 0.10 250)', 'bg' => 'oklch(96% 0.025 250)', 'dot' => 'oklch(48% 0.14 250)'];
    };

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
            <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" style="color:var(--fg-2);flex-shrink:0;">
                <path d="M12 8v5l3 2"/>
                <circle cx="12" cy="12" r="9"/>
            </svg>
            <div class="card-ttl">กิจกรรมล่าสุด</div>
        </div>

        <a href="{{ route('admin.audit_logs.index') }}" class="btn btn-sm">ดูทั้งหมด</a>
    </div>

    <div style="border-top:1px solid var(--border);">
        @forelse($recentAuditLogs as $log)
            @php
                $category = $displayCategoryFor($log->category);
                $action = $displayActionFor($log->action);
                $categoryStyle = $categoryStyles[$category] ?? ['bg' => 'oklch(96% 0.008 220)', 'fg' => 'oklch(36% 0.03 235)', 'dot' => 'oklch(50% 0.04 235)'];
                $severity = $severityFor($log);
                $createdAt = $log->created_at?->copy();
                $timeText = $createdAt
                    ? $createdAt->format('d/m/') . ($createdAt->year + 543) . ' ' . $createdAt->format('H:i')
                    : '-';
            @endphp

            <div data-testid="recent-activity-row"
                 style="display:flex;gap:12px;align-items:flex-start;padding:12px 20px;border-bottom:1px solid var(--border);">
                <div aria-hidden="true"
                     style="width:9px;height:9px;border-radius:999px;background:{{ $severity['dot'] }};margin-top:7px;flex-shrink:0;"></div>

                <div style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px;">
                        <span class="pill" data-testid="recent-activity-category"
                              style="font-size:11px;background:{{ $categoryStyle['bg'] }};color:{{ $categoryStyle['fg'] }};">
                            {{ $category }}
                        </span>
                        <span class="pill" data-testid="recent-activity-action"
                              style="font-size:11px;background:oklch(97% 0.008 220);color:var(--fg-2);">
                            {{ $action }}
                        </span>
                        <span class="pill"
                              style="font-size:11px;background:{{ $severity['bg'] }};color:{{ $severity['fg'] }};">
                            {{ $severity['label'] }}
                        </span>
                    </div>

                    <div style="font-size:13px;font-weight:600;color:var(--fg-1);line-height:1.55;overflow:hidden;text-overflow:ellipsis;">
                        {{ $log->description ?: $action }}
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
