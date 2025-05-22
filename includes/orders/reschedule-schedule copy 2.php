<?php

function reschedule_schedule($wpdb)
{
  $meal_plan_table = $wpdb->prefix . 'wdb_meal_plans';
  $schedule_table = $wpdb->prefix . 'wdb_meal_plan_schedules';
  $schedule_status_table = $wpdb->prefix . 'wdb_meal_plan_schedule_status';

  $logFile = __DIR__ . '/request.log';

  // Log incoming POST data
  $logData = "---- " . date('Y-m-d H:i:s') . " ----\n";
  $logData .= "POST Data:\n" . print_r($_POST, true) . "\n";
  file_put_contents($logFile, $logData, FILE_APPEND);

  if (
    !isset($_POST['cancel_reschedule_nonce']) ||
    !wp_verify_nonce($_POST['cancel_reschedule_nonce'], 'cancel_or_reschedule_action')
  ) {
    file_put_contents($logFile, "ERROR: Invalid or missing security token.\n", FILE_APPEND);
    echo "<p class='text-danger'>Invalid or missing security token.</p>";
    return;
  }

  $required_fields = ['meal_plan_id', 'meal_plan_schedule_id', 'new_serve_date', 'new_weekday', 'new_meal_type', 'new_delivery'];
  foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
      file_put_contents($logFile, "ERROR: Missing required field: $field\n", FILE_APPEND);
      echo "<p class='text-danger'>Missing required field: $field</p>";
      return;
    }
  }

  // Sanitize inputs
  $meal_plan_id = intval($_POST['meal_plan_id']);
  $schedule_id = intval($_POST['meal_plan_schedule_id']);
  $new_serve_date = sanitize_text_field($_POST['new_serve_date']);
  $new_weekday = sanitize_text_field($_POST['new_weekday']);
  $new_meal_type = sanitize_text_field($_POST['new_meal_type']);
  $new_delivery = sanitize_text_field($_POST['new_delivery']);

  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $new_serve_date)) {
    file_put_contents($logFile, "ERROR: Invalid date format: $new_serve_date\n", FILE_APPEND);
    echo "<p class='text-danger'>Invalid date format.</p>";
    return;
  }

  $date_timestamp = strtotime($new_serve_date);
  if (!$date_timestamp) {
    file_put_contents($logFile, "ERROR: Invalid date provided: $new_serve_date\n", FILE_APPEND);
    echo "<p class='text-danger'>Invalid date provided.</p>";
    return;
  }

  $calculated_weekday = date('l', $date_timestamp);
  if ($calculated_weekday !== $new_weekday) {
    file_put_contents($logFile, "ERROR: Weekday mismatch. Provided: $new_weekday, Calculated: $calculated_weekday\n", FILE_APPEND);
    echo "<p class='text-danger'>Weekday does not match the date provided.</p>";
    return;
  }

  $existing = $wpdb->get_row(
    $wpdb->prepare(
      "SELECT * FROM $schedule_table WHERE id = %d AND meal_plan_id = %d",
      $schedule_id,
      $meal_plan_id
    )
  );

  if (!$existing) {
    file_put_contents($logFile, "ERROR: Schedule not found for id: $schedule_id and meal_plan_id: $meal_plan_id\n", FILE_APPEND);
    echo "<p class='text-danger'>Schedule with given ID and meal plan ID not found.</p>";
    return;
  }

  $updated = $wpdb->update(
    $schedule_table,
    [
      'serve_date' => $new_serve_date,
      'weekday' => $new_weekday,
      'meal_type' => $new_meal_type,
      'delivery_window' => $new_delivery
    ],
    [
      'id' => $schedule_id,
      'meal_plan_id' => $meal_plan_id
    ],
    ['%s', '%s', '%s', '%s'],
    ['%d', '%d']
  );

  if ($updated === false) {
    $error = $wpdb->last_error;
    file_put_contents($logFile, "ERROR: Failed to update schedule. DB error: $error\n", FILE_APPEND);
    echo "<p class='text-danger'>Failed to update schedule. Please try again.</p>";
  } else if ($updated === 0) {
    file_put_contents($logFile, "INFO: No changes made, schedule data is the same for id: $schedule_id\n", FILE_APPEND);
    echo "<p class='text-warning'>No changes made, schedule data is the same.</p>";
  } else {
    file_put_contents($logFile, "SUCCESS: Schedule rescheduled successfully for id: $schedule_id\n", FILE_APPEND);
    echo "<p class='text-success'>Schedule rescheduled successfully.</p>";
  }
}
