=== Focal Haus Core Plugin ===
Contributors: focalhausdev
Tags: admin, dashboard, menu, hide, security, roles, core, multilingual, google tag manager, gtm
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.1.9
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A comprehensive plugin that provides multiple functionalities for WordPress sites, including multilingual support and Google Tag Manager integration.

== Description ==

The Focal Haus Core Plugin is a powerful toolkit that provides multiple essential functionalities for WordPress sites. This plugin is designed to enhance your WordPress experience with a collection of useful features, all accessible through a clean, tabbed interface.

= Current Features =

* **Hide Dashboard Menu Items**: Allows administrators to choose which dashboard menu items will be hidden for non-admin users. This is particularly useful for client sites where you want to simplify the WordPress admin interface for your clients or restrict access to certain areas of the dashboard.
* **Custom Permalinks**: Remove base slugs from Custom Post Types (CPTs) to create cleaner, more user-friendly URLs.
* **Multilingual Support**: Enhances compatibility with Polylang by allowing duplicate slugs for different languages, improving URL structure for multilingual sites.
* **Google Tag Manager**: Easily integrate Google Tag Manager into your website with options to exclude logged-in users from tracking.
* **Misc. Utilities**: Various utilities including custom login logo, admin toolbar customization, SEOPress editor access, and file editor security options.

= Custom Permalinks Features =

* Remove base slugs from any public Custom Post Type
* Create cleaner, more SEO-friendly URLs
* Maintain compatibility with popular plugins through our integrations system
* Prevent 404 errors and redirect loops when using custom permalink structures

= Multilingual Features =

* Allow duplicate slugs for different languages when using Polylang
* Create more intuitive URL structures for multilingual content
* Maintain language-specific URLs without numeric suffixes
* Seamless integration with Polylang's language management

= Google Tag Manager Features =

* Simple configuration with a clean user interface
* Add your Google Tag Manager ID in one central location
* Automatically insert GTM code in both header and body sections
* Option to exclude logged-in users from tracking
* Code preview showing exactly what will be added to your site
* Proper implementation following Google's recommended practices
* Works with any WordPress theme that supports the wp_head and wp_body_open hooks

= Misc. Utilities =

The plugin includes several utility features:

* **Custom Login Logo**: Add your own logo to the WordPress login page
* **Admin Toolbar Customization**: Remove unnecessary items from the admin toolbar
* **SEOPress Integration**: Give editor users full access to SEOPress features
* **Security Options**: Disable Theme and Plugin File Editors for enhanced security

= Hide Dashboard Menu Items Features =

* User-friendly settings page with a clean, organized layout
* Menu items organized in columns for better usability
* Visual feedback with highlighted selected items
* Hide both top-level menu items and their submenu items
* Admin whitelist feature allowing only specific admin users to see all menu items
* Options to hide menu items from both non-admin users and non-whitelisted admin users
* Removes update count strings from menu names (e.g., "Updates 0", "Comments 00")

= Security =

This plugin follows WordPress security best practices:

* All user input is properly sanitized
* All output is properly escaped
* Capability checks ensure only administrators can access the settings
* Nonces are used to protect against CSRF attacks

== Installation ==

1. Upload the `focal-haus-core` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > Focal Haus Core to configure the plugin's features

== Frequently Asked Questions ==

= Does the multilingual support work with WPML? =

Currently, the multilingual features are designed to work with Polylang. Support for WPML may be added in future updates.

= Will the Hide Menu Items feature hide menu items for administrators? =

By default, administrators will see all menu items. However, with the new admin whitelist feature, you can choose to hide menu items from all admin users except those whose email addresses you've added to the whitelist.

= Will removing CPT base slugs cause conflicts with other plugins? =

The plugin is designed to work with most well-coded plugins. If you encounter any issues with specific plugins, please contact us for support.

= Can I hide submenu items as well? =

Yes! The Hide Menu Items feature allows you to hide both top-level menu items and their submenu items. You can choose to hide an entire menu section or just specific submenu items within it.

= Will hiding menu items remove access to those pages? =

This feature only hides menu items from the dashboard menu. It does not restrict access to the pages themselves. If a user knows the direct URL to a page, they can still access it. For complete access restriction, you should use a role management plugin.

= Does Google Tag Manager work with all themes? =

The Google Tag Manager integration will work with any theme that properly implements the WordPress `wp_head` and `wp_body_open` hooks. Most modern themes do this correctly, but if you're using a custom or older theme, you might need to check that these hooks are properly implemented.

= Why exclude logged-in users from Google Tag Manager? =

Excluding logged-in users (like administrators and editors) from tracking is a common practice to avoid skewing your analytics data with internal traffic. The plugin gives you the option to enable or disable this feature based on your needs.

== Screenshots ==

1. The main settings page with tabs for different functionalities.
2. The Hide Menu Items tab where administrators can select which menu items to hide.

== Changelog ==

= 1.1.9 =
* Added enhanced security for plugin settings
* Restricted access to plugin settings page based on user email domain
* Only users with @focalhaus.com domain or specific authorized email addresses can access settings
* Improved access control for plugin configuration

= 1.1.8 =
* Fixed critical memory exhaustion issue in Admin Whitelist feature
* Simplified and optimized the code for better performance
* Improved email whitelist checking with faster filtering
* Addressed potential memory leaks and infinite loops

= 1.1.7 =
* Completely rewrote the Admin Whitelist logic to ensure it works correctly
* Added improved menu handling for non-whitelisted admin users
* Fixed edge cases in user role and email checking
* Improved global menu management to prevent display issues

= 1.1.6 =
* Fixed a bug in the Admin Whitelist feature where all admins could see all menu items regardless of whitelist settings
* Improved logic to properly restrict menu visibility for non-whitelisted admin users

= 1.1.5 =
* Added Google Tag Manager integration
* Easily add Google Tag Manager code to your site with a simple settings interface
* Option to exclude logged-in users from tracking
* Code preview feature to see exactly what will be added to your site
* Input validation to ensure proper GTM ID format

= 1.1.4 =
* Added Admin Whitelist feature to the Hide Menu Items functionality
* Now you can selectively show menu items to specific admin users by email address
* Added toggle to enable/disable the admin whitelist feature
* Improved UI with a dedicated section for whitelist configuration
* Updated security to properly sanitize and validate email addresses

= 1.1.3 =
* Added new "Misc." tab for miscellaneous features
* Added multilingual support with the ability to allow duplicate slugs for different languages when using Polylang
* Improved URL structure for multilingual sites by removing the need for numeric suffixes in translated content

= 1.1.2 =
* Refactored CPT base slug removal logic to use a more robust method inspired by the 'Remove CPT Base' plugin.
* Updated Permalink Settings tab to include 'Alternation Mode' for hierarchical post types.

= 1.1.1 =
* Fixed critical issue with SEOPress XML sitemaps for CPTs with removed base slugs
* Added specific rewrite rules for SEOPress sitemaps to ensure proper functionality
* Improved compatibility with SEOPress sitemap generation

= 1.1.0 =
* Added Custom Permalinks functionality to remove base slugs from CPTs
* Added Plugin Integrations to ensure compatibility with popular plugins
* Fixed issues with WooCommerce, TutorLMS, LearnDash, and EDD when using custom permalinks
* Improved handling of query variables to prevent 404 errors

= 1.0.0 =
* Initial release
* Added Hide Dashboard Menu Items functionality
* Implemented tabbed interface for future feature expansion
* Added support for removing update count strings from menu names
* Improved UI with visual feedback for checked items
* Fixed submenu text overflow issues
* Enhanced checkbox alignment and spacing

== Upgrade Notice ==

= 1.1.1 =
This update fixes a critical issue with SEOPress XML sitemaps for CPTs with removed base slugs. If you're using SEOPress and experiencing 404 errors on your sitemaps, this update will resolve the issue.

= 1.1.0 =
This update adds Custom Permalinks functionality and Plugin Integrations to ensure compatibility with popular plugins when using custom permalink structures.

= 1.0.0 =
Initial release of the Focal Haus Core Plugin with Hide Dashboard Menu Items functionality.
