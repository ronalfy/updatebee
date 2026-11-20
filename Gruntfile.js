/**
 * Build UpdateBee plugin zip for distribution.
 *
 * What belongs in the zip
 * - Ship: updatebee.php, readme.txt, LICENSE, includes/, assets/, build/ (no source maps), languages/.
 * - Production only: vendor/autoload.php and vendor/composer/ (PSR-4 autoload metadata).
 * - Omit: src/, tests/, composer.json, node_modules/, and other dev-only tooling.
 *
 * vendor/ and Composer
 * - `grunt` and `grunt release` run `composer install --no-dev`, add ABSPATH guards, zip with autoload, then
 *   `composer install` so local require-dev tools are restored.
 * - Every zip task runs `php bin/add-asset-guards.php` first so build/*.asset.php exits on direct access.
 *
 * Translations
 * - `grunt i18n` (run by every zip task) builds the translation catalogs from the built bundles in build/.
 *   Strings are extracted from build/*.js, not src/js, so each reference is already the path of the script that
 *   WordPress loads and no source map is needed. It writes languages/updatebee.pot, and compiles every committed
 *   languages/*.po into .mo (PHP strings) and .json (one per script, JavaScript strings). The generated files are
 *   git-ignored and only exist in the zip. Needs WP-CLI (set WP_CLI to its path if `wp` is not on PATH).
 *
 * Tasks
 * - `grunt` or `grunt release` — production zip (Composer no-dev cycle + vendor autoload in archive).
 * - `grunt compress` — development zip only (shared runtime files, no vendor/autoload). Asset PHP guards run first.
 * - `grunt compress-only` — composer_guard, asset guards, then development zip (no Composer install cycle).
 */
'use strict';

const { execSync } = require( 'child_process' );
const path = require( 'path' );

module.exports = function ( grunt ) {
	const sharedZipFiles = [
		{ src: [ 'updatebee.php' ], dest: '/', filter: 'isFile' },
		{ src: [ 'readme.txt' ], dest: '/', filter: 'isFile' },
		{ src: [ 'LICENSE' ], dest: '/', filter: 'isFile' },
		{ src: [ 'includes/**' ], dest: '/' },
		{ src: [ 'assets/**' ], dest: '/' },
		{ src: [ 'build/**', '!build/**/*.map' ], dest: '/' },
		{ src: [ 'languages/**' ], dest: '/' },
		{ src: [ 'composer.json	' ], dest: '/', filter: 'isFile' },
	];

	grunt.registerTask(
		'composer-install-no-dev',
		'Install Composer deps without require-dev before a production zip.',
		function composerInstallNoDev() {
			try {
				execSync(
					'composer install --no-dev --no-interaction --optimize-autoloader',
					{
						stdio: 'inherit',
						cwd: path.resolve( __dirname ),
					}
				);
			} catch ( err ) {
				grunt.fail.fatal(
					'composer install --no-dev failed. Install Composer and run from the plugin root, or use grunt compress for a dev zip without vendor/.'
				);
			}
		}
	);

	grunt.registerTask(
		'composer-install',
		'Restore Composer require-dev after a production zip build.',
		function composerInstall() {
			try {
				execSync(
					'composer install --no-interaction --optimize-autoloader',
					{
						stdio: 'inherit',
						cwd: path.resolve( __dirname ),
					}
				);
			} catch ( err ) {
				grunt.fail.fatal(
					'composer install failed. Install Composer and run from the plugin root.'
				);
			}
		}
	);

	grunt.initConfig( {
		zip: {
			production: {
				options: {
					archive: 'updatebee.zip',
				},
				files: [
					...sharedZipFiles,
					{
						src: [ 'vendor/autoload.php' ],
						dest: '/',
						filter: 'isFile',
					},
					{ src: [ 'vendor/composer/**' ], dest: '/' },
				],
			},
			dev: {
				options: {
					archive: 'updatebee.zip',
				},
				files: sharedZipFiles,
			},
		},
	} );

	const productionZip = [
		'composer-install-no-dev',
		'composer_guard',
		'assets_guard',
		'i18n',
		'zip:production',
		'composer-install',
	];

	grunt.registerTask(
		'composer_guard',
		'Add ABSPATH guards to Composer-generated files.',
		function () {
			const done = this.async();
			const { exec } = require( 'child_process' );
			exec(
				'php bin/add-composer-guards.php',
				{ cwd: path.resolve( __dirname ) },
				function ( error, stdout, stderr ) {
					if ( error ) {
						grunt.log.error( error );
						if ( stderr ) {
							grunt.log.error( stderr );
						}
						done( false );
						return;
					}
					grunt.log.write( stdout );
					done();
				}
			);
		}
	);

	grunt.registerTask(
		'assets_guard',
		'Add ABSPATH guards to generated build/*.asset.php files.',
		function () {
			const done = this.async();
			const { exec } = require( 'child_process' );
			exec(
				'php bin/add-asset-guards.php',
				{ cwd: path.resolve( __dirname ) },
				function ( error, stdout, stderr ) {
					if ( error ) {
						grunt.log.error( error );
						if ( stderr ) {
							grunt.log.error( stderr );
						}
						done( false );
						return;
					}
					grunt.log.write( stdout );
					done();
				}
			);
		}
	);

	grunt.registerTask(
		'i18n',
		'Build translation catalogs (.pot, .mo and per-script .json) from the built bundles.',
		function () {
			const root = path.resolve( __dirname );
			const languages = path.join( root, 'languages' );
			const wp = process.env.WP_CLI || 'wp';
			const run = ( args ) =>
				execSync( `${ wp } --skip-plugins --skip-themes ${ args }`, {
					cwd: root,
					stdio: 'inherit',
				} );

			if ( ! grunt.file.isDir( path.join( root, 'build' ) ) ) {
				grunt.fail.fatal(
					'build/ is missing. Run `npm run build` first. Strings are extracted from the built bundles.'
				);
			}

			// Remove files from earlier runs first. A locale whose .po was deleted would otherwise keep its
			// old .mo and .json files, and they would be shipped in the zip. Only generated files are removed.
			grunt.file
				.expand( [
					path.join( languages, '*.pot' ),
					path.join( languages, '*.mo' ),
					path.join( languages, '*.json' ),
				] )
				.forEach( ( file ) => grunt.file.delete( file ) );

			const poFiles = grunt.file.expand( path.join( languages, '*.po' ) );

			let hasWpCli = true;
			try {
				execSync( `${ wp } --version`, { stdio: 'ignore' } );
			} catch ( err ) {
				hasWpCli = false;
			}

			if ( ! hasWpCli ) {
				if ( poFiles.length > 0 ) {
					grunt.fail.fatal(
						'WP-CLI is required to compile languages/*.po. Install it, or set WP_CLI to its path.'
					);
				}
				grunt.log.writeln(
					'WP-CLI not found and there are no .po files, so no translation files were built.'
				);
				return;
			}

			// Template for translators. PHP strings come from the source, React strings from the built bundles.
			run(
				'i18n make-pot . languages/updatebee.pot --domain=updatebee --include=updatebee.php,includes,updater,build'
			);

			if ( poFiles.length === 0 ) {
				grunt.log.writeln( 'No languages/*.po files, so only the .pot was built.' );
				return;
			}

			// --no-purge keeps every string in the .po so make-mo still sees it.
			run( 'i18n make-mo languages' );
			run( 'i18n make-json languages --no-purge' );
		}
	);

	grunt.registerTask( 'default', productionZip );
	grunt.registerTask( 'release', productionZip );
	grunt.registerTask( 'compress-only', [
		'composer_guard',
		'assets_guard',
		'i18n',
		'zip:dev',
	] );

	grunt.loadNpmTasks( 'grunt-contrib-compress' );
	grunt.renameTask( 'compress', 'zip' );
	grunt.registerTask(
		'compress',
		'Zip shared runtime files without vendor/autoload.',
		[ 'assets_guard', 'i18n', 'zip:dev' ]
	);
};
