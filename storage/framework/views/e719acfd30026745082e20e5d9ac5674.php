<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gintly - Iniciar Sesión</title>
    <script src="https://tailwindcss.com"></script>
    <link href="https://googleapis.com" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 flex items-center justify-center min-h-screen p-4">

    <div class="w-full max-w-md bg-white rounded-2xl border border-slate-100 shadow-xl p-8 transition-all duration-300">
        <!-- Logotipo -->
        <div class="flex justify-center mb-6">
            <div class="w-12 h-12 flex items-center justify-center">
                <img src="<?php echo e(asset('images/gintlylogo.png')); ?>" alt="Gintly Logo" class="w-full h-full object-contain">
            </div>
        </div>

        <!-- Título -->
        <div class="text-center mb-7">
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Ingresa a tu cuenta</h1>
            <p class="text-xs text-slate-400 mt-1">Digita tus credenciales para acceder al panel</p>
        </div>

        <!-- Formulario -->
        <form id="loginForm" class="space-y-4">
            <?php echo csrf_field(); ?>
            <div>
                <label for="email" class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">Correo Electrónico</label>
                <input type="email" id="email" name="email" required placeholder="propietario@gintly.test"
                    class="w-full h-10 px-4 text-xs bg-slate-50 border border-slate-100 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#146F8A]/25 focus:border-[#146F8A] transition-all text-slate-800">
            </div>

            <div>
                <label for="password" class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">Contraseña</label>
                <input type="password" id="password" name="password" required placeholder="••••••••••••"
                    class="w-full h-10 px-4 text-xs bg-slate-50 border border-slate-100 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#146F8A]/25 focus:border-[#146F8A] transition-all text-slate-800">
            </div>

            <!-- Contenedor para Alertas de Error de Validación -->
            <div id="loginFeedback" class="hidden text-[11px] bg-rose-50 text-rose-600 p-3 rounded-xl border border-rose-100/50 font-medium"></div>

            <button type="submit" id="submitBtn"
                class="w-full h-10 bg-[#146F8A] hover:bg-[#10596e] text-white font-bold text-xs tracking-wide rounded-xl shadow-lg shadow-[#146F8A]/25 transition-all duration-300 ease-in-out hover:scale-[1.02] active:scale-95 flex items-center justify-center gap-2">
                <span>Iniciar Sesión</span>
            </button>
        </form>
    </div>

    
</body>
</html>
<?php /**PATH C:\laragon\www\gintly_app\resources\views/auth/login.blade.php ENDPATH**/ ?>