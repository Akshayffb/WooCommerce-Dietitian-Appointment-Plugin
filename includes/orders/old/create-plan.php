<?php
// function wdb_generate_plan_schedule($order_id)
// {
//   error_log("Working : $order_id");

//   global $wpdb;

//   $plan = $wpdb->get_row($wpdb->prepare(
//     "SELECT * FROM {$wpdb->prefix}wdb_meal_plans WHERE order_id = %d LIMIT 1",
//     $order_id
//   ));

//   if (!$plan) {
//     error_log("No meal plan found for order ID: $order_id");
//     return;
//   }

//   $selected_days = maybe_unserialize($plan->selected_days);
//   $meal_types    = maybe_unserialize($plan->meal_type);

//   $start_date    = $plan->start_date;
//   $plan_duration = (int)$plan->plan_duration;
//   $time_slot     = $plan->time;
//   $category      = $plan->category;
//   $product_id    = $plan->product_id;

//   $schedule_data = [];
//   $current_date = new DateTime($start_date);
//   $deliveries_made = 0;

//   while ($deliveries_made < $plan_duration) {
//     $weekday = strtolower($current_date->format('l'));

//     if (in_array($weekday, $selected_days)) {
//       $formatted_date = $current_date->format('Y-m-d');
//       $meal_plan_info = calculate_meal_info($formatted_date, $product_id);

//       if (isset($meal_plan_info['error'])) {
//         error_log("Meal plan error for $formatted_date: " . $meal_plan_info['error']);
//         $meal_name = 'N/A';
//         $ingredients = 'N/A';
//       } else {
//         $meal_name = $meal_plan_info['plan_name'];
//         $ingredients = $meal_plan_info['ingredients'];
//       }

//       $schedule_data[] = [
//         'no' => $deliveries_made + 1,
//         'date' => $current_date->format('d M Y'),
//         'day' => ucfirst($weekday),
//         'meals' => $meal_name,
//         'times' => $time_slot,
//         'meal_type' => $meal_types,
//         'ingredients' => $ingredients,
//         'category' => $category,
//       ];
//       $deliveries_made++;
//     }

//     $current_date->modify('+1 day');
//   }

//   error_log("Meal Schedule for Order #$order_id: " . print_r($schedule_data, true));

//   $meals_table = $wpdb->prefix . 'wdb_meal_plan_schedules';

//   foreach ($schedule_data as $schedule) {
//     $wpdb->insert(
//       $meals_table,
//       [
//         'meal_plan_id'    => $plan->id,
//         'serve_date'      => DateTime::createFromFormat('d M Y', $schedule['date'])->format('Y-m-d'),
//         'weekday'         => $schedule['day'],
//         'meal_info'       => "Meals: " . $schedule['meals'] . " | Ingredients: " . $schedule['ingredients'],
//         'meal_type'       => implode(', ', $schedule['meal_type']),
//         'delivery_window' => $schedule['times'],
//       ]
//     );
//   }
// }


// function calculate_meal_info($date_str, $product_id)
// {
//   $date = new DateTime($date_str);
//   $day_name = $date->format('l');         // e.g., 'Monday'
//   $weekday_number = (int)$date->format('N'); // 1 (Mon) to 7 (Sun)
//   $day_of_month = (int)$date->format('j');

//   $occurrence = 0;
//   for ($i = 1; $i <= $day_of_month; $i++) {
//     $loop_date = new DateTime($date->format('Y-m') . '-' . str_pad($i, 2, '0', STR_PAD_LEFT));
//     if ((int)$loop_date->format('N') === $weekday_number) {
//       $occurrence++;
//     }
//   }

//   // 🔄 Get meal-category terms for this product
//   $acf_terms = get_field('acf_meal_plan_category', $product_id);
//   if (!is_array($acf_terms)) {
//     $acf_terms = [$acf_terms];
//   }

//   $term_ids = [];
//   foreach ($acf_terms as $term) {
//     if (is_object($term) && isset($term->term_id)) {
//       $term_ids[] = $term->term_id;
//     } elseif (is_string($term)) {
//       $term_obj = get_term_by('slug', $term, 'meal-category');
//       if ($term_obj) {
//         $term_ids[] = $term_obj->term_id;
//       }
//     }
//   }

//   if (empty($term_ids)) {
//     return ['error' => 'No valid category terms found.'];
//   }

//   // 🔍 Query all plans in this category
//   $query_args = [
//     'post_type' => 'meal-plan',
//     'posts_per_page' => -1,
//     'tax_query' => [
//       [
//         'taxonomy' => 'meal-category',
//         'field' => 'term_id',
//         'terms' => $term_ids,
//       ]
//     ]
//   ];

//   $matched_plan = null;
//   $plans = new WP_Query($query_args);

//   if ($plans->have_posts()) {
//     while ($plans->have_posts()) {
//       $plans->the_post();
//       $plan_id = get_the_ID();
//       $plan_week = (int) get_field('week_number', $plan_id);
//       $plan_day_terms = get_the_terms($plan_id, 'meal-days');

//       if (empty($plan_day_terms)) {
//         continue;
//       }

//       foreach ($plan_day_terms as $term) {
//         $term_name = $term->name;
//         $plan_day_num = is_numeric($term_name) ? intval($term_name) : [
//           'Monday' => 1,
//           'Tuesday' => 2,
//           'Wednesday' => 3,
//           'Thursday' => 4,
//           'Friday' => 5,
//           'Saturday' => 6,
//           'Sunday' => 7
//         ][$term_name] ?? 0;

//         // ✅ Match the exact day AND occurrence
//         if ($plan_day_num === $weekday_number && $occurrence === $plan_week) {
//           // If there's a match, return the meal plan details
//           return [
//             'plan_name' => get_the_title(),
//             'ingredients' => get_field('meal_ingredients', $plan_id),
//           ];
//         }
//       }
//     }
//   }

//   return ['error' => 'No matching meal plan found.'];
// }
