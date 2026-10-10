# Revisión de acabado del CRM

## 1. Validez de evidencia

Revisión independiente realizada el 10 de octubre de 2026, sin navegador y sin modificar implementación. Se examinaron con `view_image` las diez capturas requeridas: directorio y perfil en claro/oscuro, escritorio/móvil, y vista rápida oscura en escritorio/móvil. Las capturas presentan datos sintéticos de verificación; no acreditan resultados reales del negocio. Los anchos de imagen de algunas capturas descontaron la barra de desplazamiento del viewport comunicado. No hay composición aprobada para comparar píxel a píxel: se aplica el contrato escrito de la superficie y el sistema existente de ByeCode. Se leyeron también DESIGN.md, PRODUCT.md, craft-floor y los componentes/páginas implicados.

La suite de 123 pruebas/770 aserciones, Pint, Larastan, TypeScript, build y las comprobaciones de interacción en navegador fueron comunicadas por el implementador; este revisor no las volvió a ejecutar. Las imágenes no prueban por sí solas teclado, lectores de pantalla, errores ni persistencia.

## 2. Cumplimiento del contrato

El resultado visible conserva identidad ByeCode, Instrument Sans, paleta neutra, navegación compacta, tabla comparativa, filtros y consulta lateral. El móvil cambia a lista vertical y hoja a ancho completo. El perfil ofrece secciones identificables, revisión por dimensión, enlaces a evidencia e historial, con distinción textual entre hipótesis, confirmación y desconocido. Las cantidades se identifican en MXN sin convertirse en puntuaciones comerciales. La vista rápida conserva una acción persistente para abrir el perfil y evidencia. No se observa importación de identidad o datos de la referencia ajena.

## 3. Evaluación visual y accesibilidad

La jerarquía, separación de grupos, cifras tabulares y superficies planas son coherentes con la dirección. Las diez capturas no muestran superposición ni pérdida material de contenido. La lista móvil reduce datos de comparación y permite recuperar el perfil completo mediante la vista rápida. La hoja desplaza su contenido conservando título, cierre y acción final. Los estados desconocidos, hipótesis y ausencia de IA son explícitos. El código incluye etiquetas, encabezados de tabla, nombres accesibles para botones de icono, reducción de movimiento y devolución del foco al disparador de la hoja.

El defecto inicial de contraste del indicador de foco se resolvió en una segunda inspección acotada, descrita a continuación. Esta revisión no certifica accesibilidad integral.

## 4. Hallazgos materiales

**P2 — Contraste insuficiente en el foco de teclado del CRM.** `crmControl` en `resources/js/components/byecode/crm.tsx` elimina el outline nativo y utiliza `focus-visible:ring-ring`; `crmLink` usa `focus-visible:outline-ring`. La navegación de secciones de `resources/js/pages/journey/profile.tsx` también usa ese token. En `resources/css/app.css`, `--ring: oklch(0.87 0 0)` sobre blanco tiene aproximadamente 1.48:1; el valor oscuro `oklch(0.439 0 0)` sobre `oklch(0.145 0 0)` da aproximadamente 2.54:1. Ambos quedan por debajo de 3:1 para el indicador visual del estado de estos controles. Al quitarse el indicador nativo, el usuario de teclado depende de esta señal tenue. Reparación acotada: usar un token de foco con contraste suficiente en los controles y enlaces específicos del CRM (por ejemplo foreground), conservando el sistema global. Verificar el foco por teclado en claro y oscuro tras el cambio.

**Estado del hallazgo: resolved.** La segunda inspección confirmó `focus-visible:ring-foreground` en `crmControl`, `focus-visible:outline-foreground` en `crmLink` y el mismo outline en la navegación del perfil. Con los tokens existentes, el contraste sobre background supera 18:1 en ambos temas. Se examinaron con `view_image` las capturas adicionales `crm-focus-dark.jpg` y `crm-focus-light.jpg`: muestran un contorno de foco inequívoco y continuo alrededor del selector Tipo de negocio, sin alteración de composición. El nuevo build aprobado fue comunicado por el implementador. No se modificaron tokens globales para reparar este hallazgo.

No quedan hallazgos materiales abiertos en el alcance revisado. No se requieren cambios de composición ni microajustes especulativos.

## 5. Disposición

**ship** — Hallazgo **resolved**, con corrección confirmada en código y evidencia visual adicional de foco en ambos temas. La composición revisada no requiere reconstrucción ni recaptura completa. El veredicto no cubre rutas ajenas a `/customers` y `/customers/{customer}`, accesibilidad integral, calidad semántica de IA ni entrenamiento predictivo.
