<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
  exit;
}

global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ypn_booking_forms");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ypn_bookings");
