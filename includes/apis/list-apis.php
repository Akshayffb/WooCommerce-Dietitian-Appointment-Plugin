<?php
if (!defined('ABSPATH')) {
  exit;
}

if (!current_user_can('manage_options')) {
  wp_die(__('You do not have permission to view this page.'));
}

global $wpdb;
$apis_table = $wpdb->prefix . 'wdb_apis';

// Handle delete action
if (isset($_GET['delete_id']) && current_user_can('manage_options')) {
  $delete_id = intval($_GET['delete_id']);
  if ($delete_id > 0 && check_admin_referer('delete_api_' . $delete_id)) {
    $wpdb->delete($apis_table, ['id' => $delete_id], ['%d']);
    echo '<div class="notice notice-success is-dismissible"><p>API deleted successfully.</p></div>';
  }
}

// Fetch APIs
$apis = $wpdb->get_results("SELECT * FROM $apis_table");

?>

<div class="wrap">
  <h1 class="wp-heading-inline mb-0">Manage APIs</h1>
  <a href="<?php echo esc_url(admin_url('admin.php?page=wdb-add-api')); ?>" class="page-title-action">Add New</a>
  <a href="<?php echo esc_url(admin_url('admin.php?page=wdb-api-logs')); ?>" class="page-title-action">View Logs</a>
  <hr class="wp-header-end">

  <table class="wp-list-table widefat fixed striped">
    <thead>
      <tr>
        <th scope="col" width="4%">ID</th>
        <th scope="col">API Name</th>
        <th scope="col">Slug</th>
        <th scope="col">Method</th>
        <th scope="col">Headers</th>
        <th scope="col">Salt Key</th>
        <th scope="col">Hashed Key</th>
        <th scope="col">Public Key</th>
        <th scope="col">Endpoint</th>
        <th scope="col" width="6%">Active</th>
        <th scope="col" width="10%">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($apis)) : ?>
        <?php foreach ($apis as $api) : ?>
          <tr>
            <td><?php echo esc_html($api->id); ?></td>
            <td>
              <strong>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wdb-api-detail&id=' . $api->id)); ?>">
                  <?php echo esc_html($api->api_name); ?>
                </a>
              </strong>
            </td>
            <td><?php echo esc_html($api->api_slug); ?></td>
            <td><?php echo esc_html($api->method); ?></td>
            <td>
              <div style="max-height: 60px; overflow-y: auto;">
                <pre style="white-space: pre-wrap; word-break: break-word; margin: 0;"><?php echo esc_html($api->headers); ?></pre>
              </div>
            </td>
            <td><code style="word-break: break-word;"><?php echo esc_html($api->secret_salt); ?></code></td>
            <td><code style="word-break: break-word;"><?php echo esc_html($api->api_key); ?></code></td>
            <td><code style="word-break: break-word;"><?php echo esc_html($api->public_key); ?></code></td>
            <td><a href="<?php echo esc_url($api->endpoint); ?>" target="_blank"><?php echo esc_html($api->endpoint); ?></a></td>
            <td>
              <?php echo $api->is_active
                ? '<span class="dashicons dashicons-yes" style="color:green;"></span>'
                : '<span class="dashicons dashicons-no-alt" style="color:red;"></span>'; ?>
            </td>
            <td>
              <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=wdb-api-detail&id=' . $api->id)); ?>">View</a>
              <?php if (current_user_can('manage_options')) : ?>
                <a class="button button-small delete" style="margin-left: 5px; color: #a00;"
                  href="<?php echo wp_nonce_url(admin_url('admin.php?page=wdb-manage-apis&delete_id=' . $api->id), 'delete_api_' . $api->id); ?>"
                  onclick="return confirm('Are you sure you want to delete this API?');">
                  Delete
                </a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else : ?>
        <tr>
          <td colspan="10"><em>No APIs found.</em></td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>