const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
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
		branding: path.resolve(
			process.cwd(),
			'src/js/react/views/Branding/index.tsx'
		),
	},
};
