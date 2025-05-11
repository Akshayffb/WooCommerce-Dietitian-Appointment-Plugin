<?php
// 1. Add "Schedules" column to Orders table
add_filter('woocommerce_my_account_my_orders_columns', function ($columns) {
  $columns['view_schedules'] = __('Schedules', 'your-text-domain');
  return $columns;
});

// 2. Display "View" button linking to the custom endpoint
add_action('woocommerce_my_account_my_orders_column_view_schedules', function ($order) {
  $order_id = $order->get_id();
  $url = wc_get_endpoint_url('view-schedule', $order_id, wc_get_page_permalink('myaccount'));
  echo '<a href="' . esc_url($url) . '" class="button">View</a>';
}, 10, 1);

// 3. Register custom endpoint
add_action('init', function () {
  add_rewrite_endpoint('view-schedule', EP_PAGES);
});

// 4. Flush rewrite rules on plugin activation
register_activation_hook(__FILE__, function () {
  add_rewrite_endpoint('view-schedule', EP_PAGES);
  flush_rewrite_rules();
});

// 5. Add custom query var
add_filter('query_vars', function ($vars) {
  $vars[] = 'view-schedule';
  return $vars;
});

// 6. Highlight Orders menu when viewing schedule
add_filter('woocommerce_account_menu_item_classes', function ($classes, $endpoint) {
  if (strpos($_SERVER['REQUEST_URI'], 'view-schedule') !== false && $endpoint === 'orders') {
    $classes[] = 'is-active';
  }
  return $classes;
}, 10, 2);

// 7. Load schedule content handler
add_action('woocommerce_account_view-schedule_endpoint', 'wdb_schedule_endpoint_content');
