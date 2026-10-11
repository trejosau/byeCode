# Estado del desarrollo de ByeCode

## Navegación y evidencia explicada en el ranking — 10 de octubre de 2026

`BehaviorEvidence` añade al contexto secciones visibles, duración activa acumulada y desplazamiento máximo por versión, filtrados por caso/cliente y corte de ocurrencia e ingestión. Conserva todos los hechos comerciales y muestras de navegación por tipo/sección/versión; los agregados no se limitan a los últimos 30 eventos. El catálogo admite citas verificadas de actividad en los planes sin convertirlas en señales explícitas de intención ni modificar R1–R6. Se mantiene el límite de contexto y presupuesto.

Contrato semántico 4.2: motivos que conectan hechos observados con prioridad, distinguen inferencia y navegación y respetan mensajes/decisiones posteriores. Ranking con evidencia legible, fecha, versión, origen y resumen de navegación al corte. Caché semántica sensible al nuevo contexto; recomendaciones anteriores siguen como referencia sin inventar respaldo retrospectivo. El análisis continúa privado al equipo.

Validación aislada: 47 pruebas Pest (586 aserciones), cuatro pruebas frontend de telemetría, TypeScript, Larastan, formato/lint frontend y build aprobados. Tras el ajuste de presentación de evidencia, se repitieron las 18 pruebas de decisiones por eventos y Larastan, aprobadas. Sin migraciones, seeders, cambios de datos reales, correos ni llamadas reales de IA; calidad semántica real pendiente de medición.

## Costo de playeras, contraofertas y continuidad del ranking — 10 de octubre de 2026

Costo confirmado de fabricación: 47.30 MXN por playera, centralizado en `config/byecode.php`. Las evaluaciones operativas reciben costos por cantidad, contribución antes de otros gastos y límites; no se atribuye ese costo a prendas de categoría desconocida. Laravel rechaza totales sugeridos por IA inferiores al costo completo conocido más impuestos/envío; las concesiones siguen requiriendo revisión interna. El contrato distingue una primera objeción de precio de una incompatibilidad económica extrema y documentada: solo esta última puede justificar no negociar, sin cerrar automáticamente el caso. No se inventan márgenes objetivo ni utilidad neta.

El cliente puede proponer un total por producto y cantidad seleccionada en «Tu decisión». Cambiar el importe registra una solicitud de cambio con centavos exactos, trazabilidad e idempotencia; nunca acepta ni sobrescribe la cotización publicada. Comercio recibe la contraoferta para revisar y publicar otra versión.

Recargar conserva el análisis vigente. Si cambió el contexto o venció, se muestra el último resultado no rechazado como referencia desactualizada mientras se obtiene otro; no se presenta como autorización vigente. El historial analítico es privado de comercio/administración. La recarga no solicita por sí misma una evaluación.

Verificación: 5 pruebas nuevas / 117 aserciones y 40 pruebas existentes / 452 aserciones aprobadas; TypeScript, formato frontend, Pint, Larastan y build aprobados. Sin migraciones, seeders, correos ni llamadas reales a OpenAI. Calidad semántica del modelo no medida; el cambio de contrato se aplica a futuras evaluaciones, sin recálculo masivo de pago.

## Decisiones comerciales concretas en el ranking

El contrato de IA incorpora planes por acción: título contextual, motivo, pasos, borrador de respuesta, total propuesto y días adicionales cuando existe evidencia, comprobaciones pendientes y referencias a eventos del caso. La prioridad principal y las alternativas se presentan en el panel comercial. Las concesiones requieren revisión interna; no alteran precios publicados ni envían mensajes. Se conserva compatibilidad con recomendaciones anteriores, sin fabricar planes para ellas.

Validación aislada: 37 pruebas Pest y 176 aserciones aprobadas, TypeScript y build aprobados. OpenAI simulado; no se realizaron llamadas reales para comprobar este cambio. Los nuevos planes se generan en las siguientes evaluaciones automáticas por eventos.

## Corrección del indicador de análisis pendiente

El panel usa únicamente la evaluación de la revisión actual: un fallo o bloqueo no queda oculto por una marca de agenda, y una evaluación anterior no se presenta como el estado de un evento nuevo. El fallo retira su marca de recuperación vencida solo si la revisión sigue siendo la misma. Se refrescan los resultados al recuperar foco, volver a la pestaña o recuperar conexión, además del intervalo habitual.

Diagnóstico local de solo lectura: OpenAI sí produjo una recomendación vigente para la solicitud real; había intentos anteriores rechazados por validación. No se repitió la llamada ni se modificaron datos del caso para diagnosticarlo.

## Análisis automático y lectura por versión

Retirados Analizar con IA y Actualizar análisis de IA del panel comercial. El recorrido utiliza DecisionUpdates tras confirmar eventos, la cola y la agenda de recuperación. Navegación agrupada en ventanas de 20 segundos; mensajes y hechos de negocio sin demora añadida. El despachador conserva la marca de recuperación si llega una revisión nueva durante su ejecución.

Telemetría por caso/versión, observación de secciones agregadas por Inertia, duraciones acumuladas en bloques de 30 segundos, entrega de fracciones al ocultar/salir, y corte tras 60 segundos de inactividad. No atribuye lectura humana completa a visibilidad ni registra navegación comercial como cliente. El panel muestra también decisiones deterministas identificadas como reglas y estado de actualización automática, siempre privado al equipo.

Validación: 53 pruebas Pest, 472 aserciones, y cuatro pruebas de telemetría frontend aprobadas; TypeScript, Larastan, Pint, lint y build aprobados. Pruebas aisladas con proveedor simulado, sin llamadas reales de IA ni correos; tarifas y límites intactos.

## Ranking de IA integrado en la pantalla comercial

Sustituida la página separada de los casos con enlace de cuenta por redirección autorizada a su solicitud/cotización. La columna izquierda muestra las alternativas vigentes de origen IA y ranking validado, ordenadas por rango, incluyendo esperar/no actuar cuando corresponda; las alternativas bloqueadas se muestran aparte. El panel permanece tras publicar y Ver análisis es un ancla local. El lado derecho conserva cotización y conversación; el cliente no recibe análisis ni estado interno de evaluación. Los visores de kits/importaciones sin enlace de cuenta permanecen independientes.

Incluye solicitud explícita de evaluación y actualización del estado mediante el refresco existente. Sin ejecutar llamadas reales de IA ni modificar el cliente o la solicitud local.

## Preparación simplificada de cotizaciones

Formulario de solicitudes con un precio total por detalle, productos y cantidades precargados, suma automática y texto opcional. Datos generales y vigencia plegados; borrador y retorno a la conversación conservados. No ofrece cargos adicionales nuevos; muestra los ajustes existentes al recuperar una versión anterior.

Antes del primer envío se ocultan Ver análisis y Registrar resultado; se muestra directamente el análisis de IA vigente disponible para comercio/administración, sin llamadas automáticas y sin exponerlo al cliente. Los listados también reservan Ver análisis para solicitudes cotizadas.

Validación aislada: 23 pruebas del recorrido del cliente aprobadas, 278 aserciones; incluye suma de dos detalles con distintas cantidades, texto opcional y privacidad/vigencia del análisis. Sin modificaciones de datos reales, llamadas a OpenAI ni envíos de correo.

## Inicialización definitiva sin clientes sembrados

Por nueva decisión del usuario se retiraron las 12 cuentas demo y sus 34 casos locales, conservando una cuenta de cliente, su CRM y su solicitud creados fuera de esa carga. Conservados seis modelos, 36 variantes y registros de consumo de IA; respaldo PostgreSQL cifrado y verificado en `.local/postgresql/before-account-formalization-*.dump.enc`. Admin y cuatro comercios usan las credenciales locales solicitadas, verificadas mediante hash; sesiones anteriores invalidadas. Secretos únicamente en `.env` ignorado. Supabase y Railway sin cambios.

`DatabaseSeeder` llama solo a `InitialAdminSeeder`, `CommerceTeamSeeder` y `ProductCatalogSeeder`, en transacción. Su repetición conserva cambios de contraseña y clientes registrados manualmente. Carga sintética y asignación automática de procedencia demo bloqueadas fuera de `testing`; los fixtures permanecen para las pruebas. Se ejecutó la carga inicial contra la base local sin crear clientes ni duplicar catálogo. Sin llamadas reales de IA ni correos.

Validación: 38 pruebas de inicialización, equipo, recorrido de cliente y fixtures aislados aprobadas (1,069 aserciones); Pint y Larastan aprobados. Verificación real de cinco hashes del equipo, catálogo intacto y cliente manual conservado. No se alteró la protección de contraseñas temporales presenciales.

## Seeders ejecutados en PostgreSQL local el 10 de octubre de 2026

Por nueva instrucción expresa del usuario, cargado y auditado `operational-demo-v2` en la base local: 12 cuentas cliente, 10 CRM, 34 casos (22 cerrados y 12 abiertos), 46 versiones, 2 borradores, 17 formalizaciones, 27 pagos, 149 mensajes, 6 adjuntos y 77 hechos de perfil. Se reutilizaron los 6 modelos y 36 variantes existentes, asociando su procedencia a la ejecución sin duplicarlos. Se usó el replay directo transaccional de `DemoScenarioLoader`, evitando que el preparador PostgreSQL intentase insertar otra vez el catálogo. Admin, cuatro comerciales, contraseñas y registros de consumo de IA comparados antes/después sin cambios.

Auditoría inicial independiente aprobada: relaciones, cantidades, importes, evidencia, adjuntos y aislamiento; 12 contraseñas verificadas mediante hash. Contraseña aleatoria demo guardada exclusivamente en `.env`, clave `DEMO_CUSTOMER_PASSWORD`. Correos demo suprimidos y cero llamadas a OpenAI. El modo demo global permanece deshabilitado; la procedencia individual mantiene las protecciones. Informes y enlaces locales actualizados. Supabase y Railway no se modificaron y conservan su estado limpio.

## PostgreSQL local independiente del 10 de octubre de 2026

Instalado PostgreSQL 17.11 Windows x64 desde EDB, distribución enlazada por PostgreSQL, sin Docker ni privilegios administrativos. Clúster privado en `.local/postgresql/data`, escucha exclusiva `127.0.0.1:5432`, SCRAM-SHA-256, base/esquema `byecode` y rol de Laravel sin superusuario ni creación de roles/bases. Arranque oculto al iniciar sesión de Windows, control mediante `scripts/postgresql-local.ps1` y utilidades en PATH de nuevas terminales. Guía: `POSTGRESQL_LOCAL.md`.

Transferencia desde una instantánea de solo lectura de Supabase, sin modificar el origen. Verificación de contenido y cantidad mediante SHA-256 en 29 tablas persistentes y comprobación de secuencias. Conservados 1 admin, 4 comercio, 6 modelos, 36 variantes, APP_KEY y hashes de contraseñas; 0 clientes/casos. Sesiones, caché, tokens de recuperación y trabajos no se trasladaron. No se ejecutaron seeders. Respaldo cifrado de la instantánea, copia privada del entorno anterior e informe en `.local/postgresql`; credenciales locales fuera de Git.

El `.env` de desarrollo conecta ahora al servidor local. Railway conserva Supabase y no hubo despliegue ni cambios de variables remotas. Las bases son independientes y no se sincronizan. Los párrafos históricos sobre una base compartida corresponden al estado anterior.

Validación real: conexión PostgreSQL local y permisos limitados, escritura con rollback, migraciones al día, caché, job diagnóstico procesado por otro proceso y catálogo completo de 48 PNG aprobados. Ocho pruebas aisladas de stack/autenticación aprobadas, 26 aserciones. Sin llamadas reales a OpenAI ni envíos de correo; precios y presupuesto conservados. El lanzador de Inicio se comprobó tras detener y volver a arrancar PostgreSQL.

## Limpieza y cotización por total de producto del 10 de octubre de 2026

Por solicitud del usuario se retiraron de Supabase las 12 cuentas de cliente y todo el historial de demostración: clientes, solicitudes, cotizaciones, conversaciones, pagos, perfiles, evaluaciones, importaciones y navegación previa. Se conservaron el administrador, las cuatro cuentas de comercio, seis modelos, 36 variantes y sus imágenes. Respaldo cifrado verificado e informe en `.local`; sin reiniciar esquema, migraciones ni secuencias. El presupuesto y sus registros permanecen intactos. Las sesiones previas se invalidaron.

La contraseña del administrador fue sustituida por la indicada por el usuario y verificada mediante hash; su acceso privado local se actualizó. Las cuentas conservadas y los seeders de admin/equipo ya no exigen cambiar la contraseña al entrar. La protección de accesos temporales de 24 horas para nuevas altas presenciales permanece. El modo demo local quedó deshabilitado y sin ejecución activa. Los fixtures históricos se conservan para pruebas aisladas, no se volvieron a cargar.

El recorrido conserva la selección durante registro/login y crea CRM al enviar la solicitud. Comercio revisa los productos y el mensaje original, captura el total por producto y ve el importe por pieza calculado. El servidor valida los totales exactos, incluso si el precio unitario debe mostrarse aproximado. Se conservan borradores y versiones; la publicación registra al comercial automáticamente y vuelve a la conversación, donde el cliente consulta y responde. Se retiró el JSON visible de evidencia y propiedades del perfil.

Validación: 153 pruebas Pest, 1,773 aserciones y nueve omisiones de verificación de correo; después del ajuste final de navegación se repitieron las 21 pruebas del recorrido, con 230 aserciones. Pint, Larastan, TypeScript, lint/formato de archivos afectados y build aprobados. Navegador con SQLite temporal aislado: acceso comercial directo, solicitud precargada, 10 piezas por $2,000 con unitario de $200, publicación hacia conversación y acceso del cliente mediante Ver cotización. Captura en `.local/quote-flow-reviewed.png`. Pruebas con OpenAI y correo deshabilitados. La limpieza afecta la base compartida; estos cambios de código siguen locales, sin despliegue nuevo de Railway.

El manifiesto de transferencia de archivos a Railway quedó vacío porque los adjuntos/importaciones de prueba ya no están referenciados. Las copias privadas anteriores no deben restaurarse ni cargarse automáticamente.

## Preparación de producción en Railway del 10 de octubre de 2026

Configurado el servicio `byeCode` del proyecto `luminous-endurance`, entorno `production`, con autodespliegue desde `trejosau/byeCode:main` esperando GitHub CI, Railpack, pre-deploy incremental, inicio supervisado, `/up`, una réplica activa y volumen de 500 MB en `/app/storage/app`. Dominio reservado: `https://byecode-production.up.railway.app`. Guía vigente: `PRODUCCION_RAILWAY.md`.

Transferidas y cotejadas las 52 variables del `.env`, incluida OpenAI, con ajustes de producción y ocho variables adicionales: 60 valores sin ausencias ni diferencias. APP_KEY y la base Supabase existente se conservan; no se hicieron migraciones ni seeders en esta preparación. SMTP sigue deshabilitado por ausencia de contraseña de aplicación de Gmail. No se cambian modelo, tarifas ni presupuesto.

Archivos de arranque para FrankenPHP, Supervisor, cola `production` y agenda. `byecode:setup --force` habilita únicamente migraciones pendientes y rechaza generar una clave en producción. HTTPS detrás del proxy no confía en hosts reenviados. El healthcheck verifica base de datos y almacenamiento sin exponer detalles de conexión. PHPUnit fuerza clave OpenAI vacía y correo deshabilitado, independientemente del `.env` real.

Validaciones locales: build, TypeScript, Pint, Larastan y lint/formato aprobados; PostgreSQL, caché y job diagnóstico en cola exclusiva aprobados. Suite final completa: 150 pruebas y 1,727 aserciones, con nueve omitidas de verificación de correo. Incluye seis pruebas de producción (15 aserciones), incluida la respuesta segura ante fallo de base de datos. Escaneo de 579 archivos versionables y del build sin coincidencias de secretos. No representa una validación del primer arranque en Railway ni de la calidad real de OpenAI.

Pendiente el push de los archivos nuevos y el primer despliegue activo. Hay 285 archivos privados referenciados (246,264 bytes) preparados con manifiesto SHA-256 en `.local/production-storage`; su transferencia al volumen no pudo completarse por timeout de SFTP sin servicio activo. No se presentan como cargados. Los ajustes de Railway se guardaron sin publicar estos cambios; se cancelaron intentos con código anterior sin los scripts nuevos. Tras el primer arranque deben verificarse HTTP, procesos y archivos existentes. La configuración no implica que Gmail esté operativo.

## Cierre de catálogo e imágenes del 10 de octubre de 2026

Por instrucción urgente del usuario se instalaron los 48 PNG existentes, sin nuevas generaciones; el verificador completo de catálogo aprobó dimensiones y canal alfa. Esto sustituye el pendiente de imágenes descrito más abajo. Se quitó el desplegable de variantes de la solicitud presencial y se enlazó al configurador del catálogo. La edición comercial conserva selección de prendas mediante botones, IDs, cantidades y precios. Cambios acotados, sin nuevas migraciones ni cambios económicos.

Comprobación de cierre: 34 pruebas de solicitudes/cotizaciones, 238 aserciones, TypeScript y lint/formato aprobados. Remoto sincronizado sin conflictos antes del commit. Hosting y SMTP de producción conservan su estado previo; subir código no configura esos servicios.

## Catálogo configurable del 10 de octubre de 2026

Implementado el catálogo v2 de seis modelos y 36 variantes, fichas por color con cantidades S/M/L, selección persistida en sesión y panel inferior editable. El flujo completo admite hasta 100 líneas. La navegación espera el guardado y ofrece reintento ante fallos. El tema claro/oscuro persiste; se retiraron inventario operativo y avisos/filtros visibles de demostración, manteniendo las protecciones internas. Las cotizaciones conservan sus atributos e imágenes como snapshots.

Actualización incremental aplicada en Supabase con respaldo privado cifrado y mantenimiento. Se conservaron IDs, cuentas, clientes, casos, pagos, mensajes, perfiles y el hash original de la ejecución v1. La comparación independiente del respaldo confirmó cantidades, precios y atributos históricos sin cambios económicos. La repetición es idempotente; los roles públicos siguen sin acceso al esquema `byecode`. Se conserva intacto el kit público y el fixture v1.

Validación: suite completa con 143 pruebas Pest aprobadas, 1,694 aserciones y nueve pruebas de verificación de correo omitidas porque esa función está deshabilitada. Después se añadió y aprobó una regresión adicional de manifiesto/publicación con 18 aserciones. Pint, Larastan, TypeScript, lint/formato y build aprobados. La primera ejecución durante mantenimiento produjo respuestas 503; se repitió la suite completa con el sitio disponible. Sin llamadas reales a OpenAI ni correos enviados.

En navegador se verificaron configuración, edición de cantidades, guardado y persistencia tras recargar, traslado al formulario de solicitud y tema persistente; vistas de escritorio y móvil de 390 px. No se envió una solicitud de prueba: se retiró la selección temporal y se restauró el tema original.

Las 48 imágenes se producen y revisan en otro chat por indicación del usuario. Los datos y rutas están preparados; los recursos ausentes no se publican como enlaces rotos. Esta entrega no certifica imágenes todavía ausentes: `byecode:verify-catalog --data-only` valida datos y el comando sin esa opción exige todos los PNG y sus dimensiones/canal alfa. Consultar `CATALOGO_INTEGRACION.md` y `.impeccable/review/catalog-configurable.md`.

## Navegación automática del 10 de octubre de 2026

Por indicación del usuario, se retiraron el aviso y los controles de analítica del catálogo y las cotizaciones. El hook inicia los eventos al montar esas páginas y deja de capturar al salir. El receptor acepta eventos sin un campo de consentimiento; no se declara una autorización inexistente. El perfil y la guía reflejan este comportamiento, que sustituye las referencias históricas a navegación consentida.

Se conservan la lista de eventos y campos permitidos, la autorización de casos, la exclusión de actividad del equipo, la deduplicación y la marca `browser_unverified`. Las interacciones no demuestran intención ni resultados comerciales. Sin migraciones, llamadas reales a OpenAI ni envíos de correo durante la comprobación.

Validación: 27 pruebas Pest y 227 aserciones aprobadas en CustomerJourneyTest/EventDrivenDecisionTest; TypeScript, Pint, lint/formato de los archivos cambiados y build aprobados. Se comprobó el rechazo de resultados comerciales inventados y campos ajenos como contraseñas.

## Historial comercial de demostración del 10 de octubre de 2026

Implementado `PLAN_SEEDER_HISTORIAL_DEMO.md` mediante servicios compartidos con los formularios reales. `OperationalDemoSeeder` y `byecode:seed-demo` reproducen 351 operaciones ordenadas sobre nueve meses: 12 cuentas, 10 CRM, 34 solicitudes/casos, 46 versiones, dos borradores, 17 formalizaciones, 27 pagos, 149 mensajes, seis adjuntos privados y 36 variantes. Guía de cuentas y siguientes pasos: `GUIA_DEMO.md`.

Una migración incremental incorpora `demo_runs`, `demo_records`, procedencia persistente en entidades raíz/correos y autor/origen en hechos del perfil. La carga es transaccional, con manifiesto versionado y reloj estable, detección de colisiones, limpieza de archivos propios ante error y repetición sin sobrescribir avances. `byecode:verify-demo` audita importes, relaciones, evidencia temporal, archivos originales y aislamiento. Nuevas solicitudes/cotizaciones heredan la procedencia antes de programar evaluaciones.

Correo suprimido en productor y worker; análisis semántico deshabilitado por procedencia en evaluación, adaptador y perfil; exclusión del predictor tanto en filas como en agregados. Los escenarios siguen en modo operativo y admiten modificaciones normales. El envío comercial de mensajes ahora comprueba todos los bloqueos de contacto, incluidos R3/R4. Aviso visible y alcance todos/demo/sin demo en CRM, casos, solicitudes y administración. Las confirmaciones e hipótesis del guion son sintéticas y no prueban calidad de OpenAI.

- `composer run ci:check`: **148 pruebas Pest, 1676 aserciones**, Pint, Larastan, formato/lint y TypeScript aprobados.
- `npm run build`: aprobado; aviso opcional preexistente sobre optimización de fuentes.
- Pruebas nuevas de continuidad HTTP hasta cierre, borradores/versiones, privacidad de adjuntos, filtros/indicadores, evidencia temporal, no consumo de IA/correo, exclusión predictiva, colisiones e idempotencia; fallo inyectado con reversión/limpieza y reintento aprobado.
- Migración incremental y carga confirmadas en Supabase, ejecución `operational-demo-v1` en estado `ready`, referencia estable `2026-10-10T19:44:00Z`. Auditoría independiente aprobada: 77 hechos de perfil, 314 actividades y todos los conteos iniciales esperados; 12 accesos preparados y cuentas previas intactas. Informe y enlaces en `.local/demo-report.json` y `.local/demo-links.md` (ignorados por Git).
- Preparación mediante los servicios reales en SQLite temporal privada, reserva de IDs exclusivos y transferencia por lotes en una transacción PostgreSQL con auditoría previa al commit. Prueba de transferencia completa, incluidas las referencias de eventos dentro de snapshots. Directorio temporal limpio al terminar; las secuencias nunca retroceden.
- Aislamiento real comprobado: sin consumo adicional de IA, 46 notificaciones `suppressed_demo`, roles `anon`/`authenticated` sin acceso al esquema ni a las tablas nuevas. Worker verificado con `byecode:check --queue`.
- Navegador contra Supabase: acceso de Marina, sus cinco solicitudes, conversación y aceptación parcial de 18/36 unidades, comparación V1/V2 e importes visibles. Sin errores de consola ni desbordamiento horizontal en la vista disponible de 553 px. Acceso de Daniela y estado sin solicitudes comprobados tras la compilación final. No se avanzaron los escenarios durante esta revisión; los recorridos hasta cierre se prueban en SQLite aislado.

La integración conserva los cambios de decisiones por evento del chat relacionado, así como tarifas, presupuesto, kit y cuentas del equipo existentes. Los datos de prueba son ficticios y se distinguen de cualquier futura operación real.

## Decisiones por evento y ranking del 10 de octubre de 2026

Se revisaron las cuatro páginas del PDF del reto facilitado por el usuario y se documentó la diferencia entre el contrato obligatorio y las ampliaciones solicitadas en `ESPECIFICACION_DECISIONES_POR_EVENTO.md`.

Los eventos del negocio solicitan procesamiento en cola después de confirmar la transacción. Se incluyen operaciones registradas, pagos sin cierre, navegación consentida y cambios del perfil. Las solicitudes y resultados comerciales invalidan también los otros casos abiertos del cliente por sus indicadores compartidos. El worker usa el contexto confirmado más reciente; las evaluaciones pendientes sustituidas dejan de aparecer como trabajo vigente. Los lotes completados del perfil continúan automáticamente si queda actividad.

El modelo propone un orden de acciones en el contrato interno v2; Laravel valida cada alternativa y excluye las bloqueadas o sin evidencia. La pantalla muestra prioridades, razones, evidencia, momento, exclusiones y cambio respecto de la recomendación anterior. El contrato público del reto conserva once campos. Las pantallas de caso y perfil consultan actualizaciones cada diez segundos mientras están visibles.

La probabilidad experimental del caso a 14 días sigue condicionada a historial real validado; ahora se muestran muestra, fecha y métricas del entrenamiento elegible. No hay porcentajes de éxito estimados por acción, ni se convierte confianza cualitativa en probabilidad. La implementación no habilita un predictor comercial por sí misma.

- `composer run ci:check`: **140 pruebas Pest y 919 aserciones aprobadas**, Pint, Larastan, formato/lint de frontend y TypeScript aprobados.
- `npm run build`: aprobado; permanece el aviso opcional de optimización de fuentes, sin bloqueo de compilación.
- Revisión de navegador sobre SQLite temporal, usuario y respuestas sintéticas: ranking, exclusiones R2, historial de cambios y ausencia explícita de porcentaje; sin errores de consola ni desbordamiento horizontal a 1280 y 390 px. Registro en `.impeccable/review/event-ranking.md`.
- Sin migraciones nuevas, llamadas reales a OpenAI, cambios de tarifa/presupuesto ni alteraciones de los datos de negocio para verificar estos cambios. El kit público permanece intacto.

Las pruebas de IA usan respuestas simuladas: prueban flujo, contratos y restricciones, no calidad semántica real ni precisión de probabilidades.

## Equipo inicial de comercio del 10 de octubre de 2026

Se creó y ejecutó `CommerceTeamSeeder` para las cuatro cuentas solicitadas de Jorge, Dani, Jesús y Sau. PostgreSQL `byecode` conserva el administrador existente y añade cuatro usuarios `commerce`, con correo verificado y cambio de contraseña al primer ingreso. La contraseña inicial se configura únicamente en `.env`; no se versiona.

El lote es transaccional: un correo de otro rol cancela todas las altas. Repetirlo conserva nombres, contraseñas y estado de las cuentas existentes. No llama al seeder de administración ni se ejecuta automáticamente desde `DatabaseSeeder`.

- Cinco pruebas nuevas aprobadas (56 aserciones): acceso de las cuatro cuentas, cambio inicial, conservación del administrador y contraseñas modificadas, repetición, conflicto de roles y configuración ausente. Las 123 pruebas anteriores también aprobaron; Pint y Larastan sin errores.
- Comprobación separada en Supabase: cuatro cuentas creadas, hashes de acceso verificados, repetición sin cambios y administrador conservado. Sin migraciones ni envíos de correo.

## Diseño del CRM y perfiles del 10 de octubre de 2026

Se adaptó la organización visual del CRM de referencia al directorio y al perfil de ByeCode. El directorio usa tabla en escritorio, fichas en móvil, filtros combinables, etiquetas removibles, orden por nombre/actividad/pagos y vista rápida lateral. El perfil separa perfil comercial, actividad/evidencia y oportunidades; cada dimensión se revisa en su contexto. La navegación conserva la consulta del directorio.

Los agregados de solicitudes, pagos y actividad se calculan por cliente en subconsultas independientes antes de ordenar y paginar. Las evidencias enlazadas se recuperan aunque queden fuera de los últimos 200 eventos y se restringen al cliente. Los importes no se convierten en clasificaciones ni probabilidades. Sin migraciones, cambios de presupuesto o llamadas reales de IA.

- Pest: **123 pruebas, 770 aserciones aprobadas**. Cobertura de agregados, orden global y desempates, filtros, vista rápida, roles, evidencia antigua y retorno a filtros.
- Pint, Larastan, TypeScript y build de producción aprobados.
- Revisión visual con datos sintéticos en SQLite temporal: escritorio y móvil, temas claro y oscuro; filtro, edición con evidencia y regreso al directorio comprobados. Capturas en `.impeccable/review/crm-*.jpg`.
- Revisión independiente cerrada sin hallazgos materiales pendientes tras mejorar el foco de teclado en ambos temas. Servidor, base SQLite y scripts temporales retirados; se conservan únicamente las capturas de prueba.

## Perfil comercial por necesidades del 10 de octubre de 2026

Se añadieron siete dimensiones comerciales validadas sobre `profile_facts`: tipo de negocio, escala operativa, recurrencia, proceso de compra, prioridades, requisitos de atención y oportunidad de desarrollo. La clasificación se mantiene independiente del importe de una cotización y de los pagos históricos. Desconocido, hipótesis y confirmado se distinguen; una clasificación puede retirarse conservando evidencia e historial. Las propiedades particulares siguen disponibles.

El CRM incorpora búsqueda, filtros combinables de datos confirmados vigentes y paginación de 24 clientes. Se cargan perfiles por lote usando los índices existentes por cliente/estado. La interpretación en cola recibe el vocabulario y límites de inferencia; no se realizaron llamadas reales ni se alteraron presupuestos. No hay cambios de esquema, se conserva PostgreSQL/JSONB y no se instala MongoDB.

- Pest: **117 pruebas, 633 aserciones aprobadas**, incluidos dos clientes con $50,000 pagados y perfiles operativos diferentes, validación de categorías/evidencias, retiro de clasificación, filtros, paginación y roles.
- Larastan, TypeScript y compilación frontend aprobados. Proveedor de IA simulado; no se acredita calidad semántica real.
- Navegador con SQLite temporal: comparación de cuentas, filtro corporativo y guardado de requisitos con evidencia comprobados; revisión móvil a 390 px sin desbordamiento horizontal. Evidencia sintética en `.impeccable/review/commercial-profile.md`.

## Entrega vigente: cuentas, solicitudes y CRM del 10 de octubre de 2026

El registro público crea clientes; la primera solicitud enviada crea el CRM una sola vez. Se implementaron administrador de lectura/alta de comercio, bandeja compartida, captura presencial excepcional con acceso temporal, catálogo público detallado sin precios, borradores internos, cotizaciones versionadas, enlace autenticado estable, conversación y adjuntos privados, decisiones parciales/condicionales y registro separado de formalización/pago/cierre. La interfaz anterior de portal revocable fue sustituida por el espacio de cuenta; el enlace antiguo exige autenticación y conserva compatibilidad de consulta.

También se implementaron eventos de aplicación/navegación con consentimiento, propiedades evolutivas con evidencia y correcciones, procesamiento de perfiles en cola con presupuesto global compartido y entrenamiento local sobre el historial del negocio. Las particiones y características respetan el momento en que se conocieron los datos. El historial actual no habilita probabilidades comerciales.

- Pest: **109 pruebas, 461 aserciones aprobadas**. Proveedor simulado, SQLite aislado y cero llamadas reales de IA.
- Larastan y TypeScript aprobados; frontend compilado. Pruebas añadidas para roles, aislamiento, CRM idempotente, contraseña temporal, cola cifrada y caducidad, borradores concurrentes, versión retirada, adjuntos, evidencias inventadas, reintento explícito de IA y corte temporal predictivo.
- Dos migraciones incrementales aplicadas en PostgreSQL `byecode`. Se preservan casos y presupuesto; no se reinició Supabase.
- Administrador inicial creado con los datos indicados por el usuario. Contraseña aleatoria local, excluida de Git, con cambio obligatorio. No se crearon compradores ni productos ficticios en PostgreSQL.
- Gmail SMTP configurado como transporte y remitente. **Pendiente `MAIL_PASSWORD` y habilitar envíos**. No hubo correos reales ni verificación de entrega. Los mensajes de autenticación también pasan por la cola cifrada.
- Navegador en SQLite temporal: catálogo con cantidades, inicio de sesión, recuperación de selección, envío de solicitud, bandeja comercial, borrador recuperable, publicación, conversación y decisión parcial condicionada comprobados. Los datos son sintéticos y no forman parte del negocio.
- Cierre de verificación: base SQLite y scripts temporales retirados; PostgreSQL conserva un administrador, cero clientes, cero productos y cero solicitudes. `byecode:check --queue` aprobó conexión, caché y job en proceso separado; `anon` y `authenticated` siguen sin USAGE sobre el esquema. No se hallaron secretos de configuración en 418 archivos del proyecto/build. Kit público y lockfiles intactos. Evidencia visual: `.impeccable/review/customer-journey.md`.
- Pendientes de operación: completar Gmail, cargar fichas/variantes reales y elegir hosting/URL pública. OpenAI sigue sin clave; calidad semántica y validación predictiva con historial real pendientes.

Esta sección sustituye las descripciones anteriores de cuentas aisladas, alta habitual de clientes por comercio, portal anónimo y correo en logs. Las secciones siguientes conservan la historia de las entregas previas.

## Entrega posterior: versión usable del 10 de octubre de 2026

Se implementaron el plan de `PLAN_IMPLEMENTACION.md` y los recorridos de `GUIA_USO.md`: cotizaciones y líneas versionadas, clientes, catálogo, portal por enlace revocable, eventos, kit oficial, R1–R6, evidencia, recomendaciones e historial, revisión/feedback, colas/outbox, reservas de presupuesto, Responses API y laboratorio predictivo local. Vistas shadcn/ui basadas en la composición del Figma indicado; sin Docker ni servicios nuevos.

| Comprobación de esta entrega                   | Resultado                                                                                                                                     |
| ---------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------- |
| Pest, stack + dominio + seguridad + predicción | 94 pruebas, 340 aserciones, aprobadas                                                                                                         |
| Larastan y TypeScript                          | Sin errores                                                                                                                                   |
| Formato y lint frontend                        | Sin advertencias ni errores                                                                                                                   |
| Build de producción                            | Compilado                                                                                                                                     |
| Migraciones PostgreSQL                         | Cuatro migraciones nuevas aplicadas incrementalmente en `byecode`; consumo conservado al eliminar una cuenta                                  |
| `byecode:check --queue`                        | PostgreSQL, caché y worker separado correctos                                                                                                 |
| Privilegios de API de Supabase                 | `anon` y `authenticated` siguen sin USAGE en `byecode`                                                                                        |
| Kit preservado                                 | Ocho hashes SHA-256 coinciden con la auditoría inicial                                                                                        |
| Navegador sobre PostgreSQL real                | Cliente, variante, cotización, portal, mensaje, kit inicial, evaluación por reglas y P06-e3 comprobados en cuenta temporal                    |
| Capturas responsivas                           | Resumen en 1440/1280/390 px; detalle en 1440/390 px; portal en 1280 px, guardados en `.impeccable/review`                                     |
| Secretos y limpieza                            | Secretos ausentes de 365 archivos visibles para Git/build; cuenta temporal, dos conjuntos, siete casos y catorce archivos de prueba retirados |
| Consumo real de OpenAI                         | Cero llamadas; clave ausente                                                                                                                  |
| Documentación visual                           | JSON válido, diez referencias de componentes resueltas, ocho secciones canónicas y colores cotejados con CSS                                  |

Los mocks verifican contratos y control de flujo, **no** la comprensión lingüística de GPT. La batería semántica real se ejecuta explícitamente con `byecode:evaluate-model --user=ID --allow-paid`. `gpt-5.6-luna` se seleccionó por tarifa y capacidades documentadas, pendiente de medir su calidad real. No hay un predictor de cierre entrenado con historial comercial: el laboratorio está implementado y probado, y bloquea porcentajes cuando falta evidencia de validación. No hay SMTP real, hosting público ni contactos automáticos.

La revisión de Impeccable terminó con disposición `ship` limitada a sus cuatro correcciones, puntuadas `resolved`: decisión visible en bandeja móvil, prioridad de decisión en detalle, alcance explícito de indicadores y aviso de IA sin truncamiento. Informe: `.impeccable/review/verdict.md`. Sistema visual: `DESIGN.md` y `.impeccable/design.json`. Las capturas contienen datos sintéticos usados para verificar, no resultados comerciales. La cuenta temporal se eliminó al terminar y sus enlaces privados quedaron revocados.

El servidor local quedó iniciado con `composer run dev`; la comprobación final confirmó PostgreSQL, caché y un job procesado por otro proceso. No se crearon servicios públicos ni credenciales compartidas.

El agente documentador alcanzó su límite después de escribir ambos archivos. La comprobación final se completó en el agente principal: se corrigió una referencia documental de `button-link`, se validó el JSON y se cotejaron tokens con el CSS. Esto no añade una revisión visual independiente distinta del verdict ya registrado.

## Registro histórico: stack inicial, antes de esta implementación

Fecha: 10 de octubre de 2026.

## Inicializado y verificado

- Laravel 13.35, PHP 8.5, React 19, TypeScript, Inertia 3, Vite 8 mediante Vite+ 0.3.3, Tailwind 4 y componentes shadcn/ui.
- Registro, inicio y cierre de sesión, perfil, recuperación y verificación de correo, y configuración de segundo factor mediante Laravel Fortify.
- Supabase PostgreSQL por Session pooler, puerto 5432 y SSL, con tablas de la aplicación en el esquema `byecode`.
- Migraciones iniciales de usuarios, sesiones, caché, colas y segundo factor aplicadas. Lectura y escritura de Eloquent verificadas dentro de una transacción revertida.
- Caché y job de diagnóstico comprobados en Supabase; el job se procesó desde un worker separado.
- `composer run dev` probado en Windows: Laravel, worker, agenda y Vite. Sin Docker ni Sail.
- Configuración de OpenAI preparada exclusivamente en Laravel. No se configuró una clave ni se hicieron llamadas.
- Git local inicializado en `main`, sin publicación remota. Credenciales locales excluidas de Git y ausentes del frontend compilado.
- Skills `supabase` y `supabase-postgres-best-practices` instaladas en `.agents/skills` desde `supabase/agent-skills`; disponibles para próximos turnos.

## Validación ejecutada

| Comprobación                              | Resultado                                                  |
| ----------------------------------------- | ---------------------------------------------------------- |
| Pest                                      | 42 pruebas aprobadas, 143 aserciones                       |
| Pint y Larastan                           | Aprobados                                                  |
| TypeScript                                | Sin errores                                                |
| Lint y formato frontend                   | Aprobados                                                  |
| Build de producción                       | Completado                                                 |
| Coherencia del lockfile npm               | `npm ci --dry-run` aprobado                                |
| Auditorías de dependencias Composer y npm | Sin vulnerabilidades reportadas                            |
| PostgreSQL/Eloquent                       | Inserción y lectura correctas; transacción revertida       |
| `byecode:check --queue`                   | Tablas, caché y worker correctos                           |
| Acceso al esquema desde roles de la API   | `anon` y `authenticated` sin permiso USAGE sobre `byecode` |
| Navegador                                 | Portada y pantalla de acceso renderizadas                  |

Las pruebas Pest usan SQLite en memoria; las verificaciones de Supabase se ejecutaron aparte. Estos resultados corresponden al stack y a la autenticación inicial, no a una evaluación del dominio comercial ni del modelo de IA.

## Siguiente etapa

Implementar cotizaciones, eventos y el adaptador explícito del kit público; después las reglas R1–R6, el flujo de evaluación y las pruebas de dominio. Seleccionar un modelo con mediciones y conectar OpenAI cuando haya una clave y presupuesto autorizados. Elegir hosting de Laravel para el despliegue.

El correo de desarrollo escribe en el log local (`MAIL_MAILER=log`). Aún no hay proveedor SMTP ni envío de correos reales. La portada y el panel indican que los módulos comerciales están en desarrollo.

Ver [README.md](README.md) para instalación y comandos.
## Ampliación de contexto del 10 de octubre de 2026

Corrección posterior: `request.submitted` estaba presente en `activity_context`, pero ausente del catálogo de evidencias de planes. Se incorpora como hecho comercial del mismo caso, con productos/cantidades, manteniendo las restricciones de contacto. Versión de contexto 4.3. Validación: 47 pruebas (324 aserciones) de negociación, seguridad y eventos; rechazo de evidencias inventadas o de otra solicitud. Para resolver el fallo reportado se hicieron dos análisis reales locales dentro del presupuesto existente: diagnóstico y evaluación corregida. La evaluación 13 terminó correctamente y dejó un ranking vigente para la solicitud reportada. Sin cambios de tarifas, presupuesto ni correo.

Por petición del usuario, el máximo de contexto serializado pasa de 20,000 a 200,000 bytes UTF-8, configurable mediante `BYECODE_MAX_CONTEXT_BYTES`, tanto para ranking como para lotes de perfil. Se conservan la evidencia completa del ranking, tarifas y límites de presupuesto. Los bloqueos anteriores no se reintentan automáticamente.

Validación: 49 pruebas de seguridad, análisis por eventos y perfil comercial aprobadas (371 aserciones), incluyendo envío íntegro de un historial mayor de 20 KB y bloqueo previo a gasto si supera el nuevo máximo. HTTP simulado y SQLite aislado, sin llamadas reales de IA ni correo.

## Ranking directo de acciones a tomar del 10 de octubre de 2026

Por requerimiento comercial, se rediseña la presentación del ranking y el contrato de razonamiento:
- Se elimina la sección de "Acciones excluidas por las reglas" y el desglose de evidencia técnica (UUIDs de eventos y resúmenes redundantes de navegación/mensajes ya visibles en el caso).
- La interfaz presenta el "Ranking de acciones a tomar" con enfoque ejecutivo: título de la acción directa, escenario y probabilidad de qué puede pasar (`Qué puede pasar · Escenario y probabilidad`), pasos directos a tomar, borrador de respuesta condicional y validaciones previas.
- Prompt versión `byecode-semantic-4.4`: instruye a la IA a evitar preámbulos y repetición del historial, ordenando las alternativas por viabilidad comercial y sintetizando en cada opción el escenario directo y las probabilidades estimadas de avance vs. riesgo (máximo 45 palabras).
