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
  $user_name = esc_attr($current_user->display_name);
  $user_email = esc_attr($current_user->user_email);

  // Get order_id from URL or session
  $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

  if ($order_id == 0) {
    $args = [
      'customer_id' => $user_id,
      'limit' => 1,
      'orderby' => 'date',
      'order' => 'DESC',
      'return' => 'ids',
    ];
    $orders = wc_get_orders($args);

    if (!empty($orders)) {
      $order_id = $orders[0];
    }
  }

  if (!$order_id) {
    return '<div class="woocommerce-error">Invalid Order ID. Please provide a valid order.</div>';
  }

  // Fetch API settings
  $settings_table = $wpdb->prefix . 'wdb_settings';
  $settings = $wpdb->get_row("SELECT * FROM $settings_table", ARRAY_A);
  if (!$settings) {
    return '<div class="woocommerce-error">Appointment scheduling settings are not configured. Please contact support.</div>';
  }

  $callback_url = $settings['callback_url'] ?? '';
  $meeting_link = esc_attr($callback_url);

  // Get all dietitians
  $dietitians_table = $wpdb->prefix . 'wdb_dietitians';
  $dietitians = $wpdb->get_results("SELECT id, name, email FROM $dietitians_table");

  $success_message = '';

  // ✅ Handle Form Submission BEFORE output starts
  if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['appointment_form'])) {
    $dietitian_id = intval($_POST['dietitian_id']);
    $appointment_date = sanitize_text_field($_POST['appointment_date']);
    $status = 'Confirmed';

    if (!$order_id) {
      $success_message = '<div id="success-message" class="woocommerce-error">Invalid Order ID. Please provide a valid order.</div>';
      return $success_message;
    }

    $dietitian = $wpdb->get_row($wpdb->prepare("SELECT id, name, email FROM $dietitians_table WHERE id = %d", $dietitian_id));
    if (!$dietitian) {
      $success_message = '<div id="success-message" class="woocommerce-error">Invalid Dietitian. Please select a valid dietitian.</div>';
      return $success_message;
    }

    if (empty($appointment_date)) {
      $success_message = '<div id="success-message" class="woocommerce-error">Please provide an appointment date.</div>';
      return $success_message;
    }

    $appointments_table = $wpdb->prefix . 'wdb_appointments';
    $existing_booking = $wpdb->get_var($wpdb->prepare(
      "SELECT id FROM $appointments_table WHERE order_id = %d",
      $order_id
    ));

    if ($existing_booking) {
      $success_message = '<div id="success-message" class="woocommerce-info">An appointment has already been booked for this order.</div>';
      return $success_message;
    }

    // Insert into database
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

    delete_post_meta($order_id, '_wdb_order_id');

    $dietitian_name = esc_html($dietitian->name);
    $dietitian_email = esc_html($dietitian->email);
    $site_name = get_bloginfo('name');

    $customer_subject = "📅 Appointment Confirmation with Dietitian $dietitian_name";

    $customer_message = "
            <html>
            <head>
                <title>$customer_subject</title>
                <style>
                    body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
                    .email-container { max-width: 600px; background: #ffffff; margin: 20px auto; padding: 30px; border-radius: 8px; box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1); text-align: left; }
                    .content { padding: 20px; }
                    .content h2 { color: #333; font-size: 24px; margin-bottom: 15px; }
                    .content p { color: #555; font-size: 16px; margin-bottom: 15px; }
                    .btn { background: #0073aa; color: white; padding: 14px 20px; text-decoration: none; display: inline-block; border-radius: 5px; font-size: 16px; font-weight: bold; margin-top: 20px; }
                    .footer { margin-top: 30px; font-size: 14px; color: #777; }
                </style>
            </head>
            <body>
                <div class='email-container'>
                    <div class='content'>
                        <h3>Hi $user_name,</h3>
            <p>Thank you for booking your appointment with <strong>$dietitian_name</strong>.</p>
            <p>Here are your appointment details:</p>
            <p>Date & Time: $appointment_date</p>
            <p>Meeting Link: <a href='$meeting_link'>$meeting_link</a></p>
            <p>To get the most out of your session, consider preparing a list of questions or topics you'd like to discuss.</p>
            <p>If you have any concerns or need to reschedule, feel free to reach out.</p>
            <p>We look forward to seeing you!</p>
            <p>Best regards,<br>The $site_name Team</p>
                    <div class='footer'>
                      &copy; " . date('Y') . " " . esc_html($site_name) . ". All rights reserved.</div>
                </div>
            </body>
            </html>";

    $headers = [
      'Content-Type: text/html; charset=UTF-8',
      'From: ' . get_bloginfo('name') . ' <' . esc_attr(get_option('admin_email')) . '>'
    ];

    // Send email to the customer
    wp_mail($user_email, $customer_subject, $customer_message, $headers);

    // Email content for Dietitian
    $dietitian_subject = "📅 New Appointment Scheduled with $user_name";

    $dietitian_message = "
            <html>
            <head>
                <title>$dietitian_subject</title>
                <style>
                    body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
                    .email-container { max-width: 600px; background: #ffffff; margin: 20px auto; padding: 30px; border-radius: 8px; box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1); text-align: left; }
                    .content { padding: 20px; }
                    .content h2 { color: #333; font-size: 24px; margin-bottom: 15px; }
                    .content p { color: #555; font-size: 16px; margin-bottom: 15px; }
                    .btn { background: #0073aa; color: white; padding: 14px 20px; text-decoration: none; display: inline-block; border-radius: 5px; font-size: 16px; font-weight: bold; margin-top: 20px; }
                    .footer { margin-top: 30px; font-size: 14px; color: #777; }
                </style>
            </head>
            <body>
                <div class='email-container'>
                    <div class='content'>
                        <h3>Hello $dietitian_name,</h3>
            <p>You have a new appointment scheduled with <strong>$user_name.</p>
            <p>Here are the details:</p>
            <p>Date & Time: $appointment_date</p>
            <p>Meeting Link: <a href='$meeting_link'>$meeting_link</a></p>
            <p>Please review any notes or history before the session to provide the best advice.</p>
            <p>Make sure to be available on time and prepare any necessary resources.</p>
            <p>Best regards,<br>The $site_name Team</p>
                    </div>
                    <div class='footer'>
                      &copy; " . date('Y') . " " . esc_html($site_name) . ". All rights reserved.</div>
                </div>
            </body>
            </html>";

    // Send email to the dietitian
    wp_mail($dietitian_email, $dietitian_subject, $dietitian_message, $headers);

    // Set success message
    $success_message = '<div id="success-message" class="woocommerce-message">Appointment booked successfully! A confirmation email has been sent to your email. Redirecting you in <span id="counter">3</span> seconds...</div>';
    $redirect_url = site_url('/my-account/my-appointments');
    $success_message .= "
        <script>
            var counter = 5;
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
