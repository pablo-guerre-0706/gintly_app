<?php

return [
    'kpi_01' => [
        'brd' => 'KPI-01',
        'label' => 'Correspondencia ventas-caja-inventario',
        'unit' => 'porcentaje',
        'goalable' => false,
        'family' => 'integridad',
        'source' => 'vw_kpi_correspondencia'
        ],
    'kpi_02' => [
        'brd' => 'KPI-02',
        'label' => 'Correspondencia bodega-inventario',
        'unit' => 'porcentaje',
        'goalable' => false,
        'family' => 'integridad', 
        'source' => 'vw_kpi_exactitud_stock'
        ],
    'kpi_03' => [
        'brd' => 'KPI-03',
        'label' => 'Reducción de faltantes no justificados',
        'unit' => 'monto',
        'goalable' => true, 
        'family' => 'control',
        'source' => 'vw_kpi_faltantes'
        ],
    'kpi_04' => [
        'brd' => 'KPI-04',
        'label' => 'Uso consistente del sistema',
        'unit' => 'porcentaje',
        'goalable' => true,
        'family' => 'cumplimiento',
        'source' => 'vw_kpi_uso_sistema'
        ],
    'kpi_05' => [
        'brd' => 'KPI-05',
        'label' => 'Evolución de ventas',
        'unit' => 'monto',
        'goalable' => true,
        'family' => 'comercial',
        'source' => 'vw_kpi_ventas'
        ],
    'kpi_06' => [
        'brd' => 'KPI-06',
        'label' => 'Cumplimiento de metas',
        'unit' => 'porcentaje',
        'goalable' => false,
        'family' => 'agregador',
        'source' => null
        ],
    'kpi_07' => [
        'brd' => 'KPI-07',
        'label' => 'Disponibilidad de reportes confiables',
        'unit' => 'porcentaje',
        'goalable' => false,
        'family' => 'sla',
        'source' => 'vw_kpi_disponibilidad'
        ],
    'kpi_08' => [
        'brd' => 'KPI-08',
        'label' => 'Recuperación de cartera',
        'unit' => 'porcentaje',
        'goalable' => true,
        'family' => 'financiero',
        'source' => 'vw_kpi_cartera'
        ],

        'margen'              => ['brd' => null, 'label' => 'Margen bruto',            'unit' => 'porcentaje', 'goalable' => true, 'family' => 'comercial', 'source' => null],
        'ticket_promedio'     => ['brd' => null, 'label' => 'Ticket promedio',         'unit' => 'monto',      'goalable' => true, 'family' => 'comercial', 'source' => 'vw_kpi_ventas'],
        'rotacion_inventario' => ['brd' => null, 'label' => 'Rotación de inventario',  'unit' => 'ratio',      'goalable' => true, 'family' => 'operativo', 'source' => null],
];