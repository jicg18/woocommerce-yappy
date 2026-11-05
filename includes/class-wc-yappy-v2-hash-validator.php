<?php

defined( 'ABSPATH' ) || exit;

final class WC_Yappy_V2_Hash_Validator {
    public function validate( $order_id, $status, $domain, $received_hash ) {
        $secret = WC_Yappy_V2_Config::secret_key();
        $decoded = base64_decode( $secret, true );

        if ( false === $decoded || '' === $decoded ) {
            return false;
        }

        $parts = explode( '.', $decoded );
        if ( empty( $parts[0] ) ) {
            return false;
        }

        $signature = hash_hmac( 'sha256', (string) $order_id . (string) $status . (string) $domain, $parts[0] );
        return hash_equals( strtolower( $signature ), strtolower( (string) $received_hash ) );
    }

    public function domain_matches( $received_domain ) {
        $configured = $this->canonical_domain( WC_Yappy_V2_Config::domain() );
        $received   = $this->canonical_domain( $received_domain );
        return '' !== $configured && hash_equals( $configured, $received );
    }

    private function canonical_domain( $domain ) {
        $domain = untrailingslashit( trim( (string) $domain ) );
        $parts  = wp_parse_url( $domain );
        if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
            return strtolower( $domain );
        }
        $scheme = ! empty( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : 'https';
        $host   = strtolower( $parts['host'] );
        $port   = ! empty( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
        $path   = isset( $parts['path'] ) ? untrailingslashit( $parts['path'] ) : '';
        return $scheme . '://' . $host . $port . $path;
    }
}
