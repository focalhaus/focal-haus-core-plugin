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
     * @var object
     */
    protected static $instance = null;

    /**
     * Initialize the class.
     *
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
     */
    public function register_settings() {
        // First, make sure the options exist to avoid initialization issues
        if ( false === get_option( 'fhc_gtm_settings' ) ) {
            add_option( 'fhc_gtm_settings', array(
                'enabled' => false,
                'gtm_id' => '',
                'exclude_logged_in' => true,
                'excluded_roles' => array(),
            ) );
        } else {
            // Migrate old settings if needed (from track_subscribers to excluded_roles)
            $current_settings = get_option( 'fhc_gtm_settings' );
            if (!isset($current_settings['excluded_roles']) && isset($current_settings['track_subscribers'])) {
                // If track_subscribers was enabled, we don't want to exclude subscribers
                if ($current_settings['track_subscribers']) {
                    $current_settings['excluded_roles'] = array(
                        'administrator' => 1,
                        'editor' => 1,
                        'author' => 1,
                        'contributor' => 1,
                    );
                } else {
                    // If track_subscribers was disabled, we want to exclude all roles
                    $current_settings['excluded_roles'] = array(
                        'administrator' => 1,
                        'editor' => 1,
                        'author' => 1,
                        'contributor' => 1,
                        'subscriber' => 1,
                    );
                }
                unset($current_settings['track_subscribers']);
                update_option('fhc_gtm_settings', $current_settings);
            }
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
                    'excluded_roles' => array(
                        'administrator' => 1,
                        'editor' => 1,
                        'author' => 1,
                        'contributor' => 1,
                        'subscriber' => 1,
                    ),
                ),
            )
        );
    }

    /**
     * Sanitize GTM settings.
     *
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
        
        // Sanitize excluded_roles (array)
        $sanitized_input['excluded_roles'] = array();
        if (isset($input['excluded_roles']) && is_array($input['excluded_roles'])) {
            foreach ($input['excluded_roles'] as $role => $value) {
                if ($value) {
                    $sanitized_input['excluded_roles'][sanitize_key($role)] = 1;
                }
            }
        }
        
        return $sanitized_input;
    }

    /**
     * Add Google Tag Manager script to header.
     *
     */
    public function add_gtm_head_script() {
        $gtm_settings = get_option( 'fhc_gtm_settings', array() );
        
        // Check if GTM is enabled and ID is set
        $enabled = isset( $gtm_settings['enabled'] ) ? $gtm_settings['enabled'] : false;
        $gtm_id = isset( $gtm_settings['gtm_id'] ) ? $gtm_settings['gtm_id'] : '';
        
        if ( $enabled && ! empty( $gtm_id ) ) {
            // Check if the current user should be tracked
            if (!$this->should_track_current_user()) {
                return; // Skip tracking for this user
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
     */
    public function add_gtm_body_script() {
        $gtm_settings = get_option( 'fhc_gtm_settings', array() );
        
        // Check if GTM is enabled and ID is set
        $enabled = isset( $gtm_settings['enabled'] ) ? $gtm_settings['enabled'] : false;
        $gtm_id = isset( $gtm_settings['gtm_id'] ) ? $gtm_settings['gtm_id'] : '';
        
        if ( $enabled && ! empty( $gtm_id ) ) {
            // Check if the current user should be tracked
            if (!$this->should_track_current_user()) {
                return; // Skip tracking for this user
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
     * Get all available WordPress roles.
     * 
     * @since 0.2.5
     * @return array Array of role slugs and display names.
     */
    private function get_available_roles() {
        global $wp_roles;
        
        if (!isset($wp_roles)) {
            $wp_roles = new \WP_Roles();
        }
        
        $all_roles = array();
        foreach ($wp_roles->roles as $role_slug => $role_info) {
            $all_roles[$role_slug] = translate_user_role($role_info['name']);
        }
        
        // Sort roles alphabetically by display name
        asort($all_roles);
        
        // Move administrator, editor, author, contributor, and subscriber to the top in that order
        $ordered_roles = array();
        $core_roles = array('administrator', 'editor', 'author', 'contributor', 'subscriber');
        
        foreach ($core_roles as $core_role) {
            if (isset($all_roles[$core_role])) {
                $ordered_roles[$core_role] = $all_roles[$core_role];
                unset($all_roles[$core_role]);
            }
        }
        
        // Add remaining roles
        $ordered_roles = array_merge($ordered_roles, $all_roles);
        
        return $ordered_roles;
    }

    /**
     * Check if current user should be tracked.
     * 
     * @since 0.2.5
     * @return bool True if user should be tracked, false otherwise.
     */
    private function should_track_current_user() {
        $gtm_settings = get_option('fhc_gtm_settings', array());
        
        // If not excluding logged-in users, track everyone
        $exclude_logged_in = isset($gtm_settings['exclude_logged_in']) ? $gtm_settings['exclude_logged_in'] : true;
        if (!$exclude_logged_in) {
            return true;
        }
        
        // If excluding logged-in but user is not logged in, track them
        if (!is_user_logged_in()) {
            return true;
        }
        
        // At this point, user is logged in and we're excluding logged-in users
        // But we need to check if their role is in the excluded list
        
        $excluded_roles = isset($gtm_settings['excluded_roles']) ? $gtm_settings['excluded_roles'] : array();
        
        // If no roles are excluded, don't track any logged-in users
        if (empty($excluded_roles)) {
            return false;
        }
        
        // Check if any of the user's roles are excluded
        $current_user = wp_get_current_user();
        foreach ($current_user->roles as $role) {
            if (isset($excluded_roles[$role]) && $excluded_roles[$role]) {
                return false; // This role is excluded, don't track
            }
        }
        
        // If we get here, the user is logged in but none of their roles are excluded
        return true;
    }

    /**
     * Render the Google Tag Manager tab content.
     *
     */
    public function render_tab_content() {
        // Get saved settings.
        $gtm_settings = get_option( 'fhc_gtm_settings', array(
            'enabled' => false,
            'gtm_id' => '',
            'exclude_logged_in' => true,
            'excluded_roles' => array(
                'administrator' => 1,
                'editor' => 1,
                'author' => 1,
                'contributor' => 1,
                'subscriber' => 1,
            ),
        ) );
        
        // Get all available roles
        $all_roles = $this->get_available_roles();
        
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
                <tr class="fhc-exclude-roles-row" <?php echo (!isset($gtm_settings['exclude_logged_in']) || !$gtm_settings['exclude_logged_in']) ? 'style="display:none;"' : ''; ?>>
                    <th scope="row">
                        <label>
                            <?php esc_html_e( 'Exclude User Roles', 'focal-haus-core' ); ?>
                        </label>
                    </th>
                    <td>
                        <div class="fhc-roles-container">
                            <p class="description">
                                <?php esc_html_e( 'Select which user roles should be excluded from tracking. Users with any of these roles will not be tracked.', 'focal-haus-core' ); ?>
                            </p>
                            
                            <div class="fhc-roles-grid">
                                <?php foreach ( $all_roles as $role_slug => $role_name ) : ?>
                                    <div class="fhc-role-item">
                                        <label>
                                            <input 
                                                type="checkbox" 
                                                name="fhc_gtm_settings[excluded_roles][<?php echo esc_attr( $role_slug ); ?>]" 
                                                value="1" 
                                                <?php checked( isset( $gtm_settings['excluded_roles'][$role_slug] ) && $gtm_settings['excluded_roles'][$role_slug] ); ?>
                                            >
                                            <span><?php echo esc_html( $role_name ); ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="fhc-roles-actions">
                                <button type="button" class="button fhc-select-all-roles"><?php esc_html_e( 'Select All', 'focal-haus-core' ); ?></button>
                                <button type="button" class="button fhc-select-none-roles"><?php esc_html_e( 'Select None', 'focal-haus-core' ); ?></button>
                            </div>
                        </div>
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
                    $excluded_roles = isset( $gtm_settings['excluded_roles'] ) ? $gtm_settings['excluded_roles'] : array();
                    
                    if (!$exclude_logged_in) {
                        echo esc_html( '<?php /* All users are tracked */ ?>' . "\n" );
                        $condition = 'true';
                    } elseif ($exclude_logged_in && empty($excluded_roles)) {
                        echo esc_html( '<?php /* Only non-logged-in users are tracked */ ?>' . "\n" );
                        $condition = '! is_user_logged_in()';
                    } else {
                        // Get the roles that are excluded
                        $excluded_role_list = array();
                        foreach ($excluded_roles as $role => $value) {
                            if ($value) {
                                $excluded_role_list[] = $role;
                            }
                        }
                        
                        if (count($excluded_role_list) === count($all_roles)) {
                            // All roles are excluded
                            echo esc_html( '<?php /* Only non-logged-in users are tracked */ ?>' . "\n" );
                            $condition = '! is_user_logged_in()';
                        } else {
                            // Some roles are excluded
                            $excluded_role_names = array();
                            foreach ($excluded_role_list as $role) {
                                if (isset($all_roles[$role])) {
                                    $excluded_role_names[] = $all_roles[$role];
                                }
                            }
                            
                            echo esc_html( '<?php /* Non-logged-in users and users without the following roles are tracked: ' . implode(', ', $excluded_role_names) . ' */ ?>' . "\n" );
                            
                            // Build the role check condition
                            $role_condition = array();
                            foreach ($excluded_role_list as $role) {
                                $role_condition[] = '!current_user_can("' . esc_js($role) . '")';
                            }
                            
                            $condition = '! is_user_logged_in() || (is_user_logged_in() && ' . implode(' && ', $role_condition) . ')';
                        }
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
                    } elseif ($exclude_logged_in && empty($excluded_roles)) {
                        echo esc_html( '<?php /* Only non-logged-in users are tracked */ ?>' . "\n" );
                        $condition = '! is_user_logged_in()';
                    } else {
                        // We already calculated this above, just reuse the condition
                        echo esc_html( '<?php /* Same tracking conditions as in header */ ?>' . "\n" );
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
