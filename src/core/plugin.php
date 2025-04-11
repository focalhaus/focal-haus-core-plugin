<?php
/**
 * Main plugin class.
 *
 * @package Focal_Core_Settings
 * @subpackage Core
 */

namespace FocalCore\core;

use FocalCore\admin\Settings;
use FocalCore\MenuHiding\MenuHiding;
use FocalCore\Permalinks\Permalinks;
use FocalCore\misc\Misc;
use FocalCore\GTM\GTM;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class Plugin.
 *
 * Main plugin class to handle initialization and loading of modules.
 */
class Plugin {

    /**
     * Instance of this class.
     *
     * @var object
     */
    protected static $instance = null;

    /**
     * Initialize the plugin.
     *
     */
    private function __construct() {
        // Load plugin text domain.
        add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );
        
        // Initialize modules
        $this->init_modules();
    }

    /**
     * Return an instance of this class.
     *
     * @return object A single instance of this class.
     */
    public static function get_instance() {
        // If the single instance hasn't been set, set it now.
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Load the plugin text domain for translation.
     *
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'focal-core-settings',
            false,
            dirname( FCS_PLUGIN_BASENAME ) . '/languages/'
        );
    }
    
    /**
     * Initialize all modules.
     *
     */
    private function init_modules() {
        // Initialize admin settings
        if ( is_admin() ) {
            Settings::get_instance();
        }
        
        // Initialize menu hiding module
        MenuHiding::get_instance();
        
        // Initialize permalinks module
        Permalinks::get_instance();
        
        // Initialize misc module
        Misc::get_instance();
        
        // Initialize Google Tag Manager module
        GTM::get_instance();
    }
}
