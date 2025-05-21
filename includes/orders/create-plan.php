<?php
function wdb_generate_plan_schedule($order_id)
{
  global $wpdb;

  $plan = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}wdb_meal_plans WHERE order_id = %d LIMIT 1",
    $order_id
  ));

  if (!$plan) {
    error_log("No meal plan found for order ID: $order_id");
    return;
  }

  // Unserialize the selected_days and meal_type fields
  $selected_days = maybe_unserialize($plan->selected_days);
  $raw_meal_types = maybe_unserialize($plan->meal_type);

  // Extract individual meal parts without hardcoding
  $meal_parts = [];

  foreach ($raw_meal_types as $meal) {
    $parts = preg_split('/\s+/', trim($meal));
    foreach ($parts as $part) {
      $cleaned = ucfirst(strtolower(trim($part)));
      if (!empty($cleaned) && !in_array($cleaned, $meal_parts)) {
        $meal_parts[] = $cleaned;
      }
    }
  }

  $cleaned_meal_types = $meal_parts;

  $start_date    = $plan->start_date;
  $plan_duration = (int)$plan->plan_duration;
  $time_slots    = array_map('trim', explode(',', $plan->time)); // delivery windows
  $category      = $plan->category;
  $product_id    = $plan->product_id;

  $current_date = new DateTime($start_date);
  $deliveries_made = 0;

  while ($deliveries_made < $plan_duration) {
    $weekday = strtolower($current_date->format('l'));

    if (in_array($weekday, $selected_days)) {
      $formatted_date = $current_date->format('Y-m-d');
      $meal_plan_info = calculate_meal_info($formatted_date, $product_id);

      if (isset($meal_plan_info['error'])) {
        error_log("Meal plan error for $formatted_date: " . $meal_plan_info['error']);
        $meal_name = 'N/A';
        $ingredients = 'N/A';
      } else {
        $meal_name = $meal_plan_info['plan_name'];
        $ingredients = $meal_plan_info['ingredients'];
      }

      // Insert one row per meal type with matching delivery window or fallback to last
      foreach ($cleaned_meal_types as $index => $meal_type) {
        $delivery_window = $time_slots[$index] ?? end($time_slots) ?? 'N/A';

        $wpdb->insert(
          $wpdb->prefix . 'wdb_meal_plan_schedules',
          [
            'meal_plan_id'    => $plan->id,
            'serve_date'      => $formatted_date,
            'weekday'         => ucfirst($weekday),
            'meal_info'       => "Meals: $meal_name | Ingredients: $ingredients",
            'meal_type'       => $meal_type,
            'delivery_window' => $delivery_window,
            'status'          => $plan->status ?? 'active',
            'message'         => null,
          ]
        );
      }

      $deliveries_made++;
    }

    $current_date->modify('+1 day');
  }
}

function calculate_meal_info($date_str, $product_id)
{
  $date = new DateTime($date_str);
  $day_name = $date->format('l');         // e.g., 'Monday'
  $weekday_number = (int)$date->format('N'); // 1 (Mon) to 7 (Sun)
  $day_of_month = (int)$date->format('j');

  $occurrence = 0;
  for ($i = 1; $i <= $day_of_month; $i++) {
    $loop_date = new DateTime($date->format('Y-m') . '-' . str_pad($i, 2, '0', STR_PAD_LEFT));
    if ((int)$loop_date->format('N') === $weekday_number) {
      $occurrence++;
    }
  }

  // 🔄 Get meal-category terms for this product
  $acf_terms = get_field('acf_meal_plan_category', $product_id);
  if (!is_array($acf_terms)) {
    $acf_terms = [$acf_terms];
  }

  $term_ids = [];
  foreach ($acf_terms as $term) {
    if (is_object($term) && isset($term->term_id)) {
      $term_ids[] = $term->term_id;
    } elseif (is_string($term)) {
      $term_obj = get_term_by('slug', $term, 'meal-category');
      if ($term_obj) {
        $term_ids[] = $term_obj->term_id;
      }
    }
  }

  if (empty($term_ids)) {
    return ['error' => 'No valid category terms found.'];
  }

  // 🔍 Query all plans in this category
  $query_args = [
    'post_type' => 'meal-plan',
    'posts_per_page' => -1,
    'tax_query' => [
      [
        'taxonomy' => 'meal-category',
        'field' => 'term_id',
        'terms' => $term_ids,
      ]
    ]
  ];

  $matched_plan = null;
  $plans = new WP_Query($query_args);

  if ($plans->have_posts()) {
    while ($plans->have_posts()) {
      $plans->the_post();
      $plan_id = get_the_ID();
      $plan_week = (int) get_field('week_number', $plan_id);
      $plan_day_terms = get_the_terms($plan_id, 'meal-days');

      if (empty($plan_day_terms)) {
        continue;
      }

      foreach ($plan_day_terms as $term) {
        $term_name = $term->name;
        $plan_day_num = is_numeric($term_name) ? intval($term_name) : [
          'Monday' => 1,
          'Tuesday' => 2,
          'Wednesday' => 3,
          'Thursday' => 4,
          'Friday' => 5,
          'Saturday' => 6,
          'Sunday' => 7
        ][$term_name] ?? 0;

        // ✅ Match the exact day AND occurrence
        if ($plan_day_num === $weekday_number && $occurrence === $plan_week) {
          // If there's a match, return the meal plan details
          return [
            'plan_name' => get_the_title(),
            'ingredients' => get_field('ingredients', $plan_id),
          ];
        }
      }
    }
    wp_reset_postdata();
  }

  return ['error' => 'No matching meal plan found.'];
}
