<?php

defined( 'ABSPATH' ) || exit;

final class WC_Yappy_V2_Plugin {
    private static $instance;
    private $blocks_loaded = false;

    public static function instance() {
        if ( ! self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function boot() {
        load_plugin_textdomain( 'woocommerce-yappy-v2', false, dirname( plugin_basename( WC_YAPPY_V2_FILE ) ) . '/languages' );
        if ( get_option( 'wc_yappy_v2_db_version' ) !== WC_YAPPY_V2_VERSION ) {
            WC_Yappy_V2_Activator::activate();
        }
        add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateway' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( WC_YAPPY_V2_FILE ), array( $this, 'action_links' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest' ) );
        add_action( 'wp_ajax_wc_yappy_test_connection', array( $this, 'test_connection' ) );
        add_action( 'woocommerce_blocks_loaded', array( $this, 'load_blocks' ) );
        $this->load_blocks();
    }

    public function action_links( $links ) {
        $url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=yappy_v2' );
        array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Configurar', 'woocommerce-yappy-v2' ) . '</a>' );
        return $links;
    }

    public function register_gateway( $gateways ) {
        $gateways[] = 'WC_Gateway_Yappy_V2';
        return $gateways;
    }

    public function register_rest() {
        ( new WC_Yappy_V2_REST_Controller() )->register_routes();
    }

    public function load_blocks() {
        if ( $this->blocks_loaded || ! class_exists( '\\Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType' ) ) {
            return;
        }
        $this->blocks_loaded = true;
        require_once WC_YAPPY_V2_DIR . 'includes/class-wc-yappy-v2-blocks-support.php';
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            static function ( $registry ) {
                $registry->register( new WC_Yappy_V2_Blocks_Support() );
            }
        );
    }

    public function test_connection() {
        check_ajax_referer( 'wc_yappy_test_connection' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'No autorizado.', 'woocommerce-yappy-v2' ) ), 403 );
        }
        if ( ! WC_Yappy_V2_Config::ready() ) {
            wp_send_json_error( array( 'message' => __( 'Guarda primero Merchant ID, clave secreta y dominio.', 'woocommerce-yappy-v2' ) ), 400 );
        }

        try {
            ( new WC_Yappy_V2_API_Client() )->validate_merchant();
            wp_send_json_success( array( 'message' => __( '✓ Conexión con Yappy establecida.', 'woocommerce-yappy-v2' ) ) );
        } catch ( WC_Yappy_V2_Exception $e ) {
            $message = WC_Yappy_V2_Payment_Service::customer_message( $e->get_yappy_code(), $e->getMessage() );
            if ( $e->get_yappy_code() ) {
                $message .= ' (' . $e->get_yappy_code() . ')';
            }
            wp_send_json_error( array( 'message' => $message ), 400 );
        }
    }
}
