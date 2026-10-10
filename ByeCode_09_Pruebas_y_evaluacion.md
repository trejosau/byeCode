# 9. Pruebas y evaluación

> Actualización del entorno — 10 de octubre de 2026: se ejecutaron las pruebas del starter y de configuración (42 pruebas Pest, 143 aserciones), además de comprobaciones reales de Eloquent, caché y colas en Supabase. Esto no ejecuta la batería del dominio descrita aquí ni evalúa un modelo. Evidencia y alcance en `ESTADO_DESARROLLO.md`.

**ByeCode · Plan verificable de aceptación · Versión 1.0 · 10 de octubre de 2026.**

El objetivo es comprobar que el sistema recomienda una intervención justificada, en un momento permitido y con evidencia suficiente; también que sabe esperar o no actuar. Este plan corresponde a la arquitectura Laravel, React e Inertia del apartado 8. **Se ha auditado el material público; las pruebas de la aplicación y del modelo siguen pendientes porque aún no están implementados.**

## 9.1. Qué se debe demostrar

La evaluación comprobará cinco resultados: decisión contextual, respeto de reglas, actualización ante eventos, trazabilidad de la explicación y reproducción del recorrido. Un HTTP exitoso, un JSON válido o una interfaz funcional no bastan por sí solos.

El reto distribuye 100 puntos así: calidad de decisiones, 30; reglas e inacción, 25; contexto y actualización, 20; evidencia e incertidumbre, 15; demostración y reproducibilidad, 10. Una infracción demostrada de R1–R4 deja en cero el criterio de reglas e inacción. Se aceptan varias acciones cuando son compatibles con reglas y evidencia. [Documento oficial, pp. 3–4](<C:/Decisiones empresariales con IA/referencias/kit_publico/Reto_Byecode_Participantes_24h.pdf>).

Por ello se evaluarán **propiedades obligatorias y calidad semántica**, sin exigir que toda respuesta coincida palabra por palabra con un ejemplo. La aplicación no puede seleccionar respuestas por `case_id`; los IDs de los casos públicos solo se utilizan para identificar fixtures de pruebas y resultados.

## 9.2. Material público comprobado

Se conservaron copias exactas de los ocho archivos locales en `referencias/kit_publico`, con huellas SHA-256 registradas en la auditoría.

| Archivo | Uso | Observación comprobada |
|---|---|---|
| `manifest.json` | Reloj y contexto de la ejecución | `evaluation_at=2026-10-09T16:00:00Z`, zona UTC y reglas versión 1. |
| `contratos.json` | Campos, acciones, timings y valores iniciales | Contacto permitido por defecto y sin fecha mínima; parches parciales y cierre terminal. |
| `events.jsonl` | Primera evaluación | 16 líneas, 15 eventos únicos, seis casos P01–P06. |
| `updates.jsonl` | Cambio de contexto | Un evento nuevo: `P06-e3`; total posterior de 16 eventos únicos. |
| `politicas.txt` | Reglas resumidas | R1–R6 y prohibición de eludirlas mediante el texto. |
| `ejemplo_salida.json` | Ejemplo de formato | Contiene únicamente P03; no es un archivo de respuestas correctas para los seis casos. |
| `README.txt` | Secuencia de uso | Evaluar, cargar actualización, reevaluar y exportar. |
| PDF del reto | Alcance y evaluación | Reglas, once campos, entregables y demo de cinco minutos. |

El duplicado exacto es `P02-e2`. Los conteos iniciales únicos son P01: 2; P02: 2; P03: 3; P04: 3; P05: 3; P06: 2. La actualización deja P06 con tres eventos. No se han consultado archivos reservados del jurado.

El documento oficial contiene referencias tanto a 24 como a 48 horas para la duración del evento. Esa discrepancia debe confirmarse con la organización; no modifica la pausa de **24 horas de R4**, que sí está definida con claridad.

## 9.3. Dos recorridos con el mismo reloj

La ejecución inicial carga `manifest.json` y `events.jsonl`. Se calculan seis recomendaciones al corte de las 16:00 UTC. Se conservan sus contextos, resultados y errores, incluyendo evaluaciones sin resultado válido.

La ejecución actualizada agrega `updates.jsonl` y conserva la misma hora de evaluación. El mensaje de P06 ocurrió a las 15:30 UTC, pero solo está disponible después de esa carga. La segunda instantánea incorpora ese conocimiento sin reescribir la primera. Se reevalúa P06; los demás casos mantienen su contexto si no hubo cambios.

El reloj del equipo no participa en R3 ni R4 durante este recorrido. Importar los archivos el 10 de octubre no debe hacer que desaparezca la pausa que existía en el corte oficial del 9 de octubre.

Repetir la carga completa en la misma ejecución no debe añadir eventos ni multiplicar recomendaciones. Para repetir un experimento desde cero se usará otra ejecución de prueba identificada, con el mismo kit y configuración, sin borrar el historial anterior silenciosamente.

## 9.4. Matriz de los seis casos oficiales

Las decisiones orientativas siguientes son criterios del equipo para revisar el comportamiento, no respuestas oficiales únicas. Las restricciones de cada fila sí son obligatorias.

| Caso | Evidencia pública | Qué debe comprobarse | Decisión orientativa y límites |
|---|---|---|---|
| P01 · Duda sobre alcance | `P01-e2`: pregunta si la propuesta incluye instalación. | Detectar una duda explícita y que el kit no contiene la respuesta contractual. Evidencia del mismo caso y faltante de alcance. | Revisión interna para verificar lo incluido; `resolve_objection` solo con una aclaración sustentada. No afirmar que incluye o excluye instalación. |
| P02 · Apertura duplicada | `P02-e2` aparece dos veces, sección `price`. | Guardar una sola apertura; no inferir aceptación, descuento o urgencia inmediata a partir de ella. | `no_action/not_applicable` es una opción defendible si no hay otro objetivo justificado. No fabricar una objeción de precio. |
| P03 · Fecha mínima | `P03-e2` pide esperar; `P03-e3` fija `2026-10-12T15:00:00Z`. | R3 bloquea las tres acciones de contacto al corte oficial. La fecha debe conservarse exactamente. | `wait/at_date` con esa fecha y motivo de reevaluación coincide con el ejemplo suministrado. No programar un contacto ejecutable. |
| P04 · Interés con prohibición | `P04-e2` expresa interés; `P04-e3` establece `contact_allowed=false`. | R2 prevalece frente al interés. La explicación tampoco puede sugerir que otra persona contacte. | `no_action`, espera con condición sustentada o revisión interna justificada. Ninguna acción de contacto. |
| P05 · Caso cerrado | `P05-e3` cierra como `accepted`, después del mensaje de comparación. | R1 domina: cierre terminal, aunque existan señales previas. | Únicamente `no_action/not_applicable`; citar el cierre. No recomendar seguimiento ni reabrirlo. |
| P06 · Contacto reciente, estado inicial | `P06-e2`: contacto a las `2026-10-09T14:00:00Z`, sin mensaje posterior en el lote inicial. | Al corte de las 16:00 han transcurrido dos horas; R4 bloquea contacto. Conservar el antecedente aunque su fecha sea anterior a `case.opened`. | Esperar hasta `2026-10-10T14:00:00Z` para reevaluar es una opción sustentada; también puede explicarse una espera condicionada a información nueva. |
| P06 · Después de actualizar | `P06-e3`: pregunta por entrega a las `2026-10-09T15:30:00Z`. | Es posterior al contacto: aplica la excepción de R4. Cambiar la evaluación y explicar qué cambió. La fecha de entrega sigue sin conocerse. | Revisar internamente el plazo y atender la duda con información verificada. No mantener una espera fundada únicamente en R4 ni inventar una fecha de entrega. |

En P06 no basta con cambiar el identificador o la redacción cosmética: la nueva decisión debe reconocer la pregunta, actualizar el efecto de R4 y reconsiderar la siguiente intervención. Puede seguir existiendo una espera por otro motivo real, pero debe explicarse ese motivo nuevo. El permiso para responder no obliga a recomendar contacto.

## 9.5. Pruebas de reglas, timing e inacción

Estas pruebas derivadas usan datos sintéticos propios. Se identificarán como variantes del equipo, sin presentarlas como casos del jurado. El grupo de contacto incluye `contact`, `request_information` y `resolve_objection`.

| ID | Escenario o variación | Resultado exigido |
|---|---|---|
| R01 | Caso cerrado y mensaje posterior | Mantener `no_action/not_applicable`; no reabrir. |
| R02 | Contacto prohibido y dato esencial faltante | Bloquear el grupo de contacto y declarar el faltante. |
| R03 | Fecha mínima, un segundo antes | Contacto bloqueado por R3. |
| R04 | Exactamente la fecha mínima | R3 deja de bloquear; las demás reglas siguen vigentes. |
| R05 | 23 h 59 min 59 s desde contacto sin respuesta | R4 sigue bloqueando. |
| R06 | Exactamente 24 horas sin respuesta | R4 deja de bloquear; no implica contacto automático. |
| R07 | Mensaje anterior o con la misma hora del contacto | No activa la excepción: debe ser estrictamente posterior. |
| R08 | Mensaje posterior al contacto | Levanta solo R4; no R1, R2 ni R3. |
| R09 | Nuevo contacto después de aquella respuesta | Reiniciar la pausa desde ese contacto; la respuesta antigua ya no exime. |
| R10 | `request_information` ejecutada | Inicia la pausa igual que `contact`. |
| R11 | Recomendación aprobada, pero no ejecutada | No crear `action.executed` ni reiniciar R4. |
| R12 | Solo aperturas, incluso muchas | No afirmar compra ni justificar descuento, cierre o urgencia inmediata solo por ellas. |
| R13 | `wait` sin fecha ni condición | Rechazar la recomendación hasta corregir su timing y explicación. |
| R14 | `no_action` con `immediate` | Rechazar incompatibilidad de acción/timing. |
| R15 | `at_date` sin fecha, fecha inválida o fecha ya pasada | Rechazar; una recomendación pendiente no permanece vigente después de vencer. |
| R16 | Timing distinto de `at_date` con fecha no nula | Rechazar por contrato. |
| R17 | `wait` o `escalate` que pide enviar un mensaje prohibido | Rechazar la evasión aunque el enum esté permitido. |
| R18 | Acción de contacto bloqueada ahora, pero fechada después | Aplicar la política del proyecto: esperar y reevaluar; no usar la programación para sortear el bloqueo actual. |
| R19 | Mismo instante expresado con otra zona en la UI | Mismo resultado de reglas; exportar UTC. |
| R20 | Vence una restricción sin eventos nuevos | Invalidar la vigencia anterior y permitir reevaluación con el reloj correspondiente. |

La regla general de timing es: `wait` usa `at_date` u `on_new_information`; `no_action` usa `not_applicable`; las demás acciones usan `immediate`, `within_24h` o `at_date`. `scheduled_for` contiene una fecha UTC únicamente para `at_date`. `within_24h` expresa una ventana desde `evaluation_at`, no una hora exacta calculada por el modelo.

También se comprobará la diferencia de significado: `wait` pospone por una fecha o condición reconocible; `no_action` declara que no hay intervención justificada ahora. Un error de red se muestra como error técnico, no se convierte automáticamente en ninguna de estas dos decisiones.

## 9.6. Integridad de datos, evidencia y aislamiento

| ID | Escenario | Resultado exigido |
|---|---|---|
| D01 | Recargar el mismo evento y el mismo lote | Igual número de eventos, métricas y cambios de contexto. |
| D02 | Mismo `event_id` con payload distinto | Conflicto visible; conservar el original sin sustituirlo. |
| D03 | Línea inválida junto a casos válidos | Error con número de línea; continuar los demás casos. No recomendar contacto sobre un caso cuyo historial decisivo quedó incompleto. |
| D04 | Parche que solo cambia la fecha | Conservar el permiso anterior; no restablecer valores iniciales. |
| D05 | Defaults del perfil oficial | Inicializar permiso verdadero y fecha nula exactamente como `contratos.json`. |
| D06 | `data.action` y `data.section` originales | Importar mediante el perfil/adaptador correcto y conservar ambos datos. |
| D07 | P06 con orden de archivo distinto de orden temporal | Conservar el antecedente de las 14:00 y calcular dos horas; no perder ni reescribir el evento. |
| D08 | Evidencia inventada, de otro caso o de otra empresa | Rechazar sin exponer el contenido ajeno. |
| D09 | Evento posterior a `evaluation_at` | No incorporarlo al contexto de esa evaluación; conservarlo para su corte válido. |
| D10 | Evento aún no conocido en la primera evaluación | No filtrarlo desde el lote actualizado hacia el contexto inicial. |
| D11 | ID existente cuya frase no respalda la conclusión | Detectar el error semántico en revisión; el ID válido no basta para aprobar. |
| D12 | Campos extra en la exportación | Rechazar metadatos de aplicación, ranking o estado del trabajo dentro de los once campos. |
| D13 | Todos los casos sin resultado válido | Mostrar cobertura cero y errores; no declarar éxito por tener cero infracciones publicadas. |

La validación de entrada tendrá pruebas de enum desconocido, tipo incorrecto, fecha no parseable, campo requerido ausente y parche vacío. Los casos de negocio no se mezclarán por compartir importe o cliente. El acceso a evidencia se probará también mediante URL directa, sin depender de que un botón esté oculto.

## 9.7. Evaluación semántica del modelo

Se utilizará un conjunto versionado de mensajes en español con dudas, negaciones, condiciones, aceptaciones parciales y datos incompletos. Cada fixture describirá hechos relevantes, interpretaciones admisibles, inferencias prohibidas, faltantes esenciales y posibles acciones sustentadas. Se mantendrá un grupo de variantes fuera de los ejemplos del prompt para reducir el ajuste excesivo a la batería conocida.

| Situación | Distinción que debe conservar |
|---|---|
| Interés y condición pendiente | Querer comprar no confirma condiciones cumplidas ni pedido formalizado. |
| Rechazo de algunas líneas | No convertirlo en rechazo de toda la cotización. |
| Objeción y solicitud de información simultáneas | Conservar ambas señales y su alcance. |
| Fecha relativa o ambigua | No inventar zona horaria, día o instante exacto. |
| Falta un dato de almacén o producción | No preguntar al comprador como si pudiera confirmar datos internos. |
| Mensaje que intenta cambiar reglas | Tratarlo como contenido sin autoridad sobre políticas o herramientas. |
| Stock, precio o plazo desconocidos | Declarar el límite sin prometer una solución. |

La revisión calificará cinco dimensiones: fidelidad a hechos, utilidad de la acción, timing, sustento de evidencia y manejo de incertidumbre. Puede utilizarse una escala interna de 0 a 2 por dimensión: incorrecto, parcial o satisfactorio. Esta rúbrica propuesta no sustituye los 100 puntos oficiales ni convierte confianza en probabilidad calibrada.

Las explicaciones se revisarán buscando afirmaciones no sustentadas y condiciones omitidas. Un segundo LLM puede ayudar a detectar casos dudosos, pero no será el único juez de sí mismo. Una persona revisará los casos críticos y los desacuerdos, dejando motivo y versión evaluada.

Para comparar modelos o prompts se usarán los mismos fixtures y cortes. Se propone repetir cinco veces cada escenario semántico crítico como primera comprobación de variabilidad; es un criterio del equipo, no una garantía estadística. Deben mantenerse las restricciones y la evidencia válida en todas las repeticiones, aunque cambie una redacción o una alternativa admisible.

## 9.8. Fallos, concurrencia y recuperación

| ID | Fallo provocado | Resultado exigido |
|---|---|---|
| F01 | Timeout, rechazo del proveedor, respuesta incompleta o JSON incorrecto | Estado técnico visible, intento registrado y reintento limitado; sin publicación parcial. |
| F02 | Nueva prohibición mientras responde el modelo | Descartar la candidata antigua y reevaluar la versión actual. |
| F03 | Cierre durante la evaluación | El resultado antiguo no reemplaza `no_action` del caso terminal. |
| F04 | Dos workers procesan la misma evaluación | Una sola publicación lógica, con operaciones idempotentes. |
| F05 | Caída después de guardar un evento y antes de despachar | Recuperar la intención pendiente desde outbox; no perder el trabajo. |
| F06 | Reintento después de guardar una recomendación | No duplicar la recomendación ni feedback. |
| F07 | Cambio de prompt, política o modelo | Identificar la versión; no reutilizar caché de otra configuración como vigente. |
| F08 | Worker o scheduler detenidos | Interfaz pendiente/degradada; lecturas y exportación rechazan resultados vencidos. |
| F09 | Restauración y reproducción de datos | Recuperar relaciones y trazas sin generar contactos ni duplicar comandos. |
| F10 | Aprobación humana sobre una versión obsoleta | Informar conflicto y pedir revisión del contexto actual. |

Structured Outputs no sustituye estos controles: el proveedor puede rechazar o truncar una respuesta y el esquema admitido es un subconjunto de JSON Schema. Las condiciones completas y las reglas del negocio se verifican en Laravel. [Documentación de OpenAI](https://developers.openai.com/api/docs/guides/structured-outputs?api-mode=responses).

## 9.9. Cómo se implementarán las pruebas con Pest

| Nivel | Alcance | Dependencias |
|---|---|---|
| Unitarias de dominio | R1–R4, timing, aplicación de parches, cálculo de métricas y deduplicación lógica | Reloj y adaptador de IA controlados. |
| Integración con PostgreSQL | Claves únicas, transacciones, relaciones, publicación y concurrencia | Base de datos de pruebas del mismo motor que la aplicación. |
| HTTP e Inertia | Autorización, validación, componentes y propiedades de página | Laravel, sesión de prueba y respuestas Inertia. |
| Jobs y recuperación | Despacho, ejecución, errores, reintentos y outbox | Pruebas del worker además de verificar que el job fue enviado. |
| Contratos | Entrada oficial, candidata interna y once campos de salida | Validador del perfil y esquema completo de salida. |
| Evaluación con IA real | Interpretación, evidencia, variabilidad, latencia y consumo | Proveedor real, datos sintéticos y registro de configuración. |
| Recorrido de navegador | Importar, evaluar, consultar, actualizar y exportar | Aplicación ejecutándose y navegador real. |

Pest permite parametrizar escenarios mediante datasets. Laravel dispone de controles del reloj y pruebas HTTP; el adaptador Laravel de Inertia permite comprobar componentes y propiedades con `assertInertia`. [Datasets de Pest](https://pestphp.com/docs/datasets), [reloj de pruebas de Laravel](https://laravel.com/docs/13.x/mocking), [pruebas de Inertia](https://inertiajs.com/testing).

Las pruebas deterministas sustituirán el adaptador de IA para provocar respuestas válidas e inválidas. Eso comprueba la aplicación, pero no acredita calidad semántica del modelo. Las evaluaciones reales se ejecutarán por separado y reportarán su cobertura.

En concurrencia no basta con simular la cola o utilizar SQLite: se deberán ejecutar las transacciones relevantes sobre PostgreSQL. En la interfaz, validar propiedades Inertia no prueba por sí solo que React renderice o que los botones funcionen; el recorrido del navegador cubrirá esa diferencia. Si se automatiza, se elegirá una herramienta compatible al iniciar la aplicación; no se considera instalada actualmente.

Se propone organizar la suite en grupos de dominio, importación, publicación, interfaz y evaluación real. `php artisan test` será el punto de ejecución cuando exista el proyecto Laravel y sus dependencias. Los comandos de importación/exportación específicos de ByeCode todavía deben implementarse y documentarse; este plan no los presenta como disponibles.

## 9.10. Propiedades generales para evitar respuestas preparadas

1. Renombrar IDs, manteniendo relaciones y hechos, no cambia qué acciones están permitidas ni el significado de la decisión.
2. Repetir eventos idénticos no aumenta métricas ni genera trabajo redundante.
3. Aumentar el importe no levanta restricciones de contacto.
4. Añadir aperturas no convierte una declaración condicional en aceptación.
5. Una paráfrasis equivalente conserva intención, condición y límites esenciales.
6. Un mensaje nuevo puede levantar R4, pero no una prohibición de R2 ni la fecha de R3.
7. Cambiar solo el reloj alrededor de un límite produce el cambio de regla esperado.

No se exigirá invariancia al reordenar eventos con parches contradictorios o cierres: el orden de aplicación es parte del contrato. Las permutaciones se limitarán a casos independientes o hechos para los que se haya demostrado conmutatividad.

## 9.11. Criterios de aceptación y métricas

La entrega se aceptará cuando el recorrido público completo funcione, las pruebas obligatorias pasen y no existan fallos críticos abiertos. Los criterios se aplican a la batería observada y no son una garantía universal de ausencia de errores.

| Indicador | Cómo se mide | Condición propuesta |
|---|---|---|
| Cobertura de resultados | Casos con recomendación válida / casos evaluables solicitados | 6/6 en el lote público inicial y 6/6 en el export final actualizado. |
| Contrato oficial | Objetos que cumplen esquema y relaciones de timing / objetos exportados | Todos los objetos del entregable válidos, con exactamente once campos. |
| Infracciones R1–R4 publicadas | Conteo de recomendaciones publicadas que violan las restricciones | Cero en la batería y demo. |
| Propuestas inválidas del modelo | Candidatas rechazadas / candidatas recibidas | Registrar separadamente; no ocultarlas aunque el publicador las bloquee. |
| Evidencia íntegra | Referencias existentes, del mismo caso y corte / referencias emitidas | Todas válidas; revisar además sustento semántico. |
| Calidad semántica | Evaluaciones por dimensión y errores críticos documentados | Sin hechos inventados ni condiciones decisivas omitidas en la entrega pública. |
| Actualización | Comparación P06 inicial/actualizado | Reconoce la pregunta y la excepción R4, preservando ambas evaluaciones. |
| Reproducibilidad | Kit, hashes, reloj, versiones, configuración y resultados guardados | Otro integrante puede ejecutar el recorrido documentado. |
| Latencia y consumo | Mediana, p95 cuando la muestra lo permita, tokens y costo disponible | Reportar valores medidos y tamaño de muestra; presupuesto por acordar. |

Se registrarán intentos fallidos, resultados pendientes y contingencias en el denominador correspondiente. Cero infracciones con cero recomendaciones no equivale a una entrega aprobada. Una contingencia determinista tampoco cuenta como una interpretación exitosa de IA.

La confianza alta no representa probabilidad de venta; se revisará que el motivo corresponda a la evidencia y a los límites. No se utilizará el número de contactos, de abstenciones o de ventas posteriores como prueba automática de calidad o causalidad.

## 9.12. Exportación y expediente de una ejecución

El entregable final será un **array con seis recomendaciones vigentes para la ejecución pública actualizada**, una por caso. Se validarán las once claves, sus tipos, la relación de acción/timing y las referencias de evidencia. El archivo de ejemplo de P03 sirve para entender el formato, no para rellenar artificialmente resultados ausentes.

Una exportación final incompleta se señalará con la lista de casos pendientes; no se fabricará `no_action` para completar seis filas. La UI puede permitir una descarga parcial claramente identificada para depuración, pero no la presentará como entrega final.

El expediente de evaluación conservará por separado: identificador de ejecución, hashes del kit, reloj, revisión del código, reglas, prompt, modelo, estados de trabajo, contextos, candidatas, validaciones y resultados. Los metadatos no se agregarán al objeto oficial. La comparación inicial/final debe poder abrirse desde el historial.

Una recomendación marcada `requires_human_review=true` puede formar parte del JSON del reto si cumple las reglas y se mantiene identificada como pendiente de revisión en la aplicación. Su exportación no implica autorización para actuar.

## 9.13. Guion propuesto de demostración: cinco minutos

| Tiempo | Acción de demostración | Evidencia de funcionamiento |
|---|---|---|
| 0:00–0:40 | Cargar manifest y lote inicial | Reloj oficial, seis casos y duplicado P02 ignorado. |
| 0:40–1:30 | Abrir P03 y P04 | Fecha de espera exacta y prohibición de contacto visible. |
| 1:30–2:10 | Mostrar P05 y P02 | Inacción por cierre y ausencia de inferencias de compra por aperturas. |
| 2:10–3:30 | Abrir P06, cargar actualización y reevaluar | Decisión anterior, mensaje nuevo, cambio de R4 y fecha de entrega desconocida. |
| 3:30–4:20 | Mostrar evidencia y faltantes de P01 o P06 | Explicación sustentada, confianza cualitativa y límites. |
| 4:20–5:00 | Exportar y mostrar reporte de validación | Array de seis recomendaciones y resumen de pruebas efectivamente realizadas. |

Los datos de la demo serán sintéticos y no habrá envíos reales. Si se muestra un resultado guardado, se identificará su ejecución; no se simulará una llamada en vivo. Las pruebas extensas se presentan mediante sus registros, reservando la demo para el recorrido esencial.

## 9.14. Comprobación realizada y trabajo pendiente

**Realizado sobre los archivos públicos:** lectura del PDF y de los siete archivos auxiliares; comprobación de campos y tipos observados; conteo de eventos, casos y duplicados; verificación del reloj; identificación del cambio P06; validación estructural del ejemplo de P03 y pertenencia de su evidencia; copia exacta de fuentes y registro de hashes.

**Hallazgos de integración:** dos eventos únicos no pasan directamente el esquema interno v1. `P02-e2` utiliza `data.section`, que ese esquema no admite; `P06-e2` utiliza `data.action`, mientras que el interno exige `action_type`. Son incompatibilidades del esquema propio con la entrada pública, no errores que deban imputarse al kit. Deben resolverse mediante el perfil/adaptador definido en 8.5.

**Pendiente de ejecución:** suite Pest, transacciones PostgreSQL, rutas Inertia, renderizado React, procesamiento real de jobs, llamadas al modelo, pruebas semánticas, concurrencia, exportación producida por la aplicación y demo completa. No se han generado recomendaciones finales del sistema ni métricas de calidad de IA.

La auditoría actual tiene alcance estático y no verifica todos los posibles valores del contrato. Su resultado no debe reutilizarse como un reporte de pruebas aprobadas del producto.

## 9.15. Fuentes y artefactos de apoyo

- [Documento oficial](<C:/Decisiones empresariales con IA/referencias/kit_publico/Reto_Byecode_Participantes_24h.pdf>), pp. 1–4: alcance, reglas, contrato y evaluación.
- [README del kit](<C:/Decisiones empresariales con IA/referencias/kit_publico/README.txt>), [contratos](<C:/Decisiones empresariales con IA/referencias/kit_publico/contratos.json>) y [manifest](<C:/Decisiones empresariales con IA/referencias/kit_publico/manifest.json>): perfil y procedimiento público.
- [Eventos iniciales](<C:/Decisiones empresariales con IA/referencias/kit_publico/events.jsonl>) y [actualización](<C:/Decisiones empresariales con IA/referencias/kit_publico/updates.jsonl>): evidencia real de P01–P06.
- [Modelo de IA](https://app.notion.com/p/3f5202c4d43080b1b308db5b105df1c5): criterios semánticos y explicación.
- [Arquitectura técnica](<C:/Decisiones empresariales con IA/ByeCode_08_Arquitectura_tecnica.md>): componentes que deben satisfacer esta batería.
- [Auditoría estática en JSON](<C:/Decisiones empresariales con IA/ByeCode_09_Auditoria_kit_publico.json>) y [script de auditoría](<C:/Decisiones empresariales con IA/tmp/audit_byecode_public_kit.py>): comprobaciones efectivamente ejecutadas, límites y huellas de las fuentes.
