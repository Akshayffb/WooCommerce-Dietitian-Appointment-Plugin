<?php
// Hook to WooCommerce order completion
add_action('woocommerce_thankyou', 'wdb_store_order_meta_to_meal_plan_table', 10, 1);

function wdb_store_order_meta_to_meal_plan_table($order_id)
{
    if (!$order_id) return;

    global $wpdb;
    $table = $wpdb->prefix . 'wdb_meal_plans';

    $order = wc_get_order($order_id);
    $user_id = $order->get_user_id();
    $grand_total = (float) $order->get_total();

    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        $product_name = $item->get_name();

        // Default values
        $start_date = '';
        $ingredients = '';
        $selected_days = [];
        $meal_type = [];
        $delivery_time = [];

        // Loop through metadata and extract values
        foreach ($item->get_meta_data() as $meta) {
            $meta_key = strtolower($meta->key);
            $meta_value = $meta->value;

            if ($meta_key === 'start date' && !empty($meta_value)) {
                $start_date = esc_html($meta_value);
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
        $wpdb->insert($table, [
            'order_id'      => $order_id,
            'user_id'       => $user_id,
            'plan_name'     => $product_name,
            'plan_duration' => get_plan_duration($order),
            'category'      => $category_name,
            'start_date'    => date('Y-m-d', strtotime($start_date)),
            'selected_days' => maybe_serialize($selected_days),
            'meal_type'     => maybe_serialize($meal_type),
            'time'          => implode("\n", $delivery_time),
            'ingredients'   => maybe_serialize($ingredients),
            'grand_total'   => $grand_total,
            'notes'         => $item->get_meta('food_notes'),
        ]);
    }
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
