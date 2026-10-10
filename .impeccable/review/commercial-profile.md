# Revisión del perfil comercial — 10 de octubre de 2026

- Entorno aislado: SQLite temporal y servidor 127.0.0.1:8001, sin SMTP ni llamadas a OpenAI. Las cuentas y los importes son sintéticos; no representan clientes reales.
- Dos clientes con $50,000 pagados mantienen estructuras diferentes: operación individual con decisión directa y cadena con proceso de compras.
- La lista muestra las diferencias, mantiene desconocidos como «Por confirmar» y filtra una cuenta corporativa mediante el control visible.
- En la ficha corporativa se seleccionaron muestras y calendario de suministro, se vinculó evidencia del cliente y se guardó correctamente. Los requisitos y su historial aparecieron actualizados.
- Capturas: `commercial-segments-desktop.jpg`, `commercial-profile-desktop.jpg` y `commercial-profile-mobile.jpg`.
- Revisión móvil a 390 × 844: contenido de 380 px, sin desbordamiento horizontal. Viewport restaurado y pestaña temporal cerrada al terminar.
- Sin errores de consola en la lista. La revisión usó el build compilado para aislarse del servidor Vite habitual.
- Verificación automatizada: 117 pruebas Pest / 633 aserciones, Larastan, TypeScript, formato/lint y compilación correctos. Los mocks de IA verifican contratos, no calidad semántica real.
