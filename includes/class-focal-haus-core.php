<?php
/**
 * Main plugin class.
 *
 * @package Focal_Haus_Core
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Main plugin class.
 */
class Focal_Haus_Core {

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
        // Load required files
        $this->load_files();
        
        // Initialize admin settings
        if ( is_admin() ) {
            FHC_Settings::get_instance();
        }
        
        // Initialize menu hiding module
        FHC_Menu_Hiding::get_instance();
        
        // Initialize permalinks module
        FHC_Permalinks::get_instance();
        
        // Initialize integrations module
        new Focal_Haus_Core_Integrations();
    }
    
    /**
     * Load required files.
     *
     * @since 1.0.0
     */
    private function load_files() {
        // Admin
        require_once FHC_PLUGIN_DIR . 'includes/admin/class-settings.php';
        
        // Modules
        require_once FHC_PLUGIN_DIR . 'includes/modules/class-menu-hiding.php';
        require_once FHC_PLUGIN_DIR . 'includes/modules/class-permalinks.php';
        require_once FHC_PLUGIN_DIR . 'includes/modules/class-integrations.php';
    }
}
