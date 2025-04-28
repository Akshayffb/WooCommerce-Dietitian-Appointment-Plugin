<?php
// Display list of APIs
function wdb_list_apis_page()
{
  if (!current_user_can('manage_options')) {
    return;
  }

  echo '<div class="wrap">';
  echo '<h1>Manage APIs</h1>';

  if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['api_id'])) {
    $api_id = intval($_GET['api_id']);
    wdb_delete_api($api_id);  // Assume you have the function to delete an API

    echo '<div class="updated"><p>API deleted successfully!</p></div>';
  }

  $apis = wdb_get_all_apis(); // Assume you have the function to fetch APIs

  echo '<a href="' . admin_url('admin.php?page=wdb_add_api') . '" class="button button-primary">Add New API</a><br><br>';

  if (!empty($apis)) {
    echo '<table class="widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th>ID</th>';
    echo '<th>Name</th>';
    echo '<th>URL</th>';
    echo '<th>API Key</th>';
    echo '<th>Actions</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    foreach ($apis as $api) {
      echo '<tr>';
      echo '<td>' . esc_html($api->id) . '</td>';
      echo '<td>' . esc_html($api->api_name) . '</td>';
      echo '<td>' . esc_html($api->api_url) . '</td>';
      echo '<td>' . esc_html($api->api_key) . '</td>';
      echo '<td>';
      echo '<a href="' . admin_url('admin.php?page=wdb_edit_api&api_id=' . $api->id) . '" class="button">Edit</a> ';
      echo '<a href="' . admin_url('admin.php?page=wdb_list_apis&action=delete&api_id=' . $api->id) . '" class="button delete-button" onclick="return confirm(\'Are you sure you want to delete this API?\')">Delete</a>';
      echo '</td>';
      echo '</tr>';
    }

    echo '</tbody></table>';
  } else {
    echo '<p>No APIs found. <a href="' . admin_url('admin.php?page=wdb_add_api') . '">Add a new one</a>.</p>';
  }

  echo '</div>';
}
