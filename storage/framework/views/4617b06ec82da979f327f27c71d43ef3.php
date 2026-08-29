<header class="flex items-center gap-4 p-6">
    <!-- Logo SVG blanco de tu colaborador -->
    <a href="<?php echo e(route('landing')); ?>" class="flex items-center justify-center w-12 h-12 rounded-full bg-white/5 border border-white/15 backdrop-blur-md hover:bg-white/10 transition-colors p-2.5">
        <svg class="w-full h-full" viewBox="0 0 43 47" fill="none" xmlns="http://w3.org">
            <path d="M43 32.6592C35.8361 36.8728 28.6639 41.078 21.5 45.2916C14.3361 41.078 7.16392 36.8728 0 32.6592C1.43278 31.8132 2.86557 30.9755 4.29835 30.1294L21.4918 40.2319C27.2311 36.8644 32.9623 33.4969 38.7016 30.1294L43 32.6592Z" fill="white"/>
            <path d="M43 23.9499C39.6733 25.9017 36.3548 27.8536 33.0282 29.8054C29.1827 32.0671 25.3372 34.3205 21.4918 36.5823C14.3278 32.3687 7.16392 28.1635 0 23.9499C1.43278 23.1039 2.86557 22.2662 4.29835 21.4201C10.0295 24.7876 15.7689 28.1551 21.5 31.5226C27.2311 28.1551 32.9705 24.7876 38.7016 21.4201L43 23.9499Z" fill="white"/>
            <path d="M43 14.3346C35.8278 18.5481 28.6639 22.7533 21.4918 26.9669C14.3278 22.7533 7.16392 18.5398 0 14.3346C7.16392 10.121 14.3278 5.91579 21.4918 1.70221C21.4918 3.76293 21.4918 5.83202 21.4918 7.89273L9.5766 14.8874C13.5456 17.2162 17.5146 19.5534 21.4918 21.8821C23.3857 20.768 25.2796 19.6539 27.1817 18.5398C29.5862 17.1324 31.9906 15.7251 34.3951 14.3262L30.0967 11.7964C27.2311 13.4801 24.3656 15.1639 21.5 16.8476V11.7964C24.3656 10.1126 27.2311 8.42885 30.0967 6.7451C34.3951 9.27492 38.6934 11.7964 43 14.3262V14.3346Z" fill="white"/>
        </svg>
    </a>
    <!-- Barra flotante con tus estilos .liquid-glass-nav -->
    <nav class="liquid-glass-nav flex items-center rounded-full px-2 py-1">
        <a href="<?php echo e(route('landing')); ?>" class="btn-glass-card rounded-l-full border-none">
            <span class="btn-label">Inicio</span>
        </a>
        <a href="<?php echo e(route('landing')); ?>#funciones" class="btn-glass-card border-none">
            <span class="btn-label">Funciones</span>
        </a>
        <a href="<?php echo e(route('landing')); ?>#modulos" class="btn-glass-card border-none">
            <span class="btn-label">Módulos</span>
        </a>
        <a href="<?php echo e(route('landing')); ?>#planes" class="btn-glass-card border-none">
            <span class="btn-label">Planes</span>
        </a>
        <a href="<?php echo e(route('register.step1')); ?>" class="btn-glass-card btn-liquid-cta rounded-full ml-2">
            <span class="btn-label text-white">Regístrate</span>
        </a>
    </nav>
</header>
<?php /**PATH C:\laragon\www\gintly_app\resources\views/layouts/partials/navbar-landing.blade.php ENDPATH**/ ?>