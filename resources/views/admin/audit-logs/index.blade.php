<x-layouts.app title="Audit Log">
    <form method="GET" class="card mb-5 grid gap-3 p-4 md:grid-cols-[1fr_240px_auto]">
        <input name="search" value="{{ $filters['search'] ?? '' }}" class="field" placeholder="Cari aktivitas, pengguna, atau IP">
        <select name="event" class="field">
            <option value="">Semua event</option>
            @foreach($events as $event)
                <option value="{{ $event }}" @selected(($filters['event'] ?? '') === $event)>{{ $event }}</option>
            @endforeach
        </select>
        <button class="btn-secondary">Filter</button>
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="table-cell">Aktivitas</th>
                        <th class="table-cell">Pelaku</th>
                        <th class="table-cell">IP</th>
                        <th class="table-cell">Waktu</th>
                        <th class="table-cell text-right">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($logs as $log)
                        @php
                            $tone = match (true) {
                                str_contains($log->event, 'deactivated') => 'badge-danger',
                                str_contains($log->event, 'permissions') => 'badge-warn',
                                str_contains($log->event, 'login') => 'badge-ok',
                                default => 'badge-brand',
                            };
                        @endphp
                        <tr>
                            <td class="table-cell align-top">
                                <span class="badge {{ $tone }}">{{ $log->event }}</span>
                                <p class="mt-2 font-semibold text-slate-900">{{ $log->description }}</p>
                                @if($log->auditable_type)
                                    <p class="mt-1 font-mono text-[11px] text-slate-400">{{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}</p>
                                @endif
                            </td>
                            <td class="table-cell align-top">
                                <p class="font-semibold text-slate-800">{{ $log->actor_name ?? 'Sistem' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $log->actor_role ?? '-' }}</p>
                            </td>
                            <td class="table-cell align-top font-mono text-xs text-slate-600">{{ $log->ip_address ?? '-' }}</td>
                            <td class="table-cell align-top text-xs font-medium text-slate-600 tabular-nums">
                                {{ $log->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}
                            </td>
                            <td class="table-cell align-top text-right">
                                @if($log->metadata)
                                    <details class="inline-block text-left">
                                        <summary class="btn-secondary text-xs">Lihat</summary>
                                        <pre class="mt-2 max-h-72 w-[min(520px,80vw)] overflow-auto rounded-lg border border-slate-200 bg-slate-950 p-3 text-xs leading-relaxed text-slate-100">{{ json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </details>
                                @else
                                    <span class="text-xs text-slate-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="table-cell text-center text-sm text-slate-500">Belum ada aktivitas tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 p-4">{{ $logs->links() }}</div>
    </div>
</x-layouts.app>
