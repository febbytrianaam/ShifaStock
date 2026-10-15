@props(['name', 'message' => ''])
@if($message)
    <p class="px-2 pb-2 text-[11px] text-rose-700" data-receipt-field-error data-error-name="{{ $name }}" role="alert">{{ $message }}</p>
@endif
