<?php

include_once(__DIR__ . '/update-schedule.php');
include_once(__DIR__ . '/cancel-schedule.php');
include_once(__DIR__ . '/reschedule-schedule.php');


function wdb_schedule_endpoint_content()
{
  global $wpdb;

  $meal_plan_table = $wpdb->prefix . 'wdb_meal_plans';
  $schedule_table = $wpdb->prefix . 'wdb_meal_plan_schedules';
  $schedule_status_table = $wpdb->prefix . 'wdb_meal_plan_schedule_status';

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

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['form_action'])) {
      switch ($_POST['form_action']) {
        case 'cancel_schedule':
          cancel_schedule($wpdb);
          break;
        case 'reschedule_schedule':
          reschedule_schedule($wpdb);
          break;
        case 'update_schedule':
          update_schedule($wpdb);
          break;
      }

      wp_redirect($_SERVER['REQUEST_URI']);
    }
  }

  $meals = $wpdb->get_results(
    $wpdb->prepare("SELECT * FROM $schedule_table WHERE meal_plan_id = %d ORDER BY serve_date ASC", $plan['id']),
    ARRAY_A
  );

  $meal_ids = array_column($meals, 'id');

  $statuses = [];
  if (!empty($meal_ids)) {
    $placeholders = implode(',', array_fill(0, count($meal_ids), '%d'));
    $query = "SELECT meal_schedule_id, meal_type, status FROM $schedule_status_table WHERE meal_schedule_id IN ($placeholders)";
    $prepared = $wpdb->prepare($query, ...$meal_ids);
    $results = $wpdb->get_results($prepared, ARRAY_A);

    foreach ($results as $row) {
      $key = $row['meal_schedule_id'] . '_' . trim($row['meal_type']);
      $statuses[$key] = $row['status'];
    }
  }

?>
  <h2 class="fs-4">Meal Plan: <?php echo esc_html($plan['plan_name']); ?></h2>
  <p><strong>Start Date:</strong> <?php echo esc_html($plan['start_date']); ?></p>
  <p><strong>Duration:</strong> <?php echo esc_html($plan['plan_duration']); ?> days</p>
  <p><strong>Category:</strong> <?php echo esc_html($plan['category']); ?></p>
  <p><strong>Ingredients not included:</strong> <?php echo esc_html($plan['ingredients']); ?></p>
  <p><strong>Notes:</strong> <?php echo esc_html($plan['notes']); ?></p>

  <h3 class="fs-4 mt-3">Scheduled Meals</h3>
  <p>Cancellation allowed only before a day or 24 hours before.</p>
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
        $sl = 1;
        foreach ($meals as $meal) {
          $key = $meal['serve_date'] . '_' . $meal['weekday'];
          $grouped[$key][] = $meal;
        }

        foreach ($grouped as $group_meals):
          foreach ($group_meals as $meal):
            $meal_types = array_map('trim', explode(',', $meal['meal_type']));
            $delivery_times = array_map('trim', explode(',', $meal['delivery_window']));
            $count = count($meal_types);

            for ($i = 0; $i < $count; $i++):
              echo '<tr>';
              if ($i === 0):
                echo '<td rowspan="' . $count . '">' . $sl++ . '</td>';
                echo '<td rowspan="' . $count . '">';
                echo '<div>' . date('d M Y', strtotime($meal['serve_date'])) . '</div>';

                $status = strtolower(trim($meal['status'] ?? 'N/A'));
                $status_class = 'text-muted'; // default

                if ($status === 'cancelled') {
                  $status_class = 'text-danger';    // red
                } elseif ($status === 'rescheduled') {
                  $status_class = 'text-warning';   // orange/yellow
                } else {
                  $status_class = 'text-success';   // green
                }

                echo '<div class="' . $status_class . ' small">' . esc_html($status) . '</div>';
                echo '</td>';
                echo '<td rowspan="' . $count . '">' . esc_html($meal['weekday']) . '</td>';
                echo '<td rowspan="' . $count . '">';
                $meal_info_parts = isset($meal['meal_info']) ? explode('|', $meal['meal_info']) : [];
                foreach ($meal_info_parts as $part) {
                  echo '<div>' . esc_html(trim($part)) . '</div>';
                }
                echo '</td>';
              endif;

              echo '<td>';
              echo esc_html($meal_types[$i] ?? 'N/A');

              echo '</td>';

              echo '<td>' . (!empty($delivery_times[$i]) ? esc_html($delivery_times[$i]) : 'N/A') . '</td>';
              echo '<td>';

              $key = $meal['id'] . '_' . $meal_types[$i];
              $status_for_meal_type = isset($statuses[$key]) ? strtolower(trim($statuses[$key])) : '';

              if ($status !== 'cancelled' && $status !== 'rescheduled') {
                if (empty($status_for_meal_type)) {
                  echo '
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
    <br>
    <div class="text-center mt-1">
        <a href="#" class="open-cancel-modal"
            data-bs-toggle="modal"
            data-bs-target="#cancelModal"
            data-meal-id="' . esc_attr($meal['id']) . '"
            data-meal-plan-id="' . esc_attr($meal['meal_plan_id']) . '"
            data-meal-type="' . esc_attr($meal_types[$i]) . '"
            data-serve-date="' . esc_attr($meal['serve_date']) . '">
            Cancel
        </a>
    </div>';
                } else {
                  echo ucfirst($status_for_meal_type);
                }
              }
              echo '</td>';

              echo '</tr>';
            endfor;
          endforeach;
        endforeach;
        ?>
      </tbody>
    </table>
  <?php else: ?>
    <p>No meals scheduled for this plan.</p>
  <?php endif; ?>

  <!-- Update Modal -->
  <div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form id="updateScheduleForm" method="POST">
          <input type="hidden" name="form_action" value="update_schedule">
          <div class="modal-header">
            <h5 class="modal-title">Update Schedule</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <?php wp_nonce_field('update_schedule_action', 'update_schedule_nonce'); ?>
            <input type="hidden" name="order_id" id="modal-order-id">
            <input type="hidden" name="original_date" id="original_date_selected">
            <div class="d-flex justify-content-between gap-2">
              <div class="mb-3 w-100">
                <label for="modal-date" class="form-label">Date</label>
                <input type="date" class="form-control" name="new-date" id="modal-date" required>
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
                <input type="hidden" name="original_meal_type" id="original_meal_type">
                <select class="form-select" name="meal_type" id="modal-meal-type" required>
                  <option value="breakfast">Breakfast</option>
                  <option value="lunch">Lunch</option>
                  <option value="dinner">Dinner</option>
                </select>
              </div>
              <div class="mb-3 w-100">
                <label for="modal-delivery" class="form-label">Delivery Time</label>
                <input type="hidden" name="original_delivery" id="original_delivery">
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

  <!-- Cancel Modal -->
  <div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form id="cancelMealForm" method="POST">
          <input type="hidden" name="form_action" value="">
          <div class="modal-header">
            <div class="d-flex flex-column">
              <h5 class="modal-title" id="cancelModalLabel">Cancel Meal </h5>
              <p id="cancel-meal-date" class="mb-0" style="color: #666;"></p>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <?php wp_nonce_field('cancel_or_reschedule_action', 'cancel_reschedule_nonce'); ?>
            <input type="hidden" name="meal_id" id="modal-meal-id">
            <input type="hidden" name="meal_plan_id" id="modal-meal-plan-id">
            <input type="hidden" name="serve_date" id="modal-serve-date">
            <p id="cancel-modal-text">Are you sure you want to cancel the selected meal?</p>

            <div class="mb-3">
              <label class="form-label">Cancel Option</label>
              <div>
                <div class="form-check">
                  <input class="form-check-input cancel-option" type="radio" name="cancel_option" id="cancel-full-day" value="full_day" checked>
                  <label class="form-check-label" for="cancel-full-day">Cancel entire day</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input cancel-option" type="radio" name="cancel_option" id="cancel-meal-type" value="reschedule_meal_type">
                  <label class="form-check-label" for="cancel-meal-type" id="selected-meal-type">
                  </label>
                </div>
                <div class="form-check">
                  <input class="form-check-input cancel-option" type="radio" name="cancel_option" id="reschedule-full-day" value="reschedule_entire_day" checked>
                  <label class="form-check-label" for="reschedule-full-day">Reschedule entire day</label>
                </div>
              </div>
            </div>

            <div id="reschedule-section" class="d-none">
              <div class="d-flex justify-content-between gap-2">
                <div class="mb-3">
                  <label for="reschedule-date" class="form-label">Reschedule to</label>
                  <input type="date" class="form-control" name="reschedule_date" id="reschedule-date">
                  <div class="invalid-feedback">Date cannot be in the past.</div>
                </div>

                <div class="mb-3 w-100">
                  <label for="cancel-modal-weekday" class="form-label">Weekday</label>
                  <input type="text" class="form-control" name="new_weekday" id="cancel-modal-weekday" readonly>
                </div>
              </div>

              <div class="d-flex justify-content-between gap-2">
                <div class="mb-3 w-100">
                  <label for="cancel-modal-meal-type" class="form-label">Meal Type</label>
                  <input type="hidden" name="cancel_original_meal_type" id="cancel_original_meal_type">
                  <select class="form-select" name="new_meal_type" id="cancel-modal-meal-type" required>
                    <option value="breakfast">Breakfast</option>
                    <option value="lunch">Lunch</option>
                    <option value="dinner">Dinner</option>
                  </select>
                </div>
                <div class="mb-3 w-100">
                  <label for="cancel-modal-delivery" class="form-label">Delivery Time</label>
                  <select class="form-select" name="new_delivery" id="cancel-modal-delivery" required></select>
                </div>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-danger" id="just-cancel">Yes, Cancel</button>
            <button type="submit" class="btn btn-success d-none" id="confirm-reschedule">Confirm Reschedule</button>
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

      function updateDeliveryOptions(selectId, mealType, selectedDelivery = "") {
        const deliverySelect = $(selectId);
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

      function validateDate(selectedDateStr, minDateStr, weekdayOutputSelector, inputElement) {
        const minDate = new Date(minDateStr);
        minDate.setHours(0, 0, 0, 0);

        const selectedDate = new Date(selectedDateStr);
        selectedDate.setHours(0, 0, 0, 0);

        if (selectedDate < minDate) {
          inputElement.setCustomValidity("Date cannot be in the past");
          $(inputElement).addClass("is-invalid");
        } else {
          inputElement.setCustomValidity("");
          $(inputElement).removeClass("is-invalid");
          if (weekdayOutputSelector) {
            const weekdayName = selectedDate.toLocaleDateString("en-US", {
              weekday: "long"
            });
            $(weekdayOutputSelector).val(weekdayName);
          }
        }
      }

      function updateModalTextAndSections() {
        const selectedOption = $('input[name="cancel_option"]:checked').val();
        const serveDateText = $("#meal-date").text() || 'selected date';

        if (selectedOption === "reschedule_meal_type") {
          $("#cancelModalLabel").text('Reschedule Meal');
          $("#cancel-modal-text").text("You are rescheduling the selected meal.");
          $("#reschedule-section").removeClass("d-none");
          $("#confirm-reschedule").removeClass("d-none");
          $("#just-cancel").addClass("d-none");
          $("#show-reschedule").addClass("d-none");
        } else {
          $("#cancelModalLabel").text('Cancel Meal');
          $("#cancel-modal-text").text("Are you sure you want to cancel the selected meal?");
          $("#reschedule-section").addClass("d-none");
          $("#confirm-reschedule").addClass("d-none");
          $("#just-cancel").removeClass("d-none");
          $("#show-reschedule").removeClass("d-none");
        }
      }

      $(".open-add-modal").on("click", function() {
        const date = $(this).data("date");
        const weekday = $(this).data("weekday");
        const mealType = $(this).data("meal_type").toLowerCase();
        const delivery = $(this).data("delivery");
        const orderID = $(this).data("order-id");

        $("#modal-date").val(date);
        $("#original_date_selected").val(date);
        $("#modal-weekday").val(weekday);
        $("#modal-meal-type").val(mealType);
        $("#original_meal_type").val(mealType);
        $("#modal-order-id").val(orderID);

        $("#original_delivery").val(delivery);

        updateDeliveryOptions("#modal-delivery", mealType, delivery);
      });

      $(".open-cancel-modal").on("click", function() {
        const mealID = $(this).data("meal-id");
        const serveDate = $(this).data("serve-date");
        const mealPlanId = $(this).data("meal-plan-id");
        const mealType = $(this).data("meal-type");

        $("#modal-meal-id").val(mealID);
        $("#modal-meal-plan-id").val(mealPlanId);
        $("#modal-serve-date").val(serveDate);
        $("#reschedule-date").val(serveDate);
        $("#cancel_original_meal_type").val(mealType);
        $("#meal-date").text(serveDate);
        $("#cancel-meal-date").text(serveDate);
        $("#selected-meal-type").text(`Cancel Only ${mealType}`);

        $("#cancel-full-day").prop("checked", true);

        updateModalTextAndSections();
      });

      $("#just-cancel").on("click", function() {
        const selectedOption = $('input[name="cancel_option"]:checked').val();

        if (selectedOption === "full_day") {
          $("#cancelMealForm").append('<input type="hidden" name="action" value="cancel">');
          $('#cancelMealForm input[name="form_action"]').val('cancel_schedule');
          $("#cancelMealForm").submit();
        } else {
          $("#reschedule-section").removeClass("d-none");
          $("#confirm-reschedule").removeClass("d-none");
          $("#just-cancel").addClass("d-none");
          $("#show-reschedule").addClass("d-none");
        }
      });

      $('input[name="cancel_option"]').on('change', function() {
        updateModalTextAndSections();
      });

      $("#show-reschedule").on("click", function() {
        $("#reschedule-section").removeClass("d-none");
        $("#confirm-reschedule").removeClass("d-none");
        $("#just-cancel").addClass("d-none");
        $("#show-reschedule").addClass("d-none");
      });

      $("#confirm-reschedule").on("click", function() {
        const rescheduleDate = $("#reschedule-date").val();
        if (!rescheduleDate) {
          $("#reschedule-date").addClass("is-invalid");
          return;
        }
        $("#reschedule-date").removeClass("is-invalid");

        $("#cancelMealForm").append('<input type="hidden" name="action" value="reschedule">');
        $('#cancelMealForm input[name="form_action"]').val('reschedule_schedule');
        $("#cancelMealForm").submit();
      });

      $("#modal-meal-type").on("change", function() {
        updateDeliveryOptions("#modal-delivery", $(this).val());
      });

      $("#cancel-modal-meal-type").on("change", function() {
        updateDeliveryOptions("#cancel-modal-delivery", $(this).val());
      });

      $("#modal-date").on("change", function() {
        const originalDateSelected = $("#original_date_selected").val();
        validateDate($(this).val(), originalDateSelected, "#modal-weekday", this);
      });

      $("#reschedule-date").on('change', function() {
        const cancelMealDate = $("#cancel-meal-date").text().trim();
        validateDate($(this).val(), cancelMealDate, "#cancel-modal-weekday", this);
      });
    });
  </script>

<?php
}
?>