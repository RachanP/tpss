@php
    $viewer = \Illuminate\Support\Facades\Auth::user();
    $activeRole = session('active_role');
    $today = now('Asia/Bangkok')->toDateString();

    $statusMeta = [
        'draft' => ['label' => 'แบบร่าง', 'class' => 'badge-gray'],
        'pending_approval' => ['label' => 'รออนุมัติ', 'class' => 'badge-warn'],
        'approved' => ['label' => 'อนุมัติแล้ว', 'class' => 'badge-ok'],
        'revised' => ['label' => 'ส่งกลับแก้ไข', 'class' => 'badge-err'],
    ];

    $upcomingSchedules = isset($upcomingSchedules)
        ? collect($upcomingSchedules)->take(5)
        : \App\Models\Schedule::query()
            ->with([
                'activityType',
                'room',
                'courseOffering.course',
                'courseOffering.academicYear',
                'studentGroups' => fn ($query) => $query->orderBy('group_code'),
                'instructors.instructorProfile.department',
            ])
            ->whereDate('end_date', '>=', $today)
            ->when($activeRole === 'course_head' && $viewer, fn ($query) => $query
                ->whereHas('courseOffering', fn ($offeringQuery) => $offeringQuery->where('coordinator_id', $viewer->id)))
            ->when($activeRole === 'instructor' && $viewer, fn ($query) => $query
                ->whereHas('instructors', fn ($instructorQuery) => $instructorQuery->where('users.id', $viewer->id)))
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->limit(5)
            ->get();

    $formatThaiDate = function ($date) {
        if (! $date) {
            return '-';
        }

        $date = $date instanceof \Carbon\CarbonInterface ? $date : \Carbon\Carbon::parse($date);

        return $date->format('d/m/').($date->year + 543);
    };

    $formatTime = fn ($time) => $time ? substr((string) $time, 0, 5) : '-';
@endphp

<div class="card" data-testid="dashboard-upcoming-schedules">
    <div class="card-hdr">
        <div style="display:flex;align-items:center;gap:10px;min-width:0;">
            <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" style="color:var(--fg-2);flex-shrink:0;">
                <rect x="3" y="4" width="18" height="18" rx="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/>
                <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            <div>
                <div class="card-ttl">ตารางสอนที่กำลังจะมาถึง</div>
                <div style="font-size:12px;color:var(--fg-3);margin-top:2px;">รายการใกล้ถึงกำหนดตามช่วงวันที่และเวลา</div>
            </div>
        </div>

        <span class="badge badge-gray" data-testid="upcoming-schedules-count">{{ $upcomingSchedules->count() }} รายการ</span>
    </div>

    <div style="border-top:1px solid var(--border);">
        @forelse($upcomingSchedules as $schedule)
            @php
                $courseOffering = $schedule->courseOffering;
                $course = $courseOffering?->course;
                $meta = $statusMeta[$schedule->status] ?? ['label' => $schedule->status ?: 'ไม่ระบุ', 'class' => 'badge-gray'];
                $scheduleUrl = $activeRole === 'course_head' && $courseOffering
                    ? route('maker.course_offerings.schedules.index', $courseOffering)
                    : null;
                $dateLabel = $formatThaiDate($schedule->start_date);
                $endDateLabel = $formatThaiDate($schedule->end_date);
                $dateRange = $dateLabel === $endDateLabel ? $dateLabel : "{$dateLabel} ถึง {$endDateLabel}";
                $groupLabel = $schedule->studentGroups->pluck('group_code')->take(3)->implode(', ');
                $extraGroups = max(0, $schedule->studentGroups->count() - 3);
            @endphp

            <div data-testid="upcoming-schedules-row"
                 style="display:grid;grid-template-columns:minmax(92px,.55fr) minmax(0,1.45fr) auto;gap:12px;align-items:start;padding:13px 20px;border-bottom:1px solid var(--border);">
                <div style="font-variant-numeric:tabular-nums;">
                    <div style="font-size:13px;font-weight:700;color:var(--fg-1);line-height:1.45;">{{ $dateRange }}</div>
                    <div style="font-size:12px;color:var(--fg-3);margin-top:2px;">{{ $formatTime($schedule->start_time) }} - {{ $formatTime($schedule->end_time) }}</div>
                </div>

                <div style="min-width:0;">
                    <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-bottom:4px;">
                        <span style="font-size:13px;font-weight:700;color:var(--fg-1);line-height:1.5;">
                            {{ $course?->course_code ?? '-' }}
                        </span>
                        <span class="badge {{ $meta['class'] }}">{{ $meta['label'] }}</span>
                    </div>

                    <div style="font-size:13px;font-weight:600;color:var(--fg-1);line-height:1.5;overflow:hidden;text-overflow:ellipsis;">
                        {{ $schedule->activityType?->name ?? 'ไม่ระบุกิจกรรม' }}
                    </div>
                    <div style="font-size:12px;color:var(--fg-3);line-height:1.5;margin-top:2px;">
                        {{ $schedule->topic ?: ($course?->name_th ?? $course?->name_en ?? 'ไม่ระบุหัวข้อ') }}
                    </div>

                    <div style="display:flex;gap:7px;align-items:center;flex-wrap:wrap;margin-top:7px;font-size:12px;color:var(--fg-3);">
                        <span>{{ $schedule->room?->room_code ?? 'ไม่ระบุห้อง' }}</span>
                        @if($groupLabel)
                            <span aria-hidden="true" style="color:var(--border);">•</span>
                            <span>{{ $groupLabel }}{{ $extraGroups > 0 ? " +{$extraGroups}" : '' }}</span>
                        @endif
                    </div>
                </div>

                @if($scheduleUrl)
                    <a href="{{ $scheduleUrl }}" class="btn btn-sm" data-testid="upcoming-schedules-open">เปิด</a>
                @endif
            </div>
        @empty
            <div style="padding:28px 20px;text-align:center;color:var(--fg-3);font-size:13px;line-height:1.55;">
                <div style="font-weight:700;color:var(--fg-2);margin-bottom:4px;">ยังไม่มีตารางสอนที่กำลังจะมาถึง</div>
                <div>เมื่อมีรายการสอนที่กำหนดวันและเวลาแล้ว ระบบจะแสดงรายการใกล้ถึงกำหนดที่นี่</div>
            </div>
        @endforelse
    </div>
</div>
