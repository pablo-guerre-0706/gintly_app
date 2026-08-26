import $ from 'jquery';

const SELECTOR = Object.freeze({
    root: '[data-goods-receipts-index]',
});

export function init() {
    const $root = $(SELECTOR.root);

    if (!$root.length) {
        return;
    }

    /*
     * Aquí se conectará:
     * - GET /goods-receipts;
     * - filtros purchase_order_id / match_status;
     * - paginación;
     * - badges ok/discrepancia/bloqueada;
     * - acción Resolver visible mediante @can;
     * - diseño final proveniente de Figma.
     */
}

export default init;
