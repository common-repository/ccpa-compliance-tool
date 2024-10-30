<?php
/**
 * Plugin Name:       All-in-One Data Privacy & Cookie Policy Solution for GDPR/CCPA
 * Plugin URI:        https://www.privacypixel.com
 * Description:       We make protecting your business easy. Use our All-in-One Data Privacy & Cookie Policy Solution for GDPR/CCPA to automate some of the work.
 * Version:           1.2.2
 * Requires at least: 4.4.0
 * Author:            Privacy Pixel
 * Author URI:        https://www.privacypixel.com
 * License:           GPLv2
 * License URI:       https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain:       pp-ccpa-compliance-tool
 * Domain Path:       /languages
 *
 * @package All-in-One Data Privacy & Cookie Policy Solution for GDPR/CCPA
 */

/*
This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see
https://www.gnu.org/licenses/old-licenses/gpl-2.0.html.
*/

/* Blocking direct access of PHP files */
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! class_exists( 'PPCCPAComplianceTool' ) ) {

  require_once plugin_dir_path( __FILE__ ) . 'includes/ccpact-functions.php';

  /** Main Class **/
  class PPCCPAComplianceTool {

    /**
     * Variable used to display errors to the user
     *
     * @var string
     */
    public $error;

    /**
     * Variable used to clean the old DB scripts upon update
     *
     * @var string
     */
    public $version = '1.2.2';

    /** First Function Automatically Executed **/
    public function __construct() {
      register_activation_hook( __FILE__, 'ccpact_activation' );
      register_deactivation_hook( __FILE__, 'ccpact_deactivation' );

      add_action( 'init', array( &$this, 'ccpact_set_header' ) );
      add_action( 'admin_init', array( &$this, 'ccpact_settings' ) );
      add_action( 'admin_init', array( &$this, 'ccpact_privacy_api_key_tmp_logic' ) );
      add_action( 'admin_init', array( &$this, 'ccpact_privacy_mode_tmp_logic' ) );
      add_action( 'admin_init', array( &$this, 'ccpact_privacy_domain_tmp_logic' ) );
      add_action( 'admin_menu', array( &$this, 'ccpact_setup_submenu' ) );
      add_action( 'plugins_loaded', 'ccpact_load_plugin_textdomain' );
      
      add_filter(
        'plugin_action_links_' . plugin_basename(__FILE__),
        'ccpact_add_settings_link'
      );
    }

    /** Check and Update Old DB Scripts **/
    public function compare_versions() {
      $db_version = get_option( 'ccpact_db_version' );

      if ( $db_version !== $this->version ) {
        try {
          $this->ccpact_get_api_metadata();
          update_option( 'ccpact_db_version', $this->version );
        } catch (Exception $e) {
          return null;
        }
      }
    }

    /** Setting Meta and Script on Header **/
    public function ccpact_set_header() {
      global $error;

      $privacy_api_key_tmp = get_option( 'ccpact_privacy_api_key_tmp' );
      $privacy_mode        = get_option( 'ccpact_privacy_mode' );
      $privacy_domain_tmp  = get_option( 'ccpact_privacy_domain_tmp' );
      $paranoid_metadata   = get_option( 'ccpact_paranoid_metadata' );
      $sane_metadata       = get_option( 'ccpact_sane_metadata' );

      if ( $privacy_api_key_tmp && $privacy_domain_tmp ) {
        if ( isset( $_GET['settings-updated'] ) ) {
          $error = 'settings saved';
          $this->ccpact_get_api_metadata();
        }
        
        $this->compare_versions();

        switch ( $privacy_mode ) {
          case 'paranoid':
            add_action( 'wp_head', array( &$this, 'ccpact_paranoid_metadata' ), 0 );
            break;
          case 'sane':
            add_action( 'wp_head', array( &$this, 'ccpact_sane_metadata' ), 0 );
            break;
        }
      } else {
        update_option( 'ccpact_privacy_domain_tmp', null, true );
      }
    }

    /** Adding Paranoid Metadata to Header **/
    public function ccpact_paranoid_metadata() {
      echo get_option( 'ccpact_paranoid_metadata' );
    }

    /** Adding Sane Metadata to Header **/
    public function ccpact_sane_metadata() {
      echo get_option( 'ccpact_sane_metadata' );
    }

    /** Get API Metadata and Store in DB **/
    public function ccpact_get_api_metadata() {
      global $error;

      $privacy_api_key_tmp = get_option( 'ccpact_privacy_api_key_tmp' );
      $privacy_domain_tmp  = get_option( 'ccpact_privacy_domain_tmp' );
      $paranoid_metadata   = get_option( 'ccpact_paranoid_metadata' );
      $sane_metadata       = get_option( 'ccpact_sane_metadata' );

      $response = wp_remote_get(
        'https://api.privacypixel.com/v1/code?domain=' . $privacy_domain_tmp .
        '&apiKey=' . $privacy_api_key_tmp
      );

      if ( is_wp_error( $response ) ) {
        return false;
      }

      $body = wp_remote_retrieve_body( $response );

      $data = json_decode( $body );

      if ( false === $data->ok ) {
        $error = $data->error;
        return false;
      }

      if ( ! ( $paranoid_metadata && $sane_metadata ) ) {
        add_option( 'ccpact_paranoid_metadata', $data->paranoid );
        add_option( 'ccpact_sane_metadata', $data->sane );
        add_option( 'ccpact_privacy_api_key', $privacy_api_key_tmp );
        add_option( 'ccpact_privacy_domain', $privacy_domain_tmp );
      } else {
        update_option( 'ccpact_paranoid_metadata', $data->paranoid );
        update_option( 'ccpact_sane_metadata', $data->sane );
        update_option( 'ccpact_privacy_api_key', $privacy_api_key_tmp );
        update_option( 'ccpact_privacy_domain', $privacy_domain_tmp );
      }
    }

    /**
     * Display errors to the user
     *
     * @param string $error - Error Variable.
     */
    public function ccpact_display_api_error( $error ) {
      switch ( $error ) {
        case 'apiKey not found':
          add_settings_error(
            'ccpact-notices',
            'api_key_not_found',
            __( 'API key not found.', 'pp-ccpa-compliance-tool' ),
            'error'
          );
          break;
        case 'domain not found':
          add_settings_error(
            'ccpact-notices',
            'domain_not_found',
            __( 'Domain not found.', 'pp-ccpa-compliance-tool'),
            'error'
          );
          break;
        case 'domain does not belong to this account':
          add_settings_error(
            'ccpact-notices',
            'domain_not_account',
            __( 'Domain does not belong to this account.', 'pp-ccpa-compliance-tool' ),
            'error'
          );
          break;
        case 'settings saved':
          add_settings_error(
            'ccpact-notices',
            'settings_saved',
            __( 'Settings saved.', 'pp-ccpa-compliance-tool' ),
            'updated'
          );
          break;
        default:
          null;
      }

      settings_errors( 'ccpact-notices' );
    }

    /** Setting Up Admin Submenu **/
    public function ccpact_setup_submenu() {
      add_submenu_page(
        'tools.php',
        'CCPA Compliance Tool',
        'CCPA Compliance Tool',
        'manage_options',
        'ccpa-compliance-tool',
        array( &$this, 'ccpact_load_submenu' )
      );
    }

    /** Loading Admin Submenu Form **/
    public function ccpact_load_submenu() {
      global $error;
      $this->ccpact_display_api_error( $error );

      ?>
        <div class="wrap">
          <h1>
            <?php esc_html_e( 'CCPA Compliance Tool Settings', 'pp-ccpa-compliance-tool' ); ?>
          </h1>

          <form action="options.php" method="post">
            <?php
              settings_fields( 'privacy_group' );
              do_settings_sections( 'privacy_page' );
              submit_button();
            ?>
          </form>
        </div>
      <?php
    }

    /** Registering and Adding Settings to Plugin **/
    public function ccpact_settings() {
      register_setting(
        'privacy_group',
        'ccpact_privacy_api_key_tmp',
        array(
          'type'              => 'string',
          'sanitize_callback' => 'sanitize_key',
        )
      );
      /*
      register_setting(
        'privacy_group',
        'ccpact_privacy_mode_tmp'
      );
      */
      register_setting(
        'privacy_group',
        'ccpact_privacy_domain_tmp',
        array(
          'type'              => 'string',
          'sanitize_callback' => 'sanitize_text_field',
        )
      );

      add_settings_section(
        'privacy-global-settings',
        __( 'Global Settings', 'pp-ccpa-compliance-tool' ),
        null,
        'privacy_page'
      );

      add_settings_field(
        'ccpact_privacy_api_key_tmp',
        __( 'Enter API key:', 'pp-ccpa-compliance-tool' ),
        array( &$this, 'ccpact_privacy_api_key_tmp_display' ),
        'privacy_page',
        'privacy-global-settings'
      );
      /*
      add_settings_field(
        'ccpact_privacy_mode_tmp',
        __( 'Select mode:', 'pp-ccpa-compliance-tool' ),
        array( &$this, 'ccpact_privacy_mode_tmp_display' ),
        'privacy_page',
        'privacy-global-settings'
      );
      */
      add_settings_field(
        'ccpact_privacy_domain_tmp',
        __( 'Enter domain:', 'pp-ccpa-compliance-tool' ),
        array( &$this, 'ccpact_privacy_domain_tmp_display' ),
        'privacy_page',
        'privacy-global-settings'
      );
    }

    /** Logic For Displaying the API key Input Option **/
    public function ccpact_privacy_api_key_tmp_logic() {
      global $error;

      $privacy_api_key_tmp = get_option( 'ccpact_privacy_api_key_tmp' );
      $privacy_api_key     = get_option( 'ccpact_privacy_api_key' );

      if ( ! $error || 'settings saved' === $error ) {
        update_option( 'ccpact_privacy_api_key_tmp', $privacy_api_key );
      }
    }

    /** HTML For Displaying the API key Input Option **/
    public function ccpact_privacy_api_key_tmp_display() {
      $privacy_api_key_tmp = get_option( 'ccpact_privacy_api_key_tmp' );

      ?>
        <input
          type="text"
          size=38
          name="ccpact_privacy_api_key_tmp"
          value="<?php echo esc_attr( $privacy_api_key_tmp ); ?>"
        />
      <?php

      if ( ! $privacy_api_key_tmp ) {
        $this->ccpact_link_website_display();
      }
    }

    /** HTML For Displaying the See Our Website Link **/
    public function ccpact_link_website_display() {
      ?>
        <a 
          href="https://app.privacypixel.com/register-free"
          target="_blank"
          rel="noopener noreferrer"
          aria-label="<?php echo esc_attr_e( 'See our website', 'pp-ccpa-compliance-tool' ); ?>"
        >
          <?php esc_html_e( 'Get your key here', 'pp-ccpa-compliance-tool' ); ?>
        </a>
      <?php
    }

    /** Logic For Displaying the Privacy Mode Radio Option **/
    public function ccpact_privacy_mode_tmp_logic() {
      global $error;

      $privacy_mode        = get_option( 'ccpact_privacy_mode' );
      $privacy_mode_tmp    = get_option( 'ccpact_privacy_mode_tmp' );
      $privacy_api_key_tmp = get_option( 'ccpact_privacy_api_key_tmp' );

      if ( ! $privacy_api_key_tmp ) {
        $privacy_mode_tmp = 'sane';
        add_option( 'ccpact_privacy_mode_tmp', $privacy_mode_tmp );
      };

      if ( $error && 'settings saved' !== $error ) {
        update_option( 'ccpact_privacy_mode_tmp', $privacy_mode, true );
      }

      if ( ! $error || 'settings saved' === $error ) {
        update_option( 'ccpact_privacy_mode', $privacy_mode_tmp, true );
      }
    }

    /** HTML For Displaying the Privacy Mode Radio Option **/
    /*
    public function ccpact_privacy_mode_tmp_display() {
      $privacy_mode_tmp = get_option( 'ccpact_privacy_mode_tmp' );

      ?>
        <input
          type="radio"
          id="radio_paranoid"
          name="ccpact_privacy_mode_tmp"
          value="paranoid"
          <?php checked( 'paranoid', $privacy_mode_tmp ); ?>
        />
        <label for="radio_paranoid">
          Paranoid
        </label>
        <input
          type="radio"
          id="radio_sane"
          name="ccpact_privacy_mode_tmp"
          style="margin-left: 25px;"
          value="sane"
          checked="checked"
          <?php checked( 'sane', $privacy_mode_tmp ); ?>
        />
        <label for="radio_sane">
          Sane
        </label>
      <?php
    }
    */

    /** Logic For Displaying the Privacy Domain Option **/
    public function ccpact_privacy_domain_tmp_logic() {
      global $error;

      $privacy_domain_tmp = get_option( 'ccpact_privacy_domain_tmp' );
      $privacy_domain     = get_option( 'ccpact_privacy_domain' );

      if ( ! $error || 'settings saved' === $error ) {
        update_option( 'ccpact_privacy_domain_tmp', $privacy_domain, true );
      }

      if ( ! $privacy_domain_tmp ) {
        $privacy_domain_tmp = ccpact_get_url();
        update_option( 'ccpact_privacy_domain_tmp', $privacy_domain_tmp, true );
      };
    }

    /** HTML For Displaying the Privacy Domain Option **/
    public function ccpact_privacy_domain_tmp_display() {
      $privacy_domain_tmp = get_option( 'ccpact_privacy_domain_tmp' );

      ?>
        <input
          type="text"
          size=23
          name="ccpact_privacy_domain_tmp"
          value="<?php echo esc_attr( $privacy_domain_tmp ); ?>"
        />
      <?php
    }

  }

  $ppcppa_compliance_tool = new PPCCPAComplianceTool();
}
