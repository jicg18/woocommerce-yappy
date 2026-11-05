<?php

defined( 'ABSPATH' ) || exit;

final class WC_Yappy_V2_Config {
    const OPTION_KEY = 'woocommerce_yappy_v2_settings';

    public static function all() {
        $settings = get_option( self::OPTION_KEY, array() );
        return is_array( $settings ) ? $settings : array();
    }

    public static function get( $key, $default = '' ) {
        $settings = self::all();
        return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
    }

    public static function merchant_id() {
        if ( defined( 'WC_YAPPY_MERCHANT_ID' ) && WC_YAPPY_MERCHANT_ID ) {
            return (string) WC_YAPPY_MERCHANT_ID;
        }
        return trim( (string) self::get( 'merchant_id', '' ) );
    }

    public static function secret_key() {
        if ( defined( 'WC_YAPPY_SECRET_KEY' ) && WC_YAPPY_SECRET_KEY ) {
            return trim( (string) WC_YAPPY_SECRET_KEY );
        }
        return trim( (string) self::get( 'secret_key', '' ) );
    }

    public static function domain() {
        if ( defined( 'WC_YAPPY_DOMAIN' ) && WC_YAPPY_DOMAIN ) {
            return untrailingslashit( esc_url_raw( (string) WC_YAPPY_DOMAIN ) );
        }

        $domain = trim( (string) self::get( 'domain', '' ) );
        if ( '' === $domain ) {
            $domain = home_url();
        }
        return untrailingslashit( esc_url_raw( $domain ) );
    }

    public static function environment() {
        return 'production' === self::get( 'environment', 'test' ) ? 'production' : 'test';
    }

    public static function api_base_url() {
        return 'production' === self::environment()
            ? 'https://apipagosbg.bgeneral.cloud'
            : 'https://api-comecom-uat.yappycloud.com';
    }

    public static function cdn_url() {
        return 'production' === self::environment()
            ? 'https://bt-cdn.yappy.cloud/v1/cdn/web-component-btn-yappy.js'
            : 'https://bt-cdn-uat.yappycloud.com/v1/cdn/web-component-btn-yappy.js';
    }

    public static function debug_enabled() {
        return 'yes' === self::get( 'debug', 'no' );
    }

    public static function ready() {
        return '' !== self::merchant_id() && '' !== self::secret_key() && '' !== self::domain();
    }

    public static function theme() {
        $allowed = array( 'blue', 'darkBlue', 'orange', 'dark', 'sky', 'light' );
        $theme   = (string) self::get( 'theme', 'blue' );
        return in_array( $theme, $allowed, true ) ? $theme : 'blue';
    }

    public static function rounded() {
        return 'yes' === self::get( 'rounded', 'yes' );
    }
}
