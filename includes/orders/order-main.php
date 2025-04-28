<?php
// Main file for handling all order-related functionality

// Hook into WooCommerce order creation to trigger logging
add_action('woocommerce_new_order', 'log_new_order_data', 10, 1);

// Function to log order data when a new order is created
function log_new_order_data($order_id)
{
    // Access the WooCommerce order object
    $order = wc_get_order($order_id);

    if (!$order) {
        return; // Order not found, exit
    }

    // Gather all order data to log
    $order_data = [
        'order_id'            => $order->get_id(),
        'order_key'           => $order->get_order_key(),
        'order_total'         => $order->get_total(),
        'order_status'        => $order->get_status(),
        'payment_method'      => $order->get_payment_method(),
        'payment_method_title'=> $order->get_payment_method_title(),
        'shipping_method'     => get_shipping_methods($order),  // Shipping method
    ];

    // Order Items and details (such as ingredients, start date, etc.)
    $items = $order->get_items();
    $order_items = [];
    foreach ($items as $item_id => $item) {
        $product = $item->get_product();

        // Retrieve custom fields (for ingredients, start date, meal type, etc.)
        $ingredients = get_post_meta($product->get_id(), '_ingredients', true);
        $start_date = get_post_meta($product->get_id(), '_start_date', true);
        $selected_days = get_post_meta($product->get_id(), '_selected_days', true);
        $meal_type = get_post_meta($product->get_id(), '_meal_type', true);
        $lunch_time = get_post_meta($product->get_id(), '_lunch_time', true);

        $order_items[] = [
            'product_name'    => $item->get_name(),
            'quantity'        => $item->get_quantity(),
            'total'           => $item->get_total(),
            'ingredients'     => $ingredients,
            'start_date'      => $start_date,
            'selected_days'   => $selected_days,
            'meal_type'       => $meal_type,
            'lunch_time'      => $lunch_time,
        ];
    }
    $order_data['order_items'] = $order_items;

    // Shipping and Billing Details
    $billing_address = [
        'name'      => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
        'address'   => $order->get_billing_address_1(),
        'city'      => $order->get_billing_city(),
        'postcode'  => $order->get_billing_postcode(),
        'country'   => $order->get_billing_country(),
        'state'     => $order->get_billing_state(),
        'phone'     => $order->get_billing_phone(),
        'email'     => $order->get_billing_email(),
    ];

    $shipping_address = [
        'name'      => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
        'address'   => $order->get_shipping_address_1(),
        'city'      => $order->get_shipping_city(),
        'postcode'  => $order->get_shipping_postcode(),
        'country'   => $order->get_shipping_country(),
        'state'     => $order->get_shipping_state(),
    ];

    $order_data['billing_address'] = $billing_address;
    $order_data['shipping_address'] = $shipping_address;

    // Convert the order data array to a string for logging
    $log_entry = "Order ID: {$order_data['order_id']} | " .
                 "Order Key: {$order_data['order_key']} | " .
                 "Total: {$order_data['order_total']} | " .
                 "Status: {$order_data['order_status']} | " .
                 "Payment Method: {$order_data['payment_method']} | " .
                 "Payment Title: {$order_data['payment_method_title']} | " .
                 "Shipping Method: {$order_data['shipping_method']} | " .
                 "Billing Name: {$billing_address['name']} | " .
                 "Billing Address: {$billing_address['address']}, {$billing_address['city']}, {$billing_address['postcode']} | " .
                 "Shipping Name: {$shipping_address['name']} | " .
                 "Shipping Address: {$shipping_address['address']}, {$shipping_address['city']}, {$shipping_address['postcode']} | ";

    // Log product information
    foreach ($order_data['order_items'] as $item) {
        $log_entry .= "Product: {$item['product_name']} | " .
                      "Quantity: {$item['quantity']} | " .
                      "Total: {$item['total']} | " .
                      "Ingredients: {$item['ingredients']} | " .
                      "Start Date: {$item['start_date']} | " .
                      "Selected Days: {$item['selected_days']} | " .
                      "Meal Type: {$item['meal_type']} | " .
                      "Lunch Time: {$item['lunch_time']} | ";
    }

    // Log the entry to the log file
    $log_entry .= "\n";

    // Define the log file path (current plugin directory)
    $log_file = plugin_dir_path(__FILE__) . 'order-logs.log';

    // Check if the log file exists, if not, create it
    if (!file_exists($log_file)) {
        touch($log_file); // Create empty log file
    }

    // Append the log entry to the file
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Function to get shipping methods for the order
function get_shipping_methods($order)
{
    $shipping_methods = [];
    foreach ($order->get_items('shipping') as $shipping_item) {
        $shipping_methods[] = $shipping_item->get_method_title();
    }
    return implode(", ", $shipping_methods); // Combine all shipping methods into a string
}
