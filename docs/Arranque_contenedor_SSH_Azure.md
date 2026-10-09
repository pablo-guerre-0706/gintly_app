# Arranque Laravel y WebSSH de App Service

## Diagnóstico y alcance — 2026-10-09

En el Dockerfile inspeccionado faltaban `openssh-server`, su configuración,
el puerto interno 2222 y el inicio del daemon. Además, `entrypoint.sh` existía
pero no estaba conectado: la imagen ejecutaba directamente `apache2-foreground`.
Por tanto, el código no garantizaba WebSSH ni la regeneración de configuración
con las variables de Azure. Esto es consistente con el síntoma comunicado,
pero no acredita qué imagen/configuración ejecuta actualmente Azure.

La consola de Kudu/SCM no sustituye una sesión dentro del contenedor de la
aplicación; no encontrar allí PHP o `/var/www/html` no demuestra que falten
en la imagen PHP de Gintly.

## Parche

- `Dockerfile`: instala OpenSSH, copia y valida configuración y entrypoint,
  normaliza CRLF, conecta `ENTRYPOINT` y conserva Apache como `CMD`.
- `sshd_config`: puerto 2222 para el túnel WebSSH de App Service; HTTP permanece
  en 80. Solo root, sin contraseña vacía, X11 ni reenvío TCP.
- `entrypoint.sh`: prepara permisos, inicia SSH, regenera configuración/rutas/
  vistas y ejecuta el entrypoint de la imagen PHP con los argumentos recibidos.
- `tests/frontend/canonical-deployment.test.mjs`: regresión de empaquetado y
  arranque, incluido rechazo de fallos antes de iniciar Apache.
- Este documento: publicación y comprobaciones reproducibles.

Se conservan los permisos y las cachés de configuración, rutas y vistas del
arranque de Roberto. Se retira únicamente `cache:clear`: vacía el almacén de
datos y puede consultar BD/Redis; no es necesario para actualizar configuración.
No se añaden migraciones, seeders, cambios de APP_KEY ni rutas de mantenimiento.
`.dockerignore`, workflow, Apache, Vite/HTTPS y código de aplicación no cambian.

App Service inyecta sus variables antes de ejecutar el entrypoint. La secuencia
`config:clear` → `config:cache` se ejecuta entonces, no al construir la imagen.
Después se ejecutan `route:cache` y `view:cache`. Los archivos creados recuperan
los permisos existentes antes de iniciar Apache. Un error detiene el arranque
con código distinto de cero y un mensaje de etapa; no se imprimen variables.
Si el proceso principal sale por error, no se garantiza que WebSSH permanezca
disponible: utilizar Log stream para ese diagnóstico.

### Seguridad SSH

Se autorizó expresamente la configuración requerida por Microsoft: credencial
estándar de la plataforma para root y compatibilidad AES-CBC/HMAC-SHA1. Se añaden
solo `aes128-cbc` y `hmac-sha1` a los algoritmos modernos predeterminados, sin
reemplazarlos. Las claves host de validación se eliminan de la imagen final;
cada contenedor genera las suyas al iniciar.

**No publicar/mapear 2222 en Internet, no configurar WEBSITES_PORT=2222 ni
añadirlo a docker-compose.** Mantener el acceso SCM autenticado y sus restricciones
de red. La credencial estándar no es una credencial de la aplicación ni un
sustituto de la autorización Azure.

Referencias oficiales:
[SSH y variables de contenedores personalizados](https://learn.microsoft.com/en-us/azure/app-service/configure-custom-container?pivots=container-linux&tabs=debian#enable-ssh),
[entrada SSH](https://learn.microsoft.com/en-us/azure/app-service/configure-linux-open-ssh-session),
[cambio de imagen](https://learn.microsoft.com/en-us/cli/azure/webapp/config/container#az-webapp-config-container-set).

## Validación local realizada

Desde `C:\laragon\www\gintly_app`, PowerShell:

```powershell
$env:QA_BASH_BIN = 'C:\laragon\bin\git\bin\bash.exe'
& $env:QA_BASH_BIN -n entrypoint.sh
node --check tests/frontend/canonical-deployment.test.mjs
node --test tests/frontend/canonical-deployment.test.mjs
git diff --check
```

Puede usarse otra instalación de Bash indicando su ejecutable; en Linux,
`bash` en PATH basta, sin `QA_BASH_BIN`.

Resultado: **14 aprobadas, 0 fallos, 0 omitidas; salida 0**. Bash y sintaxis
JavaScript: salida 0. Diff sin errores: salida 0. Las pruebas ejecutan el script
con Bash real, pero sustituyen PHP, sshd, permisos y exec por dobles. Verifican
orden, disponibilidad de una variable inyectada, argumentos y detención ante
fallos; **no demuestran una conexión SSH real ni ejecutan Artisan real**.

No hay Docker disponible en este equipo; no se construyó ni inició una imagen.
No se modificaron cachés locales ni bases de datos. El Dockerfile ahora obliga
a `bash -n`, `sshd -t`, lint PHP y manifest válido durante la construcción.

### Validación pendiente con Docker, sin acceso a BD

En un runner/equipo con Docker, desde las fuentes revisadas:

```powershell
docker build --build-arg GIT_SHA=local-validation -t gintly-startup:qa .
docker run --rm --entrypoint /bin/bash gintly-startup:qa -c 'mkdir -p /run/sshd && ssh-keygen -A && /usr/sbin/sshd -t && bash -n /usr/local/bin/gintly-entrypoint'
```

Es una validación en un contenedor desechable: no montar `.env`, conectar BD ni
publicar SSH. No arranca Laravel. La prueba de arranque completo debe usar
exclusivamente configuración QA autorizada o la instancia de despliegue aprobada.

## Publicación y aceptación Azure — no ejecutadas

1. Revisar e incluir exclusivamente los cinco archivos del parche. README y
   archivos de caché preexistentes no pertenecen a este lote. Commit/push y
   despliegue requieren aprobación aparte.
2. El workflow existente construye/publica en ACR una etiqueta del SHA del commit.
   Verificar que ese commit incluye estos cinco archivos y que el build pasa.
   Publicar en ACR **no acredita** que App Service haya cambiado de imagen.
3. Revisar la imagen seleccionada y el Startup Command. Debe usarse el arranque
   incorporado, con el CMD `apache2-foreground`. No establecer como comando
   adicional `entrypoint.sh`/`bash entrypoint.sh`: provocaría un segundo arranque
   dentro del nuevo entrypoint. Si hay otro comando personalizado de Roberto,
   conciliarlo antes de sustituirlo; no cambiarlo a ciegas.
4. Tras autorización, seleccionar la imagen del SHA aprobado en App Service y
   reiniciar la instancia. Mantener APP_ENV=production, APP_DEBUG=false y HTTP 80.
   Las variables deben estar guardadas antes del reinicio.
5. En Log stream comprobar etapas 1/4, 2/4, 3/4, los tres mensajes de caché
   completada y 4/4; después verificar HTTP. Si falla, capturar únicamente la
   etapa/código y error sanitizado, nunca el volcado de configuración o secretos.
6. Abrir `https://gintly-app-web.scm.azurewebsites.net/webssh/host`, autenticarse
   en Azure y comprobar que aparece prompt. Dentro del contenedor:

```sh
cd /var/www/html
php -v
test -s bootstrap/cache/config.php && echo 'config cache presente'
/usr/sbin/sshd -T | grep -E '^(port|permitrootlogin|passwordauthentication|allowtcpforwarding) '
php -r '$c=require "bootstrap/cache/config.php"; echo "APP_ENV=".$c["app"]["env"].PHP_EOL; echo "APP_DEBUG=".($c["app"]["debug"]?"true":"false").PHP_EOL;'
```

Estos comandos no consultan BD ni muestran credenciales. No imprimir el archivo
config.php ni ejecutar `env`/`printenv`. La shell SSH puede tener un entorno
distinto del proceso inicial: **no regenerar config:cache desde SSH** para
aplicar App Settings; guardar los settings y reiniciar mediante el procedimiento
aprobado, que usa el entorno inyectado al proceso principal.

La validación externa permanece pendiente hasta confirmar SHA/digest ejecutado,
arranque real, caché efectiva y prompt WebSSH. No se afirma que Azure esté corregido.

## Estado de la entrega

Modificados: `Dockerfile`, `entrypoint.sh`,
`tests/frontend/canonical-deployment.test.mjs`.

Creados: `sshd_config`, `docs/Arranque_contenedor_SSH_Azure.md`.

Eliminados: ninguno. No se tocó `.env`, MFA, demo, registro, billing o datos.
No se hizo staging, commit, push, instalación local ni cambios remotos.
