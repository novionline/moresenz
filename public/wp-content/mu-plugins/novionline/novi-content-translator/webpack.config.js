require("webpack")
const path = require("path")
const glob = require("glob")

//import plugins
const TerserPlugin = require("terser-webpack-plugin")
const CssMinimizerPlugin = require("css-minimizer-webpack-plugin")
const {WebpackManifestPlugin} = require("webpack-manifest-plugin")
const {CleanWebpackPlugin} = require("clean-webpack-plugin")

//project setup
const PLUGIN_NAME = 'novi-content-translator'
const distDirectory = path.join(__dirname, `dist`)
const production = process.env.NODE_ENV === 'production'

//scan for JS and SCSS assets
const jsPaths = glob.sync(path.join(__dirname, `assets/js/*.js`))
const scssPaths = glob.sync(path.join(__dirname, `assets/scss/*.scss`))

const jsEntries = {}
const scssEntries = {}

if (jsPaths && jsPaths.length > 0) {
    jsPaths.forEach((jsPath) => {
        const entryName = jsPath.substring(jsPath.lastIndexOf('/') + 1).replace('.js', '')
        if (entryName) jsEntries[entryName + '.js'] = [jsPath]
    })
}

if (scssPaths && scssPaths.length > 0) {
    scssPaths.forEach((scssPath) => {
        const entryName = scssPath.substring(scssPath.lastIndexOf('/') + 1).replace('.scss', '')
        if (entryName) scssEntries[entryName + '.scss'] = [scssPath]
    })
}

module.exports = {
    target: production ? ['web', 'es5'] : 'web',

    entry: {
        ...jsEntries,
        ...scssEntries
    },


    ...production ? {} : {devtool: 'inline-source-map'},

    output: {
        filename: `[name]-[contenthash].min.js`,
        chunkFilename: `chunk-[name]-[contenthash].min.js`,
        path: distDirectory,
        publicPath: `/wp-content/mu-plugins/novionline/${PLUGIN_NAME}/dist/`
    },

    module: {
        rules: [
            {
                test: /\.js?$/,
                exclude: /node_modules/,
                use: 'babel-loader'
            },
            {
                test: /\.scss|css$/,
                exclude: /node_modules/,
                use: [
                    {
                        loader: 'file-loader',
                        options: {
                            name: `[name]-[contenthash].min.css`,
                            publicPath: '/'
                        }
                    },
                    'extract-loader',
                    {
                        loader: 'css-loader',
                        options: {
                            url: {
                                filter: url => !url.startsWith('/')
                            }
                        }
                    },
                    'postcss-loader',
                    'sass-loader'
                ]
            }
        ]
    },

    optimization: {
        nodeEnv: production ? 'production' : 'development',
        minimize: production,
        sideEffects: true, // Mark all files as having side effects
        ...production ? {
            mangleExports: 'size',
            minimizer: [
                new TerserPlugin({
                    test: /\.js?$/,
                    extractComments: false,
                    terserOptions: {
                        compress: {
                            passes: 1,
                            pure_funcs: []
                        }
                    }
                }),
                new CssMinimizerPlugin({
                    test: /\.scss|css$/,
                    minimizerOptions: {
                        preset: require.resolve('cssnano-preset-default'),
                    },
                })
            ]
        } : {},
    },

    performance: {
        hints: false,
    },

    plugins: [
        //add JSON manifest for loading files in PHP with a dynamic hash in the name
        new WebpackManifestPlugin({}),

        //clear dist folder before building
        new CleanWebpackPlugin()
    ]
}
