# Extensión del sistema ByeCode: CRM y perfiles

Fecha: 10 de octubre de 2026. Documentación de la implementación revisada, limitada a `/customers` y `/customers/{customer}`. Se conserva el mundo visual «Mesa de trabajo comercial». Este registro complementa el contrato de superficie; no sustituye ni actualiza `DESIGN.md` o `.impeccable/design.json`.

## Autoridad y evidencia

Se contrastaron `PRODUCT.md`, `DESIGN.md`, `.impeccable/design.json`, `.impeccable/surfaces/resources-js-pages-journey-customers-tsx.md`, la referencia Impeccable `reference/document.md` y los cuatro artefactos de implementación: `resources/js/pages/journey/customers.tsx`, `resources/js/pages/journey/profile.tsx`, `resources/js/components/byecode/crm.tsx` y `resources/js/components/byecode/commercial-profile.tsx`.

Se inspeccionaron directamente las capturas `crm-directory-light.jpg`, `crm-profile-dark.jpg`, `crm-profile-mobile-light.jpg` y `crm-focus-light.jpg`. El registro complementario `crm-verdict.md` acredita la inspección independiente de directorio y perfil en ambos temas y tamaños, vista rápida oscura en escritorio/móvil, y foco en ambos temas. Son capturas de la aplicación con datos sintéticos de verificación; los nombres, importes y clasificaciones no acreditan actividad real. No se incorporan imágenes de stock, ilustraciones ni activos raster al producto: estos JPG son evidencia de revisión, no material de interfaz.

El veredicto vigente es **ship**, con el hallazgo de foco resuelto. Las pruebas funcionales, TypeScript y build fueron comunicados por el implementador y consignados por el revisor; este paso documental no los ejecutó de nuevo. Las capturas no verifican por sí solas persistencia, navegación de teclado completa, lectores de pantalla ni calidad del modelo.

## Continuidad con el sistema incumbente

La extensión reutiliza Instrument Sans, tokens semánticos de claro/oscuro, negro/grises/blanco, iconos lineales, bordes finos, radios suaves y controles existentes. Conserva `AppLayout`, navegación lateral, botones shadcn y la hoja lateral. La tabla y las secciones del perfil son planas: agrupan mediante borde, fondo y espacio, sin sombras decorativas o colores asociados a la calidad del cliente.

Las cifras usan números tabulares e importes MXN; solicitudes, formalización, pagos y promedio se presentan como hechos separados. «Por confirmar» expresa ausencia y «Hipótesis» identifica inferencias. La evidencia y el origen acompañan a las clasificaciones; no se crean puntuaciones, probabilidades ni condiciones comerciales a partir del tamaño o el pago.

## Decisiones locales vigentes

- **Directorio para comparar.** Cabecera breve, búsqueda, tipo y escala visibles desde `sm`, filtros adicionales desplegables y filtros activos retirables. La tabla desde `md` reúne identidad, tipo/escala, recurrencia, solicitudes, pagado y actividad. Antes de `md`, fichas verticales conservan identidad y acciones. El orden se aplica al conjunto en servidor y la paginación muestra hasta 24 clientes.
- **Vista rápida para orientarse.** Hoja de lectura con identidad, contexto y acceso al perfil completo. Conserva la consulta del directorio y devuelve el foco al disparador al cerrar. La versión móvil ocupa el ancho disponible. No reemplaza la edición con evidencia del perfil.
- **Perfil para revisar.** Indicadores al principio; navegación entre perfil comercial, actividad/evidencia y oportunidades. En `xl`, contenido flexible más columna contextual de 280 px; en móvil se apilan. Las siete dimensiones se revisan de forma contextual, con evidencia seleccionable e historial. Necesidades particulares e hipótesis en conflicto conservan su contexto propio.
- **Densidad específica del CRM.** `CrmPage` usa separación de 24 px y padding de 16 px, que pasa a 24 px desde `md`; título de 24 px. Es una adaptación local respecto a la página compartida documentada en DESIGN (28 px de separación y padding 20/32/40 px), no una nueva escala global ni una orden de modificar otras páginas.
- **Accesibilidad y retorno.** Etiquetas asociadas, caption y encabezados de tabla, nombres de botones de icono, texto de estado, secciones identificadas y retorno al directorio filtrado. La lectura administrativa conserva su indicación y excluye las acciones de edición.
- **Foco local reforzado.** `crmControl` usa anillo de 2 px con `foreground`; `crmLink` y la navegación del perfil usan outline de 2 px con `foreground`. Es la reparación acotada del hallazgo del revisor. Mantiene el contraste en claro y oscuro sin cambiar el token global `ring` ni el sistema de componentes compartidos.

## Drift preexistente y límites de canonización

`DESIGN.md` es más reciente que el sidecar: las fechas de escritura observadas son 10/10/2026 09:57:00 y 09:56:59, respectivamente; el JSON declara además `generatedAt: 2026-10-10T09:32:01Z`. La marca temporal sola no identifica cambios semánticos. El sidecar conserva el relato y los ejemplos del sistema anterior y no representa estas composiciones CRM ni su foco local reforzado. Sus ejemplos de foco siguen usando `ring`, por lo que no deben emplearse como especificación del foco de estos controles CRM.

DESIGN conserva el alcance histórico de cuatro correcciones y señala que la evidencia oscura estaba pendiente; ese límite sigue siendo correcto para aquella revisión. Las capturas nuevas acreditan únicamente las superficies CRM indicadas, sin convertir la revisión en una certificación global. El patrón anterior de formularios auxiliares de clientes a 340 px tampoco debe imponerse al perfil actual con columna contextual de 280 px.

No se reparan estas divergencias documentales ni se canonizan excepciones globales. Tampoco se alteran las divergencias ya registradas en DESIGN sobre textos aislados de 11 px, colores chart o AlertTitle heredados. Una sincronización futura del sistema global requerirá su propio alcance; esta entrega registra la extensión donde corresponde.

## Inventario de evidencia de la extensión

En `.impeccable/review/`: `crm-directory-light.jpg`, `crm-directory-dark.jpg`, `crm-directory-mobile-light.jpg`, `crm-directory-mobile-dark.jpg`, `crm-profile-light.jpg`, `crm-profile-dark.jpg`, `crm-profile-mobile-light.jpg`, `crm-profile-mobile-dark.jpg`, `crm-preview-dark.jpg`, `crm-preview-mobile-dark.jpg`, `crm-focus-light.jpg` y `crm-focus-dark.jpg`. Decisión de acabado y reparación: `crm-verdict.md`.

La evidencia permite conservar esta distribución y su identidad visual. No implica validación de todas las rutas, accesibilidad integral, calidad semántica de IA ni entrenamiento predictivo.
