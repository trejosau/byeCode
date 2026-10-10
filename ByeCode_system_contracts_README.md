# ByeCode — contratos de información v1.0

Diseño propuesto que acompaña al apartado 7.22 de «Modelo de IA». Define fronteras y datos; no incluye un backend desplegado, conectores reales ni un modelo elegido. Los ejemplos son sintéticos y sus estados de validación son ilustrativos.

## Archivos

- `ByeCode_system.schema.json`: JSON Schema 2020-12, 13 contratos internos y 12 tipos de evento (6 del núcleo y 6 extensiones propias). Cada mensaje lleva `contract`, `schema_version: "1.0"` y `payload`.
- `ByeCode_system.examples.json`: 16 mensajes de un recorrido completo con recursos enlazados. Validar cada elemento de `messages` contra el esquema; el contenedor es un paquete de demostración, no una petición de API.
- `ByeCode_recommendation.schema.json`: contrato de recomendación ya existente, incorporado sin cambios dentro de `$defs.recommendation` del nuevo esquema. La salida oficial mantiene exactamente once campos.
- Los archivos `ByeCode_ranking.schema.json` y `ByeCode_ranking.example.json` siguen separados. La política de ranking es ilustrativa, no una política empresarial aprobada.

## Contratos por frontera

| Contrato | Productor → consumidor | Sentido |
|---|---|---|
| `event_ingress` | Adaptador autorizado → ingesta | Hecho comercial canónico, sujeto a validación de autoridad. |
| `event_record` | Ingesta → registro/proyector | Evento más metadatos de servidor y secuencia aplicada. |
| `domain_observation` | Fuente operativa → backend | Observación por recurso, todavía independiente de un caso. |
| `acceptance_record` | Captura verificada → estado comercial | Aceptación declarada/condicional por versión y línea. |
| `order_record` | Sistema autorizado → estado comercial | Formalización con requisitos cumplidos y evidencia. |
| `case_snapshot` | Constructor de contexto → evaluación | Instantánea inmutable con ambos cortes temporales. |
| `model_request` | Backend → adaptador LLM | Contexto seleccionado, tarea y alternativas preliminares. |
| `model_response` | LLM → validador | Señales, candidata y explicación; nunca se publica directamente. |
| `recommendation_record` | Publicador → interfaz/registro | Recomendación oficial dentro de un sobre de aplicación. |
| `evaluation_job` | Orquestador → trabajador/interfaz | Estado de evaluación, distinto del estado comercial. |
| `human_command` | Usuario autenticado → backend | Aprobación/rechazo o registro de una actuación real. |
| `feedback_record` | Backend → evaluación del producto | Decisión humana atribuida, sin declarar eficacia causal. |
| `operation_receipt` | Backend → solicitante | Acuse de persistencia o error; no resultado comercial. |

## Perfiles, nulos y límites

El perfil `event_ingress` admite los campos mínimos del núcleo y datos opcionales propios. La ingesta de producción debe exigir procedencia, atribución y comprobantes según el tipo/canal; aceptar el esquema mínimo no concede autoridad. `case.closed.data.status` es texto no vacío por compatibilidad: el adaptador debe mapearlo a un estado terminal permitido y rechazar estados ambiguos. `case.opened` usa `open` en este perfil propio.

`proposal_amount_mxn` admite el importe decimal de entrada cuando exista. Los contextos propios usan objetos monetarios MXN en centavos enteros; convertir sin redondeo silencioso. Monedas adicionales, cantidades fraccionarias, devoluciones monetarias negativas o acciones/canales nuevos necesitan un perfil explícito. Las prendas se cuentan en piezas enteras.

En parches, ausente significa conservar; `contact_not_before:null` elimina solo con autoridad; `contact_allowed` siempre booleano cuando está presente. En instantáneas, `null` significa desconocido o no aplicable según el campo y su explicación, nunca cero. Los arrays vacíos no afirman que un historial sea completo: la cobertura se declara por separado.

Las tallas conocidas sumadas a `size_pending_quantity` deben igualar `quoted_quantity`. Las cantidades de abastecimiento previstas se suman solo si pertenecen al mismo total y están completas. Aceptadas/formalizadas/canceladas representan estados relacionados, no cantidades que deban sumarse ciegamente. Validar cada registro contra la versión correspondiente, cantidades disponibles y autorizaciones.

`event_record.case_sequence` es la secuencia aplicada al caso, no la hora de ocurrencia. `received_at` e `ingested_at` los escribe el servidor. Un acuse `accepted` solo certifica persistencia. El contexto emplea hechos ocurridos antes de `evaluation_at` y conocidos antes de `knowledge_cutoff`. Campos de modelo, validación y publicación son registros del backend, no valores confiables que pueda declarar el cliente.

`model_request.context.quote` es un DTO tipado que comparte la forma de la cotización pero permite ocultar valores no necesarios usando nulos y referencias seudónimas. El backend conserva aparte el registro completo. La cobertura de evidencia decisiva debe seguir explícita. El ejemplo omite precios y atributos no necesarios para pedir tallas; esa omisión no autoriza emitir una oferta o promesa de suministro.

Las seis comprobaciones de reglas deben contener R1–R6 una sola vez cada una. `preliminary_allowed_actions` no garantiza autorización final: las señales semánticas nuevas pueden añadir un bloqueo. Un `result=block` puede bloquear ciertas alternativas sin invalidar una recomendación de espera; verificar el efecto por acción. El esquema no sustituye esas comprobaciones.

`publication_status=published` exige validaciones aprobadas y revisión resuelta: `requires_human_review=false` con `human_review.status=not_required`, o `requires_human_review=true` con aprobación y referencia de revisión. Las propuestas con revisión pendiente usan `awaiting_review`; una aprobación humana genera un nuevo estado/versionado auditable, sin borrar el indicador original. El backend comprueba autoridad de la revisión y vigencia del contexto. Ningún estado autoriza envío automático. `valid_until` es un límite máximo, nunca una garantía de vigencia si entran datos nuevos.

## Validaciones fuera del JSON Schema

1. Autenticación, autorización por dominio y aislamiento por empresa/caso.
2. Idempotencia, existencia y unicidad de IDs, correspondencia de versiones y vínculos externos.
3. Semántica temporal, cobertura del historial y revisión/frescura de cada dominio.
4. Fuentes de cada dato, exactitud de citas y sustento de explicaciones; no toda comprobación semántica es determinista.
5. R1–R6, nuevas restricciones semánticas, coherencia entre acción, texto y momento.
6. Unidades, cantidades, particiones de líneas, totales, reservas y autorizaciones.
7. Vigencia atómica antes de publicar; invalidación/reevaluación por cambios o vencimientos.
8. Registro de acciones solo con evidencia de ejecución; conciliación de resultados desconocidos.
9. Privacidad, retención, acceso a evidencia y protección de adjuntos.

Activar validación real de `format: date-time`; el esquema normaliza las fechas a UTC con `Z`. Rechazar claves extra y versiones desconocidas. Una futura v2 no debe reinterpretar los significados de v1.

## Recorrido de los ejemplos

`FLOW-E01` abre el caso, `FLOW-E02` registra restricciones, `FLOW-E03` publica la versión 3 y `FLOW-E04` recibe el mensaje de tallas pendientes. Los cuatro registros comparten empresa/caso; `QUOTE-SNAPSHOT-DEMO-03` se resuelve en `linked_resources`. La instantánea `SNAPSHOT-DEMO-01` crea `CTX-DEMO-01`; `EVAL-DEMO-01` produce la candidata y el sobre `REC-DEMO-01`. `CMD-DEMO-01` aprueba para consideración humana y `FB-DEMO-01` registra la aceptación de la recomendación. No se incluye `action.executed`, porque nadie ha enviado un contacto en este ejemplo.

Los perfiles de observación, aceptación y formalización se definen para las fronteras adicionales; no se simula que ocurrieron en este caso. Sus reglas de negocio siguen necesitando aprobación y pruebas de integración.
