$(function () {
    const $form = $('#formStoreCustomer');
    if (!$form.length) return;

    // Lógica liviana de cálculo: Formatea el límite de crédito a 2 decimales reales en el desenfoque
    $form.find('input[name="credit_limit"]').on('blur', function () {
        let val = parseFloat($(this).val());
        if (isNaN(val) || val < 0) val = 0;
        $(this).val(val.toFixed(2));
    });

    // Interceptar envío del formulario
    $form.on('submit', function (e) {
        e.preventDefault();
        clearValidationErrors();

        // Conversión limpia de FormData a Objeto JSON para la API
        let data = {};
        $form.serializeArray().forEach(item => {
            data[item.name] = item.value;
        });

        // Limpieza de documento en blanco de acuerdo a la restricción parcial del backend
        if (!data.document_number || $.trim(data.document_number) === '') {
            data.document_number = null;
        }

        $.ajax({
            url: $form.data('url'),
            method: 'POST',
            dataType: 'json',
            contentType: 'application/json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Accept': 'application/json'
            },
            data: JSON.stringify(data),
            success: function () {
                window.location.href = $form.find('a').attr('href'); // Redirección al index
            },
            error: function (xhr) {
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    renderValidationErrors(xhr.responseJSON.errors);
                } else {
                    alert('Ocurrió un error inesperado al procesar la solicitud.');
                }
            }
        });
    });

    function renderValidationErrors(errors) {
        Object.keys(errors).forEach(field => {
            const $input = $form.find(`[name="${field}"]`);
            if ($input.length) {
                $input.addClass('border-red-500');
                $input.closest('.form-group').find('.error-field').text(errors[field][0]).removeClass('hidden');
            }
        });
    }

    function clearValidationErrors() {
        $form.find('input, select, textarea').removeClass('border-red-500');
        $form.find('.error-field').addClass('hidden').text('');
    }
});
