<?php
if (!defined('ABSPATH')) {
  exit;
}

if (!current_user_can('manage_options')) {
  wp_die(__('You do not have permission to view this page.'));
}

global $wpdb;
$table = $wpdb->prefix . 'wdb_apis';

// Handle form submission (your existing form logic)
if (
  $_SERVER['REQUEST_METHOD'] === 'POST'
  && isset($_POST['wdb_add_api_nonce'])
  && wp_verify_nonce($_POST['wdb_add_api_nonce'], 'wdb_add_api')
) {

  $secret_salt = sanitize_text_field($_POST['secret_salt']);
  $plain_api_key = bin2hex(random_bytes(32));
  $hashed_api_key = hash_hmac('sha256', $plain_api_key, $secret_salt);

  $inserted = $wpdb->insert($table, [
    'api_name'   => sanitize_text_field($_POST['api_name']),
    'api_slug'   => sanitize_title($_POST['api_slug']),
    'endpoint'   => esc_url_raw($_POST['endpoint']),
    'method'     => sanitize_text_field($_POST['method']),
    'headers'    => sanitize_textarea_field($_POST['headers']),
    'api_key'    => $hashed_api_key,
    'secret_salt' => $secret_salt,
    'is_active'  => isset($_POST['is_active']) ? 1 : 0,
    'created_at' => current_time('mysql'),
    'updated_at' => current_time('mysql'),
  ]);

  if ($inserted) {
    echo '<div class="notice notice-success is-dismissible">';
    echo '<p>API added successfully.</p>';
    echo '<p><strong>Your API key (copy it now, it will not be shown again):</strong></p>';
    echo '<pre style="background:#eee;padding:10px;border:1px solid #ccc;">' . esc_html($plain_api_key) . '</pre>';
    echo '</div>';
  } else {
    echo '<div class="notice notice-error is-dismissible"><p>Failed to add API. Please try again.</p></div>';
  }
}

// Register REST API endpoint for external apps (add this after your form code)
add_action('rest_api_init', function () use ($wpdb, $table) {
  register_rest_route('wdb/v1', '/secure-endpoint', [
    'methods' => 'POST',
    'callback' => function (WP_REST_Request $request) use ($wpdb, $table) {
      // Get Authorization header
      $headers = $request->get_headers();
      if (empty($headers['authorization'])) {
        return new WP_REST_Response(['error' => 'Missing API key'], 401);
      }
      $auth_header = $headers['authorization'][0];
      if (stripos($auth_header, 'Bearer ') !== 0) {
        return new WP_REST_Response(['error' => 'Invalid Authorization header'], 401);
      }
      $provided_api_key = substr($auth_header, 7);

      // Fetch active APIs
      $apis = $wpdb->get_results("SELECT * FROM $table WHERE is_active = 1");

      $valid = false;
      foreach ($apis as $api) {
        $hashed_provided_key = hash_hmac('sha256', $provided_api_key, $api->secret_salt);
        if (hash_equals($hashed_provided_key, $api->api_key)) {
          $valid = true;
          break;
        }
      }

      if (!$valid) {
        return new WP_REST_Response(['error' => 'Invalid API key'], 403);
      }

      // Process the data sent by the external app
      $params = $request->get_json_params();

      // Here, you can do whatever processing you want with $params
      // For demonstration, just echo back received data

      return new WP_REST_Response([
        'success' => true,
        'message' => 'Authorized successfully',
        'data_received' => $params,
      ]);
    },
    'permission_callback' => '__return_true',
  ]);
});
?>

<div class="wrap">
  <h1>Add New API</h1>

  <form method="POST">
    <?php wp_nonce_field('wdb_add_api', 'wdb_add_api_nonce'); ?>

    <table class="form-table">
      <!-- Your form fields here (unchanged) -->
      <tr>
        <th><label for="api_name">API Name</label></th>
        <td><input id="api_name" name="api_name" type="text" class="regular-text" required></td>
      </tr>
      <tr>
        <th><label for="api_slug">API Slug</label></th>
        <td>
          <select id="api_slug" name="api_slug" required>
            <option value="">-- Select API Slug --</option>
            <option value="cancel-schedule">cancel-schedule</option>
            <option value="update-schedule">update-schedule</option>
          </select>
        </td>
      </tr>
      <tr>
        <th><label for="endpoint">Endpoint URL</label></th>
        <td><input id="endpoint" name="endpoint" type="url" class="regular-text" required></td>
      </tr>
      <tr>
        <th><label for="method">HTTP Method</label></th>
        <td>
          <select id="method" name="method">
            <option value="GET">GET</option>
            <option value="POST">POST</option>
            <option value="PUT">PUT</option>
            <option value="DELETE">DELETE</option>
          </select>
        </td>
      </tr>
      <tr>
        <th><label for="headers">Headers (JSON)</label></th>
        <td><textarea id="headers" name="headers" rows="4" class="large-text"></textarea></td>
      </tr>
      <tr>
        <th><label for="secret_salt">Secret Salt (Keep it safe!)</label></th>
        <td><input name="secret_salt" type="text" class="regular-text" required></td>
      </tr>
      <tr>
        <th><label for="is_active">Active?</label></th>
        <td><input id="is_active" type="checkbox" name="is_active" value="1" checked></td>
      </tr>
    </table>

    <?php submit_button('Add API'); ?>
  </form>
</div>