<?php
if (!defined('ABSPATH')) {
  exit;
}

if (!current_user_can('manage_options')) {
  wp_die(__('You do not have permission to view this page.'));
}

global $wpdb;
$apis_table = $wpdb->prefix . 'wdb_apis';
$apis = $wpdb->get_results("SELECT * FROM $apis_table");

?>

<div class="wrap">
  <h1 class="wp-heading-inline">All APIs</h1>
  <a href="<?php echo esc_url(admin_url('admin.php?page=wdb-add-api')); ?>" class="page-title-action">Add New</a>
  <hr class="wp-header-end">

  <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
    <thead>
      <tr>
        <th width="4%">ID</th>
        <th>API Name</th>
        <th>Slug</th>
        <th>Method</th>
        <th>Headers</th>
        <th>Salt Key</th>
        <th>Hashed Key</th>
        <th>Endpoint</th>
        <th width="6%">Active</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($apis)) : ?>
        <?php foreach ($apis as $api) : ?>
          <tr>
            <td><?php echo esc_html($api->id); ?></td>
            <td>
              <a href="<?php echo esc_url(admin_url('admin.php?page=wdb-api-detail&id=' . $api->id)); ?>"><?php echo esc_html($api->api_name); ?></a>
            </td>
            <td><?php echo esc_html($api->api_slug); ?></td>
            <td><?php echo esc_html($api->method); ?></td>
            <td>
              <pre style="white-space: pre-wrap; word-break: break-word;"><?php echo esc_html($api->headers); ?></pre>
            </td>
            <td><code style="word-break: break-word;"><?php echo esc_html($api->secret_salt); ?></code></td>
            <td><code style="word-break: break-word;"><?php echo esc_html($api->api_key); ?></code></td>
            <td><?php echo esc_url($api->endpoint); ?></td>
            <td><?php echo $api->is_active ? '<span style="color:green;">Yes</span>' : '<span style="color:red;">No</span>'; ?></td>
          </tr>
        <?php endforeach; ?>
      <?php else : ?>
        <tr>
          <td colspan="9" class="text-center">No APIs found.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>