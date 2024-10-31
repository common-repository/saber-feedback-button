<?php
/*
Plugin Name: Saber Feedback Button
Plugin URI:  https://saberfeedback.com
Description: Include the Saber Feedback widget in your Wordpress site.
Version:     2.0.4
Author:      Saber Feedback
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

class SaberFeedbackButton
{  
  // Singleton instance
  private static $instance = null;
   
  // Saved options
  public $options;
    
  /**
   * Creates or returns an instance of this class.
   *
   * @return SaberFeedbackButton A single instance of this class.
   */
  public static function get_instance() {
    if ( null == static::$instance ) {
      static::$instance = new self;
    }

    return static::$instance;
  }

  /**
   * Initializes the plugin
   */
  private function __construct() {
    // Get saved option
    $this->options = get_option('saberfeedback_settings_options');

    // when migrating from old version, create the default all visibility
    if(!isset($this->options['visibility'])) {
      $this->options['visibility'] = 'all';
    }

    // Add the page to the admin menu
    add_action('admin_menu', array(&$this, 'add_page'));
     
    // Register page options
    add_action('admin_init', array(&$this, 'register_page_options'));    

    // Add Saber Javascript to Head
    add_action('wp_head', array(&$this, 'saber_javascript'));
     
    // redirect to plugin settings after actication
    register_activation_hook(__FILE__, array(&$this, 'plugin_activated'));
    add_action('admin_init', array(&$this, 'redirect_on_activation'));
  }

  /** 
   * get the options and add the script
   * Will not add if API key not included.
   */
  public function saber_javascript() {
    if(is_admin()) return;

    $logged_in = is_user_logged_in();

    if($this->options['visibility'] == 'guests' && $logged_in) {
      return;
    }

    if($this->options['visibility'] == 'users' && !$logged_in) {
      return;
    }
        
    if(!$this->options || !isset($this->options['api_key']) || $this->options['api_key'] == '') return;

    ?>
      <!-- Saber Feedback button -->
      <script type="text/javascript">
          (function () {
              window.Saber = {
                  apiKey: '<?php echo $this->options['api_key'] ?>',
                  com:[],do:function(){this.com.push(arguments)}
              };
              var e = document.createElement("script");
              e.setAttribute("type", "text/javascript");
              e.setAttribute("src", "https://widget.saberfeedback.com/v2/widget.js");
              document.getElementsByTagName("head")[0].appendChild(e);
          })();
      </script>
      <!-- End of Saber Feedback button -->
    <?php
  }

  /**
   * Flag for post-activation redirect on admin_init
   */
  public function plugin_activated() {
    add_option('saberfeedback_do_activation_redirect', true);
  }

  /**
   * Redirect to plugin options if flagged
   */
  function redirect_on_activation() {
    if (get_option('saberfeedback_do_activation_redirect', false)) {
      delete_option('saberfeedback_do_activation_redirect');
      exit( wp_redirect("options-general.php?page=saber-feedback"));
    }
  }
    
  /**
   * Settings Menu options
   */
  public function add_page() {
    add_options_page(__('Saber Feedback Configuration', 'saber-feedback-button'), __('Saber Feedback', 'saber-feedback-button'), 'manage_options', 'saber-feedback', array(&$this, 'display_page'));
  }
    
  /**
   * Function that will display the options page.
   */
  public function display_page() {
    if(isset($this->options['language'])): ?>
      <div id="legacy_options_warning" class="error notice"> 
        <p><strong><?php _e('Saber Feedback settings are now managed from within the <a href="https://app.saberfeedback.com/" target="_blank">control panel</a>. Please make sure your feedback button is correctly configured in the control panel before clicking the Save button below.', 'saber-feedback-button'); ?></strong></p>
      </div>
    <?php endif ?>

    <div class="wrap">
      <h2><?php _e('Saber Feedback', 'saber-feedback-button'); ?></h2>
      <p><?php _e('Thanks for using Saber Feedback! This plugin requires a Saber Feedback account, if you don\'t yet have one, you can sign up for free at <a href="https://saberfeedback.com/" target="_blank">saberfeedback.com</a>.', 'saber-feedback-button'); ?></p>

      <form method="post" action="options.php">
      <?php 
        settings_fields('saberfeedback_settings');      
        do_settings_sections('saberfeedback_settings');
        submit_button(__('Save', 'saber-feedback-button'));
      ?>
      </form>
      <h3><?php _e('Form and Button Configuration', 'saber-feedback-button'); ?></h3>
      <p><?php _e('You can customise the feedback button and form by logging into the <a href="https://app.saberfeedback.com/" target="_blank">Saber Feedback control panel</a> and choosing <strong>Edit Website</strong> or <strong>Form Builder</strong> from the menu on the right.', 'saber-feedback-button'); ?></p>
      <p>
        <a href="https://app.saberfeedback.com" class="button button-default" target="_blank"><?php _e('Go to Saber Feedback Control Panel', 'saber-feedback-button'); ?></a>
      </p>
    </div> <!-- /wrap -->
    <?php    
  }
     
  /**
   * Function that will register admin page options.
   */
  public function register_page_options() {
    // Add Section for option fields
    add_settings_section('saberfeedback_api_section', __('Saber Feedback Connection', 'saber-feedback-button'), array(&$this, 'display_api_section'), 'saberfeedback_settings');
     
    // Add API key Field
    add_settings_field(
      'saberfeedback_api_key_field',                  // id
      __('Public API Key', 'saber-feedback-button'),  // name
      array(&$this, 'render_api_key_field'),          // display method ($this->render_api_key_field)
      'saberfeedback_settings',                       // page
      'saberfeedback_api_section'                     // section
    );

    // Add Visibility Field
    add_settings_field(
      'saberfeedback_visibility_field',               // id
      __('Load Saber Feedback for', 'saber-feedback-button'), // name
      array(&$this, 'render_visibility_field'),       // display method ($this->render_visibility_field)
      'saberfeedback_settings',                       // page
      'saberfeedback_api_section'                     // section
    );

    // Register Settings
    //                option group              option name                      sanitize method
    register_setting('saberfeedback_settings', 'saberfeedback_settings_options', array(&$this, 'validate_options')); 

  }


  public function general_admin_notice(){
    global $pagenow;
    if ( $pagenow == 'options-general.php' ) {
         echo '<div class="notice notice-warning is-dismissible">
             <p>' . __('This notice appears on the settings page.', 'saber-feedback-button') . '</p>
         </div>';
    }
  }
      
  /**
   * Function that will validate all fields.
   */
  public function validate_options($fields) {        
    $valid_fields = array();
     
    // Validate API Key Field
    $api_key = strip_tags( stripslashes( trim( $fields['api_key'] )));
    if($api_key == ''){
      add_settings_error('saberfeedback_settings_options', 'saberfeedback_bg_error', __('Public API Key is required to use Saber Feedback', 'saber-feedback-button'), 'error');
    }
    elseif(preg_match('/^(?=[a-f0-9]*$)(?:.{20}|.{40})$/i', $api_key ) == 0){
      add_settings_error('saberfeedback_settings_options', 'saberfeedback_bg_error', __('API Key is not in the correct format. Please check and try again', 'saber-feedback-button'), 'error');
    }
        
    $valid_fields['api_key'] = $api_key;

    $visibility = strip_tags(stripslashes( trim( $fields['visibility'] )));
    $valid_fields['visibility'] = $visibility;

    $cacheCleared = false;
    
    // Clear all W3 Total Cache if it's installed
    // https://wordpress.org/support/topic/how-to-flush-w3tc-cache-remotely/#post-11745129
    if( function_exists('w3tc_flush_all') ) {
      w3tc_flush_all();
      add_settings_error('saberfeedback_settings_options', 'saberfeedback_bg_warning', __('Settings saved. Your W3 Total Cache has been automatically cleared.', 'saber-feedback-button'), 'success');
      $cacheCleared = true;
    }

    // Clear WP_Optimize cache if it's installed
    // https://getwpo.com/documentation/#Purging-the-cache-from-an-other-plugin-or-theme
    if( function_exists('WP_Optimize') ) {
      WP_Optimize()->get_page_cache()->purge();
      add_settings_error('saberfeedback_settings_options', 'saberfeedback_bg_warning', __('Settings saved. Your WP Optimize cache has been automatically cleared.', 'saber-feedback-button'), 'success');
      $cacheCleared = true;
    }

    // Clear WP Rocket cache if it's installed
    // https://docs.wp-rocket.me/article/92-rocketcleandomain
    if( function_exists('rocket_clean_domain') ) {
      rocket_clean_domain();
      add_settings_error('saberfeedback_settings_options', 'saberfeedback_bg_warning', __('Settings saved. Your WP Rocket cache has been automatically cleared.', 'saber-feedback-button'), 'success');
      $cacheCleared = true;
    }

    // Clear WP Super Cahce if it's installed
    // https://github.com/projectestac/wordpress-super-cache/blob/master/wp-cache-phase2.php#L2491
    if( function_exists('wp_cache_clear_cache')) {
      wp_cache_clear_cache();
      add_settings_error('saberfeedback_settings_options', 'saberfeedback_bg_warning', __('Settings saved. Your WP Super Cache has been automatically cleared.', 'saber-feedback-button'), 'success');
      $cacheCleared = true;
    }

    // Clear SiteGround Optimizer if it's installed
    // https://wordpress.org/support/topic/calling-the-purge-programmatically/#post-13667030
    if( function_exists('sg_cachepress_purge_cache')) {
      sg_cachepress_purge_cache();
      add_settings_error('saberfeedback_settings_options', 'saberfeedback_bg_warning', __('Settings saved. Your SiteGround Optimizer cache has been automatically cleared.', 'saber-feedback-button'), 'success');
      $cacheCleared = true;
    }

    // Clear Autoptimize cache if it's installed
    // https://gist.github.com/lukecav/8e690a19b151002a4ab42dae767efb76#file-functions-php-L8
    if (class_exists('autoptimizeCache')) {
      autoptimizeCache::clearall();
      add_settings_error('saberfeedback_settings_options', 'saberfeedback_bg_warning', __('Settings saved. Your Autoptimize cache has been automatically cleared.', 'saber-feedback-button'), 'success');
      $cacheCleared = true;
    }
    
    // Clear Cache Enabler cache if it's installed
    // https://www.keycdn.com/support/wordpress-cache-enabler-plugin#cache_enabler_clear_complete_cache
    if (has_action('cache_enabler_clear_complete_cache')) {
      do_action( 'cache_enabler_clear_complete_cache' );
      add_settings_error('saberfeedback_settings_options', 'saberfeedback_bg_warning', __('Settings saved. Your Cache Enabler cache has been automatically cleared.', 'saber-feedback-button'), 'success');
      $cacheCleared = true;
    }
    
    // Clear Hummingbird cache if it's installed
    // https://wpmudev.com/forums/topic/hummingbird-remotely-trigger-clear-cache/
    if (has_action('wphb_clear_page_cache')) {
      do_action( 'wphb_clear_page_cache' );
      add_settings_error('saberfeedback_settings_options', 'saberfeedback_bg_warning', __('Settings saved. Your Hummingbird cache has been automatically cleared.', 'saber-feedback-button'), 'success');
      $cacheCleared = true;
    }

    // WP Fastest Cache cannot be cleared automatically, and it's not detected by the condition below, so we need to chec this
    if (class_exists('WpFastestCache')) {
      add_settings_error('saberfeedback_settings_options', 'saberfeedback_bg_warning', __('Settings saved. You need to clear your WP Fastest Cache after updating these settings.', 'saber-feedback-button'), 'warning');
      $cacheCleared = true;
    }

    // For all other plugins, display a warning message
    if ( ! $cacheCleared && defined( 'WP_CACHE' ) && WP_CACHE) {
      add_settings_error('saberfeedback_settings_options', 'saberfeedback_bg_warning', __('Settings saved. You need to clear your WordPress cache after updating these settings.', 'saber-feedback-button'), 'warning');
    }
    
    return apply_filters('validate_options', $valid_fields, $fields);
  }
   
  /**
   * Callback function for settings section
   */
  public function display_api_section() {
    echo '<p>' . __('Enter your public API key to connect to Saber Feedback, you can find your public API key at the top of the screen below your website name when you log in to <a href="https://app.saberfeedback.com/" target="_blank">app.saberfeedback.com</a>.', 'saber-feedback-button') . '</p>';
  } 
   
  /**
   * Functions that display the fields.
   */
  public function render_api_key_field() {
    $val = isset($this->options['api_key']) ? $this->options['api_key'] : '';
    echo '<input type="text" name="saberfeedback_settings_options[api_key]" value="' . $val . '" class="regular-text" />';
  }        

  public function render_visibility_field() {
    $html = '<select name="saberfeedback_settings_options[visibility]">';

    $html .= '<option value="all"'.selected($this->options['visibility'], 'all', false).'>' . __('All Visitors', 'saber-feedback-button') . '</option>';
    $html .= '<option value="users"'.selected($this->options['visibility'], 'users', false).'>' . __('Logged in users only', 'saber-feedback-button') . '</option>';
    $html .= '<option value="guests"'.selected($this->options['visibility'], 'guests', false).'>' . __('Guests only', 'saber-feedback-button') . '</option>';

    $html .= '</select>';
       
    echo $html;
  }
}

SaberFeedbackButton::get_instance();
