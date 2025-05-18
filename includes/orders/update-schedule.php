<?php

function update_schedule($wpdb)
{
  $meal_plan_table = $wpdb->prefix . 'wdb_meal_plans';
  $schedule_table = $wpdb->prefix . 'wdb_meal_plan_schedules';

  if (isset($_POST['update_schedule_nonce']) && wp_verify_nonce($_POST['update_schedule_nonce'], 'update_schedule_action')) {

    $order_id = intval($_POST['order_id']);
    $record_id = intval($_POST['record_id']);
    $original_date = sanitize_text_field($_POST['original_date']);
    $original_meal_type = sanitize_text_field($_POST['original_meal_type']);
    $new_date = sanitize_text_field($_POST['new-date']);
    $meal_type = sanitize_text_field($_POST['meal_type']);
    $delivery = sanitize_text_field($_POST['delivery']);

    if (empty($order_id) || empty($record_id) || empty($original_date) || empty($new_date) || empty($meal_type) || empty($delivery)) {
      echo "<p class='text-danger'>All fields are required for update.</p>";
      return;
    }

    $plan = $wpdb->get_row(
      $wpdb->prepare("SELECT * FROM $meal_plan_table WHERE order_id = %d", $order_id),
      ARRAY_A
    );

    if (!$plan || $plan['user_id'] != get_current_user_id()) {
      echo "<p class='text-danger'>Invalid or unauthorized meal plan.</p>";
      return;
    }

    $meal_plan_id = $plan['id'];
    $existing_entry = $wpdb->get_row(
      $wpdb->prepare("SELECT * FROM $schedule_table WHERE id = %d AND meal_plan_id = %d", $record_id, $meal_plan_id),
      ARRAY_A
    );

    if (!$existing_entry) {
      echo "<p class='text-warning'>No schedule found for the original date: $original_date.</p>";
      return;
    }

    $data = [];
    $format = [];
    $messages = [];

    if (!empty($new_date) && $new_date !== $original_date) {
      $data['serve_date'] = $new_date;
      $format[] = '%s';
      $messages[] = "Date changed from $original_date to $new_date.";
    }

    if (!empty($original_meal_type) && !empty($meal_type) && !empty($delivery)) {
      $meal_types = array_map('trim', explode(',', $existing_entry['meal_type']));
      $delivery_windows = array_map('trim', explode(',', $existing_entry['delivery_window']));

      $index = -1;
      foreach ($meal_types as $i => $type) {
        if (strtolower($type) === strtolower($original_meal_type)) {
          $index = $i;
          break;
        }
      }

      if ($index >= 0) {
        if (strtolower($meal_types[$index]) !== strtolower($meal_type)) {
          $messages[] = "Meal type changed from '{$meal_types[$index]}' to '$meal_type'.";
          $meal_types[$index] = $meal_type;
        }
        if (strtolower($delivery_windows[$index]) !== strtolower($delivery)) {
          $messages[] = "Delivery window changed from '{$delivery_windows[$index]}' to '$delivery'.";
          $delivery_windows[$index] = $delivery;
        }

        $data['meal_type'] = implode(', ', $meal_types);
        $data['delivery_window'] = implode(', ', $delivery_windows);
        $format[] = '%s';
        $format[] = '%s';
      } else {

        $meal_types[] = $meal_type;
        $delivery_windows[] = $delivery;
        $data['meal_type'] = implode(', ', $meal_types);
        $data['delivery_window'] = implode(', ', $delivery_windows);
        $format[] = '%s';
        $format[] = '%s';

        $messages[] = "New meal type '$meal_type' and delivery window '$delivery' added.";
      }
    }

    if (!empty($data)) {
      $rows_updated = $wpdb->update(
        $schedule_table,
        $data,
        ['id' => $existing_entry['id']],
        $format,
        ['%d']
      );

      if ($rows_updated === false) {
        echo "<p class='text-danger'>Error updating the schedule. Please try again later.</p>";
      } else {
        if (!empty($messages)) {
          echo "<p class='text-success'>" . implode(' ', $messages) . "</p>";
        } else {
          echo "<p class='text-success'>Schedule updated successfully.</p>";
        }
      }
    } else {
      echo "<p class='text-muted'>No changes detected.</p>";
    }
  }
}
