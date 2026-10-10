<?php
/**
 * Copies the fixture plugins to a temporary plugins folder.
 *
 * @package DPWD\HPOSCompatPlugin
 */

namespace DPWD\HPOSCompatPlugin\Tests\Support;

use FilesystemIterator;
use InvalidArgumentException;
use RuntimeException;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages go to the terminal, not a browser.

/**
 * Owns the temporary plugins folder of the test process.
 */
final class Fixture_Plugins {

	/**
	 * Name prefix shared by every folder this class may delete.
	 */
	private const FOLDER_PREFIX = '/hpos-scanner-tests-';

	/**
	 * The tracked fixture folder.
	 */
	public static function source(): string {
		return dirname( __DIR__ ) . '/fixtures/plugins';
	}

	/**
	 * Parallel process token, taken from TEST_TOKEN.
	 *
	 * @throws InvalidArgumentException When the token is not a safe identifier.
	 */
	public static function token(): string {
		$token = getenv( 'TEST_TOKEN' );

		return Identifier::check( false === $token || '' === $token ? '0' : $token );
	}

	/**
	 * Short hash of the checkout path, so checkouts and worktrees get separate folders.
	 */
	public static function checkout_id(): string {
		return substr( md5( dirname( __DIR__, 2 ) ), 0, 8 );
	}

	/**
	 * The plugins folder of this process, used as WP_PLUGIN_DIR.
	 */
	public static function path(): string {
		return sys_get_temp_dir() . self::FOLDER_PREFIX . self::checkout_id() . '-' . self::token();
	}

	/**
	 * The content folder of this process, with an empty themes folder so WordPress finds a theme root.
	 *
	 * @throws RuntimeException When the folder cannot be created.
	 */
	public static function content_path(): string {
		$path = self::path() . '-content';

		if ( ! is_dir( $path . '/themes' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Test support outside WordPress.
			if ( ! mkdir( $path . '/themes', 0755, true ) ) {
				throw new RuntimeException( 'Cannot create ' . $path );
			}
		}

		return $path;
	}

	/**
	 * Replace the temporary folder with a fresh copy of the fixtures.
	 *
	 * @throws RuntimeException When a folder cannot be created or a file copied.
	 * @return string The folder path.
	 */
	public static function create(): string {
		$path = self::path();

		if ( file_exists( $path ) ) {
			self::remove( $path );
		}

		self::copy_tree( self::source(), $path );

		return $path;
	}

	/**
	 * Delete a temporary folder made by this class.
	 *
	 * @param string $path Folder to delete.
	 * @throws InvalidArgumentException When the path is outside the temporary root.
	 */
	public static function remove( string $path ): void {
		if ( 0 !== strpos( $path, sys_get_temp_dir() . self::FOLDER_PREFIX ) || false !== strpos( $path, '..' ) ) {
			throw new InvalidArgumentException( 'Refusing to remove a path outside the fixture root.' );
		}

		if ( ! file_exists( $path ) ) {
			return;
		}

		self::delete_tree( $path );
	}

	/**
	 * Remove and recreate the folder, for tests that changed the copy.
	 */
	public static function reset(): void {
		self::remove( self::path() );
		self::create();
	}

	/**
	 * Copy a folder recursively, keeping file bytes as they are.
	 *
	 * @param string $from Source folder.
	 * @param string $to   Target folder.
	 * @throws RuntimeException When a folder cannot be created or a file copied.
	 */
	private static function copy_tree( string $from, string $to ): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Test support outside WordPress.
		if ( ! is_dir( $to ) && ! mkdir( $to, 0755, true ) ) {
			throw new RuntimeException( 'Cannot create ' . $to );
		}

		foreach ( new FilesystemIterator( $from ) as $entry ) {
			$target = $to . '/' . $entry->getFilename();

			if ( $entry->isDir() ) {
				self::copy_tree( $entry->getPathname(), $target );
				continue;
			}

			if ( ! copy( $entry->getPathname(), $target ) ) {
				throw new RuntimeException( 'Cannot copy ' . $entry->getPathname() );
			}
		}
	}

	/**
	 * Delete a file or folder, restoring permissions first.
	 *
	 * @param string $path File or folder to delete.
	 * @throws RuntimeException When something cannot be deleted.
	 */
	private static function delete_tree( string $path ): void {
		if ( is_link( $path ) || ! is_dir( $path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Test support outside WordPress.
			chmod( $path, 0644 );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test support outside WordPress.
			if ( ! unlink( $path ) ) {
				throw new RuntimeException( 'Cannot delete ' . $path );
			}
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Test support outside WordPress.
		chmod( $path, 0755 );

		foreach ( new FilesystemIterator( $path ) as $entry ) {
			self::delete_tree( $entry->getPathname() );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test support outside WordPress.
		if ( ! rmdir( $path ) ) {
			throw new RuntimeException( 'Cannot delete ' . $path );
		}
	}
}
