<?php
if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

// Display Appointments on the My Account Page
function wdb_my_appointments_content()
{
  global $wpdb;
  $user_id = get_current_user_id();

  if (!$user_id) {
    echo '<p>' . esc_html__('You must be logged in to view your appointments.', 'your-text-domain') . '</p>';
    return;
  }

  // Fetch appointments for the logged-in user, ordered by latest first
  $appointments_table = $wpdb->prefix . 'wdb_appointments';
  $appointments = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $appointments_table WHERE customer_id = %d ORDER BY appointment_date DESC",
    $user_id
  ));

  if (!$appointments) {
    echo '<p>' . esc_html__('No appointments available.', 'your-text-domain') . '</p>';
    return;
  }

  // Start table
  echo '<table class="shop_table shop_table_responsive my_account_orders">';
  echo '<thead>';
  echo '<tr>';
  echo '<th>' . esc_html__('Order ID', 'your-text-domain') . '</th>';
  echo '<th>' . esc_html__('Dietitian', 'your-text-domain') . '</th>';
  echo '<th>' . esc_html__('Status', 'your-text-domain') . '</th>';
  echo '<th>' . esc_html__('Meeting Link', 'your-text-domain') . '</th>';
  echo '<th>' . esc_html__('Appointment Date', 'your-text-domain') . '</th>';
  echo '</tr>';
  echo '</thead>';
  echo '<tbody>';

  // Loop through each appointment
  foreach ($appointments as $appointment) {
    $dietitian = $wpdb->get_var($wpdb->prepare(
      "SELECT name FROM {$wpdb->prefix}wdb_dietitians WHERE id = %d",
      $appointment->dietitian_id
    ));

    // Get order details
    $order_id = intval($appointment->order_id);
    $order_link = esc_url(wc_get_endpoint_url('view-order', $order_id, wc_get_page_permalink('myaccount')));
    $appointment_date = date('Y-m-d H:i', strtotime($appointment->appointment_date));

    echo '<tr>';
    echo '<td><a href="' . $order_link . '">#' . esc_html($order_id) . '</a></td>';
    echo '<td>' . esc_html($dietitian) . '</td>';
    echo '<td>' . esc_html(ucfirst($appointment->status)) . '</td>';
    echo '<td><a href="' . esc_url($appointment->meeting_link) . '" target="_blank">' . esc_html__('Join Meeting', 'your-text-domain') . '</a></td>';
    echo '<td>' . esc_html($appointment_date) . '</td>';
    echo '</tr>';
  }

  echo '</tbody>';
  echo '</table>';
}

// Hook the function into WooCommerce My Account page
add_action('woocommerce_account_my-appointments_endpoint', 'wdb_my_appointments_content');
