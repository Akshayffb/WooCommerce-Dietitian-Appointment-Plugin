<?php

/**
 * Plugin Name: WooCommerce Dietitian Booking
 * Plugin URI:  https://yourwebsite.com
 * Description: Allows customers to book a dietitian after ordering a product.
 * Version:     1.0
 * Author:      Akshayffb
 * Author URI:  https://yourwebsite.com
 * License:     GPL2
 */

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

// Hook to activate plugin (Ensures tables are created)
function wdb_activate()
{
  global $wpdb;
  $charset_collate = $wpdb->get_charset_collate();

  // Drop old tables if they exist (in correct order)
  $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wdb_appointments");
  $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wdb_dietitians");
  $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wdb_settings");
  $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wdb_forms");


  // Dietitians Table (Updated Fields)
  $dietitians_table = $wpdb->prefix . 'wdb_dietitians';
  $sql1 = "CREATE TABLE $dietitians_table (
        id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        phone VARCHAR(50),
        specialization VARCHAR(255),
        experience INT(3),
        availability TEXT,
        consultation_fee DECIMAL(10,2),
        bio TEXT,
        allow_login TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

  // Appointments Table (Retaining this in case it's needed)
  $appointments_table = $wpdb->prefix . 'wdb_appointments';
  $sql2 = "CREATE TABLE $appointments_table (
        id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        customer_id BIGINT(20) NOT NULL,
        order_id BIGINT(20) NOT NULL,
        dietitian_id BIGINT(20) UNSIGNED NOT NULL,
        meeting_link TEXT,
        appointment_date DATETIME NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (dietitian_id) REFERENCES $dietitians_table(id) ON DELETE CASCADE
    ) $charset_collate;";

  // Settings Table (Retaining this in case it's needed)
  $settings_table = $wpdb->prefix . 'wdb_settings';
  $sql3 = "CREATE TABLE $settings_table (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scheduling_api VARCHAR(255) NOT NULL,
    api_key TEXT NOT NULL,
    api_secret TEXT NOT NULL,
    callback_url TEXT NOT NULL,
    meeting_duration INT(10) NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) $charset_collate;";


  $forms_table = $wpdb->prefix . 'wdb_forms';
  $sql4 = "CREATE TABLE $forms_table (
      id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      form_name VARCHAR(255) NOT NULL,
      fields TEXT NOT NULL,
      shortcode VARCHAR(50) NOT NULL UNIQUE,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) $charset_collate;";

  // Execute table creation
  require_once ABSPATH . 'wp-admin/includes/upgrade.php';

  dbDelta($sql1);
  dbDelta($sql2);
  dbDelta($sql3);
  dbDelta($sql4);

  // Check for errors
  if ($wpdb->last_error) {
    error_log("DB Error: " . $wpdb->last_error);
  }
}

// Run activation function
register_activation_hook(__FILE__, 'wdb_activate');

function register_dietitian_role()
{
  add_role('dietitian', 'Dietitian', [
    'read' => true,
    'edit_posts' => true,
    'delete_posts' => false,
    'upload_files' => true
  ]);
}

add_action('init', 'register_dietitian_role');

// Load Plugin Functionality
function wdb_init()
{
  include_once plugin_dir_path(__FILE__) . 'includes/booking-functions.php';
}
add_action('plugins_loaded', 'wdb_init');

// Admin Menu with Submenus
function wdb_add_admin_menu()
{
  add_menu_page(
    'Dietitian Bookings',   // Page Title
    'Dietitian Bookings',   // Menu Title
    'manage_options',       // Capability (Admin Only)
    'wdb-all-appointments', // Default Page
    'wdb_all_appointments_page', // Function Callback
    'dashicons-calendar-alt', // Icon
    56                      // Position in Menu
  );

  add_submenu_page(
    'wdb-all-appointments',
    'All Appointments',
    'All Appointments',
    'manage_options',
    'wdb-all-appointments',
    'wdb_all_appointments_page'
  );

  add_submenu_page(
    'wdb-all-appointments',
    'Add/Edit Appointment',
    'Add New Appointment',
    'manage_options',
    'wdb-add-appointment',
    'wdb_display_add_appointment'
  );

  add_submenu_page(
    'wdb-all-appointments',
    'Components',
    'Components',
    'manage_options',
    'wdb-components',
    'wdb_components_page'
  );

  add_submenu_page(
    'wdb-all-appointments',
    'Dietitian',
    'Dietitian',
    'manage_options',
    'wdb-add-dietitian',
    'wdb_add_dietitian_page'
  );

  add_submenu_page(
    'wdb-all-appointments',
    'Settings',
    'Settings',
    'manage_options',
    'wdb-settings',
    'wdb_settings_page'
  );
}

// Callback Functions for Submenu Pages
function wdb_all_appointments_page()
{
  include_once plugin_dir_path(__FILE__) . 'includes/all-appointments.php';
}

function wdb_display_add_appointment()
{
  include_once plugin_dir_path(__FILE__) . 'includes/new-appointment.php';
}

function wdb_add_dietitian_page()
{
  include_once plugin_dir_path(__FILE__) . 'includes/add-dietitian.php';
}

function wdb_settings_page()
{
  include_once plugin_dir_path(__FILE__) . 'includes/settings.php';
}

function wdb_components_page()
{
  include_once plugin_dir_path(__FILE__) . 'includes/components/form-creator.php';
}

add_action('admin_menu', 'wdb_add_admin_menu');

require_once plugin_dir_path(__FILE__) . 'includes/components/shortcodes.php';
require_once plugin_dir_path(__FILE__) . 'includes/components/form-handler.php';


function wdb_my_bookings_shortcode()
{
  ob_start();
  include plugin_dir_path(__FILE__) . 'includes/components/my-bookings.php';
  return ob_get_clean();
}

add_shortcode('wdb_my_bookings', 'wdb_my_bookings_shortcode');
