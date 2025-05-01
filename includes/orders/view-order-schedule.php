<?php
// 1. Add "Schedules" column to Orders table
add_filter('woocommerce_my_account_my_orders_columns', 'custom_add_schedules_column');
function custom_add_schedules_column($columns)
{
  $columns['view_schedules'] = __('Schedules', 'your-text-domain');
  return $columns;
}

// 2. Display "View" button linking to the custom endpoint
add_action('woocommerce_my_account_my_orders_column_view_schedules', 'custom_view_schedules_column_content', 10, 1);
function custom_view_schedules_column_content($order)
{
  $order_id = $order->get_id();
  $view_schedules_url = wc_get_endpoint_url('view-schedule', $order_id, wc_get_page_permalink('myaccount'));

  echo '<a href="' . esc_url($view_schedules_url) . '" class="button">View</a>';
}

// 3. Register custom endpoint
add_action('init', 'custom_add_schedule_endpoint');
function custom_add_schedule_endpoint()
{
  add_rewrite_endpoint('view-schedule', EP_PAGES);
}

// 4. Flush rewrite rules on plugin activation
register_activation_hook(__FILE__, 'custom_flush_rewrite_rules');
function custom_flush_rewrite_rules()
{
  custom_add_schedule_endpoint();
  flush_rewrite_rules();
}

// 5. Add custom query var
add_filter('query_vars', 'custom_add_schedule_query_vars');
function custom_add_schedule_query_vars($vars)
{
  $vars[] = 'view-schedule';
  return $vars;
}

// 6. Highlight "Orders" menu when viewing schedule
add_filter('woocommerce_account_menu_item_classes', 'custom_highlight_orders_for_schedule', 10, 2);
function custom_highlight_orders_for_schedule($classes, $endpoint)
{
  if (strpos($_SERVER['REQUEST_URI'], 'view-schedule') !== false && $endpoint === 'orders') {
    $classes[] = 'is-active';
  }
  return $classes;
}

// 7. Display schedule content at /my-account/view-schedule/{id}
add_action('woocommerce_account_view-schedule_endpoint', 'wdb_schedule_endpoint_content');
function wdb_schedule_endpoint_content()
{
  global $wpdb;

  $meal_plan_table = $wpdb->prefix . 'wdb_meal_plans';
  $meal_plan_schedule_table = $wpdb->prefix . 'wdb_meal_plan_schedules';

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

  $current_user_id = get_current_user_id();
  if ($plan['user_id'] != $current_user_id) {
    echo "<p>You are not allowed to view this meal plan.</p>";
    return;
  }

  $meals = $wpdb->get_results(
    $wpdb->prepare("SELECT * FROM $meal_plan_schedule_table WHERE meal_plan_id = %d ORDER BY serve_date ASC", $plan['id']),
    ARRAY_A
  );

  // Output
?>
  <h2 class="fs-4">Meal Plan: <?php echo esc_html($plan['plan_name']); ?></h2>
  <p><strong>Start Date:</strong> <?php echo esc_html($plan['start_date']); ?></p>
  <p><strong>Duration:</strong> <?php echo esc_html($plan['plan_duration']); ?> days</p>
  <p><strong>Category:</strong> <?php echo esc_html($plan['category']); ?></p>
  <p><strong>Notes:</strong> <?php echo esc_html($plan['notes']); ?></p>

  <h3 class="fs-4">Scheduled Meals</h3>
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
