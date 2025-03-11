<?php
if (!defined('ABSPATH')) {
  exit;
}

// Check user role
if (!current_user_can('manage_options') && !current_user_can('dietitian')) {
  wp_die(__('You do not have permission to view this page.'));
}

global $wpdb;
$appointments_table = $wpdb->prefix . 'wdb_appointments';
$dietitians_table = $wpdb->prefix . 'wdb_dietitians';
$current_user = wp_get_current_user();
$message = ''; // Initialize message variable

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_appointment'])) {
  // Verify nonce for security
  if (!isset($_POST['delete_appointment_nonce']) || !wp_verify_nonce($_POST['delete_appointment_nonce'], 'delete_appointment_action')) {
    add_settings_error('wdb_messages', 'wdb_security_error', 'Security check failed. Please try again.', 'error');
  } elseif (!current_user_can('manage_options')) {
    add_settings_error('wdb_messages', 'wdb_permission_error', 'You do not have permission to delete appointments.', 'error');
  } else {
    $appointment_id = intval($_POST['delete_appointment']);

    if ($appointment_id > 0) {
      // Delete appointment from the database
      $deleted = $wpdb->delete($appointments_table, ['id' => $appointment_id], ['%d']);

      if ($deleted) {
        add_settings_error('wdb_messages', 'wdb_delete_success', 'Appointment deleted successfully.', 'updated');
        wp_redirect(admin_url('admin.php?page=wdb-all-appointments&deleted=1'));
        exit;
      } else {
        add_settings_error('wdb_messages', 'wdb_delete_fail', 'Error: Could not delete appointment.', 'error');
      }
    } else {
      add_settings_error('wdb_messages', 'wdb_invalid_id', 'Invalid appointment ID.', 'error');
    }
  }
}

if (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
  add_settings_error('wdb_messages', 'wdb_delete_success_redirect', 'Appointment deleted successfully.', 'updated');
}

if (current_user_can('manage_options')) {
  $query = "
        SELECT a.id, a.order_id, a.customer_id, a.appointment_date, a.meeting_link, a.status, a.dietitian_id, d.name AS dietitian_name
        FROM $appointments_table a
        LEFT JOIN $dietitians_table d ON a.dietitian_id = d.id
        ORDER BY a.appointment_date DESC";
} elseif (in_array('dietitian', (array) $current_user->roles)) {
  $query = $wpdb->prepare("
        SELECT a.id, a.order_id, a.customer_id, a.appointment_date, a.meeting_link, a.status, a.dietitian_id, d.name AS dietitian_name
        FROM $appointments_table a
        LEFT JOIN $dietitians_table d ON a.dietitian_id = d.id
        WHERE a.dietitian_id = %d
        ORDER BY a.appointment_date DESC", $current_user->ID);
}

$appointments = $wpdb->get_results($query);

?>

<div class="wrap">
  <h1 class="wp-heading-inline">All Appointments</h1>
  <a href="<?php echo admin_url('admin.php?page=wdb-add-appointment'); ?>" class="page-title-action">Add New</a>
  <hr class="wp-header-end">

  <?php settings_errors('wdb_messages'); ?>

  <div class="inside">
    <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
      <thead>
        <tr>
          <th>ID</th>
          <th>Order ID</th>
          <th>Customer</th>
          <th>Dietitian</th>
          <th>Appointment Date</th>
          <th>Meeting Link</th>
          <th>Status</th>
          <?php if (current_user_can('manage_options')) : ?>
            <th>Actions</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if ($appointments) : ?>
          <?php foreach ($appointments as $appointment) : ?>
            <tr>
              <td><?php echo esc_html($appointment->id); ?></td>
              <td>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=shop_order&action=edit&post=' . $appointment->order_id)); ?>">
                  <?php echo esc_html($appointment->order_id); ?>
                </a>
              </td>
              <td><?php echo esc_html($appointment->customer_id); ?></td>
              <td>
                <?php if ($appointment->dietitian_id) : ?>
                  <a href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . $appointment->dietitian_id)); ?>">
                    <?php echo esc_html($appointment->dietitian_name ?: 'Unassigned'); ?>
                  </a>
                <?php else : ?>
                  <span class="text-danger">Unassigned</span>
                <?php endif; ?>
              </td>
              <td><?php echo esc_html($appointment->appointment_date); ?></td>
              <td>
                <?php if ($appointment->meeting_link) : ?>
                  <a href="<?php echo esc_url($appointment->meeting_link); ?>" target="_blank">Join</a>
                <?php else : ?>
                  <span class="text-muted">No link</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge bg-<?php echo ($appointment->status == 'confirmed') ? 'success' : 'warning'; ?>">
                  <?php echo esc_html(ucfirst($appointment->status)); ?>
                </span>
              </td>
              <?php if (current_user_can('manage_options')) : ?>
                <td>
                  <a href="<?php echo admin_url('admin.php?page=wdb-add-appointment&edit_id=' . $appointment->id); ?>" class="button">Edit</a>
                  <form method="post" style="display:inline;">
                    <?php wp_nonce_field('delete_appointment_action', 'delete_appointment_nonce'); ?>
                    <input type="hidden" name="delete_appointment" value="<?php echo esc_attr($appointment->id); ?>">
                    <button type="submit" class="button button-danger" onclick="return confirm('Are you sure you want to delete this appointment?')">Delete</button>
                  </form>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        <?php else : ?>
          <tr>
            <td colspan="8" class="text-center text-muted">No appointment found.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>