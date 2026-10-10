# Catálogo v2: operación e integración

Entrega final del 10 de octubre: instalados los 48 PNG existentes de la lista proporcionada por el usuario, sin generar recursos adicionales. `byecode:verify-catalog` aprobado. Se retiró el desplegable de variantes de la solicitud presencial: las prendas se configuran en el catálogo. En la edición comercial se eligen mediante una lista de botones conservando el ID interno, cantidades y cálculo de precios.

La fuente única es `database/seeders/fixtures/catalog-v2/catalog.json`: seis modelos, 36 variantes, Negro `#171717` y Natural `#EEE9DC`, S/M/L. Las imágenes se comparten por modelo/color; las cuatro vistas son `front`, `back`, `three-quarter` y `detail`.

## Carga y actualización

- Instalación nueva con equipo de comercio: `php artisan db:seed --class=ProductCatalogSeeder`.
- Historial optativo nuevo: `php artisan byecode:seed-demo --fixture=operational-demo-v2`. No ejecutarlo sobre las cuentas de v1.
- Instalación v1: `php artisan byecode:upgrade-catalog --dry-run`, revisar conflictos y luego `php artisan byecode:upgrade-catalog`.
- Auditoría de ejecuciones existentes: `php artisan byecode:verify-demo`; admite `--fixture=operational-demo-v1`.

La migración incremental retira el campo de inventario y sus claves JSON operativas. Guarda los valores anteriores y posteriores cifrados en `catalog_upgrades`. El actualizador conserva los IDs, el hash de v1, cuentas y hechos económicos; registra correspondencias de SKU y el contenido anterior cifrado. Repetirlo verifica y conserva las ediciones posteriores. No usar `migrate:fresh` ni recargar las mismas cuentas como una instalación vacía.

Antes de aplicarlo a una base existente: respaldar en privado, poner el sitio en mantenimiento, esperar trabajos en curso, aplicar migración/actualización y reiniciar workers con el código nuevo. El archivo local cifrado requiere la `APP_KEY` original para recuperarlo; no publicar el respaldo ni esa clave.

## Recursos visuales

Las rutas finales son `public/images/catalog/v1/{REG|OVR|HVE|LNG|CRW|HOD}/{negro|natural}/{vista}.png`. La producción de estos recursos se lleva en el chat de imágenes. No generar duplicados por talla ni sustituir prendas por tintes CSS.

`php artisan byecode:verify-catalog --data-only` comprueba atributos, combinaciones y rutas esperadas sin exigir archivos. `php artisan byecode:verify-catalog` exige las 48 imágenes, dimensiones declaradas y PNG con canal alfa; la inspección visual y transparencia efectiva se verifican además en la entrega de recursos. La validación de datos no equivale a aprobar imágenes ausentes.

El catálogo puede operar durante la entrega separada de imágenes: conserva sus referencias canónicas y no expone rutas locales ausentes. Al copiar los recursos correctos a esas rutas aparecen en la próxima carga, sin volver a sembrar ni tocar pedidos. Si cambia el manifiesto después de una actualización aplicada, crear una nueva versión de actualización; no reemplazar hashes instalados.

## Recorrido

El comprador filtra el catálogo, configura color y cantidades por talla y pulsa **Agregar a mi selección**. **Ver selección** abre el panel inferior para editar o eliminar. Agregar la misma variante suma cantidades. **Solicitar cotización** continúa al formulario o al acceso de cuenta y conserva la selección. El envío confirmado y el cierre de sesión la eliminan.

Selección, solicitud, borrador, publicación y aceptación parcial admiten hasta 100 líneas. Cada variante admite cantidades enteras hasta 1,000,000. La interfaz pública no muestra SKU, precios, inventario ni disponibilidad; las condiciones, vigencia, fechas acordadas y pagos siguen siendo parte del flujo privado.

El interruptor sol/luna del encabezado conserva la elección al navegar y recargar. La preferencia inicial sigue al sistema. La antigua ruta de apariencia redirige a los ajustes de perfil.
