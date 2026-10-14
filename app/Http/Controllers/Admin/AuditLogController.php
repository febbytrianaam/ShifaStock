<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'event' => ['nullable', 'string', 'max:80'],
            'user_id' => ['nullable', 'exists:users,id'],
            'auditable_type' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'export' => ['nullable', 'in:csv'],
        ]);

        $query = AuditLog::query()
            ->when($filters['event'] ?? null, fn ($query, $event) => $query->where('event', $event))
            ->when($filters['user_id'] ?? null, fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($filters['auditable_type'] ?? null, fn ($query, $type) => $query->where('auditable_type', $type))
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->where('created_at', '>=', Carbon::createFromFormat('Y-m-d', $date, 'Asia/Jakarta')->startOfDay()))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->where('created_at', '<=', Carbon::createFromFormat('Y-m-d', $date, 'Asia/Jakarta')->endOfDay()))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($q) => $q
                ->where('actor_name', 'like', '%'.$search.'%')
                ->orWhere('description', 'like', '%'.$search.'%')
                ->orWhere('event', 'like', '%'.$search.'%')
                ->orWhere('ip_address', 'like', '%'.$search.'%')))
            ->latest();

        if (($filters['export'] ?? null) === 'csv') {
            return response()->streamDownload(function () use ($query) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['Waktu', 'Event', 'Deskripsi', 'Pelaku', 'Role', 'Subjek', 'IP']);
                (clone $query)->chunk(500, function ($logs) use ($handle) {
                    foreach ($logs as $log) {
                        fputcsv($handle, [
                            $log->created_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
                            $log->event,
                            $log->description,
                            $log->actor_name,
                            $log->actor_role,
                            $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : '',
                            $log->ip_address,
                        ]);
                    }
                });
                fclose($handle);
            }, 'audit-log-'.now('Asia/Jakarta')->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
        }

        $logs = $query
            ->paginate(20)
            ->withQueryString();

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'filters' => $filters,
            'events' => AuditLog::query()->distinct()->orderBy('event')->pluck('event'),
            'actors' => User::whereIn('id', AuditLog::query()->whereNotNull('user_id')->distinct()->pluck('user_id'))->orderBy('name')->get(),
            'auditableTypes' => AuditLog::query()->whereNotNull('auditable_type')->distinct()->orderBy('auditable_type')->pluck('auditable_type'),
        ]);
    }
}
