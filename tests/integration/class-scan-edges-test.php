<?php
/**
 * Pins the scan edge cases: snippet windows, the comment rule, line endings, file names and error messages.
 *
 * @package DPWD\HPOSCompatPlugin
 */

namespace DPWD\HPOSCompatPlugin\Tests\Integration;

use DPWD\HPOSCompatPlugin\Tests\Support\Ajax_Test_Case;
use DPWD\HPOSCompatPlugin\Tests\Support\Fixture_Plugins;
use UnexpectedValueException;
use WPAjaxDieContinueException;

/**
 * Scan_Edges_Test class.
 */
final class Scan_Edges_Test extends Ajax_Test_Case {

	/**
	 * Folders made unreadable by a test, restored in tear_down().
	 *
	 * @var string[]
	 */
	private array $locked = array();

	/**
	 * Restore permissions and the fixture copy, then run the base checks.
	 */
	public function tear_down(): void {
		if ( array() !== $this->locked ) {
			foreach ( $this->locked as $path ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Test support outside WordPress.
				chmod( $path, 0755 );
			}
			$this->locked = array();
			Fixture_Plugins::reset();
		}

		parent::tear_down();
	}

	/**
	 * Scan a fixture plugin as an administrator.
	 *
	 * @param string $plugin Plugin file relative to the plugins folder.
	 * @return array<string, mixed>
	 */
	private function scan( string $plugin ): array {
		return $this->call( 'hpos_scan_plugin', array( 'plugin' => $plugin ) );
	}

	/**
	 * Make a folder in the plugins copy and lock it.
	 *
	 * @param string $relative Folder relative to the plugins folder.
	 * @return string Absolute path.
	 */
	private function make_locked_folder( string $relative ): string {
		$path = Fixture_Plugins::path() . '/' . $relative;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Test support outside WordPress.
		$this->assertTrue( mkdir( $path, 0755 ) );
		$this->locked[] = $path;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Test support outside WordPress.
		$this->assertTrue( chmod( $path, 0000 ) );

		return $path;
	}

	/**
	 * Files in the snippet edges plugin are all scanned, and a match on line 1 has no earlier context.
	 */
	public function test_snippet_edges(): void {
		$normalized = $this->assert_expected( 'scan-snippet-edges', $this->scan( 'snippet-edges/snippet-edges.php' ) );
		$issues     = $normalized['data']['issues'];

		$this->assertCount( 5, $issues );

		$first = $issues['/snippet-edges/first-line.php'][0];
		$this->assertSame( 1, $first['line'] );
		$this->assertSame( '', $first['context']['before'] );
		$this->assertStringStartsWith( '>    1:', $first['snippet'] );
	}

	/**
	 * Only comment lines starting with the two skipped markers are ignored.
	 */
	public function test_comment_rule(): void {
		$normalized = $this->assert_expected( 'scan-comment-rule', $this->scan( 'comment-rule/comment-rule.php' ) );
		$issues     = $normalized['data']['issues']['/comment-rule/comment-rule.php'];

		$this->assertCount( 3, $issues );
		$this->assertSame( 15, $issues[0]['line'] );
		$this->assertSame( 16, $issues[1]['line'] );
		$this->assertSame( 17, $issues[2]['line'] );
	}

	/**
	 * A CRLF file is reported, and PHP's line reader strips the carriage return from every field.
	 */
	public function test_crlf(): void {
		$normalized = $this->assert_expected( 'scan-crlf', $this->scan( 'crlf/crlf.php' ) );
		$issues     = $normalized['data']['issues']['/crlf/crlf.php'];

		$this->assertCount( 1, $issues );
		$this->assertStringNotContainsString( '\r', wp_json_encode( $issues[0] ) );
	}

	/**
	 * A file with no final newline is reported with the lines the scanner can see.
	 */
	public function test_no_final_newline(): void {
		$normalized = $this->assert_expected( 'scan-no-final-newline', $this->scan( 'no-final-newline/no-final-newline.php' ) );
		$issues     = $normalized['data']['issues']['/no-final-newline/no-final-newline.php'];

		$this->assertCount( 1, $issues );
		$this->assertSame( 11, $issues[0]['line'] );
		$this->assertSame( '', $issues[0]['context']['after'] );
		$this->assertStringEndsWith( '12: }', $issues[0]['snippet'] );
	}

	/**
	 * A file with an upper case extension is scanned and reported under its own name.
	 */
	public function test_uppercase_extension(): void {
		$normalized = $this->assert_expected( 'scan-uppercase-extension', $this->scan( 'uppercase-extension/uppercase-extension.php' ) );

		$this->assertSame( array( '/uppercase-extension/sample.PHP' ), array_keys( $normalized['data']['issues'] ) );
		$this->assertCount( 1, $normalized['data']['issues']['/uppercase-extension/sample.PHP'] );
	}

	/**
	 * A plugin with no findings gets the message and an empty issue list.
	 */
	public function test_no_findings(): void {
		$response = $this->scan( 'non-php/non-php.php' );

		$this->assert_expected( 'scan-non-php', $response );
		$this->assertTrue( $response['success'] );
		$this->assertSame( array(), $response['data']['issues'] );
		$this->assertSame( 'No issues found in the selected plugin.', $response['data']['message'] );
	}

	/**
	 * A plugin that is a single file at the plugins root scans the whole plugins folder.
	 */
	public function test_single_file_plugin_scans_the_plugins_folder(): void {
		$normalized = $this->assert_expected( 'scan-single-file', $this->scan( 'single-file.php' ) );
		$files      = array_keys( $normalized['data']['issues'] );

		$this->assertContains( '/single-file.php', $files );
		$this->assertContains( '/search-terms/search-terms.php', $files );
	}

	/**
	 * A plugin file that is missing gets its own message.
	 */
	public function test_missing_plugin_file_message(): void {
		$response = $this->scan( 'does-not-exist/does-not-exist.php' );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Plugin file does not exist.', $response['data']['message'] );
	}

	/**
	 * A plugin folder the process cannot read gets the inaccessible message.
	 */
	public function test_unreadable_directory_message(): void {
		$this->skip_without_folder_permissions();
		$this->make_locked_folder( 'unreadable-dir' );

		$response = $this->scan( 'unreadable-dir' );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Plugin directory not found or inaccessible.', $response['data']['message'] );
	}

	/**
	 * An unreadable subfolder is not handled: the exception leaves the AJAX handler before any JSON is sent.
	 */
	public function test_unreadable_subfolder_throws(): void {
		$this->skip_without_folder_permissions();
		$this->make_locked_folder( 'comment-rule/locked' );

		$this->_setRole( 'administrator' );
		$_POST = array(
			'plugin' => 'comment-rule/comment-rule.php',
			'nonce'  => wp_create_nonce( 'hpos_scanner_nonce' ),
		);

		$buffer_level = ob_get_level();

		try {
			$this->_handleAjax( 'hpos_scan_plugin' );
			$this->fail( 'The scan finished without an exception.' );
		} catch ( UnexpectedValueException $e ) {
			$this->assertStringContainsString( 'locked', $e->getMessage() );
			$this->assertStringContainsString( 'Permission denied', $e->getMessage() );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->fail( 'The scan sent a response: ' . $this->_last_response );
		} finally {
			// The handler opened an output buffer and the exception skipped its cleanup.
			while ( ob_get_level() > $buffer_level ) {
				ob_end_clean();
			}
			$_POST = array();
		}
	}
}
