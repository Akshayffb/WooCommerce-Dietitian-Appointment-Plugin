if (!$) {
  $ = jQuery;
}

// $(function () {
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
//         date: date,
//         weekday: weekday,
//         meal_type: mealType,
//         delivery_time: deliveryTime,
//         nonce: customPlugin.nonce,
//       },
//       success: function (response) {
//         if (response.success) {
//           alert('yes');
//           alert(response.data.message);
//           $("#addModal").modal("hide");
//           // location.reload();
//         } else {
//           alert(response.data.message);
//         }
//         updateTableRow(response);
//       },
//       error: function (response) {
//         alert("An error occurred. Please try again.");
//       },
//     });
//   });
// });

// function updateTableRow(response) {
//   const updatedRow = $("tr[data-id='" + response.id + "']");
//   updatedRow.find(".meal-type").text(response.meal_type);
//   updatedRow.find(".delivery-time").text(response.delivery_time);
// }
