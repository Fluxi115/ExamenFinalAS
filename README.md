```markdown
# FoodOrder (servicios separados)

Descripción
- Aplicación demo para controlar pedidos en un restaurante.
- Arquitectura orientada a servicios dentro del mismo proyecto: cada responsabilidad tiene su propio endpoint en public/services:
  - public/services/orders -> servicio de Pedidos (CRUD)
  - public/services/kitchen -> servicio de Cocina (acciones: start, ready, deliver)
  - public/services/billing -> servicio de Facturación (usa Adapter para pagar)

Compatibilidad
- Los endpoints legacy en public/api/* siguen presentes y reenvían a los nuevos servicios para mantener compatibilidad.

Requisitos
- PHP 7.4+ con PDO MySQL
- MySQL (o MariaDB)
- Navegador moderno

Estructura
- sql/schema.sql — script para crear DB y tabla de pedidos con datos de ejemplo.
- src/db.php — conexión PDO.
- src/adapter/ThirdPartyPayment.php — clase simulada de pago incompatible.
- src/adapter/ThirdPartyPaymentAdapter.php — adaptador para la clase anterior.
- public/services/orders/index.php — API de pedidos.
- public/services/kitchen/index.php — API de cocina (acciones).
- public/services/billing/index.php — API de facturación.
- public/api/* — proxies a los servicios (compatibilidad).
- public/index.php — frontend que consume los servicios.

Instalación y ejecución rápida
1. Crear la base de datos:
   mysql -u tu_usuario -p < sql/schema.sql

2. Configurar conexión:
   - Edita `src/db.php` y pon tus credenciales.

3. Ejecutar servidor de desarrollo:
   - Desde la raíz del proyecto:
     php -S localhost:8000 -t public
   - Abrir en el navegador:
     http://localhost:8000/

Endpoints principales
- GET  /services/orders/index.php                 -> listar pedidos (opcional ?id=, ?mesa=, ?cliente=)
- POST /services/orders/index.php                 -> crear pedido (JSON: mesa, cliente, platillo, total)
- PUT  /services/orders/index.php?id={id}         -> actualizar pedido (JSON campos: mesa, cliente, platillo, total, estado)
- DELETE /services/orders/index.php?id={id}       -> eliminar pedido
- POST /services/kitchen/index.php                -> { order_id, action=start|ready|deliver }
- POST /services/billing/index.php                -> { order_id } -> usa Adapter para procesar pago y marca facturado

Notas
- En producción cada servicio puede vivir en su contenedor/proceso independiente.
- Para notificaciones en tiempo real puedes agregar SSE o WebSockets en el futuro.
```