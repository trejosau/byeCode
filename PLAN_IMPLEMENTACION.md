# ByeCode — plan de implementación ejecutable

Fecha: 10 de octubre de 2026. Alcance autorizado: construir una versión local usable, sin Docker, sobre el stack existente. Este documento registra el plan ejecutado; los resultados verificados están en ESTADO_DESARROLLO.md y el recorrido en GUIA_USO.md. La calidad real de GPT y el entrenamiento con historial comercial permanecen pendientes de clave y datos, respectivamente.

## Fuentes y precedencia

Se recuperaron las ocho páginas de Notion solicitadas, conservadas en `referencias/notion/00.md` a `07.md`, en el mismo orden de los enlaces del usuario. Se inspeccionó visualmente el archivo Figma `janyEh3129fn9yUeb4bn3Z`, página 0:1: resumen, bandeja, detalle comercial, listado y detalle del portal. Notion y R1–R6 gobiernan el comportamiento; Figma gobierna composición y navegación. Los ejemplos CLP, porcentajes predictivos, urgencia y botones de ejecución del diseño no autorizan inventar datos ni enviar contactos.

## Entrega y criterios

1. **Dominio y datos.** Migraciones Laravel incrementales en `byecode`, aislamiento por propietario de espacio, conjuntos de datos separados para negocio y kit, importación JSONL con errores por línea, deduplicación global por conjunto, detección de conflictos, conservación del original y perfil explícito. Orden de ingesta para proyección; fecha real para métricas. Defaults oficiales, parches parciales, cierre terminal, P06 anterior a apertura, reloj fijo solo en kit. Criterio: reproducción inicial y actualización sin pérdida ni duplicación.
2. **Motor confiable.** Instantáneas inmutables, R1–R6 antes y después de IA, contrato exacto de once campos, evidencia local al caso y corte, señales con citas verificadas, exclusiones por acción, explicación basada en trazas, historial y caducidad. Criterio: nunca publicar contacto bloqueado, no disfrazar fallos como inacción.
3. **IA económica.** Responses API desde Laravel, modelo pequeño configurable con tarifa conocida, salida estructurada, sin herramientas ni efectos externos. Presupuesto reservado atómicamente antes de llamar, límites diario/semanal y de contexto/salida, caché por versión, una llamada por cambio relevante, sin escalamiento automático a un modelo caro. Fallos y costos inciertos conservan reserva. Criterio: configurar únicamente OPENAI_API_KEY para habilitar el proveedor; pruebas automáticas sin consumo.
4. **Colas y recuperación.** Evaluaciones pendientes como outbox duradero; jobs idempotentes; agenda de reevaluación, control de versión al publicar y en lectura/exportación, fallos visibles y reintento explícito. No mantener transacción durante llamada HTTP. Criterio: reinicio y respuesta tardía no duplican ni publican estado viejo.
5. **Operación B2B.** Clientes, catálogo de blanks, cotizaciones y líneas versionadas, precios en centavos, modalidades inventario/fabricación/mixto, datos operativos desconocidos explícitos, permisos y revisión humana, feedback separado de ejecución, portal con acceso limitado y mensajes. No inventar MOQ, capacidad, impuestos, descuentos o aceptación comercial automática.
6. **Interfaz.** Resumen con métricas observadas, bandeja con filtros, detalle con historial/evidencia/restricciones, importaciones y simulador del kit, consumo y estado de IA, catálogo/clientes/cotización y portal. shadcn/ui, Inertia, español, moneda MXN y fechas con zona. Estados vacíos, pendientes, error, obsoleto y revisión humana. No botones decorativos sin comportamiento.
7. **Predicción.** Implementar una ruta de entrenamiento/evaluación reproducible con resultado y horizonte explícitos, separación temporal, casos abiertos censurados, métricas de calibración y comparación contra baseline. Bloquear porcentajes cuando falten datos o validación. El kit de seis casos no entrena ni valida un predictor de cierre; GPT interpreta y recomienda, no certifica probabilidades.
8. **Validación y entrega.** Pest (dominio, contrato, aislamiento, concurrencia lógica, proveedor simulado, presupuesto, ciclo P06), pruebas semánticas versionadas y comando de evaluación real optativo, tipos/lint/build, PostgreSQL real sin reinicios, recorrido de navegador, documentación de arranque y límites. Conservar kit original y lockfiles. Verificar la interfaz en escritorio y móvil y revisar el acabado según Impeccable.

## Presupuesto operativo propuesto

Presupuesto de la aplicación: 160 MXN por siete días móviles y 22 MXN por 24 horas móviles; dejar 40 MXN del saldo declarado como margen para conversión, impuestos u otros consumos. Usar una conversión conservadora configurable de 25 MXN/USD para contabilidad preventiva, no declararla tipo de cambio real. La reserva cubre el máximo de entrada y salida con margen; el consumo sin confirmación no se libera automáticamente. Las tarifas son configuración del servidor, no campos editables por el navegador. El límite controla esta aplicación, no otras llamadas hechas con la misma cuenta. No se puede prometer una semana ilimitada: al llegar al límite se pausa IA y se conservan datos, interfaz y reglas.

## Decisiones para avanzar sin preguntas

- Un espacio privado por cuenta Laravel; no asumir pertenencia a una empresa por correo ni añadir usuarios ajenos automáticamente.
- Las reglas comerciales no definidas quedan como datos desconocidos o validaciones humanas, nunca defaults inventados.
- Datos de demo claramente sintéticos y separados de los reales. Importar la actualización es un paso distinto de cargar el kit inicial.
- Registro manual de acciones exige confirmar que ya ocurrieron y aportar referencia; aprobar una recomendación no crea `action.executed`.
- La demo y las pruebas gratuitas usan proveedor simulado identificado. La calidad real del modelo queda pendiente de ejecutar la batería con una clave; no se contabilizan mocks como precisión de IA.
- Hosting y despliegue público quedan fuera de esta entrega local porque no se ha elegido proveedor. No publicar datos del proyecto.

## Orden y cierre

Datos → reglas y contratos → evaluaciones/presupuesto → operación B2B → vistas y portal → predicción/evaluación → pruebas y documentación. Cada bloque deberá tener evidencia verificable antes de marcarlo completado. No declarar perfección ni rendimiento comercial no medido.
