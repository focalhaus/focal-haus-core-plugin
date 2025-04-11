<?php
/**
 * Disable Theme Editor Feature.
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
 * Class for handling the theme editor feature.
 */
class Theme_Editor extends Feature_Base {
    
    /**
     * Feature identifier
     *
     * @var string
     */
    protected $id = 'theme_editor';
    
    /**
     * Feature setting key in options array
     *
     * @var string
     */
    protected $setting_key = 'disable_theme_editor';
    
    /**
     * Initialize the feature.
     */
    public function init_feature() {
        // This feature is handled during plugin initialization
        // by setting the DISALLOW_FILE_EDIT constant
        if (!defined('DISALLOW_FILE_EDIT')) {
            define('DISALLOW_FILE_EDIT', true);
        }
    }
    
    /**
     * Render the settings field.
     */
    public function render_settings_field() {
        $settings = $this->parent->get_settings();
        ?>
        <tr>
            <th scope="row">
                <label for="fhc_disable_theme_editor">
                    <?php esc_html_e('Disable Theme Editor', 'focal-haus-core'); ?>
                </label>
            </th>
            <td>
                <label>
                    <input type="checkbox" 
                           name="fhc_misc_settings[disable_theme_editor]" 
                           id="fhc_disable_theme_editor"
                           value="1" 
                           <?php checked(isset($settings['disable_theme_editor']) && $settings['disable_theme_editor']); ?> />
                    <?php esc_html_e('Disable the WordPress theme file editor for security.', 'focal-haus-core'); ?>
                </label>
                <p class="description">
                    <?php esc_html_e('This setting will take effect after the page is reloaded.', 'focal-haus-core'); ?>
                </p>
            </td>
        </tr>
        <?php
    }
}
