<?php

defined( 'ABSPATH' ) || exit;

final class WC_Yappy_V2_Payment_Service {
    private $repo;
    private $api;
    private $id_generator;
    private $amount_mapper;

    public function __construct() {
        $this->repo          = new WC_Yappy_V2_Transaction_Repository();
        $this->api           = new WC_Yappy_V2_API_Client();
        $this->id_generator  = new WC_Yappy_V2_Order_ID_Generator();
        $this->amount_mapper = new WC_Yappy_V2_Amount_Mapper();
    }

    public function create_payment( WC_Order $order, $alias_yappy ) {
        if ( ! WC_Yappy_V2_Config::ready() ) {
            throw new WC_Yappy_V2_Exception( __( 'Yappy no está configurado correctamente.', 'woocommerce-yappy-v2' ), 'E011' );
        }
        if ( 'yappy_v2' !== $order->get_payment_method() ) {
            throw new WC_Yappy_V2_Exception( __( 'Este pedido no utiliza Yappy.', 'woocommerce-yappy-v2' ) );
        }
        if ( ! $order->needs_payment() ) {
            throw new WC_Yappy_V2_Exception( __( 'Este pedido ya no requiere pago.', 'woocommerce-yappy-v2' ) );
        }

        $alias_yappy = $this->normalize_phone( $alias_yappy );
        if ( ! preg_match( '/^\d{8}$/', $alias_yappy ) ) {
            throw new WC_Yappy_V2_Exception( __( 'Ingresa un número de celular panameño de 8 dígitos.', 'woocommerce-yappy-v2' ) );
        }

        $active = $this->repo->is_recent_active_attempt( $order->get_id(), 360 );
        if ( $active ) {
            throw new WC_Yappy_V2_Exception( __( 'Ya existe una solicitud Yappy activa para este pedido. Revisa tu aplicación o espera a que expire antes de intentar nuevamente.', 'woocommerce-yappy-v2' ), 'ACTIVE' );
        }

        $attempt        = $this->repo->next_attempt_number( $order->get_id() );
        $yappy_order_id = $this->id_generator->generate( $order->get_id(), $attempt );
        $row            = $this->repo->create_attempt( $order->get_id(), $attempt, $yappy_order_id );

        if ( ! $row ) {
            throw new WC_Yappy_V2_Exception( __( 'No fue posible iniciar el intento de pago. Intenta nuevamente.', 'woocommerce-yappy-v2' ) );
        }

        try {
            $auth    = $this->api->validate_merchant();
            $amounts = $this->amount_mapper->from_order( $order );
            $payload = array_merge(
                array(
                    'merchantId' => WC_Yappy_V2_Config::merchant_id(),
                    'orderId'    => $yappy_order_id,
                    'domain'     => WC_Yappy_V2_Config::domain(),
                    'paymentDate'=> $auth['epochTime'],
                    'aliasYappy' => $alias_yappy,
                    'ipnUrl'     => rest_url( 'wc-yappy/v1/ipn' ),
                ),
                $amounts
            );

            $body = $this->api->create_payment( $payload, $auth['token'] );
            $this->repo->mark_pending( $yappy_order_id, $body['transactionId'] );

            $order->update_meta_data( '_yappy_last_order_id', $yappy_order_id );
            $order->update_meta_data( '_yappy_last_transaction_id', (string) $body['transactionId'] );
            $order->update_meta_data( '_yappy_last_status', 'pending' );
            $order->update_meta_data( '_yappy_attempt_count', $attempt );
            $order->save();

            WC_Yappy_V2_Logger::log( 'info', 'Yappy payment created', array( 'order' => $order->get_id(), 'yappy_order' => $yappy_order_id, 'attempt' => $attempt, 'transaction' => (string) $body['transactionId'] ) );

            return array(
                'yappyOrderId'  => $yappy_order_id,
                'transactionId' => (string) $body['transactionId'],
                'documentName'  => (string) $body['documentName'],
                'token'         => (string) $body['token'],
            );
        } catch ( WC_Yappy_V2_Exception $e ) {
            $this->repo->mark_error( $yappy_order_id, $e->get_yappy_code() );
            WC_Yappy_V2_Logger::log( 'error', 'Yappy payment creation failed', array( 'order' => $order->get_id(), 'yappy_order' => $yappy_order_id, 'code' => $e->get_yappy_code() ) );
            throw $e;
        }
    }

    public function normalize_phone( $phone ) {
        $digits = preg_replace( '/\D+/', '', (string) $phone );
        if ( 11 === strlen( $digits ) && 0 === strpos( $digits, '507' ) ) {
            $digits = substr( $digits, 3 );
        }
        return $digits;
    }

    public static function customer_message( $code, $fallback = '' ) {
        $map = array(
            'E002' => __( 'Algo salió mal. Intenta nuevamente.', 'woocommerce-yappy-v2' ),
            'E005' => __( 'Este número no está registrado en Yappy.', 'woocommerce-yappy-v2' ),
            'E006' => __( 'Algo salió mal. Intenta nuevamente.', 'woocommerce-yappy-v2' ),
            'E007' => __( 'El pedido ya fue registrado en Yappy.', 'woocommerce-yappy-v2' ),
            'E008' => __( 'Algo salió mal. Intenta nuevamente.', 'woocommerce-yappy-v2' ),
            'E009' => __( 'No fue posible generar el identificador del pedido.', 'woocommerce-yappy-v2' ),
            'E010' => __( 'No fue posible validar el monto del pedido.', 'woocommerce-yappy-v2' ),
            'E011' => __( 'Hay un problema de configuración de Yappy.', 'woocommerce-yappy-v2' ),
            'E012' => __( 'Algo salió mal. Intenta nuevamente.', 'woocommerce-yappy-v2' ),
            'E100' => __( 'La solicitud enviada a Yappy no es válida.', 'woocommerce-yappy-v2' ),
            'ACTIVE'=> __( 'Ya existe una solicitud Yappy activa para este pedido.', 'woocommerce-yappy-v2' ),
        );
        return isset( $map[ $code ] ) ? $map[ $code ] : ( $fallback ?: __( 'No fue posible iniciar el pago con Yappy.', 'woocommerce-yappy-v2' ) );
    }
}
