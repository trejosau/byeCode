# Revisión de decisiones por evento

Fecha: 10 de octubre de 2026.

Alcance: detalle de caso, prioridad de acciones, exclusiones, comparación con la recomendación anterior y estado de probabilidad no disponible. Se conserva el sistema visual existente de ByeCode.

Se sirvió la aplicación real compilada desde un proceso temporal en `127.0.0.1:8097`, con base SQLite y almacenamiento separados dentro de `tmp`. Se usaron usuario, cliente, eventos y respuestas de IA sintéticos. No se utilizó el acceso del administrador ni se llamó al proveedor. La pestaña y el servidor temporales se cerraron después de la revisión.

Observaciones verificadas:

- Cambio de “Resolver una duda” a “Revisión interna” al registrar una prohibición de contacto.
- Ranking con revisión interna y espera, evidencia enlazada y momento sugerido.
- Las tres acciones de contacto quedan excluidas por R2; no reciben posición ni porcentaje.
- El porcentaje del caso aparece como no disponible al no existir un entrenamiento validado.
- Pantalla consultando cambios automáticamente, sin errores de consola observados.
- Anchos revisados: 1280 px y 390 px. El documento no supera el ancho de la ventana en ninguno de los dos.
- Texto, enlaces y tarjetas legibles en móvil y tema oscuro. No se cambió la preferencia de tema del usuario.

Capturas locales de la revisión: `tmp/decision-ranking-desktop.png`, `tmp/decision-ranking-mobile.png` y `tmp/decision-ranking-preview.png`. Son evidencia sintética de interfaz, no de desempeño del modelo. No se versionan la base temporal ni las sesiones.

El repaso visual detectó que evaluaciones pendientes antiguas podían dejar el botón en espera; se corrigió su sustitución por revisión/configuración y se cubrió mediante la prueba que procesa el contexto vigente una sola vez.

No constituye una auditoría completa de accesibilidad, una prueba semántica real ni una certificación estadística.
