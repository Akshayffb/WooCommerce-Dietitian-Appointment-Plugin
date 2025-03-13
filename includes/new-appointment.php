<?php
if (!defined('ABSPATH')) {
  exit;
}

// Get current user role
$current_user = wp_get_current_user();
$is_admin = current_user_can('manage_options');
$is_dietitian = current_user_can('dietitian');

if (!$is_admin && !$is_dietitian) {
  wp_die(__('You do not have permission to access this page.', 'textdomain'));
}

global $wpdb;
$appointments_table = $wpdb->prefix . 'wdb_appointments';
$dietitians_table = $wpdb->prefix . 'wdb_dietitians';

// Get Dietitian ID for logged-in user
$dietitian = $wpdb->get_row($wpdb->prepare("SELECT id FROM $dietitians_table WHERE user_id = %d", $current_user->ID));
$dietitian_id = $dietitian ? $dietitian->id : 0;

// Restrict dietitians to their own data
$edit_id = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : 0;
$is_edit = ($edit_id > 0);
$appointment = null;
$message = '';

// Fetch appointment details if editing
if ($is_edit) {
  $appointment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $appointments_table WHERE id = %d", $edit_id));

  if (!$appointment) {
    wp_die(__('Appointment not found.'));
  }

  // Restrict dietitians to editing only their own appointments
  if ($is_dietitian && $appointment->dietitian_id != $dietitian_id) {
    wp_die(__('You can only edit your own appointments.', 'textdomain'));
  }
} elseif ($is_dietitian) {
  // Dietitians are not allowed to create new appointments
  wp_die(__('You do not have permission to create an appointment.', 'textdomain'));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wdb_save_appointment'])) {
  check_admin_referer('wdb_appointment_action', 'wdb_appointment_nonce');

  $order_id = intval($_POST['order_id']);
  $customer_id = intval($_POST['customer_id']);
  $dietitian_id_input = intval($_POST['dietitian_id']);
  $appointment_date = sanitize_text_field($_POST['appointment_date']);
  $meeting_link = esc_url_raw($_POST['meeting_link']);
  $status = sanitize_text_field($_POST['status']);

  if ($is_edit) {
    // Dietitians can only update their own appointments
    if ($is_dietitian && $appointment->dietitian_id != $dietitian_id) {
      wp_die(__('You can only edit your own appointments.', 'textdomain'));
    }

    // Update appointment
    $result = $wpdb->update(
      $appointments_table,
      [
        'order_id'         => $order_id,
        'customer_id'      => $customer_id,
        'dietitian_id'     => $dietitian_id_input,
        'appointment_date' => $appointment_date,
        'meeting_link'     => $meeting_link,
        'status'           => $status,
      ],
      ['id' => $edit_id],
      ['%d', '%d', '%d', '%s', '%s', '%s'],
      ['%d']
    );

    if ($result === false) {
      echo '<div class="error notice is-dismissible"><p>Error updating appointment: ' . $wpdb->last_error . '</p></div>';
    } else {
      echo '<div class="updated notice is-dismissible"><p>Rows affected: ' . $result . '</p></div>';
    }
  } elseif ($is_admin) {
    // Admins can create new appointments
    $wpdb->insert(
      $appointments_table,
      [
        'order_id'         => $order_id,
        'customer_id'      => $customer_id,
        'dietitian_id'     => $dietitian_id_input,
        'appointment_date' => $appointment_date,
        'meeting_link'     => $meeting_link,
        'status'           => $status,
      ],
      ['%d', '%d', '%d', '%s', '%s', '%s']
    );

    $message = '<div class="updated notice is-dismissible"><p>Appointment added successfully!</p></div>';
  }
}

// Fetch only the dietitian's appointments
if ($is_dietitian) {
  $appointments = $wpdb->get_results($wpdb->prepare("SELECT * FROM $appointments_table WHERE dietitian_id = %d", $dietitian_id));
} else {
  // Admin fetches all appointments
  $appointments = $wpdb->get_results("SELECT * FROM $appointments_table");
}

// Fetch dietitians for dropdown
$dietitians = $wpdb->get_results("SELECT id, name FROM $dietitians_table");
?>

<div class="wrap">
  <h1 class="wp-heading-inline"><?php echo $is_edit ? 'Edit Appointment' : 'Add New Appointment'; ?></h1>
  <a href="<?php echo esc_url(admin_url('admin.php?page=wdb-all-appointments')); ?>" class="page-title-action">Back to List</a>
  <hr class="wp-header-end">

  <?php if (isset($message)) echo $message; ?>

  <form method="POST">
    <?php wp_nonce_field('wdb_appointment_action', 'wdb_appointment_nonce'); ?>

    <table class="form-table">
      <tr>
        <th><label for="order_id">Order ID</label></th>
        <td><input type="number" name="order_id" id="order_id" value="<?php echo esc_attr($appointment->order_id ?? ''); ?>" required class="regular-text" <?php echo $is_dietitian ? 'disabled' : ''; ?>></td>
      </tr>
      <tr>
        <th><label for="customer_id">Customer ID</label></th>
        <td><input type="number" name="customer_id" id="customer_id" value="<?php echo esc_attr($appointment->customer_id ?? ''); ?>" required class="regular-text" <?php echo $is_dietitian ? 'disabled' : ''; ?>></td>
      </tr>
      <tr>
        <th><label for="dietitian_id">Dietitian</label></th>
        <td>
          <select name="dietitian_id" id="dietitian_id" class="regular-text" <?php echo $is_dietitian ? 'disabled' : ''; ?>>
            <option value="">Select Dietitian</option>
            <?php foreach ($dietitians as $dietitian) : ?>
              <option value="<?php echo esc_attr($dietitian->id); ?>" <?php selected($appointment->dietitian_id ?? '', $dietitian->id); ?>>
                <?php echo esc_html($dietitian->name); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </td>
      </tr>
      <tr>
        <th><label for="appointment_date">Appointment Date</label></th>
        <td><input type="datetime-local" name="appointment_date" id="appointment_date" value="<?php echo esc_attr($appointment->appointment_date ?? ''); ?>" required class="regular-text"></td>
      </tr>
      <tr>
        <th><label for="meeting_link">Meeting Link</label></th>
        <td><input type="url" name="meeting_link" id="meeting_link" value="<?php echo esc_attr($appointment->meeting_link ?? ''); ?>" class="regular-text"></td>
      </tr>
      <tr>
        <th><label for="status">Status</label></th>
        <td>
          <select name="status" id="status" class="regular-text">
            <option value="pending" <?php selected($appointment->status ?? '', 'pending'); ?>>Pending</option>
            <option value="confirmed" <?php selected($appointment->status ?? '', 'confirmed'); ?>>Confirmed</option>
            <option value="completed" <?php selected($appointment->status ?? '', 'completed'); ?>>Completed</option>
            <option value="cancelled" <?php selected($appointment->status ?? '', 'cancelled'); ?>>Cancelled</option>
          </select>
        </td>
      </tr>
    </table>

    <p class="submit">
      <button type="submit" name="wdb_save_appointment" class="button button-primary"><?php echo $is_edit ? 'Update Appointment' : 'Save Appointment'; ?></button>
    </p>
  </form>
</div>