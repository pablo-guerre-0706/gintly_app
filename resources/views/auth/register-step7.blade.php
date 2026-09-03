<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gintly - ¡Felicitaciones!</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; margin: 0; padding: 0; overflow: hidden; height: 100vh; }

        /* Contenedor principal */
        .signup-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 24px 0 0 0;
            gap: 16px;
            position: relative;
            width: 100vw;
            height: 100vh;
            background: #FFFFFF;
            border-radius: 0;
            box-sizing: border-box;
            overflow: hidden;
        }

        /*  imágenes de fondo */
        .dashboard-bg {
            background-size: cover;
            background-position: top center;
            background-repeat: no-repeat;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            transform: translateZ(0);
            backface-visibility: hidden;
        }

        /* Animación */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in-down {
            animation: fadeInDown 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
    </style>
</head>
<body class="bg-white flex items-center justify-center h-screen w-screen overflow-hidden">

    <!-- Contenedor Principal -->
    <div class="signup-container">
        
        <!-- Sección Superior Central: Logotipo, Título, Descripción y Botón -->
        <div class="w-full max-w-(850px) flex flex-col items-center text-center z-20 animate-fade-in-down gap-2 shrink-0">
            
            <!-- Logotipo -->
            <div class="w-12 h-12 flex items-center justify-center transition-transform duration-300 hover:scale-105">
                <img src="{{ asset('images/gintlylogo.png') }}" alt="Gintly Logo" class="w-full h-full object-contain drop-shadow-md">
            </div>

            <!-- Título y Descripción -->
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight leading-tight">
                    ¡Felicitaciones! Ya eres parte de Gintly 
                </h1>
                <p class="text-xs text-slate-500 leading-relaxed max-w-(760px)">
                    Tu cuenta ha sido activada con éxito. Estás a un paso de transformar la gestión y el control de tu negocio. A partir de hoy, tienes el control total de tu inventario, tus ventas y tus cuentas por cobrar en un solo lugar. ¡Es momento de llevar tu negocio al siguiente nivel!
                </p>
            </div>

            <!-- Botón de Acción Principal -->
            <div class="pt-0.5">
                <a href="{{ route('dashboard') }}" 
                   class="inline-flex items-center justify-center gap-2.5 px-7 h-10 bg-[#146F8A] hover:bg-[#10596e] text-white font-bold text-xs tracking-wide rounded-xl shadow-lg shadow-[#146F8A]/25 transition-all duration-300 ease-in-out hover:scale-105 active:scale-95 cursor-pointer">
                    <span>Ingresa a Gintly</span>
                    <svg class="w-3.5 h-3.5 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                    </svg>
                </a>
            </div>

        </div>

        <!-- Sección Inferior: Dashboards -->
        <div class="w-full flex-1 relative flex items-start justify-center overflow-hidden">
            
            <div class="absolute w-(1400px) h-(400px) mt-8 top-(35px) pointer-events-none">
                
                <!-- 1. Dashboard del Fondo / Central (catalogo de productos) -->
                <div class="absolute top-(0px) left-(200px) w-(1000px) h-(450px) bg-white rounded-3xl shadow-xl border border-slate-200/80 overflow-hidden transform rotate-0 transition-all duration-500 ease-out hover:scale-[1.01] hover:z-25 origin-top pointer-events-auto cursor-pointer dashboard-bg"
                     style="background-image: url('{{ asset('images/catalogoproductos.png') }}');">
                </div>

                <!-- 2. Dashboard Izquierda (General / dashboard - Rotación negativa -->
                <div class="absolute top-(75px) left-(-70px) w-(900px) h-(480px) bg-white rounded-3xl shadow-2xl border border-slate-200/80 overflow-hidden transform rotate-12 transition-all duration-500 ease-out hover:scale-[1.01] hover:rotate-12 hover:z-30 origin-top-left pointer-events-auto cursor-pointer dashboard-bg"
                     style="background-image: url('{{ asset('images/dashboard.png') }}');">
                </div>

                <!-- 3. Dashboard Derecha (cierre de caja) - Rotación negativa -->
                <div class="absolute top-(75px) right-(-70px) w-(900px) h-(480px) bg-white rounded-3xl shadow-2xl border border-slate-200/80 overflow-hidden transform -rotate-12 transition-all duration-500 ease-out hover:scale-[1.01] hover:-rotate-12 hover:z-30 origin-top-right pointer-events-auto cursor-pointer dashboard-bg"
                     style="background-image: url('{{ asset('images/cierrecaja.png') }}');">
                </div>

            </div>

        </div>

    </div>

</body>
</html>