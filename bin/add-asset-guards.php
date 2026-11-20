<?php
/**
 * Add ABSPATH exit guards to webpack-generated asset files.
 *
 * The wp-scripts build writes build/*.asset.php as `<?php return array(...);`.
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

/**
 * Write one line to standard output.
 *
 * These scripts only run from the command line, so the text is never sent to a browser.
 *
 * @param string $message Message to print.
 */
function updatebee_cli_line( $message ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Build script that writes to the terminal.
	fwrite( STDOUT, $message . "\n" );
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

/**
 * Add the ABSPATH guard to every build/*.asset.php file.
 *
 * @return int Process exit code. 0 on success, 1 when a file could not be guarded.
 */
function updatebee_add_asset_guards() {
	$build_dir = __DIR__ . '/../build';
	$files     = glob( $build_dir . '/*.asset.php' );

	if ( false === $files || array() === $files ) {
		updatebee_cli_line( "No asset PHP files found in {$build_dir}" );
		return 0;
	}

	$updated = 0;
	$skipped = 0;
	$failed  = 0;

	foreach ( $files as $file ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a local build file; WordPress is not loaded.
		$contents = file_get_contents( $file );

		if ( false === $contents ) {
			updatebee_cli_line( "Failed to read {$file}" );
			++$failed;
			continue;
		}

		$new_contents = updatebee_guard_asset_php( $contents );

		if ( false === $new_contents ) {
			updatebee_cli_line( "Skipping {$file}: expected <?php return array(...)" );
			++$failed;
			continue;
		}

		if ( $new_contents === $contents ) {
			++$skipped;
			continue;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writes a local build file; WordPress is not loaded.
		$result = file_put_contents( $file, $new_contents, LOCK_EX );

		if ( false === $result ) {
			updatebee_cli_line( "Failed to write {$file}" );
			++$failed;
			continue;
		}

		updatebee_cli_line( "Guard added to {$file}" );
		++$updated;
	}

	updatebee_cli_line( "Updated {$updated} file(s), skipped {$skipped} file(s)." );

	if ( $failed > 0 ) {
		updatebee_cli_line( "Failed {$failed} file(s)." );
		return 1;
	}

	return 0;
}

if ( 0 !== updatebee_add_asset_guards() ) {
	exit( 1 );
}
