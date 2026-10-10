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
