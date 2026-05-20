<x-app-layout title="บันทึกการใช้งาน">

<div style="padding: 2rem;" x-data="auditLogPage()">

{{-- Page Header --}}
<div class="page-hdr">
    <div>
        <h1 class="page-title">บันทึกการใช้งาน</h1>
        <p class="page-sub">ประวัติการดำเนินการสำคัญในระบบ</p>
    </div>
</div>

{{-- Filter Bar --}}
<div class="card" style="margin-bottom: 1.25rem;">
    <div class="card-hdr">
        <div style="display:flex;align-items:center;gap:10px;">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
            </svg>
            <span class="card-ttl">ตัวกรอง</span>
            @if(request()->hasAny(['category','actor','action','date_from','date_to']))
                <span class="pill p-primary" style="font-size:11px;">กำลังกรองอยู่</span>
            @endif
        </div>
    </div>

    <div style="border-top:1px solid var(--border); padding:16px 20px;">
        <form method="GET" action="{{ route('admin.audit_logs.index') }}"
              x-ref="filterForm"
              @submit.prevent="fetchResults()"
              style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;align-items:end;">

            <div style="display:flex;flex-direction:column;gap:4px;min-width:0;">
                <label style="font-size:11px;font-weight:600;color:var(--fg-2);">ผู้ดำเนินการ</label>
                <input type="text" name="actor" class="form-ctrl"
                       value="{{ request('actor') }}"
                       placeholder="ค้นหาชื่อหรืออีเมล..."
                       data-testid="audit-logs-filter-actor"
                       @input.debounce.500ms="fetchResults()"
                       style="font-size:13px;">
            </div>

            <div style="display:flex;flex-direction:column;gap:4px;min-width:0;">
                <label style="font-size:11px;font-weight:600;color:var(--fg-2);">หมวดหมู่</label>
                <select name="category" class="form-ctrl" data-testid="audit-logs-filter-category" @change="fetchResults()" style="font-size:13px;">
                    <option value="">ทุกหมวดหมู่</option>
                    @foreach($categoryLabels as $key => $label)
                        <option value="{{ $key }}" {{ request('category') === $key ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="display:flex;flex-direction:column;gap:4px;min-width:0;">
                <label style="font-size:11px;font-weight:600;color:var(--fg-2);">การกระทำ</label>
                <select name="action" class="form-ctrl" data-testid="audit-logs-filter-action" @change="fetchResults()" style="font-size:13px;">
                    <option value="">ทุกการกระทำ</option>
                    @foreach($actionOptions as $option)
                        <option value="{{ $option['value'] }}" {{ request('action') === $option['value'] ? 'selected' : '' }}>
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="display:flex;flex-direction:column;gap:4px;min-width:0;">
                <label style="font-size:11px;font-weight:600;color:var(--fg-2);">วันที่เริ่ม</label>
                <input type="date" name="date_from" class="form-ctrl"
                       value="{{ request('date_from') }}"
                       data-testid="audit-logs-filter-date-from"
                       @change="fetchResults()"
                       style="font-size:13px;">
            </div>

            <div style="display:flex;flex-direction:column;gap:4px;min-width:0;">
                <label style="font-size:11px;font-weight:600;color:var(--fg-2);">วันที่สิ้นสุด</label>
                <input type="date" name="date_to" class="form-ctrl"
                       value="{{ request('date_to') }}"
                       data-testid="audit-logs-filter-date-to"
                       @change="fetchResults()"
                       style="font-size:13px;">
            </div>

            <div style="display:flex;flex-direction:column;gap:4px;min-width:164px;">
                <span aria-hidden="true" style="font-size:11px;font-weight:600;line-height:1.55;visibility:hidden;">คำสั่ง</span>
                <div style="display:grid;grid-template-columns:minmax(82px,1fr) minmax(72px,.85fr);gap:8px;align-items:stretch;">
                <button type="submit" class="btn btn-primary" style="font-size:13px;display:inline-flex;align-items:center;justify-content:center;gap:6px;height:44px;min-height:44px;padding:0 16px;border-radius:8px;white-space:nowrap;">
                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <span>ค้นหา</span>
                </button>
                <a href="{{ route('admin.audit_logs.index') }}" class="btn"
                   @click.prevent="resetFilters()"
                   style="font-size:13px;display:inline-flex;align-items:center;justify-content:center;height:44px;min-height:44px;padding:0 16px;background:var(--bg-1,#fff);border:1px solid #cbd5e1;border-radius:8px;color:var(--fg-2);text-decoration:none;white-space:nowrap;">
                    รีเซต
                </a>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Table --}}
<div class="card" style="position:relative;">
    <div x-show="loading" x-cloak style="position:absolute;inset:0;background:rgba(255,255,255,.55);z-index:5;pointer-events:none;"></div>
    <div x-ref="results" id="audit-log-results" @click="handleResultsClick($event)" :style="loading ? 'opacity:.55;' : ''">
        @include('admin.audit_logs._table')
    </div>

    {{-- Detail Modal (inline) --}}
    @include('admin.audit_logs._detail_modal')
</div>

<script>
function auditLogPage() {
    return {
        loading: false,
        open: false,
        activeLog: null,
        jsonError: false,
        copied: false,

        baseUrl: @json(route('admin.audit_logs.index')),

        filteredUrl(partial = false) {
            const formData = new FormData(this.$refs.filterForm);
            const params = new URLSearchParams();

            for (const [key, value] of formData.entries()) {
                if (String(value).trim() !== '') {
                    params.set(key, value);
                }
            }

            if (partial) {
                params.set('partial', 'table');
            }

            const query = params.toString();
            return query ? `${this.baseUrl}?${query}` : this.baseUrl;
        },

        async fetchResults() {
            await this.fetchUrl(this.filteredUrl(false));
        },

        async fetchUrl(url) {
            const browserUrl = new URL(url, window.location.origin);
            browserUrl.searchParams.delete('partial');
            const partialUrl = new URL(browserUrl.toString());
            partialUrl.searchParams.set('partial', 'table');

            this.loading = true;
            try {
                const response = await fetch(partialUrl.toString(), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'text/html',
                    },
                });

                if (!response.ok) {
                    throw new Error('Unable to load audit logs');
                }

                this.$refs.results.innerHTML = await response.text();
                if (window.Alpine && typeof window.Alpine.initTree === 'function') {
                    window.Alpine.initTree(this.$refs.results);
                }
                window.history.replaceState({}, '', browserUrl.toString());
            } catch (e) {
                window.location.href = browserUrl.toString();
            } finally {
                this.loading = false;
            }
        },

        resetFilters() {
            this.$refs.filterForm.querySelectorAll('input, select').forEach((field) => {
                field.value = '';
            });
            this.fetchUrl(this.baseUrl);
        },

        handleResultsClick(event) {
            const link = event.target.closest('a[href]');
            if (!link || !this.$refs.results.contains(link)) return;

            event.preventDefault();
            this.fetchUrl(link.href);
        },

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
