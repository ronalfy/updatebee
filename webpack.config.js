const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

/**
 * Re-apply ABSPATH guards after DependencyExtractionWebpackPlugin writes
 * build/*.asset.php. Those files are emitted as `<?php return array(...);`,
 * which wp-scripts would otherwise leave executable on a direct request.
 */
class AssetPhpAbspathGuardPlugin {
	apply( compiler ) {
		compiler.hooks.afterEmit.tap( 'AssetPhpAbspathGuardPlugin', () => {
			execFileSync(
				'php',
				[ path.resolve( __dirname, 'bin/add-asset-guards.php' ) ],
				{
					cwd: __dirname,
					stdio: 'inherit',
				}
			);
		} );
	}
}

module.exports = {
	...defaultConfig,
	plugins: [
		...( defaultConfig.plugins || [] ),
		new AssetPhpAbspathGuardPlugin(),
	],
	entry: {
		common: path.resolve( process.cwd(), 'src/js/common.ts' ),
		dashboard: path.resolve(
			process.cwd(),
			'src/js/react/views/Dashboard/index.tsx'
		),
		plugins: path.resolve(
			process.cwd(),
			'src/js/react/views/Plugins/index.tsx'
		),
		themes: path.resolve(
			process.cwd(),
			'src/js/react/views/Themes/index.tsx'
		),
		translations: path.resolve(
			process.cwd(),
			'src/js/react/views/Translations/index.tsx'
		),
		logs: path.resolve(
			process.cwd(),
			'src/js/react/views/Logs/index.tsx'
		),
		tools: path.resolve(
			process.cwd(),
			'src/js/react/views/Tools/index.tsx'
		),
		emails: path.resolve(
			process.cwd(),
			'src/js/react/views/Emails/index.tsx'
		),
		cron: path.resolve(
			process.cwd(),
			'src/js/react/views/Cron/index.tsx'
		),
		advanced: path.resolve(
			process.cwd(),
			'src/js/react/views/Advanced/index.tsx'
		),
		commands: path.resolve( process.cwd(), 'src/js/commands/index.tsx' ),
	},
};
