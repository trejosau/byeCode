---
version: 1
slug: 'resources-js-pages-journey-customers-tsx'
primary_target: 'resources/js/pages/journey/customers.tsx'
related_targets: ['resources/js/pages/journey/profile.tsx']
---

# CRM de clientes

Modo: Operate. Equipo comercial compara clientes y revisa su perfil con evidencia; administración consulta perfiles en lectura.

## Direction contract

THESIS: Lista compacta para comparar relaciones, consulta lateral para orientarse y perfil dedicado para corregir con evidencia.

OWN-WORLD: Instrument Sans, blanco/grises/negro y tokens existentes de ByeCode en claro y oscuro. Bordes suaves, jerarquía compacta, cifras tabulares; sin colores por supuesta calidad del cliente.

STORY: Buscar y filtrar → comparar → consultar resumen → revisar dimensión → guardar con evidencia e historial.

FIRST VIEWPORT: Navegación lateral existente, cabecera corta, búsqueda y filtros en una barra, tabla con cliente, tipo/escala, recurrencia, solicitudes, pagado y actividad. Consulta lateral de lectura. Móvil usa fichas y hoja de ancho completo.

FORM: Adaptación directa de la tabla y el panel de https://sales-crm-kargulstudio.vercel.app/ al mundo existente. No se requiere concept-seed: estructura especificada por referencia y plan aprobado.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

Alcance: /customers y /customers/{customer}, más componentes específicos y consultas de presentación. El resto del aplicativo, reglas comerciales, permisos, presupuesto y esquema se conserva. Propiedades desconocidas no generan categorías; hipótesis y confirmaciones se distinguen. No hay probabilidades ni puntuaciones nuevas. Orden/paginación en servidor y sin API REST separada.
