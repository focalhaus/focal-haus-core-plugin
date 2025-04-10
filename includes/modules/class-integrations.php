<?php
/**
 * Plugin Integrations for Focal Haus Core
 * 
 * This file contains essential integration functions to solve issues
 * when removing base slugs from Custom Post Types (CPTs).
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

class Focal_Haus_Core_Integrations {

    /**
     * Initialize integrations
     */
    public function __construct() {
        // WooCommerce integration
        add_filter('request', array($this, 'woocommerce_detect'), 10, 1);
        
        // Stop canonical redirects for specific plugins
        add_action('parse_request', array($this, 'stop_canonical_redirects'), 10);
        
        // SEOPress integration
        add_filter('request', array($this, 'seopress_sitemap_fix'), 10, 1);
        add_filter('seopress_sitemaps_index_cpt_query', array($this, 'seopress_sitemaps_index_cpt_query'), 10, 1);
        add_filter('seopress_sitemaps_cpt_query', array($this, 'seopress_sitemaps_cpt_query'), 10, 2);
    }

    /**
     * Fix query on WooCommerce shop page & disable the canonical redirect if WooCommerce query variables are set
     * 
     * @param array $query The current query variables
     * @return array Modified query variables
     */
    public function woocommerce_detect($query) {
        global $woocommerce;

        $shop_page_id = get_option('woocommerce_shop_page_id');

        // WPML - translate shop page id
        if (function_exists('apply_filters')) {
            $shop_page_id = apply_filters('wpml_object_id', $shop_page_id, 'page', true);
        }

        // Fix shop page
        if (get_theme_support('woocommerce') && !empty($query['pagename']) && !empty($shop_page_id)) {
            $post = get_post($shop_page_id);
            if ($post && $post->post_name === $query['pagename']) {
                $query['post_type'] = 'product';
                unset($query['pagename']);
            }
        }

        // Fix WooCommerce pages
        if (!empty($woocommerce->query->query_vars)) {
            $query_vars = $woocommerce->query->query_vars;

            foreach ($query_vars as $key => $val) {
                if (isset($query[$key])) {
                    $query['do_not_redirect'] = 1;
                    break;
                }
            }
        }

        return $query;
    }

    /**
     * Stop canonical redirect for specific plugin pages
     * 
     * @param object $wp The WordPress object
     */
    public function stop_canonical_redirects($wp) {
        global $wp_query;

        if (!empty($wp->query_vars)) {
            $query_vars = $wp->query_vars;

            // TutorLMS
            if (!empty($query_vars['tutor_dashboard_page'])) {
                $wp_query->query_vars['do_not_redirect'] = 1;
            }

            // LearnDash
            if (!empty($query_vars['sfwd-courses']) || !empty($query_vars['sfwd-lessons']) || 
                !empty($query_vars['sfwd-topic']) || !empty($query_vars['sfwd-quiz']) || 
                !empty($query_vars['sfwd-certificates']) || !empty($query_vars['sfwd-assignment'])) {
                $wp_query->query_vars['do_not_redirect'] = 1;
            }

            // EDD
            if (!empty($query_vars['edd-api'])) {
                $wp_query->query_vars['do_not_redirect'] = 1;
            }

            // Custom post types with query vars
            $custom_post_types = get_post_types(array('_builtin' => false), 'objects');
            if (!empty($custom_post_types)) {
                foreach ($custom_post_types as $cpt) {
                    if (!empty($cpt->query_var) && !empty($query_vars[$cpt->query_var])) {
                        $wp_query->query_vars['do_not_redirect'] = 1;
                        break;
                    }
                }
            }
        }
    }
    /**
     * Fix SEOPress sitemap requests for CPTs with removed base slugs
     * 
     * @param array $query The query variables
     * @return array Modified query variables
     */
    public function seopress_sitemap_fix($query) {
        // Check if this is a SEOPress sitemap request
        if (isset($query['seopress_sitemap']) || isset($query['seopress_cpt'])) {
            // Get CPTs with removed base slugs
            $cpt_without_base = get_option('fhc_cpt_without_base', array());
            
            // If no CPTs have their base slug removed, return the original query
            if (empty($cpt_without_base)) {
                return $query;
            }
            
            // Check if this is a CPT sitemap request
            if (isset($query['seopress_cpt'])) {
                $cpt = $query['seopress_cpt'];
                
                // If this CPT has its base slug removed, set a flag to prevent redirect
                if (isset($cpt_without_base[$cpt])) {
                    $query['do_not_redirect'] = 1;
                }
            }
            
            // For sitemap index, always set the flag to prevent redirect
            if (isset($query['seopress_sitemap']) && $query['seopress_sitemap'] === 'index') {
                $query['do_not_redirect'] = 1;
            }
        }
        
        return $query;
    }
    
    /**
     * Fix SEOPress sitemap index query for CPTs with removed base slugs
     * 
     * @param array $args The query arguments
     * @return array Modified query arguments
     */
    public function seopress_sitemaps_index_cpt_query($args) {
        // Get CPTs with removed base slugs
        $cpt_without_base = get_option('fhc_cpt_without_base', array());
        
        // If no CPTs have their base slug removed, return the original args
        if (empty($cpt_without_base)) {
            return $args;
        }
        
        // Modify the query to include all public post types, including those with removed base slugs
        $args['post_type'] = get_post_types(array('public' => true));
        
        return $args;
    }
    
    /**
     * Fix SEOPress sitemap query for CPTs with removed base slugs
     * 
     * @param array $args The query arguments
     * @param string $post_type The post type
     * @return array Modified query arguments
     */
    public function seopress_sitemaps_cpt_query($args, $post_type) {
        // Get CPTs with removed base slugs
        $cpt_without_base = get_option('fhc_cpt_without_base', array());
        
        // If no CPTs have their base slug removed, return the original args
        if (empty($cpt_without_base)) {
            return $args;
        }
        
        // If this CPT has its base slug removed, ensure it's included in the query
        if (isset($cpt_without_base[$post_type])) {
            $args['post_type'] = $post_type;
            $args['posts_per_page'] = -1; // Get all posts
            $args['orderby'] = 'modified';
            $args['order'] = 'DESC';
        }
        
        return $args;
    }
}

// Initialize the integrations
function focal_haus_core_init_integrations() {
    new Focal_Haus_Core_Integrations();
}
add_action('init', 'focal_haus_core_init_integrations', 99);
