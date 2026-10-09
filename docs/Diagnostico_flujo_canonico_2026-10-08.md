# Flujo canónico: diagnóstico y lote local para revisión

Fecha local: 8 de octubre de 2026. Proyecto: `C:\laragon\www\gintly_app`.

## Veredicto

**Actualización del diagnóstico, 9 de octubre 03:47 UTC (8 de octubre en Managua):**
la landing y `/register` vuelven a servir HTML normal, HTTP 200; ya no muestran
el parse error del bootstrap. El POST 500 comunicado por el responsable es un
problema posterior y distinto. Se localizó su mensaje en el lock de registro y
se preparó observabilidad sanitizada, todavía sin publicar. La excepción concreta
de Azure sigue pendiente del log; no se presume una causa TLS, credenciales o migración.

**Aceptación final pendiente: Azure y contratación TEST real no están aprobados.**
El registro, su resultado, la idempotencia y el login manual sí se comprobaron
en Chrome con Laravel/CSRF reales sobre `gintly_frontend_qa_rol03`.
No se incorporó MFA. Tampoco se fabricaron pagos, se enviaron webhooks falsos
ni se concedió acceso global. No se hizo commit, push, despliegue ni migración remota.

## Evidencia confirmada

### Antecedente Azure: la aplicación no podía arrancar

GET de `/`, `/register`, `/login` y `/api/v1/auth/register` devolvieron **HTTP 200,
Content-Type `text/html; charset=UTF-8`**, con este contenido público:

```text
Parse error: Unmatched '}' in /var/www/html/bootstrap/app.php on line 89
```

Última comprobación GET: `2026-10-09T01:20:30Z` (8 de octubre en Managua).
No se envió ningún POST a Azure. Es un error previo al arranque de Laravel;
por ello no se debe buscar exclusivamente en `storage/logs/laravel.log`:
es necesario el log PHP/Apache del contenedor.

El `bootstrap/app.php` local pasa `php -l`. SHA-256 local:
`493d3159120793cb16c04876013fd0364ea90ce48b07782dc259ec961edfe1bf`.
**No se editó este archivo:** se necesita publicar su versión válida dentro
del lote aprobado, no añadir MFA ni remendar el registro para aceptar HTML.
Se conoce el error de la instancia pública, pero no su digest ni su contenido
interno completo. No se atribuye todavía a una revisión o merge particular.

Un HTML 200 no es el éxito canónico 201 JSON. El estado de incertidumbre del
frontend evita correctamente afirmar un alta sin confirmación. Esta observación
no demuestra si una alta realizada en otro momento llegó a persistirse en Azure;
eso requiere lectura correlacionada de `registration_requests`, negocio y usuario.

### Lemon Squeezy: configuración TEST incompleta

Consultas **GET reales**, usando la configuración local sin imprimir secretos:

| Comprobación | Resultado |
| --- | --- |
| Tienda configurada | 200, identidad coincidente, moneda NIO |
| Plan Inicial mensual | 404; no existe variante publicada correspondiente |
| Plan Inicial anual | 200, publicada, 1,392,000 unidades menores, anual |
| Plan Comercio mensual/anual | 200/200, 228,000/2,736,000 unidades menores |
| Plan Cadena mensual/anual | 200/200, 440,000/5,280,000 unidades menores |
| Lista completa de variantes del mismo producto/tienda | Sin página adicional; falta Inicial mensual |
| Webhook configurado | GET 200, un webhook TEST, host `azurewebsites.net`, sin ruta |
| Eventos configurados | `subscription_created`, `subscription_updated`, `subscription_cancelled` |

El callback no es `https://gintly-app-web.azurewebsites.net/api/v1/billing/webhook`
y falta **`subscription_payment_success`**, el evento que usa
`SubscriptionService::PAYING_EVENT` para acreditar la vigencia.
`subscription_created`, un estado active o el retorno del checkout no conceden
acceso. No se modificó la tienda, las variantes, el webhook ni sus secretos.

Existía un checkout local de la demo en estado uncertain. La consulta completa
`LemonSqueezyGateway::findCheckoutByIntentKey()` devolvió **outcome absent**.
No se reenvió el POST ni se alteró ese intento. Se preservaron ambas filas
previas de checkout, sin crear otras en la base local `gintly_app`.

### URLs locales de retorno

Se corrigieron exclusivamente dos valores no secretos de `.env`:

```dotenv
BILLING_RETURN_URL=http://localhost:8000/billing/return
BILLING_CANCEL_URL=http://localhost:8000/billing/return
```

Antes apuntaban al puerto 3000 y a `/billing/success`/`billing/cancel`, ajenos a
las rutas existentes. `APP_URL` local usa `http://localhost:8000`.
La configuración no estaba cacheada: no fue necesario limpiar cachés.
Cancelar/abandonar el checkout solo navega al retorno; no cancela una suscripción.
No copiar `.env` a Azure: allí deben usarse URL pública HTTPS y configuración
TEST/LIVE explícitamente separada.

## Correcciones del lote

1. `.dockerignore`: excluye `public/hot`, sus backups y herramientas/artefactos
   bajo `storage/app/qa` y `storage/app/integration`. Antes una construcción
   local podía incorporar un indicador que dirige a Vite en el equipo del desarrollador.
2. `Dockerfile`: lint de PHP en app/bootstrap/config/routes antes de publicar;
   exige manifest y ausencia de hot. No arranca Laravel ni migra durante el lint.
   Añade etiqueta OCI de revisión.
3. Workflow: conserva latest y además publica el tag de SHA completo, pasando
   ese SHA a la etiqueta OCI. **No reinicia App Service ni ejecuta migraciones.**
4. Harness QA: inicializa action antes de la guarda para que un rechazo seguro
   no quede oculto por Undefined variable. Añade diagnóstico sanitizado de fallo.
5. Router exclusivo de pruebas: usa el manifest compilado sin mover ni borrar
   el hot activo. Exige CLI-server, opt-ins, entorno, URL exacta, configuración
   efectiva Laravel y PDO de la base allowlisted. No sustituye gateway,
   middleware, sesiones ni decisiones comerciales.

No fue necesario cambiar wizard, api-client, registro Backend ni login.
`README.md` cambió concurrentemente y se preservó; no pertenece a este parche.
Los cambios de concesión demo preexistentes también se preservaron íntegramente.

## Aceptación local y límites

Chrome `154.0.8037.93`, Playwright `1.62.1`, URL QA `http://127.0.0.1:8840`.
Última corrida: **QA-REGISTER-RESULT-585c8704567b, 34 checks, salida 0**.

- Registro real 201, JSON con exclusivamente data.business_slug/owner_email.
- UUID/CSRF por cabecera, body exacto, contraseña con espacios preservada.
- Doble envío bloqueado; un POST inicial.
- Resultado visible y contraseñas borradas; /me 401 antes del login.
- 422 real inducido alterando solo el email QA de la petición: sin alta;
  corrección con nuevo UUID y 201 real.
- Alta real procesada por Laravel y respuesta perdida mediante interceptación:
  lectura PDO confirma una fila; replay real 201 con misma clave y bytes,
  sin duplicación. La pérdida fue inducida; el commit y replay no fueron mocks.
- Login manual 200, /me 200, ROL-01/negocio/capacidades coherentes.
- Propietarios QA 531/532/533 vinculados a usuarios de su propio negocio;
  CheckoutRequest autoriza; GET /billing 200 con controles del propietario.
  Sin pago, grants_access permanece false.
- Registro con sesión humana 403; pantalla restringida HTML 403; logout 204.
- Resultado inspeccionado visualmente en 375 y 1280 px, sin overflow.
- Cero excepciones JS y 404 inesperados. Los avisos de recurso debidos a
  401/403/422 y abortos inducidos no se presentan como consola completamente vacía.

Dos corridas iniciales finalizaron con salida 1 al no observar logout. La segunda
detectó la recreación concurrente de public/hot. No se adjudicó este resultado
a un defecto del registro: con el router de assets aislado el recorrido y logout
pasaron. El hot activo quedó conservado; el único backup propio idéntico se retiró.

La demo local (negocio 1, propietario 2) conserva su concesión previa:
48 checks GET del Kernel, 0 fallos, todos los roles/perfiles requeridos;
otro negocio sin acceso sigue en 403. Cero suscripciones fabricadas en la demo.
Esta es evidencia de evaluación, **no de pago**.

No se ejecutó un nuevo checkout real: los bloqueos de variante/webhook deben
resolverse primero. Tampoco se utilizó el harness que activa fixtures de pago
para afirmar una contratación. Las suites MySQL protegidas para otra base no
se forzaron contra gintly_app ni la QA frontend.

## Comandos y resultados

Ejecutados con PHP 8.3.30 y Node del runtime disponible; cada resultado aprobado
terminó con salida 0:

| Comando / comprobación | Resultado final |
| --- | --- |
| `node --check` sobre resources/js y tests/frontend (.js/.mjs) | 119 archivos, 0 errores antes del router; nueva prueba/router JS también comprobados |
| `node --test tests/frontend/*.test.mjs` (glob expandido en PowerShell) | 133 aprobadas, 0 fallos, 0 omitidas |
| PHPUnit: propietario demo, concesión y gateway (comando debajo) | 41 pruebas, 273 aserciones, sin omisiones; SQLite/HTTP fake, no pago real |
| Lint de todos los PHP app/bootstrap/config/routes | 577 archivos, 0 errores |
| Lint de canonical-browser-router.php y subscription-browser-db.php | 0 errores |
| `node node_modules/vite/bin/vite.js build` | Vite 8.2.1, 106 módulos, manifest 75 entradas; salida 0 |
| `php artisan route:list --path=billing` | 9 rutas, salida 0 |
| `php tests/frontend/render-registration.php` | Registro/login/landing, IDs y ARIA válidos, manifest válido |
| `php tests/frontend/render-billing.php` | 6 variantes de vistas, landmarks/IDs/ARIA válidos |
| `php tests/Support/verify-demo-access.php --database=gintly_app --demo-id=1 --blocked-id=2` | 48 checks, 0 fallos |
| `git diff --check` | 0 |

Vite emitió solo aviso de tiempo de plugins Tailwind; no error de compilación.
Docker, az y gh no están instalados/disponibles en esta sesión. No se ejecutó
docker build ni se comprobó la imagen remota con una CLI autenticada.

```powershell
# Desde el repositorio; PHP/Node instalados y QA sin caché apuntando a otra BD.
php vendor/phpunit/phpunit/phpunit --filter 'DemoOwnerLinkTest|DemoAccessTest|LemonSqueezyGatewayTest'
$frontendTests = @(Get-ChildItem tests/frontend -File -Filter '*.test.mjs' | Select-Object -ExpandProperty FullName)
node --test @frontendTests
node node_modules/vite/bin/vite.js build
php artisan route:list --path=billing
php tests/frontend/render-registration.php
php tests/frontend/render-billing.php
```

Para repetir únicamente registro en navegador, desde este repositorio,
usar herramientas externas existentes (no instalarlas en dependencias de producción):

```powershell
$env:QA_PHP = 'RUTA_ABSOLUTA_A_PHP_8_3'
$env:QA_CHROME = 'RUTA_ABSOLUTA_A_CHROME'
$env:QA_PLAYWRIGHT_MODULE = 'RUTA_ABSOLUTA_EXTERNA_A_PLAYWRIGHT_1_62_1/index.mjs'
$env:QA_REGISTRATION_BROWSER = '1'
$env:QA_SUBSCRIPTION_BROWSER = '1'
$env:QA_CANONICAL_BUILT_ASSETS = '1'
$env:QA_HEADLESS = '1'
$env:APP_ENV = 'local'
$env:APP_DEBUG = 'false'
$env:APP_URL = 'http://127.0.0.1:8840'
$env:DB_CONNECTION = 'mysql'
$env:DB_HOST = '127.0.0.1'
$env:DB_DATABASE = 'gintly_frontend_qa_rol03'
$env:DB_URL = ''
$env:DB_SOCKET = ''
$env:SESSION_DRIVER = 'file'
$env:SESSION_CONNECTION = ''
$env:SESSION_DOMAIN = ''
$env:SESSION_SECURE_COOKIE = 'false'
$env:CACHE_STORE = 'file'
$env:SANCTUM_STATEFUL_DOMAINS = '127.0.0.1:8840'
# Credenciales QA locales existentes: no imprimir ni añadir a este documento.
& $env:QA_PHP tests/frontend/subscription-browser-db.php preflight
if ($LASTEXITCODE) { throw 'QA rechazada; no escribir' }
node tests/frontend/registration-result-browser.mjs
```

El router nuevo y subscription-qa-guard.php son fuentes de pruebas entregables.
No se depende de helpers fuente omitidos bajo storage/app/qa. Servidor y perfil
propios se detienen/retiran al terminar; no se detuvo Vite ni el servidor del usuario.

## Lote para revisar antes de Azure

**No aplicado remotamente.**

1. Obtener digest/tag realmente ejecutado, logs de arranque PHP/Apache y,
   en la consola del contenedor, estas lecturas sin secretos:

   ```sh
   php -l /var/www/html/bootstrap/app.php
   sha256sum /var/www/html/bootstrap/app.php
   ```

2. Revisar/publicar el lote válido y seleccionar su tag SHA/digest en App Service.
   El workflow actual construye/push, pero no garantiza una actualización de
   la instancia. Verificar la revisión OCI y el digest tras el rollout autorizado.
   No basta con que el tag latest exista en ACR.
3. Una vez que arranque Laravel, comprobar migraciones y conexión efectiva en
   consola; `php artisan migrate:status` es diagnóstico. Comparar esquema de
   registro/MOD-SUB. **Cualquier migración pendiente se presenta primero para
   aprobación**, con base y path exactos; nunca usar una URL pública.
4. Configurar Azure para TEST con propósito demo, tienda/clave TEST, seis
   variantes publicadas y callback firmado a
   `https://gintly-app-web.azurewebsites.net/api/v1/billing/webhook`.
   Crear/publicar Inicial mensual NIO 1,160.00 y usar su ID verificado; no
   reutilizar el anual ni el Default pendiente. Mantener LIVE separado.
5. Suscribir el webhook TEST a subscription_created, subscription_updated,
   subscription_cancelled, subscription_expired, subscription_payment_success,
   subscription_payment_failed y subscription_payment_refunded. Verificar
   secret coincidente sin publicarlo. Return/cancel Azure: URL HTTPS pública
   `/billing/return`. No desactivar CSRF globalmente; solo el webhook firmado
   utiliza la excepción existente.
6. No completar pagos TEST de negocios QA locales contra un webhook de Azure:
   sus IDs pertenecen a bases distintas. La prueba real completa debe ocurrir
   íntegramente en el mismo entorno/backend/base que recibe la notificación.
7. Tras aprobación del lote/configuración remota, crear un fixture Azure
   identificable una sola vez: CSRF 204, registro 201 JSON, /me 401, login manual
   200, propietario vinculado, checkout TEST 201, pago de prueba del proveedor,
   webhook firmado confirmado/procesado, evidencia de pago única, grants_access
   true y dashboard 200. Antes de reintentar un resultado incierto, consultar
   la fila idempotente y el proveedor. No usar tarjetas ni cargos reales.

Falta recibir la evidencia/acceso de lectura a la consola de Azure. La respuesta
"sí" a facilitar acceso no aportó aún digest, logs ni una conexión autenticada.
No se pidió ningún secreto por chat. Hasta completar los puntos anteriores no
declarar aceptación final Azure ni habilitación LIVE.

## Manifiesto exacto de esta intervención

Modificados:

- `.dockerignore`
- `.github/workflows/deploy.yml`
- `Dockerfile`
- `tests/frontend/registration-browser-runtime.mjs`
- `tests/frontend/registration-result-browser.mjs`
- `tests/frontend/subscription-browser-db.php`
- `.env`: únicamente return/cancel locales; **no versionar ni copiar**.

Creados:

- `tests/frontend/canonical-browser-router.php`
- `tests/frontend/canonical-deployment.test.mjs`
- `docs/Diagnostico_flujo_canonico_2026-10-08.md`

Eliminados: ninguna fuente. Solo se retiraron perfiles propios por los harness
y el backup temporal hot idéntico. Los archivos previos de concesión demo y
los cambios ajenos no forman parte de este manifiesto y no se revirtieron.

Excluidos del lote fuente: .env/secretos, public/build, public/hot, dependencias,
storage/app/qa, perfiles, capturas y cache de ejecución. public/build se regenera.

Fixtures retenidos intencionalmente únicamente en `gintly_frontend_qa_rol03`:

- QA-REGISTER-RESULT-262d9b9727cd: negocios 525/526/527 (corrida fallida).
- QA-REGISTER-RESULT-ce373f08a92f: negocios 528/529/530 (corrida fallida).
- QA-REGISTER-RESULT-585c8704567b: negocios 531/532/533 (corrida final aprobada).

Cada alta confirmada conserva un propietario y una fila idempotente. Contraseñas
generadas solo en memoria; no hay una credencial reutilizable guardada. El
fixture previo QA-REGISTER-6defd52506e9 (negocio QA 5), la tabla legacy y datos
anteriores permanecen. No se ejecutó limpieza general.

Evidencia final/capturas:
`storage/app/qa/registration-result/QA-REGISTER-RESULT-585c8704567b/`.
Las capturas result-375.png y result-1280.png fueron inspeccionadas. La evidencia
incluye también recuperación real. No publicar estos artefactos en la imagen.

## Microcierre posterior: 500 por indisponibilidad del registro

### Lo confirmado y lo que aún no se conoce

- **Caso comunicado por el responsable:** POST `/api/v1/auth/register`, HTTP 500,
  mensaje «No se pudo completar el registro por indisponibilidad temporal…».
  No se reprodujo ese POST remotamente ni se creó otra alta para diagnosticarlo.
- **Lectura pública propia:** GET `/` a `2026-10-09T03:47:12Z`, HTTP 200,
  `text/html; charset=utf-8`, 77,255 bytes; GET `/register` a `03:47:13Z`,
  HTTP 200, mismo Content-Type, 74,333 bytes. Sin el parse error anterior.
  Esto no acredita la imagen ejecutada, el registro exitoso ni la conexión a BD.
- **Origen local único del mensaje:** `RegistrationLockUnavailableException::__construct()`.
  Su `render()` devuelve 500 con `message`. La lanza `RegistrationService::acquireLock()`
  cuando `SELECT GET_LOCK(?, ?) AS locked` no confirma adquisición.
- El recorrido es `RegisterRequest` → `RegisterController::__invoke()` →
  `RegistrationService::register()` → fingerprint → lock → consulta idempotente →
  transacción de alta/Observer/propietario/rol → Resource 201.
  El fallo señalado pertenece al lock, **antes** de la consulta de idempotencia y
  de la transacción del alta en esa ejecución. No demuestra que un intento previo
  con la misma clave no haya creado el negocio: no reiniciar el intento ni sus datos.
- Antes del parche, `acquireLock()` capturaba **cualquier Throwable** de la consulta,
  ejecutaba `report($e)` y devolvía false. Después se lanzaba otra excepción sin
  conservar la causa. Los resultados 0 (timeout) y NULL no tenían un reporte que
  los distinguiera. `releaseLock()` también reportaba errores crudos.
- La configuración predeterminada del repositorio es stack → single, archivo
  `storage/logs/laravel.log`; no necesariamente aparece en Log stream del contenedor.
  No se pudo leer la configuración efectiva de logging en Azure. En las últimas
  1,800 líneas del log **local** no se encontraron entradas de ese mensaje/GET_LOCK;
  ese log no acredita lo que sucede en Azure.

### Corrección preparada: observabilidad, no bypass

1. Se conserva la causa como `previous` y se distingue `exception`, `timeout`,
   `null_result` o `unexpected_result`. Solo el resultado 1 permite continuar,
   con el mismo PDO de escritura y espera acotada. No se retiró el lock ni se
   cambiaron atomicidad, fingerprint, UUID, roles, propietario o permisos.
2. `RegistrationLockUnavailableException::report()` emite **un** evento sanitizado,
   evitando el reporte Laravel crudo y duplicado de la excepción y su causa.
   El cuerpo JSON/HTTP queda igual; añade únicamente el header
   `X-Registration-Diagnostic-ID`, UUID correlacionado con ese evento.
3. `RegistrationFailureReporter` escribe `registration.infrastructure_failure`
   mediante un escritor Monolog aislado basado en la configuración dedicada
   `registration`, JSON a `php://stderr`, nivel error. No hereda Context, contextos
   compartidos, processors, taps ni listeners de logging de Laravel.
   No exige cambiar LOG_CHANNEL ni APP_DEBUG. Si falla el canal, usa `error_log`
   con el mismo contenido sanitizado. La preparación del contexto y ambos destinos
   están protegidos: no cambian el resultado de la operación. Si fallan ambos,
   puede perderse el evento; el HTTP y su ID siguen siendo los originales.
4. El reporte contiene etapa, motivo, clase de excepción/causa, archivo relativo y
   línea, SQLSTATE/código numérico cuando están disponibles, driver efectivo
   (resolviendo DB_URL sin conectarse), presencia de DB_URL/APP_KEY/pdo_mysql,
   configuración cacheada y espera. **No contiene** mensajes crudos de excepción,
   SQL, bindings, request, contraseñas, cookies, DSN, host, usuario de BD, email,
   clave idempotente, fingerprint ni stack con argumentos.
5. Un fallo de liberación se identifica como `stage=release_lock` sin romper un
   resultado ya confirmado. La demo, frontend y MFA no se modificaron.

La categoría del código numérico es una clasificación diagnóstica, no evidencia
de que ese código haya ocurrido en Azure. TLS, conectividad, autenticación, driver
equivocado y timeout siguen siendo alternativas hasta obtener el evento real.

### Dependencias del flujo revisadas

- Conexión efectiva **local**: MySQL en host local, base `gintly_app`; APP_DEBUG=false,
  APP_KEY presente, sin config cacheada ni DB_URL, pdo_mysql disponible. Se leyó
  configuración, sin conectar para escribir ni modificar `.env`.
- Azure debe usar la conexión de escritura correcta; `DB_URL`, si existe, puede
  prevalecer sobre los valores DB_HOST/DB_DATABASE/driver. Revisar host, puerto,
  identidad, contraseña, firewall/red y TLS privadamente, sin compartir secretos.
  `config/database.php` admite CA mediante `MYSQL_ATTR_SSL_CA`; su fichero debe
  existir en el contenedor si se configura. No desactivar validación TLS ni
  cambiar `require_secure_transport` como solución especulativa.
- GET_LOCK y RELEASE_LOCK deben funcionar en la misma sesión MySQL/MariaDB.
  El timeout canónico es 10 segundos, limitado a 1–60. 0 y NULL son distintos.
- Luego se requieren `registration_requests` (uuid UNIQUE/fingerprint/resultado),
  businesses/users con owner_user_id, clientes genéricos, secuencias, reglas de
  anomalías, reglas fiscales y tablas/pivots Spatie con business_id/guard web.
  El Observer sigue siendo síncrono dentro de la transacción. **Una tabla de
  idempotencia faltante falla después de adquirir el lock**: no atribuirle el
  mensaje de lock ni ejecutar migraciones sin evidencia/aprobación.
- Sesión/CSRF/APP_KEY y almacén del rate limiter operan antes del controlador.
  No rotar APP_KEY: también sirve de respaldo estable al fingerprint.

### Procedimiento mínimo para Roberto, sin SSH

1. Primero consultar **logs existentes** del intento fallido: App Service
   `gintly-app-web` → Monitoring → Log stream. Alternativa autenticada Kudu/SCM:
   `https://gintly-app-web.scm.azurewebsites.net/api/logs/docker` y
   `https://gintly-app-web.scm.azurewebsites.net/api/logs/docker/zip`.
   No pegar el ZIP ni líneas crudas en el chat: pueden contener datos antiguos.
2. Si no se captura stdout/stderr, Roberto puede activar Container logging a
   filesystem. **Es un cambio remoto que requiere su aprobación; no se ejecutó.**
   Con Cloud Shell/Azure CLI autenticada, reemplazando el resource group real:

   ```powershell
   $rg = 'RESOURCE_GROUP_REAL'
   # Solo si se aprobó habilitar captura de logs:
   az webapp log config --name gintly-app-web --resource-group $rg --docker-container-logging filesystem
   # Lectura; también disponible en Portal > Log stream:
   az webapp log tail --name gintly-app-web --resource-group $rg
   ```

3. Si los logs previos no permiten conocer la causa, revisar/publicar **este lote**
   junto al lote pendiente correspondiente y seleccionar su imagen en App Service,
   comprobando SHA/digest efectivo. Mantener APP_DEBUG=false. No se hizo commit,
   push, despliegue, cambio de configuración remota o migración en esta intervención.
   El nuevo canal emite a stderr incluso si LOG_CHANNEL continúa como single/stack.
   Una configuración cacheada antigua debe regenerarse exclusivamente en el
   procedimiento de publicación aprobado, nunca mediante una URL pública.
4. No repetir un registro incierto para obtener un log. Antes de cualquier
   recuperación, un administrador debe consultar de solo lectura, en la **BD de
   Azure correcta**, registration_requests por la UUID original, y comprobar el
   negocio/propietario enlazado si hay fila. No publicar UUID, email ni fingerprint.
   Si el intento se recupera posteriormente con autorización, usar la misma clave
   y exactamente los mismos datos; nunca generar otra alta a ciegas.
5. Para un siguiente fallo observado tras publicar, copiar de Network únicamente
   método/URL, UTC, HTTP, Content-Type, mensaje sanitizado y
   `X-Registration-Diagnostic-ID`. Correlacionar ese ID con el evento
   `registration.infrastructure_failure`. Compartir solo su contexto allowlisted.
   `stage=acquire_lock, reason=exception` aporta clase/códigos de la causa;
   `reason=timeout` exige investigar el lock retenido sin matar sesiones a ciegas;
   `null_result` requiere revisar el motor. Un release_lock es un incidente posterior
   y no demuestra por sí mismo que haya fallado el alta.

La imagen usa php:8.3-apache y no instala/configura sshd ni el puerto SSH interno:
el SSH del portal no es una vía de diagnóstico que este Dockerfile garantice.
Esto es consistente con la terminal negra, pero **no se comprobó su causa remota**.
No se amplió la imagen para incorporar SSH: Log stream/SCM permite capturar el
error sin hacerlo. No hay CLI Azure instalada ni sesión de consola aportada aquí.

Fuentes oficiales de estos procedimientos:
[logs de contenedores App Service](https://learn.microsoft.com/en-us/azure/app-service/configure-custom-container#access-diagnostic-logs),
[az webapp log](https://learn.microsoft.com/en-us/cli/azure/webapp/log),
[SSH de App Service](https://learn.microsoft.com/en-us/azure/app-service/configure-linux-open-ssh-session),
[GET_LOCK de MySQL](https://dev.mysql.com/doc/refman/8.4/en/locking-functions.html),
[TLS de Azure MySQL](https://learn.microsoft.com/en-us/azure/mysql/flexible-server/security-tls-how-to-connect).

### Verificación de este microcierre

- PHPUnit focalizado: `php vendor/phpunit/phpunit/phpunit --filter
  'RegistrationDiagnosticsTest|DemoOwnerLinkTest|DemoAccessTest'`:
  **41 pruebas aprobadas, 344 aserciones, salida 0**. SQLite exclusivamente en memoria;
  fallos de lock inducidos, sanitización, replay, header/reporte, rechazo antes de
  escribir y regresión de demo. No se forzó MysqlTestCase contra otra allowlist.
- `node --test tests/frontend/registration.test.mjs
  tests/frontend/api-client-registration.test.mjs`: **24 aprobadas, 0 fallos,
  0 omitidas, salida 0**. Transportes simulados: no se presentan como registro Azure.
- `php -l` en service, excepción, reporter, config, nueva prueba y bootstrap:
  **6 archivos, 0 errores, salida 0**. Bootstrap no se modificó.
- Prueba **MySQL real**, protegida por `subscriptionQaGuard()` y configuración
  Laravel/PDO efectiva en `gintly_frontend_qa_rol03`: una segunda conexión retuvo
  un lock temporal propio; la función real acquireLock obtuvo timeout y su render
  produjo 500/header. Se observó el evento JSON en stderr. Al liberar el lock,
  adquirirlo/liberarlo funcionó. Los conteos de businesses/users/registration_requests
  antes y después fueron idénticos; las 12 tablas núcleo revisadas existen.
  **Sin POST de registro ni filas nuevas**; ambas conexiones liberaron su lock.
  Esta es evidencia local de observabilidad, **no reproducción de la causa Azure**.
- `php artisan route:list --path=api/v1/auth/register --json`: una ruta POST real,
  RegisterController y throttle:register; salida 0. `git diff --check`: salida 0;
  índice Git sin archivos staged. No se cambió frontend: no se regeneró el bundle ni se repitió su aceptación
  visual cerrada. No se inició un servidor temporal ni se creó un fixture adicional.

### Manifiesto exclusivo del parche de observabilidad

Modificados (preservando los cambios previos):

- `app/Services/Auth/RegistrationService.php`
- `app/Exceptions/RegistrationLockUnavailableException.php`
- `config/logging.php`
- `docs/Diagnostico_flujo_canonico_2026-10-08.md` (fuente creada en el lote previo,
  aún sin versionar, ahora actualizada)

Creados:

- `app/Support/RegistrationFailureReporter.php`
- `tests/Unit/Auth/RegistrationDiagnosticsTest.php`

Eliminados: ninguno. No se modificaron Docker/workflow, demo, MFA, .env ni fuentes
frontend; no se limpiaron caches ni se cambiaron datos en este parche. Sin staging/commit/push. La aceptación
Azure continúa pendiente del evento real y de la corrección que ese evento justifique.

### Microcierre de seguridad del lote de seis archivos

Revisión posterior del diff completo contra HEAD (`c9517a3`) y de las tres fuentes
sin versionar. Este cierre no crea negocios, no modifica la demo, no incorpora MFA
y no realiza staging, commit, push ni cambios remotos.

**Defectos comprobados y ajustes mínimos:**

- El reporter anterior llamaba a `Log::channel('registration')`. Aunque su contexto
  propio estaba sanitizado, Laravel podía añadir `withContext`/`shareContext` y
  `ContextLogProcessor`. Una reproducción aislada en SQLite `:memory:` comprobó
  que claves sintéticas de contraseña, cookie, email y payload heredados llegaban
  al evento. No se observaron ni publicaron datos reales de Azure.
- Se sustituye únicamente ese transporte por Monolog aislado usando el sink fijo
  definido en `config/logging.php`: stderr, JSON y nivel error. No se invocan
  processors/taps/listeners ni el fallback emergency de LogManager, que podría
  reportar una excepción cruda. La configuración global permanece intacta y
  tampoco se borran los contextos de otros módulos.
- La construcción del contexto y el respaldo `error_log` estaban fuera de una
  protección completa. Ahora ambos destinos y la construcción están guardados.
  No se reporta la excepción del logger y se suprimen advertencias del sink de
  respaldo. Si no puede construirse la metadata, queda únicamente endpoint,
  etapa e ID seguro. Si ambos sinks fallan, se preserva la respuesta original,
  aunque no puede garantizarse la entrega del evento.
- Solo se admite un UUID como ID diagnóstico; motivos y etapas son allowlisted.
  Los ficheros externos se presentan como `[external]`, sin rutas del equipo.
  Los mensajes de excepción, SQL, bindings, request, credenciales, cookies, datos
  personales, UUID idempotente y fingerprint no se serializan. El UUID del header
  es distinto de la clave idempotente y coincide con el evento principal/respaldo.

**Locks, atomicidad e idempotencia:**

- `GET_LOCK(?, ?)` y `RELEASE_LOCK(?)`, parámetros, nombre del lock, PDO de escritura,
  espera acotada 1–60 segundos, exclusión antes de adquirir y liberación en
  `finally` conservan la semántica anterior. El parche cambia el retorno privado
  bool de `acquireLock` por una excepción diagnóstica ante fallo; no elimina
  ni relaja la adquisición.
- Comparación de tokens PHP contra HEAD: 13 métodos sin cambios funcionales,
  incluidos `runResilient`, `persist`, `resolveExisting`, `fingerprint`, su secreto,
  generación de slug, clasificación SQL y `lockName`. Solo los tres métodos del
  recorrido del lock tienen las diferencias de observabilidad descritas.
- Las pruebas comprueban rechazo antes de escribir, mismo replay sin filas nuevas,
  conflicto de payload 409 y liberación obligatoria. Un fallo de liberación y
  de ambos loggers no reemplaza ni el resultado confirmado ni el 409 original.

**Verificación final reproducible:**

```powershell
Set-Location C:\laragon\www\gintly_app
$php = 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
& $php vendor/phpunit/phpunit/phpunit --do-not-cache-result --display-warnings --display-deprecations --display-notices --filter 'RegistrationDiagnosticsTest|DemoOwnerLinkTest|DemoAccessTest'
$files = @(
    'app/Services/Auth/RegistrationService.php',
    'app/Exceptions/RegistrationLockUnavailableException.php',
    'app/Support/RegistrationFailureReporter.php',
    'config/logging.php',
    'tests/Unit/Auth/RegistrationDiagnosticsTest.php',
    'bootstrap/app.php'
)
foreach ($file in $files) {
    & $php -l $file
    if ($LASTEXITCODE -ne 0) { throw "Error de sintaxis: $file" }
}
git diff --check
```

- PHP 8.3.30 / PHPUnit 12.5.31: **52 pruebas, 451 aserciones, 0 fallos, 0 errores,
  0 omitidas, salida 0**. Resultado contrastado con JUnit temporal propio y eliminado.
  Incluye 22 casos de diagnóstico y 30 casos de propietario/demo. Se usa SQLite
  exclusivamente en memoria, con guardas explícitas; no se ejecutan suites MySQL
  fuera de sus allowlists ni se desactiva middleware global.
- Sin warnings/deprecations/notices reportados en la ejecución final. Una invocación
  preliminar del reporte usó `--display-phpunit-warnings`, no admitido por esta
  versión: salida 1 antes de ejecutar pruebas. Se corrigió el comando y se repitió
  la suite completa focalizada; no fue un fallo de aplicación ni una prueba omitida.
- Prueba de los sinks **reales**, no solo dobles: proceso Laravel/PDO protegido en
  SQLite `:memory:`, contexto global sintético y dos fallos sintéticos reportados
  mediante ExceptionHandler. Se capturaron dos eventos, uno de Monolog stderr y
  otro del respaldo al retirar su configuración solo en memoria. En ambos, el
  ID del header coincide con el evento y no aparece ningún sentinel sensible.
  HTTP 500 y cuerpo original preservados; salida 0. Sin POST ni altas.
- Lint de seis archivos PHP, incluidos bootstrap sin modificar: salida 0.
  No se modificó frontend y no se repitió su build/aceptación visual cerrada.

**Veredicto del lote:** apto para commit y despliegue del parche de diagnóstico,
no una declaración de registro Azure resuelto. Se mantienen los mismos seis
archivos del manifiesto anterior; este microcierre ajustó reporter, configuración
(comentario), pruebas y este documento. Service y excepción se revisaron sin
añadir modificaciones respecto del parche recibido. No hay archivos eliminados
ni dependencias nuevas. Los demás cambios de Roberto siguen fuera de este lote.
La causa concreta del 500 de Azure continúa pendiente de su evento correlacionado;
mantener APP_DEBUG=false y el procedimiento de lectura de logs descrito arriba.

## Conciliación del lote completo para staging, sin commit ni publicación

La lectura actual de `git status --porcelain=v1 -uall` muestra **62 entradas**,
no 56: 32 fuentes/pruebas/documentos y 30 ficheros generados de caché. Se revisaron
los diffs de las fuentes modificadas, los seis archivos previamente preparados
y el contenido/dependencias de las fuentes nuevas. No se revirtieron cambios.

El lote seleccionado contiene **31 archivos: 12 incorporaciones y 19 modificaciones**.
Incluye el diagnóstico ya preparado, la concesión demo caducable y su presentación,
configuración de ejemplo sin secretos, build/identificación de imagen Azure y
harness de aceptación reproducibles. README y las cachés quedan fuera.

`bootstrap/app.php`, `database/seeders/UserSeeder.php` y `routes/web.php` ya están
versionados y coinciden con HEAD: no tienen cambios pendientes que añadir. El
bootstrap pasa lint; conserva el SHA indicado arriba. La vinculación idempotente
del propietario demo y la retirada de `/run-migrations` también están en ese
baseline. Las escrituras legacy siguen deshabilitadas. No se añade MFA.

### Lista exacta de archivos seleccionados

- `.dockerignore`
- `.env.example`
- `.github/workflows/deploy.yml`
- `Dockerfile`
- `app/Console/Commands/DemoAccessCommand.php`
- `app/Exceptions/RegistrationLockUnavailableException.php`
- `app/Http/Controllers/Api/V1/BillingController.php`
- `app/Http/Middleware/EnsureActiveSubscription.php`
- `app/Http/Resources/SubscriptionStatusResource.php`
- `app/Models/DemoAccessGrant.php`
- `app/Services/Auth/RegistrationService.php`
- `app/Services/Billing/CommercialAccess.php`
- `app/Support/RegistrationFailureReporter.php`
- `config/billing.php`
- `config/logging.php`
- `database/migrations/2026_10_08_000001_create_demo_access_grants_table.php`
- `docs/Acceso_demo_comercial.md`
- `docs/Diagnostico_flujo_canonico_2026-10-08.md`
- `resources/js/modules/billing/contracts.js`
- `resources/js/modules/billing/index.js`
- `resources/js/modules/billing/view.js`
- `resources/views/billing/index.blade.php`
- `tests/Support/verify-demo-access.php`
- `tests/Unit/Auth/RegistrationDiagnosticsTest.php`
- `tests/Unit/Billing/DemoAccessTest.php`
- `tests/frontend/billing-demo.test.mjs`
- `tests/frontend/canonical-browser-router.php`
- `tests/frontend/canonical-deployment.test.mjs`
- `tests/frontend/registration-browser-runtime.mjs`
- `tests/frontend/registration-result-browser.mjs`
- `tests/frontend/subscription-browser-db.php`

### Cambios conservados fuera del lote y motivo

- `README.md`: texto de Roberto sobre perfiles ROL-03, ajeno al registro,
  billing y despliegue. Se preserva sin incluirlo en staging.
- Los siguientes 30 ficheros son almacenamiento generado de Laravel, no fuentes.
  No se leyó ni publicó su contenido. Se conservan para no invalidar cachés de una
  aplicación local activa; no hace falta eliminarlos para preparar un índice limpio:

- `storage/framework/cache/data/25/e5/25e54f2109ab1fca2b12a8b312349301ba2c9f8f`
- `storage/framework/cache/data/27/29/272959f53eceabaee27246856428bed3ec37a0d6`
- `storage/framework/cache/data/2c/a5/2ca559cd4de70b564d99d4c0629214a6c361875c`
- `storage/framework/cache/data/34/87/34879ed278708d5f20e1ed64579c91988c108991`
- `storage/framework/cache/data/3e/0d/3e0d009d985a80a2b80fe69afc7a132ac4af9c20`
- `storage/framework/cache/data/41/3b/413b00f232852e97f72262112defb7c69c9031a4`
- `storage/framework/cache/data/4a/e6/4ae68a19eb1dd1341c1c925339c5cd346bf3a398`
- `storage/framework/cache/data/4c/3e/4c3ebf80626510796d77c48a3f4db0d3e9d00ac7`
- `storage/framework/cache/data/4f/57/4f57f2cdf53fb342fe18d36c27b2adea2be4475d`
- `storage/framework/cache/data/53/2e/532ed230934ffeb788df00642362c9a06a536d76`
- `storage/framework/cache/data/5a/45/5a45fba2d8bf2a921eeb3e743dfa234424248b1c`
- `storage/framework/cache/data/79/56/795652ba4c1e9939ba1547bf185db3f4eeda8b2f`
- `storage/framework/cache/data/79/6c/796c339688c75174bf0b39cd61ecb780c88d784d`
- `storage/framework/cache/data/79/b8/79b8f0cac941ad29585172623d5001ad893c28a9`
- `storage/framework/cache/data/7d/2f/7d2f34f34c2d5273cc309bbccb1000284c5b4430`
- `storage/framework/cache/data/91/3d/913d6697fa5cf68ce864177bf3e7b7df5eef43ed`
- `storage/framework/cache/data/9f/a4/9fa4c253193ab52dc87d5d0d7e1f0241bf4dc986`
- `storage/framework/cache/data/a1/94/a194fb6dee03159f805a670a0bbc4d7073a8ec59`
- `storage/framework/cache/data/a8/4e/a84e9ac88c488d81ddcb2a79c948006dfe803c17`
- `storage/framework/cache/data/bf/00/bf00afb3057bf50927e7b993d7729e5bd5549444`
- `storage/framework/cache/data/c0/71/c071422490d526a8aada046adf3a9c9fa0593468`
- `storage/framework/cache/data/c3/75/c37581067e6f61cf447ccbd9054833737e624844`
- `storage/framework/cache/data/cc/46/cc4604b8412be2818aa42970dbd848f878d078c3`
- `storage/framework/cache/data/d3/87/d387049ba5eadde9317ce4063ace426b8c0f2a23`
- `storage/framework/cache/data/e0/5e/e05edecfe2db39db9592af6b0619f90767c0b219`
- `storage/framework/cache/data/e4/ff/e4ffbd7ab270766c2478db57f2a3c0f31ebd517d`
- `storage/framework/cache/data/ec/c4/ecc49f49f4da6b940dcde13f0571e79c299871e6`
- `storage/framework/cache/data/ee/fe/eefef8cbb8edcc039c12129bbc4e6e7ec02b04e3`
- `storage/framework/cache/data/f0/12/f012c677f807f3e44deface02365f0005a16ed97`
- `storage/framework/cache/data/fe/94/fe940aafc1003b67cf70d8649b37dd154d7c96ed`

Otros artefactos excluidos aunque estén ignorados por Git: `.env`, secretos,
logs, dependencias, capturas, perfiles y herramientas bajo `storage/app/qa`.
Se cotejaron los valores sensibles locales únicamente en memoria contra las
31 fuentes seleccionadas: sin coincidencias; no se imprimieron sus valores.
Los campos sensibles de `.env.example` permanecen vacíos o placeholders.

### Tratamiento comprobado de assets

Git ignora `/public/build` y no tiene archivos compilados versionados. Docker
construye la etapa `frontend` con `npm ci` y `npm run build`, y copia su salida
al runtime. Por ello se excluyen los assets generados de staging sin omitirlos
de la imagen desplegable. `public/hot` local se conserva, pero `.dockerignore`
impide que entre en la imagen; el Dockerfile además verifica su ausencia.
No cambian paquetes, lockfiles, claves ni configuración local.

### Validaciones de esta conciliación

- Node **24.19.0**, PHP **8.3.30**, Vite **8.2.1**.
- `php vendor/phpunit/phpunit/phpunit --do-not-cache-result --testsuite Unit`:
  **56 pruebas aprobadas, 459 aserciones, salida 0**; Unit usa SQLite en memoria
  y sus pruebas con escrituras verifican ese destino antes de crear esquema.
- Suite frontend completa, glob expandido mediante `Get-ChildItem`:
  **133 aprobadas, 0 fallos, 0 canceladas, 0 omitidas, 0 TODO, salida 0**.
- Lint de app/bootstrap/config/routes y seis PHP adicionales del lote:
  **584 archivos, 0 errores, salida 0**. `node --check`:
  **7 archivos afectados, 0 errores, salida 0**.
- `node node_modules/vite/bin/vite.js build` (equivalente exacto al script
  `npm run build`, npm no disponible en PATH): **106 módulos, salida 0**.
  Único aviso: `PLUGIN_TIMINGS` por tiempo de Tailwind, no fallo de compilación.
- `php tests/frontend/render-registration.php`: registro, login y landing,
  IDs únicos y referencias ARIA válidas; **75 entradas de manifest válidas**.
- `php tests/frontend/render-billing.php`: **6 variantes de vistas** aprobadas,
  landmarks/IDs/ARIA válidos; ambos renders salida 0.
- `php artisan route:list --json`: **13 rutas pertinentes** de registro/billing,
  ninguna `/run-migrations`, salida 0. No se ejecutaron esas mutaciones.

No se ejecutó Docker porque el binario no está disponible: su construcción real
queda a cargo del pipeline. Las pruebas estáticas verifican el lint que detiene
una imagen con PHP inválido, el tag SHA y la etiqueta OCI. Publicar la imagen en
ACR **no actualiza automáticamente App Service**: sigue siendo necesario seleccionar
la versión/digest y comprobar el contenedor real con autorización del operador.

Este lote está preparado para revisión y commit; no declara corregido el 500 de
Azure ni acreditado un pago TEST. Siguen pendientes el evento diagnóstico real,
la variante TEST Inicial mensual y el webhook auténtico descritos anteriormente.
No se repitieron altas ni checkouts inciertos, no se escribieron bases existentes,
no se ejecutaron migraciones ni se realizaron cambios remotos.
