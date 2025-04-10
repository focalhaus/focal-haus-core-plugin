<?php
/**
 * Integrated Permalink Manager Functions
 * 
 * This file contains selected integration functions from the Permalink Manager plugin
 * compiled into a single reference file.
 * 
 * Included integrations:
 * - SEOPress
 * - WooCommerce
 * - Polylang
 * - Gutenberg
 * - WP All Import
 * - WP All Export
 * - Tutor LMS
 */

//-----------------------------------------------------------------------------------
// SEOPress INTEGRATION
//-----------------------------------------------------------------------------------

/**
 * Filter the breadcrumbs array to match the structure of currently requested URL
 *
 * @param array $links The current breadcrumb links.
 *
 * @return array The $links array.
 */
function permalink_manager_seopress_breadcrumbs( $links ) {
    // Get post type permastructure settings
    global $permalink_manager_options, $post, $wpdb, $wp, $wp_current_filter;

    // Check if the filter should be activated
    if ( empty( $permalink_manager_options['general']['yoast_breadcrumbs'] ) ) {
        return $links;
    }

    // Get current post/page/term (if available)
    $queried_element = get_queried_object();
    if ( ! empty( $queried_element->ID ) ) {
        $element_id = $queried_element->ID;
    } else if ( ! empty( $queried_element->term_id ) ) {
        $element_id = "tax-{$queried_element->term_id}";
    } else if ( defined( 'REST_REQUEST' ) && ! empty( $post->ID ) ) {
        $element_id = $post->ID;
    }

    // Get the custom permalink (if available) or the current request URL (if unavailable)
    $custom_uri = ( ! empty( $element_id ) ) ? Permalink_Manager_URI_Functions::get_single_uri( $element_id, false, true, null ) : '';

    if ( ! empty( $custom_uri ) ) {
        $custom_uri = preg_replace( "/([^\/]+)$/", '', $custom_uri );
    } else {
        return $links;
    }

    $custom_uri_parts             = explode( '/', trim( $custom_uri ) );
    $breadcrumbs                  = array();
    $snowball                     = '';
    $available_taxonomies         = Permalink_Manager_Helper_Functions::get_taxonomies_array( null, null, true );
    $available_post_types         = Permalink_Manager_Helper_Functions::get_post_types_array( null, null, true );
    $available_post_types_archive = Permalink_Manager_Helper_Functions::get_post_types_array( 'archive_slug', null, true );
    $current_filter               = end( $wp_current_filter );

    // Check what array keys should be used for breadcrumbs
    $breadcrumb_key_text = 0;
    $breadcrumb_key_url  = 1;
    $is_aioseo           = false;

    // Get internal breadcrumb elements
    foreach ( $custom_uri_parts as $slug ) {
        if ( empty( $slug ) ) {
            continue;
        }

        $snowball = ( empty( $snowball ) ) ? $slug : "{$snowball}/{$slug}";

        // 1A. Try to match any custom URI
        $uri     = trim( $snowball, "/" );
        $element = Permalink_Manager_URI_Functions::find_uri( $uri, true );

        if ( ! empty( $element ) && strpos( $element, 'tax-' ) !== false ) {
            $element_id = intval( preg_replace( "/[^0-9]/", "", $element ) );
            $element    = get_term( $element_id );
        } else if ( is_numeric( $element ) ) {
            $element = get_post( $element );
        }

        // 1B. Try to get term
        if ( empty( $element ) && ! empty( $available_taxonomies ) ) {
            $sql = sprintf( "SELECT t.term_id, t.name, tt.taxonomy FROM {$wpdb->terms} AS t LEFT JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id WHERE slug = '%s' AND tt.taxonomy IN ('%s') LIMIT 1", esc_sql( $slug ), implode( "','", array_keys( $available_taxonomies ) ) );

            $element = $wpdb->get_row( $sql );
        }

        // 1C. Try to get page/post
        if ( empty( $element ) && ! empty( $available_post_types ) ) {
            $sql = sprintf( "SELECT ID, post_title, post_type FROM {$wpdb->posts} WHERE post_name = '%s' AND post_status = 'publish' AND post_type IN ('%s') AND post_type != 'attachment' LIMIT 1", esc_sql( $slug ), implode( "','", array_keys( $available_post_types ) ) );

            $element = $wpdb->get_row( $sql );
        }

        // 1D. Try to get post type archive
        if ( empty( $element ) && ! empty( $available_post_types_archive ) && in_array( $snowball, $available_post_types_archive ) ) {
            $post_type_slug = array_search( $snowball, $available_post_types_archive );
            $element        = get_post_type_object( $post_type_slug );
        }

        // 2A. When the term is found, we can add it to the breadcrumbs
        if ( ! empty( $element->term_id ) ) {
            $term_id = apply_filters( 'wpml_object_id', $element->term_id, $element->taxonomy, true );
            $term    = ( ( $element->term_id !== $term_id ) || $is_aioseo ) ? get_term( $term_id ) : $element;

            // Alternative title for SEOPress
            if ( $current_filter == 'seopress_pro_breadcrumbs_crumbs' ) {
                $alt_title = get_term_meta( $term->term_id, '_seopress_robots_breadcrumbs', true );
            }

            $title = ( ! empty( $alt_title ) ) ? $alt_title : $term->name;

            $breadcrumbs[] = array(
                $breadcrumb_key_text => wp_strip_all_tags( $title ),
                $breadcrumb_key_url  => get_term_link( (int) $term->term_id, $term->taxonomy )
            );
        } // 2B. When the post/page is found, we can add it to the breadcrumbs
        else if ( ! empty( $element->ID ) ) {
            $page_id = apply_filters( 'wpml_object_id', $element->ID, $element->post_type, true );
            $page    = ( ( $element->ID !== $page_id ) || $is_aioseo ) ? get_post( $page_id ) : $element;

            // Alternative title for SEOPress
            if ( $current_filter == 'seopress_pro_breadcrumbs_crumbs' ) {
                $alt_title = get_post_meta( $page->ID, '_seopress_robots_breadcrumbs', true );
            }

            $title = ( ! empty( $alt_title ) ) ? $alt_title : $page->post_title;

            $breadcrumbs[] = array(
                $breadcrumb_key_text => wp_strip_all_tags( $title ),
                $breadcrumb_key_url  => get_permalink( $page->ID )
            );
        } // 2C. When the post archive is found, we can add it to the breadcrumbs
        else if ( ! empty( $element->rewrite ) && ( ! empty( $element->labels->name ) ) ) {
            $breadcrumbs[] = array(
                $breadcrumb_key_text => apply_filters( 'post_type_archive_title', $element->labels->name, $element->name ),
                $breadcrumb_key_url  => get_post_type_archive_link( $element->name )
            );
        }
    }

    // Add new links to current breadcrumbs array
    if ( ! empty( $links ) && is_array( $links ) ) {
        $first_element  = reset( $links );
        $last_element   = end( $links );
        $b_last_element = ( count( $links ) > 2 && ( ! is_singular() || is_home() ) ) ? prev( $links ) : "";
        $breadcrumbs    = ( ! empty( $breadcrumbs ) ) ? $breadcrumbs : array();

        // Support SEOPress breadcrumbs
        if ( $current_filter == 'seopress_pro_breadcrumbs_crumbs' ) {
            // Append the element before the last element if the last breadcrumb does not have a URL set (e.g. if the /page/ endpoint is used)
            if ( ! empty( $wp->query_vars['paged'] ) && $wp->query_vars['paged'] > 1 && ! empty( $b_last_element[ $breadcrumb_key_url ] ) ) {
                $links = array_merge( array( $first_element ), $breadcrumbs, array( $b_last_element ), array( $last_element ) );
            } else {
                $links = array_merge( array( $first_element ), $breadcrumbs, array( $last_element ) );
            }
        }
    }

    return array_filter( $links );
}

//-----------------------------------------------------------------------------------
// WOOCOMMERCE INTEGRATION
//-----------------------------------------------------------------------------------

/**
 * Fix query on WooCommerce shop page & disable the canonical redirect if WooCommerce query variables are set
 */
function permalink_manager_woocommerce_detect( $query, $old_query, $uri_parts, $pm_query, $content_type ) {
    global $woocommerce, $pm_query;

    $shop_page_id = get_option( 'woocommerce_shop_page_id' );

    // WPML - translate shop page id
    $shop_page_id = apply_filters( 'wpml_object_id', $shop_page_id, 'page', true );

    // Fix shop page
    if ( get_theme_support( 'woocommerce' ) && ! empty( $pm_query['id'] ) && is_numeric( $pm_query['id'] ) && $shop_page_id == $pm_query['id'] ) {
        $query['post_type'] = 'product';
        unset( $query['pagename'] );
    }

    // Fix WooCommerce pages
    if ( ! empty( $woocommerce->query->query_vars ) ) {
        $query_vars = $woocommerce->query->query_vars;

        foreach ( $query_vars as $key => $val ) {
            if ( isset( $query[ $key ] ) ) {
                $query['do_not_redirect'] = 1;
                break;
            }
        }
    }

    return $query;
}

/**
 * If the URI contains %pa_attribute_name% tag, replace it with the value of the attribute
 */
function permalink_manager_woocommerce_product_attributes( $default_uri, $slug, $post, $post_name, $native_uri ) {
    // Do not affect native URIs
    if ( $native_uri ) {
        return $default_uri;
    }

    // Use only for products
    if ( empty( $post->post_type ) || $post->post_type !== 'product' ) {
        return $default_uri;
    }

    preg_match_all( "/%pa_(.[^\%]+)%/", $default_uri, $custom_fields );

    if ( ! empty( $custom_fields[1] ) ) {
        $product = wc_get_product( $post->ID );

        foreach ( $custom_fields[1] as $i => $custom_field ) {
            $attribute_name  = sanitize_title( $custom_field );
            $attribute_value = $product->get_attribute( $attribute_name );

            $default_uri = str_replace( $custom_fields[0][ $i ], Permalink_Manager_Helper_Functions::sanitize_title( $attribute_value ), $default_uri );
        }
    }

    return $default_uri;
}

/**
 * Generate a new custom permalink for duplicated product
 */
function permalink_manager_woocommerce_generate_permalinks_after_duplicate( $new_product, $old_product ) {
    if ( ! empty( $new_product ) ) {
        $product_id = $new_product->get_id();

        // Ignore variations
        if ( $new_product->get_type() === 'variation' || Permalink_Manager_Helper_Functions::is_post_excluded( $product_id, true ) ) {
            return;
        }

        $custom_uri = Permalink_Manager_URI_Functions_Post::get_default_post_uri( $product_id, false, true );
        Permalink_Manager_URI_Functions::save_single_uri( $product_id, $custom_uri, false, true );
    }
}

//-----------------------------------------------------------------------------------
// POLYLANG INTEGRATION
//-----------------------------------------------------------------------------------

/**
 * Return the language code string for specific post or term
 */
function permalink_manager_get_language_code( $element ) {
    global $TRP_LANGUAGE, $icl_adjust_id_url_filter_off, $sitepress, $polylang, $wpml_post_translations, $wpml_term_translations;

    // Disable WPML adjust ID filter
    $icl_adjust_id_url_filter_off = true;

    // Fallback
    if ( is_string( $element ) && strpos( $element, 'tax-' ) !== false ) {
        $element_id = intval( preg_replace( "/[^0-9]/", "", $element ) );
        $element    = get_term( $element_id );
    } else if ( is_numeric( $element ) ) {
        $element = get_post( $element );
    }

    // Polylang
    if ( ! empty( $polylang ) && function_exists( 'pll_get_post_language' ) && function_exists( 'pll_get_term_language' ) ) {
        if ( isset( $element->post_type ) ) {
            $lang_code = pll_get_post_language( $element->ID, 'slug' );
        } else if ( isset( $element->taxonomy ) ) {
            $lang_code = pll_get_term_language( $element->term_id, 'slug' );
        }
    }

    // Enable WPML adjust ID filter
    $icl_adjust_id_url_filter_off = false;

    // Use default language if nothing detected
    return ( ! empty( $lang_code ) ) ? $lang_code : permalink_manager_get_default_language();
}

/**
 * Return the language code for the default language
 */
function permalink_manager_get_default_language() {
    global $sitepress, $translate_press_settings;

    if ( function_exists( 'pll_default_language' ) ) {
        $def_lang = pll_default_language( 'slug' );
    } else if ( is_object( $sitepress ) ) {
        $def_lang = $sitepress->get_default_language();
    } else {
        $def_lang = '';
    }

    return $def_lang;
}

/**
 * Support the endpoints translated by Polylang
 */
function permalink_manager_pl_translate_pagination_endpoint( $endpoints ) {
    $pagination_endpoint = permalink_manager_pl_get_translated_slugs( 'paged' );

    if ( ! empty( $pagination_endpoint ) && ! empty( $pagination_endpoint['translations'] ) && function_exists( 'pll_current_language' ) ) {
        $current_language = pll_current_language();

        if ( ! empty( $current_language ) && ! empty( $pagination_endpoint['translations'][ $current_language ] ) ) {
            $endpoints .= "|" . $pagination_endpoint['translations'][ $current_language ];
        }
    }

    return $endpoints;
}

/**
 * Get the translated slugs array
 */
function permalink_manager_pl_get_translated_slugs( $slug = '' ) {
    $translated_slugs = get_transient( 'pll_translated_slugs' );

    if ( is_array( $translated_slugs ) ) {
        if ( ! empty( $slug ) && ! empty( $translated_slugs[ $slug ] ) ) {
            $translated_slug = $translated_slugs[ $slug ];
        } else {
            $translated_slug = $translated_slugs;
        }
    } else {
        $translated_slug = array();
    }

    return $translated_slug;
}

//-----------------------------------------------------------------------------------
// GUTENBERG INTEGRATION
//-----------------------------------------------------------------------------------

/**
 * Gutenberg integration class
 */
class Permalink_Manager_Gutenberg {

    public function __construct() {
        add_action( 'enqueue_block_editor_assets', array( $this, 'init_gutenberg_hooks' ) );
    }

    /**
     * Initialize Gutenberg hooks
     */
    function init_gutenberg_hooks() {
        global $current_screen, $post;

        // Get displayed post type
        $post_type = $current_screen->post_type;

        // Stop the hook if post type is not supported
        if ( Permalink_Manager_Helper_Functions::is_post_type_disabled( $post_type ) ) {
            return;
        }

        // Custom URI field
        if ( current_user_can( 'publish_posts' ) ) {
            wp_enqueue_script( 'permalink-manager-gutenberg', PERMALINK_MANAGER_URL . '/out/permalink-manager-gutenberg.js', array(
                'wp-plugins',
                'wp-edit-post',
                'wp-element',
                'wp-components'
            ) );

            // Translate the alert text
            if ( $post->post_status == 'auto-draft' ) {
                $alert_text = __( 'Save the draft first to access the permalink editor!', 'permalink-manager' );
            } else if ( $post->post_status == 'publish' ) {
                $alert_text = __( 'The permalinks cannot be edited after publishing the post!', 'permalink-manager' );
            } else {
                $alert_text = '';
            }

            wp_localize_script( 'permalink-manager-gutenberg', 'permalink_manager_gutenberg', array(
                'alert_text' => $alert_text
            ) );
        }
    }
}

//-----------------------------------------------------------------------------------
// WP ALL IMPORT INTEGRATION
//-----------------------------------------------------------------------------------

/**
 * Add a new section to the WP All Import interface
 */
function wpaiextra_uri_display( $content_type, $current_values ) {
    // Check if post type is supported
    if ( $content_type !== 'taxonomies' && Permalink_Manager_Helper_Functions::is_post_type_disabled( $content_type ) ) {
        return;
    } else if ( $content_type == 'taxonomies' && ( ! class_exists( 'Permalink_Manager_URI_Functions_Tax' ) || empty( $current_values['taxonomy_type'] ) || Permalink_Manager_Helper_Functions::is_taxonomy_disabled( $current_values['taxonomy_type'] ) ) ) {
        return;
    }

    // Get custom URI format
    $custom_uri = ( ! empty( $current_values['custom_uri'] ) ) ? sanitize_text_field( $current_values['custom_uri'] ) : "";

    $html = '<div class="wpallimport-collapsed closed wpallimport-section">';
    $html .= '<div class="wpallimport-content-section">';
    $html .= sprintf( '<div class="wpallimport-collapsed-header"><h3>%s</h3></div>', __( 'Permalink Manager', 'permalink-manager' ) );
    $html .= '<div class="wpallimport-collapsed-content">';

    $html .= '<div class="template_input">';
    $html .= Permalink_Manager_UI_Elements::generate_option_field( 'custom_uri', array( 'extra_atts' => 'style="width:100%; line-height: 25px;"', 'placeholder' => __( 'Custom permalink', 'permalink-manager' ), 'value' => $custom_uri ) );
    /* translators: %s: Permastructures admin URL */
    $html .= wpautop( sprintf( __( 'If empty, a default permalink based on your current <a href="%s" target="_blank">permastructure settings</a> will be used.', 'permalink-manager' ), Permalink_Manager_Admin_Functions::get_admin_url( '&section=permastructs' ) ) );
    $html .= '</div>';

    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';

    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo $html;
}

/**
 * Allow to choose if the custom permalink should be updated during import
 */
function wpai_toggle_import( $post_type, $post ) {
    if ( Permalink_Manager_Helper_Functions::is_post_type_disabled( $post_type ) ) {
        return;
    }

    $default_value = ( ! isset( $post['is_update_custom_uri'] ) ) ? 1 : $post['is_update_custom_uri'];

    $html = '<input type="hidden" name="is_update_custom_uri" value="0" />';
    $html .= sprintf( '<input type="checkbox" id="is_update_custom_uri" name="is_update_custom_uri" value="1" %s class="switcher" /> ', checked( 1, $default_value, false ) );
    $html .= sprintf( '<label for="is_import_custom_permalink">%s (%s)</label>', esc_html__( 'Custom permalink', 'permalink-manager' ), esc_html__( 'Permalink Manager', 'permalink-manager' ) );

    echo sprintf( '<div class="input">%s</div>', $html );
}

/**
 * Add a new field to the list of WP All Import options
 */
function wpai_api_options( $all_options ) {
    return $all_options + array( 'custom_uri' => null );
}

/**
 * Save the Permalink Manager plugin data extracted from WP All Import API data feed
 */
function wpai_api_import_function( $importData, $parsedData ) {
    // Check if the array with $parsedData is not empty
    if ( empty( $parsedData ) || empty( $importData['post_type'] ) ) {
        return;
    }

    // Check if the imported elements are terms
    if ( $importData['post_type'] == 'taxonomies' ) {
        $is_term = true;

        if ( ! class_exists( 'Permalink_Manager_URI_Functions_Tax' ) ) {
            return;
        }
    } else if ( Permalink_Manager_Helper_Functions::is_post_type_disabled( $importData['post_type'] ) ) {
        return;
    }

    // Get the parsed custom URI
    $index = ( isset( $importData['i'] ) ) ? $importData['i'] : false;
    $pid   = ( ! empty( $importData['pid'] ) ) ? (int) $importData['pid'] : false;

    if ( isset( $index ) && ! empty( $pid ) && ! empty( $parsedData['custom_uri'][ $index ] ) ) {
        $new_uri = Permalink_Manager_Helper_Functions::sanitize_title( $parsedData['custom_uri'][ $index ] );

        if ( ! empty( $new_uri ) ) {
            if ( ! empty( $is_term ) ) {
                $default_uri = Permalink_Manager_URI_Functions_Tax::get_default_term_uri( $pid );
                $native_uri  = Permalink_Manager_URI_Functions_Tax::get_default_term_uri( $pid, true );
                $custom_uri  = Permalink_Manager_URI_Functions_Tax::get_term_uri( $pid, false, true );
                $old_uri     = ( ! empty( $custom_uri ) ) ? $custom_uri : $native_uri;

                if ( $new_uri !== $old_uri ) {
                    Permalink_Manager_URI_Functions::save_single_uri( $pid, $new_uri, true, true );
                    do_action( 'permalink_manager_updated_term_uri', $pid, $new_uri, $old_uri, $native_uri, $default_uri, $uri_saved = true );
                }
            } else {
                $default_uri = Permalink_Manager_URI_Functions_Post::get_default_post_uri( $pid );
                $native_uri  = Permalink_Manager_URI_Functions_Post::get_default_post_uri( $pid, true );
                $custom_uri  = Permalink_Manager_URI_Functions_Post::get_post_uri( $pid, false, true );
                $old_uri     = ( ! empty( $custom_uri ) ) ? $custom_uri : $native_uri;

                if ( $new_uri !== $old_uri ) {
                    Permalink_Manager_URI_Functions::save_single_uri( $pid, $new_uri, false, true );
                    do_action( 'permalink_manager_updated_post_uri', $pid, $new_uri, $old_uri, $native_uri, $default_uri, $uri_saved = true );
                }
            }
        }
    }
}

//-----------------------------------------------------------------------------------
// WP ALL EXPORT INTEGRATION
//-----------------------------------------------------------------------------------

/**
 * Add a new section to the WP All Export interface
 */
function wpae_custom_uri_section( $sections ) {
    if ( is_array( $sections ) ) {
        $sections['permalink_manager'] = array(
            'title'   => __( 'Permalink Manager', 'permalink-manager' ),
            'content' => 'permalink_manager_fields'
        );
    }

    return $sections;
}

/**
 * Add a new field to the "Permalink Manager" section of the WP All Export interface
 */
function wpae_custom_uri_section_fields( $fields ) {
    if ( is_array( $fields ) ) {
        $fields['permalink_manager_fields'] = array(
            array(
                'label' => 'custom_uri',
                'name'  => 'Custom URI',
                'type'  => 'custom_uri'
            )
        );
    }

    return $fields;
}

/**
 * Add a new column to the export file with the custom permalink for each post/term
 */
function wpae_export_custom_uri( $articles, $options ) {
    if ( ( ! empty( $options['selected_post_type'] ) && $options['selected_post_type'] == 'taxonomies' ) || ! empty( $options['is_taxonomy_export'] ) ) {
        $is_term = true;
    } else {
        $is_term = false;
    }

    foreach ( $articles as &$article ) {
        if ( ! empty( $article['id'] ) ) {
            $item_id = $article['id'];
        } else if ( ! empty( $article['ID'] ) ) {
            $item_id = $article['ID'];
        } else if ( ! empty( $article['Term ID'] ) ) {
            $item_id = $article['Term ID'];
        } else {
            continue;
        }

        if ( ! empty( $is_term ) ) {
            $article['Custom URI'] = Permalink_Manager_URI_Functions_Tax::get_term_uri( $item_id );
        } else {
            $article['Custom URI'] = Permalink_Manager_URI_Functions_Post::get_post_uri( $item_id );
        }
    }

    return $articles;
}

//-----------------------------------------------------------------------------------
// TUTOR LMS INTEGRATION
//-----------------------------------------------------------------------------------

/**
 * Stop canonical redirect for TutorLMS dashboard
 */
function permalink_manager_tutor_lms_stop_redirect() {
    global $wp_query;

    if ( ! empty( $wp_query->query ) ) {
        $query_vars = $wp_query->query;

        // Tutor LMS
        if ( ! empty( $query_vars['tutor_dashboard_page'] ) ) {
            $wp_query->query_vars['do_not_redirect'] = 1;
        }
    }
}