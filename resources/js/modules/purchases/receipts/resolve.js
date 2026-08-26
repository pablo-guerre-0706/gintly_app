import $ from 'jquery';

import {
    api,
    ApiError,
} from '@/core/api-client';

import {
    notify,
    alertModal,
} from '@/core/notifications';

import {
    setButtonLoading,
} from '@/core/loading';

const SELECTOR = Object.freeze({
    root: '[data-goods-receipt-resolve]',
    form: '[data-resolution-form]',
    submit: '[data-submit]',
    context: '[data-purchase-match-context]',
});

let purchaseMatchContext = null;

export function setPurchaseMatchContext(context) {
    if (!context || typeof context !== 'object') {
        purchaseMatchContext = null;
        return;
    }

    purchaseMatchContext =
        context.data ??
        context.receipt ??
        context;
}

function readEmbeddedContext($root) {
    const element = $root.find(SELECTOR.context).get(0);

    if (!element?.textContent?.trim()) {
        return null;
    }

    try {
        return JSON.parse(element.textContent);
    } catch (error) {
        console.error(
            '[Gintly] Contexto PURCHASE_MATCH inválido.',
            error,
        );

        return null;
    }
}

function receiptId($root) {
    return (
        purchaseMatchContext?.id ??
        Number($root.data('receipt-id')) ??
        null
    );
}

function buildPayload($form) {
    return {
        resolution:
            $form.find('[name="resolution"]').val(),

        notes:
            $form.find('[name="notes"]').val()?.trim() || null,
    };
}

async function resolveReceipt(event) {
    event.preventDefault();

    const $form = $(event.currentTarget);
    const $root = $form.closest(SELECTOR.root);
    const button = $form.find(SELECTOR.submit).get(0);

    const id = receiptId($root);
    const payload = buildPayload($form);

    if (!id) {
        notify({
            type: 'error',
            message: 'No fue posible determinar la recepción a resolver.',
        });

        return;
    }

    if (!['aceptar', 'rechazar'].includes(payload.resolution)) {
        notify({
            type: 'warning',
            message: 'Seleccione una resolución válida.',
        });

        return;
    }

    setButtonLoading(button, true, {
        label: 'Resolviendo...',
    });

    try {
        const response = await api.post(
            `/goods-receipts/${id}/resolve`,
            payload,
        );

        const receipt = response?.data ?? response;

        notify({
            type: 'success',
            message:
                payload.resolution === 'aceptar'
                    ? 'La discrepancia fue aceptada y procesada.'
                    : 'La recepción fue rechazada y quedó bloqueada.',
        });

        document.dispatchEvent(
            new CustomEvent(
                'gintly:goods-receipt-resolved',
                {
                    detail: {
                        receipt,
                        resolution: payload.resolution,
                    },
                },
            ),
        );
    } catch (error) {
        if (!(error instanceof ApiError)) {
            throw error;
        }

        if (error.status === 403) {
            await alertModal({
                type: 'error',
                title: 'Acción no autorizada',
                message: error.message,
            });

            return;
        }

        if (error.status === 422) {
            notify({
                type: 'warning',
                message: error.message,
            });

            return;
        }

        /*
         * IMPORTANTE:
         * Este 409 NO representa el PURCHASE_MATCH original.
         * En /resolve, 409 significa que otra operación ya cambió
         * el estado y la recepción dejó de estar en "discrepancia".
         */
        if (error.status === 409) {
            await alertModal({
                type: 'warning',
                title: 'Estado actualizado',
                message:
                    error.message ||
                    'La recepción ya no se encuentra pendiente de resolución.',
            });

            document.dispatchEvent(
                new CustomEvent(
                    'gintly:goods-receipt-stale',
                    {
                        detail: {
                            receiptId: id,
                            error,
                        },
                    },
                ),
            );

            return;
        }

        throw error;
    } finally {
        setButtonLoading(button, false);
    }
}

export function init() {
    const $root = $(SELECTOR.root);

    if (!$root.length) {
        return;
    }

    const embeddedContext = readEmbeddedContext($root);

    if (embeddedContext) {
        setPurchaseMatchContext(embeddedContext);
    }

    const $form = $root.find(SELECTOR.form);

    if (!$form.length) {
        return;
    }

    $form.on('submit', resolveReceipt);
}

export default init;
