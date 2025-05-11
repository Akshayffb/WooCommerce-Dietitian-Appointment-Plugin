<?php
// Ensure WordPress functions work
require_once(dirname(__FILE__) . '/../../wp-load.php'); // Adjust based on file location

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($_POST['update_schedule_nonce']) || !wp_verify_nonce($_POST['update_schedule_nonce'], 'update_schedule_action')) {
    wp_die('Security check failed');
  }

  global $wpdb;
  $schedule_table = $wpdb->prefix . 'wdb_meal_plan_schedules';

  $order_id   = intval($_POST['order_id']);
  $date       = sanitize_text_field($_POST['date']);
  $weekday    = sanitize_text_field($_POST['weekday']);
  $meal_type  = sanitize_text_field($_POST['meal_type']);
  $delivery   = sanitize_text_field($_POST['delivery']);

  // Get the corresponding meal_plan_id
  $meal_plan_id = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM {$wpdb->prefix}wdb_meal_plans WHERE order_id = %d AND user_id = %d",
    $order_id,
    get_current_user_id()
  ));

  if (!$meal_plan_id) {
    wp_die('Invalid request.');
  }

  // Update the schedule
  $updated = $wpdb->update(
    $schedule_table,
    ['delivery_window' => $delivery],
    [
      'meal_plan_id' => $meal_plan_id,
      'serve_date'   => $date,
      'weekday'      => $weekday,
      'meal_type'    => $meal_type
    ]
  );

  if ($updated !== false) {
    wp_redirect(site_url('/view-schedule/' . $order_id . '/?updated=1'));
    exit;
  } else {
    wp_die('Failed to update schedule. Please try again.');
  }
}
