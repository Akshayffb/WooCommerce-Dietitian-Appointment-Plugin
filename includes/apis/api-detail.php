<?php
if (!defined('ABSPATH')) {
  exit;
}

if (!current_user_can('manage_options')) {
  wp_die(__('You do not have permission to view this page.'));
}

global $wpdb;
$table = $wpdb->prefix . 'wdb_apis';

$api_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$api = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $api_id));

if (!$api) {
  echo '<div class="notice notice-error"><p>API not found.</p></div>';
  return;
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('update_api_details')) {
  $new_api_name = sanitize_text_field($_POST['api_name']);
  $new_api_slug = sanitize_title($_POST['api_slug']);
  $new_endpoint = esc_url_raw($_POST['endpoint']);
  $new_method = sanitize_text_field($_POST['method']);
  $new_headers = wp_kses_post($_POST['headers']);
  $new_secret_salt = sanitize_text_field($_POST['secret_salt']);
  $is_active = isset($_POST['is_active']) ? 1 : 0;

  $api_key = $api->api_key;

  // Check if slug or salt changed, rehash API key
  if ($new_secret_salt !== $api->secret_salt || $new_api_slug !== $api->api_slug) {
    $api_key = hash_hmac('sha256', $new_api_slug, $new_secret_salt);
  }

  $updated = $wpdb->update(
    $table,
    [
      'api_name' => $new_api_name,
      'api_slug' => $new_api_slug,
      'endpoint' => $new_endpoint,
      'method' => $new_method,
      'headers' => $new_headers,
      'secret_salt' => $new_secret_salt,
      'api_key' => $api_key,
      'is_active' => $is_active,
    ],
    ['id' => $api_id]
  );

  if ($updated !== false) {
    echo '<div class="notice notice-success is-dismissible"><p>API updated successfully.</p></div>';
    // Refresh data
    $api = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $api_id));
  } else {
    echo '<div class="notice notice-error"><p>Failed to update API.</p></div>';
  }
}
?>

<div class="wrap">
  <h1 class="wp-heading-inline">Edit API</h1>
  <a href="<?php echo esc_url(admin_url('admin.php?page=wdb-manage-apis')); ?>" class="page-title-action">Back to All APIs</a>
  <hr class="wp-header-end">

  <form method="post">
    <?php wp_nonce_field('update_api_details'); ?>
    <table class="form-table">
      <tr>
        <th scope="row"><label for="api_name">API Name</label></th>
        <td><input name="api_name" type="text" class="regular-text" value="<?php echo esc_attr($api->api_name); ?>" required></td>
      </tr>
      <tr>
        <th scope="row"><label for="api_slug">API Slug</label></th>
        <td><input name="api_slug" type="text" class="regular-text" value="<?php echo esc_attr($api->api_slug); ?>" required></td>
      </tr>
      <tr>
        <th scope="row"><label for="endpoint">Endpoint URL</label></th>
        <td><input name="endpoint" type="url" class="regular-text" value="<?php echo esc_attr($api->endpoint); ?>" required></td>
      </tr>
      <tr>
        <th scope="row"><label for="method">HTTP Method</label></th>
        <td>
          <select name="method">
            <option value="GET" <?php selected($api->method, 'GET'); ?>>GET</option>
            <option value="POST" <?php selected($api->method, 'POST'); ?>>POST</option>
            <option value="PUT" <?php selected($api->method, 'PUT'); ?>>PUT</option>
            <option value="DELETE" <?php selected($api->method, 'DELETE'); ?>>DELETE</option>
          </select>
        </td>
      </tr>
      <tr>
        <th scope="row"><label for="headers">Headers</label></th>
        <td><textarea name="headers" rows="6" class="large-text"><?php echo esc_textarea($api->headers); ?></textarea></td>
      </tr>
      <tr>
        <th scope="row"><label for="secret_salt">Secret Salt Key</label></th>
        <td><input name="secret_salt" type="text" class="regular-text" value="<?php echo esc_attr($api->secret_salt); ?>"></td>
      </tr>
      <tr>
        <th scope="row"><label for="api_key">API Key</label></th>
        <td><input name="api_key" type="text" class="regular-text" value="<?php echo esc_attr($api->api_key); ?>" readonly></td>
      </tr>
      <tr>
        <th scope="row"><label for="is_active">Status</label></th>
        <td><label><input type="checkbox" name="is_active" value="1" <?php checked($api->is_active, 1); ?>> Active</label></td>
      </tr>
    </table>
    <?php submit_button('Update API'); ?>
  </form>
</div>
