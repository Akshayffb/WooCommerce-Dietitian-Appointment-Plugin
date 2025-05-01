<?php

// Include create plan file
include_once(__DIR__ . '/create-plan.php');

// Hook to WooCommerce order completion
add_action('woocommerce_thankyou', 'wdb_store_order_meta_to_meal_plan_table', 10, 1);

function wdb_store_order_meta_to_meal_plan_table($order_id)
{
    if (!$order_id) return;

    global $wpdb;
    $table = $wpdb->prefix . 'wdb_meal_plans';

    $log_file = __DIR__ . '/meal_plan_debug.log';

    if (!file_exists($log_file)) {
        file_put_contents($log_file, "Log file created at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    }

    $order = wc_get_order($order_id);
    $user_id = $order->get_user_id();
    $grand_total = (float) $order->get_total();

    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        $product_name = $item->get_name();
        $product_id = $product->get_id();

        file_put_contents($log_file, "Product details - ID: $product_id, Name: $product_name\n", FILE_APPEND);

        // Default values
        $start_date = '';
        $ingredients = '';
        $food_notes = '';
        $selected_days = [];
        $meal_type = [];
        $delivery_time = [];

        // Loop through metadata and extract values
        foreach ($item->get_meta_data() as $meta) {
            $meta_key = strtolower($meta->key);
            $meta_value = $meta->value;

            file_put_contents($log_file, "Meta: {$meta_key} => " . print_r($meta_value, true) . "\n", FILE_APPEND);

            if ($meta_key === 'start date' && !empty($meta_value)) {
                $start_date = esc_html($meta_value);
            }

            if ($meta_key === 'food notes' && !empty($meta_value)) {
                $food_notes = esc_html($meta_value);
            }

            if ($meta_key === 'ingredients' && !empty($meta_value) && is_string($meta_value)) {
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

            if ($meta_key === 'select days' && !empty($meta_value)) {
                $days_array = array_filter(array_map('trim', preg_split('/[\s,|]+/', strtolower($meta_value))));
                $selected_days = array_unique($days_array);
            }

            if ($meta_key === 'meal type' && !empty($meta_value)) {
                $meal_type = array_map('trim', explode('|', $meta_value));
            }

            if (in_array($meta_key, ['lunch time', 'dinner time', 'breakfast time']) && !empty($meta_value)) {
                $parts = explode('|', $meta_value);
                $time_only = isset($parts[0]) ? trim($parts[0]) : '';
                if (!empty($time_only)) {
                    $delivery_time[] = $time_only;
                }
            }
        }

        // Get first category name
        $categories = wp_get_post_terms($product->get_id(), 'product_cat');
        $category_name = !empty($categories) ? $categories[0]->name : '';

        // Insert into DB
        $inserted = $wpdb->insert($table, [
            'order_id'      => $order_id,
            'product_id' => $product_id,
            'user_id'       => $user_id,
            'plan_name'     => $product_name,
            'plan_duration' => get_plan_durations($order),
            'category'      => $category_name,
            'start_date'    => date('Y-m-d', strtotime($start_date)),
            'selected_days' => maybe_serialize($selected_days),
            'meal_type'     => maybe_serialize($meal_type),
            'time'          => implode(',', $delivery_time),
            'ingredients'   => maybe_serialize($ingredients),
            'grand_total'   => $grand_total,
            'notes'         => $food_notes,
        ]);

        if ($inserted) {
            if (function_exists('wdb_generate_plan_schedule')) {
                wdb_generate_plan_schedule($order_id);
            } else {
                file_put_contents($log_file, "Function wdb_generate_plan_schedule does not exist.\n", FILE_APPEND);
            }
        }
    }
}

function get_plan_durations($order)
{
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();

        if ($product) {
            $attributes = $product->get_attributes();
            foreach ($attributes as $attribute) {
                $attr_name = wc_attribute_label($attribute->get_name());
                if (strtolower($attr_name) === 'number of salads') {
                    $salads = $attribute->get_options();
                    return implode(', ', $salads);
                }
            }

            // Optional fallback
            $meta_salads = $item->get_meta('Number of Salads', true);
            if ($meta_salads) {
                return $meta_salads;
            }
        }
    }

    return null;
}
