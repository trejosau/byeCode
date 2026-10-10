---
name: ByeCode / BLANKS
description: Mesa de trabajo comercial monocroma con decisiones y evidencia visibles.
colors:
    background: 'oklch(1 0 0)'
    foreground: 'oklch(0.145 0 0)'
    primary: 'oklch(0.205 0 0)'
    primary-foreground: 'oklch(0.985 0 0)'
    muted: 'oklch(0.97 0 0)'
    muted-foreground: 'oklch(0.556 0 0)'
    border: 'oklch(0.922 0 0)'
    ring: 'oklch(0.87 0 0)'
    sidebar: 'oklch(0.985 0 0)'
    destructive: 'oklch(0.577 0.245 27.325)'
    dark-background: 'oklch(0.145 0 0)'
    dark-foreground: 'oklch(0.985 0 0)'
    dark-primary: 'oklch(0.985 0 0)'
    dark-primary-foreground: 'oklch(0.205 0 0)'
    dark-muted: 'oklch(0.269 0 0)'
    dark-muted-foreground: 'oklch(0.708 0 0)'
    dark-border: 'oklch(0.269 0 0)'
    dark-ring: 'oklch(0.439 0 0)'
    dark-sidebar: 'oklch(0.205 0 0)'
    dark-destructive: 'oklch(0.396 0.141 25.723)'
    dark-destructive-foreground: 'oklch(0.637 0.237 25.331)'
typography:
    headline:
        fontFamily: "'Instrument Sans', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji'"
        fontSize: '1.5rem'
        fontWeight: 600
        lineHeight: '2rem'
        letterSpacing: '-0.025em'
    headline-wide:
        fontSize: '1.875rem'
        fontWeight: 600
        lineHeight: '2.25rem'
        letterSpacing: '-0.025em'
    title:
        fontSize: '1.125rem'
        fontWeight: 600
        lineHeight: '1.75rem'
    panel-title:
        fontSize: '1rem'
        fontWeight: 600
        lineHeight: 1
    body:
        fontSize: '0.875rem'
        fontWeight: 400
        lineHeight: 1.625
    table-body:
        fontSize: '0.875rem'
        fontWeight: 400
        lineHeight: '1.25rem'
    control:
        fontSize: '0.875rem'
        fontWeight: 500
        lineHeight: '1.25rem'
    label:
        fontSize: '0.75rem'
        fontWeight: 400
        lineHeight: '1rem'
    badge:
        fontSize: '0.75rem'
        fontWeight: 500
        lineHeight: '1rem'
rounded:
    sm: '6px'
    md: '8px'
    lg: '10px'
    xl: '12px'
spacing:
    '1': '4px'
    '2': '8px'
    '3': '12px'
    '4': '16px'
    '5': '20px'
    '6': '24px'
    '7': '28px'
    '8': '32px'
    '10': '40px'
components:
    button-primary:
        backgroundColor: '{colors.primary}'
        textColor: '{colors.primary-foreground}'
        typography: '{typography.control}'
        rounded: '{rounded.md}'
        padding: '8px 16px'
        height: '36px'
    button-primary-hover:
        backgroundColor: 'color-mix(in oklab, oklch(0.205 0 0) 90%, transparent)'
    button-outline:
        backgroundColor: '{colors.background}'
        textColor: '{colors.foreground}'
        typography: '{typography.control}'
        rounded: '{rounded.md}'
        padding: '8px 16px'
        height: '36px'
    button-ghost:
        textColor: '{colors.foreground}'
        typography: '{typography.control}'
        rounded: '{rounded.md}'
        padding: '8px 16px'
        height: '36px'
    button-link:
        textColor: '{colors.primary}'
        typography: '{typography.control}'
        rounded: '{rounded.md}'
        padding: '8px 16px'
        height: '36px'
    input:
        textColor: '{colors.foreground}'
        rounded: '{rounded.md}'
        padding: '4px 12px'
        height: '36px'
        width: '100%'
    chip-secondary:
        backgroundColor: '{colors.muted}'
        textColor: '{colors.primary}'
        typography: '{typography.badge}'
        rounded: '{rounded.md}'
        padding: '2px 8px'
    chip-outline:
        textColor: '{colors.foreground}'
        typography: '{typography.badge}'
        rounded: '{rounded.md}'
        padding: '2px 8px'
    card-flat:
        backgroundColor: '{colors.background}'
        textColor: '{colors.foreground}'
        rounded: '{rounded.xl}'
        padding: '24px'
    navigation-item:
        textColor: '{colors.foreground}'
        rounded: '{rounded.md}'
        padding: '8px'
        height: '32px'
    recommendation-panel:
        backgroundColor: '{colors.background}'
        textColor: '{colors.foreground}'
        rounded: '{rounded.xl}'
        padding: '24px'
---

# Design System: ByeCode / BLANKS

## Overview

**Creative North Star: "Mesa de trabajo comercial"**

ByeCode usa una superficie sobria de trabajo: blanco, grises neutros y negro, navegación lateral compacta, tablas legibles y decisiones acompañadas por contexto. La jerarquía depende de la tipografía, el espacio y los bordes; los estados se expresan con palabras y etiquetas discretas. La interfaz está en español y los importes del negocio se presentan en MXN.

El sistema describe la implementación Laravel, Inertia, React, TypeScript, Tailwind CSS y shadcn/ui observada el 10 de octubre de 2026. La referencia vinculante es Figma `janyEh3129fn9yUeb4bn3Z`, nodo `0:1`. El código concreta esa dirección con Instrument Sans y esquinas suaves; los valores del mockup no se convierten en datos de producción. La ausencia de datos, interpretación o validación se muestra de forma explícita, sin sustituirla por probabilidades ilustrativas.

**Key Characteristics:**

- Paleta neutra con rojo reservado para errores y restricciones que requieren atención.
- Una familia sans serif, cifras tabulares y jerarquía compacta.
- Bordes finos, tarjetas planas y controles con relieve mínimo.
- Evidencia, origen y límites visibles junto a la decisión.
- Composición adaptable que conserva el acceso al caso y al panel de decisión.

Fuentes de extracción: `resources/css/app.css`; `resources/js/components/ui/{button,input,textarea,badge,card,table,sidebar,alert}.tsx`; `resources/js/components/byecode/*.tsx`; `resources/js/pages/dashboard.tsx`; `resources/js/pages/byecode/*.tsx`; valores de las utilidades instaladas en `node_modules/tailwindcss/theme.css`. Contexto: `PRODUCT.md` y `.impeccable/surface-contract.md`. Los tokens del frontmatter conservan OKLCH, el formato normativo del CSS. Los nombres `dark-*` registran los valores de `.dark`; en la aplicación se consumen mediante las mismas variables semánticas, no mediante nuevos nombres CSS.

Evidencia visual: `.impeccable/review/figma-reference.jpg`, `desktop.jpg`, `mobile.jpg`, `user-1280.jpg`, `detail-desktop.jpg`, `detail-mobile.jpg` y `portal-desktop.jpg`. El `ship` de `.impeccable/review/verdict.md` cubre exclusivamente cuatro correcciones: legibilidad de los casos móviles, posición móvil de la decisión, alcance de los indicadores y aviso de IA sin truncamiento. No constituye certificación integral de accesibilidad, del tema oscuro, del dominio ni del modelo. No hay un modelo predictivo real entrenado y validado acreditado por estas evidencias.

## Colors

El negro cálido aparente y los grises acromáticos mantienen el foco en el contenido, con superficies blancas separadas por líneas suaves.

### Primary

- **Negro de acción — `primary`:** acciones principales, con `primary-foreground` para su texto.
- **Rojo de incidencia — `destructive`:** mensajes de validación, integridad y acciones destructivas. Es una excepción funcional al monocromo, no un acento comercial.

### Neutral

- **Blanco de trabajo — `background`:** lienzo y tarjetas. Los tokens CSS `card` y `popover` comparten este valor en cada tema.
- **Tinta — `foreground`:** títulos, importes y contenido principal. `card-foreground` y `popover-foreground` comparten su valor.
- **Gris de apoyo — `muted`:** superficies de estados y hover; `secondary` y `accent` comparten este valor. Su texto usa los valores de primer plano correspondientes del CSS.
- **Gris de lectura auxiliar — `muted-foreground`:** fechas, explicaciones, metadatos y rótulos.
- **Línea — `border`:** límites y divisiones de un píxel. `input` y `sidebar-border` comparten su valor.
- **Foco — `ring`:** anillo visible de teclado; no se usa para comunicar estados comerciales.
- **Marco lateral — `sidebar`:** área de navegación, ligeramente distinta del lienzo.

El tema oscuro mantiene estas funciones y cambia los valores mediante `.dark`. La preferencia del usuario o del sistema determina la apariencia; esta documentación no ordena forzar el tema claro. Las capturas disponibles prueban la presentación clara, no una revisión visual completa de la oscura.

**The Estado con palabras Rule.** Todo estado comercial conserva una etiqueta textual; el color no sustituye estado, origen, vigencia ni explicación.

## Typography

**Display Font:** no existe una familia de exhibición separada.  
**Body Font:** Instrument Sans con la pila de respaldo registrada en `headline`, heredada por todos los roles.  
**Label/Mono Font:** las etiquetas usan la misma familia; no se establece una fuente monoespaciada para el producto.

**Character:** una voz sans serif legible y directa. Los títulos usan peso semibold y tracking estrecho; el cuerpo y los metadatos tienen menos contraste visual sin convertirse en decoración.

### Hierarchy

- **Headline / headline-wide:** título de página y total destacado; el título pasa del rol base al amplio desde `md`. La decisión y las métricas del resumen usan el tamaño base.
- **Title:** títulos de sección como la bandeja y el historial.
- **Panel-title:** títulos compactos de tarjetas; el componente conserva interlínea unitaria.
- **Body:** párrafos explicativos, con interlínea relajada.
- **Table-body:** filas, valores y contenido compacto.
- **Control:** botones y etiquetas de control con peso medio.
- **Label:** metadatos y ayudas; las insignias usan este tamaño con peso medio, no el tamaño de control.

Las páginas de trabajo activan `font-variant-numeric: tabular-nums`; las columnas económicas se alinean a la derecha. Los campos Input usan texto de 16 px antes de `md` y texto de 14 px desde ese punto.

**The Jerarquía sobria Rule.** Crear jerarquía con tamaño, peso, espacio y contenido; conservar la misma familia y evitar titulares promocionales en el espacio operativo.

## Layout

El ritmo parte de la unidad de 4 px de Tailwind. La página compartida tiene separación vertical de 28 px y padding de 20 px, que aumenta a 32 px desde `md` y a 40 px desde `lg`. El encabezado permite envolver acciones, deja 24 px antes de su divisor y limita el bloque de texto a 42 rem.

La navegación utiliza una anchura base de 16 rem en escritorio, una hoja móvil de 18 rem y una base de 3 rem en modo de iconos; los márgenes del marco inset se aplican además. La barra lateral de escritorio comienza en `md`. El contenido principal conserva `min-width: 0` donde convive con columnas.

Los puntos de adaptación instalados son `sm` (40 rem), `md` (48 rem), `lg` (64 rem), `xl` (80 rem) y `2xl` (96 rem). El hook móvil cambia en 768 px. Con raíz de 16 px corresponden a 640, 768, 1024, 1280 y 1536 px.

Patrones observados, sin convertir una pantalla en plantilla obligatoria para todas:

- **Resumen:** bloque de indicadores de dos columnas que pasa a cuatro en `xl`; cada celda tiene 20 px de padding y separaciones de un píxel.
- **Bandeja:** tabla desde `md`, con cabecera de 44 px y celdas de 16 px de padding. Antes de `md`, cada caso presenta identidad, importe, estado, decisión y enlace en un artículo apilado.
- **Detalle:** columna flexible y panel de 360 px desde `xl`, separados por 28 px. El panel se fija a 24 px del borde superior. En una columna aparece inmediatamente después de las métricas y antes del historial.
- **Formularios auxiliares:** paneles de 340 px en clientes/importaciones desde `xl`; catálogo de 400 px desde `2xl`. No son un ancho universal.
- **Portal:** contenedor máximo de 72 rem, padding horizontal de 20 px, resumen de 320 px desde `lg` y separación de 28 px. El formulario y la acción de impresión se excluyen de la impresión.

## Elevation & Depth

El sistema es plano en las superficies comerciales y usa líneas y tono para agrupar. Las tarjetas de ByeCode eliminan la sombra que el componente shadcn Card trae por defecto. Los botones e inputs conservan una sombra mínima; el marco inset del escritorio usa la sombra pequeña heredada.

### Shadow Vocabulary

- **Control mínimo — shadow-xs:** `0 1px 2px 0 rgb(0 0 0 / 0.05)`.
- **Marco inset — shadow-sm:** `0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1)`.
- **Tarjeta comercial:** `box-shadow: none`.

**The Superficie plana Rule.** Las tarjetas comerciales se separan con fondo, borde y espacio; no heredan automáticamente la sombra predeterminada de Card.

La entrada de `workspace-page` desplaza el contenido 3 px hasta su posición final durante 180 ms con `ease-out`, únicamente cuando no se ha solicitado movimiento reducido. Los controles heredan transiciones breves de Tailwind; la expansión lateral usa 200 ms lineales. No se deduce de esto una auditoría de todas las animaciones heredadas.

## Shapes

Los contenedores principales usan esquinas suaves `xl`; avisos usan `lg`; botones, campos, insignias e ítems de navegación usan `md`. La base CSS es `--radius: 0.625rem`: `lg` toma esa base, `md` resta 2 px y `sm` resta 4 px. `xl` conserva el valor instalado de Tailwind. Estas conversiones asumen raíz de 16 px.

Los bordes son finos y continuos. Los vacíos usan borde discontinuo y un icono lineal; las barras de presupuesto usan extremos redondos. Los iconos son SVG de Lucide, habitualmente de 16 px, con 12 px en metadatos y 24–32 px en marca o vacíos; no son glifos tipográficos.

## Components

### Buttons

Controles compactos con etiqueta explícita. El primario usa negro de acción; outline conserva fondo y línea; ghost se integra en la superficie; link subraya al pasar el puntero. El botón normal mide 36 px; small, 32 px; large, 40 px. Con un SVG directo, el padding horizontal normal se reduce de 16 a 12 px. El hover del primario reduce el fondo al 90 %; outline y ghost usan accent. El foco visible añade borde ring y anillo de 3 px al 50 %. Un botón deshabilitado baja al 50 % de opacidad y no recibe eventos de puntero. No hay escala ni desplazamiento decorativo al pulsar.

### Chips

Etiquetas compactas con borde, padding de 2 px por 8 px, texto de 12 px y peso 500. Secondary señala un estado sobre gris; outline identifica procedencia, reglas y evidencia. Los enlaces dentro de insignias conservan hover y foco. La etiqueta «Kit sintético» sigue acompañando a sus casos.

### Cards / Containers

Tarjetas blancas, borde de un píxel y radio `xl`. El componente distribuye 24 px de padding vertical y 24 px horizontal en cabecera/contenido, con 24 px entre bloques; en las superficies comerciales la sombra está anulada. Los contenedores de tabla recortan el borde exterior, mientras la tabla genérica permite desplazamiento horizontal. La bandeja de casos añade su representación móvil propia.

### Inputs / Fields

Input usa fondo transparente, borde input, radio `md`, altura de 36 px y padding de 4 px por 12 px. Placeholder usa muted-foreground. El foco visible añade borde ring y anillo de 3 px al 50 %; `aria-invalid` utiliza borde destructive y anillo al 20 % en claro, 40 % en oscuro. Disabled conserva cursor y opacidad de indisponibilidad.

Textarea usa altura mínima de 96 px, padding de 8 px por 12 px, texto de 14 px y anillo de foco de 2 px al 40 %. El contenedor Field mantiene etiqueta asociada, ayuda y error separado con `role="alert"`. No unificar esos dos anillos como si el código ya fuera idéntico.

### Navigation

Navegación lateral con iconos lineales, texto de 14 px e ítems normales de 32 px de alto. Hover, pulsación y ruta activa usan sidebar-accent; la ruta activa tiene peso 500. El foco es un anillo de 2 px. El modo reducido conserva iconos y tooltips; en móvil se usa una hoja lateral. La selección de escritorio observada es gris claro, aunque el mockup de referencia usa un bloque negro.

### Siguiente decisión

El panel reúne acción principal, fuente, estado de publicación, momento, motivo, confianza cualitativa, riesgo, información pendiente y enlaces a eventos. Secciones de alternativas y utilidad pueden desplegarse. La confianza en una decisión no representa probabilidad de compra. Una estimación predictiva solo se muestra si existe información habilitada por la aplicación; el estado vacío explica la ausencia de un modelo validado.

### Avisos y vacíos

Los avisos usan borde, icono y texto, sin bandas de color de marketing. El aviso de conexión de IA del resumen permite varias líneas en su título. Los vacíos tienen altura mínima de 224 px, padding de 32 px, título, explicación y una acción cuando corresponde. Mantener estados pendientes comprensibles en lenguaje comercial.

## Do's and Don'ts

### Do:

- **Do** conservar Instrument Sans, la escala compacta y los tokens semánticos para que ambos temas mantengan sus funciones.
- **Do** acompañar estados y decisiones con texto, origen y evidencia accesible.
- **Do** alinear importes a la derecha y conservar cifras tabulares en el espacio de trabajo.
- **Do** presentar el caso completo y su acción en móvil, y situar la decisión después de las métricas.
- **Do** distinguir indicadores del espacio operativo, kits sintéticos y consumo de toda la instalación.
- **Do** expresar ausencia de datos o validación con un estado explícito y texto legible.

### Don't:

- **Don't** convertir cifras, porcentajes, urgencias o monedas ilustrativas de Figma en hechos del producto.
- **Don't** usar el rojo de incidencia como decoración o sustituir etiquetas de estado por color.
- **Don't** añadir sombras grandes o gradientes a las tarjetas comerciales de este sistema.
- **Don't** recortar avisos necesarios para comprender el estado ni ocultar la acción del caso en una tabla móvil.
- **Don't** presentar confianza cualitativa o aperturas de una propuesta como una probabilidad de compra validada.
- **Don't** extender el `ship` de cuatro correcciones a una certificación global.

No canonizado ni reparado: los tamaños aislados de 11 px en metadatos de tabla/catálogo quedan fuera de la escala reutilizable; los colores chart heredados sin uso observado no definen una paleta analítica; otras instancias heredadas de AlertTitle conservan truncamiento que no debe extrapolarse a avisos extensos. Estas divergencias se registran sin modificar implementación. La compatibilidad oscura procede del código y queda pendiente de evidencia visual propia.
