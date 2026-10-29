@props(['document'])
@can('operate-inventory')
    @if(in_array($document->status, ['draft', 'expired'], true) && !$document->posted_at && !$document->posted_by && (int)$document->posted_count === 0 && (int)$document->duplicate_count === 0)
        <form method="POST" action="{{ route('issue-imports.destroy', $document) }}" class="inline-flex" data-issue-import-delete data-import-reference="IMP-{{ $document->id }}">
            @csrf
            @method('DELETE')
            <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-md text-rose-600 hover:bg-rose-50 focus-visible:outline-2 focus-visible:outline-rose-500" aria-label="Hapus pratinjau IMP-{{ $document->id }}" title="Hapus pratinjau impor">
                <span data-issue-import-delete-icon aria-hidden="true" class="inline-flex">&times;</span>
            </button>
        </form>
    @endif
@endcan
