# ByeCode

Aplicación local de cotizaciones B2B, evidencia comercial y recomendaciones verificadas por reglas. El plan está en [PLAN_IMPLEMENTACION.md](PLAN_IMPLEMENTACION.md) y el recorrido completo en [GUIA_USO.md](GUIA_USO.md).

## Stack instalado

Laravel 13 + PHP 8.5, React 19 + TypeScript, Inertia 3, Vite 8 (herramientas de desarrollo Vite+), Tailwind 4 y shadcn/ui. PostgreSQL en Supabase mediante Eloquent. Autenticación de Laravel Fortify, sesiones, caché y colas en la base de datos. Pest 4 y Larastan. Sin Docker ni Sail.

Incluye clientes, catálogo, cotizaciones versionadas, portal privado, eventos, adaptador del kit, R1–R6, evaluaciones en cola, revisión humana, control de presupuesto y laboratorio predictivo. OpenAI está implementado en el servidor con `gpt-5.6-luna` como opción económica predeterminada. Falta tu clave; no se realizaron llamadas facturables ni se atribuye precisión real a las pruebas simuladas.

## Arrancar en esta computadora

Las dependencias y el archivo local de configuración ya están instalados:

```powershell
rtk composer run dev
```

Abrir http://localhost:8000. El comando inicia Laravel, Vite, el worker y la agenda. Ctrl+C detiene el conjunto. El registro público crea cuentas de cliente. El administrador inicial crea cuentas de comercio y consulta análisis; comercio comparte catálogo, solicitudes y CRM. La primera solicitud enviada inicia el CRM; registrarse no lo crea. La cuenta inicial local está provisionada y su acceso privado está en `.local/acceso-inicial-admin.txt` (ignorado por Git), con cambio obligatorio de contraseña.

El catálogo público muestra detalles y cantidades, sin precios. El comprador necesita iniciar sesión y verificar el correo para enviar su solicitud. Cotizaciones, versiones, conversación, adjuntos y decisiones se consultan desde su cuenta. Gmail SMTP está preparado; falta configurar `MAIL_PASSWORD` y activar `TRANSACTIONAL_MAIL_ENABLED` en `.env`. Verificación, recuperación y accesos temporales usan una cola cifrada, nunca el log. Consulta [GUIA_USO.md](GUIA_USO.md) para habilitarlo.

Para activar IA, agrega únicamente `OPENAI_API_KEY` en `.env` y reinicia `composer run dev`. Si necesitas confirmar tu nueva cuenta sin SMTP en este entorno local: `php artisan byecode:verify-local-email tu-correo`. Este comando está bloqueado fuera de `APP_ENV=local`.

La app reserva consumo antes de consultar: 22 MXN por 24 horas móviles y 160 MXN por 7 días móviles, compartidos por esta instalación. No equivale a un saldo de OpenAI ni a uso ilimitado. La predicción de cierre permanece deshabilitada hasta aportar historial real que supere la evaluación; la confianza de GPT nunca se presenta como probabilidad de venta.

## Preparar otra computadora

1. Instalar PHP 8.5 con pdo_pgsql, pgsql, pdo_sqlite, mbstring, openssl, curl, fileinfo, intl, zip y extensiones XML; Composer 2; Node 24 y npm.
2. Copiar .env.example a .env y completar DB_HOST, DB_USERNAME y DB_PASSWORD con la conexión Session pooler de Supabase (puerto 5432).
3. Ejecutar `composer setup`: instala dependencias, genera la clave solo si falta, prepara el esquema, aplica migraciones pendientes y compila el frontend.
4. Ejecutar `composer run dev`.

RTK se usa como prefijo en esta computadora según AGENTS.md. En otra computadora sin RTK, los comandos funcionan sin ese prefijo.

## Base de datos

El esquema de la aplicación es `byecode`; la conexión usa SSL y el Session pooler para IPv4. Laravel gestiona autenticación y acceso a los datos. Las claves Supabase y JWKS quedan únicamente en configuración del servidor para futuras necesidades; Eloquent usa las credenciales DB_*, sin Supabase Auth ni API REST entre React y Laravel.

```powershell
rtk php artisan byecode:setup
rtk php artisan migrate:status
rtk php artisan byecode:check --queue
```

El último comando verifica tablas, caché y un job en una cola diagnóstica exclusiva con un worker separado. No procesa otras colas. No ejecutar migrate:fresh, db:wipe ni las pruebas con RefreshDatabase sobre Supabase.

## Comprobaciones

```powershell
rtk composer test
rtk proxy npm.cmd run types:check
rtk proxy npm.cmd run check
rtk proxy npm.cmd run build
```

Pest usa SQLite en memoria, colas síncronas y sesiones/caché en memoria. Así las pruebas de autenticación no alteran Supabase. La comprobación real de PostgreSQL es independiente mediante byecode:check. CI usa las mismas pruebas aisladas y no necesita secretos de Supabase.

Los secretos solo viven en .env (ignorado por Git). .env.example no contiene credenciales. No poner claves privadas en variables VITE_* ni en props Inertia.

## Documentación y herramientas

- AGENTS.md: decisiones vigentes y reglas de trabajo.
- ByeCode_08_Arquitectura_tecnica.md y ByeCode_09_Pruebas_y_evaluacion.md: diseño y evaluación planificados.
- referencias/kit_publico: kit oficial preservado sin cambios.
- Skills de Supabase instaladas localmente con `npx skills add supabase/agent-skills --yes --agent codex`; reproducibles desde skills-lock.json.

Base generada desde laravel/react-starter-kit, revisión 717b8f55aefd82d25d4119eaebdc8e3a72b8d7e5. Las versiones resueltas están fijadas en composer.lock y package-lock.json.
