<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$tables = [
    'wdb_dietitians',
    'wdb_appointments',
    'wdb_settings',
    'wdb_meal_plans',
    'wdb_meal_plan_schedules',
    'wdb_apis',
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}$table");
}
