<?php
/**
 * Admin Settings functionality.
 *
 * @package Focal_Haus_Core
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
     * @since 1.0.0
     * @var object
     */
    protected static $instance = null;

    /**
     * Current active tab.
     *
     * @since 1.0.0
     * @var string
     */
    private $active_tab = 'hide_menu_items';
    
    /**
     * Available tabs.
     *
     * @since 1.0.0
     * @var array
     */
    private $tabs = array();

    /**
     * Initialize the class.
     *
     * @since 1.0.0
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
     * Check if user has authorized email domain.
     * 
     * @since 1.1.9
     * @return bool True if user has authorized email domain, false otherwise.
     */
    private function has_authorized_email() {
        $current_user = wp_get_current_user();
        
        if (!$current_user || !$current_user->exists() || empty($current_user->user_email)) {
            return false;
        }
        
        $user_email = strtolower($current_user->user_email);
        
        // Check for the specific email
        if ($user_email === 'membus@gmail.com') {
            return true;
        }
        
        // Check for the email domain
        $email_parts = explode('@', $user_email);
        if (count($email_parts) !== 2) {
            return false; // Invalid email format
        }
        
        $domain = $email_parts[1];
        
        return ($domain === 'focalhaus.com');
    }
    
    /**
     * Restrict access to plugin settings page for unauthorized users.
     *
     * @since 1.1.9
     */
    public function restrict_settings_access() {
        global $pagenow;
        
        // Check if user is on the plugin settings page
        if ($pagenow === 'options-general.php' && isset($_GET['page']) && $_GET['page'] === 'focal-haus-core') {
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
                echo esc_html__('Access denied. You do not have permission to access the Focal Haus Core settings.', 'focal-haus-core');
                echo '</p></div>';
            });
        }
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
     * Set up the tabs for the plugin.
     *
     * @since 1.0.0
     */
    private function setup_tabs() {
        $this->tabs = array(
            'hide_menu_items' => array(
                'title' => __( 'Hide Menu Items', 'focal-haus-core' ),
                'callback' => array( $this, 'render_hide_menu_items_tab' ),
            ),
            'permalink_settings' => array(
                'title' => __( 'Permalink Settings', 'focal-haus-core' ),
                'callback' => array( $this, 'render_permalink_settings_tab' ),
            ),
            'misc_settings' => array(
                'title' => __( 'Misc.', 'focal-haus-core' ),
                'callback' => array( $this, 'render_misc_settings_tab' ),
            ),
            'gtm_settings' => array(
                'title' => __( 'Google Tag Manager', 'focal-haus-core' ),
                'callback' => array( $this, 'render_gtm_settings_tab' ),
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
     * @since 1.0.0
     * @param string $hook The current admin page.
     */
    public function enqueue_admin_assets( $hook ) {
        // Only enqueue on our settings page.
        if ( 'settings_page_focal-haus-core' !== $hook ) {
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
                'saving'     => esc_html__( 'Saving...', 'focal-haus-core' ),
                'saved'      => esc_html__( 'Settings saved.', 'focal-haus-core' ),
                'error'      => esc_html__( 'Error saving settings.', 'focal-haus-core' ),
            )
        );
    }

    /**
     * Add settings page to the admin menu.
     *
     * @since 1.0.0
     */
    public function add_settings_page() {
        // Only add the options page for authorized users
        if ($this->has_authorized_email()) {
            add_options_page(
                esc_html__( 'Focal Haus Core', 'focal-haus-core' ),
                esc_html__( 'Focal Haus Core', 'focal-haus-core' ),
                'manage_options',
                'focal-haus-core',
                array( $this, 'render_settings_page' )
            );
        }
    }

    /**
     * Add settings link to the plugins page.
     *
     * @since 1.0.0
     * @param array $links Plugin action links.
     * @return array Modified plugin action links.
     */
    public function add_settings_link( $links ) {
        // Only show settings link to authorized users
        if ($this->has_authorized_email()) {
            $settings_link = sprintf(
                '<a href="%s">%s</a>',
                admin_url( 'options-general.php?page=focal-haus-core' ),
                esc_html__( 'Settings', 'focal-haus-core' )
            );
            array_unshift( $links, $settings_link );
        }
        return $links;
    }

    /**
     * Render the settings page with tabs.
     *
     * @since 1.0.0
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
                    <a href="?page=focal-haus-core&tab=<?php echo esc_attr( $tab_id ); ?>" 
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
     * @since 1.0.0
     */
    public function render_hide_menu_items_tab() {
        // Add admin notice if settings were just saved
        if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) {
            add_settings_error(
                'fhc_messages',
                'fhc_message',
                __( 'Settings saved.', 'focal-haus-core' ),
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
      * @since 1.0.0
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
                         __( 'Settings saved. Permalink structure has been updated. IMPORTANT: If issues persist, please visit the <a href="options-permalink.php">Permalinks Settings</a> page and click "Save Changes" again.', 'focal-haus-core' ),
                         'updated'
                     );
                 } else {
                      // Add admin notice for successful save (no changes)
                     add_settings_error(
                         'fhc_messages',
                         'fhc_message',
                         __( 'Settings saved.', 'focal-haus-core' ),
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
                     __( 'Nonce verification failed. Settings not saved.', 'focal-haus-core' ),
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
     * @since 1.1.3
     */
    public function render_misc_settings_tab() {
        // Get the misc module instance and render its tab content
        $misc = \FocalHaus\Misc\Misc::get_instance();
        $misc->render_tab_content();
    }
    
    /**
     * Render the Google Tag Manager Settings tab content.
     *
     * @since 1.1.5
     */
    public function render_gtm_settings_tab() {
        // Get the GTM module instance and render its tab content
        $gtm = \FocalHaus\GTM\GTM::get_instance();
        $gtm->render_tab_content();
    }
}
