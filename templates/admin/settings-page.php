<?php
/**
 * Template for the main settings page of the HPOS Compatibility Scanner.
 *
 * @package DPWD\HPOSCompatPlugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Template variables.
 *
 * @var array $plugins List of installed plugins.
 */
?>
<div class="wrap hpos-settings-page">
	<h1>
		<?php esc_html_e( 'HPOS Compatibility Scanner', 'hpos-compatibility-scanner' ); ?>
		<a id="hpos-scanner-support-btn" href="https://github.com/dustinparker/hpos-compatibility-scanner/issues" target="_blank" class="button button-alt" style="margin-left: 10px;">
			<span class="dashicons dashicons-format-chat" style="vertical-align: middle;"></span> <?php esc_html_e( 'Support', 'hpos-compatibility-scanner' ); ?>
		</a>
		<a id="hpos-scanner-docs-btn" href="https://github.com/dustinparker/hpos-compatibility-scanner/" target="_blank" class="button button-alt" style="margin-left: 5px;">
			<span class="dashicons dashicons-media-document" style="vertical-align: middle;"></span> <?php esc_html_e( 'Documentation', 'hpos-compatibility-scanner' ); ?>
		</a>
	</h1>
	<hr />

	<!-- Tab navigation -->
	<h2 class="nav-tab-wrapper">
		<a href="#scan-tab" class="nav-tab nav-tab-active" id="scan-tab-link"><?php esc_html_e( 'Scan Plugin', 'hpos-compatibility-scanner' ); ?></a>
		<a href="#overview-tab" class="nav-tab" id="overview-tab-link"><?php esc_html_e( 'Plugins Overview', 'hpos-compatibility-scanner' ); ?></a>
	</h2>

	<!-- Tab content -->
	<?php
	// Include tab templates.
	require HPOS_COMPATIBILITY_SCANNER_PLUGIN_DIR_PATH . '/templates/admin/tabs/scan-tab.php';
	require HPOS_COMPATIBILITY_SCANNER_PLUGIN_DIR_PATH . '/templates/admin/tabs/overview-tab.php';
	?>
</div>