<?php
if (!defined('ABSPATH')) {
  exit;
}

if (!current_user_can('manage_options')) {
  wp_die(__('You do not have permission to view this page.'));
}

global $wpdb;
$log_table = $wpdb->prefix . 'wdb_api_logs';

$log_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$log = $wpdb->get_row(
  $wpdb->prepare("SELECT * FROM $log_table WHERE id = %d", $log_id)
);

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
  <h1 class="wp-heading-inline">API Log Detail</h1>
  <a href="<?php echo admin_url('admin.php?page=wdb-api-logs'); ?>" class="button">Back to Logs</a>
  <hr class="wp-header-end">

  <?php if ($log): ?>
    <table class="widefat fixed striped">
      <tbody>
        <tr>
          <th>ID</th>
          <td><?php echo esc_html($log->id); ?></td>
        </tr>
        <tr>
          <th>User ID</th>
          <td><?php echo esc_html($log->user_id); ?></td>
        </tr>
        <tr>
          <th>API Slug</th>
          <td><?php echo esc_html($log->api_slug); ?></td>
        </tr>
        <tr>
          <th>Status Code</th>
          <td><?php echo esc_html($log->status_code); ?></td>
        </tr>
        <tr>
          <th>Retries</th>
          <td><?php echo esc_html($log->retries); ?></td>
        </tr>
        <tr>
          <th>IP Address</th>
          <td><?php echo esc_html($log->ip_address); ?></td>
        </tr>
        <tr>
          <th>User Agent</th>
          <td><?php echo esc_html($log->user_agent); ?></td>
        </tr>
        <tr>
          <th>Duration (ms)</th>
          <td><?php echo esc_html($log->duration_ms); ?></td>
        </tr>
        <tr>
          <th>Triggered At</th>
          <td><?php echo esc_html($log->created_at); ?></td>
        </tr>
        <tr>
          <th>Request Payload</th>
          <td>
            <pre><?php echo pretty_print_json($log->request_payload); ?></pre>
          </td>
        </tr>
        <tr>
          <th>Response</th>
          <td>
            <pre><?php echo pretty_print_json($log->response_text); ?></pre>
          </td>
        </tr>
        <?php if (!empty($log->error_message)): ?>
          <tr>
            <th>Error</th>
            <td>
              <pre><?php echo esc_html($log->error_message); ?></pre>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p><em>Log not found.</em></p>
  <?php endif; ?>
</div>