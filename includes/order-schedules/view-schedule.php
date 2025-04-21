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

function get_plan_duration($order)
{
  foreach ($order->get_items() as $item) {
    $product = $item->get_product();

    if ($product) {
      $attributes = $product->get_attributes();

      foreach ($attributes as $attribute) {
        $attr_name = wc_attribute_label($attribute->get_name());

        if (strtolower($attr_name) === 'number of salads') {
          $salads = $attribute->get_options(); // array
          return implode(', ', $salads);
        }
      }

      // Optional fallback: check item meta
      $meta_salads = $item->get_meta('Number of Salads', true);
      if ($meta_salads) {
        return $meta_salads;
      }
    }
  }

  return null;
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
    $ingredients = '';
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

      if ($meta_key === 'ingredients' && !empty($meta_value)) {
        if (is_string($meta_value)) {
          $lines = explode("\n", $meta_value);
          $clean_values = [];

          foreach ($lines as $line) {
            $parts = explode('|', $line);
            if (isset($parts[1])) {
              $value = trim($parts[1]);
              if (!empty($value)) {
                $clean_values[] = $value;
              }
            }
          }

          $ingredients = esc_html(implode(', ', $clean_values));
        }
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

      if (in_array($meta_key, ['lunch time', 'dinner time', 'breakfast time']) && !empty($meta_value)) {
        $parts = explode('|', $meta_value);
        $time_label = ucfirst($meta_key); // e.g., "Lunch time"
        $time_only = isset($parts[0]) ? trim($parts[0]) : '';
        if (!empty($time_only)) {
          // $delivery_time[] = "$time_only ($time_label)";
          $delivery_time[] = $time_only;
        }
      }
    }

    $meal_type = !empty($meal_type) ? array_map('trim', explode('|', implode('|', $meal_type))) : [];
    $delivery_time = !empty($delivery_time) ? array_map('trim', explode('|', implode('|', $delivery_time))) : [];

    $start_timestamp = !empty($start_date) ? strtotime($start_date) : false;

    echo '<div class="plan-details">
    <p class="mb-1"><strong>Plan: </strong>' . esc_html($product_name) . '</p>
    <p class="mb-0"><strong>Start Date:</strong> ' . ($start_date ? date('F j, Y', $start_timestamp) : 'Not specified') . '</p>
    <p class="mb-0"><strong>Ingredients not included:</strong> ' . esc_html($ingredients) . '</p>
    </div>';

    if ($start_timestamp && !empty($delivery_days)) {
      $current_date = $start_timestamp;
      $schedule_data = [];
      $deliveries_made = 0;

      $plan_duration = get_plan_duration($order);

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

      $product_id = $item->get_product_id();

      // Schedule the deliveries
      while ($deliveries_made < $plan_duration) {
        $day_name = strtolower(date('l', $current_date));

        if (in_array($day_name, $delivery_days, true)) {
          $date_formatted = date('Y-m-d', $current_date);

          $meal_plan = calculate($date_formatted, $product_id);
          $schedule_data[] = [
            'no' => $deliveries_made + 1,
            'date' => date('Y-m-d', $current_date),
            'day' => ucfirst($day_name),
            'meals' => array_unique(array_map('trim', explode("\n", str_replace("\r", '', implode("\n", $meal_type))))),
            'times' => array_unique(array_map('trim', explode("\n", str_replace("\r", '', implode("\n", $delivery_time))))),
            'meal_plan' => $meal_plan, // ✅ Include calculated plan here
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
  <table border="1" cellspacing="0" cellpadding="8" style="table-layout: fixed; width: 100%;">
    <thead>
      <tr>
        <th>No.</th>
        <th>Date</th>
        <th>Day</th>
        <th style="width: 250px;">
          Meal Info
          <?php
          $category_slug = get_field('acf_meal_plan_category', $product_id);
          $category_slug_for_url = str_replace('_', '-', strtolower($category_slug));
          if (!empty($category_slug_for_url)) : ?>
            <a href="/<?= esc_attr($category_slug_for_url); ?>" target="_blank" style="font-weight: normal; font-size: 12px; margin-left: 5px;">
              (Check Details)
            </a>
          <?php endif; ?>
        </th>

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
        $meal_plan_name = $data['meal_plan']['plan_name'];
        $meal_ingredients = $data['meal_plan']['ingredients'] ?? '';
        ?>

        <?php foreach ($meals as $index => $meal) : ?>
          <tr>
            <?php if ($first_row) : ?>
              <td rowspan="<?= $meal_count; ?>"><?= $data['no']; ?></td>
              <td rowspan="<?= $meal_count; ?>"><?= date('d M Y', strtotime($data['date'])); ?></td>
              <td rowspan="<?= $meal_count; ?>"><?= $data['day']; ?></td>
              <td rowspan="<?= $meal_count; ?>">
                <?php if (!empty($data['meal_plan']['plan_name'])): ?>
                  <strong><?= htmlspecialchars($data['meal_plan']['plan_name']); ?></strong><br>
                  <span>Ingredients: <?= nl2br(htmlspecialchars($data['meal_plan']['ingredients'] ?? '')); ?></span>
                <?php else: ?>
                  <span style="font-size: 12px; color: #666;">N/A</span>
                <?php endif; ?>
              </td>
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

<?php
function calculate($date_str, $product_id)
{
  $date = new DateTime($date_str);
  $day_name = $date->format('l');         // e.g., 'Monday'
  $weekday_number = (int)$date->format('N'); // 1 (Mon) to 7 (Sun)
  $day_of_month = (int)$date->format('j');

  // ➕ Calculate occurrence of the day in the month
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
        if ($plan_day_num === $weekday_number && $plan_week === $occurrence) {
          $matched_plan = [
            'plan_id'       => $plan_id,
            'day' => $plan_day_num,
            'plan_name'     => get_field('plan_name', $plan_id),
            // 'meal_image'    => get_field('meal_image', $plan_id),
            'ingredients'   => get_field('ingredients', $plan_id),
            // 'calories'      => get_field('calories_kcal', $plan_id),
            // 'carbohydrates' => get_field('carbohydrates_g', $plan_id),
            // 'protein'       => get_field('protein_g', $plan_id),
            // 'fat'           => get_field('fat_g', $plan_id),
            'week_number'   => $plan_week,
            'meal_day'      => $weekday_number,
            'date'          => $date->format('Y-m-d'),
            'occurrence'    => $occurrence,
          ];
          break 2; // ✅ Stop loop once found
        }
      }
    }
    wp_reset_postdata();
  }

  if ($matched_plan) {
    return $matched_plan;
  } else {
    return null;
  }
}

?>
</div>