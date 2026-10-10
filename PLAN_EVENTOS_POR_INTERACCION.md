# Plan de implementación: eventos al finalizar interacciones y entrega de cotizaciones

Fecha: 10 de octubre de 2026. Estado: **plan verificado contra el código actual; implementación pendiente**.

## 1. Objetivo y límites

Sustituir el envío periódico de telemetría cada diez segundos por el envío al terminar una interacción, incluyendo los eventos anteriores pendientes. Identificar qué sección estuvo visible, en qué versión, durante cuánto tiempo y por qué terminó ese tramo. Mantener publicación, historial, conversación y acceso privado a las cotizaciones.

Ejemplo esperado: el cliente consulta precios en V2 durante 18 segundos y pasa a condiciones; al producirse la transición se envía el tramo de precios. La observación se expresa como «sección visible», nunca como lectura, comprensión o intención confirmadas.

Se mantiene el registro automático sin el aviso ni los botones retirados por petición del usuario. No se registra un consentimiento inexistente. No se añaden captura de pantalla, grabación de sesión, pulsaciones ni contenido de formularios de autenticación.

Este trabajo conserva Laravel/Inertia/React, PostgreSQL mediante Eloquent, el endpoint de sesión `/activity` y las colas existentes. No requiere un servicio externo de analítica, WebSockets, Supabase Realtime, Docker ni una API REST separada. No incluye activar SMTP, enviar mensajes reales, cambiar tarifas/presupuesto o ejecutar llamadas pagadas a OpenAI.

El enlace directo sigue requiriendo la cuenta autorizada. No se introduce acceso anónimo. La consulta periódica de mensajes, cotizaciones y recomendaciones es independiente de la telemetría y se conserva.

## 2. Verificación realizada antes de escribir el plan

Se revisaron las instrucciones del proyecto, RTK, `GUIA_USO.md`, el estado vigente de `ESTADO_DESARROLLO.md`, `GUIA_DEMO.md`, el sistema de diseño y los archivos de los recorridos afectados. Se consultaron las referencias técnicas del apartado 12.

Se preservaron los nueve archivos con cambios previos de este chat. Este turno añade solamente este plan al código fuente versionable; no modifica la aplicación. La compilación regenera los artefactos habituales.

| Comprobación ejecutada                                                       | Resultado actual                                                                                   |
| ---------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------- |
| `rtk proxy php vendor/bin/pest --compact`                                    | **148 pruebas, 1674 aserciones, aprobadas**                                                        |
| `rtk proxy npm run types:check`                                              | Aprobado                                                                                           |
| `rtk proxy npm run check`                                                    | Formato y lint aprobados                                                                           |
| `rtk proxy php vendor/bin/phpstan analyse --memory-limit=512M --no-progress` | Sin errores                                                                                        |
| `rtk proxy php vendor/bin/pint --test`                                       | Aprobado                                                                                           |
| `rtk proxy npm run build`                                                    | Aprobado; aviso preexistente de dependencia opcional `fontaine`                                    |
| `rtk proxy npx --no-install vp test --help`                                  | El ejecutor frontend de Vite+ está disponible; no se ejecutaron pruebas frontend de comportamiento |

Pest utiliza SQLite en memoria. El resultado corresponde al estado posterior a retirar el consentimiento; el registro histórico de 1676 aserciones no es la cifra de esta ejecución. No se hicieron llamadas reales de IA, envíos SMTP, migraciones ni verificaciones de escritura en Supabase. No se realizó en este turno un recorrido visual en navegador ni una prueba de carga. Esas comprobaciones quedan definidas como entregables posteriores, no como resultados ya obtenidos.

## 3. Estado real y riesgos encontrados

| Área / archivo                                    | Hallazgo verificado                                                                                                                           | Consecuencia para la implementación                                                                                                                 |
| ------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| `resources/js/hooks/use-activity.ts`              | Cola global en memoria, envío de hasta 50 eventos cada 10 s; reencola fallos de red y HTTP 5xx                                                | Separar observación y transporte; enviar al cierre y tratar explícitamente 429/419 y lotes pendientes                                               |
| Mismo hook                                        | Se extraen eventos antes de confirmar recepción; los HTTP 429/422/419 no se reencolan                                                         | No considerar entregado un evento solo por haber iniciado `fetch`                                                                                   |
| Mismo hook                                        | Consulta los elementos una vez al montar; el efecto depende del caso, no de la versión                                                        | Registrar/desregistrar secciones dinámicas y cortar tramos al cambiar de versión                                                                    |
| Mismo hook                                        | Duración por sección cada 10 s; no calcula el último tramo parcial al salir                                                                   | Medir con reloj monotónico entre transiciones, incluyendo el último tramo                                                                           |
| Mismo hook                                        | Hay umbral 0.4, pero el callback decide por `isIntersecting`; secciones altas y contenedores anidados complican la interpretación             | Definir cobertura explícita y excluir contenedores de las sumas; probar móvil y secciones largas                                                    |
| `resources/js/pages/journey/quote.tsx`            | Secciones `proposal`, `products`, `prices`, `terms`, `decision`, `conversation`; `proposal` contiene otras                                    | Usar las secciones de contenido como unidades; no contabilizar el padre y sus hijos como tiempo total independiente                                 |
| Mismo archivo                                     | El botón de comparación emite `ui.version_comparison_opened` tanto al abrir como al cerrar                                                    | Distinguir apertura/cierre y finalizar el tramo correspondiente                                                                                     |
| Mismo archivo                                     | Recarga mensajes, decisiones, versiones y caso cada 10 s                                                                                      | Conservar esa recarga; no generar aperturas por cada respuesta de Inertia                                                                           |
| `resources/js/pages/journey/catalog.tsx`          | Comparte el hook; búsquedas y cantidades se registran al salir del campo                                                                      | Proteger catálogo y selección; los cambios del hook no se limitan a cotizaciones                                                                    |
| `TelemetryController.php`                         | Lista permitida, UUID, autorización por caso, validación de versión, origen `browser_unverified`, máximo 50 eventos, duración máxima 30000 ms | Añadir contrato v2 compatible; un tramo de 45 s no cabe hoy sin ajustar explícitamente su validación                                                |
| `routes/journey.php`                              | `/activity` tiene `throttle:60,1`                                                                                                             | Agrupar transiciones próximas y respetar `Retry-After`; evitar una petición por píxel o pulsación                                                   |
| Middleware instalado de Laravel                   | El límite numérico usa identidad de usuario autenticado, o dominio/IP para visitantes                                                         | Probar varias pestañas de una cuenta y visitantes con IP compartida; no prometer capacidad ilimitada                                                |
| `TelemetryController.php` / `DecisionUpdates.php` | La actividad nueva puede invalidar todos los casos abiertos del cliente y encolar perfil y evaluación                                         | Medir el efecto de las ráfagas; no cambiar indiscriminadamente el alcance de invalidación                                                           |
| `EvaluationService.php::cachedSemantic`           | La clave incluye `activity_context` y `customer_context`                                                                                      | Nuevos eventos pueden invalidar la reutilización semántica; no asumir que ya se evita una llamada por interacción                                   |
| `RefreshCaseDecision` / `EnrichCustomer`          | Jobs únicos hasta comenzar el procesamiento, un intento; existe recuperación de pendientes                                                    | La unicidad no garantiza por sí sola ausencia de trabajos simultáneos una vez iniciados; comprobar exclusión por entidad                            |
| `BusinessHistory.php`                             | El predictor suma `ui.section_visibility_duration.data.duration_ms`                                                                           | Versionar el cambio de medición; no mezclar duraciones antiguas y nuevas como si fueran equivalentes                                                |
| `QuoteService.php`                                | Publicación compara `expected_revision` del caso; telemetría incrementa esa misma revisión                                                    | Más envíos pueden interrumpir la publicación aunque nadie haya editado la cotización; separar conflicto comercial de navegación antes de activar v2 |
| Migración de `activity_events`                    | Ya existen UUID único, `schema_version`, JSONB y fechas de ocurrencia/ingesta                                                                 | Usar el esquema existente para el contrato nuevo; no se prevé migración destructiva ni recreación de datos                                          |

Los hallazgos anteriores son de inspección estática, excepto las comprobaciones automatizadas del apartado 2. Se convertirán en pruebas de regresión antes de corregirlos.

## 4. Comportamiento a implementar

### 4.1 Tramos de visibilidad

Cada tramo queda ligado a página, pestaña, caso, versión y sección. Estados: fuera de vista, visible, pausado y finalizado. Al salir o cambiar de estado se calcula la duración con `performance.now()` y se cierra una sola vez. La ausencia de interacción no permite concluir que alguien dejó de leer.

Parámetros iniciales propuestos, configurados en un único lugar y sujetos a las pruebas visuales:

- Una sección es candidata cuando se ve al menos el 50 % de su altura visible posible: el denominador se limita a la altura del área de visualización. No exigir ver la mitad de una tabla más alta que la pantalla.
- Exigir 250 ms de estabilidad para evitar cambios repetidos en un borde al desplazar. No es un ciclo de envío; solo estabiliza una transición.
- Conservar 60 s como límite inicial de ausencia de interacción para pausar la medición de actividad. Registrar `idle`, no «terminó de leer». Movimiento, desplazamiento, toque o foco pueden reanudar; no capturar teclas ni valores de texto como señal de actividad.
- Al iniciar o reanudar, partir de un reloj nuevo. Tiempo oculto o pausado no se agrega retrospectivamente.
- Varias secciones pueden estar visibles simultáneamente. Sus duraciones son exposición por sección; no se suman para afirmar tiempo total de sesión o atención exclusiva. El agregado de página utiliza la unión de intervalos.
- No medir `proposal` como otro bloque de contenido si ya se miden sus hijos. Los elementos dinámicos se registran mediante referencias React con limpieza, sin observar indiscriminadamente todo el documento.

La duración es una observación aproximada del navegador. Un tramo cerrado por inactividad se distingue de uno cerrado por cambio de sección. La interfaz de análisis conserva esa causa para evitar interpretaciones excesivas.

### 4.2 Qué finaliza una interacción

| Acción o transición                                    | Registro esperado                                                                                                                                         |
| ------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Una sección deja de cumplir el criterio de visibilidad | Un cierre con sección, versión, duración y causa `section_exit`                                                                                           |
| Cambia de V1 a V2                                      | Finalizar los tramos de V1 antes de abrir los de V2 y registrar consulta de V2                                                                            |
| Abre comparación                                       | Registrar una apertura real y las versiones incluidas                                                                                                     |
| Cierra comparación                                     | Registrar cierre y duración; no otra apertura                                                                                                             |
| Edita cantidades o búsqueda en catálogo                | Registrar valor final al confirmar/salir, solamente si cambió; sin enviar cada pulsación                                                                  |
| Selecciona filtro                                      | Registrar la selección confirmada y enviar con pendientes                                                                                                 |
| Termina desplazamiento                                 | Registrar profundidad alcanzada al estabilizar el desplazamiento; usar fallback con debounce acotado                                                      |
| Pulsa imprimir                                         | Registrar clic; no afirmar impresión terminada, porque el diálogo puede cancelarse                                                                        |
| Envía mensaje, acepta o solicita cambios               | El servicio Laravel conserva el evento comercial tras guardar. La telemetría puede describir el clic, pero nunca sustituye ni duplica el hecho confirmado |
| Oculta pestaña, navega o desmonta página               | Cerrar tramos y solicitar envío de pendientes una sola vez                                                                                                |
| Regresa mediante historial/bfcache                     | Reanudar con un tramo nuevo sin contar tiempo ausente ni repetir cierres                                                                                  |

Al completarse una interacción se incluye el trabajo anterior pendiente. En condiciones normales se inicia el envío en el siguiente turno de ejecución; eventos simultáneos se agrupan. Si hay una petición activa, se drena la cola al terminar. No se espera un ciclo fijo de diez segundos.

Si cambia una cotización por recarga del servidor mientras la página sigue abierta, solo reiniciar observadores cuando cambien realmente los elementos o la versión mostrada. Una recarga idéntica no equivale a otra visita.

### 4.3 Transporte y recuperación

- Reutilizar `POST /activity`, cookies de sesión, CSRF y `fetch` con `keepalive` para salida/ocultación. Mantener límites por número de eventos y tamaño de lote: máximo 50 eventos y objetivo máximo de 32 KiB serializados por petición.
- Una petición activa por instancia del transportador. Identificadores estables para reintentos y orden por página/pestaña. Un reintento nunca genera otro UUID.
- Mientras no hay confirmación, conservar el lote pendiente. Un 2xx posterior al commit confirma entrega; un timeout no demuestra que el servidor no guardó.
- Reintentar errores de red, 5xx y 429 con espera creciente acotada, variación aleatoria y `Retry-After` cuando corresponda. Los timers de recuperación no son una nueva cadencia de captura.
- Para 401/403, detener los eventos protegidos y no reenviarlos bajo otra cuenta. Para 419, suspender hasta recuperar sesión/CSRF sin recargar forzosamente el formulario. Los 422 se aíslan con diagnóstico acotado y sin bucle infinito; no descartar silenciosamente todo el historial válido.
- Mantener una cola en memoria durante la navegación SPA. No prometer recuperación después de matar el proceso del navegador o perder energía. Persistencia offline duradera queda fuera de esta primera entrega para evitar recuperar datos bajo otra identidad.
- Dar a cada contexto una identidad de propietario; limpiar o cerrar pendientes al cerrar sesión/cambiar de cuenta. No usar una variable global `allowed` que pueda habilitar eventos del contexto equivocado.
- Limitar memoria por eventos y bytes, con contador de descartes por saturación. No ocultar pérdida de cobertura ni guardar contenido sensible en diagnósticos.
- Si falla la telemetría, la cotización, mensajes y decisiones siguen funcionando. No mostrar errores de analítica como errores del negocio al cliente.

El despliegue debe conservar el límite actual de peticiones inicialmente. Si las mediciones muestran competencia entre visitantes detrás de la misma IP, diseñar un limitador específico de actividad por sesión de servidor con protección adicional por IP. No aumentar límites globales sin medir ni basarse en identificadores arbitrarios aportados por el navegador.

## 5. Contrato compatible y datos

Introducir **`schema_version: 2`** en eventos nuevos. Su ausencia sigue significando v1. No reinterpretar registros históricos ni editar el manifiesto/demo cargado.

Ejemplo orientativo del cierre de un tramo:

```json
{
    "event_id": "UUID",
    "schema_version": 2,
    "type": "ui.section_visibility_duration",
    "case_id": 123,
    "data": {
        "section": "prices",
        "version": 2,
        "duration_ms": 18000,
        "page_view_id": "UUID",
        "segment_id": "UUID",
        "sequence": 4,
        "end_reason": "section_exit"
    }
}
```

En el contrato final se enumeran campos y causas por tipo. Para duraciones v2, permitir hasta 30 minutos por segmento con cobertura explícita de segmentos largos; si se alcanza ese máximo, cerrar con causa `duration_limit` y abrir otro tramo. Ese límite es una salvaguarda excepcional, no el disparador habitual de envío. Mantener el máximo v1 de 30 s y compatibilidad con clientes ya abiertos.

Validar versión real del mismo caso, secciones permitidas, UUID, enteros y rangos. Campos de caso/cuenta/cliente y `source` se resuelven en Laravel; no se confían al cuerpo del navegador. Tipos comerciales como pago, aceptación o cierre siguen prohibidos en este receptor.

Las fechas autoritativas `occurred_at` e `ingested_at` se conservan asignadas por el servidor para esta primera versión. La duración monotónica, secuencia y causa describen el tramo; no reconstruir una hora histórica confiable a partir del reloj del cliente. Una recepción tardía nunca debe entrar retrospectivamente en una instantánea predictiva anterior a su ingesta.

La respuesta de éxito mantiene `204`. La inserción y sus efectos son transaccionales: duplicados con mismo UUID/carga se confirman sin otra fila ni revisión. Añadir prueba y tratamiento explícito para UUID repetido con carga diferente, sin sobrescribir el original ni aceptar modificaciones de cuenta/caso. No convertir el endpoint en una forma de consultar eventos de otros clientes.

Antes de activar v2, revisar todos los consumidores: etiquetas del historial, perfil comercial, snapshot de caso, informe de cambios, contrato semántico y predictor. Mostrar resumen de navegación acotado con referencias a los eventos, identificando los omitidos. No desplazar mensajes relevantes por una ráfaga de eventos de visibilidad.

## 6. Integración con evaluaciones y presupuesto

Persistir navegación y ejecutar IA son procesos distintos. El flujo conserva:

```text
Fin de interacción → receptor Laravel → transacción confirmada
                                        ├─ historial / evidencia
                                        └─ invalidación y job agrupado
                                             → reglas R1–R6
                                             → IA si hace falta y hay presupuesto
                                             → validación / revisión humana
```

1. Mantener invalidación inmediata y recuperación durable (`next_review_at`). No mostrar como vigente una recomendación de otra revisión.
2. Agrupar trabajo pendiente por caso/cliente con una espera breve propuesta de 1 s desde la primera llegada, sin posponerlo indefinidamente si continúan eventos. La evidencia ya está guardada; esta espera no retrasa su envío.
3. Probar una exclusión compartida por entidad durante el procesamiento; si llega actividad durante un trabajo, recuperar el último corte posteriormente. No habilitar dos llamadas facturables simultáneas para el mismo corte.
4. Mantener la invalidación de casos relacionados mientras compartan indicadores de cliente. Reducir ese alcance solo después de demostrar con pruebas qué dependencias se han separado.
5. Construir un resumen determinista de navegación para el contexto semántico con referencias/omisiones explícitas. No quitar `activity_context` de la clave de caché para simular ahorro, ni reutilizar interpretación cuando una nueva evidencia material lo invalida. Versionar el prompt si cambia su contrato.
6. El enriquecimiento del perfil conserva los cortes, los fallos con reintento explícito y el avance por lotes sin perder evidencia. No deducir escala, solvencia o intención a partir de exposición a precios.
7. Verificar cantidades de jobs/llamadas con proveedor simulado. Una ráfaga recibida antes de iniciar un job debe producir una evaluación del último contexto. Ráfagas posteriores pueden requerir otra: no prometer una llamada por sesión.
8. Conservar 22 MXN/24 h, 160 MXN/7 días, 200 llamadas/24 h, reservas inciertas, sin escalamiento y bloqueo demo. Ninguna prueba de este plan necesita llamadas reales.

### Protección del predictor

Las duraciones v1 y v2 tienen distinta forma de medición. Antes de habilitar su uso predictivo, separar los agregados por `schema_version`, incluir cobertura/faltantes y crear un registro de características nuevo para los cambios semánticos, por ejemplo `business-history-v2`.

Conservar la extracción v1 para reproducir sus experimentos históricos; no modificarla silenciosamente para leer nuevas duraciones. Un modelo de registro desconocido o incompatible debe quedar no disponible, sin caer por defecto a las características CSV. No evaluar datos v2 con un modelo v1 como si fueran equivalentes. El modelo nuevo se habilita únicamente tras el mismo proceso de validación temporal con historial real suficiente. No entrenar automáticamente ni utilizar el kit/demo para habilitar probabilidades.

## 7. Cotización: crear, publicar, compartir y consultar

### Recorrido que ya existe y se conserva

1. Comercio entra a `/requests` y usa **Preparar cotización**; la excepción presencial empieza en `/physical-request`.
2. Completa variantes, cantidades, precios, abastecimiento, impuestos, envío, descuento, condiciones, vigencia y referencia de autorización.
3. **Guardar borrador interno** conserva el trabajo sin mostrarlo al cliente.
4. El botón actual **Guardar versión** publica: `QuoteService::save` guarda una versión inmutable, registra `quote.published` y prepara una notificación con el enlace estable.
5. El caso aparece en **Mis solicitudes** del cliente; **Abrir conversación** abre la cotización publicada y su historial.
6. Comercio ya tiene **Copiar enlace** en el detalle. El mismo `/my/quotes/{public_id}` se usa en correo y dentro de la cuenta.
7. Con sesión autorizada, el enlace abre el espacio. Sin sesión debe iniciar sesión; verificar que el retorno conserva el destino incluso al verificar correo o cambiar contraseña inicial. Otra cuenta no debe obtener acceso.

Gmail SMTP continúa sujeto a su configuración y al worker. Publicación, notificación preparada, aceptación SMTP y lectura son estados diferentes. No afirmar «correo enviado» cuando solo se guardó o encoló. La cotización publicada debe seguir accesible aunque el correo esté deshabilitado o falle. En local, el enlace utiliza una dirección de aplicación alcanzable por el cliente; este plan no configura hosting.

### Ajustes pequeños de claridad incluidos

- Cambiar **Guardar versión** por **Publicar cotización** en la primera publicación y **Publicar nueva versión** en las siguientes; conservar el botón separado de borrador y las validaciones.
- Ajustar el mensaje de éxito a publicación y disponibilidad en la cuenta, sin afirmar entrega de correo. Reutilizar el enlace copiable existente; no crear otro token ni otro portal.
- Verificar el fallo de portapapeles, especialmente por HTTP en red local: ofrecer el enlace seleccionable para copia manual, sin bloquear el flujo.
- Mantener versiones anteriores, chat, archivos privados, aceptación parcial, retirada y vigencia. Aceptación, formalización, pago y cierre siguen siendo hechos distintos.

### Concurrencia entre navegación y edición comercial

Reproducir primero el conflicto actual: comercio abre el formulario, llega telemetría del comprador, comercio publica con la revisión que recibió al abrir. La revisión global también sirve para invalidar recomendaciones y no debe dejar de incrementarse para ocultar este conflicto.

Introducir un control de concurrencia específico de edición de cotización, calculado y firmado por el servidor sobre caso/cliente, estado comercial, última versión publicada, revisión de borrador y corte de eventos comerciales relevantes. Excluir únicamente cambios de navegación. Compararlo dentro de la transacción/bloqueo de publicación y borrador. Puede derivarse de registros existentes sin nueva columna.

Los formularios nuevos reciben ese token; los clientes antiguos que solo envían `expected_revision` siguen usando la comprobación conservadora actual. Un token inválido o una modificación comercial concurrente rechaza el guardado y conserva los datos introducidos. Nunca actualizar automáticamente la revisión esperada para forzar una publicación. Probar expresamente dos comerciales, aceptación/retirada/pago durante edición y navegación simultánea.

## 8. Etapas y condiciones para avanzar

| Etapa                                  | Trabajo y archivos principales                                                                                                                      | Condición de salida                                                                                                                    |
| -------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| 0. Base reproducible                   | Conservar diff actual; nueva rama si procede; fixtures y pruebas de caracterización en `tests/Feature/ByeCode`                                      | Base verde y evidencia de los fallos concretos, sin tocar Supabase real                                                                |
| 1. Contrato y receptor                 | `TelemetryController`, validador dedicado si simplifica; `routes/journey.php`; contrato v1/v2 y deduplicación                                       | Viejos eventos siguen entrando, nuevos campos se validan, no se fabrican hechos ni accesos                                             |
| 2. Núcleo de observación/transporte    | Extraer módulos pequeños bajo `resources/js/lib/activity/`; integrar `use-activity.ts`; pruebas con reloj y transporte controlados usando `vp test` | Transiciones y reintentos deterministas; sin envío periódico ni doble cierre; ninguna dependencia nueva si el ejecutor existente basta |
| 3. Cotización y catálogo               | `journey/quote.tsx`, `journey/catalog.tsx`; registro de secciones, cambios de versión, comparación, formularios y ciclo Inertia                     | Se envía al finalizar; cada evento pertenece a su caso/versión; catálogo y navegación SPA conservados                                  |
| 4. Publicación concurrente y acceso    | `QuoteService`, `DraftService`, `CommerceController`, `byecode/quote-form.tsx`; enlaces existentes                                                  | Navegación no impide publicar; ediciones comerciales reales sí detectan conflicto; cuenta y enlace conducen al mismo recurso           |
| 5. Consumidores y colas                | `CaseProjector`, `CustomerProfile`, `DecisionUpdates`, jobs, `EvaluationService`, etiquetas y predictor                                             | Evidencia v1/v2 legible, reglas y presupuesto intactos, sin duplicación de trabajos/costos ni inferencia engañosa                      |
| 6. Integración, navegador y liberación | Suite completa, frontend, pruebas reales de navegador aisladas; actualizar guía/estado                                                              | Matriz de aceptación aprobada, limitaciones documentadas y reversión probada                                                           |

Implementar por cambios revisables. Añadir una bandera de servidor para activar el productor v2; habilitarla solo tras completar las dependencias. Los dos productores no deben ejecutarse simultáneamente. Desplegar primero receptor/consumidores compatibles y después el productor. La bandera es operativa, no un control de consentimiento para el cliente.

## 9. Matriz de aceptación y regresión

| Grupo               | Casos mínimos a comprobar                                                                                             | Resultado exigido                                                                                             |
| ------------------- | --------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| Fin de interacción  | Precios 18 s → condiciones; salida tras 600 ms; interacción estable >30 s                                             | Duraciones correctas, tramo parcial conservado, envío sin esperar 10 s                                        |
| Pestaña/inactividad | Ocultar/volver, pausa de 60 s, cambio de ventana, historial/bfcache                                                   | Sin tiempo oculto añadido; cierre único con causa y reanudación limpia                                        |
| Versiones/DOM       | V1 → V2, cotización publicada mientras esperaba, recarga idéntica de Inertia, montaje/limpieza React                  | Secciones nuevas observadas, versiones correctas, sin visitas duplicadas por rerender                         |
| Visibilidad         | Tabla más alta que pantalla, móvil/escritorio, conversación con scroll interno, varias secciones visibles             | Cobertura definida; sin contar padre e hijos como total de sesión; métricas entendibles                       |
| Acciones            | Abrir/cerrar comparación, cancelar impresión, modificar/dejar igual cantidad, búsqueda/filtro                         | Apertura y cierre distintos; clic no se convierte en impresión/compra confirmada                              |
| Transporte          | Lote >50, >32 KiB, 500, offline/online, timeout tras commit, 429 con espera                                           | División y recuperación acotadas; UUID conservado; sin duplicados ni bucles                                   |
| Sesión              | 419, logout/login con otra cuenta, invitado → registro → primera solicitud                                            | No atribuir pendientes a otra identidad; mantener asociación legítima del catálogo y selección                |
| Receptor            | Versiones v1/v2, UUID repetido igual/distinto, caso ajeno, versión inexistente, `sale.payment`, contraseña como campo | Compatibilidad; rechazo transaccional de entradas inválidas; sin sobrescribir originales                      |
| Equipo              | Comercio/admin visitan cotización                                                                                     | No añadir su navegación al historial del comprador                                                            |
| Colas               | Ráfaga antes de ejecución, evento durante ejecución, varios workers, duplicado recibido                               | Último corte procesado, recuperación pendiente, un procesamiento simultáneo por entidad                       |
| Reglas/IA           | R1–R6, contexto obsoleto, perfil corregido, presupuesto agotado, falla incierta                                       | Restricciones y revisión humana preservadas; ninguna llamada real en pruebas                                  |
| Predictor           | Mezcla v1/v2, evento recibido después del corte, registro de modelo desconocido, demo                                 | Sin fuga temporal ni mezcla de métricas incompatible; probabilidades bloqueadas cuando corresponde            |
| Edición             | Dos comerciales, navegación durante edición, nueva aceptación/retirada/pago                                           | Navegación sola permite publicar; cambios comerciales se detectan y no se sobreescriben                       |
| Publicación         | Borrador, V1, V2, versión retirada/vencida, cantidades parciales                                                      | Borrador privado, historial inmutable, acciones limitadas a versión válida                                    |
| Enlace/cuenta       | Mis solicitudes, enlace con/sin sesión, cuenta incorrecta, verificación/contraseña inicial                            | Un mismo recurso privado y retorno correcto al destino autorizado                                             |
| Correo              | Deshabilitado, demo, SMTP simulado aceptado, timeout, publicación fallida                                             | Acceso web independiente; sin duplicar notificación por versión ni reenviar un estado incierto                |
| Carga funcional     | Tres clientes y tres pestañas de una cuenta; visitante con IP compartida, red lenta                                   | Sesiones y casos separados; 429 recuperable; formularios siguen utilizables; medir antes de afirmar capacidad |
| Datos existentes    | Suite demo completa, kit, evidencias viejas, correcciones humanas, adjuntos                                           | No alterar datos originales, ni reactivar correo/IA demo, ni romper permisos                                  |

Las pruebas de comportamiento frontend son necesarias: TypeScript y Pest no demuestran que IntersectionObserver, los relojes y el ciclo de React funcionen correctamente. Usar relojes simulados y puertos inyectables para el núcleo; completar con navegador real para geometría, scroll y salida de página.

Ejecutar la suite completa y build una vez al integrar, además de pruebas enfocadas por etapa. Las pruebas visuales se harán en una instancia aislada con SQLite y proveedores simulados, en escritorio y móvil. No abrir casos reales para producir telemetría como parte de una prueba sin controlar sus efectos en jobs y presupuesto.

## 10. Criterio de entrega

- [ ] Eventos enviados al completar acciones y transiciones, con pendientes anteriores incluidos y sin intervalo periódico de telemetría.
- [ ] Cada tramo tiene identidad, sección, versión, duración y causa; no se presenta como lectura cierta.
- [ ] Receptor compatible con datos y clientes v1; historial existente intacto.
- [ ] Reintentos, rate limit, cambios de cuenta y salidas comprobados; pérdida residual documentada.
- [ ] Actividad del cliente no provoca conflicto de edición comercial por sí sola; concurrencia real protegida.
- [ ] Publicación, enlace, acceso desde cuenta, chat, archivos y decisiones conservados.
- [ ] Dependencias de perfil, recomendaciones y predictor tratadas explícitamente; costos y demo protegidos.
- [ ] Pruebas backend y frontend, Pint, Larastan, TypeScript, lint y build aprobados; navegador y carga funcional documentados.
- [ ] `GUIA_USO.md` y `ESTADO_DESARROLLO.md` actualizados con resultados realmente ejecutados, sin atribuir calidad semántica a mocks.

No se puede garantizar ausencia absoluta de fallos ni entrega de un último evento tras cierre forzoso. La entrega exige evidencia de estas condiciones y degradación que mantenga utilizable el negocio.

## 11. Reversión sin perder trabajo

Si falla una condición de liberación, mantener desactivado el productor v2. Tras activar, se puede volver al productor anterior mediante la bandera; receptor y lectores compatibles permanecen desplegados para aceptar pestañas antiguas/nuevas y conservar eventos ya guardados.

La reversión de telemetría no elimina filas, no restaura copias antiguas de cotizaciones y no revierte la base de datos. Mantener soporte al token nuevo de edición para formularios abiertos aunque se reviertan cambios visuales. No usar `git reset --hard`, `migrate:fresh`, borrado de esquemas ni reejecución modificada del manifiesto demo.

En una instalación con varias computadoras, alinear primero backend/contrato y configuración antes de activar el productor nuevo. Las sesiones, el límite de peticiones y los locks de jobs deben usar la infraestructura compartida prevista; no usar memoria local de cada worker como exclusión global.

## 12. Referencias técnicas consultadas

- [MDN: medir visibilidad con Intersection Observer](https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API/Timing_element_visibility). Base para medir entradas/salidas; la visibilidad de pestaña se controla por separado. Los umbrales de este plan son una propuesta propia que debe probarse con las superficies de ByeCode.
- [MDN: Page Visibility API](https://developer.mozilla.org/en-US/docs/Web/API/Page_Visibility_API). Manejo de pestaña visible/oculta; no constituye evidencia de atención humana.
- [MDN: envío al finalizar sesión y keepalive](https://developer.mozilla.org/en-US/docs/Web/API/Navigator/sendBeacon). Priorizar ocultación; no depender de `unload`. Para conservar cabeceras CSRF y observar la respuesta se propone `fetch` con `keepalive`.
- [React: ciclo y limpieza de useEffect](https://react.dev/reference/react/useEffect). Referencia para limpiar observadores/listeners y probar montaje adicional en desarrollo sin eventos duplicados.

Las decisiones de contratos, límites, fases y pruebas anteriores son propuestas específicas para este repositorio, no garantías de las APIs del navegador.
