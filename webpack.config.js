const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

module.exports = {
	...defaultConfig,
	entry: {
		'block-settings': './src/block-settings.js',
	},
	output: {
		path: path.resolve(__dirname, 'build'),
		filename: '[name].js',
	},
};
