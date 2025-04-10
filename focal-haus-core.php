<?php
/**
 * Plugin Name: Focal Haus Core Plugin
 * Plugin URI: https://www.focalhaus.com/plugins/focal-haus-core
 * Description: A comprehensive plugin that provides multiple functionalities for WordPress sites, including hiding dashboard menu items, custom permalinks, plugin integrations, multilingual support, and Google Tag Manager integration.
 * Version: 1.1.6
 * Author: Focal Haus Dev Team
 * Author URI: https://www.focalhaus.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: focal-haus-core
 * Domain Path: /languages
 *
 * @package Focal_Haus_Core
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'FHC_VERSION', '1.1.6' );
define( 'FHC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FHC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FHC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Autoload plugin classes
require_once FHC_PLUGIN_DIR . 'src/core/autoloader.php';
new \FocalHaus\core\Autoloader();

// Initialize the plugin
add_action( 'plugins_loaded', function() {
    \FocalHaus\core\Plugin::get_instance();
}, 10 );

// Add a custom action for flushing rewrite rules
add_action( 'fhc_flush_rewrite_rules', 'flush_rewrite_rules' );

/**
 * Register activation hook.
 */
function fhc_activate() {
    // Migrate settings from old plugin if they exist
    $old_settings = get_option( 'hdmi_hidden_menu_items', false );
    if ( $old_settings !== false ) {
        update_option( 'fhc_hidden_menu_items', $old_settings );
        delete_option( 'hdmi_hidden_menu_items' );
    }
    
    // Set flag to force permalinks update
    update_option('fhc_permalinks_updated', '1.1.1');
    
    // Flush rewrite rules on activation
    flush_rewrite_rules();
    
    // Schedule another flush after a short delay to ensure all rules are registered
    wp_schedule_single_event( time() + 5, 'fhc_flush_rewrite_rules' );
}
register_activation_hook( __FILE__, 'fhc_activate' );

/**
 * Register deactivation hook.
 */
function fhc_deactivate() {
    // Clear scheduled events
    wp_clear_scheduled_hook( 'fhc_flush_rewrite_rules' );
    
    // Flush rewrite rules on deactivation
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'fhc_deactivate' );

/**
 * Register uninstall hook.
 */
function fhc_uninstall() {
    // Clean up plugin options.
    delete_option( 'fhc_hidden_menu_items' );
    delete_option( 'fhc_menu_hiding_settings' );
    delete_option( 'fhc_cpt_without_base' );
    delete_option( 'fhc_misc_settings' );
    delete_option( 'fhc_gtm_settings' );
    
    // Flush rewrite rules on uninstall
    flush_rewrite_rules();
}
register_uninstall_hook( __FILE__, 'fhc_uninstall' );
