require("webpack")
const path = require("path")
const glob = require("glob")

//import plugins
const SpritePlugin = require("svg-sprite-loader/plugin")
const TerserPlugin = require("terser-webpack-plugin")
const CssMinimizerPlugin = require("css-minimizer-webpack-plugin")
const {WebpackManifestPlugin} = require("webpack-manifest-plugin")
const {CleanWebpackPlugin} = require("clean-webpack-plugin")

//project setup
const PLUGIN_NAME = 'wordpress-core-plugin'
const distDirectory = path.join(__dirname, `dist`)
const production = process.env.NODE_ENV === 'production'

//scan for block assets which should be ran through webpack
const scssPaths = glob.sync(path.join(__dirname, `scss/*.scss`))
const scssEntries = {}

if (scssPaths && scssPaths.length > 0) {
    scssPaths.forEach((scssPath) => {
        const entryName = scssPath.substring(scssPath.lastIndexOf('/') + 1).replace('.scss', '')
        if (entryName) scssEntries[entryName] = [scssPath]
    })
}

module.exports = {

    target: production ? ['web', 'es5'] : 'web',

    entry: {
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
                test: /\.svg$/,
                exclude: /node_modules/,
                loader: 'svg-sprite-loader',
                options: {
                    extract: true,
                    spriteFilename: `[chunkname]-[contenthash].svg`
                }
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
            },
            {
                test: /\.(gif|jpe?g|png)$/,
                exclude: /node_modules/,
                use: [
                    {
                        loader: 'file-loader',
                        options: {
                            name: '[name].[ext]',
                            publicPath: '/'
                        }
                    }
                ]
            }
        ]
    },

    optimization: {
        nodeEnv: production ? 'production' : 'development',
        minimize: production,
        ...production ? {
            mangleExports: 'size',
            minimizer: [
                new TerserPlugin({
                    test: /\.js?$/,
                    extractComments: false
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
        //extract SVG icons to one sprite per entry
        new SpritePlugin({plainSprite: true}),

        //add JSON manifest for loading files in PHP with a dynamic hash in the name
        new WebpackManifestPlugin({}),

        //clear dist folder before building
        new CleanWebpackPlugin()
    ]
}
