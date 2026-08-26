import $ from 'jquery';

const SELECTOR = Object.freeze({
    root: '[data-purchase-order-edit]',
    form: '[data-purchase-order-form]',
});

export function init() {
    const $root = $(SELECTOR.root);

    if (!$root.length) {
        return;
    }

    const $form = $root.find(SELECTOR.form);

    if (!$form.length) {
        return;
    }

    /*
     * Aquí se conectará:
     * - clonación/eliminación de líneas;
     * - cálculos visuales con money.js;
     * - PUT /purchase-orders/{id};
     * - errores 422;
     * - loading del botón.
     *
     * Nunca:
     * - business_id;
     * - user_id;
     * - code;
     * - expected_total calculado por el cliente.
     */
}

export default init;
