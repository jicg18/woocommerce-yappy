<?php
/**
 * Plugin Name: WooCommerce Yappy V2
 * Plugin URI:  https://www.vibrasport.com/
 * Description: Pasarela de pago Yappy V2 para WooCommerce, compatible con Checkout clásico, Checkout Block y HPOS.
 * Version:     0.1.0
 * Author:      Vibrasport
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woocommerce-yappy-v2
 * Domain Path: /languages
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * WC requires at least: 8.3
 */

defined( 'ABSPATH' ) || exit;

define( 'WC_YAPPY_V2_VERSION', '0.1.0' );
define( 'WC_YAPPY_V2_FILE', __FILE__ );
define( 'WC_YAPPY_V2_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_YAPPY_V2_URL', plugin_dir_url( __FILE__ ) );

require_once WC_YAPPY_V2_DIR . 'includes/class-wc-yappy-v2-activator.php';
register_activation_hook( __FILE__, array( 'WC_Yappy_V2_Activator', 'activate' ) );

add_action(
    'before_woocommerce_init',
    static function () {
        if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
        }
    }
);

add_action(
    'plugins_loaded',
    static function () {
        if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Payment_Gateway' ) ) {
            add_action(
                'admin_notices',
                static function () {
                    if ( current_user_can( 'activate_plugins' ) ) {
                        echo '<div class="notice notice-error"><p>' . esc_html__( 'WooCommerce Yappy V2 requiere WooCommerce activo.', 'woocommerce-yappy-v2' ) . '</p></div>';
                    }
                }
            );
            return;
        }

        $files = array(
            'class-wc-yappy-v2-config.php',
            'class-wc-yappy-v2-logger.php',
            'class-wc-yappy-v2-exception.php',
            'class-wc-yappy-v2-api-client.php',
            'class-wc-yappy-v2-transaction-repository.php',
            'class-wc-yappy-v2-hash-validator.php',
            'class-wc-yappy-v2-order-id-generator.php',
            'class-wc-yappy-v2-amount-mapper.php',
            'class-wc-yappy-v2-payment-token.php',
            'class-wc-yappy-v2-payment-service.php',
            'class-wc-gateway-yappy-v2.php',
            'class-wc-yappy-v2-rest-controller.php',
            'class-wc-yappy-v2-plugin.php',
        );

        foreach ( $files as $file ) {
            require_once WC_YAPPY_V2_DIR . 'includes/' . $file;
        }

        WC_Yappy_V2_Plugin::instance()->boot();
    },
    20
);
