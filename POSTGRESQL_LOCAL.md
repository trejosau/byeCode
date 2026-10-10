# PostgreSQL local de ByeCode

Configuración solicitada el 10 de octubre de 2026. Laravel local utiliza una base PostgreSQL independiente en esta computadora. Railway conserva su conexión a Supabase; los cambios entre ambas bases no se sincronizan.

## Conexión

| Parámetro          | Valor                                                        |
| ------------------ | ------------------------------------------------------------ |
| Servidor           | PostgreSQL 17.11, distribución Windows x64 de EDB            |
| Host y puerto      | `127.0.0.1:5432`                                             |
| Base de datos      | `byecode`                                                    |
| Esquema            | `byecode`                                                    |
| Usuario de Laravel | `byecode_local`, sin superusuario ni creación de roles/bases |
| Autenticación      | Contraseña aleatoria, SCRAM-SHA-256                          |
| SSL local          | Deshabilitado; escucha exclusivamente en loopback            |

Laravel lee la contraseña desde `.env`, ignorado por Git. La cuenta administrativa y una copia de las credenciales locales están en `.env.postgresql-local`, con permisos de archivo restringidos. No compartir estos archivos ni subirlos al repositorio. `APP_KEY`, las contraseñas de las cuentas de la aplicación y la configuración económica se conservan.

Instalación por usuario, sin Docker ni servicio que requiera administrador: `%LOCALAPPDATA%\Programs\ByeCodePostgreSQL17\pgsql`. Los datos persistentes están en `.local\postgresql\data`; no borrar esa carpeta. El log del servidor está en `.local\postgresql\server.log`. `psql` y las utilidades quedan en el PATH de las nuevas terminales.

Fuente: [descarga oficial para Windows](https://www.postgresql.org/download/windows/) y [binarios de EDB](https://www.enterprisedb.com/download-postgresql-binaries).

## Arranque y comprobación

PostgreSQL se inicia oculto al iniciar sesión con este usuario de Windows mediante `ByeCode PostgreSQL.vbs` en la carpeta Inicio. La aplicación se ejecuta con `composer run dev` como antes.

Para controlar únicamente la base desde la raíz del proyecto:

```powershell
rtk proxy powershell -NoProfile -ExecutionPolicy Bypass -File scripts/postgresql-local.ps1 start
rtk proxy powershell -NoProfile -ExecutionPolicy Bypass -File scripts/postgresql-local.ps1 status
rtk proxy powershell -NoProfile -ExecutionPolicy Bypass -File scripts/postgresql-local.ps1 stop
```

`start` conserva los datos y no reinicializa un clúster existente. Si se mueve la carpeta del proyecto, actualizar la ruta del acceso de Inicio. El arranque automático corresponde a PostgreSQL; Laravel, Vite, worker y agenda se levantan con `composer run dev`.

```powershell
rtk proxy php artisan byecode:check --queue
rtk proxy php artisan byecode:setup --no-interaction
```

El primer comando comprueba conexión, caché y un job diagnóstico exclusivo. El segundo aplica únicamente migraciones pendientes. No ejecutar `migrate:fresh` ni seeders para iniciar esta instalación.

## Datos y recuperación

La transferencia toma una instantánea de solo lectura del esquema vigente de Supabase. Conserva las cuentas, hashes de contraseñas, catálogo y registros persistentes, y verifica por tabla su cantidad y SHA-256, además de los valores de las secuencias. No restaura el historial demo eliminado. Sesiones, tokens de recuperación, caché y trabajos de cola no se trasladan, para no reutilizar sesiones ni ejecutar trabajos del otro entorno.

Respaldo de la configuración anterior: `.local/postgresql/.env.supabase-before-local`. Respaldo cifrado de la instantánea actual: `.local/postgresql/supabase-current-schema.dump.encrypted`, protegido con el cifrado de Laravel y la `APP_KEY` existente. Informe técnico sin filas de negocio: `.local/postgresql/transfer-report.json`.

Volver a Supabase requiere recuperar exclusivamente los valores de conexión de ese respaldo, limpiar la caché de configuración y reiniciar los procesos de Laravel. Los datos nuevos creados localmente no se transfieren al cambiar la conexión. No copiar el `.env` local a Railway: `127.0.0.1` allí no corresponde a esta computadora.
