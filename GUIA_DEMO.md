# Historial de demostración de ByeCode

El seeder reproduce nueve meses de trabajo en las tablas reales de la aplicación. Las cuentas pueden entrar, conversar, continuar cotizaciones y enviar nuevas solicitudes. Los datos se identifican como demostración y no entrenan el predictor ni generan correos o consumo de OpenAI.

## Entrar y elegir un recorrido

Abre [ByeCode local](http://127.0.0.1:8000/login). Todos los correos siguientes usan la contraseña de prueba indicada por el usuario, guardada únicamente en `DEMO_CUSTOMER_PASSWORD` del `.env` local. Las cuentas iniciales están verificadas y no requieren cambiar esa contraseña para probar. El seeder no restablece una contraseña que cambies posteriormente.

| Cuenta            | Nombre          | Cliente           | Historial inicial        | Casos abiertos   |
| ----------------- | --------------- | ----------------- | ------------------------ | ---------------- |
| marina@gmail.com  | Marina Salazar  | Lumbre Estudio    | 2 aceptados, 1 rechazado | A01, A02         |
| diego@gmail.com   | Diego Montes    | Ruta Básica       | 3 aceptados              | A03              |
| valeria@gmail.com | Valeria Ríos    | Aula Norte        | 1 aceptado, 1 cancelado  | A04              |
| rodrigo@gmail.com | Rodrigo Vega    | Foro Naranja      | 1 rechazado              | A05              |
| camila@gmail.com  | Camila Duarte   | Tinta Clara       | 1 aceptado, 1 rechazado  | A06              |
| andres@gmail.com  | Andrés Molina   | Suministros Prado | 4 aceptados              | A07, A08         |
| paula@gmail.com   | Paula Serrano   | Casa Laurel       | 1 aceptado, 1 cancelado  | A09              |
| ricardo@gmail.com | Ricardo Paredes | Taller Vértice    | 2 aceptados              | A10              |
| elena@gmail.com   | Elena Campos    | Colectivo Sur     | 1 aceptado               | A11              |
| mateo@gmail.com   | Mateo Navarro   | Punto Nube        | 1 aceptado, 1 rechazado  | A12              |
| daniela@gmail.com | Daniela Fuentes | Todavía sin CRM   | Sin solicitudes          | Crear la primera |
| nicolas@gmail.com | Nicolás Torres  | Todavía sin CRM   | Sin solicitudes          | Crear la primera |

Para empezar desde cero, entra como Daniela o Nicolás, selecciona variantes en **Blanks** y envía una solicitud. Eso crea su ficha CRM; las solicitudes siguientes reutilizan esa ficha. Para trabajar con historial, empieza como Marina, Diego o Camila.

Jorge, Dani, Jesús y Sau conservan sus cuentas y comparten el CRM operativo. Alberto conserva su administrador de lectura. Esta carga no cambia sus contraseñas, requisitos de primer acceso ni roles.

## Casos que puedes continuar

Los enlaces concretos de esta instalación están en [el informe local de enlaces](.local/demo-links.md); requieren iniciar sesión con el rol y cliente correspondientes. También puedes buscar el cliente o título en Solicitudes y CRM.

| Clave | Responsable | Situación inicial                                                                      | Siguiente prueba                                                               |
| ----- | ----------- | -------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------ |
| A01   | Jorge       | Marina aceptó la mitad de V2, sujeta a muestra; todavía sin formalización ni pagos     | Revisar condiciones y publicar la versión que corresponda a lo acordado        |
| A02   | Sin asignar | Nueva solicitud de Marina, sin cotización                                              | Asignar responsable, preparar y publicar la primera versión                    |
| A03   | Dani        | Diego aceptó V1, formalización completa y anticipo del 30 %                            | Registrar el saldo pendiente y cerrar con evidencia                            |
| A04   | Jesús       | Valeria pidió esperar a una fecha situada dos días después de la carga                 | Comprobar R3; el mensaje no autoriza contactar antes de esa fecha              |
| A05   | Sau         | Rodrigo pidió seis prendas, por debajo del MOQ; borrador sin precio y datos pendientes | Completar datos, acordar cantidades/excepción y publicar V1                    |
| A06   | Jorge       | Camila pidió cambiar color y cantidad sobre V2                                         | Publicar V3 y probar una nueva decisión de cliente                             |
| A07   | Dani        | Andrés prohibió nuevos contactos para este pedido                                      | Verificar R2; las otras oportunidades de Andrés siguen siendo independientes   |
| A08   | Jesús       | Otra solicitud de Andrés tiene V1 retirada y borrador pendiente                        | Completar el borrador y publicar V2; V1 ya no se puede aceptar                 |
| A09   | Sau         | Paula consultó la propuesta tres veces, sin mensajes en ese caso                       | Verificar que las vistas no implican aceptación, urgencia ni pago              |
| A10   | Jorge       | Ricardo recibió un contacto seis horas antes de la carga y no respondió                | Comprobar R4; una respuesta posterior del comprador levanta esa pausa          |
| A11   | Dani        | Elena tiene una versión vencida y fecha y términos pendientes de renovación            | Confirmar fecha y condiciones y publicar una versión vigente                   |
| A12   | Jesús       | Mateo respondió después del último contacto                                            | Comprobar que R4 se levanta, conservando la interpretación semántica pendiente |

Las fechas avanzan con el reloj real después de la carga. R3/R4 pueden haber vencido cuando abras la guía. El informe guarda la fecha de referencia original: repetir el comando no rejuvenece los casos ni reinicia sus plazos.

## Qué contiene

- 12 cuentas, 10 fichas CRM, 34 solicitudes y casos: 22 cerrados y 12 abiertos.
- 46 versiones publicadas y dos borradores internos; aceptar, formalizar, pagar y cerrar son hechos separados.
- 17 formalizaciones y 27 pagos. Los 16 pedidos históricos aceptados concilian cotización, formalización y pagos; A03 conserva saldo pendiente.
- 149 mensajes, seis adjuntos PNG privados y originales de eventos conservados con hash.
- Seis estilos y 36 variantes con tallas, composición, gramaje, MOQ, instrucciones y recursos visuales y referencias de ficha. El catálogo público sigue sin precios.
- Siete dimensiones comerciales por ficha, desconocidos explícitos, evidencia propia y autor de las confirmaciones reproducidas. Camila tiene hipótesis, corrección y conflicto; Diego cambió el empaque; Paula retiró un requisito; Marina conserva una propiedad con alcance de colección.
- Evaluaciones históricas y actuales del motor de reglas, revisiones humanas reproducidas y feedback. El ranking usa el contrato vigente de decisiones por evento.

Las hipótesis son parte del guion sintético y llevan origen `demo_scenario`; no son respuestas de un modelo. Los casos con mensajes que necesitan interpretación muestran **Análisis no habilitado para este registro**. Las restricciones conocidas sí pueden producir recomendaciones de contingencia sujetas a revisión. No se inventan porcentajes ni resultados de OpenAI.

## Procedencia y protecciones internas

Los listados muestran todos los registros autorizados, sin filtros ni avisos de procedencia. El valor antiguo `demo_origin` se retira de la sesión. La marca interna se conserva para auditoría y protección del correo, IA y predictor.

Con `BYECODE_DEMO_ENABLED=true` y `BYECODE_DEMO_RUN=operational-demo-v1`, las nuevas altas de clientes de este entorno local también son de prueba. Las cuentas existentes conservan su procedencia. Desactivar estos indicadores impide nuevas altas demo, pero no elimina la marca ni las protecciones de las cuentas ya creadas. El catálogo de ejemplo continúa publicado hasta que comercio cambie su publicación.

Los mensajes privados de la aplicación se guardan y se ven normalmente. Las notificaciones y recuperaciones por correo para destinatarios demo quedan como `suppressed_demo`, sin cuerpo cifrado pendiente de envío. No se mandan mensajes a las direcciones Gmail ficticias. Los análisis de perfil y evaluaciones demo permanecen sin llamadas facturables aunque se configure una clave real después.

## Catálogo v2 y compatibilidad

Las nuevas instalaciones usan `operational-demo-v2` y el catálogo compartido `catalog-v2`. Las solicitudes de v2 cubren las 36 variantes y conservan los recorridos R2/R3/R4, versiones, pagos y aceptación parcial. Los archivos originales de `operational-demo-v1` permanecen sellados.

La instalación v1 existente se actualiza con `byecode:upgrade-catalog`; no se vuelve a sembrar sobre sus cuentas. Su hash original se conserva. `byecode:verify-demo` reconoce ambas versiones y verifica por defecto todas las ejecuciones listas. El respaldo y las correspondencias quedan privados y cifrados. Ver [CATALOGO_INTEGRACION.md](CATALOGO_INTEGRACION.md).

## Ejecutar o comprobar

En una instalación local con el equipo de comercio ya creado, configura los tres valores anteriores en `.env`; la contraseña inicial debe tener al menos ocho caracteres. No incluyas ese secreto en archivos versionados.

```powershell
rtk php artisan byecode:setup
rtk php artisan byecode:seed-demo --dry-run
rtk php artisan byecode:seed-demo
rtk php artisan byecode:verify-demo
```

`OperationalDemoSeeder` también puede invocarse con `db:seed --class=OperationalDemoSeeder`. Está separado de `DatabaseSeeder` y bloqueado fuera de `local`/`testing`.

La primera ejecución comprueba cuentas, SKUs y manifiesto; reproduce todo en una transacción y audita antes de confirmar. Un error revierte las altas y retira únicamente los archivos creados por esa carga. Una ejecución fallida se puede reintentar conservando su identidad y reloj. Una interrupción que deje estado `loading` requiere revisar la ejecución anterior; no hay un reinicio destructivo automático.

Para Supabase, el comando reserva IDs exclusivos y reproduce primero los servicios de negocio en una SQLite temporal privada. Después transfiere las filas validadas en lotes, con esos mismos IDs, y vuelve a auditar dentro de una transacción de PostgreSQL antes de confirmar. Esto evita miles de consultas remotas repetidas; la aplicación continúa usando PostgreSQL normalmente. La preparación temporal se elimina al terminar. Las reservas pueden dejar saltos en los IDs si falla un intento; no se reutilizan ni se retroceden secuencias.

Repetir una ejecución terminada solo verifica: no reescribe mensajes, precios, perfiles, estados, fechas, contraseñas ni avances del usuario. Los correos existentes ajenos al escenario provocan un conflicto antes de crear cuentas. La auditoría detecta archivos o registros iniciales ausentes; no los recrea silenciosamente. No usar `migrate:fresh` con Supabase.

El reporte estructurado de esta instalación está en [.local/demo-report.json](.local/demo-report.json). Los comandos también imprimen conteos y enlaces sin contraseñas. El manifiesto versionado está en `database/seeders/fixtures/operational-demo-v1`.

## Verificación

Las pruebas aisladas cubren repetición con cambios de usuario, nuevas solicitudes y propagación de procedencia, publicación desde A02/A05/A06/A08 hasta pago/cierre, permisos de adjuntos, bloqueos de contacto R2/R3/R4, evidencia temporal, filtros e indicadores, aislamiento de correo/IA/predictor y reversión con limpieza de archivos seguida de reintento. Los escenarios cargados en Supabase se conservan para tus pruebas; las pruebas que los avanzan se realizan en SQLite aislado.
