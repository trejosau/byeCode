# Estado del desarrollo de ByeCode

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
