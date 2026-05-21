<x-app-layout title="บันทึกการใช้งาน">

<div class="audit-page" x-data="auditLogPage()">

{{-- Page Header --}}
<div class="page-hdr audit-page-hdr">
    <div>
        <h1 class="page-title">บันทึกการใช้งาน</h1>
        <p class="page-sub">ประวัติการดำเนินการสำคัญในระบบ</p>
    </div>
</div>

{{-- Filter Bar --}}
<div class="card audit-filter-card">
    <div class="card-hdr audit-filter-hdr">
        <div class="audit-filter-title">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
            </svg>
            <span class="card-ttl">ตัวกรอง</span>
            @if(request()->hasAny(['category','actor','action','date_from','date_to']))
                <span class="pill p-primary">กำลังกรองอยู่</span>
            @endif
        </div>
    </div>

    <div class="audit-filter-body">
        <form method="GET" action="{{ route('admin.audit_logs.index') }}"
              x-ref="filterForm"
              @submit.prevent="fetchResults()"
              class="audit-filter-grid">

            <div class="audit-filter-field">
                <label>ผู้ดำเนินการ</label>
                <input type="text" name="actor" class="form-ctrl"
                       value="{{ request('actor') }}"
                       placeholder="ค้นหาชื่อหรืออีเมล..."
                       data-testid="audit-logs-filter-actor"
                       @input.debounce.500ms="fetchResults()">
            </div>

            <div class="audit-filter-field">
                <label>หมวดหมู่</label>
                <select name="category" class="form-ctrl" data-testid="audit-logs-filter-category" @change="fetchResults()">
                    <option value="">ทุกหมวดหมู่</option>
                    @foreach($categoryLabels as $key => $label)
                        <option value="{{ $key }}" {{ request('category') === $key ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="audit-filter-field">
                <label>การกระทำ</label>
                <select name="action" class="form-ctrl" data-testid="audit-logs-filter-action" @change="fetchResults()">
                    <option value="">ทุกการกระทำ</option>
                    @foreach($actionOptions as $option)
                        <option value="{{ $option['value'] }}" {{ request('action') === $option['value'] ? 'selected' : '' }}>
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="audit-filter-field">
                <label>วันที่เริ่ม</label>
                <x-thai-date-input
                    name="date_from"
                    class="form-ctrl"
                    :value="$dateFilterValues['date_from'] ?? request('date_from')"
                    data-testid="audit-logs-filter-date-from"
                    @change="fetchResults()" />
            </div>

            <div class="audit-filter-field">
                <label>วันที่สิ้นสุด</label>
                <x-thai-date-input
                    name="date_to"
                    class="form-ctrl"
                    :value="$dateFilterValues['date_to'] ?? request('date_to')"
                    data-testid="audit-logs-filter-date-to"
                    @change="fetchResults()" />
            </div>

            <div class="audit-filter-actions">
                <button type="submit" class="btn btn-primary audit-filter-submit">
                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <span>ค้นหา</span>
                </button>
                <a href="{{ route('admin.audit_logs.index') }}" class="btn audit-filter-reset"
                   @click.prevent="resetFilters()"
                   >
                    รีเซต
                </a>
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

<style>
    .audit-page {
        padding: 28px 32px;
    }
    .audit-page-hdr {
        margin-bottom: 18px;
    }
    .audit-filter-card {
        margin-bottom: 20px;
    }
    .audit-filter-hdr {
        min-height: 48px;
    }
    .audit-filter-title {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
    }
    .audit-filter-body {
        border-top: 1px solid var(--border);
        padding: 14px 20px 16px;
    }
    .audit-filter-grid {
        display: grid;
        grid-template-columns: minmax(190px, 1.25fr) minmax(150px, 1fr) minmax(150px, 1fr) minmax(145px, .9fr) minmax(145px, .9fr) auto;
        gap: 12px;
        align-items: end;
    }
    .audit-filter-field {
        display: flex;
        flex-direction: column;
        gap: 5px;
        min-width: 0;
    }
    .audit-filter-field label {
        font-size: 11px;
        font-weight: 700;
        color: var(--fg-2);
        line-height: 1.35;
    }
    .audit-filter-field .form-ctrl {
        height: 40px;
        min-height: 40px;
        font-size: 13px;
    }
    .audit-filter-actions {
        display: grid;
        grid-template-columns: 104px 86px;
        gap: 8px;
        align-items: end;
    }
    .audit-filter-submit,
    .audit-filter-reset {
        height: 40px;
        min-height: 40px;
        padding: 0 14px;
        border-radius: var(--r-md);
        font-size: 13px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        white-space: nowrap;
        text-decoration: none;
    }
    .audit-filter-reset {
        background: var(--surface);
        border: 1px solid var(--border);
        color: var(--fg-2);
    }
    .audit-page .pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 22px;
        max-width: 100%;
        padding: 3px 9px;
        border: 1px solid var(--border);
        border-radius: var(--r-pill);
        font-size: 11px;
        font-weight: 700;
        line-height: 1.25;
        white-space: nowrap;
    }
    .audit-page .p-primary {
        background: var(--brand-navy-50);
        border-color: var(--brand-navy-100);
        color: var(--brand-navy);
    }
    .audit-page .p-success {
        background: var(--status-success-bg);
        border-color: var(--status-success-border);
        color: var(--status-success-fg);
    }
    .audit-page .p-warning,
    .audit-page .p-gold {
        background: var(--status-warning-bg);
        border-color: var(--status-warning-border);
        color: var(--status-warning-fg);
    }
    .audit-page .p-conflict {
        background: var(--status-conflict-bg);
        border-color: var(--status-conflict-border);
        color: var(--status-conflict-fg);
    }
    .audit-page .p-info,
    .audit-page .p-teal {
        background: var(--status-info-bg);
        border-color: var(--status-info-border);
        color: var(--status-info-fg);
    }
    .audit-page .p-neutral {
        background: var(--bg-2);
        border-color: var(--border);
        color: var(--fg-2);
    }
    .audit-page .p-purple {
        background: color-mix(in oklch, var(--brand-navy) 8%, var(--surface));
        border-color: color-mix(in oklch, var(--brand-navy) 22%, var(--border));
        color: var(--brand-navy-700);
    }

    @media (max-width: 1180px) {
        .audit-filter-grid {
            grid-template-columns: repeat(3, minmax(170px, 1fr));
        }
        .audit-filter-actions {
            grid-template-columns: minmax(104px, 1fr) minmax(86px, .85fr);
        }
    }
    @media (max-width: 720px) {
        .audit-page {
            padding: 20px 16px;
        }
        .audit-filter-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
</x-app-layout>
