<?php
function wdb_schedule_endpoint_content()
{
  global $wpdb;

  $meal_plan_table = $wpdb->prefix . 'wdb_meal_plans';
  $schedule_table = $wpdb->prefix . 'wdb_meal_plan_schedules';

  $order_id = get_query_var('view-schedule');

  if (!is_numeric($order_id)) {
    echo "<p>Invalid schedule ID.</p>";
    return;
  }

  $plan = $wpdb->get_row(
    $wpdb->prepare("SELECT * FROM $meal_plan_table WHERE order_id = %d", $order_id),
    ARRAY_A
  );

  if (!$plan) {
    echo "<p>Meal plan not found.</p>";
    return;
  }

  if ($plan['user_id'] != get_current_user_id()) {
    echo "<p>You are not allowed to view this meal plan.</p>";
    return;
  }

  $meals = $wpdb->get_results(
    $wpdb->prepare("SELECT * FROM $schedule_table WHERE meal_plan_id = %d ORDER BY serve_date ASC", $plan['id']),
    ARRAY_A
  );

  // Output HTML
?>
  <h2 class="fs-4">Meal Plan: <?php echo esc_html($plan['plan_name']); ?></h2>
  <p><strong>Start Date:</strong> <?php echo esc_html($plan['start_date']); ?></p>
  <p><strong>Duration:</strong> <?php echo esc_html($plan['plan_duration']); ?> days</p>
  <p><strong>Category:</strong> <?php echo esc_html($plan['category']); ?></p>
  <p><strong>Notes:</strong> <?php echo esc_html($plan['notes']); ?></p>

  <h3 class="fs-4">Scheduled Meals</h3>
  <?php if (!empty($meals)): ?>
    <table class="woocommerce-table shop_table" border="1" cellpadding="8">
      <thead>
        <tr>
          <th>Sl. No</th>
          <th>Date</th>
          <th>Weekday</th>
          <th>Meal Info</th>
          <th>Meal Type</th>
          <th>Delivery Window</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $grouped = [];
        foreach ($meals as $meal) {
          $key = $meal['serve_date'] . '_' . $meal['weekday'];
          $grouped[$key][] = $meal;
        }

        $sl = 1;
        foreach ($grouped as $group_meals):
          $first = true;
          $meal_count = count($group_meals);
          foreach ($group_meals as $meal): ?>
            <tr>
              <?php if ($first): ?>
                <td rowspan="<?php echo $meal_count; ?>"><?php echo $sl++; ?></td>
                <td rowspan="<?php echo $meal_count; ?>"><?php echo date('d M Y', strtotime($meal['serve_date'])); ?></td>
                <td rowspan="<?php echo $meal_count; ?>"><?php echo esc_html($meal['weekday']); ?></td>
                <td rowspan="<?php echo $meal_count; ?>">
                  <?php echo nl2br(esc_html($meal['meal_info'])); ?>
                </td>
                <?php $first = false; ?>
              <?php endif; ?>
              <td><?php echo esc_html($meal['meal_type']); ?></td>
              <td><?php echo esc_html($meal['delivery_window']); ?></td>
            </tr>
        <?php endforeach;
        endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p>No meal data available.</p>
<?php endif;
}
