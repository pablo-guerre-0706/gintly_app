<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Título de tu Empresa</title>
    
    <!-- Fuente Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Carga de Assets unificada con la Arquitectura Base -->
    <?php echo app('Illuminate\Foundation\Vite')([
        'resources/css/app.css',
        'resources/js/app.js'
    ]); ?>
</head>

<body class="hero-body">

    <div class="hero-container">
        <!-- Navegación -->
        <header class="navbar">
            <div class="logo-box">
                <!-- COLOCA AQUÍ TU LOGO (SVG o <img>) -->
                <img src="<?php echo e(asset('images/logo.svg')); ?>" alt="Logo">
            </div>
            <nav class="nav-links">
                <a href="#inicio" class="nav-item active">Inicio</a>
                <a href="#funciones" class="nav-item">Funciones</a>
                <a href="#modulos" class="nav-item">Módulos</a>
                <a href="#planes" class="nav-item">Planes</a>
                <a href="<?php echo e(route('register')); ?>" class="btn-pill-teal">Regístrate</a>
            </nav>
        </header>

        <!-- Contenido Principal -->
        <main class="hero-content">
            <!-- Texto Principal -->
            <div class="hero-text-col">
                <h1 class="hero-title">
                    El sistema de facturación y gestión empresarial diseñado para Nicaragua.
                </h1>
                <p class="hero-description">
                    Gestiona. Impulsa. Crece. Centraliza la administración de tu negocio con contabilidad automatizada, facturación, cobros, gestión de clientes e inventario en una sola plataforma en la nube.
                </p>
                <div class="hero-actions">
                    <a href="<?php echo e(route('register')); ?>" class="btn-primary">Regístrate</a>
                    <a href="<?php echo e(route('login')); ?>" class="btn-secondary">Iniciar sesión</a>
                </div>
            </div>

            <!-- Bloque Visual Derecho -->
            <div class="hero-visual-col">
                <div class="top-cards-row">
                    <!-- Tarjeta 1 -->
                    <div class="info-card">
                        <div class="card-text">
                            <span class="card-subtitle">Gestiona tu personal</span>
                            <h3 class="card-title">Une a todo tu equipo de trabajo</h3>
                        </div>
                        <!-- RUTA DE TU ÍCONO 1 -->
                        <img src="<?php echo e(asset('images/icono-personal.png')); ?>" alt="Personal" class="card-icon">
                    </div>

                    <!-- Tarjeta 2 -->
                    <div class="info-card">
                        <div class="card-text">
                            <span class="card-subtitle">Maneja tu inventario</span>
                            <h3 class="card-title">Las mejores herramientas</h3>
                        </div>
                        <!-- RUTA DE TU ÍCONO 2 -->
                        <img src="<?php echo e(asset('images/icono-inventario.png')); ?>" alt="Inventario" class="card-icon">
                    </div>
                </div>

                <!-- Tarjeta de Imagen Principal -->
                <div class="main-image-card">
                    <div class="radial-circles"></div>
                    <!-- RUTA DE LA FOTO DEL MODELO/PERSONA -->
                    <img src="<?php echo e(asset('images/modelo-principal.png')); ?>" alt="Gestión" class="man-image">
                </div>
            </div>
        </main>
    </div>

</body>
</html><?php /**PATH C:\laragon\www\gintly_app\resources\views/landing.blade.php ENDPATH**/ ?>