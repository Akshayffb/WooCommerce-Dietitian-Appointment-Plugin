<?php
if (!defined('ABSPATH')) {
  exit;
}

// Add booking button after order confirmation
function wdb_add_booking_link($order_id)
{
  $order = wc_get_order($order_id);
  if (!$order) return;

  echo '<p><a href="' . site_url('/book-a-dietitian/?order_id=' . $order_id) . '" class="button">Book Your Dietitian</a></p>';
}
add_action('woocommerce_thankyou', 'wdb_add_booking_link', 20);



// Handle the booking page
function wdb_booking_page_content()
{
  if (!isset($_GET['order_id'])) {
    return '<p>Invalid request.</p>';
  }

  $order_id = intval($_GET['order_id']);
  $order = wc_get_order($order_id);

  if (!$order) {
    return '<p>Order not found.</p>';
  }

  ob_start(); ?>

  <h2>Select a Dietitian and Time</h2>
  <form method="post" action="">
    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
    <label for="dietitian">Choose Dietitian:</label>
    <select name="dietitian">
      <option value="1">Dietitian A</option>
      <option value="2">Dietitian B</option>
    </select>

    <label for="time">Select Time:</label>
    <input type="datetime-local" name="time" required>

    <button type="submit" name="book_dietitian">Confirm Booking</button>
  </form>

<?php return ob_get_clean();
}

// Create shortcode for the booking page
function wdb_register_shortcode()
{
  add_shortcode('wdb_booking', 'wdb_booking_page_content');
}
add_action('init', 'wdb_register_shortcode');

function wdb_save_booking()
{
  if (isset($_POST['book_dietitian'])) {
    global $wpdb;

    $order_id = intval($_POST['order_id']);
    $dietitian = sanitize_text_field($_POST['dietitian']);
    $time = sanitize_text_field($_POST['time']);

    // Insert into custom table
    $wpdb->insert(
      $wpdb->prefix . 'dietitian_bookings',
      [
        'order_id'   => $order_id,
        'dietitian'  => $dietitian,
        'time'       => $time,
        'status'     => 'pending'
      ]
    );

    echo '<p>Booking Confirmed! You will receive a Google Meet link soon.</p>';
  }
}
add_action('init', 'wdb_save_booking');


function wdb_generate_google_meet_link($dietitian_email, $customer_email, $time)
{
  $google_calendar_api_key = 'YOUR_GOOGLE_API_KEY';

  $event = [
    'summary' => 'Dietitian Appointment',
    'start' => ['dateTime' => $time, 'timeZone' => 'UTC'],
    'end' => ['dateTime' => date('Y-m-d\TH:i:s', strtotime($time) + 3600), 'timeZone' => 'UTC'],
    'attendees' => [
      ['email' => $dietitian_email],
      ['email' => $customer_email]
    ],
    'conferenceData' => [
      'createRequest' => ['requestId' => uniqid()]
    ]
  ];

  $headers = [
    "Authorization: Bearer YOUR_ACCESS_TOKEN",
    "Content-Type: application/json"
  ];

  $ch = curl_init('https://www.googleapis.com/calendar/v3/calendars/primary/events');
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($event));
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  $response = curl_exec($ch);
  curl_close($ch);

  $response = json_decode($response, true);
  return $response['hangoutLink'] ?? null;
}

?>