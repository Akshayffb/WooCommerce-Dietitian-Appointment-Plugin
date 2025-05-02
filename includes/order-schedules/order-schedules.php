<?php

// Add a new column to the "My Orders" table
add_filter('woocommerce_my_account_my_orders_columns', 'custom_add_schedules_column');
function custom_add_schedules_column($columns)
{
    $columns['view_schedules'] = __('Schedules', 'your-text-domain');
    return $columns;
}

// Display content in the "View Schedules" column
add_action('woocommerce_my_account_my_orders_column_view_schedules', 'custom_view_schedules_column_content', 10, 1);
function custom_view_schedules_column_content($order)
{
    $order_id = $order->get_id();
    $view_schedules_url = wc_get_endpoint_url('view-schedule', $order_id, wc_get_page_permalink('myaccount'));

    echo '<a href="' . esc_url($view_schedules_url) . '" class="button">View</a>';
}

// Add custom endpoint for viewing schedules
function custom_add_schedule_endpoint()
{
    add_rewrite_endpoint('view-schedule', EP_PAGES);
}
add_action('init', 'custom_add_schedule_endpoint');

// Flush rewrite rules on plugin activation
function custom_flush_rewrite_rules()
{
    custom_add_schedule_endpoint();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'custom_flush_rewrite_rules');

// Add schedule endpoint to WooCommerce My Account menu (optional)
function custom_add_schedule_query_vars($vars)
{
    $vars[] = 'view-schedule';
    return $vars;
}
add_filter('query_vars', 'custom_add_schedule_query_vars');

// Handle the schedule page content
function custom_view_schedule_content()
{
    global $wp_query;

    if (!isset($wp_query->query_vars['view-schedule'])) {
        return;
    }

    $order_id = absint(get_query_var('view-schedule'));

    if (!$order_id) {
        echo '<p>Invalid Order ID.</p>';
        return;
    }

    // Load the template manually
    $template = plugin_dir_path(__FILE__) . './orders/view-schedule-endpoint.php';

    if (file_exists($template)) {
        include $template;
    } else {
        echo '<p>Schedule template not found!</p>';
    }
}
add_action('woocommerce_account_view-schedule_endpoint', 'custom_view_schedule_content');

// Highlight Orders menu when visiting View Schedule
add_filter('woocommerce_account_menu_item_classes', 'custom_highlight_orders_for_schedule', 10, 2);
function custom_highlight_orders_for_schedule($classes, $endpoint)
{
    // Get the current URL
    $current_url = $_SERVER['REQUEST_URI'];

    // Check if the URL contains '/view-schedule/'
    if (strpos($current_url, 'view-schedule') !== false && $endpoint === 'orders') {
        $classes[] = 'is-active'; // WooCommerce default active class
    }

    return $classes;
}
