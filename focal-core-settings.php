<?php
/**
 * Plugin Name: Focal Core Settings
 * Plugin URI: https://www.focalhaus.com/plugins/focal-core-settings
 * Description: A comprehensive plugin that provides multiple functionalities for WordPress sites, including hiding dashboard menu items, custom permalinks, plugin integrations, multilingual support, and Google Tag Manager integration.
 * Version: 0.2.5
 * Author: Focal Haus Dev Team
 * Author URI: https://www.focalhaus.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: focal-core-settings
 * Domain Path: /languages
 *
 * @package Focal_Core_Settings
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'FCS_VERSION', '0.2.5' );
define( 'FCS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FCS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FCS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Autoload plugin classes
require_once FCS_PLUGIN_DIR . 'src/core/autoloader.php';
new \FocalCore\core\Autoloader();

// Initialize the plugin
add_action( 'plugins_loaded', function() {
    \FocalCore\core\Plugin::get_instance();
}, 10 );

// Add a custom action for flushing rewrite rules
add_action( 'fcs_flush_rewrite_rules', 'flush_rewrite_rules' );

/**
 * Register activation hook.
 */
function fcs_activate() {
    // Migrate settings from old plugin if they exist
    $old_settings = get_option( 'hdmi_hidden_menu_items', false );
    if ( $old_settings !== false ) {
        update_option( 'fcs_hidden_menu_items', $old_settings );
        delete_option( 'hdmi_hidden_menu_items' );
    }
    
    // Migrate settings from focal-haus-core plugin if they exist
    $fhc_hidden_menu_items = get_option( 'fhc_hidden_menu_items', false );
    if ( $fhc_hidden_menu_items !== false ) {
        update_option( 'fcs_hidden_menu_items', $fhc_hidden_menu_items );
    }
    
    $fhc_menu_hiding_settings = get_option( 'fhc_menu_hiding_settings', false );
    if ( $fhc_menu_hiding_settings !== false ) {
        update_option( 'fcs_menu_hiding_settings', $fhc_menu_hiding_settings );
    }
    
    $fhc_cpt_without_base = get_option( 'fhc_cpt_without_base', false );
    if ( $fhc_cpt_without_base !== false ) {
        update_option( 'fcs_cpt_without_base', $fhc_cpt_without_base );
    }
    
    $fhc_misc_settings = get_option( 'fhc_misc_settings', false );
    if ( $fhc_misc_settings !== false ) {
        update_option( 'fcs_misc_settings', $fhc_misc_settings );
    }
    
    $fhc_gtm_settings = get_option( 'fhc_gtm_settings', false );
    if ( $fhc_gtm_settings !== false ) {
        update_option( 'fcs_gtm_settings', $fhc_gtm_settings );
    }
    
    $fhc_settings_access_control = get_option( 'fhc_settings_access_control', false );
    if ( $fhc_settings_access_control !== false ) {
        update_option( 'fcs_settings_access_control', $fhc_settings_access_control );
    }
    
    // Set flag to force permalinks update
    update_option('fcs_permalinks_updated', '1.1.1');
    
    // Flush rewrite rules on activation
    flush_rewrite_rules();
    
    // Schedule another flush after a short delay to ensure all rules are registered
    wp_schedule_single_event( time() + 5, 'fcs_flush_rewrite_rules' );
}
register_activation_hook( __FILE__, 'fcs_activate' );

/**
 * Register deactivation hook.
 */
function fcs_deactivate() {
    // Clear scheduled events
    wp_clear_scheduled_hook( 'fcs_flush_rewrite_rules' );
    
    // Reset the access control whitelist settings to prevent admin lockout
    $access_settings = get_option('fcs_settings_access_control', array());
    if (isset($access_settings['enable_whitelist']) && $access_settings['enable_whitelist']) {
        $access_settings['enable_whitelist'] = false;
        update_option('fcs_settings_access_control', $access_settings);
    }
    
    // Flush rewrite rules on deactivation
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'fcs_deactivate' );

/**
 * Register uninstall hook.
 */
function fcs_uninstall() {
    // Clean up plugin options.
    delete_option( 'fcs_hidden_menu_items' );
    delete_option( 'fcs_menu_hiding_settings' );
    delete_option( 'fcs_cpt_without_base' );
    delete_option( 'fcs_misc_settings' );
    delete_option( 'fcs_gtm_settings' );
    delete_option( 'fcs_settings_access_control' );
    
    // Flush rewrite rules on uninstall
    flush_rewrite_rules();
}
register_uninstall_hook( __FILE__, 'fcs_uninstall' );
