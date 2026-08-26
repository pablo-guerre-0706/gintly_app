import $ from 'jquery';
import { api, ApiError } from '@/core/api-client';

const FORM = '#purchase-order-form';

function notify(type, message) {
    $(document).trigger('gintly:notify', [{
        type,
        message,
    }]);
}

function setLoading($form, loading) {
    const $button = $form.find('[data-submit]');

    $button.prop('disabled', loading);

    $button
        .find('[data-submit-text]')
        .toggleClass('hidden', loading);

    $button
        .find('[data-submit-spinner]')
        .toggleClass('hidden', !loading);
}

function clearValidation($form) {
    $form.find('[data-error-for]')
        .text('')
        .addClass('hidden');
}

function renderValidation($form, errors) {
    Object.entries(errors).forEach(([field, messages]) => {
        $form.find('[data-error-for]')
            .filter((_, element) =>
                element.dataset.errorFor === field
            )
            .first()
            .text(messages[0] ?? '')
            .removeClass('hidden');
    });
}

function buildPayload($form) {
    const items = $form
        .find('[data-order-item]')
        .map((_, row) => {
            const $row = $(row);

            return {
                product_id:
                    $row.find('[data-field="product_id"]').val(),

                ordered_quantity:
                    $row.find('[data-field="ordered_quantity"]').val(),

                agreed_unit_cost:
                    $row.find('[data-field="agreed_unit_cost"]').val(),
            };
        })
        .get();

    return {
        supplier_id:
            $form.find('[name="supplier_id"]').val(),

        branch_id:
            $form.find('[name="branch_id"]').val(),

        ordered_at:
            $form.find('[name="ordered_at"]').val(),

        items,
    };
}

async function submitOrder(event) {
    event.preventDefault();

    const $form = $(event.currentTarget);

    clearValidation($form);
    setLoading($form, true);

    try {
        const response = await api.post(
            '/purchase-orders',
            buildPayload($form),
        );

        const order = response?.data ?? response;

        notify(
            'success',
            Orden ${order?.code ?? ''} creada correctamente.,
        );

        $(document).trigger(
            'gintly:purchase-order-created',
            [order],
        );
    } catch (error) {
        if (!(error instanceof ApiError)) {
            throw error;
        }

        if (error.status === 422) {
            renderValidation($form, error.errors);

            if (error.code === 'SUPPLIER_NOT_APPROVED') {
                notify(
                    'warning',
                    'El proveedor seleccionado no está aprobado.',
                );
                return;
            }

            notify('warning', error.message);
            return;
        }

        if (error.status === 403) {
            notify('error', error.message);
            return;
        }

        if (error.status === 409) {
            notify('warning', error.message);
            return;
        }

        if (error.status >= 500 || error.status === 0) {
            notify(
                'error',
                'No fue posible crear la orden. Inténtelo nuevamente.',
            );
        }
    } finally {
        setLoading($form, false);
    }
}

export default function init() {
    const $form = $(FORM);

    if (!$form.length) {
        return;
    }

    $form.on('submit', submitOrder);
}
