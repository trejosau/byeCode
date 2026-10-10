# Plan de implementación: historial comercial de demostración de ByeCode

Fecha: 10 de octubre de 2026. Estado: **implementado, probado y cargado en Supabase**.

## 1. Resultado que debe entregar

Dejar ByeCode con nueve meses de operación ficticia, cuentas de clientes utilizables y oportunidades que puedan continuarse mediante las pantallas normales. El equipo existente —Jorge, Dani, Jesús y Sau— atenderá estos casos. Alberto conserva su cuenta y permisos de administrador.

Los datos se persistirán en PostgreSQL mediante los servicios y validaciones del negocio. No habrá contadores inventados en React, cotizaciones representadas únicamente con JSON de pantalla ni estados finales desconectados de sus eventos. Los escenarios tendrán datos iniciales definidos y reproducibles; los importes, relaciones, versiones, revisiones y proyecciones se calcularán con la lógica de la aplicación.

El plan original se conserva como especificación. La implementación integra los cambios locales del chat [Sube los cambios con commits](codex://threads/01a1270a-9bfc-72d2-bcd8-fb1f372e3af9): decisiones por evento, perfil en cola, ranking validado, comparación entre recomendaciones y actualización de pantallas, sin sobrescribirlos. La guía ejecutable, las cuentas y los siguientes pasos están en `GUIA_DEMO.md`; la evidencia de pruebas y de Supabase está en `ESTADO_DESARROLLO.md`. Para reducir la latencia remota, los servicios se reproducen primero en SQLite temporal privada con IDs reservados, y se transfieren con auditoría y transacción a PostgreSQL.

### Alcance inicial cerrado

| Elemento                           | Cantidad o alcance                                                                                    |
| ---------------------------------- | ----------------------------------------------------------------------------------------------------- |
| Cuentas nuevas de cliente          | 12; todas con rol `customer`                                                                          |
| Clientes con ficha CRM inicial     | 10, creadas al reproducir su primera solicitud                                                        |
| Cuentas sin CRM ni solicitudes     | 2, para probar el recorrido completo desde cero                                                       |
| Historial                          | Nueve meses anteriores a la fecha de referencia de la carga                                           |
| Casos                              | 34: 22 históricos cerrados y 12 abiertos                                                              |
| Cierres históricos                 | 16 aceptados, 4 rechazados y 2 cancelados                                                             |
| Solicitudes                        | 34, una por cada caso inicial                                                                         |
| Versiones publicadas de cotización | 46: 32 históricas y 14 en los casos abiertos                                                          |
| Borradores internos pendientes     | 2, independientes de las versiones publicadas                                                         |
| Formalizaciones                    | 17: 16 históricas y una en curso                                                                      |
| Pagos                              | 27: 26 históricos y un anticipo en curso                                                              |
| Registros comerciales              | 50: 17 formalizaciones + 27 pagos + 4 rechazos + 2 cancelaciones                                      |
| Catálogo                           | 6 fichas y 36 variantes publicadas                                                                    |
| Conversaciones                     | Entre 110 y 150 mensajes útiles, determinados por los guiones; sin rellenar cuotas artificialmente    |
| Adjuntos privados                  | 6 archivos de ejemplo válidos, ligados a mensajes concretos                                           |
| Perfil                             | Siete dimensiones disponibles por cliente, con confirmaciones, desconocidos y cambios sustentados     |
| Recomendaciones                    | Calculadas en los puntos reproducibles por el motor real de reglas; sin inventar resultados de OpenAI |

La fecha de referencia se captura una vez y se conserva en el registro de ejecución. Las cuentas nuevas y la actividad que introduzca el usuario después no están incluidas en estos totales iniciales.

## 2. Qué cambia respecto de un seeder de usuarios

La revisión del código identifica trabajo necesario antes de insertar el historial:

| Situación actual comprobada                                                                                                                        | Decisión de implementación                                                                                                                                           |
| -------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `CustomerJourney::submit` crea CRM, solicitud y apertura; `QuoteService::save` publica versiones                                                   | Reutilizar esos flujos para que todas las entidades queden enlazadas                                                                                                 |
| Mensajes, decisiones, resultados, retiros y borradores tienen lógica dentro de controladores                                                       | Extraer servicios compartidos y reglas de validación reutilizables, manteniendo los permisos HTTP; no duplicar esa lógica dentro del seeder                          |
| El envío comercial de `ConversationController` comprueba `contact_prohibited`, que en `RuleEngine` representa R2; R3/R4 están en el mapa `blocked` | Corregir la guarda compartida para consultar todos los bloqueos de la acción `contact`; probar el rechazo HTTP de R3 y R4, no solo su exclusión visual en el ranking |
| `ImportService`, `CustomerJourney` y `CustomerProfile` solicitan evaluaciones y jobs                                                               | Añadir un contexto de carga que agrupe el trabajo; evaluar después de tener una operación completa                                                                   |
| Los jobs trabajan en procesos diferentes                                                                                                           | Persistir la procedencia de demostración y comprobarla también en el worker; una variable temporal del seeder no basta                                               |
| Publicar una cotización inserta una notificación pendiente                                                                                         | Suprimir los envíos para destinatarios demo tanto al encolar como al despachar                                                                                       |
| `BusinessHistory::train` considera real todo caso con conjunto `live`                                                                              | Excluir los datos demo de filas de entrenamiento y de todos los agregados usados como características                                                                |
| Un caso necesita modo operativo `live` para poder seguir cotizándose                                                                               | Conservar ese modo de trabajo y distinguir la procedencia en campos propios; no convertir estos casos en un kit de solo reproducción                                 |
| Las confirmaciones de perfil almacenan evidencia, pero no tienen un campo dedicado al autor de la revisión                                         | Incorporar autor nullable y procedencia al registrar nuevas confirmaciones; preservar los registros anteriores sin atribuirles un autor inventado                    |

Fuentes locales principales: `ESPECIFICACION_DECISIONES_POR_EVENTO.md`, `GUIA_USO.md`, `ESTADO_DESARROLLO.md`, `CustomerJourney`, `QuoteService`, `ConversationController`, `CustomerProfile`, `DecisionUpdates`, `EvaluationService`, `ActionRanking` y `BusinessHistory`.

## 3. Cuentas y perfiles de cliente

Se usarán identidades ficticias reproducibles. Cada correo se forma con el primer nombre, en minúsculas y sin acentos, seguido de `@gmail.com`, conforme a lo solicitado. El apellido, empresa y narrativa se conservan entre ejecuciones.

| Cliente         | Correo de acceso  | Negocio ficticio                            | Históricos: aceptados / rechazados / cancelados | Abiertos  | Responsable inicial |
| --------------- | ----------------- | ------------------------------------------- | ----------------------------------------------- | --------- | ------------------- |
| Marina Salazar  | marina@gmail.com  | Lumbre Estudio; marca propia                | 2 / 1 / 0                                       | A01 y A02 | Jorge               |
| Diego Montes    | diego@gmail.com   | Ruta Básica; reventa                        | 3 / 0 / 0                                       | A03       | Dani                |
| Valeria Ríos    | valeria@gmail.com | Aula Norte; uniformes                       | 1 / 0 / 1                                       | A04       | Jesús               |
| Rodrigo Vega    | rodrigo@gmail.com | Foro Naranja; eventos                       | 0 / 1 / 0                                       | A05       | Sau                 |
| Camila Duarte   | camila@gmail.com  | Tinta Clara; marca propia                   | 1 / 1 / 0                                       | A06       | Jorge               |
| Andrés Molina   | andres@gmail.com  | Suministros Prado; distribución             | 4 / 0 / 0                                       | A07 y A08 | Dani / Jesús        |
| Paula Serrano   | paula@gmail.com   | Casa Laurel; uniformes                      | 1 / 0 / 1                                       | A09       | Sau                 |
| Ricardo Paredes | ricardo@gmail.com | Taller Vértice; personalización de prendas  | 2 / 0 / 0                                       | A10       | Jorge               |
| Elena Campos    | elena@gmail.com   | Colectivo Sur; eventos                      | 1 / 0 / 0                                       | A11       | Dani                |
| Mateo Navarro   | mateo@gmail.com   | Punto Nube; reventa                         | 1 / 1 / 0                                       | A12       | Jesús               |
| Daniela Fuentes | daniela@gmail.com | Por declarar al enviar la primera solicitud | 0 / 0 / 0                                       | Ninguno   | Sin asignar         |
| Nicolás Torres  | nicolas@gmail.com | Por declarar al enviar la primera solicitud | 0 / 0 / 0                                       | Ninguno   | Sin asignar         |

### Comportamiento del acceso

- La contraseña común será la indicada por el usuario, leída de `DEMO_CUSTOMER_PASSWORD` en `.env`, y se guardará mediante el hash del modelo `User`. No se escribirá en fixtures, documentación versionada, informes ni props de Inertia.
- Las doce cuentas demo tendrán correo verificado, sin caducidad de contraseña y sin cambio obligatorio inicial, para poder entrar y probar directamente. Esta configuración pertenece exclusivamente a la carga demo; no cambia el registro normal ni el alta presencial.
- El seeder no restablece contraseñas de cuentas existentes. Una colisión con un correo ajeno a la ejecución cancela la carga antes de escribir; no adopta esa cuenta ni cambia su rol.
- No crea otros administradores o comerciales ni vuelve a ejecutar sus seeders. Resuelve las cuatro cuentas de comercio por los correos existentes y exige su rol correcto.
- Daniela y Nicolás serán únicamente cuentas al terminar la carga. Su primera solicitud deberá crear el CRM; la segunda reutilizará la misma ficha.
- Un cliente consulta exclusivamente sus solicitudes, cotizaciones, decisiones, conversación y archivos. Los cuatro comerciales conservan el espacio operativo compartido; la asignación no restringe artificialmente esa colaboración. Alberto consulta en modo lectura.

### Contenido comercial de las diez fichas

Los siguientes valores serán respaldados por declaraciones explícitas de los compradores dentro de su historial. La tabla es el guion de contenido, no una inferencia basada en nombres o montos.

| Cliente | Escala / recurrencia / proceso                     | Prioridades y requisitos                               | Evolución que debe poder revisarse                                                                          |
| ------- | -------------------------------------------------- | ------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------- |
| Marina  | Individual; por temporada; decisión directa        | Calidad, flexibilidad; muestras                        | Cambio documentado de fit y cantidades; oportunidad de segunda colección condicionada a aprobar una muestra |
| Diego   | Equipo en una sede; reposición recurrente; directa | Precio, continuidad; empaque particular                | Historial de reposiciones y pagos; una preferencia antigua de empaque sustituida por una confirmación nueva |
| Valeria | Varias sedes; campañas; varias aprobaciones        | Fecha, calidad; orden de compra y varios destinos      | Cambio de aprobador y espera explícita; presupuesto no equivale a autorización                              |
| Rodrigo | Equipo en una sede; compra puntual; directa        | Fecha, personalización                                 | Cantidades conocidas y tallas todavía pendientes; sin rellenar solvencia o recurrencia                      |
| Camila  | Individual; temporada; directa                     | Calidad, personalización; muestras                     | Hipótesis histórica de fit corregida por comercio con evidencia del cliente                                 |
| Andrés  | Varias sedes; abastecimiento; área de compras      | Continuidad, precio; calendario y órdenes de compra    | Cambio confirmado de calendario; dos oportunidades simultáneas con restricciones distintas                  |
| Paula   | Equipo en una sede; temporada; directa             | Calidad; requisitos particulares aún desconocidos      | Declaración retirada por falta de vigencia; el dato queda nulo con historia, no desaparece                  |
| Ricardo | Equipo en una sede; recurrente; directa            | Calidad, precio; trazabilidad                          | Necesidad específica de tolerancia de color; contacto reciente sin respuesta                                |
| Elena   | Individual; puntual; varias aprobaciones           | Fecha, flexibilidad; condiciones de entrega pendientes | Preferencia de pago sin convertirla en crédito concedido; disponibilidad todavía no confirmada              |
| Mateo   | Equipo en una sede; recurrente; directa            | Calidad, continuidad; empaque                          | Respuesta posterior a un contacto; actualización de una necesidad de la siguiente reposición                |

Se conservarán propiedades particulares con alcance de pedido o colección: técnica de estampado prevista, etiqueta, tolerancia de color, empaque y fechas solicitadas. No se elevarán automáticamente al alcance `cliente`. La oportunidad de desarrollo será texto condicionado y sustentado, nunca una compra o ingreso asegurado.

## 4. Catálogo que permite cotizar de verdad

Se crearán seis fichas textiles: playera regular, playera oversize, playera pesada, manga larga, sudadera sin capucha y sudadera con capucha. Cada ficha tendrá dos colores y tres tallas, con un fit definido: 36 combinaciones distintas.

Cada ficha incluirá nombre, categoría, descripción, tejido, construcción, cuidados, guía de fit y tabla textual de medidas. Cada variante tendrá SKU exclusivo con prefijo `DEMO-`, composición, gramaje, color, talla, medidas, tolerancia, MOQ, disponibilidad observada y fuente de esa observación. Los valores serán consistentes entre ficha, solicitud y versión de cotización.

- Habrá variantes con stock observado suficiente, stock cero para fabricación y abastecimiento mixto. `stock` significa existencia observada; el proyecto no implementa una reserva o kardex y el seeder no afirmará que existe.
- Una variante tendrá disponibilidad desconocida y un caso incluirá cantidades inferiores al MOQ para revisar la excepción.
- Los precios serán internos y por línea de cotización. El catálogo público seguirá sin precios, autorizaciones o información de clientes.
- Las observaciones de stock se reproducirán en orden temporal. Una cotización antigua no incluirá una verificación de stock del futuro; sus snapshots no se reescriben al actualizar el catálogo.
- No se usarán fotografías de terceros o URLs rotas para aparentar completitud. Si no se incorporan recursos propios válidos, `photos` estará vacío y se usará el estado sin foto que admite la aplicación.
- La carga no cambiará fichas o SKUs existentes. Un conflicto de SKU o combinación se reportará en la validación previa.

## 5. Casos abiertos listos para continuar

`T` es la fecha y hora de referencia persistida de la primera carga. Las vigencias se almacenan en UTC; las fechas comerciales se interpretan con `America/Mexico_City`. Los identificadores A01–A12 son claves estables del escenario, no IDs numéricos de base de datos.

| Caso          | Estado inicial y versiones publicadas     | Evidencia y situación                                                                     | Siguiente prueba desde la interfaz                                                               |
| ------------- | ----------------------------------------- | ----------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ |
| A01 · Marina  | V2 vigente; 2 versiones                   | Aceptación parcial con cantidades por línea y condición de muestra; sin formalización     | Negociar pendientes, publicar V3 si corresponde y formalizar solo lo confirmado                  |
| A02 · Marina  | Solicitud recibida; 0 versiones           | Reposición nueva con variantes y cantidades; sin importe                                  | Asignar responsable, preparar cotización nueva y observar el mismo CRM reutilizado               |
| A03 · Diego   | V1 vigente; 1 versión                     | Aceptación, formalización y anticipo del 30%; caso todavía abierto                        | Registrar el saldo del 70% y cerrar sin volver a contabilizar el anticipo                        |
| A04 · Valeria | V2 vigente; 2 versiones                   | Aprobación pendiente y `contact_not_before = T + 48 h`, con evento y mensaje sustentantes | Consultar espera/exclusiones R3 y el efecto de un mensaje nuevo sin levantar R3                  |
| A05 · Rodrigo | Borrador interno; 0 versiones             | Cantidades bajo MOQ, tallas o información incompleta por confirmar                        | Completar el borrador; comprobar que el cliente todavía no ve una cotización publicada           |
| A06 · Camila  | V2 vigente; 2 versiones                   | Cliente solicita cambiar color y cantidades; V1 permanece como historia                   | Publicar V3 y comparar versiones; rechazar decisiones enviadas sobre V1/V2 sustituidas           |
| A07 · Andrés  | V1 vigente; 1 versión                     | Contacto expresamente prohibido, representado mediante `constraint.updated` y evidencia   | Comprobar R2, exclusiones y rechazo de mensajes comerciales; vistas nuevas no autorizan contacto |
| A08 · Andrés  | V1 retirada y borrador interno; 1 versión | Retiro motivado por dato de disponibilidad corregido                                      | Publicar V2 desde el borrador; V1 sigue visible como retirada y no acepta decisiones             |
| A09 · Paula   | V1 vigente; 1 versión                     | Solo consulta/vistas en el caso actual; solicitud sin mensaje y sin decisión              | Ver R5; introducir después el primer mensaje explícito del comprador                             |
| A10 · Ricardo | V1 vigente; 1 versión                     | Contacto comercial a `T - 6 h`, sin mensaje posterior                                     | Ver pausa R4 hasta `T + 18 h`; comprobar rechazo de un nuevo contacto durante la pausa           |
| A11 · Elena   | V1 vencida a `T - 1 día`; 1 versión       | Disponibilidad/fecha de fabricación sin confirmar                                         | Revisar internamente, actualizar evidencia y publicar V2 antes de intentar aceptación            |
| A12 · Mateo   | V2 vigente; 2 versiones                   | Contacto a `T - 8 h`, respuesta del comprador a `T - 2 h`                                 | Comprobar que el mensaje levanta R4; continuar la negociación con las demás reglas vigentes      |

Las propuestas activas salvo A11 tendrán suficiente vigencia al terminar la carga, normalmente de 7 a 14 días. La fecha real sigue avanzando después: un caso puede vencer o salir de R4 mientras se prueba. Repetir el seeder no moverá silenciosamente sus fechas para volver a producir el estado inicial.

Marina y Andrés permiten probar que una nueva solicitud, un pago o un cambio de perfil invalida también el análisis de sus otros casos abiertos. Las restricciones pertenecen al caso correspondiente; no se extenderán a otros por conveniencia del guion.

## 6. Historial de los 22 casos cerrados

Se distribuirá desde `T - 270 días` hasta `T - 15 días`, con compras repetidas separadas y duraciones diferentes. La recurrencia estará respaldada por operaciones y por las declaraciones de cada cliente.

### Secuencias de negocio

1. **16 aceptados:** solicitud → publicación → conversación/decisión del comprador → formalización → pago o pagos → cierre `accepted`. Seis tendrán un pago completo y diez tendrán dos parcialidades. El cierre se registra con el último pago; no se crea un pago duplicado para representar el cierre.
2. **4 rechazados:** solicitud → cotización → objeción o rechazo explícito → resultado comercial `rejected` y cierre. No tendrán pago ni formalización.
3. **2 cancelados:** solicitud → cotización → cancelación documentada → cierre `cancelled`. No se introducirán anticipos en estos casos, porque el proyecto no tiene un flujo de devolución que justifique dejarlos como liquidación normal.

Catorce casos históricos tendrán una versión, seis tendrán dos y dos tendrán tres: **32 versiones históricas**. Cada nueva versión tendrá una razón: cambio de cantidades, abastecimiento, talla, condición o precio autorizado. La nueva publicación no modifica las líneas de la anterior.

Los mensajes deben mencionar las mismas cantidades, productos, condiciones y fechas que las operaciones. Una aceptación parcial no se narrará como aceptación total. Los documentos ficticios usarán referencias identificables como `DEMO-OC-...` o `DEMO-PAGO-...`, sin aparentar comprobantes bancarios reales.

## 7. Contrato de datos y concordancia

| Entidad                          | Información requerida y relación                                                                                            |
| -------------------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| `users`                          | Nombre, correo, hash, rol, verificación y procedencia demo; el acceso existe antes de la primera solicitud                  |
| `customers`                      | Cuenta única, empresa, contacto y comercial propietario; alta por primera solicitud                                         |
| `product_styles`, `products`     | Ficha, SKU y combinación única; fechas y observaciones coherentes                                                           |
| `purchase_requests`              | Cuenta, caso, `submission_id`, variantes/cantidades, empresa/mensaje/fecha y origen real del recorrido reproducido          |
| `commercial_cases`               | Cliente, conjunto operativo, ID público estable, responsable, estado/etapa/revisión proyectados desde operaciones y eventos |
| `quote_drafts`                   | Payload del formulario, revisión del borrador y del caso; solo interno                                                      |
| `quote_versions`                 | Número consecutivo, snapshot de líneas, subtotal, impuestos, envío, descuento, total, condiciones, autorización y vigencia  |
| `chat_messages`                  | Autor real de la operación reproducida, rol, cuerpo, versión pertinente y archivo privado si aplica                         |
| `buyer_decisions`                | Cuenta del propietario, versión válida al decidir, líneas y cantidades exactas, condiciones; identidad propia del evento    |
| `sale_records`                   | Comercial autor, formalización/pago/rechazo/cancelación, monto cuando corresponda, referencia y fechas de hecho/registro    |
| `case_events`                    | Tipos y contrato `business-v1`, payload original, normalización, hash e identidad estable                                   |
| `activity_events`                | Cuenta, cliente, caso, actor, origen, dato observado y fechas; correspondencia con la operación que produjo la actividad    |
| `profile_facts`                  | Nombre/alcance, valor tipado, naturaleza, evidencia del mismo cliente, confianza, autor/procedencia, vigencia y sucesión    |
| `evaluations`, `recommendations` | Snapshot y revisión reales del punto evaluado, contrato vigente, fuente correcta, validación, ranking e historial           |
| `human_reviews`, feedback        | Comercial que intervino, recomendación concreta, motivo y fecha; aprobar no genera contactos                                |
| `mail_deliveries`                | Notificación demo identificada y suprimida; nunca se presenta como aceptada por SMTP                                        |

### Invariantes verificables

- Subtotal = suma de cantidad × precio unitario en centavos. Total = subtotal + impuesto + envío − descuento. No usar float para liquidaciones o conciliaciones.
- El impuesto es un dato monetario explícito de cada escenario. Si se deriva de una tasa de prueba, definir base y redondeo; no añadir una lógica fiscal nueva bajo el seeder.
- Un pago parcial no supera el importe formalizado pendiente y los pagos de cada caso aceptado cerrado suman exactamente lo formalizado. El monto acordado está respaldado por la versión/decisión y su referencia.
- Una aceptación nunca excede cantidades publicadas. La publicación era vigente y no estaba retirada ni sustituida cuando se decidió.
- Apertura ≤ publicación ≤ decisión ≤ formalización ≤ pagos/cierre en los escenarios donde esas etapas existen. No toda oportunidad necesita todas las etapas.
- Fechas de evidencia y conocimiento no exceden el corte donde se utilizan. `occurred_at`, `ingested_at`, `created_at` y `observed_at` tienen significado distinto.
- Los contadores de revisión se generan por el flujo existente; no asumir que un gesto de usuario equivale a un único incremento cuando produce varios eventos.
- Ninguna ficha de cliente puede enlazar evidencias de otro. Una corrección confirmada conserva historia y no es reemplazada por una hipótesis posterior.
- Los indicadores de solicitudes, formalizaciones y pagos se consultan por separado, evitando multiplicar importes al unir líneas, mensajes o versiones.
- Ningún caso cerrado vuelve a abrirse al consultarlo; R1 prevalece. Para otra compra se crea otra solicitud/cotización.
- Las vistas no cuentan como aceptación, pago, intención demostrada o permiso de contacto.

## 8. Procedencia demo que sobrevive a la carga

Se requiere una migración incremental; no modificar las migraciones históricas ni reiniciar `byecode`.

### Registro de ejecución

- `demo_runs`: UUID, clave única de ejecución, versión del escenario, semilla, fecha de referencia, fecha real de carga, hash del manifiesto, estado `loading/ready/failed`, política de IA y localización del informe.
- `demo_records`: ejecución, tipo de entidad permitido, ID persistido, clave lógica estable y hash inicial. Unicidad de clave dentro de la ejecución y de la entidad registrada. No aceptar nombres de tabla arbitrarios desde parámetros.
- `demo_run_id` nullable e indexado en las raíces necesarias para consultas y propagación: usuarios, clientes, casos, fichas de producto y variantes. Las entidades hijas heredan por sus relaciones. Las notificaciones guardan su propia referencia para mantener la supresión aunque cambie el correo del destinatario.
- Añadir autor nullable a nuevas revisiones del perfil y conservar una procedencia explícita en los registros que puedan mostrar una hipótesis preparada por el escenario.

Los conjuntos `live` se reutilizan según las reglas actuales de `Workspace`, sin etiquetar como demo todo el conjunto compartido del comercial. Los flags de procedencia se asignan en el servidor, no desde campos manipulables de formularios.

### Propagación y presentación

Una nueva solicitud de una cuenta demo, una cotización de su CRM y cualquier mensaje, resultado o análisis de ese caso conservarán la procedencia. La prueba seguirá siendo sintética aunque se introduzca actividad manual después del seeder.

El entorno local podrá activar un modo demostración vinculado a la ejecución para que las cuentas nuevas registradas durante esas pruebas también queden marcadas. La activación será explícita, estará limitada a `local/testing` y no reclasificará usuarios anteriores. Las cuentas de administración/comercio existentes no se convierten en cuentas demo.

Los listados, detalle y métricas mostrarán una etiqueta o contexto visible **Datos de prueba**. Si conviven datos reales y demo, las consultas de indicadores deben permitir distinguir su alcance; el estado de origen no se deduce del nombre de una empresa ni del prefijo del SKU. Se mantiene el diseño ByeCode, sin rediseñar el CRM.

### Predictor

Excluir la demostración de `BusinessHistory::train`, de casos previos/pagos de referencia, de cierres globales, de perfiles usados como características y de cualquier agregado compartido que pueda contaminar un entrenamiento real. Añadir además un bloqueo explícito de porcentaje para casos demo en la consulta de predicción, incluso si ya existiera un predictor elegible entrenado con datos reales.

El seeder no generará 200 filas para saltarse el requisito de historial real, no creará entrenamientos elegibles ficticios y no alterará métricas guardadas. Los ensayos sintéticos del predictor, si se necesitan, pertenecen a las pruebas aisladas y se identifican como tales.

## 9. Mensajes, notificaciones y archivos

Las conversaciones se redactarán por escenario: motivo del pedido, dudas sobre ficha, petición de cambios, decisiones y seguimiento. Cada mensaje tendrá un propósito y una respuesta coherente; no se repetirá texto genérico para aumentar volumen.

Se incluirán seis adjuntos de prueba pequeños y válidos: imágenes de referencia de distribución de tallas, empaque o diseño ilustrativo. Se guardarán en el disco privado con nombre, tipo y tamaño verificables, enlazados a sus mensajes. No son recibos oficiales ni documentos de terceros. Solo los participantes autorizados pueden descargarlos.

Las notificaciones dirigidas a cuentas demo quedarán en estado explícito `suppressed_demo`, sin cuerpo sensible retenido y sin envío. Esta comprobación se aplicará al encolar y nuevamente en `DeliverTransactionalMail`, incluyendo correo de acceso, recuperación y nuevas publicaciones realizadas después de la carga. Se cubrirá el caso de SMTP habilitado y de jobs antiguos ya pendientes. No basta con dejar `TRANSACTIONAL_MAIL_ENABLED=false` en la consola del seeder.

La carga no enviará mensajes externos a los Gmail elegidos ni cambiará el transporte de correo del resto de la aplicación. Los mensajes internos de chat son registros ficticios de la demostración y pasan por las mismas restricciones R1–R4.

## 10. Perfil evolutivo, análisis por evento y ranking

### Perfil inicial útil

Se cargarán confirmaciones con evidencia usando `CommercialProfile::validate` y `CustomerProfile::fact`, después de reproducir los hechos que las justifican. El conjunto incluirá datos vigentes, desconocidos representados por ausencia o JSON nulo, propiedades sustituidas y al menos dos historias de corrección.

Para probar visualmente hipótesis y conflictos se pueden incluir candidatos históricos redactados en el manifiesto, identificados como **hipótesis sintética de demostración**, sin nombre de modelo OpenAI ni falsa afirmación de que una llamada los produjo. Pasarán por el mismo validador de evidencia y mecanismo de no sustitución de correcciones. No se añadirá un intérprete paralelo basado en palabras clave que pretenda analizar cualquier texto nuevo.

### Evaluaciones durante la reconstrucción

- Mantener `DecisionUpdates`, `RefreshCaseDecision` y la revisión vigente como autoridad. Agrupar las solicitudes de trabajo durante la carga masiva, sin ejecutar centenares de jobs sobre pasos intermedios.
- En hitos concretos del guion, generar snapshots completos y ejecutar reglas/validadores reales bajo el reloj de ese hito, para producir historia comprobable de recomendaciones deterministas.
- Incluir transiciones que las reglas sí resuelven: solo vistas → pausa R4, aplicación de R2/R3, modificación de frontera temporal y cierre → R1. Conservar recomendaciones anteriores y `change_summary`.
- Las revisiones humanas de esas recomendaciones se registran con los comerciales del guion y motivos concretos. No producen `action.executed` por aprobarlas.
- Al terminar, generar una evaluación del estado final por caso y la situación pendiente del perfil correspondiente. El worker deberá poder seguir procesando eventos nuevos y las pantallas seguirán consultando sus cambios.

### Qué funciona sin pagar a OpenAI

La carga inicial y su verificación usarán cero llamadas facturables, incluso si existe una clave global. La política persistida de la ejecución demo se comprobará al seleccionar proveedor, procesar perfiles y ejecutar jobs recuperados por la agenda. Los marcadores de recuperación no deben permitir que un proceso externo eluda esa política.

Las reglas generan decisiones y exclusiones cuando hay fundamento determinista. Si un caso necesita interpretar lenguaje y esa parte está deshabilitada, se mostrará una causa honesta —por ejemplo, análisis semántico de demo deshabilitado—, conservando el caso operativo. No se insertará una recomendación con fuente `openai`, ranking `model_validated` o costo simulado para completar la pantalla.

Para un ranking semántico nuevo sobre mensajes libres será necesario habilitar expresamente el proveedor real. Esa activación es posterior al seeder y conserva los límites globales existentes; este plan no autoriza llamadas pagadas ni aumenta presupuesto. Los tests con proveedor simulado comprobarán el contrato v2, el ranking y la cola, pero no se presentarán como calidad real de IA ni se publicarán como resultados comerciales.

**Porcentaje:** estos datos no habilitan probabilidades de cierre reales ni de éxito por acción. La ausencia del porcentaje debe aparecer con su motivo. La funcionalidad de solicitudes, cotizaciones, mensajes, decisiones, pagos y perfiles confirmados quedará disponible sin depender de ese porcentaje.

## 11. Construcción reproducible del historial

### Manifiesto versionado

Separar los datos del escenario de su ejecución:

- `database/seeders/fixtures/operational-demo-v1/manifest.json`: versión, semilla, claves, cuotas y relaciones esperadas.
- `customers.json`: identidades ficticias y narrativa comercial, sin contraseñas.
- `catalog.json`: fichas, combinaciones y observaciones.
- `scenarios.json`: operaciones fechadas relativamente, actores, referencias, mensajes y resultados esperados.
- `profile-history.json`: confirmaciones/correcciones y referencias lógicas a evidencia ya existente.
- `attachments/`: archivos ilustrativos usados realmente por esos escenarios.

Las claves lógicas y los IDs de envío/evento serán estables. Los IDs numéricos de PostgreSQL y los UUID públicos generados por el flujo se resolverán en `demo_records`; nunca se supondrá que un cliente es el ID 1. La aleatoriedad solo podrá variar detalles de manera determinista y respetando el contrato de cada escenario; no decidirá al azar si un pago pertenece a otra venta.

### Orden y reloj

El ejecutor construirá una línea temporal global de operaciones, incluyendo observaciones de catálogo, cuentas, solicitudes, mensajes, confirmaciones y resultados. Se ordenará por instante y orden estable de desempate. Esto evita que una decisión histórica vea por accidente la actividad futura de otro caso del mismo cliente.

La fecha de carga real se guarda aparte. El reloj de reproducción afecta únicamente al proceso de la carga y se restaura en un `finally`; no congela Laravel web o el worker general. `now()` y `CarbonImmutable::now()` deben coincidir durante la reconstrucción.

El evento se crea con su fecha histórica y fecha de conocimiento prevista. No se insertará todo con `created_at = ahora` para luego cambiar columnas aisladas. Las evaluaciones históricas se calculan cuando todavía solo existe el contexto correspondiente a su corte.

Las fechas de creación reales de Alberto y del equipo comercial se conservan. Su vinculación a acciones de meses anteriores es una representación del guion ficticio, no una afirmación de que esas cuentas ya operaban entonces: la procedencia y la fecha real de reproducción lo harán explícito. Las cuentas nuevas de cliente sí tendrán su alta simulada anterior a su primera solicitud. Esta diferencia no se resuelve retrodatando usuarios ajenos al seeder.

### Flujo transaccional

1. Validar configuración, entorno, equipo existente, manifiestos, correos, SKUs y archivos sin escribir.
2. Adquirir un bloqueo de ejecución por clave y registrar la ejecución `loading`; impedir dos cargas simultáneas.
3. Preparar únicamente los archivos privados propios en un área de staging de esa ejecución.
4. Reproducir los datos en una transacción de negocio. Suprimir temporalmente el despacho normal de evaluaciones durante la reconstrucción mediante un contexto explícito del cargador, sin quitar restricciones del dominio.
5. Auditar coherencia, totales, ownership, evidencia y origen dentro de la transacción. Ante un fallo, revertir el lote completo.
6. Finalizar los archivos requeridos y guardar sus hashes. Si falla la base o el almacenamiento, limpiar solo los archivos creados por esta ejecución y dejar un diagnóstico recuperable; no prometer atomicidad distribuida del filesystem y PostgreSQL.
7. Marcar `ready` junto con los datos, confirmar la transacción y solicitar solo el procesamiento final necesario. Los snapshots históricos ya calculados quedan conservados.
8. Consultar desde otro proceso para confirmar persistencia y generar el informe de uso.

Una ejecución `loading/failed` no habilita procesamiento en workers. Una caída posterior al commit se recupera usando la procedencia y los marcadores de revisión; no repitiendo toda la historia.

## 12. Repetición, preservación y comandos propuestos

Los siguientes comandos son parte de la implementación pendiente, no comandos disponibles hoy:

```powershell
php artisan byecode:seed-demo --dry-run
php artisan byecode:seed-demo
php artisan byecode:verify-demo
php artisan db:seed --class=OperationalDemoSeeder
```

El comando y `OperationalDemoSeeder` usarán el mismo ejecutor. `DatabaseSeeder` seguirá sin cargar la demo automáticamente. El entrypoint estándar del seeder tendrá las mismas barreras de entorno, origen, contraseña y ejecución que el comando.

- Primera carga: crear `operational-demo-v1`, con referencia temporal fijada entonces y semilla reproducible.
- Segunda carga: si la ejecución está completa, informar que ya está instalada y verificar sus identidades; no rehacer publicaciones, mensajes, contraseñas, snapshots o jobs.
- Actividad manual posterior: permanece intacta. El informe distingue la base cargada de las operaciones añadidas por el usuario; no exige que los totales sigan en 34 casos para declarar sana la ejecución.
- Configuración o manifiesto incompatibles: detenerse con un diagnóstico concreto; no sobrescribir la base para acomodarlos.
- Fallo incompleto: resolver desde el estado de ejecución y una auditoría de claves/hash. Si hay cambios manuales o ambigüedad, no hacer una reparación destructiva automática.
- No ofrecer `--fresh`, truncados, borrado por correo, purga global de jobs o reinicio de secuencias. Una nueva versión del escenario requiere una evolución explícita o una base de prueba independiente.
- La ejecución solo se permite en `local/testing`, con demo habilitada. Que PostgreSQL esté alojado en Supabase no elimina esa comprobación. Nunca usar `migrate:fresh` contra Supabase.

## 13. Archivos y fases de implementación

Los nombres nuevos son la propuesta de organización; los archivos existentes se ampliarán conservando sus contratos y los cambios del otro chat.

| Fase                      | Trabajo                                                                                                                             | Entregable / condición para continuar                                                         |
| ------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------- |
| 1 · Baseline              | Revisar cambios locales de decisiones por evento, dependencias y permisos; ejecutar regresión inicial                               | Estado de partida documentado y sin sobrescribir trabajo pendiente                            |
| 2 · Procedencia           | Migración incremental, modelos/registro demo, política persistente, propagación, exclusión predictiva y supresión de correo         | Tests prueban que ningún worker gasta o envía por la demo y que no contamina datos reales     |
| 3 · Servicios compartidos | Extraer mensajes, decisiones, resultados, retiros, borradores y validaciones pertinentes; reutilizar solicitud/publicación/perfiles | Controladores y seeder usan la misma lógica; permisos y resultados previos conservados        |
| 4 · Manifiestos           | Fijar las 12 cuentas, 6 fichas/36 SKUs, 34 guiones, perfiles y adjuntos                                                             | Validación estructural y conciliación exacta de los conteos y montos del manifiesto           |
| 5 · Reproductor           | `OperationalDemoSeeder`, comando, `DemoScenarioLoader`, reloj, registro de claves, transacción y gestión de archivos                | Carga íntegra e idempotente sobre SQLite aislado; los escenarios pasan el dominio             |
| 6 · Análisis e interfaz   | Agrupar trabajos, checkpoints históricos, evaluación final y etiquetas/alcance demo                                                 | Cambios de contexto, reglas, historial y fuentes visibles sin presentar IA simulada como real |
| 7 · Auditoría             | `DemoIntegrityAudit`, `byecode:verify-demo`, pruebas de navegación y de continuidad                                                 | Matriz de aceptación completa y reporte sin secretos                                          |
| 8 · Carga local           | Aplicar solo migraciones nuevas y ejecutar contra Supabase configurado tras aprobar las verificaciones técnicas                     | Doce accesos, clientes/casos persistidos y prueba posterior desde otro proceso                |
| 9 · Entrega               | Guía de recorridos, informe de escenarios/enlaces, estado de desarrollo y revisión de cambios                                       | Seeder terminado y guía que permite probarlo sin conocer IDs internos                         |

Ubicación propuesta del código de carga: `app/Domain/Demo/`. Comandos: `app/Console/Commands/SeedDemo.php` y `VerifyDemo.php`. Pruebas: `tests/Feature/ByeCode/OperationalDemoSeederTest.php`, `DemoContinuityTest.php` y `DemoIsolationTest.php`. La creación concreta de la migración se hará mediante Artisan; no se fija aquí un nombre de migración ejecutable ni se modifica el esquema durante la planificación.

Los puntos de integración incluyen `CustomerJourney`, `QuoteService`, `CustomerProfile`, `ImportService`, `DecisionUpdates`, `EvaluationService`, `BusinessHistory`, `PredictionService`, `TransactionalMail`, `DeliverTransactionalMail`, `EnrichCustomer` y la recuperación de tareas de `routes/console.php`.

## 14. Pruebas y definición de terminado

### Validación automática aislada

| Prueba                 | Resultado exigido                                                                                                     |
| ---------------------- | --------------------------------------------------------------------------------------------------------------------- |
| Carga completa         | 12 cuentas, 10 CRM, 34 solicitudes/casos, 46 versiones, 2 borradores, 50 registros comerciales y 36 variantes         |
| Repetición             | Mismos IDs, contraseñas, referencias, versiones y número de operaciones; sin nuevos jobs de reconstrucción            |
| Conservación           | Administrador, cuatro comerciales, kit público, presupuestos y datos ajenos sin modificación                          |
| Colisión               | Correo/SKU/conflicto de manifiesto cancelan antes de altas parciales o cambios de rol                                 |
| Integridad financiera  | Totales de líneas y pagos conciliados; anticipo abierto y saldo correcto; sin duplicación por joins                   |
| Historia temporal      | Ninguna evidencia, versión o pago del futuro aparece en un snapshot anterior                                          |
| Permisos               | Cliente A no puede leer/descargar/decidir sobre B; administrador no edita; comercio comparte operaciones              |
| Primera solicitud      | Daniela crea un CRM al enviar; siguiente solicitud reutiliza el mismo; Nicolás sigue sin CRM hasta actuar             |
| Continuidad            | Publicar una versión nueva, decidir, enviar mensaje permitido, pagar y cerrar mantiene referencias y proyecciones     |
| Borradores/versiones   | No exponer borrador al cliente; bloqueo de revisiones concurrentes; rechazo de versión vencida, retirada o sustituida |
| R1–R6                  | Casos de cada regla y sus combinaciones; mensaje posterior levanta solo R4, no R2/R3                                  |
| Perfiles               | Evidencia propia, confirmación, sustitución, nulo y conflicto; hipótesis no sobrescribe corrección                    |
| Cola nueva             | Evento confirmado programa actualización; rollback no; ráfaga usa contexto final; revisión vieja no se publica        |
| Repercusión compartida | Nuevo pago/solicitud/perfil invalida otros casos abiertos del mismo cliente, sin tocar otros clientes                 |
| Correo demo            | Cero entregas SMTP aun con transporte habilitado y recuperación en otro proceso                                       |
| IA y costos            | Cero HTTP facturable durante carga/verificación; política demo respetada por jobs y agenda; presupuesto real intacto  |
| Predictor              | Casos y agregados demo excluidos del entrenamiento real; demo no muestra probabilidad elegible                        |
| Fallos                 | Reversión del lote y limpieza únicamente de archivos propios; ejecución recuperable e informe concreto                |

Las pruebas de continuidad que cambian o cierran casos se ejecutarán en SQLite aislado. La comprobación final sobre Supabase verificará lectura, relaciones, acceso y procedencia sin consumir los escenarios que deben quedar listos para el usuario.

### Verificación de interfaz

1. Entrar como comercial y revisar directorio, filtros, orden, vista rápida, perfil, historial y retorno al directorio.
2. Entrar como cliente con historia y comprobar sus propias solicitudes/cotizaciones; comparar versiones y descargar un adjunto propio.
3. Completar en pruebas aisladas los recorridos A02, A03, A05, A06 y A08 hasta nuevas publicaciones/resultados, usando formularios reales.
4. Observar A07, A09, A10 y A12 para permisos de contacto, evidencia y diferencias entre vistas, mensajes y decisiones.
5. Revisar la actualización automática del caso/perfil y que el origen del ranking, el análisis pendiente y la ausencia de porcentaje sean comprensibles.
6. Verificar escritorio y móvil con el diseño existente, sin filtros que mezclen silenciosamente datos reales y demo.

Ejecutar Pest y Larastan, Pint y los checks de frontend cuando haya cambios de presentación. Usar la batería del proyecto `composer run ci:check` y el build al cerrar la implementación. Las simulaciones de proveedor quedan limitadas a pruebas aisladas.

### Entrega utilizable

- `OperationalDemoSeeder` y comandos implementados y documentados.
- Migración incremental y política de procedencia verificadas.
- Datos cargados en la base configurada con un reporte de conteos, conciliaciones y estado de ejecución.
- `GUIA_DEMO.md` con lista de escenarios, cuenta apropiada para cada recorrido, siguiente acción esperada y explicación de los casos bloqueados por reglas.
- Informe local que resuelve claves como A01 a enlaces reales del caso y del portal privado, sin contraseñas ni tokens de sesión.
- Accesos iniciales según lo solicitado, protegidos por hash; secreto de configuración únicamente en `.env`.
- Declaración exacta de lo verificado y de la dependencia pendiente de proveedor real para interpretación semántica. No prometer precisión ni calidad de IA por haber cargado datos sintéticos.

**La implementación estará terminada cuando se pueda entrar con esas cuentas, continuar las oportunidades existentes y crear otras desde la aplicación, conservando el historial y las reglas, sin volver a ejecutar el seeder para que aparezca la siguiente etapa.**
