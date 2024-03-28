const pkg = require('./package.json');
const {
	getFileLoaderOptions,
	issuerForNonStyleFiles,
	issuerForStyleFiles,
	getBabelPresets,
	getDefaultBabelPresetOptions,
	issuerForJsTsFiles,
	issuerForNonJsTsFiles,
	// eslint-disable-next-line import/no-extraneous-dependencies
} = require('@wpackio/scripts');

module.exports = {
	// Project Identity
	appName: 'cpConnect', // Unique name of your project
	type: 'plugin', // Plugin or theme
	slug: 'cp-connect', // Plugin or Theme slug, basically the directory name under `wp-content/<themes|plugins>`
	// Used to generate banners on top of compiled stuff
	bannerConfig: {
		name: 'cpConnect',
		author: 'Mission Lab',
		license: 'GPL-3.0',
		link: 'https://missionlab.dev',
		version: pkg.version,
		copyrightText:
			'This software is released under the GPL-3.0 License\nhttps://opensource.org/licenses/GPL-3.0',
		credit: true,
	},
	// Files we need to compile, and where to put
	files: [
		// If this has length === 1, then single compiler
		// {
		// 	name: 'mobile',
		// 	entry: {
		// 		// mention each non-interdependent files as entry points
		//      // The keys of the object will be used to generate filenames
		//      // The values can be string or Array of strings (string|string[])
		//      // But unlike webpack itself, it can not be anything else
		//      // <https://webpack.js.org/concepts/#entry>
		//      // You do not need to worry about file-size, because we would do
		//      // code splitting automatically. When using ES6 modules, forget
		//      // global namespace pollutions 😉
		// 		vendor: './src/mobile/vendor.js', // Could be a string
		// 		main: ['./src/mobile/index.js'], // Or an array of string (string[])
		// 	},
		// // If enabled, all WordPress provided external scripts, including React
		// // and ReactDOM are aliased automatically. Do note that all `@wordpress`
		// // namespaced imports are automatically aliased and enqueued by the
		// // PHP library. It will not change the JSX pragma because of external
		// // dependencies.
		// optimizeForGutenberg: false,
		// 	// Extra webpack config to be passed directly
		// 	webpackConfig: undefined,
		// },
		// If has more length, then multi-compiler
		// We need to punt app compiling to `app/package.json`
		{
			name         : 'styles',
			entry        : {
				main: ['./assets/scss/main.scss'],
				admin: ['./assets/scss/admin.scss'],
			},
			webpackConfig: (config, merge, appDir, isDev) => {
				const customRules = {
					module: {
						rules: [
							{
								test: /\.(png|jpg|gif|svg)$/i,
								use : [
									{
										loader : 'url-loader',
										options: {
											limit: 8192,
										}
									}
								]
							}
						]
					}
				};

				return merge(config, customRules);
			},
		},
		{
			name : 'scripts',
			entry: {
				main: ['./assets/js/main.js'],
				admin: ['./assets/js/admin.js']
			},
		}
	],

	// Output path relative to the context directory
	// We need relative path here, else, we can not map to publicPath
	outputPath: 'dist',
	// Project specific config
	// Needs react(jsx)?
	hasReact: false,
	// Whether or not to use the new jsx runtime introduced in React 17
	// this is opt-in
	// @see {https://reactjs.org/blog/2020/09/22/introducing-the-new-jsx-transform.html}
	useReactJsxRuntime: false,
	// Disable react refresh
	disableReactRefresh: false,
	// Needs sass?
	hasSass: true,
	// Needs less?
	hasLess: false,
	// Needs flowtype?
	hasFlow: false,
	// Externals
	// <https://webpack.js.org/configuration/externals/>
	externals: {
		jquery: 'jQuery',
	},
	// Webpack Aliases
	// <https://webpack.js.org/configuration/resolve/#resolve-alias>
	alias: undefined,
	// Show overlay on development
	errorOverlay: true,
	// Auto optimization by webpack
	// Split all common chunks with default config
	// <https://webpack.js.org/plugins/split-chunks-plugin/#optimization-splitchunks>
	// Won't hurt because we use PHP to automate loading
	optimizeSplitChunks: true,
	// Usually PHP and other files to watch and reload when changed
	watch: './inc|includes|templates/**/*.php',
	jsBabelOverride: defaults => ({
		...defaults,
		plugins: ['react-hot-loader/babel'],
	}),
	// Files that you want to copy to your ultimate theme/plugin package
	// Supports glob matching from minimatch
	// @link <https://github.com/isaacs/minimatch#usage>
	packageFiles: [
		'assets/images/**',
		'includes/**',
		'vendor/**',
		'dist/**',
		'*.php',
		'*.md',
		'readme.txt',
		'languages/**',
		'layouts/**',
		'templates/**',
		'LICENSE',
		'*.css',
	],
	// Path to package directory, relative to the root
	packageDirPath: 'package',
	// whether or not to disable wordpress external scripts handling
	disableWordPressExternals: false,
};
