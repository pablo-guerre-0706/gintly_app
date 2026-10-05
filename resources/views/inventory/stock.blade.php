@extends('layouts.panel')
@section('document-title', $physical ? 'Bodega física' : 'Inventario lógico')
@section('page-title', $physical ? 'Bodega física' : 'Inventario lógico')
@section('breadcrumb-root', 'Inventario y bodega')
@section('page-script', 'inventory/stock')

@section('content')
<section class="space-y-6" data-inventory-page data-mode="{{ $physical ? 'physical' : 'logical' }}" aria-labelledby="inventory-title" aria-busy="true">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 max-w-3xl">
            <p class="text-sm font-semibold text-gintly-brand" data-inventory-context></p>
            <h1 id="inventory-title" class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">{{ $physical ? 'Bodega física' : 'Inventario lógico' }}</h1>
            <p class="mt-3 text-sm leading-6 text-gintly-text-secondary">{{ $physical ? 'Consulta tus bodegas asignadas y registra lo que realmente observas. El conteo no modifica directamente las existencias.' : 'Saldos registrados, reservas y disponibilidad actualizados por las operaciones del negocio. Esta vista es de consulta.' }}</p>
        </div>
        <button type="button" class="min-h-11 rounded-xl border border-gintly-border bg-white px-4 text-sm font-semibold disabled:opacity-50" data-inventory-refresh>Actualizar consultas</button>
    </header>
    <nav class="flex flex-wrap gap-3" aria-label="Vistas de inventario">
        <a href="{{ route('panel.operations.stock') }}" data-inventory-link="operativeStock" class="inline-flex min-h-11 items-center rounded-xl border border-gintly-border px-4 text-sm font-semibold" hidden>Bodega física</a>
        <a href="{{ route('panel.inventory.stock') }}" data-inventory-link="inventoryStock" class="inline-flex min-h-11 items-center rounded-xl border border-gintly-border px-4 text-sm font-semibold" hidden>Inventario lógico</a>
        <a href="{{ route('panel.operations.physical-count') }}" data-inventory-link="operativePhysicalCount" class="inline-flex min-h-11 items-center rounded-xl bg-gintly-brand px-4 text-sm font-semibold text-white" hidden>Registrar conteo</a>
        <a href="{{ route('panel.operations.goods-receipts.create') }}" data-inventory-link="operativeGoodsReceiptsCreate" class="inline-flex min-h-11 items-center rounded-xl border border-gintly-border px-4 text-sm font-semibold" hidden>Registrar recepción</a>
        <a href="{{ route('panel.operations.transfers.create') }}" data-inventory-link="operativeTransfersCreate" class="inline-flex min-h-11 items-center rounded-xl border border-gintly-border px-4 text-sm font-semibold" hidden>Registrar traspaso</a>
    </nav>
    <div class="rounded-2xl border border-red-200 bg-red-50 p-5 text-sm text-red-800" data-inventory-fatal role="alert" hidden></div>
    <p class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900" data-inventory-unassigned hidden>No tienes bodegas activas asignadas. Solicita una asignación al administrador para consultar saldos o registrar conteos.</p>
    <form class="grid gap-4 rounded-2xl border border-gintly-border bg-white p-5 sm:grid-cols-2 xl:grid-cols-4" data-inventory-filters hidden>
        <label class="text-sm font-semibold" data-branch-filter hidden>Sucursal
            <select name="branch_id" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3"></select>
        </label>
        <label class="text-sm font-semibold">Bodega
            <select name="warehouse_id" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3"></select>
        </label>
        <label class="text-sm font-semibold">Buscar producto o SKU
            <input name="search" type="search" minlength="2" maxlength="160" autocomplete="off" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3" placeholder="Al menos dos caracteres">
        </label>
        <button type="submit" class="min-h-11 self-end rounded-xl bg-gintly-brand px-4 text-sm font-semibold text-white">Aplicar filtros</button>
    </form>
    <p class="text-xs leading-5 text-gintly-text-secondary">La diferencia histórica compara la cantidad física con el saldo del sistema en la fecha del mismo conteo. No es una discrepancia nueva respecto al stock actual. Un aviso de mínimo es independiente del estado de conciliación.</p>
    @foreach (['stock' => 'Saldos digitales', 'counts' => 'Historial de conteos físicos', 'alerts' => 'Avisos de mínimo disponible'] as $source => $title)
        <section class="min-w-0 rounded-2xl border border-gintly-border bg-white p-4 sm:p-6" data-inventory-source="{{ $source }}" aria-labelledby="inventory-{{ $source }}-title" aria-busy="true">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div><h2 id="inventory-{{ $source }}-title" class="text-lg font-semibold">{{ $title }}</h2><p class="mt-1 text-xs text-gintly-text-secondary" data-consulted-at></p></div>
                @if ($source === 'counts')
                    <label class="text-sm">Estado de conciliación
                        <select class="ms-2 min-h-11 rounded-xl border border-slate-300 bg-white px-3" data-count-status><option value="">Todos</option><option value="abierto">Abierto</option><option value="justificado">Justificado</option><option value="ajustado">Ajustado</option></select>
                    </label>
                @endif
            </header>
            <p class="mt-4 text-sm text-gintly-text-secondary" data-source-loading role="status">Consultando datos…</p>
            <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" data-source-error role="alert" hidden><p data-source-error-message></p><button type="button" class="mt-3 min-h-11 rounded-xl border border-red-300 bg-white px-4 font-semibold" data-source-retry>Reintentar consulta</button></div>
            <p class="mt-4 rounded-xl bg-slate-50 p-5 text-sm text-gintly-text-secondary" data-source-empty hidden>No hay registros para este alcance.</p>
            <div class="mt-4 space-y-3" data-source-records hidden></div>
            <div class="mt-5 flex flex-wrap items-center justify-between gap-3" data-source-pagination hidden>
                <p class="text-sm text-gintly-text-secondary" data-source-page-label></p>
                <div class="flex gap-2"><button type="button" class="min-h-11 rounded-xl border border-slate-300 px-4 text-sm disabled:opacity-40" data-source-prev>Anterior</button><button type="button" class="min-h-11 rounded-xl border border-slate-300 px-4 text-sm disabled:opacity-40" data-source-next>Siguiente</button></div>
            </div>
        </section>
    @endforeach
</section>
@endsection
