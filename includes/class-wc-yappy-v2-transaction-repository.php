<?php

defined( 'ABSPATH' ) || exit;

final class WC_Yappy_V2_Transaction_Repository {
    private function table() {
        global $wpdb;
        return $wpdb->prefix . 'wc_yappy_transactions';
    }

    public function next_attempt_number( $order_id ) {
        global $wpdb;
        $max = $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(attempt_no) FROM ' . $this->table() . ' WHERE wc_order_id = %d', $order_id ) );
        return max( 1, (int) $max + 1 );
    }

    public function create_attempt( $order_id, $attempt_no, $yappy_order_id ) {
        global $wpdb;
        $now = current_time( 'mysql', true );
        $ok  = $wpdb->insert(
            $this->table(),
            array(
                'wc_order_id'   => (int) $order_id,
                'attempt_no'    => (int) $attempt_no,
                'yappy_order_id'=> (string) $yappy_order_id,
                'status'        => 'creating',
                'created_at'    => $now,
                'updated_at'    => $now,
            ),
            array( '%d', '%d', '%s', '%s', '%s', '%s' )
        );

        if ( false === $ok ) {
            return false;
        }

        return $this->find_by_yappy_order_id( $yappy_order_id );
    }

    public function mark_pending( $yappy_order_id, $transaction_id ) {
        return $this->update_by_yappy_id(
            $yappy_order_id,
            array(
                'status'         => 'pending',
                'transaction_id' => (string) $transaction_id,
                'api_status_code'=> '',
            )
        );
    }

    public function mark_error( $yappy_order_id, $api_status_code = '' ) {
        return $this->update_by_yappy_id(
            $yappy_order_id,
            array(
                'status'          => 'error',
                'api_status_code' => (string) $api_status_code,
            )
        );
    }

    public function mark_ipn_status( $yappy_order_id, $status ) {
        $data = array( 'status' => (string) $status );
        if ( 'E' === $status ) {
            $data['completed_at'] = current_time( 'mysql', true );
        }
        return $this->update_by_yappy_id( $yappy_order_id, $data );
    }

    private function update_by_yappy_id( $yappy_order_id, array $data ) {
        global $wpdb;
        $data['updated_at'] = current_time( 'mysql', true );
        return false !== $wpdb->update( $this->table(), $data, array( 'yappy_order_id' => (string) $yappy_order_id ) );
    }

    public function find_by_yappy_order_id( $yappy_order_id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE yappy_order_id = %s LIMIT 1', $yappy_order_id ),
            ARRAY_A
        );
    }

    public function latest_for_order( $order_id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE wc_order_id = %d ORDER BY attempt_no DESC LIMIT 1', $order_id ),
            ARRAY_A
        );
    }

    public function is_recent_active_attempt( $order_id, $seconds = 360 ) {
        $latest = $this->latest_for_order( $order_id );
        if ( ! $latest || ! in_array( $latest['status'], array( 'creating', 'pending' ), true ) ) {
            return false;
        }

        $created = strtotime( $latest['created_at'] . ' UTC' );
        return $created && ( time() - $created ) < (int) $seconds ? $latest : false;
    }
}
