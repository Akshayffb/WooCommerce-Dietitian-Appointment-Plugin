<?php

add_action('wp_ajax_save_update_schedule', 'save_update_schedule_callback');
add_action('wp_ajax_nopriv_save_update_schedule', 'save_update_schedule_callback');

function save_update_schedule_callback()
{
  // Check nonce
  if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'custom_plugin_nonce')) {
    wp_send_json_error(['message' => 'Nonce verification failed']);
  }

  // Sanitize inputs
  $date = sanitize_text_field($_POST['date']);
  $weekday = sanitize_text_field($_POST['weekday']);
  $meal_type = sanitize_text_field($_POST['meal_type']);
  $delivery_time = sanitize_text_field($_POST['delivery_time']);

  global $wpdb;
  $table = $wpdb->prefix . 'wdb_meal_plan_schedules';

  $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE date = %s", $date));

  if ($existing) {
    $wpdb->update(
      $table,
      [
        'meal_type'     => $meal_type,
        'delivery_time' => $delivery_time,
        'weekday'       => $weekday,
      ],
      ['id' => $existing]
    );
    $id = $existing;
  } else {
    $wpdb->insert(
      $table,
      [
        'date'          => $date,
        'meal_type'     => $meal_type,
        'delivery_time' => $delivery_time,
        'weekday'       => $weekday,
      ]
    );
    $id = $wpdb->insert_id;
  }

  wp_send_json_success([
    'message'       => 'Schedule updated successfully!',
    'id'            => $id,
    'meal_type'     => $meal_type,
    'delivery_time' => $delivery_time,
  ]);
}
