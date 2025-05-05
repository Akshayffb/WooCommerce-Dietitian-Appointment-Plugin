if (typeof jQuery !== "undefined" && typeof $ === "undefined") {
  $ = jQuery;
}

// $(document).ready(function ($) {
//   $("#updateForm").on("submit", function (event) {
//     event.preventDefault();

//     const date = $("#modal-date").val();
//     const weekday = $("#modal-weekday").val();
//     const mealType = $("#modal-meal-type").val();
//     const deliveryTime = $("#modal-delivery").val();

//     if (new Date(date) < new Date()) {
//       alert("Please select a valid future date.");
//       return;
//     }

//     if (!mealType || !deliveryTime) {
//       alert("Please select meal type and delivery time.");
//       return;
//     }

//     $.ajax({
//       url: customPlugin.ajax_url,
//       method: "POST",
//       data: {
//         action: "save_update_schedule",
//         nonce: customPlugin.nonce,
//         date: date,
//         weekday: weekday,
//         meal_type: mealType,
//         delivery_time: deliveryTime,
//       },
//       success: function (response) {
//         if (response.success && response.data) {
//           alert(response.data.message);
//           $("#addModal").modal("hide");
//           updateTableRow(response.data);
//         } else {
//           alert(response.data.message || "Failed to update.");
//         }
//       },
//       error: function (response) {
//         console.error("AJAX error:", response);
//         alert("An error occurred. Please try again.");
//       },
//     });
//   });

//   function updateTableRow(data) {
//     const updatedRow = $("tr[data-id='" + data.id + "']");
//     updatedRow.find(".meal-type").text(data.meal_type);
//     updatedRow.find(".delivery-time").text(data.delivery_time);
//   }
// });

// document
//   .getElementById("updateScheduleForm")
//   .addEventListener("submit", function (e) {
//     e.preventDefault();

//     const formData = new FormData(this);
//     formData.append("action", "update_schedule_entry");

//     console.log("Sending AJAX request...");

//     fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
//       method: "POST",
//       body: formData,
//     })
//       .then((res) => res.json())
//       .then((data) => {
//         console.log("AJAX response:", data);
//         alert(data.success ? "Updated successfully!" : "Error: " + data.data);
//       })
//       .catch((error) => {
//         console.error("AJAX error:", error);
//         alert("AJAX request failed.");
//       });
//   });
