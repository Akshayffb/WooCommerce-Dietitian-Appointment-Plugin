<?php
if (!defined('ABSPATH')) {
  exit;
}

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
  $user_name = $current_user->display_name;
  $user_email = $current_user->user_email;

  // Get order_id from URL
  $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

  if ($order_id == 0) {
    return '<div class="woocommerce-error">Invalid Order ID. Please provide a valid order.</div>';
  }

  // Fetch API settings (Only One Record)
  $settings_table = $wpdb->prefix . 'wdb_settings';
  $settings = $wpdb->get_row("SELECT * FROM $settings_table", ARRAY_A);

  if (!$settings) {
    return '<div class="woocommerce-error">Settings not found.</div>';
  }

  $callback_url = $settings['callback_url'] ?? '';

  // Set meeting link strictly to callback_url
  $meeting_link = $callback_url;

  // Get all dietitians
  $dietitians_table = $wpdb->prefix . 'wdb_dietitians';
  $dietitians = $wpdb->get_results("SELECT id, name FROM $dietitians_table");

  // ✅ Handle Form Submission **Before Any Output**
  if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['appointment_form'])) {
    $dietitian_id = intval($_POST['dietitian_id']);
    $appointment_date = sanitize_text_field($_POST['appointment_date']);
    $status = 'pending';

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


    $subject = "Appointment Confirmation - Order #$order_id";

    $message = "
    <html>
    <head>
        <title>Appointment Confirmation</title>
    </head>
    <body>
        <p>Hello <strong>$user_name</strong>,</p>
        <p>Your appointment has been successfully booked. Here are the details:</p>
        
        <p><strong>Order ID:</strong> $order_id</p>
        <p><strong>Appointment Date:</strong> $appointment_date</p>
        <p><strong>Meeting Link:</strong> <a href='$meeting_link' style='color: #0073aa;'>$meeting_link</a></p>

        <p>Please join the meeting at the scheduled time.</p>

        <p>Best regards,<br>Your Team</p>
    </body>
    </html>";

    // Email headers
    $headers = array('Content-Type: text/html; charset=UTF-8');

    // Send email
    $email_sent = wp_mail($user_email, $subject, $message, $headers);

    if ($email_sent) {
      error_log("Email sent successfully to: $user_email for Order ID: $order_id");
    } else {
      error_log("Email failed to send to: $user_email for Order ID: $order_id");
    }

    $success_message = '<div class="woocommerce-message">Your appointment has been booked successfully!</div>';
  }

  ob_start();
?>

  <form action="" method="post" class="woocommerce-form woocommerce-checkout contact-form input-smoke ajax-contact">
    <h3 class="woocommerce-form-title sec-title">Book an Appointment</h3>

    <div class="row">
      <div class="form-group col-md-6">
        <label for="full_name">Full Name:</label>
        <input type="text" id="full_name" name="full_name" class="form-control input-text" value="<?php echo esc_attr($user_name); ?>" readonly>
      </div>

      <div class="form-group col-md-6">
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" class="form-control input-text" value="<?php echo esc_attr($user_email); ?>" readonly>
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
        <input type="text" id="meeting_link" name="meeting_link" class="form-control input-text" value="<?php echo esc_attr($meeting_link); ?>" readonly>
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

function wdb_render_booking_page()
{
  global $wpdb, $current_user;

  // Get logged-in user details
  wp_get_current_user();
  $user_id = $current_user->ID;

  if (!$user_id) {
    return '<div class="woocommerce-Message woocommerce-Message--info woocommerce-info">Please log in to view your bookings.</div>';
  }

  ob_start();
?>

  <div class="woocommerce-MyAccount-content">
    <h2 class="woocommerce-MyAccount-title">My Bookings</h2>

    <div class="woocommerce-columns woocommerce-columns--2 woocommerce-columns--equal">
      <!-- Booking Form Section -->
      <div class="woocommerce-column woocommerce-column--1">
        <div class="woocommerce-box">
          <h3 class="woocommerce-box-title">Book an Appointment</h3>
          <div class="woocommerce-box-content">
            <?php echo do_shortcode('[wdb_booking_form]'); ?>
          </div>
        </div>
      </div>

      <!-- My Bookings Section -->
      <div class="woocommerce-column woocommerce-column--2">
        <div class="woocommerce-box">
          <h3 class="woocommerce-box-title">My Appointments</h3>
          <div class="woocommerce-box-content">
            <?php
            $appointments_table = $wpdb->prefix . 'wdb_appointments';
            $dietitians_table = $wpdb->prefix . 'wdb_dietitians';

            $bookings = $wpdb->get_results("
                            SELECT a.id, a.order_id, a.meeting_link, a.appointment_date, a.status, d.name AS dietitian_name
                            FROM $appointments_table a
                            LEFT JOIN $dietitians_table d ON a.dietitian_id = d.id
                            WHERE a.customer_id = $user_id
                            ORDER BY a.appointment_date DESC
                        ");

            if (empty($bookings)) :
            ?>
              <p class="woocommerce-info">You have no bookings yet.</p>
            <?php else : ?>
              <table class="shop_table shop_table_responsive my_account_orders">
                <thead>
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
                      <td><?php echo esc_html($booking->dietitian_name); ?></td>
                      <td><a href="<?php echo esc_url($booking->meeting_link); ?>" class="woocommerce-button button" target="_blank">Join Meeting</a></td>
                      <td><?php echo esc_html(date('F j, Y, g:i A', strtotime($booking->appointment_date))); ?></td>
                      <td><span class="woocommerce-badge <?php echo $booking->status == 'pending' ? 'woocommerce-badge--pending' : 'woocommerce-badge--success'; ?>">
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

  <style>
    .woocommerce-box {
      background: #fff;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      margin-bottom: 20px;
    }

    .woocommerce-box-title {
      margin-bottom: 15px;
      font-size: 18px;
      font-weight: bold;
    }

    .woocommerce-button {
      display: inline-block;
      padding: 8px 12px;
      background: #0071a1;
      color: #fff;
      border-radius: 4px;
      text-decoration: none;
    }

    .woocommerce-badge {
      padding: 5px 10px;
      border-radius: 5px;
      font-size: 12px;
      color: #fff;
    }

    .woocommerce-badge--pending {
      background: #ff9800;
    }

    .woocommerce-badge--success {
      background: #4caf50;
    }
  </style>

<?php
  return ob_get_clean();
}

// Register the shortcode
add_shortcode('wdb_my_bookings', 'wdb_render_booking_page');
