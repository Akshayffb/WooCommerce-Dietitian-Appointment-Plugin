<?php
function wdb_schedule_endpoint_content()
{
  global $wpdb;

  $meal_plan_table = $wpdb->prefix . 'wdb_meal_plans';
  $meal_plan_schedule_table     = $wpdb->prefix . 'wdb_meal_plan_schedules';

  // Get the ID from URL
  $meal_plan_id = get_query_var('view-schedule');

  if (!is_numeric($meal_plan_id)) {
    echo "<p>Invalid schedule ID.</p>";
    return;
  }

  // Get plan
  $plan = $wpdb->get_row(
    $wpdb->prepare("SELECT * FROM $meal_plan_table WHERE id = %d", $meal_plan_id),
    ARRAY_A
  );

  if (!$plan) {
    echo "<p>Meal plan not found.</p>";
    return;
  }

  $current_user_id = get_current_user_id();
  if ($plan['user_id'] != $current_user_id) {
    echo "<p>You are not allowed to view this meal plan.</p>";
    return;
  }

  // Get meals
  $meals = $wpdb->get_results(
    $wpdb->prepare("SELECT * FROM $meal_plan_schedule_table WHERE meal_plan_id = %d ORDER BY serve_date ASC", $meal_plan_id),
    ARRAY_A
  );

  // Output: similar to order details style
?>
  <h2>Meal Plan: <?php echo esc_html($plan['plan_name']); ?></h2>
  <p><strong>Start Date:</strong> <?php echo esc_html($plan['start_date']); ?></p>
  <p><strong>Duration:</strong> <?php echo esc_html($plan['plan_duration']); ?> days</p>
  <p><strong>Category:</strong> <?php echo esc_html($plan['category']); ?></p>
  <p><strong>Meal Type:</strong> <?php echo esc_html($plan['meal_type']); ?></p>
  <p><strong>Time:</strong> <?php echo esc_html($plan['time']); ?></p>
  <p><strong>Notes:</strong> <?php echo esc_html($plan['notes']); ?></p>

  <h3>Scheduled Meals</h3>
  <?php if (!empty($meals)): ?>
    <table class="woocommerce-table shop_table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Weekday</th>
          <th>Meal Type</th>
          <th>Meal Info</th>
          <th>Delivery Window</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($meals as $meal): ?>
          <tr>
            <td><?php echo esc_html($meal['serve_date']); ?></td>
            <td><?php echo esc_html($meal['weekday']); ?></td>
            <td><?php echo esc_html($meal['meal_type']); ?></td>
            <td><?php echo esc_html($meal['meal_info']); ?></td>
            <td><?php echo esc_html($meal['delivery_window']); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p>No meals found for this plan.</p>
<?php endif;
}
add_action('woocommerce_account_view-schedule_endpoint', 'wdb_schedule_endpoint_content');
