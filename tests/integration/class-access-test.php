<?php
/**
 * Pins who may call the three AJAX actions.
 *
 * @package DPWD\HPOSCompatPlugin
 */

namespace DPWD\HPOSCompatPlugin\Tests\Integration;

use DPWD\HPOSCompatPlugin\Tests\Support\Ajax_Test_Case;

/**
 * Access_Test class.
 */
final class Access_Test extends Ajax_Test_Case {

	/**
	 * Request values that make the action valid.
	 *
	 * @param string $action Action name.
	 * @return array<string, string>
	 */
	private function valid_post( string $action ): array {
		return 'hpos_scan_plugin' === $action ? array( 'plugin' => 'non-php/non-php.php' ) : array();
	}

	/**
	 * Assert the refusal response.
	 *
	 * @param array<string, mixed> $response Decoded response.
	 */
	private function assert_unauthorized( array $response ): void {
		$this->assertFalse( $response['success'] );
		$this->assertSame( array( 'message' => 'Unauthorized access.' ), $response['data'] );
	}

	/**
	 * An administrator with a valid nonce succeeds.
	 *
	 * @dataProvider actions
	 *
	 * @param string $action Action name.
	 */
	public function test_administrator_with_a_valid_nonce_succeeds( string $action ): void {
		$response = $this->call( $action, $this->valid_post( $action ) );

		$this->assertTrue( $response['success'] );
	}

	/**
	 * Roles without manage_options and visitors are refused.
	 *
	 * @dataProvider refused_users
	 *
	 * @param string      $action Action name.
	 * @param string|null $role   Role of the user, or null for a visitor.
	 */
	public function test_users_without_manage_options_are_refused( string $action, ?string $role ): void {
		$this->assert_unauthorized( $this->call( $action, $this->valid_post( $action ), $role ) );
	}

	/**
	 * An administrator with no nonce, or a nonce for another action, is refused.
	 *
	 * @dataProvider actions
	 *
	 * @param string $action Action name.
	 */
	public function test_administrator_without_a_valid_nonce_is_refused( string $action ): void {
		$this->assert_unauthorized( $this->call( $action, $this->valid_post( $action ), 'administrator', null ) );
		$this->assert_unauthorized( $this->call( $action, $this->valid_post( $action ), 'administrator', 'other_action' ) );
	}

	/**
	 * A scan without a plugin value is refused in the same way.
	 */
	public function test_scan_without_a_plugin_value_is_refused(): void {
		$this->assert_unauthorized( $this->call( 'hpos_scan_plugin' ) );
	}

	/**
	 * Action names.
	 *
	 * @return array<string, array<int, string>>
	 */
	public function actions(): array {
		return array(
			'scan'     => array( 'hpos_scan_plugin' ),
			'overview' => array( 'hpos_get_all_plugins_compatibility' ),
			'refresh'  => array( 'hpos_refresh_compatibility_cache' ),
		);
	}

	/**
	 * Every action with every user that must be refused.
	 *
	 * @return array<string, array<int, string|null>>
	 */
	public function refused_users(): array {
		$cases = array();

		foreach ( $this->actions() as $action_label => $action ) {
			foreach ( array( 'editor', 'subscriber', null ) as $role ) {
				$cases[ $action_label . ', ' . ( $role ?? 'visitor' ) ] = array( $action[0], $role );
			}
		}

		return $cases;
	}
}
