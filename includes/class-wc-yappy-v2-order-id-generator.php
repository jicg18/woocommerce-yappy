<?php

defined( 'ABSPATH' ) || exit;

final class WC_Yappy_V2_Order_ID_Generator {
    public function generate( $order_id, $attempt ) {
        $value = 'Y' . $this->base36( (int) $order_id ) . 'A' . $this->base36( (int) $attempt );
        $value = strtoupper( preg_replace( '/[^A-Z0-9]/i', '', $value ) );

        if ( strlen( $value ) > 15 ) {
            throw new WC_Yappy_V2_Exception( __( 'No fue posible generar un identificador Yappy válido para este pedido.', 'woocommerce-yappy-v2' ), 'E009' );
        }

        return $value;
    }

    private function base36( $number ) {
        $number = max( 0, (int) $number );
        if ( 0 === $number ) {
            return '0';
        }

        $digits = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $out    = '';
        while ( $number > 0 ) {
            $out    = $digits[ $number % 36 ] . $out;
            $number = intdiv( $number, 36 );
        }
        return $out;
    }
}
