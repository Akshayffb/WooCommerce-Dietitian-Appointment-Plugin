<?php
if (!defined('ABSPATH')) {
  exit;
}

// Process form submissions
function wdb_handle_form_submission()
{
  global $wpdb;

  if (!isset($_POST['form_id'])) {
    wp_die('Invalid request');
  }

  $form_id = intval($_POST['form_id']);
  $fields = array_map('sanitize_text_field', $_POST);
  unset($fields['action'], $fields['form_id']); // Remove unnecessary fields

  $table = $wpdb->prefix . 'wdb_appointments';
  $wpdb->insert($table, [
    'customer_id' => get_current_user_id(),
    'dietitian_id' => 1, // This should be dynamic
    'order_id' => 0,
    'appointment_date' => current_time('mysql'),
    'status' => 'pending'
  ]);

  wp_redirect(home_url('/thank-you'));
  exit;
}

add_action('admin_post_wdb_handle_form', 'wdb_handle_form_submission');
add_action('admin_post_nopriv_wdb_handle_form', 'wdb_handle_form_submission');
