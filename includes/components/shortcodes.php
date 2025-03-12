<?php
if (!defined('ABSPATH')) {
  exit;
}

// Start session if not started already
if (!function_exists('wdb_start_session')) {
  function wdb_start_session()
  {
    if (!session_id()) {
      session_start();
    }
  }
}
add_action('init', 'wdb_start_session');

// Render Booking Form
function wdb_render_booking_form()
{
  global $wpdb;

  // Get user details
  $user_id = get_current_user_id();
  if (!$user_id) {
    return '<div class="woocommerce-error">You must be logged in to book an appointment.</div>';
  }

  $current_user = wp_get_current_user();
  $user_name = esc_attr($current_user->display_name);
  $user_email = esc_attr($current_user->user_email);

  // Get order_id from URL or session
  $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : ($_SESSION['wdb_order_id'] ?? 0);
  if ($order_id == 0) {
    return '<div class="woocommerce-error">Invalid Order ID. Please provide a valid order.</div>';
  }

  // Fetch API settings
  $settings_table = $wpdb->prefix . 'wdb_settings';
  $settings = $wpdb->get_row("SELECT * FROM $settings_table", ARRAY_A);
  if (!$settings) {
    return '<div class="woocommerce-error">Settings not found.</div>';
  }

  $callback_url = $settings['callback_url'] ?? '';
  $meeting_link = esc_attr($callback_url);

  // Get all dietitians
  $dietitians_table = $wpdb->prefix . 'wdb_dietitians';
  $dietitians = $wpdb->get_results("SELECT id, name FROM $dietitians_table");

  $success_message = '';

  // ✅ Handle Form Submission BEFORE output starts
  if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['appointment_form'])) {
    $dietitian_id = intval($_POST['dietitian_id']);
    $appointment_date = sanitize_text_field($_POST['appointment_date']);
    $status = 'Confirmed';

    if (!$dietitian_id || empty($appointment_date)) {
      return '<div class="woocommerce-error">Please provide all required details.</div>';
    }

    // Insert into database
    $appointments_table = $wpdb->prefix . 'wdb_appointments';
    $wpdb->insert(
      $appointments_table,
      [
        'customer_id' => $user_id,
        'order_id' => $order_id,
        'dietitian_id' => $dietitian_id,
        'meeting_link' => $meeting_link,
        'appointment_date' => $appointment_date,
        'status' => $status
      ]
    );

    // Set success message
    $success_message = '<div id="success-message" class="woocommerce-message">Appointment booked successfully! Redirecting you in <span id="counter">3</span> seconds...</div>';
    $redirect_url = site_url('/my-account/my-appointments');
    $success_message .= "
            <script>
                var counter = 3;
                var countdown = setInterval(function() {
                    document.getElementById('counter').textContent = counter;
                    counter--;
                    if (counter < 0) {
                        clearInterval(countdown);
                        window.location.href = '{$redirect_url}';
                    }
                }, 1000);
            </script>
        ";
  }

  ob_start();
?>

  <?php echo $success_message; ?>

  <form action="" id="wdb-booking-form" method="post" class="woocommerce-form woocommerce-checkout contact-form input-smoke ajax-contact">
    <h3 class="woocommerce-form-title sec-title">Book an Appointment</h3>

    <div class="row">
      <div class="form-group col-md-6">
        <label for="full_name">Full Name:</label>
        <input type="text" id="full_name" name="full_name" class="form-control input-text" value="<?php echo $user_name; ?>" readonly>
      </div>

      <div class="form-group col-md-6">
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" class="form-control input-text" value="<?php echo $user_email; ?>" readonly>
      </div>

      <div class="form-group col-md-6">
        <label for="order_id">Order ID:</label>
        <input type="text" id="order_id" name="order_id" class="form-control input-text" value="<?php echo esc_attr($order_id); ?>" readonly>
      </div>

      <div class="form-group col-md-6">
        <label for="dietitian_id">Select Dietitian:</label>
        <select id="dietitian_id" name="dietitian_id" class="form-select input-text" required>
          <option value="">Select a Dietitian</option>
          <?php foreach ($dietitians as $dietitian) : ?>
            <option value="<?php echo esc_attr($dietitian->id); ?>">
              <?php echo esc_html($dietitian->name); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group col-md-6">
        <label for="meeting_link">Meeting Link:</label>
        <input type="text" id="meeting_link" name="meeting_link" class="form-control input-text" value="<?php echo $meeting_link; ?>" readonly>
      </div>

      <div class="form-group col-md-6">
        <label for="appointment_date">Appointment Date:</label>
        <input type="datetime-local" id="appointment_date" name="appointment_date" class="form-control input-text" required>
      </div>

      <input type="hidden" name="appointment_form" value="1">

      <div class="form-btn col-12 mt-4">
        <button type="submit" class="th-btn btn-fw style4">Book Appointment <i class="fas fa-chevrons-right ms-2"></i></button>
      </div>
    </div>
  </form>

<?php
  return ob_get_clean();
}

add_shortcode('wdb_booking_form', 'wdb_render_booking_form');
