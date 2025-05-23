<?php
if (!defined('ABSPATH')) {
  exit;
}

if (!current_user_can('manage_options')) {
  wp_die(__('You do not have permission to view this page.'));
}

global $wpdb;
$table = $wpdb->prefix . 'wdb_apis';

$encryption_key = defined('API_ENCRYPTION_KEY') ? API_ENCRYPTION_KEY : null;
if (!$encryption_key) {
  wp_die('Encryption key not configured.');
}

function encrypt_api_key($plain_key, $encryption_key)
{
  $iv = random_bytes(16);
  $encrypted = openssl_encrypt($plain_key, 'AES-256-CBC', $encryption_key, 0, $iv);
  return ['encrypted_key' => $encrypted, 'iv' => base64_encode($iv)];
}

if (
  $_SERVER['REQUEST_METHOD'] === 'POST'
  && isset($_POST['wdb_add_api_nonce'])
  && wp_verify_nonce($_POST['wdb_add_api_nonce'], 'wdb_add_api')
) {
  $secret_salt = sanitize_text_field($_POST['secret_salt']);
  $plain_api_key = bin2hex(random_bytes(32));

  $encryption_result = encrypt_api_key($plain_api_key, $encryption_key);
  $encrypted_api_key = $encryption_result['encrypted_key'];
  $api_key_iv = $encryption_result['iv'];

  $hashed_api_key = hash_hmac('sha256', $plain_api_key, $secret_salt);

  // NEW: generate public and secret keys for HMAC signature
  $public_key = bin2hex(random_bytes(16));
  $secret_key = bin2hex(random_bytes(32));

  $inserted = $wpdb->insert($table, [
    'api_name'     => sanitize_text_field($_POST['api_name']),
    'api_slug'     => sanitize_title($_POST['api_slug']),
    'endpoint'     => esc_url_raw($_POST['endpoint']),
    'method'       => sanitize_text_field($_POST['method']),
    'headers'      => sanitize_textarea_field($_POST['headers']),
    'api_key'      => $hashed_api_key,
    'secret_salt'  => $secret_salt,
    'public_key'   => $public_key,
    'secret_key'   => $secret_key,
    'is_active'    => isset($_POST['is_active']) ? 1 : 0,
    'created_at'   => current_time('mysql'),
    'updated_at'   => current_time('mysql'),
  ]);

  if ($inserted) {
    echo '<div class="notice notice-success is-dismissible">';
    echo '<p>API added successfully.</p>';
    echo '<p><strong>Your API key (copy it now, it will not be shown again):</strong></p>';
    echo '<pre style="background:#eee;padding:10px;border:1px solid #ccc;">' . esc_html($plain_api_key) . '</pre>';
    echo '<p><strong>Your Public Key:</strong></p>';
    echo '<pre style="background:#eee;padding:10px;border:1px solid #ccc;">' . esc_html($public_key) . '</pre>';
    echo '<p><strong>Your Secret Key (save securely, not shown again):</strong></p>';
    echo '<pre style="background:#eee;padding:10px;border:1px solid #ccc;">' . esc_html($secret_key) . '</pre>';
    echo '</div>';
  } else {
    echo '<div class="notice notice-error is-dismissible"><p>Failed to add API. Please try again.</p></div>';
  }
}
?>

<div class="wrap">
  <h1>Add New API</h1>

  <form method="POST">
    <?php wp_nonce_field('wdb_add_api', 'wdb_add_api_nonce'); ?>

    <table class="form-table">
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