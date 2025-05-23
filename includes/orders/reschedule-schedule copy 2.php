<?php

function reschedule_schedule($wpdb)
{
  if (!verify_nonce()) {
    echo error_msg('Invalid or missing security token.');
    return;
  }

  $fields = extract_required_fields([
    'meal_plan_id',
    'meal_plan_schedule_id',
    'new_serve_date',
    'new_weekday',
    'new_meal_type',
    'new_delivery'
  ]);
  if (!$fields) return;

  extract($fields); // now you can use $meal_plan_id, $new_serve_date, etc.

  if (!validate_date_format($new_serve_date)) {
    echo error_msg('Invalid date format.');
    return;
  }

  if (!validate_weekday($new_serve_date, $new_weekday)) {
    echo error_msg('Weekday does not match the date provided.');
    return;
  }

  $schedule = get_existing_schedule($wpdb, $meal_plan_schedule_id, $meal_plan_id);
  if (!$schedule) {
    echo error_msg('Schedule with given ID and meal plan ID not found.');
    return;
  }

  $product_id = get_product_id($wpdb, $meal_plan_id);
  if (!$product_id) {
    echo error_msg('Product ID not found for the given meal plan.');
    return;
  }

  $meal_info = calculate_meal_info($new_serve_date, $product_id);
  $meal_name = $meal_info['plan_name'] ?? 'N/A';
  $ingredients = $meal_info['ingredients'] ?? 'N/A';

  $updated = update_meal_schedule($wpdb, $meal_plan_schedule_id, $meal_plan_id, [
    'serve_date' => $new_serve_date,
    'weekday' => $new_weekday,
    'meal_info' => "Meals: $meal_name | Ingredients: $ingredients",
    'meal_type' => $new_meal_type,
    'delivery_window' => $new_delivery
  ]);

  if ($updated === false) {
    echo error_msg('Failed to update schedule. Please try again.');
  } elseif ($updated === 0) {
    echo "<p class='text-warning'>No changes made, schedule data is the same.</p>";
  } else {
    echo success_msg('Schedule rescheduled successfully.');
    $user_id = get_current_user_id();
    send_schedule_update_to_api($wpdb, $fields, $user_id);
  }
}

// ------------------ Modular Functions ------------------

function verify_nonce()
{
  return isset($_POST['cancel_reschedule_nonce']) &&
    wp_verify_nonce($_POST['cancel_reschedule_nonce'], 'cancel_or_reschedule_action');
}

function extract_required_fields($required_fields)
{
  $data = [];
  foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
      echo error_msg("Missing required field: $field");
      return false;
    }
    $data[$field] = sanitize_text_field($_POST[$field]);
    if (in_array($field, ['meal_plan_id', 'meal_plan_schedule_id'])) {
      $data[$field] = intval($data[$field]);
    }
  }
  return $data;
}

function validate_date_format($date)
{
  return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date);
}

function validate_weekday($date, $expected_day)
{
  return date('l', strtotime($date)) === $expected_day;
}

function get_existing_schedule($wpdb, $schedule_id, $meal_plan_id)
{
  $table = $wpdb->prefix . 'wdb_meal_plan_schedules';
  return $wpdb->get_row(
    $wpdb->prepare(
      "SELECT * FROM $table WHERE id = %d AND meal_plan_id = %d",
      $schedule_id,
      $meal_plan_id
    )
  );
}

function get_product_id($wpdb, $meal_plan_id)
{
  $table = $wpdb->prefix . 'wdb_meal_plans';
  return $wpdb->get_var(
    $wpdb->prepare(
      "SELECT product_id FROM $table WHERE id = %d",
      $meal_plan_id
    )
  );
}

function update_meal_schedule($wpdb, $schedule_id, $meal_plan_id, $data)
{
  $table = $wpdb->prefix . 'wdb_meal_plan_schedules';
  return $wpdb->update(
    $table,
    $data,
    ['id' => $schedule_id, 'meal_plan_id' => $meal_plan_id],
    ['%s', '%s', '%s', '%s', '%s'],
    ['%d', '%d']
  );
}

function error_msg($text)
{
  return "<p class='text-danger'>$text</p>";
}

function success_msg($text)
{
  return "<p class='text-success'>$text</p>";
}

function send_schedule_update_to_api($wpdb, $data, $user_id = null)
{
  $api_slug = 'update-schedule';
  $api_table = $wpdb->prefix . 'wdb_apis';
  $api_log_table = $wpdb->prefix . 'wdb_api_logs';

  $api_config = $wpdb->get_row(
    $wpdb->prepare(
      "SELECT * FROM $api_table WHERE api_slug = %s AND is_active = 1",
      $api_slug
    )
  );

  if (!$api_config) {
    error_log("API config not found or inactive for slug: $api_slug");
    return;
  }

  $url = $api_config->endpoint;
  $method = strtoupper($api_config->method);
  $headers = ['Content-Type' => 'application/json'];

  if (!empty($api_config->headers)) {
    $custom_headers = json_decode($api_config->headers, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($custom_headers)) {
      $headers = array_merge($headers, $custom_headers);
    }
  }

  // Generate HMAC signature if keys are present
  if (!empty($api_config->public_key) && !empty($api_config->secret_key)) {
    $body = json_encode($data);
    $key = $api_config->public_key . '_' . $api_config->secret_key;
    $signature = 'sha1=' . hash_hmac('sha1', $body, $key, false);

    $headers['X-PUBLIC-KEY'] = $api_config->public_key;
    $headers['X-SIGNATURE'] = $signature;
  }

  // if (!empty($api_config->api_key) && !empty($api_config->secret_salt)) {
  //   $token = hash_hmac('sha256', $api_config->api_key, $api_config->secret_salt);
  //   $headers['X-API-TOKEN'] = $token;
  // } elseif (!empty($api_config->api_key)) 
  //   $headers['X-API-TOKEN'] = $api_config->api_key;
  // }

  $args = [
    'headers' => $headers,
    'method'  => $method,
    'body'    => json_encode($data),
    'timeout' => 10,
  ];

  $start_time = microtime(true);
  $response = wp_remote_request($url, $args);
  $end_time = microtime(true);

  $duration_ms = round(($end_time - $start_time) * 1000);
  $status_code = 0;
  $response_text = '';
  $error_message = null;

  if (is_wp_error($response)) {
    $error_message = $response->get_error_message();
    error_log("API Error ($api_slug): $error_message");
  } else {
    $status_code = wp_remote_retrieve_response_code($response);
    $response_text = wp_remote_retrieve_body($response);

    if ($status_code !== 200) {
      error_log("API ($api_slug) returned status $status_code. Response: $response_text");
    } else {
      error_log("API ($api_slug) success. Response: $response_text");
    }
  }

  $wpdb->insert(
    $api_log_table,
    [
      'user_id'        => $user_id,
      'api_slug'       => $api_slug,
      'request_payload' => json_encode($data),
      'response_text'  => $response_text,
      'status_code'    => $status_code,
      'error_message'  => $error_message,
      'retries'        => 0,
      'ip_address'     => $_SERVER['REMOTE_ADDR'] ?? null,
      'user_agent'     => $_SERVER['HTTP_USER_AGENT'] ?? null,
      'duration_ms'    => $duration_ms,
      'created_at'     => current_time('mysql'),
    ],
    ['%d', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%d', '%s']
  );
}
