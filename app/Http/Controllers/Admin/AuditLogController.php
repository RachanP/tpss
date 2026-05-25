<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AuditLogger;
use App\Support\ThaiDate;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
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

        foreach (['date_from', 'date_to'] as $param) {
            if (! $request->filled($param)) {
                continue;
            }

            $date = ThaiDate::parseToIso($request->input($param));
            if (! $date) {
                continue;
            }

            $query->where(
                'created_at',
                $param === 'date_from' ? '>=' : '<=',
                $param === 'date_from' ? "{$date} 00:00:00" : "{$date} 23:59:59",
            );
        }

        $logs          = $query->paginate(25)->withQueryString();
        $categoryLabels = AuditLogger::CATEGORY_LABELS;
        $actionOptions = AuditLog::query()
            ->select('action')
            ->whereNotNull('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->map(fn (string $action) => Str::after($action, '.') ?: $action)
            ->unique()
            ->values()
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

        return view('admin.audit_logs.index', compact('logs', 'categoryLabels', 'actionOptions', 'dateFilterValues'));
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
