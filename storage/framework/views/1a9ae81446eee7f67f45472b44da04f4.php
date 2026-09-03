<?php $__env->startSection('title', 'Dashboard'); ?>
<?php $__env->startSection('page-script', 'modules/dashboard/index'); ?>

<?php $__env->startSection('content'); ?>
<?php
$kpis = [
    ['key'=>'sales_today','title'=>'Ventas del día','icon'=>'<i class="fa-solid fa-chart-line"></i>','class'=>'xl:col-start-1 xl:col-span-4 xl:row-start-1'],
    ['key'=>'average_ticket','title'=>'Ticket promedio','icon'=>'<i class="fa-solid fa-receipt"></i>','class'=>'xl:col-start-5 xl:col-span-4 xl:row-start-1'],
    ['key'=>'overdue_receivables','title'=>'CxC Vencida +60 días','icon'=>'<i class="fa-solid fa-money-bill-wave"></i>','class'=>'xl:col-start-9 xl:col-span-4 xl:row-start-1'],
    ['key'=>'inventory_variance','title'=>'Descuadre Inv.','icon'=>'<i class="fa-solid fa-boxes-stacked"></i>','class'=>'xl:col-start-1 xl:col-span-6 xl:row-start-2'],
    ['key'=>'active_alerts','title'=>'Alertas activas','icon'=>'<i class="fa-solid fa-triangle-exclamation"></i>','class'=>'xl:col-start-7 xl:col-span-6 xl:row-start-2'],
    ['key'=>'gross_margin','title'=>'Margen bruto','icon'=>null,'class'=>'xl:col-start-10 xl:col-span-3 xl:row-start-4'],
    ['key'=>'returns','title'=>'Devoluciones','icon'=>null,'class'=>'xl:col-start-10 xl:col-span-3 xl:row-start-5'],
    ['key'=>'pending_purchases','title'=>'Compras p. de aprobación','icon'=>null,'class'=>'xl:col-start-10 xl:col-span-3 xl:row-start-6'],
];
?>

<main class="mx-auto w-full max-w-6xl bg-stone-100 px-6 py-7" data-dashboard-root>
    <header class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-neutral-900">Dashboard</h1>
            <p id="dashboardContext" class="mt-1 text-xs text-neutral-500">Actualizando información...</p>
        </div>

        <div class="flex gap-2.5">
            <button class="h-10 rounded-full border border-neutral-300 bg-white px-5 text-xs text-neutral-600">
                <i class="fa-solid fa-download mr-2"></i> Exportar
            </button>
            <button data-dashboard-refresh class="h-10 rounded-full bg-cyan-800 px-5 text-xs font-semibold text-white">
                <i class="fa-solid fa-rotate mr-2"></i> Actualizar
            </button>
        </div>
    </header>
    <nav class="mb-8 rounded-xl border border-neutral-200 bg-white p-4 shadow-sm" aria-label="Accesos rápidos">
        <h2 class="mb-3 text-[11px] font-bold uppercase tracking-wider text-neutral-400">Accesos Rápidos al Sistema</h2>
        <div class="flex flex-wrap gap-3">
            <a href="<?php echo e(route('pos.index')); ?>" class="inline-flex h-9 items-center rounded-lg bg-neutral-50 px-4 text-xs font-medium text-neutral-700 ring-1 ring-neutral-200 transition hover:bg-neutral-100">
                <i class="fa-solid fa-cash-register mr-2 text-neutral-500"></i> Punto de venta
            </a>
            <a href="<?php echo e(route('finance.cash-closing')); ?>" class="inline-flex h-9 items-center rounded-lg bg-neutral-50 px-4 text-xs font-medium text-neutral-700 ring-1 ring-neutral-200 transition hover:bg-neutral-100">
                <i class="fa-solid fa-vault mr-2 text-neutral-500"></i> Cierre de caja
            </a>
            <a href="<?php echo e(route('customers.index')); ?>" class="inline-flex h-9 items-center rounded-lg bg-neutral-50 px-4 text-xs font-medium text-neutral-700 ring-1 ring-neutral-200 transition hover:bg-neutral-100">
                <i class="fa-solid fa-users mr-2 text-neutral-500"></i> Clientes y Fidelidad
            </a>
            <a href="<?php echo e(route('inventory.reconciliation')); ?>" class="inline-flex h-9 items-center rounded-lg bg-neutral-50 px-4 text-xs font-medium text-neutral-700 ring-1 ring-neutral-200 transition hover:bg-neutral-100">
                <i class="fa-solid fa-boxes-packing mr-2 text-neutral-500"></i> Conciliación y stock
            </a>
        </div>
    </nav>

    <section class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-12">
        <?php $__currentLoopData = $kpis; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $kpi): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php if (isset($component)) { $__componentOriginala4ae059936bc185e758290466e2179c1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala4ae059936bc185e758290466e2179c1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.kpi-card','data' => ['id' => 'kpi-'.e($kpi['key']).'','class' => ''.e($kpi['class']).' min-h-24','title' => $kpi['title'],'value' => '—','subtext' => '—','icon' => $kpi['icon']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('kpi-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'kpi-'.e($kpi['key']).'','class' => ''.e($kpi['class']).' min-h-24','title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($kpi['title']),'value' => '—','subtext' => '—','icon' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($kpi['icon'])]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala4ae059936bc185e758290466e2179c1)): ?>
<?php $attributes = $__attributesOriginala4ae059936bc185e758290466e2179c1; ?>
<?php unset($__attributesOriginala4ae059936bc185e758290466e2179c1); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala4ae059936bc185e758290466e2179c1)): ?>
<?php $component = $__componentOriginala4ae059936bc185e758290466e2179c1; ?>
<?php unset($__componentOriginala4ae059936bc185e758290466e2179c1); ?>
<?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <article class="card xl:col-start-1 xl:col-span-7 xl:row-start-3">
            <div class="card-head">
                <div><h2>Estadísticas de ventas semanal</h2><p>VS ventas diarias</p></div>
                <button class="rounded-full bg-neutral-100 px-3 py-2 text-xs text-neutral-500">Ver más</button>
            </div>
            <div class="h-80"><canvas id="salesWeeklyChart"></canvas></div>
        </article>

        <article class="card xl:col-start-8 xl:col-span-5 xl:row-start-3">
            <div class="card-head"><h2>Estado de caja: Encuadre</h2></div>
            <div class="h-80"><canvas id="cashStatusChart"></canvas></div>
        </article>

        <article class="card xl:col-start-1 xl:col-span-3 xl:row-start-4 xl:row-span-3">
            <div class="card-head"><div><h2>Inv. Lógico vs. Físico</h2><p>Diferencia por sucursal (uds)</p></div></div>
            <div class="h-72"><canvas id="inventoryComparisonChart"></canvas></div>
        </article>

        <article class="card xl:col-start-4 xl:col-span-6 xl:row-start-4 xl:row-span-3">
            <div class="card-head">
                <div><h2>Exposición de cuentas por cobrar</h2><p>Antigüedad de cartera · Últimos 5 meses</p></div>
                <strong id="receivablesTotal" class="text-base">—</strong>
            </div>
            <div class="h-72"><canvas id="receivablesChart"></canvas></div>
        </article>

        <article class="card xl:col-start-1 xl:col-span-12 xl:row-start-7">
            <div class="card-head">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-red-600"></i>
                    <h2>Centro de Alertas de Anomalías</h2>
                    <?php if (isset($component)) { $__componentOriginal8c81617a70e11bcf247c4db924ab1b62 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8c81617a70e11bcf247c4db924ab1b62 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.status-badge','data' => ['type' => 'danger','text' => 'Activas']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('status-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'danger','text' => 'Activas']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8c81617a70e11bcf247c4db924ab1b62)): ?>
<?php $attributes = $__attributesOriginal8c81617a70e11bcf247c4db924ab1b62; ?>
<?php unset($__attributesOriginal8c81617a70e11bcf247c4db924ab1b62); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8c81617a70e11bcf247c4db924ab1b62)): ?>
<?php $component = $__componentOriginal8c81617a70e11bcf247c4db924ab1b62; ?>
<?php unset($__componentOriginal8c81617a70e11bcf247c4db924ab1b62); ?>
<?php endif; ?>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <tbody id="anomalyAlertsBody"></tbody>
                </table>
            </div>
        </article>
    </section>
</main>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.panel', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\gintly_app\resources\views/dashboard.blade.php ENDPATH**/ ?>