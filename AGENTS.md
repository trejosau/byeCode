# Instrucciones del proyecto

Leer y seguir `C:\Users\User\.codex\RTK.md`.

## Memoria: stack acordado de ByeCode

Decisión confirmada por el usuario el 10 de octubre de 2026. Usar este stack como referencia para el desarrollo y las próximas conversaciones sobre este proyecto:

- Arquitectura: una aplicación Laravel modular, con navegación tipo SPA mediante Inertia.js y React, en un mismo proyecto.
- Backend y rutas: Laravel + PHP.
- Frontend: React + TypeScript.
- Comunicación entre Laravel y React: Inertia.js, sin desarrollar una API REST separada para la interfaz.
- Compilación de frontend: Vite.
- Interfaz: Tailwind CSS + shadcn/ui.
- Base de datos: PostgreSQL, accedida mediante Eloquent.
- Evaluaciones de IA en segundo plano: jobs y colas de Laravel.
- Pruebas del backend: Pest.
- IA: OpenAI API, llamada exclusivamente desde Laravel; el modelo concreto queda pendiente de selección mediante pruebas.

Laravel gestiona sesiones, permisos, validación, eventos, reglas R1–R6 y persistencia. React presenta el dashboard, cotizaciones, historial y recomendaciones. Inertia gestiona navegación y formularios. La IA interpreta y propone; el backend aplica las reglas y valida antes de publicar recomendaciones.

«Sin API» se refiere a no crear una API REST propia separada entre frontend y backend; sí se consume la API externa del proveedor de IA desde el servidor.

Esta decisión sustituye la propuesta anterior de Next.js y sus Route Handlers. El 10 de octubre de 2026 el usuario confirmó Supabase para alojar PostgreSQL y prohibió Docker. Laravel accede por Eloquent al Session pooler (puerto 5432, SSL), usando el esquema propio `byecode`. Autenticación y sesiones siguen en Laravel; no usar Supabase Auth ni su API de datos para la interfaz. Vercel y el hosting de Laravel siguen pendientes.

## Desarrollo local

- Aplicación inicializada en esta raíz con el starter oficial Laravel React, PHP 8.5 y Node 24. Mantener `composer.lock` y `package-lock.json`.
- Sin Docker ni Laravel Sail. `composer run dev` levanta Laravel, el worker de colas, la agenda y Vite.
- Credenciales únicamente en `.env` ignorado por Git. No exponerlas mediante `VITE_*`, props de Inertia, logs o documentación.
- `php artisan byecode:setup` crea el esquema propio y aplica únicamente migraciones pendientes; no usar `migrate:fresh` contra Supabase.
- Pest usa SQLite en memoria para aislar las pruebas automatizadas. Las comprobaciones reales de Supabase se ejecutan aparte, sin reiniciar su esquema.
- OpenAI tiene configuración del servidor preparada; API key y elección del modelo pendientes. No realizar llamadas facturables como parte de instalar o comprobar el stack.
- Mantener intacto `referencias/kit_publico`. El stack inicial no implica que estén implementados los módulos de cotizaciones, el adaptador del kit ni las reglas R1–R6.

## Documentación y pruebas del proyecto

Los apartados 8 (Arquitectura técnica) y 9 (Pruebas y evaluación) se desarrollaron el 10 de octubre de 2026 en la página de Notion indicada por el usuario: https://app.notion.com/p/3f5202c4d430800eaa69eb843aec926b. Las copias locales son `ByeCode_08_Arquitectura_tecnica.md` y `ByeCode_09_Pruebas_y_evaluacion.md`.

El kit público está copiado sin cambios en `referencias/kit_publico`; su auditoría estática está en `ByeCode_09_Auditoria_kit_publico.json`. El reloj oficial es `2026-10-09T16:00:00Z`. Hay 16 líneas iniciales, 15 eventos únicos y una actualización `P06-e3`. Los defaults oficiales son contacto permitido y fecha mínima nula. P06 contiene un contacto con fecha anterior a la apertura, que debe conservarse para calcular R4.

El esquema interno v1 no acepta directamente toda la entrada oficial: el kit usa `action.executed.data.action` y `proposal.viewed.data.section`, mientras que el interno usa `action_type` y no admite `section`. Implementar un perfil/adaptador explícito sin modificar el kit ni perder esos datos. No confundir la auditoría estática con pruebas del dominio o del modelo; estas siguen pendientes. Las pruebas del stack inicial y de autenticación están registradas por separado en `ESTADO_DESARROLLO.md`.

## Entrega local posterior del 10 de octubre de 2026

El plan de `PLAN_IMPLEMENTACION.md` ya se implementó sobre este stack: cotizaciones versionadas, catálogo/clientes, portal privado, adaptador explícito de eventos, reglas R1–R6, revisión humana, colas, presupuesto y laboratorio predictivo. Consultar `GUIA_USO.md`, `ESTADO_DESARROLLO.md`, `DESIGN.md` y `.impeccable/design.json` antes de continuar. Los párrafos anteriores sobre módulos pendientes describen el estado inicial, no esta entrega.

Hay 94 pruebas Pest aprobadas (340 aserciones) y comprobaciones separadas de Supabase. No atribuirlas a calidad semántica real: no se hicieron llamadas a OpenAI. Modelo predeterminado económico `gpt-5.6-luna`, tarifa conocida, sin escalamiento automático; su calidad debe medirse con `byecode:evaluate-model --allow-paid --user=ID` después de configurar la clave. Límites globales preventivos: 22 MXN/24 h, 160 MXN/7 días, 200 llamadas/24 h. Las reservas/consumos sobreviven a eliminar cuentas. No cambiar precios ni aumentar límites por iniciativa propia.

El predictor local necesita historial real con validación temporal, calibración y métricas para habilitar porcentajes experimentales; actualmente no hay un predictor comercial entrenado. El kit sintético nunca habilita esas probabilidades. La IA propone, Laravel valida, y aprobar no ejecuta contactos. La cuenta y los datos temporales de verificación se retiraron; no hay contraseña predeterminada. Hosting y SMTP de producción siguen pendientes.

## Flujo de clientes implementado posteriormente el 10 de octubre de 2026

- Roles: `admin` solo lee análisis y crea `commerce`; el registro público siempre crea `customer`. Comercio comparte catálogo y CRM operativo, con responsable opcional. Los kits/importaciones mantienen su propietario.
- Cuenta y CRM son distintos: solo enviar la primera solicitud crea `customers.account_id`; siguientes solicitudes reutilizan ese cliente. Excepción presencial en `/physical-request`, contraseña temporal aleatoria de 24 h para correos nuevos, cambio obligatorio; nunca restablecer una cuenta existente.
- Catálogo público por estilos y variantes sin precios. Cotizaciones con borrador interno, versiones, enlace de cuenta estable, chat y archivos privados. Aceptación, formalización, pago y cierre son hechos separados. No restaurar el portal anónimo ni el alta habitual de clientes por comercio.
- Gmail SMTP sustituye Resend por decisión del usuario. Configuración en `.env`; `TRANSACTIONAL_MAIL_ENABLED=false` hasta completar contraseña de aplicación. Correos de autenticación y notificación van cifrados a `mail_deliveries`, con caducidad y estado incierto sin reenvío automático.
- Administrador inicial local creado mediante `InitialAdminSeeder`; sus credenciales están únicamente en `.local/acceso-inicial-admin.txt`, ignorado por Git. No leerlas ni imprimirlas en diagnósticos. Debe cambiar contraseña al entrar. No volver a generar o restablecer ese acceso por iniciativa propia.
- Perfil dinámico con evidencia, naturaleza/confianza, historial y corrección humana. Inferencias no desplazan correcciones. Lotes en cola con máximo de contexto y presupuesto compartido; un fallo requiere reintento explícito y no omite evidencia. Sin llamadas reales a OpenAI en esta entrega.
- Predictor `business-history-v1` usa eventos y propiedades conocidos en cada corte, pagos previos y agregados históricos, con feature hashing y validación temporal. Sigue bloqueado hasta disponer de historial suficiente; no atribuir precisión a los mocks.
- 109 pruebas Pest (461 aserciones) aprobadas. Dos migraciones nuevas incrementales en `byecode`; rollback destructivo bloqueado. Leer la sección vigente de `ESTADO_DESARROLLO.md` y `GUIA_USO.md` antes de continuar.

## Perfil comercial por necesidades del 10 de octubre de 2026

- Siete dimensiones comunes en `profile_facts` con prefijo `commercial.` y alcance `cliente`: tipo de negocio, escala, recurrencia, proceso de compra, prioridades, requisitos y oportunidad de desarrollo. Se conservan las propiedades particulares con su contexto.
- El importe de un pedido, los pagos observados y el potencial son conceptos distintos. No deducir tamaño, solvencia o recurrencia por nombre de marca o monto; no otorgar crédito/descuentos por clasificación. Desconocido se representa como ausencia o valor JSON nulo, con historial.
- CRM con búsqueda, filtros combinables sobre clasificaciones confirmadas vigentes y 24 fichas por página. Hipótesis visibles para revisión; no sustituyen correcciones humanas. PostgreSQL/JSONB, sin MongoDB ni migraciones nuevas.
- 117 pruebas Pest, 633 aserciones; Larastan/TypeScript/build aprobados. Sin llamadas reales a OpenAI ni alteración de presupuesto. Consultar la sección más reciente de `ESTADO_DESARROLLO.md`.

## Diseño del CRM y perfiles del 10 de octubre de 2026

- Directorio responsive con tabla/fichas, filtros confirmados, orden global y vista rápida; perfil con secciones y edición contextual con evidencia. Mantener el retorno al directorio filtrado y los permisos de lectura del administrador.
- `CustomerDirectory` agrega solicitudes/pagos/actividad por separado para evitar multiplicar importes. Los enlaces de evidencia admiten eventos anteriores a la ventana de 200, siempre del mismo cliente. Fechas enviadas en ISO UTC.
- 123 pruebas Pest, 770 aserciones; Pint/Larastan/TypeScript/build aprobados. Sin migraciones ni llamadas reales de IA. La adaptación visual conserva el sistema ByeCode y se limita al CRM; consultar la guía y el brief de superficie en `.impeccable/surfaces`.

## Historial de demostración del 10 de octubre de 2026

- `OperationalDemoSeeder` / `byecode:seed-demo` implementados y cargados en Supabase: ejecución `operational-demo-v1` lista, 12 cuentas de cliente, 10 CRM, 34 casos (22 cerrados y 12 abiertos), 46 versiones, 27 pagos, 149 mensajes y 77 hechos de perfil. Leer `GUIA_DEMO.md` y la sección vigente de `ESTADO_DESARROLLO.md`.
- Contraseña inicial demo únicamente en `.env`; no restablecer cuentas existentes. Admin y equipo conservados. Dos cuentas sin CRM permiten probar su primera solicitud. La repetición solo verifica y conserva avances, archivos, fechas y contraseñas.
- Procedencia persistente `demo_run_id`; las nuevas solicitudes/cotizaciones de estas cuentas la heredan. Supresión de correo en productor y worker, bloqueo de OpenAI en evaluación/adaptador/perfil y exclusión del predictor, incluidos agregados. Desactivar el modo demo no elimina estas protecciones.
- Servicios de negocio compartidos y replay cronológico; para PostgreSQL se prepara SQLite temporal privada con IDs reservados y se transfiere en lotes dentro de una transacción auditada. Nunca retroceder secuencias ni reiniciar el esquema. No modificar silenciosamente el manifiesto de una ejecución cargada.
- 148 pruebas Pest, 1676 aserciones; Pint/Larastan/TypeScript/build aprobados. Verificación independiente en Supabase y portal de Marina en navegador. Sin llamadas reales a OpenAI ni envío de correos. Informes locales ignorados: `.local/demo-report.json` y `.local/demo-links.md`.
