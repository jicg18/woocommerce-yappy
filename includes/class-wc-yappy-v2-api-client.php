<?php

defined( 'ABSPATH' ) || exit;

final class WC_Yappy_V2_API_Client {
    public function validate_merchant() {
        $payload = array(
            'merchantId' => WC_Yappy_V2_Config::merchant_id(),
            'urlDomain'  => WC_Yappy_V2_Config::domain(),
        );

        $data = $this->request( '/payments/validate/merchant', $payload );
        if ( empty( $data['body']['token'] ) || empty( $data['body']['epochTime'] ) ) {
            throw new WC_Yappy_V2_Exception( __( 'Yappy no devolvió un token de autorización válido.', 'woocommerce-yappy-v2' ), $this->extract_status_code( $data ) );
        }

        return $data['body'];
    }

    public function create_payment( array $payload, $authorization_token ) {
        $data = $this->request(
            '/payments/payment-wc',
            $payload,
            array( 'Authorization' => (string) $authorization_token )
        );

        if ( empty( $data['body']['transactionId'] ) || empty( $data['body']['token'] ) || empty( $data['body']['documentName'] ) ) {
            throw new WC_Yappy_V2_Exception( __( 'Yappy no devolvió los datos necesarios para iniciar el pago.', 'woocommerce-yappy-v2' ), $this->extract_status_code( $data ) );
        }

        return $data['body'];
    }

    private function request( $path, array $payload, array $extra_headers = array() ) {
        $url = untrailingslashit( WC_Yappy_V2_Config::api_base_url() ) . '/' . ltrim( $path, '/' );

        $headers = array_merge(
            array( 'Content-Type' => 'application/json' ),
            $extra_headers
        );

        WC_Yappy_V2_Logger::log( 'debug', 'Yappy API request', array( 'path' => $path, 'environment' => WC_Yappy_V2_Config::environment() ) );

        $response = wp_remote_post(
            $url,
            array(
                'timeout' => 20,
                'headers' => $headers,
                'body'    => wp_json_encode( $payload ),
            )
        );

        if ( is_wp_error( $response ) ) {
            WC_Yappy_V2_Logger::log( 'error', 'Yappy API transport error', array( 'path' => $path, 'error' => $response->get_error_message() ) );
            throw new WC_Yappy_V2_Exception( __( 'No fue posible conectar con Yappy. Intenta nuevamente.', 'woocommerce-yappy-v2' ) );
        }

        $http_code = (int) wp_remote_retrieve_response_code( $response );
        $raw       = (string) wp_remote_retrieve_body( $response );
        $data      = json_decode( $raw, true );

        if ( ! is_array( $data ) ) {
            WC_Yappy_V2_Logger::log( 'error', 'Invalid JSON from Yappy', array( 'path' => $path, 'http_code' => $http_code ) );
            throw new WC_Yappy_V2_Exception( __( 'Yappy devolvió una respuesta no válida.', 'woocommerce-yappy-v2' ) );
        }

        if ( $http_code < 200 || $http_code >= 300 ) {
            $code        = $this->extract_status_code( $data );
            $description = $this->extract_status_description( $data );
            WC_Yappy_V2_Logger::log( 'error', 'Yappy API rejected request', array( 'path' => $path, 'http_code' => $http_code, 'yappy_code' => $code ) );
            throw new WC_Yappy_V2_Exception( $description ?: __( 'Yappy rechazó la solicitud.', 'woocommerce-yappy-v2' ), $code, $http_code );
        }

        $status_code = $this->extract_status_code( $data );
        if ( $status_code && preg_match( '/^E\d+$/i', $status_code ) ) {
            throw new WC_Yappy_V2_Exception( $this->extract_status_description( $data ) ?: __( 'Yappy no pudo procesar la solicitud.', 'woocommerce-yappy-v2' ), $status_code );
        }

        return $data;
    }

    private function extract_status_code( array $data ) {
        return isset( $data['status']['code'] ) ? (string) $data['status']['code'] : '';
    }

    private function extract_status_description( array $data ) {
        return isset( $data['status']['description'] ) ? (string) $data['status']['description'] : '';
    }
}
