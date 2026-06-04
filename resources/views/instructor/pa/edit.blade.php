<x-app-layout title="กรอกสัดส่วน PA">
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

    <div class="pa-page" x-data="{
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
            'kicker' => 'อาจารย์ / PA',
            'title'  => 'กรอกสัดส่วนภาระงาน PA',
            'desc'   => 'บันทึกสัดส่วนภาระงานของคุณสำหรับรอบปัจจุบัน',
        ])

        @if(session('success'))
            <div class="pa-alert pa-alert-success" data-testid="pa-success-message">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="pa-alert pa-alert-error" data-testid="pa-error-message">
                <strong>ยังบันทึกไม่ได้</strong>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        @if(! $profile)
            <div class="card pa-empty" data-testid="pa-profile-empty">
                <div class="pa-empty-title">ยังไม่มีข้อมูลโปรไฟล์อาจารย์</div>
                <p>กรุณาให้ผู้ดูแลระบบบันทึกข้อมูลพื้นฐาน เช่น ตำแหน่งทางวิชาการ ภาควิชา และประเภทการจ้างงาน ก่อนกรอก PA</p>
            </div>
        @elseif(! $academicYear || ! $round)
            <div class="card pa-empty" data-testid="pa-round-empty">
                <div class="pa-empty-title">ยังไม่มีรอบ PA ที่พร้อมใช้งาน</div>
                <p>ต้องมีปีการศึกษาในระบบก่อนจึงจะกรอกสัดส่วน PA ได้</p>
            </div>
        @else
            <div class="pa-shell">
                <section class="card pa-summary-card" aria-label="ข้อมูลรอบ PA">
                    <div>
                        <div class="pa-label">รอบ PA ปัจจุบัน</div>
                        <div class="pa-round-name" data-testid="pa-round-name">{{ $round->name }}</div>
                        <div class="pa-round-date">
                            {{ optional($round->start_date)->format('d/m/Y') }} ถึง {{ optional($round->end_date)->format('d/m/Y') }}
                        </div>
                    </div>

                    <div class="pa-summary-grid">
                        <div>
                            <div class="pa-label">เกณฑ์ตำแหน่ง</div>
                            <div class="pa-summary-value">{{ $criteriaGroup ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="pa-label">รอบคำนวณภาระสอน</div>
                            <div class="pa-summary-value">{{ $periodLabel }}</div>
                        </div>
                        <div>
                            <div class="pa-label">บันทึกล่าสุด</div>
                            <div class="pa-summary-value" data-testid="pa-submitted-at">
                                {{ $allocation?->submitted_at ? $allocation->submitted_at->format('d/m/Y H:i') : 'ยังไม่เคยบันทึก' }}
                            </div>
                        </div>
                    </div>
                </section>

                <form method="POST" action="{{ route('instructor.pa.update') }}" class="card pa-form-card" data-testid="pa-form">
                    @csrf
                    @method('PUT')

                    <div class="pa-form-head">
                        <div>
                            <h2>สัดส่วนภาระงาน</h2>
                            <p>กรอกเป็นเปอร์เซ็นต์รวม 100%</p>
                        </div>
                        <div class="pa-total" :class="{ 'is-complete': total() === 100, 'is-invalid': total() !== 100 }" data-testid="pa-total">
                            <span x-text="total()"></span>%
                        </div>
                    </div>

                    <div class="pa-fields">
                        @foreach($fieldLabels as $field => $meta)
                            @php $rule = $paRules[$meta['key']] ?? null; @endphp
                            <label class="pa-field">
                                <span>
                                    <strong>{{ $meta['title'] }}</strong>
                                    <small>เกณฑ์ {{ $formatRule($rule) }}</small>
                                </span>
                                <span class="pa-input-wrap">
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

                    <div class="pa-quota-row">
                        <div>
                            <div class="pa-label">เกณฑ์ภาระงานสอนโดยประมาณ</div>
                            <div class="pa-quota-value"><span x-text="teachingQuota()"></span> ชั่วโมง / {{ $periodLabel }}</div>
                        </div>
                        <button type="submit" class="btn btn-primary" data-testid="pa-submit">
                            บันทึก PA
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </div>

    <style>
        .pa-page {
            display: flex;
            flex-direction: column;
            gap: clamp(14px, 2vw, 22px);
        }

        .pa-alert {
            display: flex;
            gap: 10px;
            align-items: center;
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
        }

        .pa-alert-success {
            color: var(--status-success-fg);
            background: var(--status-success-bg);
            border: 1px solid color-mix(in oklch, var(--status-success-fg) 24%, transparent);
        }

        .pa-alert-error {
            color: var(--status-conflict-fg);
            background: var(--status-conflict-bg);
            border: 1px solid color-mix(in oklch, var(--status-conflict-fg) 24%, transparent);
        }

        .pa-alert span {
            font-weight: 600;
        }

        .pa-shell {
            display: grid;
            grid-template-columns: minmax(240px, 0.86fr) minmax(0, 1.4fr);
            gap: clamp(14px, 2vw, 20px);
            align-items: start;
        }

        .pa-summary-card,
        .pa-form-card,
        .pa-empty {
            padding: clamp(18px, 2vw, 24px);
            border-radius: 8px;
        }

        .pa-summary-card {
            display: flex;
            flex-direction: column;
            gap: 22px;
        }

        .pa-label {
            margin-bottom: 5px;
            color: var(--fg-3);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.01em;
        }

        .pa-round-name {
            color: var(--brand-navy);
            font-family: var(--font-display);
            font-size: 22px;
            font-weight: 800;
            line-height: 1.25;
        }

        .pa-round-date,
        .pa-summary-value {
            color: var(--fg-2);
            font-size: 14px;
            font-weight: 700;
        }

        .pa-summary-grid {
            display: grid;
            gap: 16px;
        }

        .pa-form-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }

        .pa-form-head h2 {
            margin: 0;
            color: var(--fg-1);
            font-family: var(--font-display);
            font-size: 22px;
            font-weight: 800;
        }

        .pa-form-head p {
            margin: 4px 0 0;
            color: var(--fg-3);
            font-size: 13px;
            font-weight: 600;
        }

        .pa-total {
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

        .pa-total.is-complete {
            color: var(--status-success-fg);
            background: var(--status-success-bg);
        }

        .pa-total.is-invalid {
            color: var(--status-conflict-fg);
            background: var(--status-conflict-bg);
        }

        .pa-fields {
            display: grid;
            gap: 12px;
        }

        .pa-field {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 132px;
            gap: 14px;
            align-items: center;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: color-mix(in oklch, var(--surface) 88%, var(--bg-2));
        }

        .pa-field strong,
        .pa-field small {
            display: block;
        }

        .pa-field strong {
            color: var(--fg-1);
            font-size: 14px;
        }

        .pa-field small {
            margin-top: 3px;
            color: var(--fg-3);
            font-size: 12px;
            font-weight: 700;
        }

        .pa-input-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pa-input-wrap input {
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

        .pa-input-wrap input:focus {
            outline: none;
            border-color: var(--brand-navy);
            box-shadow: 0 0 0 3px color-mix(in oklch, var(--brand-navy) 14%, transparent);
        }

        .pa-input-wrap em {
            color: var(--fg-3);
            font-style: normal;
            font-weight: 800;
        }

        .pa-quota-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }

        .pa-quota-value {
            color: var(--brand-navy);
            font-size: 18px;
            font-weight: 800;
        }

        .pa-empty {
            max-width: 680px;
        }

        .pa-empty-title {
            margin-bottom: 6px;
            color: var(--brand-navy);
            font-family: var(--font-display);
            font-size: 20px;
            font-weight: 800;
        }

        .pa-empty p {
            margin: 0;
            color: var(--fg-2);
            font-size: 14px;
            line-height: 1.7;
        }

        @media (max-width: 860px) {
            .pa-shell {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 430px) {
            .pa-form-head,
            .pa-quota-row {
                align-items: stretch;
                flex-direction: column;
            }

            .pa-total {
                width: 100%;
            }

            .pa-field {
                grid-template-columns: 1fr;
            }

            .pa-input-wrap input {
                width: 100%;
            }
        }
    </style>
</x-app-layout>
