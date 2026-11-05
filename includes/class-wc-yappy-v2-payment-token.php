<?php

defined( 'ABSPATH' ) || exit;

final class WC_Yappy_V2_Payment_Token {
    public static function create( WC_Order $order ) {
        return hash_hmac( 'sha256', $order->get_id() . '|' . $order->get_order_key(), wp_salt( 'auth' ) );
    }

    public static function validate( WC_Order $order, $token ) {
        return hash_equals( self::create( $order ), (string) $token );
    }
}
