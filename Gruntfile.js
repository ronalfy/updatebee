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
 *
 * Tasks
 * - `grunt` or `grunt release` — production zip (Composer no-dev cycle + vendor autoload in archive).
 * - `grunt compress` — development zip only (shared runtime files, no vendor/autoload).
 * - `grunt compress-only` — composer_guard then development zip (no Composer install cycle).
 */
'use strict';

const { execSync } = require( 'child_process' );
const path = require( 'path' );

module.exports = function( grunt ) {
	const sharedZipFiles = [
		{ src: [ 'updatebee.php' ], dest: '/', filter: 'isFile' },
		{ src: [ 'readme.txt' ], dest: '/', filter: 'isFile' },
		{ src: [ 'LICENSE' ], dest: '/', filter: 'isFile' },
		{ src: [ 'includes/**' ], dest: '/' },
		{ src: [ 'assets/**' ], dest: '/' },
		{ src: [ 'build/**', '!build/**/*.map' ], dest: '/' },
		{ src: [ 'languages/**' ], dest: '/' },
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
					},
				);
			} catch ( err ) {
				grunt.fail.fatal(
					'composer install --no-dev failed. Install Composer and run from the plugin root, or use grunt compress for a dev zip without vendor/.',
				);
			}
		},
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
					},
				);
			} catch ( err ) {
				grunt.fail.fatal(
					'composer install failed. Install Composer and run from the plugin root.',
				);
			}
		},
	);

	grunt.initConfig( {
		zip: {
			production: {
				options: {
					archive: 'updatebee.zip',
				},
				files: [
					...sharedZipFiles,
					{ src: [ 'vendor/autoload.php' ], dest: '/', filter: 'isFile' },
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
		'zip:production',
		'composer-install',
	];

	grunt.registerTask( 'composer_guard', 'Add ABSPATH guards to Composer-generated files.', function() {
		const done = this.async();
		const { exec } = require( 'child_process' );
		exec( 'php bin/add-composer-guards.php', { cwd: path.resolve( __dirname ) }, function( error, stdout, stderr ) {
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
		} );
	} );

	grunt.registerTask( 'default', productionZip );
	grunt.registerTask( 'release', productionZip );
	grunt.registerTask( 'compress-only', [ 'composer_guard', 'zip:dev' ] );

	grunt.loadNpmTasks( 'grunt-contrib-compress' );
	grunt.renameTask( 'compress', 'zip' );
	grunt.registerTask( 'compress', 'Zip shared runtime files without vendor/autoload.', [ 'zip:dev' ] );
};
