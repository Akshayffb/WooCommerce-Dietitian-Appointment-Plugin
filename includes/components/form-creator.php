<?php
if (!defined('ABSPATH')) {
  exit;
}

global $wpdb;
$table = $wpdb->prefix . 'wdb_forms';

// Handle form creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_name'])) {
  $form_name = sanitize_text_field($_POST['form_name']);
  $fields = implode(',', array_map('sanitize_text_field', explode(',', $_POST['fields'])));
  $shortcode = 'wdb_booking_form_' . time();

  $wpdb->insert($table, [
    'form_name' => $form_name,
    'fields' => $fields,
    'shortcode' => $shortcode
  ]);
}
echo '<div class="alert alert-success">Form created! Use this shortcode: <strong>[wdb_booking_form]</strong></div>';
?>

<div class="container mt-4">
  <h2>Create Booking Form</h2>
  <form method="post" class="p-4 border rounded bg-light">
    <div class="mb-3">
      <label class="form-label">Form Name:</label>
      <input type="text" name="form_name" class="form-control" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Fields (comma-separated):</label>
      <input type="text" name="fields" class="form-control" required placeholder="Name, Email, Phone">
    </div>

    <button type="submit" class="btn btn-primary">Create Form</button>
  </form>
</div>