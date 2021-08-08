<?php
/**
 * Plugin Name: BP Content Restriction
 * Version: 1.0
 * Author: Kim Le
 * Author URI: https://www.kimleonline.net/
 * Description: This is a Buddypress extentsion which allows content to be restricted to members of specific Buddypress groups. BuddyPress is required.
 * 
 */


if ( ! defined( 'ABSPATH' ) ) exit;

class BPContentRestrictionPlugin {

  function __construct() {
    add_action( 'add_meta_boxes', array($this, 'add_form_setting_to_edit_view') );
    add_action( 'save_post', array($this, 'save_data') );
    add_filter( 'the_content', array($this, 'bp_group_restrict_thecontent_main_loop'), 1 );
  }

  /**
   * Add the custom fields to all post and page sidebars
   */
  public function add_form_setting_to_edit_view() {
    $page_types = ['post', 'page'];

    foreach ( $page_types as $page_type ) {
      add_meta_box(
        'restrict-access-id', 
        'Restricted Access', 
        array($this,'restriction_form_metabox'),
        $page_type, 
        'side', // list in the sidebar
        'high' // listed it high in the settings 
      );
    }
  }


  /**
   * Create the fields
   */
  public function restriction_form_metabox($post) {

    echo '<p>' . __('Only selected groups can view. If none are checked, the public can view.') . '</p>';

    $bp_groups = array();

    if ( function_exists('bp_has_groups') && bp_has_groups() ) {
      $bp_groups['all_groups'] = 'All Buddypress Groups';

      while ( bp_groups() ) {
        bp_the_group();
        $bp_groups[bp_get_group_id()] = bp_get_group_name();
      }
    }

    $postmeta = get_post_meta($post->ID, 'bp_groups_restricted_to', true);

    // Loop through array and make a checkbox for each element
    foreach ( $bp_groups as $key => $value) {
      // If the postmeta for checkboxes exist and 
      // this element is part of saved meta check it.
      if ( is_array($postmeta) && in_array($key, $postmeta) ) {
        $checked = 'checked';
      } else {
        $checked = null;
      }
  ?>
      <div class="components-panel__row">
        <div class="components-base-control">
          <div class="components-base-control__field">

            <span class="components-checkbox-control__input-container">
              <input 
                type="checkbox" 
                name="bp_group_restriction_val[]"
                id="bp_group_<?php echo $key;?>" 
                value="<?php echo $key;?>" 
              <?php echo $checked; ?> />
            </span>

            <label for="bp_group_<?php echo $key;?>" class="components-checkbox-control__label"><?php echo $value;?></label>
          </div>
        </div>
      </div>
  <?php
    }

     // hidden field for security
     wp_nonce_field( basename(__FILE__), 'bp_content_restriction_metabox_nonce' );
  }


  /**
   * Save data on user save
   * 
   * Note: does not save on autosave 
   * https://github.com/WordPress/gutenberg/issues/20755
   */
  public function save_data($post_id) {

    if ( 
      !isset($_POST['bp_content_restriction_metabox_nonce']) || 
      !wp_verify_nonce( $_POST['bp_content_restriction_metabox_nonce'], basename(__FILE__) ) 
    ) {
      return wp_send_json_error($data);
    }

    // If the checkbox was not empty, save it as array in post meta
    if ( !empty($_POST['bp_group_restriction_val']) ) {
        update_post_meta( $post_id, 'bp_groups_restricted_to', $_POST['bp_group_restriction_val'] );
    // Otherwise just delete it if its blank value.
    } else {
        delete_post_meta($post_id, 'bp_groups_restricted_to');
    }
  }


  /**
   * Filter the_content() so we don't have to touch template files
   */
  public function bp_group_restrict_thecontent_main_loop($content) {
  
    // Check if we're inside the main loop in a single Post.
    if ( is_singular() && in_the_loop() && is_main_query() ) {
      $restriction_arr = get_post_meta( get_the_ID(), 'bp_groups_restricted_to', true);

      if (!empty($restriction_arr)) { // restriction is set

        $member_arr = array();
        
        // get all groups user is a member of
        if ( bp_has_groups() ) {
          while ( bp_groups() ) {
            bp_the_group();

            if ( groups_is_user_member(get_current_user_id(), bp_get_group_id()) ) {
              array_push( $member_arr, bp_get_group_id() );
            }              
          }
        }
        
        // if "all groups checkbox" and a member of a group
        if ( in_array('all_groups', $restriction_arr) && !empty($member_arr) ) {
          return $content;
        }

        // if a member of certian subteams
        $restriction_match = array_intersect($restriction_arr, $member_arr);
        if (!empty($restriction_match)) {
          return $content;
        } else {
          return '<p>' . __('This is restricted content.', 'bp-content-restriction') . '</p>';
        }

      } else {
        return $content; // if there is no restriction then it's public content
      }
      
    }
  
    return $content;
  }


  public function activate() {
    flush_rewrite_rules();
  }

  public function deactivate() {
    flush_rewrite_rules();
  }

} // close class


 /**
 * Make sure class exists
 */
if ( class_exists('BPContentRestrictionPlugin') ) {
  $bpContentRestrictionPlugin = new BPContentRestrictionPlugin();
}


/**
 * Activation hook for the plugin.
 */
register_activation_hook( __FILE__, array($bpContentRestrictionPlugin, 'activate') );

/**
 * Deactivation hook for the plugin.
 */
register_deactivation_hook( __FILE__, array($bpContentRestrictionPlugin, 'deactivate') );

?>
