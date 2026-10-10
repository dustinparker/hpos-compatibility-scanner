<?php
/**
 * Tests for the identifier whitelist.
 *
 * @package DPWD\HPOSCompatPlugin
 */

namespace DPWD\HPOSCompatPlugin\Tests\Unit;

use DPWD\HPOSCompatPlugin\Tests\Support\Identifier;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Identifier_Test class.
 */
final class Identifier_Test extends TestCase {

	/**
	 * Safe values come back unchanged.
	 *
	 * @dataProvider accepted_values
	 *
	 * @param string $value Value to check.
	 */
	public function test_accepts_safe_values( string $value ): void {
		$this->assertSame( $value, Identifier::check( $value ) );
	}

	/**
	 * Unsafe values throw.
	 *
	 * @dataProvider rejected_values
	 *
	 * @param string $value Value to check.
	 */
	public function test_rejects_unsafe_values( string $value ): void {
		$this->expectException( InvalidArgumentException::class );
		Identifier::check( $value );
	}

	/**
	 * Accepted values.
	 *
	 * @return array<string, array<int, string>>
	 */
	public function accepted_values(): array {
		return array(
			'table prefix'  => array( 'wptests_1_' ),
			'database name' => array( 'hpos_scanner_tests' ),
			'digits only'   => array( '0' ),
			'mixed case'    => array( 'AbC_9' ),
		);
	}

	/**
	 * Rejected values.
	 *
	 * @return array<string, array<int, string>>
	 */
	public function rejected_values(): array {
		return array(
			'sql injection'    => array( 'tests; DROP DATABASE x' ),
			'hyphen'           => array( 'a-b' ),
			'empty'            => array( '' ),
			'space'            => array( 'wp tests' ),
			'trailing newline' => array( "wptests_\n" ),
			'backtick'         => array( 'a`b' ),
			'path'             => array( '../x' ),
		);
	}
}
