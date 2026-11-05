=== WooCommerce Yappy V2 ===
Contributors: vibrasport
Tags: woocommerce, yappy, payments, panama
Requires at least: 6.9
Requires PHP: 7.4
WC requires at least: 8.3
Stable tag: 0.1.0
License: GPLv2 or later

Pasarela Yappy V2 para WooCommerce con Checkout clásico, Checkout Block, HPOS e IPN firmado.

== Instalación ==
1. Instala y activa WooCommerce.
2. Sube el ZIP desde Plugins > Añadir plugin > Subir plugin.
3. Activa WooCommerce Yappy V2.
4. Ve a WooCommerce > Ajustes > Pagos > Yappy V2.
5. Configura Ambiente, Merchant ID, Clave secreta y Dominio exactamente como aparecen en Yappy Comercial.
6. Guarda y usa "Probar conexión con Yappy".
7. Realiza primero pruebas UAT antes de habilitar Producción.

== Credenciales opcionales por wp-config.php ==
define( 'WC_YAPPY_MERCHANT_ID', '...' );
define( 'WC_YAPPY_SECRET_KEY', '...' );
define( 'WC_YAPPY_DOMAIN', 'https://www.ejemplo.com' );

Las constantes tienen prioridad sobre los ajustes guardados en WordPress.

== Seguridad ==
- Las llamadas a validate/merchant y payment-wc se ejecutan en el backend.
- La clave secreta nunca se envía al navegador.
- El IPN valida HMAC-SHA256 sobre orderId + status + domain.
- Se utiliza una tabla de intentos independiente y APIs CRUD de WC_Order para HPOS.
- eventSuccess del navegador nunca marca por sí solo una orden como pagada; la autoridad es el IPN firmado.

== Importante antes de producción ==
Yappy documenta subtotal, discount, taxes y total, pero no un campo separado de shipping. Este plugin incorpora envío y fees netos dentro de subtotal mediante: subtotal = total + discount - taxes. Valida este caso en UAT con la configuración real de tu comercio.

Documentación oficial Yappy:
https://www.yappy.com.pa/comercial/desarrolladores/boton-de-pago-yappy-nueva-integracion/

Documentación WooCommerce Blocks:
https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks/checkout-payment-methods/payment-method-integration

== Changelog ==
= 0.1.0 =
* Primera versión funcional.
* Yappy V2 API UAT/Producción.
* Checkout clásico y Checkout Block.
* HPOS.
* IPN HMAC-SHA256.
* Reintentos e idempotencia básica.
* Logs WooCommerce.
