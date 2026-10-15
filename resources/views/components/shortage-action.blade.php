@props(['medicineId', 'locationId'])
@can('operate-inventory')
    @can('view-transfers')
        @if($medicineId && $locationId)
            <a class="mt-2 inline-block text-xs font-semibold text-blue-700 hover:underline" href="{{ route('preview.transfers', ['medicine_id' => $medicineId, 'destination_location_id' => $locationId]) }}">Transfer ke Lokasi Ini</a>
        @endif
    @endcan
@endcan
