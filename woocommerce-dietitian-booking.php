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
    shortcode_slug TEXT NOT NULL,
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

// Plugin Activation Hook: Ensures "My Appointments" is added to My Account
function wdb_plugin_activate()
{
  wdb_add_appointments_endpoint(); // Ensure endpoint exists
  flush_rewrite_rules(); // Refresh permalinks

  // Get existing menu items
  $menu_items = get_option('woocommerce_account_menu_items', []);

  // Check if 'My Appointments' is already added, if not, add it
  if (!array_key_exists('my-appointments', $menu_items)) {
    $menu_items['my-appointments'] = __('My Appointments', 'your-text-domain');
    update_option('woocommerce_account_menu_items', $menu_items);
  }

  // Automatically create "My Appointments" page with a unique name
  wdb_create_appointments_page();
}
register_activation_hook(__FILE__, 'wdb_plugin_activate');



// Function to create a unique My Appointments page
function wdb_create_appointments_page()
{
  if (get_option('wdb_appointments_page_id')) {
    return; // Page already exists, no need to create again
  }

  $page_title = 'My Appointments ';

  $page_id = wp_insert_post([
    'post_title'   => $page_title,
    'post_content' => '[wdb_my_appointments]',
    'post_status'  => 'publish',
    'post_type'    => 'page',
  ]);

  if ($page_id) {
    update_option('wdb_appointments_page_id', $page_id); // Store the page ID
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

// Show Appointments on the My Account Page
function wdb_my_appointments_content()
{
  global $wpdb;
  $user_id = get_current_user_id();

  if (!$user_id) {
    echo '<p>' . esc_html__('You must be logged in to view your appointments.', 'your-text-domain') . '</p>';
    return;
  }

  $appointments_table = $wpdb->prefix . 'wdb_appointments';
  $appointments = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $appointments_table WHERE customer_id = %d ORDER BY appointment_date DESC",
    $user_id
  ));

  if (!$appointments) {
    echo '<p>' . esc_html__('No appointments available.', 'your-text-domain') . '</p>';
    return;
  }

  echo '<table class="shop_table shop_table_responsive my_account_orders">';
  echo '<thead><tr><th>' . esc_html__('Appointment Date', 'your-text-domain') . '</th><th>' . esc_html__('Dietitian', 'your-text-domain') . '</th><th>' . esc_html__('Status', 'your-text-domain') . '</th><th>' . esc_html__('Meeting Link', 'your-text-domain') . '</th><th>' . esc_html__('Order', 'your-text-domain') . '</th></tr></thead><tbody>';

  foreach ($appointments as $appointment) {
    $dietitian = $wpdb->get_var($wpdb->prepare(
      "SELECT name FROM {$wpdb->prefix}wdb_dietitians WHERE id = %d",
      $appointment->dietitian_id
    ));

    // Get order details
    $order_id = intval($appointment->order_id);
    $order_link = esc_url(wc_get_endpoint_url('view-order', $order_id, wc_get_page_permalink('myaccount')));

    echo '<tr>';
    echo '<td>' . esc_html(date('Y-m-d H:i', strtotime($appointment->appointment_date))) . '</td>';
    echo '<td>' . esc_html($dietitian) . '</td>';
    echo '<td>' . esc_html(ucfirst($appointment->status)) . '</td>';
    echo '<td><a href="' . esc_url($appointment->meeting_link) . '" target="_blank">' . esc_html__('Join Meeting', 'your-text-domain') . '</a></td>';
    echo '<td><a href="' . $order_link . '">#' . esc_html($order_id) . '</a></td>';
    echo '</tr>';
  }

  echo '</tbody></table>';


  echo '</tbody></table>';
}
add_action('woocommerce_account_my-appointments_endpoint', 'wdb_my_appointments_content');

// Flush rewrite rules once after plugin activation
add_action('admin_init', function () {
  if (get_option('wdb_permalinks_flushed') !== 'yes') {
    flush_rewrite_rules();
    update_option('wdb_permalinks_flushed', 'yes');
  }
});
