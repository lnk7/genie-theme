const mix = require(`laravel-mix`)
const polyfil = require(`laravel-mix-polyfill`)


// Stops mix-manifest.json from beiung created
Mix.manifest.refresh = _ => void 0

mix
.js(`src/ui/js/theme.js`, `theme.js`)
.postCss(`src/ui/css/theme.css`, `theme.css`, [
  require(`postcss-import`),
  require(`tailwindcss`)(`tailwind.config.js`),
  require(`postcss-nested`),
  require(`autoprefixer`)
])
.polyfill({
    enabled: true,
    useBuiltIns: false,
    targets: {
      // must be '' not ``
      'firefox': '50',
      'ie': 11
    }
})
.options({
  processCssUrls: false
})
.setPublicPath(`.`)
