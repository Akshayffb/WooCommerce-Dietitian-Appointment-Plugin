<?php

function cancel_schedule($wpdb)
{
  $schedule_table = $wpdb->prefix . 'wdb_meal_plan_schedules';
  $api_table = $wpdb->prefix . 'wdb_apis';

  if (isset($_POST['cancel_reschedule_nonce']) && wp_verify_nonce($_POST['cancel_reschedule_nonce'], 'cancel_or_reschedule_action')) {

    $meal_id = isset($_POST['meal_id']) ? intval($_POST['meal_id']) : 0;
    $meal_plan_id = isset($_POST['meal_plan_id']) ? intval($_POST['meal_plan_id']) : 0;
    $serve_date = isset($_POST['serve_date']) ? sanitize_text_field($_POST['serve_date']) : '';

    if (!$meal_id || !$meal_plan_id || empty($serve_date)) {
      echo "<p class='text-danger'>All fields are required for cancel.</p>";
      return;
    }

    $record = $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM $schedule_table WHERE id = %d AND meal_plan_id = %d AND serve_date = %s",
      $meal_id,
      $meal_plan_id,
      $serve_date
    ));

    if (!$record) {
      echo "<p class='text-warning'>No matching schedule found.</p>";
      return;
    }

    $updated = $wpdb->update(
      $schedule_table,
      ['status' => 'cancelled'],
      ['id' => $meal_id, 'meal_plan_id' => $meal_plan_id, 'serve_date' => $serve_date],
      ['%s'],
      ['%d', '%d', '%s']
    );

    if ($updated !== false) {
      echo "<p class='text-success'>Schedule cancelled successfully.</p>";

      $updated_data = [
        'id' => $meal_id,
        'meal_plan_id' => $meal_plan_id,
        'serve_date' => $serve_date,
        'status' => 'cancelled',
      ];

      send_cancel_schedule_api($api_table, $wpdb, $updated_data);
    } else {
      echo "<p class='text-danger'>No changes made to the schedule.</p>";
    }
  } else {
    echo "<p class='text-muted'>Invalid request.</p>";
  }
}


/**
 * Sends the cancel schedule data to an external API.
 *
 * @param string $api_table The API table name
 * @param wpdb $wpdb The WordPress DB instance
 * @param array $data The cancel schedule data to send
 */
function send_cancel_schedule_api($api_table, $wpdb, $data)
{
  $api_slug = 'cancel-schedule';

  $log_file = __DIR__ . '/cancel_schedule_log.txt';

  $api = $wpdb->get_row(
    $wpdb->prepare("SELECT * FROM $api_table WHERE api_slug = %s AND is_active = 1 LIMIT 1", $api_slug),
    ARRAY_A
  );

  if (!$api) {
    file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] API config for $api_slug not found.\n", FILE_APPEND);
    return;
  }

  $headers = json_decode($api['headers'], true);
  if (!is_array($headers)) {
    $headers = [];
  }
  $headers['X-API-Key'] = $api['api_key'];

  $curl_headers = [];
  foreach ($headers as $key => $value) {
    $curl_headers[] = "$key: $value";
  }

  $payload = json_encode($data);
  $start = microtime(true);

  // Make the actual API request
  $ch = curl_init($api['endpoint']);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

  $response = curl_exec($ch);
  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curl_error = curl_error($ch);
  curl_close($ch);

  $duration = round((microtime(true) - $start) * 1000);
  $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
  $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

  // Insert API call log
  $wpdb->insert(
    $wpdb->prefix . 'wdb_api_logs',
    [
      'user_id'         => get_current_user_id(),
      'api_slug'        => $api_slug,
      'request_payload' => $payload,
      'response_text'   => $response ?: '',
      'status_code'     => $http_code ?: 0,
      'error_message'   => $curl_error ?: null,
      'retries'         => 0,
      'ip_address'      => $ip_address,
      'user_agent'      => $user_agent,
      'duration_ms'     => $duration,
      'created_at'      => current_time('mysql'),
    ],
    ['%d', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%d', '%s']
  );

  // Also keep file logs if needed
  file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] API called, status: $http_code, error: $curl_error\n", FILE_APPEND);
}
