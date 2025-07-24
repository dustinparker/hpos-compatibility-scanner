<?php
/**
 * Template for the scan tab content of the HPOS Compatibility Scanner.
 *
 * @package DPWD\HPOSCompatPlugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Template variables for the scan tab.
 *
 * @var array $plugins List of installed plugins.
 */
?>
<div id="scan-tab" class="hpos-tab-content">
	<div class="hpos-controls">
		<label for="hpos-plugin-selector" class="hpos-label"><?php esc_html_e( 'Select a Plugin to Scan:', 'hpos-compatibility-scanner' ); ?></label>
		<select id="hpos-plugin-selector" class="hpos-select">
			<option value=""><?php esc_html_e( 'Select a Plugin', 'hpos-compatibility-scanner' ); ?></option>
			<?php foreach ( $plugins as $plugin_path => $details ) : ?>
				<option value="<?php echo esc_attr( $plugin_path ); ?>"><?php echo esc_html( $details['Name'] ); ?></option>
			<?php endforeach; ?>
		</select>
		<button id="hpos-scan-button" class="button button-primary hpos-scan-button"><?php esc_html_e( 'Scan Plugin', 'hpos-compatibility-scanner' ); ?></button>
	</div>
	<div id="hpos-scan-results" class="hpos-results"></div>
</div>