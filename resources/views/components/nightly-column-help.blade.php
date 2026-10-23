@props(['column', 'label'])
<button type="button" class="nightly-column-help" data-nightly-column-help="{{ $column }}" popovertarget="nightly-help-{{ $column }}" aria-describedby="nightly-help-{{ $column }}" aria-label="Penjelasan kolom {{ $label }}">
    <span>{{ $label }}</span><span data-nightly-help-icon aria-hidden="true"></span>
</button>
