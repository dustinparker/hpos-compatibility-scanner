<?php
/**
 * Exception for a scan folder that cannot be opened.
 *
 * @package DPWD\HPOSCompatPlugin
 */

namespace DPWD\HPOSCompatPlugin\Scanner;

use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * Class Directory_Open_Exception
 *
 * Thrown by Directory_Scanner when the folder it is given cannot be opened. Exceptions raised during the walk are not wrapped.
 *
 * @internal This class may change without notice.
 * @since 1.0.3
 */
class Directory_Open_Exception extends RuntimeException {
}
