<x-layouts.app title="Audit Log">
    @php
        $control = 'field min-h-11 lg:min-h-[34px] lg:py-1.5';
    @endphp
    <form method="GET" class="mb-3.5 grid gap-2 sm:grid-cols-2 xl:flex xl:flex-wrap xl:items-center">
        <x-ui.search name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Cari aktivitas, pengguna, event, atau IP" label="Cari aktivitas" width="sm:col-span-2 xl:w-80" />
        <select name="event" class="{{ $control }} xl:w-48" aria-label="Event">
            <option value="">Semua event</option>
            @foreach($events as $event)
                <option value="{{ $event }}" @selected(($filters['event'] ?? '') === $event)>{{ $event }}</option>
            @endforeach
        </select>
        <select name="user_id" class="{{ $control }} xl:w-44" aria-label="Pelaku">
            <option value="">Semua pelaku</option>
            @foreach($actors as $actor)
                <option value="{{ $actor->id }}" @selected((string)($filters['user_id'] ?? '') === (string)$actor->id)>{{ $actor->name }}</option>
            @endforeach
        </select>
        <select name="auditable_type" class="{{ $control }} xl:w-44" aria-label="Subjek">
            <option value="">Semua subjek</option>
            @foreach($auditableTypes as $type)
                <option value="{{ $type }}" @selected(($filters['auditable_type'] ?? '') === $type)>{{ class_basename($type) }}</option>
            @endforeach
        </select>
        <label class="flex items-center gap-2 text-xs font-bold text-slate-600">Dari <input name="from" type="date" value="{{ $filters['from'] ?? '' }}" class="{{ $control }} xl:w-40"></label>
        <label class="flex items-center gap-2 text-xs font-bold text-slate-600">s.d. <input name="to" type="date" value="{{ $filters['to'] ?? '' }}" class="{{ $control }} xl:w-40"></label>
        <div class="flex gap-2 sm:col-span-2 xl:col-span-1">
            <button class="btn-secondary min-h-11 flex-1 lg:min-h-[34px] lg:py-1.5">Filter</button>
            <button class="btn-secondary min-h-11 flex-1 lg:min-h-[34px] lg:py-1.5" name="export" value="csv"><x-ui.icon name="download" /> CSV</button>
        </div>
    </form>

    <x-ui.section title="Riwayat aktivitas" icon="audit" tone="slate" flush
        subtitle="Login, perubahan pengguna, hak akses, dan transaksi stok, terbaru di atas."
        :meta="$logs->total().' aktivitas'">
        <div class="overflow-x-auto">
            <table class="block min-w-full text-[13px] md:table">
                <thead class="table-head hidden text-left md:table-header-group">
                    <tr>
                        <th class="px-4 py-2.5">Aktivitas</th>
                        <th class="px-4 py-2.5">Pelaku</th>
                        <th class="px-4 py-2.5">IP</th>
                        <th class="px-4 py-2.5">Waktu</th>
                        <th class="px-4 py-2.5 text-right">Detail</th>
                    </tr>
                </thead>
                <tbody class="block md:table-row-group">
                    @forelse($logs as $log)
                        @php
                            $tone = match (true) {
                                str_contains($log->event, 'deactivated') => 'badge-danger',
                                str_contains($log->event, 'permissions') => 'badge-warn',
                                str_contains($log->event, 'login') => 'badge-ok',
                                default => 'badge-brand',
                            };
                        @endphp
                        <tr class="flex flex-wrap gap-x-4 gap-y-1.5 border-t border-slate-100 px-4 py-3 first:border-t-0 md:table-row md:p-0 md:first:border-t">
                            <td class="w-full md:w-auto md:px-4 md:py-2.5 md:align-top">
                                <span class="badge {{ $tone }}">{{ $log->event }}</span>
                                <p class="mt-1.5 font-bold text-slate-900">{{ $log->description }}</p>
                                @if($log->auditable_type)
                                    <p class="mt-1 inline-block rounded bg-slate-100 px-1.5 font-mono text-[11px] font-semibold text-slate-600">{{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}</p>
                                @endif
                            </td>
                            <td class="md:px-4 md:py-2.5 md:align-top">
                                <p class="font-bold text-slate-800">{{ $log->actor_name ?? 'Sistem' }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $log->actor_role ?? '-' }}</p>
                            </td>
                            <td class="self-end font-mono md:px-4 md:py-2.5 md:align-top text-xs text-slate-600">{{ $log->ip_address ?? '-' }}</td>
                            <td class="self-end whitespace-nowrap md:px-4 md:py-2.5 md:align-top text-xs font-semibold tabular-nums text-slate-600">
                                {{ $log->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}
                            </td>
                            <td class="w-full md:w-auto md:px-4 md:py-2.5 md:text-right md:align-top">
                                @if($log->metadata)
                                    <details class="inline-block text-left">
                                        <summary class="btn-secondary min-h-11 lg:min-h-8 lg:py-1">Lihat</summary>
                                        <pre class="mt-2 max-h-72 w-[min(520px,80vw)] overflow-auto rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs leading-relaxed text-slate-800">{{ json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </details>
                                @else
                                    <span class="text-xs text-slate-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-state">Belum ada aktivitas tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">{{ $logs->links() }}</div>
        @endif
    </x-ui.section>
</x-layouts.app>
