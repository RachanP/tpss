@php
    $instructorDiffCount = fn ($d) => count($d['added']) + count($d['removed']) + count($d['role_changed']);
    $detailsDiffCount = fn ($d) => count($d);
    $offeringDiffCount = fn ($oid) => $instructorDiffCount($deviations[$oid]) + $detailsDiffCount($detailsDeviations[$oid] ?? []);

    $totalDeviations = $offerings->sum(fn ($o) => $offeringDiffCount($o->id));
    $deviatedOfferings = $offerings->filter(fn ($o) => $offeringDiffCount($o->id) > 0)->count();

    $formatUser = function ($userId) use ($users) {
        $user = $users[$userId] ?? null;
        if (! $user) return ['name' => "ผู้ใช้ #{$userId}", 'department' => '-'];
        return [
            'name' => $user->formatted_name ?? $user->name,
            'department' => $user->instructorProfile?->department?->name ?? '-',
        ];
    };

    $roleName = fn ($id) => $id ? ($courseRoles[$id]?->name_th ?? '-') : 'อาจารย์ผู้สอน';

    $phaseLabel = fn ($phase) => match($phase) {
        'preparation' => ['label' => 'เตรียมข้อมูล', 'tone' => null],
        'scheduling'  => ['label' => 'เปิดจัดตาราง', 'tone' => 'success'],
        'published'   => ['label' => 'เผยแพร่แล้ว',   'tone' => 'info'],
        default       => ['label' => $phase ?? '-',     'tone' => null],
    };

    $toBuddhistDate = function ($ts) {
        if (! $ts) return null;
        $dt = \Illuminate\Support\Carbon::parse($ts);
        $thaiYear = $dt->year + 543;
        return $dt->format('d/m/') . $thaiYear;
    };

    $templateInstructors = $course->instructors
        ->sortBy(fn ($u) => $courseRoles[$u->pivot->course_role_id ?? 0]?->sort_order ?? 99)
        ->values();

    // Pattern signal — ตอนนี้ใช้กฎง่าย: ถ้า > 50% ของ offering แก้ไข → ควรพิจารณาปรับ template
    $patternRatio = $offerings->count() > 0 ? $deviatedOfferings / $offerings->count() : 0;
    $patternHint = match(true) {
        $offerings->isEmpty()  => ['label' => 'ยังไม่มีข้อมูล', 'tone' => 'gray',    'desc' => 'รอเปิดรอบแรก'],
        $patternRatio === 0.0  => ['label' => 'ใช้แม่แบบครบ',   'tone' => 'success', 'desc' => 'ทุกรอบใช้ตามที่ออกแบบ'],
        $patternRatio <= 0.5   => ['label' => 'ส่วนใหญ่ตามแม่แบบ', 'tone' => 'info', 'desc' => 'มีบางรอบที่ปรับเอง'],
        default                => ['label' => 'ควรทบทวนแม่แบบ', 'tone' => 'warning', 'desc' => 'หลายรอบที่ปรับนอกแม่แบบ'],
    };
@endphp

<x-app-layout title="แดชบอร์ดรายวิชา · {{ $course->course_code }}">
<div class="course-dashboard">

    {{-- Back link --}}
    <a href="{{ route('admin.master_data', ['tab' => 'courses']) }}" class="back-link" data-testid="back-to-master-data">
        <span class="back-link-icon" aria-hidden="true">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"/>
                <polyline points="12 19 5 12 12 5"/>
            </svg>
        </span>
        <span>กลับไปข้อมูลหลัก</span>
    </a>

    {{-- ── SECTION 1 · IDENTITY ── --}}
    <section class="dash-hero">
        <div class="dash-section-tag">ส่วนที่ 1 · ข้อมูลรายวิชา</div>
        <div class="dash-hero-eyebrow">แดชบอร์ดรายวิชา · ติดตามการใช้แม่แบบ</div>
        <div class="dash-hero-title-row">
            <div>
                <div class="dash-hero-code">{{ $course->course_code }}</div>
                <h1 class="dash-hero-name">{{ $course->name_th ?? $course->name_en }}</h1>
                @if($course->name_th && $course->name_en)
                    <div class="dash-hero-name-en">{{ $course->name_en }}</div>
                @endif
            </div>
            <div class="dash-hero-badges">
                @if($course->status === 'active')
                    <span class="dash-badge dash-badge-success" data-testid="course-status-badge">เปิดสอน</span>
                @else
                    <span class="dash-badge dash-badge-gray" data-testid="course-status-badge">ปิดสอน</span>
                @endif
                @if($course->is_required)
                    <span class="dash-badge dash-badge-navy">วิชาบังคับ</span>
                @else
                    <span class="dash-badge dash-badge-gray">วิชาเลือก</span>
                @endif
            </div>
        </div>
        <div class="dash-hero-meta">
            <div class="dash-meta-cell">
                <div class="dash-meta-label">ภาควิชา</div>
                <div class="dash-meta-value">{{ $course->department?->name ?? '-' }}</div>
            </div>
            @if($course->curriculum)
            <div class="dash-meta-cell">
                <div class="dash-meta-label">หลักสูตร</div>
                <div class="dash-meta-value">{{ $course->curriculum->name }}</div>
            </div>
            @endif
            <div class="dash-meta-cell">
                <div class="dash-meta-label">หน่วยกิต</div>
                <div class="dash-meta-value">{{ $course->credits }}</div>
            </div>
            @if($course->default_year_level)
            <div class="dash-meta-cell">
                <div class="dash-meta-label">ชั้นปี</div>
                <div class="dash-meta-value">ปี {{ $course->default_year_level }}</div>
            </div>
            @endif
            @if($course->capacity)
            <div class="dash-meta-cell">
                <div class="dash-meta-label">รับได้</div>
                <div class="dash-meta-value">{{ number_format($course->capacity) }} คน</div>
            </div>
            @endif
        </div>
    </section>

    {{-- ── SECTION 2 · OVERVIEW ── --}}
    <section class="dash-section dash-section--overview">
        <header class="dash-section-header">
            <div class="dash-section-tag">ส่วนที่ 2 · ภาพรวม</div>
            <h2 class="dash-section-title">ภาพรวมการใช้แม่แบบ</h2>
            <p class="dash-section-sub">สรุปจำนวนรอบเปิดสอน เปรียบเทียบกับแม่แบบ และสัญญาณว่าควรปรับแม่แบบหรือไม่</p>
        </header>
        <div class="dash-kpis">
        <div class="kpi-card">
            <div class="kpi-label">รอบเปิดสอน</div>
            <div class="kpi-value">{{ $offerings->count() }}</div>
            <div class="kpi-hint">ทุกสถานะรวมกัน</div>
        </div>
        <div class="kpi-card kpi-card--{{ $deviatedOfferings > 0 ? 'warning' : 'success' }}">
            <div class="kpi-label">รอบที่แก้ไข</div>
            <div class="kpi-value">
                {{ $deviatedOfferings }}<span class="kpi-frac">/{{ $offerings->count() ?: 0 }}</span>
            </div>
            <div class="kpi-hint">{{ $totalDeviations }} รายการรวม</div>
        </div>
        <div class="kpi-card kpi-card--navy">
            <div class="kpi-label">ผู้สอนในแม่แบบ</div>
            <div class="kpi-value">{{ $templateInstructors->count() }}</div>
            <div class="kpi-hint">ไม่นับหัวหน้าวิชา</div>
        </div>
        <div class="kpi-card kpi-card--{{ $patternHint['tone'] }}" data-testid="pattern-card">
            <div class="kpi-label">การใช้แม่แบบ</div>
            <div class="kpi-pattern">{{ $patternHint['label'] }}</div>
            <div class="kpi-hint">{{ $patternHint['desc'] }}</div>
        </div>
        </div>
    </section>

    {{-- ── SECTION 3 · TEMPLATE & HISTORY ── --}}
    <section class="dash-section dash-section--history">
        <header class="dash-section-header">
            <div class="dash-section-tag">ส่วนที่ 3 · แม่แบบและประวัติ</div>
            <h2 class="dash-section-title">เปรียบเทียบแม่แบบกับการใช้งานจริง</h2>
            <p class="dash-section-sub">ซ้าย: ผู้รับผิดชอบที่ออกแบบไว้ · ขวา: รอบเปิดสอนแต่ละครั้งและการแก้ไข</p>
        </header>
        <div class="dash-grid">
        {{-- Template panel --}}
        <section class="dash-card" data-testid="template-panel">
            <header class="dash-card-hdr">
                <div>
                    <h2 class="dash-card-title">แม่แบบผู้รับผิดชอบ</h2>
                    <div class="dash-card-sub">
                        @if($templateUpdatedAt)
                            แก้ไขล่าสุด {{ $toBuddhistDate($templateUpdatedAt) }}
                        @else
                            ยังไม่เคยตั้งค่าผู้สอน
                        @endif
                    </div>
                </div>
                <a href="{{ route('admin.master_data', ['tab' => 'courses', 'edit_course' => $course->id]) }}"
                   class="dash-action-btn"
                   data-testid="edit-template-link">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                    แก้ไขแม่แบบ
                </a>
            </header>
            <div class="dash-card-body">
                {{-- Coordinator highlight --}}
                <div class="instructor-card instructor-card--head">
                    <div class="instructor-avatar">
                        @if($course->headInstructor)
                            {{ mb_substr(preg_replace('/^(อ\.|ดร\.|ผศ\.|รศ\.|ศ\.|นาย|นาง|นางสาว|น\.ส\.)+\s*/u', '', $course->headInstructor->name), 0, 1) }}
                        @else
                            ?
                        @endif
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div class="instructor-role">หัวหน้าวิชา</div>
                        @if($course->headInstructor)
                            <div class="instructor-name">{{ $course->headInstructor->formatted_name ?? $course->headInstructor->name }}</div>
                            <div class="instructor-dept">{{ $course->headInstructor->instructorProfile?->department?->name ?? '-' }}</div>
                        @else
                            <div class="instructor-empty">ยังไม่ระบุหัวหน้าวิชา</div>
                        @endif
                    </div>
                </div>

                {{-- Other instructors --}}
                @forelse($templateInstructors as $instructor)
                    <div class="instructor-card">
                        <div class="instructor-avatar">{{ mb_substr(preg_replace('/^(อ\.|ดร\.|ผศ\.|รศ\.|ศ\.|นาย|นาง|นางสาว|น\.ส\.)+\s*/u', '', $instructor->name), 0, 1) }}</div>
                        <div style="flex:1;min-width:0;">
                            <div class="instructor-role">{{ $roleName($instructor->pivot->course_role_id ?? null) }}</div>
                            <div class="instructor-name">{{ $instructor->formatted_name ?? $instructor->name }}</div>
                            <div class="instructor-dept">{{ $instructor->instructorProfile?->department?->name ?? '-' }}</div>
                        </div>
                    </div>
                @empty
                    <div class="instructor-card instructor-card--empty">
                        <div class="instructor-empty">ยังไม่มีผู้สอนอื่นในแม่แบบ — ใช้เฉพาะหัวหน้าวิชา</div>
                    </div>
                @endforelse
            </div>
        </section>

        {{-- History panel --}}
        <section class="dash-card" data-testid="history-panel">
            <header class="dash-card-hdr">
                <div>
                    <h2 class="dash-card-title">ประวัติการใช้งานในแต่ละรอบเปิดสอน</h2>
                    <div class="dash-card-sub">
                        @if($offerings->isEmpty())
                            ยังไม่มีรอบเปิดสอนของวิชานี้
                        @else
                            เรียงจากล่าสุด · กดแถวที่มีการแก้ไขเพื่อดูรายละเอียด
                        @endif
                    </div>
                </div>
            </header>

            @if($offerings->isEmpty())
                <div class="dash-empty">
                    <div class="dash-empty-icon">📋</div>
                    <div class="dash-empty-title">ยังไม่มีรอบเปิดสอนของวิชานี้</div>
                    <div class="dash-empty-hint">สร้างรอบเปิดสอนผ่าน "ตั้งค่าระบบ → ปีการศึกษา → เปิดช่วงจัดตาราง"</div>
                </div>
            @else
                <div class="history-timeline">
                    @foreach($offerings as $offering)
                        @php
                            $diff = $deviations[$offering->id];
                            $details = $detailsDeviations[$offering->id] ?? [];
                            $count = $instructorDiffCount($diff) + $detailsDiffCount($details);
                            $phase = $offering->academicYear?->phase;
                            $pl = $phaseLabel($phase);
                        @endphp
                        <div x-data="{ open: false }" data-testid="history-row" data-offering-id="{{ $offering->id }}"
                             class="history-row {{ $count > 0 ? 'history-row--deviated' : '' }}">
                            <button type="button"
                                    @click="open = !open"
                                    :disabled="{{ $count === 0 ? 'true' : 'false' }}"
                                    class="history-row-btn">
                                <div class="history-term">
                                    <div class="history-term-year">ปีการศึกษา {{ $offering->academicYear?->name ?? '-' }}</div>
                                </div>
                                <div class="history-info">
                                    <div class="history-coord">หัวหน้าวิชา: {{ $offering->coordinator?->formatted_name ?? $offering->coordinator?->name ?? '-' }}</div>
                                    <div class="history-badges">
                                        @if($pl['tone'])
                                            <span class="dash-badge dash-badge-{{ $pl['tone'] }}">{{ $pl['label'] }}</span>
                                        @else
                                            <span class="dash-badge dash-badge-gray">{{ $pl['label'] }}</span>
                                        @endif
                                        @if($count === 0)
                                            <span class="dash-badge dash-badge-success" data-testid="deviation-zero">ตรงกับแม่แบบ</span>
                                        @else
                                            <span class="dash-badge dash-badge-warning" data-testid="deviation-count">แก้ไข {{ $count }} รายการ</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="history-chevron" :style="open ? 'transform:rotate(180deg);' : ''" style="visibility:{{ $count > 0 ? 'visible' : 'hidden' }};">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="6 9 12 15 18 9"/>
                                    </svg>
                                </div>
                            </button>

                            @if($count > 0)
                                <div x-show="open" x-cloak class="history-detail">
                                    @if(count($diff['added']) > 0)
                                        <div data-testid="deviation-added" class="diff-bucket">
                                            <div class="diff-bucket-label diff-bucket-label--success">
                                                <span class="diff-bucket-symbol">+</span>
                                                เพิ่มจากแม่แบบ ({{ count($diff['added']) }})
                                            </div>
                                            <ul class="diff-list">
                                                @foreach($diff['added'] as $entry)
                                                    @php $u = $formatUser($entry['user_id']); @endphp
                                                    <li>
                                                        <strong>{{ $u['name'] }}</strong>
                                                        <span>{{ $u['department'] }} · {{ $roleName($entry['role_id']) }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                    @if(count($diff['removed']) > 0)
                                        <div data-testid="deviation-removed" class="diff-bucket">
                                            <div class="diff-bucket-label diff-bucket-label--conflict">
                                                <span class="diff-bucket-symbol">−</span>
                                                ไม่ได้ใช้จากแม่แบบ ({{ count($diff['removed']) }})
                                            </div>
                                            <ul class="diff-list">
                                                @foreach($diff['removed'] as $entry)
                                                    @php $u = $formatUser($entry['user_id']); @endphp
                                                    <li>
                                                        <strong>{{ $u['name'] }}</strong>
                                                        <span>{{ $u['department'] }} · {{ $roleName($entry['role_id']) }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                    @if(count($diff['role_changed']) > 0)
                                        <div data-testid="deviation-role-changed" class="diff-bucket">
                                            <div class="diff-bucket-label diff-bucket-label--info">
                                                <span class="diff-bucket-symbol">↻</span>
                                                เปลี่ยนบทบาท ({{ count($diff['role_changed']) }})
                                            </div>
                                            <ul class="diff-list">
                                                @foreach($diff['role_changed'] as $entry)
                                                    @php $u = $formatUser($entry['user_id']); @endphp
                                                    <li>
                                                        <strong>{{ $u['name'] }}</strong>
                                                        <span>{{ $roleName($entry['template_role_id']) }} → {{ $roleName($entry['offering_role_id']) }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif

                                    @if(isset($details['rotation']))
                                        <div data-testid="deviation-rotation" class="diff-bucket" style="grid-column:1 / -1;">
                                            <div class="diff-bucket-label diff-bucket-label--warning">
                                                <span class="diff-bucket-symbol">⚠</span>
                                                การตั้งค่าระดับรอบเปิดสอนต่างจากแม่แบบ
                                            </div>
                                            <ul class="diff-list">
                                                <li>
                                                    <strong>การหมุนเวียนแหล่งฝึก</strong>
                                                    <span>
                                                        แม่แบบ: {{ $details['rotation']['template'] ? 'มีการหมุนเวียน' : 'ไม่มีการหมุนเวียน' }}
                                                        → รอบนี้: {{ $details['rotation']['offering'] ? 'มีการหมุนเวียน' : 'ไม่มีการหมุนเวียน' }}
                                                    </span>
                                                    @if(!empty($details['rotation']['note']))
                                                        <span style="margin-top:4px;color:var(--fg-2);font-style:italic;">เหตุผลจากหัวหน้าวิชา: "{{ $details['rotation']['note'] }}"</span>
                                                    @endif
                                                </li>
                                            </ul>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
        </div>
    </section>
</div>

<style>
    .course-dashboard {
        display: flex;
        flex-direction: column;
        gap: 18px;
        padding: clamp(14px, 2vw, 28px);
        background:
            radial-gradient(circle at 8% 0%, color-mix(in oklch, var(--brand-navy) 9%, transparent), transparent 30%),
            linear-gradient(180deg,
                color-mix(in oklch, var(--brand-navy) 6%, var(--bg)) 0%,
                color-mix(in oklch, var(--brand-navy) 3%, var(--bg)) 46%,
                var(--bg) 100%);
    }

    /* Section panel — boxed container with subtle bg */
    .dash-section {
        padding: clamp(18px, 2vw, 28px);
        background:
            linear-gradient(180deg,
                color-mix(in oklch, var(--brand-navy) 9%, var(--surface)),
                color-mix(in oklch, var(--brand-navy) 3%, var(--surface)) 45%,
                var(--surface));
        border: 1px solid color-mix(in oklch, var(--brand-navy) 26%, var(--border));
        border-radius: 16px;
        box-shadow:
            0 1px 2px rgba(0, 36, 84, 0.08),
            0 18px 42px -28px rgba(0, 36, 84, 0.45);
    }
    .dash-section--history {
        background:
            linear-gradient(180deg,
                color-mix(in oklch, var(--brand-navy) 8%, var(--surface)),
                color-mix(in oklch, var(--brand-navy) 4%, var(--surface)) 38%,
                var(--surface));
    }
    .dash-section-header {
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px solid color-mix(in oklch, var(--brand-navy) 18%, var(--border));
    }
    .dash-section-tag {
        display: inline-block;
        padding: 3px 10px;
        background: var(--brand-navy);
        color: #fff;
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        border-radius: 999px;
        margin-bottom: 10px;
    }
    .dash-section-title {
        margin: 0 0 4px;
        font-family: var(--font-display);
        font-size: 1.125rem;
        font-weight: 700;
        color: var(--fg-1);
    }
    .dash-section-sub {
        margin: 0;
        font-size: 0.8125rem;
        color: var(--fg-3);
    }

    /* Back link */
    .back-link {
        align-self: flex-start;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 9px 16px 9px 12px;
        background: var(--brand-navy);
        color: #fff;
        font-size: 0.875rem;
        font-weight: 600;
        text-decoration: none;
        border: 1px solid var(--brand-navy);
        border-radius: 8px;
        transition: background 0.15s;
    }
    .back-link:hover { background: var(--brand-navy-700, #0f1e3a); color: #fff; }
    .back-link-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        background: rgba(255,255,255,0.18);
        border-radius: 6px;
    }
    .back-link-icon svg { transition: transform 0.15s; }
    .back-link:hover .back-link-icon svg { transform: translateX(-2px); }

    /* Hero */
    .dash-hero {
        padding: 22px 26px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-left: 6px solid var(--brand-navy);
        border-radius: 12px;
    }
    .dash-hero-eyebrow {
        font-size: 0.7rem;
        font-weight: 700;
        color: var(--brand-navy);
        letter-spacing: 0.06em;
        text-transform: uppercase;
        margin-bottom: 8px;
    }
    .dash-hero-title-row {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }
    .dash-hero-code {
        font-family: var(--font-mono, monospace);
        font-size: 0.875rem;
        font-weight: 700;
        color: var(--brand-navy);
        letter-spacing: 0.02em;
    }
    .dash-hero-name {
        margin: 4px 0 2px;
        font-family: var(--font-display);
        font-size: 1.625rem;
        font-weight: 700;
        line-height: 1.15;
        color: var(--fg-1);
    }
    .dash-hero-name-en {
        font-size: 0.875rem;
        color: var(--fg-3);
        font-style: italic;
    }
    .dash-hero-badges {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .dash-hero-meta {
        margin-top: 18px;
        padding-top: 16px;
        border-top: 1px dashed var(--border);
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 18px;
    }
    .dash-meta-label {
        font-size: 0.7rem;
        font-weight: 700;
        color: var(--fg-3);
        letter-spacing: 0.04em;
        text-transform: uppercase;
        margin-bottom: 4px;
    }
    .dash-meta-value {
        font-size: 0.9375rem;
        font-weight: 600;
        color: var(--fg-1);
    }

    /* Badges */
    .dash-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 12px;
        font-size: 0.75rem;
        font-weight: 700;
        border-radius: 999px;
        border: 1px solid;
        white-space: nowrap;
    }
    .dash-badge-success { background: var(--status-success-bg); color: var(--status-success-fg); border-color: var(--status-success-border); }
    .dash-badge-warning { background: var(--status-warning-bg); color: var(--status-warning-fg); border-color: var(--status-warning-border); }
    .dash-badge-info    { background: var(--status-info-bg);    color: var(--status-info-fg);    border-color: var(--status-info-border); }
    .dash-badge-navy    { background: var(--brand-navy); color: #fff; border-color: var(--brand-navy); }
    .dash-badge-gray    { background: var(--bg-2); color: var(--fg-2); border-color: var(--border); }

    /* KPI strip */
    .dash-kpis {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 14px;
    }
    @media (max-width: 720px) {
        .dash-kpis { grid-template-columns: repeat(2, 1fr); }
    }
    .kpi-card {
        position: relative;
        min-height: 132px;
        padding: 18px 20px;
        background:
            radial-gradient(circle at 92% 16%, color-mix(in oklch, var(--brand-navy) 10%, transparent), transparent 36%),
            linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 4%, var(--surface)), var(--surface));
        border: 1px solid color-mix(in oklch, var(--brand-navy) 22%, var(--border));
        border-top: 4px solid var(--brand-navy-300);
        border-radius: 12px;
        box-shadow:
            0 1px 2px rgba(0, 36, 84, 0.08),
            0 14px 28px -24px rgba(0, 36, 84, 0.5);
        overflow: hidden;
        transition: transform 0.16s ease, box-shadow 0.16s ease, border-color 0.16s ease;
    }
    .kpi-card:hover {
        transform: translateY(-2px);
        border-color: color-mix(in oklch, var(--brand-navy) 34%, var(--border));
        box-shadow:
            0 2px 5px rgba(0, 36, 84, 0.1),
            0 20px 36px -24px rgba(0, 36, 84, 0.62);
    }
    .kpi-card--navy {
        border-top-color: var(--brand-navy);
        background:
            radial-gradient(circle at 92% 16%, color-mix(in oklch, var(--brand-navy) 16%, transparent), transparent 38%),
            linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 7%, var(--surface)), var(--surface));
    }
    .kpi-card--success {
        border-top-color: var(--status-success);
        background:
            radial-gradient(circle at 92% 16%, color-mix(in oklch, var(--status-success) 13%, transparent), transparent 38%),
            linear-gradient(180deg, color-mix(in oklch, var(--status-success) 6%, var(--surface)), var(--surface));
    }
    .kpi-card--warning {
        border-top-color: var(--status-warning);
        background:
            radial-gradient(circle at 92% 16%, color-mix(in oklch, var(--status-warning) 14%, transparent), transparent 38%),
            linear-gradient(180deg, color-mix(in oklch, var(--status-warning) 7%, var(--surface)), var(--surface));
    }
    .kpi-card--info {
        border-top-color: var(--status-info);
        background:
            radial-gradient(circle at 92% 16%, color-mix(in oklch, var(--status-info) 13%, transparent), transparent 38%),
            linear-gradient(180deg, color-mix(in oklch, var(--status-info) 6%, var(--surface)), var(--surface));
    }
    .kpi-card--gray    { border-top-color: var(--border); }
    .kpi-label {
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--fg-2);
        letter-spacing: 0.01em;
    }
    .kpi-value {
        font-family: var(--font-display);
        font-size: 2rem;
        font-weight: 700;
        line-height: 1;
        color: var(--brand-navy);
        margin-top: 10px;
    }
    .kpi-frac {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--fg-3);
        margin-left: 2px;
    }
    .kpi-pattern {
        font-size: 1.0625rem;
        font-weight: 700;
        line-height: 1.2;
        color: var(--brand-navy);
        margin-top: 10px;
    }
    .kpi-hint {
        font-size: 0.75rem;
        color: var(--fg-3);
        margin-top: 6px;
    }

    /* Two-column grid */
    .dash-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr);
        gap: 22px;
    }
    @media (max-width: 1024px) {
        .dash-grid { grid-template-columns: 1fr; }
    }

    /* Cards */
    .dash-card {
        background:
            linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 4%, var(--surface)), var(--surface) 46%);
        border: 1px solid color-mix(in oklch, var(--brand-navy) 24%, var(--border));
        border-radius: 14px;
        overflow: hidden;
        box-shadow:
            0 1px 2px rgba(0, 36, 84, 0.08),
            0 18px 38px -26px rgba(0, 36, 84, 0.46);
    }
    .dash-card-hdr {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 18px 22px;
        background:
            linear-gradient(180deg,
                color-mix(in oklch, var(--brand-navy) 10%, var(--surface)),
                color-mix(in oklch, var(--brand-navy) 4%, var(--surface)));
        border-bottom: 1px solid color-mix(in oklch, var(--brand-navy) 18%, var(--border));
        flex-wrap: wrap;
    }
    .dash-card-title {
        margin: 0;
        font-family: var(--font-display);
        font-size: 1rem;
        font-weight: 700;
        color: var(--fg-1);
    }
    .dash-card-sub {
        margin-top: 2px;
        font-size: 0.75rem;
        color: var(--fg-3);
    }
    .dash-card-body {
        padding: 18px 20px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        background: color-mix(in oklch, var(--brand-navy) 2%, var(--surface));
    }
    .dash-action-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 14px;
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--brand-navy);
        background: var(--surface);
        border: 1px solid var(--brand-navy-300);
        border-radius: 6px;
        text-decoration: none;
        transition: background 0.15s, color 0.15s;
    }
    .dash-action-btn:hover {
        background: var(--brand-navy);
        color: #fff;
    }

    /* Instructor cards */
    .instructor-card {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 16px;
        background:
            linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 3%, var(--surface)), var(--surface));
        border: 1px solid color-mix(in oklch, var(--brand-navy) 16%, var(--border));
        border-radius: 12px;
        box-shadow: 0 10px 24px -22px rgba(0, 36, 84, 0.42);
    }
    .instructor-card--head {
        background:
            linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 8%, var(--surface)), var(--surface));
        border-color: color-mix(in oklch, var(--brand-navy) 30%, var(--border));
    }
    .instructor-card--empty {
        border-style: dashed;
        justify-content: center;
        text-align: center;
    }
    .instructor-avatar {
        flex-shrink: 0;
        width: 38px;
        height: 38px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--brand-navy);
        color: #fff;
        font-family: var(--font-display);
        font-size: 0.9375rem;
        font-weight: 700;
        border-radius: 50%;
    }
    .instructor-card--head .instructor-avatar {
        background: linear-gradient(135deg, var(--brand-navy), var(--brand-navy-700, #0f1e3a));
    }
    .instructor-role {
        font-size: 0.7rem;
        font-weight: 700;
        color: var(--brand-navy);
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }
    .instructor-name {
        font-size: 0.9375rem;
        font-weight: 700;
        color: var(--fg-1);
        margin-top: 2px;
    }
    .instructor-dept {
        font-size: 0.75rem;
        color: var(--fg-3);
        margin-top: 2px;
    }
    .instructor-empty {
        font-size: 0.8125rem;
        color: var(--fg-3);
    }

    /* History timeline */
    .history-timeline {
        display: flex;
        flex-direction: column;
    }
    .history-row {
        border-top: 1px solid color-mix(in oklch, var(--brand-navy) 12%, var(--border));
    }
    .history-row:first-child {
        border-top: 0;
    }
    .history-row-btn {
        display: grid;
        grid-template-columns: 100px 1fr 18px;
        gap: 16px;
        align-items: center;
        width: 100%;
        padding: 16px 22px;
        background: transparent;
        border: 0;
        cursor: pointer;
        text-align: left;
        font-family: inherit;
        color: var(--fg-1);
        transition: background 0.15s, box-shadow 0.15s;
    }
    @media (max-width: 560px) {
        .history-row-btn {
            grid-template-columns: 80px 1fr;
            gap: 10px;
            padding: 12px 14px;
        }
        .history-row-btn .history-chevron { display: none; }
    }
    .history-row-btn:disabled {
        cursor: default;
    }
    .history-row--deviated .history-row-btn:hover {
        background: color-mix(in oklch, var(--brand-navy) 5%, var(--surface));
        box-shadow: inset 3px 0 0 var(--brand-navy);
    }
    .history-term {
        border-right: 2px solid var(--brand-navy-300);
        padding-right: 14px;
    }
    .history-term-year {
        font-family: var(--font-display);
        font-size: 1.125rem;
        font-weight: 700;
        color: var(--brand-navy);
        line-height: 1;
    }
    .history-term-semester {
        font-size: 0.75rem;
        color: var(--fg-3);
        margin-top: 4px;
    }
    .history-info {
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .history-coord {
        font-size: 0.8125rem;
        color: var(--fg-2);
    }
    .history-badges {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .history-chevron {
        color: var(--fg-3);
        transition: transform 0.15s;
    }
    .history-detail {
        padding: 14px 22px 20px;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 14px;
        background:
            linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 6%, var(--surface)), color-mix(in oklch, var(--brand-navy) 3%, var(--surface)));
        border-top: 1px solid color-mix(in oklch, var(--brand-navy) 16%, var(--border));
    }

    .diff-bucket {}
    .diff-bucket-label {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.8125rem;
        font-weight: 700;
        margin-bottom: 8px;
        letter-spacing: 0.02em;
    }
    .diff-bucket-symbol {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 18px;
        height: 18px;
        font-size: 14px;
        font-weight: 800;
        border-radius: 50%;
    }
    .diff-bucket-label--success { color: var(--status-success-fg); }
    .diff-bucket-label--success .diff-bucket-symbol { background: var(--status-success-bg); color: var(--status-success-fg); }
    .diff-bucket-label--conflict { color: var(--status-conflict-fg); }
    .diff-bucket-label--conflict .diff-bucket-symbol { background: var(--status-conflict-bg); color: var(--status-conflict-fg); }
    .diff-bucket-label--info { color: var(--status-info-fg); }
    .diff-bucket-label--info .diff-bucket-symbol { background: var(--status-info-bg); color: var(--status-info-fg); }

    .diff-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .diff-list li {
        display: flex;
        flex-direction: column;
        gap: 2px;
        padding: 8px 10px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 6px;
        font-size: 0.8125rem;
    }
    .diff-list li span {
        font-size: 0.72rem;
        color: var(--fg-3);
    }

    /* Empty state */
    .dash-empty {
        padding: 40px 20px;
        text-align: center;
    }
    .dash-empty-icon {
        font-size: 32px;
        margin-bottom: 8px;
        opacity: 0.5;
    }
    .dash-empty-title {
        font-size: 0.9375rem;
        font-weight: 600;
        color: var(--fg-2);
    }
    .dash-empty-hint {
        font-size: 0.8125rem;
        color: var(--fg-3);
        margin-top: 6px;
    }

    .course-dashboard {
        padding: clamp(14px, 2vw, 28px);
        background:
            radial-gradient(circle at 8% 0%, color-mix(in oklch, var(--brand-navy) 10%, transparent), transparent 30%),
            linear-gradient(180deg,
                color-mix(in oklch, var(--brand-navy) 7%, var(--bg)) 0%,
                color-mix(in oklch, var(--brand-navy) 4%, var(--bg)) 34%,
                var(--bg) 100%);
    }

    .course-dashboard .dash-hero,
    .course-dashboard .dash-card,
    .course-dashboard .card,
    .course-dashboard .metric-card,
    .course-dashboard .instructor-card,
    .course-dashboard .diff-list li {
        border-color: color-mix(in oklch, var(--brand-navy) 24%, var(--border)) !important;
        background:
            linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 5%, var(--surface)), var(--surface) 46%),
            var(--surface) !important;
        box-shadow:
            0 1px 2px rgba(0, 36, 84, 0.09),
            0 16px 34px -22px rgba(0, 36, 84, 0.42);
    }

    .course-dashboard .dash-hero {
        background:
            radial-gradient(circle at 10% 0%, color-mix(in oklch, var(--brand-navy) 14%, transparent), transparent 32%),
            linear-gradient(135deg, color-mix(in oklch, var(--brand-navy) 12%, var(--surface)), var(--surface) 64%) !important;
    }

    .course-dashboard .dash-card-hdr,
    .course-dashboard .card-hdr,
    .course-dashboard thead th {
        border-bottom-color: color-mix(in oklch, var(--brand-navy) 20%, var(--border)) !important;
        background:
            linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 10%, var(--surface)), color-mix(in oklch, var(--brand-navy) 4%, var(--surface))) !important;
    }

    .course-dashboard .back-link {
        border-color: color-mix(in oklch, var(--brand-navy) 24%, var(--border));
        background: color-mix(in oklch, var(--brand-navy) 5%, var(--surface));
        color: var(--brand-navy);
    }

    .course-dashboard .history-detail {
        border-top-color: color-mix(in oklch, var(--brand-navy) 18%, var(--border));
        background: color-mix(in oklch, var(--brand-navy) 5%, var(--surface));
    }

    .course-dashboard .history-row-btn:hover {
        background: color-mix(in oklch, var(--brand-navy) 6%, var(--surface));
    }
</style>
</x-app-layout>
