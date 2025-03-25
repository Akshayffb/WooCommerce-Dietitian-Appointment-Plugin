<?php



// Show dietitian note in the admin order detail page

function add_meeting_note_order_meta_field($order)

{

  $meeting_note = get_post_meta($order->get_id(), 'dietitian_note', true);

?>

  <p class="form-field form-field-wide">

    <label for="meeting_note">Dietitian Meeting Note:</label>

    <textarea name="meeting_note" rows="3" cols="50"><?php echo esc_textarea($meeting_note); ?></textarea>

  </p>

<?php

}

add_action('woocommerce_admin_order_data_after_order_details', 'add_meeting_note_order_meta_field');



// Save Dietitian Note when Admin Updates Order

function save_meeting_note_order_meta_field($order_id)

{

  if (isset($_POST['meeting_note'])) {

    update_post_meta($order_id, 'dietitian_note', sanitize_textarea_field($_POST['meeting_note']));
  }
}

add_action('woocommerce_process_shop_order_meta', 'save_meeting_note_order_meta_field');



// Show Dietitian Note in Customer Order Details Page

function show_dietitian_note_in_order_details($order)

{

  $dietitian_note = get_post_meta($order->get_id(), 'dietitian_note', true);

  if (!empty($dietitian_note)) {

    echo '<h5>Dietitian Note:</h5>';

    echo '<textarea rows="3" cols="40" disabled>' . esc_textarea($dietitian_note) . '</textarea>';
  }
}

add_action('woocommerce_order_details_after_order_table', 'show_dietitian_note_in_order_details');


// Add Dietitian Note to WooCommerce Emails

function add_dietitian_note_to_emails($order, $sent_to_admin, $plain_text, $email)

{

  $dietitian_note = get_post_meta($order->get_id(), 'dietitian_note', true);

  if (!empty($dietitian_note)) {

    echo '<p>Dietitian Note:<br>' . esc_html($dietitian_note) . '</p>';
  }
}

add_action('woocommerce_email_order_meta', 'add_dietitian_note_to_emails', 20, 4);





// Delete Dietitian Note When Order is Deleted

function delete_dietitian_note_on_order_delete($order_id)

{

  delete_post_meta($order_id, 'dietitian_note');
}

add_action('before_delete_post', 'delete_dietitian_note_on_order_delete');





// Include Dietitian Note in WooCommerce Order API

function include_dietitian_note_in_api($response, $order, $request)

{

  $response->data['dietitian_note'] = get_post_meta($order->get_id(), 'dietitian_note', true);

  return $response;
}

add_filter('woocommerce_rest_prepare_shop_order', 'include_dietitian_note_in_api', 10, 3);



// Include Dietitian Note in WooCommerce Order Meta

function add_dietitian_note_to_order_meta($order_id, $posted_data, $order = null)
{
  if (!$order) {
    $order = wc_get_order($order_id);
  }

  if (!empty($_POST['dietitian_note'])) {
    update_post_meta($order_id, 'dietitian_note', sanitize_textarea_field($_POST['dietitian_note']));
  }
}

add_action('woocommerce_checkout_update_order_meta', 'add_dietitian_note_to_order_meta', 10, 3);
