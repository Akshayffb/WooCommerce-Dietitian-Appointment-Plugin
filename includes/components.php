<?php
// Prevent direct access
if (!defined('ABSPATH')) {
  exit;
}

function wdb_components_page()
{
?>
  <div class="wrap">
    <h1>Create Booking Form</h1>
    <form method="post" action="">
      <table class="form-table">
        <tr>
          <th><label for="form_name">Form Name</label></th>
          <td><input type="text" name="form_name" id="form_name" class="regular-text" required></td>
        </tr>
        <tr>
          <th><label for="form_fields">Fields (comma-separated)</label></th>
          <td><input type="text" name="form_fields" id="form_fields" class="regular-text" placeholder="Name, Email, Phone, Date" required></td>
        </tr>
      </table>
      <p class="submit">
        <input type="submit" name="submit_form" class="button button-primary" value="Generate Shortcode">
      </p>
    </form>

    <?php
    if (isset($_POST['submit_form'])) {
      global $wpdb;
      $table = $wpdb->prefix . 'wdb_forms';

      $form_name = sanitize_text_field($_POST['form_name']);
      $form_fields = sanitize_text_field($_POST['form_fields']);
      $shortcode = 'wdb_booking_form_' . time();

      $wpdb->insert($table, [
        'form_name' => $form_name,
        'fields' => $form_fields,
        'shortcode' => $shortcode,
      ]);

      echo "<div class='updated'><p>Shortcode Generated: <strong>[$shortcode]</strong></p></div>";
    }
    ?>
  </div>
<?php
}
