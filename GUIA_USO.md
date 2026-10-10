# ByeCode: usar, comprobar y activar IA

Versión local del 10 de octubre de 2026. Laravel + Inertia + React/shadcn; PostgreSQL en Supabase. Sin Docker. El plan y las fuentes recuperadas están en `PLAN_IMPLEMENTACION.md` y `referencias/notion/00.md` a `07.md`.

## Catálogo configurable — actualización del 10 de octubre de 2026

El catálogo se configura por modelo, color y cantidades por talla. La selección se abre desde abajo y se conserva en sesión al navegar, recargar y entrar a la cuenta; el envío confirmado o salir de la cuenta la vacía. Se admiten 100 variantes por solicitud, borrador o cotización. El catálogo público no muestra precios ni gestiona inventario. El interruptor de tema está en el encabezado.

Los listados muestran todos los registros autorizados y eliminan el filtro anterior de procedencia. Los bloqueos internos de correo, IA y entrenamiento para las cuentas sembradas continúan activos. El acceso no exige verificación de correo, conforme al cambio anterior del proyecto. La documentación histórica de las entregas iniciales no modifica este comportamiento vigente.

Ver [CATALOGO_INTEGRACION.md](CATALOGO_INTEGRACION.md) para carga nueva, actualización sin pérdida de historial y entrega separada de imágenes.

## 1. Abrir la aplicación

En esta carpeta ejecuta `composer run dev` y abre [ByeCode local](http://127.0.0.1:8000). Se levantan Laravel, Vite, worker y agenda. No cierres ese proceso mientras esperas evaluaciones. Ctrl+C lo detiene. En esta computadora, las herramientas del agente se ejecutan con prefijo RTK; también puedes usar `rtk proxy composer run dev`.

El registro público crea únicamente cuentas de **cliente**. El administrador inicial consulta análisis y crea usuarios de **comercio**; comercio comparte catálogo, CRM y oportunidades operativas. Los conjuntos importados de prueba conservan su propietario. La cuenta inicial local se creó para Alberto Trejo; su acceso está en `.local/acceso-inicial-admin.txt`, fuera de Git. Al entrar debe cambiar la contraseña y después eliminar ese archivo.

Los correos se guardan cifrados en una cola privada. El envío por Gmail está desactivado hasta completar su contraseña de aplicación. Para confirmar una cuenta exclusivamente durante desarrollo local puedes ejecutar:

```powershell
php artisan byecode:verify-local-email tu-correo@example.com
```

El comando solo funciona en entorno `local`. Recuperación de contraseña y segundo factor siguen disponibles en la configuración de Laravel. No publiques el entorno de desarrollo en Internet; hosting, correo real y configuración de producción siguen pendientes.

### Equipo inicial de comercio

`CommerceTeamSeeder` crea las cuatro cuentas de Jorge, Dani, Jesús y Sau con rol `commerce` y correo verificado. Configura `COMMERCE_TEAM_INITIAL_PASSWORD` únicamente en `.env` (mínimo ocho caracteres), ejecuta `php artisan config:clear` y luego `php artisan db:seed --class=CommerceTeamSeeder`. La contraseña se almacena mediante hash y debe cambiarse al primer ingreso.

El seeder conserva las cuentas de comercio existentes, incluidas sus contraseñas y nombres. No ejecuta `InitialAdminSeeder` ni modifica el administrador. Si algún correo pertenece a otro rol, cancela el lote completo. No se ejecuta automáticamente desde `DatabaseSeeder`; no envía correos ni requiere migraciones.

## 2. Activar OpenAI con una clave

El historial listo para pruebas, las 12 cuentas y los recorridos de continuación están en [GUIA_DEMO.md](GUIA_DEMO.md). `byecode:seed-demo` carga el conjunto una sola vez y después verifica sin borrar avances. Los listados incluyen todos los registros autorizados, conservando internamente su procedencia. La marca demo bloquea correo e IA facturable incluso después de activar servicios reales.

Agrega tu clave únicamente en `.env`:

```dotenv
OPENAI_API_KEY=tu_clave
```

No es necesario elegir un modelo: `config/byecode.php` usa `gpt-5.6-luna` si `OPENAI_MODEL` está vacío. Reinicia `composer run dev` después de cambiar `.env`; los workers mantienen configuración en memoria. Si anteriormente ejecutaste `config:cache`, ejecuta primero `php artisan config:clear`.

En **Inteligencia y consumo** debe aparecer «Clave configurada». Esto confirma presencia, no validez: la primera evaluación que necesite interpretar un mensaje comprobará la conexión real. Abre un caso y usa **Evaluar contexto**. Las reglas deterministas pueden resolver cierres, prohibiciones o ausencia de mensajes sin consultar GPT. Los errores del proveedor, la falta de clave, el historial conflictivo y el límite de gasto se muestran como estados de evaluación; no se convierten en una recomendación comercial inventada.

Se usa Responses API, salida estructurada estricta, `store=false`, sin herramientas, y razonamiento `none` para el modelo predeterminado. Solo Laravel ve la clave. Se envía el contexto del caso necesario para interpretar mensajes; no se envían credenciales, tablas de usuarios ni catálogos ajenos al caso. Los eventos pueden contener texto del comprador, por lo que deben manejarse como información comercial autorizada.

Fuentes de selección consultadas: [modelo Luna y tarifas](https://developers.openai.com/api/docs/models/gpt-5.6-luna), [salidas estructuradas](https://developers.openai.com/api/docs/guides/structured-outputs). La tarifa configurada es USD 0.20 de entrada y USD 1.20 de salida por millón de tokens. También hay una tarifa explícita para [GPT-4.1 mini](https://developers.openai.com/api/docs/models/gpt-4.1-mini); no hay cambio automático de modelo. Un modelo sin tarifa configurada se bloquea. La calidad semántica de Luna todavía no se ha medido con llamadas reales en esta instalación.

## 3. Cuidar los 200 MXN

La instalación completa comparte un límite preventivo de **22 MXN por 24 horas móviles**, **160 MXN por siete días móviles** y **200 llamadas en 24 horas**. El margen de 40 MXN no se gasta por configuración de la app. La contabilidad usa 25 MXN/USD y factor 1.25 como supuestos conservadores, no como tipo de cambio real. Consulta el saldo/facturación del proveedor por separado: la app no controla otras aplicaciones que usen esa cuenta ni impuestos o cambios de tarifa.

Antes de cada llamada se reserva bajo bloqueo de PostgreSQL una cota de entrada y 1,800 tokens de salida. El contexto máximo es 20,000 bytes; no se recortan silenciosamente mensajes decisivos. Una respuesta con uso confirmado liquida esa reserva; si hubo timeout o no se conoce el uso, la reserva permanece. El presupuesto muestra consumo **preventivo**, no una factura exacta.

Los trabajos son idempotentes, no reintentan llamadas automáticamente y nunca escalan a modelos caros. Las vistas adicionales pueden reutilizar una interpretación reciente si no cambió el resto del contexto y supera de nuevo las reglas. Los reintentos manuales se limitan a tres por revisión y hora. Agotar el límite pausa la IA, conservando interfaz, historial y reglas. Una semana de duración depende del volumen y tamaño de los casos; no se promete uso ilimitado con ese saldo.

La eliminación desde ajustes está bloqueada para el administrador y para cuentas con historial comercial. Las relaciones de base de datos protegen ese historial. Los asientos de consumo y las reservas inciertas sobreviven a la eliminación de cuentas de prueba sin historial; crear otra cuenta nunca reinicia el presupuesto global.

## 4. Recorrido comercial

1. **Preparar el equipo:** desde `/admin`, el administrador crea cuentas comerciales. Cada una recibe acceso temporal por correo, cambia su contraseña y verifica su dirección. El administrador no puede publicar cotizaciones, conversar con compradores ni modificar el CRM.
2. **Catálogo:** comercio crea fichas con tejido, construcción, fotos HTTPS o rutas locales restringidas del catálogo, cuidados, guía de fits y medidas. Cada combinación de fit, color y talla tiene un SKU, composición, gramaje, medidas, tolerancia, MOQ y fecha de revisión de ficha. Se publica explícitamente; vacío significa desconocido. No hay precios ni identidad de clientes en el catálogo público. El seeder compartido añade seis fichas y 36 variantes con códigos comerciales internos; sus precios solo aparecen en las cotizaciones privadas.
3. **Cliente nuevo:** explora `/catalog`, selecciona cantidades por variante y pulsa **Solicitar cotización**. Si falta sesión, se registra o entra a su cuenta. La selección se conserva. Empresa, fecha deseada y mensaje son opcionales; una cantidad menor al MOQ se presenta como excepción pendiente de revisión.
4. **Enviar solicitud:** solo al enviarla se crea su ficha CRM. La cuenta y la ficha son entidades distintas. Se abre una oportunidad sin importe todavía. Solicitudes futuras reutilizan el mismo cliente; reintentar el mismo envío no duplica la oportunidad. El cliente ve **Mis solicitudes**; comercio recibe todo en `/requests`, con responsable opcional.
5. **Excepción presencial:** comercio usa **Cotización presencial** para capturar productos y datos del comprador. Si el correo es nuevo, se crea la cuenta con contraseña aleatoria de 24 horas y cambio obligatorio. El formulario explica el correo de acceso. Si ya existe la cuenta, se reutiliza sin cambiar su contraseña ni identidad. No hay un alta habitual de clientes desde el CRM.
6. **Preparar y publicar:** desde la solicitud se recuperan variantes y cantidades. El borrador es interno, admite guardado incompleto y detecta ediciones simultáneas. Para publicar se exige precio por línea, vigencia y autorización; Laravel calcula centavos y valida condiciones, autorización y descuentos. Cada publicación crea una versión inmutable y encola el enlace de cuenta por correo.
7. **Conversación:** el enlace `/my/quotes/...` es estable y requiere la cuenta del comprador o acceso de comercio. Incluye notas de la solicitud, versiones, comparación, condiciones, mensajes y adjuntos privados (imagen/PDF, hasta 5 MB). La conversación ocurre en el aplicativo; el correo solo notifica. Los enlaces antiguos exigen sesión y redirigen al nuevo espacio cuando son válidos.
8. **Decidir y formalizar:** el comprador puede aceptar cantidades específicas, añadir condiciones, rechazar o pedir cambios. La decisión guarda su versión y nunca cierra automáticamente. Versiones vencidas, retiradas o reemplazadas no aceptan decisiones nuevas. Comercio puede retirar la vigente con motivo y registrar formalización, pago, rechazo o cancelación con referencia verificable. Solo cierra la oportunidad al confirmar que todos los pendientes quedaron resueltos.
9. **Revisar análisis:** reglas, evidencias, recomendaciones, revisión humana y feedback siguen disponibles. Aprobar una recomendación no ejecuta contacto ni formaliza la venta. Los mensajes de comercio dentro del aplicativo sí registran un contacto y respetan R1–R4. Fechas de resultados se capturan en Ciudad de México; restricciones y observaciones técnicas usan UTC explícito.

R1 bloquea cualquier intervención al cerrar; R2 y R3 prevalecen sobre el interés; R4 usa 24 horas reales desde el último contacto/solicitud y solo un mensaje estrictamente posterior la levanta. Ese mensaje no levanta R2/R3. R5 impide convertir vistas o importe en intención/urgencia. R6 conserva faltantes y evita compromisos no sustentados. Si se desconoce una condición interna, se pide revisión interna antes de comprometer una respuesta.

Las recomendaciones conservan instantánea, versión de reglas/prompt, evidencia, alternativas y vigencia. Cambiar el caso, llegar a una frontera temporal o rechazar la recomendación impide presentarla/exportarla como vigente. «Confianza» describe la decisión; **no es probabilidad de cierre**.

## 5. Reproducir el kit

En **Simulador e importaciones**, crea una ejecución, pulsa **Evaluar casos** y abre P06. El reloj permanece en `2026-10-09T16:00:00Z`. La carga inicial contiene 16 líneas, 15 eventos únicos y seis casos. El contacto P06 de las 14:00, anterior a la apertura de las 15:00, se conserva y activa R4.

Incorpora **P06-e3** como un paso separado y vuelve a evaluar. El mensaje de las 15:30 cambia el conocimiento y levanta únicamente R4. Conserva el resultado anterior en el historial. Reimportar un evento idéntico no lo duplica; un ID con contenido distinto queda en cuarentena y no reemplaza el original. Corrige un conjunto conflictivo cargando una ejecución independiente, sin editar el kit original.

El exportador entrega exactamente los once campos del contrato por caso. Si falta algún resultado vigente, devuelve cobertura y casos faltantes, sin exportar un conjunto parcial como si estuviera completo. Una decisión pendiente de revisión sigue marcada `requires_human_review=true` en su contrato. Sin clave no habrá cobertura completa para los casos que necesitan interpretación.

## 6. Evaluar el modelo sin confundir mocks con calidad

`tests/Fixtures/semantic-v1.json` contiene diez escenarios de regresión: negación, aceptación condicionada/parcial, espera, dependencia interna, inyección de instrucciones, interés, cierre, prohibición y vistas. No se ejecutan llamadas reales al instalar ni correr Pest. Para autorizar la batería pagada:

```powershell
php artisan byecode:evaluate-model --user=ID_DE_TU_CUENTA --allow-paid --repetitions=1
```

El ID corresponde a tu usuario Laravel. El comando crea conjuntos de prueba privados, respeta los mismos límites y guarda un JSON en `storage/app/private/evaluations`. Reporta cobertura, fallos, acción esperada y señales esperadas; distingue resultados del proveedor de los resueltos por reglas. Admite hasta tres repeticiones. Si faltan clave o `--allow-paid`, termina sin llamar al proveedor. La batería pequeña es una regresión inicial, no una certificación de precisión. Revisa manualmente los fragmentos y los sentidos: una cita literal por sí sola no demuestra que la interpretación sea correcta.

## 7. Laboratorio de predicción de cierre

Abre **Inteligencia y consumo → Laboratorio predictivo** y pulsa **Evaluar historial comercial**. Usa oportunidades reales de todo el equipo, una instantánea por caso al día siguiente de su creación, señales conocidas hasta ese momento, historial previo de pagos y propiedades particulares del cliente. La fecha de incorporación también limita lo que se podía conocer. No incorpora información futura al entrenar una instantánea anterior. El objetivo sigue siendo aceptación confirmada en los siguientes 14 días, no intención inferida ni pago. Por consola: `php artisan byecode:train-history --user=ID_COMERCIO`.

La plantilla CSV permanece para experimentos externos controlados. El registro de características identifica si se usó CSV o historial del aplicativo. Las propiedades nuevas se codifican mediante 16 espacios de feature hashing; admite diferencias entre clientes, con posibles colisiones y sin atribución individual confiable. Una fila por caso, agrupada por cliente. Casos sin horizonte suficiente quedan censurados.

El entrenamiento local usa regresión logística regularizada, normalización ajustada solo con entrenamiento y calibración con un bloque separado. Divide temporalmente 60/20/20, elimina clientes compartidos y etiquetas que aún no habían madurado antes de cada corte. Exige 200 casos elegibles y, después de purgar, al menos 100/30/30 filas con diez resultados de cada clase en cada partición. Reporta Brier, log-loss, AUC, error de calibración y tabla de confiabilidad frente a una referencia constante.

Solo un conjunto declarado real, con Brier mejor que la referencia, AUC ≥ 0.65 y ECE ≤ 0.15, habilita una estimación **experimental**. El último entrenamiento sustituye la elegibilidad del anterior; no se oculta un fracaso eligiendo automáticamente otro. Los datos sintéticos nunca habilitan predicciones comerciales. Se bloquean modelos de más de 30 días y extrapolaciones evidentes. Los umbrales son criterios técnicos iniciales, no garantías; la procedencia de los contadores requiere verificación humana y falta evaluación prospectiva/deriva.

Actualmente no se proporcionó ese historial: no hay porcentajes de cierre válidos ni un modelo comercial entrenado. El kit de seis casos no sirve para estimar esa precisión. El laboratorio funciona sin OpenAI ni costo por tokens. La predicción no autoriza ninguna acción y nunca supera R1–R6.

## 8. Operación y comprobaciones

```powershell
php artisan byecode:setup
php artisan byecode:check --queue
php artisan byecode:dispatch --inline
composer test
npm run types:check
npm run check
npm run build
```

`setup` solo aplica migraciones pendientes. `dispatch --inline` procesa evaluaciones pendientes en el proceso actual y **puede consumir tokens si ya configuraste una clave**; es una alternativa diagnóstica al worker, no un paso necesario para instalar. La agenda recupera trabajos pendientes y programa revisiones vencidas. Un worker interrumpido no repite automáticamente una llamada de costo incierto; queda visible para revisión/reintento.

Pest usa SQLite en memoria y proveedor simulado; no reinicia Supabase. Nunca uses `migrate:fresh` ni `db:wipe` contra ese servidor. Los originales importados, reportes predictivos, evaluaciones y credenciales quedan privados. Conserva `composer.lock` y `package-lock.json`. La conexión real sigue el [Session pooler de Supabase](https://supabase.com/docs/guides/database/connecting-to-postgres), puerto 5432 y SSL; no usa Supabase Auth ni una API REST separada para la interfaz.

## 9. Activar Gmail y crear el administrador en otra instalación

El remitente de esta instalación es el correo indicado por el usuario. Google requiere verificación en dos pasos para generar una [contraseña de aplicación](https://support.google.com/accounts/answer/185833?hl=es). Guarda esa contraseña únicamente en `MAIL_PASSWORD` dentro de `.env`, sin espacios. No uses la contraseña normal de la cuenta ni la publiques en un chat. El servidor SMTP configurado es `smtp.gmail.com`, con `MAIL_SCHEME=smtps` y puerto 465 ([documentación de Google](https://developers.google.com/workspace/gmail/imap/imap-smtp)).

Completa `MAIL_USERNAME` y `MAIL_FROM_ADDRESS` con la misma dirección, establece `TRANSACTIONAL_MAIL_ENABLED=true`, ejecuta `php artisan config:clear` y reinicia `composer run dev`. Verifica `APP_URL`: los destinatarios necesitan una dirección accesible; `localhost` solo sirve en esta computadora. Este cambio habilita envíos de la cola, por lo que debe hacerse cuando las credenciales y el destino estén listos. No se envió ningún correo real durante la implementación.

Los correos de acceso, verificación, recuperación y publicación quedan cifrados. La agenda recupera pendientes cada cinco minutos. Al enviarlos, caducar o quedar inciertos se elimina el cuerpo cifrado. `smtp_accepted` significa aceptado por SMTP, no lectura ni entrega garantizada. Un timeout puede ocurrir después de la aceptación: queda `uncertain` y no se reenvía automáticamente para evitar duplicados. Si el acceso temporal caducó, el destinatario usa recuperación de contraseña; su cuenta existente nunca se restablece al capturar otra solicitud presencial.

Para otra instalación, configura `INITIAL_ADMIN_NAME`, `INITIAL_ADMIN_EMAIL` e `INITIAL_ADMIN_PASSWORD` (al menos 12 caracteres, letras y números) en `.env`. Ejecuta:

```powershell
php artisan db:seed --class=InitialAdminSeeder
```

El seeder no duplica cuentas, no restablece contraseñas y rechaza un correo que ya tenga otro rol. Después retira `INITIAL_ADMIN_PASSWORD` de `.env`. El administrador debe cambiar su contraseña al entrar; nunca se incluye una contraseña predeterminada en el repositorio.

## 10. Perfil evolutivo y trazabilidad

El **Perfil comercial** distingue siete dimensiones: tipo de negocio, escala de operación, recurrencia, proceso de compra, prioridades, requisitos de atención y oportunidad de desarrollo. Un pedido de $50,000 no determina si el comprador opera individualmente o pertenece a una cadena; el nombre de una empresa tampoco confirma su estructura ni su potencial. Los importes formalizados y pagados permanecen separados de la clasificación y de cualquier previsión.

Comercio confirma o corrige cada dimensión seleccionando evidencia del cliente. Las hipótesis de IA se muestran como pendientes de revisión y no sustituyen confirmaciones humanas. «Por confirmar» significa dato desconocido; guardarlo retira la clasificación anterior conservando el historial. Las dimensiones compartidas tienen opciones validadas y alcance cliente; las necesidades exclusivas de un pedido o colección se conservan como propiedades particulares con su contexto. Las condiciones de crédito, precio o atención requieren su autorización habitual.

La lista `/customers` permite buscar por empresa/contacto/correo y combinar filtros comerciales. Los filtros incluyen solo datos confirmados y vigentes, con 24 clientes por página. En escritorio se presentan en tabla y en móvil en fichas. Puedes ordenar por nombre, actividad reciente o importe pagado; las etiquetas permiten retirar filtros individuales. La flecha de cada cliente abre una vista rápida y su nombre abre el perfil completo. El regreso a Clientes conserva la consulta. Los perfiles mantienen sus propiedades JSONB en PostgreSQL, sin una segunda base ni nuevas migraciones. El administrador consulta los perfiles en modo lectura.

El perfil se organiza en **Perfil comercial**, **Actividad y evidencia** y **Oportunidades**. «Revisar» abre la edición de una dimensión con su evidencia; las necesidades particulares y el historial se consultan aparte. Un enlace a un evento abre su evidencia, incluso si es anterior a los últimos 200 eventos de la lista. Los importes formalizados, pagados y el promedio permanecen separados. La vista rápida es de consulta; la edición se realiza en el perfil.

En CRM, cada cliente muestra solicitudes, importes formalizados y pagados, promedio por oportunidad, actividad y evidencia. Una propiedad tiene nombre y alcance propios, valor JSON, fecha, confianza cualitativa, evidencia, naturaleza y estado. Las inferencias de IA se distinguen de los datos confirmados. Una corrección preserva el historial; una inferencia contraria a una corrección humana se registra como conflicto y no la reemplaza.

Con una clave de OpenAI configurada, cada actividad nueva vinculada al cliente solicita análisis en cola después de confirmar su registro. El perfil procesa lotes pendientes de hasta 30 eventos y continúa automáticamente si quedan eventos después de un lote completado. La agenda de cinco minutos sirve de recuperación. El contexto y el resultado se validan en Laravel; no se admiten IDs de evidencia inventados ni de otro cliente. Los reintentos de fallos son explícitos y limitados; el lote fallido no se omite al llegar actividad nueva. Comparten los mismos límites preventivos globales que las recomendaciones. Los cambios de propiedades activas invalidan las recomendaciones anteriores y solicitan revisión inmediata en cola. No se midió la calidad semántica real en esta entrega.

### Decisiones automáticas y ranking

Los eventos del negocio en modo operativo solicitan evaluación automáticamente después de confirmar la operación. Varias llegadas pueden agruparse antes de procesar el estado más reciente. Los duplicados no producen nuevas revisiones; las respuestas sobre un contexto sustituido no se publican como vigentes. Un pago sin cierre actualiza el análisis sin cerrar la oportunidad. Solicitudes y resultados comerciales también invalidan otros casos abiertos del cliente cuyos indicadores compartidos cambiaron. Se requiere el worker activo de `composer run dev`; la agenda conserva la recuperación de pendientes.

En el detalle de la cotización se muestran **Prioridad de acciones**, evidencia, momento sugerido, acciones excluidas con su motivo y **Qué cambió desde la decisión anterior**. El modelo ordena las acciones y Laravel valida cada alternativa con R1–R6 y su evidencia. El botón de evaluación sigue disponible para solicitudes explícitas y reintentos. Las pantallas de caso y perfil consultan actualizaciones cada diez segundos mientras están visibles.

La posición del ranking y la confianza baja/media/alta no son porcentajes. **Probabilidad de cierre · 14 días** muestra una estimación experimental solo cuando el predictor dispone de historial real y validación elegibles; incluye fecha, muestra y métricas de esa validación. En otro caso indica **Porcentaje no disponible** y la causa. La probabilidad del caso no mide el éxito atribuible a cada acción; actualmente ese porcentaje por acción no está estimado. Consulta `ESPECIFICACION_DECISIONES_POR_EVENTO.md` para el alcance frente al PDF del reto y los requisitos de datos pendientes.

La analítica de navegación se inicia automáticamente en el catálogo y en la cotización, sin aviso ni botones de autorización. No declara un consentimiento del cliente. Recoge búsquedas, filtros, cantidades, secciones visibles, duración visible, desplazamiento y consultas de versiones; no registra teclas ni contraseñas. El cómputo de duración se pausa con pestaña oculta o inactividad prolongada. Se etiqueta como dato no verificado del navegador; no demuestra lectura, intención, aceptación ni pago. Las operaciones comerciales mantienen su trazabilidad independiente de la navegación.

El enlace estable permite consultar el historial del propio cliente. Las decisiones, pagos y conversaciones no se eliminan al cerrar la oportunidad. La recuperación ante errores se realiza con cambios incrementales: las migraciones nuevas rechazan un rollback destructivo de este historial.
