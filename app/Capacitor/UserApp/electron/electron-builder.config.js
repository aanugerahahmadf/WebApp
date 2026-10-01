/** @type {import('electron-builder').Configuration} */
module.exports = {
  // Tracks `appId` in ../capacitor.config.json. Keep in sync with
  // `strings.xml` (`package_name`) on Android and PRODUCT_BUNDLE_IDENTIFIER in
  // ios/App/App.xcodeproj/project.pbxproj on iOS.
  appId: 'id.dekorasi.pengantin.user',
  productName: 'Dekorasi',
  directories: {
    output: 'dist',
    buildResources: 'assets',
  },
  files: [
    'build/**/*',
    'app/**/*',
    'generated/**/*',
    // `assets` is also the electron-builder `buildResources` directory, whose
    // contents are NOT packaged by default. Include it explicitly so the
    // splash screen (and any other runtime assets) ship in the app.
    'assets/**/*',
    'package.json',
    // Platform runtime + plugins, prepared by `capacitor-electron vendor`.
    { from: 'vendor/node_modules', to: 'node_modules' },
  ],
};
