<section id="planes" data-public-plans class="bg-slate-50 px-5 py-20 sm:px-8" aria-labelledby="public-plans-title">
    <div class="mx-auto max-w-6xl">
        <header class="text-center"><h2 id="public-plans-title" class="text-3xl font-bold text-gintly-sidebar sm:text-4xl">Un plan para tu negocio</h2><p class="mx-auto mt-4 max-w-2xl text-base leading-7 text-slate-600">Precios publicados en NIO. El importe y la moneda cobrables se muestran en el checkout alojado antes de confirmar.</p></header>
        <div class="mx-auto mt-8 flex w-fit flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white p-2" role="group" aria-label="Periodicidad del plan"><button type="button" data-public-period="monthly" aria-pressed="true" class="min-h-11 rounded-xl bg-gintly-sidebar px-5 py-3 font-semibold text-white">Mensual</button><button type="button" data-public-period="annual" aria-pressed="false" class="min-h-11 rounded-xl px-5 py-3 font-semibold text-gintly-sidebar">Anual</button></div>
        <p class="mt-3 text-center text-sm text-slate-600">Anual: 12 meses cobrados por adelantado, sin descuento.</p>
        <div class="mt-10 grid gap-6 lg:grid-cols-3">
        @foreach($billingPlans as $plan)
            <article data-public-plan="{{ $plan['key'] }}" class="flex min-w-0 flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <h3 class="text-2xl font-bold text-gintly-sidebar">{{ $plan['name'] }}</h3>
                @foreach(['monthly' => 'mes', 'annual' => 'año'] as $period => $label)
                    <p data-public-price="{{ $period }}" @if($period === 'annual') hidden @endif class="mt-6 text-3xl font-bold text-gintly-sidebar">C$ {{ number_format($plan['prices'][$period]['nio_minor'] / 100, 2) }} <span class="text-sm font-normal text-slate-600">/ {{ $label }}</span></p>
                @endforeach
                <p class="mt-5 text-sm leading-7 text-slate-600">{{ $plan['limits']['branches'] }} {{ $plan['limits']['branches'] === 1 ? 'sucursal' : 'sucursales' }}<br>{{ $plan['limits']['cash_sessions'] === null ? 'Sin límite comercial de cajas simultáneas' : $plan['limits']['cash_sessions'].' cajas simultáneas' }}</p>
                <p class="mt-2 text-xs leading-5 text-slate-500">El límite corresponde a sesiones de caja simultáneas, no a cajas registradas.</p>
                <ul class="my-6 space-y-2 text-sm text-slate-700">
                @foreach($plan['features'] as $feature)
                    <li>{{ __('billing-features.'.$feature) !== 'billing-features.'.$feature ? __('billing-features.'.$feature) : ['pos'=>'Punto de venta','sales'=>'Ventas','catalog'=>'Catálogo','inventory'=>'Inventario','cash'=>'Caja','returns'=>'Devoluciones','receivables'=>'Cuentas por cobrar','three_way_match'=>'Contraste de compras 3-Way','anomalies'=>'Anomalías','supplier_map'=>'Mapa de proveedores','multi_branch'=>'Múltiples sucursales','advanced_reports'=>'Reportes avanzados','warehouse_transfers'=>'Traspasos entre bodegas'][$feature] ?? $feature }}</li>
                @endforeach
                </ul>
                <a data-public-select="{{ $plan['key'] }}" href="{{ route('register.index') }}" class="mt-auto inline-flex min-h-11 items-center justify-center rounded-xl bg-gintly-sidebar px-4 py-3 text-center font-semibold text-white">Seleccionar {{ $plan['name'] }}</a>
            </article>
        @endforeach
        </div>
    </div>
</section>
