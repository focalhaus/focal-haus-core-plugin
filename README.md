# Focal Haus Core Plugin

A comprehensive plugin that provides multiple functionalities for WordPress sites, including multilingual support.

## Description

The Focal Haus Core Plugin is a powerful toolkit that provides multiple essential functionalities for WordPress sites. This plugin is designed to enhance your WordPress experience with a collection of useful features, all accessible through a clean, tabbed interface.

### Current Features

* **Hide Dashboard Menu Items**: Allows administrators to choose which dashboard menu items will be hidden for non-admin users. This is particularly useful for client sites where you want to simplify the WordPress admin interface for your clients or restrict access to certain areas of the dashboard.
* **Custom Permalinks**: Remove base slugs from Custom Post Types (CPTs) to create cleaner, more user-friendly URLs.
* **Multilingual Support**: Enhances compatibility with Polylang by allowing duplicate slugs for different languages, improving URL structure for multilingual sites.
* **Misc. Utilities**: Various utilities including custom login logo, admin toolbar customization, SEOPress editor access, and file editor security options.

### Custom Permalinks Features

* Remove base slugs from any public Custom Post Type
* Create cleaner, more SEO-friendly URLs
* Maintain compatibility with popular plugins
* Prevent 404 errors and redirect loops when using custom permalink structures

### Multilingual Features

* Allow duplicate slugs for different languages when using Polylang
* Create more intuitive URL structures for multilingual content
* Maintain language-specific URLs without numeric suffixes
* Seamless integration with Polylang's language management

### Hide Dashboard Menu Items Features

* User-friendly settings page with a clean, organized layout
* Menu items organized in columns for better usability
* Visual feedback with highlighted selected items
* Hide both top-level menu items and their submenu items
* Only administrators can see and modify the settings
* Menu items are hidden only for non-admin users
* Removes update count strings from menu names (e.g., "Updates 0", "Comments 00")

### Misc. Utilities

* **Customize Login Page**: Add your own logo to the WordPress login page
* **Admin Toolbar Customization**: Remove unnecessary items from the admin toolbar
* **SEOPress Integration**: Give editor users full access to SEOPress features
* **Security Options**: Disable Theme and Plugin File Editors for enhanced security

### Security

This plugin follows WordPress security best practices:

* All user input is properly sanitized
* All output is properly escaped
* Capability checks ensure only administrators can access the settings
* Nonces are used to protect against CSRF attacks

## Installation

1. Upload the `focal-haus-core` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > Focal Haus Core to configure the plugin's features

## Frequently Asked Questions

### Does the multilingual support work with WPML?

Currently, the multilingual features are designed to work with Polylang. Support for WPML may be added in future updates.

### Will the Hide Menu Items feature hide menu items for administrators?

No, administrators will always see all menu items. This feature only affects non-admin users.

### Will removing CPT base slugs cause conflicts with other plugins?

Our plugin includes special integrations to prevent conflicts with popular plugins like SEOPress, WooCommerce, TutorLMS, LearnDash, and Easy Digital Downloads. If you encounter any issues with other plugins, please contact us for support.

### Can I hide submenu items as well?

Yes! The Hide Menu Items feature allows you to hide both top-level menu items and their submenu items. You can choose to hide an entire menu section or just specific submenu items within it.

### Will hiding menu items remove access to those pages?

This feature only hides menu items from the dashboard menu. It does not restrict access to the pages themselves. If a user knows the direct URL to a page, they can still access it. For complete access restriction, you should use a role management plugin.

## Requirements

* WordPress 5.0 or higher
* PHP 7.2 or higher

## License

GPL-2.0+
