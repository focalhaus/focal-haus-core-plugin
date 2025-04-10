# Focal Haus Core Plugin

A comprehensive WordPress plugin that provides multiple functionalities for WordPress sites including:

- Custom post type permalink management (removing base slugs)
- Dashboard menu item hiding and access restriction
- Other core functionalities for Focal Haus WordPress projects

## Features

### Permalinks Management

The plugin allows you to remove the base slug from custom post type permalinks. For example, instead of:
```
example.com/book/book-name/
```

Your URLs can be simplified to:
```
example.com/book-name/
```

The plugin handles the necessary URL rewriting and redirections to ensure proper SEO and user experience.

### Dashboard Menu Item Hiding

Administrators can hide specific menu items in the WordPress dashboard for certain user roles, providing a cleaner and more focused admin experience.

## Installation

1. Upload the `focal-haus-core` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the settings under the Focal Haus Core settings page

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher

## License

GPL-2.0+
