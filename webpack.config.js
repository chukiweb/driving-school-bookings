const path = require('path');

module.exports = {
  entry: path.resolve(__dirname, './includes/pwa/assets/js/app.js'), // Asegurar ruta correcta
  output: {
    path: path.resolve(__dirname, 'pwa/assets/dist'),
    filename: 'app.bundle.js',
  },
  module: {
    rules: [
      {
        test: /\.js$/,
        exclude: /node_modules/,
        use: {
          loader: 'babel-loader',
          options: {
            presets: ['@babel/preset-env', '@babel/preset-react'],
          },
        },
      },
    ],
  },
  resolve: {
    extensions: ['.js', '.jsx'], // Asegurar que Webpack pueda importar archivos .js y .jsx
  },
  mode: 'production',
};

