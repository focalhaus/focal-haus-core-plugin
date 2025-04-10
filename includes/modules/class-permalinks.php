<?php
/**
 * Permalink functionality.
 *
 * @package Focal_Haus_Core
 * @subpackage Modules
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class for handling permalink functionality.
 */
class FHC_Permalinks {

    /**
     * Instance of this class.
     *
     * @since 1.0.0
     * @var object
     */
    protected static $instance = null;

    /**
     * Custom post types with removed base slugs.
     *
     * @since 1.0.0
     * @var array
     */
    private $cpt_without_base = array();

    /**
     * Initialize the class.
     *
     * @since 1.0.0
     */
    private function __construct() {
        // Register settings.
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        
        // Load custom post types without base
        $this->load_cpt_without_base();

        // Remove CPT base slug from permalinks
        add_filter( 'post_type_link', array( $this, 'custom_post_permalinks' ), 99, 2 );
        add_filter( 'page_link', array( $this, 'custom_post_permalinks' ), 99, 2 );
        add_filter( 'post_link', array( $this, 'custom_post_permalinks' ), 99, 2 );
        add_filter( 'attachment_link', array( $this, 'custom_post_permalinks' ), 99, 2 );
        
        // Handle custom permalink requests
        add_action( 'template_redirect', array( $this, 'check_for_404' ), 1 );
        add_action( 'pre_get_posts', array( $this, 'handle_custom_permalinks' ) );
        add_filter( 'request', array( $this, 'filter_request' ) );
        add_action( 'init', array( $this, 'add_cpt_rewrite_rules' ), 1 );
        add_filter( 'rewrite_rules_array', array( $this, 'filter_rewrite_rules' ), 1, 1 );
        
        // SEOPress sitemap integration
        add_action( 'init', array( $this, 'add_seopress_sitemap_rewrite_rules' ), 2 );
        
        // Force flush rewrite rules once after this update
        // This is necessary for the new SEOPress sitemap rules to take effect
        if (get_option('fhc_permalinks_updated') !== '1.1.1') {
            add_action('init', function() {
                flush_rewrite_rules();
                update_option('fhc_permalinks_updated', '1.1.1');
            }, 999);
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
     * Register plugin settings.
     *
     * @since 1.0.0
     */
    public function register_settings() {
        // Register permalink settings
        if ( false === get_option( 'fhc_cpt_without_base' ) ) {
            add_option( 'fhc_cpt_without_base', array() );
        }
        
        register_setting(
            'fhc_permalink_settings',
            'fhc_cpt_without_base',
            array(
                'sanitize_callback' => array( $this, 'sanitize_cpt_without_base' ),
                'default'           => array(),
            )
        );
    }
    
    /**
     * Load custom post types that should have their base slug removed.
     *
     * @since 1.0.0
     */
    private function load_cpt_without_base() {
        $this->cpt_without_base = get_option( 'fhc_cpt_without_base', array() );
    }
    
    /**
     * Sanitize custom post types without base.
     *
     * @since 1.0.0
     * @param array|string $input The value being saved.
     * @return array Sanitized value.
     */
    public function sanitize_cpt_without_base( $input ) {
        // If input is not an array, initialize an empty array
        if ( ! is_array( $input ) ) {
            $input = array();
        }

        $sanitized_input = array();
        
        // Loop through each input item
        foreach ( $input as $post_type => $value ) {
            if ( $value && post_type_exists( $post_type ) ) { // Only add if value is truthy and post type exists
                $sanitized_input[ sanitize_key( $post_type ) ] = 1;
            }
        }
        
        // Schedule a delayed flush of rewrite rules to ensure all rules are registered
        wp_schedule_single_event( time() + 5, 'fhc_flush_rewrite_rules' );
        
        return $sanitized_input;
    }

    /**
     * Custom post permalinks method to remove base slugs from custom post types.
     *
     * @since 1.0.0
     * @param string $permalink The post's permalink.
     * @param object $post The post object.
     * @return string Modified permalink without base slug.
     */
    public function custom_post_permalinks( $permalink, $post ) {
        global $custom_permalinks;
        static $recursion_guard = false;
        
        // Prevent infinite recursion
        if ($recursion_guard) {
            return $permalink;
        }
        
        // Set recursion guard
        $recursion_guard = true;
        
        // Check if we are in the Customizer or WPML String Editor
        if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
            $recursion_guard = false;
            return $permalink;
        }
        
        // Validate post object
        if ( empty( $post ) || empty( $post->ID ) || empty( $post->post_type ) || ! is_string( $permalink ) ) {
            $recursion_guard = false;
            return $permalink;
        }
        
        // Start with the homepage URL
        $home_url = get_home_url();
        
        // Check if this post type should have its base slug removed
        if ( isset( $this->cpt_without_base[ $post->post_type ] ) ) {
            // Get post type object
            $post_type_obj = get_post_type_object( $post->post_type );
            
            // If the post type has rewrite rules
            if ( $post_type_obj->rewrite ) {
                // Get the base slug
                $base_slug = $post_type_obj->rewrite['slug'];
                
                // Remove the base slug from the permalink
                $permalink = str_replace( '/' . $base_slug . '/', '/', $permalink );
            }
        }
        
        // Check if a custom permalink is assigned
        if ( isset( $custom_permalinks[ $post->ID ] ) ) {
            $permalink = "{$home_url}/" . rawurlencode( $custom_permalinks[$post->ID] );
        }
        
        // Reset recursion guard
        $recursion_guard = false;
        
        return $permalink;
    }
    
    /**
     * Check if a 404 is about to be served and try to find a matching post.
     *
     * @since 1.0.0
     */
    public function check_for_404() {
        static $redirect_guard = false;
        
        // Prevent redirect loops
        if ($redirect_guard) {
            return;
        }
        
        // Set redirect guard
        $redirect_guard = true;
        
        // Only run on 404 pages
        if (!is_404()) {
            $redirect_guard = false;
            return;
        }
        
        // Get the current URL path
        $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        
        // Skip empty paths
        if (empty($path)) {
            $redirect_guard = false;
            return;
        }
        
        // Skip WordPress admin and system paths
        if (strpos($path, 'wp-') === 0) {
            $redirect_guard = false;
            return;
        }
        
        // Skip paths with multiple segments unless they match our CPT pattern
        if (strpos($path, '/') !== false) {
            // Check if this is a URL with the base slug already included
            $path_parts = explode('/', $path);
            
            // If it's a URL with a base slug, don't process it
            foreach ($this->cpt_without_base as $post_type => $value) {
                if ($path_parts[0] === $post_type) {
                    $redirect_guard = false;
                    return;
                }
            }
            
            // If it's more than 2 segments deep, it's likely not a CPT with removed base
            if (count($path_parts) > 2) {
                $redirect_guard = false;
                return; // Skip deep paths
            }
        }
        
        $slug = basename($path);
        
        // Get all public custom post types
        $post_types = get_post_types(array(
            'public'   => true,
            '_builtin' => false
        ), 'names');
        
        // Filter to only include post types with removed base
        $filtered_post_types = array();
        foreach ($post_types as $post_type) {
            if (isset($this->cpt_without_base[$post_type])) {
                $filtered_post_types[] = $post_type;
            }
        }
        
        if (empty($filtered_post_types)) {
            $redirect_guard = false;
            return;
        }
        
        // First check if this is a post with a CPT prefix
        foreach ($filtered_post_types as $post_type) {
            if (strpos($slug, $post_type . '-') === 0) {
                // This is likely a CPT post with a specific naming pattern
                $args = array(
                    'name' => $slug,
                    'post_type' => $post_type,
                    'post_status' => 'publish',
                    'numberposts' => 1
                );
                
                $posts = get_posts($args);
                
                if (!empty($posts)) {
                    // Get the permalink
                    $permalink = get_permalink($posts[0]->ID);
                    
                    // Only redirect if the permalink is different from the current URL
                    $current_url = home_url($path);
                    if ($permalink !== $current_url) {
                        $redirect_guard = false;
                        wp_redirect($permalink, 301);
                        exit;
                    }
                }
            }
        }
        
        // If we get here, check if the slug matches any CPT post
        // But be more selective - only check single-segment paths
        if (strpos($path, '/') === false) {
            $args = array(
                'name' => $slug,
                'post_type' => $filtered_post_types,
                'post_status' => 'publish',
                'numberposts' => 1
            );
            
            $posts = get_posts($args);
            
            if (!empty($posts)) {
                // Get the permalink
                $permalink = get_permalink($posts[0]->ID);
                
                // Only redirect if the permalink is different from the current URL
                $current_url = home_url($path);
                if ($permalink !== $current_url) {
                    $redirect_guard = false;
                    wp_redirect($permalink, 301);
                    exit;
                }
            }
        }
        
        // Reset redirect guard
        $redirect_guard = false;
    }
    
    /**
     * Handle custom permalinks in pre_get_posts.
     *
     * @since 1.0.0
     * @param WP_Query $query The WordPress query object.
     */
    public function handle_custom_permalinks($query) {
        // Only run on main query and if it's not admin
        if (!$query->is_main_query() || is_admin()) {
            return;
        }
        
        // Only run on 404 pages
        if (!$query->is_404()) {
            return;
        }
        
        // Get the requested path
        $path = isset($_SERVER['REQUEST_URI']) ? trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') : '';
        
        // Skip if path is empty
        if (empty($path)) {
            return;
        }
        
        // Skip WordPress admin and system paths
        if (strpos($path, 'wp-') === 0) {
            return;
        }
        
        // Skip paths with multiple segments unless they match our CPT pattern
        if (strpos($path, '/') !== false) {
            // Only process paths that look like "post-type-slug/something"
            $path_parts = explode('/', $path);
            if (count($path_parts) > 2) {
                return; // Skip deep paths
            }
        }
        
        // Get the slug from the path
        $slug = basename($path);
        
        // Get all public custom post types with removed base
        $post_types = array();
        foreach ($this->cpt_without_base as $post_type => $value) {
            $post_types[] = $post_type;
        }
        
        if (empty($post_types)) {
            return;
        }
        
        // First check if this is a post with a CPT prefix
        foreach ($post_types as $post_type) {
            if (strpos($slug, $post_type . '-') === 0) {
                // This is likely a CPT post with a specific naming pattern
                $args = array(
                    'name' => $slug,
                    'post_type' => $post_type,
                    'post_status' => 'publish',
                    'posts_per_page' => 1
                );
                
                $posts = get_posts($args);
                
                if (!empty($posts)) {
                    // Set query variables to show this post
                    $query->set('post_type', $post_type);
                    $query->set('name', $slug);
                    $query->set('page', '');
                    $query->set('pagename', '');
                    $query->is_404 = false;
                    $query->is_singular = true;
                    $query->is_single = true;
                    return;
                }
            }
        }
        
        // If we get here, check if the slug matches any CPT post
        // But be more selective - only check single-segment paths
        if (strpos($path, '/') === false) {
            $args = array(
                'name' => $slug,
                'post_type' => $post_types,
                'post_status' => 'publish',
                'posts_per_page' => 1
            );
            
            $posts = get_posts($args);
            
            if (!empty($posts)) {
                // Set query variables to show this post
                $query->set('post_type', $posts[0]->post_type);
                $query->set('name', $slug);
                $query->set('page', '');
                $query->set('pagename', '');
                $query->is_404 = false;
                $query->is_singular = true;
                $query->is_single = true;
            }
        }
    }
    
    /**
     * Filter the request to handle custom permalinks.
     *
     * @since 1.0.0
     * @param array $query_vars The query variables.
     * @return array Modified query variables.
     */
    public function filter_request($query_vars) {
        // If this is already a post, don't modify
        if (isset($query_vars['post_type']) || isset($query_vars['name']) || isset($query_vars['pagename'])) {
            return $query_vars;
        }
        
        // Get the requested path
        $path = isset($_SERVER['REQUEST_URI']) ? trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') : '';
        
        // If the path is empty, don't modify
        if (empty($path)) {
            return $query_vars;
        }
        
        // Skip admin URLs completely
        if (strpos($path, 'wp-admin') === 0 || strpos($path, 'wp-login') === 0) {
            return $query_vars;
        }
        
        // Skip common WordPress URL patterns
        $wp_patterns = array('wp-content', 'wp-includes', 'wp-json', 'feed', 'comments', 'wp-cron', 'favicon', 'robots.txt');
        foreach ($wp_patterns as $pattern) {
            if (strpos($path, $pattern) === 0) {
                return $query_vars;
            }
        }
        
        // Skip if path has multiple segments (likely a regular page or post)
        // Only process single-segment paths that might be CPT items without base slug
        if (strpos($path, '/') !== false) {
            // Only process if it looks like a CPT with removed base
            $path_parts = explode('/', $path);
            
            // If it's more than 2 segments deep, it's likely not a CPT with removed base
            if (count($path_parts) > 2) {
                return $query_vars;
            }
            
            // Check if the first segment matches any of our CPT types
            $first_segment = $path_parts[0];
            $matches_cpt = false;
            
            foreach ($this->cpt_without_base as $post_type => $value) {
                if ($first_segment === $post_type) {
                    $matches_cpt = true;
                    break;
                }
            }
            
            // If it doesn't match any of our CPTs, let WordPress handle it
            if (!$matches_cpt) {
                return $query_vars;
            }
        }
        
        // Get all public custom post types with removed base
        $post_types = array();
        foreach ($this->cpt_without_base as $post_type => $value) {
            $post_types[] = $post_type;
        }
        
        if (empty($post_types)) {
            return $query_vars;
        }
        
        // First check for CPT-specific patterns like "product-my-product-name"
        foreach ($post_types as $post_type) {
            if (strpos($path, $post_type . '-') === 0) {
                // Extract the slug after the post type prefix
                $slug = $path;
                
                // Check if this specific post exists
                $check_post = get_page_by_path($slug, OBJECT, $post_type);
                if ($check_post) {
                    $query_vars['post_type'] = $post_type;
                    $query_vars['name'] = $slug;
                    $query_vars['page'] = '';
                    $query_vars['pagename'] = '';
                    return $query_vars;
                }
            }
        }
        
        // If we get here, the slug doesn't start with a CPT prefix
        // Try to find a matching post in ANY of our custom post types
        // But ONLY modify the request if we find a match
        $args = array(
            'name' => $path,
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => 1
        );
        
        $posts = get_posts($args);
        
        if (!empty($posts)) {
            // Set query variables to show this post
            $query_vars['post_type'] = $posts[0]->post_type;
            $query_vars['name'] = $path;
            $query_vars['page'] = '';
            $query_vars['pagename'] = '';
        }
        
        return $query_vars;
    }

    /**
     * Filter the rewrite rules array to ensure our rules take precedence.
     *
     * @since 1.0.0
     * @param array $rules The existing rewrite rules.
     * @return array Modified rewrite rules.
     */
    public function filter_rewrite_rules( $rules ) {
        // Get all public custom post types
        $post_types = get_post_types( array(
            'public'   => true,
            '_builtin' => false
        ), 'objects' );
        
        $new_rules = array();
        
        // Loop through each post type
        foreach ( $post_types as $post_type ) {
            // If this post type should have its base slug removed
            if ( isset( $this->cpt_without_base[ $post_type->name ] ) ) {
                $post_type_name = $post_type->name;
                
                // Get all posts of this type to create specific rules for them
                $posts = get_posts(array(
                    'post_type' => $post_type_name,
                    'post_status' => 'publish',
                    'numberposts' => -1,
                ));
                
                // Create specific rules for each post
                if (!empty($posts)) {
                    foreach ($posts as $post) {
                        // Add a rule specifically for this post's slug
                        // Use a very specific pattern to avoid conflicts
                        $new_rules['^' . preg_quote($post->post_name, '/') . '/?$'] = 
                            'index.php?post_type=' . $post_type_name . '&name=' . $post->post_name;
                    }
                }
                
                // Add rule for this specific post type, using a distinctive pattern
                // that is less likely to conflict with other content types
                $new_rules['^(' . $post_type_name . '-[^/]+)/?$'] = 
                    'index.php?post_type=' . $post_type_name . '&name=$matches[1]';
            }
        }
        
        // Merge our rules with the existing rules
        // Put our rules at the beginning to give them higher priority
        return array_merge($new_rules, $rules);
    }

    /**
     * Add rewrite rules for SEOPress sitemaps.
     *
     * @since 1.1.0
     */
    public function add_seopress_sitemap_rewrite_rules() {
        // Only proceed if SEOPress is active
        if (!function_exists('seopress_get_toggle_option') || !seopress_get_toggle_option('xml-sitemap')) {
            return;
        }
        
        // Get CPTs with removed base slugs
        $cpt_without_base = $this->cpt_without_base;
        
        // If no CPTs have their base slug removed, return
        if (empty($cpt_without_base)) {
            return;
        }
        
        // Add rewrite rules for each CPT with removed base slug
        foreach ($cpt_without_base as $post_type => $value) {
            // Add rule for sitemap index
            add_rewrite_rule(
                '^sitemaps.xml$',
                'index.php?seopress_sitemap=1',
                'top'
            );
            
            // Add rule for CPT sitemap
            add_rewrite_rule(
                '^sitemaps/' . $post_type . '.xml$',
                'index.php?seopress_cpt=' . $post_type,
                'top'
            );
        }
    }
    
    /**
     * Add rewrite rules for custom post types without base slug.
     *
     * @since 1.0.0
     */
    public function add_cpt_rewrite_rules() {
        global $wp_rewrite;
        
        // Get all public custom post types
        $post_types = get_post_types( array(
            'public'   => true,
            '_builtin' => false
        ), 'objects' );
        
        // Loop through each post type
        foreach ( $post_types as $post_type ) {
            // If this post type should have its base slug removed
            if ( isset( $this->cpt_without_base[ $post_type->name ] ) ) {
                // Get the base slug
                $base_slug = $post_type->rewrite['slug'];
                $post_type_name = $post_type->name;
                
                // Get all posts of this type to create specific rules for them
                $posts = get_posts(array(
                    'post_type' => $post_type_name,
                    'post_status' => 'publish',
                    'numberposts' => -1,
                ));
                
                // Create specific rules for each post
                if (!empty($posts)) {
                    foreach ($posts as $post) {
                        // Add a rule specifically for this post's slug
                        add_rewrite_rule(
                            '^' . $post->post_name . '/?$',
                            'index.php?post_type=' . $post_type_name . '&name=' . $post->post_name,
                            'top'
                        );
                        
                        // Add rules for pagination and attachments for this specific post
                        add_rewrite_rule(
                            '^' . $post->post_name . '/page/?([0-9]{1,})/?$',
                            'index.php?post_type=' . $post_type_name . '&name=' . $post->post_name . '&paged=$matches[1]',
                            'top'
                        );
                        
                        add_rewrite_rule(
                            '^' . $post->post_name . '/attachment/([^/]+)/?$',
                            'index.php?post_type=' . $post_type_name . '&name=' . $post->post_name . '&attachment=$matches[1]',
                            'top'
                        );
                    }
                }
                
                // Add a rule for posts with the post type as a prefix (e.g., product-my-product)
                add_rewrite_rule(
                    '^' . $post_type_name . '-([^/]+)/?$',
                    'index.php?post_type=' . $post_type_name . '&name=$matches[1]',
                    'top'
                );
                
                // Add pagination and attachment rules for prefixed posts
                add_rewrite_rule(
                    '^' . $post_type_name . '-([^/]+)/page/?([0-9]{1,})/?$',
                    'index.php?post_type=' . $post_type_name . '&name=$matches[1]&paged=$matches[2]',
                    'top'
                );
                
                add_rewrite_rule(
                    '^' . $post_type_name . '-([^/]+)/attachment/([^/]+)/?$',
                    'index.php?post_type=' . $post_type_name . '&name=$matches[1]&attachment=$matches[2]',
                    'top'
                );
                
                // Add feed rules
                add_rewrite_rule(
                    '^' . $post_type_name . '-([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$',
                    'index.php?post_type=' . $post_type_name . '&name=$matches[1]&feed=$matches[2]',
                    'top'
                );
                
                add_rewrite_rule(
                    '^' . $post_type_name . '-([^/]+)/(feed|rdf|rss|rss2|atom)/?$',
                    'index.php?post_type=' . $post_type_name . '&name=$matches[1]&feed=$matches[2]',
                    'top'
                );
                
                // IMPORTANT: Add rules to ensure URLs with the base slug still work
                // This prevents redirect loops by ensuring both formats work
                add_rewrite_rule(
                    '^' . $base_slug . '/([^/]+)/?$',
                    'index.php?post_type=' . $post_type_name . '&name=$matches[1]',
                    'top'
                );
                
                add_rewrite_rule(
                    '^' . $base_slug . '/([^/]+)/page/?([0-9]{1,})/?$',
                    'index.php?post_type=' . $post_type_name . '&name=$matches[1]&paged=$matches[2]',
                    'top'
                );
            }
        }
    }
    
    /**
     * Render the Permalink Settings tab content.
     *
     * @since 1.0.0
     */
    public function render_tab_content() {
        // Get saved settings.
        $cpt_without_base = get_option( 'fhc_cpt_without_base', array() );
        
        // Get all public custom post types
        $post_types = get_post_types( array(
            'public'   => true,
            '_builtin' => false
        ), 'objects' );
        
        ?>
        <div class="fhc-tab-description">
            <p><?php esc_html_e( 'Select which custom post types should have their base slug removed from permalinks.', 'focal-haus-core' ); ?></p>
        </div>
        
        <form method="post" action="options.php">
            <?php
            // Output security fields.
            settings_fields( 'fhc_permalink_settings' );
            ?>
            
            <div class="fhc-section-info">
                <p><?php esc_html_e( 'For example, if you have a custom post type "Books" with slug "book", the URL will change from /book/book-title to /book-title', 'focal-haus-core' ); ?></p>
                <p><?php esc_html_e( 'Note: This may cause conflicts if you have pages with the same slug as your custom post type items. The custom post type will take precedence.', 'focal-haus-core' ); ?></p>
            </div>
            
            <div class="fhc-grid-container">
                <?php if ( empty( $post_types ) ) : ?>
                    <div class="fhc-notice">
                        <?php esc_html_e( 'No custom post types found.', 'focal-haus-core' ); ?>
                    </div>
                <?php else : ?>
                    <table class="form-table">
                        <tbody>
                            <?php foreach ( $post_types as $post_type ) : ?>
                                <tr>
                                    <th scope="row">
                                        <label for="fhc_cpt_without_base_<?php echo esc_attr( $post_type->name ); ?>">
                                            <?php echo esc_html( $post_type->labels->name ); ?>
                                        </label>
                                    </th>
                                    <td>
                                        <label class="fhc-checkbox-label">
                                            <input
                                                type="checkbox"
                                                id="fhc_cpt_without_base_<?php echo esc_attr( $post_type->name ); ?>"
                                                name="fhc_cpt_without_base[<?php echo esc_attr( $post_type->name ); ?>]"
                                                value="1"
                                                <?php checked( isset( $cpt_without_base[ $post_type->name ] ) ); ?>
                                            >
                                            <span class="fhc-menu-title">
                                                <?php 
                                                printf(
                                                    esc_html__( 'Remove base slug (%s) from permalinks', 'focal-haus-core' ),
                                                    '<code>' . esc_html( $post_type->rewrite['slug'] ) . '</code>'
                                                ); 
                                                ?>
                                            </span>
                                        </label>
                                        <p class="description">
                                            <?php 
                                            printf(
                                                esc_html__( 'Current permalink structure: %s', 'focal-haus-core' ),
                                                '<code>' . esc_url( home_url( $post_type->rewrite['slug'] . '/sample-post' ) ) . '</code>'
                                            ); 
                                            ?>
                                            <br>
                                            <?php 
                                            printf(
                                                esc_html__( 'New permalink structure: %s', 'focal-haus-core' ),
                                                '<code>' . esc_url( home_url( 'sample-post' ) ) . '</code>'
                                            ); 
                                            ?>
                                        </p>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <?php submit_button( __( 'Save Changes', 'focal-haus-core' ) ); ?>
        </form>
        <?php
    }
}
