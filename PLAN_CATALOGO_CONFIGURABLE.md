# Plan de implementación: catálogo configurable de ByeCode

Fecha: 10 de octubre de 2026. Estado: **plan preparado; implementación pendiente**.

Base confirmada por el usuario: completar los **seis modelos y las 36 variantes existentes** con imágenes coherentes. La entrega de implementación deberá dejar catálogo, imágenes, selección, solicitudes, cotizaciones y seeders conectados y utilizables.

## 1. Preparación completada y alcance

- Se ejecutaron `git fetch --all --prune` y `git pull --ff-only --autostash origin main`. Se incorporó `876889d` (`Disable email verification requirement`). `main` quedó sincronizado con `origin/main` y se conservaron los cambios locales de interacciones y documentación, sin conflictos pendientes.
- Se revisaron las guías vigentes, el sistema visual, el catálogo, solicitudes, cotizaciones, apariencia, migraciones y el cargador de historial.
- Consultas de solo lectura en la base configurada confirmaron **6 fichas publicadas, 36 variantes publicadas y las seis galerías vacías**. No se modificó Supabase durante esta planificación.
- La captura anterior del catálogo sirve de referencia estructural; contiene avisos de analítica ya retirados por los cambios locales. El código actual gobierna el comportamiento.

Este plan amplía Laravel + Inertia + React + TypeScript + Tailwind/shadcn y PostgreSQL mediante Eloquent. Implementar por áreas afectadas; conservar el registro de interacciones y la eliminación reciente del requisito de verificación de correo. Sin Docker, API REST separada, cambio de stack ni refactorización general.

**Resultado esperado:** explorar prendas por modelo/corte, ver sus colores reales en pantalla, configurarlas, distribuir cantidades entre tallas y acumularlas en un carrito inferior para solicitar una cotización. El catálogo público sigue sin precios, checkout ni cobro.

## 2. Catálogo y cobertura de imágenes

Los colores ya registrados por el seeder son **Negro `#171717`** y **Natural `#EEE9DC`**. Ambos se conservarán como fuente única de los selectores y de la dirección de color de las imágenes.

| Clave interna | Nombre comercial propuesto | Corte mostrado | Gramaje  | Composición existente       | Colores                           | Tallas  | Variantes | Imágenes |
| ------------- | -------------------------- | -------------- | -------- | --------------------------- | --------------------------------- | ------- | --------: | -------: |
| REG           | Playera clásica            | Clásico        | 180 g/m² | 100% algodón                | Negro, Natural                    | S, M, L |         6 |        8 |
| OVR           | Playera oversize           | Oversize       | 220 g/m² | 100% algodón                | Negro, Natural                    | S, M, L |         6 |        8 |
| HVE           | Playera boxy               | Boxy           | 260 g/m² | 100% algodón                | Negro, Natural                    | S, M, L |         6 |        8 |
| LNG           | Playera de manga larga     | Clásico        | 200 g/m² | 100% algodón                | Negro, Natural                    | S, M, L |         6 |        8 |
| CRW           | Sudadera sin capucha       | Clásico        | 300 g/m² | 80% algodón / 20% poliéster | Negro, Natural                    | S, M, L |         6 |        8 |
| HOD           | Sudadera con capucha       | Relajado       | 320 g/m² | 80% algodón / 20% poliéster | Negro, Natural                    | S, M, L |         6 |        8 |
| **Total**     | **6 modelos**              |                |          |                             | **12 combinaciones modelo/color** |         |    **36** |   **48** |

Cada combinación modelo/color tendrá **frente, espalda, vista tres cuartos y detalle de tejido/costura**. Son 48 archivos originales distintos, con cobertura de las 36 variantes. Las tallas comparten las imágenes del mismo modelo, gramaje y color; no generar duplicados idénticos solo para multiplicar archivos. Si una futura variante cambia material, gramaje o construcción, tendrá su propio conjunto cuando corresponda.

Las imágenes serán representaciones generadas de las especificaciones existentes. No inventar certificaciones, prestaciones, nuevas composiciones ni supuestas fotografías de un proveedor. Las medidas actuales son ilustrativas y no justifican nuevas promesas de ajuste; conservar su procedencia técnica y permitir edición comercial.

### Producción de los 48 recursos

1. Crear un manifiesto versionado de catálogo con claves estables, atributos, color hexadecimal y lista completa de recursos esperados. Cada variante deberá resolver un conjunto de cuatro vistas antes de dar por terminada la carga.
2. Usar la herramienta integrada `image_gen`, conforme a la skill `imagegen`, con `transparent_background: true`. Una llamada por recurso; generar una base coherente y usarla como referencia al producir otras vistas y colores. No usar la API key de Laravel ni activar llamadas del módulo comercial para producir estos recursos.
3. Mantener encuadre de producto, proporciones consistentes, iluminación neutra, prenda completa en vistas generales y ausencia de personas, maniquí visible, perchas, logotipos, texto, marcas de agua o sombras de fondo opacas. Cada corte debe distinguirse por su silueta, mangas y caída.
4. Guardar los PNG con alfa real en `public/images/catalog/v1/{modelo}/{color}/{vista}.png`, copiando los archivos finales al repositorio. Los archivos originales de la herramienta no serán la ubicación de consumo de la aplicación.
5. Verificar alfa, dimensiones, bordes, silueta y coherencia entre vistas. El cuadro de color utilizará el HEX exacto; la prenda debe representar ese tono conservando luces y sombras naturales. No simular el cambio de color aplicando un filtro CSS a una única foto.
6. Guardar prompts y procedencia fuera de `public`, en documentación técnica. El manifiesto público solo expondrá lo necesario para mostrar la prenda. Optimizar tamaño/resolución conservando alfa; no introducir una dependencia pesada solo para convertir formatos.
7. Hacer una revisión conjunta de las 48 imágenes sobre fondos claro y oscuro. Regenerar únicamente las fallidas. La entrega no se cierra con placeholders, archivos ausentes o variantes sin galería.

**Plantilla de prompt:** «Imagen de producto para catálogo de prendas blank: {modelo}, corte {corte}, {composición}, {gramaje} g/m², color {nombre} con objetivo sRGB {hex}, vista {vista}. Fondo realmente transparente con canal alfa. Iluminación neutra suave, textura de tela realista, consistencia con la referencia del mismo modelo. Prenda sin estampado ni marca, sin personas, sin perchas, sin texto y sin fondo blanco incorporado. Conservar diseño, proporciones y construcción entre vistas».

## 3. Experiencia del catálogo

Modo de uso: **operar**. El comprador compara prendas y arma una solicitud; el equipo comercial mantiene esas mismas fichas. Conservar la identidad ByeCode, Instrument Sans y tokens existentes de claro/oscuro. El color de las prendas aporta el acento visual.

### Directorio y ficha configurable

- Sustituir las fichas extensas con todas las variantes en tabla por una cuadrícula de **una tarjeta por modelo**, con fotografía protagonista, nombre, gramaje y cuadros de color. Distribución prevista: dos columnas en móvil cuando el ancho lo permita y tres en escritorio; una columna en pantallas estrechas.
- Encabezado breve «Catálogo» y acción clara; retirar el bloque explicativo de demostración. Búsqueda por nombre, categorías comprensibles y filtros combinables por corte, color, talla y gramaje, derivados de las combinaciones publicadas. Mostrar resultados vacíos y permitir limpiar filtros.
- Al pulsar un color en la tarjeta, actualizar su imagen inmediatamente. Mantener «Configurar» como acción explícita. No mostrar filas de SKU, HEX en bruto, `null`, claves de base de datos ni etiquetas repetidas como «Regular».
- Incorporar ficha enlazable `/catalog/{style}`. En escritorio: galería y configuración en dos columnas; en móvil: galería seguida de configuración. Mantener filtros y posición al volver al directorio.
- Orden de configuración: modelo/corte → material/gramaje → color → cantidades por talla → «Agregar a mi selección». Los atributos con una sola opción se presentan como dato; no inventar opciones para llenar selectores.
- Cambiar color, material o gramaje resuelve combinaciones existentes y actualiza la galería. Una combinación que no pertenece al catálogo se explica como «Esta combinación no forma parte del modelo», sin lenguaje de existencias.
- Tallas S/M/L con controles de cantidad. El comprador puede añadir varias tallas de una combinación en una acción. Al cambiar de configuración, no reinterpretar silenciosamente cantidades ya agregadas.
- Cuadros de color accesibles, con nombre, borde visible para Natural, foco y marca de selección; el color por sí solo no comunica el estado. Seleccionar mediante teclado debe funcionar igual que pulsar.
- Material, cuidados y medidas quedan en secciones secundarias desplegables. Conservar mínimos comerciales existentes como condiciones de cotización, con el flujo actual de solicitud de excepción; **MOQ no es stock**.

### Carrito inferior desplegable

- Barra fija en el borde inferior: icono, número de configuraciones, total de prendas y «Ver selección». Al abrir, despliega un panel **hacia arriba desde abajo**, tanto en escritorio como en móvil. Nunca usar carrito lateral.
- Dentro: miniatura del color elegido, modelo, gramaje, color, tallas y cantidades; editar, eliminar y seguir configurando. Agrupar visualmente por modelo/gramaje/color, conservando `product_id` y cantidad por talla para el servidor.
- Añadir la misma variante suma cantidades; añadir otro color o gramaje crea otra configuración. El cambio de filtros o de ficha no elimina la selección.
- Acción principal «Solicitar cotización», sin precios públicos ni importe inventado. El carrito vacío muestra una indicación breve y no permite enviar una solicitud vacía.
- Persistir en la sesión Laravel mediante una ruta Inertia dedicada a guardar la selección, separada del envío final. Guardar al añadir/editar/eliminar, sin una petición por cada tecla. Bloquear el envío hasta confirmar el último guardado y evitar que respuestas anteriores sobrescriban cambios recientes.
- Recuperar selección tras recarga y tras entrar/crear cuenta. Vaciarla después de una solicitud confirmada o cierre de sesión, para no mostrar la selección de una cuenta en otra. Mantener el comportamiento presencial separado y limpiar también su selección cuando se complete.
- Conservar el identificador idempotente de solicitud. Validar productos publicados, pertenencia al modelo, cantidades enteras positivas y ausencia de duplicados tanto al guardar selección como al enviar; no confiar en atributos enviados por React.
- Unificar el límite de líneas en selección, solicitud, borrador y publicación. El límite actual de 30 no admite las 36 variantes juntas: proponer **100 líneas** como límite técnico compartido, independiente del inventario.
- Reservar espacio para la barra y el área segura del móvil. Panel con altura máxima, scroll interno, cierre con Escape, foco controlado y retorno al botón de apertura. El teclado virtual no debe ocultar la cantidad ni la acción principal.

Componentes previstos: `ProductCard`, `ProductConfigurator`, `ProductGallery`, `ColorSwatch`, `SizeQuantityInput` y `SelectionDrawer`, dentro de `resources/js/components/catalog/`. Reutilizar Button, Dialog, Collapsible y controles instalados; no incorporar una biblioteca de ecommerce o un estado global nuevo para este recorrido.

## 4. Datos de catálogo, imágenes y contratos

- Mantener `product_styles` y `products`. La variante sigue siendo la unidad solicitada/cotizada; no reemplazar el dominio completo.
- Usar `products.details.color_code` con validación estricta `#RRGGBB` y añadir `details.images` con vista, ruta pública, texto alternativo y dimensiones. Las tallas pueden referenciar los mismos archivos. Evitar una tabla nueva por imagen en esta primera entrega.
- Adaptar `CatalogService` y su formulario: actualmente las fotos solo admiten URLs HTTPS a nivel de ficha. Permitir recursos locales del catálogo mediante rutas restringidas y validadas, manteniendo soporte de fotos HTTPS existentes; impedir rutas arbitrarias o traversal.
- Entregar DTOs explícitos a Inertia con atributos visibles y recursos resueltos. Los códigos internos y metadatos de carga no forman parte del contrato público.
- Extender la comprobación de combinación única para incluir gramaje y composición/material; hoy solo considera ficha, corte, color y talla. Normalizar valores y detectar colisiones antes de actualizar datos.
- Adoptar códigos comerciales estables para nuevas cargas, por ejemplo `BC-LNG-200-NAT-L`, manteniéndolos internos. Cambiar los SKUs existentes mediante un mapa explícito; **conservar IDs** de productos y sus relaciones.
- Guardar en nuevas líneas de cotización la descripción y los atributos acordados, además de la referencia versionada de imagen/color. Las versiones históricas no deben cambiar de producto o precio porque después se edite el catálogo.

## 5. Retirar stock y disponibilidad de toda la función operativa

La retirada incluye frontend, backend, validaciones, datos operativos, seeders y prompts activos; ocultar una columna no satisface el requisito.

| Área                  | Cambio concreto                                                                                                                                                                                                                                                                                                              |
| --------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Base y modelo         | Migración incremental para eliminar `products.stock`; retirar cast y anotaciones. Eliminar `details.availability` de los productos. Revisar `verified_at`: conservarlo solo como fecha de revisión de ficha, sin semántica de existencias.                                                                                   |
| Catálogo              | Retirar entradas y salida de stock/disponibilidad en `CatalogService`, `CatalogController`, `CommerceController`, tipos y formularios.                                                                                                                                                                                       |
| Cotización y borrador | Retirar `supply_mode`, `stock_units`, `manufacture_units`, `observed_stock`, `stock_verified_at` y el indicador heredado `fulfillment_confirmed` asociado a ese reparto. Eliminar la validación inventario/fabricación/mixto y la comparación con existencias.                                                               |
| Persistencia JSON     | Limpiar esas claves en borradores y líneas operativas existentes mediante transformación acotada e idempotente. Registrar antes/después de las claves retiradas; preservar IDs, cantidades, atributos, versiones, fechas y todos los importes.                                                                               |
| Interfaz completa     | Catálogo público y gestión, selección, solicitud, editor de cotizaciones, portal, impresión y componentes heredados: sin badges, columnas, formularios ni mensajes de inventario. Retirar código heredado únicamente cuando se confirme que ya no tiene consumidores.                                                        |
| IA y reglas           | Retirar `stock_confirmation` del contrato activo, validadores, prompts y fixtures semánticos afectados. Mantener R1–R6 y la necesidad de validar internamente fechas, autorización y términos. Las interpretaciones almacenadas con el contrato anterior deben invalidarse/versionarse para que no reactiven este requisito. |
| Seeders               | Eliminar `stock`, `supply` y textos de disponibilidad del nuevo manifiesto. El caso A11 seguirá demostrando vencimiento y renovación de términos/fecha, sin depender de disponibilidad.                                                                                                                                      |

Se siguen manejando **cantidad solicitada, talla, mínimos comerciales, vigencia, entrega acordada, precios privados y pagos**. No sustituir stock por «capacidad», «disponible por encargo» o un inventario con otro nombre.

No reescribir migraciones ya aplicadas ni ejecutar `migrate:fresh`. Antes de retirar campos, guardar una copia privada de los valores afectados y comprobar que importes y relaciones no varían. Desplegar código y migración coordinados, con reinicio de workers, para que ningún proceso antiguo vuelva a escribir las claves eliminadas.

Los originales sellados de eventos/importaciones, documentación histórica y `referencias/kit_publico` se conservan como evidencia técnica. No sostienen una función de stock ni se entregan en bruto en el recorrido comercial. Si se consulta evidencia histórica, no falsificar su contenido para hacer coincidir una búsqueda textual; el criterio es cero gestión de stock activa y cero datos de stock en la presentación operativa.

## 6. Retirar menciones de demostración de la aplicación

- Eliminar `DemoNotice` y sus inserciones en `Journey` y `AppLayout`, incluido el hero citado por el usuario.
- Eliminar filtros y badges «demo», «demostración», «sintético» y equivalentes en las pantallas de operación. Limpiar también títulos, descripciones, ayudas, estados vacíos, textos accesibles, correos de plantilla y presentaciones de referencias generadas por los seeders.
- Revisar las pantallas de importaciones, inteligencia y predicción: presentar estados útiles como «Análisis no habilitado para este registro» o «No hay un modelo validado». No convertir datos de prueba en evidencia de precisión ni afirmar que se entrenó un predictor.
- Al retirar el filtro visual de procedencia, dejar una política explícita para los listados y limpiar el `demo_origin` persistido. El valor predeterminado para la operación local será mostrar todos los registros autorizados; un filtro invisible antiguo no debe ocultar clientes o solicitudes.
- Ocultar códigos como `DEMO-LNG-NAT-L` de tarjetas, solicitud, cotización, tablas y etiquetas accesibles. Mostrar «Playera de manga larga · Natural · L · 200 g/m²». Sustituir la etiqueta comercial «Regular» por «Clásico» en los modelos correspondientes.
- Mantener internamente `demo_run_id`, auditoría, bloqueo de envíos a las cuentas sembradas, bloqueo de IA facturable y exclusión del predictor. Esos mecanismos no aparecerán como avisos del catálogo. Retirar el aviso no convierte el historial sembrado en transacciones reales.
- La limpieza editorial se realiza por campos y referencias conocidas, no mediante un reemplazo global sobre conversaciones, archivos privados o evidencia del cliente. Los datos originales de auditoría permanecen técnicos; la presentación utiliza títulos y etiquetas legibles y evita volcar JSON.

## 7. Actualizar todos los seeders y el historial ya instalado

**Problema detectado:** `DemoScenarioLoader` y `DemoIntegrityAudit` comparan el hash del manifiesto `operational-demo-v1`. Editar sus JSON y repetir `byecode:seed-demo` no actualiza la instalación: produciría una discrepancia de integridad. Además, las solicitudes iniciales utilizan referencias `DEMO-{modelo}-NEG-M`.

### Fuente única y nuevas instalaciones

1. Crear `database/seeders/fixtures/catalog-v2/catalog.json` y su manifiesto de imágenes. Encapsular la carga en `ProductCatalogSeeder`, usando el servicio de catálogo existente y claves comerciales estables.
2. Preparar `operational-demo-v2` para referenciar ese catálogo por claves, sin copias divergentes de especificaciones ni atributos de stock. Resolver claves a IDs al ejecutar, sin IDs numéricos codificados en fixtures.
3. Adaptar `OperationalDemoSeeder`, `DemoScenarioLoader`, `DemoPostgresLoader` y `DemoIntegrityAudit` para seleccionar explícitamente la versión de fixture. Mantener la versión original reconocible y auditable.
4. Revisar solicitudes, versiones, borradores, mensajes de guion, adjuntos, referencias y hechos de perfil que describan prendas. Las variantes de cada caso deben coincidir con lo que se solicitó, cotizó y pagó. Los seis adjuntos de conversación no sustituyen las 48 imágenes públicas.
5. Distribuir ejemplos de la nueva carga entre modelos, colores y tallas. Incluir solicitudes con varias tallas/colores y cubrir las 36 variantes en el conjunto, conciliando precios, cantidades, versiones y pagos. Conservar los recorridos R2/R3/R4, aceptación parcial y cambios de versión.
6. `DatabaseSeeder` deberá orquestar solo lo que corresponda a cada entorno; el historial operativo seguirá siendo optativo. `InitialAdminSeeder` y `CommerceTeamSeeder` conservarán cuentas, roles y contraseñas, sin recrearlas para cargar productos.

### Instalación actual

1. Crear un comando específico propuesto `byecode:upgrade-catalog --dry-run` que prepare un informe: productos actuales, correspondencias a catálogo v2, colisiones, imágenes faltantes y campos que cambiarán. No ejecutar este comando hasta implementarlo.
2. Aplicar la actualización en una transacción, con bloqueo, revisión de la versión esperada y registro duradero de versión/hash de actualización. Conservar el hash original de `operational-demo-v1`; no sustituirlo para hacer pasar la auditoría.
3. Actualizar los 36 productos y seis fichas **en sus IDs actuales**, incorporando nombres, galería, códigos comerciales y limpieza de campos retirados. Registrar alias de los SKUs anteriores para resolver referencias históricas sin mostrarlos al comprador.
4. Reconciliar referencias en solicitudes/borradores y en la presentación de cotizaciones. Para las versiones emitidas conservar la configuración y todos los hechos económicos; añadir recursos visuales por correspondencia exacta. Si no hay correspondencia exacta, reportar el conflicto sin reasignar la prenda.
5. Conservar las cuentas, CRM, 34 casos iniciales, 46 versiones, 27 pagos, mensajes, adjuntos, evidencia y cualquier avance posterior. Estos conteos son la base del seeder; no se fuerza que la base viva siga teniendo exactamente ese número.
6. Repetir la actualización solo verifica el resultado y conserva ediciones posteriores. Una colisión o cambio del usuario requiere resolución por registro; no borrar la ejecución y cargarla otra vez. El nuevo seeder no se ejecutará sobre las mismas cuentas como si fueran una instalación vacía.

## 8. Interruptor de tema en el encabezado

- Crear un componente reutilizable `ThemeSwitch` con sol/luna y estado accesible de interruptor.
- Visitante: **a la derecha de «Crear cuenta»**. Sesión iniciada: en el mismo extremo derecho, junto a las acciones de cuenta. Mantenerlo visible en móvil.
- Integrarlo en `Journey` y los encabezados usados por el espacio autenticado; revisar también las pantallas de acceso/registro. No exigir entrar a configuración.
- Reutilizar `useAppearance`, cookie y almacenamiento actuales. Respetar la preferencia inicial del sistema; el primer cambio explícito elige claro u oscuro y se conserva al navegar/recargar.
- Retirar el acceso duplicado de apariencia en ajustes si deja de tener función; mantener compatibilidad de la ruta antigua mediante redirección si corresponde. Conservar el arranque de tema que evita parpadeos.
- La preferencia de tema no altera imágenes ni colores de prenda. Ajustar el soporte visual de la galería para distinguir Negro y Natural en ambos fondos.

## 9. Orden de implementación y archivos principales

| Fase           | Trabajo                                                                                | Cierre verificable                                                         |
| -------------- | -------------------------------------------------------------------------------------- | -------------------------------------------------------------------------- |
| 0 — completada | Actualización remota, preservación de cambios locales y diagnóstico                    | `main` sincronizado; 6/36 confirmados por lectura                          |
| 1              | Manifiesto canónico, mapa de SKUs y contrato de configuración                          | 36 variantes inequívocas, 12 conjuntos de imágenes, 48 rutas previstas     |
| 2              | Generación y revisión de imágenes                                                      | 48 PNG transparentes utilizables, sin huecos                               |
| 3              | Extender catálogo y retirar stock del contrato/backend; preparar migraciones y upgrade | Configuración validada; cotización sin inventario; actualización ensayable |
| 4              | Cuadrícula, ficha, colores, cantidades y carrito inferior                              | Flujo navegable completo con selección persistente                         |
| 5              | Limpieza de menciones y switch de tema                                                 | Interfaz coherente en visitante, cliente, comercio y administrador         |
| 6              | Seeders v2 y actualización auditada de la instalación                                  | Productos y todas sus referencias conciliados, historial conservado        |
| 7              | Comprobaciones acotadas, compilación y documentación                                   | Criterios de entrega satisfechos y evidencia breve registrada              |

Áreas existentes a editar: `CatalogService.php`, `CatalogController.php`, `RequestController.php`, `CustomerJourney.php`, `QuoteService.php`, `DraftService.php`, `CommerceController.php`, `ConversationController.php`, `Product.php`, `SemanticContract.php`, `DecisionValidator.php`, dominio Demo y sus comandos; `journey/catalog.tsx`, `catalog-manage.tsx`, `request.tsx`, `quote.tsx`, `byecode/quote-form.tsx`, tipos, layouts, encabezados y `use-appearance.tsx`.

Añadir únicamente la ficha, componentes del catálogo, manifiestos/recursos, seeder compartido, migraciones incrementales y comando de actualización necesarios. Actualizar `GUIA_USO.md`, `GUIA_DEMO.md`, `ESTADO_DESARROLLO.md` y el brief del catálogo cuando la implementación exista. No atribuir a este plan resultados de implementación.

Impeccable no tiene `buildPath` registrado. Para este alcance se propone implementación directa en los componentes existentes, con las imágenes de producto como recursos reales; una maqueta previa de página queda como alternativa opcional, sin cambiar ahora la configuración global ni añadir una ronda de diseño innecesaria.

## 10. Verificación breve y útil

Priorizar funcionamiento y datos correctos. Adaptar pruebas existentes afectadas; no crear una prueba por fotografía, talla, texto o clase CSS, ni repetir la suite completa después de cada ajuste.

**Comprobaciones automatizadas esenciales, agrupadas:**

1. Un verificador del manifiesto recorre las 36 variantes y las 48 imágenes: existencia, alfa, atributos, cobertura completa y referencias sin duplicados incorrectos. No necesita 48 tests independientes.
2. Actualizar los casos pertinentes de `CustomerJourneyTest` y `WorkflowTest`: selección multivariante, persistencia/autenticación, cantidades y productos publicados, solicitud idempotente y cotización sin campos de stock. Sustituir las aserciones de inventario obsoletas.
3. Extender `OperationalDemoTest` con un escenario de actualización de v1: IDs y totales conservados, repetición segura, conflicto explícito y continuidad de protecciones de correo/IA/predictor. Reutilizar los escenarios existentes para nuevas instalaciones.
4. Ajustar la comprobación del contrato semántico que dependía de stock y confirmar que entrega/autorización siguen escalándose cuando falta evidencia. No efectuar llamadas reales a OpenAI.

Ejecutar Pest focalizado mientras se construye; al terminar, una pasada de los controles existentes de CI y build. Repetir únicamente lo que falle o cambie después. La base de pruebas sigue siendo SQLite aislado; la actualización real en Supabase se valida aparte, sin reiniciarlo ni avanzar pedidos del usuario.

**Recorrido de navegador en una ronda conjunta:** escritorio y móvil, claro y oscuro; abrir los seis modelos, recorrer ambos colores, distribuir tallas, añadir repetidos, editar/eliminar, recargar, iniciar sesión y enviar una solicitud en entorno de verificación. Confirmar continuidad a borrador y cotización, galerías correctas, switch, teclado/foco y ausencia de desbordamientos. Corregir lo encontrado en un lote y realizar como máximo una ronda adicional de confirmación.

## 11. Definición de terminado

- [ ] Los seis modelos y las 36 variantes están completos y publicados con atributos coherentes.
- [ ] Existen 48 recursos transparentes; cada variante resuelve sus cuatro vistas y ninguna ficha sembrada muestra «Fotografía pendiente».
- [ ] Color seleccionado, cuadro HEX, prenda y miniatura del carrito coinciden.
- [ ] El catálogo se explora por modelo y se configura; no expone una tabla de todas las combinaciones.
- [ ] El carrito se despliega desde abajo, admite edición y se recupera tras acceso/recarga.
- [ ] Se puede solicitar y cotizar el conjunto completo de 36 variantes sin el antiguo tope de 30.
- [ ] Stock y disponibilidad desaparecieron de contratos activos, formularios, persistencia operativa, IA activa y nuevos seeders.
- [ ] No aparecen avisos demo, códigos `DEMO-*`, etiquetas repetidas «Regular» ni datos técnicos en el recorrido del aplicativo.
- [ ] Las salvaguardas de procedencia continúan funcionando internamente.
- [ ] El switch de tema aparece en el encabezado y persiste en todas las pantallas previstas.
- [ ] Las cargas nuevas y la actualización instalada utilizan el mismo catálogo; no se perdieron cuentas, cotizaciones, pagos, mensajes ni cambios del usuario.
- [ ] Pasan las comprobaciones relevantes, el build y el recorrido visual; documentación actualizada sin afirmar llamadas o precisión de IA inexistentes.

**Estado de esta entrega:** se actualizó el repositorio y se creó este plan. Todavía no se generaron las imágenes ni se cambiaron la interfaz, el esquema o los seeders.
