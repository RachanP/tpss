<x-app-layout title="ภาระงานสอน">
    @php
        $initialValues = [
            'teaching_pct' => (int) old('teaching_pct', $allocation?->teaching_pct ?? $profile?->teaching_pct ?? 0),
            'research_pct' => (int) old('research_pct', $allocation?->research_pct ?? $profile?->research_pct ?? 0),
            'service_pct' => (int) old('service_pct', $allocation?->service_pct ?? $profile?->service_pct ?? 0),
            'culture_pct' => (int) old('culture_pct', $allocation?->culture_pct ?? $profile?->culture_pct ?? 0),
            'other_pct' => (int) old('other_pct', $allocation?->other_pct ?? $profile?->other_pct ?? 0),
        ];
        $fieldLabels = [
            'teaching_pct' => ['title' => 'ด้านการสอน', 'key' => 't'],
            'research_pct' => ['title' => 'ด้านวิจัย', 'key' => 'r'],
            'service_pct' => ['title' => 'บริการวิชาการ', 'key' => 's'],
            'culture_pct' => ['title' => 'ศิลปวัฒนธรรม', 'key' => 'c'],
            'other_pct' => ['title' => 'งานอื่นๆ ที่ได้รับมอบหมาย', 'key' => 'o'],
        ];
        $formatRule = function ($rule) {
            if (! is_array($rule)) return '-';
            $min = (int) ($rule['min'] ?? 0);
            $max = (int) ($rule['max'] ?? 100);
            if ($min === 0 && $max === 0) return '0%';
            if ($min === 0) return "ไม่เกิน {$max}%";
            return "{$min}-{$max}%";
        };
    @endphp

    <div class="workload-page" x-data="{
        values: @js($initialValues),
        quotaBase: @js((float) ($quotaBase ?? 0)),
        total() {
            return Object.values(this.values).reduce((sum, value) => sum + (parseInt(value, 10) || 0), 0);
        },
        teachingQuota() {
            return Math.round((this.quotaBase * (parseInt(this.values.teaching_pct, 10) || 0)) / 100);
        }
    }">
        @include('shared.dashboard.role_header', [
            'kicker' => 'อาจารย์ / ภาระงานสอน',
            'title'  => 'ภาระงานสอน',
            'desc'   => 'กรอกสัดส่วน PA และดูเกณฑ์ภาระงานสอนของคุณ',
        ])

        @if(session('success'))
            <div class="workload-alert workload-alert-success" data-testid="pa-success-message">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="workload-alert workload-alert-error" data-testid="pa-error-message">
                <strong>ยังบันทึกไม่ได้</strong>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        @if(! $profile)
            <div class="card workload-empty" data-testid="pa-profile-empty">
                <div class="workload-empty-title">ยังไม่มีข้อมูลโปรไฟล์อาจารย์</div>
                <p>กรุณาให้ผู้ดูแลระบบบันทึกข้อมูลพื้นฐานก่อนกรอกสัดส่วน PA</p>
            </div>
        @elseif(! $academicYear || ! $round)
            <div class="card workload-empty" data-testid="pa-round-empty">
                <div class="workload-empty-title">ยังไม่มีรอบ PA ที่พร้อมใช้งาน</div>
                <p>ต้องมีปีการศึกษาในระบบก่อนจึงจะกรอกสัดส่วน PA ได้</p>
            </div>
        @else
            <div class="workload-grid">
                <section class="card workload-side-stats" aria-label="สรุปภาระงานสอน">
                    <div class="workload-side-head">
                        <div>
                            <h2>สรุป PA</h2>
                            <p>รอบปัจจุบันและสถานะการบันทึก</p>
                        </div>
                    </div>

                    <div class="workload-stat is-primary">
                        <div class="workload-stat-label">รอบ PA ปัจจุบัน</div>
                        <div class="workload-stat-value workload-stat-text">{{ $round->name }}</div>
                        <div class="workload-stat-sub">{{ optional($round->start_date)->format('d/m/Y') }} ถึง {{ optional($round->end_date)->format('d/m/Y') }}</div>
                    </div>
                    <div class="workload-stat">
                        <div class="workload-stat-label">เกณฑ์ชั่วโมงสอน</div>
                        <div class="workload-stat-value"><span x-text="teachingQuota()"></span><span>ชม.</span></div>
                        <div class="workload-stat-sub">ชั่วโมงทำการ / {{ $periodLabel }}</div>
                    </div>
                    <div class="workload-stat">
                        <div class="workload-stat-label">สัดส่วน PA รวม</div>
                        <div class="workload-stat-value" :class="{ 'is-complete': total() === 100, 'is-invalid': total() !== 100 }">
                            <span x-text="total()"></span><span>%</span>
                        </div>
                        <div class="workload-stat-sub" x-text="total() === 100 ? 'พร้อมบันทึกข้อมูล' : 'ต้องรวมให้ครบ 100%'"></div>
                    </div>
                    <div class="workload-stat">
                        <div class="workload-stat-label">บันทึกล่าสุด</div>
                        <div class="workload-stat-value workload-stat-text" data-testid="pa-submitted-at">{{ $allocation?->submitted_at ? $allocation->submitted_at->format('d/m/Y H:i') : 'ยังไม่เคยบันทึก' }}</div>
                        <div class="workload-stat-sub">เกณฑ์ตำแหน่ง: {{ $criteriaGroup ?? '-' }}</div>
                    </div>
                </section>

                <form method="POST" action="{{ route('lecturer.pa.update') }}" class="card workload-form" data-testid="pa-form">
                    @csrf
                    @method('PUT')

                    <div class="workload-form-head">
                        <div>
                            <h2>สัดส่วน PA</h2>
                            <p>กรอกเปอร์เซ็นต์รวมให้ครบ 100%</p>
                        </div>
                        <div class="workload-total" :class="{ 'is-complete': total() === 100, 'is-invalid': total() !== 100 }" data-testid="pa-total">
                            <span x-text="total()"></span>%
                        </div>
                    </div>

                    <div class="workload-fields">
                        @foreach($fieldLabels as $field => $meta)
                            @php $rule = $paRules[$meta['key']] ?? null; @endphp
                            <label class="workload-field">
                                <span>
                                    <strong>{{ $meta['title'] }}</strong>
                                    <small>เกณฑ์ {{ $formatRule($rule) }}</small>
                                </span>
                                <input
                                    type="range"
                                    class="workload-range"
                                    min="0"
                                    max="100"
                                    x-model.number="values.{{ $field }}"
                                    aria-label="{{ $meta['title'] }}"
                                    :style="{ '--value': Math.min(100, Math.max(0, values.{{ $field }} || 0)) + '%' }"
                                >
                                <span class="workload-input">
                                    <input
                                        type="number"
                                        name="{{ $field }}"
                                        min="0"
                                        max="100"
                                        required
                                        x-model.number="values.{{ $field }}"
                                        data-testid="pa-{{ str_replace('_', '-', $field) }}"
                                    >
                                    <em>%</em>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <div class="workload-actions">
                        <button type="submit" class="btn btn-primary" data-testid="pa-submit">บันทึก PA</button>
                    </div>
                </form>
            </div>
        @endif
    </div>

    <style>
        .workload-page {
            display: flex;
            flex-direction: column;
            gap: 18px;
            padding: clamp(14px, 2vw, 24px);
            background:
                radial-gradient(circle at 8% 0%, color-mix(in oklch, var(--brand-navy) 10%, transparent), transparent 30%),
                linear-gradient(180deg,
                    color-mix(in oklch, var(--brand-navy) 7%, var(--bg)) 0%,
                    color-mix(in oklch, var(--brand-navy) 4%, var(--bg)) 34%,
                    var(--bg) 100%);
        }

        .workload-side-stats {
            display: flex;
            flex-direction: column;
            gap: 12px;
            height: 100%;
            box-sizing: border-box;
            padding: 20px;
        }

        .workload-side-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding-bottom: 6px;
        }

        .workload-side-head h2 {
            margin: 0;
            color: var(--fg-1);
            font-family: var(--font-display);
            font-size: 21px;
            font-weight: 800;
            line-height: 1.2;
        }

        .workload-side-head p {
            margin: 4px 0 0;
            color: var(--fg-3);
            font-size: 13px;
            font-weight: 600;
        }

        .workload-stat {
            min-width: 0;
            padding: 16px 18px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 18%, var(--border));
            border-radius: 8px;
            background:
                radial-gradient(circle at 10% 0%, color-mix(in oklch, var(--brand-navy) 6%, transparent), transparent 32%),
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 3.5%, var(--surface)), var(--surface) 66%);
        }

        .workload-stat.is-primary {
            border-color: color-mix(in oklch, var(--brand-navy) 22%, var(--border));
            background:
                radial-gradient(circle at 10% 0%, color-mix(in oklch, var(--brand-navy) 8%, transparent), transparent 32%),
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 5%, var(--surface)), var(--surface) 66%);
        }

        .workload-stat-label,
        .workload-label {
            margin-bottom: 6px;
            color: var(--fg-3);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .02em;
        }

        .workload-stat-value {
            display: flex;
            align-items: baseline;
            gap: 4px;
            min-height: 26px;
            color: var(--fg-1);
            font-family: var(--font-display);
            font-size: 24px;
            font-weight: 850;
            line-height: 1;
            white-space: nowrap;
        }

        .workload-stat-value span:last-child {
            color: var(--fg-3);
            font-size: 12px;
            font-weight: 800;
        }

        .workload-stat-value.is-complete {
            color: var(--status-success-fg);
        }

        .workload-stat-value.is-invalid {
            color: var(--status-conflict-fg);
        }

        .workload-stat-text {
            display: block;
            overflow: hidden;
            font-size: 18px;
            text-overflow: ellipsis;
        }

        .workload-stat-sub {
            margin-top: 6px;
            overflow: hidden;
            color: var(--fg-3);
            font-size: 12px;
            font-weight: 700;
            line-height: 1.45;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .workload-grid {
            display: grid;
            grid-template-columns: minmax(260px, 0.56fr) minmax(0, 1.44fr);
            grid-auto-rows: 1fr;
            gap: 18px;
            align-items: stretch;
        }

        .workload-side-stats,
        .workload-form,
        .workload-empty {
            border: 1px solid color-mix(in oklch, var(--brand-navy) 22%, var(--border));
            border-radius: 10px;
            background:
                radial-gradient(circle at 12% 0%, color-mix(in oklch, var(--brand-navy) 9%, transparent), transparent 34%),
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 4.5%, var(--surface)), var(--surface) 44%),
                var(--surface);
            box-shadow:
                0 1px 2px rgba(0, 36, 84, 0.08),
                0 12px 28px -24px rgba(0, 36, 84, 0.34);
        }

        .workload-summary {
            display: flex;
            flex-direction: column;
            gap: 16px;
            min-height: 274px;
            padding: 20px 22px 22px;
            border-color: color-mix(in oklch, var(--brand-navy) 24%, var(--border));
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 7%, var(--surface)), var(--surface) 46%),
                var(--surface);
            box-shadow:
                0 1px 2px rgba(0, 36, 84, 0.09),
                0 16px 34px -22px rgba(0, 36, 84, 0.42),
                inset 0 1px 0 rgba(255, 255, 255, 0.7);
        }

        .workload-form,
        .workload-empty {
            height: 100%;
            box-sizing: border-box;
            padding: 20px;
        }

        .workload-form {
            display: flex;
            flex-direction: column;
        }

        .workload-form-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }

        .workload-alert {
            display: flex;
            gap: 10px;
            align-items: center;
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
        }

        .workload-alert-success {
            color: var(--status-success-fg);
            background: var(--status-success-bg);
            border: 1px solid color-mix(in oklch, var(--status-success-fg) 24%, transparent);
        }

        .workload-alert-error {
            color: var(--status-conflict-fg);
            background: var(--status-conflict-bg);
            border: 1px solid color-mix(in oklch, var(--status-conflict-fg) 24%, transparent);
        }

        .workload-round,
        .workload-empty-title {
            color: var(--brand-navy);
            font-family: var(--font-display);
            font-size: 20px;
            font-weight: 800;
            line-height: 1.25;
        }

        .workload-muted,
        .workload-empty p {
            color: var(--fg-2);
            font-size: 14px;
            line-height: 1.7;
        }

        .workload-visual-heading {
            display: block;
        }

        .workload-visual-title {
            margin-bottom: 4px;
            color: color-mix(in oklch, var(--brand-navy) 78%, var(--fg-2));
            font-size: 13px;
            font-weight: 800;
            line-height: 1.35;
        }

        .workload-visual-subtitle {
            color: color-mix(in oklch, var(--brand-navy) 38%, var(--fg-3));
            font-size: 11.5px;
            font-weight: 700;
            line-height: 1.45;
        }

        .workload-visual-body {
            display: grid;
            grid-template-columns: minmax(148px, 0.92fr) minmax(0, 1.08fr);
            align-items: center;
            gap: 18px;
            min-height: 186px;
        }

        .workload-donut-stage {
            width: min(100%, 220px);
            aspect-ratio: 1;
            display: grid;
            place-items: center;
            justify-self: center;
            border-radius: 50%;
            background:
                radial-gradient(circle at 50% 50%, rgba(255, 255, 255, 0.9) 0 42%, transparent 43%),
                radial-gradient(circle at 44% 36%, rgba(255, 255, 255, 0.98), color-mix(in oklch, var(--brand-navy) 9%, var(--surface)) 64%, transparent 65%);
            box-shadow:
                0 20px 38px -24px rgba(0, 36, 84, 0.58),
                inset 0 1px 0 rgba(255, 255, 255, 0.85);
        }

        .workload-donut {
            width: min(166px, 84%);
            aspect-ratio: 1;
            display: grid;
            place-items: center;
            border-radius: 50%;
            filter: drop-shadow(0 8px 14px rgba(0, 36, 84, 0.12));
        }

        .workload-donut-center {
            width: 58%;
            aspect-ratio: 1;
            display: grid;
            place-items: center;
            align-content: center;
            border-radius: 50%;
            background: var(--surface);
            text-align: center;
        }

        .workload-donut-center strong {
            color: var(--fg-1);
            font-family: var(--font-display);
            font-size: 31px;
            font-weight: 850;
            line-height: 1;
        }

        .workload-donut.is-complete .workload-donut-center strong {
            color: var(--status-success-fg);
        }

        .workload-donut.is-invalid .workload-donut-center strong {
            color: var(--brand-navy);
        }

        .workload-donut-center span {
            margin-top: 4px;
            color: var(--fg-3);
            font-size: 11px;
            font-weight: 700;
            line-height: 1.2;
        }

        .workload-legend {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 0;
        }

        .workload-legend-row {
            display: grid;
            grid-template-columns: 14px minmax(0, 1fr) auto;
            align-items: center;
            gap: 9px;
            min-height: 38px;
            padding: 8px 9px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 18%, var(--border));
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.62);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.62);
        }

        .workload-legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 4px;
            background: var(--brand-navy);
            box-shadow: inset 0 0 0 1px rgba(0, 36, 84, 0.12);
        }

        .workload-legend-row:nth-child(2) .workload-legend-dot {
            background: color-mix(in oklch, var(--brand-navy) 72%, var(--status-info-fg));
        }

        .workload-legend-row:nth-child(3) .workload-legend-dot {
            background: var(--status-info-fg);
        }

        .workload-legend-row:nth-child(4) .workload-legend-dot {
            background: var(--status-success-fg);
        }

        .workload-legend-row:nth-child(5) .workload-legend-dot {
            background: var(--status-warning-fg);
        }

        .workload-legend-row span:nth-child(2) {
            overflow: hidden;
            color: var(--fg-2);
            font-size: 12.5px;
            font-weight: 700;
            line-height: 1.35;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .workload-legend-row strong {
            color: var(--fg-1);
            font-family: var(--font-display);
            font-size: 13.5px;
            font-weight: 850;
            font-variant-numeric: tabular-nums;
        }

        .workload-quota-panel {
            padding: 14px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 13%, var(--border));
            border-radius: 9px;
            background: color-mix(in oklch, var(--brand-navy) 5%, var(--surface));
        }

        .workload-quota {
            display: flex;
            align-items: baseline;
            gap: 6px;
            color: var(--brand-navy);
            font-family: var(--font-display);
            font-size: 42px;
            font-weight: 800;
            line-height: 1;
        }

        .workload-quota span:last-child {
            color: var(--fg-3);
            font-size: 15px;
            font-weight: 800;
        }

        .workload-summary-list {
            display: grid;
            gap: 0;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 18%, var(--border));
            border-radius: 9px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.56);
        }

        .workload-summary-list div {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 11px 12px;
            border-bottom: 1px solid var(--border);
            color: var(--fg-3);
            font-size: 13px;
        }

        .workload-summary-list div:last-child {
            border-bottom: 0;
        }

        .workload-summary-list strong {
            color: var(--fg-1);
            text-align: right;
        }

        .workload-form-head h2 {
            margin: 0;
            color: var(--fg-1);
            font-family: var(--font-display);
            font-size: 21px;
            font-weight: 800;
        }

        .workload-form-head p {
            margin: 4px 0 0;
            color: var(--fg-3);
            font-size: 13px;
            font-weight: 600;
        }

        .workload-total,
        .workload-status-pill {
            min-width: 88px;
            padding: 9px 13px;
            border: 1px solid var(--border);
            border-radius: 8px;
            text-align: center;
            font-family: var(--font-display);
            font-size: 24px;
            font-weight: 800;
            line-height: 1;
        }

        .workload-status-pill {
            min-width: 68px;
            font-size: 20px;
        }

        .workload-total.is-complete,
        .workload-status-pill.is-complete {
            color: var(--status-success-fg);
            background: var(--status-success-bg);
            border-color: color-mix(in oklch, var(--status-success-fg) 24%, var(--border));
        }

        .workload-total.is-invalid,
        .workload-status-pill.is-invalid {
            color: var(--status-conflict-fg);
            background: var(--status-conflict-bg);
            border-color: color-mix(in oklch, var(--status-conflict-fg) 24%, var(--border));
        }

        .workload-fields {
            display: grid;
            gap: 10px;
            margin-top: 18px;
        }

        .workload-field {
            display: grid;
            grid-template-columns: minmax(190px, 1fr) minmax(160px, 0.74fr) 124px;
            gap: 16px;
            align-items: center;
            padding: 12px 14px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 12%, var(--border));
            border-radius: 9px;
            background: color-mix(in oklch, var(--brand-navy) 3%, var(--surface));
            transition: border-color 150ms ease, background 150ms ease;
        }

        .workload-field:focus-within {
            border-color: var(--brand-navy);
            background: var(--surface);
        }

        .workload-field strong,
        .workload-field small {
            display: block;
        }

        .workload-field strong {
            color: var(--fg-1);
            font-size: 14px;
        }

        .workload-field small {
            margin-top: 3px;
            color: var(--fg-3);
            font-size: 12px;
            font-weight: 700;
        }

        .workload-range {
            --value: 0%;
            width: 100%;
            height: 20px;
            appearance: none;
            background: transparent;
            cursor: pointer;
        }

        .workload-range:focus {
            outline: none;
        }

        .workload-range::-webkit-slider-runnable-track {
            height: 6px;
            border-radius: 999px;
            background: linear-gradient(
                90deg,
                var(--brand-navy) 0 var(--value),
                color-mix(in oklch, var(--brand-navy) 10%, var(--bg)) var(--value) 100%
            );
        }

        .workload-range::-webkit-slider-thumb {
            width: 18px;
            height: 18px;
            margin-top: -6px;
            appearance: none;
            border: 3px solid var(--surface);
            border-radius: 999px;
            background: var(--brand-navy);
            box-shadow: 0 2px 6px rgba(0, 36, 84, 0.24);
        }

        .workload-range::-moz-range-track {
            height: 6px;
            border-radius: 999px;
            background: color-mix(in oklch, var(--brand-navy) 10%, var(--bg));
        }

        .workload-range::-moz-range-progress {
            height: 6px;
            border-radius: 999px;
            background: var(--brand-navy);
        }

        .workload-range::-moz-range-thumb {
            width: 14px;
            height: 14px;
            border: 3px solid var(--surface);
            border-radius: 999px;
            background: var(--brand-navy);
            box-shadow: 0 2px 6px rgba(0, 36, 84, 0.24);
        }

        .workload-range:focus-visible::-webkit-slider-thumb {
            box-shadow:
                0 2px 6px rgba(0, 36, 84, 0.24),
                0 0 0 4px color-mix(in oklch, var(--brand-navy) 16%, transparent);
        }

        .workload-range:focus-visible::-moz-range-thumb {
            box-shadow:
                0 2px 6px rgba(0, 36, 84, 0.24),
                0 0 0 4px color-mix(in oklch, var(--brand-navy) 16%, transparent);
        }

        .workload-input {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .workload-input input {
            width: 86px;
            min-height: 40px;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 8px 10px;
            color: var(--fg-1);
            font-size: 16px;
            font-weight: 800;
            text-align: right;
        }

        .workload-input input:focus {
            outline: none;
            border-color: var(--brand-navy);
            box-shadow: 0 0 0 3px color-mix(in oklch, var(--brand-navy) 14%, transparent);
        }

        .workload-input em {
            color: var(--fg-3);
            font-style: normal;
            font-weight: 800;
        }

        .workload-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: auto;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }

        .workload-actions .btn {
            min-height: 42px;
            padding-inline: 18px;
            font-weight: 800;
        }

        @media (max-width: 1120px) {
            .workload-grid {
                grid-template-columns: minmax(230px, 0.64fr) minmax(0, 1.36fr);
            }
        }

        @media (max-width: 860px) {
            .workload-grid {
                grid-template-columns: 1fr;
                grid-auto-rows: auto;
            }

            .workload-side-stats,
            .workload-form {
                height: auto;
            }
        }

        @media (max-width: 640px) {
            .workload-page {
                padding: 12px;
            }

            .workload-form-head,
            .workload-actions {
                align-items: stretch;
                flex-direction: column;
            }

            .workload-total {
                width: 100%;
            }

            .workload-field {
                grid-template-columns: 1fr;
            }

            .workload-range {
                width: 100%;
            }

            .workload-input input {
                width: 100%;
            }
        }
    </style>
</x-app-layout>
