<?php
function wdb_generate_plan_schedule($order_id)
{

  error_log("create-plan copy : $order_id");

  global $wpdb;

  $plan = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}wdb_meal_plans WHERE order_id = %d LIMIT 1",
    $order_id
  ));

  if (!$plan) {
    error_log("No meal plan found for order ID: $order_id");
    return;
  }

  $selected_days = maybe_unserialize($plan->selected_days);
  $meal_types    = maybe_unserialize($plan->meal_type);

  $start_date    = $plan->start_date;
  $plan_duration = (int)$plan->plan_duration;
  $time_slot     = $plan->time;
  $ingredients   = $plan->ingredients;
  $plan_name     = $plan->plan_name;
  $category      = $plan->category;

  $schedule_data = [];
  $current_date = new DateTime($start_date);
  $deliveries_made = 0;

  while ($deliveries_made < $plan_duration) {
    $weekday = strtolower($current_date->format('l'));

    if (in_array($weekday, $selected_days)) {
      $schedule_data[] = [
        'no' => $deliveries_made + 1,
        'date' => $current_date->format('d M Y'),
        'day' => ucfirst($weekday),
        'meals' => $plan_name,
        'times' => $time_slot,
        'meal_type' => $meal_types,
        'ingredients' => $ingredients,
        'category' => $category,
      ];
      $deliveries_made++;
    }

    $current_date->modify('+1 day');
  }

  error_log("Meal Schedule for Order #$order_id: " . print_r($schedule_data, true));

  // You can also optionally store $schedule_data in another table here
  $meals_table = $wpdb->prefix . 'wdb_meal_plan_schedules';

  foreach ($schedule_data as $schedule) {
    $meal_info = "Meals: " . $schedule['meals'] . " | Ingredients: " . $schedule['ingredients'];

    $wpdb->insert(
      $meals_table,
      [
        'meal_plan_id'    => $plan->id, // Assuming the meal plan table has an ID
        'serve_date'      => $current_date->format('Y-m-d'),
        'weekday'         => $schedule['day'],
        'meal_info'       => $meal_info,
        'meal_type'       => implode(', ', $schedule['meal_type']),
        'delivery_window' => $schedule['times'], // Delivery time window
      ]
    );
  }
}
