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