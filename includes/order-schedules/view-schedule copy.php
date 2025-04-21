<?php
if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

// Get the order ID from the query var
$order_id = absint(get_query_var('view-schedule'));

if (!$order_id) {
  echo '<p>Invalid order.</p>';
  return;
}

$order = wc_get_order($order_id);
if (!$order) {
  echo '<p>Order not found.</p>';
  return;
}

function get_plan_duration_from_title($title)
{
  preg_match('/^\d+/', $title, $matches);
  return !empty($matches) ? (int) $matches[0] : null;
}

// Order link and date
$order_link = esc_url(wc_get_endpoint_url('view-order', $order_id, wc_get_page_permalink('myaccount')));
$order_date = wc_format_datetime($order->get_date_created());

$schedule_data = [];
?>

<div class="order-details mb-3 p-4 rounded bg-light">
  <p class="font-weight-bold mb-1">
    Order <a href="<?php echo $order_link; ?>">#<?php echo esc_html($order->get_id()); ?></a>
    was placed on <?php echo esc_html($order_date); ?>
  </p>

  <!-- Working fine -->
  <?php foreach ($order->get_items() as $item_id => $item) : ?>
    <?php
    $product_name = esc_html($item->get_name());
    $start_date = '';
    $delivery_days = [];
    $meal_type = [];
    $delivery_time = [];

    // Get product meta data
    foreach ($item->get_meta_data() as $meta) {
      $meta_key = strtolower($meta->key);
      $meta_value = $meta->value;

      if ($meta_key === 'start date' && !empty($meta_value)) {
        $start_date = esc_html($meta_value);
      }

      if ($meta_key === 'select days' && !empty($meta_value)) {
        // Convert input into an array and remove empty values
        $days_array = array_filter(array_map('trim', preg_split('/[\s,|]+/', strtolower($meta_value))));

        // Remove duplicate values
        $delivery_days = array_unique($days_array);
      }

      if ($meta_key === 'meal type' && !empty($meta_value)) {
        $meal_type = array_map('trim', explode('|', $meta_value));
      }

      if ($meta_key === 'lunch time' && !empty($meta_value)) {
        $delivery_time = array_map('trim', explode('|', $meta_value));
      }
    }

    $meal_type = !empty($meal_type) ? array_map('trim', explode('|', implode('|', $meal_type))) : [];
    $delivery_time = !empty($delivery_time) ? array_map('trim', explode('|', implode('|', $delivery_time))) : [];

    $start_timestamp = !empty($start_date) ? strtotime($start_date) : false;

    echo '<div class="plan-details">
    <p class="mb-1"><strong>Plan: </strong>' . esc_html($product_name) . '</p>
    <p class="mb-0"><strong>Start Date:</strong> ' . ($start_date ? date('F j, Y', $start_timestamp) : 'Not specified') . '</p>
  </div>';

    $product_id = $item->get_product_id();

    $acf_terms = get_field('acf_meal_plan_category', $product_id);

    echo '<h5>ACF Category Field:</h5><pre>';
    print_r($acf_terms);
    echo '</pre>';

    if (!is_array($acf_terms)) {
      $acf_terms = [$acf_terms];
    }

    $term_ids = [];

    foreach ($acf_terms as $term_item) {
      if (is_object($term_item) && isset($term_item->term_id)) {
        $term_ids[] = $term_item->term_id;
      } elseif (is_string($term_item)) {
        $term_obj = get_term_by('slug', $term_item, 'meal-category');
        if ($term_obj) {
          $term_ids[] = $term_obj->term_id;
        }
      }
    }

    if (!empty($term_ids)) {
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

      $related_plans = new WP_Query($query_args);

      if ($related_plans->have_posts()) {
        // Map number to day name
        $day_map = [
          1 => 'Monday',
          2 => 'Tuesday',
          3 => 'Wednesday',
          4 => 'Thursday',
          5 => 'Friday',
          6 => 'Saturday',
          7 => 'Sunday'
        ];

        $grouped_meals = [];

        while ($related_plans->have_posts()) {
          $related_plans->the_post();

          $day_number = get_field('week_number'); // Numeric (1-7)

          $day_name = isset($day_map[$day_number]) ? $day_map[$day_number] : 'Unknown';

          $grouped_meals[$day_name][] = [
            'plan_name'     => get_field('plan_name'),
            'meal_image'    => get_field('meal_image'),
            'ingredients'   => get_field('ingredients'),
            'calories'      => get_field('calories_kcal'),
            'carbohydrates' => get_field('carbohydrates_g'),
            'protein'       => get_field('protein_g'),
            'fat'           => get_field('fat_g'),
            'week_number'   => $day_number,
          ];
        }

        wp_reset_postdata();

        // Output grouped by weekday
        echo '<h5>📅 Meal Plans by Day:</h5>';
        foreach ($day_map as $day_num => $day_name) {
          if (isset($grouped_meals[$day_name])) {
            echo '<h3 style="margin-top:30px;">' . esc_html($day_name) . '</h3>';
            foreach ($grouped_meals[$day_name] as $plan) {
              echo '<div style="border:1px solid #ccc; padding:15px; margin-bottom:15px;">';
              echo '<h4>' . esc_html($plan['plan_name']) . '</h4>';
              if ($plan['meal_image']) {
                echo '<img src="' . esc_url($plan['meal_image']['url']) . '" alt="' . esc_attr($plan['meal_image']['alt']) . '" style="max-width:200px;"><br>';
              }
              echo '<strong>Ingredients:</strong> ' . esc_html($plan['ingredients']) . '<br>';
              echo '<strong>Calories (kcal):</strong> ' . esc_html($plan['calories']) . '<br>';
              echo '<strong>Carbohydrates (g):</strong> ' . esc_html($plan['carbohydrates']) . '<br>';
              echo '<strong>Protein (g):</strong> ' . esc_html($plan['protein']) . '<br>';
              echo '<strong>Fat (g):</strong> ' . esc_html($plan['fat']) . '<br>';
              echo '<strong>Day Number:</strong> ' . esc_html($plan['week_number']) . '<br>';
              echo '</div>';
            }
          }
        }
      } else {
        echo '<p>⚠️ No related meal plans found.</p>';
      }
    } else {
      echo '<p>⚠️ No valid term IDs found.</p>';
    }

    if ($start_timestamp && !empty($delivery_days)) {
      $current_date = $start_timestamp;
      $schedule_data = [];
      $deliveries_made = 0;

      // Get the plan duration from the product title
      $plan_duration = get_plan_duration_from_title($product_name);
      if (!$plan_duration) {
        $plan_duration = 5;
      }

      // Define valid week days in order
      $week_days_order = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

      // Sort user-selected delivery days in order of the week
      usort($delivery_days, function ($a, $b) use ($week_days_order) {
        $indexA = array_search($a, $week_days_order);
        $indexB = array_search($b, $week_days_order);

        // return array_search($a, $week_days_order) - array_search($b, $week_days_order);
        return ($indexA !== false ? $indexA : 7) - ($indexB !== false ? $indexB : 7);
      });

      // Move to the first available delivery day
      while (!in_array(strtolower(date('l', $current_date)), $delivery_days, true)) {
        $current_date = strtotime('+1 day', $current_date);
      }

      // Schedule the deliveries
      while ($deliveries_made < $plan_duration) {
        $day_name = strtolower(date('l', $current_date));

        if (in_array($day_name, $delivery_days, true)) {
          $schedule_data[] = [
            'no' => $deliveries_made + 1,
            'date' => date('Y-m-d', $current_date),
            'day' => ucfirst($day_name),
            'meals' => array_unique(array_map('trim', explode("\n", str_replace("\r", '', implode("\n", $meal_type))))),
            'times' => array_unique(array_map('trim', explode("\n", str_replace("\r", '', implode("\n", $delivery_time))))),
          ];

          $deliveries_made++;
        }

        // Move to the next day
        $current_date = strtotime('+1 day', $current_date);

        // Ensure it only schedules on selected delivery days
        while (!in_array(strtolower(date('l', $current_date)), $delivery_days, true)) {
          $current_date = strtotime('+1 day', $current_date);
        }
      }
    }
    ?>
  <?php endforeach; ?>
</div>

<!-- Schedule Table -->
<?php if (!empty($schedule_data)) : ?>
  <table border="1" cellspacing="0" cellpadding="8">
    <thead>
      <tr>
        <th>No.</th>
        <th>Date</th>
        <th>Day</th>
        <th>Ingredients</th>
        <th>Meal Type</th>
        <th>Delivery Time</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($schedule_data as $data) : ?>
        <?php
        $meals = array_unique($data['meals']);
        $meal_count = count($meals);
        $first_row = true;
        ?>

        <?php foreach ($meals as $index => $meal) : ?>
          <tr>
            <?php if ($first_row) : ?>
              <td rowspan="<?= $meal_count; ?>"><?= $data['no']; ?></td>
              <td rowspan="<?= $meal_count; ?>"><?= date('d M Y', strtotime($data['date'])); ?></td>
              <td rowspan="<?= $meal_count; ?>"><?= $data['day']; ?></td>
              <td>N/A</td>
              <?php $first_row = false; ?>
            <?php endif; ?>

            <td><?= ucfirst($meal); ?></td>
            <td><?= $data['times'][$index] ?? ''; ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php else : ?>
  <p>No schedules found for this order.</p>
<?php endif; ?>

</div>