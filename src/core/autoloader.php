<?php
/**
 * Autoloader class.
 *
 * @package Focal_Haus_Core
 * @subpackage Core
 */

namespace FocalHaus\core;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class Autoloader.
 *
 * PSR-4 autoloader for plugin classes.
 */
class Autoloader {

    /**
     * Constructor.
     */
    public function __construct() {
        spl_autoload_register( array( $this, 'autoload' ) );
    }

    /**
     * Autoloader method.
     *
     * @param string $class_name Fully qualified class name.
     */
    public function autoload( $class_name ) {
        // Only load our own classes.
        if ( strpos( $class_name, 'FocalHaus\\' ) !== 0 ) {
            return;
        }

        // Remove namespace prefix.
        $class_name = str_replace( 'FocalHaus\\', '', $class_name );

        // Convert class name to file path.
        $file_path = str_replace( '\\', DIRECTORY_SEPARATOR, $class_name );
        
        // Handle special cases for directory names
        $file_path = str_replace('MenuHiding', 'menu-hiding', $file_path);
        $file_path = str_replace('Misc', 'misc', $file_path);
        $file_path = str_replace('features/Feature_Base', 'features/feature-base', $file_path);
        $file_path = str_replace('features/Duplicate_Slugs', 'features/duplicate-slugs', $file_path);
        $file_path = str_replace('features/Toolbar_Items', 'features/toolbar-items', $file_path);
        $file_path = str_replace('features/SEOPress_Access', 'features/seopress-access', $file_path);
        $file_path = str_replace('features/Login_Logo', 'features/login-logo', $file_path);
        $file_path = str_replace('features/Theme_Editor', 'features/theme-editor', $file_path);
        $file_path = str_replace('features/Plugin_Editor', 'features/plugin-editor', $file_path);
        $file_path = str_replace('features/Disable_Bundled_Themes', 'features/disable-bundled-themes', $file_path);
        
        $file_path = FHC_PLUGIN_DIR . 'src' . DIRECTORY_SEPARATOR . $file_path . '.php';

        // If the file exists, require it.
        if ( file_exists( $file_path ) ) {
            require_once $file_path;
        }
    }
}
