<?php
// Main file for handling all order-related functionality

add_action('woocommerce_new_order', 'store_meal_plan_details_from_order', 10, 1);

function store_meal_plan_details_from_order($order_id)
{
    if (!function_exists('wc_get_order')) {
        return;
    }

    global $wpdb;
    $meal_plan_table = $wpdb->prefix . 'wdb_meal_plans';

    $order = wc_get_order($order_id);
    if (!$order) {
        error_log('Order not found for ID: ' . $order_id);
        return;
    }

    $user_id = $order->get_user_id();
    $grand_total = $order->get_total();

    foreach ($order->get_items() as $item_id => $item) {
        $product_id = $item->get_product_id();
        $product = wc_get_product($product_id);

        if (!$product) {
            error_log('Product not found for ID: ' . $product_id);
            continue;
        }

        // Get product-specific meta from order item
        $product_name = $item->get_name();
        $start_date = wc_get_order_item_meta($item_id, 'start date', true);
        $ingredients = wc_get_order_item_meta($item_id, 'ingredients', true);
        $select_days = wc_get_order_item_meta($item_id, 'select days', true);
        $meal_type = wc_get_order_item_meta($item_id, 'meal type', true);
        $lunch_time = wc_get_order_item_meta($item_id, 'lunch time', true);
        $dinner_time = wc_get_order_item_meta($item_id, 'dinner time', true);
        $breakfast_time = wc_get_order_item_meta($item_id, 'breakfast time', true);
        $food_notes = wc_get_order_item_meta($item_id, 'food notes', true);

        // Get plan_duration (Number of Salads attribute)
        $plan_duration = $product->get_attribute('number of salads');
        if (empty($plan_duration)) {
            $plan_duration = 0;
        }

        // Get Product Category
        $categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'names'));
        $category = !empty($categories) ? implode(', ', $categories) : '';

        // Combine the times
        $times = [
            'breakfast' => $breakfast_time,
            'lunch' => $lunch_time,
            'dinner' => $dinner_time,
        ];
        $time_serialized = maybe_serialize($times);

        // Prepare data for insertion
        $data = [
            'order_id' => $order_id,
            'user_id' => $user_id,
            'plan_name' => $product_name,
            'plan_duration' => intval($plan_duration),
            'category' => $category,
            'start_date' => $start_date,
            'selected_days' => maybe_serialize($select_days),
            'meal_type' => maybe_serialize($meal_type),
            'time' => $time_serialized,
            'ingredients' => maybe_serialize($ingredients),
            'grand_total' => $grand_total,
            'notes' => $food_notes,
            'created_at' => current_time('mysql')
        ];

        $format = [
            '%d',
            '%d',
            '%s',
            '%d',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%f',
            '%s',
            '%s'
        ];

        // Insert into meal plan table
        $inserted = $wpdb->insert($meal_plan_table, $data, $format);

        if (!$inserted) {
            error_log('Meal Plan Inserted Successfully: ' . print_r($data, true));
        }
    }
}
