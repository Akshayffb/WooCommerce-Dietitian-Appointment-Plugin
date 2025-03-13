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

  // Dietitians Table (Updated Fields)
  $dietitians_table = $wpdb->prefix . 'wdb_dietitians';
  $sql1 = "CREATE TABLE $dietitians_table (
        id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT(20) UNSIGNED NULL,
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
        order_id BIGINT(20) NOT NULL UNIQUE,
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
    shortcode_slug TEXT NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) $charset_collate;";

  // Execute table creation
  require_once ABSPATH . 'wp-admin/includes/upgrade.php';

  dbDelta($sql1);
  dbDelta($sql2);
  dbDelta($sql3);

  // Check for errors
  if ($wpdb->last_error) {
    error_log("DB Error: " . $wpdb->last_error);
  }
}

// Run activation function
register_activation_hook(__FILE__, 'wdb_activate');

// Load Plugin Functionality
function wdb_init()
{
  include_once plugin_dir_path(__FILE__) . 'includes/booking-functions.php';
}

add_action('plugins_loaded', 'wdb_init');

// Admin Menu Setup
function wdb_add_admin_menu()
{
  add_menu_page(
    'Dietitian Bookings',
    'Dietitian Bookings',
    'view_wdb_appointments',
    'wdb-all-appointments',
    'wdb_all_appointments_page',
    'dashicons-calendar-alt',
    56
  );

  add_submenu_page(
    'wdb-all-appointments',
    'All Appointments',
    'All Appointments',
    'view_wdb_appointments', // Dietitians & Admins
    'wdb-all-appointments',
    'wdb_all_appointments_page'
  );

  add_submenu_page(
    'wdb-all-appointments',
    'Add/Edit Appointment',
    'Add New Appointment',
    'edit_wdb_appointments', // Dietitians & Admins
    'wdb-add-appointment',
    'wdb_display_add_appointment'
  );

  add_submenu_page(
    'wdb-all-appointments',
    'All Dietitians',
    'All Dietitians',
    'manage_options', // Admins only
    'wdb-all-dietitians',
    'wdb_all_dietitians_page'
  );

  add_submenu_page(
    'wdb-all-appointments',
    'Add/Edit Dietitian',
    'Add New Dietitian',
    'manage_options', // Admins only
    'wdb-add-dietitian',
    'wdb_display_add_dietitian'
  );

  add_submenu_page(
    'wdb-all-appointments',
    'Settings',
    'Settings',
    'manage_options', // Admins only
    'wdb-settings',
    'wdb_settings_page'
  );
}

add_action('admin_menu', 'wdb_add_admin_menu');

// Create Dietitian role if it doesn't exist
function wdb_register_dietitian_role()
{
  if (!get_role('dietitian')) {
    add_role('dietitian', 'Dietitian', [
      'read'                  => true,  // Can access admin
      'upload_files'          => false, // No media uploads
      'edit_posts'            => false, // No post editing
      'list_users'            => false, // No user management
      'edit_wdb_appointments' => true,  // Can edit appointments
      'view_wdb_appointments' => true   // Can view appointments
    ]);
  }

  // Ensure Dietitian role has correct capabilities
  $role = get_role('dietitian');
  if ($role) {
    $capabilities = [
      'edit_wdb_appointments',
      'view_wdb_appointments'
    ];

    foreach ($capabilities as $cap) {
      if (!$role->has_cap($cap)) {
        $role->add_cap($cap);
      }
    }
  }

  // Ensure Admins Have Full Access
  $admin_role = get_role('administrator');
  if ($admin_role) {
    $admin_caps = [
      'edit_wdb_appointments',
      'view_wdb_appointments'
    ];

    foreach ($admin_caps as $cap) {
      if (!$admin_role->has_cap($cap)) {
        $admin_role->add_cap($cap);
      }
    }
  }
}

add_action('admin_init', 'wdb_register_dietitian_role');

// Callback Functions for Submenu Pages
function wdb_all_appointments_page()
{
  include_once plugin_dir_path(__FILE__) . 'includes/all-appointments.php';
}

function wdb_display_add_appointment()
{
  include_once plugin_dir_path(__FILE__) . 'includes/new-appointment.php';
}

function wdb_all_dietitians_page()
{
  include_once plugin_dir_path(__FILE__) . 'includes/all-dietitian.php';
}

function wdb_display_add_dietitian()
{
  include_once plugin_dir_path(__FILE__) . 'includes/new-dietitian.php';
}

function wdb_settings_page()
{
  include_once plugin_dir_path(__FILE__) . 'includes/settings.php';
}

function wdb_my_bookings_shortcode()
{
  ob_start();
  include plugin_dir_path(__FILE__) . 'includes/components/my-bookings.php';
  return ob_get_clean();
}

add_shortcode('wdb_my_bookings', 'wdb_my_bookings_shortcode');

require_once plugin_dir_path(__FILE__) . 'includes/components/shortcodes.php';


// Plugin Activation Hook: Ensures "My Appointments" is added to My Account
function wdb_plugin_activate()
{
  wdb_add_appointments_endpoint();
  flush_rewrite_rules();

  // Get existing menu items
  $menu_items = get_option('woocommerce_account_menu_items', []);

  if (!isset($menu_items['my-appointments'])) {
    $menu_items['my-appointments'] = __('My Appointments', 'your-text-domain');
    update_option('woocommerce_account_menu_items', $menu_items);
  }

  wdb_create_appointments_page();
}
register_activation_hook(__FILE__, 'wdb_plugin_activate');

// Function to create a unique My Appointments page
function wdb_create_appointments_page()
{
  if (get_option('wdb_appointments_page_id')) {
    return;
  }

  $page_id = wp_insert_post([
    'post_title'   => 'My Appointments',
    'post_content' => '[wdb_my_appointments]',
    'post_status'  => 'publish',
    'post_type'    => 'page',
  ]);

  if ($page_id) {
    update_option('wdb_appointments_page_id', $page_id);
  }
}

// Add Rewrite Endpoint for My Appointments
function wdb_add_appointments_endpoint()
{
  add_rewrite_endpoint('my-appointments', EP_PAGES);
}
add_action('init', 'wdb_add_appointments_endpoint');

// Add 'My Appointments' to WooCommerce My Account Menu
function wdb_add_my_appointments_menu($items)
{
  $items['my-appointments'] = __('My Appointments', 'your-text-domain');
  return $items;
}
add_filter('woocommerce_account_menu_items', 'wdb_add_my_appointments_menu');

require_once plugin_dir_path(__FILE__) . '/includes/my-appointments.php';

// Flush rewrite rules once after plugin activation
add_action('admin_init', function () {
  if (get_option('wdb_permalinks_flushed') !== 'yes') {
    flush_rewrite_rules();
    update_option('wdb_permalinks_flushed', 'yes');
  }
});

function custom_login_redirect($user_login, $user)
{
  if (in_array('dietitian', $user->roles)) {
    wp_redirect(admin_url());
    exit;
  }
}
add_action('wp_login', 'custom_login_redirect', 10, 2);
