<div class="grid gap-5 lg:grid-cols-2" aria-label="Conteo físico por moneda">
    @foreach (['NIO' => 'Córdobas · NIO', 'USD' => 'Dólares · USD'] as $code => $label)
        <fieldset data-cash-grid="{{ $code }}" class="min-w-0 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
            <legend class="px-2 text-base font-bold text-gintly-sidebar">{{ $label }}</legend>
            <div class="grid grid-cols-[minmax(0,1fr)_72px_minmax(0,1fr)] gap-2 pb-2 text-xs font-semibold uppercase tracking-wide text-gintly-text-secondary"><span>Denominación</span><span class="text-center">Unidades</span><span class="text-right">Subtotal</span></div>
            <div data-cash-grid-rows></div>
            <button type="button" data-cash-add-denomination class="mt-3 min-h-11 rounded-xl border border-gintly-border px-4 text-sm font-semibold text-gintly-brand">Añadir denominación</button>
            <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-200 pt-4 text-sm font-semibold"><span>Total {{ $code }}</span><output data-cash-grid-total class="text-lg tabular-nums">{{ $code === 'USD' ? 'US$' : 'C$' }} 0.00</output></div>
        </fieldset>
    @endforeach
</div>
