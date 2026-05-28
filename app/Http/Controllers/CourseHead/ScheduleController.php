<?php

namespace App\Http\Controllers\CourseHead;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\CourseOffering;
use App\Models\Schedule;
use App\Models\ScheduleTemplate;
use App\Services\AuditLogger;
use App\Services\NavigationBadgeService;
use App\Services\ReferenceDataCache;
use App\Services\ScheduleConflictChecker;
use App\Services\ScheduleConflictInvalidationService;
use App\Services\ScheduleConflictIndex;
use App\Services\ScheduleConflictReadRepository;
use App\Services\ScheduleSeriesGenerator;
use App\Support\ThaiDate;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function workspace(Request $request): View|RedirectResponse
    {
        if ($targetOfferingId = $this->coordinatorScheduleOfferingRedirectTarget($request)) {
            return redirect()->route('maker.course_offerings.schedules.index', array_filter([
                'courseOffering' => $targetOfferingId,
                'week_start' => $request->query('week_start'),
                'date' => $request->query('date'),
                'period' => $request->query('period'),
                'include_weekends' => $request->query('include_weekends'),
                'modal' => $request->query('modal'),
            ]));
        }

        $offerings = $this->coordinatorScheduleOfferings(includeSchedulingData: false);

        return view('course_head.schedules.index', $this->schedulePageData(
            request: $request,
            courseOffering: null,
            isWorkspace: true,
            availableOfferings: $offerings,
        ));
    }

    public function conflicts(Request $request): View
    {
        $userId = (int) Auth::id();
        $availableYears = $this->coordinatorAcademicYears($userId);
        $defaultAcademicYear = $this->defaultConflictAcademicYear($availableYears);
        $selectedAcademicYear = $this->selectedConflictAcademicYear($request, $availableYears, $defaultAcademicYear);
        $selectedAcademicYearId = $selectedAcademicYear?->id ? (int) $selectedAcademicYear->id : null;
        $emptyStateKey = $this->conflictEmptyStateKey($availableYears);

        if (config('conflicts.async_reads')) {
            return $this->asyncConflicts($request, $userId, $availableYears, $selectedAcademicYear, $selectedAcademicYearId, $emptyStateKey);
        }

        $result = app(ScheduleConflictIndex::class)->conflictsForCoordinator($userId, $selectedAcademicYearId);

        if ($defaultAcademicYear && (int) $selectedAcademicYearId === (int) $defaultAcademicYear->id) {
            app(NavigationBadgeService::class)->putCourseHeadConflictCount($userId, $result['total']);
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 50;
        $conflictSchedules = new LengthAwarePaginator(
            $result['schedules']->forPage($page, $perPage)->values(),
            $result['schedules']->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
        $schedules = $conflictSchedules->getCollection();
        $conflictMap = $result['conflictMap'];

        $conflictGroups = $schedules
            ->groupBy('course_offering_id')
            ->map(function (Collection $offeringSchedules) use ($conflictMap) {
                /** @var Schedule $firstSchedule */
                $firstSchedule = $offeringSchedules->first();

                return [
                    'offering' => $firstSchedule->courseOffering,
                    'schedules' => $offeringSchedules->values(),
                    'conflict_count' => $offeringSchedules->sum(
                        fn (Schedule $schedule) => $conflictMap->get($schedule->id, collect())->count()
                    ),
                ];
            })
            ->values();

        $conflictTypeCounts = $conflictMap
            ->flatten(1)
            ->groupBy('type')
            ->map(fn ($items) => $items->count())
            ->all();

        return view('course_head.schedule_conflicts.index', [
            'offerings' => collect(),
            'availableYears' => $availableYears,
            'selectedAcademicYear' => $selectedAcademicYear,
            'selectedAcademicYearId' => $selectedAcademicYearId,
            'conflictSchedules' => $conflictSchedules,
            'conflictGroups' => $conflictGroups,
            'conflictMap' => $conflictMap,
            'totalConflictCount' => $result['total'],
            'conflictTypeCounts' => $conflictTypeCounts,
            'conflictStatus' => ['status' => 'ready'],
            'conflictEmptyStateKey' => $this->conflictEmptyStateKey($availableYears),
            'asyncConflictReads' => false,
        ]);
    }

    private function asyncConflicts(
        Request $request,
        int $userId,
        Collection $availableYears,
        ?AcademicYear $selectedAcademicYear,
        ?int $selectedAcademicYearId,
        ?string $emptyStateKey = null
    ): View {
        $repository = app(ScheduleConflictReadRepository::class);
        $page = max(1, (int) $request->query('page', 1));
        $conflictStatus = $repository->getStatusForUser($userId, $selectedAcademicYearId);

        if (($conflictStatus['status'] ?? 'missing') === 'missing' && $selectedAcademicYearId) {
            app(ScheduleConflictInvalidationService::class)->markDirty($selectedAcademicYearId, 'manual');
        }

        $resultPage = $repository->getScheduleSummaryPageForUser($userId, $selectedAcademicYearId, $page);
        $conflictGroups = $this->conflictGroupsFromSummaries($resultPage->getCollection());

        return view('course_head.schedule_conflicts.index', [
            'offerings' => collect(),
            'availableYears' => $availableYears,
            'selectedAcademicYear' => $selectedAcademicYear,
            'selectedAcademicYearId' => $selectedAcademicYearId,
            'conflictSchedules' => $resultPage,
            'conflictGroups' => $conflictGroups,
            'conflictMap' => collect(),
            'totalConflictCount' => $repository->getCountForUser($userId, $selectedAcademicYearId),
            'conflictTypeCounts' => $repository->getTypeCountsForUser($userId, $selectedAcademicYearId),
            'conflictStatus' => $conflictStatus,
            'conflictEmptyStateKey' => $emptyStateKey ?? $this->conflictEmptyStateKey($availableYears),
            'asyncConflictReads' => true,
        ]);
    }

    public function conflictDetails(Request $request, Schedule $schedule): JsonResponse
    {
        $validated = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
        ]);
        $academicYearId = (int) $validated['academic_year_id'];

        $schedule->loadMissing('courseOffering:id,course_id,academic_year_id,coordinator_id');

        abort_unless(
            $schedule->courseOffering && (int) $schedule->courseOffering->academic_year_id === $academicYearId,
            404
        );

        $role = session('active_role');
        abort_unless(in_array($role, ['admin', 'course_head'], true), 403);

        $repository = app(ScheduleConflictReadRepository::class);
        $userId = (int) Auth::id();

        abort_unless(
            $repository->canReadScheduleDetails($schedule, $userId, $academicYearId, (string) $role),
            403
        );

        $conflicts = $repository->getDetailsForSchedule($schedule, $userId, $academicYearId, (string) $role);
        $typeCounts = $conflicts
            ->groupBy('type')
            ->map(fn (Collection $items) => $items->count())
            ->all();

        return response()->json([
            'html' => view('course_head.schedule_conflicts._conflict_sets', [
                'conflicts' => $conflicts,
                'conflictTypeLabels' => $this->conflictTypeLabels(),
            ])->render(),
            'total' => $conflicts->count(),
            'type_counts' => $typeCounts,
        ]);
    }

    private function conflictGroupsFromSummaries(Collection $summaries): Collection
    {
        return $summaries
            ->groupBy(fn (array $summary) => (int) $summary['schedule']->course_offering_id)
            ->map(function (Collection $offeringSummaries) {
                $firstSummary = $offeringSummaries->first();
                /** @var Schedule $firstSchedule */
                $firstSchedule = $firstSummary['schedule'];

                return [
                    'offering' => $firstSchedule->courseOffering,
                    'schedules' => $offeringSummaries->values(),
                    'conflict_count' => $offeringSummaries->sum(fn (array $summary) => (int) $summary['conflict_count']),
                ];
            })
            ->values();
    }

    private function conflictTypeLabels(): array
    {
        return [
            'instructor_overlap' => 'ผู้สอนชน',
            'room_overlap' => 'ห้อง/สถานที่ชน',
            'group_overlap' => 'กลุ่มนักศึกษาชน',
        ];
    }

    private function conflictGroupsFromStoredResults(Collection $storedResults): array
    {
        $conflictMap = collect();
        $schedules = collect();

        foreach ($storedResults as $result) {
            /** @var Schedule|null $schedule */
            $schedule = $result->getRelation('sourceSchedule');

            if (! $schedule) {
                continue;
            }

            $schedules->put($schedule->id, $schedule);
            $conflicts = $conflictMap->get($schedule->id, collect());
            $conflicts->push([
                'type' => $result->conflict_type,
                'message' => $result->message,
                'schedule_id' => (int) $result->conflicting_schedule_id,
            ]);
            $conflictMap->put($schedule->id, $conflicts);
        }

        $conflictGroups = $schedules
            ->values()
            ->groupBy('course_offering_id')
            ->map(function (Collection $offeringSchedules) use ($conflictMap) {
                /** @var Schedule $firstSchedule */
                $firstSchedule = $offeringSchedules->first();

                return [
                    'offering' => $firstSchedule->courseOffering,
                    'schedules' => $offeringSchedules->values(),
                    'conflict_count' => $offeringSchedules->sum(
                        fn (Schedule $schedule) => $conflictMap->get($schedule->id, collect())->count()
                    ),
                ];
            })
            ->values();

        return [$conflictGroups, $conflictMap];
    }

    /**
     * @return Collection<int, AcademicYear>
     */
    /**
     * Empty-state key สำหรับหน้าแจ้งเตือนการชน
     * 'ready' → 'no_conflicts' (มี offering แต่ไม่พบการชน)
     */
    private function conflictEmptyStateKey(Collection $availableYears): string
    {
        $key = \App\Support\CoordinatorEmptyState::forCoordinator((int) Auth::id());

        return $key === \App\Support\CoordinatorEmptyState::READY ? 'no_conflicts' : $key;
    }

    private function coordinatorAcademicYears(int $userId): Collection
    {
        return AcademicYear::query()
            ->select(['id', 'name', 'semester', 'start_date', 'end_date', 'is_active', 'phase'])
            ->whereIn('id', CourseOffering::query()
                ->select('academic_year_id')
                ->withActiveCourse()
                ->where('coordinator_id', $userId))
            ->orderByDesc('start_date')
            ->orderByDesc('semester')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  Collection<int, AcademicYear>  $availableYears
     */
    private function selectedConflictAcademicYear(
        Request $request,
        Collection $availableYears,
        ?AcademicYear $defaultAcademicYear
    ): ?AcademicYear
    {
        if ($availableYears->isEmpty()) {
            return null;
        }

        $requestedYearId = (int) $request->query('academic_year_id', 0);

        if ($requestedYearId > 0) {
            $requestedYear = $availableYears->firstWhere('id', $requestedYearId);

            if ($requestedYear) {
                return $requestedYear;
            }
        }

        return $defaultAcademicYear;
    }

    /**
     * @param  Collection<int, AcademicYear>  $availableYears
     */
    private function defaultConflictAcademicYear(Collection $availableYears): ?AcademicYear
    {
        return $availableYears->firstWhere('phase', 'scheduling')
            ?: $availableYears->firstWhere('is_active', true)
            ?: $availableYears->first();
    }

    public function index(Request $request, CourseOffering $courseOffering): View
    {
        $this->authorizeCourseHeadOffering($courseOffering);

        return view('course_head.schedules.index', $this->schedulePageData(
            request: $request,
            courseOffering: $courseOffering,
            isWorkspace: false,
            availableOfferings: $this->coordinatorScheduleOfferings(includeSchedulingData: false),
        ));
    }

    public function createGlobal(Request $request): View|RedirectResponse
    {
        $offerings = $this->coordinatorScheduleOfferings()
            ->filter(fn (CourseOffering $offering) => $offering->academicYear?->phase === 'scheduling')
            ->values();

        if ($offerings->isEmpty()) {
            return redirect()
                ->route('maker.schedules.index')
                ->withErrors(['schedule' => 'ยังไม่มีรายวิชาที่ต้องจัดตาราง']);
        }

        $selectedOfferingId = (int) old('course_offering_id', $request->query('course_offering_id'));
        $targetOffering = $selectedOfferingId ? $offerings->firstWhere('id', $selectedOfferingId) : null;
        $targetOffering = $targetOffering ?: $offerings->first();

        return redirect()->route('maker.course_offerings.schedules.index', array_filter([
            'courseOffering' => $targetOffering,
            'modal' => 'create',
            'week_start' => $request->query('week_start'),
            'date' => $request->query('date'),
            'period' => $request->query('period'),
            'include_weekends' => $request->query('include_weekends'),
        ]));
    }

    public function storeGlobal(
        Request $request,
        ScheduleConflictChecker $conflictChecker
    ): RedirectResponse {
        $offerings = $this->coordinatorScheduleOfferings();
        $courseOffering = $offerings->firstWhere('id', (int) $request->input('course_offering_id'));

        if (! $courseOffering) {
            throw ValidationException::withMessages([
                'course_offering_id' => 'เลือกรายวิชาที่คุณรับผิดชอบ',
            ]);
        }

        return $this->storeForOffering($request, $courseOffering, $conflictChecker, true);
    }

    private function schedulePageData(
        Request $request,
        ?CourseOffering $courseOffering,
        bool $isWorkspace,
        Collection $availableOfferings
    ): array {
        $courseOffering?->load([
            'course.curriculum',
            'course.department',
            'academicYear',
            'instructorPool.instructorProfile.department',
            'studentGroups' => fn ($query) => $query->orderBy('group_code'),
        ]);
        $offeringIds = $isWorkspace
            ? $availableOfferings->pluck('id')->all()
            : ($courseOffering ? [$courseOffering->id] : []);

        $firstScheduleDate = empty($offeringIds)
            ? null
            : $this->firstScheduleDate($offeringIds);

        $period = $this->validSchedulePeriod($request);
        $includeWeekends = $request->boolean('include_weekends');
        $baseDate = $this->validScheduleDate($request, 'date')
            ?? $this->validWeekStart($request)
            ?? ($firstScheduleDate ? CarbonImmutable::parse($firstScheduleDate) : null)
            ?? ($courseOffering?->academicYear?->start_date ? CarbonImmutable::parse($courseOffering->academicYear->start_date) : null)
            ?? ($availableOfferings->first()?->academicYear?->start_date ? CarbonImmutable::parse($availableOfferings->first()->academicYear->start_date) : null)
            ?? CarbonImmutable::now();

        $periodStart = match ($period) {
            'day' => CarbonImmutable::parse($baseDate)->startOfDay(),
            'month' => CarbonImmutable::parse($baseDate)->startOfMonth(),
            default => CarbonImmutable::parse($baseDate)->startOfWeek(CarbonInterface::MONDAY),
        };
        $periodEnd = match ($period) {
            'day' => $periodStart,
            'month' => $periodStart->endOfMonth(),
            default => $periodStart->addDays(6),
        };
        $gridEnd = match ($period) {
            'day' => $periodStart,
            'month' => $periodEnd,
            default => $includeWeekends ? $periodEnd : $periodStart->addDays(4),
        };

        $scheduleRelations = $this->scheduleRelations();

        $schedules = empty($offeringIds)
            ? collect()
            : $this->orderSchedulesByDate(
                $this->filterSchedulesByDateRange(
                    Schedule::query()
                        ->with($scheduleRelations)
                        ->whereIn('course_offering_id', $offeringIds),
                    $periodStart->toDateString(),
                    $periodEnd->toDateString()
                )
            )->get();

        $allSchedules = empty($offeringIds)
            ? collect()
            : ($isWorkspace
                ? $schedules
                : $this->orderSchedulesByDate(
                    Schedule::query()
                        ->with($scheduleRelations)
                        ->whereIn('course_offering_id', $offeringIds)
                )->get());

        $totalScheduleCount = $isWorkspace
            ? (empty($offeringIds) ? 0 : Schedule::query()->whereIn('course_offering_id', $offeringIds)->count())
            : $allSchedules->count();

        $weekDays = collect(CarbonPeriod::create($periodStart, $gridEnd))
            ->map(fn ($date) => CarbonImmutable::parse($date))
            ->filter(fn (CarbonImmutable $date) => $period === 'day' || $includeWeekends || $date->dayOfWeekIso <= 5)
            ->values();
        $occurrences = $this->scheduleOccurrences($schedules, $periodStart, $gridEnd, $includeWeekends);
        $timeSlots = $this->scheduleTimeSlots($occurrences);
        $occurrencesByDate = $occurrences->groupBy(fn ($item) => $item['date']->toDateString());
        $groupedSchedules = $allSchedules->groupBy(fn (Schedule $schedule) => $schedule->start_date?->dayOfWeekIso);
        $selectedDate = CarbonImmutable::parse($baseDate)->startOfDay();
        $previousPeriod = match ($period) {
            'day' => $selectedDate->subDay(),
            'month' => $selectedDate->subMonthNoOverflow(),
            default => $selectedDate->subWeek(),
        };
        $nextPeriod = match ($period) {
            'day' => $selectedDate->addDay(),
            'month' => $selectedDate->addMonthNoOverflow(),
            default => $selectedDate->addWeek(),
        };

        return [
            'courseOffering' => $courseOffering,
            'availableOfferings' => $availableOfferings,
            'isWorkspace' => $isWorkspace,
            ...$this->scheduleDatePickerYearRange(),
            'schedules' => $schedules,
            'allSchedules' => $allSchedules,
            'totalScheduleCount' => $totalScheduleCount,
            'schedulePeriod' => $period,
            'includeWeekends' => $includeWeekends,
            'selectedScheduleDate' => $selectedDate,
            'weekStart' => $periodStart,
            'weekEnd' => $periodEnd,
            'weekDays' => $weekDays,
            'occurrences' => $occurrences,
            'occurrencesByDate' => $occurrencesByDate,
            'gridOccurrencesByDate' => $occurrencesByDate,
            'groupedSchedules' => $groupedSchedules,
            'scheduleConflicts' => $this->scheduleConflictMap($isWorkspace ? $schedules : $allSchedules),
            'timeSlots' => $timeSlots,
            'activityTypes' => app(ReferenceDataCache::class)->activityTypes(),
            'rooms' => app(ReferenceDataCache::class)->activeRooms(),
            'dayViewUrl' => $this->schedulePeriodUrl($courseOffering, $periodStart, $isWorkspace, 'day', $includeWeekends),
            'weekViewUrl' => $this->schedulePeriodUrl($courseOffering, $periodStart, $isWorkspace, 'week', $includeWeekends),
            'monthViewUrl' => $this->schedulePeriodUrl($courseOffering, $periodStart, $isWorkspace, 'month', $includeWeekends),
            'previousWeekUrl' => $this->schedulePeriodUrl($courseOffering, $previousPeriod, $isWorkspace, $period, $includeWeekends),
            'nextWeekUrl' => $this->schedulePeriodUrl($courseOffering, $nextPeriod, $isWorkspace, $period, $includeWeekends),
            'weekendToggleUrl' => $this->schedulePeriodUrl($courseOffering, $selectedDate, $isWorkspace, $period, $period === 'week' ? ! $includeWeekends : $includeWeekends),
            'coordinatorEmptyStateKey' => \App\Support\CoordinatorEmptyState::forCoordinator((int) Auth::id()),
        ];
    }

    private function scheduleDatePickerYearRange(): array
    {
        $years = AcademicYear::query()
            ->get(['name', 'start_date', 'end_date'])
            ->flatMap(function (AcademicYear $academicYear): array {
                $values = [];

                foreach (['start_date', 'end_date'] as $field) {
                    if ($academicYear->{$field}) {
                        $values[] = CarbonImmutable::parse($academicYear->{$field})->year;
                    }
                }

                if (is_numeric($academicYear->name)) {
                    $year = (int) $academicYear->name;
                    $values[] = $year >= 2400 ? $year - 543 : $year;
                }

                return $values;
            })
            ->filter()
            ->values();

        $currentYear = CarbonImmutable::now()->year;
        $minAcademicYear = (int) ($years->min() ?: $currentYear);
        $maxAcademicYear = (int) ($years->max() ?: $currentYear);

        return [
            'scheduleDatePickerYearStart' => min($minAcademicYear - 10, $currentYear - 20),
            'scheduleDatePickerYearEnd' => max($maxAcademicYear + 5, $currentYear + 1),
        ];
    }

    private function scheduleRelations(): array
    {
        return [
            'courseOffering.course.curriculum',
            'courseOffering.course.department',
            'courseOffering.academicYear',
            'courseOffering.instructorPool.instructorProfile.department',
            'courseOffering.studentGroups' => fn ($query) => $query->orderBy('group_code'),
            'activityType',
            'scheduleTemplate',
            'room.locationType',
            'instructors.instructorProfile.department',
            'studentGroups',
        ];
    }

    private function hasScheduleBlockDates(): bool
    {
        return Schema::hasColumn('schedules', 'start_date') && Schema::hasColumn('schedules', 'end_date');
    }

    private function firstScheduleDate(array $offeringIds): ?string
    {
        $column = $this->hasScheduleBlockDates() ? 'start_date' : 'teaching_date';

        return Schedule::query()
            ->whereIn('course_offering_id', $offeringIds)
            ->whereNotNull($column)
            ->orderBy($column)
            ->value($column);
    }

    private function filterSchedulesByDateRange($query, string $startDate, string $endDate)
    {
        if ($this->hasScheduleBlockDates()) {
            return $query
                ->whereDate('start_date', '<=', $endDate)
                ->whereDate('end_date', '>=', $startDate);
        }

        return $query
            ->whereDate('teaching_date', '>=', $startDate)
            ->whereDate('teaching_date', '<=', $endDate);
    }

    private function orderSchedulesByDate($query)
    {
        if ($this->hasScheduleBlockDates()) {
            return $query
                ->orderBy('start_date')
                ->orderBy('end_date')
                ->orderBy('start_time');
        }

        return $query
            ->orderBy('teaching_date')
            ->orderBy('start_time');
    }

    private function scheduleDatePayload(array $validated): array
    {
        if ($this->hasScheduleBlockDates()) {
            return [
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'teaching_date' => null,
            ];
        }

        return [
            'teaching_date' => $validated['start_date'],
        ];
    }

    private function scheduleConflictMap(Collection $schedules): Collection
    {
        if ($schedules->isEmpty()) {
            return collect();
        }

        if (! config('conflicts.async_reads')) {
            // Fallback: real-time scan (used when async_reads is disabled)
            return app(ScheduleConflictIndex::class)->conflictsFor($schedules);
        }

        $userId = (int) Auth::id();
        $repository = app(ScheduleConflictReadRepository::class);

        // Group by academic_year_id so workspace mode (multiple offerings) works correctly.
        // courseOffering.academicYear is already eager-loaded via scheduleRelations().
        $result = collect();

        foreach ($schedules->groupBy(fn (Schedule $s) => (int) ($s->courseOffering?->academic_year_id ?? 0)) as $academicYearId => $yearSchedules) {
            if (! $academicYearId) {
                continue;
            }

            $ids = $yearSchedules->pluck('id')->map(fn ($id) => (int) $id)->all();
            $conflictMap = $repository->getConflictMapForSchedules($ids, $userId, $academicYearId);
            $result = $result->union($conflictMap);
        }

        return $result;
    }

    /**
     * Build a conflict map for the OWNED schedules — includes cross-course conflicts
     * (owned vs. anyone else's overlapping schedule). Single fetch of all overlapping
     * schedules in the relevant date range, then in-memory pairwise comparison.
     */
    private function buildOwnedConflictMap(Collection $ownedSchedules, ScheduleConflictChecker $conflictChecker): Collection
    {
        if ($ownedSchedules->isEmpty()) {
            return collect();
        }

        $ownedIds = $ownedSchedules->pluck('id')->map(fn ($v) => (int) $v)->all();
        $minStart = $ownedSchedules->pluck('start_date')->filter()->min();
        $maxEnd   = $ownedSchedules->pluck('end_date')->filter()->max();

        if (! $minStart || ! $maxEnd) {
            return collect();
        }

        // Fetch all schedules system-wide that could overlap any owned schedule's date window.
        // Reuse owned schedules' loaded relations to avoid re-querying them.
        $otherSchedules = Schedule::query()
            ->with($this->scheduleRelations())
            ->whereNotIn('id', $ownedIds)
            ->whereDate('start_date', '<=', $maxEnd)
            ->whereDate('end_date', '>=', $minStart)
            ->get();

        $candidates = $ownedSchedules->concat($otherSchedules);

        // Bulk pairwise comparison; output map keyed by every schedule id involved.
        return $conflictChecker
            ->bulkConflictMap($candidates)
            ->only($ownedIds);
    }

    private function scheduleTimeSlots(Collection $occurrences): array
    {
        return collect(range(6, 16))
            ->map(fn (int $hour) => sprintf('%02d:00', $hour))
            ->merge($occurrences->pluck('time_slot'))
            ->filter()
            ->unique()
            ->sortBy(fn (string $slot) => (int) substr($slot, 0, 2))
            ->values()
            ->all();
    }

    private function validWeekStart(Request $request): ?CarbonImmutable
    {
        return $this->validScheduleDate($request, 'week_start');
    }

    private function validScheduleDate(Request $request, string $key): ?CarbonImmutable
    {
        $value = $request->query($key);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }

        return $date instanceof CarbonImmutable && $date->format('Y-m-d') === $value
            ? $date
            : null;
    }

    private function validSchedulePeriod(Request $request): string
    {
        $period = $request->query('period');

        return in_array($period, ['day', 'week', 'month'], true) ? $period : 'week';
    }

    private function coordinatorScheduleOfferingRedirectTarget(Request $request): ?int
    {
        $query = CourseOffering::query()
            ->withActiveCourse()
            ->where('coordinator_id', Auth::id());

        $selectedId = (int) $request->query('course_offering_id');

        if ($selectedId && (clone $query)->whereKey($selectedId)->exists()) {
            return $selectedId;
        }

        return $this->coordinatorScheduleOfferings(includeSchedulingData: false)
            ->first()?->id;
    }

    private function coordinatorScheduleOfferings(bool $includeSchedulingData = true): Collection
    {
        $relations = [
            'course.curriculum',
            'course.department',
            'academicYear',
        ];

        if ($includeSchedulingData) {
            $relations = [
                ...$relations,
                'instructorPool.instructorProfile.department',
                'studentGroups' => fn ($query) => $query->orderBy('group_code'),
            ];
        }

        return CourseOffering::query()
            ->with($relations)
            ->withCount(['schedules', 'studentGroups', 'instructorPool'])
            ->withActiveCourse()
            ->where('coordinator_id', Auth::id())
            ->get()
            ->sortBy(fn (CourseOffering $offering) => implode('|', [
                $offering->academicYear?->phase === 'scheduling' ? '0' : '1',
                mb_strtolower($offering->course?->course_code ?? ''),
                str_pad((string) $offering->id, 10, '0', STR_PAD_LEFT),
            ]), SORT_NATURAL)
            ->values();
    }

    private function scheduleWeekUrl(?CourseOffering $courseOffering, CarbonImmutable $weekStart, bool $isWorkspace): string
    {
        return $this->schedulePeriodUrl($courseOffering, $weekStart, $isWorkspace, 'week');
    }

    private function schedulePeriodUrl(
        ?CourseOffering $courseOffering,
        CarbonImmutable $date,
        bool $isWorkspace,
        string $period,
        bool $includeWeekends = false
    ): string
    {
        $keepWeekendParam = $period === 'week' && $includeWeekends;

        if ($isWorkspace || ! $courseOffering) {
            return route('maker.schedules.index', array_filter([
                'course_offering_id' => $courseOffering?->id,
                'week_start' => $date->toDateString(),
                'date' => $date->toDateString(),
                'period' => $period,
                'include_weekends' => $keepWeekendParam ? 1 : null,
            ]));
        }

        return route('maker.course_offerings.schedules.index', array_filter([
            $courseOffering,
            'week_start' => $date->toDateString(),
            'date' => $date->toDateString(),
            'period' => $period,
            'include_weekends' => $keepWeekendParam ? 1 : null,
        ]));
    }

    public function create(Request $request, CourseOffering $courseOffering): View|RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        $courseOffering->load('academicYear');

        if ($courseOffering->academicYear?->phase !== 'scheduling') {
            return redirect()
                ->route('maker.course_offerings.schedules.index', $courseOffering)
                ->withErrors(['schedule' => 'ยังไม่เปิดช่วงจัดตาราง — Admin ต้องเปิดช่วงจัดตารางก่อน']);
        }

        return redirect()->route('maker.course_offerings.schedules.index', array_filter([
            $courseOffering,
            'modal' => 'create',
            'week_start' => $request->query('week_start'),
        ]));
    }

    public function store(
        Request $request,
        CourseOffering $courseOffering,
        ScheduleConflictChecker $conflictChecker
    ): RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);

        return $this->storeForOffering($request, $courseOffering, $conflictChecker);
    }

    private function storeForOffering(
        Request $request,
        CourseOffering $courseOffering,
        ScheduleConflictChecker $conflictChecker,
        bool $redirectToWorkspace = false
    ): RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering, $redirectToWorkspace)) return $redirect;

        $validated = $this->validateSchedule($request, $courseOffering);
        $validated['course_offering_id'] = $courseOffering->id;
        $this->assertScheduleWithinAcademicYear($courseOffering, $validated);
        $this->assertSelectedGroupsFitCapacity($courseOffering, $validated);
        $this->assertInstructorsBelongToCourseDepartment($courseOffering, $validated['instructor_ids']);
        $this->assertLeadInstructorSelected($validated);
        $conflicts = $this->detectConflicts($conflictChecker, $validated);

        $schedule = null;

        DB::transaction(function () use ($courseOffering, $validated, &$schedule): void {
            $schedule = Schedule::create([
                'course_offering_id' => $courseOffering->id,
                'activity_type_id' => $validated['activity_type_id'],
                'room_id' => $validated['room_id'] ?? null,
                'practicum_series_id' => null,
                ...$this->scheduleDatePayload($validated),
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'topic' => $validated['topic'] ?? null,
                'capacity_required' => $validated['capacity_required'] ?? null,
                'sub_group_label' => $validated['sub_group_label'] ?? null,
                'status' => 'draft',
                'remark' => $validated['remark'] ?? null,
            ]);

            $this->syncInstructors($schedule, $validated);
            $schedule->studentGroups()->sync($validated['student_group_ids']);
        });

        \App\Services\NavigationBadgeService::flushCourseHead((int) $courseOffering->coordinator_id);
        app(ScheduleConflictInvalidationService::class)->markScheduleDirty($schedule, 'pivot');

        AuditLogger::log(
            action: 'ตารางสอน.สร้าง',
            table: 'schedules',
            recordId: $schedule->id,
            oldValues: null,
            newValues: $this->scheduleSnapshot($schedule->fresh([
                'courseOffering.course.curriculum',
                'courseOffering.academicYear',
                'activityType',
                'room',
                'instructors',
                'studentGroups',
            ])),
            category: 'ตารางสอน',
            description: "สร้างตารางสอน: {$schedule->topic}",
        );

        return redirect()
            ->to($this->scheduleReturnUrl($request, $courseOffering, $validated['start_date'], $redirectToWorkspace))
            ->with('success', 'เพิ่มรายการสอนเรียบร้อยแล้ว')
            ->with('schedule_conflict_warning', collect($conflicts)->pluck('message')->unique()->values()->all());
    }

    public function storeSeries(
        Request $request,
        CourseOffering $courseOffering,
        ScheduleSeriesGenerator $seriesGenerator
    ): RedirectResponse {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering)) return $redirect;

        $validated = $this->validateScheduleSeries($request, $courseOffering);
        $this->assertSelectedGroupsFitCapacity($courseOffering, $validated);
        $this->assertInstructorsBelongToCourseDepartment($courseOffering, $validated['instructor_ids']);
        $this->assertLeadInstructorSelected($validated);

        $template = null;
        $instances = collect();

        DB::transaction(function () use ($courseOffering, $validated, $seriesGenerator, &$template, &$instances): void {
            $template = ScheduleTemplate::create([
                'course_offering_id' => $courseOffering->id,
                'activity_type_id' => $validated['activity_type_id'],
                'weekday' => $validated['weekday'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'start_week' => $validated['start_week'],
                'end_week' => $validated['end_week'],
                'starts_on' => $validated['starts_on'] ?? null,
                'ends_on' => $validated['ends_on'] ?? null,
                'topic' => $validated['topic'] ?? null,
                'capacity_required' => $validated['capacity_required'] ?? null,
                'sub_group_label' => $validated['sub_group_label'] ?? null,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $instances = $seriesGenerator->generateFromTemplate($template, [
                'room_id' => $validated['room_id'] ?? null,
                'instructor_ids' => $validated['instructor_ids'] ?? [],
                'lead_instructor_id' => $validated['lead_instructor_id'] ?? null,
                'student_group_ids' => $validated['student_group_ids'] ?? [],
                'status' => 'draft',
                'remark' => $validated['remark'] ?? null,
                'check_conflicts' => true,
                'populate_resources' => 'first',
            ]);
        });

        \App\Services\NavigationBadgeService::flushCourseHead((int) $courseOffering->coordinator_id);
        if ($instances->isNotEmpty()) {
            app(ScheduleConflictInvalidationService::class)->markScheduleDirty($instances->first(), 'pivot');
        }

        AuditLogger::log(
            action: 'schedule.series.create',
            table: 'schedule_templates',
            recordId: $template?->id,
            oldValues: null,
            newValues: [
                'course_offering_id' => $courseOffering->id,
                'activity_type_id' => $validated['activity_type_id'],
                'weekday' => $validated['weekday'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'start_week' => $validated['start_week'],
                'end_week' => $validated['end_week'],
                'instance_count' => $instances->count(),
            ],
            category: 'schedule',
            description: 'Create weekly schedule series: ' . ($validated['topic'] ?? '-'),
        );

        return redirect()
            ->to($this->scheduleReturnUrl($request, $courseOffering, $instances->first()?->start_date?->toDateString()))
            ->with('success', "Created {$instances->count()} weekly schedule items.");
    }

    public function updateSeriesTemplate(
        Request $request,
        CourseOffering $courseOffering,
        ScheduleTemplate $scheduleTemplate,
        ScheduleSeriesGenerator $seriesGenerator
    ): RedirectResponse {
        $this->authorizeCourseHeadOffering($courseOffering);
        abort_unless((int) $scheduleTemplate->course_offering_id === (int) $courseOffering->id, 404);
        if ($redirect = $this->requireSchedulingPhase($courseOffering)) return $redirect;

        $validated = $this->validateScheduleSeriesTemplate($request, $courseOffering);
        $snapshotBefore = $scheduleTemplate->only([
            'activity_type_id',
            'weekday',
            'start_time',
            'end_time',
            'start_week',
            'end_week',
            'starts_on',
            'ends_on',
            'topic',
            'capacity_required',
            'sub_group_label',
        ]);

        $instances = DB::transaction(function () use ($scheduleTemplate, $validated, $seriesGenerator) {
            $scheduleTemplate->update([
                'activity_type_id' => $validated['activity_type_id'],
                'weekday' => $validated['weekday'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'start_week' => $validated['start_week'],
                'end_week' => $validated['end_week'],
                'starts_on' => $validated['starts_on'] ?? null,
                'ends_on' => $validated['ends_on'] ?? null,
                'topic' => $validated['topic'] ?? null,
                'capacity_required' => $validated['capacity_required'] ?? null,
                'sub_group_label' => $validated['sub_group_label'] ?? null,
                'updated_by' => Auth::id(),
            ]);

            return $seriesGenerator->syncInstancesFromTemplate($scheduleTemplate);
        });

        \App\Services\NavigationBadgeService::flushCourseHead((int) $courseOffering->coordinator_id);
        if ($instances->isNotEmpty()) {
            app(ScheduleConflictInvalidationService::class)->markScheduleDirty($instances->first(), 'pivot');
        }

        $snapshotAfter = $scheduleTemplate->fresh()->only(array_keys($snapshotBefore));
        $diff = AuditLogger::diff($snapshotBefore, $snapshotAfter);

        if (! empty($diff['old']) || ! empty($diff['new'])) {
            AuditLogger::log(
                action: 'schedule.series.update',
                table: 'schedule_templates',
                recordId: $scheduleTemplate->id,
                oldValues: $diff['old'],
                newValues: $diff['new'],
                category: 'schedule',
                description: 'Update weekly schedule series: ' . ($snapshotAfter['topic'] ?? '-'),
            );
        }

        return redirect()
            ->to($this->scheduleReturnUrl($request, $courseOffering, $instances->first()?->start_date?->toDateString()))
            ->with('success', "Updated {$instances->count()} weekly schedule items.");
    }

    public function edit(CourseOffering $courseOffering, Schedule $schedule): View|RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        $this->assertScheduleBelongsToOffering($courseOffering, $schedule);

        if ($redirect = $this->requireSchedulingPhase($courseOffering)) return $redirect;

        return redirect()->route('maker.course_offerings.schedules.index', [
            $courseOffering,
            'edit_schedule_id' => $schedule->id,
            'week_start' => $schedule->start_date?->toDateString(),
        ]);
    }

    public function update(
        Request $request,
        CourseOffering $courseOffering,
        Schedule $schedule,
        ScheduleConflictChecker $conflictChecker
    ): RedirectResponse {
        $this->authorizeCourseHeadOffering($courseOffering);
        $this->assertScheduleBelongsToOffering($courseOffering, $schedule);
        if ($redirect = $this->requireSchedulingPhase($courseOffering)) return $redirect;

        $validated = $this->validateSchedule($request, $courseOffering, (bool) $schedule->schedule_template_id);
        $validated['course_offering_id'] = $courseOffering->id;

        if ($schedule->schedule_template_id) {
            $validated = $this->preserveSeriesInstanceTemplateFields($schedule, $validated);
        }

        $this->assertScheduleWithinAcademicYear($courseOffering, $validated);
        $this->assertSelectedGroupsFitCapacity($courseOffering, $validated);
        $this->assertInstructorsBelongToCourseDepartment($courseOffering, $validated['instructor_ids']);
        $this->assertLeadInstructorSelected($validated);

        $conflicts = $this->detectConflicts($conflictChecker, $validated, $schedule->id);

        // Snapshot before for diffing
        $schedule->loadMissing([
            'courseOffering.course.curriculum',
            'courseOffering.academicYear',
            'activityType',
            'room',
            'instructors',
            'studentGroups',
        ]);
        $snapshotBefore = $this->scheduleSnapshot($schedule);

        DB::transaction(function () use ($schedule, $validated): void {
            $schedule->update([
                'activity_type_id' => $validated['activity_type_id'],
                'room_id' => $validated['room_id'] ?? null,
                ...$this->scheduleDatePayload($validated),
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'topic' => $validated['topic'] ?? null,
                'capacity_required' => $validated['capacity_required'] ?? null,
                'sub_group_label' => $validated['sub_group_label'] ?? null,
                'remark' => $validated['remark'] ?? null,
            ]);

            $this->syncInstructors($schedule, $validated);
            $schedule->studentGroups()->sync($validated['student_group_ids']);
        });

        \App\Services\NavigationBadgeService::flushCourseHead((int) $courseOffering->coordinator_id);
        app(ScheduleConflictInvalidationService::class)->markScheduleDirty($schedule, 'pivot');

        $snapshotAfter = $this->scheduleSnapshot($schedule->fresh([
            'courseOffering.course.curriculum',
            'courseOffering.academicYear',
            'activityType',
            'room',
            'instructors',
            'studentGroups',
        ]));

        $diff = AuditLogger::diff($snapshotBefore, $snapshotAfter);

        if (! empty($diff['old']) || ! empty($diff['new'])) {
            AuditLogger::log(
                action: 'ตารางสอน.แก้ไข',
                table: 'schedules',
                recordId: $schedule->id,
                oldValues: $diff['old'],
                newValues: $diff['new'],
                category: 'ตารางสอน',
                description: "แก้ไขตารางสอน: {$schedule->topic}",
            );
        }

        return redirect()
            ->to($this->scheduleReturnUrl($request, $courseOffering, $validated['start_date']))
            ->with('success', 'อัปเดตรายการสอนเรียบร้อยแล้ว')
            ->with('schedule_conflict_warning', collect($conflicts)->pluck('message')->unique()->values()->all());
    }

    public function destroy(Request $request, CourseOffering $courseOffering, Schedule $schedule): RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        $this->assertScheduleBelongsToOffering($courseOffering, $schedule);
        if ($redirect = $this->requireSchedulingPhase($courseOffering)) return $redirect;

        if ($schedule->schedule_template_id && in_array($request->input('series_delete_scope'), ['all', 'from_current'], true)) {
            return $this->destroyScheduleSeriesFromSchedule($request, $courseOffering, $schedule);
        }

        $schedule->loadMissing([
            'courseOffering.course.curriculum',
            'courseOffering.academicYear',
            'activityType',
            'room',
            'instructors',
            'studentGroups',
        ]);
        $snapshot = $this->scheduleSnapshot($schedule);
        $scheduleId = $schedule->id;

        $schedule->delete();

        \App\Services\NavigationBadgeService::flushCourseHead((int) $courseOffering->coordinator_id);

        AuditLogger::log(
            action: 'ตารางสอน.ลบ',
            table: 'schedules',
            recordId: $scheduleId,
            oldValues: $snapshot,
            newValues: null,
            category: 'ตารางสอน',
            description: "ลบตารางสอน: {$snapshot['topic']}",
        );

        return redirect()
            ->to($this->scheduleReturnUrl($request, $courseOffering))
            ->with('warning', 'ลบรายการสอนเรียบร้อยแล้ว');
    }

    private function destroyScheduleSeriesFromSchedule(
        Request $request,
        CourseOffering $courseOffering,
        Schedule $schedule
    ): RedirectResponse {
        $schedule->loadMissing('scheduleTemplate');
        $scheduleTemplate = $schedule->scheduleTemplate;
        abort_unless($scheduleTemplate && (int) $scheduleTemplate->course_offering_id === (int) $courseOffering->id, 404);

        $validated = $request->validate([
            'series_delete_scope' => ['required', Rule::in(['all', 'from_current'])],
        ]);

        return $this->deleteSeriesTemplateSchedules(
            $request,
            $courseOffering,
            $scheduleTemplate,
            $validated['series_delete_scope'],
            $schedule->series_week_number ? (int) $schedule->series_week_number : null
        );
    }

    public function destroySeriesTemplate(
        Request $request,
        CourseOffering $courseOffering,
        ScheduleTemplate $scheduleTemplate
    ): RedirectResponse {
        $this->authorizeCourseHeadOffering($courseOffering);
        abort_unless((int) $scheduleTemplate->course_offering_id === (int) $courseOffering->id, 404);
        if ($redirect = $this->requireSchedulingPhase($courseOffering)) return $redirect;

        $validated = $request->validate([
            'delete_scope' => ['required', Rule::in(['all', 'from_current'])],
            'current_week' => ['nullable', 'integer', 'min:1'],
        ]);

        return $this->deleteSeriesTemplateSchedules(
            $request,
            $courseOffering,
            $scheduleTemplate,
            $validated['delete_scope'],
            isset($validated['current_week']) ? (int) $validated['current_week'] : null
        );
    }

    private function deleteSeriesTemplateSchedules(
        Request $request,
        CourseOffering $courseOffering,
        ScheduleTemplate $scheduleTemplate,
        string $scope,
        ?int $currentWeek
    ): RedirectResponse {
        $scheduleTemplate->loadMissing(['activityType']);
        $snapshot = $scheduleTemplate->only([
            'course_offering_id',
            'activity_type_id',
            'weekday',
            'start_time',
            'end_time',
            'start_week',
            'end_week',
            'starts_on',
            'ends_on',
            'topic',
            'capacity_required',
            'sub_group_label',
        ]);

        $deleteAll = $scope === 'all'
            || $currentWeek === null
            || $currentWeek <= (int) $scheduleTemplate->start_week;

        $deletedCount = 0;

        DB::transaction(function () use ($scheduleTemplate, $deleteAll, $currentWeek, &$deletedCount): void {
            $query = $scheduleTemplate->schedules()->orderBy('series_week_number');

            if (! $deleteAll) {
                $query->where('series_week_number', '>=', $currentWeek);
            }

            $schedules = $query->get();
            $deletedCount = $schedules->count();
            $schedules->each->delete();

            if ($deleteAll) {
                $scheduleTemplate->delete();
                return;
            }

            $scheduleTemplate->update([
                'end_week' => max((int) $scheduleTemplate->start_week, $currentWeek - 1),
                'updated_by' => Auth::id(),
            ]);
        });

        \App\Services\NavigationBadgeService::flushCourseHead((int) $courseOffering->coordinator_id);

        AuditLogger::log(
            action: $deleteAll ? 'schedule.series.delete' : 'schedule.series.delete_from_week',
            table: 'schedule_templates',
            recordId: $scheduleTemplate->id,
            oldValues: $snapshot,
            newValues: [
                'deleted_schedule_count' => $deletedCount,
                'delete_scope' => $scope,
                'current_week' => $currentWeek,
            ],
            category: 'schedule',
            description: ($deleteAll ? 'Delete weekly schedule series: ' : 'Delete weekly schedule series from week: ')
                . ($snapshot['topic'] ?? '-'),
        );

        $message = $deleteAll
            ? "ลบชุดทำซ้ำทั้งหมด {$deletedCount} รายการแล้ว"
            : "ลบรายการตั้งแต่สัปดาห์ {$currentWeek} เป็นต้นไป {$deletedCount} รายการแล้ว";

        return redirect()
            ->to($this->scheduleReturnUrl($request, $courseOffering))
            ->with('warning', $message);
    }

    private function authorizeCourseHeadOffering(CourseOffering $courseOffering): void
    {
        $courseOffering->loadMissing('course');
        abort_unless((int) $courseOffering->coordinator_id === (int) Auth::id(), 403);
        abort_unless($courseOffering->course?->status === 'active', 403);
    }

    private function assertScheduleBelongsToOffering(CourseOffering $courseOffering, Schedule $schedule): void
    {
        abort_unless((int) $schedule->course_offering_id === (int) $courseOffering->id, 404);
    }

    private function requireSchedulingPhase(CourseOffering $courseOffering, bool $redirectToWorkspace = false): ?RedirectResponse
    {
        $courseOffering->loadMissing('academicYear');

        if ($courseOffering->academicYear?->phase !== 'scheduling') {
            return redirect()
                ->route('maker.course_offerings.schedules.index', $courseOffering)
                ->withErrors(['schedule' => 'ยังไม่เปิดช่วงจัดตาราง — Admin ต้องเปิดช่วงจัดตารางก่อน']);
        }

        return null;
    }

    private function workspaceRedirectUrl(CourseOffering $courseOffering, string $date): string
    {
        $weekStart = CarbonImmutable::parse($date)->startOfWeek(CarbonInterface::MONDAY);

        return route('maker.course_offerings.schedules.index', [
            $courseOffering,
            'week_start' => $weekStart->toDateString(),
        ]);
    }

    private function scheduleReturnUrl(
        Request $request,
        CourseOffering $courseOffering,
        ?string $date = null,
        bool $redirectToWorkspace = false
    ): string {
        $returnUrl = (string) $request->input('return_url', '');

        if ($request->boolean('return_to_conflicts')) {
            return route('maker.schedule_conflicts.index');
        }

        if ($this->isScheduleReturnUrl($request, $returnUrl)) {
            return $returnUrl;
        }

        if ($redirectToWorkspace && $date) {
            return $this->workspaceRedirectUrl($courseOffering, $date);
        }

        return route('maker.course_offerings.schedules.index', $courseOffering);
    }

    private function isScheduleReturnUrl(Request $request, string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $parts = parse_url($url);
        $requestHost = $request->getHost();
        $host = $parts['host'] ?? $requestHost;
        $path = $parts['path'] ?? '';

        return $host === $requestHost
            && (str_starts_with($path, '/maker/course-offerings/') || $path === '/maker/schedules');
    }

    private function scheduleOccurrences($schedules, CarbonImmutable $weekStart, CarbonImmutable $weekEnd, bool $includeWeekends = false)
    {
        return $schedules
            ->flatMap(function (Schedule $schedule) use ($weekStart, $weekEnd, $includeWeekends) {
                $startDate = CarbonImmutable::parse($schedule->start_date ?? $schedule->teaching_date);
                $endDate = CarbonImmutable::parse($schedule->end_date ?? $schedule->teaching_date);
                $rangeStart = $startDate->greaterThan($weekStart) ? $startDate : $weekStart;
                $rangeEnd = $endDate->lessThan($weekEnd) ? $endDate : $weekEnd;

                return collect(CarbonPeriod::create($rangeStart, $rangeEnd))
                    ->filter(fn ($date) => $includeWeekends || $date->dayOfWeekIso <= 5)
                    ->map(fn ($date) => [
                        'schedule' => $schedule,
                        'date' => CarbonImmutable::parse($date),
                        'duration_minutes' => $this->durationMinutes($schedule),
                        'time_slot' => substr((string) $schedule->start_time, 0, 2) . ':00',
                    ]);
            })
            // Laravel 13: sortBy([closure, closure, ...]) เรียงผิด — ใช้ closure เดียวสร้าง composite key
            ->sortBy(fn ($item) => $item['date']->toDateString()
                . ' ' . substr((string) $item['schedule']->start_time, 0, 8)
                . ' ' . str_pad((string) $item['schedule']->id, 10, '0', STR_PAD_LEFT))
            ->values();
    }

    private function durationMinutes(Schedule $schedule): int
    {
        $start = CarbonImmutable::createFromFormat('H:i:s', strlen((string) $schedule->start_time) === 5 ? $schedule->start_time . ':00' : (string) $schedule->start_time);
        $end = CarbonImmutable::createFromFormat('H:i:s', strlen((string) $schedule->end_time) === 5 ? $schedule->end_time . ':00' : (string) $schedule->end_time);

        return (int) max(0, $start->diffInMinutes($end));
    }

    private function validateSchedule(Request $request, CourseOffering $courseOffering, bool $allowEmptyResources = false): array
    {
        // ฟอร์มส่งวันที่เป็น วว/ดด/พ.ศ. (x-thai-date-input) — normalize เป็น ISO ก่อน validate
        foreach (['start_date', 'end_date'] as $dateField) {
            $iso = ThaiDate::parseToIso($request->input($dateField));
            if ($iso !== null) {
                $request->merge([$dateField => $iso]);
            }
        }

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'activity_type_id' => ['required', 'integer', 'exists:activity_types,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'topic' => ['required', 'string', 'max:255'],
            'remark' => ['nullable', 'string'],
            'capacity_required' => ['nullable', 'integer', 'min:1'],
            'sub_group_label' => ['nullable', 'string', 'max:20'],
            'lead_instructor_id' => ['nullable', 'integer'],
            'instructor_ids' => $allowEmptyResources ? ['nullable', 'array'] : ['required', 'array', 'min:1'],
            'instructor_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('course_offering_instructors', 'user_id')
                    ->where(fn ($query) => $query->where('course_offering_id', $courseOffering->id)),
            ],
            'student_group_ids' => $allowEmptyResources ? ['nullable', 'array'] : ['required', 'array', 'min:1'],
            'student_group_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('student_groups', 'id')
                    ->where(fn ($query) => $query->where('course_offering_id', $courseOffering->id)),
            ],
        ], [
            'end_date.after_or_equal' => 'วันที่สิ้นสุดต้องไม่อยู่ก่อนวันที่เริ่ม',
            'end_time.after' => 'เวลาสิ้นสุดต้องอยู่หลังเวลาเริ่ม',
            'start_time.date_format' => 'กรุณาระบุเวลาเริ่มในรูปแบบ 24 ชั่วโมง เช่น 10:00',
            'end_time.date_format' => 'กรุณาระบุเวลาสิ้นสุดในรูปแบบ 24 ชั่วโมง เช่น 12:00',
            'topic.required' => 'กรุณาระบุหัวข้อกิจกรรม',
            'instructor_ids.required' => 'กรุณาเลือกผู้สอนอย่างน้อย 1 คน',
            'student_group_ids.required' => 'กรุณาเลือกกลุ่มนักศึกษาอย่างน้อย 1 กลุ่ม',
        ], [
            'start_date' => 'วันที่เริ่ม',
            'end_date' => 'วันที่สิ้นสุด',
            'start_time' => 'เวลาเริ่ม',
            'end_time' => 'เวลาสิ้นสุด',
            'activity_type_id' => 'ประเภทกิจกรรม',
            'room_id' => 'ห้อง/สถานที่',
            'topic' => 'หัวข้อ',
            'remark' => 'หมายเหตุ',
            'capacity_required' => 'จำนวนรองรับ',
            'sub_group_label' => 'ป้ายกลุ่มย่อย',
            'lead_instructor_id' => 'ผู้สอนหลัก',
            'instructor_ids' => 'ผู้สอน',
            'instructor_ids.*' => 'ผู้สอน',
            'student_group_ids' => 'กลุ่มนักศึกษา',
            'student_group_ids.*' => 'กลุ่มนักศึกษา',
        ]);

        $validated['instructor_ids'] = array_values(array_unique(array_map('intval', $validated['instructor_ids'] ?? [])));
        $validated['student_group_ids'] = array_values(array_unique(array_map('intval', $validated['student_group_ids'] ?? [])));

        if (
            CarbonImmutable::parse($validated['start_date'])->isSameDay(CarbonImmutable::parse($validated['end_date']))
            && $validated['end_time'] <= $validated['start_time']
        ) {
            throw ValidationException::withMessages([
                'end_time' => 'เวลาสิ้นสุดต้องอยู่หลังเวลาเริ่มเมื่อจัดรายการในวันเดียวกัน',
            ]);
        }

        return $validated;
    }

    private function preserveSeriesInstanceTemplateFields(Schedule $schedule, array $validated): array
    {
        $schedule->loadMissing(['instructors']);

        $validated['activity_type_id'] = $schedule->activity_type_id;
        $validated['start_date'] = $schedule->start_date?->toDateString() ?? $schedule->teaching_date?->toDateString();
        $validated['end_date'] = $schedule->end_date?->toDateString() ?? $schedule->teaching_date?->toDateString();
        $validated['start_time'] = substr((string) $schedule->start_time, 0, 5);
        $validated['end_time'] = substr((string) $schedule->end_time, 0, 5);
        $validated['topic'] = $schedule->topic;
        $validated['capacity_required'] = $schedule->capacity_required;
        $validated['sub_group_label'] = $schedule->sub_group_label;
        return $validated;
    }

    private function validateScheduleSeries(Request $request, CourseOffering $courseOffering): array
    {
        if (! $request->boolean('use_custom_series_range')) {
            $request->merge([
                'starts_on' => null,
                'ends_on' => null,
            ]);
        }

        foreach (['starts_on', 'ends_on'] as $dateField) {
            $iso = ThaiDate::parseToIso($request->input($dateField));
            if ($iso !== null) {
                $request->merge([$dateField => $iso]);
            }
        }

        $defaultWeeks = max(1, (int) ($courseOffering->teaching_weeks ?? 1));
        $request->merge([
            'start_week' => $request->input('start_week', 1),
            'end_week' => $request->input('end_week', $defaultWeeks),
        ]);

        $validated = $request->validate($this->scheduleSeriesValidationRules($courseOffering) + [
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'remark' => ['nullable', 'string'],
            'lead_instructor_id' => ['nullable', 'integer'],
            'instructor_ids' => ['nullable', 'array'],
            'instructor_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('course_offering_instructors', 'user_id')
                    ->where(fn ($query) => $query->where('course_offering_id', $courseOffering->id)),
            ],
            'student_group_ids' => ['nullable', 'array'],
            'student_group_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('student_groups', 'id')
                    ->where(fn ($query) => $query->where('course_offering_id', $courseOffering->id)),
            ],
        ]);

        $validated['instructor_ids'] = array_values(array_unique(array_map('intval', $validated['instructor_ids'] ?? [])));
        $validated['student_group_ids'] = array_values(array_unique(array_map('intval', $validated['student_group_ids'] ?? [])));

        $this->assertScheduleSeriesTimeOrder($validated);

        return $validated;
    }

    private function validateScheduleSeriesTemplate(Request $request, CourseOffering $courseOffering): array
    {
        foreach (['starts_on', 'ends_on'] as $dateField) {
            $iso = ThaiDate::parseToIso($request->input($dateField));
            if ($iso !== null) {
                $request->merge([$dateField => $iso]);
            }
        }

        $validated = $request->validate($this->scheduleSeriesValidationRules($courseOffering));
        $this->assertScheduleSeriesTimeOrder($validated);

        return $validated;
    }

    private function scheduleSeriesValidationRules(CourseOffering $courseOffering): array
    {
        $maxWeeks = max(1, (int) ($courseOffering->teaching_weeks ?? 52));

        return [
            'weekday' => ['required', 'integer', 'between:1,7'],
            'start_week' => ['required', 'integer', 'min:1', "max:{$maxWeeks}"],
            'end_week' => ['required', 'integer', 'min:1', "max:{$maxWeeks}", 'gte:start_week'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'activity_type_id' => ['required', 'integer', 'exists:activity_types,id'],
            'topic' => ['required', 'string', 'max:255'],
            'capacity_required' => ['nullable', 'integer', 'min:1'],
            'sub_group_label' => ['nullable', 'string', 'max:20'],
        ];
    }

    private function assertScheduleSeriesTimeOrder(array $validated): void
    {
        if (($validated['end_time'] ?? null) <= ($validated['start_time'] ?? null)) {
            throw ValidationException::withMessages([
                'end_time' => __('messages.end_time_after_start'),
            ]);
        }
    }

    private function assertScheduleWithinAcademicYear(CourseOffering $courseOffering, array $validated): void
    {
        $courseOffering->loadMissing('academicYear');
        $academicYear = $courseOffering->academicYear;

        if (! $academicYear?->start_date || ! $academicYear?->end_date) {
            return;
        }

        $scheduleStart = CarbonImmutable::parse($validated['start_date'])->startOfDay();
        $scheduleEnd = CarbonImmutable::parse($validated['end_date'])->startOfDay();
        $academicStart = CarbonImmutable::parse($academicYear->start_date)->startOfDay();
        $academicEnd = CarbonImmutable::parse($academicYear->end_date)->startOfDay();

        if ($scheduleStart->lt($academicStart) || $scheduleEnd->gt($academicEnd)) {
            throw ValidationException::withMessages([
                'schedule' => 'วันที่จัดรายการสอนต้องอยู่ในช่วงปีการศึกษาของรายวิชา ('
                    . ThaiDate::formatForInput($academicYear->start_date)
                    . ' - '
                    . ThaiDate::formatForInput($academicYear->end_date)
                    . ')',
            ]);
        }
    }

    private function assertSelectedGroupsFitCapacity(CourseOffering $courseOffering, array $validated): void
    {
        $capacity = $validated['capacity_required'] ?? null;

        if (! $capacity) {
            return;
        }

        $studentGroupIds = $validated['student_group_ids'] ?? [];
        if (empty($studentGroupIds)) {
            return;
        }

        $selectedStudentCount = (int) $courseOffering->studentGroups()
            ->whereIn('id', array_map('intval', $studentGroupIds))
            ->sum('student_count');

        if ($selectedStudentCount > (int) $capacity) {
            throw ValidationException::withMessages([
                'capacity_required' => "จำนวนผู้เรียนที่เลือก ({$selectedStudentCount} คน) เกินจำนวนที่รองรับ ({$capacity} คน)",
            ]);
        }
    }

    private function assertLeadInstructorSelected(array $validated): void
    {
        $leadId = $validated['lead_instructor_id'] ?? null;
        if (! $leadId) {
            return;
        }

        if (! in_array((int) $leadId, array_map('intval', $validated['instructor_ids']), true)) {
            throw ValidationException::withMessages([
                'lead_instructor_id' => 'ผู้สอนหลักต้องอยู่ในรายชื่อผู้สอนที่เลือก',
            ]);
        }
    }

    private function assertInstructorsBelongToCourseDepartment(CourseOffering $courseOffering, array $instructorIds): void
    {
        $courseOffering->loadMissing('course');
        $departmentId = $courseOffering->course?->department_id;

        if (! $departmentId) {
            return;
        }

        $allowedCount = $courseOffering->instructorPool()
            ->whereIn('users.id', array_map('intval', $instructorIds))
            ->whereHas('instructorProfile', fn ($query) => $query->where('department_id', $departmentId))
            ->count();

        if ($allowedCount !== count(array_unique(array_map('intval', $instructorIds)))) {
            throw ValidationException::withMessages([
                'instructor_ids' => 'เลือกได้เฉพาะผู้สอนในภาควิชาของรายวิชานี้',
            ]);
        }
    }

    private function detectConflicts(
        ScheduleConflictChecker $conflictChecker,
        array $validated,
        ?int $ignoreScheduleId = null
    ): array {
        return $conflictChecker->check(
            [
                'course_offering_id' => $validated['course_offering_id'] ?? null,
                ...Arr::only($validated, ['activity_type_id', 'start_date', 'end_date', 'start_time', 'end_time', 'room_id', 'sub_group_label']),
            ],
            array_map('intval', $validated['instructor_ids'] ?? []),
            array_map('intval', $validated['student_group_ids'] ?? []),
            $ignoreScheduleId
        );
    }

    private function syncInstructors(Schedule $schedule, array $validated): void
    {
        $leadId = isset($validated['lead_instructor_id']) ? (int) $validated['lead_instructor_id'] : null;
        $payload = collect($validated['instructor_ids'] ?? [])
            ->mapWithKeys(fn ($id) => [
                (int) $id => ['is_lead' => $leadId ? (int) $id === $leadId : false],
            ])
            ->all();

        $schedule->instructors()->sync($payload);
    }

    /**
     * Build a flat, serializable snapshot of a schedule for audit logging.
     * Relations must be loaded by caller before calling this.
     */
    private function scheduleSnapshot(Schedule $schedule): array
    {
        $co   = $schedule->courseOffering;
        $year = $co?->academicYear;

        $instructors = $schedule->relationLoaded('instructors')
            ? $schedule->instructors->map(fn ($u) => [
                'id'      => $u->id,
                'name'    => $u->name,
                'is_lead' => (bool) $u->pivot?->is_lead,
            ])->toArray()
            : [];

        $leadInstructor = collect($instructors)->firstWhere('is_lead', true);

        $groups = $schedule->relationLoaded('studentGroups')
            ? $schedule->studentGroups->map(fn ($g) => [
                'id'         => $g->id,
                'group_code' => $g->group_code,
            ])->toArray()
            : [];

        return [
            'course_offering_id' => $co?->id,
            'course_code'        => $co?->course?->course_code,
            'course_name_th'     => $co?->course?->name_th,
            'academic_year'      => $year?->name,
            'semester'           => $year?->semester,
            'start_date'         => $schedule->start_date?->toDateString(),
            'end_date'           => $schedule->end_date?->toDateString(),
            'start_time'         => (string) $schedule->start_time,
            'end_time'           => (string) $schedule->end_time,
            'activity_type'      => $schedule->activityType?->name,
            'room'               => $schedule->room?->room_code,
            'topic'              => $schedule->topic,
            'capacity_required'  => $schedule->capacity_required,
            'sub_group_label'    => $schedule->sub_group_label,
            'remark'             => $schedule->remark,
            'instructors'        => $instructors,
            'lead_instructor'    => $leadInstructor ? ['id' => $leadInstructor['id'], 'name' => $leadInstructor['name']] : null,
            'student_groups'     => $groups,
        ];
    }
}
