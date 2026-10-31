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
- Nothing heavy loads before it's needed: maps and videos wait for a click
  or for the page to finish loading, dialogs load their code when opened,
  and no third-party script (Google Maps, Instagram, Trustpilot) comes
  with a page. Motion is CSS, and stops with reduced motion.

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
| Hero (`hive/hero`) | A page's opening, in three layouts: **Copertina** (a picture, or a video that starts after the page has loaded, behind the text), **Diviso** (the picture beside the text), **Chi siamo** (the store's story and values). Takes over golden-hive-blocks' four heroes. |
| Carosello (`hive/hero-carousel`) | Full-width slides that move sideways (CSS scroll snapping), with arrows, dots and autoplay that pauses on hover, focus, out of view and with reduced motion. Only the first picture loads with the page. |
| Invito all'azione (`hive/cta`) | A call to action: a full-width **Banner**, or **Immagine e testo**, with its button. |
| Prodotto in evidenza (`hive/product-spotlight`) | One product up front: photo, brand, name, price and the card's button (add to cart, or the sizes). With none chosen, the newest featured product that can be bought. |
| Slider categorie (`hive/category-slider`) | The store's categories with their pictures, in a row that scrolls sideways. |
| Brand in scorrimento (`hive/brand-marquee`) | The brands' logos in a band that moves by itself (CSS only), still under the mouse, for keyboard users and with reduced motion. |
| Conto alla rovescia del drop (`hive/drop-countdown`) | The countdown to a release: a product's drop (date, photo, price, link) or a date written in, with "Ricordamelo" and "Acquista ora" once it's out, even on a page cached before. |
| Calendario dei drop (`hive/drop-calendar`) | The next releases in order, with "Esce tra 3 giorni", "Ricordamelo" and "Acquista ora" once out; the last week's fill what's left. |
| Acquisti recenti (`hive/social-proof`) | "Qualcuno ha appena acquistato": a few notices per visit from real paid orders (product, city, how long ago), read after the page has loaded. Never in the cart or the checkout. |
| Popup promozionale (`hive/promo-modal`) | An offer in a dialog after a few seconds or on exit intent, once per visitor, with a coupon to copy. A link to `#its-id` opens it too. |
| Contatti (`hive/contact-info`) | The store's address, VAT number, phone, email and, if wanted, WhatsApp, with a map that loads only when the visitor opens it. |
| Vieni in negozio (`hive/store-visit`) | The shop itself: open or closed now, opening hours with today's row, directions, WhatsApp and a map loaded on demand. |
| Mappa (`hive/map-embed`) | A Google map that loads only when the visitor opens it: a lighter page and no Google cookies before. Only Google Maps embed links are accepted. |
| Badge di fiducia (`hive/trust-badges`) | A strip of guarantees for buyers: authenticity, shipping, returns, secure payment, support. |
| Garanzia di autenticità (`hive/authenticity-guarantee`) | The authenticity promise: a seal, the steps of the check and a button. |
| Legit check (`hive/legit-check`) | Real or fake: for each detail (stitching, label, sole, box) what the team checks. |
| Domande frequenti (`hive/faq-schema`) | Questions and answers that open one at a time (no script), with one FAQPage structured data block per page for Google. |
| Trustpilot (`hive/trustpilot-widget`) | The store's Trustpilot rating, stars and number of reviews, without Trustpilot's script. |
| Pulsante WhatsApp (`hive/whatsapp-button`) | A button fixed in the bottom corner that opens the store's WhatsApp chat with a message written; the number from Hive's settings when the block has none. |
| Pulsanti social (`hive/social-buttons`) | The store's social profiles in a column of buttons fixed on the side of the screen. |
| Instagram (`hive/instagram-feed`) | A grid of Instagram photos, each linking to its post, and the button to follow the store. "Acquista il look" tags open the products in Quick View. No Instagram script. |
| Guida alle taglie (`hive/size-chart-modal`) | A button that opens a size guide (Hive → Guide alle taglie) in a dialog, as on the product page; without one, the US/UK/EU/CM table. A link to `#its-id` opens it too. |
| Sezione con shortcode (`hive/shortcode-wrapper`) | A section around a shortcode (a product rail, say) with eyebrow, title and button: the sections the Store Hub's Vetrina edits and switches off. |
| Cerca (`hive/search`) | A search field, button or icon opening the live search: products as you type (SKU matches first, Relevanssi when it's there), recent searches kept in the browser, popular searches, "Vedi tutti i risultati (N)". |
| Newsletter (`hive/newsletter`) | The newsletter signup, with the same settings as golden-hive-blocks': optionally up to three sizes and some brands for the size alerts, confirmed by email. Subscribers go to Hive → Iscritti; `hive_newsletter_subscribed` passes them to an email service. |
| Nella tua taglia (`hive/size-rail`) | A rail of what's in stock in the visitor's size, remembered in the browser from the last size picked on a product page. No size yet: a size chooser. Nothing in that size: the size-alert form. Also `[hive_size_rail]`. |

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
| Offerta al checkout | An extra product offered at the checkout, above the payment methods ("Aggiungi anche…"), for any cart or for carts with products of some categories (Hive → Offerte al checkout). One click adds it; the next offer that fits takes its place. |
| Coordinate bancarie | "Mostra coordinate bancarie per il bonifico" at the checkout: WooCommerce's own bank account in a dialog, IBAN and BIC one tap from the clipboard. Also `[hive_bank_details]`. |
| Ricerca | The live search behind the Cerca block. The theme's own search links and buttons can open it too: give their selector to the `hive_search_trigger_selector` filter. Popular searches: `hive_search_popular`. |
| Store Hub | The Store Hub app's Vetrina edits the homepage through `wc-gh/v1`, unchanged from golden-hive-blocks (same routes, block names and answers, so the app needs no update): which product sections, their order, pinned and excluded products, texts, with the page's revisions as history. For users with `manage_woocommerce`. |

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

**Hive → Velocità**: how links are fetched ahead (WordPress's own
speculation rules, prefetch or prerender on hover, or off), files to
preload on every page or the homepage only (a hero image, a font), and
pages to prerender or prefetch. All of it is the same for every visitor,
so it holds for cached pages too.

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

The live search asks `GET /wp-json/hive/v1/search?q=…&v={n}` and "Nella
tua taglia" `GET /wp-json/hive/v1/size-rail?size=…&v={n}`: the same words or
size give the same URL for everyone, cached like product data. Newsletter
and alert signups are POSTs to WooCommerce (`?wc-ajax=hive_newsletter`,
`?wc-ajax=hive_alerts`). The Store Hub's `wc-gh/v1` needs a login and is
never cached.

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
