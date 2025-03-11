<?php
if (!defined('ABSPATH')) {
  exit;
}

global $wpdb, $current_user;

// Get logged-in user details
wp_get_current_user();
$user_id = $current_user->ID;

// If user is not logged in, show a message
if (!$user_id) {
  echo '<div class="alert alert-warning">You must be logged in to view your bookings.</div>';
  return;
}

// Get user bookings from the database
$appointments_table = $wpdb->prefix . 'wdb_appointments';
$query = $wpdb->prepare("SELECT * FROM $appointments_table WHERE customer_id = %d ORDER BY appointment_date DESC", $user_id);
$bookings = $wpdb->get_results($query);
?>

<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-md-10">
      <div class="card shadow">
        <div class="card-header bg-primary text-white">
          <h4 class="mb-0">My Bookings</h4>
        </div>
        <div class="card-body">
          <?php if (empty($bookings)) : ?>
            <p class="text-center">You have no bookings yet.</p>
          <?php else : ?>
            <table class="table table-bordered">
              <thead class="bg-light">
                <tr>
                  <th>Order ID</th>
                  <th>Dietitian</th>
                  <th>Meeting Link</th>
                  <th>Appointment Date</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($bookings as $booking) : ?>
                  <tr>
                    <td><?php echo esc_html($booking->order_id); ?></td>
                    <td>
                      <?php
                      // Get dietitian name
                      $dietitian_table = $wpdb->prefix . 'wdb_dietitians';
                      $dietitian = $wpdb->get_row($wpdb->prepare("SELECT name FROM $dietitian_table WHERE id = %d", $booking->dietitian_id));
                      echo esc_html($dietitian ? $dietitian->name : 'Unknown');
                      ?>
                    </td>
                    <td>
                      <a href="<?php echo esc_url($booking->meeting_link); ?>" target="_blank">
                        Join Meeting
                      </a>
                    </td>
                    <td><?php echo esc_html(date('d M Y, H:i', strtotime($booking->appointment_date))); ?></td>
                    <td>
                      <span class="badge bg-<?php echo $booking->status === 'pending' ? 'warning' : 'success'; ?>">
                        <?php echo ucfirst($booking->status); ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>