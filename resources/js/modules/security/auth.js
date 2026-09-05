document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const feedback = document.getElementById('loginFeedback');
    const submitBtn = document.getElementById('submitBtn');

    if (!loginForm) return;

    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        // 1. Efecto visual de carga
        feedback.classList.add('hidden');
        submitBtn.disabled = true;
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = `<span>Validando credenciales...</span>`;

        const formData = new FormData(loginForm);
        const credentials = Object.fromEntries(formData.entries());

        try {
            // 2. Autenticación en tu API REST (Ajusta la URL a tu endpoint real de autenticación)
            const response = await fetch('/api/v1/login', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': credentials._token // Protección CSRF obligatoria
                },
                body: JSON.stringify({
                    email: credentials.email,
                    password: credentials.password
                })
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'El correo o la contraseña son incorrectos.');
            }

            // 3. Almacenar el Bearer Token en el navegador para que index.js del dashboard pueda leerlo
            if (data.token || data.access_token) {
                localStorage.setItem('auth_token', data.token || data.access_token);
            }

            // 4. Redirección limpia e inmediata al Dashboard
            window.location.href = '/dashboard';

        } catch (error) {
            console.error('Login Error:', error);
            // Mostrar error de credenciales inválidas en pantalla
            feedback.textContent = error.message;
            feedback.classList.remove('hidden');
            
            // Reestablecer botón
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
});
