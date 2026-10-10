<?php
/**
 * Whitelist check for names that end up in SQL.
 *
 * @package DPWD\HPOSCompatPlugin
 */

namespace DPWD\HPOSCompatPlugin\Tests\Support;

use InvalidArgumentException;

/**
 * Accepts only letters, digits and underscores.
 */
final class Identifier {

	/**
	 * Return the value when it is a safe identifier.
	 *
	 * @param string $value Database name or table prefix.
	 * @return string The same value.
	 * @throws InvalidArgumentException When the value has any other character or is empty.
	 */
	public static function check( string $value ): string {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_]+$/D', $value ) ) {
			throw new InvalidArgumentException( 'Identifiers may contain only letters, digits and underscores.' );
		}

		return $value;
	}
}
