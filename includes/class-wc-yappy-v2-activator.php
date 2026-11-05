<?php

defined( 'ABSPATH' ) || exit;

final class WC_Yappy_V2_Activator {
    public static function activate() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_name      = $wpdb->prefix . 'wc_yappy_transactions';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            wc_order_id bigint(20) unsigned NOT NULL,
            attempt_no smallint(5) unsigned NOT NULL,
            yappy_order_id varchar(15) NOT NULL,
            transaction_id varchar(191) NULL,
            status varchar(20) NOT NULL DEFAULT 'creating',
            api_status_code varchar(32) NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            completed_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY yappy_order_id (yappy_order_id),
            UNIQUE KEY order_attempt (wc_order_id,attempt_no),
            KEY wc_order_id (wc_order_id),
            KEY transaction_id (transaction_id)
        ) {$charset_collate};";

        dbDelta( $sql );
        update_option( 'wc_yappy_v2_db_version', WC_YAPPY_V2_VERSION );
    }
}
