=== Datacil for WooCommerce ===
Contributors: datacil
Tags: ecuador, cedula, ruc, woocommerce, checkout
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Valida cedulas y RUC de Ecuador en WooCommerce y auto-completa datos del cliente desde el SRI y Registro Civil via Datacil API.

== Description ==

Datacil for WooCommerce conecta tu tienda con la API de Datacil para validar **cedulas (10 digitos)** y **RUC (13 digitos)** de Ecuador y rellenar automaticamente los datos del cliente (nombre, provincia, ciudad, direccion, email, telefono) durante el checkout.

**Caracteristicas:**

* Campo "Cedula o RUC" condicional: aparece solo cuando el pais de facturacion es Ecuador.
* Boton **Validar** inline que consulta la API y auto-completa los campos de billing (checkout clasico).
* Compatible con WooCommerce Blocks (solo validacion, sin autofill dentro del bloque).
* Guarda la identificacion en la orden, perfil de usuario, emails y columna de listado de pedidos (con y sin HPOS).
* Dashboard propio bajo WooCommerce → Datacil: balance de creditos, historial de consumo y costos por endpoint.
* Bloqueo opcional de duplicados: evita que dos clientes usen la misma cedula/RUC.

**Seguridad:**

* La API Key vive en la BD del server (nunca se envia al navegador).
* Llamadas AJAX del frontend protegidas con nonce de WP.
* Rate-limit por IP en el endpoint de validacion publico (30 req/min).
* Endpoints admin exigen capability `manage_woocommerce`.
* Compatible con restriccion `allowed_origins` de tu API Key Datacil (protege el token aunque se publique en codigo frontend).

**Requisitos:**

* Cuenta activa en Datacil (https://datacil.com) con API Key.
* WooCommerce 7.0+.

== Installation ==

1. Sube la carpeta `datacil-woocommerce` a `/wp-content/plugins/` o instala desde el menu Plugins → Añadir nuevo → Subir.
2. Activa el plugin desde Plugins.
3. Ve a **WooCommerce → Ajustes → Datacil** y completa URL, API Key y opciones.
4. Opcional: bajo **WooCommerce → Datacil** monitorea creditos, historial y costos.

== Frequently Asked Questions ==

= No aparece el campo Cedula/RUC en el checkout =

Verifica que el pais de facturacion sea Ecuador. Si usas el bloque Gutenberg de checkout, el campo aparece como parte del address form; si usas el shortcode clasico `[woocommerce_checkout]`, aparece el campo con el boton Validar inline.

= El boton Validar no aparece con el bloque de checkout =

Es una limitacion del API de WooCommerce Blocks. Para tener el boton + autofill completo, reemplaza el bloque por el shortcode `[woocommerce_checkout]` en tu pagina Checkout.

= Donde se guarda la API Key? =

En la tabla `wp_options` bajo `datacil_wc_settings`. Nunca se envia al navegador ni se incluye en respuestas AJAX publicas.

= Que sucede si la API rechaza mi key por Origin? =

Si en tu panel Datacil limitas tu API Key a ciertos origenes, agrega el dominio publico de tu tienda (ej. `https://mi-tienda.com`). Sin el origen permitido, la API responde 403.

== Screenshots ==

1. Pestaña de configuracion del plugin bajo WooCommerce → Ajustes → Datacil.
2. Campo Cedula/RUC con boton Validar en el checkout clasico.
3. Datos del cliente auto-completados tras validar.
4. Dashboard de creditos, historial y costos bajo WooCommerce → Datacil.
5. Cedula/RUC mostrada en el detalle de orden admin.
6. Columna Cedula/RUC en el listado de pedidos.

== Changelog ==

= 1.0.0 =
* Lanzamiento inicial.
* Campo VAT condicional al pais EC en checkout clasico y Blocks.
* Boton Validar + autofill (solo clasico).
* Dashboard creditos / historial / costos.
* Display en admin, emails, cuenta del cliente y perfil de usuario.
* Columna en listado de pedidos (legacy + HPOS).
* Rate-limit + nonce en endpoints AJAX.

== Upgrade Notice ==

= 1.0.0 =
Version inicial.
