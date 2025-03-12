<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
  wp_die(__('You do not have permission to access this page.', 'textdomain'));
}

global $wpdb;
$table_name = $wpdb->prefix . 'wdb_dietitians';
$message = "";

if (!empty($_POST['delete_dietitian']) && check_admin_referer('delete_dietitian_action', 'delete_dietitian_nonce')) {
  $id = intval($_POST['delete_dietitian']);

  $dietitian = $wpdb->get_row($wpdb->prepare("SELECT email FROM $table_name WHERE id = %d", $id));

  if ($dietitian) {
    $email = $dietitian->email;

    $user = get_user_by('email', $email);

    if ($user) {
      require_once ABSPATH . 'wp-admin/includes/user.php';
      wp_delete_user($user->ID);
    }

    $wpdb->delete($table_name, ['id' => $id], ['%d']);

    add_settings_error('wdb_messages', 'dietitian_deleted', __('Dietitian user deleted successfully!', 'textdomain'), 'updated');
  } else {
    add_settings_error('wdb_messages', 'dietitian_not_found', __('Error: Dietitian not found!', 'textdomain'), 'error');
  }
}

// Fetch all dietitians
$dietitians = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id ASC");
?>

<div class="wrap">
  <h1 class="wp-heading-inline">All Dietitians</h1>
  <a href="<?php echo esc_url(admin_url('admin.php?page=wdb-add-dietitian')); ?>" class="page-title-action">Add New</a>

  <?php settings_errors('wdb_messages'); ?>

  <table class="wp-list-table widefat fixed striped mt-3" style="margin-top: 20px;">
    <thead>
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Specialization</th>
        <th>Experience</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($dietitians as $dietitian) : ?>
        <tr>
          <td><?php echo esc_html($dietitian->id); ?></td>
          <td><?php echo esc_html($dietitian->name); ?></td>
          <td><?php echo esc_html($dietitian->email); ?></td>
          <td><?php echo esc_html($dietitian->phone); ?></td>
          <td><?php echo esc_html($dietitian->specialization); ?></td>
          <td><?php echo esc_html($dietitian->experience); ?> years</td>
          <?php if (current_user_can('manage_options')) : ?>
            <td>
              <a href="<?php echo esc_url(admin_url('admin.php?page=wdb-add-dietitian&edit_id=' . $dietitian->id)); ?>" class="button">Edit</a>
              <form method="post" style="display:inline;">
                <?php wp_nonce_field('delete_dietitian_action', 'delete_dietitian_nonce'); ?>
                <input type="hidden" name="delete_dietitian" value="<?php echo esc_attr($dietitian->id); ?>">
                <button type="submit" class="button button-danger" onclick="return confirm('Are you sure you want to delete this dietitian?')">Delete</button>
              </form>
            </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($dietitians)) : ?>
        <tr>
          <td colspan="6" class="text-center">No dietitians found</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>