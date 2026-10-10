<?php
/**
 * Base class for tests that call the plugin's AJAX actions.
 *
 * @package DPWD\HPOSCompatPlugin
 */

namespace DPWD\HPOSCompatPlugin\Tests\Support;

use WP_Ajax_UnitTestCase;
use WPAjaxDieContinueException;

/**
 * Runs an AJAX action as a given user and returns the decoded JSON response.
 */
abstract class Ajax_Test_Case extends WP_Ajax_UnitTestCase {

	/**
	 * Call an AJAX action.
	 *
	 * @param string      $action       Action name, without the wp_ajax_ prefix.
	 * @param array       $post         Request values.
	 * @param string|null $role         Role of the current user, or null for a visitor.
	 * @param string|null $nonce_action Action to create the nonce for, or null to send no nonce.
	 * @return array<string, mixed> The decoded response.
	 */
	protected function call( string $action, array $post = array(), ?string $role = 'administrator', ?string $nonce_action = 'hpos_scanner_nonce' ): array {
		if ( null === $role ) {
			wp_set_current_user( 0 );
		} else {
			$this->_setRole( $role );
		}

		if ( null !== $nonce_action ) {
			$post['nonce'] = wp_create_nonce( $nonce_action );
		}

		$_POST = $post; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Building the request, not reading it.

		try {
			$this->_handleAjax( $action );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e ); // The handler finished; the response is in the buffer.
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertIsArray( $response, 'The response was not JSON: ' . $this->_last_response );

		$this->_last_response = '';
		$_POST                = array();

		return $response;
	}

	/**
	 * Skip a test that relies on folder permissions.
	 *
	 * Root reads any folder. Infection's include interceptor reports an unreadable path as missing,
	 * so inside a mutant process the test would fail whatever the mutation is.
	 */
	protected function skip_without_folder_permissions(): void {
		if ( function_exists( 'posix_geteuid' ) && 0 === posix_geteuid() ) {
			$this->markTestSkipped( 'Running as root, so folder permissions do not apply.' );
		}

		if ( class_exists( 'Infection\\StreamWrapper\\IncludeInterceptor', false ) ) {
			$this->markTestSkipped( 'Infection reports an unreadable folder as missing.' );
		}
	}

	/**
	 * Compare a response with its stored file, or rewrite the file in regenerate mode.
	 *
	 * @param string               $name     Expected file name without the extension.
	 * @param array<string, mixed> $response Decoded response.
	 * @return array<string, mixed> The normalized response.
	 */
	protected function assert_expected( string $name, array $response ): array {
		$normalized = Expected_Response::normalize( $response );

		if ( Expected_Response::updating() ) {
			Expected_Response::save( $name, $normalized );
		}

		$this->assertSame( Expected_Response::load( $name ), $normalized );

		return $normalized;
	}

	/**
	 * Assert that nothing tried to reach the network, then reset the object cache flag.
	 */
	public function tear_down(): void {
		$this->assertSame(
			0,
			$GLOBALS['hpos_scanner_tests_http_attempts'],
			'Outbound HTTP was attempted: ' . implode( ', ', $GLOBALS['hpos_scanner_tests_http_urls'] )
		);
		wp_using_ext_object_cache( false );

		parent::tear_down();
	}
}
