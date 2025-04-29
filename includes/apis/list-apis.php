<?php
// Display list of APIs
function wdb_list_apis_page()
{
  if (!current_user_can('manage_options')) {
    return;
  }

  echo '<div class="wrap">';
  echo '<h1 class="wp-heading-inline">Manage APIs</h1>';

  // Check if there's an action to delete
  if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['api_id'])) {
    $api_id = intval($_GET['api_id']);
    wdb_delete_api($api_id);  // Ensure you have the delete function implemented

    echo '<div class="notice notice-success is-dismissible"><p>API deleted successfully!</p></div>';
  }

  $apis = wdb_get_all_apis(); // Get all APIs

  echo '<a href="' . admin_url('admin.php?page=wdb_add_api') . '" class="page-title-action">Add New API</a><br><br>';

  // If there are APIs, display them in a table
  if (!empty($apis)) {
    echo '<table class="wp-list-table widefat fixed striped table-view-list posts">';
    echo '<thead><tr>';
    echo '<th class="manage-column column-id">ID</th>';
    echo '<th class="manage-column column-name">Name</th>';
    echo '<th class="manage-column column-url">URL</th>';
    echo '<th class="manage-column column-api-key">API Key</th>';
    echo '<th class="manage-column column-actions">Actions</th>';
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
      echo '<a href="' . admin_url('admin.php?page=wdb_list_apis&action=delete&api_id=' . $api->id) . '" class="button button-delete" onclick="return confirm(\'Are you sure you want to delete this API?\')">Delete</a>';
      echo '</td>';
      echo '</tr>';
    }

    echo '</tbody></table>';
  } else {
    echo '<p>No APIs found. <a href="' . admin_url('admin.php?page=wdb_add_api') . '">Add a new one</a>.</p>';
  }

  echo '</div>';
}

// Function to get all APIs from the database
function wdb_get_all_apis()
{
  global $wpdb;
  $table_name = $wpdb->prefix . 'wdb_apis'; // Ensure the table name is correct

  // Retrieve all APIs from the database
  $query = "SELECT * FROM {$table_name} WHERE is_active = 1"; // Assuming you only want active APIs
  $results = $wpdb->get_results($query);

  return $results;
}
