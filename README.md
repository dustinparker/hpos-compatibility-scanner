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

1. **Download the Plugin**:
    - Visit the [GitHub releases page](https://github.com/dustinparker/hpos-compatibility-scanner/releases).
    - Download the latest release zip file.
2. **Upload to WordPress**:
    - Navigate to `Plugins > Add New`.
    - Click on `Upload Plugin`.
    - Upload the downloaded `.zip` file and click `Install Now`.
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

## Contributing

We welcome contributions from the community. Here's how you can help:

1. Fork the repository.
2. Create a new branch for your feature or fix (example: `feature/feature-name`)
3. Submit a pull request describing your changes.

## License

This plugin is licensed under the GNU General Public License v2.0 or later (GPL-2.0-or-later).

## Credits

- Original Author: Robert DeVore
- Current Maintainer: Dustin Parker
- Website: https://dustinparkerwebdev.com/
- GitHub Repository: https://github.com/dustinparker/hpos-compatibility-scanner/