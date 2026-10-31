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
| Rail di prodotti (`hive/product-rail`) | A row of product cards that scrolls sideways: newest, best sellers, on sale, featured, top rated, a category, a brand, a tag or chosen products. Pinned, excluded and fallback order as the Store Hub sets them. Also `[hive_product_rail]`. |
| Orari del negozio (`hive/store-hours`) | Opening hours with "Aperto ora · chiude alle 19:30", today's row, Italian holidays and closed dates. |

## Modules

Features that hook into WooCommerce; each can be switched off in **Hive → Moduli**.

| Module | What it does |
|---|---|
| Schede prodotto | WooCommerce's product lists (shop, categories, search, related products) show Hive's cards in Hive's grid. |
| Anteprima rapida | The eye button on a card opens photos, sizes, price and add to cart in a dialog. |
| Aggiunta rapida | On a product with sizes, "Aggiungi al carrello" opens the size choice instead of the product page. |
| Pagine del negozio | The shop and its category, brand, tag and attribute pages are Hive's: hero, filters (sizes matched on stock, price, categories, every attribute, in stock only), sort order, grid and pages, inside the theme's header and footer. Filters are plain URLs, swapped in place without reloading. |
| Pagina prodotto | The product page is Hive's, inside the theme's header and footer, with what the store used CommerceKit for: photos that swipe, with thumbnails or in a grid, full screen with zoom; size buttons with their price and a sale mark (sold-out sizes still a choice); "Solo 2 disponibili" with a bar; size guides; a sticky add-to-cart bar; add to cart without leaving the page. Details as an accordion, then upsells and related products as rails. WooCommerce's variation script and product hooks keep working underneath, for payment buttons and other plugins. |
| Drop | A release date on a product (Product data → General): "In arrivo · 24 ott" on its card, a countdown and "Ricordamelo" (Google Calendar or an .ics file) on its page, not for sale until then. At the release the cached pages are emptied and an open page reloads itself. |
| Avvisi e lista d'attesa | "Avvisami" on a sold-out size or product, and the size alerts ("a pair in my size arrived"): one list of people (Hive → Iscritti), double opt-in, one email per person with everything that arrived, at most one every 12 hours for size alerts, at once for what someone waited for. |

## Settings

**Hive** in the admin menu: the accent and button colours, rounded or sharp
corners, the size attribute, the WhatsApp number, sold-out products last,
the filters as a sidebar or a panel on desktop, the product photos one at a
time or in a grid, the sticky bar, from how many pieces "Solo N disponibili"
shows, and the modules on or off.

The product page also has: "Venduto 3 volte negli ultimi 30 giorni" from
real orders, the delivery estimate (cutoff time and transit days, worked
out in the visitor's browser), "Chiedi su WhatsApp" naming the size
picked, "Condividi", "Non trovi la tua taglia?" and "Acquista con fiducia"
under the product (filter `hive_trust_items`), and the "Altre colorway"
and "Visti di recente" rails; each can be switched off in Hive → Pagina
prodotto.

**Hive → Guide alle taglie**: size guides written in the block editor (a
table, a picture, notes), each for some categories (their subcategories
too) and brands, or the default one. A product can pick its own guide, or
none, in Product data → General.

## Caching and the CDN

Pages are the same for every visitor, so the full-page cache and the CDN
can serve them. Product data for Quick View and Quick Add comes from
`GET /wp-json/hive/v1/product/{id}?v={n}`, which sends `Cache-Control` with a
long `s-maxage`: add a CDN rule that caches `/wp-json/hive/v1/` GET requests
and they never reach PHP twice. `v` changes whenever products do, so a
cached answer is never stale for long.

The product page is the same for every visitor too: the stock line and
each size's price come with it, and adding to the cart is a POST to
WooCommerce (`?wc-ajax=hive_add_to_cart`), which caches pass through.

"Visti di recente" asks `GET /wp-json/hive/v1/recent?ids=…&v={n}` (the
same products give the same URL, whoever asks) and the social proof feed
is `GET /wp-json/hive/v1/social-proof`, kept for five minutes. A drop's
calendar file is `/?hive_drop_ics={id}&ts={release}`.

Every filtered shop page is a plain URL (`?filter_taglia=42`,
`?orderby=price`), the same for every visitor: the page cache and the CDN
serve those too. The filter links carry `rel="nofollow"` so crawlers don't
wander through every combination.

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
