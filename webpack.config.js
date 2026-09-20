const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		common: path.resolve( process.cwd(), 'src/js/common.ts' ),
		dashboard: path.resolve( process.cwd(), 'src/js/react/views/Dashboard/index.tsx' ),
	},
};
