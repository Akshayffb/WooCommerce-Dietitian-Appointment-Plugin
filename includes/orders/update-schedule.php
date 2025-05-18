<?php

function update_schedule($wpdb)
{
  $meal_plan_table = $wpdb->prefix . 'wdb_meal_plans';
  $schedule_table = $wpdb->prefix . 'wdb_meal_plan_schedules';
  $api_table = $wpdb->prefix . 'wdb_apis';

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

      if ($rows_updated !== false) {

        if (!empty($messages)) {
          echo "<p class='text-success'>" . implode(' ', $messages) . "</p>";
        } else {
          echo "<p class='text-success'>Schedule updated successfully.</p>";
        }

        $updated_data = array_merge($existing_entry, $data);
        send_schedule_update_api($api_table, $wpdb, $updated_data);
      } else {
        echo "<p class='text-danger'>Error updating the schedule. Please try again later.</p>";
      }
    } else {
      echo "<p class='text-muted'>No changes detected.</p>";
    }
  }
}



/**
 * Sends the updated schedule data to an external API.
 * For now, logs the data to a file before sending.
 *
 * @param array $data Updated schedule data
 */

function send_schedule_update_api($api_table, $wpdb, $data)
{
  $log_file = __DIR__ . '/schedule_update_log.txt';

  file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] send_schedule_update_api called\n", FILE_APPEND);

  $api = $wpdb->get_row(
    $wpdb->prepare("SELECT * FROM $api_table WHERE api_slug = %s AND is_active = 1 LIMIT 1", 'update-schedule'),
    ARRAY_A
  );

  if (!$api) {
    file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] API config for update-schedule not found.\n", FILE_APPEND);
    return;
  }

  file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] API config found: " . print_r($api, true) . "\n", FILE_APPEND);

  $headers = json_decode($api['headers'], true);
  if (!is_array($headers)) {
    $headers = [];
  }

  $headers['X-API-Key'] = $api['api_key'];

  $curl_headers = [];
  foreach ($headers as $key => $value) {
    $curl_headers[] = $key . ': ' . $value;
  }

  file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] Headers prepared for request:\n" . implode("\n", $curl_headers) . "\n", FILE_APPEND);

  $payload = json_encode($data);
  file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] Payload prepared for request:\n" . $payload . "\n", FILE_APPEND);

  // Uncomment to make the real API call when ready
  /*
    $ch = curl_init($api['endpoint']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] API response: " . $response . "\n", FILE_APPEND);

    if ($error) {
        file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] CURL error: " . $error . "\n", FILE_APPEND);
    }
    */

  file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] API call skipped (endpoint offline).\n\n", FILE_APPEND);
}

// function send_schedule_update_api($wpdb, $data)
// {
//   $log_file = __DIR__ . '/schedule_update_log.txt';

//   file_put_contents(__DIR__ . '/schedule_update_log.txt', "[" . date('Y-m-d H:i:s') . "] update_schedule called\n", FILE_APPEND);

//   // Fetch API details from DB
//   $api = $wpdb->get_row(
//     $wpdb->prepare("SELECT * FROM {$wpdb->prefix}api_keys WHERE slug = %s AND active = 1 LIMIT 1", 'update-schedule'),
//     ARRAY_A
//   );

//   if (!$api) {
//     // Could not find active API config
//     error_log("API config for update-schedule not found.");
//     return;
//   }

//   if (!$api) {
//     file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] API config for update-schedule not found.\n", FILE_APPEND);
//     return;
//   }

//   if (file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] API config found: " . print_r($api, true) . "\n", FILE_APPEND) === false) {
//     error_log("Failed to write to log file: $log_file");
//   }

//   // Decode headers JSON stored in DB
//   $headers = json_decode($api['headers'], true);
//   if (!is_array($headers)) {
//     $headers = [];
//   }

//   // Add your hashed key as an Authorization header or custom header (adjust as needed)
//   // For example, if your API expects header: X-API-Key: <hashed_key>
//   $headers['X-API-Key'] = $api['api_key'];

//   // Format headers for cURL
//   $curl_headers = [];
//   foreach ($headers as $key => $value) {
//     $curl_headers[] = $key . ': ' . $value;
//   }

//   // $payload = json_encode($data);

//   // $ch = curl_init($api['endpoint']);
//   // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//   // curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
//   // curl_setopt($ch, CURLOPT_POST, true);
//   // curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

//   // $response = curl_exec($ch);
//   // $error = curl_error($ch);
//   // curl_close($ch);

//   file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] Headers prepared for request:\n" . implode("\n", $curl_headers) . "\n", FILE_APPEND);

//   $payload = json_encode($data);
//   file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] Payload prepared for request:\n" . $payload . "\n", FILE_APPEND);

//   file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] API call skipped (endpoint offline).\n\n", FILE_APPEND);
// }
