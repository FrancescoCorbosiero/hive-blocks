# Hive Blocks

WordPress plugin behind the ResellPiacenza WooCommerce store (sneakers and
streetwear): its blocks and its WooCommerce UI, independent of the theme and
fast on cached pages behind a CDN. It takes over from golden-hive-blocks,
the reference for the features still to port, and from the parts of
CommerceKit the store uses. `ROADMAP.md` has the steps and the decisions.

- **Style only what you render.** No selector, hook or script may depend on
  a theme's or another plugin's markup. Render your own HTML, and place it
  with blocks (block themes, Block Hooks) and standard WooCommerce hooks
  (classic themes). CI runs the e2e suite on a block theme and a classic
  one.
- **Cache-safe HTML.** Pages are cached (full-page cache + CDN), so server
  output must be the same for every visitor: nothing per user, per cart or
  per "now". Those parts run in the browser, in the block's view module,
  from data in the markup or from public GET endpoints that send
  `Cache-Control`. Never `admin-ajax.php` for a public read: caches and
  CDNs pass it through to PHP.
- **Load only what the page uses.** A block's CSS is `viewStyle` +
  `editorStyle` in block.json, never `style` (classic themes load every
  registered `style` on every page). Its JS is a `viewScriptModule`.
  `src/Assets.php` gets block CSS into `<head>` on classic themes, or right
  before a block rendered after `<head>`.
- **Blocks** live in `blocks/<name>/`: `block.json`, `render.php`,
  `edit.js` (JSX), `view.js` (module), `style.css`; `build/` is generated.
  Nest every selector under the block's root class (`.hive-hours
  .hive-hours__title`): two classes outrank a theme's content rules.
  Colours, sizes and radii come from the `--hive-*` tokens in
  `assets/base.css`; text-related colours are mixed from `currentColor`, so
  dark themes work. Radii are `calc(N * var(--hive-round))`.
- **Shared code.** Front end: `assets/modules/<name>.js` is the script
  module `@hive/<name>`. Editor: `assets/editor/` is `@hive/editor`
  (`window.hive.editor`). Pure PHP logic goes in `src/Core/` (no WordPress
  calls), pure JS next to its block (e.g. `blocks/store-hours/hours.js`);
  both get tests.
- **Modules** are features that can be switched off in Hive → Moduli: a
  class implementing `Hive\Module`, listed in `Modules::BUILT_IN`.
- **Products.** Every list of products uses `Hive\Shop\Card` (one card,
  Hive's markup, none of WooCommerce's loop hooks); rails go through
  `Hive\Shop\Rail` and `RailQuery`, whose ordering rules (`Core\RailOrder`)
  the Store Hub app mirrors: change them only together with the app.
- **Public reads** are REST GETs under `hive/v1` (see `Shop\ProductData`):
  `Cache-Control` set, and the catalog generation (`Shop\Store::generation()`,
  bumped when products change) in the URL as `?v=`, so caches can keep the
  answer long. Cart writes use WooCommerce's own `wc-ajax=add_to_cart`.
- **The shop's pages** (`Shop\Catalog`, module Pagine del negozio) are
  Hive's own template: `templates/catalog.php` on classic themes, the
  `hive/catalog` block between the theme's header and footer parts on
  block themes. Filters are WooCommerce's own URL parameters (`Core\Filters`),
  every option a link; `@hive/catalog` swaps the results in place. Product
  order on these pages is `Shop\Ordering` (sold-out last, rail pins).
- **The product page** (`Shop\ProductPage`, module Pagina prodotto) is
  Hive's own template the same way. The buying part (`Shop\BuyForm`) is
  WooCommerce's variations form underneath (its classes, data and
  `wc-add-to-cart-variation`), so payment plugins keep working: Hive's
  buttons set its selects and listen to its jQuery events. WooCommerce's
  product hooks are fired through `Shop\Hooks` (without the callbacks Hive
  replaces and without the theme's).
- **People who asked to hear from the store** (waitlist, size alerts, the
  newsletter) are `hive_subscriber` posts (`Shop\Alerts`):
  double opt-in, the email links act only on a POST, every email has the
  one-click way out. What's time-dependent on a cached page (drop
  countdown, delivery estimate, "2 ore fa") is worked out in the browser;
  a drop's release empties the page caches.
- **The Store Hub contract.** `wc-gh/v1` (`src/Hub`, rules in `Core\Hub*`)
  answers exactly as golden-hive-blocks' did: the app reads
  `golden-hive/*` names and `[gh_product_rail]`, which `Core\HubNames`
  translates both ways. Routes, fields and error codes change only together
  with the app (`tests/php/HubTest.php` pins them).
- **Interactive UI** that opens on demand (dialogs, sheets) loads on demand:
  a dynamic `import()` from the module that handles the click, and
  `@hive/dialog` brings `dialog.css` the first time a dialog opens.
- **Build.** `npm ci && npm run build` (esbuild, `tools/build.mjs`). Commit
  the output: the plugin runs from the repository as is, and CI fails when
  the build doesn't match the sources. Versions are content hashes in the
  `*.asset.php` files: no manual cache-busting, no editor version bumps.
- **Tests.** `npm test` (Node and pure PHP), `npm run test:browser` (the
  built blocks in Chromium, no WordPress), `npm run test:e2e` (WordPress +
  WooCommerce in Playground; needs wordpress.org, so it runs on GitHub
  Actions), `composer install && vendor/bin/phpcs` (WordPress security
  rules). The e2e run fails on any PHP notice or "doing it wrong" raised
  through Hive.
- **Version.** On a release, bump `Version:` and `Hive\VERSION` in
  `hive-blocks.php` together; a test checks they match.
- **Floors.** PHP 8.1 syntax, WordPress 6.8, WooCommerce 9.9.
- **Language.** Customer-facing and admin strings in Italian, code and
  comments in English.
