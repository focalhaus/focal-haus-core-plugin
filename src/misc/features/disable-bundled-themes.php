<?php
/**
 * Skip New Bundled Themes Feature.
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
 * Class for handling the skip new bundled themes feature.
 */
class Disable_Bundled_Themes extends Feature_Base {
    
    /**
     * Feature identifier
     *
     * @var string
     */
    protected $id = 'disable_bundled_themes';
    
    /**
     * Feature setting key in options array
     *
     * @var string
     */
    protected $setting_key = 'disable_bundled_themes';
    
    /**
     * Initialize the feature.
     */
    public function init_feature() {
        // Define the constant to skip bundled themes during core updates
        if (!defined('CORE_UPGRADE_SKIP_NEW_BUNDLED')) {
            define('CORE_UPGRADE_SKIP_NEW_BUNDLED', true);
        }
        
        // Add a filter to skip bundled themes for cases where the constant might not be respected
        add_filter('pre_site_option_update_core', array($this, 'modify_update_core_option'), 999);
        add_filter('pre_option_update_core', array($this, 'modify_update_core_option'), 999);
    }
    
    /**
     * Render the settings field.
     */
    public function render_settings_field() {
        $settings = $this->parent->get_settings();
        ?>
        <tr>
            <th scope="row">
                <label for="fhc_disable_bundled_themes">
                    <?php esc_html_e('Skip New Bundled Themes', 'focal-haus-core'); ?>
                </label>
            </th>
            <td>
                <label>
                    <input type="checkbox" 
                           name="fhc_misc_settings[disable_bundled_themes]" 
                           id="fhc_disable_bundled_themes"
                           value="1" 
                           <?php checked(isset($settings['disable_bundled_themes']) && $settings['disable_bundled_themes']); ?> />
                    <?php esc_html_e('Skip installation of new bundled themes during WordPress core updates.', 'focal-haus-core'); ?>
                </label>
                <p class="description">
                    <?php esc_html_e('When WordPress updates to a new version, it typically includes the latest default theme. Enabling this option prevents these new themes from being automatically installed.', 'focal-haus-core'); ?>
                </p>
                <?php if (defined('CORE_UPGRADE_SKIP_NEW_BUNDLED') && CORE_UPGRADE_SKIP_NEW_BUNDLED && !$this->is_enabled()): ?>
                <p class="description" style="color: #d63638;">
                    <?php esc_html_e('Note: The CORE_UPGRADE_SKIP_NEW_BUNDLED constant is already defined elsewhere in your installation.', 'focal-haus-core'); ?>
                </p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Modify the update core option to skip bundled themes.
     *
     * @param mixed $value The current value of the option.
     * @return mixed The modified value.
     */
    public function modify_update_core_option($value) {
        if (empty($value) || !is_object($value)) {
            return $value;
        }
        
        // Modify the response to skip bundled themes
        if (isset($value->response) && is_array($value->response)) {
            foreach ($value->response as $key => $update) {
                if (isset($update->new_bundled)) {
                    $update->new_bundled = false;
                }
            }
        }
        
        return $value;
    }
}
