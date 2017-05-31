<?php

/**
 * @author Paul Huisman
 */

namespace EasyWpmlCopy;

class Plugin {

  public function __construct() {
    add_action( 'admin_menu', array($this, 'admin_menu_page') );
    add_action( 'admin_init', array($this, 'include_custom_css') );
  }

  public function admin_menu_page() {
    add_menu_page( 'Easy WPML Copy', 'Easy WPML Copy', 'manage_options', 'easy-wpml-copy', array($this, 'admin_page') );
  }

  public function include_custom_css() {
    wp_register_style('easy_wml_copy_css', plugins_url('../css/style.css', __FILE__ ));
    wp_enqueue_style('easy_wml_copy_css');
  }

  public function admin_page() {
    if(isset($_POST['easy_wpml_copy_lang1']) && isset($_POST['easy_wpml_copy_lang2'])) {
      if(!isset($_POST['easy_wpml_copy_post_types']) || empty($_POST['easy_wpml_copy_post_types'])) {
        $this->print_message('Please select at least one post type.', 'error');
      }
      else {
        $remove_duplicate = (isset($_POST['remove_duplicate_tag'])  && $_POST['remove_duplicate_tag'] == 1 )? true : false;
        $newly_post_status = isset($_POST['easy_wpml_copy_post_status']) && in_array($_POST['easy_wpml_copy_post_status'], array('publish', 'draft')) ? $_POST['easy_wpml_copy_post_status'] : false;
        $this->copy_posts($_POST['easy_wpml_copy_lang1'], $_POST['easy_wpml_copy_lang2'], $_POST['easy_wpml_copy_post_types'], $remove_duplicate, $newly_post_status);
      }
    }
    global $sitepress;

    $available_languages = $sitepress->get_active_languages();
    $post_types = get_post_types(array('public' => true));
    ?>
    <h1><?php _e('Easy WPML Copy', 'easy-wpml-copy'); ?></h1>

    <h2><?php _e('Copy or duplicate WPML posts between different site languages', 'easy-wpml-copy'); ?></h2>
    <form action="" method="post" class="easy-wpml-copy-table">
      <table id="mc" class="form-table easy-wpml-copy-network-settings">
        <tbody class="primary">
          <tr>
            <th scope="row"><?php _e('Copy FROM language', 'easy-wpml-copy'); ?></th>
            <td>
              <select id="easy_wpml_copy_lang1" name="easy_wpml_copy_lang1" required>
                <option value="">- <?php _e('Select language', 'easy_wpml_copy'); ?> -</option>
                <?php foreach($available_languages as $lang_code => $lang): ?>
                  <option value="<?php echo $lang_code; ?>" <?php echo(isset($_POST['easy_wpml_copy_lang1']) && $_POST['easy_wpml_copy_lang1'] == $lang_code) ? 'selected' : ''; ?>>
                    <?php echo $lang['native_name']; ?> (<?php echo $lang_code; ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
          <tr>
            <th scope="row"><?php _e('Copy TO language', 'easy-wpml-copy'); ?></th>
            <td>
              <select id="easy_wpml_copy_lang2" name="easy_wpml_copy_lang2" required>
                <option value="">- <?php _e('Select language', 'easy_wpml_copy'); ?> -</option>
                <?php foreach($available_languages as $lang_code => $lang): ?>
                  <option value="<?php echo $lang_code; ?>" <?php echo(isset($_POST['easy_wpml_copy_lang2']) && $_POST['easy_wpml_copy_lang2'] == $lang_code) ? 'selected' : ''; ?>>
                    <?php echo $lang['native_name']; ?> (<?php echo $lang_code; ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
        </tbody>
        <tbody>
          <tr>
            <th scope="row"><?php _e('Post types', 'easy-wpml-copy'); ?></th>
            <td>
              <?php foreach($post_types as $post_type): ?>
                <?php
                $checked = '';
                if (isset($allowed_post_types) && in_array($post_type, (array)$allowed_post_types)) {
                  $checked = 'checked';
                }
                ?>
                <input type="checkbox" name="easy_wpml_copy_post_types[<?php echo $post_type; ?>]" value="<?php echo $post_type; ?>" id="checkbox_<?php echo $post_type; ?>" <?php echo $checked; ?>>
                <label for="checkbox_<?php echo $post_type; ?>"><?php echo  ucfirst($post_type); ?></label><br />
              <?php endforeach; ?>
            </td>
          </tr>
        </tbody>
        <tbody>
          <tr>
            <th scope="row"><?php _e('Post status', 'easy-wpml-copy'); ?><br /><span class="informative"><?php _e('The preferred post status of the newly created posts.', 'easy-wpml-copy'); ?></span></th>
            <td>
              <select id="easy_wpml_copy_post_status" name="easy_wpml_copy_post_status" required>
                <option value="">- <?php _e('Select status', 'easy_wpml_copy'); ?> -</option>
                <option value="publish" <?php if(isset($_POST['easy_wpml_copy_post_status']) && $_POST['easy_wpml_copy_post_status'] == 'publish'): ?>selected<?php endif; ?>><?php _e('Published', 'easy_wpml_copy'); ?></option>
                <option value="draft" <?php if(isset($_POST['easy_wpml_copy_post_status']) && $_POST['easy_wpml_copy_post_status'] == 'draft'): ?>selected<?php endif; ?>><?php _e('Draft', 'easy_wpml_copy'); ?></option>
              </select>
            </td>
          </tr>
        </tbody>
        <tbody>
          <tr>
            <th scope="row"><?php _e('Remove duplicate tag?', 'easy-wpml-copy'); ?></th>
            <td>
              <input type="checkbox" name="remove_duplicate_tag" value="1" id="remove_duplicate_tag">
              <label for="remove_duplicate_tag">Yes, remove duplicate tag - this way WPML will no longer synchronize this post with the original content.</label><br />
            </td>
          </tr>
        </tbody>
      </table>
      <input type="submit" value="Copy posts" class="button">
      <div class="submit-button-info"><?php _e('Please be patient, this can take a minute..', 'easy_wpml_copy'); ?></div>
    </form>
    <?php
  }

  public function copy_posts($original_lang, $destination_lang, $post_types, $remove_duplicate = false, $newly_post_status = false) {
    global $sitepress, $iclTranslationManagement, $wpdb;

    $current_site_lang = $sitepress->get_current_language();

    $args = array(
      'post_type'      => array_keys($post_types),
      'post_status'    => 'publish',
      'posts_per_page' => '-1',
    );

    $sitepress->switch_lang($original_lang);
    $wp_query = new \WP_Query($args);

    $i = 0;
    $copied_posts_groups = array();
    $newly_created_post_ids = array();

    if ( !$wp_query->have_posts() ) {
      $this->print_message(__('No posts found to duplicate for the selected post types.', 'easy-wpml-copy'), 'error');
      return;
    }

    while ( $wp_query->have_posts() ) : $wp_query->the_post();
      global $post;

      $master_post_id = $post->ID;
      $master_post = $post;

      $language_details_original = $sitepress->get_element_language_details($master_post_id, 'post_' . $master_post->post_type);
      if($language_details_original->language_code != $destination_lang) {
        $newly_created_post_ids[] = $iclTranslationManagement->make_duplicate($master_post_id, $destination_lang);
        $i++;
        $copied_posts_groups[$post->post_type][] = $master_post->post_title;
      }
    endwhile;

    if($remove_duplicate) {
      foreach($newly_created_post_ids as $newly_created_post_id) {
        // delete post meta for duplicate of old post
        delete_post_meta($newly_created_post_id, '_icl_lang_duplicate_of');
      }
    }

    if($newly_post_status && $newly_post_status == 'draft') {
      // update each duplicated post status by hand to draft
      $current_blog_id = get_current_blog_id();
      $posts_table = $current_blog_id == 1 ? 'wp_posts' : 'wp_' . $current_blog_id . '_posts';
      $sql = "
        UPDATE " . $posts_table . "
        SET post_status = 'draft'
        WHERE ID IN(".implode(', ', array_fill(0, count($newly_created_post_ids), '%s')).")
      ";

      $query = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $newly_created_post_ids));

      $wpdb->query($query);
    }

    $sitepress->switch_lang($current_site_lang);

    if($i > 0 && !empty($copied_posts_groups)) {
      $success_text = "<strong>The following {$i} posts have been copied to the '{$destination_lang}' language:</strong><br /><ul>";
      foreach($copied_posts_groups as $post_type => $copied_posts) {
        $success_text .= '<li><strong>' . ucfirst($post_type) . '</strong>';
        foreach($copied_posts as $dup_post) {
          $success_text .= '<li>- ' . $dup_post . '</li>';
        }
      }
      $success_text .= '</ul>';

      $this->print_message($success_text, 'success');
    }
  }

  public function print_message($text, $type = 'success'){
    echo "<div class=\"notice notice-{$type} is-dismissible\"><p>{$text}</p></div>";
  }
}
