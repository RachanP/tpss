@php
    $formatDate = fn ($date) => $date ? \App\Support\ThaiDate::date($date) : '-';
    $formatTime = fn ($value) => substr((string) $value, 0, 5);
    $conflictTypeLabels = [
        'instructor_overlap' => 'ผู้สอนชน',
        'room_overlap' => 'ห้อง/สถานที่ชน',
        'group_overlap' => 'กลุ่มนักศึกษาชน',
    ];
@endphp

<x-app-layout title="การแจ้งเตือนการชน">
    <style>
        .conflict-page {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .conflict-hero,
        .conflict-offering {
            border: 1px solid var(--bd);
            border-radius: 10px;
            background: var(--surface);
            box-shadow: 0 1px 3px oklch(0% 0 0 / 0.05);
        }
        .conflict-hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 18px;
            align-items: center;
            padding: 20px 22px;
        }
        .conflict-eyebrow {
            font-size: 11px;
            font-weight: 850;
            color: var(--fg-3);
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .conflict-title {
            margin-top: 4px;
            font-size: 24px;
            font-weight: 900;
            color: var(--fg-1);
            line-height: 1.25;
        }
        .conflict-copy {
            margin-top: 6px;
            color: var(--fg-2);
            font-size: 13.5px;
            line-height: 1.6;
        }
        .conflict-total {
            min-width: 130px;
            padding: 12px 14px;
            border: 1px solid var(--status-conflict-border);
            border-radius: 8px;
            background: var(--status-conflict-bg);
            color: var(--status-conflict-fg);
            text-align: center;
        }
        .conflict-total strong {
            display: block;
            font-size: 28px;
            line-height: 1;
            font-weight: 900;
        }
        .conflict-total span {
            display: block;
            margin-top: 4px;
            font-size: 11px;
            font-weight: 800;
        }
        .conflict-offering {
            overflow: hidden;
        }
        .conflict-offering-head {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border-bottom: 1px solid var(--bd);
            background: oklch(98% 0.01 228);
        }
        .conflict-course {
            min-width: 0;
            flex: 1;
        }
        .conflict-course-code {
            font-size: 15px;
            font-weight: 900;
            color: var(--fg-1);
        }
        .conflict-course-name {
            margin-top: 2px;
            font-size: 12.5px;
            color: var(--fg-2);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .conflict-list {
            display: flex;
            flex-direction: column;
        }
        .conflict-item {
            display: grid;
            grid-template-columns: 170px minmax(0, 1fr) auto;
            gap: 16px;
            padding: 15px 16px;
            border-bottom: 1px solid var(--bd);
        }
        .conflict-item:last-child {
            border-bottom: 0;
        }
        .conflict-time {
            font-size: 12.5px;
            font-weight: 850;
            color: var(--fg-1);
        }
        .conflict-topic {
            font-size: 14px;
            font-weight: 900;
            color: var(--fg-1);
        }
        .conflict-messages {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-top: 8px;
        }
        .conflict-message {
            display: flex;
            align-items: flex-start;
            gap: 7px;
            color: var(--status-conflict-fg);
            font-size: 12.5px;
            line-height: 1.55;
        }
        .conflict-dot {
            width: 6px;
            height: 6px;
            margin-top: 7px;
            border-radius: 999px;
            background: var(--status-conflict);
            flex: 0 0 auto;
        }
        .conflict-actions {
            display: flex;
            align-items: center;
        }
        .conflict-empty {
            padding: 34px 18px;
            text-align: center;
            border: 1px solid var(--bd);
            border-radius: 10px;
            background: var(--surface);
            color: var(--fg-2);
        }
        @media (max-width: 760px) {
            .conflict-hero,
            .conflict-item {
                grid-template-columns: 1fr;
            }
            .conflict-total {
                text-align: left;
            }
            .conflict-actions {
                align-items: flex-start;
            }
        }
    </style>

    <div class="conflict-page">
        <section class="conflict-hero">
            <div>
                <div class="conflict-eyebrow">Schedule Conflicts</div>
                <div class="conflict-title">การแจ้งเตือนการชน</div>
                <div class="conflict-copy">
                    แสดงรายการชนของทุกวิชาที่คุณรับผิดชอบ สามารถบันทึกรายการที่ชนไว้ก่อนเพื่อรอประสานกับหัวหน้าวิชาอื่นได้ แต่ต้องแก้ให้ไม่ชนก่อนส่งอนุมัติ
                </div>
            </div>
            <div class="conflict-total" data-testid="maker-conflict-total">
                <strong>{{ $totalConflictCount }}</strong>
                <span>รายการชนที่ต้องแก้</span>
            </div>
        </section>

        @if($conflictGroups->isEmpty())
            <div class="conflict-empty" data-testid="maker-conflict-empty">
                ยังไม่พบการชนในรายวิชาที่รับผิดชอบ
            </div>
        @else
            @foreach($conflictGroups as $group)
                @php
                    $offering = $group['offering'];
                    $course = $offering->course;
                @endphp
                <section class="conflict-offering" data-testid="maker-conflict-offering">
                    <div class="conflict-offering-head">
                        <div class="conflict-course">
                            <div class="conflict-course-code">{{ $course?->course_code ?? '-' }}</div>
                            <div class="conflict-course-name">{{ $course?->name_th ?? $course?->name_en ?? 'ไม่ระบุชื่อรายวิชา' }}</div>
                        </div>
                        <span class="badge badge-err">{{ $group['conflict_count'] }} รายการชน</span>
                    </div>
                    <div class="conflict-list">
                        @foreach($group['schedules'] as $schedule)
                            @php
                                $conflicts = $conflictMap->get($schedule->id, collect());
                                $editUrl = route('maker.course_offerings.schedules.index', [
                                    $offering,
                                    'edit_schedule_id' => $schedule->id,
                                    'week_start' => $schedule->start_date?->toDateString(),
                                ]);
                            @endphp
                            <article class="conflict-item" data-testid="maker-conflict-item">
                                <div class="conflict-time">
                                    <div>{{ $formatDate($schedule->start_date) }} @if($schedule->end_date && !$schedule->start_date?->isSameDay($schedule->end_date)) - {{ $formatDate($schedule->end_date) }} @endif</div>
                                    <div>{{ $formatTime($schedule->start_time) }} - {{ $formatTime($schedule->end_time) }}</div>
                                </div>
                                <div>
                                    <div class="conflict-topic">{{ $schedule->topic ?: ($schedule->activityType?->name ?? 'รายการสอน') }}</div>
                                    <div class="conflict-messages">
                                        @foreach($conflicts as $conflict)
                                            <div class="conflict-message">
                                                <span class="conflict-dot" aria-hidden="true"></span>
                                                <span>
                                                    <strong>{{ $conflictTypeLabels[$conflict['type']] ?? 'ตารางชน' }}:</strong>
                                                    {{ $conflict['message'] }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="conflict-actions">
                                    <a href="{{ $editUrl }}" class="btn btn-secondary" style="text-decoration:none;">ไปแก้ไข</a>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach
        @endif
    </div>
</x-app-layout>
