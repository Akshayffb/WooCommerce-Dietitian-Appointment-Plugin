<?php

function cancel_schedule($wpdb)
{
  $schedule_table = $wpdb->prefix . 'wdb_meal_plan_schedules';
  $schedule_status_table = $wpdb->prefix . 'wdb_meal_plan_schedule_status';

  if (isset($_POST['cancel_reschedule_nonce']) && wp_verify_nonce($_POST['cancel_reschedule_nonce'], 'cancel_or_reschedule_action')) {

    $meal_id       = isset($_POST['meal_id']) ? intval($_POST['meal_id']) : 0;
    $meal_plan_id  = isset($_POST['meal_plan_id']) ? intval($_POST['meal_plan_id']) : 0;
    $serve_date    = isset($_POST['serve_date']) ? sanitize_text_field($_POST['serve_date']) : '';
    $meal_type     = isset($_POST['meal_type']) ? sanitize_text_field($_POST['meal_type']) : '';

    if ($meal_id && $meal_plan_id && $serve_date && $meal_type) {

      // Check if record exists in main schedule table
      $record = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $schedule_table WHERE id = %d AND meal_plan_id = %d AND serve_date = %s",
        $meal_id,
        $meal_plan_id,
        $serve_date
      ));

      if ($record) {
        // Cancel entire date
        if ($meal_type === 'All') {
          $updated = $wpdb->update(
            $schedule_table,
            ['status' => 'cancelled'],
            ['id' => $meal_id, 'meal_plan_id' => $meal_plan_id, 'serve_date' => $serve_date],
            ['%s'],
            ['%d', '%d', '%s']
          );

          if ($updated !== false) {
            echo "<p class='text-success'>Entire day cancelled successfully.</p>";
          } else {
            echo "<p class='text-warning'>No change made to the day status.</p>";
          }
        } else {
          // Cancel individual meal type
          $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $schedule_status_table WHERE meal_schedule_id = %d AND meal_type = %s",
            $meal_id,
            $meal_type
          ));

          if ($existing) {
            $wpdb->update(
              $schedule_status_table,
              ['status' => 'cancelled'],
              ['meal_schedule_id' => $meal_id, 'meal_type' => $meal_type],
              ['%s'],
              ['%d', '%s']
            );
          } else {
            $wpdb->insert(
              $schedule_status_table,
              [
                'meal_schedule_id' => $meal_id,
                'meal_type'        => $meal_type,
                'status'           => 'cancelled',
                'cancelled_at'     => current_time('mysql'),
              ],
              ['%d', '%s', '%s', '%s']
            );
          }

          echo "<p class='text-success'>{$meal_type} cancelled successfully.</p>";
        }
      } else {
        echo "<p class='text-danger'>Schedule not found.</p>";
      }
    } else {
      echo "<p class='text-danger'>Missing required fields.</p>";
    }
  } else {
    echo "<p class='text-muted'>Invalid request.</p>";
  }
}
