<?php
/**
 * SEOPress Editor Access Feature.
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
 * Class for handling the SEOPress editor access feature.
 */
class SEOPress_Access extends Feature_Base {
    
    /**
     * Feature identifier
     *
     * @var string
     */
    protected $id = 'seopress_access';
    
    /**
     * Feature setting key in options array
     *
     * @var string
     */
    protected $setting_key = 'seopress_editor_access';
    
    /**
     * Initialize the feature.
     */
    public function init_feature() {
        add_filter('seopress_capability', array($this, 'seopress_grant_full_access_to_editors'), 10, 2);
        add_action('init', array($this, 'seopress_add_editor_caps_to_redirections'));
    }
    
    /**
     * Render the settings field.
     */
    public function render_settings_field() {
        $settings = $this->parent->get_settings();
        ?>
        <tr>
            <th scope="row">
                <label for="fhc_seopress_editor_access">
                    <?php esc_html_e('SEOPress | Give Editors Full Rights', 'focal-haus-core'); ?>
                </label>
            </th>
            <td>
                <label>
                    <input type="checkbox" 
                           name="fhc_misc_settings[seopress_editor_access]" 
                           id="fhc_seopress_editor_access"
                           value="1" 
                           <?php checked(isset($settings['seopress_editor_access']) && $settings['seopress_editor_access']); ?> />
                    <?php esc_html_e('Grant Editor role full access to SEOPress features, including redirections.', 'focal-haus-core'); ?>
                </label>
                <p class="description">
                    <?php esc_html_e('This feature requires the SEOPress plugin to be active.', 'focal-haus-core'); ?>
                </p>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Grant full access to SEOPress features for editors.
     *
     * @param string $cap The capability being checked.
     * @param string $context The context of the check.
     * @return string The modified capability.
     */
    public function seopress_grant_full_access_to_editors($cap, $context) {
        if ($context === 'redirections' || $context === 'manage_redirections') {
            $cap = 'edit_posts'; // Allow editors access to redirections
        } else {
            $cap = 'edit_posts'; // Default access for all other SEOPress sections
        }
        return $cap;
    }
    
    /**
     * Add editor capabilities for SEOPress redirections.
     *
     */
    public function seopress_add_editor_caps_to_redirections() {
        $role = get_role('editor');
        if ($role) {
            $role->add_cap('edit_seopress_404');     // Allow editing redirections
            $role->add_cap('edit_others_seopress_404'); // Allow editing others' redirections
            $role->add_cap('publish_seopress_404');  // Allow publishing redirections
            $role->add_cap('delete_seopress_404');   // Allow deleting redirections
        }
    }
}
