<?php

declare(strict_types=1);

// Registro canónico de indicadores (RF-12-02). Fuente única de etiqueta, unidad, familia,
// meta admitida (goalable), dirección del logro y origen del dato. 'direction' = up | down.
return [
    'kpi_01' => [
        'label' => 'Correspondencia ventas-caja-inventario',
        'unit' => 'porcentaje',
        'goalable' => false,
        'family' => 'integridad',
        'direction' => 'up',
        'source' => 'directo'
        ],
    'kpi_02' => [
        'label' => 'Correspondencia bodega-inventario',
        'unit' => 'porcentaje',
        'goalable' => false,
        'family' => 'integridad',
        'direction' => 'up',
        'source' => 'vw_kpi_exactitud_stock'
        ],
    'kpi_03' => [
        'label' => 'Reducción de faltantes no justificados',
        'unit' => 'monto',
        'goalable' => true,
        'family' => 'control',
        'direction' => 'down',
        'source' => 'vw_kpi_faltantes'
        ],
    'kpi_04' => [
        'label' => 'Uso consistente del sistema',
        'unit' => 'porcentaje',
        'goalable' => true, 
        'family' => 'cumplimiento',
        'direction' => 'up',
        'source' => 'vw_kpi_uso_sistema'
        ],
    'kpi_05' => [
        'label' => 'Evolución de ventas',
        'unit' => 'monto',
        'goalable' => true,
        'family' => 'comercial',
        'direction' => 'up',
        'source' => 'vw_kpi_ventas'
        ],
    'kpi_06' => [
        'label' => 'Cumplimiento de metas',
        'unit' => 'porcentaje',
        'goalable' => false,
        'family' => 'agregador',
        'direction' => 'up',
        'source' => 'snapshots'
        ],
    'kpi_07' => [
        'label' => 'Disponibilidad de reportes confiables',
        'unit' => 'porcentaje',
        'goalable' => false,
        'family' => 'sla',
        'direction' => 'up',
        'source' => 'vw_kpi_disponibilidad'
        ],
    'kpi_08' => [
        'label' => 'Recuperación de cartera',
        'unit' => 'porcentaje',
        'goalable' => true,
        'family' => 'financiero',
        'direction' => 'up',
        'source' => 'vw_kpi_cartera'
        ],

    'margen'              => ['label' => 'Margen bruto',            'unit' => 'porcentaje', 'goalable' => true, 'family' => 'comercial', 'direction' => 'up', 'source' => 'fase2'],
    'ticket_promedio'     => ['label' => 'Ticket promedio',         'unit' => 'monto',      'goalable' => true, 'family' => 'comercial', 'direction' => 'up', 'source' => 'vw_kpi_ventas'],
    'rotacion_inventario' => ['label' => 'Rotación de inventario',  'unit' => 'ratio',      'goalable' => true, 'family' => 'operativo', 'direction' => 'up', 'source' => 'fase2'],
];
