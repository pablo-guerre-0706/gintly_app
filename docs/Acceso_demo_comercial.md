# Acceso temporal de negocios de evaluación

## Alcance y mecanismo

`EnsureActiveSubscription` no usa `businesses.status=trial` como sustituto de una suscripción. Antes de
esta intervención, `CommercialAccess` exigía una `plan_subscription` con vigencia pagada y proveedor,
modo y tienda coherentes. El modo TEST tampoco dispensaba de esa evidencia. Los fixtures de pagos
de las suites automatizadas no constituyen un mecanismo productivo de evaluación.

Se utiliza una concesión independiente en `demo_access_grants`, administrada mediante
`billing:demo-access` y, opcionalmente, creada dentro del registro canónico durante una ventana explícita
(ver ampliación del 2026-10-09 al final). No registra pagos, suscripciones, checkouts ni webhooks; no llama al proveedor.
Hay una fila única por negocio con plan del catálogo, motivo, comienzo, vencimiento y revocación.
Una concesión concede acceso solo si se cumplen simultáneamente:

- `BILLING_DEMO_ACCESS_ENABLED=true`.
- En modo heredado, slug e ID pertenecen al negocio seleccionado mediante `BILLING_DEMO_BUSINESS_SLUG`.
  Con `BILLING_DEMO_MULTIPLE_BUSINESSES=true`, cada negocio necesita igualmente su propia concesión persistida.
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

## Ampliación multinegocio y ventana de altas — 2026-10-09

### Funcionamiento y límites

Se mantiene la tabla existente, su fila única por `business_id`, plan del catálogo y vencimiento exclusivo.
No hay migraciones nuevas. La nueva modalidad está deshabilitada por defecto. La bandera multinegocio
no concede acceso global: un negocio sin fila vigente sigue bloqueado. El slug demo anterior continúa
funcionando sin cambiar, renovar ni reemplazar su concesión.

El registro sigue recibiendo exclusivamente `business{name,timezone}` y los campos canónicos de `owner`.
No acepta opciones de evaluación, plan ni tenant procedentes del cliente. No autentica automáticamente.
La evaluación se crea después del propietario y su rol, dentro de la misma transacción que guarda la
idempotencia. Una falla revierte negocio, propietario, aprovisionamiento y concesión. El replay reutiliza
el resultado antes de esta operación: no crea otra concesión ni extiende su vencimiento. Lock, fingerprint,
CSRF, reglas de contraseña, throttle, autorizaciones y scopes no cambian.

La ventana se evalúa con la hora del servidor: `starts_at <= ahora < ends_at`. Los timestamps deben ser
RFC3339 válidos con zona explícita. Se registran inicio y vencimiento individual en UTC. El vencimiento
es `momento del alta + BILLING_EVALUATION_DAYS`, de 1 a 30 días. Cerrar la ventana o desactivar SOLO
`BILLING_EVALUATION_REGISTRATION_ENABLED` detiene nuevas concesiones, sin revocar las ya concedidas.
Al vencer/revocarse una concesión, la misma sesión vuelve a contratación normal salvo que tenga pago
real vigente. No hay cron que renueve automáticamente ni acceso derivado de `businesses.status=trial`.

Una configuración activa inválida (fechas, modo, banderas, duración o plan) falla cerrada y revierte el
alta; no presenta una evaluación exitosa sin concesión. Fuera de una ventana válida, se conserva el
registro normal sin concesión y el propietario sigue hacia contratación. Una suscripción pagada válida
prevalece. La evaluación conserva la identidad `access_source=demo`, `status=none`, `paid_until=null`;
el frontend existente la presenta honestamente y `/billing/access` permite continuar al dashboard.
El plan de la concesión gobierna solo funciones/límites comerciales; no concede roles ni permisos.

### Activación en Azure (pendiente, no ejecutada)

Publicar el lote revisado mediante el pipeline habitual y confirmar que App Service ejecuta su imagen
con SHA/digest correcto. Las variables son App Settings del servidor, nunca inputs ni variables Vite.
Esta modalidad reutiliza el mecanismo **demo/test** existente; no cambiar una instancia commercial/live
con suscripciones LIVE a demo/test. `APP_ENV=production` es válido y no se cambia para habilitar evaluación.

Ejemplo de ventana y duración, que el responsable debe aprobar/ajustar antes de activarla:

```dotenv
BILLING_DEPLOYMENT_PURPOSE=demo
BILLING_PROVIDER_MODE=test
BILLING_DEMO_ACCESS_ENABLED=true
BILLING_DEMO_BUSINESS_SLUG=gintly-demo
BILLING_DEMO_MULTIPLE_BUSINESSES=true
BILLING_EVALUATION_REGISTRATION_ENABLED=true
BILLING_EVALUATION_STARTS_AT=2026-10-09T00:00:00Z
BILLING_EVALUATION_ENDS_AT=2026-10-23T00:00:00Z
BILLING_EVALUATION_DAYS=7
BILLING_EVALUATION_PLAN=cadena
```

Conservar el slug demo real de esa instancia y sus valores de pago existentes; no copiar `.env` local.
No se necesitan variantes ni credenciales de Lemon Squeezy para conceder evaluación, pero la contratación
fuera de ella mantiene sus requisitos originales. Confirmar el schema existente y destino efectivo.
Solo si falta `demo_access_grants`, aplicar la migración progresiva existente de la primera intervención
tras autorización y comprobación de conexión; no usar migrate:fresh ni `/run-migrations`.

En el contenedor correcto, tras establecer App Settings, regenerar solo la configuración necesaria:

```sh
php artisan config:cache
php artisan billing:demo-access status SLUG_DEMO --database=BD_CONFIRMADA --business-id=ID_DEMO_CONFIRMADO
```

Reiniciar los procesos que mantengan configuración en memoria según el procedimiento de despliegue.
Los cambios en ACR no prueban que App Service ejecute la nueva imagen. No se realizaron publicación,
App Settings, migraciones remotas ni acciones del proveedor durante esta implementación. La ventana
se habilitó únicamente para los procesos QA; `.env` local real no fue modificado.

### Concesiones administrativas para negocios existentes

Cada objetivo requiere slug e ID exactos. El comando verifica `SELECT DATABASE()` y cada pareja antes
de escribir. `--target=slug:ID` es repetible: máximo 50 negocios explícitos por lote. Valida todos,
adquiere locks en orden por ID y aplica una sola transacción; un objetivo inválido/suspendido no deja
concesiones parciales. No afecta negocios no enumerados ni tiene endpoint público.

```sh
php artisan billing:demo-access grant SLUG_A --database=BD_CONFIRMADA --business-id=ID_A --target=SLUG_B:ID_B --days=7 --plan=cadena --reason="Evaluacion multinegocio autorizada"
php artisan billing:demo-access status SLUG_A --database=BD_CONFIRMADA --business-id=ID_A --target=SLUG_B:ID_B
php artisan billing:demo-access revoke SLUG_A --database=BD_CONFIRMADA --business-id=ID_A --target=SLUG_B:ID_B
```

`grant` renueva expresamente la fila existente; consultar antes de repetirlo si su resultado fuera incierto.
La ventana automática no limita una concesión CLI deliberada. Para detener nuevas altas de evaluación,
desactivar `BILLING_EVALUATION_REGISTRATION_ENABLED` o dejar cerrar la ventana. Revocar por comando los
negocios concretos cuando corresponda. Desactivar la bandera maestra es un corte global de TODAS las
concesiones, incluida la demo antigua: no hacerlo si se desea preservarla. Tampoco retirar la bandera
multinegocio mientras se desee mantener las concesiones de negocios distintos del slug heredado.

### Verificación ejecutada

- Unit en SQLite en memoria: **77 pruebas, 596 aserciones**, cero fallos/omisiones, código 0. Incluye
  37 casos del archivo de demo (ventana, catálogo, configuración inválida, revocación, lote y rollback),
  registro/idempotencia/diagnósticos y la guarda Vite existentes. Un primer ensayo tuvo una comparación
  de arrays por orden de claves en el test; se corrigió ordenando ambas, conservando igualdad estricta.
- Suite frontend completa: **148/148**, cero fallos/omisiones, código 0. Sin cambios al bundle frontend.
- Sintaxis: siete PHP y un JavaScript, código 0. Ayuda del comando multinegocio, nueve rutas billing,
  única ruta canónica POST de registro y `git diff --check`: código 0. No se cambiaron vistas/assets;
  no fue necesaria otra compilación Vite para este parche exclusivamente Backend y de pruebas.
- Navegador Chrome **154.0.8037.93**, backend Laravel real en `http://127.0.0.1:8840`, base efectiva
  Laravel/PDO `gintly_frontend_qa_rol03`: **47 comprobaciones aprobadas**, código 0. Sin middleware
  desactivado, respuestas simuladas, pagos o checkout. Cookies y CSRF reales; ausencia de auto-login 401.
- Altas A/B 201 y replay 201 con una fila de registro, propietario vinculado, rol/capacidades y concesión
  de vencimiento inalterado. Login manual 200, dashboard y APIs 200. Consultas manipuladas no cambian
  tenant; lectura del propietario ajeno recibe 403/404. Vencimiento de A: dashboard/API 403 y código
  `SUBSCRIPTION_REQUIRED`; B permanece en 200. C fuera de ventana: alta 201 sin concesión y login a billing.
- Concesión/revocación real en lote A/B, C no recibe acceso. Configuración activa con plan inválido:
  respuesta real 500 sanitizada y cero filas parciales de negocio/propietario/idempotencia.
- Cero excepciones JS, warnings o 404 inesperados. Se observaron seis errores de recurso HTTP esperados
  por pruebas negativas; no se afirma una consola totalmente vacía durante rechazos deliberados.
- Se aplicó SOLO la migración progresiva existente de concesiones a la QA, que no tenía esa tabla,
  después de la guarda efectiva y antes de los registros. No se migró la base local real ni Azure.
- Fixture `QA-REGISTER-EVAL-960545d4d4d8`: negocios 541/542/543 y propietarios 994/995/996. La limpieza
  acotada eliminó solo estas filas de prueba y sus concesiones; el caso D fallido no dejó negocio.
  Una lectura posterior con el helper final confirmó cero negocios restantes, usuarios huérfanos y
  filas de idempotencia huérfanas del token.
  Evidencia/capturas retenidas bajo `storage/app/qa/evaluation`, excluidas de integración.
- Demo local original: lectura confirmó negocio 1 vigente hasta `2026-10-15T22:45:08+00:00`, sin cambiarlo.
  El verificador histórico exige APP_ENV=local y rechazó el entorno efectivo production; repetido con
  APP_ENV=local SOLO para ese proceso, dio **48/48 GET**, tres roles y cuatro perfiles, sin mutaciones.

Comandos (código 0 en la validación final):

```powershell
php vendor/phpunit/phpunit/phpunit --do-not-cache-result --testsuite Unit
node --test tests/frontend/*.test.mjs
node --check tests/frontend/evaluation-browser.mjs
php -l app/Services/Billing/RegistrationEvaluationGrant.php
# En un proceso local de lectura, sin cambiar .env:
$env:APP_ENV='local'
php tests/Support/verify-demo-access.php --database=gintly_app --demo-id=1 --blocked-id=2
```

Reproducción QA: reutiliza PHP 8.3, Node 22.12+, Chrome/Chromium y Playwright externo, con Composer y
assets existentes. No instala paquetes de producción, no adjunta perfiles personales ni depende de
helpers omitidos en storage. Desde la raíz, preparar exclusivamente la conexión QA autorizada:

```powershell
$env:QA_REGISTRATION_BROWSER='1'
$env:QA_SUBSCRIPTION_BROWSER='1'
$env:QA_EVALUATION_BROWSER='1'
$env:QA_CANONICAL_BUILT_ASSETS='1'
$env:APP_ENV='local'
$env:APP_DEBUG='false'
$env:APP_URL='http://127.0.0.1:8840'
$env:DB_CONNECTION='mysql'
$env:DB_HOST='127.0.0.1'
$env:DB_DATABASE='gintly_frontend_qa_rol03'
$env:DB_URL=''
$env:DB_SOCKET=''
$env:QA_PHP='<ruta absoluta a php.exe>'
$env:QA_CHROME='<ruta absoluta a Chrome/Chromium>'
$env:QA_PLAYWRIGHT_MODULE='<ruta absoluta al index.mjs de Playwright externo>'
node tests/frontend/evaluation-browser.mjs
```

El ejecutable comprueba configuración Laravel/PDO, URL y puerto antes de crear datos; configura la
ventana únicamente en su propio proceso PHP. Si falta la tabla demo en QA, usar el helper `migrate`
con las mismas guardas y añadir a ese proceso `SESSION_DRIVER=file`, `CACHE_STORE=file`,
`SESSION_DOMAIN`/`SESSION_CONNECTION` vacíos, `SESSION_SECURE_COOKIE=false` y
`SANCTUM_STATEFUL_DOMAINS=127.0.0.1:8840`. Luego ejecutar:

```powershell
php tests/frontend/evaluation-browser-db.php migrate QA-REGISTER-EVAL-000000000000
```

Eso no crea fixtures ni ejecuta otras migraciones. El navegador genera y limpia un token nuevo en
cada ejecución y detiene su servidor/contextos. `public/hot` se conserva; el router QA verifica
explícitamente los assets compilados. Capturas no incluyen contraseñas y no se generan HAR/trazas.

### Manifiesto exclusivo de esta ampliación

Nuevos:

- `app/Services/Billing/RegistrationEvaluationGrant.php`.
- `tests/frontend/evaluation-browser-db.php`.
- `tests/frontend/evaluation-browser.mjs`.

Modificados:

- `app/Services/Auth/RegistrationService.php`.
- `app/Services/Billing/CommercialAccess.php`.
- `app/Console/Commands/DemoAccessCommand.php`.
- `config/billing.php`.
- `.env.example` (solo nombres/defaults sin secretos).
- `tests/Unit/Billing/DemoAccessTest.php`.
- `docs/Acceso_demo_comercial.md`.

Eliminados: ninguno. Excluir `.env`, QA/evidencias, cachés, dependencias y public/build. No se modificaron
autenticación, routes, middleware, contratos públicos, pagos, MFA, pipeline o datos previos. El cambio
preexistente de README y cachés fuera del alcance se conservó. No hubo staging, commit, push ni despliegue.
