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
require_once plugin_dir_path(__FILE__) . 'includes/database/db-schema.php';

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
    'Manage APIs',
    'Manage APIs',
    'manage_options',
    'wdb-manage-apis',
    'wdb_list_apis_page'
  );

  add_submenu_page(
    'wdb-manage-apis',
    'Add New API',
    'Add New API',
    'manage_options',
    'wdb-add-api',
    'wdb_add_api_page'
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

require_once plugin_dir_path(__FILE__) . 'includes/components/shortcodes.php';

// Plugin Activation Hook: Ensures "My Appointments" is added to My Account
function wdb_plugin_activate()
{
  // Run schema updates
  wdb_run_schema_updates();

  // Add appointments endpoint
  wdb_add_appointments_endpoint();

  // Flush rewrite rules to ensure new endpoint is registered
  flush_rewrite_rules();

  // Create appointments page if not exists
  wdb_create_appointments_page();

  // Get existing WooCommerce account menu items
  $menu_items = get_option('woocommerce_account_menu_items', []);

  // Check if 'my-appointments' is already in the menu
  if (!isset($menu_items['my-appointments'])) {
    $menu_items['my-appointments'] = __('My Appointments', 'your-text-domain');
    update_option('woocommerce_account_menu_items', $menu_items);
  }
}

// Add Rewrite Endpoint for My Appointments
function wdb_add_appointments_endpoint()
{
  add_rewrite_endpoint('my-appointments', EP_PAGES);
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

add_action('init', 'wdb_add_appointments_endpoint');

// Add 'My Appointments' to WooCommerce My Account Menu
function wdb_add_my_appointments_menu($items)
{
  $new_items = [];

  foreach ($items as $key => $value) {
    $new_items[$key] = $value;

    if ($key === 'orders') {
      $new_items['my-appointments'] = __('My Appointments', 'your-text-domain');
    }
  }

  return $new_items;
}

add_filter('woocommerce_account_menu_items', 'wdb_add_my_appointments_menu');

// Include additional files
include_once plugin_dir_path(__FILE__) . 'includes/my-appointments.php';
require_once plugin_dir_path(__FILE__) . 'includes/dietitian-meeting-note.php';
// require_once plugin_dir_path(__FILE__) . 'includes/order-schedules/order-schedules.php';

// Custom login redirect for Dietitian role
function custom_login_redirect($user_login, $user)
{
  if (in_array('dietitian', $user->roles)) {
    wp_redirect(admin_url());
    exit;
  }
}

add_action('wp_login', 'custom_login_redirect', 10, 2);

// Hide menu items for Dietitian role
function restrict_dietitian_admin_menu()
{
  if (!is_admin()) {
    return;
  }

  $user = wp_get_current_user();
  if (in_array('dietitian', (array) $user->roles)) {
    global $menu, $submenu;

    $allowed_menus = ['Dietitian Bookings', 'All Appointments', 'Add New Appointment'];

    foreach ($menu as $key => $menu_item) {
      if (!in_array($menu_item[0], $allowed_menus)) {
        unset($menu[$key]);
      }
    }

    foreach ($submenu as $parent => $sub) {
      foreach ($sub as $key => $menu_item) {
        if ($parent !== 'profile.php' && !in_array($menu_item[0], $allowed_menus)) {
          unset($submenu[$parent][$key]);
        }
      }
    }

    add_action('pre_get_users', function ($query) use ($user) {
      if (is_admin() && $query->is_main_query() && $query->get('orderby')) {
        $query->set('include', [$user->ID]);
      }
    });
  }
}

add_action('admin_menu', 'restrict_dietitian_admin_menu', 999);

// Include order-related functionality
include_once plugin_dir_path(__FILE__) . 'includes/orders/order-main.php';
require_once plugin_dir_path(__FILE__) . 'includes/orders/view-schedule-content.php';
require_once plugin_dir_path(__FILE__) . 'includes/orders/view-schedule-endpoint.php';

// Apis
require_once plugin_dir_path(__FILE__) . '/includes/apis/api-main.php';

// Add jquery

// Add this to your theme's functions.php or plugin main file
// function wdb_schedule_update_endpoint()
// {
//   add_rewrite_rule('^process-schedule-update/?$', 'index.php?process_schedule_update=1', 'top');
//   add_rewrite_tag('%process_schedule_update%', '1');
// }
// add_action('init', 'wdb_schedule_update_endpoint');

// function wdb_schedule_update_template_redirect()
// {
//   if (get_query_var('process_schedule_update') == 1) {
//     include get_template_directory() . '/process-schedule-update.php'; // Change path if needed
//     exit;
//   }
// }
// add_action('template_redirect', 'wdb_schedule_update_template_redirect');


// require_once plugin_dir_path(__FILE__) . 'includes/orders/update-schedule-plan.php';

// Load AJAX logic
// require_once plugin_dir_path(__FILE__) . '/includes/orders/ajax-update-schedule.php';
