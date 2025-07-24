<?php
/**
 * Template for the overview tab content of the HPOS Compatibility Scanner.
 *
 * @package DPWD\HPOSCompatPlugin
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="overview-tab" class="hpos-tab-content" style="display: none;">
	<p><?php esc_html_e( 'This table shows all installed plugins and their HPOS compatibility status.', 'hpos-compatibility-scanner' ); ?></p>

	<!-- Cache info and refresh button -->
	<div class="hpos-overview-header">
		<div class="hpos-cache-info">
			<span class="hpos-cache-text"><?php esc_html_e( 'Compatibility status is cached for 24 hours.', 'hpos-compatibility-scanner' ); ?></span>
			<span class="hpos-last-updated"><?php esc_html_e( 'Last updated:', 'hpos-compatibility-scanner' ); ?> <span id="hpos-last-updated-time">-</span></span>
		</div>
		<button id="hpos-refresh-cache" class="button">
			<span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
			<?php esc_html_e( 'Refresh Cache', 'hpos-compatibility-scanner' ); ?>
		</button>
	</div>

	<!-- Loading indicator -->
	<div id="hpos-overview-loading" class="hpos-loading"><?php esc_html_e( 'Loading plugins...', 'hpos-compatibility-scanner' ); ?></div>

	<!-- Plugins overview table -->
	<table id="hpos-overview-table" class="hpos-overview-table wp-list-table widefat fixed striped" style="display: none;">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Plugin', 'hpos-compatibility-scanner' ); ?></th>
				<th><?php esc_html_e( 'Version', 'hpos-compatibility-scanner' ); ?></th>
				<th><?php esc_html_e( 'Author', 'hpos-compatibility-scanner' ); ?></th>
				<th><?php esc_html_e( 'HPOS Compatible', 'hpos-compatibility-scanner' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'hpos-compatibility-scanner' ); ?></th>
			</tr>
		</thead>
		<tbody id="hpos-overview-table-body">
			<!-- Plugin rows will be added here via JavaScript -->
		</tbody>
	</table>
</div>