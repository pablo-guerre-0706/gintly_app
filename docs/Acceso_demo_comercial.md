# Acceso temporal del negocio de evaluación

## Alcance y mecanismo

`EnsureActiveSubscription` no usa `businesses.status=trial` como sustituto de una suscripción. Antes de
esta intervención, `CommercialAccess` exigía una `plan_subscription` con vigencia pagada y proveedor,
modo y tienda coherentes. El modo TEST tampoco dispensaba de esa evidencia. Los fixtures de pagos
de las suites automatizadas no constituyen un mecanismo productivo de evaluación.

Se añade una concesión independiente en `demo_access_grants`, administrada exclusivamente mediante
`billing:demo-access`. No registra pagos, suscripciones, checkouts ni webhooks; no llama al proveedor.
Hay una fila única por negocio con plan del catálogo, motivo, comienzo, vencimiento y revocación.
Una concesión concede acceso solo si se cumplen simultáneamente:

- `BILLING_DEMO_ACCESS_ENABLED=true`.
- Slug e ID pertenecen al negocio seleccionado mediante `BILLING_DEMO_BUSINESS_SLUG`.
- Despliegue coherente `demo/test` y negocio operable, no suspendido/eliminado.
- Plan existente en el catálogo y `starts_at <= ahora < expires_at`, sin revocación.

El valor predeterminado está deshabilitado. No existe URL pública de activación. Una suscripción
pagada real prevalece; revocar la demo no cancela una suscripción real ni modifica su vigencia.
La concesión no cambia `businesses.plan`, propietario, roles, perfiles, sucursales ni contraseñas.
Los límites y funciones se obtienen del catálogo a través de `activePlanKey`. `cadena` permite las
funciones actuales del catálogo, hasta cinco sucursales y sin límite comercial de sesiones simultáneas;
no reemplaza Policies ni concede acciones superiores al rol del usuario.

`GET /api/v1/billing/subscription` conserva los campos de suscripción. Para una demo devuelve además
`access_source: "demo"` y `demo_access: {plan_key, starts_at, expires_at}`, con `grants_access: true`.
Sin suscripción conserva `status: "none"` y `plan_key`, `period`, `paid_until` nulos: no simula una
vigencia pagada. El login existente pasa por `/billing/access`; su consulta reconoce este acceso y
continúa a `/dashboard`. La presentación indica evaluación sin pago y no ofrece gestión de una
suscripción inexistente durante la demo. No se cambió el contrato de login ni `/me`.

Las rutas, guardas web/Sanctum, operabilidad, Spatie teams, Policies, aislamiento de sucursal y
autorización de recursos siguen intactos. No se incorporó MFA de otra copia; donde exista MFA, esta
concesión no sustituye ni elimina sus controles anteriores a la compuerta comercial.

## Preparación local

Trabajar en `C:\laragon\www\gintly_app`. No copiar `.env` de otra instancia. Las únicas opciones
locales añadidas son las dos siguientes; las credenciales/variantes existentes no cambiaron:

```dotenv
BILLING_DEMO_ACCESS_ENABLED=true
BILLING_DEMO_BUSINESS_SLUG=gintly-demo
```

El entorno local confirmado es `local`, host MySQL de loopback y base efectiva PDO `gintly_app`.
Se comprobó el negocio **1 / gintly-demo**, propietario **2**, y un negocio distinto **2** sin acceso.
No había caché de configuración ni rutas; no se ejecutó limpieza general. Si una instalación tiene
caché de configuración, regenerar únicamente esa configuración tras cambiar las opciones.

Aplicar SOLO la migración progresiva nueva después de confirmar la conexión efectiva:

```powershell
php artisan migrate --path=database/migrations/2026_10_08_000001_create_demo_access_grants_table.php
```

Comandos locales (el comando compara `SELECT DATABASE()`, ID y slug antes de escribir):

```powershell
php artisan billing:demo-access status gintly-demo --database=gintly_app --business-id=1
php artisan billing:demo-access grant gintly-demo --database=gintly_app --business-id=1 --days=7 --plan=cadena --reason="Evaluacion local autorizada del negocio demo"
php artisan billing:demo-access revoke gintly-demo --database=gintly_app --business-id=1
```

`grant` exige 1–30 días y motivo obligatorio de hasta 255 caracteres. Repetirlo renueva de forma
explícita el inicio/vencimiento de la misma fila; no ejecutarlo como tarea recurrente. `status` es de
solo lectura y distingue vigencia del registro de acceso efectivo. `revoke` es idempotente y funciona
incluso si se deshabilitó la bandera o cambió el modo. Conserva la fila, no borra historial operativo.
No hay renovación automática: al vencer, el negocio vuelve a la compuerta comercial normal.

Después de habilitar, iniciar sesión normalmente con un usuario activo del negocio seleccionado.
Los ROL-03 necesitan además su sucursal, perfiles y asignaciones reales; la demo no corrige esos datos.
Actualizar la página si el navegador conservó una consulta comercial anterior. Si Vite está en
desarrollo, debe estar disponible el servidor indicado por `public/hot`; en una entrega de producción
no distribuir ese archivo, generar assets y usar el manifest.

## Azure: instrucciones para el operador, NO ejecutadas

Publicar mediante el pipeline habitual estos archivos y el build; no subir `.env`, dependencias ni
datos locales. Configurar las dos opciones demo en App Settings. Esta concesión está limitada a una
instancia de evaluación **demo/test**: no cambiar a ese modo una instancia `commercial/live` con
suscripciones LIVE, porque el mecanismo existente rechaza suscripciones de un modo distinto.
La concesión no necesita nuevas credenciales de pago ni variantes para otorgarse.

En la consola SSH del contenedor correcto, confirmar entorno, conexión Laravel/PDO, nombre de BD,
slug e ID del negocio de evaluación. No asumir que los IDs de Azure coinciden con los locales. No
usar un endpoint público, `/run-migrations` ni un webhook para esta operación. Si el esquema falta,
aplicar únicamente la migración indicada (con `--force` cuando corresponda al entorno producción),
después de verificar el destino. Actualizar la caché de configuración si el pipeline la genera.

Reemplazar los valores `BD_CONFIRMADA`, `SLUG_EVALUACION` e `ID_CONFIRMADO`:

```sh
php artisan billing:demo-access status SLUG_EVALUACION --database=BD_CONFIRMADA --business-id=ID_CONFIRMADO
php artisan billing:demo-access grant SLUG_EVALUACION --database=BD_CONFIRMADA --business-id=ID_CONFIRMADO --days=7 --plan=cadena --reason="Evaluacion autorizada"
php artisan billing:demo-access revoke SLUG_EVALUACION --database=BD_CONFIRMADA --business-id=ID_CONFIRMADO
```

Revocar no requiere quitar ni debilitar middleware. Desactivar `BILLING_DEMO_ACCESS_ENABLED` es además
un corte defensivo; no sustituye registrar la revocación. No se realizaron despliegues, cambios de
App Settings, migraciones remotas ni llamadas de pago en esta intervención.

## Validaciones reproducibles

Requisitos: PHP/dependencias Composer ya instalados, SQLite en memoria para Unit, Node y dependencias
frontend existentes para build. No se añadieron paquetes. No ejecutar las suites `MysqlTestCase`
contra `gintly_app`: su allowlist es otra base y no se modificó.

```powershell
php vendor/phpunit/phpunit/phpunit --do-not-cache-result tests/Unit/Billing/DemoAccessTest.php
php vendor/phpunit/phpunit/phpunit --do-not-cache-result --testsuite Unit
node --test tests/frontend/*.test.mjs
node --check resources/js/modules/billing/contracts.js
node --check resources/js/modules/billing/index.js
node --check resources/js/modules/billing/view.js
node --check tests/frontend/billing-demo.test.mjs
node node_modules/vite/bin/vite.js build
php tests/Support/verify-demo-access.php --database=gintly_app --demo-id=1 --blocked-id=2
```

El build directo es equivalente al script `npm run build`; npm no está disponible en el PATH de esta
sesión. El verificador de aceptación exige entorno local, host de loopback y nombre efectivo PDO
esperado; usa identidades ya persistidas, GET al Kernel real y middleware intactos. Sesión/cache se
ponen en memoria solo durante ese proceso. No crea fixtures ni llama a proveedor. Es aceptación del
Kernel autenticado, **no una prueba de introducir contraseñas ni una sesión visual de navegador**.

Resultados obtenidos:

- Pruebas nuevas de demo: **21/21**, **178 aserciones**, código 0.
- Suite Unit: **34/34**, **232 aserciones**, código 0; sin omisiones.
- Suite frontend: **128/128**, cero fallos/omitidas, código 0.
- Sintaxis: **10 PHP** y **4 JavaScript**, código 0.
- Vite **8.2.1**, **106 módulos**, build aprobado. Aviso `PLUGIN_TIMINGS` de rendimiento; no error.
- Aceptación local final: **48 comprobaciones GET** aprobadas, código 0, dashboards de los tres roles,
  cuatro perfiles operativos y combinación, respuestas 403 a administración ajena y a otro tenant.
  El otro negocio devuelve `SUBSCRIPTION_REQUIRED` aunque envíe `business_id=1` en query.
- Autenticación anónima sigue recibiendo **401**. Filtro de bodega con sucursal de otro negocio:
  **422**. Los resultados de inventario y bodegas conservan la sucursal del operador.
- Revocación local real: el propietario demo recibió **403 / SUBSCRIPTION_REQUIRED**. Después se
  restableció mediante el mismo comando; estado final vigente hasta **2026-10-15T22:45:08+00:00**
  (16:45:08, Managua), sin renovación automática. El otro negocio no se alteró: sigue sin suscripción
  ni acceso.
- Manifest: **75 entradas** y archivos/imports existentes, código 0. Rutas API: **202**, sin ruta
  de concesión demo. `git diff --check`, ayuda Artisan y render de dashboard: código 0.
- La concesión no creó filas de suscripción: **0**. No se simularon pagos ni webhooks.
- Vite local (`/@vite/client`) respondió **200**. Se conservó `public/hot` de desarrollo; no es una
  fuente para integrar ni un archivo que deba distribuirse en Azure. No se inició un servidor nuevo.

## Manifiesto de esta intervención

Creados:

- `app/Console/Commands/DemoAccessCommand.php` — administración exclusiva por CLI.
- `app/Models/DemoAccessGrant.php` — concesión separada, vigente/revocada/vencida.
- `database/migrations/2026_10_08_000001_create_demo_access_grants_table.php` — almacenamiento de concesiones.
- `tests/Unit/Billing/DemoAccessTest.php` — seguridad, vencimiento, revocación y catálogo en SQLite.
- `tests/Support/verify-demo-access.php` — aceptación local reproducible de solo lectura.
- `tests/frontend/billing-demo.test.mjs` — contrato y presentación de la demo.
- `docs/Acceso_demo_comercial.md` — operación y validaciones.

Modificados:

- `config/billing.php` — bandera y negocio seleccionado, deshabilitados por defecto.
- `.env.example` — opciones sin secretos.
- `app/Services/Billing/CommercialAccess.php` — reutilización de compuerta/catálogo y concesión explícita.
- `app/Http/Middleware/EnsureActiveSubscription.php` — documentación; ejecución de la guarda no cambia.
- `app/Http/Controllers/Api/V1/BillingController.php` — lectura del acceso efectivo.
- `app/Http/Resources/SubscriptionStatusResource.php` — metadata demo separada de pago.
- `resources/js/modules/billing/contracts.js` — validación aditiva del contrato demo.
- `resources/js/modules/billing/index.js` — navegación por acceso confirmado; sin checkout para demo.
- `resources/js/modules/billing/view.js` — presentación honesta de concesión y vencimiento.
- `resources/views/billing/index.blade.php` — explicación de acceso por suscripción o concesión.

Configuración local únicamente: `.env` (dos opciones no sensibles añadidas). Generados: `public/build`,
vistas compiladas y demás cachés normales del render, no fuentes. Ningún archivo eliminado.
No cambiaron rutas, autenticación, Policies, seeders, paquetes, MFA ni la copia de Codex.
