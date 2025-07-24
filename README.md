# HPOS Compatibility Scanner for WooCommerce®

HPOS Compatibility Scanner is a free WooCommerce® plugin designed to identify potential issues in plugins related to High-Performance Order Storage (HPOS) compatibility. 

It scans plugins for direct database access or inappropriate usage of WordPress APIs, helping developers ensure their plugins align with WooCommerce's HPOS requirements.
    
---

## About HPOS

High-Performance Order Storage (HPOS) is a WooCommerce feature that stores order data in custom tables instead of WordPress post meta tables. This provides better performance and scalability for stores with large numbers of orders.

Plugins that directly access the WordPress database tables for order data may not work correctly when HPOS is enabled. This scanner helps identify potential compatibility issues in plugins.

---

## Features

- Scans selected plugins for HPOS-related issues.
- Identifies usage of specific WordPress APIs that may cause compatibility problems.
- Provides detailed scan results in an easy-to-read format.
- Supports exporting results as a CSV file for further analysis.
- Displays an overview of all installed plugins' HPOS compatibility status.
- Caches compatibility results for 24 hours for better performance.
- Includes localization support for translations.
- Seamless integration with the WordPress admin dashboard.

---

## Installation

1. **Download the Plugin**: Clone or download the repository as a `.zip` file.
2. **Upload to WordPress**:
    - Navigate to `Plugins > Add New`.
    - Click on `Upload Plugin`.
    - Upload the `.zip` file and click `Install Now`.
3. **Activate the Plugin**:
    - Go to `Plugins > Installed Plugins`.
    - Activate the "HPOS Compatibility Scanner" plugin.

---

## Usage

1. Go to `HPOS Scanner` in the WordPress admin menu.
2. The plugin offers two main tabs:

### Scan Plugin Tab
1. Select a plugin to scan from the dropdown menu.
2. Click the **Scan Plugin** button to start the analysis.
3. View the results, including whether the plugin declares HPOS compatibility.
4. Expand code snippets to see potential issues in context.
5. Optionally download the results as a CSV file for further analysis.

### Plugins Overview Tab
1. View a table of all installed plugins and their HPOS compatibility status.
2. The compatibility status is cached for 24 hours for better performance.
3. Click the **Refresh Cache** button to update the compatibility status for all plugins.
4. Click the **Scan Plugin** button next to any plugin to perform a detailed scan.

---

## Plugin Architecture

The plugin follows a modern, object-oriented architecture with proper namespacing:

- **Namespace**: `DPWD\HPOSCompatPlugin`
- **Directory Structure**:
  - `src/` - Contains all PHP classes organized by functionality
  - `src/Admin/` - Admin interface and settings page
  - `src/Scanner/` - Core scanning functionality
  - `src/Compatibility/` - HPOS compatibility checking and caching

### Extending the Plugin

To extend or modify the plugin:

1. Follow the existing namespace structure (`DPWD\HPOSCompatPlugin`)
2. Use Composer for autoloading and dependencies
3. Follow WordPress coding standards and best practices

## Development

This plugin uses modern frontend build tools to optimize JavaScript and CSS assets.

### Requirements

- PHP 8.2 or later
- WordPress 6.0 or later
- WooCommerce 7.0 or later
- Node.js 16 or later
- npm 8 or later
- Composer

### Setup

1. Clone the repository
2. Run `composer install` to install PHP dependencies
3. Run `npm install` to install JavaScript dependencies

### Available npm Scripts

- `npm run build` - Build the plugin for production (minified JS/CSS)
- `npm run build:dev` - Build the plugin for development
- `npm run build:js` - Build only JavaScript files
- `npm run build:css` - Build only CSS files
- `npm run watch` - Watch for changes in JS and CSS files and rebuild automatically
- `npm run lint` - Run linting on JS and CSS files
- `npm run lint:js` - Run linting on JS files only
- `npm run lint:css` - Run linting on CSS files only

### Asset Structure

- JavaScript files are located in `assets/js/`
- CSS files are located in `assets/css/`
- Source files are processed and minified versions are created with the `.min` suffix

## Contributing

We welcome contributions from the community. Here's how you can help:

1. Fork the repository.
2. Create a new branch for your feature or fix (example: `feature/feature-name`)
3. Submit a pull request describing your changes.

## License

This plugin is licensed under the GNU General Public License v2.0 or later (GPL-2.0-or-later).

## Changelog

### 1.0.2 (Current)
- Added compatibility caching for better performance
- Added Plugins Overview tab to view all plugins' compatibility status
- Fixed various bugs and improved scanning accuracy

### 1.0.1
- Improved scanning algorithm to reduce false positives
- Added support for more compatibility declaration patterns

### 1.0.0
- Initial release

## Credits

- Original Author: Robert DeVore
- Current Maintainer: Dustin Parker
- Website: https://dustinparkerwebdev.com/
- GitHub Repository: https://github.com/dustinparker/hpos-compatibility-scanner/