<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;
if ( ! defined( 'WC_YAPPY_V2_REMOVE_DATA' ) || true !== WC_YAPPY_V2_REMOVE_DATA ) return;
global $wpdb;
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'wc_yappy_transactions' );
delete_option( 'woocommerce_yappy_v2_settings' );
delete_option( 'wc_yappy_v2_db_version' );
