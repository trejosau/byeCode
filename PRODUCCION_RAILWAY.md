# Producción en Railway

Preparación del 10 de octubre de 2026. El servicio `byeCode` del proyecto `luminous-endurance`, entorno `production`, sigue `trejosau/byeCode`, rama `main`. Un **push** a esa rama inicia las pruebas de GitHub; Railway espera a que aprueben para desplegar. Un commit que permanece local no dispara un despliegue.

URL reservada: <https://byecode-production.up.railway.app>. La preparación de configuración no confirma que el sitio esté publicado: falta subir estos archivos y comprobar el primer despliegue.

## Construcción y ejecución

- Railpack detecta PHP mediante `railpack.json`; Composer conserva su lockfile y PHP 8.5. Node 24 instala el lockfile de npm y ejecuta `npm run build`.
- Comando de build en Railway: `npm run build`.
- Pre-deploy: `bash railway/pre-deploy.sh`. Ejecuta `byecode:setup --force --no-interaction`, exclusivamente migraciones pendientes. No carga seeders ni reinicia datos. Una clave ausente bloquea producción.
- Inicio: `bash railway/start.sh`. Comprueba el entorno y el volumen, prepara permisos y cachés del código, y arranca Supervisor. No vacía la caché compartida de Laravel.
- Supervisor mantiene FrankenPHP, un worker y la agenda. Un único servicio y una réplica comparten el volumen; no habilitar réplicas adicionales sin rediseñar el almacenamiento.
- Worker: cola `production`, un intento por job, timeout 60 segundos y renovación cada hora. Los jobs conservan sus límites internos. La cola local `default` no se consume en producción.
- `/up` comprueba la conexión de base de datos y escritura en almacenamiento en producción; las respuestas de error no muestran credenciales. Railway espera hasta 180 segundos durante el arranque.
- Railway termina TLS; Laravel reconoce protocolo, puerto e IP reenviados, sin aceptar `X-Forwarded-Host`. La cookie de producción requiere HTTPS.
- El servicio permanece despierto para procesar la cola y la agenda, con reinicio ante fallo y 75 segundos para apagado ordenado.

Se usa Railpack gestionado por Railway. No se requiere Docker, Sail ni una API REST separada.

## Variables y datos

Se copiaron las 52 variables efectivas del `.env` local a Railway sin imprimir sus valores. Se preservan `APP_KEY`, conexión PostgreSQL, esquema `byecode`, SSL y credenciales. La clave de OpenAI facilitada por el usuario quedó en el `.env` ignorado y en las variables del servidor; no se incluye en Git ni en `VITE_*`.

Los únicos valores locales sustituidos para producción son `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` con el dominio HTTPS, `LOG_CHANNEL=stderr` y `LOG_LEVEL=warning`. Se agregan cookie segura y exclusiva, cola `production`, puerto 8080 y ajustes de Railpack/apagado. Las 60 variables resultantes se cotejaron en Railway por igualdad, sin volcar secretos.

Railway conserva la base de Supabase. Por instrucción posterior del usuario, el desarrollo local pasó a una base PostgreSQL independiente en esta computadora; ambas bases ya no se sincronizan. La transferencia inicial conserva las cuentas y el catálogo vigentes, sin restaurar historial eliminado. No copiar el `.env` local actual a Railway: sus valores `DB_*` apuntan a `127.0.0.1`. Consultar `POSTGRESQL_LOCAL.md`. No ejecutar `key:generate`, `migrate:fresh`, restauraciones ni seeders como parte del despliegue. `APP_KEY` debe mantenerse para descifrar datos existentes. Tarifas y límites de IA permanecen intactos; preparar el hosting no evalúa la calidad del modelo.

`TRANSACTIONAL_MAIL_ENABLED=false` se conserva: falta `MAIL_PASSWORD` de Gmail. Recuperación de contraseña y otros mensajes no tendrán entrega real hasta completar SMTP y habilitarlo. No se envió correo de prueba.

El volumen `byecode-volume` está montado en `/app/storage/app`, con 500 MB iniciales. Conserva adjuntos, importaciones y archivos públicos entre despliegues. El catálogo versiona sus imágenes en `public`; no depende del volumen. Los directorios privados y secretos se excluyen del contexto de Railpack.

## Archivos privados existentes y primer despliegue

Hay una copia de preparación en `.local/production-storage/private` y un manifiesto SHA-256 en `.local/production-storage-manifest.json`, ambos ignorados por Git. Solo contiene los archivos referenciados por `chat_messages` e `import_batches`, no todos los residuos de pruebas locales.

El volumen está listo, pero SFTP no permitió transferirlo antes del primer despliegue activo. La CLI devolvió timeout; la conexión directa tampoco aceptó la transferencia. La transferencia de los archivos existentes queda pendiente hasta que arranque el servicio. No confundir el volumen creado con los archivos ya cargados.

Después del primer despliegue, desde esta computadora y carpeta, está preparado este helper local (todavía no verificado contra el servidor activo):

```powershell
rtk proxy node .local/sync-production-storage.cjs
```

El helper selecciona los IDs exactos del servicio y entorno, sube una copia temporal, comprueba los hashes y copia exclusivamente archivos ausentes. Cancela si un archivo existente tiene contenido distinto; no usa `--overwrite`. Configura permisos de `www-data`, verifica el resultado y elimina sus temporales remotos al finalizar. La clave SSH local registrada para la transferencia es `byecode-local-deployment`; su privada está fuera del proyecto, en el perfil del usuario. Los helpers, el manifiesto y la copia preparada están en `.local`, fuera de Git.

Comprobaciones después del push:

1. GitHub Actions `tests` y despliegue de Railway aprobados.
2. `/up`, catálogo, login y recursos compilados responden por HTTPS.
3. `railway ssh -- php artisan byecode:check --queue` verifica base de datos y un job diagnóstico exclusivo, sin llamadas de IA.
4. `railway ssh -- supervisorctl -c /app/railway/supervisord.conf status` muestra web, queue y scheduler en `RUNNING`.
5. Completar y verificar los archivos privados referenciados, con permisos de `www-data`.
6. Comprobar un adjunto autorizado y que otro usuario no pueda descargarlo. No probar correo ni IA con llamadas reales sin solicitar esa operación.

Los despliegues con volumen pueden tener una interrupción breve. Configurar una política de respaldo del volumen antes de operar archivos irremplazables; el almacenamiento persistente por sí solo no es un respaldo.

## Fuentes

- [PHP en Railpack](https://railpack.com/languages/php/).
- [Volúmenes de Railway](https://docs.railway.com/volumes).
- [Conexión PostgreSQL de Supabase](https://supabase.com/docs/guides/database/connecting-to-postgres).
