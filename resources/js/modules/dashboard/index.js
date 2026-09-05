import Chart from 'chart.js/auto';
import { api, ApiError } from '@/core/api-client';
import { withLoading } from '@/core/loading';
import { notify } from '@/core/notifications';

const KPI_KEYS = [
    'sales_today', 'average_ticket', 'overdue_receivables',
    'inventory_variance', 'active_alerts', 'gross_margin',
    'returns', 'pending_purchases',
];

const charts = {};

const createChart = (id, type, datasets = [], stacked = false, indexAxis = 'x') => {
    const canvas = document.getElementById(id);
    if (!canvas) return;

    charts[id] = new Chart(canvas, {
        type,
        data: { labels: [], datasets },
        options: {
            indexAxis, // Permite barras horizontales si se especifica 'y'
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: { legend: { position: 'top', labels: { boxWidth: 8, font: { size: 9 } } } },
            scales: {
                x: { stacked, grid: { borderDash: [4, 4] }, ticks: { font: { size: 9 } } },
                y: { stacked, beginAtZero: true, grid: { borderDash: [4, 4] }, ticks: { font: { size: 9 } } },
            },
        },
    });
};

const initCharts = () => {
    // A. Gráfica Semanal (Líneas con área de relleno)
    createChart('salesWeeklyChart', 'line', [
        { label: 'Venta bruta', data: [], tension: .4, fill: true },
        { label: 'Impuestos', data: [], tension: .4, fill: true },
        { label: 'Venta neta', data: [], tension: .4, fill: true },
    ]);
    
    // B. Estado de Caja: CORRECCIÓN a tipo 'bar' horizontal ('y') para calcar tu imagen
    createChart('cashStatusChart', 'bar', [{ data: [] }], false, 'y');
    
    // C. Inventario Lógico vs Físico
    createChart('inventoryComparisonChart', 'bar', [
        { label: 'Lógico', data: [] }, { label: 'Físico', data: [] }, { label: 'Descuadre', data: [] },
    ]);
    
    // D. Exposición de Cuentas por Cobrar
    createChart('receivablesChart', 'bar', [
        { label: 'Corriente', data: [] }, { label: '1-30 días', data: [] },
        { label: '31-60 días', data: [] }, { label: '+60 días', data: [] },
    ], true);
};

const updateChart = (id, labels, series) => {
    const chart = charts[id];
    if (!chart) return;
    chart.data.labels = labels ?? [];
    chart.data.datasets.forEach((dataset, i) => dataset.data = series?.[i] ?? []);
    chart.update();
};

const renderKpis = (metrics = {}) => KPI_KEYS.forEach(key => {
    const card = document.getElementById(`kpi-${key}`);
    if (!card || !metrics[key]) return;

    // CORRECCIÓN: Buscadores compatibles con las etiquetas generadas por x-kpi-card
    const valueEl = card.querySelector('.kpi-value') || card.querySelector('strong') || card.querySelector('.text-2xl');
    const subtextEl = card.querySelector('.kpi-subtext') || card.querySelector('p');

    if (valueEl) valueEl.textContent = metrics[key].display_value ?? metrics[key].value ?? '—';
    if (subtextEl) subtextEl.textContent = metrics[key].subtext ?? '—';
});

const renderCharts = data => {
    const s = data.sales_weekly ?? {}, i = data.inventory ?? {}, r = data.receivables ?? {}, c = data.cash ?? {};
    updateChart('salesWeeklyChart', s.labels, [s.gross, s.tax, s.net]);
    updateChart('cashStatusChart', c.labels, [c.values]); // Sincroniza barras horizontales
    updateChart('inventoryComparisonChart', i.labels, [i.logical, i.physical, i.variance]);
    updateChart('receivablesChart', r.labels, [r.current, r.days_1_30, r.days_31_60, r.days_60_plus]);
    
    const recTotal = document.getElementById('receivablesTotal');
    if (recTotal) recTotal.textContent = r.display_total ?? '—';
};

const loadDashboard = async () => {
    try {
        const response = await withLoading(() => api.get('/dashboard'), { message: 'Actualizando indicadores...' });
        const data = response?.data ?? response;
        renderKpis(data.metrics);
        renderCharts(data);
        
        const contextEl = document.getElementById('dashboardContext');
        if (contextEl) contextEl.textContent = data.context_label ?? 'Información actualizada';
    } catch (error) {
        if (error instanceof ApiError && [401, 403].includes(error.status)) return;
        notify({ type: 'error', title: 'Dashboard no disponible', message: 'No fue posible cargar los indicadores.' });
    }
};

// Autoejecución al cargar el layout si el root existe en el DOM
if (document.querySelector('[data-dashboard-root]')) {
    initCharts();
    document.querySelector('[data-dashboard-refresh]')?.addEventListener('click', loadDashboard);
    loadDashboard();
}
