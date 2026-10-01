@php
    $legacyTitle = trim($__env->yieldContent('title', 'Panel'));
    $documentTitle = trim($__env->yieldContent('document-title', $legacyTitle));
    $pageTitle = trim($__env->yieldContent('page-title', $legacyTitle));
    $breadcrumbRoot = trim($__env->yieldContent('breadcrumb-root', 'Gintly'));
    $breadcrumbCurrent = trim($__env->yieldContent('breadcrumb-current', $pageTitle));
    $pageScript = trim($__env->yieldContent('page-script', ''));
@endphp
<!DOCTYPE html>
<html lang="es" data-page="{{ $pageScript }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="api-base-url" content="{{ url('/api/v1') }}">
    <meta name="login-url" content="{{ route('login') }}">
    <title>{{ $documentTitle }} · Gintly</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="min-h-dvh bg-slate-50 font-sans text-gintly-text-primary antialiased">
    <a class="fixed start-3 top-3 z-[10000] -translate-y-[200%] rounded-xl bg-white px-4 py-3 font-bold text-gintly-sidebar shadow-xl transition-transform focus:translate-y-0" href="#main-content">Saltar al contenido</a>

    <div
        class="shell-layout relative min-h-dvh bg-slate-50 transition-[padding-inline-start] duration-200"
        data-panel-shell
        data-url-login="{{ route('login') }}"
        data-url-dashboard="{{ route('dashboard') }}"
        data-url-pos="{{ route('pos.index') }}"
        data-url-cash-closing="{{ route('finance.cash-closing') }}"
        data-url-sales-summary="{{ route('panel.sales.summary') }}"
        data-url-customers="{{ route('web.customers.index') }}"
        data-url-customer-create="{{ route('web.customers.create') }}"
        data-url-inventory-reconciliation="{{ route('inventory.reconciliation') }}"
        data-url-inventory-summary="{{ route('panel.inventory.summary') }}"
        data-url-catalog-products="{{ route('catalog.products') }}"
        data-url-purchases-hub="{{ route('panel.purchases') }}"
        data-url-finance-hub="{{ route('panel.finance') }}"
        data-url-cash-overview="{{ route('panel.finance.cash-overview') }}"
        data-url-receivables="{{ route('panel.finance.receivables') }}"
        data-url-payables="{{ route('panel.finance.payables') }}"
        data-url-intelligence-hub="{{ route('panel.intelligence') }}"
        data-url-suppliers="{{ route('panel.suppliers.index') }}"
        data-url-external-suppliers="{{ route('panel.suppliers.explore') }}"
        data-url-anomalies="{{ route('panel.anomalies.index') }}"
        data-url-reconciliations="{{ route('panel.reconciliations.index') }}"
        data-url-kpi-snapshots="{{ route('panel.kpi-snapshots.index') }}"
        data-url-report-definitions="{{ route('panel.report-definitions.index') }}"
        data-url-audit="{{ route('panel.audit.index') }}"
        data-url-organization-hub="{{ route('panel.organization') }}"
        data-url-configuration-hub="{{ route('panel.settings') }}"
        data-url-help="{{ route('panel.help') }}"
        data-url-users="{{ route('panel.users.index') }}"
        data-url-profiles="{{ route('panel.profiles.index') }}"
        data-url-branches="{{ route('panel.branches.index') }}"
        data-url-admin-invoices="{{ route('panel.admin.invoices') }}"
        data-url-admin-warehouses="{{ route('panel.admin.warehouses') }}"
        data-url-admin-physical-counts="{{ route('panel.admin.physical-counts') }}"
        data-url-admin-purchase-orders="{{ route('panel.admin.purchase-orders') }}"
        data-url-admin-goods-receipts="{{ route('panel.admin.goods-receipts') }}"
        data-url-admin-cash-registers="{{ route('panel.admin.cash-registers') }}"
        data-url-admin-cash-sessions="{{ route('panel.admin.cash-sessions') }}"
    >
        @include('layouts.partials.sidebar')

        <div class="flex min-h-dvh min-w-0 flex-col" data-shell-surface>
            @include('layouts.partials.navbar', [
                'pageTitle' => $pageTitle,
                'breadcrumbRoot' => $breadcrumbRoot,
                'breadcrumbCurrent' => $breadcrumbCurrent,
            ])

            <main id="main-content" class="min-w-0 flex-1" tabindex="-1">
                <div class="mx-auto w-full max-w-[1512px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    @yield('content')
                </div>
            </main>

            @include('layouts.partials.footer')
        </div>

        <button
            type="button"
            class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-[2px]"
            data-shell-backdrop
            aria-label="Cerrar navegación"
            tabindex="-1"
            hidden
        ></button>

        <div id="sidebar-tooltip" class="fixed z-[100] max-w-[220px] rounded-lg bg-black px-2.5 py-2 text-xs/5 text-white shadow-xl" data-sidebar-tooltip role="tooltip" hidden></div>
    </div>

    @stack('scripts')
</body>
</html>
