<?php

defined( 'ABSPATH' ) || exit;

final class WC_Yappy_V2_Logger {
    public static function log( $level, $message, array $context = array() ) {
        if ( 'debug' === $level && ! WC_Yappy_V2_Config::debug_enabled() ) {
            return;
        }

        if ( ! function_exists( 'wc_get_logger' ) ) {
            return;
        }

        $safe = self::sanitize_context( $context );
        $line = $message;
        if ( ! empty( $safe ) ) {
            $line .= ' ' . wp_json_encode( $safe );
        }

        wc_get_logger()->log( $level, $line, array( 'source' => 'yappy-v2' ) );
    }

    private static function sanitize_context( array $context ) {
        $blocked = array( 'secret', 'secret_key', 'token', 'documentName', 'document_name', 'hash', 'authorization' );
        $out     = array();

        foreach ( $context as $key => $value ) {
            if ( in_array( strtolower( (string) $key ), array_map( 'strtolower', $blocked ), true ) ) {
                continue;
            }

            if ( in_array( $key, array( 'aliasYappy', 'alias_yappy', 'phone' ), true ) ) {
                $digits      = preg_replace( '/\D+/', '', (string) $value );
                $out[ $key ] = strlen( $digits ) >= 4 ? substr( $digits, 0, 2 ) . '****' . substr( $digits, -2 ) : '****';
                continue;
            }

            $out[ $key ] = is_scalar( $value ) || null === $value ? $value : '[complex]';
        }

        return $out;
    }
}
