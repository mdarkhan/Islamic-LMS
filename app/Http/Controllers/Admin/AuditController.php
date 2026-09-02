<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $action = $request->query('action');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $logs = AuditLog::query()
            ->with('user')
            ->when($action, fn ($q) => $q->where('action', $action))
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $actions = AuditLog::query()->distinct()->orderBy('action')->pluck('action');

        return view('admin.audit.index', compact('logs', 'actions', 'action', 'dateFrom', 'dateTo'));
    }
}
