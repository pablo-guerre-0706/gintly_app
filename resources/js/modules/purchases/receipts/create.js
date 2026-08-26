import $ from 'jquery';
import { api, ApiError } from '@/core/api-client';

function notify(type, message) {
    $(document).trigger('gintly:notify', [{
        type,
        message,
    }]);
}

export async function submitReceipt(payload) {
    try {
        const response = await api.post(
            '/goods-receipts',
            payload,
        );

        const receipt = response?.data ?? response;

        notify(
            'success',
            'Recepción registrada y conciliada correctamente.',
        );

        $(document).trigger(
            'gintly:goods-receipt-created',
            [receipt],
        );

        return receipt;
    } catch (error) {
        if (
            error instanceof ApiError &&
            error.is(409, 'PURCHASE_MATCH')
        ) {
            /*
             * IMPORTANTE:
             * No se trata como rollback.
             * El Resource creado permanece dentro del payload.
             */
            const receipt =
                error.payload?.data ??
                error.payload?.receipt ??
                error.payload;

            notify(
                'warning',
                'La recepción fue registrada, pero presenta una discrepancia de conciliación.',
            );

            $(document).trigger(
                'gintly:purchase-match',
                [{
                    receipt,
                    message: error.message,
                }],
            );

            return receipt;
        }

        throw error;
    }
}
