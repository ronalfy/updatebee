<?php
/**
 * Add ABSPATH exit guards to webpack-generated asset files.
 *
 * wp-scripts writes build/*.asset.php as `<?php return array(...);`.
 * WordPress loads those files with include, which must still return the
 * asset array when ABSPATH is defined. A direct request has no ABSPATH
 * and should exit before that return.
 *
 * @package UpdateBee
 */

// Prevent direct web access, but allow CLI execution.
if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) {
	exit; // Exit if accessed directly.
}

$build_dir = __DIR__ . '/../build';
$files     = glob( $build_dir . '/*.asset.php' );

if ( false === $files || array() === $files ) {
	echo "No asset PHP files found in {$build_dir}\n";
	exit( 0 );
}

/**
 * Insert the ABSPATH guard immediately after the opening PHP tag.
 *
 * The rest of the file stays a top-level return, so include/require still
 * receives the asset array when ABSPATH is defined.
 *
 * @param string $contents Original file contents.
 * @return string|false Guarded contents, or false when the file is not a return map.
 */
function updatebee_guard_asset_php( $contents ) {
	if ( substr( $contents, 0, 5 ) !== '<?php' ) {
		return false;
	}

	$contents = str_replace( array( "\r\n", "\r" ), "\n", $contents );

	// Drop a guard this script already inserted so the rewrite stays idempotent.
	$contents = preg_replace(
		'/\/\/ Exit if accessed directly\.\nif \( ! defined\( \'ABSPATH\' \) \) \{\n\texit;\n\}\n?/',
		'',
		$contents,
		1
	);

	if ( ! is_string( $contents ) || 1 !== preg_match( '/^<\?php\s+return\s+/s', $contents, $matches ) ) {
		return false;
	}

	$guard = "// Exit if accessed directly.\nif ( ! defined( 'ABSPATH' ) ) {\n\texit;\n}";
	$rest  = substr( $contents, strlen( $matches[0] ) );

	$updated = "<?php\n" . $guard . "\nreturn " . $rest;

	if ( "\n" !== substr( $updated, -1 ) ) {
		$updated .= "\n";
	}

	return $updated;
}

$updated = 0;
$skipped = 0;
$failed  = 0;

foreach ( $files as $file ) {
	$contents = file_get_contents( $file );

	if ( false === $contents ) {
		echo "Failed to read {$file}\n";
		++$failed;
		continue;
	}

	$new_contents = updatebee_guard_asset_php( $contents );

	if ( false === $new_contents ) {
		echo "Skipping {$file}: expected <?php return array(...)\n";
		++$failed;
		continue;
	}

	if ( $new_contents === $contents ) {
		++$skipped;
		continue;
	}

	$result = file_put_contents( $file, $new_contents, LOCK_EX );

	if ( false === $result ) {
		echo "Failed to write {$file}\n";
		++$failed;
		continue;
	}

	echo "Guard added to {$file}\n";
	++$updated;
}

echo "Updated {$updated} file(s), skipped {$skipped} file(s).\n";

if ( $failed > 0 ) {
	echo "Failed {$failed} file(s).\n";
	exit( 1 );
}
