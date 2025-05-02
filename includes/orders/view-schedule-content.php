<?php
function wdb_schedule_endpoint_content()
{
  global $wpdb;

  $meal_plan_table = $wpdb->prefix . 'wdb_meal_plans';
  $schedule_table = $wpdb->prefix . 'wdb_meal_plan_schedules';

  $order_id = get_query_var('view-schedule');

  if (!is_numeric($order_id)) {
    echo "<p>Invalid schedule ID.</p>";
    return;
  }

  $plan = $wpdb->get_row(
    $wpdb->prepare("SELECT * FROM $meal_plan_table WHERE order_id = %d", $order_id),
    ARRAY_A
  );

  if (!$plan) {
    echo "<p>Meal plan not found.</p>";
    return;
  }

  if ($plan['user_id'] != get_current_user_id()) {
    echo "<p>You are not allowed to view this meal plan.</p>";
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
                  $part = trim($part);
                  if (stripos($part, 'Meals:') === 0) {
                    echo '<div><strong>' . esc_html($part) . '</strong></div>';
                  } elseif (stripos($part, 'Ingredients:') === 0) {
                    echo '<div>' . esc_html($part) . '</div>';
                  } else {
                    echo '<div>' . esc_html($part) . '</div>';
                  }
                }
                echo '</td>';
              endif;

              echo '<td>' . esc_html($meal_types[$i]) . '</td>';
              echo '<td>' . (!empty($delivery_times[$i]) ? esc_html($delivery_times[$i]) : '') . '</td>';
              echo '<td>
              <button type="button" 
                      class="btn btn-outline-info btn-sm open-add-modal"
                      id="update_modal"
                      data-bs-toggle="modal" 
                      data-bs-target="#addModal"
                      data-date="' . date('d M Y', strtotime($meal['serve_date'])) . '"
                      data-weekday="' . esc_attr($meal['weekday']) . '"
                      data-meal_type="' . esc_attr($meal_types[$i]) . '"
                      data-delivery="' . (!empty($delivery_times[$i]) ? esc_attr($delivery_times[$i]) : '') . '"
              >
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

    <div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Update Schedule</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form id="updateForm">
              <div class="row mb-3">
                <div class="col-md-6">
                  <label for="modal-date" class="form-label">Date</label>
                  <input type="date" class="form-control" id="modal-date" name="date" required>
                  <div class="invalid-feedback">Date cannot be in the past.</div>
                </div>

                <div class="col-md-6">
                  <label for="modal-weekday" class="form-label">Weekday</label>
                  <input type="text" class="form-control" id="modal-weekday" name="weekday" readonly>
                </div>
              </div>

              <div class="row mb-3">
                <div class="col-md-6">
                  <label for="modal-meal-type" class="form-label">Meal Type</label>
                  <select class="form-select" id="modal-meal-type" name="meal_type" required>
                    <option value="breakfast">Breakfast</option>
                    <option value="lunch">Lunch</option>
                    <option value="dinner">Dinner</option>
                  </select>
                </div>

                <div class="col-md-6">
                  <label for="modal-delivery" class="form-label">Delivery Time</label>
                  <select class="form-select" id="modal-delivery" name="delivery" required>
                  </select>
                </div>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" form="updateForm" class="btn btn-primary">Save Changes</button>
          </div>
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
          const dateText = $(this).data("date");
          const weekday = $(this).data("weekday");
          const mealType = $(this).data("meal_type").toLowerCase();
          const delivery = $(this).data("delivery");

          const parsedDate = new Date(dateText);
          const formattedDate = parsedDate.toISOString().split("T")[0];

          $("#modal-date").val(formattedDate);
          $("#modal-weekday").val(weekday);
          $("#modal-meal-type").val(mealType);

          updateDeliveryOptions(mealType, delivery);
        });

        $("#modal-meal-type").on("change", function() {
          const selectedMeal = $(this).val();
          updateDeliveryOptions(selectedMeal);
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

        $("#updateForm").on("submit", function(event) {
          event.preventDefault();

          const date = $("#modal-date").val();
          const weekday = $("#modal-weekday").val();
          const mealType = $("#modal-meal-type").val();
          const deliveryTime = $("#modal-delivery").val();

          if (new Date(date) < new Date()) {
            alert("Please select a valid future date.");
            return;
          }

          if (!mealType || !deliveryTime) {
            alert("Please select meal type and delivery time.");
            return;
          }

          $.ajax({
            url: customPlugin.ajax_url,
            method: "POST",
            data: {
              action: "save_update_schedule",
              date: date,
              weekday: weekday,
              meal_type: mealType,
              delivery_time: deliveryTime,
              nonce: customPlugin.nonce,
            },
            success: function(response) {
              if (response.success && response.data) {
                alert(response.data.message);
                $("#addModal").modal("hide");
                updateTableRow(response.data); // pass only the useful data
              } else {
                alert(response.data.message);
              }
            },
            error: function(response) {
              console.error("AJAX error:", response);
              alert("An error occurred. Please try again.");
            },
          });
        });

        function updateTableRow(data) {
          const updatedRow = $("tr[data-id='" + data.id + "']");
          updatedRow.find(".meal-type").text(data.meal_type);
          updatedRow.find(".delivery-time").text(data.delivery_time);
        }
      });
    </script>

  <?php else: ?>
    <p>No meal data available.</p>
<?php endif;
}
