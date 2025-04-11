<?php
/**
 * Miscellaneous functionality.
 *
 * @package Focal_Haus_Core
 * @subpackage Misc
 */

namespace FocalHaus\misc;

use FocalHaus\misc\features\Duplicate_Slugs;
use FocalHaus\misc\features\Toolbar_Items;
use FocalHaus\misc\features\SEOPress_Access;
use FocalHaus\misc\features\Login_Logo;
use FocalHaus\misc\features\Theme_Editor;
use FocalHaus\misc\features\Plugin_Editor;
use FocalHaus\misc\features\Disable_Bundled_Themes;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class for handling miscellaneous functionality.
 */
class Misc {

    /**
     * Instance of this class.
     *
     * @since 0.2.0
     * @var object
     */
    protected static $instance = null;

    /**
     * Option name for storing settings.
     *
     * @var string
     */
    private $option_name = 'fhc_misc_settings';
    
    /**
     * Default settings.
     *
     * @var array
     */
    private $default_settings = array(
        'allow_duplicate_slugs' => false,
        'remove_toolbar_items' => false,
        'seopress_editor_access' => false,
        'custom_login_logo' => false,
        'login_logo_url' => '',
        'disable_theme_editor' => false,
        'disable_plugin_editor' => false,
        'disable_bundled_themes' => false,
    );
    
    /**
     * Settings array.
     *
     * @var array
     */
    private $settings = array();
    
    /**
     * Features array.
     *
     * @var array
     */
    private $features = array();

    /**
     * Initialize the class.
     *
     */
    private function __construct() {
        // Load settings
        $this->load_settings();
        
        // Initialize features
        $this->init_features();
        
        // Add admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
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
     * Load settings from the database.
     *
     */
    private function load_settings() {
        $this->settings = get_option($this->option_name, $this->default_settings);
        
        // Ensure all default settings exist
        $this->settings = wp_parse_args($this->settings, $this->default_settings);
    }
    
    /**
     * Get current settings.
     *
     * @return array The current settings.
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * Initialize features based on settings.
     *
     */
    private function init_features() {
        // Register all features
        $this->features = array(
            'duplicate_slugs' => new Duplicate_Slugs($this),
            'toolbar_items' => new Toolbar_Items($this),
            'seopress_access' => new SEOPress_Access($this),
            'login_logo' => new Login_Logo($this),
            'theme_editor' => new Theme_Editor($this),
            'plugin_editor' => new Plugin_Editor($this),
            'disable_bundled_themes' => new Disable_Bundled_Themes($this),
        );
    }
    
    /**
     * Enqueue scripts and styles for the admin area.
     *
     * @param string $hook The current admin page.
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our settings page
        if ('settings_page_focal-haus-core' !== $hook) {
            return;
        }
        
        // Enqueue the WordPress media scripts
        wp_enqueue_media();
        
        // Enqueue custom script for media uploader
        wp_enqueue_script(
            'fhc-media-uploader',
            FHC_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            FHC_VERSION,
            true
        );
    }

    /**
     * Render the tab content for the Misc settings.
     *
     */
    public function render_tab_content() {
        // Check if the form has been submitted
        if (isset($_POST['submit']) && isset($_POST['fhc_misc_nonce'])) {
            // Verify nonce
            if (check_admin_referer('fhc_save_misc_settings', 'fhc_misc_nonce')) {
                // Get the old settings to check if we need to reinitialize capabilities
                $old_settings = $this->settings;
                
                // Process checkbox settings
                $checkboxes = array(
                    'allow_duplicate_slugs',
                    'remove_toolbar_items',
                    'seopress_editor_access',
                    'custom_login_logo',
                    'disable_theme_editor',
                    'disable_plugin_editor',
                    'disable_bundled_themes'
                );
                
                foreach ($checkboxes as $checkbox) {
                    $this->settings[$checkbox] = isset($_POST['fhc_misc_settings'][$checkbox]) ? true : false;
                }
                
                // Process the login logo URL
                $this->settings['login_logo_url'] = isset($_POST['fhc_misc_settings']['login_logo_url']) 
                    ? esc_url_raw($_POST['fhc_misc_settings']['login_logo_url']) 
                    : '';
                
                // Save settings
                update_option($this->option_name, $this->settings);
                
                // Add admin notice for successful save
                add_settings_error(
                    'fhc_messages',
                    'fhc_message',
                    __('Settings saved.', 'focal-haus-core'),
                    'updated'
                );
                
                // Reload settings
                $this->load_settings();
                
                // Reinitialize features
                $this->init_features();
                
                // Special handling for SEOPress editor access
                if ($old_settings['seopress_editor_access'] != $this->settings['seopress_editor_access']) {
                    // Flush rewrite rules when SEOPress access is toggled
                    flush_rewrite_rules();
                }
            } else {
                // Nonce verification failed
                add_settings_error(
                    'fhc_messages',
                    'fhc_message',
                    __('Nonce verification failed. Settings not saved.', 'focal-haus-core'),
                    'error'
                );
            }
        }
        
        // Display settings errors/notices
        settings_errors('fhc_messages');
        
        // Render the form
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('fhc_save_misc_settings', 'fhc_misc_nonce'); ?>
            
            <h2><?php esc_html_e('WordPress Features', 'focal-haus-core'); ?></h2>
            
            <table class="form-table">
                <tbody>
                    <?php 
                    // Render WordPress features
                    $this->features['duplicate_slugs']->render_settings_field();
                    $this->features['toolbar_items']->render_settings_field();
                    $this->features['theme_editor']->render_settings_field();
                    $this->features['plugin_editor']->render_settings_field();
                    $this->features['disable_bundled_themes']->render_settings_field();
                    ?>
                </tbody>
            </table>
            
            <h2><?php esc_html_e('Plugin Integrations', 'focal-haus-core'); ?></h2>
            
            <table class="form-table">
                <tbody>
                    <?php 
                    // Render Plugin Integration features
                    $this->features['seopress_access']->render_settings_field();
                    ?>
                </tbody>
            </table>
            
            <h2><?php esc_html_e('Branding', 'focal-haus-core'); ?></h2>
            
            <table class="form-table">
                <tbody>
                    <?php 
                    // Render Branding features
                    $this->features['login_logo']->render_settings_field();
                    ?>
                </tbody>
            </table>
            
            <?php submit_button(); ?>
        </form>
        <?php
    }
}
