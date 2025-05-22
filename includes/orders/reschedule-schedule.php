<?php

function reschedule_schedule($wpdb)
{
  $meal_plan_table = $wpdb->prefix . 'wdb_meal_plans';
  $schedule_table = $wpdb->prefix . 'wdb_meal_plan_schedules';

  if (
    !isset($_POST['cancel_reschedule_nonce']) ||
    !wp_verify_nonce($_POST['cancel_reschedule_nonce'], 'cancel_or_reschedule_action')
  ) {
    echo "<p class='text-danger'>Invalid or missing security token.</p>";
    return;
  }

  $required_fields = ['meal_plan_id', 'meal_plan_schedule_id', 'new_serve_date', 'new_weekday', 'new_meal_type', 'new_delivery'];
  foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
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
    echo "<p class='text-danger'>Invalid date format.</p>";
    return;
  }

  $date_timestamp = strtotime($new_serve_date);
  if (!$date_timestamp) {
    echo "<p class='text-danger'>Invalid date provided.</p>";
    return;
  }

  $calculated_weekday = date('l', $date_timestamp);
  if ($calculated_weekday !== $new_weekday) {
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
    echo "<p class='text-danger'>Schedule with given ID and meal plan ID not found.</p>";
    return;
  }

  $product_id = $wpdb->get_var(
    $wpdb->prepare(
      "SELECT product_id FROM $meal_plan_table WHERE id = %d",
      $meal_plan_id
    )
  );

  if (!$product_id) {
    echo "<p class='text-danger'>Product ID not found for the given meal plan.</p>";
    return;
  }

  $meal_info = calculate_meal_info($new_serve_date, $product_id);

  if (isset($meal_info['error'])) {
    error_log("Meal Info Error: " . $meal_info['error'] . "\n");
    $meal_name = 'N/A';
    $ingredients = 'N/A';
  } else {
    $meal_name = $meal_info['plan_name'];
    $ingredients = $meal_info['ingredients'];
  }

  $updated = $wpdb->update(
    $schedule_table,
    [
      'serve_date' => $new_serve_date,
      'weekday' => $new_weekday,
      'meal_info'       => "Meals: $meal_name | Ingredients: $ingredients",
      'meal_type' => $new_meal_type,
      'delivery_window' => $new_delivery
    ],
    [
      'id' => $schedule_id,
      'meal_plan_id' => $meal_plan_id
    ],
    ['%s', '%s', '%s', '%s', '%s'],
    ['%d', '%d']
  );

  if ($updated === false) {
    echo "<p class='text-danger'>Failed to update schedule. Please try again.</p>";
  } else if ($updated === 0) {
    echo "<p class='text-warning'>No changes made, schedule data is the same.</p>";
  } else {
    echo "<p class='text-success'>Schedule rescheduled successfully.</p>";
  }
}
