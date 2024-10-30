<?php
/**
 * Common Functions Used in the Main Class.
 *
 * @package All-in-One Data Privacy & Cookie Policy Solution for GDPR/CCPA
 */

/* Blocking direct access of PHP files */
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/** Flush rewrite rules upon activation**/
function ccpact_activation() {
  flush_rewrite_rules();
}

/** Flush rewrite rules and delete options upon deactivation **/
function ccpact_deactivation() {
  flush_rewrite_rules();

  delete_option( 'ccpact_privacy_mode' );
  delete_option( 'ccpact_privacy_mode_tmp' );
  delete_option( 'ccpact_privacy_api_key' );
  delete_option( 'ccpact_privacy_api_key_tmp' );
  delete_option( 'ccpact_privacy_domain' );
  delete_option( 'ccpact_privacy_domain_tmp' );
  delete_option( 'ccpact_paranoid_metadata' );
  delete_option( 'ccpact_sane_metadata' );
  delete_option( 'ccpact_db_version' );
}

/** Loading plugin translations **/
function ccpact_load_plugin_textdomain() {
  load_plugin_textdomain(
    'pp-ccpa-compliance-tool',
    false,
    basename( dirname( __FILE__ ) ) . '/languages/'
  );
}

/* Add Settings Link */
function ccpact_add_settings_link( $links ) {
  $links[] = '<a href="' .
  admin_url( 'tools.php?page=ccpa-compliance-tool' ) .
  '">' . __('Settings') . '</a>';

  return $links;
}

/** Code For Getting the Website URL **/
function ccpact_get_url() {
  $site_url     = site_url();
  $parsed_parts = wp_parse_url( $site_url );

  if ( ! $parsed_parts ) {
    wp_die( 'ERROR: Path corrupt for parsing.' );
  }

  $n_port      = $parsed_parts['port'];
  $site_host   = $parsed_parts['host'];
  $n_port      = 80 === $n_port ? '' : $n_port;
  $n_port      = 443 === $n_port ? '' : $n_port;
  $site_port   = ! empty( $site_port ) ? ":$n_port" : '';
  $site_return = $site_host . $site_port;
  return $site_return;
}
