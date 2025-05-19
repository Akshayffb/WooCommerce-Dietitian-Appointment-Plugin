<?php
if (!defined('ABSPATH')) {
  exit;
}

if (!current_user_can('manage_options')) {
  wp_die(__('You do not have permission to view this page.'));
}

global $wpdb;
$log_table = $wpdb->prefix . 'wdb_api_logs';

// Get the latest 100 logs
$logs = $wpdb->get_results("SELECT * FROM $log_table ORDER BY created_at DESC LIMIT 100");

function pretty_print_json($json_string)
{
  $decoded = json_decode($json_string, true);
  if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
    return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  }
  return esc_html($json_string);
}
?>

<div class="wrap">
  <h1 class="wp-heading-inline">API Logs</h1>
  <hr class="wp-header-end">

  <table class="wp-list-table widefat fixed striped">
    <thead>
      <tr>
        <th>ID</th>
        <th>User ID</th>
        <th>API Slug</th>
        <th>Status</th>
        <th>Retries</th>
        <th>IP</th>
        <th>IP</th>
        <th>Created On</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($logs)) : ?>
        <?php foreach ($logs as $log) : ?>
          <tr>
            <td><?php echo esc_html($log->id); ?></td>
            <td><?php echo esc_html($log->user_id); ?></td>
            <td><code><?php echo esc_html($log->api_slug); ?></code></td>
            <td><?php echo esc_html($log->status_code); ?></td>
            <td><?php echo esc_html($log->retries); ?></td>
            <td><?php echo esc_html($log->ip_address); ?></td>
            <td><?php echo esc_html($log->created_at); ?></td>
            <td>
              <a href="<?php echo admin_url('admin.php?page=wdb-api-log-detail&id=' . intval($log->id)); ?>">View Details</a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else : ?>
        <tr>
          <td colspan="8"><em>No logs found.</em></td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<script>
  (function($) {
    $(document).ready(function() {
      $('.toggle-log-details').on('click', function(e) {
        e.preventDefault();
        $(this).next('.log-details').slideToggle();
      });
    });
  })(jQuery);
</script>