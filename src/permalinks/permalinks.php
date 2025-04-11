<?php
/**
 * Permalink functionality using the remove-cpt-base approach.
 *
 * @package Focal_Haus_Core
 * @subpackage Permalinks
 */

namespace FocalHaus\Permalinks;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class for handling permalink functionality.
 */
class Permalinks {

    /**
     * Instance of this class.
     *
     * @var object
     */
    protected static $instance = null;

    /**
     * Custom post types selected for base removal and their alternation status.
     * Format: [ 'cpt_name' => 0 (no alternation) or 1 (alternation) ]
     *
     * @var array
     */
    private $cpt_without_base = array();

    /**
     * Keys (post type names) of the selected CPTs.
     *
     * @var array
     */
    private $cpt_without_base_keys = array();


    /**
     * Initialize the class.
     *
      */
     private function __construct() {
         // Settings registration is now handled manually in Settings.php

         // Load selected custom post types
         $this->load_cpt_without_base();

        // Only add hooks if there are selected CPTs
        if ( ! empty( $this->cpt_without_base ) ) {
            // Remove CPT base slug from permalinks
            add_filter( 'post_type_link', array( $this, 'remove_slug' ), 10, 3 );

            // Handle incoming requests for base-less URLs
            add_filter( 'request', array( $this, 'handle_request' ), 99 ); // High priority

            // Auto redirect old URLs to non-base versions
            add_action( 'template_redirect', array( $this, 'handle_redirect' ), 1 );
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
     * Load selected custom post types and their alternation status.
     *
     */
    private function load_cpt_without_base() {
        $this->cpt_without_base = get_option( 'fhc_cpt_without_base', array() );
        $this->cpt_without_base_keys = array_keys( $this->cpt_without_base );
     }

     /**
     * Removes the CPT slug from the permalink.
     * Adapted from remove-cpt-base plugin.
     *
     * @param string $permalink Original permalink.
     * @param WP_Post $post Post object.
     * @param bool $leavename Whether to keep the post name.
     * @return string Modified permalink.
     */
    function remove_slug( $permalink, $post, $leavename ){
        // Check if the post type is in our list of selected CPTs
        if ( isset( $this->cpt_without_base[ $post->post_type ] ) ) {
            $post_type_obj = get_post_type_object( $post->post_type );
            if ( $post_type_obj && ! empty( $post_type_obj->rewrite['slug'] ) ) {
                $slug = trim( $post_type_obj->rewrite['slug'], '/' );
                // Ensure we replace '/slug/' not just 'slug' anywhere
                $permalink = str_replace( '/' . $slug . '/', '/', $permalink );
            }
        }
        return $permalink;
    }

    /**
     * Handles incoming requests to interpret base-less URLs.
     * Adapted from remove-cpt-base plugin.
     *
     * @param array $query_vars Original query variables.
     * @return array Modified query variables.
     */
    function handle_request( $query_vars ){
        // Only run on frontend, if post_type is not set, and if it's potentially a 404 or a standard page/post request
        if( ! is_admin() && ! isset( $query_vars['post_type'] ) && ( ( isset( $query_vars['error'] ) && $query_vars['error'] == 404 ) || isset( $query_vars['pagename'] ) || isset( $query_vars['attachment'] ) || isset( $query_vars['name'] ) || isset( $query_vars['category_name'] ) ) ){

            $web_roots = array();
            $web_roots[] = site_url();
            if( site_url() != home_url() ){
                $web_roots[] = home_url();
            }
            // Add compatibility for Polylang if needed
            if( function_exists('pll_home_url') ){
                if( site_url() != pll_home_url() ){
                    $web_roots[] = pll_home_url();
                }
            }

            foreach( $web_roots as $web_root ){
                // Get clean current URL path
                $path = $this->get_current_url();
                $path = str_replace( $web_root, '', $path );
                $path = trim( $path, '/' );

                // Clean custom rewrite endpoints (e.g., /page/2/)
                $path_parts = explode( '/', $path );
                foreach( $path_parts as $i => $path_part ){
                    // Check if the part matches a known query var (like 'page' for pagination)
                    if( isset( $query_vars[ $path_part ] ) ){
                        // If it does, assume the actual post path ends before this part
                        $path = implode('/', array_slice( $path_parts, 0, $i ));
                        break;
                    }
                }
                // If path becomes empty after cleaning, skip
                if (empty($path)) continue;

                // Test for standard posts first
                $post_data = get_page_by_path( $path, OBJECT, 'post' );
                if( ! ( $post_data instanceof \WP_Post ) ){
                    // Test for standard pages
                    $post_data = get_page_by_path( $path, OBJECT, 'page' ); // Check pages explicitly
                    if( ! ( $post_data instanceof \WP_Post ) ){
                        // Test for selected CPTs
                        $post_data = get_page_by_path( $path, OBJECT, $this->cpt_without_base_keys );
                        if( $post_data instanceof \WP_Post ){
                            // Found a match in one of our CPTs
                            // Check if alternation mode (hierarchical) is needed
                            $post_name = $post_data->post_name;
                            if( isset($this->cpt_without_base[ $post_data->post_type ]) && $this->cpt_without_base[ $post_data->post_type ] == 1 ){
                                $ancestors = get_post_ancestors( $post_data->ID );
                                foreach( $ancestors as $ancestor ){
                                    $ancestor_post = get_post($ancestor);
                                    if ($ancestor_post) {
                                        $post_name = $ancestor_post->post_name . '/' . $post_name;
                                    }
                                }
                                // If hierarchical, the matched path should equal the full name path
                                if ($path !== $post_name) {
                                    // If paths don't match in alternation mode, this isn't the right post.
                                    // Continue searching or let WP 404.
                                    continue;
                                }
                            }

                            // Modify query vars to load the found CPT post
                            unset( $query_vars['error'] );
                            unset( $query_vars['pagename'] );
                            unset( $query_vars['attachment'] );
                            unset( $query_vars['category_name'] );
                            $query_vars['page'] = ''; // Reset page query var
                            $query_vars['name'] = $path; // Use the matched path as the 'name'
                            $query_vars['post_type'] = $post_data->post_type;
                            // Add the specific post type query var (e.g., 'portfolio' => 'my-item')
                            $query_vars[ $post_data->post_type ] = $path;
                            // We found a match, break the web_roots loop
                            break;
                        } else {
                            // Deeper matching using rewrite rules (less preferred but necessary for complex cases)
                            global $wp_rewrite;
                            // Test all selected CPTs
                            foreach( $this->cpt_without_base_keys as $post_type ){
                                $post_type_object = get_post_type_object( $post_type );
                                if( ! $post_type_object || empty($post_type_object->query_var) ) continue;

                                $query_var = $post_type_object->query_var;

                                // Test all rewrite rules
                                if (is_array($wp_rewrite->rules)) {
                                    foreach( $wp_rewrite->rules as $pattern => $rewrite ){
                                        // Test only rules potentially related to this CPT query var
                                        if( strpos( $rewrite, $query_var . '=' ) !== false ){
                                            // Simulate matching the pattern against the *base-less* path
                                            // This is tricky; the original plugin tried matching against '/query_var/path'
                                            // Let's try matching the pattern against the base-less path directly
                                            preg_match( '#' . $pattern . '#', $path, $matches );

                                            if( !empty($matches) && isset( $matches[1] ) ){ // Check if pattern matched and captured something
                                                // Build URL query array from the rewrite rule destination
                                                $rewrite_query = str_replace( 'index.php?', '', $rewrite );
                                                parse_str( $rewrite_query, $url_query );

                                                // Find the query var key corresponding to the main slug capture group
                                                $slug_key = '';
                                                foreach ($url_query as $key => $value) {
                                                    if (preg_match('/^\$matches\[(\d+)\]$/', $value, $m)) {
                                                        if (isset($matches[$m[1]])) {
                                                            // Check if this captured value matches our path (or part of it)
                                                            // This logic needs refinement based on specific rule structures
                                                            // For simple rules like 'cpt/([^/]+)', $matches[1] is the slug
                                                            if ($key === $query_var) {
                                                                $slug_key = $key;
                                                                break;
                                                            }
                                                        }
                                                    }
                                                }

                                                // If we identified the slug from the rewrite rule
                                                if( !empty($slug_key) && isset( $url_query[ $slug_key ] ) ){
                                                    // Extract the potential slug value from the match
                                                    preg_match('/^\$matches\[(\d+)\]$/', $url_query[$slug_key], $m);
                                                    $potential_slug = isset($matches[$m[1]]) ? $matches[$m[1]] : null;

                                                    if ($potential_slug) {
                                                        // Test this potential slug for the current CPT
                                                        $post_data = get_page_by_path( $potential_slug, OBJECT, $post_type );
                                                        if( $post_data instanceof \WP_Post ){
                                                            // Found a match via rewrite rule simulation
                                                            unset( $query_vars['error'] );
                                                            unset( $query_vars['pagename'] );
                                                            unset( $query_vars['attachment'] );
                                                            unset( $query_vars['category_name'] );
                                                            $query_vars['page'] = '';
                                                            $query_vars['name'] = $potential_slug; // Use the matched slug
                                                            $query_vars['post_type'] = $post_data->post_type;
                                                            $query_vars[ $post_data->post_type ] = $potential_slug;

                                                            // Add other query vars from the rewrite rule if they are static values
                                                            foreach( $url_query as $key => $value ){
                                                                if( $key != 'post_type' && $key != $slug_key && strpos($value, '$matches') === false ){
                                                                    $query_vars[ $key ] = $value;
                                                                }
                                                            }
                                                            // Break all loops once a match is found
                                                            break 3;
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                // If we found a match in this web_root iteration, $post_data will be set.
                // If $post_data is set, we break the web_roots loop.
                if ($post_data instanceof \WP_Post && isset($query_vars['post_type']) && $query_vars['post_type'] === $post_data->post_type) {
                    break;
                }
            } // End foreach $web_roots
        }
        return $query_vars;
    }


    /**
     * Redirects old URLs (with base slug) to the new base-less URLs.
     * Adapted from remove-cpt-base plugin.
     */
    function handle_redirect(){
        global $post;
        // Check if it's a single post view, not a preview, and the post type is one we're handling
        if( ! is_preview() && is_single() && is_object( $post ) && isset( $this->cpt_without_base[ $post->post_type ] ) ){
            // Get the expected canonical URL (which remove_slug filter should have processed)
            $new_url = get_permalink( $post->ID );
            // Get the actual requested URL
            $real_url = $this->get_current_url();

            // Compare the structure. If the real URL has the base slug, it will likely differ from the canonical one.
            // A simple check is comparing slash counts, but direct comparison is safer if possible.
            // If the real URL doesn't match the canonical URL, redirect.
            if( $new_url != $real_url ){
                // Check if the real URL contains the base slug segment
                $post_type_obj = get_post_type_object( $post->post_type );
                if ($post_type_obj && !empty($post_type_obj->rewrite['slug'])) {
                    $base_slug_segment = '/' . trim($post_type_obj->rewrite['slug'], '/') . '/';
                    if (strpos($real_url, $base_slug_segment) !== false) {
                         wp_redirect( $new_url, 301 );
                         exit;
                    }
                }
                // Fallback check: Compare slash counts (less reliable)
                // else if ( substr_count( $new_url, '/' ) != substr_count( $real_url, '/' ) ) {
                //     wp_redirect( $new_url, 301 );
                //     exit;
                // }
            }
        }
    }

    /**
     * Helper function to get the current URL.
     * Adapted from remove-cpt-base plugin.
     *
     * @return string The current URL.
     */
    function get_current_url(){
        $is_https = ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' )
            || ( isset( $_SERVER['SERVER_PORT'] ) && $_SERVER['SERVER_PORT'] == 443 )
            || ( ! empty( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' )
            || ( isset( $_SERVER['REQUEST_SCHEME'] ) && $_SERVER['REQUEST_SCHEME'] === 'https' );

        $host = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME']; // Prefer HTTP_HOST if available
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
        $current_url = ( $is_https ? 'https://' : 'http://' ) . $host . strtok( $uri, '?' );

        // Apply a filter for potential modifications by other plugins (e.g., language plugins)
        return apply_filters( 'fhc_current_url', $current_url );
    }


    /**
     * Render the Permalink Settings tab content.
     * Adapted to include the 'alternation' option and manual save handling.
     */
    public function render_tab_content() {
        // Get saved settings for display
        // Note: $this->cpt_without_base might be stale if saved on the same request before rendering.
        // Always fetch fresh from DB for rendering the current state.
        $cpt_settings = get_option( 'fhc_cpt_without_base', array() );

        // Get all public custom post types
        $post_types = get_post_types( array(
            'public'   => true,
            '_builtin' => false
        ), 'objects' );

        ?>
        <div class="fhc-tab-description">
            <p><?php esc_html_e( 'Select which custom post types should have their base slug removed from permalinks.', 'focal-haus-core' ); ?></p>
        </div>

        <?php /* Form action is empty, submitting to the current page */ ?>
        <form method="post" action="">
            <?php
            // Add nonce field for security verification
            wp_nonce_field( 'fhc_save_permalink_settings', 'fhc_permalink_nonce' );
            // settings_fields() is removed as we are handling saving manually
            ?>
            <?php /* Add hidden inputs to ensure the tab stays active after saving */ ?>
            <input type="hidden" name="page" value="focal-haus-core" />
            <input type="hidden" name="tab" value="permalink_settings" />

            <div class="fhc-section-info">
                <p><?php esc_html_e( 'For example, if you have a custom post type "Books" with slug "book", the URL will change from /book/book-title to /book-title', 'focal-haus-core' ); ?></p>
                <p><?php esc_html_e( 'Note: This may cause conflicts if you have pages or posts with the same slug as your custom post type items. The custom post type will usually take precedence.', 'focal-haus-core' ); ?></p>
                 <p><?php esc_html_e( '* If your custom post type children (hierarchical posts) return error 404, try enabling alternation mode.', 'focal-haus-core' ); ?></p>
            </div>

            <div class="fhc-grid-container">
                <?php if ( empty( $post_types ) ) : ?>
                    <div class="fhc-notice">
                        <?php esc_html_e( 'No public custom post types found.', 'focal-haus-core' ); ?>
                    </div>
                <?php else : ?>
                    <table class="form-table widefat" style="width:auto">
                        <tbody>
                            <?php foreach ( $post_types as $post_type ) :
                                $post_type_name = $post_type->name;
                                // Check settings fetched fresh from DB
                                $is_selected = isset( $cpt_settings[ $post_type_name ] );
                                // Alternation is true (1) if the key exists and the value is 1
                                $is_alternation = $is_selected && $cpt_settings[ $post_type_name ] == 1;
                                $base_slug = isset($post_type->rewrite['slug']) ? $post_type->rewrite['slug'] : $post_type_name;
                            ?>
                                <tr>
                                    <td>
                                        <label>
                                            <input
                                                type="checkbox"
                                                name="fhc_cpt_without_base[selected][<?php echo esc_attr( $post_type_name ); ?>]"
                                                value="1"
                                                <?php checked( $is_selected ); ?>
                                            >
                                            <?php echo esc_html( $post_type->labels->name ); ?> (<?php echo esc_html( $post_type_name ); ?>)
                                            - <i>Base: <code><?php echo esc_html($base_slug); ?></code></i>
                                        </label>
                                    </td>
                                    <td>
                                        <label>
                                            <input
                                                type="checkbox"
                                                name="fhc_cpt_without_base[alternation][<?php echo esc_attr( $post_type_name ); ?>]"
                                                value="1"
                                                <?php checked( $is_alternation ); ?>
                                                <?php disabled( ! $is_selected ); // Disable if main checkbox is not checked ?>
                                            >
                                            <?php esc_html_e( 'Alternation Mode (for hierarchical)', 'focal-haus-core' ); ?>
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <?php submit_button( __( 'Save Changes', 'focal-haus-core' ) ); ?>
        </form>
        <script type="text/javascript">
            // Add simple JS to enable/disable alternation checkbox based on the main selection
            document.addEventListener('DOMContentLoaded', function() {
                const table = document.querySelector('.form-table');
                if (table) {
                    table.addEventListener('change', function(event) {
                        if (event.target.name && event.target.name.includes('[selected]')) {
                            const alternationCheckbox = event.target.closest('tr').querySelector('input[name*="[alternation]"]');
                            if (alternationCheckbox) {
                                alternationCheckbox.disabled = !event.target.checked;
                                if (!event.target.checked) {
                                    alternationCheckbox.checked = false; // Uncheck alternation if main is unchecked
                                }
                            }
                        }
                    });
                }
            });
        </script>
        <?php
    }

} // End Class
