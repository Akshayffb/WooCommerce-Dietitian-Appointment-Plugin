<?php

/**
 * Creates / updates the custom DB tables for WooCommerce Dietitian Booking.
 * Loaded by the main plugin file on activation.
 */

if (!defined('ABSPATH')) {
  exit; // Prevent direct access.
}

function wdb_run_schema_updates()
{
  global $wpdb;
  $charset_collate = $wpdb->get_charset_collate();

  /* ----------  TABLE NAMES  ---------- */
  $dietitians_table       = $wpdb->prefix . 'wdb_dietitians';
  $appointments_table     = $wpdb->prefix . 'wdb_appointments';
  $settings_table         = $wpdb->prefix . 'wdb_settings';
  $meal_plan_table        = $wpdb->prefix . 'wdb_meal_plans';
  $meals_schedule_table   = $wpdb->prefix . 'wdb_meal_plan_schedules';
  $meal_status_table      = $wpdb->prefix . 'wdb_meal_plan_schedule_status';
  $api_table              = $wpdb->prefix . 'wdb_apis';
  $api_log_table          = $wpdb->prefix . 'wdb_api_logs';

  $tables = [
    $dietitians_table,
    $appointments_table,
    $settings_table,
    $meal_plan_table,
    $meals_schedule_table,
    $meal_status_table,
    $api_table,
  ];

  foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS $table");
  }

  $sql1 = "CREATE TABLE {$dietitians_table} (
    id               BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          BIGINT(20) UNSIGNED NULL,
    name             VARCHAR(255) NOT NULL,
    email            VARCHAR(255) NOT NULL UNIQUE,
    phone            VARCHAR(50),
    specialization   VARCHAR(255),
    experience       INT(3),
    availability     TEXT,
    consultation_fee DECIMAL(10,2),
    bio              TEXT,
    allow_login      TINYINT(1) DEFAULT 0,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) {$charset_collate};";

  $sql2 = "CREATE TABLE {$appointments_table} (
    id               BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id      BIGINT(20) NOT NULL,
    order_id         BIGINT(20) NOT NULL UNIQUE,
    dietitian_id     BIGINT(20) UNSIGNED NOT NULL,
    meeting_link     TEXT,
    appointment_date DATETIME NOT NULL,
    status           VARCHAR(50) NOT NULL DEFAULT 'pending',
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dietitian_id) REFERENCES {$dietitians_table}(id) ON DELETE CASCADE,
    KEY (dietitian_id)
) {$charset_collate};";

  $sql3 = "CREATE TABLE {$settings_table} (
    id               BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scheduling_api   VARCHAR(255) NOT NULL,
    api_key          TEXT NOT NULL,
    api_secret       TEXT NOT NULL,
    callback_url     TEXT NOT NULL,
    meeting_duration INT(10) NOT NULL,
    shortcode_slug   TEXT NOT NULL,
    updated_at       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) {$charset_collate};";

  $sql4 = "CREATE TABLE {$meal_plan_table} (
    id               BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id         BIGINT(20) NOT NULL UNIQUE,
    product_id       BIGINT(20) NOT NULL,
    user_id          BIGINT(20) NOT NULL,
    plan_name        VARCHAR(255) NOT NULL,
    plan_duration    BIGINT(20) NOT NULL,
    category         VARCHAR(255) NOT NULL,
    start_date       DATE NOT NULL,
    selected_days    TEXT NOT NULL,
    meal_type        TEXT NOT NULL,
    time             TEXT NOT NULL,
    ingredients      TEXT NOT NULL,
    grand_total      DECIMAL(10,2) NOT NULL,
    notes            TEXT,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) {$charset_collate};";

  $sql5 = "CREATE TABLE {$meals_schedule_table} (
    id              BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meal_plan_id    BIGINT(20) UNSIGNED NOT NULL,
    serve_date      DATE NOT NULL,
    weekday         VARCHAR(15) NOT NULL,
    meal_info       TEXT NOT NULL,
    meal_type       VARCHAR(50) NOT NULL,
    delivery_window VARCHAR(50),
    status          VARCHAR(20) NOT NULL DEFAULT 'active',
    message         TEXT,
    FOREIGN KEY (meal_plan_id) REFERENCES {$meal_plan_table}(id) ON DELETE CASCADE,
    KEY (meal_plan_id)
) {$charset_collate};";

  $sql6 = "CREATE TABLE {$meal_status_table} (
    id               BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meal_schedule_id BIGINT(20) UNSIGNED NOT NULL,
    meal_type        VARCHAR(50) NOT NULL,
    status           VARCHAR(20) NOT NULL DEFAULT 'active',
    message          TEXT,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meal_schedule_id) REFERENCES {$meals_schedule_table}(id) ON DELETE CASCADE,
    KEY (meal_schedule_id)
) {$charset_collate};";

  $sql7 = "CREATE TABLE {$api_table} (
    id           BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    api_name     VARCHAR(255) NOT NULL,
    api_slug     VARCHAR(255) NOT NULL UNIQUE,
    endpoint     TEXT NOT NULL,
    method       VARCHAR(10) NOT NULL DEFAULT 'GET',
    headers      TEXT NULL,
    secret_salt  TEXT NULL,
    api_key      TEXT NULL,
    is_active    TINYINT(1) NOT NULL DEFAULT 1,
    client_ip    VARCHAR(45) NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) {$charset_collate};";

  $sql8 = "CREATE TABLE {$api_log_table} (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       BIGINT UNSIGNED NULL,
    api_slug      VARCHAR(100),
    request_payload LONGTEXT,
    response_text LONGTEXT,
    status_code   INT,
    error_message TEXT,
    retries       INT DEFAULT 0,
    ip_address    VARCHAR(45) NULL,
    user_agent    VARCHAR(255) NULL,
    duration_ms   INT NULL,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (api_slug),
    INDEX (created_at)
);";

  require_once ABSPATH . 'wp-admin/includes/upgrade.php';

  dbDelta($sql1);
  dbDelta($sql2);
  dbDelta($sql3);
  dbDelta($sql4);
  dbDelta($sql5);
  dbDelta($sql6);
  dbDelta($sql7);
  dbDelta($sql8);

  if ($wpdb->last_error) {
    error_log('WDB DB Error: ' . $wpdb->last_error);
    return false;
  }

  return true;
}
