# Registro público canónico — frontend

Esta unidad confirma exclusivamente el alta de negocio y propietario. No incluye
suscripción, planes, checkout, pagos ni activación comercial. Backend conserva la
autoridad sobre operabilidad. Figma no tiene un diseño específico para este flujo.

## Recorrido y reemplazo del asistente

| Paso anterior | Recorrido actual |
| --- | --- |
| 1. Perfil | Cuenta propietaria: nombres, apellidos, correo, contraseña y confirmación |
| 2. Negocio | Nombre del negocio |
| 3. Tipo de negocio | Retirado: no pertenece al contrato de alta |
| 4. Región | Solo zona horaria, editable, dentro de Negocio; se retiran los otros campos |
| 5. Usuarios | Retirado: corresponde a administración autenticada posterior |
| 6. Plan/contratación | Retirado: entrega independiente |
| 7. Resultado legacy | Revisión sin contraseña y Resultado, solo tras HTTP 201 canónico |

Las primeras dos etapas solo editan datos en memoria. Revisión muestra únicamente
propietario, correo, nombre y zona horaria. Crear negocio ejecuta la única escritura
de dominio. Resultado muestra el slug y correo retornados, ofrece copiar el slug e
iniciar sesión manualmente. No afirma envío por correo, pago, contratación ni ERP
habilitado. Nunca ejecuta login automáticamente.

## Contrato efectivo

`POST /api/v1/auth/register`, JSON con exactamente:

```json
{
  "business": { "name": "Nombre del negocio", "timezone": "America/Managua" },
  "owner": {
    "first_name": "Nombres", "last_name": "Apellidos", "email": "propietario@example.test",
    "password": "<introducida por el usuario>", "password_confirmation": "<confirmación exacta>"
  }
}
```

Cabeceras: Accept y Content-Type application/json, Idempotency-Key UUID seguro y
X-XSRF-TOKEN proveniente del handshake real GET /sanctum/csrf-cookie. Todo pasa por
core/api-client.js y las cookies de sesión. No se envían _token, business_id, slug,
roles, perfiles, empleados, planes, suscripciones o pagos.

Éxito y replay: HTTP 201 y exactamente `{"data":{"business_slug":"...","owner_email":"..."}}`.
El cliente comprueba estado HTTP y estructura antes de mostrar éxito.

Fuentes verificadas: RegisterRequest, RegisterController, RegistrationResultResource,
RegistrationService, AppServiceProvider, contratos JSON MOD-01 y FRD RF-01-07.
Password::defaults() instalado exige min(12), letras, números, símbolos y
uncompromised(). El último control es exclusivamente Backend. La contraseña y su
confirmación no se recortan, transforman ni limitan silenciosamente.

El LoginRequest instalado y el frontend existente envían business_slug, email y
password. No existe un control remember en este formulario ni se añade uno por
inferencia a partir de documentación histórica. No se transportan credenciales por
query string. El usuario copia/conserva el slug y escribe su contraseña en login.

## Estado e idempotencia

- editing: se permite navegar/corregir; validación local útil y autoridad Backend.
- submitting: bloqueo síncrono antes de cualquier await; controles disabled y aria-busy.
- uncertain/recoverable: snapshot inmutable y clave originales en memoria; edición
  bloqueada; únicamente reintento explícito del mismo intento. Cancelar/cerrar/recargar
  no implica rollback y no existe recuperación garantizada después de recargar.
- 422: errores por rutas literales, resumen accesible, etapa/campo correspondiente;
  se libera el intento y la siguiente confirmación genera una clave nueva.
- 403: sesión humana existente; enlace al dashboard; no logout automático.
- 409 REGISTRATION_IDEMPOTENCY_CONFLICT: bloqueo definitivo sin rotación de clave;
  secretos liberados. 409 BUSINESS_SLUG_CONFLICT: replay explícito del mismo intento.
- 419: una renovación CSRF y como máximo un replay automático con igual clave/body.
- 429: respeta Retry-After (segundos o fecha); sin bucles de solicitud.
- Fallo del primer handshake, antes de cualquier POST: vuelve a editing y explica
  que no se envió el registro. Un fallo de handshake después de un POST incierto
  nunca libera su snapshot ni genera otra clave.
- 404/405 de registro: diagnóstico de ruta/publicación, conservando el intento.
- 500/503/red/timeout/201 ilegible/HTTP inesperado después del POST: mensaje sanitizado e incertidumbre;
  nunca se supone éxito ni se crea otra clave silenciosamente.
- success: no segunda creación; se liberan snapshot, contraseña y confirmación y se
  muestran solo el resultado público. No hay persistencia de secretos ni telemetría.

## Rutas y artefactos legacy

- GET /register: auth.register, sin escrituras.
- GET /register/step/1..7: redirección 302 a /register, sin conservar query strings.
- GET /register/step/1..7/store: 410, sin invocar RegisterWizardController.
- Métodos de escritura legacy: no registrados; POST comprobado devuelve 405.
- Único alta: POST /api/v1/auth/register. Login/logout canónicos permanecen separados.

RegisterWizardController, RegisterWizardRequest, RegisterWizard, su migración histórica,
tabla y alias de morphMap permanecen intactos pero sin rutas web/API activas. Las siete
vistas legacy sin consumidores se eliminaron; el entry wizard.js y su import CSS se
reutilizaron para no introducir otro pipeline. No se eliminan tablas ni datos históricos.

## Validación e integración

```sh
node --check resources/js/modules/registration/wizard.js
node --check resources/js/modules/registration/attempt.js
node --check resources/js/modules/registration/contract.js
node --check resources/js/modules/security/auth.js
node --check resources/js/core/api-client.js
node --test tests/frontend/*.test.mjs
npm run build
php artisan view:cache
php artisan route:cache
php artisan route:list --path=register
php tests/frontend/render-registration.php
```

Los tests JS usan Fetch/transportes simulados y lo declaran. La herramienta aislada
storage/app/qa/registration-acceptance.php ejecuta HTTP real, sin desactivar CSRF,
solo en gintly_frontend_qa_rol03, local/testing y host 127.0.0.1. No integrar esa
herramienta, capturas, cachés, dependencias ni public/build. La QA conservó un negocio
identificable, un propietario y una fila idempotente; no se borraron documentos.
La migración progresiva de registration_requests se aplicó únicamente a esa QA.

La aceptación HTTP real comprobó 201 inicial/replay, 419 sin token, 422, 409 de
idempotencia, ausencia de auto-login (/me 401), login 200, /me 200 con ROL-01/negocio,
403 con sesión activa, logout 204 y legacy 410/302/405 sin escrituras.

## Microcierre de navegador — 6 de octubre de 2026

La ausencia del controlador integrado se resolvió usando las herramientas ya
instaladas: Playwright 1.62.1 externo al proyecto y Google Chrome 154.0.8037.93,
con ventana real y perfil QA temporal. No se instalaron paquetes ni navegadores,
no se modificó el pipeline y no se usó un servicio externo. URL de la aceptación:
http://127.0.0.1:8840, exclusivamente contra gintly_frontend_qa_rol03 en MySQL local.
El preflight comprobó APP_ENV local, host 127.0.0.1, nombre efectivo de la base y
ausencia de DB_URL alternativo; no imprimió credenciales. No se aplicaron migraciones
en este microcierre.

### Evidencia real e inducida

La ejecución completa `QA-REGISTER-BROWSER-9737c53a3ad6` aprobó 71 comprobaciones
(salida 0), con 29 capturas. La regresión posterior al ajuste visual del enlace,
`QA-REGISTER-BROWSER-d9a14c0d4832`, aprobó otras 37 comprobaciones (salida 0) y dejó
2 capturas de error definitivas, sin nuevas escrituras. El recorrido real entró desde la landing, navegó
Cuenta → Negocio (zona horaria incluida) → Revisión → Resultado y obtuvo 201.
Durante la carga se bloquearon campos, botón y envíos repetidos; solo hubo un POST
inicial. Se comprobaron en memoria allowlist exacta, UUID por header, JSON, X-XSRF-TOKEN
y contraseña con espacios iniciales/finales intactos, sin registrar sus valores.

El resultado mostró exclusivamente slug/correo del servidor, eliminó los valores
de contraseña y confirmación y enfocó su título. Copiar funcionó en Chrome; la
indisponibilidad inducida de Clipboard API mostró la alternativa de selección y
copia manual del texto público. Recargar no emitió otra creación ni recuperó secretos.
/me devolvió 401 antes del login explícito. El login desde su formulario devolvió
200 con esa contraseña exacta y /me devolvió ROL-01, negocio correcto y capacidades
efectivas. Registrar con sesión humana activa devolvió 403, conservó la sesión y
ofreció el panel. Logout desde el menú de cuenta devolvió 204. El contrato actual
de autenticación conserva solamente slug, email y password; no se añadió remember.

En una segunda alta real del mismo recorrido, la interceptación dejó que el
Backend procesara el POST y devolviera 201, pero descartó su respuesta al navegador.
La UI quedó incierta, bloqueó la edición y recuperó el resultado con un reintento
explícito: mismo UUID, mismo cuerpo y mismo resultado 201. La consulta de BD confirmó
un negocio, un propietario y una fila idempotente por alta, sin duplicación.
Este caso es pérdida de una respuesta real, no un 201 fabricado.

Los estados 422, ambos códigos 409, 419 repetido, 429 con Retry-After=2, 500,
fallo de red y JSON 201 ilegible se provocaron mediante interceptación controlada.
Se identifican como `induced-*` en la evidencia y no acreditan fallos reales del
Backend. La corrección tras 422 preservó valores, enfocó owner.email y generó una
clave nueva; los reintentos inciertos mantuvieron cuerpo/UUID y bloquearon edición.
419 produjo exactamente dos POST y una renovación adicional de CSRF, sin bucle.
429 bloqueó el reintento durante el plazo y no envió solicitudes automáticamente.
Ninguna respuesta fallida o ilegible mostró éxito. La recuperación de 419 seguida
de éxito y otros límites también conserva sus pruebas unitarias de transporte.

Las 7 escrituras legacy GET volvieron a responder 410; los 7 enlaces antiguos, 302;
el POST legacy, 405. La tabla register_wizards conservó 0 filas. No se tocaron las
clases, tablas o migraciones históricas.

### Presentación, foco y corrección indispensable

Se recorrieron 375, 768, 1024, 1280 y 1512 px en cuenta, negocio, revisión y resultado,
con capturas inspeccionadas visualmente, sin desbordamiento horizontal ni controles
superpuestos. Tab, Shift+Tab y Enter comprobaron orden natural y foco visible; los
cambios de etapa enfocaron su encabezado y los errores, el campo pertinente. Se
comprobaron IDs únicos y relaciones ARIA. Esto no constituye una certificación WCAG
completa ni una prueba de todas las tecnologías de asistencia.

El zoom fue **real de Chrome al 200%**, mediante Settings → Appearance → Page zoom;
el valor seleccionado fue 2 y Page.getLayoutMetrics confirmó cssVisualViewport.zoom=2.
No se sustituyó por CSS zoom, devicePixelRatio ni un viewport reducido. Se capturaron
las cuatro etapas ampliadas. La herramienta de captura se ajustó a DIP de Chrome
para evitar que el clip de una página ampliada cortara la imagen en dos; ese recorte
era del arnés QA, no del formulario.

La inspección visual detectó un defecto real: el enlace «Ir a mi panel» se mostraba
en errores anónimos 422, porque la combinación de clases hidden e inline-flex no
garantizaba ocultarlo. Se sustituyó por el atributo HTML hidden y su propiedad DOM.
La prueba de regresión exige que el enlace permanezca invisible en errores anónimos
y aparezca solamente ante el rechazo 403 por sesión autenticada. Se volvieron a
probar esos estados con el bundle corregido, incluyendo mensajes largos en móvil.

No se encontraron errores JavaScript, warnings ni 404 inesperados. Chrome sí
registró avisos de recurso al recibir los 401/403 esperados y los errores inducidos;
no se ocultan ni se confunden con defectos de consola del recorrido exitoso. No se
guardaron HAR, trazas, cuerpos de request, contraseñas ni cookies en la evidencia.
El perfil de cada ejecución se elimina después de cerrar Chrome.

### Comandos y reproducción acotada — verificación de entrega

La aceptación funcional anterior se conserva; este cierre verifica únicamente que
su ejecutable sea reproducible después de integrar el manifiesto. No cambia vistas,
contratos, transporte de la aplicación ni dependencias de producción.

La prueba reutilizable tiene ahora **todas sus fuentes permanentes en tests/frontend**:

- registration-browser.mjs: interacción y evidencia del navegador.
- registration-browser-runtime.mjs: preflight, dependencias y servidor PHP propio.
- registration-browser-db.php: guardas CLI de configuración/conexión y evidencia
  read-only; trasladado desde storage/app/qa. No tiene ruta HTTP.
- registration-browser-guards.test.mjs: regresión de estas guardas.

No depende de storage/app/qa/registration-browser-db.php ni de registration-zoom.mjs.
Tampoco lee un Preferences preexistente: genera su propio browser-profile/Default/Preferences
dentro del directorio exclusivo de la ejecución y lo elimina al cerrar el navegador,
incluso si falla su lanzamiento. No utiliza perfiles personales.

#### Requisitos fuera del bundle

- Proyecto Laravel existente y su Backend canónico integrado; vendor preparado con
  composer install a partir del lockfile existente, PHP 8.3+ y pdo_mysql. DOM también
  es necesario para render-registration.php. No ejecutar composer setup, que incluye
  migraciones, ni instalar otra aplicación.
- Node **22.12+** y npm. Se verificó Node 24.19.0 y PHP 8.3.30.
- Google Chrome instalado, con sesión gráfica para la aceptación visual completa.
  Versión comprobada: **154.0.8037.93**. QA_CHROME es una ruta absoluta configurable;
  no hay fallback al equipo de Codex ni se entrega un binario.
- Playwright **1.62.1**, instalado fuera de la aplicación. Su index.mjs se proporciona
  mediante QA_PLAYWRIGHT_MODULE. La instalación externa contiene también playwright-core.
  No se cambia package.json/package-lock.json de Gintly.
- MySQL local 127.0.0.1, base ya autorizada gintly_frontend_qa_rol03, credenciales locales
  disponibles de forma segura en el entorno/.env existente, APP_KEY válido, esquema
  progresivo del proyecto (incluida registration_requests) y roles/permisos sembrados.
  El ejecutable no crea la base, no migra, no siembra ni limpia datos.
- Puerto **8840 libre**. No lanzar artisan serve aparte ni reutilizar un servidor
  desconocido. public/hot debe estar ausente; regenerar el build de producción.
- Backend conserva Password::uncompromised(): la prueba completa requiere que su
  verificador instalado pueda funcionar; no se desactiva para aprobar QA.

Preparación desde la raíz del proyecto ya existente, PowerShell (no requiere una
ruta C:/Users/... particular):

```powershell
node --version
# Si PHP no está en PATH, establecer antes QA_PHP con su ruta absoluta instalada.
$registrationQaPhp = if ($env:QA_PHP) { $env:QA_PHP } else { (Get-Command php -ErrorAction Stop).Source }
& $registrationQaPhp --version
# Solo si faltan las dependencias del proyecto existente:
composer install
npm ci
npm run build

# O reutilizar un Playwright 1.62.1 externo ya preparado.
# Esta instalación, si hace falta, queda en TEMP, fuera del bundle/repositorio.
$registrationQaTools = Join-Path $env:TEMP 'gintly-registration-qa-tools'
npm install --prefix $registrationQaTools --no-save --package-lock=false playwright@1.62.1

$env:QA_PLAYWRIGHT_MODULE = Join-Path $registrationQaTools 'node_modules/playwright/index.mjs'
$env:QA_CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe'
$env:QA_PHP = $registrationQaPhp

$env:QA_REGISTRATION_BROWSER = '1'
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
# DB_PORT/DB_USERNAME/DB_PASSWORD proceden de las credenciales locales QA
# ya autorizadas. No copiarlas al informe ni introducirlas en scripts.
$env:QA_HEADLESS = '0'
$env:QA_ERRORS_ONLY = '0'
$env:QA_PREFLIGHT_ONLY = '1'
node tests/frontend/registration-browser.mjs
if ($LASTEXITCODE -ne 0) { throw 'Preflight QA rechazado; no continuar' }
```

El preflight comprueba primero el opt-in, URL exacta, host/driver/nombre allowlisted y
ausencia de DB_URL/socket alternativos; después arranca el helper CLI con el mismo
PHP y entorno que recibirá el servidor. El helper inspecciona la **configuración
resuelta de Laravel**, incluida cualquier caché, rechaza conexiones read/write
alternativas y comprueba **SELECT DATABASE() en PDO**. Valida además sesión/cache
locales, Sanctum, esquema y rol propietario. Solo emite nombres/IDs QA y conteos,
nunca credenciales. Los errores identifican la fase sin imprimir SQL o DSN.

Las comprobaciones de assets/manifest, imports, binarios y puerto también preceden
la creación del perfil y cualquier escritura de aceptación. Si una configuración
cacheada apunta a otra base, el preflight falla: no la borra ni la reemplaza.
El responsable debe preparar una configuración local QA válida y repetir solo el
preflight; no modificar el Backend para eludir la guarda.

Para ejecutar **solamente este microcierre**, conservar las variables anteriores:

```powershell
$env:QA_PREFLIGHT_ONLY = '0'
$env:QA_ERRORS_ONLY = '1'
node tests/frontend/registration-browser.mjs
if ($LASTEXITCODE -ne 0) { throw 'Aceptación acotada incompleta' }

node --check tests/frontend/registration-browser.mjs
node --check tests/frontend/registration-browser-runtime.mjs
node --check tests/frontend/registration-browser-guards.test.mjs
& $registrationQaPhp -l tests/frontend/registration-browser-db.php
node --test tests/frontend/registration.test.mjs tests/frontend/api-client-registration.test.mjs tests/frontend/registration-browser-guards.test.mjs
& $registrationQaPhp tests/frontend/render-registration.php
```

QA_ERRORS_ONLY=1 usa navegador/backend reales para cargar el formulario y verificar
legacy, pero intercepta las respuestas de registro; **no crea negocios**. Se comprueba
esto por lectura de BD al finalizar. No sustituye al recorrido funcional real ya
acreditado; no presenta un 201 inducido como un alta real.

Para repetir la **aceptación completa**, cuando sea necesario y autorizado:

```powershell
$env:QA_PREFLIGHT_ONLY = '0'
$env:QA_ERRORS_ONLY = '0'
node tests/frontend/registration-browser.mjs
if ($LASTEXITCODE -ne 0) { throw 'Aceptación completa incompleta' }
```

Ese modo crea dos fixtures identificables: alta inicial y alta con respuesta real
perdida/replay. Verifica en BD un negocio, propietario y fila idempotente por cada
uno. No necesita recuperar contraseñas antiguas ni reutilizar cuentas personales.

El runner inicia directamente **un proceso PHP -S** en public, con el router
existente de Laravel en vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php.
Mismo binario, raíz y entorno verificados por CLI; exige su propio anuncio de bind
en 127.0.0.1:8840 y aborta si el puerto estaba ocupado o el proceso muere. No consulta
un endpoint de configuración ni confía únicamente en que la URL sea localhost.
No deja un subproceso artisan independiente. El bloque finally cierra Chrome,
detiene ese PHP y elimina el perfil; mantiene capturas/evidence.json como artefactos
excluidos. Un cierre forzado del sistema no garantiza finalmente: antes de repetir,
comprobar y detener únicamente el proceso/perfil QA propio, nunca un perfil personal.

#### Resultado de esta verificación de reproducibilidad

- Preflight real completo: **salida 0**, antes de crear artefactos o iniciar servidor.
- Pruebas de guardas: **14/14**, 0 fallos, 0 omitidas, salida 0. Incluyen ausencia de
  opt-in, producción, BD ordinaria, host remoto, URL distinta, driver distinto,
  DB_URL/socket, rutas de herramienta inválidas, Playwright dentro de la aplicación
  y puerto ocupado. Dos rechazos CLI reales adicionales (debug efectivo activo y
  nombre de base no autorizado) devolvieron **1 esperado**, antes de escribir.
- Pruebas pertinentes de registro + transporte + guardas: **33/33**, 0 fallos,
  0 omitidas, salida 0. No se repitieron suites de otros módulos.
- Sintaxis de los tres módulos QA y lint PHP del helper: salida 0.
- Navegador real acotado: **39 comprobaciones**, salida 0, ejecución
  QA-REGISTER-BROWSER-aed3f0bb5f83. Incluye 37 casos existentes y 2 comprobaciones
  nuevas de evidencia BD: legacy intacto y ningún fixture nuevo.
- Render de registro/login/landing: salida 0; IDs únicos y ARIA válido.
  Manifest generado existente: **73 entradas válidas**.
- Relectura read-only: QA-REGISTER-6defd52506e9 y los 9 fixtures de navegador anteriores
  siguen con 1 propietario y 1 fila idempotente cada uno; legacy conserva 0 filas.
- No se recompiló Vite ni se cambió la aplicación en este cierre: los cambios son
  exclusivamente fuentes de prueba/documentación fuera del bundle. Se conservan
  los resultados anteriores (84 pruebas y build 96 módulos, todos salida 0); no se
  presentan como nuevas ejecuciones.

La prueba es reproducible con las fuentes integrables y los requisitos anteriores.
No queda ningún helper fuente obligatorio bajo storage/app/qa ni dependencia de un
perfil particular de Codex. La aceptación visual completa previa se conserva; aquí
no se reejecutaron los cinco anchos ni el zoom porque el formulario no cambió.

### Fixtures y residuos identificados

Se conserva QA-REGISTER-6defd52506e9, negocio 5, de la aceptación HTTP previa.
El microcierre conservó intencionalmente estas altas QA identificables, incluidas
las generadas al corregir el arnés; cada una tiene 1 propietario y 1 fila idempotente:

| Negocio | Fixture |
| --- | --- |
| 6 | QA-REGISTER-BROWSER-4539c52ebc90 |
| 7 | QA-REGISTER-BROWSER-fb797993c70f |
| 8 | QA-REGISTER-BROWSER-fb797993c70f-lost |
| 9 | QA-REGISTER-BROWSER-7fdce36e84a5 |
| 10 | QA-REGISTER-BROWSER-7fdce36e84a5-lost |
| 11 | QA-REGISTER-BROWSER-5a7a4dc28be8 |
| 12 | QA-REGISTER-BROWSER-5a7a4dc28be8-lost |
| 13 | QA-REGISTER-BROWSER-9737c53a3ad6 |
| 14 | QA-REGISTER-BROWSER-9737c53a3ad6-lost |

No se afirma «cero residuos»: se conservan estas filas, capturas/evidencia de las
ejecuciones y sesiones/cache QA generadas por Laravel. No se eliminaron documentos
ni se hizo limpieza general. Las contraseñas generadas solo vivieron durante las
pruebas; no se conservan para reutilizar estas cuentas. Las capturas definitivas
del recorrido están en storage/app/qa/registration-browser/QA-REGISTER-BROWSER-9737c53a3ad6;
las capturas de errores tras la corrección están en
storage/app/qa/registration-browser/QA-REGISTER-BROWSER-d9a14c0d4832.
Todo storage/app/qa queda fuera de integración.

En la verificación de reproducibilidad no se crearon negocios/propietarios ni
idempotencias nuevas. Se conserva únicamente la evidencia generada bajo
storage/app/qa/registration-browser/QA-REGISTER-BROWSER-aed3f0bb5f83. Se comprobó el
filesystem actual: registration-zoom.mjs, registration-zoom-profile y su antiguo
Default/Preferences están ausentes; tampoco quedan browser-profile de las
ejecuciones conservadas. El archivo Preferences mostrado en el historial de
ediciones fue temporal, no una fuente entregada. La copia temporal
storage/app/qa/registration-browser-db.php se retiró tras trasladar su función a
tests/frontend/registration-browser-db.php. No se limpiaron datos ni otras herramientas.

El servidor temporal quedó detenido al terminar y no se entrega public/hot. No hay
un defecto Backend canónico demostrado ni quedan pendientes de suscripción dentro
de esta aceptación: suscripciones/contratación siguen explícitamente fuera de alcance.

**Veredicto: APTO PARA INTEGRACIÓN FRONTEND DEL REGISTRO.** Se limita al registro,
su aceptación en navegador y la regresión de autenticación comprobada; no incluye
suscripciones, planes, pagos ni contratación.

## Manifiesto mínimo

Fuentes creadas (15):

- resources/js/modules/registration/contract.js
- resources/js/modules/registration/attempt.js
- resources/views/layouts/registration.blade.php
- resources/views/components/registration-field.blade.php
- resources/views/auth/register.blade.php
- tests/frontend/registration.test.mjs
- tests/frontend/api-client-registration.test.mjs
- tests/frontend/render-registration.php
- tests/frontend/registration-browser.mjs (prueba reutilizable opt-in; fuera del bundle)
- tests/frontend/registration-browser-runtime.mjs (nuevo en verificación de entrega; servidor/guardas)
- tests/frontend/registration-browser-db.php (nuevo en tests; helper CLI permanente que debe integrarse)
- tests/frontend/registration-browser-guards.test.mjs (nuevo en verificación de entrega; regresión)
- tests/frontend/registration-password-browser.mjs (nuevo en microcierre de contraseña; aceptación focalizada)
- tests/frontend/registration-result-browser.mjs (nuevo en microcierre de resultado; aceptación focalizada)
- docs/Frontend_registro_canonico.md

Fuentes modificadas o compartidas (6), reemplazar por su versión final:

- routes/web.php (compartido con panel; preservar otras rutas al integrar)
- resources/js/core/api-client.js (compartido: incorpora Retry-After en ApiError)
- resources/js/modules/security/auth.js (compartido: init idempotente, 419 acotado y HTTP 200)
- resources/views/auth/login.blade.php (compartido: label de contraseña asociado)
- resources/js/modules/registration/wizard.js (reemplazo del legado por coordinador canónico)
- resources/css/register-wizard.css (estilos aislados del registro, elimina reglas legacy)

Siete vistas legacy eliminadas; retirar tras actualizar rutas/entry:

- resources/views/auth/register-step1.blade.php
- resources/views/auth/register-step2.blade.php
- resources/views/auth/register-step3.blade.php
- resources/views/auth/register-step4.blade.php
- resources/views/auth/register-step5.blade.php
- resources/views/auth/register-step6.blade.php
- resources/views/auth/register-step7.blade.php

En el microcierre de aceptación visual se creó registration-browser.mjs y se
actualizaron wizard.js, auth/register.blade.php, registration.test.mjs y este documento.
En la verificación de entrega posterior solo cambian registration-browser.mjs y
este documento, y se añaden los tres helpers/pruebas indicados. **No cambia código
de aplicación**. Integrar la versión final de esos archivos; el resto acumulado no
requiere copiarse de nuevo si Pablo ya lo integró. No copiar la antigua herramienta
de storage/app/qa: el helper definitivo de tests/frontend sí es fuente integrable.

Artefactos/dependencias excluidos: todo storage/app/qa (incluida la herramienta HTTP
histórica registration-acceptance.php), capturas, evidence.json, perfiles y Preferences
generados, public/build, public/hot, vistas compiladas, cachés, bases QA, vendor,
node_modules, instalación externa de Playwright, binarios Chrome/PHP/Node y credenciales.
La retirada de la copia temporal del helper no es una eliminación de aplicación
que Pablo deba replicar; las únicas bajas integrables son las siete vistas legacy.

package.json, package-lock.json, vite.config.js, app.css, landing y Backend no se
modificaron. No hay nuevas dependencias de producción. Regenerar public/build mediante npm run build
después de integración manual. No hacer staging general, commit, push ni copias
automáticas. Esta declaración no incluye suscripciones ni pagos.

## Microcierre de contraseña y contraste con Azure

### Causa comprobada antes de editar

El Backend integrado usa `RegisterRequest` con `Password::defaults()`. Se inspeccionó también la regla Laravel instalada y se obtuvo su configuración efectiva tras bootstrap: mínimo **12 caracteres**, al menos una **letra**, un **número** y un **símbolo/separador**, `uncompromised=true` con umbral 0; `mixedCase=false`, `max=null`. No se exigen simultáneamente mayúscula/minúscula ni se establece un máximo artificial. Unicode y espacios se preservan; el control de filtraciones es exclusivamente Backend.

Azure se consultó **únicamente mediante GET**, sin enviar formularios ni llamar escrituras legacy:

| Recurso público | Evidencia observada |
| --- | --- |
| `https://gintly-app-web.azurewebsites.net/` | 200; título antiguo `Gintly - Sistema de Facturación`. |
| `https://gintly-app-web.azurewebsites.net/register` | 302 hacia `http://gintly-app-web.azurewebsites.net/register/step/1`. Se inspeccionó ese destino usando HTTPS, sin seguir la bajada a HTTP. |
| `https://gintly-app-web.azurewebsites.net/register/step/1` | 200; título `Gintly App - Creación de Perfil`; Perfil/Región/Usuarios, campos `nombre`, `apellido`, `correo`, `codigo_pais`, `telefono`, `password`. No tiene los hooks de las cuatro etapas canónicas. |
| Asset publicado | `wizard-zL-isU6L.js`, GET 200; SHA-256 `33F1BD64E4F78528E6BC67946B600FED77D2B72BEC68A49E6EB21A0D22DCBDED`. No consume `/auth/register`. |

Su validación legacy utiliza `^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d@$!%*?&]{12,}$`, pero anuncia solo «Mínimo 12 caracteres, letras y números». Esa whitelist rechaza `#`, `+`, guiones, espacios y letras acentuadas aun cumpliendo longitud, letras y números. «Las contraseñas coinciden» comprueba igualdad, no cumplimiento de esa regex. No se dedujo la contraseña de la captura ni se afirma qué carácter concreto escribió el usuario.

La versión local es distinta: GET `/register` sirve `Registrar negocio · Gintly`, Cuenta → Negocio → Revisión → Resultado, sin teléfono. El build final de este microcierre tiene `assets/wizard-m3a8Shij.js`; el nombre es una huella de este build, **no debe hardcodearse**.

Antes de modificar aplicación, Chrome comprobó el baseline con token `QA-REGISTER-PASSWORD-b752408d488c`: siete comprobaciones, salida 0, sin alta. Una contraseña válida sí avanzaba pese a los campos de Negocio todavía vacíos. Por tanto, no se reprodujo el bloqueo de Continuar de Azure en el canónico. Sí se reprodujo un defecto local menor: un error de política ya corregido seguía visible hasta otro submit (`correctedErrorBeforeSubmit=true`).

### Ajuste aplicado y alcance

Se modifica **solo `resources/js/modules/registration/wizard.js` como fuente de aplicación**. Los eventos `input` y `change` actualizan los errores de los campos editados y recalculan la confirmación al cambiar cualquiera de las dos contraseñas. Se reconstruyen resumen/ARIA sin robar foco. Los rechazos exclusivos del servidor no desaparecen por editar un campo ajeno. Continuar sigue validando únicamente la etapa actual; no se introduce bloqueo por etapas ocultas.

No se cambia política, contrato, normalización de contraseñas, atributos de password, cliente HTTP, autenticación, rutas, vistas, Backend o suscripciones. La ayuda existente ya enumera letras, números, símbolos y comprobación Backend de filtraciones. No se añaden `trim`, whitelist ASCII, maxlength, setCustomValidity, nuevas dependencias o persistencia de credenciales.

### Aceptación focalizada reproducible

La herramienta nueva reutiliza `registration-browser-runtime.mjs` y `registration-browser-db.php`, entregados bajo `tests/frontend`; no depende de helpers en `storage/app/qa`. Verifica ENV, Laravel y PDO antes de cualquier escritura, servidor propio, URL `http://127.0.0.1:8840` y exclusivamente base `gintly_frontend_qa_rol03`. Requisitos externos: Node 22.12+, PHP/Composer del proyecto, MySQL local, Playwright externo 1.62.1 y Chrome existente. No instalar QA dentro del bundle ni usar perfiles personales.

Desde la raíz de frontend-audit, PowerShell (las credenciales de BD permanecen en la configuración QA existente, nunca en este documento):

```powershell
$env:QA_REGISTRATION_BROWSER = '1'
$env:APP_ENV = 'local'
$env:APP_URL = 'http://127.0.0.1:8840'
$env:DB_CONNECTION = 'mysql'
$env:DB_HOST = '127.0.0.1'
$env:DB_DATABASE = 'gintly_frontend_qa_rol03'
$env:DB_URL = ''
$env:DB_SOCKET = ''
$env:QA_PLAYWRIGHT_MODULE = '<ruta-externa>/node_modules/playwright/index.mjs'
$env:QA_CHROME = '<ruta-absoluta-al-chrome>/chrome.exe'
$env:QA_PASSWORD_REPRO_ONLY = '0'
node --check resources/js/modules/registration/wizard.js
node --check tests/frontend/registration.test.mjs
node --check tests/frontend/registration-password-browser.mjs
node --test tests/frontend/registration.test.mjs tests/frontend/api-client-registration.test.mjs
node --test tests/frontend/*.test.mjs
npm run build
php tests/frontend/render-registration.php
node tests/frontend/registration-password-browser.mjs
```

En Codex, el equivalente disponible de build fue `node C:/laragon/bin/nodejs/node-v22/node_modules/npm/bin/npm-cli.js run build`; en el destino se usa su npm. El runtime fija sesión/caché file, debug false y dominio Sanctum QA para su proceso, sin editar `.env`. `QA_PASSWORD_REPRO_ONLY=1` ejecuta solo diagnóstico sin alta; el modo normal conserva intencionalmente un negocio QA identificable por ejecución y no altera fixtures anteriores.

Run final **`QA-REGISTER-PASSWORD-27de93d24253`**, Chrome **154.0.8037.93**: **31 comprobaciones, salida 0**. Verificados contraseña larga coincidente pero sin complejidad, contraseña válida con caracteres fuera de la whitelist legacy, confirmación distinta, corrección inmediata, cambios en ambos campos, avance hasta Revisión sin exigir campos ocultos, pegado real desde clipboard (limpiado inmediatamente), espacios extremos intactos, request canónico exacto con UUID/cookies/CSRF, borrado de passwords después del 201 y login manual con la contraseña exacta.

Autofill: comprobados los atributos `autocomplete="new-password"` y valores introducidos con evento **change sin input**, emulando el comportamiento de autofill. Esa parte es **inducida**, no una prueba de un gestor de contraseñas con credenciales almacenadas. No se guardaron contraseñas en Chrome. Se indujo un 422 para comprobar asociación de `owner.password` y `owner.password_confirmation`, conservación de valores/foco y corrección; no se simuló el alta principal ni el login.

HTTP reales: handshake **204**, registro **201** (un único POST real), `/me` previo **401**, login explícito **200**, `/me` autenticado **200**, logout **204**. BD: negocio **34**, un propietario y una fila idempotente. No hay 404 ni excepciones JavaScript. La consola registró cuatro diagnósticos HTTP de recursos esperados: 422 inducido, `/me` 401 antes de login y dos lecturas 401 al terminar la sesión; no se presentan como consola cero ni como defectos del registro.

Validaciones finales: sintaxis de tres JS **0**; pruebas focalizadas **20 aprobadas, 0 fallos/omitidas**, salida **0**; suite completa **121 aprobadas, 0 fallos/omitidas/canceladas/todo**, salida **0**; Vite **106 módulos**, salida **0**, únicamente aviso no bloqueante `PLUGIN_TIMINGS`; render registro/login/landing **0**, IDs/ARIA válidos y **75 entradas** del manifest verificadas.

Se inspeccionaron capturas de Cuenta 1280 px y Revisión/Resultado 375 px, sin overflow horizontal. Se conservan en `storage/app/qa/registration-password/QA-REGISTER-PASSWORD-27de93d24253`, junto con evidencia sanitizada; excluidas de integración. Perfil temporal eliminado, servidor 8840 detenido y `public/hot` ausente. Se conserva intencionalmente el negocio QA 34; el diagnóstico previo no creó datos. Los fixtures anteriores siguen intactos.

### Integración mínima de este microcierre

- Modificado: `resources/js/modules/registration/wizard.js` — sustituir coordinador final.
- Modificado: `tests/frontend/registration.test.mjs` — regresión de complejidad/Unicode/espacios.
- Creado: `tests/frontend/registration-password-browser.mjs` — aceptación focalizada reproducible.
- Modificado: `docs/Frontend_registro_canonico.md` — evidencia y procedimiento.
- Eliminados en este microcierre: **ninguno**.

Si Azure todavía está sobre el legado comprobado, **copiar solo wizard.js no es suficiente**: Pablo debe publicar mediante su procedimiento el conjunto canónico completo del manifiesto anterior (vistas/layout/componente, entry, contrato/intento, cliente/auth compartidos conciliados y rutas que desconectan legacy), sin deshacer los cambios aprobados posteriores. Conservar las dependencias actuales de los archivos compartidos; no desplegar una mezcla de versiones. No se autoriza ni se hace publicación desde Codex.

En el procedimiento de despliegue aprobado, generar `npm run build`, publicar el manifest y todos los assets que este referencia como una sola versión junto a las fuentes Blade/JS, y reconstruir cachés de vistas/rutas con `php artisan view:cache` y `php artisan route:cache`. Si el procedimiento conserva cachés previas, invalidarlas de manera controlada antes de reconstruirlas. Reabrir en sesión privada y comprobar `/register` 200 con cuatro etapas, que los antiguos enlaces redirigen al canónico y que `/register/step/{step}/store` permanece 410; verificar assets 200, navegación y QA de registro en el entorno autorizado. La redirección HTTP observada también debe revisarse en la configuración HTTPS/proxy del despliegue; no se alteró esa configuración aquí.

**Conclusión:** frontend local corregido y verificado; **Azure sigue mostrando una versión anterior**. No se declara corregido ni actualizado Azure, ya que no hubo publicación. No se tocó Backend-Claude, gintly_app, producción, suscripciones ni MFA; no hubo staging, commit, push, integración o despliegue.

## Microcierre del resultado de registro — 2026-10-07

### Causa comprobada y diferencia con la observación anterior

La conclusión anterior describe el despliegue observado durante el microcierre de
contraseña. **No describe el Azure actual**. En esta nueva revisión, GET HTTPS
`https://gintly-app-web.azurewebsites.net/register` respondió **200 text/html** y
mostró Cuenta → Negocio → Revisión → Resultado. Ya no redirigió al asistente antiguo.

Sin embargo, ese HTML incluyó:

- `api-base-url = http://gintly-app-web.azurewebsites.net/api/v1`.
- Acción del formulario `http://gintly-app-web.azurewebsites.net/api/v1/auth/register`.
- Login `http://gintly-app-web.azurewebsites.net/login`.

Chrome **154.0.8037.93** reprodujo en esa página el bloqueo **Mixed Content** y
`TypeError: Failed to fetch` mediante un **GET de diagnóstico**, sin cuerpo ni
credenciales, a la URL de registro construida por el cliente. No hubo respuesta
HTTP ni Content-Type para la petición bloqueada. Todos los métodos distintos de
GET/HEAD estaban bloqueados en el contexto de inspección pública. **No se envió
un POST a Azure** y no se consultó su base de producción.

El recorrido causal es: HTML HTTPS → meta API HTTP → api-client construye URL HTTP
→ navegador bloquea contenido mixto → ApiError de transporte con status 0 →
attempt.js conserva el intento en uncertain → mensaje reportado. El fallo de
configuración de esquema sí está demostrado; no se puede atribuir un registro
concreto de producción a ese fallo ni confirmar su persistencia sin su Network y
una consulta de lectura autorizada en ese entorno.

No se encontró doble extracción de `data`: api-client retorna el JSON completo y
registrationResult extrae `data` una sola vez. El cliente publicado coincidió por
SHA-256 con el local: `B7F780149469EF8244F2CC127A5360E43DD1C07A4ED7EBC6D4C144484F17B16E`.
El entry publicado antes del parche, `wizard-m3a8Shij.js`, también coincidió con
el baseline local: `CF95B319F8DF299E0CABBEF7F26A50C6B3FAD2038FAAA30CC7355DC52010C183`.
El módulo de intento publicado contiene el contrato canónico y el mensaje de
incertidumbre. No hubo evidencia de un asistente antiguo, import roto o doble
unwrap como causa del problema actual.

GET HTTPS `/login` publicado también respondió 200, pero sus metas API/dashboard/
post-login utilizaron HTTP. Se informó esa configuración; **no se cambió auth.js,
login.blade.php ni suscripción** en este microcierre.

### Correcciones delimitadas

- Layout de registro: API y navegación del registro usan rutas relativas al origen
  actual. Se obtiene la ruta API del URL generado por Laravel, sin hardcodear un
  dominio ni forzar HTTPS en el entorno HTTP local. Se preserva cualquier prefijo
  de ruta generado. No se altera el cliente central ni el Backend/proxy.
- Vista de registro: action, panel y login permanecen en el origen de la página.
  Tras el 201 validado, el resultado muestra **«Negocio y cuenta propietaria creados»**,
  slug/correo reales y login manual; nunca contraseña en URL ni auto-login.
- Máquina de intento: diferencia un fallo inicial de CSRF sin POST de un resultado
  posiblemente persistido. El primero conserva los campos y permite corregir;
  el segundo retiene UUID/snapshot. Explica 404/405 y el HTTP 500/503 recibido sin
  mostrar respuestas HTML, detalles SQL o trazas. Se mantiene un solo replay 419.
- No cambia el número de etapas, payload, política de contraseña, Resources,
  transacciones, autenticación, catálogo o compuerta comercial.

### Aceptación real y fallos inducidos

Base **gintly_frontend_qa_rol03**, MySQL local, Laravel/PDO efectivos verificados por
los helpers entregados; servidor propio `http://127.0.0.1:8840`. Cookies, Sanctum y
CSRF reales. Los secretos solo existieron en memoria de la prueba.

Run final **QA-REGISTER-RESULT-15ea190654ec**, Chrome **154.0.8037.93**:
**34 comprobaciones, salida 0**.

| Escenario | Evidencia |
| --- | --- |
| Handshake | GET /sanctum/csrf-cookie 204 real |
| Alta y resultado | POST /api/v1/auth/register 201 application/json; envelope exacto data{business_slug,owner_email}; un POST inicial pese a submits repetidos |
| Seguridad del resultado | Contraseñas borradas, foco en título, slug/correo reales, login accesible; GET /me 401 antes del login |
| Validación | Request QA interceptado para cambiar únicamente email a un valor inválido; Backend real devuelve 422; error owner.email, foco y valores conservados; BD sin alta para el intento rechazado |
| Corrección | Corrección desde UI, UUID nuevo tras el 422, POST real 201 |
| Respuesta perdida | Backend real procesó 201; la interceptación descartó únicamente la respuesta; lectura PDO confirmó un negocio antes del replay |
| Recuperación | Reintento explícito real 201, mismo UUID y body byte por byte, mismo resultado; un negocio/propietario/fila idempotente para ese intento |
| Login manual | POST /auth/login 200 con contraseña exacta y espacios; /me 200, ROL-01, negocio y capacidades correctos |
| Sesión humana activa | Registro real 403; enlace al panel, sin logout automático |
| Salida | Logout UI 204 real |
| Primer CSRF fallido | GET abortado de forma inducida; cero POST; «No se envió el registro», sin falsa afirmación de posible alta |
| Presentación | Capturas 375/1280 y recuperación 375 inspeccionadas; sin overflow horizontal, resultado y login legibles, foco útil |

Consola final: **0 excepciones JavaScript, 0 warnings, 0 respuestas 404**. Los errores
de recurso corresponden al /me 401 esperado, 422/403 reales y abortos inducidos.
Chrome también notificó ERR_ABORTED al terminar algunas respuestas 204 sin cuerpo;
el cliente las recibió y el recorrido continuó. No se contabilizan como fallos de
dominio ni se afirma una consola sin los rechazos esperados.

Hay dos correcciones del ejecutable QA, no de la aplicación: una espera inicial
de 15s expiró antes de recibir la respuesta del verificador Backend instalado;
se amplió a 65s, sin modificar el timeout del cliente ni su política. Otro run llegó
a login/403 pero esperaba el hook de contratación al volver al panel restringido;
se corrigió al hook existente de estado restringido. Esos runs terminaron con
salida 1 y no se presentan como aceptación completa.

Fixtures conservados intencionalmente en la misma base QA, sin limpieza general:

- QA-REGISTER-RESULT-2d2b00017287: negocio **35**, baseline real; salida 0, 14 checks.
- QA-REGISTER-RESULT-0bb4c8b54710: negocio **36**; run incompleto por espera corta;
  lectura posterior confirmó un propietario y una fila idempotente.
- QA-REGISTER-RESULT-e9e1d1e61aef: negocios **37,38,39**, 29 checks, salida 0.
- QA-REGISTER-RESULT-02238dd63944: negocios **40,41,42**; 31 checks previos al fallo
  del selector QA, salida 1; no se reutilizaron credenciales ni se borraron documentos.
- QA-REGISTER-RESULT-15ea190654ec: negocios **43,44,45**, aceptación final. Cada
  uno tiene exactamente un propietario y una fila idempotente, incluso tras replay.
- Los fixtures anteriores identificados en este documento permanecen intactos.

Capturas/evidence.json de esta fase están bajo `storage/app/qa/registration-result/`;
no se integran. Se eliminaron los perfiles temporales propios y se detuvo el
servidor de aceptación. No se guardaron contraseñas, cookies, trazas ni HAR.

### Comandos reproducibles y resultados finales

Usar la preparación completa de Node/PHP/Chrome/Playwright y guardas QA documentada
arriba; no existe ningún helper fuente nuevo bajo storage/app/qa. El ejecutable
nuevo importa únicamente el runtime/helper permanente de `tests/frontend`.

```powershell
$env:QA_REGISTER_REPRO_ONLY = '0'
$env:QA_REGISTER_PUBLIC_READONLY = '0'
# Mantener QA_REGISTRATION_BROWSER=1, APP_ENV local/testing, APP_URL=8840,
# DB_HOST=127.0.0.1, DB_DATABASE=gintly_frontend_qa_rol03 y demás guardas anteriores.
node tests/frontend/registration-result-browser.mjs
```

Opcional: `QA_REGISTER_REPRO_ONLY=1` ejecuta la comparación baseline (sí crea un
fixture QA); `QA_REGISTER_PUBLIC_READONLY=1` añade inspección de Azure con solo
GET/HEAD antes de la prueba local y necesita acceso de red. Nunca hace escrituras
externas. No se necesitan esas opciones para la aceptación local final.

| Comando | Resultado final / código |
| --- | --- |
| node --check sobre attempt.js, registration-result-browser.mjs, registration.test.mjs y api-client-registration.test.mjs | 4 archivos, 0 errores, salida 0 |
| node --test tests/frontend/registration.test.mjs tests/frontend/api-client-registration.test.mjs | 24 pass, 0 fail/skip, salida 0 |
| Añadiendo tests/frontend/registration-browser-guards.test.mjs a ese comando | 38 pass, 0 fail/skip, salida 0 |
| node --test tests/frontend/*.test.mjs | 125 pass, 0 fail/skip, salida 0 |
| npm run build | Vite 8.2.1, 106 módulos, salida 0; aviso PLUGIN_TIMINGS de rendimiento, no error de compilación |
| php -l tests/frontend/render-registration.php | Sin errores, salida 0 |
| php artisan view:cache | Aprobado, salida 0 |
| php artisan route:cache | Aprobado, salida 0 |
| php artisan route:list --path=register | 13 coincidencias, incluye /register y API canónica; salida 0; no se modificaron rutas |
| php tests/frontend/render-registration.php | Registro/login/landing renderizados; 19/11/9 IDs únicos, ARIA válido; 75 entradas de manifest válidas; salida 0 |

En este equipo npm no estaba en PATH: se ejecutó el mismo script build mediante
`node C:/laragon/bin/nodejs/node-v22/node_modules/npm/bin/npm-cli.js run build`.
Esa ruta es una particularidad del entorno QA, no una dependencia de la aplicación.
El test de HTTPS usa Fetch simulado con el cliente real y meta relativa; no se
confunde con un POST exitoso real a Azure.

### Manifiesto exacto de este parche

Aplicación — reemplazar versiones anteriores:

- resources/views/layouts/registration.blade.php.
- resources/views/auth/register.blade.php.
- resources/js/modules/registration/attempt.js.

Pruebas/documentación — reemplazar:

- tests/frontend/registration.test.mjs.
- tests/frontend/api-client-registration.test.mjs.
- tests/frontend/render-registration.php.
- docs/Frontend_registro_canonico.md.

Prueba nueva — crear:

- tests/frontend/registration-result-browser.mjs.

**7 modificados, 1 creado, 0 eliminados.** El manifiesto acumulado previo conserva
sus archivos y las siete bajas legacy; se suma únicamente el ejecutable nuevo.
No se modificaron wizard.js, api-client.js, auth.js, login, rutas, paquetes, Vite,
.env o Backend en este parche. Excluir todas las evidencias/perfiles/herramientas
de storage/app/qa y public/build; no copiar vendor/node_modules ni cachés.

### Integración/publicación manual por Roberto y aceptación externa pendiente

1. Conciliar los tres archivos de aplicación de este parche sobre el canónico ya
   integrado; conservar el resto de funcionalidades aprobadas. Integrar pruebas
   y documento, con sus helpers previamente entregados, para reproducir QA.
2. En su proceso de publicación, revisar la configuración **efectiva** HTTPS de
   Azure/Laravel: URL pública HTTPS, terminación TLS y proxies/forwarded headers
   de confianza según la infraestructura real. No confiar indiscriminadamente en
   proxies ni desactivar CSRF. Verificar dominio de sesión y Sanctum sin publicar
   secretos. Este parche protege el origen de registro, pero no arregla por sí
   solo las metas HTTP observadas en login y otras pantallas.
3. Desde el proyecto de destino preparado, generar `npm run build`. Publicar las
   fuentes Blade/JS, manifest y todos sus assets referenciados como una versión
   coherente, sin hardcodear nombres con hash ni reutilizar un public/hot.
4. Invalidar/reconstruir las vistas mediante el procedimiento autorizado:

   ```powershell
   php artisan view:clear
   php artisan view:cache
   ```

   No cambiaron rutas en este parche. Si el proceso reconstruye también su caché,
   usar `php artisan route:cache`, conservando la desconexión legacy existente.
5. Abrir una sesión privada y verificar que /register HTTPS genera meta relativa
   `/api/v1`, action relativo y login relativo; assets 200 y ninguna llamada HTTP
   desde una página HTTPS. Comprobar también las metas de /login tras la revisión
   de proxy/configuración. Capturar solo Network sanitizado, sin cuerpos con claves.
6. Completar el POST de aceptación **en un entorno QA autorizado del despliegue**:
   CSRF real, POST 201 application/json con data{business_slug,owner_email},
   confirmación, /me 401 previo, login manual y /me correcto. No crear pruebas en
   una base real. Consultar en lectura negocio/propietario/idempotencia si vuelve
   a existir un resultado incierto; conservar el UUID del intento, no refrescar
   para eludirlo.

Sin publicación ni acceso de escritura a un QA desplegado, **la aceptación externa
permanece pendiente**. Azure no se declara corregido. Para un incidente distinto,
la evidencia mínima es URL/método, HTTP, Content-Type, nombres de claves de respuesta
sanitizada, excepción de consola, identificador del intento y lectura autorizada
de persistencia por el responsable. Nunca compartir contraseña, cookies o cuerpos
completos del request. No hubo staging, commit, push, copias, despliegue, cambios de
Backend-Claude/gintly_app/Figma ni trabajo de suscripción/contratación.

## Incidente Azure: shell sin formulario — 2026-10-09

Esta intervención está autorizada directamente en `C:/laragon/www/gintly_app`.
No integra MFA ni cambia registro, autenticación, contratos o reglas comerciales.
El HEAD encontrado era `442a073`; el código del registro, sus entradas Vite y
package/lock siguen idénticos a `a062172`. README y cambios ajenos se preservan.

### Causa comprobada en navegador, no una inferencia por cero campos

Chrome **154.0.8037.93**, contexto aislado, únicamente GET/HEAD a Azure:

- `/register`: **200 text/html**, cuatro indicadores, formulario `hidden`, sin
  `data-initialized`, sin etapa activa y **cero inputs visibles**.
- El HTML solicita `http://[::1]:5173/@vite/client`, el módulo fuente
  `http://[::1]:5173/resources/js/modules/registration/wizard.js` y app.css al
  mismo servidor local. Console registra bloqueo CORS/loopback y `net::ERR_FAILED`.
- `/hot`: **200**, contenido `http://[::1]:5173`. Laravel Vite considera ese
  archivo prueba de servidor HMR y omite el manifest; el navegador del visitante
  no puede acceder al servidor de desarrollo del despliegue.
- `/build/manifest.json`: **200**, 75 entradas. Wizard, api-client y attempt
  compilados: **200 text/javascript**, idénticos por SHA-256 al build local del
  código de registro de `a062172`:

| Asset | SHA-256 |
| --- | --- |
| wizard-6-6kRHr6.js | 3892470e5c39c3c5335658c8125f915b1ebe854b2a7f0e3289f34438a980d077 |
| api-client-7BJXOQN-.js | b7f780149469ef8244f2cc127a5360e43dd1c07a4ed7ebc6d4c144484f17b16e |
| attempt-DlyvlX6X.js | 5e655e24e13492cef4abe0e15bdea21584ab689cda4545e1f66a47efdc7e54ce |

Esto descarta un wizard ausente o esos imports rotos, no demuestra el SHA de la
imagen ejecutada. El CSS tiene hash distinto entre builds; no se afirma paridad
completa de todos los assets ni se atribuye la causa a esa diferencia.
`.dockerignore` ya excluye public/hot y Dockerfile ya exige su ausencia y un
manifest compilado. No hay evidencia para modificar esos archivos: falta saber
si App Service ejecuta otra imagen o si el marcador fue incorporado en ejecución
o mediante un montaje. No se realizó ninguna escritura remota.

### Corrección y protección de regresión

`EnvironmentAwareVite`, registrado en AppServiceProvider, admite HMR **solo con
APP_ENV local**. En production/staging/testing usa el manifest, aunque exista un
marcador residual. No elimina archivos, modifica requests ni oculta el fallo con
un formulario sin inicializar. El public/hot del desarrollador permanece intacto.
Azure debe tener APP_ENV efectivo production; no se presume esa configuración.

`registration-shell.mjs` comprueba inicialización, cuatro etapas, primera etapa
activa, formulario y cinco campos de Cuenta realmente visibles. El ejecutable
existente lo utiliza al iniciar cada recorrido. La prueba negativa bloquea solo
el módulo compilado: el shell renderiza, pero la aceptación falla sin enviar POST.
La inspección opcional de Azure también falla si únicamente aparece el shell.
Se corrigieron además dos defectos del helper QA público: su sonda GET construía
una URL sin resolver la meta relativa `/api/v1` contra el origen, y utilizaba
`response.status()` en un Response Fetch nativo, cuya propiedad es `status`.
No eran defectos del cliente de la aplicación ni la causa del incidente desplegado.

### Aceptación local con build de producción

Run `QA-REGISTER-RESULT-b6d5e8fac00c`: **40 comprobaciones, salida 0**, Chrome
154.0.8037.93, servidor propio `http://127.0.0.1:8840`, Laravel/PDO efectivos en
`gintly_frontend_qa_rol03`. Se ejecutan módulos compilados, no Vite dev.

- Cuenta → Negocio (zona horaria) → Revisión → Resultado visibles.
- Handshake real 204; alta real 201 con envelope exacto y un POST pese al doble
  submit; contraseña con espacios preservada y borrada tras éxito; /me previo 401.
- Validación real 422 y corrección 201 con UUID nuevo; respuesta perdida después
  de un 201 real, replay real 201 con la misma clave y snapshot, sin duplicación.
- Login manual 200, /me 200 con propietario/negocio correctos, registro con sesión
  humana 403 y logout 204. Sin auto-login ni cambios en la compuerta comercial.
- Cero excepciones JS, warnings y 404. Los errores de recurso esperados son los
  rechazos 401/422/403 y abortos inducidos; no se presenta una consola sin ellos.
- Capturas inspeccionadas de Cuenta/Negocio/Revisión/Resultado en escritorio y
  Resultado móvil, sin overflow. No se repite ni certifica toda la auditoría
  responsive/zoom/accesibilidad previa, fuera de este microcierre.

Fixtures retenidos intencionalmente: negocios QA **535,536,537**, prefijo del run
anterior; exactamente un propietario y una fila idempotente por negocio. No se
limpiaron datos históricos ni se alteró la demo. Servidor detenido y perfil propio
eliminado. Evidencias bajo `storage/app/qa/registration-result/<run>/` y diagnóstico
Azure previo bajo `storage/app/qa/registration-production/azure-before/`; excluidos.

| Validación | Resultado / salida |
| --- | --- |
| php -l de guard Vite, provider y test | 3 archivos, sin errores / 0 |
| php vendor/phpunit/phpunit/phpunit tests/Unit/Frontend/EnvironmentAwareViteTest.php | 5 tests, 28 aserciones / 0 |
| php vendor/phpunit/phpunit/phpunit --testsuite Unit | 61 tests, 487 aserciones, sin omitidos / 0 |
| node --check de registration-shell.mjs, su .test y registration-result-browser.mjs | 3 archivos, sin errores / 0 |
| node --test tests/frontend/*.test.mjs | 140 pass, 0 fail/skip/cancelled/TODO / 0 |
| npm run build | Vite 8.2.1, 106 módulos; PLUGIN_TIMINGS de rendimiento, no error / 0 |
| php tests/frontend/render-registration.php con APP_ENV=production | Registro/login/landing; 19/11/9 IDs únicos, ARIA, manifest 75 entradas / 0 |
| php artisan view:cache; php artisan route:cache | Ambas aprobadas / 0 |
| php artisan route:list --path=register --json | 13 coincidencias, rutas canónicas y compatibilidad intactas / 0 |

Node/PHP no estaban en PATH. Se usaron sus ejecutables instalados; build mediante
`node node_modules/vite/bin/vite.js build`, equivalente exacto al script npm.
Las comprobaciones CLI de render/cachés usan conexión QA, no la base de gintly_app.
No se ejecutaron migraciones ni se instaló ninguna dependencia.

### Comandos de navegador reproducibles

Node 22.12+, PHP compatible con composer.lock, Chrome instalado y módulo Playwright
QA fuera del proyecto. Los helpers permanentes están todos bajo tests. Ajustar
solo las rutas de herramientas del equipo, sin introducirlas en fuentes:

```powershell
$env:QA_PHP = (Get-Command php).Source
$env:QA_CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe'
$env:QA_PLAYWRIGHT_MODULE = '<ruta absoluta externa a playwright/index.mjs>'
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
$env:QA_REGISTER_REPRO_ONLY = '0'
$env:QA_REGISTER_PUBLIC_READONLY = '0'
npm run build
node tests/frontend/registration-result-browser.mjs
```

Exige guardas Laravel/PDO antes de escribir. El recorrido local crea tres fixtures
QA; no ejecutar sobre otra base. Para validar primero la publicación, establecer
`QA_REGISTER_PUBLIC_READONLY=1`: solo GET/HEAD a Azure, y una página sin formulario
provoca salida 1 **antes de iniciar el servidor local o enviar registros**.
Si Azure pasa, el ejecutable continúa la aceptación local anterior. El primer
intento público de este microcierre (91b3e9b5a7fb) salió 1 por el defecto de URL
relativa del helper, no se cuenta como prueba positiva; no creó fixtures.

La segunda sonda (4495db0d999d) detectó correctamente el shell oculto, pero aún
mostraba el error de `status()` del helper. Tras corregirlo, la sonda definitiva
`QA-REGISTER-RESULT-958dae8aa452` observó HTML 200 y GET de registro 405 JSON
(método GET rechazado correctamente); cuatro indicadores, inicialización falsa
y cero campos visibles. Terminó con **salida 1 esperada**, por el incidente aún
desplegado, antes de cualquier servidor/POST local. No creó registros ni realizó
escrituras Azure; su perfil temporal se eliminó. Captura y evidence.json quedan
bajo storage/app/qa y no se integran. Este resultado no se cuenta como aprobación
de Azure, aunque sí demuestra que la nueva comprobación detecta el problema.

### Manifiesto exacto y aceptación externa pendiente

Creados (4):

- app/Support/EnvironmentAwareVite.php — adaptación de presentación Vite.
- tests/Unit/Frontend/EnvironmentAwareViteTest.php — build incluso con hot residual.
- tests/frontend/registration-shell.mjs — detector reutilizable, sin secretos.
- tests/frontend/registration-shell.test.mjs — siete casos de aceptación del detector.

Modificados (3):

- app/Providers/AppServiceProvider.php — binding compartido; conciliar al publicar.
- tests/frontend/registration-result-browser.mjs — detección, captura y sonda GET.
- docs/Frontend_registro_canonico.md — este cierre.

Eliminados: **ninguno**. Se suman a las fuentes anteriores del registro, no se
reintegran cambios ajenos. Excluir public/build, public/hot, cachés, capturas,
perfiles, .env, vendor/node_modules y herramientas temporales. El build es salida
regenerable de la imagen, no una fuente para staging.

Para cerrar Azure, Roberto debe publicar este lote como una versión coherente
mediante el proceso autorizado, comprobar APP_ENV efectivo production y ejecutar
en App Service la etiqueta SHA/digest resultante. El workflow actual construye y
publica en ACR: **eso no cambia por sí solo la imagen ejecutada por App Service**.
No se realizó commit/push, actualización de imagen, restart ni limpieza remota.
Se requiere la etiqueta/digest ejecutada, log de arranque y comprobar el archivo
hot/montajes para determinar cómo llegó ese artefacto a la instancia actual.

Tras publicación, `/register` debe solicitar únicamente `/build/assets/...`, sin
@vite/client ni direcciones :5173, y mostrar Cuenta propietaria y su etapa activa.
Ejecutar la sonda pública anterior y verificar Console/Network sin los bloqueos
observados. **Corrección y aceptación local aprobadas; Azure no se declara
corregido hasta esa publicación y comprobación.** No es un fallo de política de
contraseña, idempotencia o registro, ni se corrige integrando MFA.
