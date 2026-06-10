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
            'teaching_pct' => ['title' => 'ด้านการสอน', 'short' => 'สอน', 'key' => 't', 'color' => 'var(--pa-teaching)'],
            'research_pct' => ['title' => 'ด้านวิจัย', 'short' => 'วิจัย', 'key' => 'r', 'color' => 'var(--pa-research)'],
            'service_pct' => ['title' => 'บริการวิชาการ', 'short' => 'บริการ', 'key' => 's', 'color' => 'var(--pa-service)'],
            'culture_pct' => ['title' => 'ศิลปวัฒนธรรม', 'short' => 'ศิลปฯ', 'key' => 'c', 'color' => 'var(--pa-culture)'],
            'other_pct' => ['title' => 'งานอื่นๆ ที่ได้รับมอบหมาย', 'short' => 'อื่นๆ', 'key' => 'o', 'color' => 'var(--pa-other)'],
        ];
        $formatRule = function ($rule) {
            if (! is_array($rule)) return '-';
            $min = (int) ($rule['min'] ?? 0);
            $max = (int) ($rule['max'] ?? 100);
            if ($min === 0 && $max === 0) return '0%';
            if ($min === 0) return "ไม่เกิน {$max}%";
            return "{$min}-{$max}%";
        };
        $formatHours = fn ($value) => number_format((float) $value, 1);
        $quotaForRatio = max((float) ($teachingQuota ?? 0), 0);
        $formatPercent = fn ($value) => $quotaForRatio > 0
            ? number_format((((float) $value) / $quotaForRatio) * 100, 1) . '%'
            : '-';
        $formatRatio = fn ($value) => $quotaForRatio > 0
            ? $formatHours($value) . ' / ' . number_format($quotaForRatio, 0) . ' ชม. (' . $formatPercent($value) . ')'
            : $formatHours($value) . ' ชม.';
        $taughtPct = $quotaForRatio > 0 ? min(100, max(0, ((float) ($taughtTeachingHours ?? 0) / $quotaForRatio) * 100)) : 0;
        $upcomingPct = $quotaForRatio > 0 ? min(max(0, 100 - $taughtPct), max(0, ((float) ($upcomingTeachingHours ?? 0) / $quotaForRatio) * 100)) : 0;
        $remainingPct = max(0, 100 - $taughtPct - $upcomingPct);
        $publishedCount = $approvedTeachingSchedules?->count() ?? 0;
    @endphp

    <div class="workload-page" x-data="{
        paOpen: true,
        values: @js($initialValues),
        paFields: @js(collect($fieldLabels)->map(fn ($meta, $field) => ['field' => $field, 'title' => $meta['title'], 'short' => $meta['short'], 'color' => $meta['color']])->values()),
        quotaBase: @js((float) ($quotaBase ?? 0)),
        approvedHours: @js((float) ($approvedTeachingHours ?? 0)),
        taughtHours: @js((float) ($taughtTeachingHours ?? 0)),
        total() {
            return Object.values(this.values).reduce((sum, value) => sum + (parseInt(value, 10) || 0), 0);
        },
        teachingQuota() {
            return Math.round((this.quotaBase * (parseInt(this.values.teaching_pct, 10) || 0)) / 100);
        },
        remainingHours() {
            return Math.round((this.teachingQuota() - this.approvedHours) * 10) / 10;
        },
        remainingToday() {
            return Math.round((this.teachingQuota() - this.taughtHours) * 10) / 10;
        },
        valueFor(field) {
            return Math.max(0, Math.min(100, parseInt(this.values[field], 10) || 0));
        },
        // donut (conic-gradient): เรียงสัดส่วน 5 ด้านต่อเนื่องเป็นวง + ส่วนที่ยังขาดเป็นสีจาง
        donutStyle() {
            let start = 0;
            const parts = this.paFields.map((item) => {
                const value = this.valueFor(item.field);
                const end = start + value;
                const segment = `${item.color} ${start}% ${end}%`;
                start = end;
                return segment;
            }).filter(Boolean);

            if (start < 100) {
                parts.push(`color-mix(in oklch, var(--brand-navy) 8%, var(--surface)) ${start}% 100%`);
            }

            return `conic-gradient(${parts.join(', ')})`;
        }
    }">
        <header class="workload-header">
            <div>
                <div class="workload-kicker">อาจารย์ / ภาระงานสอน</div>
                <h1>ภาระงานสอน</h1>
                <p>รายละเอียดรายวิชาและคาบสอนที่เผยแพร่แล้วสำหรับอาจารย์ผู้สอน</p>
            </div>
            <div class="workload-header-meta" aria-label="รอบภาระงาน">
                <span>รอบปัจจุบัน</span>
                <strong>{{ $round?->name ?? '-' }}</strong>
                <small>
                    @if($round)
                        {{ optional($round->start_date)->format('d/m/Y') }} ถึง {{ optional($round->end_date)->format('d/m/Y') }}
                    @else
                        ยังไม่มีรอบ PA
                    @endif
                </small>
            </div>
        </header>

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
            <div class="workload-empty" data-testid="pa-profile-empty">
                <h2>ยังไม่มีข้อมูลโปรไฟล์อาจารย์</h2>
                <p>กรุณาให้ผู้ดูแลระบบบันทึกข้อมูลพื้นฐานก่อนดูภาระงานสอนและกรอกสัดส่วน PA</p>
            </div>
        @elseif(! $academicYear || ! $round)
            <div class="workload-empty" data-testid="pa-round-empty">
                <h2>ยังไม่มีรอบ PA ที่พร้อมใช้งาน</h2>
                <p>ต้องมีปีการศึกษาในระบบก่อนจึงจะดูภาระงานสอนและกรอกสัดส่วน PA ได้</p>
            </div>
        @else
            {{-- กรอกสัดส่วน PA ก่อน (action หลัก) → เลื่อนลงดูภาพรวมภาระงานที่คำนวณจากสัดส่วนนี้ --}}
            <section class="workload-pa-panel" data-testid="pa-form">
                <button type="button" class="workload-pa-toggle" @click="paOpen = !paOpen" :aria-expanded="paOpen.toString()">
                    <span>
                        <strong>ตั้งค่าสัดส่วน PA</strong>
                        <small>ใช้คำนวณเกณฑ์ชั่วโมงสอน ไม่ใช่รายละเอียดคาบสอนที่ได้รับมอบหมาย</small>
                    </span>
                    <span class="workload-total" :class="{ 'is-complete': total() === 100, 'is-invalid': total() !== 100 }" data-testid="pa-total">
                        <span x-text="total()"></span>%
                    </span>
                </button>

                <div class="workload-pa-body" x-show="paOpen" x-collapse>
                    <form method="POST" action="{{ route('lecturer.pa.update') }}" class="workload-form">
                        @csrf
                        @method('PUT')

                        <div class="workload-form-meta">
                            <div>
                                <span>บันทึกล่าสุด</span>
                                <strong data-testid="pa-submitted-at">{{ $allocation?->submitted_at ? $allocation->submitted_at->format('d/m/Y H:i') : 'ยังไม่เคยบันทึก' }}</strong>
                            </div>
                            <div>
                                <span>เกณฑ์ตำแหน่ง</span>
                                <strong>{{ $criteriaGroup ?? '-' }}</strong>
                            </div>
                        </div>

                        <div class="workload-pa-editor">
                            <div class="workload-fields">
                                @foreach($fieldLabels as $field => $meta)
                                    @php $rule = $paRules[$meta['key']] ?? null; @endphp
                                    <label class="workload-field" style="--pa-color: {{ $meta['color'] }}">
                                        <span class="workload-field-title">
                                            <i aria-hidden="true"></i>
                                            <span>
                                                <strong>{{ $meta['title'] }}</strong>
                                                <small>เกณฑ์ {{ $formatRule($rule) }}</small>
                                            </span>
                                        </span>
                                        <span class="workload-input">
                                            <input
                                                type="number"
                                                name="{{ $field }}"
                                                min="0"
                                                max="100"
                                                step="1"
                                                required
                                                x-model.number="values.{{ $field }}"
                                                data-testid="pa-{{ str_replace('_', '-', $field) }}"
                                            >
                                            <em>%</em>
                                        </span>
                                    </label>
                                @endforeach
                            </div>

                            <aside class="workload-pa-chart" aria-label="สัดส่วนภาระงาน PA">
                                {{-- donut: สัดส่วน 5 ด้านรวมเป็น 100% (conic-gradient) แยกสีต่อด้าน + รูตรงกลางโชว์ % รวม --}}
                                <div class="workload-donut" :style="{ background: donutStyle() }">
                                    <div class="workload-donut-center" :class="{ 'is-complete': total() === 100, 'is-invalid': total() !== 100 }">
                                        <span>รวม</span>
                                        <strong x-text="total() + '%'"></strong>
                                    </div>
                                </div>
                                <div class="workload-pa-status" :class="{ 'is-complete': total() === 100, 'is-invalid': total() !== 100 }">
                                    <span>ผลรวมสัดส่วน PA</span>
                                    <strong x-text="total() + '%'"></strong>
                                    <em x-text="total() === 100 ? 'พร้อมบันทึก' : (total() < 100 ? 'ยังขาด ' + (100 - total()) + '%' : 'เกิน ' + (total() - 100) + '%')"></em>
                                    <span>ผลรวมสัดส่วน PA ต้องเท่ากับ 100%</span>
                                </div>
                                <div class="workload-pa-legend">
                                    @foreach($fieldLabels as $field => $meta)
                                        <div style="--pa-color: {{ $meta['color'] }}">
                                            <span><i aria-hidden="true"></i>{{ $meta['title'] }}</span>
                                            <strong x-text="valueFor('{{ $field }}') + '%'"></strong>
                                        </div>
                                    @endforeach
                                </div>
                            </aside>
                        </div>

                        <div class="workload-actions">
                            <button type="submit" class="btn btn-primary" data-testid="pa-submit">บันทึก PA</button>
                        </div>
                    </form>
                </div>
            </section>

            <section class="workload-overview" aria-label="ภาพรวมภาระงานสอน">
                <div class="workload-overview-chart">
                    <div class="workload-chart-head">
                        <div>
                            <span>ภาพรวมเทียบเกณฑ์ภาระงานสอน</span>
                            <strong>{{ $formatHours($taughtTeachingHours ?? 0) }} ชม. ที่สอนแล้ว</strong>
                        </div>
                        <div>
                            <span>เกณฑ์ตาม PA</span>
                            <strong>{{ number_format($quotaForRatio, 0) }} ชม.</strong>
                        </div>
                    </div>
                    <div class="workload-stack" aria-label="กราฟชั่วโมงสอนเทียบเกณฑ์">
                        <span class="workload-stack-segment is-taught" style="width: {{ $taughtPct }}%"></span>
                        <span class="workload-stack-segment is-upcoming" style="width: {{ $upcomingPct }}%"></span>
                        <span class="workload-stack-segment is-remaining" style="width: {{ $remainingPct }}%"></span>
                    </div>
                    <div class="workload-stack-legend">
                        <span><i class="is-taught"></i>สอนแล้ว {{ $formatHours($taughtTeachingHours ?? 0) }} ชม. ({{ $formatPercent($taughtTeachingHours ?? 0) }})</span>
                        <span><i class="is-upcoming"></i>รอวันสอน {{ $formatHours($upcomingTeachingHours ?? 0) }} ชม. ({{ $formatPercent($upcomingTeachingHours ?? 0) }})</span>
                        <span><i class="is-remaining"></i>เหลือจากเกณฑ์ {{ $formatHours($remainingTeachingHoursToday ?? 0) }} ชม.</span>
                    </div>
                </div>

                <div class="workload-overview-groups">
                    <div class="workload-overview-group is-progress">
                        <div class="workload-overview-group-title">ความคืบหน้าถึงวันนี้</div>
                        <div class="workload-overview-pair">
                            <div class="workload-overview-item is-primary">
                                <span>สอนแล้ว</span>
                                <strong class="workload-metric-value">{{ $formatHours($taughtTeachingHours ?? 0) }} ชม.</strong>
                                <small>{{ $formatPercent($taughtTeachingHours ?? 0) }} ของเกณฑ์</small>
                            </div>
                            <div class="workload-overview-item">
                                <span>เหลือจากเกณฑ์</span>
                                <strong class="workload-metric-value" :class="{ 'is-over': remainingToday() < 0 }"><span x-text="remainingToday()"></span> ชม.</strong>
                                <small>หลังหักชั่วโมงที่สอนแล้ว</small>
                            </div>
                        </div>
                    </div>

                    <div class="workload-overview-group is-published">
                        <div class="workload-overview-group-title">ตารางที่เผยแพร่แล้ว</div>
                        <div class="workload-overview-pair">
                            <div class="workload-overview-item">
                                <span>ชั่วโมงรวม</span>
                                <strong class="workload-metric-value">{{ $formatHours($approvedTeachingHours ?? 0) }} ชม.</strong>
                                <small>{{ $formatRatio($approvedTeachingHours ?? 0) }} ของเกณฑ์</small>
                            </div>
                            <div class="workload-overview-item">
                                <span>รอวันสอน</span>
                                <strong class="workload-metric-value">{{ $formatHours($upcomingTeachingHours ?? 0) }} ชม.</strong>
                                <small>คาบอนาคตในตารางเผยแพร่แล้ว</small>
                            </div>
                        </div>
                    </div>

                    <div class="workload-overview-group is-reference">
                        <div class="workload-overview-group-title">เกณฑ์อ้างอิง</div>
                        <div class="workload-overview-pair">
                            <div class="workload-overview-item">
                                <span>เกณฑ์ตาม PA</span>
                                <strong class="workload-metric-value"><span x-text="teachingQuota()"></span> ชม.</strong>
                                <small>สัดส่วนด้านการสอน {{ $initialValues['teaching_pct'] }}% / {{ $periodLabel }}</small>
                            </div>
                            <div class="workload-overview-item">
                                <span>คาบเผยแพร่</span>
                                <strong class="workload-metric-value">{{ number_format($publishedCount) }} คาบ</strong>
                                <small>จำนวนคาบที่นำมาคิดภาระงาน</small>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="workload-report">
                <div class="workload-section-head">
                    <div>
                        <h2>สรุปภาระงานตามรายวิชา</h2>
                        <p>นับเฉพาะคาบที่เผยแพร่แล้วและประเภทกิจกรรมที่กำหนดให้นับภาระงาน</p>
                    </div>
                    <span class="workload-status-badge">เผยแพร่แล้ว</span>
                </div>

                @if($workloadCourseSummaries->isEmpty())
                    <div class="workload-empty workload-empty-inline" data-testid="approved-workload-empty">
                        <h3>ยังไม่มีภาระงานสอนที่เผยแพร่แล้ว</h3>
                        <p>เมื่อรายวิชาหรือคาบสอนได้รับการอนุมัติแล้ว รายละเอียดภาระงานของคุณจะแสดงในหน้านี้</p>
                    </div>
                @else
                    <div class="workload-table-wrap workload-summary-wrap">
                        <table class="workload-table workload-summary-table" data-testid="course-workload-summary">
                            <thead>
                                <tr>
                                    <th>รายวิชา</th>
                                    <th>ประเภทกิจกรรม</th>
                                    <th>กลุ่ม</th>
                                    <th class="is-number">จำนวนคาบ</th>
                                    <th class="is-number">ชั่วโมง</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($workloadCourseSummaries as $summary)
                                    <tr>
                                        <td data-label="รายวิชา">
                                            <div class="workload-course-cell">
                                                <strong>{{ $summary['course_code'] }}</strong>
                                                <span>{{ $summary['course_name'] }}</span>
                                            </div>
                                        </td>
                                        <td data-label="ประเภทกิจกรรม">
                                            <div class="workload-chip-list">
                                                @forelse($summary['activity_names'] as $activityName)
                                                    <span class="workload-chip">{{ $activityName }}</span>
                                                @empty
                                                    <span class="workload-muted">-</span>
                                                @endforelse
                                            </div>
                                        </td>
                                        <td data-label="กลุ่ม">
                                            <div class="workload-chip-list">
                                                @forelse($summary['group_codes'] as $groupCode)
                                                    <span class="workload-chip is-soft">{{ $groupCode }}</span>
                                                @empty
                                                    <span class="workload-muted">-</span>
                                                @endforelse
                                            </div>
                                        </td>
                                        <td data-label="จำนวนคาบ" class="is-number">
                                            <span class="workload-number-pill">{{ number_format($summary['schedule_count']) }}</span>
                                        </td>
                                        <td data-label="ชั่วโมง" class="is-number">
                                            <span class="workload-number-pill is-hours">{{ $formatHours($summary['hours']) }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="workload-report">
                <div class="workload-section-head">
                    <div>
                        <h2>รายละเอียดคาบสอน</h2>
                        <p>เรียงตามวันที่และเวลา เพื่อให้อาจารย์ตรวจสอบงานที่ได้รับมอบหมายได้โดยตรง</p>
                    </div>
                </div>

                @if($approvedTeachingSchedules->isEmpty())
                    <div class="workload-empty workload-empty-inline">
                        <h3>ยังไม่มีรายการคาบสอน</h3>
                        <p>รายการจะแสดงเมื่อมีคาบสอนสถานะเผยแพร่แล้วที่นับภาระงาน</p>
                    </div>
                @else
                    <div class="workload-table-wrap workload-detail-wrap">
                        <table class="workload-table workload-detail-table" data-testid="approved-teaching-schedules">
                            <thead>
                                <tr>
                                    <th>วัน/เวลา</th>
                                    <th>รายวิชา</th>
                                    <th>รายละเอียด</th>
                                    <th>สถานที่</th>
                                    <th>บทบาท</th>
                                    <th class="is-number">ชั่วโมง</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($approvedTeachingSchedules as $schedule)
                                    @php
                                        $course = $schedule->courseOffering?->course;
                                        $room = $schedule->room;
                                        $groups = $schedule->studentGroups->pluck('group_code')->filter()->join(', ');
                                    @endphp
                                    <tr>
                                        <td data-label="วัน/เวลา">
                                            <div class="workload-date-cell">
                                                <strong>{{ optional($schedule->teaching_date)->format('d/m/Y') }}</strong>
                                                <span>{{ substr((string) $schedule->start_time, 0, 5) }}-{{ substr((string) $schedule->end_time, 0, 5) }}</span>
                                            </div>
                                        </td>
                                        <td data-label="รายวิชา">
                                            <div class="workload-course-cell">
                                                <strong>{{ $course?->course_code ?? '-' }}</strong>
                                                <span>{{ $course?->name_th ?? $course?->name_en ?? '-' }}</span>
                                            </div>
                                        </td>
                                        <td data-label="รายละเอียด">
                                            <div class="workload-session-cell">
                                                <span class="workload-activity-label">{{ $schedule->activityType?->name ?? '-' }}</span>
                                                <strong>{{ $schedule->topic ?: 'ไม่ระบุหัวข้อ' }}</strong>
                                            </div>
                                            @if($groups)
                                                <div class="workload-chip-list is-compact">
                                                    @foreach($schedule->studentGroups->pluck('group_code')->filter() as $groupCode)
                                                        <span class="workload-chip is-soft">{{ $groupCode }}</span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                        <td data-label="สถานที่">
                                            <div class="workload-room-cell">
                                                <strong>{{ $room?->room_code ?? '-' }}</strong>
                                                <span>{{ $room?->room_name ?? $room?->building ?? '-' }}</span>
                                            </div>
                                        </td>
                                        <td data-label="บทบาท"><span class="workload-role">{{ $schedule->workload_role }}</span></td>
                                        <td data-label="ชั่วโมง" class="is-number">
                                            <span class="workload-number-pill is-hours">{{ $formatHours($schedule->workload_hours) }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endif
    </div>

    <style>
        .workload-page {
            --workload-border: color-mix(in oklch, var(--brand-navy) 24%, var(--border));
            --workload-border-soft: color-mix(in oklch, var(--brand-navy) 14%, var(--border));
            --workload-surface-tint: color-mix(in oklch, var(--brand-navy) 3.5%, var(--surface));
            --workload-surface-soft: color-mix(in oklch, var(--brand-navy) 6%, var(--surface));
            --pa-teaching: oklch(0.38 0.11 248);
            --pa-research: oklch(0.57 0.12 210);
            --pa-service: oklch(0.53 0.11 168);
            --pa-culture: oklch(0.58 0.12 285);
            --pa-other: oklch(0.62 0.11 62);
            width: min(100%, 1280px);
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 16px;
            padding: clamp(14px, 2vw, 24px);
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 5%, var(--bg)), var(--bg) 240px),
                var(--bg);
        }

        .workload-header,
        .workload-overview,
        .workload-report,
        .workload-pa-panel,
        .workload-empty {
            border: 1px solid var(--workload-border);
            border-radius: 10px;
            background: var(--surface);
            box-shadow:
                0 1px 2px rgba(0, 36, 84, 0.08),
                0 14px 30px -26px rgba(0, 36, 84, 0.42);
        }

        .workload-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 22px;
            padding: 18px 20px 18px 22px;
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 8%, var(--surface)), var(--surface) 74%),
                var(--surface);
        }

        .workload-kicker {
            margin-bottom: 6px;
            color: var(--brand-navy);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .02em;
        }

        .workload-header h1 {
            margin: 0;
            color: var(--fg-1);
            font-family: var(--font-display);
            font-size: 24px;
            font-weight: 800;
            line-height: 1.2;
        }

        .workload-header p,
        .workload-section-head p,
        .workload-pa-toggle small,
        .workload-empty p {
            margin: 5px 0 0;
            color: var(--fg-3);
            font-size: 13px;
            line-height: 1.55;
        }

        .workload-header p {
            color: var(--fg-3);
        }

        .workload-header-meta {
            min-width: 250px;
            max-width: 360px;
            padding: 12px 14px;
            border: 1px solid var(--workload-border-soft);
            border-radius: 8px;
            background: color-mix(in oklch, var(--brand-navy) 4%, var(--surface));
            text-align: right;
        }

        .workload-header-meta span,
        .workload-header-meta small,
        .workload-overview-item span,
        .workload-overview-item small,
        .workload-form-meta span {
            display: block;
            color: var(--fg-3);
            font-size: 12px;
            font-weight: 700;
            line-height: 1.45;
        }

        .workload-header-meta strong {
            display: block;
            margin-top: 3px;
            color: var(--brand-navy);
            font-size: 16px;
            font-weight: 800;
            line-height: 1.35;
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

        .workload-overview {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0;
            overflow: hidden;
            background: var(--surface);
        }

        .workload-overview-chart {
            grid-column: 1 / -1;
            padding: 17px 18px 15px;
            border-bottom: 1px solid var(--workload-border-soft);
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 10%, var(--surface)), color-mix(in oklch, var(--brand-navy) 4%, var(--surface))),
                var(--surface);
        }

        .workload-chart-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 12px;
        }

        .workload-chart-head > div:last-child {
            text-align: right;
        }

        .workload-chart-head span {
            display: block;
            color: color-mix(in oklch, var(--brand-navy) 68%, var(--fg-3));
            font-size: 12px;
            font-weight: 800;
            line-height: 1.45;
        }

        .workload-chart-head strong {
            display: block;
            margin-top: 3px;
            color: var(--brand-navy);
            font-family: var(--font-display);
            font-size: 23px;
            font-weight: 850;
            line-height: 1.2;
            font-variant-numeric: tabular-nums;
        }

        .workload-stack {
            display: flex;
            width: 100%;
            height: 18px;
            overflow: hidden;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 28%, var(--border));
            border-radius: 999px;
            background: color-mix(in oklch, var(--brand-navy) 8%, var(--surface));
            box-shadow: inset 0 1px 2px rgba(0, 36, 84, 0.12);
        }

        .workload-stack-segment {
            display: block;
            min-width: 0;
            height: 100%;
            box-shadow: inset -1px 0 0 rgba(248, 251, 253, 0.72);
        }

        .workload-stack-segment.is-taught,
        .workload-stack-legend i.is-taught {
            background: var(--brand-navy);
        }

        .workload-stack-segment.is-upcoming,
        .workload-stack-legend i.is-upcoming {
            background: color-mix(in oklch, var(--status-info-fg) 78%, var(--surface));
        }

        .workload-stack-segment.is-remaining,
        .workload-stack-legend i.is-remaining {
            background: color-mix(in oklch, var(--brand-navy) 12%, var(--surface));
        }

        .workload-stack-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 10px;
            margin-top: 11px;
        }

        .workload-stack-legend span {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            min-height: 28px;
            padding: 3px 0;
            border: 0;
            border-radius: 999px;
            color: color-mix(in oklch, var(--brand-navy) 55%, var(--fg-2));
            font-size: 12px;
            font-weight: 750;
            line-height: 1.45;
            background: transparent;
        }

        .workload-stack-legend i {
            width: 11px;
            height: 11px;
            border-radius: 999px;
            flex: 0 0 auto;
            box-shadow: inset 0 0 0 1px rgba(0, 36, 84, 0.12);
        }

        .workload-overview-groups {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            padding: 14px;
            background: color-mix(in oklch, var(--brand-navy) 2.5%, var(--surface));
        }

        .workload-overview-group {
            min-width: 0;
            overflow: hidden;
            border: 1px solid var(--workload-border-soft);
            border-radius: 14px;
            background: var(--surface);
            box-shadow: 0 8px 22px -20px rgba(0, 36, 84, 0.5);
        }

        .workload-overview-group-title {
            padding: 10px 13px 8px;
            border-bottom: 1px solid color-mix(in oklch, var(--brand-navy) 10%, var(--border));
            color: color-mix(in oklch, var(--brand-navy) 72%, var(--fg-2));
            font-size: 12px;
            font-weight: 850;
            line-height: 1.35;
            background: color-mix(in oklch, var(--brand-navy) 4%, var(--surface));
        }

        .workload-overview-group.is-progress .workload-overview-group-title {
            border-bottom-color: color-mix(in oklch, var(--brand-navy) 46%, var(--border));
            color: var(--brand-navy);
            background: color-mix(in oklch, var(--brand-navy) 24%, var(--surface));
        }

        .workload-overview-group.is-published .workload-overview-group-title {
            border-bottom-color: color-mix(in oklch, var(--status-info-fg) 48%, var(--border));
            color: color-mix(in oklch, var(--status-info-fg) 84%, var(--brand-navy));
            background: color-mix(in oklch, var(--status-info-fg) 27%, var(--surface));
        }

        .workload-overview-group.is-reference .workload-overview-group-title {
            border-bottom-color: color-mix(in oklch, oklch(0.58 0.10 265) 48%, var(--border));
            color: color-mix(in oklch, oklch(0.42 0.11 265) 82%, var(--brand-navy));
            background: color-mix(in oklch, oklch(0.73 0.08 265) 34%, var(--surface));
        }

        .workload-overview-pair {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .workload-overview-item {
            min-width: 0;
            padding: 13px 14px 14px;
            border-right: 1px solid color-mix(in oklch, var(--brand-navy) 9%, var(--border));
            background: var(--surface);
        }

        .workload-overview-item:last-child {
            border-right: 0;
        }

        .workload-overview-item strong,
        .workload-metric-value {
            display: block;
            margin-top: 5px;
            color: var(--fg-1);
            font-family: var(--font-display);
            font-size: 21px;
            font-weight: 800;
            line-height: 1.2;
            font-variant-numeric: tabular-nums;
        }

        .workload-metric-value,
        .workload-metric-value span {
            color: var(--fg-1);
            font-family: var(--font-display);
            font-size: 21px;
            font-weight: 800;
            line-height: 1.2;
            font-variant-numeric: tabular-nums;
        }

        .workload-overview-item.is-primary strong,
        .workload-overview-item.is-primary .workload-metric-value,
        .workload-overview-item.is-primary .workload-metric-value span {
            color: var(--brand-navy);
        }

        .workload-overview-item span {
            color: color-mix(in oklch, var(--brand-navy) 58%, var(--fg-3));
            font-weight: 800;
        }

        .workload-overview-item small {
            display: block;
            margin-top: 2px;
        }

        .workload-overview-item strong.is-over {
            color: var(--status-warning-fg);
        }

        .workload-report {
            overflow: hidden;
        }

        .workload-section-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 15px 18px;
            border-bottom: 1px solid var(--workload-border-soft);
            background:
                linear-gradient(180deg, var(--workload-surface-soft), var(--workload-surface-tint)),
                var(--surface);
        }

        .workload-section-head h2,
        .workload-empty h2,
        .workload-empty h3 {
            margin: 0;
            color: var(--fg-1);
            font-family: var(--font-display);
            font-size: 18px;
            font-weight: 800;
            line-height: 1.25;
        }

        .workload-status-badge,
        .workload-role {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 26px;
            padding: 4px 10px;
            border: 1px solid color-mix(in oklch, var(--status-success-fg) 24%, var(--border));
            border-radius: 999px;
            background: var(--status-success-bg);
            color: var(--status-success-fg);
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .workload-table-wrap {
            overflow-x: auto;
            scrollbar-gutter: stable;
        }

        .workload-table {
            width: 100%;
            min-width: 860px;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .workload-table th,
        .workload-table td {
            padding: 13px 14px;
            border-bottom: 1px solid var(--border);
            text-align: left;
            vertical-align: top;
        }

        .workload-table th {
            color: var(--fg-3);
            background: color-mix(in oklch, var(--brand-navy) 4%, var(--surface));
            font-size: 12px;
            font-weight: 800;
            line-height: 1.35;
            white-space: nowrap;
        }

        .workload-table tbody tr {
            transition: background-color 150ms ease;
        }

        .workload-table tbody tr:hover {
            background: color-mix(in oklch, var(--brand-navy) 3%, var(--surface));
        }

        .workload-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .workload-table strong,
        .workload-table span,
        .workload-table em {
            display: block;
        }

        .workload-table strong {
            color: var(--fg-1);
            font-size: 13.5px;
            font-weight: 800;
            line-height: 1.45;
            overflow-wrap: anywhere;
        }

        .workload-table span,
        .workload-table em {
            margin-top: 2px;
            color: var(--fg-3);
            font-size: 12px;
            font-style: normal;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .workload-table .is-number {
            text-align: right;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .workload-summary-wrap {
            padding: 10px 12px 12px;
            background: linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 2%, var(--surface)), var(--surface));
        }

        .workload-summary-table {
            min-width: 900px;
            overflow: hidden;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 10%, var(--border));
            border-radius: 12px;
            border-collapse: separate;
            border-spacing: 0;
            background: var(--surface);
        }

        .workload-summary-table th {
            padding-block: 11px;
            border-bottom-color: color-mix(in oklch, var(--brand-navy) 14%, var(--border));
            color: color-mix(in oklch, var(--brand-navy) 58%, var(--fg-3));
            background: color-mix(in oklch, var(--brand-navy) 7%, var(--surface));
        }

        .workload-summary-table td {
            padding-block: 15px;
            background: var(--surface);
        }

        .workload-summary-table tbody tr:hover td {
            background: color-mix(in oklch, var(--brand-navy) 3.5%, var(--surface));
        }

        .workload-course-cell {
            min-width: 0;
        }

        .workload-course-cell strong {
            color: var(--brand-navy);
            font-size: 14px;
            letter-spacing: 0.01em;
        }

        .workload-chip-list {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-start;
            gap: 6px;
        }

        .workload-table .workload-chip,
        .workload-table .workload-muted,
        .workload-table .workload-number-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: fit-content;
            margin-top: 0;
        }

        .workload-table .workload-chip {
            min-height: 26px;
            padding: 4px 9px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 16%, var(--border));
            border-radius: 999px;
            color: color-mix(in oklch, var(--brand-navy) 74%, var(--fg-2));
            font-size: 12px;
            font-weight: 800;
            line-height: 1.2;
            background: color-mix(in oklch, var(--brand-navy) 5%, var(--surface));
        }

        .workload-table .workload-chip.is-soft {
            border-color: color-mix(in oklch, var(--status-info-fg) 20%, var(--border));
            color: color-mix(in oklch, var(--status-info-fg) 72%, var(--brand-navy));
            background: color-mix(in oklch, var(--status-info-fg) 8%, var(--surface));
        }

        .workload-table .workload-muted {
            color: var(--fg-3);
            font-size: 12px;
            font-weight: 700;
        }

        .workload-table .workload-number-pill {
            min-width: 46px;
            min-height: 30px;
            padding: 4px 10px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 12%, var(--border));
            border-radius: 999px;
            color: var(--fg-1);
            font-size: 14px;
            font-weight: 850;
            line-height: 1.2;
            background: color-mix(in oklch, var(--brand-navy) 3%, var(--surface));
            font-variant-numeric: tabular-nums;
        }

        .workload-table .workload-number-pill.is-hours {
            color: var(--brand-navy);
            background: color-mix(in oklch, var(--brand-navy) 7%, var(--surface));
        }

        .workload-detail-table {
            min-width: 1040px;
            border-collapse: separate;
            border-spacing: 0;
        }

        .workload-detail-wrap {
            padding: 10px 12px 12px;
            background: linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 2%, var(--surface)), var(--surface));
        }

        .workload-detail-table {
            overflow: hidden;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 10%, var(--border));
            border-radius: 12px;
            background: var(--surface);
        }

        .workload-detail-table th {
            padding-block: 11px;
            border-bottom-color: color-mix(in oklch, var(--brand-navy) 14%, var(--border));
            color: color-mix(in oklch, var(--brand-navy) 58%, var(--fg-3));
            background: color-mix(in oklch, var(--brand-navy) 7%, var(--surface));
        }

        .workload-detail-table td {
            padding-block: 15px;
            background: var(--surface);
        }

        .workload-detail-table tbody tr:hover td {
            background: color-mix(in oklch, var(--brand-navy) 3.5%, var(--surface));
        }

        .workload-date-cell {
            display: inline-flex;
            min-width: 108px;
            flex-direction: column;
            gap: 2px;
            padding: 8px 10px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 12%, var(--border));
            border-radius: 10px;
            background: color-mix(in oklch, var(--brand-navy) 4%, var(--surface));
        }

        .workload-date-cell strong,
        .workload-room-cell strong {
            color: var(--brand-navy);
        }

        .workload-date-cell span {
            color: color-mix(in oklch, var(--brand-navy) 58%, var(--fg-3));
            font-weight: 750;
        }

        .workload-session-cell {
            display: grid;
            gap: 3px;
            min-width: 0;
        }

        .workload-table .workload-activity-label {
            display: inline-flex;
            width: fit-content;
            margin-top: 0;
            padding: 3px 8px;
            border-radius: 999px;
            color: color-mix(in oklch, var(--brand-navy) 82%, var(--fg-2));
            font-size: 12px;
            font-weight: 850;
            line-height: 1.2;
            background: color-mix(in oklch, var(--brand-navy) 7%, var(--surface));
        }

        .workload-session-cell strong {
            font-size: 13px;
            font-weight: 750;
        }

        .workload-chip-list.is-compact {
            margin-top: 7px;
            gap: 5px;
        }

        .workload-chip-list.is-compact::before {
            content: "กลุ่ม";
            display: inline-flex;
            align-items: center;
            color: var(--fg-3);
            font-size: 11px;
            font-weight: 800;
        }

        .workload-room-cell {
            min-width: 0;
        }

        .workload-detail-table th:nth-child(1),
        .workload-detail-table td:nth-child(1) {
            width: 148px;
        }

        .workload-detail-table th:nth-child(4),
        .workload-detail-table td:nth-child(4) {
            width: 150px;
        }

        .workload-detail-table th:nth-child(5),
        .workload-detail-table td:nth-child(5) {
            width: 124px;
        }

        .workload-detail-table th:nth-child(6),
        .workload-detail-table td:nth-child(6) {
            width: 96px;
        }

        .workload-empty {
            padding: 22px;
        }

        .workload-empty-inline {
            margin: 16px;
            border-style: dashed;
            box-shadow: none;
            background: color-mix(in oklch, var(--brand-navy) 2.5%, var(--surface));
        }

        .workload-pa-panel {
            overflow: hidden;
        }

        .workload-pa-toggle {
            display: flex;
            width: 100%;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 15px 18px;
            border: 0;
            background: var(--surface);
            color: inherit;
            cursor: pointer;
            text-align: left;
            font: inherit;
            transition: background-color 150ms ease;
        }

        .workload-pa-toggle:hover {
            background: color-mix(in oklch, var(--brand-navy) 3%, var(--surface));
        }

        .workload-pa-toggle strong {
            display: block;
            color: var(--fg-1);
            font-family: var(--font-display);
            font-size: 18px;
            font-weight: 800;
        }

        .workload-total {
            min-width: 86px;
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            text-align: center;
            font-family: var(--font-display);
            font-size: 22px;
            font-weight: 800;
            line-height: 1;
        }

        .workload-total.is-complete {
            color: var(--status-success-fg);
            background: var(--status-success-bg);
            border-color: color-mix(in oklch, var(--status-success-fg) 24%, var(--border));
        }

        .workload-total.is-invalid {
            color: var(--status-conflict-fg);
            background: var(--status-conflict-bg);
            border-color: color-mix(in oklch, var(--status-conflict-fg) 24%, var(--border));
        }

        .workload-pa-body {
            border-top: 1px solid var(--border);
            background: color-mix(in oklch, var(--brand-navy) 2%, var(--surface));
        }

        .workload-form {
            padding: 18px;
        }

        .workload-form-meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 14px;
        }

        .workload-form-meta div {
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
        }

        .workload-form-meta strong {
            display: block;
            margin-top: 3px;
            color: var(--fg-1);
            font-weight: 800;
        }

        .workload-pa-editor {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(280px, 340px);
            gap: 16px;
            align-items: stretch; /* 2 คอลัมน์สูงเท่ากัน */
        }

        .workload-fields {
            display: grid;
            gap: 10px;
            grid-auto-rows: 1fr; /* 5 ช่องสูงเท่ากันเติมเต็มความสูง (gap ปกติ ไม่โหรงเหรง) = ก้นชนกรอบขวา */
        }

        .workload-field {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 118px;
            gap: 12px;
            align-items: center;
            padding: 12px 14px;
            border: 1px solid var(--workload-border-soft);
            border-radius: 12px;
            background: var(--surface);
            transition: border-color 150ms ease, box-shadow 150ms ease;
        }

        .workload-field:focus-within {
            border-color: var(--brand-navy);
            box-shadow: 0 0 0 3px color-mix(in oklch, var(--brand-navy) 10%, transparent);
        }

        .workload-field-title {
            display: grid;
            grid-template-columns: 12px minmax(0, 1fr);
            gap: 10px;
            align-items: center;
        }

        .workload-field-title i {
            width: 12px;
            height: 34px;
            border-radius: 999px;
            background: var(--pa-color);
            box-shadow: inset 0 0 0 1px rgba(0, 36, 84, 0.1);
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
            justify-self: end;
        }

        .workload-input input {
            width: 82px;
            min-height: 42px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 16%, var(--border));
            border-radius: 10px;
            padding: 8px 10px;
            color: var(--fg-1);
            font-size: 18px;
            font-weight: 850;
            text-align: right;
            font-variant-numeric: tabular-nums;
            background: color-mix(in oklch, var(--brand-navy) 2%, var(--surface));
        }

        .workload-input input:focus {
            outline: none;
            border-color: var(--pa-color, var(--brand-navy));
            box-shadow: 0 0 0 3px color-mix(in oklch, var(--pa-color, var(--brand-navy)) 18%, transparent);
        }

        .workload-input em {
            color: var(--fg-3);
            font-style: normal;
            font-weight: 850;
        }

        .workload-pa-chart {
            display: grid;
            gap: 14px;
            padding: 16px;
            border: 1px solid var(--workload-border-soft);
            border-radius: 14px;
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 5%, var(--surface)), var(--surface)),
                var(--surface);
        }

        .workload-donut {
            display: grid;
            width: min(100%, 220px);
            aspect-ratio: 1;
            place-items: center;
            justify-self: center;
            border-radius: 999px;
            box-shadow:
                inset 0 0 0 1px color-mix(in oklch, var(--brand-navy) 12%, var(--border)),
                0 12px 24px -22px rgba(0, 36, 84, 0.6);
            transition: background .25s ease;
        }

        .workload-donut-center {
            display: grid;
            width: 58%;
            aspect-ratio: 1;
            place-items: center;
            align-content: center;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 12%, var(--border));
            border-radius: 999px;
            background: var(--surface);
            text-align: center;
        }

        .workload-donut-center span {
            color: var(--fg-3);
            font-size: 12px;
            font-weight: 800;
        }

        .workload-donut-center strong {
            color: var(--brand-navy);
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 850;
            line-height: 1;
            font-variant-numeric: tabular-nums;
        }

        .workload-donut-center.is-complete strong {
            color: var(--status-success-fg);
        }

        .workload-donut-center.is-invalid strong {
            color: var(--status-warning-fg, #a87600);
        }

        .workload-pa-status {
            display: grid;
            gap: 3px;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--surface);
            text-align: center;
        }

        .workload-pa-status strong {
            color: var(--brand-navy);
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 850;
            line-height: 1;
            font-variant-numeric: tabular-nums;
        }

        .workload-pa-status span,
        .workload-pa-status em {
            color: var(--fg-3);
            font-size: 12px;
            font-style: normal;
            font-weight: 700;
        }

        .workload-pa-status em {
            color: var(--fg-2);
            font-weight: 850;
        }

        .workload-pa-status.is-complete {
            border-color: color-mix(in oklch, var(--status-success-fg) 24%, var(--border));
            background: var(--status-success-bg);
        }

        .workload-pa-status.is-complete strong {
            color: var(--status-success-fg);
        }

        .workload-pa-status.is-invalid {
            border-color: color-mix(in oklch, var(--status-conflict-fg) 20%, var(--border));
            background: color-mix(in oklch, var(--status-conflict-bg) 70%, var(--surface));
        }

        .workload-pa-status.is-invalid strong {
            color: var(--status-conflict-fg);
        }

        .workload-pa-legend {
            display: grid;
            gap: 8px;
        }

        .workload-pa-legend div {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            color: var(--fg-2);
            font-size: 12px;
            font-weight: 800;
        }

        .workload-pa-legend span {
            display: inline-flex;
            min-width: 0;
            align-items: center;
            gap: 8px;
        }

        .workload-pa-legend i {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            flex: 0 0 auto;
            background: var(--pa-color);
        }

        .workload-pa-legend strong {
            color: var(--fg-1);
            font-variant-numeric: tabular-nums;
        }

        .workload-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }

        .workload-actions .btn {
            min-height: 42px;
            padding-inline: 18px;
            font-weight: 800;
        }

        @media (max-width: 980px) {
            .workload-header,
            .workload-section-head {
                flex-direction: column;
                align-items: stretch;
            }

            .workload-header-meta {
                min-width: 0;
                text-align: left;
            }

            .workload-chart-head {
                flex-direction: column;
                gap: 10px;
            }

            .workload-chart-head > div:last-child {
                text-align: left;
            }

            .workload-overview-groups {
                grid-template-columns: 1fr;
            }

            .workload-field {
                grid-template-columns: 1fr;
            }

            .workload-input input {
                width: 100%;
            }
        }

        @media (max-width: 760px) {
            .workload-page {
                padding: 12px;
                gap: 12px;
            }

            .workload-header {
                padding: 16px;
            }

            .workload-header h1 {
                font-size: 22px;
            }

            .workload-overview,
            .workload-form-meta {
                grid-template-columns: 1fr;
            }

            .workload-overview-chart {
                padding: 14px;
            }

            .workload-overview-groups {
                padding: 10px;
                gap: 10px;
            }

            .workload-overview-pair {
                grid-template-columns: 1fr;
            }

            .workload-overview-item {
                border-right: 0;
                border-bottom: 1px solid color-mix(in oklch, var(--brand-navy) 9%, var(--border));
            }

            .workload-overview-item:last-child {
                border-bottom: 0;
            }

            .workload-stack {
                height: 16px;
            }

            .workload-chart-head strong {
                font-size: 21px;
            }

            .workload-stack-legend {
                display: grid;
                grid-template-columns: 1fr;
                gap: 7px;
            }

            .workload-pa-toggle {
                align-items: stretch;
                flex-direction: column;
            }

            .workload-total {
                width: 100%;
            }

            .workload-table {
                min-width: 0;
                table-layout: auto;
            }

            .workload-table thead {
                display: none;
            }

            .workload-table,
            .workload-table tbody,
            .workload-table tr,
            .workload-table td {
                display: block;
                width: 100%;
            }

            .workload-table tbody {
                padding: 10px;
                background: color-mix(in oklch, var(--brand-navy) 2%, var(--surface));
            }

            .workload-table tr {
                margin-bottom: 10px;
                border: 1px solid var(--workload-border-soft);
                border-radius: 9px;
                background: var(--surface);
                overflow: hidden;
            }

            .workload-table tr:last-child {
                margin-bottom: 0;
            }

            .workload-table td {
                display: grid;
                grid-template-columns: 104px minmax(0, 1fr);
                gap: 12px;
                padding: 10px 12px;
                border-bottom: 1px solid var(--border);
                text-align: left !important;
            }

            .workload-table td:last-child {
                border-bottom: 0;
            }

            .workload-table td::before {
                content: attr(data-label);
                color: var(--fg-3);
                font-size: 12px;
                font-weight: 800;
                line-height: 1.45;
            }

            .workload-role {
                justify-self: start;
            }
        }

        @media (max-width: 480px) {
            .workload-table td {
                grid-template-columns: 1fr;
                gap: 4px;
            }
        }
    </style>
</x-app-layout>
