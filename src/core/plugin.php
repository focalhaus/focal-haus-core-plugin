<?php
/**
 * Main plugin class.
 *
 * @package Focal_Haus_Core
 * @subpackage Core
 */

namespace FocalHaus\core;

use FocalHaus\admin\Settings;
use FocalHaus\MenuHiding\MenuHiding;
use FocalHaus\Permalinks\Permalinks;
use FocalHaus\misc\Misc;
use FocalHaus\GTM\GTM;

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
     * @since 1.0.0
     * @var object
     */
    protected static $instance = null;

    /**
     * Initialize the plugin.
     *
     * @since 1.0.0
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
     * @since 1.0.0
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
     * @since 1.0.0
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'focal-haus-core',
            false,
            dirname( FHC_PLUGIN_BASENAME ) . '/languages/'
        );
    }
    
    /**
     * Initialize all modules.
     *
     * @since 1.0.0
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
