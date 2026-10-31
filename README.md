# Hive Blocks

The blocks and WooCommerce UI of the ResellPiacenza store, written to work
with any theme and to stay fast on cached pages behind a CDN. It takes over
from golden-hive-blocks and from the CommerceKit features the store uses;
see [ROADMAP.md](ROADMAP.md).

## How it stays theme-agnostic and fast

- It only styles HTML it renders itself, so a theme change can't break it.
  Fonts and text colour come from the theme; lines, muted text and tints
  are mixed from the theme's text colour, so dark themes work too.
- A page loads only the CSS and JS of the blocks on it. Each file's URL
  carries a hash of its content, so a release only refreshes the files that
  changed and everything else stays cached.
- Server output is the same for every visitor. What depends on the visitor
  or on the time (e.g. "Aperto ora") is filled in by the browser, so cached
  pages stay correct.

## Requirements

WordPress 6.8+, WooCommerce 9.9+, PHP 8.1+.

## Install

Copy the repository into `wp-content/plugins/hive-blocks` (or upload the
ZIP from GitHub) and activate it. Settings: **Hive** in the admin menu.

## Blocks

| Block | What it does |
|---|---|
| Orari del negozio (`hive/store-hours`) | Opening hours with "Aperto ora · chiude alle 19:30", today's row, Italian holidays and closed dates. |

## Development

```bash
npm ci
```

```bash
npm run build
```

Commit the build output: the plugin runs from the repository as is, and CI
fails when it doesn't match the sources. `npm run dev` rebuilds on every
change.

```bash
npm test
```

```bash
npm run test:browser
```

```bash
npm run test:e2e
```

`npm test` runs the Node and pure PHP tests. `npm run test:browser` runs the
built blocks in Chromium without WordPress. `npm run test:e2e` starts
WordPress and WooCommerce in WordPress Playground (it needs access to
wordpress.org) and tests the theme in `HIVE_E2E_THEME`. For the WordPress
security rules:

```bash
composer install && vendor/bin/phpcs
```

### Layout

```
hive-blocks.php        plugin header, autoloader, boot
src/                   PHP (namespace Hive); src/Core is pure logic, no WordPress
blocks/<name>/         block.json, render.php, edit.js, view.js, style.css → build/
assets/base.css        design tokens and shared rules (hive-base)
assets/modules/        shared script modules (@hive/<name>)
assets/editor/         shared editor components (@hive/editor)
tools/build.mjs        esbuild build
tests/                 php, js, browser and e2e tests
```
