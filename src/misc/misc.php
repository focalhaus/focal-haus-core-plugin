<?php
/**
 * Miscellaneous functionality.
 *
 * @package Focal_Haus_Core
 * @subpackage Misc
 */

namespace FocalHaus\misc;

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
    );

    /**
     * Initialize the class.
     *
     */
    private function __construct() {
        // Load settings
        $this->load_settings();
        
        // Initialize features based on settings
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
     * Initialize features based on settings.
     *
     */
    private function init_features() {
        // Initialize duplicate slugs feature if enabled
        if (isset($this->settings['allow_duplicate_slugs']) && $this->settings['allow_duplicate_slugs']) {
            $this->init_polylang_slug_feature();
        }
        
        // Initialize remove toolbar items if enabled
        if (isset($this->settings['remove_toolbar_items']) && $this->settings['remove_toolbar_items']) {
            add_action('admin_bar_menu', array($this, 'remove_toolbar_nodes'), 999);
        }
        
        // Initialize SEOPress editor access if enabled
        if (isset($this->settings['seopress_editor_access']) && $this->settings['seopress_editor_access']) {
            add_filter('seopress_capability', array($this, 'seopress_grant_full_access_to_editors'), 10, 2);
            add_action('init', array($this, 'seopress_add_editor_caps_to_redirections'));
        }
        
        // Initialize custom login logo if enabled
        if (isset($this->settings['custom_login_logo']) && $this->settings['custom_login_logo'] && !empty($this->settings['login_logo_url'])) {
            add_action('login_head', array($this, 'custom_login_logo'));
        }
        
        // Disable Theme Editor if enabled
        if (isset($this->settings['disable_theme_editor']) && $this->settings['disable_theme_editor']) {
            if (!defined('DISALLOW_FILE_EDIT')) {
                define('DISALLOW_FILE_EDIT', true);
            }
        }
        
        // Disable Plugin Editor if enabled
        if (isset($this->settings['disable_plugin_editor']) && $this->settings['disable_plugin_editor']) {
            if (!defined('DISALLOW_FILE_EDIT') && !defined('DISALLOW_FILE_MODS')) {
                define('DISALLOW_FILE_EDIT', true);
            }
        }
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
     * Initialize the Polylang slug feature.
     *
     */
    private function init_polylang_slug_feature() {
        // Check if PLL exists & the minimum version is correct.
        if ( ! defined( 'POLYLANG_VERSION' ) || version_compare( POLYLANG_VERSION, '1.7', '<=' ) || version_compare( $GLOBALS[ 'wp_version' ], '4.0', '<=' ) ) {
            add_action( 'admin_notices', array( $this, 'polylang_slug_admin_notices' ) );
            return;
        }
        
        // Add filters for the Polylang slug feature
        add_filter( 'wp_unique_post_slug', array( $this, 'polylang_slug_unique_slug_in_language' ), 10, 6 );
        add_filter( 'query', array( $this, 'polylang_slug_filter_queries' ) );
        add_filter( 'posts_where', array( $this, 'polylang_slug_posts_where_filter' ), 10, 2 );
        add_filter( 'posts_join', array( $this, 'polylang_slug_posts_join_filter' ), 10, 2 );
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
                    'disable_plugin_editor'
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
                    <tr>
                        <th scope="row">
                            <label for="fhc_allow_duplicate_slugs">
                                <?php esc_html_e('Allow Duplicate Slugs for Different Languages', 'focal-haus-core'); ?>
                            </label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="fhc_misc_settings[allow_duplicate_slugs]" 
                                       id="fhc_allow_duplicate_slugs"
                                       value="1" 
                                       <?php checked(isset($this->settings['allow_duplicate_slugs']) && $this->settings['allow_duplicate_slugs']); ?> />
                                <?php esc_html_e('Enable this feature to allow posts in different languages to use the same slug when using Polylang.', 'focal-haus-core'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('This feature requires the Polylang plugin to be active.', 'focal-haus-core'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="fhc_remove_toolbar_items">
                                <?php esc_html_e('Remove Toolbar Items', 'focal-haus-core'); ?>
                            </label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="fhc_misc_settings[remove_toolbar_items]" 
                                       id="fhc_remove_toolbar_items"
                                       value="1" 
                                       <?php checked(isset($this->settings['remove_toolbar_items']) && $this->settings['remove_toolbar_items']); ?> />
                                <?php esc_html_e('Remove WordPress logo and stats from the admin toolbar.', 'focal-haus-core'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="fhc_disable_theme_editor">
                                <?php esc_html_e('Disable Theme Editor', 'focal-haus-core'); ?>
                            </label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="fhc_misc_settings[disable_theme_editor]" 
                                       id="fhc_disable_theme_editor"
                                       value="1" 
                                       <?php checked(isset($this->settings['disable_theme_editor']) && $this->settings['disable_theme_editor']); ?> />
                                <?php esc_html_e('Disable the WordPress theme file editor for security.', 'focal-haus-core'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('This setting will take effect after the page is reloaded.', 'focal-haus-core'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="fhc_disable_plugin_editor">
                                <?php esc_html_e('Disable Plugin Editor', 'focal-haus-core'); ?>
                            </label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="fhc_misc_settings[disable_plugin_editor]" 
                                       id="fhc_disable_plugin_editor"
                                       value="1" 
                                       <?php checked(isset($this->settings['disable_plugin_editor']) && $this->settings['disable_plugin_editor']); ?> />
                                <?php esc_html_e('Disable the WordPress plugin file editor for security.', 'focal-haus-core'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('This setting will take effect after the page is reloaded.', 'focal-haus-core'); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <h2><?php esc_html_e('Plugin Integrations', 'focal-haus-core'); ?></h2>
            
            <table class="form-table">
                <tbody>
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
                                       <?php checked(isset($this->settings['seopress_editor_access']) && $this->settings['seopress_editor_access']); ?> />
                                <?php esc_html_e('Grant Editor role full access to SEOPress features, including redirections.', 'focal-haus-core'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('This feature requires the SEOPress plugin to be active.', 'focal-haus-core'); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <h2><?php esc_html_e('Branding', 'focal-haus-core'); ?></h2>
            
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="fhc_custom_login_logo">
                                <?php esc_html_e('Change WordPress Login Logo', 'focal-haus-core'); ?>
                            </label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="fhc_misc_settings[custom_login_logo]" 
                                       id="fhc_custom_login_logo"
                                       value="1" 
                                       <?php checked(isset($this->settings['custom_login_logo']) && $this->settings['custom_login_logo']); ?> />
                                <?php esc_html_e('Use custom logo on the WordPress login page.', 'focal-haus-core'); ?>
                            </label>
                            
                            <div class="fhc-media-uploader" style="margin-top: 10px;">
                                <input type="hidden" name="fhc_misc_settings[login_logo_url]" id="fhc_login_logo_url" value="<?php echo esc_attr($this->settings['login_logo_url']); ?>" />
                                
                                <div class="fhc-logo-preview" style="margin-bottom: 10px; max-width: 150px;">
                                    <?php if (!empty($this->settings['login_logo_url'])): ?>
                                        <img src="<?php echo esc_url($this->settings['login_logo_url']); ?>" style="max-width: 100%; height: auto;" />
                                    <?php endif; ?>
                                </div>
                                
                                <input type="button" id="fhc_logo_upload_button" class="button" value="<?php esc_attr_e('Select Logo', 'focal-haus-core'); ?>" />
                                <?php if (!empty($this->settings['login_logo_url'])): ?>
                                    <input type="button" id="fhc_logo_remove_button" class="button" value="<?php esc_attr_e('Remove Logo', 'focal-haus-core'); ?>" />
                                <?php endif; ?>
                                
                                <p class="description">
                                    <?php esc_html_e('Select an image to use as the login page logo. Recommended size: 300px wide.', 'focal-haus-core'); ?>
                                </p>
                            </div>
                            
                            <script type="text/javascript">
                            jQuery(document).ready(function($) {
                                // Media uploader
                                $('#fhc_logo_upload_button').click(function(e) {
                                    e.preventDefault();
                                    
                                    var image_frame;
                                    
                                    if (image_frame) {
                                        image_frame.open();
                                    }
                                    
                                    // Define image_frame as wp.media object
                                    image_frame = wp.media({
                                        title: '<?php esc_attr_e('Select Media', 'focal-haus-core'); ?>',
                                        multiple: false,
                                        library: {
                                            type: 'image'
                                        }
                                    });
                                    
                                    image_frame.on('select', function() {
                                        // Get media attachment details from the frame state
                                        var attachment = image_frame.state().get('selection').first().toJSON();
                                        
                                        // Send the attachment URL to our custom image input field.
                                        $('#fhc_login_logo_url').val(attachment.url);
                                        
                                        // Update preview
                                        $('.fhc-logo-preview').html('<img src="' + attachment.url + '" style="max-width: 100%; height: auto;" />');
                                        
                                        // Show remove button
                                        if (!$('#fhc_logo_remove_button').length) {
                                            $('#fhc_logo_upload_button').after(' <input type="button" id="fhc_logo_remove_button" class="button" value="<?php esc_attr_e('Remove Logo', 'focal-haus-core'); ?>" />');
                                        }
                                    });
                                    
                                    // Open media uploader
                                    image_frame.open();
                                });
                                
                                // Remove image
                                $(document).on('click', '#fhc_logo_remove_button', function(e) {
                                    e.preventDefault();
                                    $('#fhc_login_logo_url').val('');
                                    $('.fhc-logo-preview').html('');
                                    $(this).remove();
                                });
                            });
                            </script>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Minimum version admin notice.
     *
     */
    public function polylang_slug_admin_notices() {
        echo '<div class="error"><p>' . __( 'Focal Haus Core: Polylang Slug feature requires at the minimum Polylang v1.7 and WordPress 4.0', 'focal-haus-core') . '</p></div>';
    }

    /**
     * Checks if the slug is unique within language.
     *
     * @global  wpdb  $wpdb        WordPress database abstraction object.
     *
     * @param  string $slug        The desired slug (post_name).
     * @param  int    $post_ID     Post ID.
     * @param  string $post_status No uniqueness checks are made if the post is still draft or pending.
     * @param  string $post_type   Post type.
     * @param  int    $post_parent Post parent ID.
     * @param  string $original_slug The original slug requested.
     *
     * @return string              Unique slug for the post within language, based on $post_name (with a -1, -2, etc. suffix).
     */
    public function polylang_slug_unique_slug_in_language( $slug, $post_ID, $post_status, $post_type, $post_parent, $original_slug ) {
        // Return slug if it was not changed.
        if ( $original_slug === $slug ) {
            return $slug;
        }

        global $wpdb;

        // Get language of a post
        $lang = pll_get_post_language( $post_ID );
        $options = get_option( 'polylang' );

        // return the slug if Polylang does not return post language or has incompatable redirect setting or is not translated post type.
        if ( empty( $lang ) || 0 === $options['force_lang'] || ! pll_is_translated_post_type( $post_type ) ) {
            return $slug;
        }

        // " INNER JOIN $wpdb->term_relationships AS pll_tr ON pll_tr.object_id = ID".
        $join_clause  = $this->polylang_slug_model_post_join_clause();
        // " AND pll_tr.term_taxonomy_id IN (" . implode(',', $languages) . ")".
        $where_clause = $this->polylang_slug_model_post_where_clause( $lang );

        // Polylang does not translate attachements - skip if it is one.
        // @TODO Recheck this with the Polylang settings
        if ( 'attachment' == $post_type ) {
            // Attachment slugs must be unique across all types.
            $check_sql = "SELECT post_name FROM $wpdb->posts $join_clause WHERE post_name = %s AND ID != %d $where_clause LIMIT 1";
            $post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $original_slug, $post_ID ) );
        } elseif ( is_post_type_hierarchical( $post_type ) ) {
            // Page slugs must be unique within their own trees. Pages are in a separate
            // namespace than posts so page slugs are allowed to overlap post slugs.
            $check_sql = "SELECT ID FROM $wpdb->posts $join_clause WHERE post_name = %s AND post_type IN ( %s, 'attachment' ) AND ID != %d AND post_parent = %d $where_clause LIMIT 1";
            $post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $original_slug, $post_type, $post_ID, $post_parent ) );
        } else {
            // Post slugs must be unique across all posts.
            $check_sql = "SELECT post_name FROM $wpdb->posts $join_clause WHERE post_name = %s AND post_type = %s AND ID != %d $where_clause LIMIT 1";
            $post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $original_slug, $post_type, $post_ID ) );
        }

        if ( ! $post_name_check ) {
            return $original_slug;
        }

        return $slug;
    }

    /**
     * Modify the sql query to include checks for the current language.
     *
     * @global wpdb   $wpdb  WordPress database abstraction object.
     *
     * @param  string $query Database query.
     *
     * @return string        The modified query.
     */
    public function polylang_slug_filter_queries( $query ) {
        global $wpdb;

        // Query for posts page, pages, attachments and hierarchical CPT. This is the only possible place to make the change. The SQL query is set in get_page_by_path()
        $is_pages_sql = preg_match(
            "#SELECT ID, post_name, post_parent, post_type FROM {$wpdb->posts} .*#",
            $this->polylang_slug_standardize_query( $query ),
            $matches
        );

        if ( ! $is_pages_sql ) {
            return $query;
        }

        // Check if should contine. Don't add $query polylang_slug_should_run() as $query is a SQL query.
        if ( ! $this->polylang_slug_should_run() ) {
            return $query;
        }

        $lang = pll_current_language();
        // " INNER JOIN $wpdb->term_relationships AS pll_tr ON pll_tr.object_id = ID".
        $join_clause  = $this->polylang_slug_model_post_join_clause();
        // " AND pll_tr.term_taxonomy_id IN (" . implode(',', $languages) . ")".
        $where_clause = $this->polylang_slug_model_post_where_clause( $lang );

        $query = preg_match(
            "#(SELECT .* (?=FROM))(FROM .* (?=WHERE))(?:(WHERE .*(?=ORDER))|(WHERE .*$))(.*)#",
            $this->polylang_slug_standardize_query( $query ),
            $matches
        );

        // Reindex array numerically $matches[3] and $matches[4] are not added together thus leaving a gap. With this $matches[5] moves up to $matches[4]
        $matches = array_values( $matches );

        // SELECT, FROM, INNER JOIN, WHERE, WHERE CLAUSE (additional), ORBER BY (if included)
        $sql_query = $matches[1] . $matches[2] . $join_clause . $matches[3] . $where_clause . $matches[4];

        /**
         * Disable front end query modification.
         *
         * Allows disabling front end query modification if not needed.
         *
         * @param string $sql_query    Database query.
         * @param array  $matches {
         *     @type string $matches[1] SELECT SQL Query.
         *     @type string $matches[2] FROM SQL Query.
         *     @type string $matches[3] WHERE SQL Query.
         *     @type string $matches[4] End of SQL Query (Possibly ORDER BY).
         * }
         * @param string $join_clause  INNER JOIN Polylang clause.
         * @param string $where_clause Additional Polylang WHERE clause.
         */
        return apply_filters( 'polylang_slug_sql_query', $sql_query, $matches, $join_clause, $where_clause );
    }

    /**
     * Extend the WHERE clause of the query.
     *
     * This allows the query to return only the posts of the current language
     *
     * @param  string   $where The WHERE clause of the query.
     * @param  WP_Query $query The WP_Query instance (passed by reference).
     *
     * @return string          The WHERE clause of the query.
     */
    public function polylang_slug_posts_where_filter( $where, $query ) {
        // Check if should contine.
        if ( ! $this->polylang_slug_should_run( $query ) ) {
            return $where;
        }

        $lang = empty( $query->query['lang'] ) ? pll_current_language() : $query->query['lang'];

        // " AND pll_tr.term_taxonomy_id IN (" . implode(',', $languages) . ")"
        $where .= $this->polylang_slug_model_post_where_clause( $lang );

        return $where;
    }

    /**
     * Extend the JOIN clause of the query.
     *
     * This allows the query to return only the posts of the current language
     *
     * @param  string   $join  The JOIN clause of the query.
     * @param  WP_Query $query The WP_Query instance (passed by reference).
     *
     * @return string          The JOIN clause of the query.
     */
    public function polylang_slug_posts_join_filter( $join, $query ) {
        // Check if should contine.
        if ( ! $this->polylang_slug_should_run( $query ) ) {
            return $join;
        }

        // " INNER JOIN $wpdb->term_relationships AS pll_tr ON pll_tr.object_id = ID".
        $join .= $this->polylang_slug_model_post_join_clause();

        return $join;
    }

    /**
     * Check if the query needs to be adapted.
     *
     * @param  WP_Query $query The WP_Query instance (passed by reference).
     *
     * @return bool
     */
    private function polylang_slug_should_run( $query = '' ) {
        /**
         * Disable front end query modification.
         *
         * Allows disabling front end query modification if not needed.
         *
         * @param bool     false  Not disabling run.
         * @param WP_Query $query The WP_Query instance (passed by reference).
         */

        // Do not run in admin or if Polylang is disabled
        $disable = apply_filters( 'polylang_slug_disable', false, $query );
        if ( is_admin() || is_feed() || ! function_exists( 'pll_current_language' ) || $disable ) {
            return false;
        }
        // The lang query should be defined if the URL contains the language
        $lang = empty( $query->query['lang'] ) ? pll_current_language() : $query->query['lang'];
        // Checks if the post type is translated when doing a custom query with the post type defined
        $is_translated = ! empty( $query->query['post_type'] ) && ! pll_is_translated_post_type( $query->query['post_type'] );

        return ! ( empty( $lang ) || $is_translated );
    }

    /**
     * Standardize the query.
     *
     * This makes the standardized and simpler to run regex on
     *
     * @param  string $query Database query.
     *
     * @return string        The standardized query.
     */
    private function polylang_slug_standardize_query( $query ) {
        // Strip tabs, newlines and multiple spaces.
        $query = str_replace(
            array( "\t", " \n", "\n", " \r", "\r", "   ", "  " ),
            array( '', ' ', ' ', ' ', ' ', ' ', ' ' ),
            $query
        );
        return trim( $query );
    }

    /**
     * Fetch the polylang join clause.
     *
     * @return string
     */
    private function polylang_slug_model_post_join_clause() {
        if ( function_exists( 'PLL' ) ) {
            return PLL()->model->post->join_clause();
        } elseif ( array_key_exists( 'polylang', $GLOBALS ) ) {
            global $polylang;
            return $polylang->model->join_clause( 'post' );
        }
        return '';
    }

    /**
     * Fetch the polylang where clause.
     *
     * @param  string $lang The current language slug.
     *
     * @return string
     */
    private function polylang_slug_model_post_where_clause( $lang = '' ) {
        if ( function_exists( 'PLL' ) ) {
            return PLL()->model->post->where_clause( $lang );
        } elseif ( array_key_exists( 'polylang', $GLOBALS ) ) {
            global $polylang;
            return $polylang->model->where_clause( $lang, 'post' );
        }
        return '';
    }
    
    /**
     * Remove items from WordPress admin toolbar.
     *
     * @param WP_Admin_Bar $wp_admin_bar The WP_Admin_Bar instance.
     */
    public function remove_toolbar_nodes($wp_admin_bar) {
        $wp_admin_bar->remove_node('wp-logo');
        $wp_admin_bar->remove_node('stats');
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
    
    /**
     * Customize the WordPress login page logo.
     *
     */
    public function custom_login_logo() {
        $logo_url = esc_url($this->settings['login_logo_url']);
        if (empty($logo_url)) return;
        
        echo '<style type="text/css">
        h1 a { 
            background-image: url(' . $logo_url . ') !important;
            background-size: contain !important;
            width: 300px !important;
            height: 200px !important;
            max-width: 300px !important;
        }
        </style>';
    }
}
