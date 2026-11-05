<?php

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Yappy_V2_Blocks_Support extends AbstractPaymentMethodType {
    protected $name = 'yappy_v2';

    public function initialize() {
        $this->settings = get_option( 'woocommerce_yappy_v2_settings', array() );
    }

    public function is_active() {
        if ( 'yes' !== $this->get_setting( 'enabled', 'no' ) || ! WC_Yappy_V2_Config::ready() ) {
            return false;
        }
        $supported = apply_filters( 'wc_yappy_v2_supported_currencies', array( 'USD', 'PAB' ) );
        return in_array( get_woocommerce_currency(), $supported, true );
    }

    public function get_payment_method_script_handles() {
        wp_register_script(
            'wc-yappy-v2-blocks',
            WC_YAPPY_V2_URL . 'assets/js/blocks.js',
            array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ),
            WC_YAPPY_V2_VERSION,
            true
        );
        return array( 'wc-yappy-v2-blocks' );
    }

    public function get_payment_method_script_handles_for_admin() {
        return $this->get_payment_method_script_handles();
    }

    public function get_payment_method_data() {
        return array(
            'title'       => $this->get_setting( 'title', 'Yappy' ),
            'description' => $this->get_setting( 'description', __( 'Paga de forma rápida y segura con Yappy.', 'woocommerce-yappy-v2' ) ),
            'buttonLabel' => __( 'Continuar con Yappy', 'woocommerce-yappy-v2' ),
            'supports'    => array( 'products' ),
        );
    }
}
