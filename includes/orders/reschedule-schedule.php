<?php

function reschedule_schedule($wpdb)
{
  $meal_plan_table = $wpdb->prefix . 'wdb_meal_plans';
  $schedule_table = $wpdb->prefix . 'wdb_meal_plan_schedules';

  if (isset($_POST['cancel_reschedule_nonce']) && wp_verify_nonce($_POST['cancel_reschedule_nonce'], 'cancel_or_reschedule_action')) {
    echo "<p class='text-success'>Schedule rescheduled successfully.</p>";
  } else {
    echo "<p class='text-muted'>No changes detected.</p>";
  }
}
