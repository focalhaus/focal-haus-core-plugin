<?php
/**
 * Menu Hiding functionality.
 *
 * @package Focal_Haus_Core
 * @subpackage menu-hiding
 */

namespace FocalHaus\MenuHiding;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class for handling menu hiding functionality.
 */
class MenuHiding {

    /**
     * Instance of this class.
     *
     * @since 1.0.0
     * @var object
     */
    protected static $instance = null;

    /**
     * Initialize the class.
     *
     * @since 1.0.0
     */
    private function __construct() {
        // Register settings.
        add_action('admin_init', array($this, 'register_settings'));

        // Hide menu items for non-admin users and non-whitelisted admin users.
        // Use an extremely high priority to ensure it runs after all other menu modifications
        add_action('admin_menu', array($this, 'hide_menu_items'), 9999);
        
        // Block access to hidden pages for non-admin users and non-whitelisted admin users.
        add_action('admin_init', array($this, 'block_hidden_page_access'), 1);
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
     * Register plugin settings.
     *
     * @since 1.0.0
     */
    public function register_settings() {
        // First, make sure the options exist to avoid initialization issues
        if ( false === get_option( 'fhc_hidden_menu_items' ) ) {
            add_option( 'fhc_hidden_menu_items', array() );
        }
        
        if ( false === get_option( 'fhc_menu_hiding_settings' ) ) {
            add_option( 'fhc_menu_hiding_settings', array(
                'use_whitelist' => false,
                'whitelisted_emails' => ''
            ) );
        }
        
        register_setting(
            'fhc_settings',
            'fhc_hidden_menu_items',
            array(
                'sanitize_callback' => array( $this, 'sanitize_hidden_menu_items' ),
                'default'           => array(),
            )
        );
        
        register_setting(
            'fhc_settings',
            'fhc_menu_hiding_settings',
            array(
                'sanitize_callback' => array( $this, 'sanitize_menu_hiding_settings' ),
                'default'           => array(
                    'use_whitelist' => false,
                    'whitelisted_emails' => ''
                ),
            )
        );
    }

    /**
     * Sanitize hidden menu items.
     *
     * @since 1.0.0
     * @param array|string $input The value being saved.
     * @return array Sanitized value.
     */
    public function sanitize_hidden_menu_items( $input ) {
        // If input is not an array, initialize an empty array
        if ( ! is_array( $input ) ) {
            $input = array();
        }

        $sanitized_input = array();
        
        // Build parent-child relationship map
        $parent_child_map = array();
        $child_parent_map = array();
        
        $menu_items = $this->get_admin_menu_items();
        
        // Create the maps
        foreach ( $menu_items as $key => $item ) {
            if ( $item['type'] === 'submenu' ) {
                $parent_slug = $item['parent'];
                $parent_key = $this->encode_menu_slug( $parent_slug );
                
                // Create parent->children map
                if ( !isset( $parent_child_map[$parent_key] ) ) {
                    $parent_child_map[$parent_key] = array();
                }
                $parent_child_map[$parent_key][] = $key;
                
                // Create child->parent map
                $child_parent_map[$key] = $parent_key;
            }
        }
        
        // Process input - first add all directly checked items
        foreach ( $input as $key => $value ) {
            if ( $value ) {
                $sanitized_input[$key] = 1;
                
                // If this is a parent, add all its children
                if ( isset( $parent_child_map[$key] ) ) {
                    foreach ( $parent_child_map[$key] as $child_key ) {
                        $sanitized_input[$child_key] = 1;
                    }
                }
            }
        }
        
        // For each submenu item, check if all siblings are checked
        foreach ( $child_parent_map as $child_key => $parent_key ) {
            // Skip if this submenu is not checked
            if ( empty( $sanitized_input[$child_key] ) ) {
                continue;
            }
            
            // Skip if parent is already checked
            if ( isset( $sanitized_input[$parent_key] ) ) {
                continue;
            }
            
            // Check if all siblings are checked
            $all_siblings_checked = true;
            foreach ( $parent_child_map[$parent_key] as $sibling_key ) {
                if ( empty( $sanitized_input[$sibling_key] ) ) {
                    $all_siblings_checked = false;
                    break;
                }
            }
            
            // If all siblings are checked, check the parent too
            if ( $all_siblings_checked ) {
                $sanitized_input[$parent_key] = 1;
            }
        }
        
        return $sanitized_input;
    }
    
    /**
     * Sanitize menu hiding settings.
     *
     * @since 1.1.4
     * @param array|string $input The value being saved.
     * @return array Sanitized value.
     */
    public function sanitize_menu_hiding_settings( $input ) {
        $sanitized_input = array();
        
        // Sanitize use_whitelist (boolean)
        $sanitized_input['use_whitelist'] = isset( $input['use_whitelist'] ) && $input['use_whitelist'] ? true : false;
        
        // Sanitize whitelisted emails (comma-separated list)
        if ( isset( $input['whitelisted_emails'] ) ) {
            $emails = explode( ',', $input['whitelisted_emails'] );
            $sanitized_emails = array();
            
            foreach ( $emails as $email ) {
                $email = trim( $email );
                if ( ! empty( $email ) && is_email( $email ) ) {
                    $sanitized_emails[] = sanitize_email( $email );
                }
            }
            
            $sanitized_input['whitelisted_emails'] = implode( ', ', $sanitized_emails );
        } else {
            $sanitized_input['whitelisted_emails'] = '';
        }
        
        return $sanitized_input;
    }

    /**
     * Encode a menu slug for use as an option key.
     *
     * @since 1.0.0
     * @param string $slug The menu slug to encode.
     * @return string The encoded slug.
     */
    public function encode_menu_slug( $slug ) {
        return base64_encode( $slug );
    }
    
    /**
     * Decode a menu slug from an option key.
     *
     * @since 1.0.0
     * @param string $encoded_slug The encoded menu slug.
     * @return string The decoded slug.
     */
    public function decode_menu_slug( $encoded_slug ) {
        return base64_decode( $encoded_slug );
    }

    /**
     * Clean menu title by removing update counts and notification strings.
     *
     * @since 1.0.0
     * @param string $title The menu title to clean.
     * @return string The cleaned menu title.
     */
    public function clean_menu_title( $title ) {
        // Remove update counts like " 0", " 1", etc. at the end of menu items
        $title = preg_replace( '/\s+\d+$/', '', $title );
        
        // Remove "X Comments in moderation" strings
        $title = preg_replace( '/\s+\d+\s+Comments?\s+in\s+moderation.*$/i', '', $title );
        
        // Remove other potential notification strings (can be expanded as needed)
        $title = preg_replace( '/\s+\(\d+\)$/', '', $title ); // Remove " (X)" format
        
        return trim( $title );
    }

    /**
     * Get all admin menu and submenu items.
     *
     * @since 1.0.0
     * @return array Admin menu and submenu items.
     */
    public function get_admin_menu_items() {
        global $menu, $submenu;
        
        // If $menu is not available yet, return empty array.
        if ( ! is_array( $menu ) ) {
            return array();
        }

        $menu_items = array();
        
        foreach ( $menu as $menu_item ) {
            if ( ! empty( $menu_item[0] ) && ! empty( $menu_item[2] ) ) {
                // Strip HTML tags from menu title and clean update counts
                $title = $this->clean_menu_title( wp_strip_all_tags( $menu_item[0] ) );
                
                // Skip separators.
                if ( empty( $title ) ) {
                    continue;
                }
                
                $menu_slug = $menu_item[2];
                $encoded_menu_slug = $this->encode_menu_slug( $menu_slug );
                
                $menu_items[ $encoded_menu_slug ] = array(
                    'title' => $title,
                    'type'  => 'menu',
                    'slug'  => $menu_slug,
                    'encoded_slug' => $encoded_menu_slug,
                );
                
                // Add submenu items if they exist.
                if ( isset( $submenu[ $menu_slug ] ) && is_array( $submenu[ $menu_slug ] ) ) {
                    foreach ( $submenu[ $menu_slug ] as $submenu_item ) {
                        if ( ! empty( $submenu_item[0] ) && ! empty( $submenu_item[2] ) ) {
                            $submenu_title = $this->clean_menu_title( wp_strip_all_tags( $submenu_item[0] ) );
                            $submenu_slug = $submenu_item[2];
                            
                            // Create a unique key for the submenu item.
                            $submenu_key = $menu_slug . '|' . $submenu_slug;
                            $encoded_submenu_key = $this->encode_menu_slug( $submenu_key );
                            
                            $menu_items[ $encoded_submenu_key ] = array(
                                'title'    => $submenu_title,
                                'type'     => 'submenu',
                                'slug'     => $submenu_slug,
                                'parent'   => $menu_slug,
                                'parent_title' => $title,
                                'encoded_slug' => $encoded_submenu_key,
                            );
                        }
                    }
                }
            }
        }
        
        return $menu_items;
    }

    /**
     * Check if current user should be able to see hidden menu items.
     * 
     * @since 1.1.4
     * @return bool True if user should see all items, false otherwise.
     */
    private function user_can_see_all_items() {
        // Super admins always see everything
        if (is_multisite() && is_super_admin()) {
            return true;
        }
        
        // Get whitelist settings
        $menu_hiding_settings = get_option('fhc_menu_hiding_settings', array());
        $use_whitelist = isset($menu_hiding_settings['use_whitelist']) && $menu_hiding_settings['use_whitelist'];
        
        // If user is not an admin, they never see restricted items
        if (!current_user_can('administrator')) {
            return false;
        }
        
        // If whitelist is disabled, admin sees everything
        if (!$use_whitelist) {
            return true;
        }
        
        // Whitelist is enabled - check if admin's email is in the whitelist
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;
        
        // Get whitelisted emails
        $whitelisted_emails = isset($menu_hiding_settings['whitelisted_emails']) ? 
            $menu_hiding_settings['whitelisted_emails'] : '';
        
        // Simple string search first (faster)
        if (strpos($whitelisted_emails, $user_email) === false) {
            return false;
        }
        
        // Detailed check with proper parsing
        $emails_array = array_map('trim', explode(',', $whitelisted_emails));
        return in_array($user_email, $emails_array);
    }

    /**
     * Block access to hidden pages for non-authorized users.
     * 
     * @since 1.0.0
     */
    public function block_hidden_page_access() {
        // Skip if user can see all items
        if ($this->user_can_see_all_items()) {
            return;
        }

        // Get hidden menu items
        $hidden_menu_items = get_option('fhc_hidden_menu_items', array());
        
        // If no items to hide, return
        if (empty($hidden_menu_items)) {
            return;
        }
        
        global $pagenow;
        
        // Get the current page and its parameters
        $current_page = $pagenow;
        $page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
        $post_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : '';
        $taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( $_GET['taxonomy'] ) : '';
        
        // Check if the current page is a hidden menu item
        foreach ( $hidden_menu_items as $encoded_key => $value ) {
            // Decode the key to get the original slug
            $decoded_key = $this->decode_menu_slug( $encoded_key );
            
            // Check if it's a submenu item (contains a pipe character).
            if ( strpos( $decoded_key, '|' ) !== false ) {
                list( $parent_slug, $submenu_slug ) = explode( '|', $decoded_key );
                
                // Check if current page matches this submenu
                $is_match = false;
                
                // Handle different types of admin pages
                if ( $parent_slug === 'edit.php' && $post_type && $current_page === 'edit.php' ) {
                    // Post type pages
                    if ( $submenu_slug === 'edit.php?post_type=' . $post_type || 
                         $submenu_slug === 'post-new.php?post_type=' . $post_type ) {
                        $is_match = true;
                    }
                } else if ( $parent_slug === 'edit.php' && $taxonomy && $current_page === 'edit-tags.php' ) {
                    // Taxonomy pages
                    if ( strpos( $submenu_slug, 'edit-tags.php?taxonomy=' . $taxonomy ) === 0 ) {
                        $is_match = true;
                    }
                } else if ( $current_page === 'admin.php' && $page ) {
                    // Admin pages with 'page' parameter
                    if ( $submenu_slug === $page || $submenu_slug === 'admin.php?page=' . $page ) {
                        $is_match = true;
                    }
                } else {
                    // Other admin pages
                    $current_full_url = $current_page . ( $page ? '?page=' . $page : '' );
                    if ( $submenu_slug === $current_full_url || $submenu_slug === $current_page ) {
                        $is_match = true;
                    }
                }
                
                if ( $is_match ) {
                    // Redirect to dashboard
                    wp_redirect( admin_url( 'index.php' ) );
                    exit;
                }
            } else {
                // It's a top-level menu item
                // Check if current page matches this menu
                $is_match = false;
                
                if ( $decoded_key === $current_page || 
                     ( $current_page === 'admin.php' && $page && $decoded_key === $page ) ||
                     ( $current_page === 'edit.php' && $post_type && $decoded_key === 'edit.php?post_type=' . $post_type ) ) {
                    $is_match = true;
                }
                
                if ( $is_match ) {
                    // Redirect to dashboard
                    wp_redirect( admin_url( 'index.php' ) );
                    exit;
                }
            }
        }
    }

    /**
     * Hide menu and submenu items for non-admin users or non-whitelisted admin users.
     *
     * @since 1.0.0
     */
    public function hide_menu_items() {
        // Skip if user can see all items
        if ($this->user_can_see_all_items()) {
            return;
        }

        // Get hidden menu items
        $hidden_menu_items = get_option('fhc_hidden_menu_items', array());
        
        // If no items to hide, return
        if (empty($hidden_menu_items)) {
            return;
        }

        global $menu, $submenu;
        
        // Ensure $menu is an array to prevent issues
        if (!is_array($menu)) {
            return;
        }
        
        // Hide each selected menu/submenu item
        foreach ($hidden_menu_items as $encoded_key => $value) {
            $decoded_key = $this->decode_menu_slug($encoded_key);
            
            // Handle submenu items
            if (strpos($decoded_key, '|') !== false) {
                list($parent_slug, $submenu_slug) = explode('|', $decoded_key);
                
                if (isset($submenu[$parent_slug])) {
                    foreach ($submenu[$parent_slug] as $index => $item) {
                        if ($item[2] === $submenu_slug) {
                            unset($submenu[$parent_slug][$index]);
                            break;
                        }
                    }
                }
            } else {
                // Handle top-level menu items
                foreach ($menu as $index => $item) {
                    if ($item[2] === $decoded_key) {
                        unset($menu[$index]);
                        // Also remove any submenu items
                        if (isset($submenu[$decoded_key])) {
                            unset($submenu[$decoded_key]);
                        }
                        break;
                    }
                }
            }
        }
    }



    /**
     * Render the Hide Menu Items tab content.
     *
     * @since 1.0.0
     */
    public function render_tab_content() {
        // Get saved settings.
        $hidden_menu_items = get_option( 'fhc_hidden_menu_items', array() );
        $menu_hiding_settings = get_option( 'fhc_menu_hiding_settings', array(
            'use_whitelist' => false,
            'whitelisted_emails' => ''
        ) );
        
        // Get all menu items.
        $menu_items = $this->get_admin_menu_items();
        
        ?>
        <div class="fhc-tab-description">
            <p><?php esc_html_e( 'Select which dashboard menu items should be hidden for non-admin users.', 'focal-haus-core' ); ?></p>
        </div>
        
        <form method="post" action="options.php">
            <?php
            // Output security fields.
            settings_fields( 'fhc_settings' );
            ?>
            
            <div class="fhc-section-info">
                <p><?php esc_html_e( 'Selecting menu items below will both hide them from the dashboard navigation AND prevent non-admin users from accessing these pages directly via URL.', 'focal-haus-core' ); ?></p>
            </div>
            
            <div class="fhc-section fhc-admin-whitelist-section">
                <h3><?php esc_html_e( 'Admin Whitelist Settings', 'focal-haus-core' ); ?></h3>
                <p class="description">
                    <?php esc_html_e( 'By default, admin users can see all menu items. Enable this option to restrict certain admin users.', 'focal-haus-core' ); ?>
                </p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="fhc_use_whitelist">
                                <?php esc_html_e( 'Use Admin Whitelist', 'focal-haus-core' ); ?>
                            </label>
                        </th>
                        <td>
                            <input 
                                type="checkbox" 
                                id="fhc_use_whitelist" 
                                name="fhc_menu_hiding_settings[use_whitelist]" 
                                value="1" 
                                <?php checked( isset( $menu_hiding_settings['use_whitelist'] ) && $menu_hiding_settings['use_whitelist'] ); ?>
                            >
                            <span class="description">
                                <?php esc_html_e( 'When enabled, only admin users with emails in the whitelist below will be exempt from menu hiding.', 'focal-haus-core' ); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="fhc_whitelisted_emails">
                                <?php esc_html_e( 'Whitelisted Admin Emails', 'focal-haus-core' ); ?>
                            </label>
                        </th>
                        <td>
                            <textarea 
                                id="fhc_whitelisted_emails"
                                name="fhc_menu_hiding_settings[whitelisted_emails]"
                                class="large-text code"
                                rows="4"
                                placeholder="admin@example.com, manager@example.com"
                            ><?php echo esc_textarea( isset( $menu_hiding_settings['whitelisted_emails'] ) ? $menu_hiding_settings['whitelisted_emails'] : '' ); ?></textarea>
                            <p class="description">
                                <?php esc_html_e( 'Enter comma-separated email addresses of admin users who should see all menu items.', 'focal-haus-core' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="fhc-section">
                <h3><?php esc_html_e( 'Menu Items to Hide', 'focal-haus-core' ); ?></h3>
                <div class="fhc-toggle-all-container">
                    <label class="fhc-toggle-all-label">
                        <input 
                            type="checkbox" 
                            id="fhc_toggle_all" 
                            class="fhc-toggle-all-checkbox"
                        >
                        <span id="fhc_toggle_all_text"><?php esc_html_e( 'Check All', 'focal-haus-core' ); ?></span>
                    </label>
                    <p class="description">
                        <?php esc_html_e( 'Quickly select or deselect all menu items at once.', 'focal-haus-core' ); ?>
                    </p>
                </div>
            </div>
            
            <div class="fhc-grid-container">
                <?php if ( empty( $menu_items ) ) : ?>
                    <div class="fhc-notice">
                        <?php esc_html_e( 'No menu items found. Please refresh this page.', 'focal-haus-core' ); ?>
                    </div>
                <?php else : ?>
                    <?php 
                    $current_parent = '';
                    $menu_sections = array();
                    
                    // Group items by parent menu
                    foreach ( $menu_items as $key => $item ) {
                        if ( $item['type'] === 'menu' ) {
                            $current_parent = $key;
                            $menu_sections[$current_parent] = array(
                                'title' => $item['title'],
                                'main' => $key,
                                'submenu' => array()
                            );
                        } else {
                            // Find the encoded parent key
                            $parent_slug = $item['parent'];
                            $parent_encoded = null;
                            
                            // Find the encoded key for the parent
                            foreach ($menu_sections as $section_key => $section) {
                                $decoded_key = $this->decode_menu_slug($section_key);
                                if ($decoded_key === $parent_slug) {
                                    $parent_encoded = $section_key;
                                    break;
                                }
                            }
                            
                            if ($parent_encoded && isset($menu_sections[$parent_encoded])) {
                                $menu_sections[$parent_encoded]['submenu'][$key] = $item;
                            }
                        }
                    }
                    
                    // Display menu sections in columns
                    $section_count = count( $menu_sections );
                    $sections_per_column = ceil( $section_count / 5 ); // 5 columns
                    $section_index = 0;
                    ?>
                    
                    <div class="fhc-columns">
                        <div class="fhc-column">
                            <?php foreach ( $menu_sections as $parent_key => $section ) : 
                                // Start a new column when needed
                                if ( $section_index > 0 && $section_index % $sections_per_column === 0 ) : ?>
                                    </div><div class="fhc-column">
                                <?php endif; ?>
                                
                                <div class="fhc-menu-group">
                                    <h3 class="fhc-menu-section"><?php echo esc_html( $section['title'] ); ?></h3>
                                    
                                    <div class="fhc-menu-item">
                                        <label class="fhc-checkbox-label">
                                            <input
                                                type="checkbox"
                                                id="fhc_hidden_menu_items_<?php echo esc_attr( $section['main'] ); ?>"
                                                name="fhc_hidden_menu_items[<?php echo esc_attr( $section['main'] ); ?>]"
                                                value="1"
                                                <?php checked( isset( $hidden_menu_items[ $section['main'] ] ) ); ?>
                                            >
                                            <span class="fhc-menu-title"><?php esc_html_e( 'Main Menu', 'focal-haus-core' ); ?></span>
                                        </label>
                                    </div>
                                    
                                    <?php if ( ! empty( $section['submenu'] ) ) : ?>
                                        <?php foreach ( $section['submenu'] as $submenu_key => $submenu_item ) : ?>
                                            <div class="fhc-submenu-item">
                                                <label class="fhc-checkbox-label">
                                                    <input
                                                        type="checkbox"
                                                        id="fhc_hidden_menu_items_<?php echo esc_attr( $submenu_key ); ?>"
                                                        name="fhc_hidden_menu_items[<?php echo esc_attr( $submenu_key ); ?>]"
                                                        value="1"
                                                        <?php checked( isset( $hidden_menu_items[ $submenu_key ] ) ); ?>
                                                    >
                                                    <span class="fhc-menu-title"><?php echo esc_html( $submenu_item['title'] ); ?></span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <?php $section_index++; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php submit_button(); ?>
        </form>
        <?php
    }
}
