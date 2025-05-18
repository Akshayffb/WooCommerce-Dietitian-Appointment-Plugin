<?php

function cancel_schedule($wpdb)
{
  $schedule_table = $wpdb->prefix . 'wdb_meal_plan_schedules';
  $schedule_status_table = $wpdb->prefix . 'wdb_meal_plan_schedule_status';

  // Log the POST data
  // file_put_contents(__DIR__ . '/cancel_log.txt', print_r($_POST, true), FILE_APPEND);

  if (isset($_POST['cancel_reschedule_nonce']) && wp_verify_nonce($_POST['cancel_reschedule_nonce'], 'cancel_or_reschedule_action')) {

    if (!empty($_POST['meal_id']) && !empty($_POST['meal_plan_id']) && !empty($_POST['serve_date'])) {
      $meal_id = intval($_POST['meal_id']);
      $meal_plan_id = intval($_POST['meal_plan_id']);
      $serve_date = sanitize_text_field($_POST['serve_date']);

      $record = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $schedule_table WHERE id = %d AND meal_plan_id = %d AND serve_date = %s",
        $meal_id,
        $meal_plan_id,
        $serve_date
      ));

      if ($record) {
        $updated = $wpdb->update(
          $schedule_table,
          ['status' => 'cancelled'],
          [
            'id' => $meal_id,
            'meal_plan_id' => $meal_plan_id,
            'serve_date' => $serve_date
          ],
          ['%s'],
          ['%d', '%d', '%s']
        );

        if ($updated !== false) {
          echo "<p class='text-success'>Schedule cancelled successfully.</p>";
        } else {
          echo "<p class='text-danger'>No changes made to the schedule.</p>";
        }
      } else {
        echo "<p class='text-warning'>No matching schedule found.</p>";
      }
    } else {
      echo "<p class='text-danger'>Missing required fields.</p>";
    }
  } else {
    echo "<p class='text-muted'>Invalid request.</p>";
  }
}
