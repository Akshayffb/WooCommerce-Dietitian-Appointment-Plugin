<?php

function reschedule_schedule($wpdb)
{

  $schedule_table = $wpdb->prefix . 'wdb_meal_plan_schedules';
  $schedule_status_table = $wpdb->prefix . 'wdb_meal_plan_schedule_status';

  if (isset($_POST['cancel_reschedule_nonce']) && wp_verify_nonce($_POST['cancel_reschedule_nonce'], 'cancel_or_reschedule_action')) {

    // Sanitize inputs
    $meal_id = intval($_POST['meal_id']);
    $meal_plan_id = intval($_POST['meal_plan_id']);
    $serve_date = sanitize_text_field($_POST['serve_date']);
    $reschedule_date = sanitize_text_field($_POST['reschedule_date']);
    $new_weekday = sanitize_text_field($_POST['new_weekday']);
    $cancel_original_meal_type = sanitize_text_field($_POST['cancel_original_meal_type']);
    $new_meal_type = sanitize_text_field($_POST['new_meal_type']);
    $new_delivery = sanitize_text_field($_POST['new_delivery']);
    $reschedule_message = 'Rescheduled from ' . $serve_date . ' (' . $cancel_original_meal_type . ')';

    if (empty($reschedule_date) || empty($new_weekday) || empty($new_meal_type) || empty($new_delivery)) {
      echo "<p class='text-danger'>All fields are required for rescheduling.</p>";
      return;
    }

    $wpdb->insert(
      $schedule_status_table,
      [
        'meal_schedule_id' => $meal_id,
        'meal_type' => $cancel_original_meal_type,
        'status' => 'rescheduled',
        'message' => 'Rescheduled to ' . $reschedule_date . ' (' . $new_meal_type . ')',
      ],
      ['%d', '%s', '%s', '%s']
    );

    $inserted = $wpdb->insert(
      $schedule_table,
      [
        'meal_plan_id' => $meal_plan_id,
        'serve_date' => $reschedule_date,
        'weekday' => $new_weekday,
        'meal_info' => '',
        'meal_type' => $new_meal_type,
        'delivery_window' => $new_delivery,
        'status' => 'rescheduled',
        'message' => $reschedule_message,
      ],
      ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
    );

    if ($inserted !== false) {
      echo "<p class='text-success'>Schedule rescheduled successfully.</p>";
    } else {
      echo "<p class='text-danger'>Failed to insert new reschedule record.</p>";
    }
  } else {
    echo "<p class='text-muted'>Invalid request.</p>";
  }
}
