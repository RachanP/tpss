<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = AuditLog::with('user')->orderByDesc('created_at');

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

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
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

        return view('admin.audit_logs.index', compact('logs', 'categoryLabels', 'actionOptions'));
    }
}
