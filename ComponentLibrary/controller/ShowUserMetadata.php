<?php


class ShowUserMetadata
{

  public function __construct()
  {
    add_action('show_user_profile', array($this, 'show_custom_user_metadata'));
    add_action('edit_user_profile', array($this, 'show_custom_user_metadata'));
    add_action('personal_options_update', array($this, 'save_custom_user_metadata'));
    add_action('edit_user_profile_update', array($this, 'save_custom_user_metadata'));
  }

  private function get_meta_list($user_id)
  {
    $meta_list = array();
    $metadata = get_user_meta($user_id);
    $meta_list = array();
    foreach ($metadata as $name => $value) {
      if (str_starts_with($name, DROIP_COMPONENT_LIBRARY_APP_PREFIX))
        $meta_list[substr($name, strlen(DROIP_COMPONENT_LIBRARY_APP_PREFIX) + 1)] = $value[0];
    }
    return $meta_list;
  }

  private function transform_key($string)
  {
    $string = str_replace('_', ' ', $string);
    $string = ucwords($string);
    return $string;
  }

  public function show_custom_user_metadata($user)
  {
    $meta_list = $this->get_meta_list($user->ID);
    if (!count($meta_list)) return;
    $can_edit = current_user_can('edit_user', $user->ID);
?>
    <h3>Droip User Information</h3>
    <table class="form-table">
      <?php foreach ($meta_list as $meta_key => $meta_value) :
      ?>
        <tr>
          <th><label for="<?php echo esc_attr($meta_key); ?>"><?php echo esc_html($this->transform_key($meta_key)); ?></label></th>
          <td>
            <input
              <?php echo $can_edit ? '' : 'disabled' ?>
              type="text"
              name="custom_meta[<?php echo esc_attr($meta_key); ?>]"
              id="<?php echo esc_attr($meta_key); ?>"
              value="<?php echo esc_attr($meta_value); ?>"
              class="regular-text" />
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
<?php
  }


  public function save_custom_user_metadata($user_id)
  {

    $meta_list = $this->get_meta_list($user_id);

    if (!current_user_can('edit_user', $user_id)) {
      return false;
    }

    if (!isset($_POST['custom_meta']) || !is_array($_POST['custom_meta'])) {
      return false;
    }

    foreach ($meta_list as $meta_key => $meta_label) {
      if (isset($_POST['custom_meta'][$meta_key])) {
        if (strpos($meta_key, 'address') !== false || strlen($_POST['custom_meta'][$meta_key]) > 50) {
          $meta_value = sanitize_textarea_field($_POST['custom_meta'][$meta_key]);
        } else {
          $meta_value = sanitize_text_field($_POST['custom_meta'][$meta_key]);
        }
        update_user_meta($user_id, DROIP_COMPONENT_LIBRARY_APP_PREFIX . '_' . $meta_key, $meta_value);
      }
    }
  }
}

new ShowUserMetadata();
