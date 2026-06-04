<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AuditLogger;
use App\Support\ThaiDate;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('user')->orderedForAudit();

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('actor')) {
            $actor = $request->actor;
            $query->whereHas('user', function ($userQuery) use ($actor) {
                $userQuery->where('name', 'like', '%' . $actor . '%')
                    ->orWhere('email', 'like', '%' . $actor . '%');
            });
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('action')) {
            $action = $request->action;
            $query->where(function ($actionQuery) use ($action) {
                $actionQuery->where('action', $action)
                    ->orWhere('action', 'like', '%.' . $action);
            });
        }

        [$dateFilters, $filterErrors] = $this->validateDateFilters($request);

        if ($filterErrors->isNotEmpty()) {
            return $this->invalidDateFilterResponse($request, $filterErrors);
        }

        foreach ($dateFilters as $param => $date) {
            $query->where(
                'created_at',
                $param === 'date_from' ? '>=' : '<=',
                $param === 'date_from' ? "{$date} 00:00:00" : "{$date} 23:59:59",
            );
        }

        $logs          = $query->paginate(25)->withQueryString();
        $categoryLabels = AuditLogger::CATEGORY_LABELS;
        $actionOptions = collect(AuditLogger::actionFilterLabels())
            ->map(fn (string $actionLabel) => [
                'value' => $actionLabel,
                'label' => $actionLabel,
            ])
            ->all();

        if ($request->query('partial') === 'table' || $request->ajax()) {
            return view('admin.audit_logs._table', compact('logs'));
        }

        $dateFilterValues = [
            'date_from' => $this->formatDateFilterInput($request->input('date_from')),
            'date_to' => $this->formatDateFilterInput($request->input('date_to')),
        ];
        $filterErrors = new MessageBag();

        return view('admin.audit_logs.index', compact('logs', 'categoryLabels', 'actionOptions', 'dateFilterValues', 'filterErrors'));
    }

    private function validateDateFilters(Request $request): array
    {
        $dates = [];
        $errors = new MessageBag();

        foreach (['date_from', 'date_to'] as $param) {
            if (! $request->filled($param)) {
                continue;
            }

            $date = ThaiDate::parseToIso($request->input($param));

            if (! $date) {
                $errors->add($param, 'กรุณากรอกวันที่ให้ถูกต้องในรูปแบบ วว/ดด/พ.ศ. เช่น 21/05/2569');
                continue;
            }

            $dates[$param] = $date;
        }

        return [$dates, $errors];
    }

    private function invalidDateFilterResponse(Request $request, MessageBag $filterErrors)
    {
        if ($request->query('partial') === 'table' || $request->ajax()) {
            return response()->json([
                'message' => 'รูปแบบวันที่ไม่ถูกต้อง',
                'errors' => $filterErrors->toArray(),
            ], 422);
        }

        $logs = $this->emptyLogsPaginator($request);
        $categoryLabels = AuditLogger::CATEGORY_LABELS;
        $actionOptions = collect(AuditLogger::actionFilterLabels())
            ->map(fn (string $actionLabel) => [
                'value' => $actionLabel,
                'label' => $actionLabel,
            ])
            ->all();
        $dateFilterValues = [
            'date_from' => $this->formatDateFilterInput($request->input('date_from')),
            'date_to' => $this->formatDateFilterInput($request->input('date_to')),
        ];

        return response()->view(
            'admin.audit_logs.index',
            compact('logs', 'categoryLabels', 'actionOptions', 'dateFilterValues', 'filterErrors'),
            422
        );
    }

    private function emptyLogsPaginator(Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 25, 1, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
    }

    private function formatDateFilterInput(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return ThaiDate::formatForInput($value);
    }
}
