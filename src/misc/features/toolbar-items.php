<?php
/**
 * Remove Toolbar Items Feature.
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
 * Class for handling the toolbar items feature.
 */
class Toolbar_Items extends Feature_Base {
    
    /**
     * Feature identifier
     *
     * @var string
     */
    protected $id = 'toolbar_items';
    
    /**
     * Feature setting key in options array
     *
     * @var string
     */
    protected $setting_key = 'remove_toolbar_items';
    
    /**
     * Initialize the feature.
     */
    public function init_feature() {
        add_action('admin_bar_menu', array($this, 'remove_toolbar_nodes'), 999);
    }
    
    /**
     * Render the settings field.
     */
    public function render_settings_field() {
        $settings = $this->parent->get_settings();
        ?>
        <tr>
            <th scope="row">
                <label for="fhc_remove_toolbar_items">
                    <?php esc_html_e('Remove Toolbar Items', 'focal-haus-core'); ?>
                </label>
            </th>
            <td>
                <label>
                    <input type="checkbox" 
                           name="fhc_misc_settings[remove_toolbar_items]" 
                           id="fhc_remove_toolbar_items"
                           value="1" 
                           <?php checked(isset($settings['remove_toolbar_items']) && $settings['remove_toolbar_items']); ?> />
                    <?php esc_html_e('Remove WordPress logo and stats from the admin toolbar.', 'focal-haus-core'); ?>
                </label>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Remove items from WordPress admin toolbar.
     *
     * @param WP_Admin_Bar $wp_admin_bar The WP_Admin_Bar instance.
     */
    public function remove_toolbar_nodes($wp_admin_bar) {
        $wp_admin_bar->remove_node('wp-logo');
        $wp_admin_bar->remove_node('stats');
    }
}
