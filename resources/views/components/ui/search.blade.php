@props(['label' => 'Cari', 'width' => 'sm:w-64'])
{{-- Rounded search field with an icon; every other attribute goes to the <input>. --}}
<label class="relative flex min-h-11 w-full items-center gap-2 rounded-full border border-slate-300 bg-white px-3 focus-within:border-brand-500 focus-within:ring-3 focus-within:ring-brand-100 lg:min-h-[34px] {{ $width }}">
    <x-ui.icon name="search" class="h-[15px] w-[15px] shrink-0 text-slate-500" />
    <span class="sr-only">{{ $label }}</span>
    <input type="search" {{ $attributes->class(['w-full min-w-0 border-0 bg-transparent p-0 text-[13px] text-slate-900 outline-none placeholder:text-slate-400 focus:ring-0 focus-visible:outline-none']) }}>
</label>
