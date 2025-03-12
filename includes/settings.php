<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
  wp_die(__('You do not have permission to access this page.', 'textdomain'));
}

global $wpdb;
$table_name = $wpdb->prefix . 'wdb_settings';
$message = "";

// Save settings when form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
  if (!check_admin_referer('save_settings_action', 'save_settings_nonce')) {
    wp_die(__('Security check failed.', 'textdomain'));
  }

  $fields = ['scheduling_api', 'api_key', 'api_secret', 'callback_url', 'meeting_duration', 'shortcode_slug'];
  $data = [];

  foreach ($fields as $field) {
    $value = isset($_POST[$field]) ? sanitize_text_field($_POST[$field]) : '';
    if ($field === 'callback_url') {
      $value = esc_url_raw($_POST[$field]);
    }
    if ($field === 'meeting_duration') {
      $value = intval($_POST[$field]);
    }
    $data[$field] = $value;
  }

  // Check if data exists
  $exists = $wpdb->get_var("SELECT COUNT(*) FROM $table_name LIMIT 1");

  if ($exists) {
    $wpdb->update($table_name, $data, ['id' => 1]);
  } else {
    $data['id'] = 1; // Ensure there's only one row
    $wpdb->insert($table_name, $data);
  }

  $message = '<div class="updated"><p>Settings saved successfully!</p></div>';
}

// Fetch settings
$settings = $wpdb->get_row("SELECT * FROM $table_name WHERE id = 1", ARRAY_A) ?: [];
?>

<div class="wrap">
  <h1>Dietitian Plugin Settings</h1>
  <?php echo $message; ?>

  <form method="post">
    <?php wp_nonce_field('save_settings_action', 'save_settings_nonce'); ?>
    <table class="form-table">
      <tr>
        <th><label for="scheduling_api">Choose Scheduling API</label></th>
        <td>
          <select name="scheduling_api" id="scheduling_api">
            <option value="none" <?php selected($settings['scheduling_api'] ?? '', 'none'); ?>>None</option>
            <option value="google_meet" <?php selected($settings['scheduling_api'] ?? '', 'google_meet'); ?>>Google Meet</option>
            <option value="zoom" <?php selected($settings['scheduling_api'] ?? '', 'zoom'); ?>>Zoom</option>
            <option value="teams" <?php selected($settings['scheduling_api'] ?? '', 'teams'); ?>>Microsoft Teams</option>
          </select>
        </td>
      </tr>
      <tr>
        <th><label for="api_key">API Key</label></th>
        <td><input type="text" name="api_key" id="api_key" class="regular-text" value="<?php echo esc_attr($settings['api_key'] ?? ''); ?>"></td>
      </tr>
      <tr>
        <th><label for="api_secret">API Secret</label></th>
        <td><input type="text" name="api_secret" id="api_secret" class="regular-text" value="<?php echo esc_attr($settings['api_secret'] ?? ''); ?>"></td>
      </tr>
      <tr>
        <th><label for="callback_url">Callback URL</label></th>
        <td><input type="url" name="callback_url" id="callback_url" class="regular-text" value="<?php echo esc_url($settings['callback_url'] ?? ''); ?>"></td>
      </tr>
      <tr>
        <th><label for="meeting_duration">Default Meeting Duration (Minutes)</label></th>
        <td><input type="number" name="meeting_duration" id="meeting_duration" class="small-text" value="<?php echo esc_attr($settings['meeting_duration'] ?? '30'); ?>"></td>
      </tr>
      <tr>
        <th><label for="shortcode_slug">Page Slug for Shortcode</label></th>
        <td><input type="text" name="shortcode_slug" id="shortcode_slug" class="regular-text" placeholder="/book-appointment" value="<?php echo esc_attr($settings['shortcode_slug'] ?? ''); ?>"></td>
      </tr>
    </table>
    <div>
      <p>To display the booking form, use this shortcode:
        <strong id="shortcode-text">[wdb_booking_form]</strong>
        <button type="button" class="button" onclick="copyShortcode()">Copy</button>
      </p>
    </div>
    <p class="submit">
      <button type="submit" name="save_settings" class="button button-primary">Save Settings</button>
    </p>
  </form>
</div>

<script>
  function copyShortcode() {
    const shortcodeText = document.getElementById('shortcode-text').innerText;
    navigator.clipboard.writeText(shortcodeText).then(() => {
      alert('Shortcode copied!');
    }).catch(err => {
      console.error('Failed to copy: ', err);
    });
  }
</script>