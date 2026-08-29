document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('.registration-form');
    if (!form) return;

    const submitBtn = form.querySelector('.submit-btn');
    const isStep1 = document.getElementById('first_name') !== null;

    // 1. CAMPOS VISUALES QUE DETECTAN SI EL BOTÓN SE ACTIVA
    const visualFields = isStep1 
        ? ['first_name', 'last_name', 'email', 'password', 'password_confirmation']
        : ['store_name', 'country', 'city', 'address', 'branches_count', 'tax_id'];

    function validateForm() {
        let allValid = true;
        visualFields.forEach(id => {
            const input = document.getElementById(id);
            if (!input || input.value.trim() === '') allValid = false;
        });

        if (isStep1) {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('password_confirmation').value;
            if (password !== confirm || password.length < 12) allValid = false;
        }

        if (allValid) {
            submitBtn.classList.remove('btn-disabled');
            submitBtn.classList.add('btn-active');
            submitBtn.disabled = false;
        } else {
            submitBtn.classList.remove('btn-active');
            submitBtn.classList.add('btn-disabled');
            submitBtn.disabled = true;
        }
    }

    visualFields.forEach(id => {
        const input = document.getElementById(id);
        if (input) input.addEventListener('input', validateForm);
    });

    validateForm();

    // 2. ENLACE DIRECTO A LOS ENDPOINTS REALES DEL BACKEND
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        if (isStep1) {
            // Guardamos temporalmente los datos en la sesión del navegador
            sessionStorage.setItem('reg_first_name', document.getElementById('first_name').value.trim());
            sessionStorage.setItem('reg_last_name', document.getElementById('last_name').value.trim());
            sessionStorage.setItem('reg_email', document.getElementById('email').value.trim());
            sessionStorage.setItem('reg_password', document.getElementById('password').value);
            sessionStorage.setItem('reg_password_confirmation', document.getElementById('password_confirmation').value);

            // Pasamos al paso 2 visualmente sin tocar el servidor aún
            window.location.href = '/register/step2';
        } else {
            // Paso 2: Rescatamos los datos del usuario y unificamos nombres según 'StoreUserRequest'
            const userPayload = {
                name: `${sessionStorage.getItem('reg_first_name')} ${sessionStorage.getItem('reg_last_name')}`,
                email: sessionStorage.getItem('reg_email'),
                password: sessionStorage.getItem('reg_password'),
                password_confirmation: sessionStorage.getItem('reg_password_confirmation'),
                role: 'Propietario' // Inyección del rol obligatorio requerido por el backend
            };

            // Estructura exacta requerida por UpdateBusinessRequest y el Modelo Business
            const businessPayload = {
                name: document.getElementById('store_name').value.trim(),
                timezone: 'America/Managua', // Husos horaria IANA real para Nicaragua
                tax_rate: 0.1500 // IVA en formato fracción (15%) para bcmath decimal(5,4)
            };

            // Ejecutamos la creación del usuario primero en las rutas API de tus desarrolladores
            fetch('/v1/users', { 
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(userPayload)
            })
            .then(res => {
                if (!res.ok) throw new Error('Error al registrar las credenciales del usuario.');
                return res.json();
            })
            .then(() => {
                // Una vez creado el usuario, actualizamos el negocio a través de tu BusinessController (PUT)
                return fetch('/v1/business', { 
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(businessPayload)
                });
            })
            .then(res => {
                if (!res.ok) throw new Error('Error al actualizar los datos del negocio.');
                return res.json();
            })
            .then(() => {
                // Capturamos los mensajes personalizados del lang/es inyectados en tu formulario Blade
                const msgUser = form.getAttribute('data-msg-success-user') || '¡Usuario creado exitosamente!';
                const msgAccount = form.getAttribute('data-msg-success-account') || '¡Cuenta creada exitosamente!';

                // Mostramos la secuencia exacta de alertas que solicitaste
                alert(msgUser);
                alert(msgAccount);
                alert("Por favor, ingrese sus credenciales para acceder al sistema.");

                sessionStorage.clear();
                window.location.href = '/login'; // Te remite al login para que el dueño entre directo al dashboard
            })
            .catch(err => alert(err.message || 'Error en la sincronización con el servidor backend.'));
        }
    });
});

