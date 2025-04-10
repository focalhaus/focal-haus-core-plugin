<?php
/**
 * Admin Settings functionality.
 *
 * @package Focal_Haus_Core
 * @subpackage Admin
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class for handling admin settings.
 */
class FHC_Settings {

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
                'checkAll'   => esc_html__( 'Check All', 'focal-haus-core' ),
                'uncheckAll' => esc_html__( 'Uncheck All', 'focal-haus-core' ),
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
        add_options_page(
            esc_html__( 'Focal Haus Core', 'focal-haus-core' ),
            esc_html__( 'Focal Haus Core', 'focal-haus-core' ),
            'manage_options',
            'focal-haus-core',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Add settings link to the plugins page.
     *
     * @since 1.0.0
     * @param array $links Plugin action links.
     * @return array Modified plugin action links.
     */
    public function add_settings_link( $links ) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'options-general.php?page=focal-haus-core' ),
            esc_html__( 'Settings', 'focal-haus-core' )
        );
        array_unshift( $links, $settings_link );
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
        $menu_hiding = FHC_Menu_Hiding::get_instance();
        $menu_hiding->render_tab_content();
    }

    /**
     * Render the Permalink Settings tab content.
     *
     * @since 1.0.0
     */
    public function render_permalink_settings_tab() {
        // Add admin notice if settings were just saved
        if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) {
            add_settings_error(
                'fhc_messages',
                'fhc_message',
                __( 'Settings saved. Permalink structure has been updated. IMPORTANT: Please visit the <a href="options-permalink.php">Permalinks Settings</a> page and click "Save Changes" to ensure all changes take effect. You may need to do this twice.', 'focal-haus-core' ),
                'updated'
            );
            
            // Force flush rewrite rules immediately
            flush_rewrite_rules();
        }
        
        // Get the permalinks module instance and render its tab content
        $permalinks = FHC_Permalinks::get_instance();
        $permalinks->render_tab_content();
    }
}
