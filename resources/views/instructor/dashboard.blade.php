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
                <section class="card workload-summary" aria-label="ข้อมูลภาระงานสอน">
                    <div>
                        <div class="workload-label">รอบ PA ปัจจุบัน</div>
                        <div class="workload-round" data-testid="pa-round-name">{{ $round->name }}</div>
                        <div class="workload-muted">
                            {{ optional($round->start_date)->format('d/m/Y') }} ถึง {{ optional($round->end_date)->format('d/m/Y') }}
                        </div>
                    </div>

                    <div class="workload-metric">
                        <div class="workload-label">เกณฑ์ภาระงานสอนโดยประมาณ</div>
                        <div class="workload-quota"><span x-text="teachingQuota()"></span></div>
                        <div class="workload-muted">ชั่วโมงทำการ / {{ $periodLabel }}</div>
                    </div>

                    <div class="workload-summary-list">
                        <div>
                            <span>เกณฑ์ตำแหน่ง</span>
                            <strong>{{ $criteriaGroup ?? '-' }}</strong>
                        </div>
                        <div>
                            <span>บันทึกล่าสุด</span>
                            <strong data-testid="pa-submitted-at">{{ $allocation?->submitted_at ? $allocation->submitted_at->format('d/m/Y H:i') : 'ยังไม่เคยบันทึก' }}</strong>
                        </div>
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
            gap: clamp(14px, 2vw, 22px);
        }

        .workload-grid {
            display: grid;
            grid-template-columns: minmax(240px, 0.85fr) minmax(0, 1.45fr);
            gap: clamp(14px, 2vw, 20px);
            align-items: start;
        }

        .workload-summary,
        .workload-form,
        .workload-empty {
            padding: clamp(18px, 2vw, 24px);
            border-radius: 8px;
        }

        .workload-summary {
            display: flex;
            flex-direction: column;
            gap: 22px;
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

        .workload-label {
            margin-bottom: 5px;
            color: var(--fg-3);
            font-size: 12px;
            font-weight: 800;
        }

        .workload-round,
        .workload-empty-title {
            color: var(--brand-navy);
            font-family: var(--font-display);
            font-size: 22px;
            font-weight: 800;
            line-height: 1.25;
        }

        .workload-muted,
        .workload-empty p {
            color: var(--fg-2);
            font-size: 14px;
            line-height: 1.7;
        }

        .workload-quota {
            color: var(--brand-navy);
            font-family: var(--font-display);
            font-size: 42px;
            font-weight: 800;
            line-height: 1;
        }

        .workload-summary-list {
            display: grid;
            gap: 12px;
        }

        .workload-summary-list div {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--border);
            color: var(--fg-3);
            font-size: 13px;
        }

        .workload-summary-list strong {
            color: var(--fg-1);
            text-align: right;
        }

        .workload-form-head {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }

        .workload-form-head h2 {
            margin: 0;
            color: var(--fg-1);
            font-family: var(--font-display);
            font-size: 22px;
            font-weight: 800;
        }

        .workload-form-head p {
            margin: 4px 0 0;
            color: var(--fg-3);
            font-size: 13px;
            font-weight: 600;
        }

        .workload-total {
            min-width: 92px;
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            text-align: center;
            font-family: var(--font-display);
            font-size: 26px;
            font-weight: 800;
            line-height: 1;
        }

        .workload-total.is-complete {
            color: var(--status-success-fg);
            background: var(--status-success-bg);
        }

        .workload-total.is-invalid {
            color: var(--status-conflict-fg);
            background: var(--status-conflict-bg);
        }

        .workload-fields {
            display: grid;
            gap: 12px;
        }

        .workload-field {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 132px;
            gap: 14px;
            align-items: center;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: color-mix(in oklch, var(--surface) 88%, var(--bg-2));
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

        .workload-input {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .workload-input input {
            width: 92px;
            min-height: 42px;
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
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }

        @media (max-width: 860px) {
            .workload-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 430px) {
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

            .workload-input input {
                width: 100%;
            }
        }
    </style>
</x-app-layout>
