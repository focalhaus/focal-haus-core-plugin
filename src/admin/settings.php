<?php
/**
 * Admin Settings functionality.
 *
 * @package Focal_Core_Settings
 * @subpackage Admin
 */

namespace FocalHaus\admin;

use FocalHaus\MenuHiding\MenuHiding;
use FocalHaus\Permalinks\Permalinks;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class for handling admin settings.
 */
class Settings {

    /**
     * Instance of this class.
     *
     * @since 0.2.0
     * @var object
     */
    protected static $instance = null;

    /**
     * Current active tab.
     *
     * @var string
     */
    private $active_tab = 'hide_menu_items';
    
    /**
     * Available tabs.
     *
     * @var array
     */
    private $tabs = array();

    /**
     * Initialize the class.
     *
     */
    private function __construct() {
        // Set up tabs
        $this->setup_tabs();
        
        // Add settings page.
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        
        // Add settings link on plugin page.
        add_filter( 'plugin_action_links_' . FHC_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );
        
        // Enqueue admin scripts and styles.
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        
        // Restrict access to plugin settings page for unauthorized users
        add_action( 'admin_init', array( $this, 'restrict_settings_access' ) );
    }
    
    /**
     * Check if user has authorized email access.
     * 
     * @return bool True if user has authorized email, false otherwise.
     */
    private function has_authorized_email() {
        $current_user = wp_get_current_user();
        
        if (!$current_user || !$current_user->exists() || empty($current_user->user_email)) {
            return false;
        }
        
        // Get settings page access control settings
        $access_settings = get_option('fhc_settings_access_control', array(
            'enable_whitelist' => false,
            'whitelist' => array()
        ));
        
        // If the settings page access whitelist is enabled, check against it
        if (!empty($access_settings['enable_whitelist'])) {
            if (!isset($access_settings['whitelist']) || !is_array($access_settings['whitelist']) || empty($access_settings['whitelist'])) {
                return false;
            }
            
            $user_email = strtolower($current_user->user_email);
            
            // Check if the user's email is in the whitelist
            foreach ($access_settings['whitelist'] as $whitelisted_email) {
                if ($user_email === strtolower(trim($whitelisted_email))) {
                    return true;
                }
            }
            
            return false;
        }
        
        // If whitelist is not enabled, all admin users can access
        return true;
    }
    
    /**
     * Restrict access to plugin settings page for unauthorized users.
     *
     */
    public function restrict_settings_access() {
        global $pagenow;
        
        // Check if user is on the plugin settings page
        if ($pagenow === 'options-general.php' && isset($_GET['page']) && $_GET['page'] === 'focal-core-settings') {
            // Verify if user has authorized email domain
            if (!$this->has_authorized_email()) {
                // Redirect to admin dashboard with error message
                wp_redirect(add_query_arg(
                    'fhc-access-denied', 
                    '1', 
                    admin_url('index.php')
                ));
                exit;
            }
        }
        
        // Show admin notice for access denied
        if (isset($_GET['fhc-access-denied']) && $_GET['fhc-access-denied'] === '1') {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>';
                echo esc_html__('Access denied. You do not have permission to access the Focal Core Settings.', 'focal-core-settings');
                echo '</p></div>';
            });
        }
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
     * Set up the tabs for the plugin.
     *
     */
    private function setup_tabs() {
        $this->tabs = array(
            'hide_menu_items' => array(
                'title' => __( 'Hide Menu Items', 'focal-core-settings' ),
                'callback' => array( $this, 'render_hide_menu_items_tab' ),
            ),
            'permalink_settings' => array(
                'title' => __( 'Permalink Settings', 'focal-core-settings' ),
                'callback' => array( $this, 'render_permalink_settings_tab' ),
            ),
            'misc_settings' => array(
                'title' => __( 'Misc.', 'focal-core-settings' ),
                'callback' => array( $this, 'render_misc_settings_tab' ),
            ),
            'gtm_settings' => array(
                'title' => __( 'Google Tag Manager', 'focal-core-settings' ),
                'callback' => array( $this, 'render_gtm_settings_tab' ),
            ),
            'access_control' => array(
                'title' => __( 'Access Control', 'focal-core-settings' ),
                'callback' => array( $this, 'render_access_control_tab' ),
            ),
            // Additional tabs can be added here
        );

        // Set active tab from query string if available
        if ( isset( $_GET['tab'] ) && array_key_exists( $_GET['tab'], $this->tabs ) ) {
            $this->active_tab = sanitize_key( $_GET['tab'] );
        }
    }
    
    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook The current admin page.
     */
    public function enqueue_admin_assets( $hook ) {
        // Only enqueue on our settings page.
        if ( 'settings_page_focal-core-settings' !== $hook ) {
            return;
        }
        
        // Enqueue admin styles.
        wp_enqueue_style(
            'fhc-admin-styles',
            FHC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            FHC_VERSION,
            'all'
        );
        
        // Enqueue admin scripts.
        wp_enqueue_script(
            'fhc-admin-scripts',
            FHC_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            FHC_VERSION,
            true
        );
        
        // Localize the script with translation strings.
        wp_localize_script(
            'fhc-admin-scripts',
            'fhcL10n',
            array(
                'saving'     => esc_html__( 'Saving...', 'focal-core-settings' ),
                'saved'      => esc_html__( 'Settings saved.', 'focal-core-settings' ),
                'error'      => esc_html__( 'Error saving settings.', 'focal-core-settings' ),
            )
        );
    }

    /**
     * Add settings page to the admin menu.
     *
     */
    public function add_settings_page() {
        // Only add the options page for authorized users
        if ($this->has_authorized_email()) {
            add_options_page(
                esc_html__( 'Focal Core Settings', 'focal-core-settings' ),
                esc_html__( 'Focal Core Settings', 'focal-core-settings' ),
                'manage_options',
                'focal-core-settings',
                array( $this, 'render_settings_page' )
            );
        }
    }

    /**
     * Add settings link to the plugins page.
     *
     * @param array $links Plugin action links.
     * @return array Modified plugin action links.
     */
    public function add_settings_link( $links ) {
        // Only show settings link to authorized users
        if ($this->has_authorized_email()) {
            $settings_link = sprintf(
                '<a href="%s">%s</a>',
                admin_url( 'options-general.php?page=focal-core-settings' ),
                esc_html__( 'Settings', 'focal-core-settings' )
            );
            array_unshift( $links, $settings_link );
        }
        return $links;
    }

    /**
     * Render the settings page with tabs.
     *
     */
    public function render_settings_page() {
        // Check user capabilities.
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <?php foreach ( $this->tabs as $tab_id => $tab ) : ?>
                    <a href="?page=focal-core-settings&tab=<?php echo esc_attr( $tab_id ); ?>" 
                       class="nav-tab <?php echo $this->active_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html( $tab['title'] ); ?>
                    </a>
                <?php endforeach; ?>
            </h2>
            
            <div class="tab-content">
                <?php
                // Call the active tab's callback function
                if ( isset( $this->tabs[ $this->active_tab ]['callback'] ) ) {
                    call_user_func( $this->tabs[ $this->active_tab ]['callback'] );
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the Hide Menu Items tab content.
     *
     */
    public function render_hide_menu_items_tab() {
        // Add admin notice if settings were just saved
        if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) {
            add_settings_error(
                'fhc_messages',
                'fhc_message',
                __( 'Settings saved.', 'focal-core-settings' ),
                'updated'
            );
        }
        
        // Get the menu hiding module instance and render its tab content
        $menu_hiding = MenuHiding::get_instance();
        $menu_hiding->render_tab_content();
    }

    /**
     * Render the Permalink Settings tab content.
     *
      */
    public function render_permalink_settings_tab() {
        global $wp_post_types; // Needed for checking post types during save

        // Check if the form has been submitted
         if ( isset( $_POST['submit'] ) && isset( $_POST['fhc_permalink_nonce'] ) ) {
             // Verify nonce
             if ( check_admin_referer( 'fhc_save_permalink_settings', 'fhc_permalink_nonce' ) ) {

                 // Sanitize and prepare data for saving
                 $sanitized_input = array();
                 $selected = isset( $_POST['fhc_cpt_without_base']['selected'] ) && is_array( $_POST['fhc_cpt_without_base']['selected'] ) ? $_POST['fhc_cpt_without_base']['selected'] : array();
                 $alternation = isset( $_POST['fhc_cpt_without_base']['alternation'] ) && is_array( $_POST['fhc_cpt_without_base']['alternation'] ) ? $_POST['fhc_cpt_without_base']['alternation'] : array();

                 foreach ( $selected as $post_type => $value ) {
                     // Check if the post type actually exists before saving
                     if ( $value && isset( $wp_post_types[ $post_type ] ) ) {
                         $post_type_key = sanitize_key( $post_type );
                         // Store 1 if alternation is checked for this post type, 0 otherwise
                         $sanitized_input[ $post_type_key ] = isset( $alternation[ $post_type_key ] ) ? 1 : 0;
                     }
                 }

                 // Get current saved options to compare
                 $current_options = get_option('fhc_cpt_without_base', array());

                 // Update the option in the database
                 update_option( 'fhc_cpt_without_base', $sanitized_input );

                 // Flush rewrite rules only if the settings have actually changed
                 if ($current_options !== $sanitized_input) {
                     flush_rewrite_rules();
                     // Add admin notice for successful save and flush reminder
                     add_settings_error(
                         'fhc_messages',
                         'fhc_message',
                         __( 'Settings saved. Permalink structure has been updated. IMPORTANT: If issues persist, please visit the <a href="options-permalink.php">Permalinks Settings</a> page and click "Save Changes" again.', 'focal-core-settings' ),
                         'updated'
                     );
                 } else {
                      // Add admin notice for successful save (no changes)
                     add_settings_error(
                         'fhc_messages',
                         'fhc_message',
                         __( 'Settings saved.', 'focal-core-settings' ),
                         'updated'
                     );
                 }

                 // Reload the permalinks instance to reflect saved changes immediately on the page
                 // This requires the Permalinks class to have a public method to reload options,
                 // or we simply reinstantiate it here for rendering purposes.
                 // For simplicity, we'll let the existing instance render, which will use the newly saved options on next page load.

             } else {
                 // Nonce verification failed
                 add_settings_error(
                     'fhc_messages',
                     'fhc_message',
                     __( 'Nonce verification failed. Settings not saved.', 'focal-core-settings' ),
                     'error'
                 );
             }
         }

         // Display settings errors/notices
         settings_errors( 'fhc_messages' );

         // Get the permalinks module instance and render its tab content
         // The render_tab_content method will now fetch the latest options when displaying the form
         $permalinks = Permalinks::get_instance();
         // We need to ensure the instance reloads its internal options if we want immediate reflection
         // A simple way is to add a public reload method to Permalinks class, or just call load_cpt_without_base again if it's public.
         // Let's assume render_tab_content fetches fresh options via get_option() internally.
         $permalinks->render_tab_content();
    }
    
    /**
     * Render the Misc Settings tab content.
     *
     */
    public function render_misc_settings_tab() {
        // Get the misc module instance and render its tab content
        $misc = \FocalHaus\Misc\Misc::get_instance();
        $misc->render_tab_content();
    }
    
    /**
     * Render the Google Tag Manager Settings tab content.
     *
     */
    public function render_gtm_settings_tab() {
        // Get the GTM module instance and render its tab content
        $gtm = \FocalHaus\GTM\GTM::get_instance();
        $gtm->render_tab_content();
    }
    
    /**
     * Render the Access Control tab content.
     *
     */
    public function render_access_control_tab() {
        // Process form submission
        if (isset($_POST['fhc_access_control_submit']) && isset($_POST['fhc_access_control_nonce'])) {
            // Verify nonce
            if (check_admin_referer('fhc_save_access_control_settings', 'fhc_access_control_nonce')) {
                // Get current settings
                $current_settings = get_option('fhc_settings_access_control', array(
                    'enable_whitelist' => false,
                    'whitelist' => array()
                ));
                
                // Get and sanitize form data
                $enable_whitelist = isset($_POST['fhc_enable_settings_access_whitelist']) ? true : false;
                
                // Process whitelist emails
                $whitelist_emails = isset($_POST['fhc_settings_access_whitelist']) ? sanitize_textarea_field($_POST['fhc_settings_access_whitelist']) : '';
                $whitelist_array = array();
                
                if (!empty($whitelist_emails)) {
                    // Split by line breaks or commas
                    $emails_raw = preg_split('/[\r\n,]+/', $whitelist_emails);
                    
                    foreach ($emails_raw as $email) {
                        $email = trim($email);
                        if (!empty($email) && is_email($email)) {
                            $whitelist_array[] = sanitize_email($email);
                        }
                    }
                }
                
                // FAIL-SAFE: If enabling the whitelist, ensure current user's email is included
                if ($enable_whitelist) {
                    $current_user = wp_get_current_user();
                    $current_user_email = sanitize_email($current_user->user_email);
                    
                    // Add current user's email if not already in the whitelist
                    if (!in_array($current_user_email, $whitelist_array)) {
                        $whitelist_array[] = $current_user_email;
                        
                        // Add notice that user's email was added
                        add_settings_error(
                            'fhc_access_control_messages',
                            'fhc_access_control_email_added',
                            sprintf(__('Your email address (%s) was automatically added to the whitelist to prevent lockout.', 'focal-core-settings'), $current_user_email),
                            'info'
                        );
                    }
                }
                
                // Save settings
                $new_settings = array(
                    'enable_whitelist' => $enable_whitelist,
                    'whitelist' => $whitelist_array
                );
                
                update_option('fhc_settings_access_control', $new_settings);
                
                // Add success message
                add_settings_error(
                    'fhc_access_control_messages',
                    'fhc_access_control_message',
                    __('Access control settings saved successfully.', 'focal-core-settings'),
                    'updated'
                );
            } else {
                // Nonce verification failed
                add_settings_error(
                    'fhc_access_control_messages',
                    'fhc_access_control_message',
                    __('Security check failed. Settings not saved.', 'focal-core-settings'),
                    'error'
                );
            }
        }
        
        // Display settings errors/notices
        settings_errors('fhc_access_control_messages');
        
        // Get current settings
        $settings = get_option('fhc_settings_access_control', array(
            'enable_whitelist' => false,
            'whitelist' => array()
        ));
        
        // Format whitelist emails for textarea
        $whitelist_emails = !empty($settings['whitelist']) ? implode("\n", $settings['whitelist']) : '';
        
        ?>
        <div class="fhc-access-control-settings">
            <form method="post" action="">
                <?php wp_nonce_field('fhc_save_access_control_settings', 'fhc_access_control_nonce'); ?>
                
                <h2><?php esc_html_e('Plugin Settings Access Control', 'focal-core-settings'); ?></h2>
                <p class="description">
                    <?php esc_html_e('Control which admin users can access the Focal Core Settings plugin settings page.', 'focal-core-settings'); ?>
                </p>
                
                <div class="notice notice-info">
                    <p>
                        <?php esc_html_e('By default, all admin users can access the plugin settings page.', 'focal-core-settings'); ?>
                        <?php esc_html_e('Enable the whitelist below to restrict access to only specific email addresses.', 'focal-core-settings'); ?>
                    </p>
                </div>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="fhc_enable_settings_access_whitelist">
                                <?php esc_html_e('Enable Settings Access Whitelist', 'focal-core-settings'); ?>
                            </label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="fhc_enable_settings_access_whitelist" id="fhc_enable_settings_access_whitelist" 
                                    <?php checked($settings['enable_whitelist'], true); ?>>
                                <?php esc_html_e('Enable settings page access whitelist', 'focal-core-settings'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('When enabled, only the specific email addresses listed below will have access to the plugin settings page.', 'focal-core-settings'); ?>
                                <br>
                                <?php esc_html_e('When disabled, all admin users can access the plugin settings page.', 'focal-core-settings'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="fhc_settings_access_whitelist">
                                <?php esc_html_e('Settings Access Whitelist', 'focal-core-settings'); ?>
                            </label>
                        </th>
                        <td>
                            <textarea name="fhc_settings_access_whitelist" id="fhc_settings_access_whitelist" rows="8" cols="50" class="large-text code"><?php echo esc_textarea($whitelist_emails); ?></textarea>
                            <p class="description">
                                <?php esc_html_e('Enter one email address per line or separated by commas.', 'focal-core-settings'); ?>
                                <br>
                                <?php esc_html_e('Only these email addresses will be able to access the plugin settings page when the whitelist is enabled.', 'focal-core-settings'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p>
                    <input type="submit" name="fhc_access_control_submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'focal-core-settings'); ?>">
                </p>
            </form>
        </div>
        <?php
    }
}
