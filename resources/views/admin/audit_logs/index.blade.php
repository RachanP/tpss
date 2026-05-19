<x-app-layout title="บันทึกการใช้งาน">

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

<div style="padding: 2rem;">

{{-- Page Header --}}
<div class="page-hdr">
    <div>
        <h1 class="page-title">บันทึกการใช้งาน</h1>
        <p class="page-sub">ประวัติการดำเนินการสำคัญในระบบ</p>
    </div>
</div>

{{-- Filter Bar --}}
<div class="card" style="margin-bottom: 1.25rem;" x-data="{ filtersOpen: {{ request()->hasAny(['category','user_id','action','date_from','date_to']) ? 'true' : 'false' }} }">
    <div class="card-hdr" style="cursor:pointer;" @click="filtersOpen = !filtersOpen">
        <div style="display:flex;align-items:center;gap:10px;">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
            </svg>
            <span class="card-ttl">ตัวกรอง</span>
            @if(request()->hasAny(['category','user_id','action','date_from','date_to']))
                <span class="pill p-primary" style="font-size:11px;">กำลังกรองอยู่</span>
            @endif
        </div>
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
             :style="filtersOpen ? 'transform:rotate(180deg)' : ''">
            <path d="M6 9l6 6 6-6"/>
        </svg>
    </div>

    <div x-show="filtersOpen" x-cloak style="border-top:1px solid var(--border); padding:16px 20px;">
        <form method="GET" action="{{ route('admin.audit_logs.index') }}"
              style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">

            <div style="display:flex;flex-direction:column;gap:4px;min-width:160px;">
                <label style="font-size:11px;font-weight:600;color:var(--fg-2);">หมวดหมู่</label>
                <select name="category" class="form-ctrl" data-testid="audit-logs-filter-category" style="font-size:13px;">
                    <option value="">ทุกหมวดหมู่</option>
                    @foreach($categoryLabels as $key => $label)
                        <option value="{{ $key }}" {{ request('category') === $key ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="display:flex;flex-direction:column;gap:4px;min-width:180px;">
                <label style="font-size:11px;font-weight:600;color:var(--fg-2);">ผู้ดำเนินการ</label>
                <select name="user_id" class="form-ctrl" data-testid="audit-logs-filter-user" style="font-size:13px;">
                    <option value="">ทุกคน</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>
                            {{ $u->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="display:flex;flex-direction:column;gap:4px;min-width:180px;">
                <label style="font-size:11px;font-weight:600;color:var(--fg-2);">การกระทำ</label>
                <input type="text" name="action" class="form-ctrl"
                       value="{{ request('action') }}"
                       placeholder="ค้นหาการกระทำ..."
                       style="font-size:13px;">
            </div>

            <div style="display:flex;flex-direction:column;gap:4px;">
                <label style="font-size:11px;font-weight:600;color:var(--fg-2);">วันที่เริ่ม</label>
                <input type="date" name="date_from" class="form-ctrl"
                       value="{{ request('date_from') }}"
                       data-testid="audit-logs-filter-date-from"
                       style="font-size:13px;">
            </div>

            <div style="display:flex;flex-direction:column;gap:4px;">
                <label style="font-size:11px;font-weight:600;color:var(--fg-2);">วันที่สิ้นสุด</label>
                <input type="date" name="date_to" class="form-ctrl"
                       value="{{ request('date_to') }}"
                       data-testid="audit-logs-filter-date-to"
                       style="font-size:13px;">
            </div>

            <div style="display:flex;gap:8px;align-items:flex-end;">
                <button type="submit" class="btn btn-primary" style="font-size:13px;">กรอง</button>
                @if(request()->hasAny(['category','user_id','action','date_from','date_to']))
                    <a href="{{ route('admin.audit_logs.index') }}" class="btn" style="font-size:13px;">ล้างตัวกรอง</a>
                @endif
            </div>
        </form>
    </div>
</div>

{{-- Results Summary --}}
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
    <span style="font-size:12px;color:var(--fg-3);">
        แสดง {{ $logs->firstItem() ?? 0 }}–{{ $logs->lastItem() ?? 0 }}
        จาก {{ number_format($logs->total()) }} รายการ
    </span>
</div>

{{-- Table --}}
<div class="card" x-data="auditDetailModal()">

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
                        <th style="width:120px;">ตาราง / รายการ</th>
                        <th style="width:60px;text-align:center;">รายละเอียด</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($logs as $log)
                    @php
                        $catClass = $categoryColors[$log->category] ?? 'p-neutral';
                        $beYear   = ($log->created_at->year + 543);
                        $dateStr  = $log->created_at->format('d/m/') . $beYear . ' ' . $log->created_at->format('H:i');
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
                        <td style="font-size:11px;color:var(--fg-2);">
                            <span style="font-family:monospace;">{{ $log->table_affected }}</span>
                            <span style="color:var(--fg-3);"> #{{ $log->record_id }}</span>
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

        {{-- Pagination --}}
        @if($logs->hasPages())
            <div style="padding:12px 20px;border-top:1px solid var(--border);">
                {{ $logs->links() }}
            </div>
        @endif
    @endif

    {{-- Detail Modal (inline) --}}
    @include('admin.audit_logs._detail_modal')
</div>

<script>
function auditDetailModal() {
    return {
        open: false,
        activeLog: null,
        jsonError: false,
        copied: false,

        get formattedJson() {
            if (!this.activeLog) return '';
            try {
                return JSON.stringify(this.activeLog, null, 2);
            } catch (e) {
                this.jsonError = true;
                return '';
            }
        },

        openModal(payload) {
            this.jsonError = false;
            this.copied    = false;
            try {
                this.activeLog = (typeof payload === 'string') ? JSON.parse(payload) : payload;
            } catch (e) {
                this.jsonError = true;
                this.activeLog = null;
            }
            this.open = true;
        },

        close() {
            this.open      = false;
            this.activeLog = null;
            this.copied    = false;
        },

        async copyJson() {
            try {
                await navigator.clipboard.writeText(this.formattedJson);
                this.copied = true;
                setTimeout(() => { this.copied = false; }, 2000);
            } catch (e) {
                // clipboard API unavailable (non-HTTPS, etc.)
            }
        },
    };
}
</script>
</div>
</x-app-layout>
