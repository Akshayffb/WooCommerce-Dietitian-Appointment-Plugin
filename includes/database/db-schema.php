<?php

/**
 * Creates / updates the custom DB tables for WooCommerce Dietitian Booking.
 * Loaded by the main plugin file on activation.
 */

if (! defined('ABSPATH')) {
  exit; // Prevent direct access.
}

function wdb_run_schema_updates()
{

  global $wpdb;
  $charset_collate = $wpdb->get_charset_collate();

  /* ----------  TABLE NAMES  ---------- */
  $dietitians_table   = $wpdb->prefix . 'wdb_dietitians';
  $appointments_table = $wpdb->prefix . 'wdb_appointments';
  $settings_table     = $wpdb->prefix . 'wdb_settings';
  $meal_plan_table     = $wpdb->prefix . 'wdb_meal_plans';
  $meals_table        = $wpdb->prefix . 'wdb_booking_meals';
  $api_table = $wpdb->prefix . 'wdb_apis';

  /* ----------  CREATE STATEMENTS  ---------- */

  $sql1 = "CREATE TABLE {$dietitians_table} (
        id               BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id          BIGINT(20) UNSIGNED NULL,
        name             VARCHAR(255)   NOT NULL,
        email            VARCHAR(255)   NOT NULL UNIQUE,
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
        FOREIGN KEY (dietitian_id) REFERENCES {$dietitians_table}(id) ON DELETE CASCADE
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

  /* Meal Plan Table */
  $sql4 = "CREATE TABLE {$meal_plan_table} (
        id         BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_id   BIGINT(20) NOT NULL UNIQUE,
        user_id   BIGINT(20) NOT NULL,
        plan_name  VARCHAR(255) NOT NULL,
        plan_duration  BIGINT(20) NOT NULL,
        start_date DATE NOT NULL,
        selected_days    JSON NOT NULL,                    
        meal_type        JSON NOT NULL,                    
        time             JSON NOT NULL,                    
        ingredients      JSON NOT NULL,                    
        grand_total         DECIMAL(10,2) NOT NULL,
        notes      TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) {$charset_collate};";

  /* Meal instances – many per booking */
  $sql5 = "CREATE TABLE {$meals_table} (
        id              BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        meal_plan_id      BIGINT(20) UNSIGNED NOT NULL,
        serve_date      DATE        NOT NULL,
        weekday         VARCHAR(15) NOT NULL,
        meal_info      JSON NOT NULL,
        meal_type       VARCHAR(50)  NOT NULL,
        delivery_window VARCHAR(50),
        FOREIGN KEY (meal_plan_id) REFERENCES {$meal_plan_table}(id) ON DELETE CASCADE
    ) {$charset_collate};";

  $sql6 = "CREATE TABLE {$api_table} (
        id           BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        api_name     VARCHAR(255) NOT NULL,
        api_slug     VARCHAR(255) NOT NULL UNIQUE,
        endpoint     TEXT NOT NULL,
        method       VARCHAR(10) NOT NULL DEFAULT 'GET', 
        headers      TEXT NULL,                          
        parameters   TEXT NULL,                          
        is_active    TINYINT(1) NOT NULL DEFAULT 1,      
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) {$charset_collate};";

  /* ----------  EXECUTE WITH dbDelta  ---------- */
  require_once ABSPATH . 'wp-admin/includes/upgrade.php';

  dbDelta($sql1);
  dbDelta($sql2);
  dbDelta($sql3);
  dbDelta($sql4);
  dbDelta($sql5);
  dbDelta($sql6);

  if ($wpdb->last_error) {
    error_log('WDB DB Error: ' . $wpdb->last_error);
    return false;
  }

  return true;
}
