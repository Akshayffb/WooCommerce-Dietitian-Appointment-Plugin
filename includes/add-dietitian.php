<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
  wp_die(__('You do not have permission to access this page.', 'textdomain'));
}

global $wpdb;
$table_name = $wpdb->prefix . 'wdb_dietitians';
$message = "";

// DELETE Dietitian
if (!empty($_POST['delete_dietitian']) && check_admin_referer('delete_dietitian_action', 'delete_dietitian_nonce')) {
  $id = intval($_POST['delete_dietitian']);
  $wpdb->delete($table_name, ['id' => $id]);
  $message = '<div class="alert alert-danger">Dietitian deleted successfully!</div>';
}

// Handle Add/Edit Dietitian
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_dietitian'])) {
  // Verify nonce for security
  if (!check_admin_referer('save_dietitian_action', 'save_dietitian_nonce')) {
    wp_die(__('Security check failed.', 'textdomain'));
  }

  // Sanitize input data
  $id = !empty($_POST['dietitian_id']) ? intval($_POST['dietitian_id']) : 0;
  $name = sanitize_text_field($_POST['dietitian_name']);
  $email = sanitize_email($_POST['dietitian_email']);
  $phone = sanitize_text_field($_POST['dietitian_phone']);
  $specialization = sanitize_text_field($_POST['dietitian_specialty']);
  $experience = intval($_POST['dietitian_experience']);
  $allow_login = isset($_POST['allow_login']) ? 1 : 0;

  if ($id) {
    // Update existing dietitian
    $result = $wpdb->update(
      $table_name,
      ['name' => $name, 'email' => $email, 'phone' => $phone, 'specialization' => $specialization, 'experience' => $experience, 'allow_login' => $allow_login],
      ['id' => $id],
      ['%s', '%s', '%s', '%s', '%d', '%d'],
      ['%d']
    );

    if ($allow_login) {
      $user_id = email_exists($email);
      if (!$user_id) {
        $random_password = wp_generate_password();
        $user_id = wp_create_user($email, $random_password, $email);

        if (!is_wp_error($user_id)) {
          wp_update_user(['ID' => $user_id, 'display_name' => $name]);
          wp_send_new_user_notifications($user_id);
        } else {
          error_log('User creation failed: ' . $user_id->get_error_message());
        }
      }
    }

    $message = $result !== false ? '<div class="alert alert-success">Dietitian updated successfully!</div>' : '<div class="alert alert-danger">Error updating dietitian!</div>';
  } else {
    // Insert new dietitian
    $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE email = %s", $email));
    if ($existing) {
      $message = '<div class="alert alert-danger">Error: Email already exists!</div>';
    } else {
      $result = $wpdb->insert(
        $table_name,
        ['name' => $name, 'email' => $email, 'phone' => $phone, 'specialization' => $specialization, 'experience' => $experience, 'allow_login' => $allow_login],
        ['%s', '%s', '%s', '%s', '%d', '%d']
      );

      if ($result !== false) {
        $dietitian_id = $wpdb->insert_id;

        if ($allow_login) {
          $user_id = email_exists($email);
          if (!$user_id) {
            $user_id = wp_create_user($email, wp_generate_password(), $email);

            if (!is_wp_error($user_id)) {
              wp_update_user(['ID' => $user_id, 'display_name' => $name, 'role' => 'dietitian']);

              // Generate password reset key
              $reset_key = get_password_reset_key(get_user_by('ID', $user_id));

              if (!is_wp_error($reset_key)) {
                $reset_url = network_site_url("wp-login.php?action=rp&key=$reset_key&login=" . rawurlencode($email));

                // Email subject
                $subject = "Welcome to Our Platform, $name!";

                // HTML email message
                $message = "
                    <html>
                    <head>
                        <title>Welcome to Our Platform</title>
                    </head>
                    <body>
                        <p>Hello <strong>$name</strong>,</p>
                        <p>Your account has been created. Please set your password by clicking the link below:</p>
                        <p><a href='$reset_url' style='background-color: #0073aa; color: #ffffff; padding: 10px 15px; text-decoration: none; display: inline-block; border-radius: 5px;'>Set Your Password</a></p>
                        <p>If the button above doesn't work, you can use the following link:</p>
                        <p><a href='$reset_url'>$reset_url</a></p>
                        <p>Best regards,<br>Your Team</p>
                    </body>
                    </html>
                ";

                // Email headers
                $headers = array('Content-Type: text/html; charset=UTF-8');

                // Send email
                wp_mail($email, $subject, $message, $headers);
              }
            } else {
              error_log('User creation failed: ' . $user_id->get_error_message());
            }
          } else {
            wp_update_user(['ID' => $user_id, 'role' => 'dietitian']);
          }
        } else {
          // If login is disabled, remove their ability to log in
          $user_id = email_exists($email);
          if ($user_id) {
            wp_update_user(['ID' => $user_id, 'role' => '']);
          }
        }

        $message = '<div class="alert alert-success">Dietitian added successfully! A password reset link has been sent.</div>';
      } else {
        error_log('DB Error: ' . $wpdb->last_error);
        $message = '<div class="alert alert-danger">Error adding dietitian! ' . esc_html($wpdb->last_error) . '</div>';
      }
    }
  }
}

// Fetch all dietitians
$dietitians = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");
?>

<div class="wrap">
  <h1 class="mb-3">Manage Dietitians</h1>
  <?php echo $message; ?>

  <button class="button button-primary" data-bs-toggle="modal" data-bs-target="#dietitianModal">Add Dietitian</button>

  <table class="wp-list-table widefat fixed striped mt-3">
    <thead>
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Email</th>
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
          <td><?php echo esc_html($dietitian->specialization); ?></td>
          <td><?php echo esc_html($dietitian->experience); ?> years</td>
          <td>
            <button class="button edit-btn"
              data-id="<?php echo esc_attr($dietitian->id); ?>"
              data-name="<?php echo esc_attr($dietitian->name); ?>"
              data-email="<?php echo esc_attr($dietitian->email); ?>"
              data-phone="<?php echo esc_attr($dietitian->phone); ?>"
              data-specialization="<?php echo esc_attr($dietitian->specialization); ?>"
              data-experience="<?php echo esc_attr($dietitian->experience); ?>"
              data-allow_login="<?php echo esc_attr($dietitian->allow_login); ?>"
              data-bs-toggle="modal"
              data-bs-target="#dietitianModal">
              Edit
            </button>
            <form method="post" style="display:inline;">
              <?php wp_nonce_field('delete_dietitian_action', 'delete_dietitian_nonce'); ?>
              <input type="hidden" name="delete_dietitian" value="<?php echo esc_attr($dietitian->id); ?>">
              <button type="submit" class="button button-danger" onclick="return confirm('Are you sure?')">Delete</button>
            </form>
          </td>
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

<!-- Modal for Adding/Editing -->
<div class="modal fade" id="dietitianModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add/Edit Dietitian</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <?php wp_nonce_field('save_dietitian_action', 'save_dietitian_nonce'); ?>
        <div class="modal-body">
          <input type="hidden" name="dietitian_id" id="dietitian_id">

          <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input type="text" name="dietitian_name" id="dietitian_name" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="dietitian_email" id="dietitian_email" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Phone</label>
            <input type="text" name="dietitian_phone" id="dietitian_phone" class="form-control">
          </div>

          <div class="mb-3">
            <label class="form-label">Specialization</label>
            <input type="text" name="dietitian_specialty" id="dietitian_specialty" class="form-control">
          </div>

          <div class="mb-3">
            <label class="form-label">Years of Experience</label>
            <input type="number" name="dietitian_experience" id="dietitian_experience" class="form-control">
          </div>

          <div class="form-check mb-3">
            <input type="checkbox" name="allow_login" id="allow_login" class="form-check-input">
            <label class="form-check-label" for="allow_login">Allow Dietitian to Login</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" name="save_dietitian" class="btn btn-success">Save Dietitian</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  document.addEventListener("DOMContentLoaded", function() {
    // Handle Edit Button Click
    document.querySelectorAll(".edit-btn").forEach(button => {
      button.addEventListener("click", function() {
        document.getElementById("dietitian_id").value = this.dataset.id;
        document.getElementById("dietitian_name").value = this.dataset.name;
        document.getElementById("dietitian_email").value = this.dataset.email;
        document.getElementById("dietitian_phone").value = this.dataset.phone;
        document.getElementById("dietitian_specialty").value = this.dataset.specialization;
        document.getElementById("dietitian_experience").value = this.dataset.experience;
        document.getElementById("allow_login").checked = this.dataset.allow_login == "1";

        // Change modal title to "Edit Dietitian"
        document.querySelector(".modal-title").textContent = "Edit Dietitian";
      });
    });

    // Handle Add Button Click (Clears the Form)
    document.querySelector("[data-bs-target='#dietitianModal']").addEventListener("click", function() {
      document.getElementById("dietitian_id").value = "";
      document.getElementById("dietitian_name").value = "";
      document.getElementById("dietitian_email").value = "";
      document.getElementById("dietitian_phone").value = "";
      document.getElementById("dietitian_specialty").value = "";
      document.getElementById("dietitian_experience").value = "";
      document.getElementById("allow_login").checked = false;

      // Change modal title to "Add Dietitian"
      document.querySelector(".modal-title").textContent = "Add Dietitian";
    });
  });
</script>