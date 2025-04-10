<?php
/**
 * Google Tag Manager functionality.
 *
 * @package Focal_Haus_Core
 * @subpackage gtm
 */

namespace FocalHaus\GTM;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class for handling Google Tag Manager functionality.
 */
class GTM {

    /**
     * Instance of this class.
     *
     * @since 1.1.5
     * @var object
     */
    protected static $instance = null;

    /**
     * Initialize the class.
     *
     * @since 1.1.5
     */
    private function __construct() {
        // Register settings.
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // Add GTM script to header
        add_action( 'wp_head', array( $this, 'add_gtm_head_script' ), 10 );
        
        // Add GTM noscript to body
        add_action( 'wp_body_open', array( $this, 'add_gtm_body_script' ), 1 );
    }

    /**
     * Return an instance of this class.
     *
     * @since 1.1.5
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
     * Register plugin settings.
     *
     * @since 1.1.5
     */
    public function register_settings() {
        // First, make sure the options exist to avoid initialization issues
        if ( false === get_option( 'fhc_gtm_settings' ) ) {
            add_option( 'fhc_gtm_settings', array(
                'enabled' => false,
                'gtm_id' => '',
                'exclude_logged_in' => true,
                'track_subscribers' => false,
            ) );
        }
        
        register_setting(
            'fhc_settings',
            'fhc_gtm_settings',
            array(
                'sanitize_callback' => array( $this, 'sanitize_gtm_settings' ),
                'default'           => array(
                    'enabled' => false,
                    'gtm_id' => '',
                    'exclude_logged_in' => true,
                    'track_subscribers' => false,
                ),
            )
        );
    }

    /**
     * Sanitize GTM settings.
     *
     * @since 1.1.5
     * @param array|string $input The value being saved.
     * @return array Sanitized value.
     */
    public function sanitize_gtm_settings( $input ) {
        $sanitized_input = array();
        
        // Sanitize enabled (boolean)
        $sanitized_input['enabled'] = isset( $input['enabled'] ) && $input['enabled'] ? true : false;
        
        // Sanitize GTM ID (string)
        if ( isset( $input['gtm_id'] ) ) {
            // Only allow valid GTM ID format (GTM-XXXXXX)
            if ( preg_match( '/^GTM-[A-Z0-9]+$/', $input['gtm_id'] ) ) {
                $sanitized_input['gtm_id'] = sanitize_text_field( $input['gtm_id'] );
            } else {
                // If invalid format, keep the old value or empty string
                $old_settings = get_option( 'fhc_gtm_settings', array() );
                $sanitized_input['gtm_id'] = isset( $old_settings['gtm_id'] ) ? $old_settings['gtm_id'] : '';
                
                // Add error message
                add_settings_error(
                    'fhc_gtm_settings',
                    'invalid_gtm_id',
                    __( 'Invalid Google Tag Manager ID format. Please use the format GTM-XXXXXX.', 'focal-haus-core' ),
                    'error'
                );
            }
        } else {
            $sanitized_input['gtm_id'] = '';
        }
        
        // Sanitize exclude_logged_in (boolean)
        $sanitized_input['exclude_logged_in'] = isset( $input['exclude_logged_in'] ) && $input['exclude_logged_in'] ? true : false;
        
        // Sanitize track_subscribers (boolean)
        $sanitized_input['track_subscribers'] = isset( $input['track_subscribers'] ) && $input['track_subscribers'] ? true : false;
        
        return $sanitized_input;
    }

    /**
     * Add Google Tag Manager script to header.
     *
     * @since 1.1.5
     */
    public function add_gtm_head_script() {
        $gtm_settings = get_option( 'fhc_gtm_settings', array() );
        
        // Check if GTM is enabled and ID is set
        $enabled = isset( $gtm_settings['enabled'] ) ? $gtm_settings['enabled'] : false;
        $gtm_id = isset( $gtm_settings['gtm_id'] ) ? $gtm_settings['gtm_id'] : '';
        $exclude_logged_in = isset( $gtm_settings['exclude_logged_in'] ) ? $gtm_settings['exclude_logged_in'] : true;
        $track_subscribers = isset( $gtm_settings['track_subscribers'] ) ? $gtm_settings['track_subscribers'] : false;
        
        if ( $enabled && ! empty( $gtm_id ) ) {
            // Check if we should exclude logged-in users
            if ( $exclude_logged_in && is_user_logged_in() ) {
                // If track_subscribers is enabled, check if the current user is a subscriber
                if ( $track_subscribers && current_user_can( 'subscriber' ) && !current_user_can( 'edit_posts' ) ) {
                    // Allow tracking for subscribers
                } else {
                    // Skip for other logged-in users
                    return;
                }
            }
            
            // Output the GTM head script
            ?>
            <!-- Google Tag Manager -->
            <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer','<?php echo esc_js( $gtm_id ); ?>');</script>
            <!-- End Google Tag Manager -->
            <?php
        }
    }

    /**
     * Add Google Tag Manager noscript to body.
     *
     * @since 1.1.5
     */
    public function add_gtm_body_script() {
        $gtm_settings = get_option( 'fhc_gtm_settings', array() );
        
        // Check if GTM is enabled and ID is set
        $enabled = isset( $gtm_settings['enabled'] ) ? $gtm_settings['enabled'] : false;
        $gtm_id = isset( $gtm_settings['gtm_id'] ) ? $gtm_settings['gtm_id'] : '';
        $exclude_logged_in = isset( $gtm_settings['exclude_logged_in'] ) ? $gtm_settings['exclude_logged_in'] : true;
        $track_subscribers = isset( $gtm_settings['track_subscribers'] ) ? $gtm_settings['track_subscribers'] : false;
        
        if ( $enabled && ! empty( $gtm_id ) ) {
            // Check if we should exclude logged-in users
            if ( $exclude_logged_in && is_user_logged_in() ) {
                // If track_subscribers is enabled, check if the current user is a subscriber
                if ( $track_subscribers && current_user_can( 'subscriber' ) && !current_user_can( 'edit_posts' ) ) {
                    // Allow tracking for subscribers
                } else {
                    // Skip for other logged-in users
                    return;
                }
            }
            
            // Output the GTM body script
            ?>
            <!-- Google Tag Manager (noscript) -->
            <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr( $gtm_id ); ?>"
            height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
            <!-- End Google Tag Manager (noscript) -->
            <?php
        }
    }

    /**
     * Render the Google Tag Manager tab content.
     *
     * @since 1.1.5
     */
    public function render_tab_content() {
        // Get saved settings.
        $gtm_settings = get_option( 'fhc_gtm_settings', array(
            'enabled' => false,
            'gtm_id' => '',
            'exclude_logged_in' => true,
        ) );
        
        ?>
        <div class="fhc-tab-description">
            <p><?php esc_html_e( 'Configure Google Tag Manager settings for your website.', 'focal-haus-core' ); ?></p>
        </div>
        
        <form method="post" action="options.php">
            <?php
            // Output security fields.
            settings_fields( 'fhc_settings' );
            ?>
            
            <div class="fhc-section-info">
                <p><?php esc_html_e( 'Google Tag Manager helps you manage and deploy marketing tags on your website without modifying the code.', 'focal-haus-core' ); ?></p>
            </div>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="fhc_gtm_enabled">
                            <?php esc_html_e( 'Enable Google Tag Manager', 'focal-haus-core' ); ?>
                        </label>
                    </th>
                    <td>
                        <input 
                            type="checkbox" 
                            id="fhc_gtm_enabled" 
                            name="fhc_gtm_settings[enabled]" 
                            value="1" 
                            <?php checked( isset( $gtm_settings['enabled'] ) && $gtm_settings['enabled'] ); ?>
                        >
                        <span class="description">
                            <?php esc_html_e( 'Enable Google Tag Manager for your website.', 'focal-haus-core' ); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="fhc_gtm_id">
                            <?php esc_html_e( 'Google Tag Manager ID', 'focal-haus-core' ); ?>
                        </label>
                    </th>
                    <td>
                        <input 
                            type="text" 
                            id="fhc_gtm_id" 
                            name="fhc_gtm_settings[gtm_id]" 
                            value="<?php echo esc_attr( isset( $gtm_settings['gtm_id'] ) ? $gtm_settings['gtm_id'] : '' ); ?>" 
                            class="regular-text"
                            placeholder="GTM-XXXXXXX"
                        >
                        <p class="description">
                            <?php esc_html_e( 'Enter your Google Tag Manager ID (e.g., GTM-XXXXXXX).', 'focal-haus-core' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="fhc_gtm_exclude_logged_in">
                            <?php esc_html_e( 'Exclude Logged-in Users', 'focal-haus-core' ); ?>
                        </label>
                    </th>
                    <td>
                        <input 
                            type="checkbox" 
                            id="fhc_gtm_exclude_logged_in" 
                            name="fhc_gtm_settings[exclude_logged_in]" 
                            value="1" 
                            <?php checked( isset( $gtm_settings['exclude_logged_in'] ) && $gtm_settings['exclude_logged_in'] ); ?>
                        >
                        <span class="description">
                            <?php esc_html_e( 'Enable this to exclude tracking of logged-in users.', 'focal-haus-core' ); ?>
                        </span>
                    </td>
                </tr>
                <tr class="fhc-track-subscribers-row" <?php echo (!isset($gtm_settings['exclude_logged_in']) || !$gtm_settings['exclude_logged_in']) ? 'style="display:none;"' : ''; ?>>
                    <th scope="row">
                        <label for="fhc_gtm_track_subscribers">
                            <?php esc_html_e( 'Track Subscribers Only', 'focal-haus-core' ); ?>
                        </label>
                    </th>
                    <td>
                        <input 
                            type="checkbox" 
                            id="fhc_gtm_track_subscribers" 
                            name="fhc_gtm_settings[track_subscribers]" 
                            value="1" 
                            <?php checked( isset( $gtm_settings['track_subscribers'] ) && $gtm_settings['track_subscribers'] ); ?>
                        >
                        <span class="description">
                            <?php esc_html_e( 'Enable this to still track subscriber-level users even when excluding other logged-in users.', 'focal-haus-core' ); ?>
                        </span>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
            
            <div class="fhc-code-preview">
                <h3><?php esc_html_e( 'Code Preview', 'focal-haus-core' ); ?></h3>
                
                <div class="fhc-code-container">
                    <h4><?php esc_html_e( 'Header Code (wp_head)', 'focal-haus-core' ); ?></h4>
                    <pre><?php
                    $gtm_id = isset( $gtm_settings['gtm_id'] ) && !empty( $gtm_settings['gtm_id'] ) ? $gtm_settings['gtm_id'] : 'GTM-XXXXXX';
                    $exclude_logged_in = isset( $gtm_settings['exclude_logged_in'] ) && $gtm_settings['exclude_logged_in'];
                    $track_subscribers = isset( $gtm_settings['track_subscribers'] ) && $gtm_settings['track_subscribers'];
                    
                    if (!$exclude_logged_in) {
                        echo esc_html( '<?php /* All users are tracked */ ?>' . "\n" );
                        $condition = 'true';
                    } elseif ($exclude_logged_in && !$track_subscribers) {
                        echo esc_html( '<?php /* Only non-logged-in users are tracked */ ?>' . "\n" );
                        $condition = '! is_user_logged_in()';
                    } else {
                        echo esc_html( '<?php /* Non-logged-in users and subscribers are tracked */ ?>' . "\n" );
                        $condition = '! is_user_logged_in() || (current_user_can("subscriber") && !current_user_can("edit_posts"))';
                    }
                    
                    echo esc_html( '<?php if ( ' . $condition . ' ) : ?>' . "\n" );
                    echo esc_html( '    <!-- Google Tag Manager -->' . "\n" );
                    echo esc_html( '    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({"gtm.start":' . "\n" );
                    echo esc_html( '    new Date().getTime(),event:"gtm.js"});var f=d.getElementsByTagName(s)[0],' . "\n" );
                    echo esc_html( '    j=d.createElement(s),dl=l!="dataLayer"?"&l="+l:"";j.async=true;j.src=' . "\n" );
                    echo esc_html( '    "https://www.googletagmanager.com/gtm.js?id="+i+dl;f.parentNode.insertBefore(j,f);' . "\n" );
                    echo esc_html( '    })(window,document,"script","dataLayer","' . $gtm_id . '");</script>' . "\n" );
                    echo esc_html( '    <!-- End Google Tag Manager -->' . "\n" );
                    echo esc_html( '<?php endif; ?>' );
                    ?></pre>
                </div>
                
                <div class="fhc-code-container">
                    <h4><?php esc_html_e( 'Body Code (wp_body_open)', 'focal-haus-core' ); ?></h4>
                    <pre><?php
                    if (!$exclude_logged_in) {
                        echo esc_html( '<?php /* All users are tracked */ ?>' . "\n" );
                        $condition = 'true';
                    } elseif ($exclude_logged_in && !$track_subscribers) {
                        echo esc_html( '<?php /* Only non-logged-in users are tracked */ ?>' . "\n" );
                        $condition = '! is_user_logged_in()';
                    } else {
                        echo esc_html( '<?php /* Non-logged-in users and subscribers are tracked */ ?>' . "\n" );
                        $condition = '! is_user_logged_in() || (current_user_can("subscriber") && !current_user_can("edit_posts"))';
                    }
                    
                    echo esc_html( '<?php if ( ' . $condition . ' ) : ?>' . "\n" );
                    echo esc_html( '    <!-- Google Tag Manager (noscript) -->' . "\n" );
                    echo esc_html( '    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . $gtm_id . '"' . "\n" );
                    echo esc_html( '    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n" );
                    echo esc_html( '    <!-- End Google Tag Manager (noscript) -->' . "\n" );
                    echo esc_html( '<?php endif; ?>' );
                    ?></pre>
                </div>
            </div>
        </form>
        <?php
    }
}
