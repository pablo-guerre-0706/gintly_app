<?php

declare(strict_types=1);

/**
 * Registro canónico de PERFILES OPERATIVOS de ROL-03 (Fase 3).
 *
 * ROL-03 es un ÚNICO rol; los perfiles son capacidades internas COMBINABLES. Este archivo es la
 * fuente ÚNICA que mapea cada perfil a las capacidades (permisos) que habilita, REUTILIZANDO los
 * nombres de permisos ya sembrados por RolesAndPermissionsSeeder (sin duplicar ni crear fuentes
 * desconectadas). El rol Spatie ROL-03 concede la UNIÓN EXACTA de estas capacidades (compuerta
 * gruesa por rol); el perfil es la compuerta FINA por usuario. Un ROL-03 sin perfiles queda
 * operativamente bloqueado (opción B para preexistentes: sin sobreautorización por compatibilidad).
 *
 * ROL-01/ROL-02 no usan perfiles: su autoridad proviene del rol humano.
 */
return [

    // profile => [permisos que habilita] (nombres idénticos al catálogo Spatie).
    'capabilities' => [

        // CAJERO · gaveta de efectivo, cobros de factura y de CxC.
        'cajero' => [
            'clientes.ver',
            'facturas.ver',
            'cuentas_por_cobrar.ver', 'cuentas_por_cobrar.abonar',
            'caja.abrir', 'caja.cerrar', 'caja.movimiento.crear',
        ],

        // FACTURADOR · catálogo/clientes, ventas y emisión de facturas, evaluación de crédito.
        'facturador' => [
            'catalogo.ver', 'clientes.ver',
            'ventas.ver', 'ventas.crear',
            'facturas.ver', 'facturas.crear',
            'clientes.credito.ver', 'clientes.credito.evaluar',
        ],

        // BODEGUERO · bodega/inventario, conteos, traspasos, recepción de compra, parte física de devoluciones.
        'bodeguero' => [
            'bodegas.ver', 'inventario.ver', 'proveedores.ver',
            'inventario.conteo', 'inventario.traspaso',
            'compras.ver', 'compras.crear', 'compras.recibir', 'cuentas_por_pagar.ver',
            'devoluciones.ver', 'devoluciones.crear', 'notas_credito.ver',
        ],

        // DESPACHADOR · retiros/entregas de mercancía facturada de su sucursal.
        'despachador' => [
            'facturas.ver',
            'entregas.ver', 'entregas.crear',
        ],
    ],
];
