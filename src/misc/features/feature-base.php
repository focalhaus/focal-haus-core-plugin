<?php
/**
 * Base Feature class for the Miscellaneous module.
 *
 * @package Focal_Haus_Core
 * @subpackage Misc
 */

namespace FocalHaus\misc\features;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Abstract base class for features.
 */
abstract class Feature_Base {
    
    /**
     * Feature identifier
     *
     * @var string
     */
    protected $id = '';
    
    /**
     * Feature setting key in options array
     *
     * @var string
     */
    protected $setting_key = '';
    
    /**
     * Stores the parent Misc class instance
     *
     * @var object
     */
    protected $parent;
    
    /**
     * Constructor.
     *
     * @param object $parent The parent Misc class instance.
     */
    public function __construct( $parent ) {
        $this->parent = $parent;
        
        // Initialize the feature if it's enabled
        if ( $this->is_enabled() ) {
            $this->init_feature();
        }
    }
    
    /**
     * Check if this feature is enabled.
     *
     * @return boolean True if the feature is enabled, false otherwise.
     */
    public function is_enabled() {
        $settings = $this->parent->get_settings();
        return isset( $settings[$this->setting_key] ) && $settings[$this->setting_key];
    }
    
    /**
     * Initialize the feature.
     */
    abstract public function init_feature();
    
    /**
     * Render the settings field.
     */
    abstract public function render_settings_field();
}
