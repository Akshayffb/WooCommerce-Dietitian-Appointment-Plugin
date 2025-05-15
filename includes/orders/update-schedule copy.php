<?php

function log_debug($message)
{
  $log_file = __DIR__ . '/debug_log.txt';
  $date = date('Y-m-d H:i:s');
  file_put_contents($log_file, "[$date] $message\n", FILE_APPEND);
}

function update_schedule($wpdb)
{
  $meal_plan_table = $wpdb->prefix . 'wdb_meal_plans';
  $schedule_table = $wpdb->prefix . 'wdb_meal_plan_schedules';
  log_debug("Function called: update_schedule");

  if (isset($_POST['update_schedule_nonce']) && wp_verify_nonce($_POST['update_schedule_nonce'], 'update_schedule_action')) {
    log_debug("Nonce verified.");

    $order_id = intval($_POST['order_id']);
    $original_date = sanitize_text_field($_POST['original_date']);
    $original_meal_type = sanitize_text_field($_POST['original_meal_type']);
    $original_delivery = sanitize_text_field($_POST['original_delivery']);
    $new_date = sanitize_text_field($_POST['new-date']);
    $meal_type = sanitize_text_field($_POST['meal_type']);
    $delivery = sanitize_text_field($_POST['delivery']);

    log_debug("Input received - order_id: $order_id, original_date: $original_date, new_date: $new_date, meal_type: $meal_type, delivery: $delivery");

    // Fetch the meal plan
    $plan = $wpdb->get_row(
      $wpdb->prepare("SELECT * FROM $meal_plan_table WHERE order_id = %d", $order_id),
      ARRAY_A
    );
    log_debug("Fetched meal plan: " . print_r($plan, true));

    if (!$plan || $plan['user_id'] != get_current_user_id()) {
      log_debug("Invalid or unauthorized meal plan.");
      echo "<p class='text-danger'>Invalid or unauthorized meal plan.</p>";
      return;
    }

    $meal_plan_id = $plan['id'];
    $existing_entry = $wpdb->get_row(
      $wpdb->prepare("SELECT * FROM $schedule_table WHERE meal_plan_id = %d AND serve_date = %s", $meal_plan_id, $original_date),
      ARRAY_A
    );
    log_debug("Fetched existing schedule: " . print_r($existing_entry, true));

    if (!$existing_entry) {
      log_debug("No schedule found for the original date: $original_date.");
      echo "<p class='text-warning'>No schedule found for the original date: $original_date.</p>";
      return;
    }

    $data = [];
    $format = [];

    if (!empty($new_date) && $new_date !== $original_date) {
      $data['serve_date'] = $new_date;
      $format[] = '%s';
      log_debug("Scheduled date updated from $original_date to $new_date");
    }

    if (!empty($original_meal_type) && !empty($meal_type) && !empty($delivery)) {
      $meal_types = array_map('trim', explode(',', $existing_entry['meal_type']));
      $delivery_windows = array_map('trim', explode(',', $existing_entry['delivery_window']));
      log_debug("Current meal_types: " . implode(', ', $meal_types));
      log_debug("Current delivery_windows: " . implode(', ', $delivery_windows));

      $index = -1;
      foreach ($meal_types as $i => $type) {
        if (strtolower($type) === strtolower($original_meal_type)) {
          $index = $i;
          break;
        }
      }

      if ($index >= 0) {
        log_debug("Original meal type '$original_meal_type' found at index $index.");
        $meal_types[$index] = $meal_type;
        $delivery_windows[$index] = $delivery;
        $data['meal_type'] = implode(', ', $meal_types);
        $data['delivery_window'] = implode(', ', $delivery_windows);
        $format[] = '%s';
        $format[] = '%s';
        log_debug("Updated meal_types: " . implode(', ', $meal_types));
        log_debug("Updated delivery_windows: " . implode(', ', $delivery_windows));
      } else {
        log_debug("Original meal type '$original_meal_type' not found, appending new values.");
        $meal_types[] = $meal_type;
        $delivery_windows[] = $delivery;
        $data['meal_type'] = implode(', ', $meal_types);
        $data['delivery_window'] = implode(', ', $delivery_windows);
        $format[] = '%s';
        $format[] = '%s';
      }
    }

    if (!empty($data)) {
      log_debug("Data prepared for update: " . print_r($data, true));
      $rows_updated = $wpdb->update(
        $schedule_table,
        $data,
        ['id' => $existing_entry['id']],
        $format,
        ['%d']
      );
      log_debug("Rows updated: " . print_r($rows_updated, true));

      if ($rows_updated === false) {
        log_debug("Error occurred while updating schedule.");
        echo "<p class='text-danger'>Error updating the schedule.</p>";
      } else {
        log_debug("Schedule updated successfully.");
        echo "<p class='text-success'>Schedule updated successfully.</p>";
      }
    } else {
      log_debug("No changes detected in input. Nothing to update.");
      echo "<p class='text-muted'>No changes detected.</p>";
    }
  } else {
    log_debug("Nonce verification failed or POST data not set.");
  }
}
