import $ from 'jquery';

const SELECTOR = Object.freeze({
    root: '[data-purchase-orders-index]',
});

export function init() {
    const $root = $(SELECTOR.root);

    if (!$root.length) {
        return;
    }

    /*
     * Punto de entrada de la pantalla.
     *
     * Próximamente:
     * - filtros;
     * - paginación;
     * - búsqueda;
     * - emitir/cancelar según Policy/estado;
     * - renderizado definido por Figma.
     */
}

export default init;
