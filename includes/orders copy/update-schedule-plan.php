<?php

add_action('wp_ajax_save_schedule_data', 'handle_save_schedule_data');
add_action('wp_ajax_nopriv_save_schedule_data', 'handle_save_schedule_data');

function handle_save_schedule_data()
{
  if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'custom_plugin_nonce')) {
    die('Permission denied');
  }

  // Get the form data from the AJAX request
  $date = sanitize_text_field($_POST['date']);
  $weekday = sanitize_text_field($_POST['weekday']);
  $meal_type = sanitize_text_field($_POST['meal_type']);
  $delivery_time = sanitize_text_field($_POST['delivery_time']);

  wp_send_json_success([
    'date' => $date,
    'weekday' => $weekday,
    'meal_type' => $meal_type,
    'delivery_time' => $delivery_time,
    'message' => 'Schedule updated successfully.',
  ]);

  // Validate the date to ensure it's not in the past
  if (strtotime($date) < time()) {
    wp_send_json_error(array('message' => 'Date cannot be in the past.'));
  }

  // Check if schedule exists for this date (replace with your own logic)
  $existing_schedule = get_post_meta($date, '_meal_schedule', true); // Example logic, customize as needed

  // Update or create new schedule (replace with your own logic)
  if ($existing_schedule) {
    // Update logic (this is just an example)
    update_post_meta($existing_schedule, '_meal_type', $meal_type);
    update_post_meta($existing_schedule, '_delivery_time', $delivery_time);
  } else {
    // Insert new schedule (this is just an example)
    $new_schedule = array(
      'post_title' => $date,
      'post_type' => 'meal_schedule', // Your custom post type for schedules
      'post_status' => 'publish',
    );
    $schedule_id = wp_insert_post($new_schedule);

    update_post_meta($schedule_id, '_meal_type', $meal_type);
    update_post_meta($schedule_id, '_delivery_time', $delivery_time);
  }

  wp_send_json_success(array('message' => 'Schedule updated successfully.'));
}
