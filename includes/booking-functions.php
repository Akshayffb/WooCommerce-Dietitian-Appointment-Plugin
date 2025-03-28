<?php

if (!defined('ABSPATH')) {
  exit;
}

// Start session if not started already
if (!function_exists('wdb_start_session')) {
  function wdb_start_session()
  {
    if (!session_id()) {
      session_start();
    }
  }
}
// add_action('init', 'wdb_start_session');

// Fetch the page slug dynamically from the database
function wdb_get_booking_slug()
{
  global $wpdb;
  $table_name = $wpdb->prefix . 'wdb_settings';
  $slug = $wpdb->get_var("SELECT shortcode_slug FROM $table_name WHERE id = 1");
  return $slug ? esc_attr($slug) : 'book-appointment';
}

function wdb_add_booking_link($order_id) {
  if (!$order_id) return;

  update_post_meta($order_id, '_wdb_order_id', $order_id);

  if (!function_exists('wdb_get_booking_slug')) {
      return;
  }

  $booking_slug = wdb_get_booking_slug();

  echo '<p><a href="' . esc_url(site_url('/' . $booking_slug . '/?order_id=' . $order_id)) . '" class="button wc-forward th-btn th_btn style4">Book Appointment</a></p>';
}
add_action('woocommerce_thankyou', 'wdb_add_booking_link', 5);
