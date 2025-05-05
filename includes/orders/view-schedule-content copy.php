<?php
function wdb_schedule_endpoint_content()
{
  global $wpdb;

  $meal_plan_table = $wpdb->prefix . 'wdb_meal_plans';
  $schedule_table = $wpdb->prefix . 'wdb_meal_plan_schedules';

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_schedule_nonce']) && wp_verify_nonce($_POST['update_schedule_nonce'], 'update_schedule_action')) {
    $order_id = intval($_POST['order_id']);
    $new_date = sanitize_text_field($_POST['date']);
    $weekday = sanitize_text_field($_POST['weekday']);
    $meal_type = sanitize_text_field($_POST['meal_type']);
    $delivery = sanitize_text_field($_POST['delivery']);

    $plan = $wpdb->get_row(
      $wpdb->prepare("SELECT * FROM $meal_plan_table WHERE order_id = %d", $order_id),
      ARRAY_A
    );

    if (!$plan || $plan['user_id'] != get_current_user_id()) {
      echo "<p class='text-danger'>Invalid or unauthorized meal plan.</p>";
      return;
    }

    $meal_plan_id = $plan['id'];
    $existing_entry = $wpdb->get_row(
      $wpdb->prepare("SELECT * FROM $schedule_table WHERE meal_plan_id = %d AND weekday = %s", $meal_plan_id, $weekday),
      ARRAY_A
    );

    if (!$existing_entry) return;

    $data = [];
    $format = [];

    if (!empty($new_date)) {
      $data['serve_date'] = $new_date;
      $format[] = '%s';
    }

    if (!empty($meal_type) && !empty($delivery)) {
      // Trim the meal types and delivery windows from the existing entry
      $meal_types = array_map('trim', explode(',', $existing_entry['meal_type']));
      $delivery_windows = array_map('trim', explode(',', $existing_entry['delivery_window']));

      $matched = false;

      // Check for matching meal type and update the corresponding delivery window
      foreach ($meal_types as $i => $type) {
        if (strtolower(trim($type)) === strtolower(trim($meal_type))) {
          $meal_types[$i] = $meal_type;
          $delivery_windows[$i] = $delivery;
          $matched = true;
          break;
        }
      }

      // If a match is found, update both the meal_type and delivery_window
      if ($matched) {
        $data['meal_type'] = implode(', ', $meal_types);
        $data['delivery_window'] = implode(', ', $delivery_windows);
        $format[] = '%s';
        $format[] = '%s';
      } else {
        // If no match is found, force update the meal_type and delivery_window
        $data['meal_type'] = $meal_type;
        $data['delivery_window'] = $delivery;
        $format[] = '%s';
        $format[] = '%s';
        echo "<p class='text-warning'>Meal type not found in schedule. Only date updated, but meal type and delivery window are updated anyway.</p>";
      }
    }

    // Only update if there's something to update
    if (!empty($data)) {
      $wpdb->update(
        $schedule_table,
        $data,
        ['id' => $existing_entry['id']],
        $format,
        ['%d']
      );
      echo "<p class='text-success'>Schedule updated successfully.</p>";
    }
  }

  $order_id = get_query_var('view-schedule');

  if (!is_numeric($order_id)) {
    echo "<p>Invalid schedule ID.</p>";
    return;
  }

  $plan = $wpdb->get_row(
    $wpdb->prepare("SELECT * FROM $meal_plan_table WHERE order_id = %d", $order_id),
    ARRAY_A
  );

  if (!$plan || $plan['user_id'] != get_current_user_id()) {
    echo "<p>Unauthorized access or meal plan not found.</p>";
    return;
  }

  $meals = $wpdb->get_results(
    $wpdb->prepare("SELECT * FROM $schedule_table WHERE meal_plan_id = %d ORDER BY serve_date ASC", $plan['id']),
    ARRAY_A
  );

  // Output HTML
?>
  <h2 class="fs-4">Meal Plan: <?php echo esc_html($plan['plan_name']); ?></h2>
  <p><strong>Start Date:</strong> <?php echo esc_html($plan['start_date']); ?></p>
  <p><strong>Duration:</strong> <?php echo esc_html($plan['plan_duration']); ?> days</p>
  <p><strong>Category:</strong> <?php echo esc_html($plan['category']); ?></p>
  <p><strong>Ingredients not included:</strong> <?php echo esc_html($plan['ingredients']); ?></p>
  <p><strong>Notes:</strong> <?php echo esc_html($plan['notes']); ?></p>

  <h3 class="fs-4 mt-3">Scheduled Meals</h3>
  <?php if (!empty($meals)): ?>
    <table class="woocommerce-table shop_table" border="1" cellpadding="8">
      <thead>
        <tr>
          <th>Sl. No</th>
          <th>Date</th>
          <th>Weekday</th>
          <th style="width: 250px;">Meal Info</th>
          <th>Meal Type</th>
          <th>Delivery Time</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $grouped = [];
        foreach ($meals as $meal) {
          $key = $meal['serve_date'] . '_' . $meal['weekday'];
          $grouped[$key][] = $meal;
        }

        $sl = 1;
        foreach ($grouped as $group_meals):
          foreach ($group_meals as $meal):
            $meal_types = array_map('trim', explode(',', $meal['meal_type']));
            $delivery_times = array_map('trim', explode(',', $meal['delivery_window']));
            $count = count($meal_types);

            for ($i = 0; $i < $count; $i++):
              echo '<tr>';
              if ($i === 0):
                echo '<td rowspan="' . $count . '">' . $sl++ . '</td>';
                echo '<td rowspan="' . $count . '">' . date('d M Y', strtotime($meal['serve_date'])) . '</td>';
                echo '<td rowspan="' . $count . '">' . esc_html($meal['weekday']) . '</td>';
                echo '<td rowspan="' . $count . '">';
                $meal_info_parts = explode('|', $meal['meal_info']);
                foreach ($meal_info_parts as $part) {
                  echo '<div>' . esc_html(trim($part)) . '</div>';
                }
                echo '</td>';
              endif;
              echo '<td>' . esc_html($meal_types[$i]) . '</td>';
              echo '<td>' . (!empty($delivery_times[$i]) ? esc_html($delivery_times[$i]) : '') . '</td>';
              echo '<td>
                                <button type="button" class="btn btn-outline-info btn-sm open-add-modal"
                                    data-bs-toggle="modal"
                                    data-bs-target="#addModal"
                                    data-date="' . esc_attr($meal['serve_date']) . '"
                                    data-weekday="' . esc_attr($meal['weekday']) . '"
                                    data-order-id="' . esc_attr($order_id) . '"
                                    data-meal_type="' . esc_attr($meal_types[$i]) . '"
                                    data-delivery="' . esc_attr($delivery_times[$i] ?? '') . '">
                                    Update
                                </button>
                            </td>';
              echo '</tr>';
            endfor;
          endforeach;
        endforeach;
        ?>
      </tbody>
    </table>
  <?php else: ?>
    <p>No meal data available.</p>
  <?php endif; ?>

  <!-- Modal -->
  <div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form id="updateScheduleForm" method="POST">
          <div class="modal-header">
            <h5 class="modal-title">Update Schedule</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <?php wp_nonce_field('update_schedule_action', 'update_schedule_nonce'); ?>
            <input type="hidden" name="order_id" id="modal-order-id">
            <div class="d-flex justify-content-between gap-2">
              <div class="mb-3 w-100">
                <label for="modal-date" class="form-label">Date</label>
                <input type="date" class="form-control" name="date" id="modal-date" required>
                <div class="invalid-feedback">Date cannot be in the past.</div>
              </div>
              <div class="mb-3 w-100">
                <label for="modal-weekday" class="form-label">Weekday</label>
                <input type="text" class="form-control" name="weekday" id="modal-weekday" readonly>
              </div>
            </div>
            <div class="d-flex justify-content-between gap-2">
              <div class="mb-3 w-100">
                <label for="modal-meal-type" class="form-label">Meal Type</label>
                <select class="form-select" name="meal_type" id="modal-meal-type" required>
                  <option value="breakfast">Breakfast</option>
                  <option value="lunch">Lunch</option>
                  <option value="dinner">Dinner</option>
                </select>
              </div>
              <div class="mb-3 w-100">
                <label for="modal-delivery" class="form-label">Delivery Time</label>
                <select class="form-select" name="delivery" id="modal-delivery" required></select>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    if (!$) $ = jQuery;

    $(function() {
      const deliveryTimeOptions = {
        breakfast: ["8 AM to 10 AM", "10 AM to 12 PM"],
        lunch: ["12 PM to 2 PM", "2 PM to 4 PM"],
        dinner: ["7 PM to 9 PM", "9 PM to 11 PM"]
      };

      function updateDeliveryOptions(mealType, selectedDelivery = "") {
        const deliverySelect = $("#modal-delivery");
        deliverySelect.empty();
        if (deliveryTimeOptions[mealType]) {
          deliveryTimeOptions[mealType].forEach(function(slot) {
            const option = $("<option>").val(slot).text(slot);
            if (slot === selectedDelivery) {
              option.prop("selected", true);
            }
            deliverySelect.append(option);
          });
        }
      }

      $(".open-add-modal").on("click", function() {
        const date = $(this).data("date");
        const weekday = $(this).data("weekday");
        const mealType = $(this).data("meal_type").toLowerCase();
        const delivery = $(this).data("delivery");
        const orderID = $(this).data("order-id");

        $("#modal-date").val(date);
        $("#modal-weekday").val(weekday);
        $("#modal-meal-type").val(mealType);
        $("#modal-order-id").val(orderID);

        updateDeliveryOptions(mealType, delivery);
      });

      $("#modal-meal-type").on("change", function() {
        updateDeliveryOptions($(this).val());
      });

      $("#modal-date").on("change", function() {
        const selectedDate = new Date(this.value);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (selectedDate < today) {
          this.setCustomValidity("Date cannot be in the past");
          $(this).addClass("is-invalid");
        } else {
          this.setCustomValidity("");
          $(this).removeClass("is-invalid");
          const weekdayName = selectedDate.toLocaleDateString("en-US", {
            weekday: "long"
          });
          $("#modal-weekday").val(weekdayName);
        }
      });
    });
  </script>
<?php
}
