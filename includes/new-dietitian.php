<?php
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options') && !current_user_can('dietitian')) {
  wp_die(__('You do not have permission to access this page.', 'textdomain'));
}

global $wpdb;
$table_name = $wpdb->prefix . 'wdb_dietitians';

$edit_id = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : 0;
$is_edit = ($edit_id > 0);
$dietitian = null;

// Fetch dietitian details if editing
if ($is_edit) {
  $dietitian = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $edit_id));
  if (!$dietitian) {
    wp_die(__('Dietitian not found.', 'textdomain'));
  }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_dietitian'])) {
  if (!check_admin_referer('save_dietitian_action', 'save_dietitian_nonce')) {
    wp_die(__('Security check failed.', 'textdomain'));
  }

  $name = sanitize_text_field($_POST['dietitian_name']);
  $phone = sanitize_text_field($_POST['dietitian_phone']);
  $specialization = sanitize_text_field($_POST['dietitian_specialization']);
  $experience = intval($_POST['dietitian_experience']);
  $allow_login = isset($_POST['allow_login']) ? 1 : 0;

  if ($is_edit) {
    $result = $wpdb->update(
      $table_name,
      [
        'name'           => $name,
        'phone'          => $phone,
        'specialization' => $specialization,
        'experience'     => $experience,
        'allow_login'    => $allow_login
      ],
      ['id' => $edit_id],
      ['%s', '%s', '%s', '%d', '%d'],
      ['%d']
    );

    if ($result === false) {
      add_settings_error('wdb_messages', 'dietitian_update_error', __('Error updating dietitian!', 'textdomain'), 'error');
    } elseif ($result === 0) {
      add_settings_error('wdb_messages', 'dietitian_no_change', __('No changes made to the dietitian.', 'textdomain'), 'updated');
    } else {
      add_settings_error('wdb_messages', 'dietitian_updated', __('Dietitian updated successfully!', 'textdomain'), 'updated');
      $dietitian = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $edit_id));
    }
  } else {
    // Insert new dietitian
    $email = sanitize_email($_POST['dietitian_email']);

    if (!is_email($email)) {
      add_settings_error('wdb_messages', 'invalid_email', __('Invalid email address!', 'textdomain'), 'error');
    } elseif ($wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE email = %s", $email))) {
      add_settings_error('wdb_messages', 'email_exists', __('Error: Email already exists!', 'textdomain'), 'error');
    } else {
      $user_id = null;

      // Create WordPress user only if login is allowed
      if ($allow_login) {
        $existing_user = get_user_by('email', $email);
        if (!$existing_user) {
          $user_id = wp_create_user($email, wp_generate_password(12, true), $email);
          if (!is_wp_error($user_id)) {
            wp_update_user(['ID' => $user_id, 'display_name' => $name]);
            $user = new WP_User($user_id);
            $user->set_role('dietitian');

            send_welcome_email($name, $email, $user_id);
          }
        } else {
          $user_id = $existing_user->ID;
        }
      }

      $result = $wpdb->insert(
        $table_name,
        [
          'user_id'        => $user_id,
          'name'           => $name,
          'email'          => $email,
          'phone'          => $phone,
          'specialization' => $specialization,
          'experience'     => $experience,
          'allow_login'    => $allow_login
        ],
        ['%d', '%s', '%s', '%s', '%s', '%d', '%d']
      );

      if ($result !== false) {
        $message = __('The dietitian has been added successfully.', 'textdomain');

        if ($allow_login && $user_id) {
          $message .= ' ' . __('An invitation email has been sent to the new dietitian.', 'textdomain');
        }

        add_settings_error('wdb_messages', 'success', $message, 'updated');
      } else {
        add_settings_error('wdb_messages', 'dietitian_add_error', __('Error adding dietitian!', 'textdomain'), 'error');
      }
    }
  }
}

settings_errors('wdb_messages');

/**
 * Sends a welcome email with an HTML template and password reset link.
 */
function send_welcome_email($name, $email, $user_id)
{
  $user = get_user_by('ID', $user_id);
  $user_name = $user ? $user->display_name : 'User';
  $site_name = get_bloginfo('name');
  $from_email = get_option('admin_email');

  $subject = __("🎉 Welcome, $user_name! Get Started Now", 'textdomain');

  $reset_key = get_password_reset_key($user);
  $password_setup_link = network_site_url("wp-login.php?action=rp&key=$reset_key&login=" . rawurlencode($user->user_login));

  $message = '
    <html>
    <head>
      <title>Welcome to ' . esc_html($site_name) . '</title>
      <style>
          body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
          .email-container { max-width: 600px; background: #ffffff; margin: 20px auto; padding: 30px; border-radius: 8px; box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1); }
          .content { text-align: left; }
          .content h2 { color: #333; font-size: 18px; margin-bottom: 15px; }
          .content p { color: #555; font-size: 16px; margin-bottom: 15px; }
          .btn { background: #0073aa; color: white; padding: 12px 18px; text-decoration: none; display: inline-block; border-radius: 5px; font-size: 16px; font-weight: bold; }
          .footer { margin-top: 20px; font-size: 14px; color: #777; }
      </style>
    </head>
    <body>
        <div class="email-container">
            <div class="content">
                <h2>Hi ' . esc_html($name) . ',</h2>
                <p>Welcome! We’re excited to have you on board.</p>
                <p>To start using your account, set up your password by clicking the button below:</p>
                <p><a href="' . esc_url($password_setup_link) . '" class="btn">Set Your Password</a></p>
                <p>If you have any questions, feel free to reach out.</p>
                <p>Best regards,<br> The ' . esc_html($site_name) . ' Team</p>
            </div>
            <div class="footer">
                &copy; ' . date("Y") . ' ' . esc_html($site_name) . '. All rights reserved.
            </div>
        </div>
    </body>
    </html>
    ';

  $headers = [
    'Content-Type: text/html; charset=UTF-8',
    'From: ' . esc_html($site_name) . ' <' . esc_attr($from_email) . '>'
  ];

  wp_mail($email, $subject, $message, $headers);
}

?>

<div class="wrap">
  <h1 class="wp-heading-inline"><?php echo $is_edit ? 'Edit Dietitian' : 'Add New Dietitian'; ?></h1>
  <a href="<?php echo esc_url(admin_url('admin.php?page=wdb-all-dietitians')); ?>" class="page-title-action">Back to List</a>

  <form method="post">
    <?php wp_nonce_field('save_dietitian_action', 'save_dietitian_nonce'); ?>

    <table class="form-table">
      <tr>
        <th><label for="dietitian_name">Name</label></th>
        <td><input type="text" name="dietitian_name" id="dietitian_name" value="<?php echo esc_attr($dietitian->name ?? ''); ?>" class="regular-text" required></td>
      </tr>
      <tr>
        <th><label for="dietitian_email">Email</label></th>
        <td><input type="email" name="dietitian_email" id="dietitian_email" value="<?php echo esc_attr($dietitian->email ?? ''); ?>" class="regular-text" required></td>
      </tr>
      <tr>
        <th><label for="dietitian_phone">Phone</label></th>
        <td>
          <input type="tel" name="dietitian_phone" id="dietitian_phone"
            value="<?php echo esc_attr($dietitian->phone ?? ''); ?>"
            class="regular-text"
            pattern="[0-9]{11}"
            placeholder="Enter 11-digit phone number"
            maxlength="11"
            required>
        </td>
      </tr>
      <tr>
        <th><label for="dietitian_specialization">Specialization</label></th>
        <td><input type="text" name="dietitian_specialization" id="dietitian_specialization" value="<?php echo esc_attr($dietitian->specialization ?? ''); ?>" class="regular-text"></td>
      </tr>
      <tr>
        <th><label for="dietitian_experience">Experience (Years)</label></th>
        <td><input type="number" name="dietitian_experience" id="dietitian_experience" value="<?php echo esc_attr($dietitian->experience ?? ''); ?>" class="regular-text"></td>
      </tr>
      <tr>
        <th><label for="allow_login">Allow Login</label></th>
        <td>
          <input type="checkbox" name="allow_login" id="allow_login" value="1" <?php checked($dietitian->allow_login ?? 0, 1); ?>>
          <label for="allow_login">Enable login for this dietitian</label>
        </td>
      </tr>
    </table>

    <p class="submit">
      <button type="submit" name="save_dietitian" class="button button-primary"><?php echo $is_edit ? 'Update Dietitian' : 'Add Dietitian'; ?></button>
    </p>
  </form>
</div>