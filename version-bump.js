/**
 * Automatically bump the version of the plugin.
 *
 * @package DPWD\HPOSCompatPlugin
 */

const fs       = require( 'fs' );
const bumpType = process.argv[ 2 ];

function bumpPluginVersion( path ) {
	const pluginVersion = /(\d+\.\d+\.\d+)/g;

	let fileContent = fs.readFileSync( path, 'utf8' );

	const versionStrings = fileContent.match( pluginVersion );

	if ( ! versionStrings[ 0 ] ) {
		return;
	}

	let versionParts = versionStrings[ 0 ].split( '.' );

	if ( 'major' === bumpType ) {
		versionParts[ 2 ] = 0;
		if ( 9 > versionParts[ 1 ] ) {
			versionParts[ 1 ]++;
		} else {
			versionParts[ 1 ] = 0;
			versionParts[ 0 ]++;
		}
	} else {
		if ( 9 > versionParts[ 2 ] ) {
			versionParts[ 2 ]++;
		} else {
			versionParts[ 2 ] = 0;
			if ( 9 > versionParts[ 1 ] ) {
				versionParts[ 1 ]++;
			} else {
				versionParts[ 1 ] = 0;
				versionParts[ 0 ]++;
			}
		}
	}

	const bumpedPluginVersion = versionParts.join( '.' );

	fileContent = fileContent.replace( pluginVersion, bumpedPluginVersion );

	fs.writeFileSync( path, fileContent );
}

bumpPluginVersion( './hpos-compatibility-scanner.php' );