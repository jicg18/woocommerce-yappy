<?php

defined( 'ABSPATH' ) || exit;

final class WC_Yappy_V2_REST_Controller {
    private $repo;

    public function __construct() {
        $this->repo = new WC_Yappy_V2_Transaction_Repository();
    }

    public function register_routes() {
        register_rest_route( 'wc-yappy/v1', '/orders/(?P<order_id>\d+)/payment', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array( $this, 'create_payment' ),
            'permission_callback' => '__return_true',
            'args' => array( 'order_id' => array( 'validate_callback' => static function ( $param ) { return is_numeric( $param ) && (int) $param > 0; } ) ),
        ) );

        register_rest_route( 'wc-yappy/v1', '/orders/(?P<order_id>\d+)/status', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array( $this, 'payment_status' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( 'wc-yappy/v1', '/ipn', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array( $this, 'ipn' ),
            'permission_callback' => '__return_true',
        ) );
    }

    public function create_payment( WP_REST_Request $request ) {
        $order = $this->authorized_order( $request );
        if ( is_wp_error( $order ) ) {
            return $order;
        }

        $params = $request->get_json_params();
        $alias  = isset( $params['aliasYappy'] ) ? sanitize_text_field( $params['aliasYappy'] ) : '';

        try {
            $result = ( new WC_Yappy_V2_Payment_Service() )->create_payment( $order, $alias );
            return rest_ensure_response( array_merge( array( 'success' => true ), $result ) );
        } catch ( WC_Yappy_V2_Exception $e ) {
            $status = in_array( $e->get_yappy_code(), array( 'ACTIVE', 'UNCONFIRMED' ), true ) ? 409 : 400;
            return new WP_Error(
                'wc_yappy_payment_error',
                WC_Yappy_V2_Payment_Service::customer_message( $e->get_yappy_code(), $e->getMessage() ),
                array( 'status' => $status, 'yappyCode' => $e->get_yappy_code() )
            );
        }
    }

    public function payment_status( WP_REST_Request $request ) {
        $order = $this->authorized_order( $request );
        if ( is_wp_error( $order ) ) {
            return $order;
        }

        return rest_ensure_response( array(
            'success'  => true,
            'paid'     => $order->is_paid(),
            'status'   => $order->get_status(),
            'canRetry' => $order->needs_payment() && ! $this->repo->is_recent_active_attempt( $order->get_id() ),
            'redirect' => $order->is_paid() ? $order->get_checkout_order_received_url() : '',
        ) );
    }

    private function authorized_order( WP_REST_Request $request ) {
        $order = wc_get_order( absint( $request['order_id'] ) );
        if ( ! $order instanceof WC_Order ) {
            return new WP_Error( 'wc_yappy_order_not_found', __( 'Pedido no encontrado.', 'woocommerce-yappy-v2' ), array( 'status' => 404 ) );
        }

        $params = array_merge( $request->get_params(), (array) $request->get_json_params() );
        $key    = isset( $params['orderKey'] ) ? wc_clean( $params['orderKey'] ) : '';
        $token  = isset( $params['paymentToken'] ) ? wc_clean( $params['paymentToken'] ) : '';

        if ( ! hash_equals( $order->get_order_key(), (string) $key ) || ! WC_Yappy_V2_Payment_Token::validate( $order, $token ) ) {
            return new WP_Error( 'wc_yappy_forbidden', __( 'No estás autorizado para consultar este pago.', 'woocommerce-yappy-v2' ), array( 'status' => 403 ) );
        }

        return $order;
    }

    public function ipn( WP_REST_Request $request ) {
        $yappy_order_id = sanitize_text_field( (string) $request->get_param( 'orderId' ) );
        $status         = strtoupper( sanitize_text_field( (string) $request->get_param( 'status' ) ) );
        $domain         = (string) $request->get_param( 'domain' );
        $hash           = $request->get_param( 'hash' );
        if ( null === $hash || '' === $hash ) {
            $hash = $request->get_param( 'Hash' );
        }
        $hash = sanitize_text_field( (string) $hash );

        if ( ! $yappy_order_id || ! in_array( $status, array( 'E', 'R', 'C', 'X' ), true ) || ! $domain || ! $hash ) {
            return new WP_Error( 'wc_yappy_bad_ipn', 'Missing or invalid parameters', array( 'status' => 400 ) );
        }

        $validator = new WC_Yappy_V2_Hash_Validator();
        if ( ! $validator->domain_matches( $domain ) || ! $validator->validate( $yappy_order_id, $status, $domain, $hash ) ) {
            WC_Yappy_V2_Logger::log( 'warning', 'Rejected Yappy IPN', array( 'yappy_order' => $yappy_order_id, 'status' => $status, 'domain' => $domain ) );
            return new WP_Error( 'wc_yappy_invalid_hash', 'Invalid signature', array( 'status' => 403 ) );
        }

        $row = $this->repo->find_by_yappy_order_id( $yappy_order_id );
        if ( ! $row ) {
            WC_Yappy_V2_Logger::log( 'warning', 'Yappy IPN for unknown order', array( 'yappy_order' => $yappy_order_id, 'status' => $status ) );
            return new WP_Error( 'wc_yappy_unknown_order', 'Unknown order', array( 'status' => 404 ) );
        }

        $order_id = (int) $row['wc_order_id'];
        if ( ! $this->repo->lock_order( $order_id ) ) {
            return new WP_Error( 'wc_yappy_order_busy', 'Order is being processed', array( 'status' => 503 ) );
        }

        try {
            return $this->apply_ipn( $order_id, $row, $yappy_order_id, $status );
        } finally {
            $this->repo->unlock_order( $order_id );
        }
    }

    private function apply_ipn( $order_id, array $row, $yappy_order_id, $status ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return new WP_Error( 'wc_yappy_wc_order_missing', 'WooCommerce order not found', array( 'status' => 404 ) );
        }

        $this->repo->mark_ipn_status( $yappy_order_id, $status );
        $latest = $this->repo->latest_for_order( $order->get_id() );

        if ( 'E' === $status ) {
            $previous_paid_yappy_id = (string) $order->get_meta( '_yappy_paid_order_id', true );
            if ( $order->is_paid() ) {
                if ( ! $previous_paid_yappy_id || ! hash_equals( $previous_paid_yappy_id, $yappy_order_id ) ) {
                    $order->add_order_note( sprintf( __( 'ATENCIÓN: se recibió una segunda confirmación Yappy para un pedido ya pagado. Yappy Order ID: %s. Revisar posible pago duplicado.', 'woocommerce-yappy-v2' ), $yappy_order_id ) );
                    WC_Yappy_V2_Logger::log( 'critical', 'Possible duplicate Yappy payment', array( 'order' => $order->get_id(), 'yappy_order' => $yappy_order_id, 'previous_yappy_order' => $previous_paid_yappy_id ) );
                }
                return rest_ensure_response( array( 'success' => true, 'alreadyPaid' => true ) );
            }

            $transaction_id = ! empty( $row['transaction_id'] ) ? (string) $row['transaction_id'] : '';
            $order->update_meta_data( '_yappy_last_order_id', $yappy_order_id );
            $order->update_meta_data( '_yappy_last_transaction_id', $transaction_id );
            $order->update_meta_data( '_yappy_last_status', 'E' );
            $order->update_meta_data( '_yappy_paid_order_id', $yappy_order_id );
            $order->save();
            $order->payment_complete( $transaction_id );
            $order->add_order_note( sprintf( __( 'Pago confirmado por Yappy. Yappy Order ID: %1$s. Transaction ID: %2$s.', 'woocommerce-yappy-v2' ), $yappy_order_id, $transaction_id ?: 'N/D' ) );
            WC_Yappy_V2_Logger::log( 'info', 'Yappy payment completed', array( 'order' => $order->get_id(), 'yappy_order' => $yappy_order_id, 'transaction' => $transaction_id ) );
        } elseif ( $latest && hash_equals( (string) $latest['yappy_order_id'], $yappy_order_id ) && $order->needs_payment() ) {
            $labels = array( 'R' => __( 'rechazado', 'woocommerce-yappy-v2' ), 'C' => __( 'cancelado', 'woocommerce-yappy-v2' ), 'X' => __( 'expirado', 'woocommerce-yappy-v2' ) );
            $order->update_meta_data( '_yappy_last_status', $status );
            $order->update_status( 'failed', sprintf( __( 'Pago Yappy %s. Se puede iniciar un nuevo intento.', 'woocommerce-yappy-v2' ), $labels[ $status ] ) );
            $order->save();
        }

        return rest_ensure_response( array( 'success' => true ) );
    }
}
