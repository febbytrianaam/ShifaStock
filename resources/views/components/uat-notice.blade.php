@if(app()->environment('uat'))
    <div role="status" data-uat-notice class="border-b border-amber-200 bg-amber-50 px-4 py-2 text-center text-xs text-amber-900">
        <strong>UAT / Simulasi</strong> &middot; Bukan data stok operasional klinik.
    </div>
@endif
