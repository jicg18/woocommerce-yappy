<?php

defined( 'ABSPATH' ) || exit;

final class WC_Yappy_V2_Amount_Mapper {
    public function from_order( WC_Order $order ) {
        $total    = round( (float) $order->get_total(), 2 );
        $discount = round( (float) $order->get_discount_total(), 2 );
        $taxes    = round( (float) $order->get_total_tax(), 2 );
        $subtotal = round( $total + $discount - $taxes, 2 );

        if ( $total <= 0 || $subtotal < 0 || $discount < 0 || $taxes < 0 ) {
            throw new WC_Yappy_V2_Exception( __( 'Los montos del pedido no son válidos para Yappy.', 'woocommerce-yappy-v2' ), 'E010' );
        }

        return array(
            'discount' => $this->money( $discount ),
            'taxes'    => $this->money( $taxes ),
            'subtotal' => $this->money( $subtotal ),
            'total'    => $this->money( $total ),
        );
    }

    private function money( $value ) {
        return number_format( (float) $value, 2, '.', '' );
    }
}
